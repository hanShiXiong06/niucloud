<?php
namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\ExpressStation;
use core\base\BaseAdminService;

class ExpressStationService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ExpressStation();
    }

    public function getPage(array $where = [])
    {
        $field = 'id,site_id,school_id,name,logo,address,express_company,contact_name,contact_mobile,business_hours,sort,status,create_time';
        $order = 'sort desc, id desc';

        $search_model = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['school_id', 'name', 'status'], $where)
            ->field($field)
            ->order($order);

        return $this->pageQuery($search_model);
    }

    public function getInfo(int $id)
    {
        $field = 'id,site_id,school_id,name,logo,address,express_company,lng,lat,contact_name,contact_mobile,business_hours,sort,status,create_time';
        $info = $this->model->field($field)->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        return $info ? $info->toArray() : [];
    }

    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $res = $this->model->create($data);
        return $res->id;
    }

    public function edit(int $id, array $data)
    {
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    public function del(int $id)
    {
        return $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->delete();
    }

    public function setStatus(int $id, int $status)
    {
        return $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update(['status' => $status]);
    }

    public function getAll(array $where = [])
    {
        $where[] = ['site_id', '=', $this->site_id];
        $where[] = ['status', '=', 1];
        return $this->model->where($where)->order('sort desc, id desc')->select()->toArray();
    }
}
