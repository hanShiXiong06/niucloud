<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\printer;

use core\printer\BasePrinter;
use addon\recycle\app\printer\PrinterLib\model\RestRequest;
use addon\recycle\app\printer\PrinterLib\service\PrintService;

/**
 * 芯烨云打印机
 */
class XpyunPrinter
{
    /**
     * @var string 开发者ID
     */
    protected $userName;

    /**
     * @var string 开发者密钥
     */
    protected $userKey;

    /**
     * @var string 打印机编号
     */
    protected $sn;

    /**
     * 构造函数
     * @param array $config 配置数组
     */
    public function __construct(array $config = [])
    {
        $this->userName = $config['user_name'] ?? '';
        $this->userKey = $config['user_key'] ?? '';
        $this->sn = $config['sn'] ?? '';
    }

    /**
     * 打印小票
     * @param array $data
     * @return mixed
     */
    public function printTicket(array $data)
    {
        $service = new PrintService();
        
        $request = new RestRequest($this->userName, $this->userKey);
        $request->sn = $this->sn;
        $request->content = $data['content'] ?? '';
        $request->copies = $data['copies'] ?? 1;
        
        // 声音播放模式 0:不播放 1:播放
        $request->voice = $data['voice'] ?? 0;
        
        // 支持多种打印模式
        $request->mode = $data['mode'] ?? 0;
        
        return $service->xpYunPrint($request);
    }

    /**
     * 打印标签
     * @param array $data
     * @return mixed
     */
    public function printLabel(array $data)
    {
        $service = new PrintService();
        
        $request = new RestRequest($this->userName, $this->userKey);
        $request->sn = $this->sn;
        $request->content = $data['content'] ?? '';
        $request->copies = $data['copies'] ?? 1;
        
        // 水平偏移量, 默认为0
        $request->horizontalOffset = $data['horizontal_offset'] ?? 0;
        
        // 垂直偏移量, 默认为0
        $request->verticalOffset = $data['vertical_offset'] ?? 0;
        

        return $service->xpYunPrintLabel($request);
    }
} 