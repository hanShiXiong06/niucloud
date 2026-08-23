<?php
declare(strict_types=1);
namespace addon\hsx_project_center\app\adminapi\controller;
use addon\hsx_project_center\app\service\admin\ProjectCenterApplicationAdminService;
use addon\hsx_project_center\app\service\admin\ProjectCenterProjectAdminService;
use core\base\BaseAdminController;
final class Application extends BaseAdminController
{
    public function lists() { return success((new ProjectCenterApplicationAdminService())->page($this->request->params([
        ['project_id', 0], ['status', ''], ['assignee_uid', 0], ['keyword', ''], ['page', 1], ['limit', 15],
    ]))); }
    public function info(int $id) { return success((new ProjectCenterApplicationAdminService())->info($id)); }
    public function reviewReasons() {
        $params = $this->request->params([['project_id', 0]]);
        return success((new ProjectCenterProjectAdminService())->reviewReasons((int)$params['project_id']));
    }
    public function saveReviewReasons() {
        $params = $this->request->params([['project_id', 0], ['reasons', []]]);
        return success('常用问题已保存', (new ProjectCenterProjectAdminService())->saveReviewReasons((int)$params['project_id'], (array)$params['reasons']));
    }
    public function review(int $id) {
        $params = $this->request->params([['action', ''], ['field_issues', []], ['remark', '']]);
        (new ProjectCenterApplicationAdminService())->review($id, (string)$params['action'], (array)$params['field_issues'], (string)$params['remark']);
        return success('审核结果已保存');
    }
    public function correctGroupNo(int $id) {
        return success('群编号已更正', (new ProjectCenterApplicationAdminService())->correctGroupNo($id, $this->request->params([
            ['group_no', ''], ['expected_group_no_full', ''], ['reason', ''],
        ])));
    }
    public function archive(int $id) {
        $file = (new ProjectCenterApplicationAdminService())->archive($id);
        return download((string)$file['path'], (string)$file['name']);
    }
}
