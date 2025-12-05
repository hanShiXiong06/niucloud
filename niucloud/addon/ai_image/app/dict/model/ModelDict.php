<?php

namespace addon\ai_image\app\dict\model;
class ModelDict
{

    public static function getModelDict()
    {
        $data = [
            [
                'name' => '一键去水印',
                'key' => 'tk_sy',
                'desc' => '上传图像一键去除水印',
                'logo' => '/addon/ai_image/icon.png',
                'prompt' => '请将图像的水印去除，并返回去除水印后的图像，需要生成高清图像',
                'status' => 1,
                'point' => 10,
                'is_upload_image' => 1,
                'limit_image' => 1,
                'is_prompt' => 0,
                'is_vip' => 0
            ],

            [
                'name' => '老照片修复',
                'key' => 'tk_lzp',
                'desc' => '上传图像需要修复的图像',
                'logo' => '/addon/ai_image/icon.png',
                'prompt' => '你是一个图像处理大师，请将图像的水印去除，进行老照片修复，并进行照片上色，需要生成高清图像',
                'status' => 1,
                'point' => 10,
                'is_upload_image' => 1,
                'limit_image' => 1,
                'is_prompt' => 0,
                'is_vip' => 0
            ],
            [
                'name' => '电商场景图',
                'key' => 'tk_dscj',
                'desc' => '上传图像需要扩充的场景',
                'logo' => '/addon/ai_image/icon.png',
                'prompt' => '你是一个图像处理大师，请将图像的水印去除，进行电商场景图像的生成，需要生成高清图像',
                'status' => 1,
                'point' => 10,
                'is_upload_image' => 1,
                'limit_image' => 1,
                'is_prompt' => 0,
                'is_vip' => 0
            ],
            [
                'name' => '证件照',
                'key' => 'tk_dscj',
                'desc' => '说明底色，多少寸即可生成',
                'logo' => '/addon/ai_image/icon.png',
                'prompt' => '你是一个图像处理大师，按照上面要求生成标准证件照，需要生成高清图像',
                'status' => 1,
                'point' => 10,
                'is_upload_image' => 1,
                'limit_image' => 1,
                'is_prompt' => 0,
                'is_vip' => 0
            ],
            [
                'name' => '文生图',
                'key' => 'tk_dscj',
                'desc' => '说明底色，多少寸即可生成',
                'logo' => '/addon/ai_image/icon.png',
                'prompt' => '根据用户的描述生成图像',
                'status' => 1,
                'point' => 10,
                'is_upload_image' => 0,
                'limit_image' => 0,
                'is_prompt' => 1,
                'is_vip' => 0
            ],
            [
                'name' => '创意生成',
                'key' => 'tk_txxg',
                'desc' => '上传图像按照您的要求进行修改',
                'logo' => '/addon/ai_image/icon.png',
                'prompt' => '根据用户的描述生成图像',
                'status' => 1,
                'point' => 10,
                'is_upload_image' => 1,
                'limit_image' => 9,
                'is_prompt' => 1,
                'is_vip' => 0
            ],
        ];
        return $data;
    }

}
