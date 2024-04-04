resource "google_storage_bucket" "mapi_file_storage_backend" {
  name                     = "${var.service_name}-files-storage"
  location                 = var.storage_location
  storage_class            = "STANDARD"
  public_access_prevention = "enforced"
  versioning {
    enabled = false
  }
  uniform_bucket_level_access = true

  lifecycle_rule {
    action {
      type = "Delete"
    }
    condition {
      age            = 2
      matches_prefix = ["exp-2/"]
    }
  }

  lifecycle_rule {
    action {
      type = "Delete"
    }

    condition {
      age            = 15
      matches_prefix = ["exp-15/"]
    }
  }
}
