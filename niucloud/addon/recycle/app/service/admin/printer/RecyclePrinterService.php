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

namespace addon\recycle\app\service\admin\printer;

use addon\recycle\app\model\printer\RecyclePrinter;
use addon\recycle\app\printer\TestPrinter;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use core\exception\AdminException;

/**
 * 回收打印机服务类（精简版）
 * Class RecyclePrinterService
 * @package addon\recycle\app\service\admin\printer
 */
class RecyclePrinterService extends BaseAdminService
{
    /**
     * 模型实例
     * @var RecyclePrinter
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecyclePrinter();
    }

    /**
     * 获取打印机品牌列表
     * @return array
     */
    public function getBrandList()
    {
        return [
            [
                'brand' => 'xpyun',
                'name' => '芯烨云打印机',
                'desc' => '支持小票打印和标签打印',
                'logo' => 'https://www.xpyun.net/img/logo.png',
                'support' => ['ticket', 'label']
            ]
        ];
    }

    /**
     * 获取用户绑定的打印机
     * @return array
     * @throws DbException
     */
    public function getUserPrinter()
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['status', '=', 1]
        ];

        $printer = $this->model->where($where)->findOrEmpty()->toArray();
            
        return $printer;
    }

    /**
     * 绑定打印机
     * @param array $data
     * @return array
     * @throws CommonException
     */
    public function bindPrinter(array $data)
    {
        try {
          

            // 将 这个uid 下的所有的打印机 停用
            $this->model->where([
                ['site_id', '=', $this->site_id],
                ['uid', '=', $this->uid]
            ])->update([
                'status' => 0,
                'is_default' => 0,
                'update_time' => time()
            ]);
            
            // 新增绑定
            $data['site_id'] = $this->site_id;
            $data['uid'] = $this->uid;
            $data['create_time'] = time();
            $data['update_time'] = time();
            $data['status'] = 1;
            $data['type'] = RecyclePrinter::TYPE_LABEL; // 默认标签打印机
            
            $this->model->save($data);
            return ['printer_id' => $this->model->printer_id];
        } catch (DbException $e) {
            throw new CommonException('绑定失败：' . $e->getMessage());
        }
    }

    /**
     * 解绑打印机
     * @return bool
     * @throws CommonException
     */
    public function unbindPrinter()
    {
        $printer = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid]
        ])->findOrEmpty();
        
        if ($printer->isEmpty()) {
            throw new CommonException('未绑定打印机');
        }
        
        return $printer->delete();
    }

    /**
     * 打印标签
     * @param array $data 打印数据
     * @return array
     */
    public function printLabel(array $data)
    {


   
        // 获取用户绑定的打印机
        $printer = $this->getUserPrinter();
        if (empty($printer)) {
            return [
                'code' => -1,
                'message' => '未绑定打印机，请先绑定打印机'
            ];
        }
        
        try {
            // 构建打印内容
            $content = $this->buildLabelContent($data);
            
            // 创建打印机实例
            $printerInstance = new TestPrinter(
                $printer['user_name'],
                $printer['user_key'],
                $printer['sn']
            );
            
            // 执行打印
            // $result = $printerInstance->testLabelPrint([
            //     'content' => $content,
            //     'copies' => $data['copies'] ?? 1
            // ]);
            
           
            
            // 解析结果
            if ($result->httpStatusCode != 200) {
                return [
                    'code' => -1,
                    'message' => '请求失败，HTTP状态码：' . $result->httpStatusCode
                ];
            }
            
            if (empty($result->content) || $result->content->code != 0) {
                return [
                    'code' => $result->content->code ?? -1,
                    'message' => $result->content->msg ?? '打印失败'
                ];
            }
            
            return [
                'code' => 0,
                'message' => '打印成功',
                'data' => $result->content
            ];
        } catch (\Exception $e) {
            // 记录异常日志
           
            
            return [
                'code' => -1,
                'message' => '打印异常: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 构建标签内容
     * @param array $data 打印数据
     * @return string
     */
    private function buildLabelContent(array $data)
    {

   
        // 构建标签内容
        $content = '<PAGE>';
        $content .= '<SIZE>60,40</SIZE>';
        $content .= '<SEQ x="8" y="8" xe="520" ye="312" s="3">';
        
        // 订单号
        $content .= '<TEXT x="24" y="16" w="1" h="1" r="0">订单号</TEXT>';
        $content .= '<TEXT x="96" y="16" w="1" h="1" r="0">' . ($data['order_id'] ?? '测试订单') . '</TEXT>';
        
        // 设备名称
        $device_name = ($data['brand'] ?? '') . ' ' . 
                      ($data['model'] ?? '') . ' ' . 
                      ($data['memory'] ?? '') . ' ' . 
                      ($data['color'] ?? '');
        $content .= '<TEXT x="24" y="56" w="1" h="1" r="0">设备名:</TEXT>';
        $content .= '<TEXT x="96" y="56" w="1" h="1" r="0">' . $device_name . '</TEXT>';
        
        // IMEI
        $content .= '<TEXT x="24" y="86" w="1" h="1" r="0">IMEI:</TEXT>';
        $content .= '<TEXT x="96" y="86" w="1" h="1" r="0">' . ($data['imei'] ?? '000000000000000') . '</TEXT>';
        
       
        
        // 质检结果 一行 17个字 要通过循环 换行 每行 +30的高度
        $check_result = $data['check_result'] ?? '质检通过';
        $content .= '<TEXT x="24" y="116" w="1" h="1" r="0">质检:</TEXT>';

        // 将质检结果按每行17个字符分割，每行y坐标增加30
        $lines = [];
      
        $result_length = mb_strlen($check_result, 'UTF-8');
        $line_length = 21  ;
        // 分割结果为多行
        for ($i = 0; $i < $result_length; $i += $line_length) {
            // 第一行只取17个字符
            if ($i == 0) {
                $lines[] = mb_substr($check_result, $i, 17, 'UTF-8');
            } else {
                $lines[] = mb_substr($check_result, $i, $line_length, 'UTF-8');
            }
        }
        
        // 如果没有分割出行，至少保留一行
        if (empty($lines)) {
            $lines[] = $check_result;
        }
        
        // 输出每一行
        $base_y = 116; // 基础Y坐标
        foreach ($lines as $index => $line) {
            // 如果是第一行 x坐标 = 96 否则 x坐标 = 24
            $x = $index == 0 ? 96 : 24;
            // 每行最大字符数
            $y = $base_y + ($index * 30); // 每行Y坐标增加30
            $content .= '<TEXT x="' . $x . '" y="' . $y . '" w="1" h="1" r="0">' . $line . '</TEXT>';
        }
        
        // 调整日期的Y坐标，确保它在质检结果下方
        $date_y = $base_y + (count($lines) * 30) + 20; // 最后一行质检结果下方20单位
        $date_y = max($date_y, 276); // 确保不小于原来的坐标
        
        // 日期
        
        $content .= '<TEXT x="24" y="' . $date_y . '" w="1" h="1" r="0">' . $data['check_at'] . '</TEXT>';
         // 质检员
         $content .= '<TEXT x="300" y="' . $date_y . '" w="1" h="1" r="0">质检员:</TEXT>';
         $content .= '<TEXT x="400" y="' . $date_y . '" w="1" h="1" r="0">' . ($data['staff_name'] ?? $this->uid ?? '质检员') . '</TEXT>';
        
        // 二维码，也需要调整Y坐标
        $qrcode_y = max(236, $date_y - 40); // 保持在日期上方一定距离
        $content .= '<QRC x="395" y="' . 10 . '" s="2" e="L">' . ($data['imei'] ?? '000000000000000') . '</QRC>';
        
        $content .= '</SEQ>';
        $content .= '</PAGE>';
        
        return $content;
    }
    
    /**
     * 打印设备标签
     * @param int $device_id 设备ID
     * @return array
     */
    // 废弃⚠️
    public function printDeviceLabel(int $device_id)
    {
        try {
            // 获取设备信息
            $deviceService = new \addon\recycle\app\service\admin\recycle_order\RecycleDeviceService();
        
            $deviceInfo = $deviceService->  getInfo($device_id ,['order_id','model','imei','check_result','check_at','check_uid']);
            
            if (empty($deviceInfo)) {
                return [
                    'code' => -1,
                    'message' => '设备不存在'
                ];
            }
            
            // 准备打印数据
            $printData = [
                'order_id' => $deviceInfo['order_id'] ?? '',
                'brand' => $deviceInfo['brand'] ?? '',
                'model' => $deviceInfo['model'] ?? '',
                'color' => $deviceInfo['color'] ?? '',
                'memory' => $deviceInfo['memory'] ?? '',
                'imei' => $deviceInfo['imei'] ?? '',
                'check_result' => $deviceInfo['check_result'] ?? '质检通过',
                'staff_name' => $deviceInfo['checkUser']['username'] ?? '质检员'.$deviceInfo['check_uid'],
                'check_at' => date('Y-m-d H:i:s', $deviceInfo['check_at']) ?? date('Y-m-d H:i:s')
            ];
            
            // 执行打印
            // return $this->printLabel($printData);
            return $printData;
        } catch (\Exception $e) {
            return [
                'code' => -1,
                'message' => '打印设备标签失败: ' . $e->getMessage()
            ];
        }
    }
    
    
    
    /**
     * 测试打印机
     * @param array $data
     * @return array
     */
    public function testPrint(array $data)
    {
        if (empty($data['sn']) || empty($data['user_name']) || empty($data['user_key'])) {
            return [
                'code' => -1,
                'message' => '打印机信息不完整'
            ];
        }
        try {
            $printer = new TestPrinter($data['user_name'], $data['user_key'], $data['sn'] ,$data['content']);
            $result = $printer->testLabelPrint();            
            if ($result->httpStatusCode != 200  ) {
                return [
                    'code' => -1,
                    'message' => '请求失败，HTTP状态码：' . $result->httpStatusCode
                ];
            }
            if (empty($result->content) || $result->content->code != 0) {
                throw new AdminException('打印失败') ;
            }
            return [
                'code' => 0,
                'message' => '测试打印成功'
            ];
        } catch (\Exception $e) {
            return [
                'code' => -1,
                'message' => '测试打印异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 添加打印机
     * @param array $data
     * @return array
     */
    public function add(array $data)
    {
        // 先停用当前用户的所有打印机
        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid]
        ])->update([
            'status' => 0,
            'is_default' => 0,
            'update_time' => time()
        ]);
        
        // 添加新打印机并设置为默认
        $data['site_id'] = $this->site_id;
        $data['uid'] = $this->uid;
        $data['create_time'] = time();
        $data['update_time'] = time();
        $data['status'] = 1;
        $data['is_default'] = 1;
        
        $this->model->save($data);
        
        return true;
    }

    /**
     * 获取打印机列表
     * @return array
     */
    public function lists()
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid]
        ];
        $field = 'printer_id,printer_name,brand,type,status,is_default,create_time,update_time,sn,user_key,user_name';
        
        $search_model = $this->model->where($where)->field($field)->order('create_time desc');
        $list = $this->pageQuery($search_model);
        
        // 处理返回数据
        if (!empty($list['data'])) {
            $brandList = $this->getBrandList();
            $brandDict = [];
            foreach ($brandList as $brand) {
                $brandDict[$brand['brand']] = $brand['name'];
            }
            
            foreach ($list['data'] as &$item) {
                // 添加品牌名称
                $item['brand_name'] = $brandDict[$item['brand']] ?? $item['brand'];
                
                // 添加类型名称
                $item['type_name'] = $item['type'] === 'label' ? '标签打印机' : '小票打印机';
                
            }
        }
        
        return $list;
    }

    /**
     * 获取打印机详情
     * @param int $id
     * @return array
     */
    public function info(int $id)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['printer_id', '=', $id]
        ];
        $field = 'printer_id,printer_name,brand,type,status,create_time,update_time,sn,user_key,user_name';
        
        $info = $this->model->field($field)->where($where)->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 更新打印机信息
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['printer_id', '=', $id]
        ];
        
        $data['update_time'] = time();
        
        $this->model->where($where)->update($data);
        return true;
    }

    /**
     * 删除打印机
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['printer_id', '=', $id]
        ];
        
        $this->model->where($where)->delete();
        return true;
    }

    /**
     * 切换打印机状态（激活/停用）
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function toggleStatus(int $id, int $status)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid]
        ];
        
        // 如果要激活当前打印机，先停用其他所有打印机
        if ($status == 1) {
            $this->model->where($where)->update([
                'status' => 0, 
                'is_default' => 0, 
                'update_time' => time()
            ]);
        }
        
        // 更新指定打印机状态
        $where[] = ['printer_id', '=', $id];
        $updateData = [
            'status' => $status, 
            'update_time' => time()
        ];
        
        // 如果激活，同时设置为默认打印机
        if ($status == 1) {
            $updateData['is_default'] = 1;
        } else {
            $updateData['is_default'] = 0;
        }
        
        $this->model->where($where)->update($updateData);
        
        return true;
    }
} 