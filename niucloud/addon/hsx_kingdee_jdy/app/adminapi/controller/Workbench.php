<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\adminapi\controller;

use addon\hsx_kingdee_jdy\app\service\ConfigService;
use addon\hsx_kingdee_jdy\app\service\ConnectionService;
use addon\hsx_kingdee_jdy\app\service\TaskService;
use addon\hsx_kingdee_jdy\app\service\NoticeService;
use addon\hsx_kingdee_jdy\app\service\PermissionService;
use addon\hsx_kingdee_jdy\app\support\Problem;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\Db;

final class Workbench extends BaseAdminController
{
    private function guard(string $method, string $path): int
    {
        (new PermissionService())->assertApi($method, 'kingdee_jdy/' . $path);
        return (int)$this->request->siteId();
    }

    private function command(callable $fn)
    {
        try { return success($fn()); }
        catch (Problem $e) { throw new CommonException($e->getMessage()); }
        catch (\Throwable $e) {
            $trace = array_values(array_filter($e->getTrace(), static fn(array $frame): bool => str_contains($frame['file'] ?? '', '/hsx_kingdee_jdy/')));
            $driverCode = $e instanceof \think\db\exception\PDOException ? ($e->getData()['PDO Error Info']['Driver Error Code'] ?? 0) : 0;
            \think\facade\Log::error('hsx_kingdee_jdy command failed; site=' . (int)$this->request->siteId()
                . '; type=' . get_class($e) . '; driver_code=' . (int)$driverCode
                . '; at=' . basename($e->getFile()) . ':' . $e->getLine()
                . '; caller=' . basename($trace[0]['file'] ?? '') . ':' . ($trace[0]['line'] ?? 0));
            throw new CommonException('精斗云操作未完成，请检查本地服务与插件表结构');
        }
    }

    public function config()
    {
        $site = $this->guard('get', 'config');
        return $this->command(fn() => (new ConfigService())->get($site));
    }

    public function saveConfig()
    {
        $site = $this->guard('post', 'config');
        $input = $this->request->params([['revision', -1], ['client_id', ''], ['client_secret', ''],
            ['app_key', ''], ['app_secret', ''], ['account_id', ''], ['domain', ''], ['mode', 'disabled'], ['assignee_uid', 0]]);
        return $this->command(fn() => (new ConfigService())->save($site, (int)$this->request->uid(), $input));
    }

    public function staff()
    {
        $site = $this->guard('get', 'staff');
        $ids = SysUserRole::where('site_id', $site)->where('status', 1)->column('uid');
        return success($ids ? SysUser::whereIn('uid', $ids)->where('status', 1)->field('uid,username,real_name')->select()->toArray() : []);
    }

    public function probe()
    {
        $site = $this->guard('post', 'connection/probe');
        $input = $this->request->params([['revision', -1]]);
        return $this->command(fn() => (new ConnectionService())->probe($site, (int)$this->request->uid(), (int)$input['revision']));
    }

    public function tasks()
    {
        $site = $this->guard('get', 'tasks');
        $input = $this->request->params([['status', ''], ['keyword', ''], ['page', 1], ['limit', 20]]);
        $query = Db::name('hsx_kingdee_jdy_task')->where('site_id', $site);
        if ($input['status'] !== '') $query->where('status', $input['status']);
        if (trim((string)$input['keyword']) !== '') $query->where('business_no', 'like', '%' . addcslashes(mb_substr(trim($input['keyword']), 0, 100), '%_\\') . '%');
        return success(['total' => (clone $query)->count(), 'data' => $query->field('id,kind,business_no,status,reason,attempts,notice_status,create_at,update_at,external_no')
            ->order('id desc')->page(max(1, (int)$input['page']), min(100, max(1, (int)$input['limit'])))->select()->toArray(),
            'counts' => Db::name('hsx_kingdee_jdy_task')->where('site_id', $site)->field('status,COUNT(*) AS total')->group('status')->select()->toArray()]);
    }

    public function detail(int $id)
    {
        $site = $this->guard('get', 'tasks/<id>');
        $row = Db::name('hsx_kingdee_jdy_task')->where('site_id', $site)->where('id', $id)->find();
        if (!$row) throw new CommonException('任务不存在');
        $row['plan'] = json_decode($row['plan_json'], true) ?: [];
        // Never expose raw event snapshots or serial-bearing payloads in the ordinary workbench.
        unset($row['snapshot_json'], $row['plan_json'], $row['lease_token'], $row['plan']['draft']);
        $row['logs'] = Db::name('hsx_kingdee_jdy_log')->where('site_id', $site)->where('task_id', $id)->order('id desc')->limit(100)->select()->toArray();
        return success($row);
    }

