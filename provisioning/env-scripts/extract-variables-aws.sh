#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source ./functions.sh

output_var 'TEST_S3_REGION' "$(terraform_output 'TEST_S3_REGION')"
output_var 'TEST_S3_FILES_BUCKET' "$(terraform_output 'TEST_S3_FILES_BUCKET')"
output_var 'TEST_S3_KEY' "$(terraform_output 'TEST_S3_KEY')"
output_var 'TEST_S3_SECRET' "$(terraform_output 'TEST_S3_SECRET')"
output_var 'TEST_S3_ROTATE_KEY' "$(terraform_output 'TEST_S3_ROTATE_KEY')"
output_var 'TEST_S3_ROTATE_SECRET' "$(terraform_output 'TEST_S3_ROTATE_SECRET')"
