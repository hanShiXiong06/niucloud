<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\dict\MemberCardDict;
use addon\hsx_member_card\app\model\MemberCardOrder;
use addon\hsx_member_card\app\model\MemberCardProduct;
use addon\hsx_member_card\app\model\MemberCardProductItem;
use addon\hsx_member_card\app\support\MemberCardMoney;
use addon\hsx_member_card\app\support\MemberCardNumber;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

final class MemberCardProductService extends BaseAdminService
{
    public function lists(array $where): array
    {
        $query = MemberCardProduct::where([
            ['site_id', '=', $this->site_id],
            ['status', '<>', MemberCardDict::PRODUCT_DELETED],
        ]);
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') $query->whereLike('product_no|product_name', '%' . $keyword . '%');
        $status = trim((string)($where['status'] ?? ''));
        if ($status !== '') $query->where('status', '=', $status);
        $page = $query->order('sort desc,id desc')->paginate([
            'list_rows' => min(100, max(1, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $ids = array_map('intval', array_column($page['data'] ?? [], 'id'));
        $items = $ids === [] ? [] : MemberCardProductItem::where([['site_id', '=', $this->site_id]])
            ->whereIn('product_id', $ids)->order('sort desc,id asc')->select()->toArray();
        $itemMap = [];
        foreach ($items as $item) $itemMap[(int)$item['product_id']][] = $item;
        foreach ($page['data'] as &$row) {
            $row['item'] = $itemMap[(int)$row['id']][0] ?? null;
            $row['validity_text'] = $this->validityText($row);
        }
        unset($row);
        return $page;
    }

    public function options(): array
    {
        $rows = MemberCardProduct::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', MemberCardDict::PRODUCT_ENABLED],
        ])->field('id,product_no,product_name,cover_url,sale_price,market_price,effective_mode,validity_mode,duration_value,duration_unit,fixed_start_at,fixed_end_at,usage_notice,sort')
            ->order('sort desc,id desc')->select()->toArray();
        $ids = array_map('intval', array_column($rows, 'id'));
        $items = $ids === [] ? [] : MemberCardProductItem::where([
            ['site_id', '=', $this->site_id], ['status', '=', 1],
        ])->whereIn('product_id', $ids)->order('sort desc,id asc')->select()->toArray();
        $map = [];
        foreach ($items as $item) $map[(int)$item['product_id']][] = $item;
        foreach ($rows as &$row) {
            $row['item'] = $map[(int)$row['id']][0] ?? null;
            $row['validity_text'] = $this->validityText($row);
        }
        unset($row);
        return $rows;
    }

    public function info(int $id): array
    {
        $product = $this->find($id);
        $row = $product->toArray();
        $row['item'] = MemberCardProductItem::where([
            ['site_id', '=', $this->site_id], ['product_id', '=', $id], ['status', '=', 1],
        ])->order('sort desc,id asc')->findOrEmpty()->toArray();
        $row['validity_text'] = $this->validityText($row);
        return $row;
    }

    public function save(array $data, int $id = 0): int
    {
        [$values, $itemValues] = $this->validateAndNormalize($data);
        $productId = 0;
        $before = [];
        Db::transaction(function () use ($id, $values, $itemValues, &$productId, &$before): void {
            $now = time();
            if ($id > 0) {
                $product = $this->find($id, true);
                $before = $this->info($id);
                if ((string)$product->status === MemberCardDict::PRODUCT_DELETED) throw new CommonException('已删除卡种不能编辑');
                $product->save(array_merge($values, [
                    'update_uid' => (int)$this->uid,
                    'update_name' => (string)$this->username,
                    'update_at' => $now,
                ]));
                $productId = $id;
            } else {
                $product = MemberCardProduct::create(array_merge($values, [
                    'site_id' => (int)$this->site_id,
                    'product_no' => MemberCardNumber::make('CP'),
                    'status' => MemberCardDict::PRODUCT_DRAFT,
                    'create_uid' => (int)$this->uid,
                    'create_name' => (string)$this->username,
                    'update_uid' => (int)$this->uid,
                    'update_name' => (string)$this->username,
                    'create_at' => $now,
                    'update_at' => $now,
                ]));
                $productId = (int)$product->id;
            }

            $item = MemberCardProductItem::where([
                ['site_id', '=', $this->site_id], ['product_id', '=', $productId], ['item_code', '=', $itemValues['item_code']],
            ])->lock(true)->findOrEmpty();
            if ($item->isEmpty()) {
                MemberCardProductItem::create(array_merge($itemValues, [
                    'site_id' => (int)$this->site_id,
                    'product_id' => $productId,
                    'status' => 1,
                    'create_at' => $now,
                    'update_at' => $now,
                ]));
            } else {
                $item->save(array_merge($itemValues, ['status' => 1, 'update_at' => $now]));
            }
        });
        (new MemberCardAuditService())->record('product', $productId, (string)$this->find($productId)->product_no, $id > 0 ? 'update' : 'create', $before, $this->info($productId));
        return $productId;
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, [MemberCardDict::PRODUCT_ENABLED, MemberCardDict::PRODUCT_DISABLED], true)) {
            throw new CommonException('卡种状态操作不正确');
        }
        $product = $this->find($id, true);
        $before = $product->toArray();
        if ($status === MemberCardDict::PRODUCT_ENABLED) $this->validateAndNormalize(array_merge($this->info($id), ['item' => $this->info($id)['item'] ?? []]));
        $product->save([
            'status' => $status,
            'update_uid' => (int)$this->uid,
            'update_name' => (string)$this->username,
            'update_at' => time(),
        ]);
        (new MemberCardAuditService())->record('product', $id, (string)$product->product_no, $status === MemberCardDict::PRODUCT_ENABLED ? 'enable' : 'disable', $before, $product->toArray());
        return true;
    }

