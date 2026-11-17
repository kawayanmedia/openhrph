packer {
  required_version = ">= 1.10.0"

  required_plugins {
    amazon = {
      source  = "github.com/hashicorp/amazon"
      version = ">= 1.3.0"
    }
  }
}

variable "aws_region" {
  type        = string
  description = "AWS region to build the AMI in."
  default     = "ap-southeast-1"
}

variable "instance_type" {
  type        = string
  description = "Temporary build instance type."
  default     = "t3.medium"
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
  description = "PHP version to install. Ubuntu 24.04 ships 8.3."
  default     = "8.3"
}

variable "ami_name_prefix" {
  type        = string
  description = "Prefix for the resulting AMI name."
  default     = "hris"
}

source "amazon-ebs" "hris" {
  region        = var.aws_region
  instance_type = var.instance_type
  ssh_username  = "ubuntu"

  ami_name = "${var.ami_name_prefix}-${var.app_ref}-${formatdate("YYYYMMDDhhmmss", timestamp())}"

  source_ami_filter {
    filters = {
      name                = "ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-amd64-server-*"
      root-device-type    = "ebs"
      virtualization-type = "hvm"
    }
    owners      = ["099720109477"] # Canonical
    most_recent = true
  }

  associate_public_ip_address = true

  tags = {
    Name       = "${var.ami_name_prefix}-${var.app_ref}"
    Component  = "hris"
    AppRepo    = var.app_repo
    AppRef     = var.app_ref
    PHPVersion = var.php_version
    BuildTime  = timestamp()
  }
}

build {
  name    = "hris"
  sources = ["source.amazon-ebs.hris"]

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
