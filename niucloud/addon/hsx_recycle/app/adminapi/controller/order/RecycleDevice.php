<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\order;

use addon\hsx_recycle\app\dict\order\RecycleRefurbishmentDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\service\admin\order\RecycleDeviceService;
use addon\hsx_recycle\app\service\admin\order\RecycleDeviceCostAdjustmentService;
use addon\hsx_recycle\app\service\admin\printer\RecyclePrinterTemplateService;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService;
use addon\hsx_recycle\app\validate\RecycleDeviceValidate;
use core\base\BaseAdminController;
use think\App;

/**
 * 回收设备管理控制器
 * Class RecycleDevice
 * @package addon\hsx_recycle\app\adminapi\controller\recycle_order
 */
class RecycleDevice extends BaseAdminController
{
    /**
     * @var RecycleDeviceService
     */
    protected $service;

    /**
     * @var RecycleDeviceValidate
     */
    protected $validate;

    /**
     * @var RecyclePrinterTemplateService
     */
    protected $template_service;

    /**
     * @var RecycleDeviceCostAdjustmentService
     */
    protected $cost_adjustment_service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleDeviceService();
        $this->validate = new RecycleDeviceValidate();
        $this->template_service = new RecyclePrinterTemplateService();
        $this->cost_adjustment_service = new RecycleDeviceCostAdjustmentService();
    }

    /**
     * 获取设备列表
     * @return mixed
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['device_name', ''],
            ['imei', ''],
            ['model', ''],
            ['status', ''],
            ['create_at', []],
            ['page', 1],
            ['limit', 10]
        ]);

        // 参数验证
        $this->validate->scene('list')->check($data);

        return success($this->service->getPage($data));
    }

    /**
     * 获取设备信息
     * @param int $id
     * @return mixed
     */
    public function getInfo(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);

        $data = $this->service->getInfo($id);

        // 获取设备完整操作链路日志：设备主流程 + 关联代卖流程
        $data['logs'] = $this->service->getTimelineLogs($id, 50);

        // 按 UI 区块组织的干净结构,前端只读 view.base / view.price / view.check / view.logs
        $data['view'] = $this->service->buildDetailView($data);

        return success($data);
    }

    /**
     * 设备详情(详情弹窗专用):按 UI 区块组织 + 裁掉弹窗用不到的重字段。
     * 单独接口,不影响 getInfo 的其它调用方。
     * @param int $id
     * @return mixed
     */
    public function detailView(int $id)
    {
        $this->validate->scene('detail')->check(['id' => $id]);

        $data = $this->service->getInfo($id);
        $data['logs'] = $this->service->getTimelineLogs($id, 50);

        // 区块结构
        $data['view'] = $this->service->buildDetailView($data);

        // 只保留详情弹窗 + 子组件(DeviceInfoCard / DownstreamProgress)实际读取的字段,其余一律不返回。
        // 字段清单来自对组件的实读统计,改组件时同步维护此清单。
        $keep = [
            // 基础信息
            'id', 'model', 'imei', 'sn', 'status', 'status_name', 'create_at',
            'check_summary', 'capacity', 'color', 'system_version', 'warranty_info',
            // 价格信息
            'initial_price', 'final_price', 'sell_price', 'price_remark', 'final_status',
            'cost_adjust_amount', 'cost_adjust_count', 'last_cost_adjust_time', 'last_cost_adjust_no',
            // 下游流转
            'downstream_stage', 'downstream_sale_price', 'downstream_stage_at', 'downstream_erp_asset_id',
            'pay_status', 'dispose_status',
            // 质检信息
            'check_at', 'checkUser', 'check_result', 'check_result_seller', 'check_result_buyer',
            'check_images', 'check_images_buyer', 'check_images_seller',
            'check_images_thumb_small', 'check_images_seller_thumb_small', 'check_images_buyer_thumb_small',
            'remark',
        ];
        $slim = [];
        foreach ($keep as $k) {
            if (array_key_exists($k, $data)) {
                $slim[$k] = $data[$k];
            }
        }
        // info 精简:只留 sn 与 basic_labels(去掉 check_meta、goods_category 等)
        $srcInfo = is_array($data['info'] ?? null) ? $data['info'] : [];
        $slimInfo = [];
        if (isset($srcInfo['sn'])) {
            $slimInfo['sn'] = $srcInfo['sn'];
        }
        if (isset($srcInfo['basic_labels'])) {
            $slimInfo['basic_labels'] = $srcInfo['basic_labels'];
        }
        $slim['info'] = $slimInfo;
        $slim['logs'] = $data['logs'] ?? [];
        $slim['view'] = $data['view'] ?? [];

        return success($slim);
    }

    /**
     * 获取设备成本调整记录
     * @param int $id
     * @return mixed
     */
    public function costAdjustLogs(int $id)
    {
        $this->validate->scene('detail')->check(['id' => $id]);

        return success($this->cost_adjustment_service->lists($id));
    }

    /**
     * 获取设备成本调整能力
     * @param int $id
     * @return mixed
     */
    public function costAdjustAbility(int $id)
    {
        $this->validate->scene('detail')->check(['id' => $id]);

        return success($this->cost_adjustment_service->ability($id));
    }

    /**
     * 已打款设备成本调整
     * @param int $id
     * @return mixed
     */
    public function costAdjust(int $id)
    {
        $data = $this->request->params([
            ['adjust_type', ''],
            ['direction', 'decrease'],
            ['adjust_amount', 0],
            ['reason', ''],
            ['images', ''],
            ['customer_handled', 0],
            ['inventory_tip_confirmed', 0],
            ['auto_sync_erp', 1],
        ]);

        return success($this->cost_adjustment_service->adjust($id, $data));
    }

    /**
     * 扫码台按设备 ID / IMEI / SN 查询设备候选
     * @return mixed
     */
    public function scanSearch()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['limit', 20],
        ]);

        return success($this->service->scanSearch((string)$data['keyword'], (int)$data['limit']));
    }

    /**
     * 添加设备
     * @return mixed
     */
    public function add()
    {
        $data = $this->request->params([
            ['order_id', 0],
            ['imei', ''],
            ['model', ''],
            ['initial_price', 0],
            ['remark', '']
        ]);

        // 参数验证(failException:校验失败抛异常拦截,而不是只返回 false)
        $this->validate->scene('create')->failException()->check($data);

        return success($this->service->add($data));
    }

    /**
     * 更新设备信息
     * @param int $id
     * @return mixed
     */
    public function update(int $id)
    {
        // 前端已改为扁平直传（action/check_result/info... 均在顶层）；
        // 这里兼容两种形态：若仍带 data 包裹则取内层，否则直接用顶层参数。
        $raw = $this->request->param();
        $payload = (is_array($raw) && isset($raw['data']) && is_array($raw['data'])) ? $raw['data'] : (is_array($raw) ? $raw : []);
        unset($payload['id']);

        // 参数验证(update 场景历史规则较杂,不整场景强拦;仅对 IMEI 长度做精准强校验:>15 位抛异常拦截)
        $this->validate->scene('update')->check(array_merge(['id' => $id], $payload));
        if (!empty($payload['imei'])) {
            $this->validate->scene('imei')->failException()->check(['imei' => $payload['imei']]);
        }

        // 检查是否是质检操作（包括完成质检和暂存质检）
        if (isset($payload['action']) && in_array($payload['action'], ['check', 'save_draft'])) {
            // 组装质检数据
            $checkData = [
                'check_result' => $payload['check_result'] ?? '',
                'check_images' => $payload['check_images'] ?? '',
                'check_result_seller' => $payload['check_result_seller'] ?? '',
                'check_result_buyer' => $payload['check_result_buyer'] ?? '',
                'check_images_seller' => $payload['check_images_seller'] ?? '',
                'check_images_buyer' => $payload['check_images_buyer'] ?? '',
            ];

            // 向后兼容：如果只提交了 seller 字段，兼容填充旧字段
            if (empty($checkData['check_result']) && !empty($checkData['check_result_seller'])) {
                $checkData['check_result'] = $checkData['check_result_seller'];
            }
            if (empty($checkData['check_images']) && !empty($checkData['check_images_seller'])) {
                $checkData['check_images'] = $checkData['check_images_seller'];
            }

            // 如果有最终价格，添加到质检数据中
            if (isset($payload['final_price']) && $payload['final_price'] > 0) {
                $checkData['final_price'] = $payload['final_price'];
            }

            // 卖货价格支持在质检阶段录入（允许为0）
            if (array_key_exists('sell_price', $payload) && $payload['sell_price'] !== '' && $payload['sell_price'] !== null) {
                $checkData['sell_price'] = $payload['sell_price'];
            }

            // 如果有check_status，添加到质检数据中
            if (isset($payload['check_status'])) {
                $checkData['check_status'] = $payload['check_status'];
            }

            // imei
            if (isset($payload['imei'])) {
                $checkData['imei'] = $payload['imei'];
            }
            // info
            if (isset($payload['info'])) {
                $checkData['info'] = $payload['info'];
            }
            // model
            if (isset($payload['model'])) {
                $checkData['model'] = $payload['model'];
            }
            // system_version
            if (isset($payload['system_version'])) {
                $checkData['system_version'] = $payload['system_version'];
            }
            // warranty_info
            if (isset($payload['warranty_info'])) {
                $checkData['warranty_info'] = $payload['warranty_info'];
            }
            // capacity
            if (isset($payload['capacity'])) {
                $checkData['capacity'] = $payload['capacity'];
            }
            // color
            if (isset($payload['color'])) {
                $checkData['color'] = $payload['color'];
            }
            // check_template_id
            if (isset($payload['check_template_id'])) {
                $checkData['check_template_id'] = $payload['check_template_id'];
            }


            // 调用质检完成方法，传递 action 参数
            return success($this->service->completeCheck($id, $checkData, $payload['remark'] ?? '', $payload['action']));
        }

        return success($this->service->update($id, $payload));
    }

    /**
     * 删除设备
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        // 参数验证
        $this->validate->scene('delete')->check(['id' => $id]);

        return success($this->service->delete($id));
    }

    /**
     * 开始质检设备
     * @param int $id
     * @return mixed
     */
    public function startCheck(int $id)
    {
        $data = $this->request->params([
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('check')->check(array_merge(['id' => $id], $data));

        return success($this->service->startCheck($id, $data['remark']));
    }

    /**
     * 完成质检设备
     * @param int $id
     * @return mixed
     */
    public function completeCheck(int $id)
    {
        $data = $this->request->params([
            ['check_data', []],
            ['final_price', 0],
            ['sell_price', ''],
            ['remark', ''],
            ['action', 'check'],  // 新增：check=完成质检，save_draft=暂存质检
            ['next_assignee_uid', 0]
        ]);

        // 参数验证
        $this->validate->scene('check')->check(array_merge(['id' => $id], $data));

        // 组装质检数据
        $checkData = $data['check_data'];
        if (isset($data['final_price']) && $data['final_price'] > 0) {
            $checkData['final_price'] = $data['final_price'];
        }
        if ($data['sell_price'] !== '' && $data['sell_price'] !== null) {
            $checkData['sell_price'] = $data['sell_price'];
        }
        if (isset($data['check_data']['check_template_id'])) {
            $checkData['check_template_id'] = $data['check_data']['check_template_id'];
        }

        return success($this->service->completeCheck($id, $checkData, $data['remark'], $data['action'], (int)$data['next_assignee_uid']));
    }

    /**
     * 确认设备价格
     * @param int $id
     * @return mixed
     */
    public function confirmPrice(int $id)
    {
        $defaults = [
            ['final_price', ''],
            ['sell_price', ''],
            ['remark', ''],
            ['sale_destination', RecycleOrderDict::SALE_DESTINATION_MALL],
            ['target_warehouse_id', 0],
            ['target_warehouse_name', ''],
            ['target_location_id', 0],
            ['target_location_name', ''],
            ['refurbishment_required', 0],
            ['refurbishment_assignee_uid', 0],
            ['refurbishment_reason', ''],
            ['refurbishment_items', []],
            ['refurbishment_estimated_cost', 0],
            ['next_assignee_uid', 0]
        ];
        $data = $this->request->params($defaults);
        $requestData = $this->request->param();
        if (is_array($requestData)) {
            $payload = isset($requestData['data']) && is_array($requestData['data']) ? $requestData['data'] : $requestData;
            foreach ($defaults as $item) {
                $key = $item[0];
                if (array_key_exists($key, $payload)) {
                    $data[$key] = $payload[$key];
                }
            }
        }
      
        // 参数验证
        $this->validate->scene('price')->check(array_merge(['id' => $id], $data));

        $sellPrice = ($data['sell_price'] === '' || $data['sell_price'] === null) ? null : (float)$data['sell_price'];
        return success($this->service->confirmPrice($id, (float)$data['final_price'], $data['remark'], $sellPrice, [
            'sale_destination' => $data['sale_destination'],
            'target_warehouse_id' => (int)$data['target_warehouse_id'],
            'target_warehouse_name' => (string)$data['target_warehouse_name'],
            'target_location_id' => (int)$data['target_location_id'],
            'target_location_name' => (string)$data['target_location_name'],
            'refurbishment_required' => $data['refurbishment_required'],
            'refurbishment_assignee_uid' => $data['refurbishment_assignee_uid'],
            'refurbishment_reason' => $data['refurbishment_reason'],
            'refurbishment_items' => $data['refurbishment_items'],
            'refurbishment_estimated_cost' => $data['refurbishment_estimated_cost'],
            'next_assignee_uid' => (int)$data['next_assignee_uid'],
        ]));
    }

    /**
     * 回收设备
     * @param int $id
     * @return mixed
     */
    public function recycle(int $id)
    {
        $data = $this->request->params([
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('recycle')->check(array_merge(['id' => $id], $data));

        return success($this->service->recycle($id, $data['remark']));
    }

    /**
     * 整备配置选项
     * @return mixed
     */
    public function refurbishmentOptions()
    {
        return success([
            'items' => RecycleRefurbishmentDict::getItemOptions(),
        ]);
    }

    public function saleDestinationOptions()
    {
        // 当 ERP 已安装时，向其同步查询启用仓库列表（解耦：只发标准事件，ERP 未装则无人应答 → 回退固定渠道）
        // erp_connected 用于区分「ERP 未接入」与「ERP 已接入但暂无仓库」两种状态，便于前端给出明确提示。
        $warehouses = [];
        $erpConnected = (new RecycleErpCapabilityService())->isEnabled((int)$this->request->siteId());
        if (!$erpConnected) {
            return success([
                'items' => RecycleOrderDict::getSaleDestinationOptions(),
                'warehouses' => [],
                'erp_connected' => false,
            ]);
        }
        try {
            $raw = (array)event('GetErpWarehouseList', ['site_id' => (int)$this->request->siteId()]);
            foreach ($raw as $r) {
                if (is_array($r)) {
                    // 只要有插件应答(哪怕返回空数组)，即视为 ERP 已接入
                    $warehouses = array_values($r);
                    break;
                }
            }
        } catch (\Throwable $e) {
            $warehouses = [];
        }

        // 兜底：事件未返回时，若 ERP 插件在场则用 class_exists 守卫直接取仓库服务
        // （ERP 未安装则类不存在 → 跳过，回退固定渠道；避免依赖事件注册时机）
        if (empty($warehouses)) {
            $cls = '\\addon\\hsx_erp\\app\\service\\admin\\ErpWarehouseService';
            if ($erpConnected && class_exists($cls)) {
                try {
                    $list = (new $cls())->getOptions();
                    if (is_array($list)) {
                        $warehouses = array_values($list);
                    }
                } catch (\Throwable $e) {
                    // ERP 在场但取数失败：保持已连接判断，仓库留空，前端给出提示
                }
            }
        }

        return success([
            'items' => RecycleOrderDict::getSaleDestinationOptions(),
            'warehouses' => $warehouses,
            'erp_connected' => $erpConnected,
        ]);
    }

    /**
     * 整备负责人候选（按被选次数倒序，常用优先）
     * @return mixed
     */
    public function refurbishmentAssigneeOptions()
    {
        $users = [];
        try {
            $users = (new \addon\hsx_recycle\app\service\admin\stats\RecycleStatsService())->getUserList();
        } catch (\Throwable $e) {
            $users = [];
        }

        $counts = [];
        try {
            $svc = new \addon\hsx_recycle\app\service\core\recycle_device\CoreRecyclePickStatService();
            $counts = $svc->getCounts(\addon\hsx_recycle\app\service\core\recycle_device\CoreRecyclePickStatService::SCENE_REFURB_ASSIGNEE);
        } catch (\Throwable $e) {
            $counts = [];
        }

        foreach ($users as &$u) {
            $u['pick_count'] = (int)($counts[(int)($u['uid'] ?? 0)] ?? 0);
        }
        unset($u);

        usort($users, function ($a, $b) {
            $ca = (int)($a['pick_count'] ?? 0);
            $cb = (int)($b['pick_count'] ?? 0);
            if ($ca !== $cb) {
                return $cb <=> $ca;
            }
            return (int)($a['uid'] ?? 0) <=> (int)($b['uid'] ?? 0);
        });

        return success(['users' => $users]);
    }

    /**
     * 退回设备
     * @param int $id
     * @return mixed
     */
    public function returnDevice(int $id)
    {
        $data = $this->request->params([
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('return')->check(array_merge(['id' => $id], $data));

        return success($this->service->returnDevice($id, $data['remark']));
    }

    /**
     * 批量更新设备状态
     * @return mixed
     */
    public function batchUpdateStatus()
    {
        $data = $this->request->params([
            ['ids', ''],
            ['status', 0],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('batch')->check($data);

        // 将逗号分隔的ID转为数组
        $ids = is_string($data['ids']) ? explode(',', $data['ids']) : $data['ids'];
        if (empty($ids)) {
            return error('请选择设备');
        }

        return success($this->service->batchUpdateStatus($ids, (int)$data['status'], $data['remark']));
    }

    /**
     * 批量回收设备
     * @return mixed
     */
    public function batchRecycle()
    {
        $data = $this->request->params([
            ['ids', ''],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('batch')->check($data);

        // 将逗号分隔的ID转为数组
        $ids = is_string($data['ids']) ? explode(',', $data['ids']) : $data['ids'];
        if (empty($ids)) {
            return error('请选择设备');
        }

        return success($this->service->batchRecycle($ids, $data['remark']));
    }

    /**
     * 批量退回设备
     * @return mixed
     */
    public function batchReturn()
    {
        $data = $this->request->params([
            ['ids', ''],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('batch')->check($data);

        // 将逗号分隔的ID转为数组
        $ids = is_string($data['ids']) ? explode(',', $data['ids']) : $data['ids'];
        if (empty($ids)) {
            return error('请选择设备');
        }

        return success($this->service->batchReturn($ids, $data['remark']));
    }

    /**
     * 获取IMEI信息
     * @param string $imei
     * @return mixed
     */
    public function getImeiInfo(string $imei)
    {
        // 参数验证
        $this->validate->scene('imei')->check(['imei' => $imei]);

        return success($this->service->getImeiInfo($imei));
    }

    /**
     * 打印设备标签
     * @param int $id
     * @return mixed
     */
    public function printDeviceLabel(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);

        $data = $this->request->params([
            ['template_type', 'device_label']
        ]);
        
        $result = $this->template_service->printDeviceLabel($id, $data['template_type']);
        
      

        if ($result['success']) {
            return success($result);
        }

        return fail($result['message'] ?? '打印失败', $result);
    }

    /**
     * 获取设备打印数据
     * @param int $id 设备ID
     * @return mixed
     */
    public function getPrintData(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);
        
        try {
            $data = $this->template_service->getDevicePrintData($id);
            
            return success([
                'device_data' => $data,
                'available_variables' => array_keys($data)
            ]);
            
        } catch (\Exception $e) {
            return error('获取设备数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试设备标签打印
     * @param int $id 设备ID
     * @return mixed
     */
    public function testPrint(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);
        
        $data = $this->request->params([
            ['template_id', 0],
            ['template_type', 'device_label']
        ]);
        
        try {
            // 获取设备数据
            $device_data = $this->template_service->getDevicePrintData($id);
            
            // 确定使用的模板
            if (!empty($data['template_id'])) {
                // 使用指定模板进行测试打印
                $result = $this->template_service->testPrint($data['template_id'], $device_data);
            } else {
                // 使用默认模板打印
                $result = $this->template_service->printDeviceLabel($id, $data['template_type']);
            }
            
            // 兜底清洗:设备数据/打印结果可能含非 UTF-8 字节(GBK 报错、二进制标签指令等)，
            // 不清洗会让 json_encode 抛 "Malformed UTF-8 characters" 导致接口 500
            return success(\addon\hsx_recycle\app\support\Utf8::clean([
                'device_id' => $id,
                'device_data' => $device_data,
                'print_result' => $result
            ], 'order.testPrint'));

        } catch (\Exception $e) {
            return error('测试打印失败: ' . $e->getMessage());
        }
    }
}
