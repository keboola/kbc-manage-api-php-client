output "storage_account_files_name" {
  value = azurerm_storage_account.files.name
}
output "storage_account_primary_access_key" {
  sensitive = true
  value     = azurerm_storage_account.files.primary_access_key
}
output "storage_account_secondary_access_key" {
  sensitive = true
  value     = azurerm_storage_account.files.secondary_access_key
}
output "storage_container_files_test_container_name" {
  value = azurerm_storage_container.files_test_container.name
}
output "storage_account_location" {
  value = azurerm_storage_account.files.location
}