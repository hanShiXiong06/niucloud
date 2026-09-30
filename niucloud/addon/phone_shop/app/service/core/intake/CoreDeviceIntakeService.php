<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 货源入库(中台定价设备 -> 待上架货源)服务
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\intake;

use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\intake\DeviceIntake;
use addon\phone_shop\app\service\core\goods\CoreDeviceAttributeService;
use addon\phone_shop\app\support\IntakeMaterialTask;
use core\base\BaseCoreService;
use think\facade\Log;
use think\facade\Db;

/**
 * 把 ERP / 中台设备事件落成"待上架货源"。
 *
 * erp_asset_id 只是当前一次 ERP 安装内的数据库主键。插件卸载重装后该数字可能
 * 被复用，因此幂等判断还必须核对 IMEI / 资产编号，不能只看一个自增 ID。
 */
class CoreDeviceIntakeService extends BaseCoreService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new DeviceIntake();
    }

    /**
     * 从事件 payload 幂等入库。
     * @param array $event 事件原始数组（含 device_id / payload）
     * @return array 处理结果
     */
    public function saveFromEvent(array $event): array
    {
        return Db::transaction(fn(): array => $this->saveFromEventLocked($event));
    }

    private function saveFromEventLocked(array $event): array
    {
        $deviceId = (int) ($event['device_id'] ?? 0);
        $payload = (array) ($event['payload'] ?? []);
        $erpAssetId = (int) ($payload['erp_asset_id'] ?? 0);

        if ($erpAssetId <= 0) {
            return [ 'skipped' => true, 'reason' => 'no_erp_asset_id' ];
        }

        // site_id：中台事件放在【顶层】(非 payload)，这里优先取顶层，回退 payload。
        // 取不到则不入库，否则 site_id=0 会导致后台列表(按 site_id 过滤)看不到货源。
        $siteId = (int) ($event['site_id'] ?? $payload['site_id'] ?? 0);
        if ($siteId <= 0) {
            return [ 'skipped' => true, 'reason' => 'no_site_id' ];
        }

        $now = time();
        $deviceAttributes = new CoreDeviceAttributeService();
        $data = [
            'site_id'         => $siteId,
            'erp_asset_id'    => $erpAssetId,
            'device_id'       => $deviceId,
            'model_name'      => (string) ($payload['model_name'] ?? ''),
            'brand_name'      => (string) ($payload['brand_name'] ?? ''),
            'memory'          => (string) ($payload['memory'] ?? ''),
            'color'           => (string) ($payload['color'] ?? ''),
            'battery_health'  => $deviceAttributes->normalizeBattery($payload['battery_health'] ?? $payload['battery_capacity'] ?? '', false),
            'warranty_expire_time' => $deviceAttributes->normalizeWarrantyExpire($payload['warranty_expire_time'] ?? $payload['warranty_expire_date'] ?? '', false),
            'condition_grade' => (string) ($payload['condition_grade'] ?? ''),
            'imei'            => (string) ($payload['imei'] ?? ''),
            'images'          => $this->toJson($payload['images'] ?? []),
            'sale_price'      => (float) ($payload['sale_price'] ?? 0),
            'peer_price'      => (float) ($payload['peer_price'] ?? 0),
            'cost_price'      => (float) ($payload['cost_price'] ?? 0),
            'qc_info'         => $this->toJson($payload['qc_info'] ?? []),
            'hidden_check_keys' => $this->toJson($payload['hidden_check_keys'] ?? []),
            'media'           => $this->toJson($payload['media'] ?? []),
            'raw_payload'     => $this->toJson($payload),
            'update_time'     => $now,
        ];

        $exist = $this->model->where([['site_id', '=', $siteId], ['erp_asset_id', '=', $erpAssetId]])->lock(true)->findOrEmpty();
        if (!$exist->isEmpty() && $this->identityConflicts($exist->toArray(), $payload)) {
            $this->archiveStaleBinding($exist->toArray(), $siteId, $erpAssetId, $payload);
            $exist = $this->model->where([['site_id', '=', $siteId], ['erp_asset_id', '=', $erpAssetId]])->findOrEmpty();
        }
        $data['raw_payload'] = $this->toJson(IntakeMaterialTask::mergeSnapshot(
            $exist->isEmpty() ? [] : $exist['raw_payload'], $payload, $now
        ));
        if (!$exist->isEmpty()) {
            // 已建品的不覆盖业务关键字段，只更新原始 payload 备查，避免回冲人工成果
            if ((int) $exist['status'] === DeviceIntake::STATUS_BUILT) {
                $this->model->where([['site_id', '=', $siteId], ['erp_asset_id', '=', $erpAssetId]])
                    ->update([ 'raw_payload' => $data['raw_payload'], 'update_time' => $now ]);
                return [ 'updated' => true, 'erp_asset_id' => $erpAssetId, 'note' => 'already_built_payload_only' ];
            }
            // 旧版事件可能不携带新增的结构化字段。空值不能反向覆盖运营已经补齐的事实，
            // 否则重新投递一次历史事件就会把颜色、电池和保修资料清空。
            if ($data['color'] === '' && trim((string)($exist['color'] ?? '')) !== '') {
                $data['color'] = (string)$exist['color'];
            }
            if ((int)$data['battery_health'] < 0 && (int)($exist['battery_health'] ?? -1) >= 0) {
                $data['battery_health'] = (int)$exist['battery_health'];
            }
            if ((int)$data['warranty_expire_time'] <= 0 && (int)($exist['warranty_expire_time'] ?? 0) > 0) {
                $data['warranty_expire_time'] = (int)$exist['warranty_expire_time'];
            }
            $this->model->where([['site_id', '=', $siteId], ['erp_asset_id', '=', $erpAssetId]])->update($data);
            return [ 'updated' => true, 'erp_asset_id' => $erpAssetId ];
        }

        $data['status'] = DeviceIntake::STATUS_PENDING;
        $data['goods_id'] = 0;
        $data['create_time'] = $now;
        $this->model->create($data);
        return [ 'created' => true, 'erp_asset_id' => $erpAssetId ];
    }

    /** 发布失败保留实际原因；不能重置已经建品的货源或操作员资料进度。 */
    public function recordPublishFailure(int $siteId, int $assetId, string $reason): void
    {
        if ($siteId <= 0 || $assetId <= 0) return;
        Db::transaction(function () use ($siteId, $assetId, $reason): void {
            $row = $this->model->where('site_id', $siteId)->where('erp_asset_id', $assetId)->lock(true)->findOrEmpty();
            if ($row->isEmpty() || (int)$row->goods_id > 0) return;
            $raw = IntakeMaterialTask::payload($row->raw_payload);
            if (empty($raw['basic_first'])) return;
            $raw['basic_block_reason'] = mb_substr($reason, 0, 500);
            $row->save(['raw_payload' => $raw, 'update_time' => time()]);
        });
    }

    /**
     * 同站点、同自增 ID 但设备身份不同，说明 ERP 重装后主键被复用。
     * 优先用 IMEI 判断；没有 IMEI 时才回退资产编号。没有可比较字段时保持旧的
     * 幂等行为，避免误拆真实关联。
     */
    private function identityConflicts(array $existing, array $payload): bool
    {
        $incomingImei = $this->identityValue((string)($payload['imei'] ?? ''));
        $existingImei = $this->identityValue((string)($existing['imei'] ?? ''));
        if ($incomingImei !== '' && $existingImei !== '') {
            return $incomingImei !== $existingImei;
        }

        $raw = $existing['raw_payload'] ?? [];
        if (is_string($raw)) $raw = json_decode($raw, true) ?: [];
        if (!is_array($raw)) $raw = [];
        $incomingAssetNo = $this->identityValue((string)($payload['asset_no'] ?? ''));
        $existingAssetNo = $this->identityValue((string)($raw['asset_no'] ?? ''));
        return $incomingAssetNo !== ''
            && $existingAssetNo !== ''
            && $incomingAssetNo !== $existingAssetNo;
    }

    /**
     * 只解除已经失效的 ERP 外键，不删除旧商城商品和历史货源。
     * 历史货源用负 intake_id 占位，既保留追溯记录，也释放被复用的正资产 ID。
     */
    private function archiveStaleBinding(array $existing, int $siteId, int $erpAssetId, array $payload): void
    {
        $intakeId = (int)($existing['intake_id'] ?? 0);
        if ($intakeId <= 0) return;

        (new GoodsSku())->where([
            ['site_id', '=', $siteId],
            ['erp_asset_id', '=', $erpAssetId],
        ])->update(['erp_asset_id' => 0]);
        $this->model->where('intake_id', $intakeId)->update([
            'erp_asset_id' => -$intakeId,
            'update_time' => time(),
        ]);

        Log::warning('商城检测到 ERP 重装后的资产主键复用，已解除旧商品关联', [
            'site_id' => $siteId,
            'erp_asset_id' => $erpAssetId,
            'archived_intake_id' => $intakeId,
            'old_imei' => (string)($existing['imei'] ?? ''),
            'new_imei' => (string)($payload['imei'] ?? ''),
            'new_asset_no' => (string)($payload['asset_no'] ?? ''),
        ]);
    }

    private function identityValue(string $value): string
    {
        return strtoupper((string)preg_replace('/[\s\-]+/', '', trim($value)));
    }

    protected function toJson($value): string
    {
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE);
        if (is_string($value)) {
            $t = trim($value);
            if ($t === '') return json_encode([], JSON_UNESCAPED_UNICODE);
            // 已是合法 JSON 原样存；纯文本(如中台质检摘要)包成 JSON 字符串，避免 json 字段读回时丢失
            json_decode($t);
            return json_last_error() === JSON_ERROR_NONE ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }
}
