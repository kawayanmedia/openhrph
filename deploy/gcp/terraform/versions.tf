terraform {
  required_version = ">= 1.6.0"

  required_providers {
    google = {
      source  = "hashicorp/google"
      version = "~> 6.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
  }

  # Recommended for shared use: keep state in an encrypted, versioned bucket.
  #
  # backend "gcs" {
  #   bucket = "your-terraform-state-bucket"
  #   prefix = "hris/production"
  # }
}

provider "google" {
  project = var.project_id
  region  = var.region
  zone    = var.zone
}
