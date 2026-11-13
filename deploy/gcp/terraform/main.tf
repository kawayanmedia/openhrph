data "google_compute_image" "hris" {
  project     = var.image_project != "" ? var.image_project : var.project_id
  filter      = "labels.component = hris AND labels.app_ref = ${local.app_slug}"
  most_recent = true
}

locals {
  name = "${var.project}-${var.environment}"

  app_slug = substr(replace(lower(var.app_ref), "/[^a-z0-9-]/", "-"), 0, 30)

  labels = merge(
    {
      project     = lower(var.project)
      environment = lower(var.environment)
      managed_by  = "terraform"
    },
    { for key, value in var.tags : lower(key) => lower(value) },
  )

  app_url = var.app_url != "" ? var.app_url : (
    var.domain_name != "" ? "https://${var.domain_name}" : "http://${google_compute_address.app.address}"
  )

  db_name     = replace(var.project, "/[^A-Za-z0-9_]/", "_")
  db_username = replace(var.project, "/[^A-Za-z0-9_]/", "_")

  session_secure   = startswith(local.app_url, "https://")
  session_domain   = var.domain_name != "" ? ".${var.domain_name}" : ""
  portal_subdomain = var.domain_name != "" ? "portal.${var.domain_name}" : ""
  portal_url       = var.domain_name != "" ? "https://${local.portal_subdomain}" : local.app_url
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

# ---------------------------------------------------------------------------
# Network
# ---------------------------------------------------------------------------

resource "google_compute_network" "main" {
  name                    = "${local.name}-vpc"
  auto_create_subnetworks = false
  routing_mode            = "REGIONAL"
}

resource "google_compute_subnetwork" "app" {
  name          = "${local.name}-app"
  ip_cidr_range = var.subnet_cidr
  region        = var.region
  network       = google_compute_network.main.id
}

resource "google_compute_firewall" "web" {
  name    = "${local.name}-web"
  network = google_compute_network.main.name

  direction     = "INGRESS"
  source_ranges = ["0.0.0.0/0"]
  target_tags   = ["hris-app"]

  allow {
    protocol = "tcp"
    ports    = ["80", "443"]
  }
}

resource "google_compute_firewall" "ssh" {
  count = length(var.ssh_allowed_cidrs) > 0 ? 1 : 0

  name    = "${local.name}-ssh"
  network = google_compute_network.main.name

  direction     = "INGRESS"
  source_ranges = var.ssh_allowed_cidrs
  target_tags   = ["hris-app"]

  allow {
    protocol = "tcp"
    ports    = ["22"]
  }
}

resource "google_compute_address" "app" {
  name         = "${local.name}-ip"
  region       = var.region
  address_type = "EXTERNAL"
}

# ---------------------------------------------------------------------------
# IAM, storage, and secrets
# ---------------------------------------------------------------------------

resource "google_service_account" "app" {
  account_id   = substr("${local.name}-app", 0, 30)
  display_name = "HRIS application instance"
}

resource "google_project_iam_member" "logging" {
  project = var.project_id
  role    = "roles/logging.logWriter"
  member  = "serviceAccount:${google_service_account.app.email}"
}

resource "google_project_iam_member" "monitoring" {
  project = var.project_id
  role    = "roles/monitoring.metricWriter"
  member  = "serviceAccount:${google_service_account.app.email}"
}

resource "google_storage_bucket" "media" {
  name                        = "${local.name}-media-${random_id.suffix.hex}"
  location                    = var.region
  storage_class               = "STANDARD"
  uniform_bucket_level_access = true
  public_access_prevention    = "enforced"
  force_destroy               = var.bucket_force_destroy

  versioning {
    enabled = true
  }

  labels = local.labels
}

resource "google_storage_bucket_iam_member" "app" {
  bucket = google_storage_bucket.media.name
  role   = "roles/storage.objectAdmin"
  member = "serviceAccount:${google_service_account.app.email}"
}

resource "google_secret_manager_secret" "env" {
  secret_id = "${local.name}-env"

  replication {
    auto {}
  }

  labels = local.labels
}

resource "google_secret_manager_secret_iam_member" "app" {
  secret_id = google_secret_manager_secret.env.secret_id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.app.email}"
}

