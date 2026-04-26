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

use addon\recycle\app\printer\PrinterLib\model\RestRequest;
use addon\recycle\app\printer\PrinterLib\model\XPYunResp;
use addon\recycle\app\printer\PrinterLib\service\PrintService;
use core\exception\AdminException;

/**
 * 芯烨云打印机测试类
 */
class TestPrinter
{
    /**
     * 开发者ID
     */
    protected $userName = '';

    /**
     * 开发者密钥
     */
    protected $userKey = '';

    /**
     * 打印机编号
     */
    protected $sn = '';

    /**
     * 构造函数
     * @param string $userName 开发者ID
     * @param string $userKey 开发者密钥
     * @param string $sn 打印机编号
     */
    public function __construct($userName = '', $userKey = '', $sn = '', $content = '')
    {
        // 如果未传参数，使用演示账号
        if($userName == '' || $userKey == '' || $sn == '' ){ 
            throw new AdminException('打印机信息不完整');
        }

        $this->userName = $userName ;
        $this->userKey = $userKey ;
        $this->sn = $sn;
        $this->content = $content ?: '<PAGE>
                    <SIZE>40,30</SIZE>
                    <TEXT x="8" y="8" w="1" h="1" r="0">
                    这是由 芯烨云 打印的标签 后台默认（测试标签），
                    </TEXT>
                    </PAGE>
                    ';
    }

  

    /**
     * 测试标签打印功能
     * @param array $device_data 设备数据
     * @return XPYunResp
     */
    public function testLabelPrint($device_data = [])
    {

        // 检查传入的数据格式，如果是内容格式，直接使用
        if (isset($device_data['content'])) {
            return $this->doPrintLabel($device_data['content'], $device_data['copies'] ?? 1);
        }
        
        // 如果没有传入设备数据，使用默认示例数据
        if (empty($device_data)) {
            $device_data = [
                'order_no' => date('YmdHis'),
                'category_name' => '手机',
                'brand' => 'iPhone',
                'model' => '14 Pro Max',
                'color' => '深空黑',
                'memory' => '8GB',
                'capacity' => '256GB',
                'imei' => '123456789012345',
                'sn' => 'ABCDEF123456',
                'check_result' => '质检通过',
                'final_price' => '5000',
                'clock'=>'on',
                'time' => date('Y-m-d H:i:s')
            ];
        }
        
        // 构建标签内容
        $content = "<PAGE>";
        $content .= "<SIZE>60,45</SIZE>";  // 设置标签尺寸为60*45mm
        $content .= "<TEXT x=\"5\" y=\"5\" w=\"50\" h=\"6\">设备回收质检标签</TEXT>";
        
        // 添加水平线
        $content .= "<LINE x1=\"5\" y1=\"12\" x2=\"55\" y2=\"12\" /></LINE>";
        
        // 顶部区域 - 基本信息
        $y_pos = 15;
        $line_height = 7;
        
        // 设备唯一标识信息放在最前面
        if (!empty($device_data['imei'])) {
            $content .= "<TEXT x=\"5\" y=\"{$y_pos}\" w=\"50\" h=\"6\">IMEI: {$device_data['imei']}</TEXT>";
            $y_pos += $line_height;
        }
        
        // 添加设备品牌型号颜色合并展示
        $device_name = '';
        if (!empty($device_data['brand'])) $device_name .= $device_data['brand'] . ' ';
        if (!empty($device_data['model'])) $device_name .= $device_data['model'] . ' ';
        if (!empty($device_data['color'])) $device_name .= $device_data['color'];
        
        if (!empty($device_name)) {
            $content .= "<TEXT x=\"5\" y=\"{$y_pos}\" w=\"50\" h=\"6\">设备: {$device_name}</TEXT>";
            $y_pos += $line_height;
        }
        
        // 添加质检人员信息
        if (!empty($device_data['staff_name'])) {
            $content .= "<TEXT x=\"5\" y=\"{$y_pos}\" w=\"50\" h=\"6\">质检员: {$device_data['staff_name']}</TEXT>";
            $y_pos += $line_height;
        }
        
        // 添加时间信息
        $time = !empty($device_data['time']) ? $device_data['time'] : date('Y-m-d H:i:s');
        $content .= "<TEXT x=\"5\" y=\"{$y_pos}\" w=\"50\" h=\"6\">时间: {$time}</TEXT>";
        $y_pos += $line_height;
        
        // 添加质检结果
        if (!empty($device_data['check_result'])) {
            $content .= "<TEXT x=\"5\" y=\"{$y_pos}\" w=\"50\" h=\"10\">质检: {$device_data['check_result']}</TEXT>";
            $y_pos += 12; // 质检结果可能较长，给更多空间
        }
        
        // 添加条形码
        if (!empty($device_data['imei'])) {
            $content .= "<BC128 x=\"5\" y=\"{$y_pos}\" h=\"10\">{$device_data['imei']}</BC128>";
        }
        
        $content .= "</PAGE>";
        
        return $this->doPrintLabel($content, $device_data['copies'] ?? 1);
    }

