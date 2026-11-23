<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\adminapi\controller;

use addon\hsx_erp_integration\app\model\{ErpIntegrationConnection as ConnectionModel, ErpIntegrationFailure, ErpIntegrationMapping, ErpIntegrationSyncTask};
use addon\hsx_erp_integration\app\service\AdapterRegistry;
use addon\hsx_erp_integration\app\contract\ManualDocumentAdapterInterface;
use addon\hsx_erp_integration\app\support\BridgeTarget;
use addon\hsx_erp_integration\app\support\ConnectionFields;
use addon\hsx_erp_integration\app\support\DisjointManualRoutes;
use addon\hsx_erp_integration\app\support\ManualRouteScope;
use InvalidArgumentException;

final class Connection
{
    private function site(): int { return (int)request()->siteId(); }
    private function registry(): AdapterRegistry { return AdapterRegistry::forSite($this->site()); }
    private function row(int $id) { return ConnectionModel::where('site_id', $this->site())->where('id', $id)->find(); }

    public function lists()
    {
        $query = ConnectionModel::where('site_id', $this->site());
        foreach (['adapter_code', 'status'] as $key) if ($v = input($key . '/s', '')) $query->where($key, $v);
        if ($v = trim(input('keyword/s', ''))) $query->whereLike('adapter_name', '%' . $v . '%');
        $page = max(1, input('page/d', 1)); $limit = min(100, max(1, input('limit/d', 20)));
        $count = (clone $query)->count();
        $rows=[];
        foreach ($query->page($page,$limit)->order('id desc')->select() as $model) {
            $cfg=ConnectionFields::values($model->config); $row=$model->hidden(['config'])->toArray();
            $row['manual_scope_label']=!empty($cfg['manual_scope'])?(string)($cfg['manual_scope_label']??'原限定范围'):'';
            $row['business_types'] = ConnectionFields::values($row['business_types']);
            $rows[]=$row;
        }
        return success(['list' => $rows, 'count' => $count]);
    }

    public function options()
    {
        $rows = ConnectionModel::where('site_id', $this->site())->field('id,adapter_code,adapter_name,status')->order('adapter_name,id')->select()->toArray();
        $registry = $this->registry();
        foreach ($rows as &$row) $row['resource_types'] = $registry->hasAdapter($row['adapter_code'])
            ? array_values(array_intersect(['product', 'warehouse', 'party', 'staff'], array_keys($registry->getAdapter($row['adapter_code'])->getMappingRequirements()))) : [];
        return success(['list' => $rows]);
    }

    public function getAdapters()
    {
        $registry=$this->registry(); $list=$registry->getAdapterList();
        foreach ($list as &$item) {
            $driver=$registry->getAdapter($item['code']);
            if (!$driver instanceof ManualDocumentAdapterInterface) continue;
            try {
                $profile=$driver->originalProfile($this->site());
                $item['manual_business_types']=$profile['manual_scope_types']??$driver->manualBusinessTypes((string)$profile['mode']);
                $item['manual_target']=['mode'=>$profile['mode'],'account_code'=>$profile['account_code']];
                $item['manual_scope_options']=ManualRouteScope::options($profile);
                if(!empty($profile['manual_scope_hint'])) $item['manual_setup_hint']=$profile['manual_scope_hint'];
            } catch (\Throwable $e) {
                $item['manual_setup_hint']='请先在厂商连接页配置本站账套，并确认有查看连接的权限。';
            }
        }
        unset($item);
        return success(['list' => $list]);
    }

    public function info(int $id)
    {
        $row = $this->row($id);
        if (!$row) return fail('连接不存在');
        $registry = $this->registry();
        if (!$registry->hasAdapter($row->adapter_code)) return fail('本站未启用对应适配器，仍可在列表查看或暂停连接');
        $fields = $registry->getAdapter($row->adapter_code)->getConfigFields();
        $info = $row->hidden(['config'])->toArray();
        $info['business_types'] = ConnectionFields::values($row->business_types);
        $cfg=ConnectionFields::values($row->config);
        return success($info + ['manual_scope'=>(string)($cfg['manual_scope']??''),'manual_scope_label'=>(string)($cfg['manual_scope_label']??'')] + ConnectionFields::present($fields,$cfg));
    }

    public function add() { return $this->save(0); }
    public function edit(int $id) { return $this->save($id); }

