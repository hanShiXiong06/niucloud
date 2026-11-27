<?php
declare(strict_types=1);
namespace addon\hsx_project_center\app\adminapi\controller;
use addon\hsx_project_center\app\service\admin\ProjectCenterProjectAdminService;
use core\base\BaseAdminController;
final class Project extends BaseAdminController
{
    public function lists() { return success((new ProjectCenterProjectAdminService())->page($this->request->params([['status', ''], ['keyword', ''], ['page', 1], ['limit', 15]]))); }
    public function metadata() { return success((new ProjectCenterProjectAdminService())->metadata()); }
    public function info(int $id) { return success((new ProjectCenterProjectAdminService())->info($id)); }
    public function add() { return success('添加成功', ['id' => (new ProjectCenterProjectAdminService())->save($this->params())]); }
    public function edit(int $id) { return success('保存成功', ['id' => (new ProjectCenterProjectAdminService())->save($this->params(), $id)]); }
    public function delete(int $id) { return success('删除成功', (new ProjectCenterProjectAdminService())->delete($id)); }
    private function params(): array { return $this->request->params([
        ['title', ''], ['subtitle', ''], ['cover', ''], ['status', 0], ['sort', 0],
        ['intro_page_id', 0], ['form_id', 0], ['payment_qrcode', ''], ['payment_amount', 0],
        ['payment_tips', ''], ['reviewer_uids', []], ['ai_enabled', 0], ['ai_scene', ''],
        ['distribution_enabled', 0], ['config_json', []],
    ]); }
}
