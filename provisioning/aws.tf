provider "aws" {
  allowed_account_ids = [var.aws_account]
  region              = var.aws_region
  profile             = var.aws_profile
}

variable "aws_profile" {
  type = string
}
variable "aws_region" {
  type = string
}
variable "aws_account" {
  type = string
}

data "aws_region" "current" {}
data "aws_caller_identity" "current" {}

# Create resources

module "aws_storage" {
  source = "./modules/aws/storage"

  service_name = local.service_name
}

module "aws_policy" {
  source = "./modules/aws/policy"

  service_name  = local.service_name
  bucket_arn    = module.aws_storage.bucket_arn
}

module "aws_user" {
  source = "./modules/aws/user"

  service_name  = local.service_name
  policy_arn    = module.aws_policy.policy_arn
}

module "aws_user_rotate" {
  source = "./modules/aws/user"

  service_name  = local.rotate_service_name
  policy_arn    = module.aws_policy.policy_arn
}

# Outputs

output "TEST_S3_REGION" {
  value = data.aws_region.current.id
  description = "Region where your S3 is located"
}
output "TEST_S3_KEY" {
  value = module.aws_user.access_key_id
  description = "First AWS key"
}
output "TEST_S3_SECRET" {
  value = module.aws_user.access_key_secret
  sensitive = true
  description = "First AWS secret"
}
output "TEST_S3_ROTATE_KEY" {
  value = module.aws_user_rotate.access_key_id
  description = "Second AWS key"
}
output "TEST_S3_ROTATE_SECRET" {
  value = module.aws_user_rotate.access_key_secret
  sensitive = true
  description = "Second AWS secret"
}
output "TEST_S3_FILES_BUCKET" {
  value = module.aws_storage.bucket_name
  description = "Name of file bucket on S3"
}