resource "google_secret_manager_secret_iam_member" "app_viewer" {
  secret_id = google_secret_manager_secret.env.secret_id
  role      = "roles/secretmanager.viewer"
  member    = "serviceAccount:${google_service_account.app.email}"
}

resource "google_secret_manager_secret_version" "env" {
  secret = google_secret_manager_secret.env.id

  secret_data = templatefile("${path.module}/templates/env.tftpl", {
    app_key          = "base64:${base64encode(random_password.app_key.result)}"
    app_url          = local.app_url
    portal_url       = local.portal_url
    portal_subdomain = local.portal_subdomain
    session_domain   = local.session_domain
    session_secure   = local.session_secure
    acme_email       = var.acme_email
    db_name          = local.db_name
    db_username      = local.db_username
    db_password      = random_password.db.result
    bucket           = google_storage_bucket.media.name
    app_ref          = var.app_ref
  })
}

# ---------------------------------------------------------------------------
# Application instance
# ---------------------------------------------------------------------------

resource "google_compute_disk" "mysql" {
  name = "${local.name}-mysql-data"
  type = "pd-balanced"
  zone = var.zone
  size = var.db_disk_size
}

resource "google_compute_attached_disk" "mysql" {
  disk        = google_compute_disk.mysql.id
  instance    = google_compute_instance.app.id
  device_name = "${local.name}-mysql-data"
}

resource "google_compute_instance" "app" {
  name                      = "${local.name}-app"
  machine_type              = var.machine_type
  zone                      = var.zone
  tags                      = ["hris-app"]
  labels                    = local.labels
  allow_stopping_for_update = true
  deletion_protection       = var.deletion_protection

  boot_disk {
    initialize_params {
      image = data.google_compute_image.hris.self_link
      size  = var.disk_size
      type  = var.disk_type
    }
  }

  network_interface {
    network    = google_compute_network.main.id
    subnetwork = google_compute_subnetwork.app.id

    access_config {
      nat_ip = google_compute_address.app.address
    }
  }

  service_account {
    email  = google_service_account.app.email
    scopes = ["cloud-platform"]
  }

  metadata = {
    enable-oslogin = "TRUE"
  }

  metadata_startup_script = templatefile("${path.module}/templates/startup.sh.tftpl", {
    project_id       = var.project_id
    secret_name      = google_secret_manager_secret.env.secret_id
    backup_bucket    = google_storage_bucket.media.name
    data_disk_device = "/dev/disk/by-id/google-${local.name}-mysql-data"
  })

  depends_on = [
    google_project_iam_member.logging,
    google_project_iam_member.monitoring,
    google_secret_manager_secret_iam_member.app,
    google_secret_manager_secret_iam_member.app_viewer,
    google_secret_manager_secret_version.env,
    google_storage_bucket_iam_member.app,
  ]
}

# ---------------------------------------------------------------------------
# Optional DNS
# ---------------------------------------------------------------------------

data "google_dns_managed_zone" "main" {
  count = var.dns_managed_zone != "" ? 1 : 0

  name = var.dns_managed_zone
}

resource "google_dns_record_set" "app" {
  count = var.dns_managed_zone != "" && var.domain_name != "" ? 1 : 0

  managed_zone = data.google_dns_managed_zone.main[0].name
  name         = "${var.domain_name}."
  type         = "A"
  ttl          = 300
  rrdatas      = [google_compute_address.app.address]
}

resource "google_dns_record_set" "portal" {
  count = var.dns_managed_zone != "" && var.domain_name != "" ? 1 : 0

  managed_zone = data.google_dns_managed_zone.main[0].name
  name         = "portal.${var.domain_name}."
  type         = "A"
  ttl          = 300
  rrdatas      = [google_compute_address.app.address]
}
