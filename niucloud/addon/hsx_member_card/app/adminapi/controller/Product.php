<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\adminapi\controller;
use addon\hsx_member_card\app\service\admin\MemberCardProductService;
use core\base\BaseAdminController;
final class Product extends BaseAdminController
{
    public function lists() { return success((new MemberCardProductService())->lists($this->request->params([['keyword', ''], ['status', ''], ['page', 1], ['limit', 15]]))); }
    public function options() { return success((new MemberCardProductService())->options()); }
    public function info(int $id) { return success((new MemberCardProductService())->info($id)); }
    public function save(int $id = 0) { return success(['id' => (new MemberCardProductService())->save($this->request->params([['product_name', ''], ['cover_url', ''], ['sale_price', ''], ['market_price', 0], ['effective_mode', 'immediate'], ['validity_mode', 'permanent'], ['duration_value', 0], ['duration_unit', 'day'], ['fixed_start_at', 0], ['fixed_end_at', 0], ['usage_notice', ''], ['sort', 0], ['item', []]]), $id)]); }
    public function enable(int $id) { return success((new MemberCardProductService())->setStatus($id, 'enabled')); }
    public function disable(int $id) { return success((new MemberCardProductService())->setStatus($id, 'disabled')); }
    public function delete(int $id) { return success((new MemberCardProductService())->delete($id)); }
}
