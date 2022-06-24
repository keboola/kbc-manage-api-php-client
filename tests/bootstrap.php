<?php
error_reporting(-1);
date_default_timezone_set('UTC');

define('TEST_ABS_ACCOUNT_KEY', getenv('TEST_ABS_ACCOUNT_KEY'));
define('TEST_ABS_REGION', getenv('TEST_ABS_REGION'));
define('TEST_ABS_ACCOUNT_NAME', getenv('TEST_ABS_ACCOUNT_NAME'));
define('TEST_ABS_CONTAINER_NAME', getenv('TEST_ABS_CONTAINER_NAME'));
define('TEST_ABS_ROTATE_ACCOUNT_KEY', getenv('TEST_ABS_ROTATE_ACCOUNT_KEY'));

define('TEST_S3_ROTATE_KEY', getenv('TEST_S3_ROTATE_KEY'));
define('TEST_S3_ROTATE_SECRET', getenv('TEST_S3_ROTATE_SECRET'));
define('TEST_S3_FILES_BUCKET', getenv('TEST_S3_FILES_BUCKET'));
define('TEST_S3_KEY', getenv('TEST_S3_KEY'));
define('TEST_S3_REGION', getenv('TEST_S3_REGION'));
define('TEST_S3_SECRET', getenv('TEST_S3_SECRET'));
define('SUITE_NAME', getenv('SUITE_NAME'));

define('TEST_GCS_TYPE', getenv('TEST_GCS_TYPE'));
define('TEST_GCS_PROJECT_ID', getenv('TEST_GCS_PROJECT_ID'));
define('TEST_GCS_PRIVATE_KEY_ID', getenv('TEST_GCS_PRIVATE_KEY_ID'));
define('TEST_GCS_PRIVATE_KEY', getenv('TEST_GCS_PRIVATE_KEY'));
define('TEST_GCS_CLIENT_EMAIL', getenv('TEST_GCS_CLIENT_EMAIL'));
define('TEST_GCS_CLIENT_ID', getenv('TEST_GCS_CLIENT_ID'));
define('TEST_GCS_AUTH_URI', getenv('TEST_GCS_AUTH_URI'));
define('TEST_GCS_TOKEN_URI', getenv('TEST_GCS_TOKEN_URI'));
define('TEST_GCS_AUTH_PROVIDER_X509_CERT_URL', getenv('TEST_GCS_AUTH_PROVIDER_X509_CERT_URL'));
define('TEST_GCS_CLIENT_X509_CERT_URL', getenv('TEST_GCS_CLIENT_X509_CERT_URL'));
define('TEST_GCS_REGION', getenv('TEST_GCS_REGION'));

$loader = require __DIR__ . '/../vendor/autoload.php';
