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

namespace addon\wj_books\app\api\controller\wj_books_express_log;

use addon\wj_books\app\service\api\wj_books_express_log\WjBooksExpressLogService;
use core\base\BaseApiController;
use think\facade\Log;
use think\Response;

/**
 * 物流回调接口控制器
 * Class ExpressCallback
 * @package addon\wj_books\app\api\controller\wj_books_express_log
 */
class ExpressCallback extends BaseApiController
{
    /**
     * 物流回调接口
     * @return Response
     */
    public function index()
    {
        try {
            // 记录访问日志
            Log::info('物流回调接口被访问：' . date('Y-m-d H:i:s'));
            Log::info('请求方法：' . $this->request->method());
            Log::info('请求路径：' . $this->request->url(true));
            Log::info('请求IP：' . $this->request->ip());
            
            // 获取回调数据
            $data = $this->request->post();
            
            // 如果没有接收到数据，尝试从原始输入流获取
            if (empty($data)) {
                $inputData = file_get_contents('php://input');
                if (!empty($inputData)) {
                    $data = json_decode($inputData, true) ?: [];
                    Log::info('从输入流获取数据：' . json_encode($data, JSON_UNESCAPED_UNICODE));
                }
            }
            
            // 记录回调数据
            Log::info('物流回调数据：' . json_encode($data, JSON_UNESCAPED_UNICODE));
            
            // 验证必要参数
            if (empty($data['waybill'])) {
                Log::warning('物流回调数据缺少运单号，返回失败');
                return json(['code' => 1, 'message' => '推送成功']); // 依然返回成功，避免第三方平台重复推送
            }
            
            // 处理回调数据
            $service = new WjBooksExpressLogService();
            $result = $service->handleCallback($data);
            
            if ($result) {
                Log::info('物流回调处理成功');
                return json(['code' => 1, 'message' => '推送成功']);
            } else {
                Log::error('物流回调处理失败');
                return json(['code' => 1, 'message' => '推送成功']); // 依然返回成功，避免第三方平台重复推送
            }
        } catch (\Exception $e) {
            // 记录异常但仍返回成功
            Log::error('物流回调处理异常：' . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            // 即使处理失败也返回成功，避免第三方平台重复推送
            return json(['code' => 1, 'message' => '推送成功']);
        }
    }
} 