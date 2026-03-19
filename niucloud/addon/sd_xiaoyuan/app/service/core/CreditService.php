<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Credit;
use addon\sd_xiaoyuan\app\model\CreditLog;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 信誉分服务
 */
class CreditService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Credit();
    }

    /**
     * 获取或创建用户信誉分记录
     */
    public function getCredit(int $member_id)
    {
        $credit = $this->model->where([['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($credit)) {
            $credit = $this->model->create([
                'site_id' => $this->site_id,
                'member_id' => $member_id,
                'credit_score' => Credit::INIT_SCORE,
                'total_complete' => 0,
                'total_cancel' => 0,
                'total_complaint' => 0,
                'is_restricted' => 0,
                'create_time' => time(),
                'update_time' => time(),
            ]);
        }
        return $credit;
    }

    /**
     * 获取用户信誉分信息
     */
    public function getInfo(int $member_id)
    {
        $credit = $this->getCredit($member_id);
        return [
            'credit_score' => $credit['credit_score'],
            'total_complete' => $credit['total_complete'],
            'total_cancel' => $credit['total_cancel'],
            'total_complaint' => $credit['total_complaint'],
            'is_restricted' => $credit['is_restricted'],
            'restrict_threshold' => Credit::RESTRICT_THRESHOLD,
        ];
    }

    /**
     * 检查用户是否受限
     */
    public function isRestricted(int $member_id): bool
    {
        $credit = $this->getCredit($member_id);
        return $credit['is_restricted'] == 1 || $credit['credit_score'] < Credit::RESTRICT_THRESHOLD;
    }

    /**
     * 检查用户是否可以接单或发布
     */
    public function checkCanOperate(int $member_id)
    {
        if ($this->isRestricted($member_id)) {
            throw new CommonException('您的信誉分低于' . Credit::RESTRICT_THRESHOLD . '分，暂时无法接单或发布任务');
        }
        return true;
    }

    /**
     * 订单完成加分
     */
    public function onOrderComplete(int $member_id, string $biz_type = 'ORDER', int $biz_id = 0)
    {
        return $this->changeScore($member_id, Credit::COMPLETE_SCORE, CreditLog::TYPE_COMPLETE, $biz_type, $biz_id, '订单完成');
    }

    /**
     * 取消订单扣分
     */
    public function onOrderCancel(int $member_id, string $biz_type = 'ORDER', int $biz_id = 0)
    {
        return $this->changeScore($member_id, Credit::CANCEL_SCORE, CreditLog::TYPE_CANCEL, $biz_type, $biz_id, '取消订单');
    }

    /**
     * 投诉成立扣分
     * @param int $severity 严重程度 1-10，决定扣分多少
     */
    public function onComplaintValid(int $member_id, int $severity = 5, string $biz_type = 'COMPLAINT', int $biz_id = 0)
    {
        // 根据严重程度计算扣分 (10~20分)
        $severity = max(1, min(10, $severity));
        $score = Credit::COMPLAINT_MIN_SCORE - (int)(($severity - 1) / 9 * (Credit::COMPLAINT_MAX_SCORE - Credit::COMPLAINT_MIN_SCORE));
        
        return $this->changeScore($member_id, $score, CreditLog::TYPE_COMPLAINT, $biz_type, $biz_id, '投诉成立(严重程度:' . $severity . ')');
    }

    /**
     * 管理员调整分数
     */
    public function adminAdjust(int $member_id, int $score, string $remark = '')
    {
        return $this->changeScore($member_id, $score, CreditLog::TYPE_ADMIN, '', 0, $remark ?: '管理员调整');
    }

    /**
     * 变动信誉分
     */
    protected function changeScore(int $member_id, int $change_score, string $type, string $biz_type = '', int $biz_id = 0, string $remark = '')
    {
        Db::startTrans();
        try {
            $credit = $this->getCredit($member_id);
            $before_score = $credit['credit_score'];
            $after_score = max(0, $before_score + $change_score); // 最低0分
            
            // 更新信誉分
            $update_data = [
                'credit_score' => $after_score,
                'update_time' => time(),
            ];
            
            // 更新统计
            if ($type == CreditLog::TYPE_COMPLETE) {
                $update_data['total_complete'] = $credit['total_complete'] + 1;
            } elseif ($type == CreditLog::TYPE_CANCEL) {
                $update_data['total_cancel'] = $credit['total_cancel'] + 1;
            } elseif ($type == CreditLog::TYPE_COMPLAINT) {
                $update_data['total_complaint'] = $credit['total_complaint'] + 1;
            }
            
            // 检查是否需要限制
            if ($after_score < Credit::RESTRICT_THRESHOLD) {
                $update_data['is_restricted'] = 1;
            } else {
                $update_data['is_restricted'] = 0;
            }
            
            $credit->save($update_data);
            
            // 记录变动日志
            (new CreditLog())->create([
                'site_id' => $this->site_id,
                'member_id' => $member_id,
                'type' => $type,
                'change_score' => $change_score,
                'before_score' => $before_score,
                'after_score' => $after_score,
                'biz_type' => $biz_type,
                'biz_id' => $biz_id,
                'remark' => $remark,
                'create_time' => time(),
            ]);
            
            // 如果分数低于阈值，发送系统消息
            if ($after_score < Credit::RESTRICT_THRESHOLD && $before_score >= Credit::RESTRICT_THRESHOLD) {
                (new MessageService())->send($member_id, 'SYSTEM', '信誉分警告', '您的信誉分已低于' . Credit::RESTRICT_THRESHOLD . '分，暂时无法接单或发布任务，请注意提升信誉分。');
            }
            
            Db::commit();
            return $after_score;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 获取信誉分变动记录
     */
    public function getLogPage(int $member_id, array $where = [])
    {
        $field = 'id,type,change_score,before_score,after_score,biz_type,biz_id,remark,create_time';
        $order = 'id desc';
        
        $log_model = new CreditLog();
        $search_model = $log_model->where([['site_id', '=', $this->site_id], ['member_id', '=', $member_id]])->withSearch(['type'], $where)->field($field)->order($order);
        return $this->pageQuery($search_model);
    }

    /**
     * 获取信誉分列表(后台)
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,credit_score,total_complete,total_cancel,total_complaint,is_restricted,create_time,update_time';
        $order = 'credit_score asc,id desc';
        
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch(['member_id', 'is_restricted'], $where)->field($field)->order($order);
        $result = $this->pageQuery($search_model);
        
        // 附加会员信息
        $list = $result['data'] ?? [];
        if (!empty($list)) {
            $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
            if (!empty($member_ids)) {
                $members = (new \app\model\member\Member())->where([['member_id', 'in', $member_ids]])->column('nickname,headimg,mobile', 'member_id');
                foreach ($list as &$item) {
                    $mid = $item['member_id'] ?? 0;
                    $item['nickname'] = $members[$mid]['nickname'] ?? '';
                    $item['headimg'] = $members[$mid]['headimg'] ?? '';
                    $item['mobile'] = $members[$mid]['mobile'] ?? '';
                }
                unset($item);
            }
            $result['data'] = $list;
        }
        
        return $result;
    }

    /**
     * 获取信誉分统计
     */
    public function getStat()
    {
        $total = $this->model->where([['site_id', '=', $this->site_id]])->count();
        $restricted = $this->model->where([['site_id', '=', $this->site_id], ['is_restricted', '=', 1]])->count();
        $low_score = $this->model->where([['site_id', '=', $this->site_id], ['credit_score', '<', Credit::RESTRICT_THRESHOLD]])->count();
        $high_score = $this->model->where([['site_id', '=', $this->site_id], ['credit_score', '>=', 90]])->count();
        
        return [
            'total' => $total,
            'restricted' => $restricted,
            'low_score' => $low_score,
            'high_score' => $high_score,
        ];
    }
}
