resource "aws_ecr_repository" "terraform_database" {
  name = "webcms-${var.environment}-database"
}
