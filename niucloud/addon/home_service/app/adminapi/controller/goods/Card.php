<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\adminapi\controller\goods;

use core\base\BaseAdminController;
use addon\home_service\app\service\admin\goods\CardService;


/**
 * 次卡次卡项目控制器
 * @description 次卡项目
 * Class Goods
 * @package addon\home_service\app\adminapi\controller\goods
 */
class Card extends BaseAdminController
{


    /**
     * 获取有效期类型
     * @description 获取次卡项目列表
     * @return \think\Response
     */
    public function getValidType()
    {
        return success((new CardService())->getValidType());
    }

    /**
     * 获取次卡项目列表
     * @description 获取次卡项目列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["card_name", ""],
            ["start_sale_num", ""],
            ["end_sale_num", ""],
            ["status", ""],
            ['order', ''],
            ['sort', ''],
            ['create_time', []],
            ['valid_type', '']
        ]);
        return success((new CardService())->getPage($data));
    }

    /**
     * 次卡项目详情
     * @description 次卡项目详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new CardService())->getInfo($id));
    }

    /**
     * 获取商品添加/编辑数据
     * @description 获取商品添加/编辑初始化数据
     * @return \think\Response
     */
    public function init()
    {
        $data = $this->request->params([
            ["card_id", 0],
        ]);
        return success((new CardService())->getInit($data));
    }

    /**
     * 添加次卡项目
     * @description 添加次卡项目
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["card_name", ""],
            ["card_cover", ""],
            ["card_image", ""],
            ["card_content", ''],
            ["status", 0],
            ["sort", 0],
            ["virtually_sale", 0],
            ["poster_id", 0],
            ["valid_type", ''],
            ["goods_sku_data", ''],
            ['member_discount', ''],
            ['guarantee_id','']
        ]);
        $data['card_cover']=explode(',', $data['card_image'])[0]??"";
        $this->validate($data, 'addon\home_service\app\validate\Card.add');
        $id = (new CardService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 次卡项目编辑
     * @description 次卡项目编辑
     * @param $id 次卡项目id
     * @return \think\Response
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ["card_name", ""],
            ["card_image", ""],
            ["card_content", ''],
            ["status", 0],
            ["sort", 0],
            ["virtually_sale", 0],
            ["poster_id", 0],
            ["valid_type", ''],
            ["goods_sku_data", ''],
            ['member_discount', ''],
            ['guarantee_id','']
        ]);
        $data['card_cover']=explode(',', $data['card_image'])[0]??"";
        $this->validate($data, 'addon\home_service\app\validate\Card.edit');
        (new CardService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }


    /**
     * 更新排序
     * @description 更新排序
     * @param int $id
     * @return \think\Response
     */
    public function editSort(int $id)
    {
        $data = $this->request->params([
            ["sort", 0],
        ]);
        (new CardService())->editSort($id, $data);
        return success('SUCCESS');
    }


    /**
     * 编辑商品规格列表会员价格
     * @description 编辑商品规格列表会员价格
     * @return \think\Response
     */
    public function editCardListMemberPrice()
    {
        $data = $this->request->params([
            ['card_id', 0],
            ['member_discount', ''], // 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
            ['sku_list', '']
        ]);
        (new CardService())->editCardListMemberPrice($data);
        return success('EDIT_SUCCESS');
    }


    /**
     * 次卡项目删除
     * @description 次卡项目删除
     * @param $id  次卡项目id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new CardService())->del($id);
        return success('DELETE_SUCCESS');
    }


    /**
     * 查询商品SKU规格列表
     * @description 查询商品SKU规格列表
     * @return \think\Response
     */
    public function sku()
    {
        $data = $this->request->params([
            ['card_id', '']
        ]);
        return success((new CardService())->getSkuList($data));
    }

    /**
     * 服务上下架
     * @description 修改服务上下架状态
     */
    public function editStatus($id)
    {
        $data = $this->request->params([
            ["status", 0],
        ]);
        (new CardService())->editStatus($id, $data);
        return success('SUCCESS');
    }


    /**
     * 次卡选择分页列表
     * @description 次卡选择分页列表
     * @return \think\Response
     */
    public function select()
    {
        $data = $this->request->params([
            ['card_name', ''], // 搜索关键词
            ['card_ids', ''], // 已选次卡id集合
            ['verify_card_ids', ''], // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
            ['create_time', []],
        ]);
        return success((new CardService())->getSelectPage($data));
    }

    /**
     * 商品选择分页列表(带sku)
     * @return \think\Response
     */
    public function selectCardSku()
    {
        $data = $this->request->params([
            ['card_name', ''], // 搜索关键词
            ['card_ids', ''], // 已选商品id集合
            ['verify_card_ids', ''], // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        ]);

        return success((new CardService())->getSelectSku($data));
    }


}
