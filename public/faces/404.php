<?php
declare(strict_types=1);

http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

$errorPage = __DIR__ . '/404.html';

if (is_readable($errorPage)) {
    readfile($errorPage);
    exit;
}

echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>404 | neulanc</title><body><main><h1>Seite nicht gefunden</h1><p><a href="https://neulanc.com/">Zur Startseite</a></p></main></body></html>';
