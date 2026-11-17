variable "project_id" {
  type        = string
  description = "Google Cloud project id for the stack and the Packer image."
}

variable "region" {
  type        = string
  description = "Google Cloud region."
  default     = "asia-southeast1"
}

variable "zone" {
  type        = string
  description = "Google Cloud zone for the application instance."
  default     = "asia-southeast1-b"
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
  description = "App ref used to select the Packer image (matches the app_ref label)."
  default     = "main"
}

variable "app_url" {
  type        = string
  description = "Optional public URL override. Leave empty to derive it from domain_name or the static IP."
  default     = ""
}

variable "image_project" {
  type        = string
  description = "Project that holds the Packer image. Defaults to project_id."
  default     = ""
}

variable "machine_type" {
  type        = string
  description = "Compute Engine machine type."
  default     = "e2-medium"
}

variable "disk_size" {
  type        = number
  description = "Boot disk size in GB."
  default     = 40
}

variable "disk_type" {
  type        = string
  description = "Boot disk type."
  default     = "pd-balanced"
}

variable "db_disk_size" {
  type        = number
  description = "Persistent MySQL data disk size in GB."
  default     = 50
}

variable "network_cidr" {
  type        = string
  description = "VPC CIDR block."
  default     = "10.62.0.0/16"
}

variable "subnet_cidr" {
  type        = string
  description = "Application subnet CIDR block."
  default     = "10.62.1.0/24"
}

variable "ssh_allowed_cidrs" {
  type        = list(string)
  description = "CIDR blocks allowed to reach SSH. Defaults to the IAP TCP forwarding range; set to an empty list to disable SSH."
  default     = ["35.235.240.0/20"]
}

variable "domain_name" {
  type        = string
  description = "Public hostname, for example hris.example.com. Enables HTTPS through Caddy."
  default     = ""
}

variable "dns_managed_zone" {
  type        = string
  description = "Optional Cloud DNS managed zone name. Leave empty to manage DNS elsewhere."
  default     = ""
}

variable "acme_email" {
  type        = string
  description = "Email address registered with Let's Encrypt when Caddy issues certificates."
  default     = ""
}

variable "deletion_protection" {
  type        = bool
  description = "Protect the application instance from accidental deletion."
  default     = true
}

variable "bucket_force_destroy" {
  type        = bool
  description = "Allow Terraform to delete a non-empty media/backup bucket. Keep false in production."
  default     = false
}

variable "tags" {
  type        = map(string)
  description = "Additional labels applied to resources."
  default     = {}
}
