output "bucket_name" {
  value = aws_s3_bucket.mapi_file_storage.bucket
}
output "bucket_arn" {
  value = aws_s3_bucket.mapi_file_storage.arn
}