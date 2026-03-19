resource "azurerm_storage_account" "mapi_file_storage" {
  account_replication_type  = "RAGRS"
  account_tier              = "Standard"
  location                  = var.resource_group_location
  name                      = "mapi${var.resource_group_uuid}"
  resource_group_name       = var.resource_group_name
  account_kind              = "StorageV2"
  enable_https_traffic_only = true
}

resource "azurerm_storage_container" "mapi_file_storage_container" {
  name                 = var.files_container
  storage_account_name = azurerm_storage_account.mapi_file_storage.name
}

resource "azurerm_storage_management_policy" "mapi_file_storage_policy" {
  storage_account_id = azurerm_storage_account.mapi_file_storage.id

  rule {
    name    = "48-hours-expire"
    enabled = true
    filters {
      prefix_match = ["exp-2-"]
      blob_types   = ["blockBlob"]
    }
    actions {
      base_blob {
        delete_after_days_since_modification_greater_than = 2
      }
    }
  }

  rule {
    name    = "15-days-expire"
    enabled = true
    filters {
      prefix_match = ["exp-15-"]
      blob_types   = ["blockBlob"]
    }
    actions {
      base_blob {
        delete_after_days_since_modification_greater_than = 15
      }
    }
  }

  rule {
    name    = "30-days-expire"
    enabled = true
    filters {
      prefix_match = ["exp-30-"]
      blob_types   = ["blockBlob"]
    }
    actions {
      base_blob {
        delete_after_days_since_modification_greater_than = 30
      }
    }
  }

  rule {
    name    = "180-days-expire"
    enabled = true
    filters {
      prefix_match = ["exp-180-"]
      blob_types   = ["blockBlob"]
    }
    actions {
      base_blob {
        tier_to_cool_after_days_since_modification_greater_than    = 30
        tier_to_archive_after_days_since_modification_greater_than = 180
        delete_after_days_since_modification_greater_than          = 270
      }
    }
  }

}