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

variable "site-env-state" {
  description = "Indicates the bootstrap state of Drupal. Should be either the string 'run' or 'build'"
  type        = string
}

variable "site-env-name" {
  description = "Environment name of this deployment. Should be 'prod' in production."
  type        = string
}

# VPC
# cf. vpc.tf

variable "vpc-az-count" {
  description = "Number of availability zones to use when creating the VPC"
  type        = number
}

# Systems Manager
# cf. iam.tf ssm.tf

variable "ssm-customer-key" {
  description = "AWS customer key (CMK) used to encrypt SSM sessions"
  type        = string
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

variable "db-username" {
  description = "Username of the database's root user"
  type        = string
}

variable "db-password" {
  description = "Password of the database's root user"
  type        = string
}

variable "db-auto-pause" {
  description = "Whether or not to enable cluster auto-pause"
  type        = bool
  default     = false
}

# Capacity is specified in Aurora Capacity Units (ACUs) - these should be powers of two
# from 1 to 256.
# cf. https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/aurora-serverless.how-it-works.html#aurora-serverless.how-it-works.auto-scaling
variable "db-min-capacity" {
  description = "Minimum capacity for the database cluster"
  type        = number
  default     = 1
}

variable "db-max-capacity" {
  description = "Maximum capacity for the database cluster"
  type        = number
}

# Search variables
# cf. search.tf

variable "search-instance-type" {
  description = "Type of instance to deploy in the Elasticsearch cluster"
  type        = string
}

variable "search-instance-count" {
  description = "Number of instances to deploy in the Elasticsearch cluster"
  type        = number
}

variable "search-instance-storage" {
  description = "Capacity (in GB) to allocate to each search instance"
  type        = number
}

variable "search-dedicated-node-type" {
  description = "Type of dedicated master nodes to deploy in the Elasticseach cluster"
  type        = string
}

variable "search-dedicated-node-count" {
  description = "Number of dedicatd master nodes to deploy in the Elasticsearch cluster. Set to 0 to disable dedicated nodes."
  type        = number
}

variable "search-availability-zones" {
  description = "Number of availability zones to use for the search cluster. Valid values are 2 or 3"
  type        = number
}

# Cache variables
# cf. cache.tf

variable "cache-instance-type" {
  description = "Instance type of ElastiCache nodes"
  type        = string
}

variable "cache-replica-count" {
  description = "Number of ElastiCache read replicas in this cluster"
  type        = number
}

# Mail
# cf. parameters.tf

variable "email-auth-user" {
  description = "Username for SMTP authentication"
  type        = string
}

variable "email-from" {
  description = "From address for site mail"
  type        = string
}

variable "email-host" {
  description = "SMTP hostname to connect to"
  type        = string
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

variable "image-tag-drush" {
  description = "Tag of the Drush image to deploy"
  type        = string
  default     = null
}