    private function save(int $id)
    {
        $row = $id ? $this->row($id) : new ConnectionModel();
        if (!$row) return fail('连接不存在');
        $name = trim(input('adapter_name/s', ''));
        if ($name === '' || mb_strlen($name) > 100) return fail('请输入 1–100 字的连接名称');
        $adapter = $id ? $row->adapter_code : input('adapter_code/s', '');
        $registry = $this->registry();
        if (!$registry->hasAdapter($adapter)) return fail('本站尚未启用此适配器');
        $driver = $registry->getAdapter($adapter);
        $manual = ($id ? $row->sync_mode : input('sync_mode/s','async')) === 'manual';
        if ($manual && !$driver instanceof ManualDocumentAdapterInterface) return fail('此适配器未提供原单人工对接');
        $business = input('business_types/a', []);
        if (!$business || array_filter($business, fn($v) => !is_string($v) || !in_array($v, $driver->getSupportedBusinessTypes(), true)))
            return fail('请至少选择一项适配器支持的同步业务');
        $fields = $driver->getConfigFields(); $site = $this->site();
        try {
            $prev = $id ? ConnectionFields::open(ConnectionFields::values($row->config), $site, $adapter) : [];
            if ($manual) {
                $profile=$driver->originalProfile($site);
                $new=array_intersect_key($profile,array_flip(['mode','api_url','gateway_path','app_id','account_code']));
                $scope=ManualRouteScope::resolve($profile,$id?(string)($prev['manual_scope']??''):null,input('manual_scope',null),$business);
                if ($scope!=='') {
                    $new['manual_scope']=$scope;
                    // Adding a display-only label to an old connection must not require a retest or pause.
                    if(!$id || isset($prev['manual_scope_label'])) $new['manual_scope_label']=ManualRouteScope::label($profile,$scope,(string)($prev['manual_scope_label']??''));
                }
                if(array_diff($business,$driver->manualBusinessTypes((string)($new['mode']??'')))) return fail('当前目标环境尚未开放所选业务，请重新核对要关联的单据');
                $identity=BridgeTarget::key(['site_id'=>$site,'adapter_code'=>$adapter,'config'=>$new]);
                if ($id && $identity!==BridgeTarget::key($row->getData()) && (ErpIntegrationSyncTask::where('site_id',$site)->where('connection_id',$id)->count()
                    || ErpIntegrationMapping::where('site_id',$site)->where('connection_id',$id)->count()))
                    return fail('这条连接已有映射或原单，请保留旧账套，并为新账套新建连接');
            } else {
                $new = ConnectionFields::merge($fields, input('config/a', []), $prev);
            }
            $changed = !$id || $new != $prev || array_values($business) != ConnectionFields::values($row->business_types);
            $row->config = ConnectionFields::seal($new, $fields, $site, $adapter);
        } catch (InvalidArgumentException $e) { return fail($e->getMessage()); }
        catch (\Throwable $e) {
            if (!$manual) throw $e;
            \think\facade\Log::warning('ERP人工连接无法读取厂商配置',['site_id'=>$site,'type'=>get_class($e)]);
            return fail('请先在本站厂商连接页配置并核验账套，同时确认有查看厂商连接的权限');
        }
        $row->adapter_name = $name;
        $row->business_types = array_values(array_unique($business));
        if ($changed) {
            $row->status = 'disabled';
            $row->last_test_at = null; $row->last_test_result = null;
        }
        if (!$id) {
            $row->site_id = $site; $row->adapter_code = $adapter;
            $row->sync_mode = $manual ? 'manual':'async'; $row->retry_times = 0; $row->created_at = time();
        }
        $row->updated_at = time();
        $row->save();
        return success('保存成功', ['id' => $row->id, 'status' => $row->status]);
    }

