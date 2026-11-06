<?php

$publicDirectory = realpath(__DIR__.DIRECTORY_SEPARATOR.'public');
$storageDirectory = realpath(__DIR__.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public');
$requestedPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if (! is_string($publicDirectory) || ! is_string($storageDirectory) || ! is_string($requestedPath)) {
    require __DIR__.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php';

    return;
}

$requestedFile = realpath(
    $publicDirectory.DIRECTORY_SEPARATOR.ltrim(
        str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($requestedPath)),
        DIRECTORY_SEPARATOR
    )
);

if (
    $requestedFile !== false
    && is_file($requestedFile)
    && (
        str_starts_with($requestedFile, $publicDirectory.DIRECTORY_SEPARATOR)
        || str_starts_with($requestedFile, $storageDirectory.DIRECTORY_SEPARATOR)
    )
) {
    return false;
}

require $publicDirectory.DIRECTORY_SEPARATOR.'index.php';
