output "storage_account_files_name" {
  value = azurerm_storage_account.mapi_file_storage.name
}
output "storage_account_primary_access_key" {
  sensitive = true
  value     = azurerm_storage_account.mapi_file_storage.primary_access_key
}
output "storage_account_secondary_access_key" {
  sensitive = true
  value     = azurerm_storage_account.mapi_file_storage.secondary_access_key
}
output "storage_container_files_test_container_name" {
  value = azurerm_storage_container.mapi_file_storage_container.name
}
output "storage_account_location" {
  value = azurerm_storage_account.mapi_file_storage.location
}