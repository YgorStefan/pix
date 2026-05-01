<?php
declare(strict_types=1);

ini_set('display_errors', 'on');
error_reporting(E_ALL);
date_default_timezone_set('UTC');

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require BASE_PATH . '/vendor/autoload.php';
