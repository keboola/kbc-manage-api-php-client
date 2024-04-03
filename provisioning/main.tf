terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 3.74"
    }

    azurerm = {
      source  = "hashicorp/azurerm"
      version = "~> 3.0.0"
    }

    azuread = {
      source  = "hashicorp/azuread"
      version = "~> 2.24"
    }

    google = {
      source  = "hashicorp/google"
      version = "~> 4.0"
    }
  }
}

variable "name_prefix" {
  type = string
}
variable "gcs_storage_location" {
  type = string
}


locals {
  service_name = "${var.name_prefix}-mapi-services"
  rotate_service_name = "${var.name_prefix}-mapi-rotate-services"
}


