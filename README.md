# Keboola Manage API PHP client

Simple PHP wrapper library for [Keboola Management REST API](http://docs.keboolamanagementapi1.apiary.io/#)

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
TODO

## Tests
Tests requires valid Keboola Management API tokens and an endpoint URL of the API test environment.
Also, the test environment should be running a cronjob for token-expirator otherwise the testTemporaryAccess test will fail

```
export KBC_MANAGE_API_URL=https://connection.keboola.com  
export KBC_MANAGE_API_TOKEN=your_token
export KBC_SUPER_API_TOKEN=your_token
export KBC_TEST_MAINTAINER_ID=1
export KBC_TEST_ADMIN_EMAIL=email_of_another_admin
export KBC_TEST_ADMIN_TOKEN=token_of_another_admin

php vendor/bin/phpunit
```

KBC_MANAGE_API_TOKEN can be created in Account Settings under the title Personal Access Tokens 
KBC_SUPER_API_TOKEN can be created in manage-apps on the Tokens tab
KBC_TEST_ADMIN_TOKEN is also a Personal Access Token, but for a different user than that which has KBC_MANAGE_API_TOKEN 
