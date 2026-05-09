<?php

namespace addon\sd_xiaoyuan\app\listener\diy;

class DiyPagesListener
{
    public function handle($params)
    {
        $pages = include __DIR__ . '/../../dict/diy/pages.php';
        return $pages;
    }
}
