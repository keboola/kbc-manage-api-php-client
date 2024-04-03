output "bucket_name" {
  value = aws_s3_bucket.s3_files_bucket.bucket
}
output "bucket_arn" {
  value = aws_s3_bucket.s3_files_bucket.arn
}