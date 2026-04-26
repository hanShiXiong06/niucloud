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

namespace addon\wj_books\app\adminapi\controller\wj_books_scan_records;

use core\base\BaseAdminController;
use addon\wj_books\app\service\admin\wj_books_scan_records\WjBooksScanRecordsService;


/**
 * 图书扫描记录控制器
 * Class WjBooksScanRecords
 * @package addon\wj_books\app\adminapi\controller\wj_books_scan_records
 */
class WjBooksScanRecords extends BaseAdminController
{
   /**
    * 获取图书扫描记录列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["member_id",""],
             ["isbn",""]
        ]);
        return success((new WjBooksScanRecordsService())->getPage($data));
    }

    /**
     * 图书扫描记录详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new WjBooksScanRecordsService())->getInfo($id));
    }

    /**
     * 添加图书扫描记录
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["isbn",""],
             ["scan_time","2025-06-12 15:04:44"],
             ["scan_type",0],
             ["status",0],

        ]);
        $this->validate($data, 'addon\wj_books\app\validate\wj_books_scan_records\WjBooksScanRecords.add');
        $id = (new WjBooksScanRecordsService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 图书扫描记录编辑
     * @param $id  图书扫描记录id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["isbn",""],
             ["scan_time","2025-06-12 15:04:44"],
             ["scan_type",0],
             ["status",0],

        ]);
        $this->validate($data, 'addon\wj_books\app\validate\wj_books_scan_records\WjBooksScanRecords.edit');
        (new WjBooksScanRecordsService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 图书扫描记录删除
     * @param $id  图书扫描记录id
     * @return \think\Response
     */
    public function del(int $id){
        (new WjBooksScanRecordsService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
         return success(( new WjBooksScanRecordsService())->getMemberAll());
    }

}
