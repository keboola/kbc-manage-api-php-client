
resource "aws_s3_bucket" "s3_files_bucket" {
  bucket = "${var.service_name}-s3-bucket"

  tags = {
    Name = "keboola-mapi-file-storage"
  }

  force_destroy = true
}

resource "aws_s3_bucket_cors_configuration" "s3_files_bucket_cors_configuration" {
  bucket = aws_s3_bucket.s3_files_bucket.bucket

  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = [
      "GET",
      "PUT",
      "POST",
      "DELETE"
    ]
    allowed_origins = ["*"]
    max_age_seconds = 3600
  }
}

resource "aws_s3_bucket_lifecycle_configuration" "s3_files_bucket_lifecycle_config" {
  bucket = aws_s3_bucket.s3_files_bucket.bucket
  rule {
    id = "After 30 days IA, 180 days to glacier and 270 delete"
    filter {
      prefix = "exp-180"
    }

    expiration {
      days = 270
    }

    transition {
      storage_class = "STANDARD_IA"
      days          = 30
    }

    transition {
      storage_class = "GLACIER"
      days          = 180
    }

    status = "Enabled"
  }

  rule {
    id     = "Delete after 30 days"
    status = "Enabled"

    filter {
      prefix = "exp-30"
    }

    expiration {
      days = 30
    }
  }

  rule {
    id     = "Delete after 15 days"
    status = "Enabled"

    filter {
      prefix = "exp-15"
    }

    expiration {
      days = 15
    }
  }

  rule {
    id     = "Delete after 48 hours"
    status = "Enabled"

    filter {
      prefix = "exp-2"
    }
    expiration {
      days = 2
    }
  }

  rule {
    id     = "Delete incomplete multipart uploads"
    status = "Enabled"

    abort_incomplete_multipart_upload {
      days_after_initiation = 7
    }
  }
}
