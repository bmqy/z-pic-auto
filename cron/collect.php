<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
require __DIR__ . '/../app/bootstrap.php';
$results = (new Collector($repository, $config))->runAll();
foreach ($results as $result) {
    echo '[' . $result['status'] . '] ' . $result['source'] . ': ' . $result['message'] . PHP_EOL;
}
