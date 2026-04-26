<?php
/**
 * 发送http的json请求
 */
namespace addon\recycle\app\printer\PrinterLib\service;

use Exception;

class HttpClient
{
    /**
     * 发送http post json请求
     * 
     * @param string $url 请求url
     * @param string $jsonStr 发送的json字符串
     * @return array [http状态码, 返回内容]
     */
    public function http_post_json($url, $jsonStr)
    {
        // 设置超时时间更短，避免长时间等待
        $timeout = 5; // 降低为5秒，避免用户等待太久
        
        // 记录日志，便于调试
        // \think\facade\Log::record('【HttpClient】准备发送HTTP请求: ' . $url, 'debug');
        
        // 优先使用curl方式，更可靠
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 连接超时时间
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json;charset=UTF-8',
                'Content-Length: ' . strlen($jsonStr)
            )
        );
        
        // 支持自动重定向
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        // 设置DNS超时时间
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 120);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            // \think\facade\Log::record('【HttpClient】CURL请求失败: ' . $errorMsg, 'error');
            echo $errorMsg;
            throw new Exception($errorMsg);
        }
        
        curl_close($ch);
        
        // 记录请求结果
        // \think\facade\Log::record('【HttpClient】HTTP请求完成，状态码: ' . $httpCode, 'debug');
        if ($httpCode >= 400) {
            // \think\facade\Log::record('【HttpClient】HTTP请求失败，状态码: ' . $httpCode . '，响应: ' . $response, 'error');
        }
        
        return array($httpCode, $response);
    }
}

?>