<?php
declare(strict_types=1);
namespace addon\hsx_kingdee\app\adminapi\controller;

use addon\hsx_kingdee\app\service\ConfigService;
use addon\hsx_kingdee\app\service\PermissionService;
use addon\hsx_kingdee\app\service\TaskService;
use addon\hsx_kingdee\app\service\NoticeService;
use addon\hsx_kingdee\app\service\ConnectionProbeService;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\Db;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;

final class Workbench extends BaseAdminController
{
    private function guard(string $method, string $api): int
    {
        (new PermissionService())->assertApi($method, $api);
        return (int)$this->request->siteId();
    }

    public function config()
    {
        return success((new ConfigService())->get($this->guard('get', 'kingdee/config')));
    }

    public function staff()
    {
        $site = $this->guard('get', 'kingdee/staff');
        $ids = SysUserRole::where([['site_id', '=', $site], ['status', '=', 1]])->column('uid');
        return success($ids ? SysUser::whereIn('uid', $ids)->where('status', 1)->field('uid,username,real_name')->select()->toArray() : []);
    }

    public function logs()
    {
        $site = $this->guard('get', 'kingdee/logs');
        $input = $this->request->params([['page', 1], ['limit', 20]]);
        $query = Db::name('hsx_kingdee_log')->where('site_id', $site);
        return success(['total' => (clone $query)->count(), 'data' => $query->order('id desc')
            ->page(max(1, (int)$input['page']), min(100, max(1, (int)$input['limit'])))->select()->toArray()]);
    }

    public function saveConfig()
    {
        $site = $this->guard('post', 'kingdee/config');
        $input = $this->request->params([
            ['mode', 'disabled'], ['endpoint', ''], ['account_id', ''], ['username', ''], ['app_id', ''],
            ['app_secret', ''], ['clear_secret', false], ['organization', ''], ['assignee_uid', 0], ['revision', -1],
        ]);
        return $this->command(fn() => (new ConfigService())->save($site, (int)$this->request->uid(), $input));
    }

    public function probe()
    {
        $site = $this->guard('post', 'kingdee/connection/probe');
        $input = $this->request->params([['revision', -1]]);
        return $this->command(fn() => (new ConnectionProbeService())->run($site, (int)$this->request->uid(), (int)$input['revision']));
    }

    public function tasks()
    {
        $site = $this->guard('get', 'kingdee/tasks');
        $input = $this->request->params([['status', ''], ['page', 1], ['limit', 20], ['keyword', '']]);
        $query = Db::name('hsx_kingdee_task')->where('site_id', $site);
        if ($input['status'] !== '') $query->where('status', $input['status']);
        $keyword = trim((string)$input['keyword']);
        if ($keyword !== '') $query->where('business_no', 'like', '%' . addcslashes(mb_substr($keyword, 0, 100), '%_\\') . '%');
        $total = (clone $query)->count();
        $rows = $query->field('id,kind,business_no,status,reason,attempts,notice_status,create_at,update_at,external_no')
            ->order('id desc')->page(max(1, (int)$input['page']), max(1, min(100, (int)$input['limit'])))->select()->toArray();
        $counts = Db::name('hsx_kingdee_task')->where('site_id', $site)->field('status,COUNT(*) AS total')->group('status')->select()->toArray();
        return success(['data' => $rows, 'total' => $total, 'counts' => $counts]);
    }

    public function detail(int $id)
    {
        $site = $this->guard('get', 'kingdee/tasks/<id>');
        $row = Db::name('hsx_kingdee_task')->where([['site_id', '=', $site], ['id', '=', $id]])->find();
        if (!$row) throw new CommonException('任务不存在');
        $row['plan'] = json_decode($row['plan_json'], true) ?: [];
        // The immutable source snapshot is retained for audit, not exposed as a raw customer-data dump.
        unset($row['snapshot_json'], $row['plan_json'], $row['lease_token']);
        $row['logs'] = Db::name('hsx_kingdee_log')->where([['site_id', '=', $site], ['task_id', '=', $id]])->order('id desc')->limit(100)->select()->toArray();
        return success($row);
    }

    public function retry(int $id)
    {
        $site = $this->guard('post', 'kingdee/tasks/<id>/retry');
        return $this->command(function () use ($site, $id): array {
            $service = new TaskService();
            $service->retry($site, $id, (int)$this->request->uid());
            $service->process($site, $id);
            return ['message' => '已请求重新预检；未向金蝶写入'];
        });
    }

