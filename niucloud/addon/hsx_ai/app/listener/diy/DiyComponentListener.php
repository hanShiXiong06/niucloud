<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\listener\diy;

final class DiyComponentListener
{
    public function handle($params): array
    {
        return include __DIR__ . '/../../dict/diy/components.php';
    }
}
