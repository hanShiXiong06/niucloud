<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\kd_api\app\api\middleware;

use addon\kd_api\app\model\kdapi_api\KdapiApi;
use addon\kd_api\app\dict\status\StatusDict;
use app\Request;
use Closure;
use Exception;
use think\facade\Cache;
use think\facade\Log;


/**
 * 会员登录token验证
 * Class ApiCheckToken
 * @package app\api\middleware
 */
class ApiCheck
{
    /**
     * @param Request $request
     * @param Closure $next
     * @param bool $is_throw_exception 是否把错误抛出
     * @return mixed
     * @throws Exception
     */
    public function handle(Request $request, Closure $next)
    {
        $pub_id = $request->header('pub-id');
        $request_data = $request->post();
        if (!isset($request_data['timestamp'])) {
            return fail('参数缺少:timestamp');
        }
        if (!isset($request_data['request_id'])) {
            return fail('参数缺少:request_id');
        }
        if (!isset($request_data['api_key'])) {
            return fail('参数缺少:api_key');
        }
        if (!isset($request_data['sign'])) {
            return fail('参数缺少:sign');
        }
        $timestamp = $request_data['timestamp'];
        $apikey = $request_data['api_key'];
        if (time() - $timestamp > 60*3) {
            return fail('请求时间偏移超过180s');
        }
        $request_id = $request_data['request_id'];
        $pub_info = (new KdapiApi())->where(['id' => $pub_id])->findOrEmpty();
        if ($pub_info->isEmpty()) {
            return fail('无效pub_id');
        }
        if ($pub_info['api_key'] != $apikey) {
            return fail('api_key无效');
        }
        //进行qps限制
        if ($pub_info['qps'] > 0) {
            $redis_key = 'kd_api:qps:' . $pub_id;
            try {
                // 确保使用Redis驱动
                $cacheHandler = Cache::store('redis')->handler();
                if (!$cacheHandler) {
                    return fail('QPS超出限制:' . $pub_info['qps']);
                }
                $current_qps = $cacheHandler->incr($redis_key);
                // 设置过期时间为1秒
                if ($current_qps === 1) {
                    $cacheHandler->expire($redis_key, 1);
                }
                // 超过QPS限制
                if ($current_qps > $pub_info['qps']) {
                    return fail('QPS超出限制');
                }
            } catch (\Exception $e) {
                return fail('Redis缓存服务异常：' . $e->getMessage());
            }
        }
        if ($pub_info['status'] != StatusDict::SUCCESS) {
            return fail('账户未审核通过');
        }
        //进行加密验证
        $sign = $request_data['sign'];
        $str = $pub_info['api_key'] . $request_id . $timestamp;
        $signature = hash_hmac('sha256', $str, $pub_info['api_secret'], false);
        if ($signature != $sign) {
            return fail('签名验证失败,请严格按照格式生成签名');
        }
        $request->pub_info = $pub_info;
        $request->pub_id = $pub_id;
        $request->site_id = $pub_info['site_id'];
        return $next($request);
    }
}
