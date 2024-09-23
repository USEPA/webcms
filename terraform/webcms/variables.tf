#region Provider configuration

variable "aws_region" {
  description = "AWS region to connect to"
  type        = string
}

#endregion

#region AWS ID variables

variable "cloudfront_distributionid" {
  description = "Cloudfront distribution ID to use"
  type        = string
  default     = ""
}

#endregion

#region Deployment variables

variable "environment" {
  description = "Name of the environment corresponding to this VPC (e.g., preproduction, production)"
  type        = string
}

variable "site" {
  description = "The name of this site (e.g., dev, stage)"
  type        = string
}

variable "lang" {
  description = "The language of this site (en or es)"
  type        = string

  validation {
    condition     = contains(["en", "es"], var.lang)
    error_message = "The language must be either 'en' (for English) or 'es' (for Spanish)."
  }
}

variable "tags" {
  description = "Map of key/value pairs to apply to resources."
  type        = map(string)
  default     = {}
}

variable "image_tag" {
  description = "The image tag to apply to all custom (non-mirrored) images. This should vary on a per-build basis, such as using <branch>-<build> to associate each image with the CI build."
  type        = string
}

variable "cpu_architecture" {
  description = "The CPU architecture to pass to Fargate. Should be either 'X86_64' or 'ARM64'. Leave null to apply the Fargate default."
  type        = string
  default     = null
}

#endregion

#region Drupal service configuration

variable "drupal_hostname" {
  description = "Domain name of the WebCMS. This value is used not only by the Drupal web containers, but also during Drush runs with the --uri flag."
  type        = string
}

variable "drupal_extra_hostnames" {
  description = "Additional host names to be recognized by the WebCMS. Useful for bypassing caching tiers and connecting to the origin, or permitting private DNS aliases."
  type        = list(string)
  default     = []
}

variable "drupal_min_capacity" {
  description = "Minimum number of Drupal tasks to run in the cluster"
  type        = number
  default     = 1
}

variable "drupal_schedule_min_capacity" {
  description = "When using scheduled scaling, the elevated minimum number of Drupal tasks to run during working hours"
  type        = number
  default     = null
}

variable "drupal_max_capacity" {
  description = "Maximum number of Drupal tasks to run in the cluster"
  type        = number
}

variable "drupal_state" {
  description = "Indicates the bootstrap state of Drupal. When set to 'build', Drupal is not yet installed and settings.php will refuse to use external services. Set to 'run' after a site install to use, e.g., memcached."
  type        = string

  validation {
    condition     = contains(["run", "build"], var.drupal_state)
    error_message = "The Drupal state must be either 'build' or 'run'. Please see the README for more information."
  }
}

variable "drupal_csrf_origin_whitelist" {
  description = "A list of domains considered trustworthy by Drupal's Security Kit module. Each should be of the form PROTOCOL://DOMAIN[:PORT], such as http://example.com or https://example.net:123. The site's own domain is already part of this list."
  type        = list(string)
  default     = []
}

#endregion

#region Drush service Configuration

variable "drush_tasks" {
  description = "The number of drush tasks to implement per site.  There will be a default value of 1.  The intent is to only have to specify this if you want something other than the default 1 task."
  type        = number
  default     = 1
}

#end region

#region Email

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

  validation {
    condition     = contains(["standard", "ssl", "tls"], var.email_protocol)
    error_message = "The SMTP protocol must be one of 'standard', 'ssl', or 'tls'. Use 'standard' to disable encryption."
  }
}

variable "email_enable_workflow_notifications" {
  description = "Enable this to allow the system to email notifications to content editors about workflow state changes. This should only be enabled with care; we do not want a non-production environment mailing 'real' users notifications that they need to come review their content."
  type        = bool
}

#endregion

#region SAML

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

variable "saml_force_saml_login" {
  description = "When set to true, users are redirected when visiting the Drupal login page and instead required to login via SAML."
  type        = bool
  default     = false
}

#endregion
