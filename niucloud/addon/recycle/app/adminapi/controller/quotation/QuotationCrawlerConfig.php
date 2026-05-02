<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\quotation;

use addon\recycle\app\service\admin\quotation\QuotationCrawlerConfigService;
use core\base\BaseAdminController;

/**
 * 报价爬虫配置
 */
class QuotationCrawlerConfig extends BaseAdminController
{
    public function getConfig()
    {
        return success((new QuotationCrawlerConfigService())->getConfig());
    }

    public function setConfig()
    {
        $config = (new QuotationCrawlerConfigService())->saveConfig($this->request->post());
        return success('SAVE_SUCCESS', $config);
    }

    public function getDefaultConfig()
    {
        return success((new QuotationCrawlerConfigService())->getDefaultConfig());
    }
}
