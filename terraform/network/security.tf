#region Security group names

resource "aws_security_group" "database" {
  name        = "webcms-${var.environment}-database"
  description = "Security group for RDS/Aurora"

  vpc_id = aws_vpc.vpc.id

  tags = merge(var.tags, {
    Name = "WebCMS Database (${var.environment})"
  })
}

resource "aws_security_group" "proxy" {
  name        = "webcms-${var.environment}-proxy"
  description = "Security group for RDS Proxies"

  vpc_id = aws_vpc.vpc.id

  tags = merge(var.tags, {
    Name = "WebCMS RDS Proxy (${var.environment})"
  })
}

resource "aws_security_group" "elasticsearch" {
  name        = "webcms-${var.environment}-elasticsearch"
  description = "Security group for Elasticsearch"

  vpc_id = aws_vpc.vpc.id

  tags = merge(var.tags, {
    Name = "WebCMS Elasticsearch (${var.environment})"
  })
}

resource "aws_security_group" "memcached" {
  name        = "webcms-${var.environment}-memcached"
  description = "Security group for Memcached"

  vpc_id = aws_vpc.vpc.id

  tags = merge(var.tags, {
    Name = "WebCMS memcached (${var.environment})"
  })
}

resource "aws_security_group" "traefik" {
  name        = "webcms-${var.environment}-traefik"
  description = "Security group for the Traefik reverse proxy"

  vpc_id = aws_vpc.vpc.id

  tags = merge(var.tags, {
    Name = "WebCMS Traefik (${var.environment})"
  })
}

resource "aws_security_group" "drupal" {
  name        = "webcms-${var.environment}-drupal"
  description = "Security group for Drupal/Drush containers"

  vpc_id = aws_vpc.vpc.id

  tags = merge(var.tags, {
    Name = "WebCMS Drupal (${var.environment})"
  })
}

resource "aws_security_group" "terraform_database" {
  name        = "webcms-${var.environment}-terraform-database"
  description = "Security group for the Terraform database initialization task"

  vpc_id = aws_vpc.vpc.id

  tags = merge(var.tags, {
    Name = "WebCMS Terraform DB (${var.environment})"
  })
}

#endregion

#region DB

# Only two security groups are authorized to access the Aurora cluster directly:
# 1. The RDS proxy
# 2. The database initialization task
#
# All other access to the Aurora database must go through the RDS proxy. Limitations in
# Drupal prevent us from performing in-app connection pooling, so we rely on the proxy's
# ability to pool connections and mitigate transient connection issues during failover.

resource "aws_security_group_rule" "database_proxy_ingress" {
  description = "Allows RDS to receive traffic from proxies"

  security_group_id = aws_security_group.database.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.proxy.id
}

resource "aws_security_group_rule" "proxy_database_egress" {
  description = "Allows proxies to send traffic to RDS"

  security_group_id = aws_security_group.proxy.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.database.id
}

resource "aws_security_group_rule" "database_terraform_ingress" {
  description = "Allows RDS to receive traffic from Terraform tasks"

  security_group_id = aws_security_group.database.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.terraform_database.id
}

resource "aws_security_group_rule" "terraform_database_egress" {
  description = "Allows Terraform tasks to send traffic to RDS"

  security_group_id = aws_security_group.terraform_database.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.database.id
}

#endregion

#region Traefik

# Allow outbound HTTPS egress for Traefik. This is necessary in order to allow pulling
# Docker images on Fargate.
resource "aws_security_group_rule" "traefik_https_egress" {
  description = "Allows outbound HTTPS (needed to pull Docker images)"

  security_group_id = aws_security_group.traefik.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  cidr_blocks = ["0.0.0.0/0"]
}

