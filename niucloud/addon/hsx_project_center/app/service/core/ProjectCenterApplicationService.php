<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterReviewLog;
use app\model\diy_form\DiyForm;
use app\model\diy_form\DiyFormRecords;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use app\service\core\diy_form\CoreDiyFormRecordsService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/** 资料工单写入服务。万能表单保存内容，本服务只保存流程事实。 */
final class ProjectCenterApplicationService
{
    /** 修订原记录前先校验，避免非法资料已写入后才在工单提交阶段失败。 */
    public function validateFormValue(int $siteId, int $formId, array $value): void
    {
        $this->normalizeAndValidateSnapshot($siteId, $formId, ['value' => $value]);
    }

    public function submit(
        int $siteId,
        int $memberId,
        int $projectId,
        string $groupNo,
        int $formRecordId,
        bool $paymentDeclared,
        array $eligibilityRegion = []
    ): int
    {
        if ($memberId <= 0) throw new CommonException('请先登录');
        if (!$paymentDeclared) throw new CommonException('请先确认已按项目说明完成付款，再填写资料');
        $project = ProjectCenterProject::where([
            ['site_id', '=', $siteId], ['id', '=', $projectId], ['status', '=', ProjectCenterDict::PROJECT_ENABLED],
        ])->findOrEmpty();
        if ($project->isEmpty()) throw new CommonException('项目不存在或暂未开放');
        $formId = (int)$project->form_id;
        if ($formId <= 0) throw new CommonException('项目资料表单尚未配置');

        $groupService = new ProjectCenterGroupService();
        $group = $groupService->requireAvailableByNo($siteId, $projectId, $groupNo, $memberId);
        $groupNo = (string)$group->group_no;
        $groupId = (int)$group->id;
        $idempotencyKey = $this->idempotencyKey((int)$project->id, $groupId, $memberId);

        // 地区查询必须在付款前完成；提交工单时再次按服务端白名单校验，不能信任前端“已通过”状态。
        // 已进入办理流程的历史工单继续沿用原查询快照，避免项目后来修改地区规则卡住客户修订。
        $areaService = new ProjectCenterAreaEligibilityService();
        $existingApplication = ProjectCenterApplication::where([
            ['site_id', '=', $siteId], ['idempotency_key', '=', $idempotencyKey],
        ])->findOrEmpty();
        $existingEligibility = $existingApplication->isEmpty() ? [] : (array)$existingApplication->eligibility_snapshot;
        if ($existingEligibility !== [] && !empty($existingEligibility['eligible'])) {
            $eligibilitySnapshot = $existingEligibility;
        } elseif (!$existingApplication->isEmpty() && (int)$existingApplication->payment_declared_at > 0) {
            $eligibilitySnapshot = $areaService->historicalSnapshot();
        } else {
            $eligibilitySnapshot = $areaService->assertEligible($project, $eligibilityRegion);
        }

        $record = DiyFormRecords::where([
            ['record_id', '=', $formRecordId], ['site_id', '=', $siteId],
            ['member_id', '=', $memberId], ['form_id', '=', $formId],
        ])->field('record_id,form_id,member_id,create_time')->findOrEmpty();
        if ($record->isEmpty()) throw new CommonException('提交资料不存在，请重新填写');

        $snapshot = (new CoreDiyFormRecordsService())->getInfo(['site_id' => $siteId, 'record_id' => $formRecordId]);
        $snapshot = $this->normalizeAndValidateSnapshot($siteId, $formId, is_array($snapshot) ? $snapshot : []);
        $reviewerUids = $this->activeReviewerUids($siteId, (array)$project->reviewer_uids);
        if ($reviewerUids === []) throw new CommonException('项目暂无在职资料审核员，请联系工作人员处理');
        try {
            $result = Db::transaction(function () use ($siteId, $memberId, $project, $groupService, $groupNo, $groupId, $formId, $formRecordId, $paymentDeclared, $snapshot, $eligibilitySnapshot, $idempotencyKey, $reviewerUids) {
                // 客户提交与管理员结束群可能同时发生。事务内重新锁定并核验群，
                // 防止使用事务外的旧模型把 completed/abandoned/dissolved 覆盖回 active。
                $lockedGroup = $groupService->requireAvailableByNo(
                    $siteId,
                    (int)$project->id,
                    $groupNo,
                    $memberId,
                    true
                );
                if ((int)$lockedGroup->id !== $groupId) {
                    throw new CommonException('客户群编号已发生变化，请刷新后重试');
                }
                $now = time();
                $this->promoteGroupForApplication($lockedGroup, $memberId, $now);

                $latest = ProjectCenterApplication::where([
                    ['site_id', '=', $siteId], ['idempotency_key', '=', $idempotencyKey],
                ])->lock(true)->findOrEmpty();

            if (!$latest->isEmpty() && in_array((string)$latest->status, [ProjectCenterDict::APPLICATION_SUBMITTED, ProjectCenterDict::APPLICATION_REVIEWING], true)) {
                if ((int)$latest->form_record_id === $formRecordId) {
                    return ['id' => (int)$latest->id, 'application_no' => (string)$latest->application_no, 'created' => false];
                }
                throw new CommonException('资料已提交审核，不能用另一份表单覆盖，请等待审核结果');
            }
            if (!$latest->isEmpty() && (string)$latest->status === ProjectCenterDict::APPLICATION_APPROVED) {
                throw new CommonException('资料已审核通过，无需重复提交');
            }
            if (!$latest->isEmpty() && (string)$latest->status === ProjectCenterDict::APPLICATION_REJECTED
                && (int)$latest->form_record_id !== $formRecordId) {
                throw new CommonException('请在原资料上按驳回意见修改，不要新建另一份表单');
            }
            if (!$latest->isEmpty() && (string)$latest->status === ProjectCenterDict::APPLICATION_ABANDONED) {
                throw new CommonException('本次办理已结束，如需重新参与请联系工作人员');
            }

            $fromStatus = $latest->isEmpty() ? '' : (string)$latest->status;
            $version = $latest->isEmpty() ? 1 : ((int)$latest->submit_version + 1);
            $assigneeUid = (int)($reviewerUids[0] ?? 0);
            $payload = [
                'form_record_id' => $formRecordId,
                'submit_version' => $version,
                'status' => ProjectCenterDict::APPLICATION_SUBMITTED,
                'assignee_uid' => $assigneeUid,
                'payment_declared_at' => $paymentDeclared ? $now : 0,
                'eligibility_snapshot' => $eligibilitySnapshot,
                'last_reject_summary' => '',
                'submitted_at' => $now,
                'reviewed_at' => 0,
                'update_at' => $now,
            ];
            if ($latest->isEmpty()) {
                $latest = ProjectCenterApplication::create(array_merge($payload, [
                    'site_id' => $siteId, 'application_no' => create_no('PC'),
                    'project_id' => (int)$project->id, 'group_id' => $groupId,
                    'member_id' => $memberId, 'idempotency_key' => $idempotencyKey,
                    'form_id' => $formId, 'create_at' => $now,
                ]));
            } else {
                $latest->save($payload);
            }
            ProjectCenterReviewLog::create([
                'site_id' => $siteId, 'application_id' => (int)$latest->id, 'submit_version' => $version,
                'action' => $version > 1 ? 'resubmit' : 'submit', 'from_status' => $fromStatus,
                'to_status' => ProjectCenterDict::APPLICATION_SUBMITTED, 'field_issues_json' => [],
                'form_snapshot_json' => is_array($snapshot) ? $snapshot : [],
                'remark' => $paymentDeclared ? '客户声明已完成线下付款，待群内流水核对' : '客户提交资料',
                'operator_type' => 'member', 'operator_id' => $memberId, 'operator_name' => '', 'create_at' => $now,
            ]);
            return [
                'id' => (int)$latest->id,
                'application_no' => (string)$latest->application_no,
                'created' => true,
                'submit_version' => $version,
                'reviewer_uids' => $reviewerUids,
            ];
            });
        } catch (\Throwable $e) {
            // 两个客户请求可能同时通过无记录检查，唯一键保证只创建一张工单。
            // 唯一键冲突后回读已落库记录，保持重放语义，不依赖具体数据库错误码。
            $raceWinner = ProjectCenterApplication::where([
                ['site_id', '=', $siteId], ['idempotency_key', '=', $idempotencyKey],
            ])->findOrEmpty();
            if (!$raceWinner->isEmpty()
                && in_array((string)$raceWinner->status, [ProjectCenterDict::APPLICATION_SUBMITTED, ProjectCenterDict::APPLICATION_REVIEWING], true)) {
                if ((int)$raceWinner->form_record_id === $formRecordId) {
                    // 并发唯一键或数据库死锁的胜出记录已经存在时，仍需在独立事务
                    // 中安全修复历史 create_failed 群，避免重放成功但群状态未自愈。
                    $this->promoteGroupOnReplay($siteId, $memberId, $projectId, $groupNo, $groupId);
                    return (int)$raceWinner->id;
                }
                throw new CommonException('资料已提交审核，不能用另一份表单覆盖，请等待审核结果');
            }
            Log::warning('[hsx_project_center] 客户资料提交失败', [
                'site_id' => $siteId,
                'project_id' => $projectId,
                'member_id' => $memberId,
                'form_record_id' => $formRecordId,
                'group_no' => $groupNo,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (!empty($result['created'])) {
            foreach ((array)($result['reviewer_uids'] ?? []) as $reviewerUid) {
                $this->publishTask(
                    $siteId,
                    (int)$result['id'],
                    (int)$reviewerUid,
                    (string)$group->group_no,
                    (string)($result['application_no'] ?? ''),
                    (int)($result['submit_version'] ?? 1)
                );
            }
        }
        return (int)$result['id'];
    }

    private function promoteGroupForApplication(ProjectCenterGroup $group, int $memberId, int $now): void
    {
        $update = [];
        if ((int)$group->member_id === 0) $update['member_id'] = $memberId;
        if (in_array((string)$group->status, ['reserved', 'create_failed', 'created'], true)) {
            $update['status'] = 'active';
            $update['created_at'] = (int)$group->created_at ?: $now;
        }
        if ((string)$group->status === 'create_failed') {
            // 企微失败后使用普通微信群是正常兜底路径，不再保留技术失败标识。
            $update['create_mode'] = 'manual';
            $update['error_message'] = '';
        }
        if ($update === []) return;
        $update['update_at'] = $now;
        $group->save($update);
    }

    private function promoteGroupOnReplay(
        int $siteId,
        int $memberId,
        int $projectId,
        string $groupNo,
        int $expectedGroupId
    ): void {
        Db::transaction(function () use ($siteId, $memberId, $projectId, $groupNo, $expectedGroupId) {
            $group = (new ProjectCenterGroupService())->requireAvailableByNo(
                $siteId,
                $projectId,
                $groupNo,
                $memberId,
                true
            );
            if ((int)$group->id !== $expectedGroupId) {
                throw new CommonException('客户群编号已发生变化，请刷新后重试');
            }
            $this->promoteGroupForApplication($group, $memberId, time());
        });
    }

    private function idempotencyKey(int $projectId, int $groupId, int $memberId): string
    {
        // direct 键仅用于兼容历史数据；所有新提交都必须先解析到 groupId > 0。
        return $groupId > 0
            ? 'group:' . $projectId . ':' . $groupId
            : 'direct:' . $projectId . ':' . $memberId;
    }

    private function activeReviewerUids(int $siteId, array $configuredUids): array
    {
        $configuredUids = array_values(array_unique(array_filter(array_map('intval', $configuredUids))));
        if ($configuredUids === []) return [];
        $siteUids = array_values(array_unique(array_map('intval', SysUserRole::where([
            ['site_id', '=', $siteId], ['status', '=', 1], ['uid', 'in', $configuredUids],
        ])->column('uid'))));
        if ($siteUids === []) return [];
        $activeUids = array_values(array_unique(array_map('intval', SysUser::where([
            ['status', '=', 1], ['uid', 'in', $siteUids],
        ])->column('uid'))));
        return array_values(array_filter($configuredUids, static fn(int $uid): bool => in_array($uid, $activeUids, true)));
    }

    private function publishTask(int $siteId, int $applicationId, int $assigneeUid, string $groupNo, string $applicationNo, int $submitVersion): void
    {
        try {
            event('HsxBusinessTaskAssigned', [
                'event_id' => 'project-center-application-' . $siteId . '-' . $applicationId . '-v' . $submitVersion . '-' . $assigneeUid,
                'event_name' => 'task.assigned.v1', 'site_id' => $siteId,
                'source_plugin' => 'hsx_project_center', 'source_type' => 'project_application',
                'source_id' => $applicationId, 'stage_key' => 'project_material_review',
                'assignee_uid' => $assigneeUid,
                'title' => $groupNo !== ''
                    ? '项目资料待审核：群编号 ' . $groupNo
                    : '项目资料待审核：' . ($applicationNo !== '' ? $applicationNo : ('#' . $applicationId)) . '（直达项目申请）',
                'target' => [
                    'plugin' => 'hsx_project_center', 'route_key' => 'hsx_project_center.application',
                    'params' => ['application_id' => $applicationId],
                    'web_path' => 'site/hsx_project_center/application?application_id=' . $applicationId,
                ],
                'target_path' => 'site/hsx_project_center/application?application_id=' . $applicationId,
                'occurred_at' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[hsx_project_center] 资料审核任务通知失败', ['application_id' => $applicationId, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 以正式万能表单为规则来源，以主记录 value 为本次提交事实。
     *
     * 框架的子表字段在“修订后全部清空”时可能保留旧行，因此不能直接把
     * recordsFieldList 当成最新资料。这里同时重建审核/导出快照，保证必填校验、
     * 审核工作台和 ZIP 交付包看到的都是同一版数据，且全程不修改牛云核心。
     */
    private function normalizeAndValidateSnapshot(int $siteId, int $formId, array $snapshot): array
    {
        $form = DiyForm::where([
            ['site_id', '=', $siteId],
            ['form_id', '=', $formId],
        ])->field('form_id,value')->findOrEmpty();
        if ($form->isEmpty()) throw new CommonException('项目资料表单不存在，请联系管理员重新配置');

        $formValue = $form->value;
        if (is_string($formValue)) {
            $decoded = json_decode($formValue, true);
            $formValue = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        $formComponents = is_array($formValue) && isset($formValue['value'])
            ? (array)$formValue['value']
            : (array)$formValue;

        $officialComponents = [];
        foreach ($formComponents as $component) {
            if (!is_array($component) || (string)($component['componentType'] ?? '') !== 'diy_form') continue;
            if ((string)($component['componentName'] ?? '') === 'FormSubmit') continue;
            if (!empty($component['isHidden'])) continue;
            $fieldKey = (string)($component['id'] ?? '');
            if ($fieldKey !== '') $officialComponents[$fieldKey] = $component;
        }

        $oldRecords = [];
        foreach ((array)($snapshot['recordsFieldList'] ?? $snapshot['records_field_list'] ?? []) as $key => $field) {
            if ($field instanceof \think\Model) $field = $field->toArray();
            if (!is_array($field)) continue;
            $fieldKey = (string)($field['field_key'] ?? $key);
            if ($fieldKey !== '') $oldRecords[$fieldKey] = $field;
        }

        $submittedValue = $snapshot['value'] ?? [];
        if (is_string($submittedValue)) {
            $decoded = json_decode($submittedValue, true);
            $submittedValue = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (is_array($submittedValue) && isset($submittedValue['value']) && is_array($submittedValue['value'])) {
            $submittedValue = $submittedValue['value'];
        }
        $submittedComponents = [];
        foreach ((array)$submittedValue as $component) {
            if (!is_array($component)) continue;
            $fieldKey = (string)($component['id'] ?? '');
            if ($fieldKey !== '') $submittedComponents[$fieldKey] = $component;
        }

        $canonicalRecords = [];
        foreach ($officialComponents as $fieldKey => $component) {
            $name = trim((string)($component['field']['name'] ?? $component['componentTitle'] ?? '资料项')) ?: '资料项';
            $required = !empty($component['field']['required']);
            $submitted = $submittedComponents[$fieldKey] ?? null;
            if (!is_array($submitted)) {
                if ($required) throw new CommonException($name . '不能为空');
                continue;
            }
            if ((string)($submitted['componentName'] ?? '') !== (string)($component['componentName'] ?? '')) {
                throw new CommonException($name . '组件类型不匹配，请刷新页面后重新填写');
            }

            $value = $submitted['field']['value'] ?? null;
            if ($this->isBlankValue($value)) {
                if ($required) throw new CommonException($name . '不能为空');
                continue;
            }
            if ((string)($component['componentName'] ?? '') === 'ProjectFormLocation') {
                $this->assertProjectLocationValue(['field_value' => $value, 'field_name' => $name], $component);
            }

            $record = $oldRecords[$fieldKey] ?? [];
            unset($record['handle_field_value'], $record['render_value'], $record['detailComponent']);
            $record['field_key'] = $fieldKey;
            $record['field_type'] = (string)($component['componentName'] ?? '');
            $record['field_name'] = $name;
            $record['field_value'] = $value;
            $record['field_required'] = $required ? 1 : 0;
            $record['field_hidden'] = 0;
            $record['field_unique'] = !empty($component['field']['unique']) ? 1 : 0;
            $record['privacy_protection'] = !empty($component['field']['privacyProtection']) ? 1 : 0;
            $canonicalRecords[$fieldKey] = $record;
        }

        $snapshot['recordsFieldList'] = $canonicalRecords;
        unset($snapshot['records_field_list']);
        return $snapshot;
    }

    private function isBlankValue($value): bool
    {
        if ($value === null || $value === false) return true;
        if (is_string($value)) return trim($value) === '';
        if (!is_array($value)) return false;
        if ($value === []) return true;
        foreach ($value as $item) {
            if (!$this->isBlankValue($item)) return false;
        }
        return true;
    }

    private function assertProjectLocationValue(array $field, array $component): void
    {
        $name = trim((string)($component['field']['name'] ?? $field['field_name'] ?? '门店定位')) ?: '门店定位';
        $value = $field['handle_field_value'] ?? $field['field_value'] ?? null;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        if (!is_array($value) || array_is_list($value)) throw new CommonException($name . '数据格式不正确，请重新定位');

        $latitude = $value['latitude'] ?? null;
        $longitude = $value['longitude'] ?? null;
        if (!is_numeric($latitude) || !is_numeric($longitude)) throw new CommonException($name . '缺少有效坐标，请重新定位');
        $latitude = (float)$latitude;
        $longitude = (float)$longitude;
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new CommonException($name . '坐标超出有效范围，请重新定位');
        }

        $source = (string)($value['source'] ?? '');
        if (!in_array($source, ['wechat_js_sdk', 'native_gps', 'manual_map'], true)) {
            throw new CommonException($name . '来源无法识别，请重新定位');
        }
        $mode = (string)($component['mode'] ?? 'both');
        if (!in_array($mode, ['both', 'current_only', 'map_only'], true)) $mode = 'both';
        if ($mode === 'current_only' && $source === 'manual_map') {
            throw new CommonException($name . '必须使用当前位置，请重新定位');
        }
        if ($mode === 'map_only' && $source !== 'manual_map') {
            throw new CommonException($name . '必须通过地图选择，请重新定位');
        }
        if ((string)($value['coordinate_type'] ?? '') !== 'gcj02') {
            throw new CommonException($name . '坐标类型不正确，请重新定位');
        }

        $capturedAt = (int)($value['captured_at'] ?? 0);
        $now = time();
        if ($capturedAt <= 0 || $capturedAt > $now + 300) throw new CommonException($name . '采集时间无效，请重新定位');

        if (!empty($component['requireAddress']) && trim((string)($value['full_address'] ?? '')) === '') {
            throw new CommonException($name . '未获取到详细地址，请改用地图选择');
        }

        $maxAgeMinutes = max(0, (int)($component['maxAgeMinutes'] ?? 0));
        if ($maxAgeMinutes > 0 && $now - $capturedAt > $maxAgeMinutes * 60) {
            throw new CommonException($name . '已超过允许的定位时效，请重新定位');
        }

        $maxAccuracy = max(0, (float)($component['maxAccuracyMeters'] ?? 0));
        if ($source !== 'manual_map' && $maxAccuracy > 0) {
            $accuracy = $value['accuracy'] ?? null;
            if (!is_numeric($accuracy) || (float)$accuracy < 0 || (float)$accuracy > $maxAccuracy) {
                throw new CommonException($name . '精度不符合要求，请移至开阔位置重新定位');
            }
        }
    }
}
