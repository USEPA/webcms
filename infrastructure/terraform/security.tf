resource "aws_security_group" "interface" {
  name        = "webcms-interface-sg"
  description = "Security group for AWS interface endpoints"

  vpc_id = aws_vpc.main.id

  # Permissively allow ingress to VPC interface endpoints.
  # We allow this for a few reasons:
  # 1. Interface endpoints resolve to AWS services, which we consider trustworthy
  # 2. The service on the other end has its own permissions system (IAM) to prevent
  #    unauthorized access.
  # 3. Security group rules here will not actually prevent access to the AWS services
  #    in question; anyone can resolve the service endpoint using public DNS and make
  #    API requests.
  ingress {
    description = "Allow incoming connections"

    protocol    = "tcp"
    from_port   = 0
    to_port     = 0
    cidr_blocks = [aws_vpc.main.cidr_block]
  }

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Interfaces"
  }
}

resource "aws_security_group" "load_balancer" {
  name        = "webcms-alb-sg"
  description = "Security group for the WebCMS load balancers"

  vpc_id = aws_vpc.main.id

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

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Load Balancer"
  }
}

# NB. This is only the security group for the EC2 instances in the cluster, _not_ the
# ECS tasks that will be running in containers. These servers only need enough permissions
# to communicate with the ECS API and a few other AWS services.
resource "aws_security_group" "server" {
  name        = "webcms-ec2-sg"
  description = "Security group for the WebCMS EC2 instances"

  vpc_id = aws_vpc.main.id

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

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Cluster Server"
  }
}

resource "aws_security_group" "bastion" {
  name        = "webcms-bastion-sg"
  description = "Security group for SSH bastions"

  vpc_id = aws_vpc.main.id

  egress {
    description = "Allow access to VPC endpoint services"

    protocol        = "tcp"
    from_port       = 0
    to_port         = 0
    security_groups = [aws_security_group.interface.id]
  }

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Bastion"
  }
}

resource "aws_security_group" "database" {
  name        = "webcms-database-sg"
  description = "Security group for the RDS database"

  vpc_id = aws_vpc.main.id

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS RDS"
  }
}

# The security group for access to the DB servers is separate from the application-specific
# security group since it's used twice: once for Drupal tasks and again for the utility
# server. We also anticipate that it will help triage networking issues
resource "aws_security_group" "database_access" {
  name        = "webcms-database-access-sg"
  description = "Security group for access to database servers"

  vpc_id = aws_vpc.main.id

  egress {
    description = "Allows outgoing connections to MySQL"

    protocol        = "tcp"
    from_port       = 3306
    to_port         = 3306
    security_groups = [aws_security_group.database.id]
  }

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS DB Access"
  }
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

# Because Drupal tasks are run in the AWSVPC networking mode, we are able to assign
# custom security groups to the container - this enables us to grant database access
# to Drupal while denying it at the EC2 instance level.
resource "aws_security_group" "drupal_task" {
  name        = "webcms-drupal-sg"
  description = "Security group for the WebCMS Drupal container tasks"

  vpc_id = aws_vpc.main.id

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

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Drupal Containers"
  }
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

resource "aws_security_group" "cache" {
  name        = "webcms-cache-sg"
  description = "Security group for ElastiCache servers"

  vpc_id = aws_vpc.main.id

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS ElastiCache"
  }
}

resource "aws_security_group" "cache_access" {
  name        = "webcms-cache-access-sg"
  description = "Security group for access to ElastiCache"

  vpc_id = aws_vpc.main.id

  egress {
    description = "Allow outgoing connections to ElastiCache"

    protocol        = "tcp"
    from_port       = 6379
    to_port         = 6379
    security_groups = [aws_security_group.cache.id]
  }
}

resource "aws_security_group_rule" "cache_access_ingress" {
  description = "Allow incoming connections to ElastiCache"

  security_group_id = aws_security_group.cache.id

  type      = "ingress"
  protocol  = "tcp"
  from_port = 6379
  to_port   = 6379

  source_security_group_id = aws_security_group.cache_access.id
}
