terraform {
  # Backend configuration is left unspecified here: there should be an external backend
  # configuration file used on each `terraform init`
  # cf. https://www.terraform.io/docs/backends/config.html#partial-configuration
  backend "s3" {}
}
