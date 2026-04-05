<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . '/public' . $uri;

// Serve existing static files directly
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Forward to Symfony front controller
chdir(__DIR__ . '/public');
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';

include __DIR__ . '/public/index.php';