    public function delete(int $id): bool
    {
        $product = $this->find($id, true);
        if ((string)$product->status !== MemberCardDict::PRODUCT_DRAFT) throw new CommonException('只有未启用的草稿卡种可以删除');
        if (MemberCardOrder::where([['site_id', '=', $this->site_id], ['product_id', '=', $id]])->count() > 0) {
            throw new CommonException('卡种已有开卡历史，不能删除，只能停用');
        }
        $before = $product->toArray();
        $product->save(['status' => MemberCardDict::PRODUCT_DELETED, 'update_uid' => (int)$this->uid, 'update_name' => (string)$this->username, 'update_at' => time()]);
        (new MemberCardAuditService())->record('product', $id, (string)$product->product_no, 'delete', $before, $product->toArray());
        return true;
    }

    /** @return array{0:array,1:array} */
    private function validateAndNormalize(array $data): array
    {
        $name = mb_substr(trim((string)($data['product_name'] ?? '')), 0, 100);
        if ($name === '') throw new CommonException('请填写卡种名称');
        $salePrice = MemberCardMoney::normalize($data['sale_price'] ?? '');
        $marketPrice = MemberCardMoney::normalize($data['market_price'] ?? 0);
        if (MemberCardMoney::compare($salePrice, '0') < 0 || MemberCardMoney::compare($salePrice, '9999999999.99') > 0) throw new CommonException('卡种售价超出允许范围');
        if (MemberCardMoney::compare($marketPrice, '0') < 0) throw new CommonException('卡种划线价不能小于0');

        $effectiveMode = (string)($data['effective_mode'] ?? 'immediate');
        $validityMode = (string)($data['validity_mode'] ?? 'permanent');
        $durationValue = max(0, (int)($data['duration_value'] ?? 0));
        $durationUnit = (string)($data['duration_unit'] ?? 'day');
        $fixedStart = max(0, (int)($data['fixed_start_at'] ?? 0));
        $fixedEnd = max(0, (int)($data['fixed_end_at'] ?? 0));
        if (!in_array($effectiveMode, ['immediate', 'first_use', 'fixed'], true)) throw new CommonException('生效方式不正确');
        if (!in_array($validityMode, ['permanent', 'duration', 'fixed'], true)) throw new CommonException('有效期方式不正确');
        if ($effectiveMode === 'fixed' && $fixedStart <= 0) throw new CommonException('指定日期生效必须选择生效时间');
        if ($validityMode === 'duration' && ($durationValue <= 0 || !in_array($durationUnit, ['day', 'month'], true))) throw new CommonException('固定时长必须填写正整数并选择天或月');
        if ($validityMode === 'fixed' && ($fixedEnd <= 0 || $effectiveMode === 'fixed' && $fixedEnd <= $fixedStart)) throw new CommonException('固定失效时间必须晚于生效时间');

        $item = is_array($data['item'] ?? null) ? $data['item'] : [];
        $itemCode = $this->stableCode((string)($item['item_code'] ?? 'film_service'));
        $itemName = mb_substr(trim((string)($item['item_name'] ?? '贴膜服务')), 0, 100);
        $usageMode = (string)($item['usage_mode'] ?? 'limited');
        $totalTimes = max(0, (int)($item['total_times'] ?? 0));
        if ($itemName === '') throw new CommonException('请填写服务权益名称');
        if (!in_array($usageMode, ['limited', 'unlimited'], true)) throw new CommonException('权益次数模式不正确');
        if ($usageMode === 'limited' && $totalTimes <= 0) throw new CommonException('有限次卡必须填写大于0的可用次数');
        if ($usageMode === 'unlimited') $totalTimes = 0;
        $recognitionMode = (string)($item['recognition_mode'] ?? 'average');
        if (!in_array($recognitionMode, ['average', 'fixed', 'none'], true)) throw new CommonException('耗卡金额确认方式不正确');
        $recognitionAmount = MemberCardMoney::normalize($item['recognition_amount'] ?? 0);
        if ($recognitionMode === 'fixed' && MemberCardMoney::compare($recognitionAmount, '0') <= 0) throw new CommonException('固定耗卡金额必须大于0');

        return [[
            'product_name' => $name,
            'cover_url' => mb_substr(trim((string)($data['cover_url'] ?? '')), 0, 500),
            'sale_price' => $salePrice,
            'market_price' => $marketPrice,
            'effective_mode' => $effectiveMode,
            'validity_mode' => $validityMode,
            'duration_value' => $durationValue,
            'duration_unit' => $durationUnit,
            'fixed_start_at' => $fixedStart,
            'fixed_end_at' => $fixedEnd,
            'usage_notice' => trim((string)($data['usage_notice'] ?? '')),
            'sort' => (int)($data['sort'] ?? 0),
        ], [
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'usage_mode' => $usageMode,
            'total_times' => $totalTimes,
            'daily_limit' => max(0, (int)($item['daily_limit'] ?? 0)),
            'reference_price' => MemberCardMoney::normalize($item['reference_price'] ?? 0),
            'recognition_mode' => $recognitionMode,
            'recognition_amount' => $recognitionAmount,
            'sort' => (int)($item['sort'] ?? 0),
        ]];
    }

    private function find(int $id, bool $lock = false): MemberCardProduct
    {
        $query = MemberCardProduct::where([['site_id', '=', $this->site_id], ['id', '=', $id]]);
        if ($lock) $query->lock(true);
        $row = $query->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('卡种不存在');
        return $row;
    }

    private function stableCode(string $value): string
    {
        $value = mb_substr((string)preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($value)), 0, 40);
        return $value !== '' ? $value : 'film_service';
    }

    private function validityText(array $row): string
    {
        return match ((string)($row['validity_mode'] ?? 'permanent')) {
            'duration' => (int)($row['duration_value'] ?? 0) . ((string)($row['duration_unit'] ?? 'day') === 'month' ? '个月有效' : '天有效'),
            'fixed' => '有效至 ' . date('Y-m-d', (int)($row['fixed_end_at'] ?? 0)),
            default => '永久有效',
        };
    }
}
