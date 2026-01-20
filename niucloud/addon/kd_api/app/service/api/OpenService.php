<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\kd_api\app\service\api;

use addon\kd_api\app\dict\order\OrderDict;
use addon\kd_api\app\model\kdapi_order\KdapiOrder;
use app\service\core\weapp\CoreWeappConfigService;
use core\base\BaseApiService;

/**
 * api对接服务层
 * Class KdapiApiService
 * @package addon\kd_api\app\service\admin\kdapi_api
 */
class OpenService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getLink($data)
    {
        $pub_id = $this->request->pub_id;
        $site_id = $this->site_id;
        $core_weapp_service = new CoreWeappConfigService();
        $weapp_config = $core_weapp_service->getWeappConfig($site_id);
        $page = '/addon/tk_jhkd/pages/index?pub_id=' . $pub_id . '&sid=' . time();
        $link = [
            'original_id' => $weapp_config['weapp_original'],
            'app_id' => $weapp_config['app_id'],
            'page' => $page,
            'url' => get_wap_domain($site_id) . $page,
        ];
        return $link;
    }

    public function getOrder(array $where = [])
    {
        $field = 'id,site_id,member_id,order_id,title,order_money,pay_money,commission,status,is_js,sid,pub_id,create_time';
        $order = 'create_time desc';
        $this->model = new KdapiOrder();
        $pub_id = $this->request->pub_id;
        $site_id = $this->request->site_id;
        $search_model = $this->model
            ->where([
                ['site_id', "=", $site_id],
                ['pub_id', '=', $pub_id]
            ])
            ->withSearch(["member_id", "order_id", "title", "status", "is_js", "sid", "pub_id", "create_time"], $where)->with(['member'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['status_name'] = OrderDict::getStatusDict($v['status']);
        }
        return $list;
    }
}
