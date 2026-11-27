<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;

use addon\hsx_lead\app\service\{CatalogService,Context};
use core\base\BaseAdminController;

/** Item maintenance is separate from the bulk-file replacement endpoint. */
final class Catalog extends BaseAdminController
{
    private function service(): CatalogService
    {
        return new CatalogService(new Context((int) $this->request->siteId(), (int) $this->request->uid()));
    }
    public function items()
    {
        return success($this->service()->items($this->request->param()));
    }
    public function add()
    {
        return success($this->service()->add($this->request->param()));
    }
    public function update(string $id)
    {
        return success($this->service()->update($id, $this->request->param()));
    }
    public function remove(string $id)
    {
        return success($this->service()->remove($id, $this->request->param()));
    }
    public function logs()
    {
        return success($this->service()->logs($this->request->param()));
    }
}
