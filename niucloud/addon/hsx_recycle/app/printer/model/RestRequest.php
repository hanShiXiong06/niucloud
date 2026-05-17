<?php

namespace Xpyun\model;

class RestRequest
{
    /**
     * 开发者ID（芯烨云后台注册账号）或第三方代开发提供的开发者ID
     */
    public $user;

    /**
     * 芯烨云开发者密钥 或 代开发提供的开发者密钥
     */
    public $userKey;

    /**
     * 打印机编号，必须要在芯烨云平台注册打印机或调用添加接口新增打印机
     */
    public $sn;

    /**
     * 打印内容
     */
    public $content;

    /**
     * 声音播放模式，0 不播放，1 播放， 默认为0
     */
    public $voice = 0;

    /**
     * 打印模式 0 标准模式 1 标签模式 2 黑标模式 3 切刀模式，默认为0
     */
    public $mode = 0;

    /**
     * 打印份数，默认为1
     */
    public $copies = 1;

    /**
     * 水平偏移量, 仅标签模式有效
     */
    public $horizontalOffset = 0;

    /**
     * 垂直偏移量, 仅标签模式有效
     */
    public $verticalOffset = 0;

    /**
     * 订单ID，可用于查询订单是否打印成功
     */
    public $orderId;
} 