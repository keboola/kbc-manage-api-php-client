#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source ./functions.sh

output_var 'TEST_GCS_REGION' "$(terraform_output 'TEST_GCS_REGION')"
output_var 'TEST_GCS_FILES_BUCKET' "$(terraform_output 'TEST_GCS_FILES_BUCKET')"
output_var_json 'TEST_GCS_KEYFILE_JSON' "$(terraform_output 'TEST_GCS_KEYFILE_JSON')"
output_var_json 'TEST_GCS_KEYFILE_ROTATE_JSON' "$(terraform_output 'TEST_GCS_KEYFILE_ROTATE_JSON')"
