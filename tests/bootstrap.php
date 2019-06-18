<?php
error_reporting(-1);
date_default_timezone_set('UTC');

define('TEST_ABS_ACCOUNT_KEY', getenv('TEST_ABS_ACCOUNT_KEY'));
define('TEST_ABS_ACCOUNT_NAME', getenv('TEST_ABS_ACCOUNT_NAME'));
define('TEST_ABS_CONTAINER_NAME', getenv('TEST_ABS_CONTAINER_NAME'));
define('TEST_ABS_ROTATE_ACCOUNT_KEY', getenv('TEST_ABS_ROTATE_ACCOUNT_KEY'));

define('TEST_S3_ROTATE_KEY', getenv('TEST_S3_ROTATE_KEY'));
define('TEST_S3_ROTATE_SECRET', getenv('TEST_S3_ROTATE_SECRET'));
define('TEST_S3_FILES_BUCKET', getenv('TEST_S3_FILES_BUCKET'));
define('TEST_S3_KEY', getenv('TEST_S3_KEY'));
define('TEST_S3_REGION', getenv('TEST_S3_REGION'));
define('TEST_S3_SECRET', getenv('TEST_S3_SECRET'));

$loader = require __DIR__ . '/../vendor/autoload.php';
