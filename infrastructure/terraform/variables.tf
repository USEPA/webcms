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

# Encryption-related variables
variable "encryption-at-rest-key" {
  description = "Name of the AWS KMS key to use for encrypting at-rest data."
  type        = string
  default     = null
}

# VPC
# cf. vpc.tf

variable "vpc-existing-vpc" {
  description = "ID of an existing VPC to use. Null implies a new VPC should be created."
  type        = string
  default     = null
}

variable "vpc-existing-gateway" {
  description = "ID of an existing internet gateway to use. Null implies a new gateway should be created."
  type        = string
  default     = null
}

variable "vpc-subnet-block" {
  description = "CIDR block to use when allocating subnets. Defaults to using the full CIDR range of the VPC."
  type        = string
  default     = null
}

# This variable corresponds to the "newbits" parameter of the cidrsubnet function
# cf. https://www.terraform.io/docs/configuration/functions/cidrsubnet.html
variable "vpc-subnet-bits" {
  description = "Value to add to the VPC's CIDR block when creating a subnet's IP range."
  type        = number
}

# We use the offset to separate the public and private CIDR blocks from each other - the
# offset should be at least the number of AZs (vpc-az-count), but could be larger to give
# room to add more AZs in the future.
variable "vpc-subnet-offset" {
  description = "Number of subnets to skip when creating private subnets."
  type        = number
}

variable "vpc-az-count" {
  description = "Number of availability zones to use when creating the VPC's subnets"
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

# Since we can't use iteration in a nested override block, we have to pick a number of
# instances. We choose 3 for no particular reason other than it corresponds to AWS'
# generic instance types: t2, t3, and t3a.
#
# Note that limitations in Terraform's ability to handle dynamic lists means that we have
# to fix the number of instance types at 3. These can vary by generation (as mentioned
# above, the dev site uses the same size across the t2, t3, and t3a generations), but they
# could also vary by instance size (e.g., medium, large, and xlarge). We assume that the
# dynamics of the spot instance market will defray the costs incurred by using older
# generation (or larger size) instances.
variable "server-instance-types" {
  description = "Instance types to use with the WebCMS' servers (spot and on-demand)"

  type = object({
    primary   = string
    secondary = string
    tertiary  = string
  })
}

variable "server-extra-bootstrap" {
  description = "Additional bootstrap code to run on the cluster EC2s"
  type        = string
  default     = ""
}

variable "server-extra-policies" {
  description = "Additional IAM policies to apply to the cluster EC2s"
  type        = list(string)
  default     = []
}

variable "server-security-ingress" {
  description = "List of security groups to allow access to the cluster EC2s"
  type        = list(string)
  default     = []
}

# Cluster variables
# cf. cluster.tf

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

# S3 variables
# cf. s3.tf

variable "s3-bucket-name" {
  description = "Name of the S3 bucket used to store all uploads (public and private)."
  type        = string
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

# Users
# cf. iam.tf

variable "users-extra-admin" {
  description = "Names of additional IAM users to make administrators"
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

variable "image-tag-drush" {
  description = "Tag of the Drush image to deploy"
  type        = string
  default     = null
}
