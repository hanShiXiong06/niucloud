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

namespace addon\wj_books\app\adminapi\controller\wj_books_info;

use core\base\BaseAdminController;
use addon\wj_books\app\service\admin\wj_books_info\WjBooksInfoService;


/**
 * 图书信息控制器
 * Class WjBooksInfo
 * @package addon\wj_books\app\adminapi\controller\wj_books_info
 */
class WjBooksInfo extends BaseAdminController
{
   /**
    * 获取图书信息列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["isbn",""],
             ["isbn10",""],
             ["title",""],
             ["can_recycle",""]
        ]);
        return success((new WjBooksInfoService())->getPage($data));
    }

    /**
     * 图书信息详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new WjBooksInfoService())->getInfo($id));
    }

    /**
     * 添加图书信息
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["isbn",""],
             ["isbn10",""],
             ["title",""],
             ["author",""],
             ["publisher",""],
             ["pub_date",""],
             ["price",0.00],
             ["binding",""],
             ["page",0],
             ["edition",""],
             ["img",""],
             ["small_img",""],
             ["gist",""],
             ["recycle_price",0.00],
             ["can_recycle",0],
             ["recycle_count",0],
             ["api_json",""],
             ["api_query_time","2025-06-12 12:46:41"],

        ]);
        $this->validate($data, 'addon\wj_books\app\validate\wj_books_info\WjBooksInfo.add');
        $id = (new WjBooksInfoService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 图书信息编辑
     * @param $id  图书信息id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["isbn",""],
             ["isbn10",""],
             ["title",""],
             ["author",""],
             ["publisher",""],
             ["pub_date",""],
             ["price",0.00],
             ["binding",""],
             ["page",0],
             ["edition",""],
             ["img",""],
             ["small_img",""],
             ["gist",""],
             ["recycle_price",0.00],
             ["can_recycle",0],
             ["recycle_count",0],
             ["api_json",""],
             ["api_query_time","2025-06-12 12:46:41"],

        ]);
        $this->validate($data, 'addon\wj_books\app\validate\wj_books_info\WjBooksInfo.edit');
        (new WjBooksInfoService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 图书信息删除
     * @param $id  图书信息id
     * @return \think\Response
     */
    public function del(int $id){
        (new WjBooksInfoService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 通过ISBN查询图书信息
     * @return \think\Response
     */
    public function queryBookInfo(){
        $data = $this->request->params([
            ["isbn", ""],
        ]);
        
        // 验证ISBN参数
        if (empty($data['isbn'])) {
            return error('ISBN不能为空');
        }
        
        // 调用服务查询图书信息
        $result = (new WjBooksInfoService())->queryBookInfoByIsbn($data['isbn']);
        return success($result);
    }
}
