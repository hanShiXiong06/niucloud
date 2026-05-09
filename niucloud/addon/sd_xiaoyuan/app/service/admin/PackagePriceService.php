<?php
namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\PackagePrice;
use core\base\BaseAdminService;

class PackagePriceService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new PackagePrice();
    }

    public function getPage(array $where = [])
    {
        $field = 'id,site_id,size,name,description,price,sort,status,create_time';
        $order = 'sort asc, id asc';

        $search_model = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['name', 'status'], $where)
            ->field($field)
            ->order($order);

        return $this->pageQuery($search_model);
    }

    public function getInfo(int $id)
    {
        $field = 'id,site_id,size,name,description,price,sort,status,create_time';
        $info = $this->model->field($field)->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        return $info ? $info->toArray() : [];
    }

    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['size'] = 'P'.rand(1,9999);
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
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->delete();
        return true;
    }
}