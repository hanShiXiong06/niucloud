<?php

namespace addon\tk_jhkd\app\service\core\common;

use core\base\BaseApiService;
use Exception;

use think\facade\Cache;

/**
 * webhook群钩子通知封装
 */
class WebhookService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();

    }

    public function sendMsg($config,$text,$key='tk_jhkd_notice_msg')
    {

        if($config['min']==''){
            $config['min'] = 0;
        }
        if (!Cache::get($key)) {
            if ($config['webhook_type'] == 0) {
                $this->sendQw($config,$text);
            }
            if ($config['webhook_type'] == 1) {
                $this->sendFs($config,$text);
            }
            if ($config['webhook_type'] == 2) {
                $this->sendDd($config,$text);
            }
            Cache::set($key, 60 * $config['min']??'');
        }
        return true;
    }

    public function sendQw($config,$text)
    {
        if($config['qwurl']==''){
            return true;
        }
        $data = [
            'msgtype' => 'text',
            'text' => [
                'content' => $text . '---' . date('Y-m-d H:i:s'),
                'mentioned_list' => ["@all"],
            ]
        ];
        $data = json_encode($data);
        $this->http_post($config['qwurl'], $data);
        return true;
    }

    public function sendFs($config,$text)
    {
        if($config['fsurl']==''){
            return true;
        }
        $data = [
            "msg_type" => "text",
            "content" => [
                "text" => $text . '---' . date('Y-m-d H:i:s')
            ]
        ];
        $data = json_encode($data);
        $this->http_post($config['fsurl'], $data);
        return true;
    }

    public function sendDd($config,$text)
    {
        if($config['ddurl']==''){
            return true;
        }
        $data = [
            'msgtype' => 'text',
            'text' => [
                "content" => $text . '---' . date('Y-m-d H:i:s')
            ],
        ];
        $data = json_encode($data);
        $this->http_post($config['ddurl'], $data);
        return true;
    }

    public function http_post($url, $data = null, $headers = false)
    {
        if($url==''){
            return true;
        }
        $curl = curl_init();
        //设置header头
        if ($headers !== false) {
            if (!is_array($headers)) {
                $headers = array($headers);
            }
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        } else {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        }
        curl_setopt($curl, CURLOPT_URL, $url);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
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