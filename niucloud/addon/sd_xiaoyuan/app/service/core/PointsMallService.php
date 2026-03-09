<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\PointsGoods;
use addon\sd_xiaoyuan\app\model\PointsOrder;
use app\service\core\member\CoreMemberAccountService;
use app\dict\member\MemberAccountTypeDict;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 积分商城服务(API端)
 */
class PointsMallService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取商品列表
     */
    public function getGoodsList(int $page = 1, int $limit = 10)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ];

        $model = new PointsGoods();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->field('id,name,image,points_price,stock,exchange_count')
            ->order('sort desc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return ['list' => $list, 'total' => $count];
    }

    /**
     * 获取商品详情
     */
    public function getGoodsDetail(int $id)
    {
        $info = (new PointsGoods())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ])->find();

        if (empty($info)) {
            throw new CommonException('商品不存在');
        }

        return $info->toArray();
    }

    /**
     * 兑换商品
     */
    public function exchange(int $goods_id, string $receiver_name, string $receiver_phone, string $receiver_address, string $remark = '')
    {
        $goods = (new PointsGoods())->where([
            ['id', '=', $goods_id],
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ])->find();

        if (empty($goods)) {
            throw new CommonException('商品不存在');
        }

        if ($goods['stock'] <= 0) {
            throw new CommonException('商品库存不足');
        }

        $points_price = (int)$goods['points_price'];

        // 获取用户积分（从member表获取）
        $member = Db::name('member')->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->field('point')->find();

        $currentPoints = (int)($member['point'] ?? 0);
        if ($currentPoints < $points_price) {
            throw new CommonException('积分不足，当前积分' . $currentPoints . '，需要' . $points_price);
        }

        Db::startTrans();
        try {
            // 使用框架的CoreMemberAccountService扣减积分
            (new CoreMemberAccountService())->addLog(
                $this->site_id,
                $this->member_id,
                MemberAccountTypeDict::POINT,
                -$points_price,
                'points_exchange',
                '积分商城兑换商品：' . $goods['name'],
                0
            );

            // 扣减库存
            $goods->dec('stock', 1)->inc('exchange_count', 1)->update();

            // 创建订单
            $order_no = 'PO' . date('YmdHis') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $order = (new PointsOrder())->create([
                'site_id' => $this->site_id,
                'member_id' => $this->member_id,
                'order_no' => $order_no,
                'goods_id' => $goods_id,
                'goods_name' => $goods['name'],
                'goods_image' => $goods['image'],
                'points_price' => $points_price,
                'quantity' => 1,
                'receiver_name' => $receiver_name,
                'receiver_phone' => $receiver_phone,
                'receiver_address' => $receiver_address,
                'status' => PointsOrder::STATUS_PENDING,
                'remark' => $remark,
                'create_time' => time(),
                'update_time' => time(),
            ]);

            Db::commit();
            return ['order_id' => $order->id, 'order_no' => $order_no];
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException('兑换失败：' . $e->getMessage());
        }
    }

    /**
     * 获取我的兑换订单列表
     */
    public function getMyOrders(int $status = -1, int $page = 1, int $limit = 10)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ];

        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $model = (new PointsOrder())->where($where);
        $count = $model->count();
        $list = $model->order('id desc')
            ->page($page, $limit)
            ->append(['status_text'])
            ->select()
            ->toArray();

        // 兼容字段名
        foreach ($list as &$item) {
            $item['total_points'] = $item['points_price'] ?? $item['points'] ?? 0;
        }
        unset($item);

        return ['list' => $list, 'total' => $count];
    }

    /**
     * 获取订单详情
     */
    public function getOrderDetail(int $id)
    {
        $order = (new PointsOrder())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->append(['status_text'])->find();

        if (empty($order)) {
            throw new CommonException('订单不存在');
        }

        $data = $order->toArray();
        // 兼容字段名
        $data['total_points'] = $data['points_price'] ?? $data['points'] ?? 0;
        return $data;
    }

    /**
     * 物流查询
     */
    public function getLogistics(int $id)
    {
        $order = (new PointsOrder())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->find();

        if (empty($order)) {
            throw new CommonException('订单不存在');
        }

        if (empty($order['express_no'])) {
            return ['success' => false, 'message' => '暂无物流信息'];
        }

        // 获取配置
        $config = (new ConfigService())->getConfig();
        $express_type = $config['express_type'] ?? 1;

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
        $companyCode = $companyCodeMap[$order['express_company']] ?? 'auto';

        if ($express_type == 1) {
            return $this->queryKuaidi100($order['express_no'], $companyCode, $config, $order);
        } elseif ($express_type == 2) {
            return $this->queryAliyunExpress($order['express_no'], $companyCode, $config, $order);
        }

        return [
            'success' => true,
            'express_company' => $order['express_company'],
            'express_no' => $order['express_no'],
            'data' => ['traces' => [], 'status_text' => '运输中']
        ];
    }

    private function queryKuaidi100(string $express_no, string $company_code, array $config, $order)
    {
        $key = $config['express_app_key'] ?? '';
        $customer = $config['express_customer'] ?? '';

        if (empty($key) || empty($customer)) {
            return [
                'success' => false,
                'message' => '物流查询服务未配置',
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

    private function queryAliyunExpress(string $express_no, string $company_code, array $config, $order)
    {
        $appcode = $config['aliyun_express_appcode'] ?? '';

        if (empty($appcode)) {
            return [
                'success' => false,
                'message' => '物流查询服务未配置',
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
