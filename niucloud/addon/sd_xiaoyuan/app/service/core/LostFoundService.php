<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\LostFound;
use addon\sd_xiaoyuan\app\model\CampusAuth;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 失物招领服务
 */
class LostFoundService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new LostFound();
    }

    /**
     * 获取列表
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,school_id,campus,type,category,title,content,images,lost_time,lost_address,contact_name,contact_mobile,reward,is_urgent,view_count,status,create_time';
        $order = 'id desc';
        
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch(['type', 'status', 'school_id', 'campus', 'category', 'keyword', 'member_id'], $where)->field($field)->order($order);
        
        // 紧急筛选
        if (!empty($where['is_urgent'])) {
            $search_model = $search_model->where('is_urgent', '=', 1);
        }
        
        $result = $this->pageQuery($search_model);
        
        // 统一返回格式
        $list = $result['data'] ?? [];
        $count = $result['total'] ?? 0;
        
        // 关联会员信息和学校名称
        if (!empty($list)) {
            $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
            $school_ids = array_unique(array_filter(array_column($list, 'school_id')));
            
            $members = [];
            $schools = [];
            
            if (!empty($member_ids)) {
                $members = (new \app\model\member\Member())->where([['member_id', 'in', $member_ids]])->column('nickname,headimg', 'member_id');
            }
            
            if (!empty($school_ids)) {
                $schools = (new \addon\sd_xiaoyuan\app\model\School())->where([['id', 'in', $school_ids]])->column('name', 'id');
            }
            
            foreach ($list as &$item) {
                $mid = $item['member_id'] ?? 0;
                $sid = $item['school_id'] ?? 0;
                $item['member_nickname'] = $members[$mid]['nickname'] ?? '';
                $item['member_avatar'] = $members[$mid]['headimg'] ?? '';
                $item['school_name'] = $schools[$sid] ?? '';
            }
            unset($item);
        }
        
        return ['list' => $list, 'count' => $count];
    }

    /**
     * 获取详情
     */
    public function getInfo(int $id, bool $add_view = false)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('信息不存在');
        }
        
        // 增加浏览量
        if ($add_view) {
            $info->save(['view_count' => $info['view_count'] + 1]);
        }
        
        $data = $info->toArray();
        
        // 关联会员信息
        if (!empty($data['member_id'])) {
            $member = (new \app\model\member\Member())->where([['member_id', '=', $data['member_id']]])->field('nickname,headimg')->find();
            if ($member) {
                $data['member_nickname'] = $member['nickname'] ?? '';
                $data['member_avatar'] = $member['headimg'] ?? '';
            }
        }
        
        // 关联学校名称
        if (!empty($data['school_id'])) {
            $school = (new \addon\sd_xiaoyuan\app\model\School())->where([['id', '=', $data['school_id']]])->field('name')->find();
            if ($school) {
                $data['school_name'] = $school['name'] ?? '';
            }
        }
        
        return $data;
    }

    /**
     * 发布失物/招领
     */
    public function publish(int $member_id, array $data)
    {
        // 获取配置，判断是否自动审核通过
        $configService = new ConfigService();
        $config = $configService->getConfig();
        $autoApprove = $config['lost_found_auto_approve'] ?? 0;

        $schoolId = (int)($data['school_id'] ?? 0);
        $campus = (string)($data['campus'] ?? '');
        if ($schoolId <= 0 && $member_id > 0) {
            $authRow = (new CampusAuth())->where([
                ['member_id', '=', $member_id],
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
            ])->order('id', 'desc')->find();
            if ($authRow && !empty($authRow['school_id'])) {
                $schoolId = (int)$authRow['school_id'];
                if ($campus === '' && !empty($authRow['campus'])) {
                    $campus = (string)$authRow['campus'];
                }
            }
        }

        $item_data = [
            'site_id' => $this->site_id,
            'member_id' => $member_id,
            'school_id' => $schoolId,
            'campus' => $campus,
            'type' => $data['type'],
            'category' => $data['category'] ?? '',
            'title' => $data['title'],
            'content' => $data['content'] ?? '',
            'images' => $data['images'] ?? '',
            'lost_time' => $data['lost_time'] ?? time(),
            'lost_address' => $data['lost_address'] ?? '',
            'contact_name' => $data['contact_name'] ?? '',
            'contact_mobile' => $data['contact_mobile'] ?? '',
            'reward' => $data['type'] == 'LOST' ? ($data['reward'] ?? 0) : 0,
            'is_urgent' => intval($data['is_urgent'] ?? 0),
            'status' => $autoApprove ? LostFound::STATUS_ACTIVE : LostFound::STATUS_PENDING, // 根据配置决定状态：1-自动通过，0-待审核
            'create_time' => time(),
            'update_time' => time(),
        ];
        
        $res = $this->model->create($item_data);
        return $res->id;
    }

    /**
     * 编辑
     */
    public function edit(int $id, int $member_id, array $data)
    {
        $item = $this->model->where([['id', '=', $id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($item)) {
            throw new CommonException('信息不存在');
        }
        
        $update_data = [
            'category' => $data['category'] ?? $item['category'],
            'title' => $data['title'] ?? $item['title'],
            'content' => $data['content'] ?? $item['content'],
            'images' => $data['images'] ?? $item['images'],
            'lost_time' => $data['lost_time'] ?? $item['lost_time'],
            'lost_address' => $data['lost_address'] ?? $item['lost_address'],
            'contact_name' => $data['contact_name'] ?? $item['contact_name'],
            'contact_mobile' => $data['contact_mobile'] ?? $item['contact_mobile'],
            'reward' => $item['type'] == 'LOST' ? ($data['reward'] ?? $item['reward']) : 0,
            'update_time' => time(),
        ];
        
        $item->save($update_data);
        return true;
    }

    /**
     * 标记已找到/已归还
     */
    public function resolve(int $id, int $member_id)
    {
        $item = $this->model->where([['id', '=', $id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($item)) {
            throw new CommonException('信息不存在');
        }
        
        $item->save(['status' => LostFound::STATUS_RESOLVED, 'update_time' => time()]);
        return true;
    }

    /**
     * 关闭
     */
    public function close(int $id, int $member_id)
    {
        $item = $this->model->where([['id', '=', $id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($item)) {
            throw new CommonException('信息不存在');
        }
        
        $item->save(['status' => LostFound::STATUS_CLOSED, 'update_time' => time()]);
        return true;
    }

    /**
     * 删除
     */
    public function del(int $id, int $member_id)
    {
        $item = $this->model->where([['id', '=', $id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($item)) {
            throw new CommonException('信息不存在');
        }
        
        $item->delete();
        return true;
    }

    /**
     * 联系发布者
     */
    public function contact(int $id, int $member_id, string $message = '')
    {
        $item = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($item)) {
            throw new CommonException('信息不存在');
        }
        
        // 发送消息给发布者
        $type_name = $item['type'] == 'LOST' ? '失物' : '招领';
        (new MessageService())->send($item['member_id'], 'LOSTFOUND', '有人联系您的' . $type_name . '信息', '有用户对您发布的"' . $item['title'] . '"感兴趣', ['item_id' => $id, 'message' => $message], $member_id);
        
        return [
            'contact_name' => $item['contact_name'],
            'contact_mobile' => $item['contact_mobile'],
        ];
    }

    /**
     * 获取我的失物招领列表
     */
    public function getMyPage(array $where = [])
    {
        $where['member_id'] = $this->member_id;
        return $this->getPage($where);
    }

    /**
     * 获取类型列表
     */
    public function getTypeList()
    {
        return LostFound::getTypeList();
    }

    /**
     * 获取统计
     */
    public function getStats($school_id = 0)
    {
        $where = [
            ['site_id', '=', $this->site_id]
        ];
        
        if ($school_id > 0) {
            $where[] = ['school_id', '=', $school_id];
        }

        $total = $this->model->where($where)->count();
        $found = $this->model->where(array_merge($where, [['status', '=', LostFound::STATUS_RESOLVED]]))->count();

        return [
            'total' => $total,
            'found' => $found
        ];
    }

    /**
     * 获取分类列表
     */
    public function getCategoryList()
    {
        return [
            '证件卡片' => '证件卡片',
            '电子产品' => '电子产品',
            '钥匙' => '钥匙',
            '钱包' => '钱包',
            '书籍文具' => '书籍文具',
            '衣物饰品' => '衣物饰品',
            '生活用品' => '生活用品',
            '其他' => '其他',
        ];
    }
}
