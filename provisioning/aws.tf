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

module "create_bucket" {
  source = "./modules/aws/bucket"

  service_name = local.service_name
}

module "create_policy" {
  source = "./modules/aws/policy"

  service_name = local.service_name
  bucket_arn = module.create_bucket.bucket_arn
}

module "create_user" {
  source = "./modules/aws/user"

  service_name = local.service_name
  policy_arn = module.create_policy.policy_arn
}

module "create_user_rotate" {
  source = "./modules/aws/user"

  service_name = local.rotate_service_name
  policy_arn = module.create_policy.policy_arn
}


output "TEST_S3_REGION" {
  value = data.aws_region.current.id
  description = "Region where your S3 is located"
}
output "TEST_S3_KEY" {
  value = module.create_user.access_key_id
  description = "First AWS key"
}
output "TEST_S3_SECRET" {
  value = module.create_user.access_key_secret
  sensitive = true
  description = "First AWS secret"
}
output "TEST_S3_ROTATE_KEY" {
  value = module.create_user_rotate.access_key_id
  description = "Second AWS key"
}
output "TEST_S3_ROTATE_SECRET" {
  value = module.create_user_rotate.access_key_secret
  sensitive = true
  description = "Second AWS secret"
}
output "TEST_S3_FILES_BUCKET" {
  value = module.create_bucket.bucket_name
  description = "Name of file bucket on S3"
}

