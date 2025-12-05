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

namespace addon\ai_image\app\service\admin\aiimagecard;

use addon\ai_image\app\model\aiimagecard\AiimageCard;
use app\model\member\Member;

use core\base\BaseAdminService;
use core\exception\CommonException;


/**
 * 卡密兑换服务层
 * Class AiimageCardService
 * @package addon\ai_image\app\service\admin\aiimagecard
 */
class AiimageCardService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new AiimageCard();
    }

    /**
     * 获取卡密兑换列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,card_num,point,is_use,use_time,is_export,pid,expire_time,create_time';
        $order = 'create_time desc';

        $search_model = $this->model->where([['site_id', "=", $this->site_id]])->withSearch(["card_num", "is_use", "is_export", "pid"], $where)->with(['member'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取卡密兑换信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,member_id,card_num,point,is_use,use_time,is_export,pid,expire_time,create_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with(['member'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加卡密兑换
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        if ($data['num'] > 500) throw new CommonException('单次卡密数量不能超过500');
        $data['site_id'] = $this->site_id;
        $data['is_use'] = 0;
        $data['is_export'] = 0;
        $num = $data['num'];
        for ($i = 0; $i < $num; $i++) {
            $data['card_num'] = $this->generateCardNum();
            $this->model->create($data);
        }
        return true;

    }

    private function generateCardNum()
    {
        do {
            $cardNum = '';
            // 生成两个随机字母
            $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $randomLetters = $letters[random_int(0, 25)] . $letters[random_int(0, 25)];
            for ($i = 0; $i < 8; $i++) {
                $cardNum .= random_int(0, 8); // 生成16位纯数字卡密
            }
            $exists = $this->model->where('card_num', $randomLetters . $cardNum)->find();
        } while ($exists);

        return $randomLetters . $cardNum;
    }

    /**
     * 卡密兑换编辑
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
     * 删除卡密兑换
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

    public function delselect($ids)
    {
        $res = $this->model->where('id', 'in', $ids)->delete();
        return $res;
    }

}
