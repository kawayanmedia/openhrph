data "aws_caller_identity" "current" {}

data "aws_availability_zones" "available" {
  state = "available"
}

data "aws_ami" "hris" {
  most_recent = true
  owners      = ["self"]

  filter {
    name   = "tag:Component"
    values = ["hris"]
  }

  filter {
    name   = "tag:AppRef"
    values = [var.app_ref]
  }
}

locals {
  name = "${var.project}-${var.environment}"

  tags = merge(
    {
      Project     = var.project
      Environment = var.environment
      ManagedBy   = "terraform"
    },
    var.tags,
  )

  app_url = var.app_url != "" ? var.app_url : try(
    var.enable_alb
    ? (
      var.domain_name != "" && var.acm_certificate_arn != ""
      ? "https://${var.domain_name}"
      : "http://${aws_lb.app[0].dns_name}"
    )
    : "http://${aws_eip.app.public_ip}",
    "http://${aws_eip.app.public_ip}",
  )

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

resource "aws_vpc" "main" {
  cidr_block           = var.vpc_cidr
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = merge(local.tags, { Name = "${local.name}-vpc" })
}

resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id

  tags = merge(local.tags, { Name = "${local.name}-igw" })
}

resource "aws_subnet" "public" {
  count = 2

  vpc_id                  = aws_vpc.main.id
  cidr_block              = var.public_subnet_cidrs[count.index]
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  map_public_ip_on_launch = true

  tags = merge(local.tags, { Name = "${local.name}-public-${count.index + 1}" })
}

resource "aws_subnet" "database" {
  count = 2

  vpc_id                  = aws_vpc.main.id
  cidr_block              = var.database_subnet_cidrs[count.index]
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  map_public_ip_on_launch = false

  tags = merge(local.tags, { Name = "${local.name}-database-${count.index + 1}" })
}

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.main.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }

  tags = merge(local.tags, { Name = "${local.name}-public" })
}

resource "aws_route_table_association" "public" {
  count = 2

  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

# ---------------------------------------------------------------------------
# Security groups
# ---------------------------------------------------------------------------

resource "aws_security_group" "alb" {
  count = var.enable_alb ? 1 : 0

  name_prefix = "${local.name}-alb-"
  description = "Public HTTP/HTTPS access to the HRIS load balancer"
  vpc_id      = aws_vpc.main.id

  ingress {
    description = "HTTP"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "HTTPS"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.tags, { Name = "${local.name}-alb" })
}

resource "aws_security_group" "app" {
  name_prefix = "${local.name}-app-"
  description = "HRIS application instance"
  vpc_id      = aws_vpc.main.id

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.tags, { Name = "${local.name}-app" })
}

resource "aws_security_group_rule" "app_http_world" {
  count = var.enable_alb ? 0 : 1

  type              = "ingress"
  from_port         = 80
  to_port           = 80
  protocol          = "tcp"
  cidr_blocks       = ["0.0.0.0/0"]
  security_group_id = aws_security_group.app.id
}

resource "aws_security_group_rule" "app_http_alb" {
  count = var.enable_alb ? 1 : 0

  type                     = "ingress"
  from_port                = 80
  to_port                  = 80
  protocol                 = "tcp"
  source_security_group_id = aws_security_group.alb[0].id
  security_group_id        = aws_security_group.app.id
}

resource "aws_security_group_rule" "app_ssh" {
  count = length(var.ssh_allowed_cidrs) > 0 ? 1 : 0

  type              = "ingress"
  from_port         = 22
  to_port           = 22
  protocol          = "tcp"
  cidr_blocks       = var.ssh_allowed_cidrs
  security_group_id = aws_security_group.app.id
}

resource "aws_security_group" "database" {
  name_prefix = "${local.name}-db-"
  description = "HRIS RDS access from the application"
  vpc_id      = aws_vpc.main.id

  ingress {
    description     = "MySQL from the application"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.app.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.tags, { Name = "${local.name}-db" })
}

# ---------------------------------------------------------------------------
# IAM, SSM, S3
# ---------------------------------------------------------------------------

