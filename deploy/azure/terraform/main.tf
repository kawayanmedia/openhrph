data "azurerm_client_config" "current" {}

data "azurerm_image" "hris" {
  name_regex          = "^${var.image_prefix}-${local.app_slug}-"
  resource_group_name = var.image_resource_group
  sort_descending     = true
}

locals {
  name     = "${var.project}-${var.environment}"
  app_slug = substr(replace(lower(var.app_ref), "/[^a-z0-9-]/", "-"), 0, 30)

  tags = merge(
    {
      project     = var.project
      environment = var.environment
      managed_by  = "terraform"
    },
    var.tags,
  )

  app_url = var.app_url != "" ? var.app_url : (
    var.domain_name != "" ? "https://${var.domain_name}" : "http://${azurerm_public_ip.app.ip_address}"
  )

  db_name     = replace(var.project, "/[^A-Za-z0-9_]/", "_")
  db_username = replace(var.project, "/[^A-Za-z0-9_]/", "_")

  session_secure   = startswith(local.app_url, "https://")
  session_domain   = var.domain_name != "" ? ".${var.domain_name}" : ""
  portal_subdomain = var.domain_name != "" ? "portal.${var.domain_name}" : ""
  portal_url       = var.domain_name != "" ? "https://${local.portal_subdomain}" : local.app_url

  portal_record_name = var.dns_record_name == "@" ? "portal" : "portal.${var.dns_record_name}"

  storage_name = substr(
    replace(lower("${var.project}${var.environment}${random_id.suffix.hex}"), "/[^a-z0-9]/", ""),
    0,
    24,
  )
}

resource "random_id" "suffix" {
  byte_length = 4
}

resource "random_password" "db" {
  length           = 32
  special          = true
  override_special = "-_=+"
}

resource "random_password" "app_key" {
  length  = 32
  special = false
}

resource "random_password" "admin" {
  length           = 28
  special          = true
  override_special = "-_=+"
  min_lower        = 2
  min_upper        = 2
  min_numeric      = 2
}

# ---------------------------------------------------------------------------
# Resource group and network
# ---------------------------------------------------------------------------

resource "azurerm_resource_group" "main" {
  name     = var.resource_group_name
  location = var.location
  tags     = local.tags
}

resource "azurerm_virtual_network" "main" {
  name                = "${local.name}-vnet"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  address_space       = [var.vnet_cidr]
  tags                = local.tags
}

resource "azurerm_subnet" "app" {
  name                 = "${local.name}-app"
  resource_group_name  = azurerm_resource_group.main.name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = [var.subnet_cidr]
}

resource "azurerm_network_security_group" "app" {
  name                = "${local.name}-nsg"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.tags

  security_rule {
    name                       = "web"
    priority                   = 100
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_ranges    = ["80", "443"]
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }
}

resource "azurerm_network_security_rule" "ssh" {
  count = length(var.ssh_allowed_cidrs) > 0 ? 1 : 0

  name                        = "ssh"
  priority                    = 110
  direction                   = "Inbound"
  access                      = "Allow"
  protocol                    = "Tcp"
  source_port_range           = "*"
  destination_port_range      = "22"
  source_address_prefixes     = var.ssh_allowed_cidrs
  destination_address_prefix  = "*"
  resource_group_name         = azurerm_resource_group.main.name
  network_security_group_name = azurerm_network_security_group.app.name
}

resource "azurerm_subnet_network_security_group_association" "app" {
  subnet_id                 = azurerm_subnet.app.id
  network_security_group_id = azurerm_network_security_group.app.id
}

resource "azurerm_public_ip" "app" {
  name                = "${local.name}-ip"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  allocation_method   = "Static"
  sku                 = "Standard"
  domain_name_label   = var.dns_label != "" ? var.dns_label : null
  tags                = local.tags
}

resource "azurerm_network_interface" "app" {
  name                = "${local.name}-nic"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.tags

  ip_configuration {
    name                          = "internal"
    subnet_id                     = azurerm_subnet.app.id
    private_ip_address_allocation = "Dynamic"
    public_ip_address_id          = azurerm_public_ip.app.id
  }
}

# ---------------------------------------------------------------------------
# Key Vault and storage
# ---------------------------------------------------------------------------

resource "azurerm_key_vault" "main" {
  name                          = trim(substr("${local.name}-${random_id.suffix.hex}", 0, 24), "-")
  location                      = azurerm_resource_group.main.location
  resource_group_name           = azurerm_resource_group.main.name
  tenant_id                     = data.azurerm_client_config.current.tenant_id
  sku_name                      = "standard"
  rbac_authorization_enabled    = true
  purge_protection_enabled      = var.key_vault_purge_protection
  soft_delete_retention_days    = 7
  public_network_access_enabled = true
  tags                          = local.tags
}

resource "azurerm_storage_account" "media" {
  name                            = local.storage_name
  resource_group_name             = azurerm_resource_group.main.name
  location                        = azurerm_resource_group.main.location
  account_tier                    = "Standard"
  account_replication_type        = var.storage_replication_type
  min_tls_version                 = "TLS1_2"
  allow_nested_items_to_be_public = false
  tags                            = local.tags

  blob_properties {
    versioning_enabled = true
  }
}

