<?php

namespace addon\phone_shop\app\service\admin\member;

use addon\phone_shop\app\model\member\ForwardApplication;
use addon\phone_shop\app\service\core\member\ForwardApplicationNoticeService;
use addon\phone_shop\app\service\core\member\ForwardBenefitService;
use addon\phone_shop\app\service\core\member\ForwardApplicationSchemaService;
use app\model\member\Member;
use app\model\member\MemberLevel;
use app\service\core\diy_form\CoreDiyFormRecordsService;
use app\service\core\member\CoreMemberService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

/** 同行转发权限申请审核。 */
class ForwardApplicationService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        ForwardApplicationSchemaService::ensure();
        $this->model = new ForwardApplication();
    }

    public function getPage(array $where = []): array
    {
        $query = $this->model->where([['site_id', '=', $this->site_id]])
            ->with(['member', 'targetLevel'])
            ->field('application_id,site_id,member_id,target_level_id,target_level_name,form_id,form_record_id,reviewer_uid,reviewer_name,status,apply_message,review_reason,reviewed_uid,reviewed_name,reviewed_at,create_time,update_time')
            ->append(['status_name']);
        if (($where['status'] ?? '') !== '') $query->where('status', '=', $where['status']);
        if (($where['reviewer_uid'] ?? 0) > 0) $query->where('reviewer_uid', '=', (int)$where['reviewer_uid']);
        if (trim((string)($where['keyword'] ?? '')) !== '') {
            $keyword = '%' . trim((string)$where['keyword']) . '%';
            $memberIds = Member::where([['site_id', '=', $this->site_id]])
                ->whereLike('nickname|username|mobile|member_no', $keyword)->column('member_id');
            $query->whereIn('member_id', $memberIds ?: [0]);
        }
        return $this->pageQuery($query->order('application_id desc'));
    }

    public function getInfo(int $applicationId): array
    {
        $row = $this->model->where([
            ['site_id', '=', $this->site_id], ['application_id', '=', $applicationId],
        ])->with(['member', 'targetLevel'])->append(['status_name'])->findOrEmpty()->toArray();
        if (!$row) throw new AdminException('申请记录不存在');
        $row['form_record'] = (new CoreDiyFormRecordsService())->getInfo([
            'site_id' => $this->site_id,
            'record_id' => (int)$row['form_record_id'],
        ]);
        return $row;
    }

    public function review(int $applicationId, string $action, string $reason = ''): void
    {
        if (!in_array($action, ['approve', 'reject'], true)) throw new AdminException('审核动作不正确');
        Db::startTrans();
        try {
            $row = $this->model->where([
                ['site_id', '=', $this->site_id], ['application_id', '=', $applicationId],
            ])->lock(true)->findOrEmpty();
            if ($row->isEmpty()) throw new AdminException('申请记录不存在');
            if ((string)$row->status !== 'pending') throw new AdminException('该申请已经处理，请勿重复审核');

            if ($action === 'approve') {
                $levelId = (int)$row->target_level_id;
                $level = MemberLevel::where([
                    ['site_id', '=', $this->site_id], ['level_id', '=', $levelId],
                ])->field('level_id')->findOrEmpty();
                if ($level->isEmpty() || !(new ForwardBenefitService())->canUse($this->site_id, $levelId)) {
                    throw new AdminException('目标会员等级已删除或已关闭同行转发权益，请先检查会员等级设置');
                }
                (new CoreMemberService())->modify($this->site_id, (int)$row->member_id, 'member_level', $levelId);
            }

            $row->save([
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'review_reason' => mb_substr(trim($reason), 0, 500),
                'reviewed_uid' => (int)$this->uid,
                'reviewed_name' => (string)$this->username,
                'reviewed_at' => time(),
                'update_time' => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        (new ForwardApplicationNoticeService())->send($this->site_id, $applicationId, $action === 'approve');
    }
}
