<?php
declare(strict_types=1);
namespace addon\hsx_project_center\app\api\controller;
use addon\hsx_project_center\app\service\api\ProjectCenterPortalService;
use addon\hsx_project_center\app\service\api\ProjectCenterDistributionPortalService;
use addon\hsx_project_center\app\service\api\ProjectCenterDistributionPosterService;
use core\base\BaseApiController;
final class Portal extends BaseApiController
{
    public function project(int $id) { return success((new ProjectCenterPortalService())->project($id)); }
    public function areaEligibility(int $id) { return success((new ProjectCenterPortalService())->areaEligibility($id, $this->request->params([['province_id', 0], ['city_id', 0], ['district_id', 0]]))); }
    public function checkGroup(int $id) { return success((new ProjectCenterPortalService())->checkGroup($id, (string)$this->request->param('group_no', ''))); }
    public function resolveGroup(int $id) { return success('群编号已确认', (new ProjectCenterPortalService())->resolveGroup($id, (string)$this->request->param('group_no', ''))); }
    public function submit(int $id) { $params = $this->request->params([['group_no', ''], ['form_record_id', 0], ['payment_declared', 0], ['eligibility_region', []]]); return success('资料已提交', ['application_id' => (new ProjectCenterPortalService())->submit($id, (string)$params['group_no'], (int)$params['form_record_id'], !empty($params['payment_declared']), (array)$params['eligibility_region'])]); }
    public function revise(int $id) { $params = $this->request->params([['group_no', ''], ['value', []], ['payment_declared', 0], ['eligibility_region', []]]); return success('资料已重新提交', ['application_id' => (new ProjectCenterPortalService())->revise($id, (string)$params['group_no'], (array)$params['value'], !empty($params['payment_declared']), (array)$params['eligibility_region'])]); }
    public function status(int $id) { return success((new ProjectCenterPortalService())->status($id, (string)$this->request->param('group_no', ''))); }
    public function distributionInvite(int $id) { return success((new ProjectCenterDistributionPortalService())->invite($id)); }
    public function distributionBind(int $id) { return success('邀请关系已处理', (new ProjectCenterDistributionPortalService())->bind($id, (string)$this->request->param('token', ''))); }
    public function distributionOverview() { return success((new ProjectCenterDistributionPortalService())->overview((int)$this->request->param('project_id', 0))); }
    public function distributionDetails() { return success((new ProjectCenterDistributionPortalService())->details($this->request->params([['project_id', 0], ['status', ''], ['page', 1], ['limit', 15]]))); }
    public function distributionPoster(int $id) { $params = $this->request->params([['invite', ''], ['poster_id', 0]]); return success((new ProjectCenterDistributionPosterService())->generate($id, (string)$params['invite'], (int)$params['poster_id'])); }
}
