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
Tests requires valid Keboola Management API token and URL of API.

```
export KBC_MANAGE_API_URL=https://connection.keboola.com
export KBC_MANAGE_API_TOKEN=your_token
php vendor/bin/phpunit
```
