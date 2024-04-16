output "access_key_id" {
  value = aws_iam_access_key.mapi_user_access_key.id
}

output "access_key_secret" {
  value = aws_iam_access_key.mapi_user_access_key.secret
  sensitive = true
}