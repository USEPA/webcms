variable "mysql_endpoint" {
  description = "Aurora cluster hostname and port to connect to"
  type        = string
}

variable "mysql_credentials" {
  description = "Username and password of the root MySQL user"

  type = object({
    username = string
    password = string
  })
}

variable "aws_region" {
  description = "Name of the AWS region in which this container is running"
  type        = string
}

# Structure of this variable:
# {
#   "dev-en" = {
#     site = "dev"
#     lang = "en"
#     d7   = "arn:aws:..."
#     d8   = "arn:aws:..."
#   }
#   "dev-es" = { ... }
# }
variable "sites" {
  description = "Site names and languages to use during initialization"

  type = map(object({
    site = string
    lang = string
    d7   = string
    d8   = string
  }))
}
