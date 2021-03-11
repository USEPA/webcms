variable "email_enable_workflow_notifications" {
  description = "Enable this to allow the system to email notifications to content editors about workflow state changes. This should only be enabled with care; we do not want a non-production environment mailing 'real' users notifications that they need to come review their content."
  type        = bool
}

variable "saml_force_saml_login" {
  description = "When set to true, users are redirected when visiting the Drupal login page and instead required to login via SAML."
  type        = bool
  default     = false
}

# Akamai variables
variable "akamai_enabled" {
  description = "Set to TRUE if this site is being served via Akamai; otherwise FALSE."
  type        = bool
  default     = false
}

variable "akamai_api_host" {
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

variable "aws_region" {
  description = "AWS region to connect to"
  type        = string
}

variable "image_tag_drush" {
  description = "Tag of the Drush image to deploy"
  type        = string
}

variable "image_tag_drupal" {
  description = "Tag of the Drupal image to deploy"
  type        = string
}

variable "image_tag_nginx" {
  description = "Tag of the nginx image to deploy"
  type        = string
}

variable "site_hostname" {
  description = "Domain name of the WebCMS."
  type        = string
}

variable "site_env_name" {
  description = "Environment name of this deployment. Should be 'prod' in production. Please add -es for Spanish site variants, like 'prod-es'."
  type        = string
}

variable "cluster_min_capacity" {
  description = "Minimum number of Drupal tasks to run in the cluster"
  type        = number
  default     = 1
}

variable "cluster_max_capacity" {
  description = "Maximum number of Drupal tasks to run in the cluster"
  type        = number
}

variable "site_env_state" {
  description = "Indicates the bootstrap state of Drupal. Should be either the string 'run' or 'build'"
  type        = string
}

variable "site_s3_uses_domain" {
  description = "Determines whether S3 assets will be served from the same domain as the main site. Set to false to serve directly from S3 domain."
  type        = bool
}

variable "email_auth_user" {
  description = "Username for SMTP authentication"
  type        = string
}

variable "email_from" {
  description = "From address for site mail"
  type        = string
}

variable "email_host" {
  description = "SMTP hostname to connect to"
  type        = string
}

variable "email_port" {
  description = "SMTP port to connect to"
  type        = number
}

variable "email_protocol" {
  description = "SMTP encryption protocol. Options are 'standard' for none, 'ssl', or 'tls'."
  type        = string
}

variable "saml_sp_entity_id" {
  description = "Value this site will use as its SAML service provider (SP) entity ID"
  type        = string
}

variable "saml_sp_cert" {
  description = "Public certificate of the Drupal SAML service provider (SP)"
  type        = string
}

variable "saml_idp_id" {
  description = "Entity ID of the SAML IdP"
  type        = string
}

variable "saml_idp_sso_url" {
  description = "Single sign on service URL where the SP will direct authentication requests."
  type        = string
}

variable "saml_idp_slo_url" {
  description = "Single logout service URL where the SP will direct logout requests."
  type        = string
}

variable "saml_idp_cert" {
  description = "Public x509 certificate of the IdP."
  type        = string
}

variable "alb_hostnames" {
  description = "Additional hostnames for the load balancer to respond to in addition to the site-hostname variable"
  type        = list(string)
  default     = []
}