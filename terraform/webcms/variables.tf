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
