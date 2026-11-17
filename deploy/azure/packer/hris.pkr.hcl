packer {
  required_version = ">= 1.10.0"

  required_plugins {
    azure = {
      source  = "github.com/hashicorp/azure"
      version = ">= 2.0.0"
    }
  }
}

variable "subscription_id" {
  type        = string
  description = "Azure subscription id."
}

variable "image_resource_group" {
  type        = string
  description = "Existing resource group that will hold the managed image."
}

variable "location" {
  type        = string
  description = "Azure region for the build."
  default     = "southeastasia"
}

variable "vm_size" {
  type        = string
  description = "Temporary build VM size."
  default     = "Standard_B2s"
}

variable "app_repo" {
  type        = string
  description = "Git repository cloned into the image."
  default     = "https://github.com/kawayanmedia/openhrph.git"
}

variable "app_ref" {
  type        = string
  description = "Git branch, tag, or commit to bake into the image."
  default     = "main"
}

variable "php_version" {
  type        = string
  description = "PHP version to install."
  default     = "8.4"
}

variable "image_prefix" {
  type        = string
  description = "Prefix for the managed image name."
  default     = "hris"
}

locals {
  app_slug = substr(replace(lower(var.app_ref), "/[^a-z0-9-]/", "-"), 0, 30)
  stamp    = formatdate("YYYYMMDDhhmmss", timestamp())
}

source "azure-arm" "hris" {
  use_azure_cli_auth = true
  subscription_id    = var.subscription_id

  managed_image_resource_group_name  = var.image_resource_group
  managed_image_name                 = "${var.image_prefix}-${local.app_slug}-${local.stamp}"
  managed_image_storage_account_type = "StandardSSD_LRS"

  os_type         = "Linux"
  image_publisher = "Canonical"
  image_offer     = "ubuntu-24_04-lts"
  image_sku       = "server-gen1"

  location = var.location
  vm_size  = var.vm_size

  os_disk_size_gb = 40
  ssh_username    = "packer"
  ssh_timeout     = "20m"

  azure_tags = {
    component  = "hris"
    app_ref    = local.app_slug
    managed_by = "packer"
  }
}

build {
  name    = "hris"
  sources = ["source.azure-arm.hris"]

  provisioner "file" {
    source      = "files/nginx/hris.conf"
    destination = "/tmp/hris-nginx.conf"
  }

  provisioner "file" {
    source      = "files/php-fpm/www.conf"
    destination = "/tmp/hris-fpm-pool.conf"
  }

  provisioner "file" {
    source      = "files/php-fpm/99-hris.ini"
    destination = "/tmp/99-hris.ini"
  }

  provisioner "file" {
    source      = "files/mysql/99-hris.cnf"
    destination = "/tmp/99-hris.cnf"
  }

  provisioner "file" {
    source      = "files/systemd/hris-queue.service"
    destination = "/tmp/hris-queue.service"
  }

  provisioner "file" {
    source      = "files/systemd/hris-scheduler.service"
    destination = "/tmp/hris-scheduler.service"
  }

  provisioner "file" {
    source      = "files/systemd/hris-scheduler.timer"
    destination = "/tmp/hris-scheduler.timer"
  }

  provisioner "file" {
    source      = "files/systemd/hris-reverb.service"
    destination = "/tmp/hris-reverb.service"
  }

  provisioner "file" {
    source      = "files/systemd/hris-bootstrap.service"
    destination = "/tmp/hris-bootstrap.service"
  }

  provisioner "file" {
    source      = "files/systemd/hris-mysql-data.service"
    destination = "/tmp/hris-mysql-data.service"
  }

  provisioner "file" {
    source      = "files/systemd/mysql.service.d/10-hris-data.conf"
    destination = "/tmp/10-hris-data.conf"
  }

  provisioner "file" {
    source      = "files/systemd/hris-backup.service"
    destination = "/tmp/hris-backup.service"
  }

  provisioner "file" {
    source      = "files/systemd/hris-backup.timer"
    destination = "/tmp/hris-backup.timer"
  }

  provisioner "file" {
    source      = "files/bin/hris-bootstrap"
    destination = "/tmp/hris-bootstrap"
  }

  provisioner "file" {
    source      = "files/bin/hris-mount-data"
    destination = "/tmp/hris-mount-data"
  }

  provisioner "file" {
    source      = "files/bin/hris-deploy"
    destination = "/tmp/hris-deploy"
  }

  provisioner "file" {
    source      = "files/bin/hris-backup"
    destination = "/tmp/hris-backup"
  }

  provisioner "shell" {
    environment_vars = [
      "APP_REPO=${var.app_repo}",
      "APP_REF=${var.app_ref}",
      "PHP_VERSION=${var.php_version}",
    ]

    scripts = [
      "scripts/01-base.sh",
      "scripts/02-php.sh",
      "scripts/03-node-go.sh",
      "scripts/04-app.sh",
      "scripts/05-services.sh",
      "scripts/06-cleanup.sh",
    ]

    execute_command = "chmod +x {{ .Path }}; env {{ .Vars }} sudo -E bash '{{ .Path }}'"
  }
}
