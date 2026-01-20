<?php
namespace addon\tk_jhkd\app\api\controller;
use core\base\BaseController;
use addon\tk_jhkd\app\service\core\FenxiaoService;
use think\Response;
class Fenxiao extends BaseController
{
    /**
     * @Notes:分销关系检查绑定
     * @Interface checkFenxiao
     * @return Response
     * @author: TK
     * @Time: 2024/5/28   下午1:00
     */
    public function checkFenxiao()
    {
        $data = $this->request->params([
            ['pid', ''],
        ]);
        return success((new FenxiaoService())->checkFenxiao($data));
    }
    /**
     * @Notes:分销统计
     * @Interface getFenxiaoInfo
     * @return Response
     * @author: TK
     * @Time: 2024/9/21   下午12:45
     */
    public function getFenxiaoInfo()
    {
        return success((new FenxiaoService())->getFenxiaoInfo());
    }
    /**
     * @Notes:新版分销数据接口
     * getIndexData
     * 2024/12/19  00:17
     * author:TK
     */
    public function getOrderData()
    {
        $data = $this->request->params([
            ['pid', ''],
        ]);
        return success((new FenxiaoService())->getOrderData($data));
    }

    /**
     * @Notes:获取分销会员信息
     * getFenxiaoMember
     * 2024/12/20  14:29
     * author:TK
     */
    public function getFenxiaoMember()
    {
        $data = $this->request->params([
            ['type', 'first'],
        ]);
        if ($data['type'] == 'first') {
            $res = (new FenxiaoService())->getFirstFenxiaoMember($data);
        } else {
            $res = (new FenxiaoService())->getTwoFenxiaoMember($data);
        }
        return success($res);
    }
    public function getFenxiaoOrder()
    {
        $data = $this->request->params([
            ['type', 'two'],
        ]);
        if ($data['type'] == 'first') {
            $res = (new FenxiaoService())->getFirstFenxiaoOrder($data);
        } else {
            $res = (new FenxiaoService())->getTwoFenxiaoOrder($data);
        }
        return success($res);
    }
    public function getSelfFenxiaoOrder()
    {
        $data = $this->request->params([
            ['type', 'self'],
        ]);
        if ($data['type'] == 'self') {
            $res = (new FenxiaoService())->getSelfFenxiaoOrder();
        } else {
            $res = (new FenxiaoService())->getSelfDirectFenxiaoOrder();
        }
        return success($res);
    }
}