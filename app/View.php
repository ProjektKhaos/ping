<?php

declare(strict_types=1);

namespace PingFloodWatch;

final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $templateFile = APP_ROOT . '/app/views/' . $template . '.php';
        if (!is_file($templateFile)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        require APP_ROOT . '/app/views/partials/header.php';
        require $templateFile;
        require APP_ROOT . '/app/views/partials/footer.php';
    }
}