# Allow incoming traffic from public subnets. AWS network load balancers (NLBs) do not
# support security groups, so we need to be more permissive here. In a more hardened
# environment, these could be restricted to the exact internal IPs of the NLB.
#
# We allow two ports:
# * 80, for HTTP traffic. This is unencrypted traffic from the public internet, and we use
#   it to allow nginx to upgrade to HTTPS.
# * 443, for decrypted HTTPS traffic. The NLB handles TLS termination for us, but we use
#   this port anyway for the mnemonic convention.
resource "aws_security_group_rule" "public_traefik_http_ingress" {
  description = "Allows incoming HTTP traffic from public subnets"

  security_group_id = aws_security_group.traefik.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 80
  to_port   = 80

  cidr_blocks = aws_subnet.public[*].cidr_block
}

resource "aws_security_group_rule" "public_traefik_https_ingress" {
  description = "Allows incoming HTTPS traffic from public subnets"

  security_group_id = aws_security_group.traefik.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  cidr_blocks = aws_subnet.public[*].cidr_block
}

#endregion

#region Drupal

# Allow traffic on port 443 from Traefik to Drupal
resource "aws_security_group_rule" "drupal_traefik_ingress" {
  description = "Allows Drupal to receive traffic from Traefik"

  security_group_id = aws_security_group.drupal.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  source_security_group_id = aws_security_group.traefik.id
}

resource "aws_security_group_rule" "traefik_drupal_egress" {
  description = "Allows Traefik to send traffic to Drupal"

  security_group_id = aws_security_group.traefik.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  source_security_group_id = aws_security_group.drupal.id
}

# For Drupal, we allow arbitrary outbound for three ports:
# * 80, for HTTP-based APIs
# * 443, for HTTPS-based APIs
# * 587, for SMTP (this port may differ in your environment.)
resource "aws_security_group_rule" "drupal_http_egress" {
  description = "Allows Drupal to send outbound HTTP"

  security_group_id = aws_security_group.drupal.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 80
  to_port   = 80

  cidr_blocks = ["0.0.0.0/0"]
}

resource "aws_security_group_rule" "drupal_https_egress" {
  description = "Allows Drupal to send outbound HTTPS"

  security_group_id = aws_security_group.drupal.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  cidr_blocks = ["0.0.0.0/0"]
}

resource "aws_security_group_rule" "drupal_smtp_egress" {
  description = "Allows Drupal to send outbound SMTP"

  security_group_id = aws_security_group.drupal.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 587
  to_port   = 587

  cidr_blocks = ["0.0.0.0/0"]
}

#endregion

#region Services

# Allow Drupal/Drush tasks access to the various internal services over their well-known
# port numbers.

resource "aws_security_group_rule" "proxy_drupal_ingress" {
  description = "Allows proxies to receive traffic from Drupal"

  security_group_id = aws_security_group.proxy.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.drupal.id
}

resource "aws_security_group_rule" "drupal_proxy_egress" {
  description = "Allows Drupal to send traffic to proxies"

  security_group_id = aws_security_group.drupal.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.proxy.id
}

resource "aws_security_group_rule" "elasticsearch_drupal_ingress" {
  description = "Allows Elasticsearch to receive traffic from Drupal"

  security_group_id = aws_security_group.elasticsearch.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  source_security_group_id = aws_security_group.drupal.id
}

resource "aws_security_group_rule" "drupal_elasticsearch_egress" {
  description = "Allows Drupal to send traffic to Elasticsearch"

  security_group_id = aws_security_group.drupal.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  source_security_group_id = aws_security_group.elasticsearch.id
}

resource "aws_security_group_rule" "memcached_drupal_ingress" {
  description = "Allows memcached to receive traffic from Drupal"

  security_group_id = aws_security_group.memcached.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 11211
  to_port   = 11211

  source_security_group_id = aws_security_group.drupal.id
}

resource "aws_security_group_rule" "drupal_memcached_egress" {
  description = "Allows Drupal to send traffic to memcached"

  security_group_id = aws_security_group.drupal.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 11211
  to_port   = 11211

  source_security_group_id = aws_security_group.memcached.id
}

#endregion

#region Terraform Database

resource "aws_security_group_rule" "terraform_database_https_egress" {
  description = "Allows outbound HTTPS"

  security_group_id = aws_security_group.terraform_database.id

  type      = "egress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  cidr_blocks = ["0.0.0.0/0"]
}

#endregion
