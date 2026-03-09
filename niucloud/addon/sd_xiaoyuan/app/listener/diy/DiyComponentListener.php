<?php

namespace addon\sd_xiaoyuan\app\listener\diy;

use addon\sd_xiaoyuan\app\dict\diy\ComponentsDict;

class DiyComponentListener
{
    public function handle($params)
    {
        $components = include __DIR__ . '/../../dict/diy/components.php';
        return $components;
    }
}
