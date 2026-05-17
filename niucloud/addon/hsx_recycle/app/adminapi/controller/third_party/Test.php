<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\third_party;

use addon\hsx_recycle\app\service\core\third_party\CoreThirdPartyService;
use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use core\base\BaseAdminController;

/**
 * 第三方服务测试控制器
 * Class Test
 * @package addon\hsx_recycle\app\adminapi\controller\third_party
 */
class Test extends BaseAdminController
{
    /**
     * 测试所有服务
     * 访问地址: /admin/hsx_recycle/third_party.test/testAll
     */
    public function testAll()
    {
        $service = new CoreThirdPartyService();
        $siteId = $this->site_id;

        $results = [];

        // 测试1: 设备查询 - 型号查询
        try {
            $result = $service->call(
                ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
                'queryByImei',
                [
                    'imei' => '352000000000000',
                    'api' => '/apple/model'
                ],
                $siteId
            );
            $results[] = [
                'test' => '设备查询 - 型号查询',
                'status' => 'success',
                'result' => $result
            ];
        } catch (\Exception $e) {
            $results[] = [
                'test' => '设备查询 - 型号查询',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        // 测试2: 设备查询 - 保修查询
        try {
            $result = $service->call(
                ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
                'getCoverage',
                [
                    'imei' => '352000000000000'
                ],
                $siteId
            );
            $results[] = [
                'test' => '设备查询 - 保修查询',
                'status' => 'success',
                'result' => $result
            ];
        } catch (\Exception $e) {
            $results[] = [
                'test' => '设备查询 - 保修查询',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        // 测试3: 快递查询
        try {
            $result = $service->call(
                ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY,
                'query',
                [
                    'express_no' => '75******1234',
                    'express_code' => 'YTO'
                ],
                $siteId
            );
            $results[] = [
                'test' => '快递查询',
                'status' => 'success',
                'result' => $result
            ];
        } catch (\Exception $e) {
            $results[] = [
                'test' => '快递查询',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        return success($results);
    }

    /**
     * 测试设备查询
     * 访问地址: /admin/hsx_recycle/third_party.test/testDeviceQuery
     */
    public function testDeviceQuery()
    {
        $imei = $this->request->param('imei', '352000000000000');
        $api = $this->request->param('api', '/apple/model');

        $service = new CoreThirdPartyService();

        try {
            $result = $service->call(
                ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
                'queryByImei',
                [
                    'imei' => $imei,
                    'api' => $api
                ],
                $this->site_id
            );

            return success($result);
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }

    /**
     * 测试快递查询
     * 访问地址: /admin/hsx_recycle/third_party.test/testExpressQuery
     */
    public function testExpressQuery()
    {
        $expressNo = $this->request->param('express_no', '');
        $expressCode = $this->request->param('express_code', '');

        if (empty($expressNo)) {
            return fail('请输入快递单号');
        }

        $service = new CoreThirdPartyService();

        try {
            $result = $service->call(
                ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY,
                'query',
                [
                    'express_no' => $expressNo,
                    'express_code' => $expressCode
                ],
                $this->site_id
            );

            return success($result);
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }

    /**
     * 健康检查
     * 访问地址: /admin/hsx_recycle/third_party.test/healthCheck
     */
    public function healthCheck()
    {
        $service = new CoreThirdPartyService();
        $siteId = $this->site_id;

        $results = [];

        // 检查设备查询服务
        try {
            $health = $service->checkHealth($siteId, ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY);
            $results['device_query'] = $health;
        } catch (\Exception $e) {
            $results['device_query'] = ['error' => $e->getMessage()];
        }

        // 检查快递查询服务
        try {
            $health = $service->checkHealth($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY);
            $results['express_query'] = $health;
        } catch (\Exception $e) {
            $results['express_query'] = ['error' => $e->getMessage()];
        }

        // 检查快递下单服务
        try {
            $health = $service->checkHealth($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER);
            $results['express_order'] = $health;
        } catch (\Exception $e) {
            $results['express_order'] = ['error' => $e->getMessage()];
        }

        return success($results);
    }

    /**
     * 查看API调用日志
     * 访问地址: /admin/hsx_recycle/third_party.test/apiLogs
     */
    public function apiLogs()
    {
        $limit = $this->request->param('limit', 20);

        $logs = \addon\hsx_recycle\app\model\third_party\ThirdPartyApiLog::where('site_id', $this->site_id)
            ->order('create_at', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return success($logs);
    }

    /**
     * 查看费用统计
     * 访问地址: /admin/hsx_recycle/third_party.test/costStats
     */
    public function costStats()
    {
        $stats = \addon\hsx_recycle\app\model\third_party\ThirdPartyCostStats::where('site_id', $this->site_id)
            ->order('date', 'desc')
            ->select()
            ->toArray();

        return success($stats);
    }
}
