<?php
declare(strict_types=1);
namespace addon\hsx_project_center\app\api\controller;
use addon\hsx_project_center\app\service\api\ProjectCenterPortalService;
use core\base\BaseApiController;
final class Portal extends BaseApiController
{
    public function project(int $id) { return success((new ProjectCenterPortalService())->project($id)); }
    public function checkGroup(int $id) { return success((new ProjectCenterPortalService())->checkGroup($id, (string)$this->request->param('group_no', ''))); }
    public function resolveGroup(int $id) { return success('群编号已确认', (new ProjectCenterPortalService())->resolveGroup($id, (string)$this->request->param('group_no', ''))); }
    public function submit(int $id) { $params = $this->request->params([['group_no', ''], ['form_record_id', 0], ['payment_declared', 0]]); return success('资料已提交', ['application_id' => (new ProjectCenterPortalService())->submit($id, (string)$params['group_no'], (int)$params['form_record_id'], !empty($params['payment_declared']))]); }
    public function revise(int $id) { $params = $this->request->params([['group_no', ''], ['value', []], ['payment_declared', 0]]); return success('资料已重新提交', ['application_id' => (new ProjectCenterPortalService())->revise($id, (string)$params['group_no'], (array)$params['value'], !empty($params['payment_declared']))]); }
    public function status(int $id) { return success((new ProjectCenterPortalService())->status($id, (string)$this->request->param('group_no', ''))); }
}