    public function resolve(int $id)
    {
        $site = $this->guard('post', 'kingdee/tasks/<id>/resolve');
        $input = $this->request->params([['note', ''], ['external_no', '']]);
        return $this->command(function () use ($site, $id, $input): array {
            (new TaskService())->resolve($site, $id, (int)$this->request->uid(), (string)$input['note'], (string)$input['external_no']);
            return [];
        });
    }

    public function notice(int $id)
    {
        $site = $this->guard('post', 'kingdee/tasks/<id>/notice');
        return $this->command(function () use ($site, $id): array {
            (new NoticeService())->request($site, $id, (int)$this->request->uid());
            return [];
        });
    }

    public function mappings()
    {
        $site = $this->guard('get', 'kingdee/mappings');
        $input = $this->request->params([['page', 1], ['limit', 20], ['kind', '']]);
        $config = (new ConfigService())->get($site);
        $query = Db::name('hsx_kingdee_mapping')->where([['site_id', '=', $site], ['connection_key', '=', $config['connection_key']]]);
        if ($input['kind'] !== '') $query->where('kind', $input['kind']);
        $total = (clone $query)->count();
        return success(['total' => $total, 'data' => $query->order('id desc')->page(max(1, (int)$input['page']), min(100, max(1, (int)$input['limit'])))->select()->toArray()]);
    }

    public function saveMapping()
    {
        $site = $this->guard('post', 'kingdee/mappings');
        $input = $this->request->params([['kind', ''], ['local_id', 0], ['external_number', ''], ['remark', '']]);
        return $this->command(function () use ($site, $input): array {
            $config = (new ConfigService())->get($site);
            if ($config['connection_key'] === '') throw new \RuntimeException('请先保存连接配置');
            $number = trim((string)$input['external_number']);
            if ($number === '' || mb_strlen($number) > 100 || preg_match('/[\x00-\x1F]/', $number)) throw new \RuntimeException('金蝶编码不能为空或包含控制字符，最多100字');
            $found = false;
            foreach (event('ErpMasterDataLookupRequested', ['site_id' => $site, 'kind' => $input['kind'], 'local_id' => (int)$input['local_id']]) as $result) {
                if (is_array($result) && ($result['provider'] ?? '') === 'hsx_erp' && !empty($result['found'])) $found = true;
            }
            if (!$found) throw new \RuntimeException('ERP 中未找到本站对应记录，请核对类型及本地ID');
            Db::transaction(function () use ($site, $input, $config, $number): void {
                // A stable parent lock serializes first mapping creation as well as replacements.
                Db::name('site')->where('site_id', $site)->lock(true)->find();
                $where = [['site_id', '=', $site], ['connection_key', '=', $config['connection_key']], ['kind', '=', $input['kind']], ['local_id', '=', (int)$input['local_id']]];
                $existing = Db::name('hsx_kingdee_mapping')->where($where)->find();
                $values = ['external_number' => $number, 'remark' => mb_substr((string)$input['remark'], 0, 255), 'operator_uid' => (int)$this->request->uid(), 'update_at' => time()];
                if ($existing) Db::name('hsx_kingdee_mapping')->where($where)->update($values);
                else Db::name('hsx_kingdee_mapping')->insert(array_merge($values, [
                    'site_id' => $site, 'connection_key' => $config['connection_key'], 'kind' => $input['kind'],
                    'local_id' => (int)$input['local_id'], 'create_at' => time(),
                ]));
                TaskService::audit($site, 0, 'mapping', 'saved', '更新映射 ' . $input['kind'] . ':' . (int)$input['local_id'] . '；金蝶编码尚未经远端验证', (int)$this->request->uid());
            });
            return [];
        });
    }

    private function command(callable $fn)
    {
        try {
            return success($fn());
        } catch (\RuntimeException $e) {
            // Only our explicit validation messages may be returned; DB failures can contain SQL.
            if (get_class($e) === \RuntimeException::class) throw new CommonException($e->getMessage());
            throw new CommonException('操作失败，请检查本地服务日志后重试');
        } catch (\Throwable $e) {
            throw new CommonException('操作失败，请检查本地服务日志后重试');
        }
    }
}
