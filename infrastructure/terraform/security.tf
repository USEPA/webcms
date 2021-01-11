resource "aws_security_group" "load_balancer" {
  name        = "webcms-alb-sg-${local.env-suffix}"
  description = "Security group for the WebCMS load balancers"

  vpc_id = local.vpc-id

  # We allow port 80 in order to perform HTTP -> HTTPS redirection here instead of at the
  # app level.
  ingress {
    description = "Allow incoming HTTP traffic"

    protocol    = "tcp"
    from_port   = 80
    to_port     = 80
    cidr_blocks = var.alb-ingress
  }

  ingress {
    description = "Allow incoming HTTPS traffic"

    protocol    = "tcp"
    from_port   = 443
    to_port     = 443
    cidr_blocks = var.alb-ingress
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Load Balancer"
  })
}

resource "aws_security_group" "utility" {
  name        = "webcms-utility-sg-${local.env-suffix}"
  description = "Security group for utility servers"

  vpc_id = local.vpc-id

  # Allow HTTP/HTTPS access
  egress {
    description = "Allow outbound HTTP access"

    protocol    = "tcp"
    from_port   = 80
    to_port     = 80
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description = "Allow outbound HTTPS access"

    protocol    = "tcp"
    from_port   = 443
    to_port     = 443
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Utility"
  })
}

resource "aws_security_group_rule" "utility_extra_ingress" {
  for_each = toset(var.server-security-ingress)

  description       = "Allows ingress from security scanners to the utility server"
  security_group_id = aws_security_group.utility.id

  type      = "ingress"
  from_port = 0
  to_port   = 65535
  protocol  = "all"

  source_security_group_id = each.value
}

resource "aws_security_group" "database" {
  name        = "webcms-database-sg-${local.env-suffix}"
  description = "Security group for the RDS database"

  vpc_id = local.vpc-id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} RDS"
  })
}

# The security group for access to the DB servers is separate from the application-specific
# security group since it's used twice: once for Drupal tasks and again for the utility
# server. We also anticipate that it will help triage networking issues
resource "aws_security_group" "database_access" {
  name        = "webcms-database-access-sg-${local.env-suffix}"
  description = "Security group for access to database servers"

  vpc_id = local.vpc-id

  egress {
    description = "Allows outgoing connections to MySQL"

    protocol        = "tcp"
    from_port       = 3306
    to_port         = 3306
    security_groups = [aws_security_group.database.id]
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} DB Access"
  })
}

# Created as a separate rule to avoid cycles in the Terraform graph
resource "aws_security_group_rule" "database_access_ingress" {
  description = "Allows incoming connections to MySQL"

  security_group_id = aws_security_group.database.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.database_access.id
}

# The above notes with regards to the database and database_access security groups applies
# to the proxy and proxy_access security groups as well.
resource "aws_security_group" "proxy" {
  name        = "webcms-proxy-sg-${local.env-suffix}"
  description = "Security group for RDS proxies"

  vpc_id = local.vpc-id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Proxy"
  })
}

resource "aws_security_group" "proxy_access" {
  name        = "webcms-proxy-access-sg-${local.env-suffix}"
  description = "Security group for access to RDS proxies"

  vpc_id = local.vpc-id

  egress {
    description = "Allow outgoing connections to MySQL proxies"

    protocol        = "tcp"
    from_port       = 3306
    to_port         = 3306
    security_groups = [aws_security_group.proxy.id]
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Proxy Access"
  })
}

resource "aws_security_group_rule" "proxy_access_ingress" {
  description = "Allows incoming connections to MySQL proxies"

  security_group_id = aws_security_group.proxy.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.proxy_access.id
}

resource "aws_security_group" "database_proxy_access" {
  name        = "webcms-database-and-access-sg-${local.env-suffix}"
  description = "Security group for access to database servers and RDS proxies"

  vpc_id = local.vpc-id

  egress {
    description = "Allows outgoing connections to MySQL"

    protocol        = "tcp"
    from_port       = 3306
    to_port         = 3306
    security_groups = [aws_security_group.database.id, aws_security_group.proxy.id]
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} DB/Proxy Access"
  })
}

# Created as a separate rule to avoid cycles in the Terraform graph
resource "aws_security_group_rule" "database_proxy_access_database_ingress" {
  description = "Allows incoming connections to MySQL"

  security_group_id = aws_security_group.database.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.database_proxy_access.id
}

resource "aws_security_group_rule" "database_proxy_access_proxy_ingress" {
  description = "Allows incoming connections to MySQL"

  security_group_id = aws_security_group.proxy.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 3306
  to_port   = 3306

  source_security_group_id = aws_security_group.database_proxy_access.id
}

# Because Drupal tasks are run in the AWSVPC networking mode, we are able to assign
# custom security groups to the container - this enables us to grant database access
# to Drupal while denying it at the EC2 instance level.
resource "aws_security_group" "drupal_task" {
  name        = "webcms-drupal-sg-${local.env-suffix}"
  description = "Security group for the WebCMS Drupal container tasks"

  vpc_id = local.vpc-id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Drupal Containers"
  })
}

