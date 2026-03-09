<?php

namespace addon\sd_xiaoyuan\app\listener\diy;

class DiyTemplateListener
{
    public function handle($params)
    {
        $template = include __DIR__ . '/../../dict/diy/template.php';
        return $template;
    }
}
