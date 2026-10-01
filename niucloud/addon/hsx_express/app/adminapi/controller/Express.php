<?php
declare(strict_types=1);
namespace addon\hsx_express\app\adminapi\controller;

use addon\hsx_express\app\service\admin\ExpressAdminService;
use core\base\BaseAdminController;
use think\Response;

final class Express extends BaseAdminController
{
    public function config(): Response { return success((new ExpressAdminService())->config()); }
    public function save(): Response { return success((new ExpressAdminService())->save($this->request->put())); }
    public function check(): Response { return success((new ExpressAdminService())->check()); }
    public function sfConfig(string $scene): Response { return success((new ExpressAdminService())->sfConfig($scene)); }
    public function saveSfConfig(string $scene): Response { return success((new ExpressAdminService())->saveSfConfig($scene, $this->request->put())); }
    public function checkSfConfig(string $scene): Response { return success((new ExpressAdminService())->checkSfConfig($scene)); }
    public function tasks(): Response { return success((new ExpressAdminService())->tasks($this->request->get())); }
    public function detail(int $id): Response { return success((new ExpressAdminService())->detail($id)); }
    public function reprint(int $id): Response { return success((new ExpressAdminService())->operate($id, 'reprint', $this->request->post())); }
    public function cancel(int $id): Response { return success((new ExpressAdminService())->operate($id, 'cancel', $this->request->post())); }
    public function recover(int $id): Response { return success((new ExpressAdminService())->operate($id, 'recover', $this->request->post())); }
    public function refresh(int $id): Response { return success((new ExpressAdminService())->operate($id, 'refresh', $this->request->post())); }
    public function pdf(int $id): Response
    {
        $file = (new ExpressAdminService())->pdf($id);
        return response($file['body'], 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $file['filename'] . '"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
