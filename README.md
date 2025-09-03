# Keboola Manage API PHP client 

[![Build on master](https://github.com/keboola/kbc-manage-api-php-client/actions/workflows/master.yml/badge.svg?branch=master)](https://github.com/keboola/kbc-manage-api-php-client/actions/workflows/master.yml)

Simple PHP wrapper library for [Keboola Management REST API](http://docs.keboolamanagementapi.apiary.io/#)

## Installation

Library is available as composer package.
To start using composer in your project follow these steps:

**Install composer**

```bash
curl -s http://getcomposer.org/installer | php
mv ./composer.phar ~/bin/composer # or /usr/local/bin/composer
```

**Create composer.json file in your project root folder:**
```json
{
    "require": {
        "php" : ">=8.1",
        "keboola/kbc-manage-api-php-client": "~0.0"
    }
}
```

**Install package:**

```bash
composer install
```

**Add autoloader in your bootstrap script:**

```php
require 'vendor/autoload.php';
```

Read more in [Composer documentation](http://getcomposer.org/doc/01-basic-usage.md)

## Usage examples


```php
require 'vendor/autoload.php';

use Keboola\ManageApi\Client;

$client = new Client([
    'token' => getenv('MY_MANAGE_TOKEN'),
    'url' => 'https://connnection.keboola.com',
]);

$project = $client->getProject(234);
```

## Tests


The main purpose of these test is "black box" test driven development of Keboola Connection. These test guards the API implementation.
You can run these tests only against non-production environments.

Tests requires valid Keboola Management API tokens and an endpoint URL of the API test environment.

*Note: For automated tests, the tests are run again three times by default if they fail. For local development this would be quite annoying, 
so you can disable this by creating new file `phpunit-retry.xml` from `phpunit-retry.xml.dist`*

*Note: The test environment should be running a cronjob for `token-expirator` otherwise the `testTemporaryAccess` test will fail.*

Create file `.env` with environment variables`:

```bash
# REQUIRED - must be filled before running any test
KBC_MANAGE_API_URL=https://connection.keboola.com  # URL where Keboola Connection is running
KBC_MANAGE_API_TOKEN=your_token # manage api token assigned to user **with** **superadmin** privileges. Can be created in Account Settings under the title Personal Access Tokens. User must have Multi-Factor Authentication disabled.
KBC_SUPER_API_TOKEN=your_token # can be created in manage-apps on the Tokens tab
KBC_MANAGE_API_SUPER_TOKEN_WITH_PROJECTS_READ_SCOPE=super_token_with_projects_read_scope # can be created in manage-apps on the Tokens tab. Token must have "projects:read" scope
KBC_MANAGE_API_SUPER_TOKEN_WITHOUT_SCOPES=super_token_without_scopes
KBC_MANAGE_API_SUPER_TOKEN_WITH_DELETED_PROJECTS_READ_SCOPE=super_token_with_deleted_projects_read_scope # can be created in manage-apps on the Tokens tab. Token must have "deleted-projects:read" scope
KBC_MANAGE_API_SUPER_TOKEN_WITH_UI_MANAGE_SCOPE=super_token_with_ui_manage_scope # can be created in manage-apps on the Tokens tab. Token must have "connection:ui-manage" scope
KBC_MANAGE_API_SUPER_TOKEN_WITH_ORGANIZATIONS_READ_SCOPE=super_token_with_organizations_read # can be created in manage-apps on the Tokens tab. Token must have "organizations:read" scope
KBC_MANAGE_API_SUPER_TOKEN_WITH_STORAGE_TOKENS_SCOPE=super_token_with_storage_tokens_scope # can be created in manage-apps on the Tokens tab. Token must have "manage:storage-tokens" scope
KBC_TEST_MAINTAINER_ID=id # `id` of maintainer. Please create a new maintainer dedicated to test suite. All maintainer's organizations and projects all purged before tests!
KBC_TEST_ADMIN_EMAIL=email_of_another_admin_having_mfa_disabled # email address of another user without any organizations
KBC_TEST_ADMIN_TOKEN=token_of_another_admin_having_mfa_disabled # is also a Personal Access Token of user **without** **superadmin** privileges , but for a different user than that which has `KBC_MANAGE_API_TOKEN`. User must have Multi-Factor Authentication disabled.
KBC_TEST_ADMIN_WITH_MFA_EMAIL=email_of_another_admin_having_mfa_enabled # email address of another user without any organizations and having Multi-Factor Authentication enabled
KBC_TEST_ADMIN_WITH_MFA_TOKEN=token_of_another_admin_having_mfa_enabled # is also a Personal Access Token of user **without** **superadmin** privileges , but for a different user than that which has `KBC_MANAGE_API_TOKEN` or `KBC_TEST_ADMIN_TOKEN`

# OPTIONAL - required only for running testCreateStorageBackend, you have to have new snowflake backend and fill credentials into following environment variables
KBC_TEST_SNOWFLAKE_BACKEND_NAME=
KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD=
KBC_TEST_SNOWFLAKE_HOST=
KBC_TEST_SNOWFLAKE_WAREHOUSE=
KBC_TEST_SNOWFLAKE_BACKEND_REGION=
```

If any of the environment variables are not set, the tests will fail with an error message explaining which variable is missing.

Run tests
```bash
docker-compose run --rm dev composer tests
```


## File Storage tests

### Setup cloud resources for File Storage tests

#### Prerequisites:

- configured and logged in az, aws and gcp CLI tools
  - `az login`
  - `aws sso login --profile=<your_profile>`
  - `gcloud auth application-default login`
- installed terraform (https://www.terraform.io) and jq (https://stedolan.github.io/jq) to setup local env

```shell
# create terraform.tfvars file from terraform.tfvars.dist
cp ./provisioning/terraform.tfvars.dist ./provisioning/terraform.tfvars

# set terraform variables
name_prefix = "<your_nick>" # your name/nickname to make your resource unique & recognizable, allowed characters are [a-zA-Z0-9-]
gcp_storage_location = "<your_region>" # region of GCP resources
gcp_project_id = "<your_project_id>" # GCP project id
gcp_project_region = "<your_region>" # region of GCP project
azure_storage_location = "<your_region>" # region of Azure resources 
azure_tenant_id = "<your_tenant_id>" # Azure tenant id
azure_subscription_id = "<your_subscription_id>" # Azure subscription id
aws_profile = "<your_profile>"  # your aws profile name
aws_region = "<your_region>" # region of AWS resources
aws_account = "<your_account_id>" # your aws account id


# Initialize terraform
terraform -chdir=./provisioning init
# Create resources
terraform -chdir=./provisioning apply

# For destroying resources run 
terraform -chdir=./provisioning apply -destroy

# Setup terraform variables to .env file (will be prepended to .env file)
# For Azure
./provisioning/update-env.sh azure
# For Aws
./provisioning/update-env.sh aws
# For GCP
./provisioning/update-env.sh gcp
```

### Required variables for File Storage tests

These variables are used for testing file storage. You have to copy these values from Azure and AWS portal.  
 - `TEST_ABS_ACCOUNT_KEY` - First secret key for Azure Storage account
 - `TEST_ABS_ACCOUNT_NAME` - Name of Azure Storage account
 - `TEST_ABS_CONTAINER_NAME` - Name of container created inside Azure Storage Account
 - `TEST_ABS_REGION` - Name of region where Azure Storage Account is located. (Note: AWS region list is used)
 - `TEST_ABS_ROTATE_ACCOUNT_KEY` - Second secret key for Azure Storage account
 - `TEST_S3_ROTATE_KEY` - Second AWS key
 - `TEST_S3_ROTATE_SECRET` - Second AWS secret
 - `TEST_S3_FILES_BUCKET` - Name of file bucket on S3
 - `TEST_S3_KEY` - First AWS key
 - `TEST_S3_REGION` - Region where your S3 is located
 - `TEST_S3_SECRET` - First AWS secret
 - `TEST_GCS_KEYFILE_JSON` - First GCS key file contents as json string  
 - `TEST_GCS_KEYFILE_ROTATE_JSON` - Second GCS key file contents as json string used for testing rotation 
 - `TEST_GCS_FILES_BUCKET` - Name of file bucket on GCS 
 - `TEST_GCS_REGION` - Region whare GCS is located
 
 Variable prefixed with _ROTATE_ are used for rotating credentials and they MUST be working credentials.


### Run File Storage tests

```bash
docker-compose run --rm dev composer tests-file-storage
```


## Build OpenAPI document

Currently, we mainly document APIs in apiary.apib file. But we want to move to OpenAPI format. By calling following commands, the apiary.apib file will be translated to OpenAPI format and stored in file openapi.yml. Then you can commit it. We should put it in CI.

You need to install `apib2swagger` [tool](https://github.com/kminami/apib2swagger) .
```
$ npm install -g apib2swagger
```
Then run following commands 
```
$ cat apiary.apib | grep -v "X-KBC-ManageApiToken:" | apib2swagger -o openapi.yml -y --open-api-3 --info-title="Manage API" 
$ php AdjustOpenApi.php
```

## License

MIT licensed, see [LICENSE](./LICENSE) file.
