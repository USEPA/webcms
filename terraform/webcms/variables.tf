variable "email-enable-workflow-notifications" {
  description = "Enable this to allow the system to email notifications to content editors about workflow state changes. This should only be enabled with care; we do not want a non-production environment mailing 'real' users notifications that they need to come review their content."
  type        = bool
}

variable "saml-force-saml-login" {
  description = "When set to true, users are redirected when visiting the Drupal login page and instead required to login via SAML."
  type        = bool
  default     = false
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

variable "environment" {
  description = "Name of the environment corresponding to this VPC (e.g., preproduction, production)"
  type        = string
}

variable "sites" {
  description = "Site names and languages to use during initialization"

  type = map(object({
    site = string
    lang = string
  }))
}

variable "aws-region" {
  description = "AWS region to connect to"
  type        = string
}

variable "image-tag-drush" {
  description = "Tag of the Drush image to deploy"
  type        = string
  default     = null
}

variable "image-tag-drupal" {
  description = "Tag of the Drupal image to deploy"
  type        = string
  default     = null
}

variable "image-tag-nginx" {
  description = "Tag of the nginx image to deploy"
  type        = string
  default     = null
}

variable "site-hostname" {
  description = "Domain name of the WebCMS."
  type        = string
}

variable "site-env-name" {
  description = "Environment name of this deployment. Should be 'prod' in production. Please add -es for Spanish site variants, like 'prod-es'."
  type        = string
}

variable "alb-hostnames" {
  description = "Additional hostnames for the load balancer to respond to in addition to the site-hostname variable"
  type        = list(string)
  default     = []
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