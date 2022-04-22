output "terraform_db_ecr" {
  value = aws_ecr_repository.terraform_database.repository_url
}

output "terraform_db_task" {
  value = aws_ecs_task_definition.terraform_database_task.family
}

output "terraform_db_awsvpc" {
  value = {
    awsvpcConfiguration = {
      subnets        = local.private_subnets
      securityGroups = [data.aws_ssm_parameter.terraform_database_security_group.value]
      assignPublicIp = "DISABLED"
    }
  }
}
