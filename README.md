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
        "php" : ">=5.4.0",
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
#REQUIRED - must be filled before running any test
KBC_MANAGE_API_URL=https://connection.keboola.com  
KBC_MANAGE_API_TOKEN=your_token
KBC_SUPER_API_TOKEN=your_token
KBC_MANAGE_API_SUPER_TOKEN_WITH_PROJECTS_READ_SCOPE=super_token_with_projects_read_scope
KBC_MANAGE_API_SUPER_TOKEN_WITHOUT_SCOPES=super_token_without_scopes
KBC_MANAGE_API_SUPER_TOKEN_WITH_DELETED_PROJECTS_READ_SCOPE=super_token_with_deleted_projects_read_scope
KBC_MANAGE_API_SUPER_TOKEN_WITH_UI_MANAGE_SCOPE=super_token_with_ui_manage_scope
KBC_TEST_MAINTAINER_ID=2
KBC_TEST_ADMIN_EMAIL=email_of_another_admin_having_mfa_disabled
KBC_TEST_ADMIN_TOKEN=token_of_another_admin_having_mfa_disabled
KBC_TEST_ADMIN_WITH_MFA_EMAIL=email_of_another_admin_having_mfa_enabled
KBC_TEST_ADMIN_WITH_MFA_TOKEN=token_of_another_admin_having_mfa_enabled

# OPTIONAL - required only for running file storage test (tests are skipped by default)
TEST_ABS_ACCOUNT_KEY=
TEST_ABS_ACCOUNT_NAME=
TEST_ABS_CONTAINER_NAME=
TEST_ABS_REGION=
TEST_ABS_ROTATE_ACCOUNT_KEY=
TEST_S3_ROTATE_KEY=
TEST_S3_ROTATE_SECRET=
TEST_S3_FILES_BUCKET=
TEST_S3_KEY=
TEST_S3_REGION=
TEST_S3_SECRET=
TEST_GCS_KEYFILE_JSON=
TEST_GCS_KEYFILE_ROTATE_JSON=
TEST_GCS_FILES_BUCKET=
TEST_GCS_REGION=

# OPTIONAL - required only for running testCreateStorageBackend, you have to have new snowflake backend and fill credentials into following environment variables

KBC_TEST_SNOWFLAKE_BACKEND_NAME=
KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD=
KBC_TEST_SNOWFLAKE_HOST=
KBC_TEST_SNOWFLAKE_WAREHOUSE=
KBC_TEST_SNOWFLAKE_BACKEND_REGION=

```

Source newly created file and run tests:

```bash
docker-compose run --rm dev composer tests
```

### Required variables

- `KBC_MANAGE_API_URL` - URL where Keboola Connection is running
- `KBC_MANAGE_API_TOKEN` - manage api token assigned to user **with** **superadmin** privileges. Can be created in Account Settings under the title Personal Access Tokens. User must have Multi-Factor Authentication disabled.
- `KBC_SUPER_API_TOKEN` - can be created in manage-apps on the Tokens tab
- `KBC_MANAGE_API_SUPER_TOKEN_WITH_UI_MANAGE_SCOPE` - can be created in manage-apps on the Tokens tab. Token must have "ui manage" scope
- `KBC_TEST_MAINTAINER_ID` - `id` of maintainer. Please create a new maintainer dedicated to test suite. All maintainer's organizations and projects all purged before tests!
- `KBC_TEST_ADMIN_EMAIL` - email address of another user without any organizations
- `KBC_TEST_ADMIN_TOKEN` - is also a Personal Access Token of user **without** **superadmin** privileges , but for a different user than that which has `KBC_MANAGE_API_TOKEN`. User must have Multi-Factor Authentication disabled.
- `KBC_TEST_ADMIN_WITH_MFA_EMAIL` - email address of another user without any organizations and having Multi-Factor Authentication enabled
- `KBC_TEST_ADMIN_WITH_MFA_TOKEN` - is also a Personal Access Token of user **without** **superadmin** privileges , but for a different user than that which has `KBC_MANAGE_API_TOKEN` or `KBC_TEST_ADMIN_TOKEN`

### Optional variables

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

## License

## Build OpenAPI document

Currently, we mainly document APIs in apiary.apib file. But we want to move to OpenAPI format. By calling following commands, the apiary.apib file will be translated to OpenAPI format and stored in file openapi.yml. Then you can commit it. We should put it in CI.

You need to install `apib2swagger` [tool](https://github.com/kminami/apib2swagger) .
```
$ npm install -g apib2swagger
```
Then run following commands 
```
$ cat apiary.apib | grep -v "X-KBC-ManageApiToken:" | apib2swagger -o openapi.yml -y --open-api-3 --info-title="Manage API" 
$ php AdjustApi.php
```
MIT licensed, see [LICENSE](./LICENSE) file.
