<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;
use addon\hsx_lead\app\service\{Context,ProviderChannelService};
use addon\hsx_lead\app\service\cooperation\{ConfigService,CooperationService,PartnerService,ReviewService,PaymentService,Store,UploadService,BusinessLicenseService,BusinessService};
use core\base\BaseAdminController;

final class Cooperation extends BaseAdminController
{
    private function context(): Context
    {
        return new Context((int) $this->request->siteId(), (int) $this->request->uid());
    }
    public function config()
    {
        $ctx = $this->context();
        Store::permission($ctx, 'config');
        $section = $this->request->param('section');
        if ($section === 'payment_help')return success(\addon\hsx_lead\app\service\cooperation\PaymentHelpService::get($ctx, true))->header(['Cache-Control' => 'private, no-store']);
        return success($section === 'erp_channels' ? (new ProviderChannelService($ctx->site))->configuration() : $this->businessConfiguration((new ConfigService($ctx))->get()));
    }
    public function saveConfig()
    {
        $ctx = $this->context();
        $input = $this->request->param();
        if (($input['section'] ?? '') === 'payment_help') {
            unset($input['section']);
            return success(\addon\hsx_lead\app\service\cooperation\PaymentHelpService::save($ctx, $input))->header(['Cache-Control' => 'private, no-store']);
        }
        return success(($input['section'] ?? '') === 'erp_channels' ? (new ProviderChannelService($ctx->site))->save($ctx, $input) : $this->businessConfiguration((new ConfigService($ctx))->save($input)));
    }
    public function profileForm()
    {
        return success((new \addon\hsx_lead\app\service\cooperation\ProfileFormService($this->context()))->admin())->header(['Cache-Control' => 'private, no-store']);
    }
    public function saveProfileForm()
    {
        return success((new \addon\hsx_lead\app\service\cooperation\ProfileFormService($this->context()))->save($this->request->param()))->header(['Cache-Control' => 'private, no-store']);
    }
    public function profileFormRecord(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\ProfileFormService($this->context()))->adminRecord($id))->header(['Cache-Control' => 'private, no-store']);
    }
    /** Available presets belong to the editor response, never the site's persisted rules. */
    private function businessConfiguration(array $config): array
    {
        return array_replace($config, ['business_presets' => BusinessService::defaults()]);
    }
    public function dashboard()
    {
        return success((new CooperationService($this->context()))->dashboard());
    }
    public function scope()
    {
        $ctx = $this->context();
        $ctx->manager();
        return success(Store::scope($ctx));
    }
    public function owners()
    {
        return success((new CooperationService($this->context()))->owners($this->request->param()));
    }
    public function leads()
    {
        return success((new CooperationService($this->context()))->lists($this->request->param()));
    }
    public function detail(int $id)
    {
        return success((new CooperationService($this->context()))->detail($id));
    }
    public function notices(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\CooperationNoticeReadService($this->context()))->lists($id, $this->request->param()))->header(['Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache']);
    }
    public function retryNotice(int $id, int $notice_id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\CooperationNoticeReadService($this->context()))->retry($id, $notice_id, $this->request->param()))->header(['Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache']);
    }
    public function transferOwner(int $id)
    {
        return success((new CooperationService($this->context()))->transferOwner($id, $this->request->param()));
    }
    public function partners()
    {
        return success((new PartnerService($this->context()))->lists($this->request->param()));
    }
    public function blockPartner(int $id)
    {
        return success((new PartnerService($this->context()))->block($id, $this->request->param()));
    }
    public function businessLicensePreview(int $id)
    {
        return success((new BusinessLicenseService($this->context()))->adminPreview($id))->header(['Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache']);
    }
    public function orders()
    {
        return success((new PaymentService($this->context()))->adminOrders($this->request->param()));
    }
    public function manualReceiptPreview(int $id)
    {
        $input = $this->request->param();
        unset($input['id']);
        return success((new \addon\hsx_lead\app\service\cooperation\ManualPaymentService($this->context()))->preview($id, $input))->header(['Cache-Control' => 'private, no-store']);
    }
    public function confirmManualReceipt(int $id)
    {
        $input = $this->request->param();
        unset($input['id']);
        return success((new \addon\hsx_lead\app\service\cooperation\ManualPaymentService($this->context()))->confirm($id, $input))->header(['Cache-Control' => 'private, no-store']);
    }
    public function manualReceipt(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\ManualPaymentService($this->context()))->read($id))->header(['Cache-Control' => 'private, no-store']);
    }
    public function retryManualReceipt(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\ManualPaymentService($this->context()))->retry($id))->header(['Cache-Control' => 'private, no-store']);
    }
    public function confirmManualRefund(int $id)
    {
        $input = $this->request->param();
        unset($input['id']);
        return success((new \addon\hsx_lead\app\service\cooperation\ManualRefundService($this->context()))->confirm($id, $input))->header(['Cache-Control' => 'private, no-store']);
    }
    public function orderOptions()
    {
        $ctx = $this->context();
        Store::permission($ctx, 'finance');
        return success(\addon\hsx_lead\app\service\cooperation\OrderPaymentReadService::options());
    }
    public function orderRefund(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->detail($id, true));
    }
    public function requestOrderRefund(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->apply($id, $this->request->param(), true));
    }
    public function reviewOrderRefund(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->review($id, $this->request->param()));
    }
    public function refreshOrderRefund(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->refresh($id, true));
    }
    public function continueOrderRefund(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\PaymentRefundService($this->context()))->continue($id, $this->request->param()));
    }
    public function closeOrder(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\PaymentOrderLifecycle($this->context()))->close($id, $this->request->param(), true));
    }
    public function checkOrder(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\PaymentOrderCheckService($this->context()))->check($id, true))->header(['Cache-Control' => 'private, no-store']);
    }
    public function repriceOrder(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\OrderPricingService($this->context()))->reprice($id, $this->request->param()));
    }
    public function orderPricingLogs(int $id)
    {
        return success((new \addon\hsx_lead\app\service\cooperation\OrderPricingService($this->context()))->logs($id, $this->request->param()));
    }
    public function reviews()
    {
        return success((new ReviewService($this->context()))->lists($this->request->param()));
    }
    public function createReview()
    {
        return success((new ReviewService($this->context()))->create($this->request->param()));
    }
    public function resolve(int $id)
    {
        return success((new ReviewService($this->context()))->resolve($id, $this->request->param()));
    }
    public function attachment()
    {
        return success((new UploadService($this->context()))->image());
    }
}
