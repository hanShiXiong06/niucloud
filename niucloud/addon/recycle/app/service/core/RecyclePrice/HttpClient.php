<?php
namespace addon\recycle\app\service\core\RecyclePrice;

class HttpClient {
    private $defaultHeaders = [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        "token"=> 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1aWQiOiI5OWFiMDY4MTMxN2JjMjRiMDMwMzIiLCJpYXQiOjE3MjgzNzI4MjR9.DW01Di9k2qUazeVZAaZ5tfU6r-WXwvDspMc3WGFzbPI'    ];

    public function setDefaultHeaders(array $headers) {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
    }

    public function get($url, array $queryParams = [],$host = 'https://api.suhuanji.com/') {
        // 构造查询字符串
        $queryString = http_build_query($queryParams);
        $fullUrl = $queryString ? $host.$url . '?' . $queryString : $url;

        // 初始化 cURL
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders());

        // 执行请求
        $response = curl_exec($ch);
        $this->handleCurlError($ch);

        // 关闭 cURL
        curl_close($ch);

        return json_decode($response, true);
    }

    public function post($url, array $data = [],$host = 'https://api.suhuanji.com') {
        // 初始化 cURL
        $ch = curl_init($host.$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders());

        // 执行请求
        $response = curl_exec($ch);
        $this->handleCurlError($ch);

        // 关闭 cURL
        curl_close($ch);

        return json_decode($response, true);
    }

    private function buildHeaders() {
        $headers = [];
        foreach ($this->defaultHeaders as $key => $value) {
            $headers[] = "$key: $value";
        }
        return $headers;
    }

    private function handleCurlError($ch) {
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }
    }
}
