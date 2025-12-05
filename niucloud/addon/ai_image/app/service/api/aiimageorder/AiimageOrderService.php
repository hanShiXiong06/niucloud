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

namespace addon\ai_image\app\service\api\aiimageorder;

use addon\ai_image\app\dict\order\OrderStatusDict;
use addon\ai_image\app\model\aiimageorder\AiimageOrder;
use addon\ai_image\app\service\core\ConfigService;
use addon\ai_image\app\service\core\OrderService;
use addon\ai_image\app\service\core\SxfApi;
use app\model\member\Member;
use addon\ai_image\app\model\aiimagepackage\AiimagePackage;

use core\base\BaseApiService;
use core\exception\CommonException;


/**
 * 订单列服务层
 * Class AiimageOrderService
 * @package addon\ai_image\app\service\admin\aiimageorder
 */
class AiimageOrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new AiimageOrder();
    }

    //随行付订单查询
    public function querySxfOrder($id)
    {
        $info = $this->model->where([['id', "=", $id]])->findOrEmpty();
        if ($info->isEmpty()) throw new CommonException('订单不存在');
        if ($info->status == OrderStatusDict::FINISH) throw new CommonException('订单已完成');
        $sxfApi = new SxfApi();
        $config = (new ConfigService())->getSxfConfig();
        $sxfApi->setConfig($config);
        $action = '/query/tradeQuery';
        $site_config = (new ConfigService())->getConfig();
        if (!isset($site_config['mno']) || $site_config['mno'] == '') throw new CommonException('商户编码未配置');
        $result = $sxfApi->httpRun($action, [
            'mno' => $site_config['mno'],
            'ordNo' => $info['order_id'],
        ]);
        if ($result['code'] != '0000') throw new CommonException($result['msg']);
        if ($result['respData']['tranSts'] == 'SUCCESS') {
            //触发支付成功
            $info['trade_id'] = $info['id'];
            $info['out_trade_no'] = $result['respData']['uuid'];
            (new OrderService())->paySuccess($info);
        }
        if ($result['respData']['tranSts'] == 'CLOSED'){
            $this->model->where(['id'=>$id])->update(['status'=> OrderStatusDict::CLOSE]);
        }
        return $result['respData'];
    }

    //获取随行付扫码二维码

    public function getSxfScan($id)
    {
        $info = $this->model->where([['id', "=", $id]])->findOrEmpty();
        if ($info->isEmpty()) throw new CommonException('订单不存在');
        if ($info->status != OrderStatusDict::WAIT_PAY) throw new CommonException('订单状态异常');
        $sxfApi = new SxfApi();
        $config = (new ConfigService())->getSxfConfig();
        $sxfApi->setConfig($config);
        $action = '/order/activePlusScan';
        $site_config = (new ConfigService())->getConfig();

        if (!isset($site_config['mno']) || $site_config['mno'] == '') {
            throw new CommonException('商户编码未配置');
        }

        $notifyUrl = $this->getDomainUrl() . '/api/ai_iamge/notify/sxf';
        $postData = [
            'mno' => $site_config['mno'],
            'ordNo' => $info['order_id'],
            'subject' => $info['name'],
            'amt' => $info['order_money'],
            'trmIp' => $_SERVER['REMOTE_ADDR'],
            'notifyUrl' => '',
        ];

        $result = $sxfApi->httpRun($action, $postData);
        if ($result['code'] != '0000') throw new CommonException($result['msg']);

        $payUrl = $result['respData']['payUrl'];

        // 生成二维码并转换为base64
        $tempDir = runtime_path() . 'qrcode';
        if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
        $tempFile = $tempDir . '/sxf_' . $info['order_id'] . '_' . time() . '.png';

        // 使用错误抑制符避免库内部的类型转换警告
        @\core\util\QRcode::png($payUrl, $tempFile, 0, 3, 4);

        if (file_exists($tempFile)) {
            $qrCodeImage = file_get_contents($tempFile);
            $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($qrCodeImage);
            @unlink($tempFile); // 删除临时文件
        } else {
            throw new CommonException('二维码生成失败');
        }
        $result['respData']['qr_code'] = $qrCodeBase64;
        return $result['respData'];
    }

    public function getDomainUrl()
    {
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if ($isSecure) {
            $url = 'https://' . $domain;
        } else {
            $url = 'http://' . $domain;
        }
        return $url;
    }

    /**
     * 获取订单列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,package_id,order_id,name,image,order_money,point,num,type,day,status,out_trade_no,pay_time,pid,create_time,close_time';
        $order = 'create_time desc';

        $search_model = $this->model->where([['site_id', "=", $this->site_id]])->withSearch(["member_id", "package_id", "order_id", "name", "image", "status"], $where)->with(['member', 'aiimagePackage'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取订单列信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,member_id,package_id,order_id,name,image,order_money,point,num,type,day,status,out_trade_no,pay_time,pid,create_time,close_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with(['member', 'aiimagePackage'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加订单列
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $package_id = $data['package_id'];
        if (!$package_id > 0) throw new CommonException('套餐参数异常');
        $package = (new AiimagePackage())->where(['id' => $package_id, 'site_id' => $this->site_id, 'status' => 1])->findOrEmpty();
        if ($package->isEmpty()) throw new CommonException('套餐不存在或已下架');
        $data['name'] = $package->name;
        $data['order_id'] = create_no();
        $data['image'] = $package->image;
        $data['order_money'] = $package->price;
        $data['point'] = $package->point;
        $data['num'] = $package->num;
        $data['type'] = $package->type;
        $data['day'] = $package->day;
        $data['status'] = OrderStatusDict::WAIT_PAY;
        $res = $this->model->create($data);
        return [
            'trade_type' => OrderStatusDict::getOrderType()['type'],
            'trade_id' => $res->id,
            'type' => $package->type
        ];

    }


    /**
     * 订单列编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除订单列
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }

    public function getMemberAll()
    {
        $memberModel = new Member();
        return $memberModel->where([["site_id", "=", $this->site_id]])->select()->toArray();
    }

    public function getAiimagePackageAll()
    {
        $aiimagePackageModel = new AiimagePackage();
        return $aiimagePackageModel->where([["site_id", "=", $this->site_id]])->select()->toArray();
    }

}
