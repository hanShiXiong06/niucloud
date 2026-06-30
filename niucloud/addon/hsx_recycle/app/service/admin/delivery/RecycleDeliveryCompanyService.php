<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\delivery;

use addon\hsx_recycle\app\dict\delivery\ExpressCompanyDict;
use addon\hsx_recycle\app\model\delivery\RecycleDeliveryCompany;
use addon\hsx_recycle\app\model\delivery\RecycleDeliveryCompanyProvider;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 快递公司管理（方案A：公司为逻辑实体，服务商编码/面单能力存于绑定表）
 * @package addon\hsx_recycle\app\service\admin\delivery
 */
class RecycleDeliveryCompanyService extends BaseAdminService
{
    private RecycleDeliveryCompanyProvider $bindingModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeliveryCompany();
        $this->bindingModel = new RecycleDeliveryCompanyProvider();
    }

    /**
     * 分页列表（附带各公司的服务商绑定）
     */
    public function getPage(array $where = []): array
    {
        $field = 'company_id,company_name,logo,url,express_code,sort,status,create_at';
        $search = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['company_name', 'status'], $where)
            ->field($field)
            ->order('sort asc, company_id desc');
        $result = $this->pageQuery($search);

        $ids = array_column($result['data'] ?? [], 'company_id');
        if (!empty($ids)) {
            $bindings = $this->bindingModel->where([
                ['site_id', '=', $this->site_id],
                ['company_id', 'in', $ids],
            ])->select()->toArray();
            $map = [];
            foreach ($bindings as $b) {
                $map[(int)$b['company_id']][] = $b;
            }
            foreach ($result['data'] as &$row) {
                $row['bindings'] = $map[(int)$row['company_id']] ?? [];
            }
            unset($row);
        }
        return $result;
    }

    /**
     * 全量公司列表（搜索下拉用，简单返回 id+名称）
     */
    public function getList(array $where = []): array
    {
        return $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['status'], $where)
            ->field('company_id,company_name,logo,sort,status')
            ->order('sort asc, company_id desc')
            ->select()
            ->toArray();
    }

    /**
     * 取某服务商下"已绑定且支持面单"的公司（电子面单选公司用）
     * 返回每家公司及该服务商的编码/业务类型/打印样式
     */
    public function getProviderCompanies(string $provider): array
    {
        if ($provider === '') {
            return [];
        }
        $bindings = $this->bindingModel->where([
            ['site_id', '=', $this->site_id],
            ['provider', '=', $provider],
            ['electronic_sheet_switch', '=', 1],
            ['status', '=', 1],
        ])->select()->toArray();
        if (empty($bindings)) {
            return [];
        }
        $companyIds = array_column($bindings, 'company_id');
        $companies = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['company_id', 'in', $companyIds],
            ['status', '=', 1],
        ])->order('sort asc, company_id desc')->column('company_name', 'company_id');

        $bindingMap = [];
        foreach ($bindings as $b) {
            $bindingMap[(int)$b['company_id']] = $b;
        }
        $result = [];
        foreach ($companies as $cid => $cname) {
            $b = $bindingMap[(int)$cid] ?? [];
            $result[] = [
                'company_id' => (int)$cid,
                'company_name' => $cname,
                'provider_code' => (string)($b['provider_code'] ?? ''),
                'exp_type' => is_array($b['exp_type'] ?? null) ? $b['exp_type'] : [],
                'print_style' => is_array($b['print_style'] ?? null) ? $b['print_style'] : [],
            ];
        }
        return $result;
    }

    /**
     * 详情（含全部服务商绑定）
     */
    public function getInfo(int $companyId): array
    {
        $info = $this->model->where([
            ['company_id', '=', $companyId],
            ['site_id', '=', $this->site_id],
        ])->findOrEmpty()->toArray();
        if (empty($info)) {
            throw new CommonException('快递公司不存在');
        }
        $info['bindings'] = $this->bindingModel->where([
            ['site_id', '=', $this->site_id],
            ['company_id', '=', $companyId],
        ])->select()->toArray();
        return $info;
    }

    /**
     * 新增
     */
    public function add(array $data): int
    {
        $companyId = 0;
        Db::transaction(function () use ($data, &$companyId) {
            $save = $this->buildCompanyData($data);
            $save['site_id'] = $this->site_id;
            $record = $this->model->create($save);
            $companyId = (int)$record->company_id;
            $this->saveBindings($companyId, $data['bindings'] ?? []);
        });
        return $companyId;
    }

    /**
     * 编辑
     */
    public function edit(int $companyId, array $data): bool
    {
        $this->getInfo($companyId);
        Db::transaction(function () use ($companyId, $data) {
            $save = $this->buildCompanyData($data);
            $this->model->where([
                ['company_id', '=', $companyId],
                ['site_id', '=', $this->site_id],
            ])->update($save);
            $this->saveBindings($companyId, $data['bindings'] ?? []);
        });
        return true;
    }

    /**
     * 删除（连带绑定）
     */
    public function del(int $companyId): bool
    {
        $this->getInfo($companyId);
        Db::transaction(function () use ($companyId) {
            $this->bindingModel->where([
                ['site_id', '=', $this->site_id],
                ['company_id', '=', $companyId],
            ])->delete();
            $this->model->where([
                ['company_id', '=', $companyId],
                ['site_id', '=', $this->site_id],
            ])->delete();
        });
        return true;
    }

    /**
     * 一键导入常用快递公司 + 各服务商绑定（幂等：按公司名跳过，已存在的绑定补齐）
     * @return int 新增公司数
     */
    public function importPresets(): int
    {
        $existNameToId = $this->model->where([['site_id', '=', $this->site_id]])
            ->column('company_id', 'company_name');

        $added = 0;
        foreach (ExpressCompanyDict::presets() as $preset) {
            $name = (string)$preset['company_name'];
            $companyId = (int)($existNameToId[$name] ?? 0);

            if ($companyId === 0) {
                $record = $this->model->create([
                    'site_id' => $this->site_id,
                    'company_name' => $name,
                    'logo' => '',
                    'url' => '',
                    'express_code' => '',
                    'sort' => (int)($preset['sort'] ?? 0),
                    'status' => 1,
                ]);
                $companyId = (int)$record->company_id;
                $added++;
            }

            foreach (($preset['bindings'] ?? []) as $provider => $bind) {
                $exists = $this->bindingModel->where([
                    ['site_id', '=', $this->site_id],
                    ['company_id', '=', $companyId],
                    ['provider', '=', $provider],
                ])->count();
                if ($exists) {
                    continue;
                }
                $this->bindingModel->create([
                    'site_id' => $this->site_id,
                    'company_id' => $companyId,
                    'provider' => (string)$provider,
                    'provider_code' => (string)($bind['code'] ?? ''),
                    'electronic_sheet_switch' => (int)($bind['electronic_sheet'] ?? 0),
                    'exp_type' => [],
                    'print_style' => [],
                    'status' => 1,
                ]);
            }
        }
        return $added;
    }

    /**
     * 公司主表字段
     */
    private function buildCompanyData(array $data): array
    {
        $name = trim((string)($data['company_name'] ?? ''));
        if ($name === '') {
            throw new CommonException('请输入快递公司名称');
        }
        return [
            'company_name' => $name,
            'logo' => (string)($data['logo'] ?? ''),
            'url' => (string)($data['url'] ?? ''),
            'express_code' => (string)($data['express_code'] ?? ''),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
        ];
    }

    /**
     * 覆盖式保存某公司的服务商绑定
     * @param int $companyId
     * @param mixed $bindings [{provider, provider_code, electronic_sheet_switch, exp_type[], print_style[]}]
     */
    private function saveBindings(int $companyId, $bindings): void
    {
        if (is_string($bindings) && $bindings !== '') {
            $decoded = json_decode($bindings, true);
            $bindings = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($bindings)) {
            $bindings = [];
        }

        // 覆盖式：先清空再写入有效项
        $this->bindingModel->where([
            ['site_id', '=', $this->site_id],
            ['company_id', '=', $companyId],
        ])->delete();

        foreach ($bindings as $bind) {
            if (!is_array($bind)) {
                continue;
            }
            $provider = trim((string)($bind['provider'] ?? ''));
            if ($provider === '') {
                continue;
            }
            $this->bindingModel->create([
                'site_id' => $this->site_id,
                'company_id' => $companyId,
                'provider' => $provider,
                'provider_code' => (string)($bind['provider_code'] ?? ''),
                'electronic_sheet_switch' => (int)($bind['electronic_sheet_switch'] ?? 0),
                'exp_type' => $this->normalizeRows($bind['exp_type'] ?? [], ['text', 'value']),
                'print_style' => $this->normalizeRows($bind['print_style'] ?? [], ['template_name', 'template_size']),
                'status' => (int)($bind['status'] ?? 1),
            ]);
        }
    }

    /**
     * 归一化动态行
     */
    private function normalizeRows($rows, array $keys): array
    {
        if (is_string($rows) && $rows !== '') {
            $decoded = json_decode($rows, true);
            $rows = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [];
            $filled = false;
            foreach ($keys as $k) {
                $v = trim((string)($row[$k] ?? ''));
                $item[$k] = $v;
                if ($v !== '') {
                    $filled = true;
                }
            }
            if ($filled) {
                $result[] = $item;
            }
        }
        return $result;
    }
}
