<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use app\model\member\Member;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 项目群编号服务。
 * 编号先于企微建群预占，避免建群失败或重复点击导致同号串单。
 */
final class ProjectCenterGroupService
{
    /**
     * 从客户输入中提取标准群编号。
     *
     * 客户经常会粘贴完整群名，例如“美团闪购 0819-1 张三”，这里主动提取
     * 0819-1，既减少输入错误，也保证后续查询、付款和资料工单使用同一编号。
     */
    public function normalizeGroupNo(string $groupNo): string
    {
        $value = trim(strtr($groupNo, ['－' => '-', '—' => '-', '–' => '-']));
        if ($value === '') return '';
        if (preg_match('/(?<!\d)(\d{4}(?:\d{4})?-\d+)(?!\d)/u', $value, $matches)) {
            return (string)$matches[1];
        }
        return mb_substr(preg_replace('/\s+/u', '', $value) ?: '', 0, 50);
    }

    /**
     * 校验群编号是否可以继续办理。
     *
     * $memberId > 0 时同时校验群与当前会员的归属。客户可以先查看付款码，
     * 进入资料步骤后，群号预检、正式提交和驳回重提都必须执行会员归属校验。
     */
    public function requireAvailableByNo(
        int $siteId,
        int $projectId,
        string $groupNo,
        int $memberId = 0,
        bool $lock = false
    ): ProjectCenterGroup
    {
        $rawGroupNo = $groupNo;
        $normalizedGroupNo = $this->normalizeGroupNo($rawGroupNo);
        if ($normalizedGroupNo === '') {
            throw new CommonException('请填写客户群编号，例如群名“美团闪购 0819-1 ×××”中仅填写 0819-1');
        }
        try {
            $parsedGroupNo = $this->parseSpecifiedGroupNo($rawGroupNo);
        } catch (CommonException $e) {
            $this->logGroupLookupFailure($siteId, $projectId, $rawGroupNo, $normalizedGroupNo, 'invalid_format');
            throw new CommonException('群编号无法核验，请检查群名称中的编号；如仍无法继续，请联系群内工作人员补录');
        }
        $group = $this->findGroupByParsedNo($siteId, $projectId, $parsedGroupNo, $lock);
        if ($group->isEmpty()) {
            $this->logGroupLookupFailure($siteId, $projectId, $rawGroupNo, $normalizedGroupNo, 'not_found');
            throw new CommonException('群编号无法核验，请检查群名称中的编号；如仍无法继续，请联系群内工作人员补录');
        }

        $this->assertAvailableForMember($group, $siteId, $memberId, $projectId, $rawGroupNo, $normalizedGroupNo);
        return $group;
    }

