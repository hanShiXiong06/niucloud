<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\adminapi\controller;

use addon\hsx_marketing\app\service\admin\MarketingCampaignAdminService;
use addon\hsx_marketing\app\service\admin\MarketingParticipationAdminService;
use core\base\BaseAdminController;
use core\exception\CommonException;

final class Campaign extends BaseAdminController
{
    public function lists() { return success((new MarketingCampaignAdminService())->page($this->request->params([['status', ''], ['keyword', ''], ['page', 1], ['limit', 15]]))); }
    public function info(int $id)
    {
        // 沿用「活动详情」只读权限；不新增需同步数据库的菜单或绕开角色校验。
        $section = (string)$this->request->param('section', '');
        if ($section === '') return success((new MarketingCampaignAdminService())->info($id));
        $service = new MarketingParticipationAdminService();
        if ($section === 'participants') return success($service->page($id, $this->request->params([
            ['keyword', ''], ['status', ''], ['reward_status', ''], ['time_type', 'create_at'],
            ['start_date', ''], ['end_date', ''], ['page', 1], ['limit', 15],
        ])));
        if ($section === 'participant') return success($service->detail($id, (int)$this->request->param('claim_id', 0), $this->request->params([
            ['fact_page', 1], ['fact_limit', 15], ['reward_page', 1], ['reward_limit', 15],
        ])));
        throw new CommonException('不支持的活动详情类型');
    }
    public function metadata() { return success((new MarketingCampaignAdminService())->metadata()); }
    public function providerOptions() { return success((new MarketingCampaignAdminService())->providerOptions((string)$this->request->param('provider_key', ''), (string)$this->request->param('reward_type', ''))); }
    public function add() { return success('添加成功', ['id' => (new MarketingCampaignAdminService())->save($this->campaignParams())]); }
    public function edit(int $id) { return success('保存成功', ['id' => (new MarketingCampaignAdminService())->save($this->campaignParams(), $id)]); }
    public function delete(int $id) { return success('删除成功', (new MarketingCampaignAdminService())->delete($id)); }
    public function status(int $id) { return success('状态已更新', (new MarketingCampaignAdminService())->changeStatus($id, (int)$this->request->param('status', 0))); }

    /**
     * 只接收活动表单允许写入的字段。
     */
    private function campaignParams(): array
    {
        return $this->request->params([
            ['title', ''],
            ['subtitle', ''],
            ['status', 0],
            ['participation_mode', 'manual'],
            ['cycle_type', 'calendar_month'],
            ['cycle_days', 30],
            ['start_at', ''],
            ['end_at', ''],
            ['allowed_level_ids', []],
            ['qualification_key', ''],
            ['application_url', ''],
            ['fact_key', ''],
            ['fact_filter_json', []],
            ['target_value', 0],
            ['grant_mode', 'manual'],
            ['claim_valid_days', 7],
            ['expire_notice_days', [7, 3, 1]],
            ['notice_channels', ['weapp', 'wechat', 'sms']],
            ['rewards', []],
            ['description', ''],
            ['sort', 0],
        ]);
    }
}
