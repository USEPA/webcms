# AWS
# cf. providers.tf

variable "aws-region" {
  description = "AWS region to connect to"
  type        = string
}

# VPC
# cf. vpc.tf

variable "vpc-az-count" {
  description = "Number of availability zones to use when creating the VPC"
  type        = number
}

# ALB
# cf. alb.tf security.tf

variable "alb-ingress" {
  description = "List of CIDR ranges for which ingress is allowed (e.g., to only allow Akamai servers)"
  type        = list(string)
}

# Server-related variables
# cf. servers.tf

variable "server-min-capacity" {
  description = "Minimum number of EC2 instances to run in the autoscaling group"
  type        = number
  default     = 1
}

variable "server-max-capacity" {
  description = "Maximum number of EC2 instances to run in the autoscaling group"
  type        = number
}

variable "server-instance-type" {
  description = "EC2 instance type to use for the WebCMS cluster"
  type        = string
}

# Cluster variables
# cf. cluster.tf

variable "cluster-name" {
  description = "Name of the ECS cluster to create"
  type        = string
  default     = "webcms-cluster"
}

variable "cluster-min-capacity" {
  description = "Minimum number of Drupal tasks to run in the cluster"
  type        = number
  default     = 1
}

variable "cluster-max-capacity" {
  description = "Maximum number of Drupal tasks to run in the cluster"
  type        = number
}

# Database variables
# cf. rds.tf

variable "db-instance-type" {
  description = "Instance class to use for the WebCMS database"
  type        = string
}

variable "db-storage-size" {
  description = "Storage to allocate to the WebCMS database"
  type        = number
}

variable "db-username" {
  description = "Username of the database's root user"
  type        = string
}

variable "db-password" {
  description = "Password of the database's root user"
  type        = string
}

# Cache variables
# cf. cache.tf

variable "cache-instance-type" {
  description = "Instance type of ElastiCache nodes"
  type        = string
}

variable "cache-instance-count" {
  description = "Number of ElastiCache nodes in this cluster"
  type        = number
}

# Bastion SSH server (optional)
# cf. bastion.tf security.tf

variable "bastion-create" {
  description = "Whether or not to create a public-facing SSH bastion"
  type        = bool
  default     = false
}

variable "bastion-key" {
  description = "EC2 keypair name for the SSH bastion server"
  type        = string
  default     = null
}

# NB. Since this is in CIDR notation, use 1.2.3.4/32 to specify a single address
variable "bastion-ingress" {
  description = "List of CIDR ranges from which SSH is allowed (e.g., jumpbox, VPN address, etc.)"
  type        = list(string)
  default     = []
}

# DNS
# cf. dns.tf alb.tf

# We separate the root domain and subdomains in order to make managing DNS somewhat easier.
# These variables will impact two parts of the configuration:
#
# The load balancer will be configured to listen to one of two hosts:
# 1. If dns-subdomain is set, the Host header must match "${dns-subdomain}.${dns-root-domain}"
# 2. Otherwise, the host header must match dns-root-domain.
#
# The ACM certificate will be generated to match these variables:
# 1. If dns-subdomain is set, the cert will be generated for "${dns-subdomain}.${dns-root-domain}"
# 2. Otherwise, the cert will be generated for dns-root-domain.
#
# The main benefit of separating these two variables is that it opens up the public-facing
# Route 53 zone to manage other records for site DNS from within Terraform or the AWS
# console, rather than having to delegate a single DNS zone to this account.
#
# When using these variables, note that while dns-root-domain is a full domain, the dns-subdomain
# string is just a domain part. That is, to have the ALB listen to "dev.example.com", you
# would specify these variables:
#
# dns-root-domain = "example.com"
# dns-subdomain = "dev"

variable "dns-root-domain" {
  description = "Root DNS name of the zone to manage (e.g., example.org or dev.example.com)"
  type        = string
}

variable "dns-subdomain" {
  description = "Subdomain of the dns-root-domain variable on which to listen to requests"
  type        = string
  default     = null
}

# Image tag variables
# cf. cluster.tf

# By allowing the two image parameters to be null, we can allow a "bootstrap" phase where
# the cluster's resources (most importantly, the ECR repositories) to be created before
# deploying services to the cluster requiring these repositories.

variable "image-tag-nginx" {
  description = "Tag of the nginx image to deploy"
  type        = string
  default     = null
}

variable "image-tag-drupal" {
  description = "Tag of the Drupal image to deploy"
  type        = string
  default     = null
}
