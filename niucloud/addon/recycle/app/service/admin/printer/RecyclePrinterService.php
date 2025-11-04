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
use addon\recycle\app\service\admin\printer\template\PrinterApiService;
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

    /**
     * 打印机API服务
     * @var PrinterApiService
     */
    protected $printerApiService;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecyclePrinter();
        $this->printerApiService = new PrinterApiService();
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
            // 将当前用户下的所有打印机停用
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
            $errorMsg = '未绑定打印机，请先绑定打印机';
            \think\facade\Log::error('【打印标签】' . $errorMsg, [
                'site_id' => $this->site_id,
                'uid' => $this->uid
            ]);
            return [
                'code' => -1,
                'message' => $errorMsg
            ];
        }
        
        try {
            // 构建打印内容
            $content = $this->buildLabelContent($data);
            
            // 创建打印机实例
            $printerInstance = new TestPrinter(
                $printer['user_name'],
                $printer['user_key'],
                $printer['sn'],
                $content
            );
            
            // 执行打印
            $result = $printerInstance->testLabelPrint();
            
            // 解析结果
            if ($result->httpStatusCode != 200) {
                $errorMsg = '请求失败，HTTP状态码：' . $result->httpStatusCode;
                \think\facade\Log::error('【打印标签】' . $errorMsg, [
                    'sn' => $printer['sn'],
                    'user_name' => $printer['user_name'],
                    'http_status_code' => $result->httpStatusCode,
                    'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'print_content' => substr($content, 0, 500)
                ]);
                return [
                    'code' => -1,
                    'message' => $errorMsg
                ];
            }
            
            if (empty($result->content) || $result->content->code != 0) {
                $apiMsg = $result->content->msg ?? '未知错误';
                $apiCode = $result->content->code ?? -1;
                $errorMsg = '打印失败：' . $apiMsg . ' (错误码: ' . $apiCode . ')';
                
                // 记录详细错误日志
                \think\facade\Log::error('【打印标签】' . $errorMsg, [
                    'sn' => $printer['sn'],
                    'user_name' => $printer['user_name'],
                    'api_code' => $apiCode,
                    'api_message' => $apiMsg,
                    'api_response' => json_encode($result->content, JSON_UNESCAPED_UNICODE),
                    'print_content' => substr($content, 0, 500) // 只记录前500个字符，避免日志过大
                ]);
                
                return [
                    'code' => $apiCode,
                    'message' => $errorMsg
                ];
            }
            
            return [
                'code' => 0,
                'message' => '打印成功',
                'data' => $result->content
            ];
        } catch (\Exception $e) {
            $errorMsg = '打印异常: ' . $e->getMessage();
            \think\facade\Log::error('【打印标签】' . $errorMsg, [
                'sn' => $printer['sn'] ?? '',
                'user_name' => $printer['user_name'] ?? '',
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString()
            ]);
            return [
                'code' => -1,
                'message' => $errorMsg
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
        
        // 质检结果 - 按每行17个字符分割，每行y坐标增加30
        $check_result = $data['check_result'] ?? '质检通过';
        $content .= '<TEXT x="24" y="116" w="1" h="1" r="0">质检:</TEXT>';

        // 将质检结果按每行17个字符分割，每行y坐标增加30
        $lines = [];
        $result_length = mb_strlen($check_result, 'UTF-8');
        $line_length = 21;
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
        $content .= '<TEXT x="24" y="' . $date_y . '" w="1" h="1" r="0">' . ($data['check_at'] ?? date('Y-m-d H:i:s')) . '</TEXT>';
        
         // 质检员
         $content .= '<TEXT x="300" y="' . $date_y . '" w="1" h="1" r="0">质检员:</TEXT>';
        $content .= '<TEXT x="400" y="' . $date_y . '" w="1" h="1" r="0">' . ($data['staff_name'] ?? '质检员') . '</TEXT>';
        
        // 二维码，也需要调整Y坐标
        $qrcode_y = max(236, $date_y - 40); // 保持在日期上方一定距离
        $content .= '<QRC x="395" y="' . 10 . '" s="2" e="L">' . ($data['imei'] ?? '000000000000000') . '</QRC>';
        
        $content .= '</SEQ>';
        $content .= '</PAGE>';
        
        return $content;
    }
    
    /**
     * 测试打印机
     * @param array $data
     * @return array
     */
    public function testPrint(array $data)
    {
        if (empty($data['sn']) || empty($data['user_name']) || empty($data['user_key'])) {
            $errorMsg = '打印机信息不完整';
            \think\facade\Log::error('【测试打印】' . $errorMsg, [
                'data' => $data
            ]);
            return [
                'code' => -1,
                'message' => $errorMsg
            ];
        }
        try {
            $printer = new TestPrinter($data['user_name'], $data['user_key'], $data['sn'], $data['content'] ?? '');
            $result = $printer->testLabelPrint();            
            if ($result->httpStatusCode != 200) {
                $errorMsg = '请求失败，HTTP状态码：' . $result->httpStatusCode;
                \think\facade\Log::error('【测试打印】' . $errorMsg, [
                    'sn' => $data['sn'],
                    'user_name' => $data['user_name'],
                    'http_status_code' => $result->httpStatusCode,
                    'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'request_content' => $data['content'] ?? ''
                ]);
                return [
                    'code' => -1,
                    'message' => $errorMsg
                ];
            }
            if (empty($result->content) || $result->content->code != 0) {
                $apiMsg = $result->content->msg ?? '未知错误';
                $apiCode = $result->content->code ?? '未知';
                $errorMsg = '打印失败：' . $apiMsg . ' (错误码: ' . $apiCode . ')';
                
                // 记录详细错误日志
                \think\facade\Log::error('【测试打印】' . $errorMsg, [
                    'sn' => $data['sn'],
                    'user_name' => $data['user_name'],
                    'api_code' => $apiCode,
                    'api_message' => $apiMsg,
                    'api_response' => json_encode($result->content, JSON_UNESCAPED_UNICODE),
                    'request_content' => $data['content'] ?? ''
                ]);
                
                throw new AdminException($errorMsg);
            }
            return [
                'code' => 0,
                'message' => '测试打印成功'
            ];
        } catch (\Exception $e) {
            $errorMsg = '测试打印异常: ' . $e->getMessage();
            \think\facade\Log::error('【测试打印】' . $errorMsg, [
                'sn' => $data['sn'] ?? '',
                'user_name' => $data['user_name'] ?? '',
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString()
            ]);
            return [
                'code' => -1,
                'message' => $errorMsg
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
        // 先调用芯烨云API添加打印机
        $printers = [[
            'sn' => $data['sn'],
            'name' => $data['printer_name'] ?? ''
        ]];
        
        $apiResult = $this->printerApiService->addPrinters(
            $data['user_name'],
            $data['user_key'],
            $printers
        );
        
        // 如果API调用失败，抛出异常
        if (!$apiResult['success']) {
            throw new CommonException('添加打印机失败：' . $apiResult['message']);
        }
        
        // API调用成功，保存到数据库
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
     * @param bool $withStatus 是否查询在线状态
     * @return array
     */
    public function lists($withStatus = false)
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
            
            // 如果需要查询状态，批量查询
            $statusList = [];
            if ($withStatus && !empty($list['data'])) {
                $statusList = $this->batchQueryPrinterStatus($list['data']);
            }
            
            foreach ($list['data'] as &$item) {
                // 添加品牌名称
                $item['brand_name'] = $brandDict[$item['brand']] ?? $item['brand'];
                
                // 添加类型名称
                $item['type_name'] = $item['type'] === 'label' ? '标签打印机' : '小票打印机';
                
                // 如果需要查询状态，添加状态信息
                if ($withStatus) {
                    $printerId = $item['printer_id'];
                    if (isset($statusList[$printerId])) {
                        $item['printer_status'] = $statusList[$printerId]['status'];
                        $item['printer_status_text'] = $statusList[$printerId]['status_text'];
                    } else {
                        $item['printer_status'] = null;
                        $item['printer_status_text'] = '未查询';
                    }
                }
            }
        }
        
        return $list;
    }
    
    /**
     * 批量查询打印机状态
     * @param array $printers 打印机列表
     * @return array 返回格式：[printer_id => ['status' => 1, 'status_text' => '在线正常'], ...]
     */
    private function batchQueryPrinterStatus(array $printers): array
    {
        $statusList = [];
        
        // 按用户分组，因为不同用户的user_name和user_key可能不同
        $groupedPrinters = [];
        foreach ($printers as $printer) {
            $key = $printer['user_name'] . '|' . $printer['user_key'];
            if (!isset($groupedPrinters[$key])) {
                $groupedPrinters[$key] = [
                    'user_name' => $printer['user_name'],
                    'user_key' => $printer['user_key'],
                    'printers' => []
                ];
            }
            $groupedPrinters[$key]['printers'][] = $printer;
        }
        
        // 逐个分组查询状态
        foreach ($groupedPrinters as $group) {
            $snList = array_column($group['printers'], 'sn');
            
            // 批量查询状态
            $result = $this->printerApiService->queryPrintersStatus(
                $group['user_name'],
                $group['user_key'],
                $snList
            );
            
            if ($result['success'] && !empty($result['data'])) {
                // 将SN映射到printer_id
                $snToPrinterId = [];
                foreach ($group['printers'] as $printer) {
                    $snToPrinterId[$printer['sn']] = $printer['printer_id'];
                }
                
                // 构建状态列表
                foreach ($result['data'] as $statusItem) {
                    $sn = $statusItem['sn'] ?? '';
                    if (isset($snToPrinterId[$sn])) {
                        $printerId = $snToPrinterId[$sn];
                        $statusList[$printerId] = [
                            'status' => $statusItem['status'],
                            'status_text' => $statusItem['status_text']
                        ];
                    }
                }
            }
        }
        
        return $statusList;
    }
    
    /**
     * 批量查询打印机状态（公开方法）
     * @param array $printerIds 打印机ID列表
     * @return array
     */
    public function batchQueryStatus(array $printerIds): array
    {
        if (empty($printerIds)) {
            return [];
        }
        
        // 获取打印机信息
        $where = [
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['printer_id', 'in', $printerIds]
        ];
        $printers = $this->model->where($where)->field('printer_id,printer_name,sn,user_name,user_key')->select()->toArray();
        
        if (empty($printers)) {
            return [];
        }
        
        $statusList = $this->batchQueryPrinterStatus($printers);
        
        // 格式化返回结果
        $result = [];
        foreach ($printers as $printer) {
            $printerId = $printer['printer_id'];
            $result[] = [
                'printer_id' => $printerId,
                'printer_name' => $printer['printer_name'],
                'sn' => $printer['sn'],
                'status' => $statusList[$printerId]['status'] ?? null,
                'status_text' => $statusList[$printerId]['status_text'] ?? '未查询'
            ];
        }
        
        return $result;
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
        // 获取原有打印机信息
        $where = [
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['printer_id', '=', $id]
        ];
        
        $printer = $this->model->where($where)->findOrEmpty();
        if ($printer->isEmpty()) {
            throw new CommonException('打印机不存在');
        }
        
        $oldSn = $printer->sn;
        $newSn = $data['sn'] ?? $oldSn;
        $newName = $data['printer_name'] ?? $printer->printer_name;
        
        // 如果SN或名称发生变化，调用芯烨云API更新
        if ($newSn !== $oldSn || $newName !== $printer->printer_name) {
            // 如果SN变化，需要先删除旧的，再添加新的
            if ($newSn !== $oldSn) {
                // 删除旧的打印机（如果失败不影响，因为可能未注册）
                if (!empty($oldSn) && !empty($data['user_name'] ?? $printer->user_name) && !empty($data['user_key'] ?? $printer->user_key)) {
                    $deleteResult = $this->printerApiService->deletePrinters(
                        $data['user_name'] ?? $printer->user_name,
                        $data['user_key'] ?? $printer->user_key,
                        [$oldSn]
                    );
                    
                    // 记录删除结果，但不阻止继续
                    if (!$deleteResult['success'] && !isset($deleteResult['not_registered'])) {
                        \think\facade\Log::warning('更新打印机：删除旧打印机失败', [
                            'old_sn' => $oldSn,
                            'error' => $deleteResult['message']
                        ]);
                    }
                }
                
                // 添加新的打印机
                $addResult = $this->printerApiService->addPrinters(
                    $data['user_name'] ?? $printer->user_name,
                    $data['user_key'] ?? $printer->user_key,
                    [['sn' => $newSn, 'name' => $newName]]
                );
                
                if (!$addResult['success']) {
                    throw new CommonException('更新打印机失败：' . $addResult['message']);
                }
            } else {
                // 只更新名称
                $updateResult = $this->printerApiService->updatePrinter(
                    $data['user_name'] ?? $printer->user_name,
                    $data['user_key'] ?? $printer->user_key,
                    $newSn,
                    $newName
                );
                
                // 如果更新失败且是未注册错误，尝试重新注册
                if (!$updateResult['success']) {
                    // 检查是否是未注册错误，如果是，尝试重新注册
                    if (isset($updateResult['re_registered']) && $updateResult['re_registered']) {
                        // 重新注册成功，继续更新数据库
                    } else {
                        // 其他错误，抛出异常
                        throw new CommonException('更新打印机失败：' . $updateResult['message']);
                    }
                }
            }
        }
        
        // API调用成功，更新数据库
        $data['update_time'] = time();
        $updateResult_db = $this->model->where($where)->update($data);
        
        if ($updateResult_db === false) {
            throw new CommonException('更新数据库记录失败');
        }
        
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
        
        // 获取打印机信息
        $printer = $this->model->where($where)->findOrEmpty();
        if ($printer->isEmpty()) {
            throw new CommonException('打印机不存在');
        }
        
        // 调用芯烨云API删除打印机
        if (!empty($printer->sn) && !empty($printer->user_name) && !empty($printer->user_key)) {
            $deleteResult = $this->printerApiService->deletePrinters(
                $printer->user_name,
                $printer->user_key,
                [$printer->sn]
            );
            
            // 如果API调用失败，检查是否是打印机未注册的错误
            if (!$deleteResult['success']) {
                // 检查是否是未注册的错误（这种情况下可以删除本地记录）
                if (isset($deleteResult['not_registered']) && $deleteResult['not_registered']) {
                    // 打印机未注册，记录日志但继续删除本地记录
                    \think\facade\Log::info('删除打印机：打印机在芯烨云未注册，仅删除本地记录', [
                        'printer_id' => $id,
                        'sn' => $printer->sn
                    ]);
                } else {
                    // 其他错误，记录日志但不阻止删除
                    \think\facade\Log::warning('删除打印机API调用失败', [
                        'printer_id' => $id,
                        'sn' => $printer->sn,
                        'error' => $deleteResult['message']
                    ]);
                }
            }
        }
        
        // 删除数据库记录（无论API调用是否成功）
        // 使用模型实例的delete方法（支持软删除）
        // delete()方法返回bool，成功返回true，失败返回false
        // 如果模型实例删除失败，尝试使用where条件删除
        try {
            $deleteResult_db = $printer->delete();
            
            if ($deleteResult_db === false) {
                // 如果实例删除失败，使用where条件删除（避免模型状态问题）
                $deleteResult_db = $this->model->where($where)->delete();
                
                if ($deleteResult_db === false || $deleteResult_db <= 0) {
                    throw new CommonException('删除数据库记录失败，请检查记录是否存在');
                }
            }
        } catch (\Exception $e) {
            throw new CommonException('删除数据库记录失败：' . $e->getMessage());
        }
        
        return true;
    }

    /**
     * 查询打印机状态
     * @param int $id 打印机ID
     * @return array
     */
    public function queryPrinterStatus(int $id): array
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['printer_id', '=', $id]
        ];
        
        $printer = $this->model->where($where)->findOrEmpty()->toArray();
        
        if (empty($printer)) {
            throw new CommonException('打印机不存在');
        }
        
        if (empty($printer['sn']) || empty($printer['user_name']) || empty($printer['user_key'])) {
            throw new CommonException('打印机配置不完整');
        }
        
        return $this->printerApiService->queryPrinterStatus(
            $printer['user_name'],
            $printer['user_key'],
            $printer['sn']
        );
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