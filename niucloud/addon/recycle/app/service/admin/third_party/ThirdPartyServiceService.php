<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\third_party;

use addon\recycle\app\model\third_party\ThirdPartyService as ThirdPartyServiceModel;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 第三方服务配置服务类
 * Class ThirdPartyServiceService
 * @package addon\recycle\app\service\admin\third_party
 */
class ThirdPartyServiceService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ThirdPartyServiceModel();
    }

    /**
     * 获取第三方服务分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,service_type,provider_name,priority,config,status,balance,min_balance_alert,create_at,update_at';
        $order = 'priority asc, create_at desc';

        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['service_type', 'provider_name', 'status'], $where)
            ->field($field)
            ->order($order);

        return $this->pageQuery($search_model);
    }

    /**
     * 获取第三方服务信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,service_type,provider_name,priority,config,status,balance,min_balance_alert,create_at,update_at';

        $info = $this->model->field($field)->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();

        if (empty($info)) {
            throw new CommonException('THIRD_PARTY_SERVICE_NOT_EXIST');
        }

        return $info;
    }

    /**
     * 添加第三方服务
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 第三方服务编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update($data);
        return true;
    }

    /**
     * 删除第三方服务
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($model)) {
            throw new CommonException('THIRD_PARTY_SERVICE_NOT_EXIST');
        }

        $res = $model->delete();
        return $res;
    }

    /**
     * 修改第三方服务状态
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function modifyStatus(int $id, int $status)
    {
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update(['status' => $status]);
        return true;
    }
}
