<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\delivery;

use addon\hsx_recycle\app\model\delivery\RecycleExpressSheet;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 电子面单模板管理（参照 phone_shop ElectronicSheetService）
 *
 * 一个模板 = 绑定快递公司 + 执行服务商(yisu/kuaidi100) + 业务类型/打印样式 + 账号/月结/网点 + 打印方式。
 * 业务发件时按模板调对应 provider 下单出面单。
 *
 * @package addon\hsx_recycle\app\service\admin\delivery
 */
class RecycleExpressSheetService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleExpressSheet();
    }

    /**
     * 邮费支付方式字典
     */
    public function getPayType(): array
    {
        return [
            1 => '现付',
            2 => '到付',
            3 => '月结',
        ];
    }

    /**
     * 分页列表（带快递公司名）
     */
    public function getPage(array $where = []): array
    {
        $search = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['template_name', 'express_company_id', 'provider', 'status'], $where)
            ->with(['company' => function ($query) {
                $query->field('company_id,company_name');
            }])
            ->order('is_default desc, id desc');
        return $this->pageQuery($search);
    }

    /**
     * 全量列表（发件下拉用）
     */
    public function getList(array $where = []): array
    {
        return $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['express_company_id', 'provider', 'status'], $where)
            ->with(['company' => function ($query) {
                $query->field('company_id,company_name');
            }])
            ->order('is_default desc, id desc')
            ->select()
            ->toArray();
    }

    /**
     * 详情
     */
    public function getInfo(int $id): array
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
        ])->findOrEmpty()->toArray();
        if (empty($info)) {
            throw new CommonException('电子面单模板不存在');
        }
        return $info;
    }

    /**
     * 新增
     */
    public function add(array $data): int
    {
        $save = $this->buildSaveData($data);
        $save['site_id'] = $this->site_id;
        $record = $this->model->create($save);
        $id = (int)$record->id;
        if (!empty($save['is_default'])) {
            $this->setDefault($id);
        }
        return $id;
    }

    /**
     * 编辑
     */
    public function edit(int $id, array $data): bool
    {
        $this->getInfo($id);
        $save = $this->buildSaveData($data);
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
        ])->update($save);
        if (!empty($save['is_default'])) {
            $this->setDefault($id);
        }
        return true;
    }

    /**
     * 删除
     */
    public function del(int $id): bool
    {
        $this->getInfo($id);
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
        ])->delete();
        return true;
    }

    /**
     * 设为默认（同站点单一默认）
     */
    public function setDefault(int $id): bool
    {
        $this->getInfo($id);
        Db::transaction(function () use ($id) {
            $this->model->where([['site_id', '=', $this->site_id]])->update(['is_default' => 0]);
            $this->model->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id],
            ])->update(['is_default' => 1, 'status' => 1]);
        });
        return true;
    }

    /**
     * 组装入库字段
     */
    private function buildSaveData(array $data): array
    {
        $name = trim((string)($data['template_name'] ?? ''));
        if ($name === '') {
            throw new CommonException('请输入模板名称');
        }
        $provider = trim((string)($data['provider'] ?? ''));
        if ($provider === '') {
            throw new CommonException('请选择执行服务商');
        }
        if ((int)($data['express_company_id'] ?? 0) <= 0) {
            throw new CommonException('请选择快递公司');
        }

        $output = strtoupper((string)($data['output_type'] ?? 'IMAGE'));
        if (!in_array($output, ['IMAGE', 'HTML', 'CLOUD', 'PDF'], true)) {
            $output = 'IMAGE';
        }
        $channel = (string)($data['print_channel'] ?? 'browser');
        if (!in_array($channel, ['browser', 'cloud', 'lodop'], true)) {
            $channel = 'browser';
        }

        return [
            'template_name' => $name,
            'provider' => $provider,
            'express_company_id' => (int)$data['express_company_id'],
            'exp_type' => (string)($data['exp_type'] ?? ''),
            'exp_type_name' => (string)($data['exp_type_name'] ?? ''),
            'print_style' => (string)($data['print_style'] ?? ''),
            'customer_name' => (string)($data['customer_name'] ?? ''),
            'customer_pwd' => (string)($data['customer_pwd'] ?? ''),
            'send_site' => (string)($data['send_site'] ?? ''),
            'send_staff' => (string)($data['send_staff'] ?? ''),
            'month_code' => (string)($data['month_code'] ?? ''),
            'pay_type' => (int)($data['pay_type'] ?? 1),
            'output_type' => $output,
            'print_channel' => $channel,
            'temp_id' => (string)($data['temp_id'] ?? ''),
            'child_temp_id' => (string)($data['child_temp_id'] ?? ''),
            'back_temp_id' => (string)($data['back_temp_id'] ?? ''),
            'siid' => (string)($data['siid'] ?? ''),
            'is_notice' => (int)($data['is_notice'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
            'is_default' => (int)($data['is_default'] ?? 0),
        ];
    }
}
