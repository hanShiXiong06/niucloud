<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Secondhand;
use addon\sd_xiaoyuan\app\model\SecondhandCategory;
use addon\sd_xiaoyuan\app\model\CampusAuth;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 二手交易服务
 */
class SecondhandService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Secondhand();
    }

    /**
     * 获取商品列表
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,school_id,campus,category_id,title,images,original_price,price,condition_level,trade_method,trade_address,contact_name,contact_mobile,contact_wechat,content,view_count,want_count,status,is_top,create_time';
        
        // 排序逻辑
        $sort = $where['sort'] ?? 'new';
        switch ($sort) {
            case 'price_asc':
                $order = 'price asc,id desc';
                break;
            case 'price_desc':
                $order = 'price desc,id desc';
                break;
            case 'hot':
                $order = 'view_count desc,id desc';
                break;
            default:
                $order = 'is_top desc,id desc';
                break;
        }
        
        $search_model = $this->model->where([['site_id', '=', $this->site_id]]);
        $goodsType = trim((string)($where['goods_type'] ?? ''));
        if ($goodsType === 'urgent') {
            $search_model->where('is_top', 1);
        } elseif ($goodsType === 'free') {
            $search_model->where('price', '<=', 0);
        } elseif ($goodsType === 'normal') {
            $search_model->where('is_top', 0)->where('price', '>', 0);
        }
        $search_model = $search_model->withSearch(['category_id', 'status', 'school_id', 'campus', 'keyword', 'member_id'], $where)->field($field)->order($order);
        
        $result = $this->pageQuery($search_model);
        
        // 统一返回格式
        $list = $result['data'] ?? [];
        $count = $result['total'] ?? 0;
        
        // 关联会员信息与学校名称（school_id 为 0 时用已通过校园认证补全展示名）
        if (!empty($list)) {
            $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
            $school_ids = array_unique(array_values(array_filter(array_map('intval', array_column($list, 'school_id')), function ($v) {
                return $v > 0;
            })));

            $members = [];
            $schools = [];
            $member_auth_school = [];

            if (!empty($member_ids)) {
                $members = (new \app\model\member\Member())->where([['member_id', 'in', $member_ids]])->column('nickname,headimg', 'member_id');
            }

            $need_auth_mids = [];
            foreach ($list as $row) {
                $sid0 = (int)($row['school_id'] ?? 0);
                $mid0 = (int)($row['member_id'] ?? 0);
                if ($sid0 <= 0 && $mid0 > 0) {
                    $need_auth_mids[$mid0] = true;
                }
            }
            if (!empty($need_auth_mids)) {
                $mid_list = array_keys($need_auth_mids);
                $auth_rows = (new CampusAuth())->where([
                    ['site_id', '=', $this->site_id],
                    ['status', '=', 1],
                    ['member_id', 'in', $mid_list],
                ])->order('id', 'desc')->field('member_id,school_id')->select()->toArray();
                foreach ($auth_rows as $ar) {
                    $am = (int)($ar['member_id'] ?? 0);
                    $as = (int)($ar['school_id'] ?? 0);
                    if ($am > 0 && $as > 0 && !isset($member_auth_school[$am])) {
                        $member_auth_school[$am] = $as;
                        if (!in_array($as, $school_ids, true)) {
                            $school_ids[] = $as;
                        }
                    }
                }
            }

            if (!empty($school_ids)) {
                $schools = (new \addon\sd_xiaoyuan\app\model\School())->where([
                    ['site_id', '=', $this->site_id],
                    ['id', 'in', $school_ids],
                ])->column('name', 'id');
            }

            foreach ($list as &$item) {
                $mid = $item['member_id'] ?? 0;
                $sid = (int)($item['school_id'] ?? 0);
                if ($sid <= 0) {
                    $mid_i = (int)$mid;
                    if ($mid_i > 0 && !empty($member_auth_school[$mid_i])) {
                        $sid = (int)$member_auth_school[$mid_i];
                    }
                }
                $item['member_nickname'] = $members[$mid]['nickname'] ?? '';
                $item['member_avatar'] = $members[$mid]['headimg'] ?? '';
                $item['school_name'] = $schools[$sid] ?? '';

                // 格式化时间
                if (!empty($item['create_time']) && is_numeric($item['create_time'])) {
                    $item['create_time'] = date('Y-m-d H:i', $item['create_time']);
                }
            }
            unset($item);
        }
        
        return ['list' => $list, 'count' => $count];
    }

    /**
     * 获取商品详情
     */
    public function getInfo(int $id, bool $add_view = false)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('商品不存在');
        }
        
        // 增加浏览量
        if ($add_view) {
            $info->save(['view_count' => $info['view_count'] + 1]);
        }
        
        $data = $info->toArray();
        
        // 附加会员头像和昵称
        if (!empty($data['member_id'])) {
            $member = (new \app\model\member\Member())->where([['member_id', '=', $data['member_id']]])->field('member_id,nickname,headimg')->findOrEmpty();
            if (!$member->isEmpty()) {
                $data['member_nickname'] = $member['nickname'];
                $data['member_avatar'] = $member['headimg'];
            }
        }
        
        // 格式化时间
        if (!empty($data['create_time']) && is_numeric($data['create_time'])) {
            $data['create_time'] = date('Y-m-d H:i', $data['create_time']);
        }
        if (!empty($data['update_time']) && is_numeric($data['update_time'])) {
            $data['update_time'] = date('Y-m-d H:i', $data['update_time']);
        }

        // 学校名称（与列表一致：支持校园认证补全）
        $sid_info = (int)($data['school_id'] ?? 0);
        if ($sid_info <= 0 && !empty($data['member_id'])) {
            $auth_one = (new CampusAuth())->where([
                ['member_id', '=', (int)$data['member_id']],
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
            ])->order('id', 'desc')->field('school_id')->find();
            if ($auth_one && (int)$auth_one['school_id'] > 0) {
                $sid_info = (int)$auth_one['school_id'];
            }
        }
        if ($sid_info > 0) {
            $sch = (new \addon\sd_xiaoyuan\app\model\School())->where([
                ['id', '=', $sid_info],
                ['site_id', '=', $this->site_id],
            ])->field('name')->find();
            if ($sch) {
                $data['school_name'] = $sch['name'] ?? '';
            }
        }

        return $data;
    }

    /**
     * 发布商品
     */
    public function publish(int $member_id, array $data)
    {
        $this->checkContact($data);
        // 获取配置，判断是否自动审核通过
        $configService = new ConfigService();
        $config = $configService->getConfig();
        $autoApprove = $config['secondhand_auto_approve'] ?? 1;
        
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
        
        // category_id 支持字符串类型分类码
        $categoryId = $data['category_id'] ?? 'OTHER';
        if (is_numeric($categoryId)) {
            $categoryId = (int)$categoryId;
        }
        
        $isTop = !empty($data['is_urgent']) ? 1 : 0;
        
        $goods_data = [
            'site_id' => $this->site_id,
            'member_id' => $member_id,
            'school_id' => $schoolId,
            'campus' => $campus,
            'category_id' => $categoryId,
            'title' => $data['title'],
            'content' => $data['content'] ?? '',
            'images' => $data['images'] ?? '',
            'original_price' => $data['original_price'] ?? 0,
            'price' => $data['price'],
            'condition_level' => $data['condition_level'] ?? 9,
            'contact_name' => $data['contact_name'] ?? '',
            'contact_mobile' => $data['contact_mobile'] ?? '',
            'contact_wechat' => $data['contact_wechat'] ?? '',
            'trade_method' => $data['trade_method'] ?? 'FACE',
            'trade_address' => $data['trade_address'] ?? '',
            'is_top' => $isTop,
            'status' => $autoApprove ? Secondhand::STATUS_ON : Secondhand::STATUS_PENDING, // 根据配置决定状态：1-自动通过，0-待审核
            'create_time' => time(),
            'update_time' => time(),
        ];
        
        $res = $this->model->create($goods_data);
        return $res->id;
    }

    /**
     * 编辑商品
     */
    public function edit(int $id, int $member_id, array $data)
    {
        $goods = $this->model->where([['id', '=', $id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($goods)) {
            throw new CommonException('商品不存在');
        }

        $this->checkContact(array_merge($goods->toArray(), $data));
        
        $update_data = [
            'category_id' => $data['category_id'] ?? $goods['category_id'],
            'title' => $data['title'] ?? $goods['title'],
            'content' => $data['content'] ?? $goods['content'],
            'images' => $data['images'] ?? $goods['images'],
            'original_price' => $data['original_price'] ?? $goods['original_price'],
            'price' => $data['price'] ?? $goods['price'],
            'condition_level' => $data['condition_level'] ?? $goods['condition_level'],
            'contact_name' => $data['contact_name'] ?? $goods['contact_name'],
            'contact_mobile' => $data['contact_mobile'] ?? $goods['contact_mobile'],
            'contact_wechat' => $data['contact_wechat'] ?? $goods['contact_wechat'],
            'trade_method' => $data['trade_method'] ?? $goods['trade_method'],
            'trade_address' => $data['trade_address'] ?? $goods['trade_address'],
            'update_time' => time(),
        ];
        if (array_key_exists('is_urgent', $data)) {
            $update_data['is_top'] = !empty($data['is_urgent']) ? 1 : 0;
        }
        
        $goods->save($update_data);
        return true;
    }

    /**
     * 下架商品
     */
    public function off(int $id, int $member_id)
    {
        $goods = $this->model->where([['id', '=', $id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($goods)) {
            throw new CommonException('商品不存在');
        }
        
        $goods->save(['status' => Secondhand::STATUS_OFF, 'update_time' => time()]);
        return true;
    }

    /**
     * 上架商品
     */
    public function on(int $id, int $member_id)
    {
        $goods = $this->model->where([['id', '=', $id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($goods)) {
            throw new CommonException('商品不存在');
        }
        
        $goods->save(['status' => Secondhand::STATUS_ON, 'update_time' => time()]);
        return true;
    }

    /**
     * 标记已售出
     */
    public function sold(int $id, int $member_id)
    {
        $goods = $this->model->where([['id', '=', $id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($goods)) {
            throw new CommonException('商品不存在');
        }
        
        $goods->save(['status' => Secondhand::STATUS_SOLD, 'update_time' => time()]);
        return true;
    }

    /**
     * 删除商品
     */
    public function del(int $id, int $member_id)
    {
        $goods = $this->model->where([['id', '=', $id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($goods)) {
            throw new CommonException('商品不存在');
        }
        
        $goods->delete();
        return true;
    }

    /**
     * 想要商品
     */
    public function want(int $goods_id, int $member_id, string $message = '')
    {
        $goods = $this->model->where([['id', '=', $goods_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($goods)) {
            throw new CommonException('商品不存在');
        }
        
        if ($goods['member_id'] == $member_id) {
            throw new CommonException('不能想要自己的商品');
        }
        
        // 增加想要数
        $goods->save(['want_count' => $goods['want_count'] + 1]);
        
        // 发送消息给卖家
        (new MessageService())->send($goods['member_id'], 'SECONDHAND', '有人想要您的商品', '有用户对您发布的"' . $goods['title'] . '"感兴趣', ['goods_id' => $goods_id], $member_id);
        
        return true;
    }

    /**
     * 获取我的商品列表
     */
    public function getMyPage(array $where = [])
    {
        $where['member_id'] = $this->member_id;
        return $this->getPage($where);
    }

    /**
     * 获取分类列表
     */
    public function getCategoryList()
    {
        return (new SecondhandCategory())->where([['site_id', '=', $this->site_id], ['status', '=', 1]])->order('sort desc,id asc')->select()->toArray();
    }

    /**
     * 获取交易方式列表
     */
    public function getTradeMethodList()
    {
        return Secondhand::getTradeMethodList();
    }

    private function checkContact(array $data)
    {
        $mobile = trim((string)($data['contact_mobile'] ?? ''));
        $wechat = trim((string)($data['contact_wechat'] ?? ''));
        if ($mobile === '' && $wechat === '') {
            throw new CommonException('请填写手机号或微信号，至少一项');
        }
    }
}