    /**
     * 客户输入群名称中的业务编号后，系统自动建账并绑定当前账号。
     *
     * 管理员预登记仍可用于提前绑定手机号；没有预登记时则在这里创建普通微信群
     * 台账，消除“先后台建台账、客户才能填资料”的人工卡点。
     */
    public function resolveForMember(int $siteId, int $projectId, string $groupNo, int $memberId): ProjectCenterGroup
    {
        if ($memberId <= 0) throw new CommonException('请先登录');
        $project = ProjectCenterProject::where([
            ['site_id', '=', $siteId], ['id', '=', $projectId], ['status', '=', 1],
        ])->field('id')->findOrEmpty();
        if ($project->isEmpty()) throw new CommonException('项目不存在或暂未开放');
        $parsed = $this->parseSpecifiedGroupNo($groupNo);
        $member = Member::where([
            ['site_id', '=', $siteId], ['member_id', '=', $memberId],
        ])->field('member_id,nickname,username,mobile')->findOrEmpty();
        if ($member->isEmpty()) throw new CommonException('当前客户账号不存在，请重新登录');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return Db::transaction(function () use ($siteId, $projectId, $memberId, $member, $parsed, $groupNo) {
                    $group = $this->findGroupByParsedNo($siteId, $projectId, $parsed, true);
                    $now = time();
                    if (!$group->isEmpty()) {
                        $this->assertAvailableForMember(
                            $group,
                            $siteId,
                            $memberId,
                            $projectId,
                            $groupNo,
                            (string)$parsed['group_no']
                        );
                        $update = [];
                        if ((int)$group->member_id === 0) $update['member_id'] = $memberId;
                        if (trim((string)$group->member_name) === '') {
                            $update['member_name'] = mb_substr((string)($member->nickname ?: $member->username ?: $member->mobile), 0, 100);
                        }
                        if (trim((string)$group->member_mobile) === '' && trim((string)$member->mobile) !== '') {
                            $update['member_mobile'] = mb_substr(trim((string)$member->mobile), 0, 30);
                        }
                        if (in_array((string)$group->status, ['reserved', 'create_failed', 'created'], true)) {
                            $update['status'] = 'active';
                            $update['created_at'] = (int)$group->created_at ?: $now;
                        }
                        if ((string)$group->status === 'create_failed') {
                            $update['create_mode'] = 'manual';
                            $update['error_message'] = '';
                        }
                        if ($update !== []) {
                            $update['update_at'] = $now;
                            $group->save($update);
                        }
                        return $group;
                    }

                    $usedAlias = Db::name('project_center_group_no_alias')->where([
                        ['site_id', '=', $siteId], ['old_group_no_full', '=', $parsed['group_no_full']],
                    ])->lock(true)->find();
                    if (is_array($usedAlias)) throw new CommonException('该群编号曾被使用，请联系群管理员核对');

                    // 客户输入的编号只用于本次群台账，不得反向抬高全站自动编号序列。
                    // 自动编号会主动跳过当前号和历史号，既避免恶意大序号污染，也不会撞号。
                    return ProjectCenterGroup::create([
                        'site_id' => $siteId,
                        'project_id' => $projectId,
                        'group_no' => (string)$parsed['group_no'],
                        'group_no_full' => (string)$parsed['group_no_full'],
                        'member_id' => $memberId,
                        'member_name' => mb_substr((string)($member->nickname ?: $member->username ?: $member->mobile), 0, 100),
                        'member_mobile' => mb_substr(trim((string)$member->mobile), 0, 30),
                        'store_name' => '',
                        'wecom_chat_id' => '',
                        'wecom_external_userid' => '',
                        'owner_uid' => 0,
                        'collaborator_uids' => [],
                        'create_mode' => 'manual',
                        'status' => 'active',
                        'error_message' => '',
                        'created_at' => $now,
                        'completed_at' => 0,
                        'dissolved_at' => 0,
                        'create_at' => $now,
                        'update_at' => $now,
                    ]);
                });
            } catch (CommonException $e) {
                throw $e;
            } catch (\Throwable $e) {
                if ($attempt === 2) throw $e;
                usleep(20000 * ($attempt + 1));
            }
        }
        throw new CommonException('客户群编号绑定失败，请稍后重试');
    }

    /** @param array{group_no:string,group_no_full:string,stat_date:int,sequence:int} $parsed */
    private function findGroupByParsedNo(int $siteId, int $projectId, array $parsed, bool $lock): ProjectCenterGroup
    {
        $query = ProjectCenterGroup::where([
            ['site_id', '=', $siteId], ['project_id', '=', $projectId],
            ['group_no_full', '=', $parsed['group_no_full']],
        ])->order('id desc');
        if ($lock) $query->lock(true);
        $group = $query->findOrEmpty();
        if (!$group->isEmpty()) return $group;

        // 历史别名不可变；先解析 group_id，再按统一锁序锁客户群主记录。
        $alias = Db::name('project_center_group_no_alias')->where([
            ['site_id', '=', $siteId], ['old_group_no_full', '=', $parsed['group_no_full']],
        ])->order('id desc')->find();
        if (!is_array($alias) || (int)($alias['group_id'] ?? 0) <= 0) return $group;
        $aliasQuery = ProjectCenterGroup::where([
            ['site_id', '=', $siteId], ['project_id', '=', $projectId], ['id', '=', (int)$alias['group_id']],
        ]);
        if ($lock) $aliasQuery->lock(true);
        return $aliasQuery->findOrEmpty();
    }

    private function assertAvailableForMember(
        ProjectCenterGroup $group,
        int $siteId,
        int $memberId,
        int $projectId,
        string $rawGroupNo,
        string $normalizedGroupNo
    ): void {
        if ($memberId > 0) {
            if ((int)$group->member_id > 0 && (int)$group->member_id !== $memberId) {
                throw new CommonException('该群编号已绑定其他客户，请勿提交');
            }
            $reservedMobile = trim((string)$group->member_mobile);
            if ((int)$group->member_id === 0 && $reservedMobile === '') {
                throw new CommonException('该客户群尚未绑定客户手机号，请联系群管理员补充后再提交资料');
            }
            if ((int)$group->member_id === 0 && $reservedMobile !== '') {
                $member = Member::where([['site_id', '=', $siteId], ['member_id', '=', $memberId]])
                    ->field('member_id,mobile')->findOrEmpty();
                $memberMobile = preg_replace('/\D+/', '', (string)($member->mobile ?? ''));
                $groupMobile = preg_replace('/\D+/', '', $reservedMobile);
                if ($memberMobile === '') throw new CommonException('请先为当前账号绑定群内登记的手机号，再提交资料');
                if ($memberMobile !== $groupMobile) throw new CommonException('当前账号手机号与群内登记手机号不一致，请联系工作人员核对');
            }
        }

        $status = (string)$group->status;
        if ($status === 'completed') throw new CommonException('该客户群已完成办理，无需重复提交资料');
        if ($status === 'refund_pending') throw new CommonException('本次办理已终止，退款正在处理中，暂时不能继续提交资料');
        if ($status === 'refunded') throw new CommonException('该客户已完成退款，本次办理已经关闭');
        if ($status === 'abandoned') throw new CommonException('本次办理已结束，如需重新参与请联系群内工作人员');
        if ($status === 'dissolved') throw new CommonException('该客户群已解散，请联系工作人员重新建群');
        if (!in_array($status, ['reserved', 'create_failed', 'created', 'active'], true)) {
            $this->logGroupLookupFailure($siteId, $projectId, $rawGroupNo, $normalizedGroupNo, 'invalid_status:' . $status);
            throw new CommonException('该客户群状态暂时无法识别，请联系群内工作人员处理');
        }
    }

    /**
     * 查询当前会员自己的群状态，用于没有资料工单时回显退款/关闭结果。
     * 失败统一返回空模型，不向客户端泄露可预测群号是否属于其他客户。
     */
    public function ownedGroupForStatus(int $siteId, int $projectId, string $groupNo, int $memberId): ProjectCenterGroup
    {
        if ($memberId <= 0 || trim($groupNo) === '') return ProjectCenterGroup::where('id', '=', 0)->findOrEmpty();
        try {
            $group = $this->findGroupByParsedNo($siteId, $projectId, $this->parseSpecifiedGroupNo($groupNo), false);
        } catch (\Throwable $e) {
            return ProjectCenterGroup::where('id', '=', 0)->findOrEmpty();
        }
        if ($group->isEmpty()) return $group;
        if ((int)$group->member_id > 0) {
            return (int)$group->member_id === $memberId ? $group : ProjectCenterGroup::where('id', '=', 0)->findOrEmpty();
        }
        $reservedMobile = preg_replace('/\D+/', '', trim((string)$group->member_mobile));
        if ($reservedMobile === '') return ProjectCenterGroup::where('id', '=', 0)->findOrEmpty();
        $member = Member::where([['site_id', '=', $siteId], ['member_id', '=', $memberId]])
            ->field('mobile')->findOrEmpty();
        $memberMobile = preg_replace('/\D+/', '', (string)($member->mobile ?? ''));
        return $memberMobile !== '' && $memberMobile === $reservedMobile
            ? $group
            : ProjectCenterGroup::where('id', '=', 0)->findOrEmpty();
    }

    public function reserve(
        int $siteId,
        int $projectId,
        array $memberData,
        int $ownerUid = 0,
        array $collaborators = [],
        string $specifiedGroupNo = ''
    ): ProjectCenterGroup
    {
        $project = ProjectCenterProject::where([
            ['site_id', '=', $siteId], ['id', '=', $projectId],
        ])->findOrEmpty();
        if ($project->isEmpty()) throw new CommonException('项目不存在');

        $memberId = max(0, (int)($memberData['member_id'] ?? 0));
        if ($memberId > 0) {
            $member = Member::where([['site_id', '=', $siteId], ['member_id', '=', $memberId]])
                ->field('member_id,nickname,username,mobile')->findOrEmpty()->toArray();
            if ($member === []) throw new CommonException('客户不存在');
            $memberData['member_name'] = trim((string)($memberData['member_name'] ?? '')) ?: (string)($member['nickname'] ?: $member['username'] ?: $member['mobile']);
            $memberData['member_mobile'] = trim((string)($memberData['member_mobile'] ?? '')) ?: (string)$member['mobile'];
        }
        $externalUserId = trim((string)($memberData['wecom_external_userid'] ?? ''));
        if ($memberId <= 0 && trim((string)($memberData['member_mobile'] ?? '')) === '' && $externalUserId === '') {
            throw new CommonException('请选择企业微信客户，或填写客户手机号');
        }
        if ($externalUserId !== '') {
            $existing = ProjectCenterGroup::where([
                ['site_id', '=', $siteId], ['project_id', '=', $projectId],
                ['wecom_external_userid', '=', $externalUserId],
                ['status', 'in', ['reserved', 'create_failed', 'created', 'active']],
            ])->findOrEmpty();
            if (!$existing->isEmpty()) {
                throw new CommonException('该客户在当前项目已有办理中的客户群，请从群台账继续操作');
            }
        }

        $now = time();
        $groupData = [
            'site_id' => $siteId,
            'project_id' => $projectId,
            'member_id' => $memberId,
            'member_name' => mb_substr(trim((string)($memberData['member_name'] ?? '')), 0, 100),
            'member_mobile' => mb_substr(trim((string)($memberData['member_mobile'] ?? '')), 0, 30),
            'store_name' => mb_substr(trim((string)($memberData['store_name'] ?? '')), 0, 150),
            'wecom_external_userid' => mb_substr($externalUserId, 0, 120),
            'owner_uid' => $ownerUid,
            'collaborator_uids' => array_values(array_unique(array_filter(array_map('intval', $collaborators)))),
            'create_mode' => $externalUserId !== '' ? 'wecom' : 'manual',
            'status' => 'reserved',
            'create_at' => $now,
            'update_at' => $now,
        ];

        if (trim($specifiedGroupNo) !== '') {
            return $this->reserveSpecifiedGroupNo(
                $siteId,
                $this->parseSpecifiedGroupNo($specifiedGroupNo),
                $groupData
            );
        }

        $date = (int)date('Ymd');
        // 群编号在同一站点内按天统一递增，不能按项目各自从 1 开始，
        // 否则同一天两个项目都会生成 0819-1，人工群聊和财务流水会串单。
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $sequence = $this->nextSequence($siteId, $date);
            $displayNo = date('md') . '-' . $sequence;
            $uniqueNo = date('Ymd') . '-' . $sequence;
            try {
                return ProjectCenterGroup::create(array_merge($groupData, [
                    'group_no' => $displayNo,
                    'group_no_full' => $uniqueNo,
                ]));
            } catch (\Throwable $e) {
                // 客户自助认领与后台自动编号可能在极小窗口同时占用候选号；
                // 唯一索引兜底后继续取下一号，不把原始数据库异常暴露给用户。
                $occupied = ProjectCenterGroup::where([
                    ['site_id', '=', $siteId], ['group_no_full', '=', $uniqueNo],
                ])->count() > 0 || Db::name('project_center_group_no_alias')->where([
                    ['site_id', '=', $siteId], ['old_group_no_full', '=', $uniqueNo],
                ])->count() > 0;
                if (!$occupied) throw $e;
                if ($attempt === 2) throw new CommonException('群编号生成冲突，请稍后重试');
            }
        }
        throw new CommonException('群编号生成失败，请稍后重试');
    }

    /**
     * 解析管理员指定的群编号。
     *
     * 允许粘贴完整群名，但提取出的编号必须严格符合 MMDD-N 或 YYYYMMDD-N；
     * N 从 1 开始且不能带前导零。MMDD 形式按“不晚于今天的
     * 最近一次该日期”补全年份，保证 1 月仍能核验上年 12 月的办理群。
     *
     * @return array{group_no:string,group_no_full:string,stat_date:int,sequence:int}
     */
    private function parseSpecifiedGroupNo(string $input): array
    {
        $normalized = $this->normalizeGroupNo($input);
        if (!preg_match('/^(\d{4}|\d{8})-([1-9]\d{0,2})$/', $normalized, $matches)) {
            throw new CommonException('群编号格式不正确，请填写 MMDD-N（如 0819-1）或 YYYYMMDD-N');
        }

        $datePart = (string)$matches[1];
        $fullDate = $datePart;
        if (strlen($datePart) === 4) {
            $month = (int)substr($datePart, 0, 2);
            $day = (int)substr($datePart, 2, 2);
            $currentYear = (int)date('Y');
            $fullDate = '';
            // 向前最多回溯 4 年，同时覆盖 2 月 29 日的闰年边界。
            for ($yearCandidate = $currentYear; $yearCandidate >= $currentYear - 4; $yearCandidate--) {
                $candidate = sprintf('%04d%s', $yearCandidate, $datePart);
                if (checkdate($month, $day, $yearCandidate) && (int)$candidate <= (int)date('Ymd')) {
                    $fullDate = $candidate;
                    break;
                }
            }
            if ($fullDate === '') throw new CommonException('群编号中的日期无效，请核对后重试');
        }
        $year = (int)substr($fullDate, 0, 4);
        $month = (int)substr($fullDate, 4, 2);
        $day = (int)substr($fullDate, 6, 2);
        if (!checkdate($month, $day, $year)) {
            throw new CommonException('群编号中的日期无效，请核对后重试');
        }
        if ((int)$fullDate > (int)date('Ymd')) {
            throw new CommonException('群编号中的日期不能晚于今天');
        }

        $sequence = (int)$matches[2];
        return [
            'group_no' => substr($fullDate, 4, 4) . '-' . $sequence,
            'group_no_full' => $fullDate . '-' . $sequence,
            'stat_date' => (int)$fullDate,
            'sequence' => $sequence,
        ];
    }

    /** 管理员补录指定编号，并原子抬高当天序列，避免后续自动编号再次撞号。 */
    private function reserveSpecifiedGroupNo(int $siteId, array $parsedGroupNo, array $groupData): ProjectCenterGroup
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return Db::transaction(function () use ($siteId, $parsedGroupNo, $groupData) {
                    $duplicate = ProjectCenterGroup::where([
                        ['site_id', '=', $siteId],
                        ['group_no_full', '=', $parsedGroupNo['group_no_full']],
                    ])->lock(true)->findOrEmpty();
                    if (!$duplicate->isEmpty()) {
                        throw new CommonException('该群编号已登记，请从群台账继续办理');
                    }
                    $reservedAlias = Db::name('project_center_group_no_alias')->where([
                        ['site_id', '=', $siteId],
                        ['old_group_no_full', '=', $parsedGroupNo['group_no_full']],
                    ])->lock(true)->find();
                    if (is_array($reservedAlias)) {
                        throw new CommonException('该群编号曾被使用，不能分配给其他客户群');
                    }

                    $this->raiseDailySequenceFloor($siteId, $parsedGroupNo);

                    return ProjectCenterGroup::create(array_merge($groupData, [
                        'group_no' => $parsedGroupNo['group_no'],
                        'group_no_full' => $parsedGroupNo['group_no_full'],
                    ]));
                });
            } catch (CommonException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $duplicate = ProjectCenterGroup::where([
                    ['site_id', '=', $siteId],
                    ['group_no_full', '=', $parsedGroupNo['group_no_full']],
                ])->findOrEmpty();
                if (!$duplicate->isEmpty()) {
                    throw new CommonException('该群编号已登记，请从群台账继续办理');
                }
                $reservedAlias = Db::name('project_center_group_no_alias')->where([
                    ['site_id', '=', $siteId],
                    ['old_group_no_full', '=', $parsedGroupNo['group_no_full']],
                ])->find();
                if (is_array($reservedAlias)) {
                    throw new CommonException('该群编号曾被使用，不能分配给其他客户群');
                }
                if ($attempt === 2) throw $e;
                usleep(20000 * ($attempt + 1));
            }
        }
        throw new CommonException('群编号补录失败，请稍后重试');
    }

    /**
     * 管理员更正已登记的业务编号。
     *
     * 工单始终通过 group_id 关联，因此更正展示编号不会丢失客户资料；新编号
     * 仍需遵守站点级唯一约束，并同步抬高当天序列，避免后续自动生成撞号。
     */
    public function correctGroupNo(
        int $siteId,
        int $groupId,
        string $groupNo,
        string $expectedGroupNoFull,
        array $audit = []
    ): ProjectCenterGroup
    {
        $parsed = $this->parseSpecifiedGroupNo($groupNo);
        $expected = $this->parseSpecifiedGroupNo($expectedGroupNoFull);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return Db::transaction(function () use ($siteId, $groupId, $parsed, $expected, $audit) {
                    $group = ProjectCenterGroup::where([
                        ['site_id', '=', $siteId], ['id', '=', $groupId],
                    ])->lock(true)->findOrEmpty();
                    if ($group->isEmpty()) throw new CommonException('客户群台账不存在');
                    if ((string)$group->group_no_full !== (string)$expected['group_no_full']) {
                        throw new CommonException('群编号已被其他管理员更正为 ' . (string)$group->group_no . '，请刷新后重新确认');
                    }
                    if ((string)$group->group_no_full === (string)$parsed['group_no_full']) {
                        throw new CommonException('正确群编号与当前编号相同，无需更正');
                    }

                    $duplicate = ProjectCenterGroup::where([
                        ['site_id', '=', $siteId],
                        ['group_no_full', '=', $parsed['group_no_full']],
                        ['id', '<>', $groupId],
                    ])->lock(true)->findOrEmpty();
                    if (!$duplicate->isEmpty()) throw new CommonException('该群编号已被其他客户群使用，请重新核对');
                    $targetAlias = Db::name('project_center_group_no_alias')->where([
                        ['site_id', '=', $siteId],
                        ['old_group_no_full', '=', $parsed['group_no_full']],
                    ])->lock(true)->find();
                    if (is_array($targetAlias) && (int)$targetAlias['group_id'] !== $groupId) {
                        throw new CommonException('该群编号曾属于其他客户群，不能重新使用');
                    }

                    $this->raiseDailySequenceFloor($siteId, $parsed);
                    $oldAlias = Db::name('project_center_group_no_alias')->where([
                        ['site_id', '=', $siteId],
                        ['old_group_no_full', '=', (string)$group->group_no_full],
                    ])->lock(true)->find();
                    if (!is_array($oldAlias)) {
                        Db::name('project_center_group_no_alias')->insert([
                            'site_id' => $siteId,
                            'project_id' => (int)$group->project_id,
                            'group_id' => $groupId,
                            'old_group_no' => (string)$group->group_no,
                            'old_group_no_full' => (string)$group->group_no_full,
                            'new_group_no' => (string)$parsed['group_no'],
                            'new_group_no_full' => (string)$parsed['group_no_full'],
                            'reason' => mb_substr(trim((string)($audit['reason'] ?? '')), 0, 500),
                            'source' => in_array((string)($audit['source'] ?? ''), ['application', 'group_ledger'], true)
                                ? (string)$audit['source'] : 'group_ledger',
                            'application_id' => max(0, (int)($audit['application_id'] ?? 0)),
                            'operator_id' => max(0, (int)($audit['operator_id'] ?? 0)),
                            'operator_name' => mb_substr(trim((string)($audit['operator_name'] ?? '')), 0, 100),
                            'create_at' => time(),
                        ]);
                    } elseif ((int)$oldAlias['group_id'] !== $groupId) {
                        throw new CommonException('当前编号的历史归属异常，请联系管理员处理');
                    }
                    // alias 负责旧入口解析和旧号占用；change_log 逐次追加，保证反复更正也可完整追溯。
                    Db::name('project_center_group_no_change_log')->insert([
                        'site_id' => $siteId,
                        'project_id' => (int)$group->project_id,
                        'group_id' => $groupId,
                        'old_group_no' => (string)$group->group_no,
                        'old_group_no_full' => (string)$group->group_no_full,
                        'new_group_no' => (string)$parsed['group_no'],
                        'new_group_no_full' => (string)$parsed['group_no_full'],
                        'reason' => mb_substr(trim((string)($audit['reason'] ?? '')), 0, 500),
                        'source' => in_array((string)($audit['source'] ?? ''), ['application', 'group_ledger'], true)
                            ? (string)$audit['source'] : 'group_ledger',
                        'application_id' => max(0, (int)($audit['application_id'] ?? 0)),
                        'operator_id' => max(0, (int)($audit['operator_id'] ?? 0)),
                        'operator_name' => mb_substr(trim((string)($audit['operator_name'] ?? '')), 0, 100),
                        'create_at' => time(),
                    ]);
                    $group->save([
                        'group_no' => $parsed['group_no'],
                        'group_no_full' => $parsed['group_no_full'],
                        'update_at' => time(),
                    ]);
                    return $group;
                });
            } catch (CommonException $e) {
                throw $e;
            } catch (\Throwable $e) {
                // 并发更正或自动编号时由唯一索引兜底；将数据库异常翻译成可操作提示。
                $duplicate = ProjectCenterGroup::where([
                    ['site_id', '=', $siteId],
                    ['group_no_full', '=', $parsed['group_no_full']],
                    ['id', '<>', $groupId],
                ])->findOrEmpty();
                if (!$duplicate->isEmpty()) {
                    throw new CommonException('该群编号已被其他客户群使用，请重新核对');
                }
                if ($attempt === 1) throw $e;
                usleep(20000);
            }
        }
        throw new CommonException('群编号更正失败，请稍后重试');
    }

    /** @param array{stat_date:int,sequence:int} $parsedGroupNo */
    private function raiseDailySequenceFloor(int $siteId, array $parsedGroupNo): void
    {
        $sequenceFloor = (int)$parsedGroupNo['sequence'];
        $sameDayNumbers = ProjectCenterGroup::where('site_id', '=', $siteId)
            ->whereLike('group_no_full', (string)$parsedGroupNo['stat_date'] . '-%')
            ->lock(true)
            ->column('group_no_full');
        foreach ($sameDayNumbers as $sameDayNumber) {
            if (preg_match('/-(\d+)$/', (string)$sameDayNumber, $sequenceMatches)) {
                $sequenceFloor = max($sequenceFloor, (int)$sequenceMatches[1]);
            }
        }
        $sameDayAliases = Db::name('project_center_group_no_alias')
            ->where('site_id', '=', $siteId)
            ->whereLike('old_group_no_full', (string)$parsedGroupNo['stat_date'] . '-%')
            ->lock(true)
            ->column('old_group_no_full');
        foreach ($sameDayAliases as $sameDayAlias) {
            if (preg_match('/-(\d+)$/', (string)$sameDayAlias, $sequenceMatches)) {
                $sequenceFloor = max($sequenceFloor, (int)$sequenceMatches[1]);
            }
        }

        $sequenceWhere = [
            ['site_id', '=', $siteId],
            ['project_id', '=', 0],
            ['stat_date', '=', $parsedGroupNo['stat_date']],
        ];
        $sequenceRow = Db::name('project_center_daily_sequence')->where($sequenceWhere)->lock(true)->find();
        if (!$sequenceRow) {
            Db::name('project_center_daily_sequence')->insert([
                'site_id' => $siteId,
                'project_id' => 0,
                'stat_date' => $parsedGroupNo['stat_date'],
                'current_seq' => $sequenceFloor,
                'update_at' => time(),
            ]);
        } elseif ((int)$sequenceRow['current_seq'] < $sequenceFloor) {
            Db::name('project_center_daily_sequence')->where($sequenceWhere)->update([
                'current_seq' => $sequenceFloor,
                'update_at' => time(),
            ]);
        }
    }

    /** 群号核验诊断仅记录脱敏输入，不记录客户手机号、姓名或其他业务资料。 */
    private function logGroupLookupFailure(
        int $siteId,
        int $projectId,
        string $rawGroupNo,
        string $normalizedGroupNo,
        string $reason
    ): void {
        Log::warning('[hsx_project_center] 群编号核验失败', [
            'site_id' => $siteId,
            'project_id' => $projectId,
            'raw_group_no' => $this->safeGroupNoForLog($rawGroupNo, 80),
            'normalized_group_no' => $this->safeGroupNoForLog($normalizedGroupNo, 50),
            'reason' => $reason,
        ]);
    }

    private function safeGroupNoForLog(string $value, int $maxLength): string
    {
        $safe = trim(strtr($value, ['－' => '-', '—' => '-', '–' => '-']));
        $safe = preg_replace('/\d{7,}/u', '[masked-number]', $safe) ?: '';
        $safe = preg_replace('/[\x{4e00}-\x{9fff}A-Za-z]+/u', '***', $safe) ?: '';
        return mb_substr($safe, 0, $maxLength);
    }

    /** 并发安全地获取项目当天序号。 */
    private function nextSequence(int $siteId, int $date): int
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return Db::transaction(function () use ($siteId, $date) {
                    // 与编号更正/补录统一使用 current -> alias -> sequence 锁顺序，
                    // 避免自动编号和管理员更正高并发时形成反向锁死锁。
                    $used = [];
                    $currentNumbers = ProjectCenterGroup::where('site_id', '=', $siteId)
                        ->whereLike('group_no_full', (string)$date . '-%')
                        ->lock(true)
                        ->column('group_no_full');
                    foreach ($currentNumbers as $number) $used[(string)$number] = true;
                    $historyNumbers = Db::name('project_center_group_no_alias')
                        ->where('site_id', '=', $siteId)
                        ->whereLike('old_group_no_full', (string)$date . '-%')
                        ->lock(true)
                        ->column('old_group_no_full');
                    foreach ($historyNumbers as $number) $used[(string)$number] = true;

                    $where = [['site_id', '=', $siteId], ['project_id', '=', 0], ['stat_date', '=', $date]];
                    $row = Db::name('project_center_daily_sequence')->where($where)->lock(true)->find();
                    $start = $row ? ((int)$row['current_seq'] + 1) : 1;
                    if ($start < 1 || $start > 999) $start = 1;
                    $next = 0;
                    for ($offset = 0; $offset < 999; $offset++) {
                        $candidate = (($start - 1 + $offset) % 999) + 1;
                        $fullNo = (string)$date . '-' . $candidate;
                        if (!isset($used[$fullNo])) {
                            $next = $candidate;
                            break;
                        }
                    }
                    if ($next <= 0) throw new CommonException('当天客户群编号已达到上限，请联系管理员处理');
                    if (!$row) {
                        Db::name('project_center_daily_sequence')->insert([
                            'site_id' => $siteId, 'project_id' => 0, 'stat_date' => $date,
                            'current_seq' => $next, 'update_at' => time(),
                        ]);
                    } else {
                        Db::name('project_center_daily_sequence')->where($where)->update([
                            'current_seq' => $next, 'update_at' => time(),
                        ]);
                    }
                    return $next;
                });
            } catch (\Throwable $e) {
                if ($attempt === 2) throw $e;
                usleep(20000 * ($attempt + 1));
            }
        }
        throw new CommonException('群编号生成失败，请稍后重试');
    }
}
