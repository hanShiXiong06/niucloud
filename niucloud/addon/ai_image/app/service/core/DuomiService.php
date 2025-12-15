<?php

namespace addon\ai_image\app\service\core;
use core\base\BaseApiService;
use core\exception\CommonException;
use Exception;

/**
 * 讯飞语音合成
 */
class DuomiService extends BaseApiService
{

    public function __construct($config)
    {
        parent::__construct();
        $this->config = $config;
    }

    public function create($data)
    {
        //图像编辑
        $channel = $data['channel'] == '' ? 'nano-banana' : $data['channel'];
        if ($data['image_urls']) {
            $api_data = [
                'prompt' => $data['prompt'],
                'image_urls' => $data['image_urls'],
                'aspect_ratio' => $data['aspect_ratio'],
                'model' => $channel,
                'image_size' => $data['image_size'] ?? '1K',
            ];
            $res = $this->execute('/api/gemini/nano-banana-edit', $api_data);
        } else {
            $api_data = [
                'prompt' => $data['prompt'],
                'aspect_ratio' => $data['aspect_ratio'],
                'model' => $channel,
                'image_size' => $data['image_size'] ?? '1K',
            ];
            $res = $this->execute('/api/gemini/nano-banana', $api_data);
        }
        if (isset($res['code']) && $res['code'] == 200) {
            return $res;
        } else {
            throw new CommonException($res['msg'] ?? '创建任务失败');
        }
    }

    public function query($id)
    {
        $res = $this->http_get('/api/gemini/nano-banana/' . $id, []);
        return $res;
    }

    public function http_get($url, $data = null)
    {
        $fullUrl = 'http://duomiapi.com' . $url;
        $apikey = $this->config['duomi_key'];
        $curl = curl_init();
        $headers = [
            'Authorization: ' . $apikey,
            'Content-Type: multipart/form-data'
        ];
        curl_setopt($curl, CURLOPT_URL, $fullUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        }
        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new CommonException($err);
        } else {
            return json_decode($result, true);
        }
    }

    /**
     * @Notes:公共执行任务方法
     * @Interface execute
     * @param $url
     * @param $data
     * @return mixed
     * @throws Exception
     * @author: TK
     * @Time: 2024/5/17   下午1:29
     */
    public function execute($url, $data)
    {
        $url = 'http://duomiapi.com' . $url;
        $apikey = $this->config['duomi_key'];
        $data['key'] = $apikey;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: multipart/form-data'
        ]);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new Exception($err);
        } else {
            return json_decode($result, true);
        }
    }
}