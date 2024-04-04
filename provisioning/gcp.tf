provider "google" {
  project = var.gcs_project_id
  region  = var.gcs_project_region
}

variable "gcs_project_id" {
  type = string
}
variable "gcs_project_region" {
  type = string
}
variable "gcs_storage_location" {
  type = string
}

# Create resources

module "gcp_storage" {
  source = "./modules/gcp/storage"

  storage_location  = var.gcs_storage_location
  service_name      = local.service_name
}

module "gcp_account" {
  source = "./modules/gcp/account"

  service_name = local.service_name
  storage_name = module.gcp_storage.storage_name
}

module "gcp_account_rotate" {
  source = "./modules/gcp/account"

  service_name = local.rotate_service_name
  storage_name = module.gcp_storage.storage_name
}

# Outputs

output "TEST_GCS_REGION" {
  value       = var.gcs_storage_location
  description = "Region whare GCS is located"
}
output "TEST_GCS_FILES_BUCKET" {
  value       = module.gcp_storage.storage_name
  description = "Name of file bucket on GCS"
}
output "TEST_GCS_KEYFILE_JSON" {
  value       = trimspace(replace(base64decode(module.gcp_account.account_private_key), "\n", ""))
  sensitive   = true
  description = "First GCS key file contents as json string"
}
output "TEST_GCS_KEYFILE_ROTATE_JSON" {
  value       = trimspace(replace(base64decode(module.gcp_account_rotate.account_private_key), "\n", ""))
  sensitive   = true
  description = "Second GCS key file contents as json string used for testing rotation"
}
