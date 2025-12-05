<?php

namespace addon\ai_image\app\upgrade\v004;

class Upgrade
{

    public function handle()
    {
        $basedir = root_path() . 'addon' . DIRECTORY_SEPARATOR . 'ai_image' . DIRECTORY_SEPARATOR . 'movefile' . DIRECTORY_SEPARATOR . 'web';
        $rootpath = dirname(root_path()) . DIRECTORY_SEPARATOR . 'web';
        if (file_exists($basedir)) {
            dir_copy($basedir, $rootpath);
        }
        return true;
    }

}