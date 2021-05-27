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
  description = "Environment name of this deployment. Should be 'prod' in production. Please add -es for Spanish site variants, like 'prod-es'."
  type        = string
}

variable "site-env-lang" {
  description = "The language for the site.  Can be left empty or set to 'en' for the main production site. For the spanish site this should be set to 'es'."
  type        = string
}

variable "site-s3-uses-domain" {
  description = "Determines whether S3 assets will be served from the same domain as the main site. Set to false to serve directly from S3 domain."
  type        = bool
}

variable "site-csrf-origin-whitelist" {
  description = "Comma separated list of trustworthy sources. The domain of the SAML IdP will need to be entered here. Do not enter the site's own URL - it is automatically added. Syntax of the source is: [protocol] :// [host] : [port] . E.g, http://example.com, https://example.com, https://www.example.com, http://www.example.com:8080"
  type        = string
  default     = "https://wamssostg.epa.gov, https://a11y_page_service.siteimprove.com:8079"
}

variable "site-log-group" {
  description = "Name of the log group for this environment"
  type        = string
}

# Akamai variables
variable "akamai-enabled" {
  description = "Set to TRUE if this site is being served via Akamai; otherwise FALSE."
  type        = bool
  default     = false
}

variable "akamai-api-host" {
  description = "The URL of the Akamai CCU API host. It should be in the format *.purge.akamaiapis.net/"
  type        = string
  default     = "https://xxxx-xxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxx.luna.akamaiapis.net/"
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

variable "alb-hostnames" {
  description = "Additional hostnames for the load balancer to respond to in addition to the site-hostname variable"
  type        = list(string)
  default     = []
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

# Extra options for the utility ASG
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

variable "db-instance-type" {
  description = "Type of instance to use for the Aurora cluster"
  type        = string
}

# "Primary" here means the always-on instances that can be promoted to the writer instance
# during a failover event.
variable "db-instance-count" {
  description = "Number of primary DB servers in the Aurora cluster"
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
  description = "Number of ElastiCache nodes in this cluster"
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

variable "email-port" {
  description = "SMTP port to connect to"
  type        = number
}

variable "email-protocol" {
  description = "SMTP encryption protocol. Options are 'standard' for none, 'ssl', or 'tls'."
  type        = string
}

variable "email-enable-workflow-notifications" {
  description = "Enable this to allow the system to email notifications to content editors about workflow state changes. This should only be enabled with care; we do not want a non-production environment mailing 'real' users notifications that they need to come review their content."
  type        = bool
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

# SAML variables
variable "saml-sp-entity-id" {
  description = "Value this site will use as its SAML service provider (SP) entity ID"
  type        = string
}

variable "saml-sp-cert" {
  description = "Public certificate of the Drupal SAML service provider (SP)"
  type        = string
}

variable "saml-idp-id" {
  description = "Entity ID of the SAML IdP"
  type        = string
}

variable "saml-idp-sso-url" {
  description = "Single sign on service URL where the SP will direct authentication requests."
  type        = string
}

variable "saml-idp-slo-url" {
  description = "Single logout service URL where the SP will direct logout requests."
  type        = string
}

variable "saml-idp-cert" {
  description = "Public x509 certificate of the IdP."
  type        = string
}

variable "saml-force-saml-login" {
  description = "When set to true, users are redirected when visiting the Drupal login page and instead required to login via SAML."
  type        = bool
  default     = false
}
