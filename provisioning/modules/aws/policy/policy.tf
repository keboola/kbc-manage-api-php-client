data "aws_iam_policy_document" "mapi_access_policy_document" {
  statement {
    sid    = "S3Access"
    effect = "Allow"
    actions = [
      "s3:*"
    ]

    resources = [
      var.bucket_arn,
      "${var.bucket_arn}/*"
    ]
  }

  statement {
    sid = "AllowListingOfUserFolder"
    actions = [
      "s3:ListBucket",
      "s3:GetBucketLocation"
    ]
    effect = "Allow"
    resources = [
      var.bucket_arn,
      "${var.bucket_arn}/*"
    ]
  }

  statement {
    sid    = "StsAccess"
    effect = "Allow"
    actions = [
      "sts:GetFederationToken"
    ]
    resources = [
      "*"
    ]
  }
}

resource "aws_iam_policy" "mapi_app_policy" {
  name        = "${var.service_name}-mapi_app_policy"
  description = "${var.service_name} - MAPI App Services"
  policy      = data.aws_iam_policy_document.mapi_access_policy_document.json
}