data "aws_iam_policy_document" "instance_assume" {
  statement {
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ec2.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "instance" {
  name_prefix        = "${local.name}-app-"
  assume_role_policy = data.aws_iam_policy_document.instance_assume.json

  tags = local.tags
}

data "aws_iam_policy_document" "instance" {
  statement {
    sid = "ReadBootstrapParameters"
    actions = [
      "ssm:GetParameter",
      "ssm:GetParameters",
      "ssm:GetParametersByPath",
    ]
    resources = [
      "arn:aws:ssm:${var.aws_region}:${data.aws_caller_identity.current.account_id}:parameter/${local.name}",
      "arn:aws:ssm:${var.aws_region}:${data.aws_caller_identity.current.account_id}:parameter/${local.name}/*",
    ]
  }

  statement {
    sid       = "DecryptSecureParameters"
    actions   = ["kms:Decrypt"]
    resources = ["*"]
  }

  statement {
    sid       = "ListMediaBucket"
    actions   = ["s3:ListBucket"]
    resources = [aws_s3_bucket.media.arn]
  }

  statement {
    sid = "ReadWriteMediaObjects"
    actions = [
      "s3:GetObject",
      "s3:PutObject",
      "s3:DeleteObject",
    ]
    resources = ["${aws_s3_bucket.media.arn}/*"]
  }
}

resource "aws_iam_role_policy" "instance" {
  name   = "${local.name}-app"
  role   = aws_iam_role.instance.id
  policy = data.aws_iam_policy_document.instance.json
}

resource "aws_iam_instance_profile" "instance" {
  name_prefix = "${local.name}-app-"
  role        = aws_iam_role.instance.name
}

resource "aws_s3_bucket" "media" {
  bucket        = "${local.name}-media-${random_id.suffix.hex}"
  force_destroy = var.s3_force_destroy

  tags = merge(local.tags, { Name = "${local.name}-media" })
}

resource "aws_s3_bucket_public_access_block" "media" {
  bucket = aws_s3_bucket.media.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_server_side_encryption_configuration" "media" {
  bucket = aws_s3_bucket.media.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

resource "aws_s3_bucket_versioning" "media" {
  bucket = aws_s3_bucket.media.id

  versioning_configuration {
    status = "Enabled"
  }
}

# ---------------------------------------------------------------------------
# Database
# ---------------------------------------------------------------------------

resource "aws_db_subnet_group" "main" {
  name       = "${local.name}-db"
  subnet_ids = aws_subnet.database[*].id

  tags = local.tags
}

resource "aws_db_instance" "main" {
  identifier = "${local.name}-db"

  engine         = "mysql"
  engine_version = "8.0"
  instance_class = var.db_instance_class

  allocated_storage     = var.db_allocated_storage
  max_allocated_storage = var.db_max_allocated_storage > 0 ? var.db_max_allocated_storage : null
  storage_type          = "gp3"
  storage_encrypted     = true

  db_name  = var.db_name
  username = var.db_username
  password = random_password.db.result
  port     = 3306

  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [aws_security_group.database.id]
  publicly_accessible    = false

  multi_az                = var.db_multi_az
  backup_retention_period = var.db_backup_retention_days
  backup_window           = "18:00-19:00"
  maintenance_window      = "sun:19:00-sun:20:00"

  auto_minor_version_upgrade = true
  apply_immediately          = false
  deletion_protection        = var.deletion_protection
  skip_final_snapshot        = var.skip_final_snapshot
  final_snapshot_identifier  = var.skip_final_snapshot ? null : "${local.name}-final-${random_id.suffix.hex}"

  performance_insights_enabled = false

  tags = merge(local.tags, { Name = "${local.name}-db" })
}

resource "aws_ssm_parameter" "app_env" {
  name        = "/${local.name}/env"
  description = "Production environment file for ${local.name}"
  type        = "SecureString"
  overwrite   = true
  value = templatefile("${path.module}/templates/env.tftpl", {
    app_key          = "base64:${base64encode(random_password.app_key.result)}"
    app_url          = local.app_url
    portal_url       = local.portal_url
    session_domain   = local.session_domain
    session_secure   = local.session_secure
    portal_subdomain = local.portal_subdomain
    trusted_proxies  = var.enable_alb ? "*" : "127.0.0.1"
    db_host          = aws_db_instance.main.address
    db_port          = aws_db_instance.main.port
    db_name          = var.db_name
    db_username      = var.db_username
    db_password      = random_password.db.result
    aws_region       = var.aws_region
    bucket           = aws_s3_bucket.media.bucket
    app_ref          = var.app_ref
  })

  tags = local.tags
}

# ---------------------------------------------------------------------------
# Application instance
# ---------------------------------------------------------------------------

resource "aws_instance" "app" {
  ami                    = data.aws_ami.hris.id
  instance_type          = var.instance_type
  subnet_id              = aws_subnet.public[0].id
  vpc_security_group_ids = [aws_security_group.app.id]
  iam_instance_profile   = aws_iam_instance_profile.instance.name
  key_name               = var.key_name != "" ? var.key_name : null

  associate_public_ip_address = true
  user_data_replace_on_change = true

  user_data = templatefile("${path.module}/templates/user_data.sh.tftpl", {
    ssm_prefix = "/${local.name}"
  })

  root_block_device {
    volume_size = var.root_volume_size
    volume_type = "gp3"
    encrypted   = true
  }

  metadata_options {
    http_endpoint               = "enabled"
    http_tokens                 = "required"
    http_put_response_hop_limit = 1
  }

  tags = merge(local.tags, { Name = "${local.name}-app" })

  depends_on = [
    aws_db_instance.main,
    aws_iam_role_policy.instance,
    aws_ssm_parameter.app_env,
    aws_s3_bucket.media,
  ]
}

resource "aws_eip" "app" {
  domain = "vpc"

  tags = merge(local.tags, { Name = "${local.name}-app" })
}

resource "aws_eip_association" "app" {
  instance_id   = aws_instance.app.id
  allocation_id = aws_eip.app.id
}

# ---------------------------------------------------------------------------
# Optional load balancer and DNS
# ---------------------------------------------------------------------------

resource "aws_lb" "app" {
  count = var.enable_alb ? 1 : 0

  name               = substr("${local.name}-alb", 0, 32)
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb[0].id]
  subnets            = aws_subnet.public[*].id

  tags = merge(local.tags, { Name = "${local.name}-alb" })
}

resource "aws_lb_target_group" "app" {
  count = var.enable_alb ? 1 : 0

  name     = substr("${local.name}-tg", 0, 32)
  port     = 80
  protocol = "HTTP"
  vpc_id   = aws_vpc.main.id

  health_check {
    enabled             = true
    path                = "/up"
    matcher             = "200"
    interval            = 30
    timeout             = 5
    healthy_threshold   = 2
    unhealthy_threshold = 3
  }

  tags = local.tags
}

resource "aws_lb_target_group_attachment" "app" {
  count = var.enable_alb ? 1 : 0

  target_group_arn = aws_lb_target_group.app[0].arn
  target_id        = aws_instance.app.id
  port             = 80
}

resource "aws_lb_listener" "http" {
  count = var.enable_alb ? 1 : 0

  load_balancer_arn = aws_lb.app[0].arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type             = var.acm_certificate_arn != "" ? "redirect" : "forward"
    target_group_arn = var.acm_certificate_arn != "" ? null : aws_lb_target_group.app[0].arn

    dynamic "redirect" {
      for_each = var.acm_certificate_arn != "" ? [1] : []

      content {
        port        = "443"
        protocol    = "HTTPS"
        status_code = "HTTP_301"
      }
    }
  }
}

resource "aws_lb_listener" "https" {
  count = var.enable_alb && var.acm_certificate_arn != "" ? 1 : 0

  load_balancer_arn = aws_lb.app[0].arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = var.acm_certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.app[0].arn
  }
}

