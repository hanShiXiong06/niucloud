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

namespace addon\wj_books\app\service\api\wj_books_scan_records;

use addon\wj_books\app\model\wj_books_scan_records\WjBooksScanRecords;
use core\base\BaseApiService;

/**
 * 图书扫描记录API服务层
 * Class WjBooksScanRecordsService
 * @package addon\wj_books\app\service\api\wj_books_scan_records
 */
class WjBooksScanRecordsService extends BaseApiService
{
    protected $model;
    
    public function __construct()
    {
        parent::__construct();
        $this->model = new WjBooksScanRecords();
    }
    
    /**
     * 添加扫描记录
     * @param string $isbn
     * @param int $scan_type 扫描方式(1:扫码,2:手动输入)
     * @param int $status 状态(0:仅扫描,1:已加入回收车,2:已提交回收)
     * @return int 记录ID
     */
    public function addScanRecord(string $isbn, int $scan_type = 1, int $status = 0)
    {
        $data = [
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'isbn' => $isbn,
            'scan_time' => date('Y-m-d H:i:s'),
            'scan_type' => $scan_type,
            'status' => $status,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s')
        ];
        
        $res = $this->model->create($data);
        return $res->id;
    }
    
    /**
     * 根据ISBN更新扫描记录状态
     * @param string $isbn
     * @param int $status 状态(0:仅扫描,1:已加入回收车,2:已提交回收)
     * @return bool
     */
    public function updateStatusByIsbn(string $isbn, int $status)
    {
        // 获取用户最近的一条扫描记录
        $record = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['isbn', '=', $isbn]
        ])->order('scan_time desc')->find();
        
        if (!$record) {
            return false;
        }
        
        // 更新状态
        $record->status = $status;
        $record->update_time = date('Y-m-d H:i:s');
        $record->save();
        
        return true;
    }
    
    /**
     * 根据用户ID和ISBN批量更新扫描记录状态
     * @param array $isbns ISBN数组
     * @param int $status 状态(0:仅扫描,1:已加入回收车,2:已提交回收)
     * @return bool
     */
    public function batchUpdateStatusByIsbns(array $isbns, int $status)
    {
        if (empty($isbns)) {
            return false;
        }
        
        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['isbn', 'in', $isbns]
        ])->update([
            'status' => $status,
            'update_time' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    }
    
    /**
     * 根据记录ID更新扫描记录状态
     * @param int $id 记录ID
     * @param int $status 状态(0:仅扫描,1:已加入回收车,2:已提交回收)
     * @return bool
     */
    public function updateStatusById(int $id, int $status)
    {
        if ($id <= 0) {
            return false;
        }
        
        $record = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->find();
        
        if (!$record) {
            return false;
        }
        
        // 更新状态
        $record->status = $status;
        $record->update_time = date('Y-m-d H:i:s');
        $record->save();
        
        return true;
    }
    
    /**
     * 获取用户扫描记录
     * @param array $where
     * @return array
     */
    public function getScanRecords(array $where = [])
    {
        $where[] = ['site_id', '=', $this->site_id];
        $where[] = ['member_id', '=', $this->member_id];
        
        $field = 'id,site_id,member_id,isbn,scan_time,scan_type,status,create_time,update_time';
        $order = 'scan_time desc';
        
        $list = $this->model->field($field)->where($where)->order($order)->select()->toArray();
        return $list;
    }
} 