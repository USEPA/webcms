# Export resources from the network environment into Parameter Store so that the
# infrastructure build can pick up on them.

#region VPC

resource "aws_ssm_parameter" "vpc_id" {
  name  = "/webcms/${var.environment}/vpc/id"
  type  = "String"
  value = aws_vpc.vpc.id

  tags = var.tags
}

resource "aws_ssm_parameter" "public_subnets" {
  name  = "/webcms/${var.environment}/vpc/public-subnets"
  type  = "StringList"
  value = join(",", aws_subnet.public[*].id)

  tags = var.tags
}

resource "aws_ssm_parameter" "public_cidrs" {
  name  = "/webcms/${var.environment}/vpc/public-cidrs"
  type  = "StringList"
  value = join(",", aws_subnet.public[*].cidr_block)

  tags = var.tags
}

resource "aws_ssm_parameter" "private_subnets" {
  name  = "/webcms/${var.environment}/vpc/private-subnets"
  type  = "StringList"
  value = join(",", aws_subnet.private[*].id)

  tags = var.tags
}

resource "aws_ssm_parameter" "private_cidrs" {
  name  = "/webcms/${var.environment}/vpc/private-cidrs"
  type  = "StringList"
  value = join(",", aws_subnet.private[*].cidr_block)

  tags = var.tags
}

#endregion

#region Security groups

resource "aws_ssm_parameter" "database_security_group" {
  name  = "/webcms/${var.environment}/security-groups/database"
  type  = "String"
  value = aws_security_group.database.id

  tags = var.tags
}

resource "aws_ssm_parameter" "proxy_security_group" {
  name  = "/webcms/${var.environment}/security-groups/proxy"
  type  = "String"
  value = aws_security_group.proxy.id

  tags = var.tags
}

resource "aws_ssm_parameter" "elasticsearch_security_group" {
  name  = "/webcms/${var.environment}/security-groups/elasticsearch"
  type  = "String"
  value = aws_security_group.elasticsearch.id

  tags = var.tags
}

resource "aws_ssm_parameter" "memcached_security_group" {
  name  = "/webcms/${var.environment}/security-groups/memcached"
  type  = "String"
  value = aws_security_group.memcached.id

  tags = var.tags
}

resource "aws_ssm_parameter" "drupal_security_group" {
  name  = "/webcms/${var.environment}/security-groups/drupal"
  type  = "String"
  value = aws_security_group.drupal.id

  tags = var.tags
}

resource "aws_ssm_parameter" "traefik_security_group" {
  name  = "/webcms/${var.environment}/security-groups/traefik"
  type  = "String"
  value = aws_security_group.traefik.id

  tags = var.tags
}

resource "aws_ssm_parameter" "terraform_database_security_group" {
  name  = "/webcms/${var.environment}/security-groups/terraform-database"
  type  = "String"
  value = aws_security_group.terraform_database.id

  tags = var.tags
}

#endregion
