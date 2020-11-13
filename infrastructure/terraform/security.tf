resource "aws_security_group" "interface" {
  name        = "webcms-interface-sg-${local.env-suffix}"
  description = "Security group for AWS interface endpoints"

  vpc_id = local.vpc-id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Interface"
  })
}

# In some cases, the interface egress rule isn't enough to allow access. This security
# group has explicit permission to access the VPC endpoint interfaces, albeit in a somewhat
# more narrow range.
resource "aws_security_group" "interface_access" {
  name        = "webcms-interface-access-sg-${local.env-suffix}"
  description = "Security group for access to VPC endpoints"

  vpc_id = local.vpc-id

  egress {
    description = "Allow outgoing HTTP connections"

    protocol        = "tcp"
    from_port       = 80
    to_port         = 80
    security_groups = [aws_security_group.interface.id]
  }

  egress {
    description = "Allow outgoing HTTPS connections"

    protocol        = "tcp"
    from_port       = 443
    to_port         = 443
    security_groups = [aws_security_group.interface.id]
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Interface Access"
  })
}

# Allow permissive access to the VPC endpoints. Since the interface security group is only
# ever applied to AWS VPC endpoints, this is safe since the AWS APIs themselves are
# protected by IAM rules.
resource "aws_security_group_rule" "interface_vpc_ingress" {
  description = "Allows permissive access to all endpoints"

  security_group_id = aws_security_group.interface.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 0
  to_port   = 65535

  cidr_blocks = [local.vpc-cidr-block]
}

# In other cases, the rules below allow HTTP and HTTPS access in case an explicit security
# group is needed.
resource "aws_security_group_rule" "interface_access_http_ingress" {
  description = "Allows HTTP ingress from the interface access group"

  security_group_id = aws_security_group.interface.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 80
  to_port   = 80

  source_security_group_id = aws_security_group.interface_access.id
}

resource "aws_security_group_rule" "interface_access_https_ingress" {
  description = "Allow HTTPS ingress from the interface access group"

  security_group_id = aws_security_group.interface.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 443
  to_port   = 443

  source_security_group_id = aws_security_group.interface_access.id
}

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

# NB. This is only the security group for the EC2 instances in the cluster, _not_ the
# ECS tasks that will be running in containers. These servers only need enough permissions
# to communicate with the ECS API and a few other AWS services.
resource "aws_security_group" "server" {
  name        = "webcms-ec2-sg-${local.env-suffix}"
  description = "Security group for the WebCMS EC2 instances"

  vpc_id = local.vpc-id

  egress {
    description = "Allow outgoing HTTP traffic"

    protocol    = "tcp"
    from_port   = 80
    to_port     = 80
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description = "Allow outgoing HTTPS traffic"

    protocol    = "tcp"
    from_port   = 443
    to_port     = 443
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description = "Allow access to VPC endpoint services"

    protocol        = "tcp"
    from_port       = 0
    to_port         = 0
    security_groups = [aws_security_group.interface.id]
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Cluster Server"
  })
}

resource "aws_security_group_rule" "server_extra_ingress" {
  for_each = toset(var.server-security-ingress)

  description       = "Allows ingress from security scanners to the ECS instances"
  security_group_id = aws_security_group.server.id

  type      = "ingress"
  from_port = 0
  to_port   = 65535
  protocol  = "all"

  source_security_group_id = each.value
}

resource "aws_security_group" "utility" {
  name        = "webcms-utility-sg-${local.env-suffix}"
  description = "Security group for utility servers"

  vpc_id = local.vpc-id

  egress {
    description = "Allow access to VPC endpoint services"

    protocol        = "tcp"
    from_port       = 0
    to_port         = 65535
    security_groups = [aws_security_group.interface.id]
  }

  # We have to allow HTTP access to the gateway from the utility server because we install
  # the mariadb package.
  # The reason for this is that Amazon Linux 2 yum repositories are configured to use
  # the domain amazonlinux.us-east-1.amazonaws.com, which is a CNAME for the domain
  # s3.dualstack.us-east-1.amazonaws.com.
  # Over HTTP, this is perfectly acceptable. But over HTTPS, the TLS verification step
  # fails because the amazonlinux subdomain isn't in the SNI domain list.
  # Until we can find an alternate means of installing the package, we're stuck with
  # allowing unencrypted access to S3 from this host.
  egress {
    description = "Allow HTTP access to the S3 gateway"

    protocol        = "tcp"
    from_port       = 80
    to_port         = 80
    prefix_list_ids = [aws_vpc_endpoint.s3.prefix_list_id]
  }

  egress {
    description = "Allow HTTPS access to the S3 gateway"

    protocol        = "tcp"
    from_port       = 443
    to_port         = 443
    prefix_list_ids = [aws_vpc_endpoint.s3.prefix_list_id]
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

resource "aws_security_group_rule" "drupal_interface_egress" {
  description = "Allow access to VPC endpoint services"

  security_group_id = aws_security_group.drupal_task.id

  type                     = "egress"
  protocol                 = "tcp"
  from_port                = 0
  to_port                  = 0
  source_security_group_id = aws_security_group.interface.id
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
