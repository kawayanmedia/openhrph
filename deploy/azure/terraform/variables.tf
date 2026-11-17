variable "subscription_id" {
  type        = string
  description = "Azure subscription id."
}

variable "resource_group_name" {
  type        = string
  description = "Resource group created for the stack."
  default     = "rg-hris-production"
}

variable "location" {
  type        = string
  description = "Azure region."
  default     = "southeastasia"
}

variable "project" {
  type        = string
  description = "Short project name used in resource names."
  default     = "hris"
}

variable "environment" {
  type        = string
  description = "Deployment environment name."
  default     = "production"
}

variable "app_ref" {
  type        = string
  description = "App ref used to select the Packer image (matches the app_ref tag in the image name)."
  default     = "main"
}

variable "app_url" {
  type        = string
  description = "Optional public URL override. Leave empty to derive it from domain_name or the public IP."
  default     = ""
}

variable "image_resource_group" {
  type        = string
  description = "Resource group that holds the Packer managed image."
}

variable "image_prefix" {
  type        = string
  description = "Managed image name prefix used by Packer."
  default     = "hris"
}

variable "vm_size" {
  type        = string
  description = "Azure VM size."
  default     = "Standard_B2s"
}

variable "disk_size" {
  type        = number
  description = "OS disk size in GB."
  default     = 40
}

variable "db_disk_size" {
  type        = number
  description = "Persistent MySQL data disk size in GB."
  default     = 50
}

variable "admin_username" {
  type        = string
  description = "VM administrator username."
  default     = "azureuser"
}

variable "ssh_public_key" {
  type        = string
  description = "SSH public key for the administrator. When empty, a random password is generated."
  default     = ""
}

variable "vnet_cidr" {
  type        = string
  description = "Virtual network CIDR block."
  default     = "10.64.0.0/16"
}

variable "subnet_cidr" {
  type        = string
  description = "Application subnet CIDR block."
  default     = "10.64.1.0/24"
}

variable "ssh_allowed_cidrs" {
  type        = list(string)
  description = "CIDR blocks allowed to reach SSH. Leave empty to disable SSH."
  default     = []
}

variable "dns_label" {
  type        = string
  description = "Optional Azure public IP DNS label, for example hris-prod."
  default     = ""
}

variable "domain_name" {
  type        = string
  description = "Public hostname, for example hris.example.com. Enables HTTPS through Caddy."
  default     = ""
}

variable "dns_zone_name" {
  type        = string
  description = "Optional Azure DNS zone name. Leave empty to manage DNS elsewhere."
  default     = ""
}

variable "dns_resource_group" {
  type        = string
  description = "Resource group of the Azure DNS zone. Defaults to resource_group_name."
  default     = ""
}

variable "dns_record_name" {
  type        = string
  description = "Record name inside the DNS zone. Use @ for the zone root or the subdomain label."
  default     = "@"
}

variable "acme_email" {
  type        = string
  description = "Email address registered with Let's Encrypt when Caddy issues certificates."
  default     = ""
}

variable "storage_replication_type" {
  type        = string
  description = "Storage account replication type."
  default     = "LRS"
}

variable "key_vault_purge_protection" {
  type        = bool
  description = "Enable purge protection on Key Vault. Keep true in production."
  default     = true
}

variable "tags" {
  type        = map(string)
  description = "Additional tags applied to resources."
  default     = {}
}
