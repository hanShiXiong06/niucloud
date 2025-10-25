<?php

namespace addon\recycle\app\printer\PrinterLib\service;

use addon\recycle\app\printer\PrinterLib\model\XPYunResp;

class PrintService
{
    // 当前使用的API地址
    private const BASE_URL = 'https://open.xpyun.net/api/openapi';
    // 备用API地址(如果主地址连接失败，可以尝试此地址)
    private const BACKUP_BASE_URL = 'http://open.xpyun.net/api/openapi';

    private function xpyunPostJson($url, $request)
    {
        // 添加一个静态标志，用于防止重复打印
        static $isRetry = false;
        
        $jsonRequest = json_encode($request);
        $client = new HttpClient();

        try {
            // 如果是在重试，记录日志
            if ($isRetry) {
                \think\facade\Log::record('【PrintService】使用备用地址尝试打印: ' . $url, 'debug');
            }
            
            list($returnCode, $returnContent) = $client->http_post_json($url, $jsonRequest);
            
            $result = new XPYunResp();
            $result->httpStatusCode = $returnCode;
            $result->content = json_decode($returnContent);
            
            return $result;
        } catch (\Exception $e) {
            // 如果使用主地址失败，尝试使用备用地址
            if (strpos($url, self::BASE_URL) === 0 && !$isRetry) {
                $backupUrl = str_replace(self::BASE_URL, self::BACKUP_BASE_URL, $url);
                try {
                    // 设置重试标志，避免无限递归
                    $isRetry = true;
                    // \think\facade\Log::record('【PrintService】主地址连接失败，使用备用地址: ' . $backupUrl, 'debug');
                    
                    list($returnCode, $returnContent) = $client->http_post_json($backupUrl, $jsonRequest);
                    
                    $result = new XPYunResp();
                    $result->httpStatusCode = $returnCode;
                    $result->content = json_decode($returnContent);
                    
                    // 重置重试标志
                    $isRetry = false;
                    return $result;
                } catch (\Exception $e2) {
                    // 重置重试标志
                    $isRetry = false;
                    
                    // 两种地址都失败，创建一个错误响应
                    $result = new XPYunResp();
                    $result->httpStatusCode = 0;
                    $result->content = (object)[
                        'code' => -1,
                        'msg' => '网络连接失败: ' . $e->getMessage() . ', 备用连接失败: ' . $e2->getMessage()
                    ];
                    return $result;
                }
            } else {
                // 创建一个错误响应
                $result = new XPYunResp();
                $result->httpStatusCode = 0;
                $result->content = (object)[
                    'code' => -1,
                    'msg' => '网络连接失败: ' . $e->getMessage()
                ];
                return $result;
            }
        }
    }

    /**
     * 1.批量添加打印机
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunAddPrinters($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/addPrinters";
        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 2.设置打印机语音类型
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunSetVoiceType($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/setVoiceType";
        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 3.打印小票订单
     * @param $restRequest 打印订单信息
     * @return XPYunResp
     */
    public function xpYunPrint($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/print";

        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 4.打印标签订单
     * @param $restRequest 打印订单信息
     * @return XPYunResp
     */
    public function xpYunPrintLabel($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/printLabel";
        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 5.批量删除打印机
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunDelPrinters($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/delPrinters";

        return $this->xpyunPostJson($url, $restRequest);
    }


    /**
     * 6.修改打印机信息
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunUpdatePrinter($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/updPrinter";
        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 7.清空待打印队列
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunDelPrinterQueue($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/delPrinterQueue";
        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 8.查询订单是否打印成功
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunQueryOrderState($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/queryOrderState";

        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 9.查询打印机某天的订单统计数
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunQueryOrderStatis($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/queryOrderStatis";
        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 10.查询打印机状态
     *
     * 0、离线 1、在线正常 2、在线不正常
     * 备注：异常一般是无纸，离线的判断是打印机与服务器失去联系超过30秒
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunQueryPrinterStatus($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/queryPrinterStatus";

        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 10.批量查询打印机状态
     *
     * 0、离线 1、在线正常 2、在线不正常
     * 备注：异常一般是无纸，离线的判断是打印机与服务器失去联系超过30秒
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunQueryPrintersStatus($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/queryPrintersStatus";

        return $this->xpyunPostJson($url, $restRequest);
    }

    /**
     * 11.云喇叭播放语音
     * @param $restRequest 播放语音信息
     * @return XPYunResp
     */
    public function xpYunPlayVoice($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/playVoice";

        return $this->xpyunPostJson($url, $restRequest);
    }
	
    /**
     * 12.POS指令
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunPos($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/pos";
    
        return $this->xpyunPostJson($url, $restRequest);
    }
	
    /**
     * 13.钱箱控制
     * @param $restRequest
     * @return XPYunResp
     */
    public function xpYunControlBox($restRequest)
    {
        $url = self::BASE_URL . "/xprinter/controlBox";
    
        return $this->xpyunPostJson($url, $restRequest);
    }
}

?>