<?php

$public = realpath(__DIR__.'/public');
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = realpath($public.$uri);

if ($uri !== '/' && $file !== false && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

require $public.'/index.php';