    public function retry(int $id)
    {
        $site = $this->guard('post', 'tasks/<id>/retry');
        return $this->command(function () use ($site, $id): array {
            $service = new TaskService();
            $service->retry($site, $id, (int)$this->request->uid());
            $service->process($site, $id);
            return ['message' => '已重新预检，未向账套写单'];
        });
    }

    public function resolve(int $id)
    {
        $site = $this->guard('post', 'tasks/<id>/resolve');
        $input = $this->request->params([['note', ''], ['external_no', '']]);
        return $this->command(function () use ($site, $id, $input): array {
            (new TaskService())->resolve($site, $id, (int)$this->request->uid(), (string)$input['note'], (string)$input['external_no']);
            return [];
        });
    }

    public function notice(int $id)
    {
        $site = $this->guard('post', 'tasks/<id>/notice');
        return $this->command(function () use ($site, $id): array {
            (new NoticeService())->request($site, $id, (int)$this->request->uid());
            return [];
        });
    }

    public function mappings()
    {
        $site = $this->guard('get', 'mappings');
        $input = $this->request->params([['page', 1], ['limit', 20], ['kind', '']]);
        $config = (new ConfigService())->get($site);
        $query = Db::name('hsx_kingdee_jdy_mapping')->where('site_id', $site)->where('connection_key', $config['connection_key']);
        if ($input['kind'] !== '') $query->where('kind', $input['kind']);
        return success(['total' => (clone $query)->count(), 'data' => $query->order('id desc')
            ->page(max(1, (int)$input['page']), min(100, max(1, (int)$input['limit'])))->select()->toArray()]);
    }

    public function saveMapping()
    {
        $site = $this->guard('post', 'mappings');
        $input = $this->request->params([['kind', ''], ['local_id', 0], ['external_number', ''], ['unit_id', ''], ['remark', '']]);
        return $this->command(function () use ($site, $input): array {
            if (!in_array($input['kind'], ['supplier', 'customer', 'asset_material', 'product', 'warehouse'], true)
                || (int)$input['local_id'] <= 0) throw new Problem('MAPPING_INVALID', '请选择资料类型和有效ERP记录ID');
            $number = trim((string)$input['external_number']);
            $unit = trim((string)$input['unit_id']);
            if ($number === '' || mb_strlen($number) > 100 || strlen($unit) > 64 || preg_match('/[\x00-\x1F]/', $number . $unit)) throw new Problem('MAPPING_INVALID', '请填写有效精斗云编码和单位ID');
            if (in_array($input['kind'], ['asset_material', 'product'], true) && $unit === '') throw new Problem('UNIT_REQUIRED', '商品映射需要精斗云计量单位ID');
            $found = false;
            foreach (event('ErpMasterDataLookupRequested', ['site_id' => $site, 'kind' => $input['kind'], 'local_id' => (int)$input['local_id']]) as $result) {
                if (is_array($result) && ($result['provider'] ?? '') === 'hsx_erp' && !empty($result['found'])) $found = true;
            }
            if (!$found) throw new Problem('LOCAL_RECORD_MISSING', 'ERP中未找到本站对应记录');
            Db::transaction(function () use ($site, $input, $number, $unit): void {
                Db::name('site')->where('site_id', $site)->lock(true)->find();
                $config = (new ConfigService())->get($site);
                if ($config['account_id'] === '' || $config['connection_key'] === '') throw new Problem('ACCOUNT_REQUIRED', '请先保存精斗云账套ID');
                $where = [['site_id', '=', $site], ['connection_key', '=', $config['connection_key']], ['kind', '=', $input['kind']], ['local_id', '=', (int)$input['local_id']]];
                $existing = Db::name('hsx_kingdee_jdy_mapping')->where($where)->find();
                $values = ['external_number' => $number, 'unit_id' => $unit, 'remark' => mb_substr((string)$input['remark'], 0, 255),
                    'operator_uid' => (int)$this->request->uid(), 'update_at' => time()];
                if ($existing) Db::name('hsx_kingdee_jdy_mapping')->where($where)->update($values);
                else Db::name('hsx_kingdee_jdy_mapping')->insert(array_merge($values, ['site_id' => $site, 'connection_key' => $config['connection_key'],
                    'kind' => $input['kind'], 'local_id' => (int)$input['local_id'], 'create_at' => time()]));
                TaskService::audit($site, 0, 'mapping', 'saved', '映射已保存，需重新预检相关任务；外部编码尚未远端验证', (int)$this->request->uid());
            });
            return [];
        });
    }

    public function logs()
    {
        $site = $this->guard('get', 'logs');
        $input = $this->request->params([['page', 1], ['limit', 20]]);
        $query = Db::name('hsx_kingdee_jdy_log')->where('site_id', $site);
        return success(['total' => (clone $query)->count(), 'data' => $query->order('id desc')
            ->page(max(1, (int)$input['page']), min(100, max(1, (int)$input['limit'])))->select()->toArray()]);
    }
}