resource "azurerm_storage_container" "media" {
  name                  = "media"
  storage_account_name  = azurerm_storage_account.media.name
  container_access_type = "private"
}

# ---------------------------------------------------------------------------
# Application VM
# ---------------------------------------------------------------------------

resource "azurerm_user_assigned_identity" "app" {
  name                = "${local.name}-identity"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.tags
}

resource "azurerm_role_assignment" "vm_key_vault" {
  scope                = azurerm_key_vault.main.id
  role_definition_name = "Key Vault Secrets User"
  principal_id         = azurerm_user_assigned_identity.app.principal_id
  principal_type       = "ServicePrincipal"
}

resource "azurerm_role_assignment" "vm_storage" {
  scope                = azurerm_storage_account.media.id
  role_definition_name = "Storage Blob Data Contributor"
  principal_id         = azurerm_user_assigned_identity.app.principal_id
  principal_type       = "ServicePrincipal"
}

resource "azurerm_linux_virtual_machine" "app" {
  name                = "${local.name}-app"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  size                = var.vm_size
  admin_username      = var.admin_username

  network_interface_ids = [azurerm_network_interface.app.id]

  admin_password = var.ssh_public_key == "" ? random_password.admin.result : null

  dynamic "admin_ssh_key" {
    for_each = var.ssh_public_key != "" ? [1] : []

    content {
      username   = var.admin_username
      public_key = var.ssh_public_key
    }
  }

  source_image_id = data.azurerm_image.hris.id

  os_disk {
    caching              = "ReadWrite"
    storage_account_type = "StandardSSD_LRS"
    disk_size_gb         = var.disk_size
  }

  identity {
    type         = "UserAssigned"
    identity_ids = [azurerm_user_assigned_identity.app.id]
  }

  custom_data = base64encode(templatefile("${path.module}/templates/startup.sh.tftpl", {
    key_vault_name    = azurerm_key_vault.main.name
    secret_name       = azurerm_key_vault_secret.env.name
    storage_account   = azurerm_storage_account.media.name
    storage_container = azurerm_storage_container.media.name
    data_disk_device  = "/dev/disk/azure/scsi1/lun0"
  }))

  tags = local.tags

  depends_on = [
    azurerm_role_assignment.vm_key_vault,
    azurerm_role_assignment.vm_storage,
    azurerm_key_vault_secret.env,
  ]
}

resource "azurerm_managed_disk" "mysql" {
  name                 = "${local.name}-mysql-data"
  location             = azurerm_resource_group.main.location
  resource_group_name  = azurerm_resource_group.main.name
  storage_account_type = "StandardSSD_LRS"
  create_option        = "Empty"
  disk_size_gb         = var.db_disk_size
  tags                 = local.tags
}

resource "azurerm_virtual_machine_data_disk_attachment" "mysql" {
  managed_disk_id    = azurerm_managed_disk.mysql.id
  virtual_machine_id = azurerm_linux_virtual_machine.app.id
  lun                = 0
  caching            = "ReadWrite"
}

resource "azurerm_key_vault_secret" "env" {
  name = "hris-env"
  value = templatefile("${path.module}/templates/env.tftpl", {
    app_key           = "base64:${base64encode(random_password.app_key.result)}"
    app_url           = local.app_url
    portal_url        = local.portal_url
    portal_subdomain  = local.portal_subdomain
    session_domain    = local.session_domain
    session_secure    = local.session_secure
    acme_email        = var.acme_email
    db_name           = local.db_name
    db_username       = local.db_username
    db_password       = random_password.db.result
    storage_account   = azurerm_storage_account.media.name
    storage_container = azurerm_storage_container.media.name
    app_ref           = var.app_ref
  })
  key_vault_id = azurerm_key_vault.main.id
  content_type = "text/plain"
  tags         = local.tags
}

# ---------------------------------------------------------------------------
# Optional DNS
# ---------------------------------------------------------------------------

data "azurerm_dns_zone" "main" {
  count = var.dns_zone_name != "" ? 1 : 0

  name                = var.dns_zone_name
  resource_group_name = var.dns_resource_group != "" ? var.dns_resource_group : var.resource_group_name
}

resource "azurerm_dns_a_record" "app" {
  count = var.dns_zone_name != "" && var.domain_name != "" ? 1 : 0

  name                = var.dns_record_name
  zone_name           = data.azurerm_dns_zone.main[0].name
  resource_group_name = data.azurerm_dns_zone.main[0].resource_group_name
  ttl                 = 300
  records             = [azurerm_public_ip.app.ip_address]
}

resource "azurerm_dns_a_record" "portal" {
  count = var.dns_zone_name != "" && var.domain_name != "" ? 1 : 0

  name                = local.portal_record_name
  zone_name           = data.azurerm_dns_zone.main[0].name
  resource_group_name = data.azurerm_dns_zone.main[0].resource_group_name
  ttl                 = 300
  records             = [azurerm_public_ip.app.ip_address]
}
