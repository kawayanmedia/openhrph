output "app_url" {
  description = "Public URL of the application."
  value       = local.app_url
}

output "public_ip" {
  description = "Static public IP attached to the VM."
  value       = azurerm_public_ip.app.ip_address
}

output "vm_name" {
  description = "Application virtual machine name."
  value       = azurerm_linux_virtual_machine.app.name
}

output "image_id" {
  description = "Packer managed image used by the VM."
  value       = data.azurerm_image.hris.id
}

output "storage_account" {
  description = "Storage account used for database and uploaded-file backups."
  value       = azurerm_storage_account.media.name
}

output "storage_container" {
  description = "Blob container used for database and uploaded-file backups."
  value       = azurerm_storage_container.media.name
}

output "key_vault" {
  description = "Key Vault that holds the application environment secret."
  value       = azurerm_key_vault.main.name
}

output "env_secret" {
  description = "Key Vault secret name that holds the application environment."
  value       = azurerm_key_vault_secret.env.name
}

output "ssh_command" {
  description = "SSH command. Use the configured key, or the generated password below."
  value       = "ssh ${var.admin_username}@${azurerm_public_ip.app.ip_address}"
}

output "vm_admin_password" {
  description = "Generated administrator password when no SSH public key was supplied."
  value       = var.ssh_public_key == "" ? random_password.admin.result : null
  sensitive   = true
}

output "create_admin_command" {
  description = "Command to run after the stack is healthy. It does not require SSH."
  value       = "az vm run-command invoke --resource-group ${azurerm_resource_group.main.name} --name ${azurerm_linux_virtual_machine.app.name} --command-id RunShellScript --scripts \"sudo -u www-data php /var/www/hris/artisan admin:create\""
}

output "backup_command" {
  description = "Run an on-demand database and file backup on the VM."
  value       = "az vm run-command invoke --resource-group ${azurerm_resource_group.main.name} --name ${azurerm_linux_virtual_machine.app.name} --command-id RunShellScript --scripts \"sudo /usr/local/bin/hris-backup\""
}
