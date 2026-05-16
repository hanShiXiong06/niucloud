<?php

namespace addon\recycle_quote_spider\app\listener\diy;

class DiyComponentListener
{
    public function handle($params)
    {
        return include __DIR__ . '/../../dict/diy/components.php';
    }
}
