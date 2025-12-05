<?php

namespace addon\ai_image\app\service\core;


use core\exception\CommonException;

/**
 * OPENAI兼容大模型接入
 */
class Openai
{
    protected $config;

    /**
     * @param array $config
     * @return void
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->api_key = $config['ai_key'] ?? '';
        $this->version = $config['ai_model'];
        $this->domain = $config['ai_host'] ?? '';
        $this->temperature = floatval($config['temperature'] ?? '0.8');
        $this->max_tokens = intval($config['max_tokens'] ?? '5000');
    }

    public function sendText($msg)
    {
        $newMsg = [];
        foreach ($msg as $key => $value) {
            foreach ($value as $k => $v) {
                if ($k == 'system') {
                    $newKey = 'assistant';
                    $newMsg[$key][$newKey] = $v;
                } else {
                    $newMsg[$key][$k] = $v;
                }

            }
        }

        $data = [
            'model' => $this->config['ai_model'],
            'messages' => $newMsg,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'prompt' => ''
        ];
        $res = $this->sendRequest($this->domain, $data);
        return [
            'token' => $res['usage']['total_tokens'] ?? 0,
            'msg' => $res['choices'][0]['message']['content'] ?? ""
        ];
    }

    public function sendMsg($msg, $callback = null)
    {
        $newMsg = [];
        foreach ($msg as $key => $value) {
            foreach ($value as $k => $v) {
                if ($k == 'system') {
                    $newKey = 'assistant';
                    $newMsg[$key][$newKey] = $v;
                } else {
                    $newMsg[$key][$k] = $v;
                }

            }
        }
        $data = [
            'model' => $this->config['version'],
            'messages' => $newMsg,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'stream' => true,
            'prompt' => ''
        ];
        $this->sendRequest($this->domain, $data, $callback);
        return $this;
    }

    public function sendRequest($url, $data, $callback = null)
    {
        if($this->api_key=='') throw new CommonException('AI大模型接口未配置');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ));
        if ($callback) {
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);
        }
        $result = curl_exec($ch);
//        Log::write('deepseek大模型返回结果');
//        Log::write($url);
//        Log::write($result);
        if (curl_errno($ch)) {
            return [
                'status' => 'error',
                'message' => 'curl 错误信息: ' . curl_error($ch)
            ];
        }
        curl_close($ch);
        return json_decode($result, true);
    }

}