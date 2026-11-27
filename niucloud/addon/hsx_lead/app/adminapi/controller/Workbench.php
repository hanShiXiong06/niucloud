<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;
use addon\hsx_lead\app\service\{Context,MerchantService,ReadService,SyncService,CatalogService,ConnectionService,AuthChallengeService,AutomaticAuthService,CaptchaConfigService,NoticeConfigService,ProviderChannelService};
use addon\hsx_lead\app\support\Data;
use core\base\BaseAdminController;

final class Workbench extends BaseAdminController
{
    private function context(): Context
    {
        return new Context((int) $this->request->siteId(), (int) $this->request->uid());
    }
    public function members()
    {
        return success((new MerchantService($this->context()))->members($this->request->param()));
    }
    public function providers()
    {
        $ctx = $this->context();
        $ctx->manager();
        return success(['items' => (new ProviderChannelService($ctx->site))->available()]);
    }
    public function connection(int $id)
    {
        return success((new ConnectionService($this->context()))->get($id));
    }
    public function authorizationChallenge(int $id)
    {
        return success((new AuthChallengeService($this->context()))->begin($id, $this->request->param()));
    }
    public function automaticAuthorization(int $id)
    {
        return success((new AutomaticAuthService($this->context()))->start($id, $this->request->param()));
    }
    public function advanceAutomaticAuthorization(int $id)
    {
        return success((new AutomaticAuthService($this->context()))->advance($id, Data::secret($this->request->param(), 'task_token', 64)));
    }
    public function cancelAutomaticAuthorization(int $id)
    {
        return success((new AutomaticAuthService($this->context()))->cancel($id, Data::secret($this->request->param(), 'task_token', 64)));
    }
    public function captchaConfig()
    {
        return success((new CaptchaConfigService($this->context()))->get());
    }
    public function saveCaptchaConfig()
    {
        return success((new CaptchaConfigService($this->context()))->save($this->request->param()));
    }
    public function captchaBalance()
    {
        return success((new CaptchaConfigService($this->context()))->balance());
    }
    public function probeConnection(int $id)
    {
        return success((new ConnectionService($this->context()))->probe($id, $this->request->param()));
    }
    public function activateConnection(int $id)
    {
        return success((new ConnectionService($this->context()))->activate($id, $this->request->param()));
    }
    public function connectionSettings(int $id)
    {
        return success((new ConnectionService($this->context()))->syncSettings($id, $this->request->param()));
    }
    public function noticeConfig()
    {
        $ctx = $this->context();
        $ctx->manager();
        $input = $this->request->param();
        if (($input['library'] ?? null) === '1' || ($input['library'] ?? null) === 1) return success((new \addon\hsx_lead\app\service\WeappNoticeLibrary())->read($ctx->site, $input));
        return success((new NoticeConfigService())->get($ctx->site));
    }
    public function saveNoticeConfig()
    {
        $ctx = $this->context();
        $ctx->manager();
        return success((new NoticeConfigService())->save($ctx->site, $this->request->param(), $ctx->uid));
    }
    public function catalog()
    {
        return success((new CatalogService($this->context()))->get($this->request->param()));
    }
    public function saveCatalog()
    {
        return success((new CatalogService($this->context()))->save($this->request->param()));
    }
    public function merchants()
    {
        return success((new MerchantService($this->context()))->lists($this->request->param()));
    }
    public function merchant(int $id)
    {
        return success((new MerchantService($this->context()))->detail($id));
    }
    public function addMerchant()
    {
        return success((new MerchantService($this->context()))->save(0, $this->request->param()));
    }
    public function editMerchant(int $id)
    {
        return success((new MerchantService($this->context()))->save($id, $this->request->param()));
    }
    public function inventory()
    {
        return success((new ReadService($this->context()))->inventory($this->request->param()));
    }
    public function sync(int $id)
    {
        $ctx = $this->context();
        $run = (new SyncService())->start($ctx->site, $id, $ctx->uid);
        return success((new SyncService())->advance($ctx->site, (int) $run['run_id']));
    }
    public function syncInfo(int $id)
    {
        return success((new SyncService())->detail($this->context()->site, $id));
    }
    public function advanceSync(int $id)
    {
        return success((new SyncService())->advance($this->context()->site, $id));
    }
}
