<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\wj_books\app\service\api\wj_books_order;

use addon\wj_books\app\model\wj_books_order\WjBooksOrder;
use addon\wj_books\app\model\wj_books_order_book\WjBooksOrderBook;
use addon\wj_books\app\model\wj_books_cart\WjBooksCart;
use addon\wj_books\app\model\wj_books_info\WjBooksInfo;
use addon\wj_books\app\model\wj_books_config\WjBooksConfig;
use addon\wj_books\app\service\api\wj_books_scan_records\WjBooksScanRecordsService;
use app\model\member\MemberAddress;
use core\base\BaseApiService;
use think\facade\Db;
use think\facade\Log;

/**
 * 图书回收订单API服务层
 * Class WjBooksOrderService
 * @package addon\wj_books\app\service\api\wj_books_order
 */
class WjBooksOrderService extends BaseApiService
{
    /**
     * @var WjBooksOrder
     */
    protected $orderModel;
    
    /**
     * @var WjBooksOrderBook
     */
    protected $orderBookModel;
    
    /**
     * @var WjBooksCart
     */
    protected $cartModel;
    
    /**
     * @var WjBooksInfo
     */
    protected $bookModel;
    
    /**
     * @var WjBooksConfig
     */
    protected $configModel;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->orderModel = new WjBooksOrder();
        $this->orderBookModel = new WjBooksOrderBook();
        $this->cartModel = new WjBooksCart();
        $this->bookModel = new WjBooksInfo();
        $this->configModel = new WjBooksConfig();
    }
    
    /**
     * 创建回收订单
     * @param array $data
     * @return array
     */
    public function createOrder(array $data)
    {
        // 记录开始创建订单
        Log::info('开始创建回收订单，会员ID：' . $this->member_id . '，站点ID：' . $this->site_id);
        
        // 参数验证
        if (empty($data['address_id'])) {
            return ['code' => -1, 'msg' => '回收地址不能为空'];
        }
        
        if (empty($data['pickup_time'])) {
            return ['code' => -1, 'msg' => '预约上门时间不能为空'];
        }
        
        if (empty($data['book_list']) || !is_array($data['book_list'])) {
            return ['code' => -1, 'msg' => '回收书籍列表不能为空'];
        }
        
        // 获取系统配置
        $config = $this->configModel->where([['site_id', '=', $this->site_id]])->find();
        if (empty($config)) {
            Log::error('系统配置不存在，站点ID：' . $this->site_id);
            return ['code' => -1, 'msg' => '系统配置不存在'];
        }
        
        // 获取地址信息
        $addressModel = new MemberAddress();
        $address = $addressModel->where([
            ['id', '=', $data['address_id']],
            ['member_id', '=', $this->member_id]
        ])->find();
        
        if (empty($address)) {
            Log::error('地址信息不存在，地址ID：' . $data['address_id'] . '，会员ID：' . $this->member_id);
            return ['code' => -1, 'msg' => '地址信息不存在'];
        }
        
        // 生成订单号
        $orderNo = $this->generateOrderNo();
        Log::info('生成订单号：' . $orderNo);
        
        // 开启事务
        Db::startTrans();
        try {
            // 计算总金额和总数量
            $totalAmount = 0;
            $bookCount = 0;
            
            // 准备订单数据
            $orderData = [
                'site_id' => $this->site_id,
                'order_no' => $orderNo,
                'member_id' => $this->member_id,
                'address_id' => $data['address_id'],
                'book_count' => 0, // 临时设置，后面会更新
                'total_amount' => 0, // 临时设置，后面会更新
                'status' => 1, // 待上门
                'remark' => $data['remark'] ?? '',
                'create_time' => date('Y-m-d H:i:s'),
                'pickup_time' => $data['pickup_time'],
                'deleted' => 0,
                'update_time' => date('Y-m-d H:i:s')
            ];
            
            // 创建订单
            $order = $this->orderModel->create($orderData);
            if (!$order || !isset($order->id)) {
                throw new \Exception('创建订单记录失败');
            }
            
            $orderId = $order->id;
            Log::info('创建订单成功，订单ID：' . $orderId);
            
            // 处理订单图书
            $bookList = [];
            foreach ($data['book_list'] as $bookItem) {
                // 获取图书信息
                $bookInfo = $this->bookModel->where([
                    ['id', '=', $bookItem['book_id'] ?? 0],
                    ['site_id', '=', $this->site_id]
                ])->find();
                
                if (empty($bookInfo)) {
                    // 尝试通过ISBN查找
                    if (!empty($bookItem['isbn'])) {
                        $bookInfo = $this->bookModel->where([
                            ['isbn', '=', $bookItem['isbn']],
                            ['site_id', '=', $this->site_id]
                        ])->find();
                    }
                    
                    if (empty($bookInfo)) {
                        continue; // 跳过无效的图书
                    }
                }
                
                // 计算数量和金额
                $quantity = intval($bookItem['quantity'] ?? 1);
                $price = floatval($bookInfo['recycle_price'] ?? 0);
                $subtotal = $price * $quantity;
                
                // 累计总数量和总金额
                $bookCount += $quantity;
                $totalAmount += $subtotal;
                
                // 添加订单图书
                $orderBookData = [
                    'site_id' => $this->site_id,
                    'order_id' => $orderId,
                    'book_id' => $bookInfo['id'],
                    'title' => $bookInfo['title'],
                    'author' => $bookInfo['author'],
                    'img' => $bookInfo['img'],
                    'isbn' => $bookInfo['isbn'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'create_time' => date('Y-m-d H:i:s')
                ];
                
                $this->orderBookModel->create($orderBookData);
                
                // 收集ISBN以更新扫描记录
                $bookList[] = [
                    'isbn' => $bookInfo['isbn'],
                    'quantity' => $quantity
                ];
            }
            
            // 更新订单总金额和总数量
            $this->orderModel->where([['id', '=', $orderId]])->update([
                'book_count' => $bookCount,
                'total_amount' => $totalAmount
            ]);
            
            // 检查是否开启自动下单云洋快递
            $autoOrder = isset($config['yunyang_auto_order']) ? intval($config['yunyang_auto_order']) : 1;
            
            // 初始化物流结果
            $expressResult = ['code' => -1, 'msg' => '未开启自动下单'];
            
            // 如果开启自动下单，则调用云洋物流API创建物流订单
            if ($autoOrder === 1) {
                Log::info('订单' . $orderId . '开启了自动下单，开始调用云洋物流API');
                $expressResult = $this->createExpressOrder($order, $address, $config);
            } else {
                Log::info('订单' . $orderId . '未开启自动下单，跳过云洋物流API调用');
            }
            
            // 创建物流日志模型
            $expressLogModel = new \addon\wj_books\app\model\wj_books_express_log\WjBooksExpressLog();
            
            if ($expressResult['code'] == 0) {
                // 物流下单成功
                Log::info('订单' . $orderId . '物流创建成功，运单号：' . ($expressResult['data']['waybill'] ?? '无'));
                
                // 提取物流信息
                $waybill = $expressResult['data']['waybill'] ?? '';
                $shopbill = $expressResult['data']['shopbill'] ?? '';
                $channelId = $expressResult['data']['channelId'] ?? '';
                $channel = $expressResult['data']['channel'] ?? '';
                $courierName = $expressResult['data']['courierName'] ?? '';
                $courierPhone = $expressResult['data']['courierPhone'] ?? '';
                $pickupCode = $expressResult['data']['pickupCode'] ?? '';
                $freight = $expressResult['data']['freight'] ?? 0;
                
                // 更新订单物流信息
                $expressUpdateData = [
                    'express_channel_id' => $channelId,
                    'express_channel' => $channel,
                    'express_waybill' => $waybill,
                    'express_shopbill' => $shopbill,
                    'express_status' => 1, // 待揽收
                    'express_courier_name' => $courierName,
                    'express_courier_phone' => $courierPhone,
                    'express_pickup_code' => $pickupCode,
                    'express_freight' => $freight,
                    'update_time' => date('Y-m-d H:i:s')
                ];
                
                // 记录更新信息
                Log::info('更新订单物流信息：订单ID=' . $orderId . '，物流信息=' . json_encode($expressUpdateData, JSON_UNESCAPED_UNICODE));
                
                // 执行更新
                $this->orderModel->where([['id', '=', $orderId]])->update($expressUpdateData);
                
                // 获取完整的API响应用于记录
                $responseData = $expressResult['data']['response'] ?? [];
                
                // 添加物流成功日志
                $expressLogModel->create([
                    'site_id' => $this->site_id,
                    'order_id' => $orderId,
                    'waybill' => $waybill,
                    'shopbill' => $shopbill,
                    'type_code' => 1, // 1:待揽收
                    'type' => '创建物流订单成功',
                    'content' => json_encode($responseData, JSON_UNESCAPED_UNICODE),
                    'weight' => $responseData['result']['weight'] ?? 0,
                    'freight' => $freight,
                    'courier_name' => $courierName,
                    'courier_phone' => $courierPhone,
                    'pickup_code' => $pickupCode,
                    'create_time' => date('Y-m-d H:i:s')
                ]);
            } else {
                // 物流下单失败或未开启自动下单
                $errorMsg = $expressResult['msg'] ?? '未知错误';
                $errorDetail = $expressResult['error'] ?? '';
                
                // 只有在开启了自动下单但失败的情况下才记录错误日志
                if ($autoOrder === 1) {
                    Log::error('订单' . $orderId . '物流创建失败：' . $errorMsg);
                    if (!empty($errorDetail)) {
                        Log::error('错误详情：' . $errorDetail);
                    }
                    
                    // 更新订单状态，标记物流创建失败但订单仍然有效
                    $this->orderModel->where([['id', '=', $orderId]])->update([
                        'express_status' => 0, // 0:物流创建失败
                        'update_time' => date('Y-m-d H:i:s')
                    ]);
                    
                    // 添加物流失败日志
                    $logData = [
                        'site_id' => $this->site_id,
                        'order_id' => $orderId,
                        'waybill' => '',
                        'shopbill' => '',
                        'type_code' => 99, // 99:已取消/失败
                        'type' => '创建物流订单失败',
                        'content' => json_encode([
                            'msg' => $errorMsg,
                            'error' => $errorDetail,
                            'response' => $expressResult['response'] ?? null
                        ], JSON_UNESCAPED_UNICODE),
                        'create_time' => date('Y-m-d H:i:s')
                    ];
                    
                    $expressLogModel->create($logData);
                } else {
                    // 未开启自动下单，记录信息日志
                    Log::info('订单' . $orderId . '未开启自动下单，无需创建物流订单');
                }
            }
            
            // 更新扫描记录状态（只更新每个ISBN最近的一条记录）
            $scanRecordsService = new WjBooksScanRecordsService();
            try {
                foreach ($bookList as $book) {
                    if (!empty($book['isbn'])) {
                        $scanRecordsService->updateStatusByIsbn($book['isbn'], 2); // 状态2:已提交回收
                        Log::info('更新扫描记录状态成功，ISBN：' . $book['isbn'] . '，状态：2(已提交回收)');
                    }
                }
            } catch (\Exception $e) {
                // 记录异常但不中断流程
                Log::error('更新扫描记录状态异常：' . $e->getMessage());
            }
            
            // 更新图书回收次数
            try {
                foreach ($bookList as $book) {
                    if (!empty($book['isbn'])) {
                        $this->bookModel->where([
                            ['isbn', '=', $book['isbn']],
                            ['site_id', '=', $this->site_id]
                        ])->inc('recycle_count', $book['quantity'])->update();
                        Log::info('更新图书回收次数成功，ISBN：' . $book['isbn'] . '，增加数量：' . $book['quantity']);
                    }
                }
            } catch (\Exception $e) {
                // 记录异常但不中断流程
                Log::error('更新图书回收次数异常：' . $e->getMessage());
            }
            
            // 清空回收车
            try {
                $cartResult = $this->cartModel->where([
                    ['site_id', '=', $this->site_id],
                    ['member_id', '=', $this->member_id]
                ])->delete();
                Log::info('清空回收车成功，会员ID：' . $this->member_id . '，影响记录数：' . $cartResult);
            } catch (\Exception $e) {
                // 记录异常但不中断流程
                Log::error('清空回收车异常：' . $e->getMessage());
            }
            
            // 提交事务
            Db::commit();
            Log::info('订单创建完成，提交事务成功，订单ID：' . $orderId);
            
            // 获取物流信息用于返回
            $expressWaybill = $expressResult['code'] == 0 ? ($expressResult['data']['waybill'] ?? '') : '';
            $expressCourierName = $expressResult['code'] == 0 ? ($expressResult['data']['courierName'] ?? '') : '';
            $expressCourierPhone = $expressResult['code'] == 0 ? ($expressResult['data']['courierPhone'] ?? '') : '';
            $expressPickupCode = $expressResult['code'] == 0 ? ($expressResult['data']['pickupCode'] ?? '') : '';
            
            // 构建返回数据
            $returnData = [
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'total_amount' => $totalAmount,
                'book_count' => $bookCount,
                'express_waybill' => $expressWaybill,
                'express_courier_name' => $expressCourierName,
                'express_courier_phone' => $expressCourierPhone,
                'express_pickup_code' => $expressPickupCode
            ];
            
            // 记录成功日志
            Log::info('订单创建成功，返回数据：' . json_encode($returnData, JSON_UNESCAPED_UNICODE));
            
            return [
                'code' => 0,
                'msg' => '订单创建成功',
                'data' => $returnData
            ];
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            
            // 记录详细异常信息
            $errorMsg = $e->getMessage();
            $errorTrace = $e->getTraceAsString();
            $errorFile = $e->getFile();
            $errorLine = $e->getLine();
            
            Log::error('创建订单异常：' . $errorMsg);
            Log::error('异常文件：' . $errorFile . '，行号：' . $errorLine);
            Log::error('异常堆栈：' . $errorTrace);
            
            // 返回友好错误信息
            $friendlyMsg = '订单创建失败';
            if (config('app.debug')) {
                $friendlyMsg .= '：' . $errorMsg;
            } else {
                $friendlyMsg .= '，请稍后重试';
            }
            
            return [
                'code' => -1,
                'msg' => $friendlyMsg,
                'error' => $errorMsg, // 仅在调试模式下会使用
                'error_trace' => $errorTrace // 仅在调试模式下会使用
            ];
        }
    }
    
    /**
     * 生成订单号
     * @return string
     */
    private function generateOrderNo()
    {
        return 'WJ' . date('YmdHis') . mt_rand(1000, 9999);
    }
    
    /**
     * 创建物流订单
     * @param WjBooksOrder $order 订单对象
     * @param MemberAddress $address 地址对象
     * @param WjBooksConfig $config 配置对象
     * @return array
     */
    private function createExpressOrder($order, $address, $config)
    {
        try {
            // 检查配置是否完整
            if (empty($config['yunyang_appid']) || empty($config['yunyang_app_secret'])) {
                Log::error('云洋物流配置不完整，无法创建物流订单');
                return ['code' => -1, 'msg' => '云洋物流配置不完整'];
            }
            
            // 构建请求参数
            $timeStamp = (string)time() * 1000; // 毫秒时间戳
            $requestId = md5(uniqid(mt_rand(), true)); // 随机请求ID
            $appid = $config['yunyang_appid'];
            $secretKey = $config['yunyang_app_secret'];
            
            // 生成签名
            $sign = md5($appid . $requestId . $timeStamp . $secretKey);
            
            // 获取收件人和发件人地址信息
            $senderProvince = $this->getProvinceName($address['province_id']);
            $senderCity = $this->getCityName($address['city_id']);
            $senderCounty = $this->getDistrictName($address['district_id']);
            $senderAddress = $this->getFullAddress($address);
            
            $receiverName = $config['express_receiver_name'] ?: '图书回收中心';
            $receiverMobile = $config['express_receiver_mobile'] ?: '18888888888';
            $receiverProvince = $config['express_receiver_province'] ?: '北京市';
            $receiverCity = $config['express_receiver_city'] ?: '北京市';
            $receiverCounty = $config['express_receiver_county'] ?: '朝阳区';
            $receiverTown = $config['express_receiver_town'] ?: '';
            $receiverLocation = $config['express_receiver_location'] ?: '图书回收中心';
            $receiverAddress = $this->getReceiverFullAddress($config);
            
            // 构建请求内容
            $content = [
                'channelTag' => '智能', // 必填：智能，得物，重货
                'channelSubTag' => $config['yunyang_channel_subtag'] ?: '京东', // 指定快递类型
                'sender' => $address['name'],
                'senderMobile' => $address['mobile'],
                'senderProvince' => $senderProvince,
                'senderCity' => $senderCity,
                'senderCounty' => $senderCounty,
                'senderLocation' => $address['address'],
                'senderAddress' => $senderAddress,
                'receiver' => $receiverName,
                'receiverMobile' => $receiverMobile,
                'receiveProvince' => $receiverProvince,
                'receiveCity' => $receiverCity,
                'receiveCounty' => $receiverCounty,
                'receiveTown' => $receiverTown,
                'receiveLocation' => $receiverLocation,
                'receiveAddress' => $receiverAddress,
                'weight' => 3, // 默认3公斤
                'packageCount' => 1,
                'itemName' => '图书',
                'billRemark' => '回收订单：' . $order['order_no'],
                // 自定义扩展字段，回调时会原样返回
                'extendField1' => $order['order_no'], // 存储订单号
                'extendField2' => $this->site_id, // 存储站点ID
                'extendField3' => $order['id'] // 存储订单ID
            ];
            
            // 构建完整请求
            $requestData = [
                'serviceCode' => 'ADD_BILL_INTELLECT',
                'timeStamp' => $timeStamp,
                'requestId' => $requestId,
                'appid' => $appid,
                'sign' => $sign,
                'content' => $content
            ];
            
            // 记录API调用请求日志
            Log::info('云洋物流API请求：' . json_encode($requestData, JSON_UNESCAPED_UNICODE));
            
            // 发送请求
            $curl = curl_init();
            
            
            $apiUrl = 'https://api.yunyangwl.com/api/wuliu/openService';
            
            curl_setopt($curl, CURLOPT_URL, $apiUrl);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 设置超时时间为10秒
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $errorMsg = curl_error($curl);
            curl_close($curl);
            
            // 记录API调用响应日志
            Log::info('云洋物流API响应状态码：' . $httpCode);
            Log::info('云洋物流API响应内容：' . $response);
            
            // 处理请求失败情况
            if ($httpCode != 200 || !$response) {
                Log::error('云洋物流API请求失败：HTTP状态码' . $httpCode . '，错误信息：' . $errorMsg);
                return [
                    'code' => -1,
                    'msg' => '物流订单创建失败：网络请求错误',
                    'error' => $errorMsg
                ];
            }
            
            // 解析响应
            $responseData = json_decode($response, true);
            if (!$responseData) {
                Log::error('云洋物流API响应解析失败：' . $response);
                return [
                    'code' => -1,
                    'msg' => '物流订单创建失败：响应解析错误',
                    'error' => '无效的JSON响应'
                ];
            }
            
            // 检查API响应状态
            if (!isset($responseData['code']) || $responseData['code'] != '1') {
                $errorMsg = $responseData['message'] ?? '未知错误';
                Log::error('云洋物流API业务失败：' . $errorMsg);
                Log::error('云洋物流API响应详情：' . json_encode($responseData, JSON_UNESCAPED_UNICODE));
                return [
                    'code' => -1,
                    'msg' => '物流订单创建失败：' . $errorMsg,
                    'error' => $errorMsg,
                    'response' => $responseData
                ];
            }
            
            // 记录成功响应
            Log::info('云洋物流API成功响应：' . json_encode($responseData, JSON_UNESCAPED_UNICODE));
            
            // 从API返回结果中提取物流信息
            // 根据文档，成功时result中包含waybill字段，也在message中返回
            $waybill = $responseData['result']['waybill'] ?? $responseData['message'] ?? '';
            $shopbill = $responseData['result']['shopbill'] ?? '';
            $channel = $responseData['result']['channel'] ?? '';
            $channelId = $content['channelSubTag'] ?? '';
            $courierName = $responseData['result']['courierName'] ?? '';
            $courierPhone = $responseData['result']['courierPhone'] ?? '';
            $pickupCode = $responseData['result']['pickupCode'] ?? '';
            $freight = $responseData['result']['freight'] ?? 0;
            
            // 记录成功日志
            Log::info('云洋物流订单创建成功：运单号=' . $waybill . '，商家单号=' . $shopbill);
            
            return [
                'code' => 0,
                'msg' => '物流订单创建成功',
                'data' => [
                    'waybill' => $waybill,
                    'shopbill' => $shopbill,
                    'channel' => $channel,
                    'channelId' => $channelId,
                    'courierName' => $courierName,
                    'courierPhone' => $courierPhone,
                    'pickupCode' => $pickupCode,
                    'freight' => $freight,
                    'response' => $responseData // 保存完整响应以便后续处理
                ]
            ];
        } catch (\Exception $e) {
            // 记录异常详细信息
            Log::error('创建物流订单异常：' . $e->getMessage());
            Log::error('异常堆栈：' . $e->getTraceAsString());
            
            return [
                'code' => -1,
                'msg' => '物流订单创建异常：' . $e->getMessage(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * 获取省份名称
     * @param int $provinceId
     * @return string
     */
    private function getProvinceName($provinceId)
    {
        // 这里应该查询地区表获取省份名称
        // 简化处理，直接返回
        return '北京市';
    }
    
    /**
     * 获取城市名称
     * @param int $cityId
     * @return string
     */
    private function getCityName($cityId)
    {
        // 这里应该查询地区表获取城市名称
        // 简化处理，直接返回
        return '北京市';
    }
    
    /**
     * 获取区县名称
     * @param int $districtId
     * @return string
     */
    private function getDistrictName($districtId)
    {
        // 这里应该查询地区表获取区县名称
        // 简化处理，直接返回
        return '朝阳区';
    }
    
    /**
     * 获取完整地址
     * @param MemberAddress $address
     * @return string
     */
    private function getFullAddress($address)
    {
        if (!empty($address['full_address'])) {
            return $address['full_address'];
        }
        
        // 拼接完整地址
        $province = $this->getProvinceName($address['province_id']);
        $city = $this->getCityName($address['city_id']);
        $district = $this->getDistrictName($address['district_id']);
        
        return $province . $city . $district . $address['address'];
    }
    
    /**
     * 获取收件人完整地址
     * @param WjBooksConfig $config
     * @return string
     */
    private function getReceiverFullAddress($config)
    {
        if (!empty($config['express_receiver_province']) && !empty($config['express_receiver_city']) && 
            !empty($config['express_receiver_county']) && !empty($config['express_receiver_location'])) {
            return $config['express_receiver_province'] . $config['express_receiver_city'] . 
                   $config['express_receiver_county'] . 
                   (!empty($config['express_receiver_town']) ? $config['express_receiver_town'] : '') . 
                   $config['express_receiver_location'];
        }
        
        return '北京市北京市朝阳区图书回收中心';
    }
    
    /**
     * 获取订单详情
     * @param int $orderId
     * @return array
     */
    public function getOrderDetail($orderId)
    {
        // 查询订单信息
        $order = $this->orderModel->where([
            ['id', '=', $orderId],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['deleted', '=', 0]
        ])->find();
        
        if (empty($order)) {
            return ['code' => -1, 'msg' => '订单不存在'];
        }

        // 查询订单图书
        $orderBooks = $this->orderBookModel->where([
            ['order_id', '=', $orderId],
            ['site_id', '=', $this->site_id]
        ])->select()->toArray();
        
        // 查询拒收书籍
        $rejectedBooks = [];
        if ($order['status'] == 4) {
            // 使用拒收书籍表查询
            $rejectedBookModel = new \addon\wj_books\app\model\wj_books_rejected_book\WjBooksRejectedBook();
            $rejectedImageModel = new \addon\wj_books\app\model\wj_books_rejected_images\WjBooksRejectedImages();
            
            $rejectedBookList = $rejectedBookModel->where([
                ['order_id', '=', $orderId],
                ['site_id', '=', $this->site_id]
            ])->select()->toArray();
            
            // 获取每本拒收书籍的图片
            foreach ($rejectedBookList as &$book) {
                $images = $rejectedImageModel->where([
                    ['rejected_book_id', '=', $book['id']],
                    ['site_id', '=', $this->site_id]
                ])->select()->toArray();
                
                // 整理图片数据
                $book['audit_images'] = [];
                foreach ($images as $image) {
                    $book['audit_images'][] = $image['image_url'];
                }
                
                // 设置拒收原因
                $book['reason'] = $book['reject_reason'];
                
                $rejectedBooks[] = $book;
            }
        }
        
        // 判断是否可以申请取回 - 只检查是否已申请过，不再检查截止时间
        $canRetrieve = false;
        $retrieveDeadline = null;
        
        // 首先设置截止时间（仅用于前端显示，不影响can_retrieve计算）
        if ($order['status'] == 4 && !empty($order['retrieve_deadline'])) {
            $retrieveDeadline = $order['retrieve_deadline'];
        }
        
        // 默认可以申请取回
        $canRetrieve = true;
        
        // 如果不是已完成状态或没有拒收书籍，则不可申请取回
        if ($order['status'] != 4 || empty($rejectedBooks)) {
            $canRetrieve = false;
            Log::info('订单' . $order['id'] . '不满足申请条件，状态=' . $order['status'] . '，拒收书籍数=' . count($rejectedBooks));
        } else {
            // 检查是否已申请取回 - 从订单表检查
            if (!empty($order['retrieve_applied']) && $order['retrieve_applied'] == 1) {
                $canRetrieve = false;
                Log::info('订单' . $order['id'] . '订单表已标记为已申请，设置can_retrieve=false');
            } else {
                // 从取回申请表中检查是否已申请取回
                $retrieveModel = new \addon\wj_books\app\model\wj_books_retrieve_apply\WjBooksRetrieveApply();
                $existApply = $retrieveModel->where([
                    ['order_id', '=', $order['id']],
                    ['member_id', '=', $this->member_id],
                    ['site_id', '=', $this->site_id]
                ])->find();
                
                if (!empty($existApply)) {
                    $canRetrieve = false;
                    Log::info('订单' . $order['id'] . '已有取回申请记录，设置can_retrieve=false');
                } else {
                    Log::info('订单' . $order['id'] . '可以申请取回，设置can_retrieve=true');
                }
            }
        }

        // 查询地址信息
        $addressModel = new MemberAddress();
        $address = $addressModel->where([
            ['id', '=', $order['address_id']]
        ])->find();
        
        // 构建返回数据
        $data = [
            'order_info' => $order->toArray(),
            'book_list' => $orderBooks,
            'rejected_books' => $rejectedBooks,
            'can_retrieve' => $canRetrieve,
            'retrieve_deadline' => $retrieveDeadline,
            'address_info' => $address ? $address->toArray() : []
        ];
        
        return ['code' => 0, 'data' => $data];
    }
    
    /**
     * 获取订单列表
     * @param int $page
     * @param int $pageSize
     * @param int $status 订单状态，0表示全部
     * @return array
     */
    public function getOrderList($page = 1, $pageSize = 10, $status = 0)
    {
        $where = [
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['deleted', '=', 0]
        ];
        
        // 按状态筛选
        if ($status > 0) {
            $where[] = ['status', '=', $status];
        }
        
        // 查询订单总数
        $count = $this->orderModel->where($where)->count();
        
        // 分页查询订单
        $list = $this->orderModel->where($where)
            ->order('create_time', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();
        
        // 查询每个订单的图书列表
        foreach ($list as &$order) {
            $books = $this->orderBookModel->where([
                ['order_id', '=', $order['id']],
                ['site_id', '=', $this->site_id]
            ])->select()->toArray();
            
            $order['book_list'] = $books;
        }
        
        return [
            'count' => $count,
            'list' => $list,
            'page' => $page,
            'page_size' => $pageSize
        ];
    }
    
    /**
     * 取消订单
     * @param int $orderId
     * @param string $cancelReason
     * @return array
     */
    public function cancelOrder($orderId, $cancelReason = '用户取消')
    {
        // 查询订单
        $order = $this->orderModel->where([
            ['id', '=', $orderId],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['deleted', '=', 0]
        ])->find();
        
        if (empty($order)) {
            return ['code' => -1, 'msg' => '订单不存在'];
        }
        
        // 检查订单状态
        if ($order['status'] != 1) {
            return ['code' => -1, 'msg' => '只有待上门状态的订单可以取消'];
        }
        
        // 开启事务
        Db::startTrans();
        try {
            // 更新订单状态
            $this->orderModel->where([
                ['id', '=', $orderId]
            ])->update([
                'status' => 5, // 已取消
                'cancel_time' => date('Y-m-d H:i:s'),
                'cancel_reason' => $cancelReason,
                'update_time' => date('Y-m-d H:i:s')
            ]);
            
            // 如果有物流单号，调用云洋物流取消接口
            if (!empty($order['express_waybill'])) {
                $this->cancelExpressOrder($order);
            }
            
            // 提交事务
            Db::commit();
            
            return ['code' => 0, 'msg' => '订单取消成功'];
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::error('取消订单异常：' . $e->getMessage());
            
            return ['code' => -1, 'msg' => '订单取消失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 取消物流订单
     * @param WjBooksOrder $order
     * @return bool
     */
    private function cancelExpressOrder($order)
    {
        try {
            // 获取配置
            $config = $this->configModel->where([['site_id', '=', $this->site_id]])->find();
            
            // 检查配置是否完整
            if (empty($config['yunyang_appid']) || empty($config['yunyang_app_secret'])) {
                Log::error('取消物流订单失败：云洋物流配置不完整');
                return false;
            }
            
            // 构建请求参数
            $timeStamp = (string)time() * 1000; // 毫秒时间戳
            $requestId = md5(uniqid(mt_rand(), true)); // 随机请求ID
            $appid = $config['yunyang_appid'];
            $secretKey = $config['yunyang_app_secret'];
            
            // 生成签名
            $sign = md5($appid . $requestId . $timeStamp . $secretKey);
            
            // 构建请求内容
            $content = [
                'waybill' => $order['express_waybill'],
                'shopbill' => $order['express_shopbill']
            ];
            
            // 构建完整请求
            $requestData = [
                'serviceCode' => 'CANCEL',
                'timeStamp' => $timeStamp,
                'requestId' => $requestId,
                'appid' => $appid,
                'sign' => $sign,
                'content' => $content
            ];
            
            // 发送请求
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, 'https://api.yunyangwl.com/api/wuliu/openService');
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            // 解析响应
            $responseData = json_decode($response, true);
            
            // 记录API调用日志
            Log::info('云洋物流取消API调用：' . json_encode($requestData, JSON_UNESCAPED_UNICODE));
            Log::info('云洋物流取消API响应：' . json_encode($responseData, JSON_UNESCAPED_UNICODE));
            
            if (!isset($responseData['code']) || $responseData['code'] != '1') {
                Log::error('取消物流订单失败：' . ($responseData['message'] ?? '未知错误'));
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('取消物流订单异常：' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除订单
     * @param int $orderId
     * @return array
     */
    public function deleteOrder($orderId)
    {
        // 查询订单
        $order = $this->orderModel->where([
            ['id', '=', $orderId],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['deleted', '=', 0]
        ])->find();
        
        if (empty($order)) {
            return ['code' => -1, 'msg' => '订单不存在'];
        }
        
        // 检查订单状态，只有已完成或已取消的订单可以删除
        if ($order['status'] != 4 && $order['status'] != 5) {
            return ['code' => -1, 'msg' => '只有已完成或已取消的订单可以删除'];
        }
        
        try {
            // 逻辑删除订单
            $this->orderModel->where([
                ['id', '=', $orderId]
            ])->update([
                'deleted' => 1,
                'update_time' => date('Y-m-d H:i:s')
            ]);
            
            return ['code' => 0, 'msg' => '订单删除成功'];
        } catch (\Exception $e) {
            Log::error('删除订单异常：' . $e->getMessage());
            return ['code' => -1, 'msg' => '订单删除失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 获取物流信息
     * @param string $waybill 物流单号
     * @param string $channel 物流渠道
     * @return array
     */
    public function getExpressInfo($waybill, $channel = '')
    {
        if (empty($waybill)) {
            return ['code' => -1, 'msg' => '物流单号不能为空'];
        }
        
        try {
            // 获取配置信息
            $config = $this->configModel->where([['site_id', '=', $this->site_id]])->find();
            if (empty($config)) {
                return ['code' => -1, 'msg' => '系统配置不存在'];
            }
            
            // 查询数据库中的物流日志
            $expressLogModel = new \addon\wj_books\app\model\wj_books_express_log\WjBooksExpressLog();
            $logs = $expressLogModel->where([
                ['waybill', '=', $waybill],
                ['site_id', '=', $this->site_id]
            ])->order('create_time', 'desc')->select()->toArray();
            
            // 如果没有物流日志或者最后一条日志创建时间超过1小时，则调用云洋物流API查询最新状态
            $needRefresh = true;
            if (!empty($logs)) {
                $lastLog = $logs[0];
                $lastTime = strtotime($lastLog['create_time']);
                if (time() - $lastTime < 3600) { // 1小时内的日志不需要刷新
                    $needRefresh = false;
                }
            }
            
            $expressData = [];
            
            if ($needRefresh) {
                // 调用云洋物流API查询物流状态
                $apiResult = $this->queryExpressStatus($waybill, $channel, $config);
                
                if ($apiResult['code'] == 0 && !empty($apiResult['data'])) {
                    $expressData = $apiResult['data'];
                    
                    // 记录最新的物流日志
                    if (!empty($expressData['traces'])) {
                        $latestTrace = $expressData['traces'][0];
                        $expressLogModel->create([
                            'site_id' => $this->site_id,
                            'order_id' => $expressData['order_id'] ?? 0,
                            'waybill' => $waybill,
                            'shopbill' => $expressData['shopbill'] ?? '',
                            'type_code' => $expressData['status_code'] ?? 0,
                            'type' => $latestTrace['status'] ?? '',
                            'content' => json_encode($apiResult['response'] ?? [], JSON_UNESCAPED_UNICODE),
                            'create_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                } else {
                    // API查询失败，使用数据库中的日志
                    $expressData = [
                        'waybill' => $waybill,
                        'channel' => $channel,
                        'status' => $logs[0]['type'] ?? '查询中',
                        'status_code' => $logs[0]['type_code'] ?? 0,
                        'traces' => []
                    ];
                    
                    // 将日志转换为轨迹
                    foreach ($logs as $log) {
                        $expressData['traces'][] = [
                            'time' => $log['create_time'],
                            'status' => $log['type'],
                            'content' => $log['content']
                        ];
                    }
                }
            } else {
                // 使用数据库中的日志
                $expressData = [
                    'waybill' => $waybill,
                    'channel' => $channel,
                    'status' => $logs[0]['type'] ?? '查询中',
                    'status_code' => $logs[0]['type_code'] ?? 0,
                    'traces' => []
                ];
                
                // 将日志转换为轨迹
                foreach ($logs as $log) {
                    $expressData['traces'][] = [
                        'time' => $log['create_time'],
                        'status' => $log['type'],
                        'content' => json_decode($log['content'], true)
                    ];
                }
            }
            
            return ['code' => 0, 'data' => $expressData];
        } catch (\Exception $e) {
            Log::error('查询物流信息异常：' . $e->getMessage());
            return ['code' => -1, 'msg' => '查询物流信息失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 查询物流状态
     * @param string $waybill 物流单号
     * @param string $channel 物流渠道
     * @param WjBooksConfig $config 配置信息
     * @return array
     */
    private function queryExpressStatus($waybill, $channel, $config)
    {
        try {
            // 检查配置是否完整
            if (empty($config['yunyang_appid']) || empty($config['yunyang_app_secret'])) {
                return ['code' => -1, 'msg' => '云洋物流配置不完整'];
            }
            
            // 构建请求参数
            $timeStamp = (string)time() * 1000; // 毫秒时间戳
            $requestId = md5(uniqid(mt_rand(), true)); // 随机请求ID
            $appid = $config['yunyang_appid'];
            $secretKey = $config['yunyang_app_secret'];
            
            // 生成签名
            $sign = md5($appid . $requestId . $timeStamp . $secretKey);
            
            // 构建请求内容
            $content = [
                'waybill' => $waybill
            ];
            
            if (!empty($channel)) {
                $content['channel'] = $channel;
            }
            
            // 构建完整请求
            $requestData = [
                'serviceCode' => 'BILL_TRACE',
                'timeStamp' => $timeStamp,
                'requestId' => $requestId,
                'appid' => $appid,
                'sign' => $sign,
                'content' => $content
            ];
            
            // 发送请求
            $curl = curl_init();
            
            $apiUrl = 'https://api.yunyangwl.com/api/wuliu/openService';
            
            curl_setopt($curl, CURLOPT_URL, $apiUrl);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 设置超时时间为10秒
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $errorMsg = curl_error($curl);
            curl_close($curl);
            
            // 记录API调用响应日志
            Log::info('云洋物流查询API响应状态码：' . $httpCode);
            Log::info('云洋物流查询API响应内容：' . $response);
            
            // 处理请求失败情况
            if ($httpCode != 200 || !$response) {
                return [
                    'code' => -1,
                    'msg' => '物流查询失败：网络请求错误',
                    'error' => $errorMsg
                ];
            }
            
            // 解析响应
            $responseData = json_decode($response, true);
            if (!$responseData) {
                return [
                    'code' => -1,
                    'msg' => '物流查询失败：响应解析错误',
                    'error' => '无效的JSON响应'
                ];
            }
            
            // 检查API响应状态
            if (!isset($responseData['code']) || $responseData['code'] != '1') {
                $errorMsg = $responseData['message'] ?? '未知错误';
                return [
                    'code' => -1,
                    'msg' => '物流查询失败：' . $errorMsg,
                    'error' => $errorMsg,
                    'response' => $responseData
                ];
            }
            
            // 处理物流轨迹数据
            $traces = [];
            if (!empty($responseData['result']['traces'])) {
                foreach ($responseData['result']['traces'] as $trace) {
                    $traces[] = [
                        'time' => $trace['acceptTime'] ?? '',
                        'status' => $trace['acceptStation'] ?? '',
                        'content' => $trace['remark'] ?? ''
                    ];
                }
            }
            
            // 获取订单ID
            $orderInfo = $this->orderModel->where([
                ['express_waybill', '=', $waybill],
                ['site_id', '=', $this->site_id]
            ])->field('id')->find();
            
            $orderID = $orderInfo ? $orderInfo->id : 0;
            
            // 构建返回数据
            $data = [
                'waybill' => $waybill,
                'shopbill' => $responseData['result']['shopbill'] ?? '',
                'channel' => $responseData['result']['channel'] ?? $channel,
                'status' => $responseData['result']['state'] ?? '查询中',
                'status_code' => $this->mapExpressStatusCode($responseData['result']['state'] ?? ''),
                'order_id' => $orderID,
                'traces' => $traces,
                'courier_name' => $responseData['result']['courierName'] ?? '',
                'courier_phone' => $responseData['result']['courierPhone'] ?? '',
                'response' => $responseData
            ];
            
            return [
                'code' => 0,
                'data' => $data,
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('查询物流状态异常：' . $e->getMessage());
            return [
                'code' => -1,
                'msg' => '查询物流状态异常：' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 映射物流状态码
     * @param string $state 物流状态
     * @return int 状态码：1待揽收，2运输中，3已签收，4拒收退回，99已取消
     */
    private function mapExpressStatusCode($state)
    {
        $stateMap = [
            '待揽收' => 1,
            '已揽收' => 2,
            '运输中' => 2,
            '派送中' => 2,
            '已签收' => 3,
            '已送达' => 3,
            '已妥投' => 3,
            '拒收' => 4,
            '退回' => 4,
            '已取消' => 99,
            '已作废' => 99
        ];
        
        return $stateMap[$state] ?? 0;
    }
    
    /**
     * 申请取回不合格书籍
     * @param array $data 申请数据
     * @return array
     */
    public function applyRetrieve($data)
    {
        // 查询订单
        $order = $this->orderModel->where([
            ['id', '=', $data['order_id']],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['deleted', '=', 0]
        ])->find();
        
        if (empty($order)) {
            return ['code' => -1, 'msg' => '订单不存在'];
        }
        
        // 检查订单状态，只有已完成的订单可以申请取回
        if ($order['status'] != 4) {
            return ['code' => -1, 'msg' => '只有已完成的订单可以申请取回'];
        }
        
        // 检查是否已申请取回 - 从取回申请表中检查而不是订单表
        $retrieveModel = new \addon\wj_books\app\model\wj_books_retrieve_apply\WjBooksRetrieveApply();
        $existApply = $retrieveModel->where([
            ['order_id', '=', $data['order_id']],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (!empty($existApply)) {
            return ['code' => -1, 'msg' => '您已申请取回，请勿重复操作'];
        }
        
        // 检查是否超过取回期限
        if (!empty($order['retrieve_deadline']) && strtotime($order['retrieve_deadline']) < time()) {
            return ['code' => -1, 'msg' => '已超过取回期限，无法申请取回'];
        }
        
        // 检查拒收书籍ID是否有效
        $bookIds = $data['book_ids'];
        if (empty($bookIds)) {
            return ['code' => -1, 'msg' => '请选择要取回的书籍'];
        }
        
        // 兼容前端传递的数组格式
        if (is_array($bookIds)) {
            $bookIds = array_map('intval', $bookIds);
        }
        
        // 检查拒收书籍是否属于当前订单
        $rejectedBookModel = new \addon\wj_books\app\model\wj_books_rejected_book\WjBooksRejectedBook();
        $rejectedBooks = $rejectedBookModel->where([
            ['id', 'in', $bookIds],
            ['order_id', '=', $data['order_id']],
            ['site_id', '=', $this->site_id]
        ])->select()->toArray();
        
        if (count($rejectedBooks) == 0) {
            return ['code' => -1, 'msg' => '未找到可取回的书籍'];
        }
        
        // 过滤出可取回的书籍
        $retrievableBooks = [];
        foreach ($rejectedBooks as $book) {
            if ($book['can_retrieve'] == 1) {
                $retrievableBooks[] = $book;
            }
        }
        
        if (count($retrievableBooks) == 0) {
            return ['code' => -1, 'msg' => '所选书籍均不可取回，可能已申请或超期'];
        }
        
        // 更新bookIds为可取回的书籍ID
        $bookIds = array_column($retrievableBooks, 'id');
        
        try {
            // 创建取回申请
            $retrieveModel = new \addon\wj_books\app\model\wj_books_retrieve_apply\WjBooksRetrieveApply();
            
            $applyData = [
                'site_id' => $this->site_id,
                'order_id' => $data['order_id'],
                'member_id' => $this->member_id,
                'address_id' => $data['address_id'],
                'rejected_book_ids' => implode(',', $bookIds),
                'status' => 0, // 申请中
                'remark' => $data['remark'] ?? '',
                'create_time' => date('Y-m-d H:i:s')
            ];
            
            $apply = $retrieveModel->create($applyData);
            
            if (!$apply) {
                return ['code' => -1, 'msg' => '申请取回失败'];
            }
            
            // 更新拒收书籍的可取回状态
            $rejectedBookModel->where([
                ['id', 'in', $bookIds]
            ])->update([
                'can_retrieve' => 0 // 设置为不可取回，因为已经申请取回
            ]);
            
            // 更新订单的retrieve_applied字段
            $updateResult = $this->orderModel->where([
                ['id', '=', $data['order_id']]
            ])->update([
                'retrieve_applied' => 1,
                'update_time' => date('Y-m-d H:i:s')
            ]);
            
            Log::info('更新订单retrieve_applied字段：订单ID=' . $data['order_id'] . '，结果=' . $updateResult);
            
            return [
                'code' => 0,
                'msg' => '申请取回成功',
                'data' => [
                    'apply_id' => $apply->id
                ]
            ];
        } catch (\Exception $e) {
            Log::error('申请取回异常：' . $e->getMessage());
            return ['code' => -1, 'msg' => '申请取回失败：' . $e->getMessage()];
        }
    }
} 