resource "aws_security_group_rule" "drupal_http_egress" {
  description = "Allow outgoing HTTP traffic"

  security_group_id = aws_security_group.drupal_task.id

  type        = "egress"
  protocol    = "tcp"
  from_port   = 80
  to_port     = 80
  cidr_blocks = ["0.0.0.0/0"]
}

resource "aws_security_group_rule" "drupal_https_egress" {
  description = "Allow outgoing HTTPS traffic"

  security_group_id = aws_security_group.drupal_task.id

  type        = "egress"
  protocol    = "tcp"
  from_port   = 443
  to_port     = 443
  cidr_blocks = ["0.0.0.0/0"]
}

resource "aws_security_group_rule" "drupal_smtp_egress" {
  description = "Allow access to SMTP servers for email"

  security_group_id = aws_security_group.drupal_task.id

  type        = "egress"
  protocol    = "tcp"
  from_port   = 587
  to_port     = 587
  cidr_blocks = ["0.0.0.0/0"]
}

# Rule: egress from load balancers to Drupal
resource "aws_security_group_rule" "lb_drupal_egress" {
  description = "Allow outgoing connections from ALBs to Drupal tasks"

  security_group_id = aws_security_group.load_balancer.id

  type                     = "egress"
  protocol                 = "tcp"
  from_port                = 80
  to_port                  = 80
  source_security_group_id = aws_security_group.drupal_task.id
}

# Rule: ingress to Drupal from load balancers
# This is the reverse of the above rule
resource "aws_security_group_rule" "drupal_lb_ingress" {
  description = "Allow incoming connections from ALBs to Drupal tasks"

  security_group_id = aws_security_group.drupal_task.id

  type                     = "ingress"
  protocol                 = "tcp"
  from_port                = 80
  to_port                  = 80
  source_security_group_id = aws_security_group.load_balancer.id
}

resource "aws_security_group_rule" "lb_drupal_ping_egress" {
  description = "Allow outgoing connections from ALBs to the PHP-FPM /ping endpoint"

  security_group_id = aws_security_group.load_balancer.id

  type                     = "egress"
  protocol                 = "tcp"
  from_port                = 8080
  to_port                  = 8080
  source_security_group_id = aws_security_group.drupal_task.id
}

resource "aws_security_group_rule" "drupal_lb_ping_ingress" {
  description = "Allow incoming connections from ALBs to the PHP-FPM /ping endpoint"

  security_group_id = aws_security_group.drupal_task.id

  type                     = "ingress"
  protocol                 = "tcp"
  from_port                = 8080
  to_port                  = 8080
  source_security_group_id = aws_security_group.load_balancer.id
}

resource "aws_security_group" "cache" {
  name        = "webcms-cache-sg-${local.env-suffix}"
  description = "Security group for ElastiCache servers"

  vpc_id = local.vpc-id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} ElastiCache"
  })
}

resource "aws_security_group" "cache_access" {
  name        = "webcms-cache-access-sg-${local.env-suffix}"
  description = "Security group for access to ElastiCache"

  vpc_id = local.vpc-id

  egress {
    description = "Allow outgoing connections to ElastiCache"

    protocol        = "tcp"
    from_port       = 11211
    to_port         = 11211
    security_groups = [aws_security_group.cache.id]
  }
}

resource "aws_security_group_rule" "cache_access_ingress" {
  description = "Allow incoming connections to ElastiCache"

  security_group_id = aws_security_group.cache.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 11211
  to_port   = 11211

  source_security_group_id = aws_security_group.cache_access.id
}

resource "aws_security_group" "search" {
  name        = "webcms-search-sg-${local.env-suffix}"
  description = "Security group for search servers"

  vpc_id = local.vpc-id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Elasticsearch"
  })
}

resource "aws_security_group" "search_access" {
  name        = "webcms-search-access-sg-${local.env-suffix}"
  description = "Security group for access to search servers"

  vpc_id = local.vpc-id

  egress {
    description = "Allow access to Elasticsearch"

    protocol        = "tcp"
    from_port       = 443
    to_port         = 443
    security_groups = [aws_security_group.search.id]
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Elasticsearch Access"
  })
}

resource "aws_security_group_rule" "search_access_ingress" {
  description = "Allows ingress from the search access group"

  security_group_id = aws_security_group.search.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  source_security_group_id = aws_security_group.search_access.id
}

# Security group for automated EC2 instances. They need arbitrary outbound HTTP in order
# to be able to access AWS APIs that we have not yet provided VPC interfaces for.
resource "aws_security_group" "automation" {
  name        = "webcms-automation-${local.env-suffix}"
  description = "Security group for automated EC2 instances"

  vpc_id = local.vpc-id

  egress {
    description = "Allow outbound HTTP"

    protocol    = "tcp"
    from_port   = 80
    to_port     = 80
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description = "Allow outbound HTTPS"

    protocol    = "tcp"
    from_port   = 443
    to_port     = 443
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Automated Instances"
  })
}
