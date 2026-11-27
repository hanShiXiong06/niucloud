<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\api\controller;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\{RegionSearchService,StorefrontService};
use core\base\BaseApiController;

final class Region extends BaseApiController
{
    public function search()
    {
        $context=new Context((int)$this->request->siteId(),0,(int)$this->request->memberId());
        (new StorefrontService($context))->assertUploadAllowed();
        return success((new RegionSearchService())->search($this->request->param()));
    }
}
