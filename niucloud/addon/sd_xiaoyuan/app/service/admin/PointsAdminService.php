<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\PointsGoods;
use addon\sd_xiaoyuan\app\model\PointsOrder;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 积分商城管理服务(后台)
 */
class PointsAdminService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }

    // ========== 商品管理 ==========

    /**
     * 商品列表(分页)
     */
    public function getGoodsPage(array $where = [])
    {
        $model = (new PointsGoods())->where([['site_id', '=', $this->site_id]]);

        if (!empty($where['name'])) {
            $model = $model->whereLike('name', '%' . $where['name'] . '%');
        }
        if (isset($where['status']) && $where['status'] !== '') {
            $model = $model->where('status', intval($where['status']));
        }

        $count = $model->count();
        $page = intval($where['page'] ?? 1);
        $limit = intval($where['limit'] ?? 10);

        $list = $model->field('id,name,image,points_price,stock,exchange_count,sort,status,create_time')
            ->order('sort desc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return ['data' => $list, 'total' => $count];
    }

    /**
     * 添加商品
     */
    public function addGoods(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $data['update_time'] = time();
        $res = (new PointsGoods())->create($data);
        return $res->id;
    }

    /**
     * 编辑商品
     */
    public function editGoods(int $id, array $data)
    {
        $info = (new PointsGoods())->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('商品不存在');
        }
        $data['update_time'] = time();
        $info->save($data);
        return true;
    }

    /**
     * 删除商品
     */
    public function delGoods(int $id)
    {
        $info = (new PointsGoods())->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('商品不存在');
        }
        $info->delete();
        return true;
    }

    // ========== 订单管理 ==========

    /**
     * 订单列表(分页)
     */
    public function getOrderPage(array $where = [])
    {
        $model = (new PointsOrder())->where([['site_id', '=', $this->site_id]]);

        if (!empty($where['order_no'])) {
            $model = $model->whereLike('order_no', '%' . $where['order_no'] . '%');
        }
        if (isset($where['status']) && $where['status'] !== '') {
            $model = $model->where('status', intval($where['status']));
        }

        $count = $model->count();
        $page = intval($where['page'] ?? 1);
        $limit = intval($where['limit'] ?? 10);

        $list = $model->order('id desc')
            ->page($page, $limit)
            ->append(['status_text'])
            ->select()
            ->toArray();

        return ['data' => $list, 'total' => $count];
    }

    /**
     * 修改订单状态
     */
    public function setOrderStatus(int $id, int $status)
    {
        $info = (new PointsOrder())->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('订单不存在');
        }

        // 状态只能向前推进: 待发货->已发货->已完成
        if ($status <= $info['status']) {
            throw new CommonException('状态不允许回退');
        }

        $info->save(['status' => $status, 'update_time' => time()]);
        return true;
    }

    /**
     * 发货
     */
    public function shipOrder(int $id, string $express_company, string $express_no)
    {
        $info = (new PointsOrder())->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('订单不存在');
        }

        if ($info['status'] != 0) {
            throw new CommonException('订单状态不允许发货');
        }

        $info->save([
            'status' => 1,
            'express_company' => $express_company,
            'express_no' => $express_no,
            'update_time' => time()
        ]);
        return true;
    }

    /**
     * 物流查询
     */
    public function getLogistics(int $id)
    {
        $info = (new PointsOrder())->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('订单不存在');
        }

        if (empty($info['express_no'])) {
            return ['success' => false, 'message' => '暂无物流信息'];
        }

        // 获取配置
        $config = (new \addon\sd_xiaoyuan\app\service\core\ConfigService())->getConfig();
        $express_type = $config['express_type'] ?? 1;

        $result = [
            'success' => true,
            'express_company' => $info['express_company'],
            'express_no' => $info['express_no'],
            'data' => ['traces' => [], 'status_text' => '运输中']
        ];

        // 快递公司代码映射
        $companyCodeMap = [
            '顺丰速运' => 'shunfeng',
            '中通快递' => 'zhongtong',
            '圆通速递' => 'yuantong',
            '韵达快递' => 'yunda',
            '申通快递' => 'shentong',
            '邮政包裹' => 'youzhengguonei',
            'EMS' => 'ems',
            '京东快递' => 'jd',
            '极兔快递' => 'jtexpress',
            '德邦快递' => 'debangkuaidi',
            '百世快递' => 'huitongkuaidi',
            '天天快递' => 'tiantian',
        ];
        $companyCode = $companyCodeMap[$info['express_company']] ?? 'auto';

        if ($express_type == 1) {
            // 快递100
            $result = $this->queryKuaidi100($info['express_no'], $companyCode, $config, $info);
        } elseif ($express_type == 2) {
            // 阿里云物流
            $result = $this->queryAliyunExpress($info['express_no'], $companyCode, $config, $info);
        }

        return $result;
    }

    /**
     * 快递100查询
     */
    private function queryKuaidi100(string $express_no, string $company_code, array $config, $order)
    {
        $key = $config['express_app_key'] ?? '';
        $customer = $config['express_customer'] ?? '';

        if (empty($key) || empty($customer)) {
            return [
                'success' => false,
                'message' => '请先配置快递100接口',
                'express_company' => $order['express_company'],
                'express_no' => $express_no
            ];
        }

        $param = json_encode(['com' => $company_code, 'num' => $express_no]);
        $sign = strtoupper(md5($param . $key . $customer));

        $postData = [
            'customer' => $customer,
            'sign' => $sign,
            'param' => $param
        ];

        $url = 'https://poll.kuaidi100.com/poll/query.do';
        $response = $this->httpPost($url, http_build_query($postData));
        $data = json_decode($response, true);

        if (!empty($data['data'])) {
            $traces = [];
            foreach ($data['data'] as $item) {
                $traces[] = [
                    'time' => $item['time'] ?? '',
                    'ftime' => $item['ftime'] ?? $item['time'] ?? '',
                    'context' => $item['context'] ?? ''
                ];
            }
            return [
                'success' => true,
                'express_company' => $order['express_company'],
                'express_no' => $express_no,
                'data' => [
                    'traces' => $traces,
                    'status_text' => $this->getStatusText($data['state'] ?? 0)
                ]
            ];
        }

        return [
            'success' => false,
            'message' => $data['message'] ?? '查询失败',
            'express_company' => $order['express_company'],
            'express_no' => $express_no
        ];
    }

    /**
     * 阿里云物流查询
     */
    private function queryAliyunExpress(string $express_no, string $company_code, array $config, $order)
    {
        $appcode = $config['aliyun_express_appcode'] ?? '';

        if (empty($appcode)) {
            return [
                'success' => false,
                'message' => '请先配置阿里云物流AppCode',
                'express_company' => $order['express_company'],
                'express_no' => $express_no
            ];
        }

        $url = "https://wuliu.market.alicloudapi.com/kdi?no={$express_no}";
        if ($company_code != 'auto') {
            $url .= "&type={$company_code}";
        }

        $headers = ["Authorization: APPCODE {$appcode}"];
        $response = $this->httpGet($url, $headers);
        $data = json_decode($response, true);

        if (!empty($data['result']['list'])) {
            $traces = [];
            foreach ($data['result']['list'] as $item) {
                $traces[] = [
                    'time' => $item['time'] ?? '',
                    'ftime' => $item['time'] ?? '',
                    'context' => $item['status'] ?? ''
                ];
            }
            return [
                'success' => true,
                'express_company' => $order['express_company'],
                'express_no' => $express_no,
                'data' => [
                    'traces' => $traces,
                    'status_text' => $data['result']['deliverystatus'] == 3 ? '已签收' : '运输中'
                ]
            ];
        }

        return [
            'success' => false,
            'message' => $data['reason'] ?? '查询失败',
            'express_company' => $order['express_company'],
            'express_no' => $express_no
        ];
    }

    private function getStatusText($state)
    {
        $map = [
            0 => '运输中',
            1 => '揽收',
            2 => '疑难',
            3 => '已签收',
            4 => '退签',
            5 => '派件中',
            6 => '退回',
            7 => '转投'
        ];
        return $map[$state] ?? '运输中';
    }

    private function httpPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function httpGet($url, $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