resource "aws_route53_record" "app_alb" {
  count = var.route53_zone_id != "" && var.domain_name != "" && var.enable_alb ? 1 : 0

  zone_id         = var.route53_zone_id
  name            = var.domain_name
  type            = "A"
  allow_overwrite = true

  alias {
    name                   = aws_lb.app[0].dns_name
    zone_id                = aws_lb.app[0].zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "portal_alb" {
  count = var.route53_zone_id != "" && var.domain_name != "" && var.enable_alb ? 1 : 0

  zone_id         = var.route53_zone_id
  name            = "*.${var.domain_name}"
  type            = "A"
  allow_overwrite = true

  alias {
    name                   = aws_lb.app[0].dns_name
    zone_id                = aws_lb.app[0].zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "app_eip" {
  count = var.route53_zone_id != "" && var.domain_name != "" && !var.enable_alb ? 1 : 0

  zone_id         = var.route53_zone_id
  name            = var.domain_name
  type            = "A"
  ttl             = 300
  records         = [aws_eip.app.public_ip]
  allow_overwrite = true
}

resource "aws_route53_record" "portal_eip" {
  count = var.route53_zone_id != "" && var.domain_name != "" && !var.enable_alb ? 1 : 0

  zone_id         = var.route53_zone_id
  name            = "*.${var.domain_name}"
  type            = "A"
  ttl             = 300
  records         = [aws_eip.app.public_ip]
  allow_overwrite = true
}
