# Keboola Manage API PHP client 

[![Build Status](https://travis-ci.org/keboola/kbc-manage-api-php-client.svg?branch=master)](https://travis-ci.org/keboola/kbc-manage-api-php-client)

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

*Note: The test environment should be running a cronjob for `token-expirator` otherwise the `testTemporaryAccess` test will fail.*

Create file `.env` with environment variables`:

```bash
#REQUIRED - must be filled before running any test
KBC_MANAGE_API_URL=https://connection.keboola.com  
KBC_MANAGE_API_TOKEN=your_token
KBC_SUPER_API_TOKEN=your_token
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
 - `TEST_S3_FILES_BUCKET` - Name of file bucker on S3
 - `TEST_S3_KEY` - First AWS key
 - `TEST_S3_REGION` - Region where your S3 is located
 - `TEST_S3_SECRET` - First AWS secret
 
 Variable prefixed with _ROTATE_ are used for rotating credentials and they MUST be working credentials.
