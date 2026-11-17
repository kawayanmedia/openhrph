output "app_url" {
  description = "Public URL of the application."
  value       = local.app_url
}

output "public_ip" {
  description = "Elastic IP attached to the application instance."
  value       = aws_eip.app.public_ip
}

output "instance_id" {
  description = "Application EC2 instance id."
  value       = aws_instance.app.id
}

output "ami_id" {
  description = "AMI used by the application instance."
  value       = data.aws_ami.hris.id
}

output "db_endpoint" {
  description = "RDS MySQL endpoint."
  value       = aws_db_instance.main.endpoint
}

output "db_name" {
  description = "RDS database name."
  value       = aws_db_instance.main.db_name
}

output "media_bucket" {
  description = "S3 bucket used for media and storage backups."
  value       = aws_s3_bucket.media.bucket
}

output "ssm_prefix" {
  description = "SSM parameter prefix that holds the application environment."
  value       = "/${local.name}"
}

output "alb_dns_name" {
  description = "ALB DNS name, when enabled."
  value       = var.enable_alb ? aws_lb.app[0].dns_name : null
}

output "ssh_command" {
  description = "SSH command when a key pair and SSH CIDR are configured."
  value       = var.key_name != "" && length(var.ssh_allowed_cidrs) > 0 ? "ssh -i <your-key.pem> ubuntu@${aws_eip.app.public_ip}" : null
}

output "ssm_session_command" {
  description = "Open a shell through Session Manager."
  value       = "aws ssm start-session --target ${aws_instance.app.id} --region ${var.aws_region}"
}

output "create_admin_command" {
  description = "Command to run inside the instance after the stack is healthy."
  value       = "sudo -u www-data php /var/www/hris/artisan admin:create"
}
