packer {
  required_version = ">= 1.10.0"

  required_plugins {
    googlecompute = {
      source  = "github.com/hashicorp/googlecompute"
      version = ">= 1.1.0"
    }
  }
}

variable "gcp_project_id" {
  type        = string
  description = "Google Cloud project that owns the image."
}

variable "gcp_zone" {
  type        = string
  description = "Zone for the temporary build instance."
  default     = "asia-southeast1-b"
}

variable "machine_type" {
  type        = string
  description = "Temporary build instance type."
  default     = "e2-medium"
}

variable "disk_size" {
  type        = number
  description = "Boot disk size in GB."
  default     = 40
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
  description = "Prefix for the image name and family."
  default     = "hris"
}

variable "image_storage_locations" {
  type        = list(string)
  description = "Optional Cloud Storage locations for the image."
  default     = []
}

locals {
  app_slug = substr(replace(lower(var.app_ref), "/[^a-z0-9-]/", "-"), 0, 30)
  stamp    = formatdate("YYYYMMDDhhmmss", timestamp())
}

source "googlecompute" "hris" {
  project_id              = var.gcp_project_id
  zone                    = var.gcp_zone
  machine_type            = var.machine_type
  disk_size               = var.disk_size
  source_image_family     = "ubuntu-2404-lts-amd64"
  source_image_project_id = ["ubuntu-os-cloud"]
  ssh_username            = "packer"

  image_name   = "${var.image_prefix}-${local.app_slug}-${local.stamp}"
  image_family = "${var.image_prefix}-${local.app_slug}"

  image_storage_locations = var.image_storage_locations

  labels = {
    component  = "hris"
    app_ref    = local.app_slug
    managed_by = "packer"
  }
}

build {
  name    = "hris"
  sources = ["source.googlecompute.hris"]

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
