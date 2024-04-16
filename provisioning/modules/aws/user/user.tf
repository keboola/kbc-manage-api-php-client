
resource "aws_iam_user" "mapi_service_user" {
  name = var.service_name
}
resource "aws_iam_access_key" "mapi_user_access_key" {
  user = aws_iam_user.mapi_service_user.name
}
resource "aws_iam_group" "mapi_service_user_group" {
  name = "${var.service_name}-group"
}
resource "aws_iam_group_policy_attachment" "mapi_service_user_group_policy_attachment" {
   group      = aws_iam_group.mapi_service_user_group.name
  policy_arn = var.policy_arn
}
resource "aws_iam_user_policy_attachment" "mapi_service_user_policy_attachment" {
   policy_arn = var.policy_arn
  user       = aws_iam_user.mapi_service_user.name
}
resource "aws_iam_group_membership" "mapi_service_user_group_membership" {
  group = aws_iam_group.mapi_service_user_group.name
  name  = "${var.service_name}-group-membership"
  users = [
    aws_iam_user.mapi_service_user.name
  ]
}
