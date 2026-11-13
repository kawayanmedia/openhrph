output "app_url" {
  description = "Public URL of the application."
  value       = local.app_url
}

output "public_ip" {
  description = "Static external IP attached to the application instance."
  value       = google_compute_address.app.address
}

output "instance_name" {
  description = "Application Compute Engine instance name."
  value       = google_compute_instance.app.name
}

output "image" {
  description = "Packer image used by the instance."
  value       = data.google_compute_image.hris.self_link
}

output "media_bucket" {
  description = "Cloud Storage bucket used for database and uploaded-file backups."
  value       = google_storage_bucket.media.name
}

output "env_secret" {
  description = "Secret Manager secret that holds the application environment."
  value       = google_secret_manager_secret.env.secret_id
}

output "ssh_command" {
  description = "Open a shell through IAP and OS Login."
  value       = "gcloud compute ssh ${google_compute_instance.app.name} --zone=${var.zone} --tunnel-through-iap --project=${var.project_id}"
}

output "create_admin_command" {
  description = "Command to run inside the instance after the stack is healthy."
  value       = "sudo -u www-data php /var/www/hris/artisan admin:create"
}

output "backup_command" {
  description = "Run an on-demand database and file backup."
  value       = "sudo /usr/local/bin/hris-backup"
}
