resource "google_service_account" "mapi_service_account" {
  account_id   = var.service_name
  display_name = "${var.service_name} Service Account"
}

resource "google_storage_bucket_iam_member" "mapi_member_creator_fs_bucket" {
  bucket = var.storage_name
  role   = "roles/storage.objectAdmin"
  member = "serviceAccount:${google_service_account.mapi_service_account.email}"
}

resource "google_service_account_key" "mapi_service_account_key" {
  service_account_id = google_service_account.mapi_service_account.name
}