    /**
     * 执行小票打印
     * @param string $content 打印内容
     * @param int $copies 打印份数
     * @return XPYunResp
     */
    private function doPrint($content, $copies = 1)
    {
        $service = new PrintService();
        
        $request = new RestRequest($this->userName, $this->userKey);
        $request->sn = $this->sn;
        $request->content = $content;
        $request->copies = $copies;
        $request->voice = 0;
        $request->mode = 0;
        
        $result = $service->xpYunPrint($request);
        
        return $result;
    }

    /**
     * 执行标签打印
     * @param string $content 打印内容
     * @param int $copies 打印份数
     * @return XPYunResp
     */
    private function doPrintLabel($content, $copies = 1)
    {
        $service = new PrintService();
        
        $request = new RestRequest($this->userName, $this->userKey);
        $request->sn = $this->sn;
        $request->content = $this->content;

            /*
            其他模版
            <PAGE>
            <SIZE>40,30</SIZE>
            <SEQ x="8" y="8" xe="296" ye="232" s="3">
            <L x="88" y="8" w="3" h="224">
            <TEXT x="24" y="16" w="1" h="1" r="0">编号</TEXT>
            <TEXT x="96" y="16" w="1" h="1" r="0">20220419</TEXT>
            <L x="8" y="48" w="284" h="3">
            <TEXT x="24" y="56" w="1" h="1" r="0">名称</TEXT>
            <TEXT x="96" y="56" w="1" h="1" r="0">芯烨云</TEXT>
            <L x="8" y="88" w="284" h="3">
            <TEXT x="24" y="96" w="1" h="1" r="0">规格</TEXT>
            <TEXT x="96" y="96" w="1" h="1" r="0">V3.90</TEXT>
            <L x="8" y="128" w="284" h="3">
            <TEXT x="24" y="136" w="1" h="1" r="0">数量</TEXT>
            <TEXT x="96" y="136" w="1" h="1" r="0">1</TEXT>
            <L x="8" y="168" w="180" h="3">
            <TEXT x="24" y="176" w="1" h="1" r="0">日期</TEXT>
            <TEXT x="96" y="176" w="1" h="1" r="0">04/19</TEXT>
            <L x="184" y="128" w="3" h="104">
            <QRC x="195" y="136" s="3" e="L">https://www.xpyun.net/open/index.html</QRC>
            </PAGE>
            <PAGE>
            <TEXT x="8" y="8" w="1" h="1" r="0">打印条形码：</TEXT>
            <BC128 x="16" y="32" h="32" s="1" n="2" w="2" r="0">12345678</BC128>
            </PAGE>
            <PAGE>
            <TEXT x="8" y="8" w="1" h="1" r="0">打印二维码宽度128：</TEXT>
            <QRC x="16" y="40" s="4" e="L">https://www.xpyun.net</QRC>
            </PAGE>
            */
        $request->copies = $copies;
        $request->horizontalOffset = 0;
        $request->verticalOffset = 0;
        
        try {
            $result = $service->xpYunPrintLabel($request);
            return $result;
        } catch (\Exception $e) {
            // 构造错误响应
            throw new AdminException('打印异常: ' . $e->getMessage());
            
        }
    }
}