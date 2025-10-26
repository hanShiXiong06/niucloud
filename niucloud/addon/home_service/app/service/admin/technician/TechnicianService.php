<?php


namespace addon\home_service\app\service\admin\technician;

use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\service\core\store\CoreTechnicianService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use addon\home_service\app\model\Member as DeMember;
use addon\home_service\app\service\core\statistics\CoreOrderService;
use addon\home_service\app\service\core\statistics\CoreEvaluateService;
use think\facade\Db;

/**
 * 师傅服务层
 * Class TechnicianService
 * @package app\service\admin\technician
 */
class TechnicianService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Technician();
    }


    // 常量定义
    const REQUIRED_FIELDS = [
        'real_name', 'mobile', 'province_id', 'city_id',
        'district_id', 'full_address', 'lng', 'lat', 'category_id'
    ];


    /**
     * 添加师傅
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        // 验证数据格式 (新增时传递null作为排除ID)
        $this->validateTechnicianData($data, null);

        // 构建创建数据 (保持不变)
        $createArray = [
            'real_name' => $data['real_name'],
            'mobile' => $data['mobile'],
            'status' => $data['status'] ?? 1,
            'member_id' => $data['member_id'] ?? 0,
            'headimg' => $data['headimg'] ?? '',
            'certificate' => $data['certificate'] ?? '',
            'province_id' => $data['province_id'],
            'city_id' => $data['city_id'],
            'district_id' => $data['district_id'],
            'full_address' => $data['full_address'],
            'lng' => $data['lng'],
            'lat' => $data['lat'],
            'store_id' => $data['store_id'],
            'category_id' => $data['category_id'],
            'level_id' => $data['level_id'] ?? 0,
            'source' => $data['source'] ?? 'internal',
            'site_id' => $data['site_id'],
            'create_time' => time(),
            'distribute_type' => $data['distribute_type'] ?? 'default',
            'order_rate' => $data['order_rate'] ?? 0,
        ];
        if (empty($createArray['level_id'])) {
            $createArray['level_id'] = (new TechnicianLevelService())->getDefault()['level_id'] ?? 0;
        }
        Db::startTrans();
        try {
            $res = $this->model->create($createArray);
            $createArray['technician_id'] = $res->id;
            if (!empty($createArray['store_id'])) {
                (new CoreTechnicianService())->storeTechnicianRate($createArray);
            }
            event("AddHsTechnician", $createArray);
            Db::commit();
            return $res->id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new AdminException($e->getMessage());
        }
    }


    /**
     * 师傅编辑 (全量编辑)
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        Db::startTrans();
        try {
            // 编辑时也需要验证数据，传入当前ID作为排除项
            $this->validateTechnicianData($data, $id);
            $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
            if (!empty($data['store_id'])) {
                $data['technician_id'] = $id;
                $data['site_id'] = $this->site_id;
                (new CoreTechnicianService())->storeTechnicianRate($data);
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new AdminException($e->getMessage());
        }
    }


    /**
     * 验证师傅数据格式
     * @param array $data
     * @param int|null $excludeId 排除的ID，用于编辑时排除自身
     * @throws AdminException
     */
    private function validateTechnicianData(array $data, ?int $excludeId = null)
    {
        // 验证必填字段 (保持不变)
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new AdminException("TECHNICIAN_{$field}_REQUIRED");
            }
        }
        // 验证手机号格式 (保持不变)
        if (!preg_match('/^1[3-9]\d{9}$/', $data['mobile'])) {
            throw new AdminException('TECHNICIAN_MOBILE_FORMAT_ERROR');
        }
        // 验证经纬度格式 (保持不变)
        if (!is_numeric($data['lng']) || !is_numeric($data['lat'])) {
            throw new AdminException('TECHNICIAN_COORDINATE_FORMAT_ERROR');
        }
        // 验证ID类字段为整数 (保持不变)
        $integerFields = ['province_id', 'city_id', 'district_id'];
        foreach ($integerFields as $field) {
            if (!is_numeric($data[$field]) || (int)$data[$field] != $data[$field]) {
                throw new AdminException("TECHNICIAN_{$field}_FORMAT_ERROR");
            }
        }
        // 检查唯一性（member_id和mobile），支持排除当前ID
        $query = $this->model->where('site_id', $this->site_id)
            ->where(function ($query) use ($data) {
                $memberId = $data['member_id'] ?? 0;
                $query->where('member_id', $memberId)
//                    ->whereOr('mobile', $data['mobile']);
                    ->where('mobile', $data['mobile']);
            });
        // 如果是编辑操作，排除当前记录
        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }
        $exists = $query->find();
        if ($exists) {
            // 判断具体重复原因
            $errorCode = ($exists['member_id'] == ($data['member_id'] ?? 0))
                ? 'HOME_SERVICE_MEMBER_IS_TECHNICIAN'
                : 'MEMBER_IS_EXIST';
            throw new AdminException($errorCode);
        }
    }


    /**
     * 获取师傅列表  ok
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'commission_get,commission,withdraw_get,evaluate_avg_scores,order_num,id,real_name,mobile,status,member_id,headimg,certificate,province_id,city_id,
        district_id,full_address,lng,lat,store_id,category_id,level_id,source,site_id,create_time,distribute_type,order_rate';
        $order = 'technician.create_time desc';
        $with_where = [];
        if (isset($where['search_text']) && !empty($where['search_text'])) $with_where[] = ['technician.mobile|technician.real_name|technician.id', 'like', "%" . $where['search_text'] . "%"];
        $search_model = $this->model->where([['technician.site_id', '=', $this->site_id]])
            ->withSearch(["real_name", "create_time", "store_id", "level_id", "source", "category_id"], $where)
            ->field($field)
            ->withJoin([
                'member' => ['member_id', 'username', 'mobile', 'nickname', 'headimg'],
                'level' => ['level_id', 'level_name', 'order_rate']
            ])
            ->where($with_where)
            ->with([
                'store' => function ($query) {
                    $query->field('store_id,contact_name,store_name, mobile');
                },
            ])
            ->order($order)->append(['headimg_mid', 'distribute_name', 'status_name', 'source_name', 'category_name']);
        $list = $this->pageQuery($search_model);
        $this->getStatistics($list);
        return $list;

    }


    /**
     *  获取统计数据
     * @param array $where
     * @return array
     */
    public function getStatistics(&$list)
    {
        $technicianIds = array_column($list['data'], 'id');
        $timeRange = [
            'start' => strtotime(date('Y-m-01 00:00:00')),
            'end' => strtotime(date('Y-m-t 23:59:59'))
        ];
        $orderTimeStatsMap = (new CoreOrderService)->batchGetStats($this->site_id, 'technician_id', $technicianIds, [], $timeRange['start'], $timeRange['end']);
        $orderNoTimeStatsMap = (new CoreOrderService)->batchGetTechnicianStatusStats($this->site_id, $technicianIds);
        $evaluateTimeStatsMap = (new CoreEvaluateService)->batchGetTimeRangeStats($this->site_id, 'technician_id', $technicianIds, 0, $timeRange['start'], $timeRange['end']);
        foreach ($list['data'] as &$datum) {
            $techId = $datum['id'];
            $datum['service_process_total_count'] = $orderTimeStatsMap[$techId]['service_process_total_count'] ?? 0;
            $datum['wait_service_count'] = $orderNoTimeStatsMap[$techId]['wait_service_count'] ?? 0;
            $datum['wait_check_count'] = $orderNoTimeStatsMap[$techId]['wait_check_count'] ?? 0;
            $total_evaluate_count = $evaluateTimeStatsMap[$techId]['total_evaluate_count'] ?? 0;
            $score_gt3_count = $evaluateTimeStatsMap[$techId]['score_gt3_count'] ?? 0;
            if ($total_evaluate_count > 0) {
                $positive_rating = round(($score_gt3_count / $total_evaluate_count) * 100, 1) . '%';
            } else {
                $positive_rating = '0%';
            }
            $datum['positive_rating'] = $positive_rating;
        }
        return $list;
    }


    /**
     * 可以当师傅的会员
     * @param array $where
     * @return array
     */
    public function getEligibleMembers($where)
    {
        $search_model = (new DeMember)
            ->field('member_id,username,mobile,headimg,nickname') // 明确指定主表字段
            ->where('member.site_id', $this->site_id) // 会员状态有效
            ->withSearch(["keyword"], $where)
            ->withJoin('technician', 'LEFT') // 左关联师傅表
            ->where('technician.member_id', null) // 无对应师傅记录（未成为师傅）
            ->order('member.member_id', 'desc')
            ->hidden(['technician']);
        $list = $this->pageQuery($search_model);
        return $list;
    }


    /**
     * 获取师傅列表（用于弹框选择）
     * @param array $where
     * @return array
     */
    public function getSelectPage(array $where = [])
    {
        $field = 'technician.id,technician.site_id,technician.member_id,technician.name,technician.age,technician.mobile,technician.working_age,technician.status,technician.label,technician.position_id,technician.position_name,technician.order_num,technician.service_time,technician.create_time,technician.bad_evaluate,technician.headimg,technician.images,technician.desc';
        $order = 'technician.create_time desc';

        $verify_ids = [];
        // 检测id集合是否存在，移除不存在的id，纠正数据准确性
        if (!empty($where['verify_ids'])) {
            $verify_ids = $this->model->where([
                ['id', 'in', $where['verify_ids']]
            ])->field('id')->select()->toArray();
            if (!empty($verify_ids)) {
                $verify_ids = array_column($verify_ids, 'id');
            }
        }
        $search_model = $this->model->where([
            ['technician.site_id', '=', $this->site_id],
            ['technician.status', '=', 1]
        ])->withSearch(["mobile", "name", "create_time"], $where)
            ->withJoin(['member' => ['member_id', 'member_no', 'username', 'mobile', 'nickname', 'headimg']])->field($field)->order($order)
            ->append(['status_name', 'headimg_mid', 'category_name']);
        $list = $this->pageQuery($search_model);
        $list['verify_ids'] = $verify_ids;
        return $list;
    }

    /**
     * 获取师傅信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'evaluate_avg_scores,commission,achievement,id,real_name,mobile,status,member_id,headimg,certificate,province_id,city_id,
        district_id,full_address,lng,lat,store_id,category_id,level_id,source,site_id,create_time,distribute_type,order_rate';
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
            ->with(
                [
                    'member' => function ($query) {
                        $query->field('member_id,mobile,nickname, headimg');
                    },
                    'store' => function ($query) {
                        $query->field('store_id,contact_name, mobile, store_name');
                    },
                    'level' => function ($query) {
                        $query->field('level_id,level_name, order_rate');
                    },
                ]
            )->field($field)->append(['distribute_name', 'headimg_mid', 'status_name', 'category_name', 'source_name'])
            ->findOrEmpty()->toArray();
        return $info;

    }


    /**
     * 通过会员查询openid
     * @param int $site_id
     * @param int $member_id
     * @param string $field
     * @return array
     */
    public function getInfoMemberId(int $member_id, string $field = 'member_id')
    {
        $info = $this->model->field($field)->where([['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        return $info;
    }


    /**
     * 更新状态
     * @param $id
     * @param $data
     * @return bool
     */
    public function editStatus($id, $data)
    {
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }


    /**
     * 师傅条数
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function getCount($where)
    {
        $count = $this->model->withSearch(["level_id"], $where)->where([['site_id', '=', $this->site_id]])->count();
        return $count;
    }


}
