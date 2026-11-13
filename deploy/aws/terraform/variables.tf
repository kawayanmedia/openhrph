variable "aws_region" {
  type        = string
  description = "AWS region for the stack."
  default     = "ap-southeast-1"
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
  description = "App ref used to select the Packer-built AMI (matches the AppRef tag)."
  default     = "main"
}

variable "app_url" {
  type        = string
  description = "Optional public URL override. Leave empty to derive it from the ALB, domain, or Elastic IP."
  default     = ""
}

variable "instance_type" {
  type        = string
  description = "EC2 instance type for the application server."
  default     = "t3.medium"
}

variable "root_volume_size" {
  type        = number
  description = "Root EBS volume size in GiB."
  default     = 40
}

variable "key_name" {
  type        = string
  description = "Optional EC2 key pair for SSH. Leave empty to use Session Manager only."
  default     = ""
}

variable "ssh_allowed_cidrs" {
  type        = list(string)
  description = "CIDR blocks allowed to reach SSH. Leave empty to disable SSH."
  default     = []
}

variable "vpc_cidr" {
  type        = string
  description = "VPC CIDR block."
  default     = "10.42.0.0/16"
}

variable "public_subnet_cidrs" {
  type        = list(string)
  description = "Two public subnet CIDRs, one per availability zone."
  default     = ["10.42.0.0/24", "10.42.1.0/24"]

  validation {
    condition     = length(var.public_subnet_cidrs) == 2
    error_message = "public_subnet_cidrs must contain exactly two CIDR blocks."
  }
}

variable "database_subnet_cidrs" {
  type        = list(string)
  description = "Two private database subnet CIDRs, one per availability zone."
  default     = ["10.42.10.0/24", "10.42.11.0/24"]

  validation {
    condition     = length(var.database_subnet_cidrs) == 2
    error_message = "database_subnet_cidrs must contain exactly two CIDR blocks."
  }
}

variable "db_instance_class" {
  type        = string
  description = "RDS instance class."
  default     = "db.t3.small"
}

variable "db_allocated_storage" {
  type        = number
  description = "RDS allocated storage in GiB."
  default     = 50
}

variable "db_max_allocated_storage" {
  type        = number
  description = "RDS storage autoscaling ceiling in GiB. Set to 0 to disable autoscaling."
  default     = 200
}

variable "db_name" {
  type        = string
  description = "MySQL database name."
  default     = "hris"
}

variable "db_username" {
  type        = string
  description = "MySQL master username."
  default     = "hris"
}

variable "db_backup_retention_days" {
  type        = number
  description = "RDS automated backup retention in days."
  default     = 7
}

variable "db_multi_az" {
  type        = bool
  description = "Enable a Multi-AZ RDS deployment."
  default     = false
}

variable "deletion_protection" {
  type        = bool
  description = "Protect the database from accidental deletion."
  default     = true
}

variable "skip_final_snapshot" {
  type        = bool
  description = "Skip the final RDS snapshot on destroy. Only use for disposable stacks."
  default     = false
}

variable "s3_force_destroy" {
  type        = bool
  description = "Allow Terraform to delete a non-empty media bucket. Keep false in production."
  default     = false
}

variable "enable_alb" {
  type        = bool
  description = "Put an Application Load Balancer in front of the instance."
  default     = false
}

variable "domain_name" {
  type        = string
  description = "Public hostname, for example hris.example.com. Required for Route 53 records and HTTPS."
  default     = ""
}

variable "route53_zone_id" {
  type        = string
  description = "Route 53 hosted zone id for domain_name. Leave empty to manage DNS elsewhere."
  default     = ""
}

variable "acm_certificate_arn" {
  type        = string
  description = "ACM certificate ARN covering domain_name and *.domain_name. Required for the HTTPS listener."
  default     = ""
}

variable "tags" {
  type        = map(string)
  description = "Additional tags applied to every resource."
  default     = {}
}
