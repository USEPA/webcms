variable "aws_region" {
  description = "AWS region to connect to"
  type        = string
}

#region Module

variable "environment" {
  description = "Name of the environment corresponding to this VPC (e.g., preproduction, production)"
  type        = string
}

variable "sites" {
  description = "List of sites (e.g., dev, stage) this infrastructure covers"
  type        = list(string)
}

variable "tags" {
  description = "Extra tags to apply to resources"
  type        = map(string)
  default     = {}
}

#endregion

#region IAM

variable "iam_prefix" {
  description = "Custom prefix for auto-generated IAM roles and policies"
  type        = string
  default     = "WebCMS"
}

#endregion

#region Load balancer
# cf. load_balancer.tf

variable "lb_default_certificate" {
  description = "ARN of the IAM/ACM certificate for the load balancer."
  type        = string
}

variable "lb_extra_certificates" {
  description = "ARNs of additional certificates for the load balancer."
  type        = list(string)
  default     = []
}

variable "lb_internal" {
  description = "Whether or not this environment's NLB is internal."
  type        = bool
  default     = false
}

#endregion

#region RDS/Aurora
# cf. rds.tf

variable "db_instance_type" {
  description = "Type of instance to use for the Aurora cluster"
  type        = string
}

# "Primary" here means the always-on instances that can be promoted to the writer instance
# during a failover event.
variable "db_instance_count" {
  description = "Number of primary DB servers in the Aurora cluster"
  type        = number
}

#endregion

#region Elasticsearch
# cf. search.tf

variable "search_instance_type" {
  description = "Type of instance to deploy in the Elasticsearch cluster"
  type        = string
}

variable "search_instance_count" {
  description = "Number of instances to deploy in the Elasticsearch cluster"
  type        = number
}

variable "search_instance_storage" {
  description = "Capacity (in GB) to allocate to each search instance"
  type        = number
}

variable "search_dedicated_node_type" {
  description = "Type of dedicated master nodes to deploy in the Elasticseach cluster"
  type        = string
}

variable "search_dedicated_node_count" {
  description = "Number of dedicatd master nodes to deploy in the Elasticsearch cluster. Set to 0 to disable dedicated nodes."
  type        = number
}

variable "search_availability_zones" {
  description = "Number of availability zones to use for the search cluster. Valid values are 2 or 3"
  type        = number
}

#endregion

#region Elasticache
# cf. cache.tf

variable "cache_instance_type" {
  description = "Instance type of ElastiCache nodes"
  type        = string
}

variable "cache_instance_count" {
  description = "Number of ElastiCache nodes in this cluster"
  type        = number
}

#endregion

#region Traefik
# cf. traefik_service.tf

variable "traefik_min_capacity" {
  description = "Minimum number of Traefik tasks to run"
  type        = number
  default     = 2
}

variable "traefik_max_capacity" {
  description = "Maximum number of Traefik tasks to run"
  type        = number
  default     = 5
}

#endregion
