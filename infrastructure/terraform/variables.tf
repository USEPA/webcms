# AWS
# cf. providers.tf

variable "aws-region" {
  description = "AWS region to connect to"
  type        = string
}

# Global/sitewide variables

variable "site-hostname" {
  description = "Domain name of the WebCMS."
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

variable "alb-certificate" {
  description = "ARN of the ACM certificate to secure the load balancer."
  type        = string
}

variable "alb-hostname" {
  description = "Hostname for the ALB to listen to, in case it differs from the site-hostname variable."
  type        = string
  default     = null
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
