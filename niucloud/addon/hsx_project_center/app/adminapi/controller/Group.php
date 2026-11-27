<?php
declare(strict_types=1);
namespace addon\hsx_project_center\app\adminapi\controller;
use addon\hsx_project_center\app\service\admin\ProjectCenterGroupAdminService;
use addon\hsx_project_center\app\service\admin\ProjectCenterWecomAdminService;
use addon\hsx_project_center\app\service\admin\ProjectCenterRefundAdminService;
use core\base\BaseAdminController;
final class Group extends BaseAdminController
{
    public function lists() { return success((new ProjectCenterGroupAdminService())->page($this->request->params([['project_id', 0], ['status', ''], ['keyword', ''], ['page', 1], ['limit', 15]]))); }
    public function reserve() { return success('群编号已登记', (new ProjectCenterGroupAdminService())->reserve($this->request->params([
        ['project_id', 0], ['member_id', 0], ['member_name', ''], ['member_mobile', ''], ['store_name', ''],
        ['owner_uid', 0], ['collaborator_uids', []], ['wecom_external_userid', ''], ['group_no', ''],
    ]))); }
    public function correctGroupNo(int $id) { return success('群编号已更正', (new ProjectCenterGroupAdminService())->correctGroupNo($id, $this->request->params([
        ['group_no', ''], ['expected_group_no_full', ''], ['reason', ''],
    ]))); }
    public function bindWecom(int $id) { return success('群信息已保存', (new ProjectCenterGroupAdminService())->bindWecom($id, $this->request->params([
        ['wecom_chat_id', ''], ['create_mode', 'manual'],
    ]))); }
    public function wecomReadiness() { return success((new ProjectCenterWecomAdminService())->readiness()); }
    public function wecomOauthUrl() {
        $params = $this->request->params([['redirect_uri', '']]);
        return success((new ProjectCenterWecomAdminService())->oauthUrl((string)$params['redirect_uri']));
    }
    public function bindWecomIdentity() {
        $params = $this->request->params([['code', ''], ['state', '']]);
        return success('企业微信员工身份绑定成功', (new ProjectCenterWecomAdminService())->bindCurrent((string)$params['code'], (string)$params['state']));
    }
    public function wecomSelectorConfig() {
        $params = $this->request->params([['url', '']]);
        return success((new ProjectCenterWecomAdminService())->selectorConfig((string)$params['url']));
    }
    public function prepareWecom(int $id) {
        $params = $this->request->params([['url', '']]);
        return success((new ProjectCenterWecomAdminService())->prepare($id, (string)$params['url']));
    }
    public function completeWecom(int $id) {
        return success('企业微信群创建并绑定成功', (new ProjectCenterWecomAdminService())->complete($id, $this->request->params([
            ['wecom_chat_id', ''], ['wecom_external_userid', ''],
        ])));
    }
    public function failWecom(int $id) {
        $params = $this->request->params([['error_message', '']]);
        (new ProjectCenterWecomAdminService())->failed($id, (string)$params['error_message']);
        return success('建群失败原因已记录');
    }
    public function close(int $id) {
        $params = $this->request->params([['status', 'abandoned'], ['reason', ''], ['confirmed_unpaid', 0]]);
        (new ProjectCenterGroupAdminService())->close($id, (string)$params['status'], (string)$params['reason'], (bool)$params['confirmed_unpaid']);
        return success('群流程已结束');
    }
    public function refundInfo(int $id) { return success((new ProjectCenterRefundAdminService())->infoByGroup($id)); }
    public function requestRefund(int $id) {
        return success('已进入待退款流程', (new ProjectCenterRefundAdminService())->request($id, $this->request->params([
            ['amount', 0], ['reason', ''],
        ])));
    }
    public function completeRefund(int $id) {
        return success('退款已完成并关闭本次办理', (new ProjectCenterRefundAdminService())->complete($id, $this->request->params([
            ['proof', ''], ['remark', ''],
        ])));
    }
    public function cancelRefund(int $id) {
        $params = $this->request->params([['reason', '']]);
        return success('退款流程已取消', (new ProjectCenterRefundAdminService())->cancel($id, (string)$params['reason']));
    }
}
