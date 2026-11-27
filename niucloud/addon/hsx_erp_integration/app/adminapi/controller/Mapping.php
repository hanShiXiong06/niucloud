<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\adminapi\controller;

use addon\hsx_erp_integration\app\model\{ErpIntegrationConnection, ErpIntegrationMapping};
use addon\hsx_erp_integration\app\service\AdapterRegistry;
use think\facade\Db;

final class Mapping
{
    private function site(): int { return (int)request()->siteId(); }
    private function scoped() { return ErpIntegrationMapping::where('site_id', $this->site()); }

    public function lists()
    {
        $query = $this->scoped();
        if ($id = input('connection_id/d', 0)) $query->where('connection_id', $id);
        if ($type = input('resource_type/s', '')) $query->where('resource_type', $type);
        if ($key = trim(input('keyword/s', ''))) $query->whereLike('local_name|external_name|local_code|external_code', '%' . $key . '%');
        $count = (clone $query)->count();
        $list = $query->page(max(1, input('page/d', 1)), min(100, max(1, input('limit/d', 20))))->order('id desc')->select()->toArray();
        return success(['list' => $list, 'count' => $count]);
    }

    // ID 均来自当前站可选资料，不读取其他站点的客户或员工。
    private function resources(string $type): array
    {
        $site = $this->site();
        switch ($type) {
            case 'product':
                return [Db::name('erp_site_catalog_product')->where('site_id', $site)->where('is_enabled', 1),
                    'site_product_id', 'site_product_id', 'product_name'];
            case 'warehouse':
                return [Db::name('erp_warehouse')->where('site_id', $site)->where('status', 1), 'id', 'warehouse_code', 'warehouse_name'];
            case 'party':
                return [Db::name('erp_party')->where('site_id', $site)->where('status', 1), 'id', 'party_no', 'party_name'];
            case 'staff':
                return [Db::name('sys_user_role')->alias('r')->join('sys_user u', 'u.uid=r.uid')->where('r.site_id', $site)
                    ->where('r.status', 1)->where('u.status', 1)->where('u.is_del', 0), 'u.uid', 'u.username', 'u.real_name'];
            default: throw new \InvalidArgumentException('此类型暂不支持从本站档案选择');
        }
    }

    public function getLocalResources()
    {
        try { [$q, $id, $code, $name] = $this->resources(input('resource_type/s', '')); }
        catch (\InvalidArgumentException $e) { return fail($e->getMessage()); }
        if ($v = trim(input('keyword/s', ''))) $q->whereLike("$code|$name", '%' . $v . '%');
        $list = $q->field("$id as id,$code as code,$name as name")->order($id, 'desc')->limit(40)->select()->toArray();
        return success(['list' => $list]);
    }

    public function add() { return $this->save(0); }
    public function edit(int $id) { return $this->save($id); }

    private function save(int $id)
    {
        $row = $id ? $this->scoped()->where('id', $id)->find() : new ErpIntegrationMapping();
        if (!$row) return fail('映射不存在，请刷新');
        $connectionId = $id ? (int)$row->connection_id : input('connection_id/d', 0);
        $conn = ErpIntegrationConnection::where('site_id', $this->site())->where('id', $connectionId)->find();
        if (!$conn) return fail('连接不存在');
        $type = $id ? $row->resource_type : input('resource_type/s', '');
        $registry = AdapterRegistry::forSite($this->site());
        if (!$registry->hasAdapter($conn->adapter_code) || !array_key_exists($type, $registry->getAdapter($conn->adapter_code)->getMappingRequirements()))
            return fail('此连接不支持该资料类型');
        if (!$id) {
            try { [$q, $pk, $code, $name] = $this->resources($type); }
            catch (\InvalidArgumentException $e) { return fail($e->getMessage()); }
            $local = $q->where($pk, input('local_id/d', 0))->field("$pk as id,$code as code,$name as name")->find();
            if (!$local) return fail('请从本站资料中选择');
            if ($this->scoped()->where(['connection_id' => $connectionId, 'resource_type' => $type, 'local_id' => $local['id']])->count())
                return fail('这条本地资料已有映射，请编辑已有记录');
            $row->site_id = $this->site(); $row->connection_id = $connectionId; $row->resource_type = $type;
            $row->local_id = $local['id']; $row->local_code = $local['code']; $row->local_name = $local['name'] ?: $local['code'];
            $row->created_at = time();
        }
        $externalCode = trim(input('external_code/s', '')); $externalName = trim(input('external_name/s', '')); $externalId = trim(input('external_id/s', ''));
        if ($externalCode === '' || mb_strlen($externalCode) > 100 || mb_strlen($externalId) > 100 || mb_strlen($externalName) > 200)
            return fail('请填写有效的外部编码（不超过 100 字）并检查名称和 ID 长度');
        $row->external_code = $externalCode; $row->external_id = $externalId; $row->external_name = $externalName;
        $row->auto_matched = 0; $row->updated_at = time(); $row->save();
        return success();
    }

    public function del(int $id)
    {
        return $this->scoped()->where('id', $id)->delete() ? success() : fail('映射不存在');
    }
}
