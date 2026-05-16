<?php

namespace addon\recycle\app\printer\PrinterLib\model;

use addon\recycle\app\printer\PrinterLib\util\Xputil;

class RestRequest
{
    /**
     * 开发者ID(芯烨云后台登录账号）
     */
    public $user;
    /**
     * 芯烨云后台开发者密钥
     */
    public $userKey;
    /**
     * 当前UNIX时间戳，10位，精确到秒
     */
    public $timestamp;
    /**
     * 对参数 user + UserKEY + timestamp 拼接后（+号表示连接符）进行SHA1加密得到签名，值为40位小写字符串，其中 UserKEY 为用户开发者密钥
     */
    public $sign;
    /**
     * debug=1返回非json格式的数据。仅测试时候使用
     */
    public $debug;
    
    /**
     * 打印机编号
     */
    public $sn;
    
    /**
     * 打印内容
     */
    public $content;
    
    /**
     * 打印份数
     */
    public $copies = 1;
    
    /**
     * 声音播放模式 0-不播放 1-播放
     */
    public $voice = 0;
    
    /**
     * 打印模式
     */
    public $mode = 0;
    
    /**
     * 水平偏移量
     */
    public $horizontalOffset = 0;
    
    /**
     * 垂直偏移量
     */
    public $verticalOffset = 0;

    function __construct($user = '', $userKey = '')
    {
        $this->user = $user;
        $this->userKey = $userKey;
        $this->debug = "0";
        $this->timestamp = time();
        // 自动生成签名
        $this->generateSign();
    }

    public function generateSign()
    {
        $this->sign = sha1($this->user . $this->userKey . $this->timestamp);
    }
}

?>