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

module "storage" {
  source = "./modules/gcp/storage"

  storage_location = var.gcs_storage_location
  service_name = local.service_name
}

module "account" {
  source = "./modules/gcp/account"

  service_name = local.service_name
  storage_name = module.storage.storage_name
}

module "account_rotate" {
  source = "./modules/gcp/account"

  service_name = local.rotate_service_name
  storage_name = module.storage.storage_name
}

# Outputs

output "TEST_GCS_REGION" {
  value = var.gcs_storage_location
}
output "TEST_GCS_FILES_BUCKET" {
  value = module.storage.storage_name
}
output "TEST_GCS_KEYFILE_JSON" {
  value     = trimspace(replace(base64decode(module.account.account_private_key), "\n", ""))
  sensitive = true
}
output "TEST_GCS_KEYFILE_ROTATE_JSON" {
  value     = trimspace(replace(base64decode(module.account_rotate.account_private_key), "\n", ""))
  sensitive = true
}
