<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\diy;

class DiyLinkListener
{
    public function handle($data = [])
    {
        return include __DIR__ . '/../../dict/diy/links.php';
    }
}
