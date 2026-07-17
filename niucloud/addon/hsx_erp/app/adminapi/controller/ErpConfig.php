<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\service\admin\ErpConfigService;
use addon\hsx_erp\app\service\admin\ErpListingTaskService;
use core\base\BaseAdminController;

class ErpConfig extends BaseAdminController
{
    public function dicts()
    {
        return success(ErpDict::lists());
    }

    public function info()
    {
        return success((new ErpConfigService())->getRules());
    }

    public function save()
    {
        $params = $this->request->params([
            ['finance', []],
            ['purchase', []],
            ['product_title', []],
            ['sale', []],
            ['refurbish', []],
            ['turnover', []],
            ['consignment', []],
        ]);
        return success((new ErpConfigService())->saveRules($params));
    }

    public function taskAssignmentSettings()
    {
        return success((new ErpListingTaskService())->assignmentSettings());
    }

    public function saveTaskAssignmentSettings()
    {
        $params = $this->request->params([['defaults', []]]);
        return success((new ErpListingTaskService())->saveDefaultAssignees((array)$params['defaults']));
    }

    public function dismissRefurbishReminder()
    {
        $params = $this->request->params([['mode', 'today']]);
        $mode = (string)$params['mode'] === 'forever' ? 'forever' : 'today';
        return success((new ErpConfigService())->dismissRefurbishReminder($mode));
    }

    public function dismissTurnoverReminder()
    {
        $params = $this->request->params([['mode', 'today']]);
        $mode = (string)$params['mode'] === 'forever' ? 'forever' : 'today';
        return success((new ErpConfigService())->dismissTurnoverReminder($mode));
    }

    public function saleChannels()
    {
        return success((new ErpConfigService())->getSaleChannels());
    }

    public function saveSaleChannels()
    {
        $params = $this->request->params([
            ['channels', []],
        ]);
        return success((new ErpConfigService())->saveSaleChannels((array)$params['channels']));
    }

    public function saleChannelOptions()
    {
        return success((new ErpConfigService())->getSaleChannelOptions());
    }

    public function saveSaleChannelOptions()
    {
        $params = $this->request->params([['channels', []]]);
        return success((new ErpConfigService())->saveSaleChannelOptions((array)$params['channels']));
    }

    public function financeCategories()
    {
        return success((new ErpConfigService())->getFinanceCategories());
    }

    public function businessSourceOptions()
    {
        return success((new ErpConfigService())->getBusinessSourceOptions());
    }

    public function saveFinanceCategories()
    {
        $params = $this->request->params([['categories', []]]);
        return success((new ErpConfigService())->saveFinanceCategories((array)$params['categories']));
    }
}
