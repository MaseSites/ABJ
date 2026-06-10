<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/inventory.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/newsletter.php';
require_once __DIR__ . '/messages.php';
db(); // initialise connection & tables
