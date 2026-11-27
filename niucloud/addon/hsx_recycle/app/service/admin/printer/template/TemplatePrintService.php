<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\printer\template;

use addon\hsx_recycle\app\service\core\third_party\RecycleThirdPartyConfigService;
use addon\hsx_recycle\app\service\admin\printer\template\VariableReplaceService;
use addon\hsx_recycle\app\service\admin\printer\template\PrinterApiService;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 模板打印服务类
 * 负责模板的打印功能
 * Class TemplatePrintService
 * @package addon\hsx_recycle\app\service\admin\printer\template
 */
class TemplatePrintService extends BaseAdminService
{
    /**
     * 变量替换服务
     * @var VariableReplaceService
     */
    protected $variableReplaceService;

    /**
     * 打印机API服务
     * @var PrinterApiService
     */
    protected $printerApiService;

    public function __construct()
    {
        parent::__construct();
        $this->variableReplaceService = new VariableReplaceService();
        $this->printerApiService = new PrinterApiService();
    }

    /**
     * 发送到打印机
     * @param array $printer 打印机配置
     * @param string $content 打印内容
     * @param bool $checkStatus 是否在打印前检查打印机状态（默认true）
     * @return array
     */
    public function sendToPrinter(array $printer, string $content, bool $checkStatus = true): array
    {
        try {
            // 检查打印机配置
            if (empty($printer['user_name']) || empty($printer['user_key']) || empty($printer['sn'])) {
                throw new AdminException('打印机配置不完整，请检查用户名、密钥和设备号');
            }

            // 打印前检查打印机状态
            if ($checkStatus) {
                $statusResult = $this->printerApiService->queryPrinterStatus(
                    $printer['user_name'],
                    $printer['user_key'],
                    $printer['sn']
                );

                if (!$statusResult['success']) {
                    return [
                        'success' => false,
                        'message' => '无法查询打印机状态：' . $statusResult['message']
                    ];
                }

                $printerStatus = $statusResult['status'];
                if ($printerStatus === 0) {
                    return [
                        'success' => false,
                        'message' => '打印机离线，请检查打印机是否在线后再试',
                        'printer_status' => $printerStatus,
                        'printer_status_text' => $statusResult['status_text']
                    ];
                }

                if ($printerStatus === 2) {
                    return [
                        'success' => false,
                        'message' => '打印机状态异常（可能缺纸），请检查打印机状态后再试',
                        'printer_status' => $printerStatus,
                        'printer_status_text' => $statusResult['status_text']
                    ];
                }
            }

            // 修复内容格式问题
            $content = $this->fixXmlQuotes($content);

            $thirdPartyConfigService = new RecycleThirdPartyConfigService();
            $printerApiConfig = $thirdPartyConfigService->getPrinterConfig($this->site_id);
            if (empty($printerApiConfig) || !$thirdPartyConfigService->isPrinterConfigComplete($this->site_id)) {
                return [
                    'success' => false,
                    'message' => '打印服务未启用或配置不完整'
                ];
            }

            $baseUrl = $printerApiConfig['base_url'] ?? '';
            $printLabelPath = $printerApiConfig['print_label_path'] ?? '';
            if (empty($baseUrl) || empty($printLabelPath)) {
                return [
                    'success' => false,
                    'message' => '芯烨云打印接口地址未配置'
                ];
            }
            $api_url = rtrim($baseUrl, '/') . '/' . ltrim($printLabelPath, '/');

            // 生成签名
            $timestamp = time();
            $sign_str = $printer['user_name'] . $printer['user_key'] . $timestamp;
            $sign = sha1($sign_str);

            $post_data = [
                'user' => $printer['user_name'],
                'timestamp' => $timestamp,
                'sign' => $sign,
                'debug' => 0,
                'sn' => $printer['sn'],
                'content' => $content,
                'copies' => 1,
                'horizontalOffset' => 0,
                'verticalOffset' => 0
            ];

            // 使用JSON格式调用API
            $result = $this->sendJsonRequest($api_url, $post_data, $printerApiConfig);

            return $result;

        } catch (AdminException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage()
            ];
        }
    }

    /**
     * 修复XML中的引号问题（只处理标签属性，不动标签内容）
     * @param string $content
     * @return string
     */
    private function fixXmlQuotes(string $content): string
    {
        // 芯烨云要求XML属性值必须用双引号包围
        // 只在XML开标签 <TAG ...> 内部修复属性引号，不影响标签之间的文本/URL内容
        $content = preg_replace_callback('/<([A-Z][A-Z0-9]*)(\s[^>]*)?>/', function ($matches) {
            $tagName = $matches[1];
            $attrs = $matches[2] ?? '';
            if (empty(trim($attrs))) {
                return $matches[0];
            }
            // 修复单引号属性值
            $attrs = preg_replace("/(\w+)='([^']*)'/", '$1="$2"', $attrs);
            // 修复缺失引号的属性值
            $attrs = preg_replace('/(\w+)=([^"\s>][^\s>]*)/', '$1="$2"', $attrs);
            return '<' . $tagName . $attrs . '>';
        }, $content);

        // 确保TEXT标签内的文本内容不包含XML特殊字符
        $content = preg_replace_callback('/<TEXT[^>]*>([^<]*)<\/TEXT>/', function ($matches) {
            $full_tag = $matches[0];
            $text_content = $matches[1];
            $escaped = htmlspecialchars($text_content, ENT_QUOTES, 'UTF-8', false);
            return str_replace($text_content, $escaped, $full_tag);
        }, $content);

        return $content;
    }

    /**
     * 发送JSON请求到芯烨云API
     * @param string $url
     * @param array $data
     * @return array
     */
    private function sendJsonRequest(string $url, array $data, array $config = []): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)($config['timeout'] ?? 30));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)($config['connect_timeout'] ?? 10));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json;charset=UTF-8',
            'Content-Length: ' . strlen(json_encode($data)),
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        // 检查CURL错误
        if ($response === false || !empty($curl_error)) {
            $error_message = '网络请求失败';
            if ($curl_errno) {
                $error_message .= "（错误码：{$curl_errno}）";
            }
            if ($curl_error) {
                $error_message .= "：{$curl_error}";
            }

            return [
                'success' => false,
                'message' => $error_message,
                'http_code' => $http_code
            ];
        }

        // 检查HTTP状态码
        if ($http_code !== 200) {
            return [
                'success' => false,
                'message' => "HTTP请求失败，状态码：{$http_code}",
                'http_code' => $http_code
            ];
        }

        // 解析JSON响应
        $result = json_decode($response, true);

        if ($result === null) {
            return [
                'success' => false,
                'message' => 'API响应格式错误，无法解析JSON',
                'http_code' => $http_code
            ];
        }

        // 检查API返回结果
        if (isset($result['msg']) && $result['msg'] === 'ok') {
            return [
                'success' => true,
                'message' => '标签打印成功',
                'api_response' => $result
            ];
        } else {
            $error_msg = $result['msg'] ?? '未知错误';
            return [
                'success' => false,
                'message' => '打印失败：' . $error_msg,
                'api_response' => $result
            ];
        }
    }

    /**
     * 替换模板变量并打印
     * @param string $content 模板内容
     * @param array $variables 变量数据
     * @param array $printer 打印机配置
     * @return array
     */
    public function printWithVariables(string $content, array $variables, array $printer): array
    {
        // 替换变量
        $final_content = $this->variableReplaceService->replaceVariables($content, $variables);

        // 发送打印
        return $this->sendToPrinter($printer, $final_content);
    }
}
