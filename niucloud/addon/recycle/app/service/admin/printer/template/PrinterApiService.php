<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\printer\template;

use addon\recycle\app\printer\PrinterLib\service\PrintService;
use core\base\BaseAdminService;
use core\exception\AdminException;

// 确保 RestRequest 基础类已加载（Xpyun\model 下的类都继承自它）
if (!class_exists('addon\\recycle\\app\\printer\\PrinterLib\\model\\RestRequest', false)) {
    require_once __DIR__ . '/../../../../printer/PrinterLib/model/RestRequest.php';
}

// 注册 Xpyun\model 命名空间的自动加载器
spl_autoload_register(function ($class) {
    if (strpos($class, 'Xpyun\\model\\') === 0) {
        $relative_class = str_replace('Xpyun\\model\\', '', $class);
        $file = __DIR__ . '/../../../../printer/PrinterLib/model/' . $relative_class . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
}, true, true); // 添加到队列前面，优先尝试加载

use Xpyun\model\AddPrinterRequest;
use Xpyun\model\AddPrinterRequestItem;
use Xpyun\model\DelPrinterRequest;
use Xpyun\model\UpdPrinterRequest;
use Xpyun\model\PrinterRequest;
use Xpyun\model\QueryOrderStateRequest;

/**
 * 打印机API服务类
 * 负责与芯烨云API交互
 * Class PrinterApiService
 * @package addon\recycle\app\service\admin\printer\template
 */
class PrinterApiService extends BaseAdminService
{
    /**
     * 芯烨云PrintService实例
     * @var PrintService
     */
    protected $printService;

    public function __construct()
    {
        parent::__construct();
        $this->printService = new PrintService();
    }

    /**
     * 添加打印机到开发者账户（可批量）
     * @param string $userName 用户名
     * @param string $userKey 用户密钥
     * @param array $printers 打印机列表，格式：[['sn' => 'xxx', 'name' => 'xxx'], ...]
     * @return array
     */
    public function addPrinters(string $userName, string $userKey, array $printers): array
    {
        try {
            $request = new AddPrinterRequest($userName, $userKey);
            $request->generateSign();
            
            // 创建AddPrinterRequestItem对象数组
            $items = [];
            foreach ($printers as $printer) {
                $item = new AddPrinterRequestItem();
                $item->sn = $printer['sn'] ?? '';
                $item->name = $printer['name'] ?? '';
                $items[] = $item;
            }
            
            $request->items = $items;
            
            $result = $this->printService->xpYunAddPrinters($request);
            
            if ($result->httpStatusCode != 200) {
                return [
                    'success' => false,
                    'message' => 'HTTP请求失败，状态码：' . $result->httpStatusCode
                ];
            }
            
            if (empty($result->content) || $result->content->code != 0) {
                $errorMsg = $result->content->msg ?? '添加打印机失败';
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'api_response' => $result->content
                ];
            }
            
            return [
                'success' => true,
                'message' => '添加打印机成功',
                'data' => $result->content->data ?? null,
                'api_response' => $result->content
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage()
            ];
        }
    }

    /**
     * 修改打印机信息
     * @param string $userName 用户名
     * @param string $userKey 用户密钥
     * @param string $sn 打印机序列号
     * @param string $name 打印机名称
     * @return array
     */
    public function updatePrinter(string $userName, string $userKey, string $sn, string $name): array
    {
        try {
            $request = new UpdPrinterRequest($userName, $userKey);
            $request->generateSign();
            $request->sn = $sn;
            $request->name = $name;
            
            $result = $this->printService->xpYunUpdatePrinter($request);
            
            if ($result->httpStatusCode != 200) {
                return [
                    'success' => false,
                    'message' => 'HTTP请求失败，状态码：' . $result->httpStatusCode
                ];
            }
            
            if (empty($result->content)) {
                return [
                    'success' => false,
                    'message' => 'API响应为空'
                ];
            }
            
            // 如果code不为0，检查是否是打印机未注册的错误
            if ($result->content->code != 0) {
                $errorMsg = $result->content->msg ?? '修改打印机信息失败';
                
                // 检查是否是打印机未注册的错误
                if (stripos($errorMsg, 'PRINTER_NOT_REGISTER') !== false || 
                    stripos($errorMsg, '未注册') !== false ||
                    stripos($errorMsg, '不存在') !== false ||
                    stripos($errorMsg, 'not found') !== false ||
                    stripos($errorMsg, 'not register') !== false) {
                    // 打印机未注册，尝试先添加打印机
                    $addResult = $this->addPrinters($userName, $userKey, [['sn' => $sn, 'name' => $name]]);
                    
                    if ($addResult['success']) {
                        return [
                            'success' => true,
                            'message' => '打印机未注册，已重新注册成功',
                            'api_response' => $addResult['api_response'] ?? $result->content,
                            're_registered' => true
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => '打印机未注册，重新注册失败：' . $addResult['message'],
                            'api_response' => $result->content
                        ];
                    }
                }
                
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'api_response' => $result->content
                ];
            }
            
            return [
                'success' => true,
                'message' => '修改打印机信息成功',
                'api_response' => $result->content
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage()
            ];
        }
    }

    /**
     * 删除打印机（可批量）
     * @param string $userName 用户名
     * @param string $userKey 用户密钥
     * @param array $snList 打印机序列号列表
     * @return array
     */
    public function deletePrinters(string $userName, string $userKey, array $snList): array
    {
        try {
            $request = new DelPrinterRequest($userName, $userKey);
            $request->generateSign();
            // snlist应该是字符串数组
            $request->snlist = $snList;
            
            $result = $this->printService->xpYunDelPrinters($request);
            
            if ($result->httpStatusCode != 200) {
                return [
                    'success' => false,
                    'message' => 'HTTP请求失败，状态码：' . $result->httpStatusCode
                ];
            }
            
            if (empty($result->content)) {
                return [
                    'success' => false,
                    'message' => 'API响应为空'
                ];
            }
            
            // 如果code不为0，检查是否是打印机未注册的错误
            if ($result->content->code != 0) {
                $errorMsg = $result->content->msg ?? '删除打印机失败';
                $errorCode = $result->content->code ?? '';
                
                // 检查是否是打印机未注册的错误（这种情况下可以允许删除本地记录）
                if (stripos($errorMsg, 'PRINTER_NOT_REGISTER') !== false || 
                    stripos($errorMsg, '未注册') !== false ||
                    stripos($errorMsg, '不存在') !== false ||
                    stripos($errorMsg, 'not found') !== false ||
                    stripos($errorMsg, 'not register') !== false) {
                    // 打印机未注册，也算作成功（因为可以删除本地记录）
                    return [
                        'success' => true,
                        'message' => '打印机在芯烨云未注册，已删除本地记录',
                        'api_response' => $result->content,
                        'not_registered' => true
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'api_response' => $result->content
                ];
            }
            
            return [
                'success' => true,
                'message' => '删除打印机成功',
                'api_response' => $result->content
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage()
            ];
        }
    }

    /**
     * 查询打印机状态
     * @param string $userName 用户名
     * @param string $userKey 用户密钥
     * @param string $sn 打印机序列号
     * @return array
     */
    public function queryPrinterStatus(string $userName, string $userKey, string $sn): array
    {
        try {
            $request = new PrinterRequest($userName, $userKey);
            $request->generateSign();
            $request->sn = $sn;
            
            $result = $this->printService->xpYunQueryPrinterStatus($request);
            
            if ($result->httpStatusCode != 200) {
                return [
                    'success' => false,
                    'message' => 'HTTP请求失败，状态码：' . $result->httpStatusCode,
                    'status' => null
                ];
            }
            
            if (empty($result->content) || $result->content->code != 0) {
                $errorMsg = $result->content->msg ?? '查询打印机状态失败';
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'status' => null,
                    'api_response' => $result->content
                ];
            }
            
            // 解析状态：0-离线，1-在线正常，2-在线不正常
            $status = $result->content->data ?? null;
            $statusText = $this->getStatusText($status);
            
            return [
                'success' => true,
                'status' => $status,
                'status_text' => $statusText,
                'message' => '查询成功',
                'api_response' => $result->content
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage(),
                'status' => null
            ];
        }
    }

    /**
     * 批量查询打印机状态
     * @param string $userName 用户名
     * @param string $userKey 用户密钥
     * @param array $snList 打印机序列号列表
     * @return array
     */
    public function queryPrintersStatus(string $userName, string $userKey, array $snList): array
    {
        try {
            // 批量查询使用DelPrinterRequest（因为它有snlist字段）
            $request = new DelPrinterRequest($userName, $userKey);
            $request->generateSign();
            $request->snlist = $snList; // snlist应该是字符串数组
            
            $result = $this->printService->xpYunQueryPrintersStatus($request);
            
            if ($result->httpStatusCode != 200) {
                return [
                    'success' => false,
                    'message' => 'HTTP请求失败，状态码：' . $result->httpStatusCode,
                    'data' => []
                ];
            }
            
            if (empty($result->content) || $result->content->code != 0) {
                $errorMsg = $result->content->msg ?? '批量查询打印机状态失败';
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'data' => [],
                    'api_response' => $result->content
                ];
            }
            
            // 解析状态数据
            $statusList = [];
            if (isset($result->content->data) && is_array($result->content->data)) {
                foreach ($result->content->data as $item) {
                    $statusList[] = [
                        'sn' => $item->sn ?? '',
                        'status' => $item->state ?? null,
                        'status_text' => $this->getStatusText($item->state ?? null)
                    ];
                }
            }
            
            return [
                'success' => true,
                'data' => $statusList,
                'message' => '查询成功',
                'api_response' => $result->content
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * 获取状态文本
     * @param int|null $status
     * @return string
     */
    private function getStatusText(?int $status): string
    {
        switch ($status) {
            case 0:
                return '离线';
            case 1:
                return '在线正常';
            case 2:
                return '在线不正常';
            default:
                return '未知状态';
        }
    }

    /**
     * 查询订单是否打印成功
     * @param string $userName 用户名
     * @param string $userKey 用户密钥
     * @param string $orderId 订单号
     * @return array
     */
    public function queryOrderState(string $userName, string $userKey, string $orderId): array
    {
        try {
            $request = new QueryOrderStateRequest($userName, $userKey);
            $request->generateSign();
            $request->orderId = $orderId;
            
            $result = $this->printService->xpYunQueryOrderState($request);
            
            if ($result->httpStatusCode != 200) {
                return [
                    'success' => false,
                    'message' => 'HTTP请求失败，状态码：' . $result->httpStatusCode,
                    'status' => null
                ];
            }
            
            if (empty($result->content) || $result->content->code != 0) {
                $errorMsg = $result->content->msg ?? '查询订单状态失败';
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'status' => null,
                    'api_response' => $result->content
                ];
            }
            
            // 解析状态：0-待打印，1-已打印，2-打印失败
            $status = $result->content->data->orderStatus ?? null;
            $statusText = $this->getOrderStatusText($status);
            
            return [
                'success' => true,
                'status' => $status,
                'status_text' => $statusText,
                'message' => '查询成功',
                'api_response' => $result->content
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage(),
                'status' => null
            ];
        }
    }

    /**
     * 获取订单状态文本
     * @param int|null $status
     * @return string
     */
    private function getOrderStatusText(?int $status): string
    {
        switch ($status) {
            case 0:
                return '待打印';
            case 1:
                return '已打印';
            case 2:
                return '打印失败';
            default:
                return '未知状态';
        }
    }
}

