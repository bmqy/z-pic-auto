<?php
declare(strict_types=1);

return [
    'site_name' => 'Z-Pic Auto 本地调试站',
    'site_url' => 'http://localhost:18080',
    'site_description' => '本地自动采集冒烟测试站点。',
    'admin_token' => 'local-debug-token',
    'database' => [
        'driver' => 'sqlite',
        'path' => __DIR__ . '/../storage/zpic.sqlite',
    ],
    'download_images' => true,
    'sources' => [
        [
            'name' => '本地 JSON 冒烟源',
            'type' => 'json',
            'url' => 'http://127.0.0.1/tests/fixtures/galleries.json',
            'enabled' => true,
            'license_note' => '仅用于本地测试。',
        ],
    ],
];
