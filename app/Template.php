<?php
declare(strict_types=1);

final class Template
{
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException('模板不存在：' . $view);
        }
        require __DIR__ . '/../views/layout.php';
    }
}
