<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\api\recycle_order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\express\ExpressProviderDict;
use addon\hsx_recycle\app\model\check\RecycleCheckField;
use addon\hsx_recycle\app\model\check\RecycleCheckOption;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderFlowService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\ApiException;
use core\exception\CommonException;

/**
 * 回收订单服务（API端 - 使用流程引擎）
 *
 * 用户端订单操作入口，所有操作通过流程引擎执行
 *
 * @package addon\hsx_recycle\app\service\api\recycle_order
 */
class RecycleOrderService extends BaseApiService
{
    /**
     * 流程引擎实例
     * @var CoreRecycleOrderFlowService
     */
    private $flowService;

    /**
     * @var RecycleOrder
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->flowService = new CoreRecycleOrderFlowService();
        $this->model = new RecycleOrder();
    }

    /**
     * 执行订单操作
     *
     * @param int $orderId 订单ID
     * @param string $action 操作名称
     * @param array $data 操作数据
     * @return array 执行结果
     * @throws CommonException
     */
    private function execute(int $orderId, string $action, array $data = []): array
    {
        return $this->flowService->execute(
            $orderId,
            $action,
            $data,
            CoreRecycleOrderFlowService::FLOW_TYPE_API,
            [
                'operator_id' => $this->member_id,
                'site_id' => $this->site_id
            ]
        );
    }

