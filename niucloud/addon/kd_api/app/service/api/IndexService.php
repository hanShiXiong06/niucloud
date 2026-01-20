<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\kd_api\app\service\api;

use addon\kd_api\app\dict\order\OrderDict;
use addon\kd_api\app\model\kdapi_api\KdapiApi;
use addon\kd_api\app\model\kdapi_order\KdapiOrder;
use addon\kd_api\app\service\core\common\CommonService;
use core\base\BaseApiService;
use core\exception\CommonException;


/**
 * api对接服务层
 * Class KdapiApiService
 * @package addon\kd_api\app\service\admin\kdapi_api
 */
class IndexService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new KdapiApi();
    }

    public function getConfig()
    {
        $info = $this->model->where(['site_id' => $this->site_id, 'member_id' => $this->member_id])->findOrEmpty();
        if ($info->isEmpty()) {
            return [
                'is_open' => 0,
                'data' => []
            ];
        }
        return [
            'is_open' => 1,
            'data' => $info->toArray()
        ];
    }

    public function resetKey()
    {
        $commonService = new CommonService();
        $info = $this->model->where(['site_id' => $this->site_id, 'member_id' => $this->member_id])->findOrEmpty();
        if ($info->isEmpty()) {
            throw new CommonException('暂未获取API推广权限');
        }
        $info->api_key = $commonService->createKey();
        $info->api_secret = $commonService->createSecret();
        $info->save();
        return true;
    }

    public function saveCallbackUrl($data)
    {
        $callbackUrl = $data['callback_url'];
        $info = $this->model->where(['site_id' => $this->site_id, 'member_id' => $this->member_id])->findOrEmpty();
        if ($info->isEmpty()) {
            throw new CommonException('暂未获取API推广权限');
        }
        $info->callback_url = $callbackUrl;
        $info->save();
        return true;
    }

    public function getOrder(array $where = [])
    {
        $field = 'id,site_id,member_id,order_id,title,order_money,pay_money,commission,status,is_js,sid,pub_id,create_time';
        $order = 'create_time desc';
        $this->model = new KdapiOrder();
        //获取当前的pub_id
        $pub_id = $this->model->where(['site_id' => $this->site_id, 'member_id' => $this->member_id])->value('pub_id');
        $search_model = $this->model
            ->where([
                ['site_id', "=", $this->site_id],
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
