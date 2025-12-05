<?php

namespace addon\phone_shop\app\service\core\delivery\delivery_search;

use addon\tk_yht\app\service\core\ExprService;
use think\Exception;
use think\facade\Cache;
use think\facade\Log;


class YhtDeliverySearch extends BaseDeliverySearch
{

    protected $logistic_config;

    /**
     * @param array $config
     * @return void
     */
    protected function initialize(array $config = [])
    {
        parent::initialize($config);
        $this->logistic_config = $config;
    }


    /**
     * 物流跟踪查询
     * @param array $data
     * @return array
     */
    public function search(array $data = [])
    {
        if (!Cache::get($data['logistic_no'])) {
            $result = (new ExprService())->exprQuery($data['logistic_no']);
            Cache::set($data['logistic_no'], $result,60*15);
        }
        $result = Cache::get($data['logistic_no']);
        if (isset($result['status']) && $result['status'] == 200) {
            $list = [];
            if (isset($result['data']) && !empty($result['data']['content'])) {
                foreach ($result['data']['content'] as $trace) {
                    $list[] = [
                        'datetime' => $trace['time'],
                        'remark' => $trace['status']
                    ];
                }
            }
            return [
                'success' => 'success',
                'reason' => '',
                'status' => !empty($result['data']['status']) ? $result['data']['status'] : '',
                'status_name' => !empty($result['data']['status']) ? $result['data']['status'] : '',
                'shipper_code' => '',
                'logistic_code' => $data['logistic_no'],
                'list' => $list
            ];

        } else {
            return [
                'success' => 'false',
                'reason' => '',
                'status' => !empty($result['data']['status']) ? $result['data']['status'] : '',
                'status_name' => !empty($result['data']['status']) ? $result['data']['status'] : '',
                'shipper_code' => '',
                'logistic_code' => $data['logistic_no'],
                'list' => ''
            ];
        }
    }
}