    /**
     * 取消订单
     *
     * @param int $orderId 订单ID
     * @param array $data 取消数据（包含reason）
     * @return bool
     * @throws CommonException
     */
    public function cancel(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'cancel', $data);
        return $result['success'];
    }

    /**
     * 确认收货
     *
     * @param int $orderId 订单ID
     * @param array $data 确认数据
     * @return bool
     * @throws CommonException
     */
    public function confirmReceipt(int $orderId, array $data = []): bool
    {
        $result = $this->execute($orderId, 'confirm_receipt', $data);
        return $result['success'];
    }

    /**
     * 确认价格
     *
     * @param int $orderId 订单ID
     * @param array $data 确认数据
     * @return bool
     * @throws CommonException
     */
    public function confirmPrice(int $orderId, array $data = []): bool
    {
        $result = $this->execute($orderId, 'confirm_price', $data);
        return $result['success'];
    }

    /**
     * 议价
     *
     * @param int $orderId 订单ID
     * @param array $data 议价数据（包含expected_price, reason等）
     * @return bool
     * @throws CommonException
     */
    public function negotiate(int $orderId, array $data): bool
    {
        $result = $this->execute($orderId, 'negotiate', $data);
        return $result['success'];
    }

    // ==================== CRUD 和工具方法 ====================

    /**
     * 获取订单列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,order_no,site_id,member_id,delivery_type,express_company,express_no,delivery_platform,delivery_status,delivery_fee,delivery_order_id,pickup_time,count,customer_name,customer_phone,remark,status,create_at,update_at';
        $order = 'create_at desc';

        // 如果 state = all
        if (!empty($where['status']) && $where['status'] == 'all') {
            unset($where['status']);
        }

        // 构建基础查询模型，并复用模型层搜索器
        $search_model = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['status', '<>', 10],
                ['delete_at', '=', 0]
            ])
            ->withSearch([
                'order_no',
                'express_no',
                'search',
                'customer_name',
                'customer_phone',
                'imei',
                'delivery_type',
                'status',
                'create_at',
                'remark'
            ], $where);

        // 设置查询字段、关联、排序和附加属性
        $search_model = $search_model
            ->field($field)
            ->with([
                'devices' => function($query) {
                    $query->field('id,order_id,site_id,imei,user_sn,model,initial_price,status,final_price,cost_adjust_amount,cost_adjust_count,last_cost_adjust_time,last_cost_adjust_no,check_images,check_images_seller')
                        ->append(['status_name', 'check_images_seller_thumb_small']);
                }
            ])
            ->order($order)
            ->append(['status_name', 'delivery_type_name']);

        return $this->pageQuery($search_model);
    }

    /**
     * 获取订单信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,order_no,site_id,member_id,delivery_type,express_company,express_no,delivery_platform,delivery_status,delivery_fee,delivery_order_id,pickup_time,count,customer_name,customer_phone,remark,status,create_at,update_at';

        $info = $this->model
            ->where([
                ['id', "=", $id],
                ['site_id', "=", $this->site_id],
                ['member_id', "=", $this->member_id]
            ])
            ->field($field)
            ->with([
                'devices' => function($query) {
                    $query->field('id,order_id,site_id,imei,imei2,sn,user_sn,model,capacity,color,initial_price,status,final_price,cost_adjust_amount,cost_adjust_count,last_cost_adjust_time,last_cost_adjust_no,remark,check_images,check_images_seller,check_result,check_result_seller,check_at,price_remark,consignment_order_id,info')
                        ->with(['consignmentOrder' => function($q) {
                            $q->field('id,consignment_no,source_device_id,status');
                        }, 'paymentRecords' => function($q) {
                            $q->field('id,site_id,pay_no,order_id,device_id,amount,pay_type,pay_account,pay_name,pay_remark,payment_images,pay_time,create_at')
                                ->order('pay_time asc,id asc');
                        }])
                        ->append(['status_name', 'check_images_seller_thumb_small']);
                },
                'member' => function($query) {
                    $query->field('member_id,nickname,mobile');
                }
            ])
            ->append(['status_name', 'delivery_type_name'])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new ApiException('订单不存在');
        }

        $info['devices'] = $this->fillInspectionReportMeta($info['devices'] ?? []);
        $info['devices'] = $this->formatDevicePaymentRecords($info['devices']);

        return $info;
    }

    private function formatDevicePaymentRecords(array $devices): array
    {
        foreach ($devices as &$device) {
            $records = $device['paymentRecords'] ?? $device['payment_records'] ?? [];
            if (!is_array($records)) {
                $records = [];
            }
            foreach ($records as $index => &$record) {
                $images = array_values(array_filter(array_map('trim', explode(',', (string)($record['payment_images'] ?? '')))));
                $record['batch_index'] = $index + 1;
                $record['amount_text'] = number_format((float)($record['amount'] ?? 0), 2);
                $record['pay_time_text'] = !empty($record['pay_time']) ? date('Y-m-d H:i:s', (int)$record['pay_time']) : '';
                $record['payment_image_list'] = $images;
            }
            unset($record);
            $device['payment_records'] = $records;
            unset($device['paymentRecords']);
        }
        unset($device);

        return $devices;
    }

    /**
     * 补全用户端验机报告结构。
     *
     * 历史质检结果可能只有 result_items / option_styles，无法支撑多选项独立样式。
     * 这里按模板选项配置补出 option_items，只增强接口返回，不修改存量数据库。
     */
    private function fillInspectionReportMeta(array $devices): array
    {
        $templateIds = [];
        foreach ($devices as $device) {
            $info = $this->normalizeArray($device['info'] ?? []);
            $checkMeta = $this->normalizeArray($info['check_meta'] ?? []);
            $templateId = (int)($checkMeta['template_id'] ?? 0);
            if ($templateId > 0) {
                $templateIds[$templateId] = $templateId;
            }
        }

        if (empty($templateIds)) {
            return $devices;
        }

        $templateOptionMap = $this->getTemplateOptionMap(array_values($templateIds));
        foreach ($devices as &$device) {
            $info = $this->normalizeArray($device['info'] ?? []);
            $checkMeta = $this->normalizeArray($info['check_meta'] ?? []);
            $templateId = (int)($checkMeta['template_id'] ?? 0);
            if ($templateId <= 0 || empty($checkMeta['result_items']) || !is_array($checkMeta['result_items'])) {
                continue;
            }

            foreach ($checkMeta['result_items'] as &$item) {
                if (!is_array($item)) {
                    continue;
                }
                $fieldKey = (string)($item['field_key'] ?? '');
                $fieldOptions = $templateOptionMap[$templateId][$fieldKey] ?? [];
                $values = $this->normalizeStringList($item['values'] ?? $item['value'] ?? []);
                $labels = $this->normalizeStringList($item['labels'] ?? []);

                $optionItems = [];
                foreach ($values as $index => $value) {
                    $option = $fieldOptions[$value] ?? [];
                    $style = $this->extractResultStyle($option['extra_config'] ?? []);
                    $label = $labels[$index] ?? ($option['label'] ?? $option['name'] ?? $value);
                    $optionItem = [
                        'value' => $value,
                        'label' => $label,
                    ];
                    if (!empty($style)) {
                        $optionItem['style'] = $style;
                    }
                    $optionItems[] = $optionItem;
                }

                if (!empty($optionItems)) {
                    $item['option_items'] = $optionItems;
                }
                if (count($optionItems) > 1) {
                    unset($item['style']);
                } elseif (count($optionItems) === 1 && !empty($optionItems[0]['style'])) {
                    $item['style'] = $optionItems[0]['style'];
                }
            }
            unset($item);

            $info['check_meta'] = $checkMeta;
            $device['info'] = $info;
        }
        unset($device);

        return $devices;
    }

    private function getTemplateOptionMap(array $templateIds): array
    {
        $fields = (new RecycleCheckField())->where([
            ['site_id', '=', $this->site_id],
        ])->whereIn('template_id', $templateIds)->field('id,template_id,field_key')->select()->toArray();

        if (empty($fields)) {
            return [];
        }

        $fieldMap = [];
        $fieldIds = [];
        foreach ($fields as $field) {
            $fieldId = (int)$field['id'];
            $fieldIds[] = $fieldId;
            $fieldMap[$fieldId] = [
                'template_id' => (int)$field['template_id'],
                'field_key' => (string)$field['field_key'],
            ];
        }

        $options = (new RecycleCheckOption())->where([
            ['site_id', '=', $this->site_id],
        ])->whereIn('field_id', $fieldIds)->field('field_id,option_label,option_value,extra_config')->select()->toArray();

        $map = [];
        foreach ($options as $option) {
            $fieldId = (int)$option['field_id'];
            if (empty($fieldMap[$fieldId])) {
                continue;
            }
            $templateId = $fieldMap[$fieldId]['template_id'];
            $fieldKey = $fieldMap[$fieldId]['field_key'];
            $value = (string)$option['option_value'];
            $map[$templateId][$fieldKey][$value] = [
                'name' => (string)$option['option_label'],
                'label' => (string)$option['option_label'],
                'value' => $value,
                'extra_config' => $this->normalizeArray($option['extra_config'] ?? []),
            ];
        }

        return $map;
    }

    private function normalizeArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function normalizeStringList($value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        return array_values(array_filter(array_map(static function ($item) {
            if ($item === null) {
                return '';
            }
            return (string)$item;
        }, $value), static fn($item) => $item !== ''));
    }

    private function extractResultStyle($config): array
    {
        $config = $this->normalizeArray($config);
        $style = $this->normalizeArray($config['result_style'] ?? ($config['option_style'] ?? $config));
        $result = [
            'text_color' => (string)($style['text_color'] ?? ''),
            'background_color' => (string)($style['background_color'] ?? ($style['bg_color'] ?? '')),
            'border_color' => (string)($style['border_color'] ?? ''),
        ];
        return array_filter($result, static fn($value) => $value !== '');
    }

    /**
     * 添加订单
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function add(array $data)
    {
        try {
            // 添加必要的字段
            $data['site_id'] = $this->site_id;
            $data['member_id'] = $this->member_id;
            $data['status'] = RecycleOrderDict::ORDER_STATUS_PENDING_SIGN;
            $data['order_no'] = $this->generateOrderNo();

            // 读取站点配置的流转模式（整单/按设备）
            $orderSubmitConfig = (new \addon\hsx_recycle\app\service\api\order\OrderSubmitConfigService())->getConfig($this->site_id);
            $data['flow_mode'] = ($orderSubmitConfig['flow']['mode'] ?? $orderSubmitConfig['payment']['mode'] ?? RecycleOrderDict::FLOW_MODE_ORDER);

            // ========== 统一快递服务（亿速）==========
            if (!empty($data['use_express']) && !empty($data['express_config'])) {
                // 先创建回收订单
                $order = $this->model->create($data);

                try {
                    // 调用统一快递服务下单
                    $expressService = new \addon\hsx_recycle\app\service\core\express\RecycleExpressService();

                    $operatorInfo = [
                        'uid' => 0,
                        'username' => '',
                        'source' => 'user',
                        'member_id' => $this->member_id,
                    ];

                    $expressResult = $expressService->createOrder(
                        $this->site_id,
                        $order->id,
                        $data['express_config'],
                        $operatorInfo
                    );

                    $provider = (string)($expressResult['provider'] ?? ($data['express_config']['provider'] ?? ExpressProviderDict::PROVIDER_YISU));
                    $providerName = trim((string)($expressResult['provider_name'] ?? ($data['express_config']['provider_name'] ?? '')));
                    if ($providerName === '') {
                        $providerName = ExpressProviderDict::getProviderName($provider);
                    }
                    $order->save([
                        'express_company' => $providerName,
                        'delivery_platform' => $provider,
                        'update_at' => time(),
                    ]);

                    // 刷新订单数据（快递服务内部已更新了订单字段）
                    $order->refresh();

                } catch (\Exception $e) {
                    // 快递下单失败，删除已创建的订单
                    $order->delete();
                    throw new ApiException('快递下单失败：' . $e->getMessage());
                }

            } else {
                // 不使用平台快递，直接创建订单（自填快递号 或 自送到店）
                $order = $this->model->create($data);
            }

            // 如果有设备列表，创建设备
            if (!empty($data['devices'])) {
                $devices = [];
                foreach ($data['devices'] as $device) {
                    $categoryId = (int)($device['category_id'] ?? 0);
                    $categoryPath = $device['category_path'] ?? [];
                    if (!is_array($categoryPath) || empty($categoryPath)) {
                        $categoryPath = $categoryId > 0 ? [ $categoryId ] : [];
                    }

                    $devices[] = [
                        'site_id' => $this->site_id,
                        'order_id' => $order->id,
                        'category_id' => $categoryId,
                        'info' => [
                            'goods_category' => array_values(array_map('strval', $categoryPath))
                        ],
                        'imei' => $device['imei'] ?? '',
                        'user_sn' => $device['user_sn'] ?? ($device['imei'] ?? ''),
                        'model' => $device['model'] ?? '',
                        'status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                        'initial_price' => $device['initial_price'] ?? 0,
                        'member_id' => $this->member_id
                    ];
                }
                (new RecycleDevice())->insertAll($devices);
            }

            return $order->toArray();
        } catch (\Exception $e) {
            throw new ApiException('创建订单失败：' . $e->getMessage());
        }
    }

    /**
     * 编辑订单
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $data['action'] = $data['action'] ?? '';
        $data['status'] = $data['status'] ?? '';

        // 客户要删除订单 只有 status == 8（已关闭） || 9（已取消） 才允许软删除
        if ($data['action'] == 'delete') {
            if (!in_array((int)$data['status'], [RecycleOrderDict::ORDER_STATUS_CLOSED, RecycleOrderDict::ORDER_STATUS_CANCELLED])) {
                throw new ApiException('删除订单失败：当前订单状态不支持删除');
            }
            // 软删除：设置 delete_at 时间戳，列表查询已通过 delete_at=0 过滤
            $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
                'delete_at' => time(),
                'update_at' => time(),
            ]);
            return true;
        }
        // 只有 status == 5 的时候才能一键确认
        if ( $data['action'] == 'confirm' && $data['status'] == RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM) {
            throw new ApiException('确认订单失败：当前订单状态不支持确认');
        }

        // 取消订单走流程引擎，以触发快递拦截等业务逻辑
        if ($data['action'] == 'cancel') {
            return $this->cancel($id, [
                'reason' => $data['reason'] ?? '用户取消订单',
                'remark' => $data['remark'] ?? '',
            ]);
        }

        $data['update_at'] = time();

        // 讲 status = 客户要执行的操作id 和 dict 中的状态对应
        $data['status'] = RecycleOrderDict::getOrderStatusId($data['action']);

        // 判断客户是否一键确认 如果不是  则只能修改订单状态
        if ($data['action'] != 'confirm') {
            unset($data['action']);
            $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
            return true;
        }
        unset($data['action']);

        // 开启事务
        $this->model->startTrans();
        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        // 将关联的设备状态修改为 已回收
        (new RecycleDevice())->where([['order_id', '=', $id],['site_id', '=', $this->site_id]])->update(['status' => RecycleOrderDict::DEVICE_STATUS_RECYCLED]);
        // 提交事务
        $this->model->commit();

        return true;
    }

    /**
     * 删除订单
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($model)) {
            throw new CommonException('ORDER_NOT_FOUND');
        }

        // 已完成的订单不允许删除
        if ($model['status'] == RecycleOrderDict::ORDER_STATUS_COMPLETED) {
            throw new CommonException('已完成的订单不允许删除');
        }

        // 只有已关闭或已取消的订单才能删除
        if (!in_array($model['status'], [
            RecycleOrderDict::ORDER_STATUS_CLOSED,
            RecycleOrderDict::ORDER_STATUS_CANCELLED
        ])) {
            throw new CommonException('当前订单状态不允许删除');
        }

        // 软删除：标记删除时间，更新状态
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
            'status' => RecycleOrderDict::ORDER_STATUS_DELETE,
            'delete_at' => time(),
            'update_at' => time()
        ]);

        return true;
    }

    /**
     * 获取所有会员
     * @return array
     */
    public function getMemberAll(){
       $memberModel = new Member();
       return $memberModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

    /**
     * 获取订单状态列表
     * @return array
     */
    public function getStatus()
    {
        return RecycleOrderDict::getOrderStatus();
    }

    /**
     * 获取订单状态统计
     * @return array
     */
    public function getStatusCount()
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['delete_at', '=', 0]
        ];

        $counts = $this->model->where($where)
            ->group('status')
            ->column('count(*)', 'status');

        // 构建状态列表数据
        $statusList = [];

        // 添加"全部"选项
        $statusList[] = [
            'key' => 'all',
            'text' => '全部',
            'count' => array_sum($counts)
        ];

        // 添加各状态数据
        foreach (RecycleOrderDict::ORDER_STATUS_TEXT as $status => $text) {
            $statusList[] = [
                'key' => (string)$status,
                'text' => $text,
                'count' => $counts[$status] ?? 0,
                'actions' => RecycleOrderDict::ORDER_STATUS_FLOW[$status] ?? []
            ];
        }

        return [
            'list' => $statusList,
            'status_dict' => [
                'device' => RecycleOrderDict::DEVICE_STATUS_TEXT,
                'order' => RecycleOrderDict::ORDER_STATUS_TEXT
            ]
        ];
    }

    /**
     * 单台确认
     * @param int $device_id
     * @return bool
     */
    public function deviceConfirm($device_id)
    {
        try {
            $deviceModel = new RecycleDevice();

            // 获取设备信息
            $device = $deviceModel->where([['id', '=', $device_id]])->find();
            if (empty($device)) {
                throw new \Exception('设备不存在');
            }

            // 检查设备状态是否为已质检(3)
            if ($device['status'] != 3) {
                throw new \Exception('设备状态不正确，无法确认');
            }

            // 开启事务
            $deviceModel->startTrans();

            try {
                // 更新设备状态为已确认(4)
                $result = $deviceModel->where([['id', '=', $device_id]])->update([
                    'status' => RecycleOrderDict::DEVICE_STATUS['CONFIRMED'],
                    'update_at' => time()
                ]);

                if (!$result) {
                    throw new \Exception('更新设备状态失败');
                }

                // 检查订单下所有设备是否都已确认
                $unconfirmed = $deviceModel->where([
                    ['order_id', '=', $device['order_id']],
                    ['status', '<>', RecycleOrderDict::DEVICE_STATUS['CONFIRMED']],
                    ['status', '<>', RecycleOrderDict::DEVICE_STATUS['RETURNED']] // 排除已退回的设备
                ])->count();

                // 如果所有设备都已确认，更新订单状态为已确认(4)
                if ($unconfirmed == 0) {
                    $order_result = $this->model->where([['id', '=', $device['order_id']]])->update([
                        'status' => RecycleOrderDict::ORDER_STATUS['PAYING'],
                        'update_at' => time()
                    ]);

                    if (!$order_result) {
                        throw new \Exception('更新订单状态失败');
                    }
                }

                // 提交事务
                $deviceModel->commit();
                return true;

            } catch (\Exception $e) {
                // 回滚事务
                $deviceModel->rollback();
                throw new ApiException($e->getMessage());
            }

        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 单台取消
     * @param int $device_id
     * @return bool
     */
    public function deviceCancel($device_id)
    {
        try {
            $deviceModel = new RecycleDevice();

            // 获取设备信息
            $device = $deviceModel->where([['id', '=', $device_id]])->find();
            if (empty($device)) {
                throw new \Exception('设备不存在');
            }

            // 检查设备状态是否为已质检(3)
            if ($device['status'] != 3) {
                throw new \Exception('设备状态不正确，无法取消');
            }

            // 开启事务
            $deviceModel->startTrans();

            try {
                // 更新设备状态为已退回(6)
                $result = $deviceModel->where([['id', '=', $device_id]])->update([
                    'status' => RecycleOrderDict::DEVICE_STATUS['RETURNED'],
                    'update_at' => time()
                ]);

                if (!$result) {
                    throw new \Exception('更新设备状态失败');
                }

                // 检查订单下是否还有未处理的设备
                $pending_devices = $deviceModel->where([
                    ['order_id', '=', $device['order_id']],
                    ['status', '<>', RecycleOrderDict::DEVICE_STATUS['CONFIRMED']], // 不是已确认
                    ['status', '<>', RecycleOrderDict::DEVICE_STATUS['RETURNED']]  // 不是已退回
                ])->count();

                // 如果没有未处理的设备，检查是否有确认的设备
                if ($pending_devices == 0) {
                    $confirmed_devices = $deviceModel->where([
                        ['order_id', '=', $device['order_id']],
                        ['status', '=', RecycleOrderDict::DEVICE_STATUS['CONFIRMED']]
                    ])->count();

                    // 更新订单状态
                    // 有确认的设备则为已确认(4)，否则为已取消(7)
                    $new_status = $confirmed_devices > 0 ?
                        RecycleOrderDict::ORDER_STATUS['PAYING'] :
                        RecycleOrderDict::ORDER_STATUS['CANCELLED'];

                    $order_result = $this->model->where([['id', '=', $device['order_id']]])->update([
                        'status' => $new_status,
                        'update_at' => time()
                    ]);

                    if (!$order_result) {
                        throw new \Exception('更新订单状态失败');
                    }
                }

                // 提交事务
                $deviceModel->commit();
                return true;

            } catch (\Exception $e) {
                // 回滚事务
                $deviceModel->rollback();
                throw new ApiException($e->getMessage());
            }

        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 生成订单编号
     * @return string
     */
    protected function generateOrderNo(): string
    {
        return 'RO' . date('YmdHis') . mt_rand(1000, 9999);
    }
}
