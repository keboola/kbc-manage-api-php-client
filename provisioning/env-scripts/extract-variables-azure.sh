#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source ./functions.sh

# output variables
output_var 'TEST_ABS_ACCOUNT_KEY' $(terraform_output 'TEST_ABS_ACCOUNT_KEY')
output_var 'TEST_ABS_ACCOUNT_NAME' $(terraform_output 'TEST_ABS_ACCOUNT_NAME')
output_var 'TEST_ABS_CONTAINER_NAME' $(terraform_output 'TEST_ABS_CONTAINER_NAME')
output_var 'TEST_ABS_REGION' $(terraform_output 'TEST_ABS_REGION')
output_var 'TEST_ABS_ROTATE_ACCOUNT_KEY' $(terraform_output 'TEST_ABS_ROTATE_ACCOUNT_KEY')

