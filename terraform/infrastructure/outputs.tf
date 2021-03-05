output "terraform_db_ecr" {
  value = aws_ecr_repository.terraform_database.repository_url
}

output "terraform_db_task" {
  value = aws_ecs_task_definition.terraform_database_task.family
}