    public function changeStatus(int $id)
    {
        $status = input('status/s', '');
        if (!in_array($status, ['enabled','disabled'], true)) return fail('请选择启用或暂停');
        return \think\facade\Db::transaction(function() use($id,$status) {
        // Serialize competing enables in stable order; legacy-only routing remains unchanged.
        $connections=ConnectionModel::where('site_id',$this->site())->order('id')->lock(true)->select();
        $row=null;
        foreach ($connections as $item) if ((int)$item->id===$id) $row=$item;
        if (!$row) return fail('连接不存在');
        if ($row->status===$status) return success();
        $query = ConnectionModel::where('id', $id)->where('site_id', $this->site());
        if ($status === 'enabled') {
            if (!$row || !$this->registry()->hasAdapter($row->adapter_code)) return fail('未找到可用适配器');
            $query->where('last_test_result', 'success');
            $bound=ConnectionFields::values($row->config);
            if($row->sync_mode==='manual') {
                $driver=$this->registry()->getAdapter($row->adapter_code);
                $profile=$driver instanceof ManualDocumentAdapterInterface ? $driver->originalProfile($this->site()):[];
                try { ManualRouteScope::resolve($profile,(string)($bound['manual_scope']??''),null,ConnectionFields::values($row->business_types)); }
                catch(InvalidArgumentException $e){return fail($e->getMessage());}
                if(($profile['checked']??false)!==true
                    || ($profile['app_id']??null)!==($bound['app_id']??null)
                    || BridgeTarget::key($row->getData())!==BridgeTarget::key(['site_id'=>$this->site(),'adapter_code'=>$row->adapter_code,'config'=>$profile]))
                    return fail('此试单授权或账套已变化，未启用连接；请核对原目标');
            }
            $overlap=[];
            foreach($connections as $other) if((int)$other->id!==$id && $other->status==='enabled' && ($row->sync_mode==='manual' || $other->sync_mode==='manual')
                && array_intersect(ConnectionFields::values($other->business_types),ConnectionFields::values($row->business_types))) $overlap[]=$other;
            if($overlap && (count($overlap)!==1 || !DisjointManualRoutes::permits($this->site(),$row->getData(),$overlap[0]->getData(),$driver??null)))
                return fail('已有连接在处理同类单据；仅厂商明确区分的两个限定范围能同时启用。原连接保持不变');
        }
        $changes=['status' => $status, 'updated_at' => time()];
        if ($row->sync_mode==='manual') $changes['bridge_started_at']=$status==='enabled'? time():0;
        if (!$query->where('status',$row->status)->update($changes)) return fail('状态已变化，或尚未测试通过，请刷新后核对');
        return success();
        });
    }

    public function testConnection(int $id)
    {
        $row = $this->row($id);
        if (!$row) return fail('连接不存在');
        $registry = $this->registry();
        if (!$registry->hasAdapter($row->adapter_code)) return fail('本站未启用对应适配器');
        try {
            $config = ConnectionFields::open(ConnectionFields::values($row->config), $this->site(), $row->adapter_code);
            $driver=$registry->getAdapter($row->adapter_code);
            if ($row->sync_mode==='manual') {
                $profile=$driver instanceof ManualDocumentAdapterInterface ? $driver->originalProfile($this->site()):[];
                ManualRouteScope::resolve($profile,(string)($config['manual_scope']??''),null,ConnectionFields::values($row->business_types));
                $ok=($profile['checked']??false)===true && BridgeTarget::key($row->getData())===BridgeTarget::key(['site_id'=>$this->site(),'adapter_code'=>$row->adapter_code,'config'=>$profile])
                    && ($config['app_id']??'')===($profile['app_id']??null);
                $result=['success'=>$ok];
            } else $result = $driver->testConnection($config + ['connection_id' => $id]);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => '连接测试未完成，请核对账号、地址和适配器'];
        }
        $ok = ($result['success'] ?? false) === true;
        // 只更新实际测试的凭据版本，不能把旧测试结果写给刚换过的账号。
        $query = ConnectionModel::where('site_id', $this->site())->where('id', $id)->where('config', $row->getData('config'));
        $changes = ['last_test_at' => time(), 'last_test_result' => $ok ? 'success' : 'failed'];
        if (!$ok) $changes['status'] = 'disabled';
        $query->update($changes);
        $current = $this->row($id);
        if (!$current || $current->getData('config') !== $row->getData('config')) return fail('配置已变更，请重新测试');
        return $ok ? success('连接测试通过') : fail($row->sync_mode==='manual'?'当前账套与原连接不一致，或原连接尚未验证。请先在厂商连接页核对账号并完成只读测试。':'测试未通过，请核对地址、账套、授权和适配器的测试接口');
    }

    public function del(int $id)
    {
        $row = $this->row($id);
        if (!$row) return fail('连接不存在');
        if ($row->status !== 'disabled') return fail('请先暂停连接');
        foreach ([ErpIntegrationMapping::class, ErpIntegrationSyncTask::class, ErpIntegrationFailure::class] as $model) {
            if ($model::where('site_id', $this->site())->where('connection_id', $id)->count())
                return fail('连接已有资料映射或历史记录，请保留为暂停状态');
        }
        $row->delete();
        return success();
    }
}
