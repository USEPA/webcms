data "aws_ssm_parameter" "vpc_id" {
  name = "/webcms/${var.environment}/vpc/id"
}

data "aws_ssm_parameter" "public_subnets" {
  name = "/webcms/${var.environment}/vpc/public-subnets"
}

data "aws_ssm_parameter" "private_subnets" {
  name = "/webcms/${var.environment}/vpc/private-subnets"
}

locals {
  public_subnets  = split(",", data.aws_ssm_parameter.public_subnets.value)
  private_subnets = split(",", data.aws_ssm_parameter.private_subnets.value)
}

data "aws_ssm_parameter" "database_security_group" {
  name = "/webcms/${var.environment}/security-groups/database"
}

data "aws_ssm_parameter" "proxy_security_group" {
  name = "/webcms/${var.environment}/security-groups/proxy"
}

data "aws_ssm_parameter" "elasticsearch_security_group" {
  name = "/webcms/${var.environment}/security-groups/elasticsearch"
}

data "aws_ssm_parameter" "memcached_security_group" {
  name = "/webcms/${var.environment}/security-groups/memcached"
}

data "aws_ssm_parameter" "drupal_security_group" {
  name = "/webcms/${var.environment}/security-groups/drupal"
}

data "aws_ssm_parameter" "traefik_security_group" {
  name = "/webcms/${var.environment}/security-groups/traefik"
}

locals {
  languages = ["en", "es"]

  sites = {
    for pair in setproduct(var.sites, local.languages) :
    "${pair[0]}-${pair[1]}" => {
      site = pair[0]
      lang = pair[1]
    }
  }
}
