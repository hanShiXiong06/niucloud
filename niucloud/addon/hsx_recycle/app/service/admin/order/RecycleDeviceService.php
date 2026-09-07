<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\stat\RecycleStageDict;
use addon\hsx_recycle\app\model\order\RecycleConsignmentLog;
use addon\hsx_recycle\app\dict\order\RecycleReturnOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleReturnDevice;
use addon\hsx_recycle\app\model\order\RecycleReturnOrder;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelDictService;
use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDeviceLogService;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderNotifyService;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceReadingArchive;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService;
use addon\hsx_recycle\app\service\core\recycle_order\RecyclePaymentOwnershipService;
use addon\hsx_recycle\app\service\admin\printer\RecyclePrintSceneService;
use addon\hsx_recycle\app\service\admin\stat\TaskService;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收设备服务
 */
class RecycleDeviceService extends BaseAdminService
{
    /**
     * 构造函数
     * @param int $site_id 站点ID
     */
    public function __construct(int $site_id = 0)
    {
        parent::__construct();
        $this->model = new RecycleDevice();
        $this->notifyService = new CoreRecycleOrderNotifyService();
        $this->logService = new CoreRecycleDeviceLogService();
    }
    /**
     * 获取设备列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param string $field
     * @param string $order
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getPage(array $where = [], int $page = 1, int $limit = 10, string $field = '*', string $order = ''): array
    {
        $search_model = new RecycleDevice();
        $search_model = $search_model->withSearch(['order_id', 'device_name', 'imei', 'model', 'status', 'create_at'], $where)
            ->with(['order'])
            ->field($field)
            ->order(empty($order) ? 'create_at desc' : $order)
            ->append(['status_name', 'check_images_thumb_small', 'check_images_seller_thumb_small', 'check_images_buyer_thumb_small']);
            
        return $this->pageQuery($search_model);
    }


    /**
     * 获取设备列表数量
     * @param array $where
     * @return int
     */
    public function getCount(array $where = [])
    {
        $search_model = new RecycleDevice();
        return $search_model->withSearch(['order_id', 'device_name', 'imei', 'status', 'create_at'], $where)->count();
    }

    /**
     * 获取设备信息
     * @param int $id 设备ID
     * @param array $field 字段
     * @return array
     */
    public function getInfo(int $id, array $field = []): array
    {
        
        $info = (new RecycleDevice())->where([['id', '=', $id]])
        ->field($field)->with(['order','checkUser'])
        ->findOrEmpty()
        ->append(['status_name', 'pay_status_name', 'confirm_status_name', 'dispose_type_name', 'dispose_status_name', 'check_template_name', 'check_images_thumb_small', 'check_images_seller_thumb_small', 'check_images_buyer_thumb_small'])
        ->toArray();

        // 后端统一产出"自描述"的设备基本信息列表 check_summary(每项自带 field_name/label/value),
        // 前端只需 v-for 渲染 {field_name}:{label},不再硬编码字段键与名称。存储仍是 ID(规范)。
        $info = $this->attachCheckSummary($info);

        // 给 check_meta.result_items 逐项打上级别(severity, 实时取字典), 并备好 abnormal_items / severity_summary
        $info = $this->enrichResultItemsSeverity($info);

        return $info;
    }

    /**
     * 对外复用:给单个设备数组(含 info.check_meta)注入 check_summary + result_items 级别 severity / abnormal_items。
     * 订单详情等"设备列表"场景复用此入口,保证与单设备详情同口径(字典唯一事实源),设备列表也能标异常。
     */
    public function enrichDeviceCheckMeta(array $device): array
    {
        $device = $this->attachCheckSummary($device);
        $device = $this->enrichResultItemsSeverity($device);
        return $device;
    }

    /**
     * 产出设备基本质检字段(capacity/color/system_version/warranty_info)的自描述渲染列表。
     * - 按 recycle_check_field 取 field_name/component/unit;按 recycle_check_option 把存储 ID→label;
     * - input/number 等无选项字段 label 即原值;查不到选项也回退原值;
     * - 不改库,只增强返回。前端遍历 check_summary 渲染,零硬编码。
     * @param array $info getInfo 的设备数组
     * @return array 增加 $info['check_summary'] = [{field_key,field_name,component,value,label,unit}, ...]
     */
    private function attachCheckSummary(array $info): array
    {
        $templateId = (int)($info['check_template_id'] ?? 0);
        if ($templateId <= 0) {
            return $info;
        }
        // 设备级基本字段(存在设备列/info 里),数组顺序即展示顺序
        $reserved = ['capacity', 'color', 'system_version', 'warranty_info'];

        $fields = Db::name('recycle_check_field')
            ->where('site_id', $this->site_id)
            ->where('template_id', $templateId)
            ->whereIn('field_key', $reserved)
            ->field('id,field_key,field_name,component,unit')
            ->select()->toArray();
        if (empty($fields)) {
            return $info;
        }
        $byKey = [];
        foreach ($fields as $f) {
            $byKey[(string)$f['field_key']] = $f;
        }
        $optMap = DeviceSummaryHelper::buildOptionLabelMap([$templateId], $reserved, (int)$this->site_id)[$templateId] ?? [];

        $nested = is_array($info['info'] ?? null) ? $info['info'] : [];
        $summary = [];
        foreach ($reserved as $fk) {
            if (empty($byKey[$fk])) {
                continue;
            }
            $raw = $nested[$fk] ?? ($info[$fk] ?? '');
            if ($raw === '' || $raw === null || $raw === []) {
                continue;
            }
            $summary[] = [
                'field_key'  => $fk,
                'field_name' => (string)$byKey[$fk]['field_name'],
                'component'  => (string)$byKey[$fk]['component'],
                'value'      => $raw,                         // 存储值(ID),规范
                'label'      => DeviceSummaryHelper::resolveDisplayValue($raw, $optMap[$fk] ?? []),
                'unit'       => (string)($byKey[$fk]['unit'] ?? ''),
            ];
        }
        if (!empty($summary)) {
            $info['check_summary'] = $summary;
        }
        return $info;
    }

    /**
     * 把设备详情按 UI 区块组织成干净结构,前端按区块渲染,不再面对一坨平铺字段。
     * 区块:base 设备基础信息 / price 价格信息 / check 质检信息 / logs 操作日志。
     * 入参为 getInfo() 返回(含 check_summary、status_name 等 append)+ 外部已挂的 logs。
     * @param array $d
     * @return array
     */
    public function buildDetailView(array $d): array
    {
        $checkUser = is_array($d['checkUser'] ?? null) ? $d['checkUser'] : [];
        return [
            'base' => [
                'model'       => (string)($d['model'] ?? ''),
                'imei'        => (string)($d['imei'] ?? ''),
                'status'      => $d['status'] ?? null,
                'status_name' => (string)($d['status_name'] ?? ''),
                'created_at'  => (string)($d['create_at'] ?? ''),
                // 内存/颜色/系统版本/保修 的自描述列表(field_name + label + value),前端 v-for 渲染
                'summary'     => $d['check_summary'] ?? [],
            ],
            'price' => [
                'initial_price' => $d['initial_price'] ?? '0.00',
                'final_price'   => $d['final_price'] ?? '0.00',
                'sell_price'    => $d['sell_price'] ?? '0.00',
                'final_status'  => $d['final_status'] ?? 0,
                'price_remark'  => (string)($d['price_remark'] ?? ''),
            ],
            'check' => [
                'check_at'      => $d['check_at'] ?? 0,
                'checker_name'  => (string)($checkUser['real_name'] ?? ($checkUser['username'] ?? '')),
                'status_name'   => (string)($d['status_name'] ?? ''),
                'result_seller' => (string)($d['check_result_seller'] ?? ''),
                'result_buyer'  => (string)($d['check_result_buyer'] ?? ''),
                // 扣费说明:优先价格备注,其次设备备注(若你的口径不同告诉我即可改)
                'fee_remark'    => (string)($d['price_remark'] ?? ($d['remark'] ?? '')),
                'images'        => $d['check_images_seller_thumb_small'] ?? [],
                // 按项的质检结果, 每项带 severity(实时取字典)。前端据此着色/筛选异常。
                'items'          => $this->buildCheckItemsWithSeverity($d),
                'summary_fields' => $this->checkMetaOf($d)['summary_fields'] ?? [],
                'abnormal_items' => $this->checkMetaOf($d)['abnormal_items'] ?? [],
                'severity_summary' => $this->checkMetaOf($d)['severity_summary'] ?? null,
            ],
            'logs' => $d['logs'] ?? [],
        ];
    }

    /**
     * 把 check_meta.result_items 整理成"带级别"的结果项列表。
     * severity 实时取自字典 recycle_check_dict(按选项文本),字典改动即时反映,无快照、无同步。
     * 结果项级别 = 其各选项标签里最严重的一档。
     * @param array $d getInfo 的设备数组(含 info.check_meta)
     * @return array
     */
    private function buildCheckItemsWithSeverity(array $d): array
    {
        $infoData = $this->toArr($d['info'] ?? null);
        $checkMeta = $this->toArr($infoData['check_meta'] ?? null);
        $resultItems = is_array($checkMeta['result_items'] ?? null) ? $checkMeta['result_items'] : [];
        if (empty($resultItems)) {
            return [];
        }
        $sevMap = $this->optionSeverityMap();
        $out = [];
        foreach ($resultItems as $it) {
            if (!is_array($it)) {
                continue;
            }
            $labels = $it['labels'] ?? [];
            if (!is_array($labels)) {
                $labels = [$labels];
            }
            // 级别 = 选中选项文本在字典里的级别(直取,不聚合)
            $firstLabel = mb_strtolower(trim((string)($labels[0] ?? '')));
            $out[] = [
                'field_key'  => (string)($it['field_key'] ?? ''),
                'field_name' => (string)($it['field_name'] ?? ''),
                'text'       => (string)($it['text'] ?? ''),
                'labels'     => array_values(array_map('strval', $labels)),
                'severity'   => $sevMap[$firstLabel] ?? 'normal',
            ];
        }
        return $out;
    }

    /**
     * 选项级别字典:recycle_check_dict(dict_type=option) 的 text → severity(唯一事实源)。
     * @return array [lower(text) => severity]
     */
    private function optionSeverityMap(): array
    {
        $rows = Db::name('recycle_check_dict')
            ->where('site_id', '=', $this->site_id)
            ->where('dict_type', '=', 'option')
            ->field('text,severity')
            ->select()->toArray();
        $map = [];
        foreach ($rows as $r) {
            $t = mb_strtolower(trim((string)($r['text'] ?? '')));
            if ($t === '') {
                continue;
            }
            $map[$t] = (string)($r['severity'] ?? 'normal');
        }
        return $map;
    }

    /**
     * 给设备 check_meta.result_items 逐项打级别(severity, 按选项文本实时取字典),
     * 并在 check_meta 上备好 abnormal_items(异常+一般, 异常在前)与 severity_summary(计数)。
     * 前端无需自己过滤/排序。级别直取、不聚合、不快照、不同步。
     * @param array $info getInfo 的设备数组
     * @return array
     */
    private function enrichResultItemsSeverity(array $info): array
    {
        // info 是模型 $json 字段, ThinkPHP 未开 jsonAssoc 时 toArray 后是 stdClass 对象 → 统一转数组
        $infoData = $this->toArr($info['info'] ?? null);
        if (empty($infoData)) {
            return $info;
        }
        $checkMeta = $this->toArr($infoData['check_meta'] ?? null);
        $items = is_array($checkMeta['result_items'] ?? null) ? $checkMeta['result_items'] : [];
        if (empty($items)) {
            return $info;
        }
        $sevMap = $this->optionSeverityMap();
        $sevOrder = ['abnormal' => 0, 'general' => 1, 'normal' => 2];
        $flagged = [];
        $count = ['abnormal' => 0, 'general' => 0, 'normal' => 0];
        foreach ($items as &$it) {
            if (!is_array($it)) {
                continue;
            }
            $labels = is_array($it['labels'] ?? null) ? $it['labels'] : [];
            // 结果项级别 = 选中选项文本的字典级别(单选直取)
            $sev = $sevMap[mb_strtolower(trim((string)($labels[0] ?? '')))] ?? 'normal';
            $it['severity'] = $sev;
            // option_items 也各打级别
            if (is_array($it['option_items'] ?? null)) {
                foreach ($it['option_items'] as &$oi) {
                    if (is_array($oi)) {
                        $oi['severity'] = $sevMap[mb_strtolower(trim((string)($oi['label'] ?? '')))] ?? 'normal';
                    }
                }
                unset($oi);
            }
            $count[$sev] = ($count[$sev] ?? 0) + 1;
            if ($sev !== 'normal') {
                $flagged[] = $it;
            }
        }
        unset($it);
        usort($flagged, static fn($a, $b) => ($sevOrder[$a['severity'] ?? 'normal'] ?? 9) <=> ($sevOrder[$b['severity'] ?? 'normal'] ?? 9));
        $checkMeta['result_items'] = $items;
        $checkMeta['abnormal_items'] = $flagged;
        $checkMeta['severity_summary'] = $count;
        // 突出项:由质检模板"设备摘要"勾选的关键字段(≤5)驱动,匹配质检值+级别
        $templateId = (int)($checkMeta['template_id'] ?? ($info['check_template_id'] ?? 0));
        $checkMeta['summary_fields'] = $this->buildSummaryFields(
            $templateId,
            $items,
            is_array($info['check_summary'] ?? null) ? $info['check_summary'] : []
        );
        $infoData['check_meta'] = $checkMeta;
        $info['info'] = $infoData;
        return $info;
    }

    /**
     * 质检模板"设备摘要"字段(extra_config.summary_visible=1, ≤10, 按 sort)→ 突出项列表。
     * 值/级别优先取自 result_items(同 field_key), 其次取自已解析的 check_summary(capacity/color 等)。
     * @return array [{field_key, field_name, label, severity}]
     */
    private function buildSummaryFields(int $templateId, array $resultItems, array $checkSummary): array
    {
        if ($templateId <= 0) {
            return [];
        }
        $fields = Db::name('recycle_check_field')
            ->where('site_id', '=', $this->site_id)
            ->where('template_id', '=', $templateId)
            ->where('is_show', '=', 1)
            ->field('field_key,field_name,sort,extra_config')
            ->order('sort asc,id asc')->select()->toArray();
        $picked = [];
        foreach ($fields as $f) {
            $ec = $this->toArr($f['extra_config'] ?? null);
            if ((int)($ec['summary_visible'] ?? ($ec['show_in_summary'] ?? 0)) === 1) {
                $picked[] = $f;
                if (count($picked) >= 10) {
                    break;
                }
            }
        }
        if (empty($picked)) {
            return [];
        }
        // 值/级别查找表
        $lookup = [];
        foreach ($resultItems as $it) {
            if (!is_array($it)) {
                continue;
            }
            $fk = (string)($it['field_key'] ?? '');
            if ($fk === '') {
                continue;
            }
            $labels = is_array($it['labels'] ?? null) ? $it['labels'] : [];
            $lookup[$fk] = [
                'label' => !empty($labels) ? implode('、', array_map('strval', $labels)) : (string)($it['text'] ?? ''),
                'severity' => (string)($it['severity'] ?? 'normal'),
            ];
        }
        foreach ($checkSummary as $cs) {
            if (!is_array($cs)) {
                continue;
            }
            $fk = (string)($cs['field_key'] ?? '');
            if ($fk !== '' && !isset($lookup[$fk])) {
                $lookup[$fk] = ['label' => (string)($cs['label'] ?? ''), 'severity' => 'normal'];
            }
        }
        $out = [];
        foreach ($picked as $f) {
            $fk = (string)$f['field_key'];
            $v = $lookup[$fk] ?? ['label' => '', 'severity' => 'normal'];
            $out[] = [
                'field_key'  => $fk,
                'field_name' => (string)$f['field_name'],
                'label'      => $v['label'],
                'severity'   => $v['severity'],
            ];
        }
        return $out;
    }

    /**
     * 跨插件取用(中台/ERP 全链路):某回收设备"带级别"的质检数据。
     * 复用 getInfo 的增强:result_items.severity / abnormal_items / severity_summary / summary_fields。
     * @param int $deviceId 回收设备ID
     * @return array check_meta(含上述增强字段);无质检则空数组
     */
    public function enrichedCheckMetaForDevice(int $deviceId): array
    {
        if ($deviceId <= 0) {
            return [];
        }
        $device = (new RecycleDevice())
            ->where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])
            ->findOrEmpty();
        if ($device->isEmpty()) {
            // 设备写库 site_id 可能为0, 兜底按主键取
            $device = (new RecycleDevice())->where([['id', '=', $deviceId]])->findOrEmpty();
        }
        if ($device->isEmpty()) {
            return [];
        }
        $arr = $device->toArray();
        $arr = $this->attachCheckSummary($arr);
        $arr = $this->enrichResultItemsSeverity($arr);
        return $this->checkMetaOf($arr);
    }

    /** 取设备 info.check_meta(已归一为数组) */
    private function checkMetaOf(array $d): array
    {
        $info = $this->toArr($d['info'] ?? null);
        return $this->toArr($info['check_meta'] ?? null);
    }

    /**
     * 统一"设备身份"拼装器(跨插件复用:财务/ERP/中台/追踪/商城都用同一口径)。
     * 返回:name=型号, imei=串号, summary_fields=≤5"加入描述"质检项, subtitle=label 拼接, identity_text=整行文字。
     * @param int $deviceId 回收设备ID
     */
    public function deviceIdentity(int $deviceId): array
    {
        if ($deviceId <= 0) {
            return $this->emptyDeviceIdentity();
        }
        $device = (new RecycleDevice())
            ->where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])
            ->findOrEmpty();
        if ($device->isEmpty()) {
            // 设备写库 site_id 可能为0,兜底按主键取
            $device = (new RecycleDevice())->where([['id', '=', $deviceId]])->findOrEmpty();
        }
        if ($device->isEmpty()) {
            return $this->emptyDeviceIdentity();
        }
        return $this->composeDeviceIdentity($device->toArray());
    }

    /**
     * 批量取设备身份(按 deviceId 映射),供列表场景避免逐行散查。
     * @param array $deviceIds
     * @return array<int,array> deviceId => identity
     */
    public function deviceIdentityMap(array $deviceIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $deviceIds), static fn ($v) => $v > 0)));
        $map = [];
        foreach ($ids as $id) {
            try {
                $map[$id] = $this->deviceIdentity($id);
            } catch (\Throwable $e) {
                $map[$id] = $this->emptyDeviceIdentity();
            }
        }
        return $map;
    }

    /** 由设备记录数组拼装身份三件套 */
    private function composeDeviceIdentity(array $d): array
    {
        $deviceId = (int)($d['id'] ?? 0);
        $name = trim((string)($d['model'] ?? ''));
        $imei = trim((string)($d['imei'] ?? ''));
        if ($imei === '') {
            $imei = trim((string)($d['imei2'] ?? ''));
        }
        if ($imei === '') {
            $imei = trim((string)($d['sn'] ?? ''));
        }

        $summaryFields = [];
        $meta = $this->enrichedCheckMetaForDevice($deviceId);
        foreach (($meta['summary_fields'] ?? []) as $sf) {
            if (!is_array($sf)) {
                continue;
            }
            $label = trim((string)($sf['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $summaryFields[] = [
                'field_key'  => (string)($sf['field_key'] ?? ''),
                'field_name' => (string)($sf['field_name'] ?? ''),
                'label'      => $label,
                'severity'   => (string)($sf['severity'] ?? 'normal'),
            ];
            if (count($summaryFields) >= 5) {
                break;
            }
        }

        $subtitle = implode(' · ', array_map(static fn ($s) => $s['label'], $summaryFields));
        $parts = array_filter([
            $name,
            $subtitle,
            $imei !== '' ? ('IMEI ' . $imei) : '',
        ], static fn ($v) => $v !== '');
        $identityText = implode('  ｜  ', $parts);

        return [
            'device_id'     => $deviceId,
            'name'          => $name,
            'imei'          => $imei,
            'summary_fields' => $summaryFields,
            'subtitle'      => $subtitle,
            'identity_text' => $identityText,
        ];
    }

    /** 空身份占位(口径统一,前端无需判空) */
    private function emptyDeviceIdentity(): array
    {
        return [
            'device_id'     => 0,
            'name'          => '',
            'imei'          => '',
            'summary_fields' => [],
            'subtitle'      => '',
            'identity_text' => '',
        ];
    }

    /** 把 模型 $json 字段(可能是 stdClass / json字符串 / 数组)统一转成深层数组 */
    private function toArr($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_object($value)) {
            $decoded = json_decode(json_encode($value), true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * 扫码台按设备 ID / IMEI / SN 查询本地设备记录
     * @param string $keyword
     * @param int $limit
     * @return array
     */
    public function scanSearch(string $keyword, int $limit = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $limit = max(1, min($limit, 50));

        $list = (new RecycleDevice())
            ->where('site_id', '=', $this->site_id)
            ->where(function ($query) use ($keyword) {
                if (ctype_digit($keyword)) {
                    $query->whereOr('id', '=', (int)$keyword);
                }
                $query->whereOr('imei', '=', $keyword)
                    ->whereOr('imei2', '=', $keyword)
                    ->whereOr('sn', '=', $keyword)
                    ->whereOr('user_sn', '=', $keyword);
            })
            ->with(['order.member'])
            ->append(['status_name', 'pay_status_name', 'confirm_status_name', 'dispose_type_name', 'dispose_status_name'])
            ->order('pay_time desc, update_at desc, create_at desc, id desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return array_map(function ($item) {
            return $this->formatScanDeviceItem($item);
        }, $list);
    }

    /**
     * 格式化扫码候选项，给前端一个明确的选择标识
     * @param array $item
     * @return array
     */
    private function formatScanDeviceItem(array $item): array
    {
        $order = $item['order'] ?? [];
        $member = $order['member'] ?? [];
        $milestone = $this->resolveScanMilestone($item, $order);

        return [
            'id' => $item['id'] ?? 0,
            'order_id' => $item['order_id'] ?? 0,
            'order_no' => $order['order_no'] ?? '',
            'model' => $item['model'] ?? '',
            'imei' => $item['imei'] ?? '',
            'imei2' => $item['imei2'] ?? '',
            'sn' => $item['sn'] ?? '',
            'user_sn' => $item['user_sn'] ?? '',
            'status' => $item['status'] ?? 0,
            'status_name' => $item['status_name'] ?? '',
            'pay_status' => $item['pay_status'] ?? 0,
            'pay_status_name' => $item['pay_status_name'] ?? '',
            'confirm_status' => $item['confirm_status'] ?? 0,
            'confirm_status_name' => $item['confirm_status_name'] ?? '',
            'dispose_type' => $item['dispose_type'] ?? '',
            'dispose_type_name' => $item['dispose_type_name'] ?? '',
            'dispose_status' => $item['dispose_status'] ?? 0,
            'dispose_status_name' => $item['dispose_status_name'] ?? '',
            'customer_name' => $member['nickname'] ?? $member['username'] ?? $order['customer_name'] ?? $order['sender_name'] ?? '',
            'customer_mobile' => $member['mobile'] ?? $order['customer_phone'] ?? $order['sender_mobile'] ?? '',
            'milestone_label' => $milestone['label'],
            'milestone_time' => $milestone['time'],
            'create_at' => $item['create_at'] ?? 0,
            'sign_at' => $order['sign_at'] ?? 0,
            'pay_time' => $item['pay_time'] ?? $order['pay_time'] ?? 0,
            'complete_at' => $order['complete_at'] ?? 0,
        ];
    }

    /**
     * 多条 IMEI 记录选择时优先展示最后关键节点
     * @param array $device
     * @param array $order
     * @return array{label: string, time: mixed}
     */
    private function resolveScanMilestone(array $device, array $order): array
    {
        $candidates = [
            ['label' => '回收时间', 'time' => $order['complete_at'] ?? 0],
            ['label' => '打款时间', 'time' => $device['pay_time'] ?? $order['pay_time'] ?? 0],
            ['label' => '签收时间', 'time' => $order['sign_at'] ?? 0],
            ['label' => '创建时间', 'time' => $device['create_at'] ?? $order['create_at'] ?? 0],
        ];

        foreach ($candidates as $item) {
            if (!empty($item['time'])) {
                return $item;
            }
        }

        return ['label' => '', 'time' => ''];
    }

    /**
     * 获取设备完整操作链路日志，包含设备主日志和关联代卖日志
     * @param int $deviceId
     * @param int $limit
     * @return array
     */
    public function getTimelineLogs(int $deviceId, int $limit = 50): array
    {
        $deviceLogs = (new RecycleDeviceLog())
            ->getDeviceLogList(['device_id' => $deviceId], 1, $limit, 'id desc')['list'] ?? [];

        $timeline = [];
        $deviceActionKeys = [];
        foreach ($deviceLogs as $log) {
            $log['source_type'] = 'device';
            $log['source_id'] = (int)($log['id'] ?? 0);
            $log['sort_time'] = (int)($log['create_at'] ?? 0);
            $timeline[] = $log;

            $operationType = (string)($log['operation_type'] ?? '');
            $action = (string)($log['action'] ?? '');
            if ($operationType !== '') {
                $deviceActionKeys[$operationType] = true;
            }
            if ($action !== '') {
                $deviceActionKeys[$action] = true;
            }
        }

        $consignmentLogs = (new RecycleConsignmentLog())
            ->where([
                ['site_id', '=', $this->site_id],
                ['source_device_id', '=', $deviceId],
            ])
            ->order('id desc')
            ->limit($limit)
            ->select()
            ->toArray();

        foreach ($consignmentLogs as $log) {
            $action = (string)($log['action'] ?? '');

            // 转代卖和结算已写入设备主日志时，避免在设备详情里出现重复节点。
            if (
                ($action === 'create' && (isset($deviceActionKeys['device_consignment']) || isset($deviceActionKeys['transfer_consignment']))) ||
                ($action === 'settle' && (isset($deviceActionKeys['consignment_payment']) || isset($deviceActionKeys['consignment_settle'])))
            ) {
                continue;
            }

            $timeline[] = $this->formatConsignmentTimelineLog($log);
        }

        usort($timeline, function ($a, $b) {
            $timeCompare = (int)($b['sort_time'] ?? 0) <=> (int)($a['sort_time'] ?? 0);
            if ($timeCompare !== 0) {
                return $timeCompare;
            }
            return (int)($b['source_id'] ?? 0) <=> (int)($a['source_id'] ?? 0);
        });

        return array_slice(array_map(function ($log) {
            unset($log['sort_time']);
            return $log;
        }, $timeline), 0, $limit);
    }

    private function formatConsignmentTimelineLog(array $log): array
    {
        $action = (string)($log['action'] ?? '');
        $before = $this->normalizeLogData($log['before_data'] ?? []);
        $after = $this->normalizeLogData($log['after_data'] ?? []);
        $remark = trim((string)($log['remark'] ?? ''));

        return [
            'id' => 'consignment-' . (int)($log['id'] ?? 0),
            'source_type' => 'consignment',
            'source_id' => (int)($log['id'] ?? 0),
            'device_id' => (int)($log['source_device_id'] ?? 0),
            'order_id' => (int)($log['source_order_id'] ?? 0),
            'operator_id' => (int)($log['operator_id'] ?? 0),
            'operator_name' => (string)($log['operator_name'] ?? '未知操作员'),
            'operation_type' => 'consignment_' . $action,
            'action' => $action,
            'old_status' => (int)($log['old_status'] ?? 0),
            'new_status' => (int)($log['new_status'] ?? 0),
            'status_name' => RecycleConsignmentDict::getActionName($action),
            'remark' => $this->buildConsignmentTimelineRemark(
                $action,
                $before,
                $after,
                $remark,
                (int)($log['old_status'] ?? 0),
                (int)($log['new_status'] ?? 0)
            ),
            'create_at' => (int)($log['create_time'] ?? 0),
            'sort_time' => (int)($log['create_time'] ?? 0),
        ];
    }

    private function normalizeLogData($data): array
    {
        if (is_array($data)) {
            return $data;
        }
        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function buildConsignmentTimelineRemark(
        string $action,
        array $before,
        array $after,
        string $remark = '',
        int $oldStatusValue = 0,
        int $newStatusValue = 0
    ): string
    {
        $consignmentNo = (string)($after['consignment_no'] ?? $before['consignment_no'] ?? '');
        $parts = [];
        $prefixMap = [
            'create' => '设备转入代卖',
            'listing' => '代卖上架',
            'sold' => '代卖成交',
            'settle' => '代卖结算',
            'cancel' => '取消代卖',
            'return' => '代卖退回',
            'notify' => '代卖通知',
        ];
        $parts[] = $prefixMap[$action] ?? RecycleConsignmentDict::getActionName($action);

        if ($consignmentNo !== '') {
            $parts[] = '代卖单号: ' . $consignmentNo;
        }

        $oldStatus = isset($before['status']) ? RecycleConsignmentDict::getStatus((int)$before['status']) : RecycleConsignmentDict::getStatus($oldStatusValue);
        $newStatus = isset($after['status']) ? RecycleConsignmentDict::getStatus((int)$after['status']) : RecycleConsignmentDict::getStatus($newStatusValue);
        if ($oldStatus !== '' && $newStatus !== '' && $oldStatus !== $newStatus) {
            $parts[] = '状态变更: ' . $oldStatus . ' → ' . $newStatus;
        }

        $priceParts = [];
        $this->appendPriceChange($priceParts, '挂牌价', $before['listing_price'] ?? null, $after['listing_price'] ?? null);
        $this->appendPriceChange($priceParts, '成交价', $before['sold_price'] ?? null, $after['sold_price'] ?? null);
        $this->appendPriceChange($priceParts, '结算金额', $before['settlement_amount'] ?? null, $after['settlement_amount'] ?? null);
        $this->appendPriceChange($priceParts, '服务费', $before['service_fee'] ?? null, $after['service_fee'] ?? null);
        if (!empty($priceParts)) {
            $parts[] = implode(' | ', $priceParts);
        }

        if ($remark !== '') {
            $parts[] = '备注: ' . $remark;
        }

        return implode(' | ', array_filter($parts, static fn($part) => $part !== ''));
    }

    private function appendPriceChange(array &$parts, string $label, $beforeValue, $afterValue): void
    {
        if ($afterValue === null || $afterValue === '') {
            return;
        }

        $beforeAmount = round((float)$beforeValue, 2);
        $afterAmount = round((float)$afterValue, 2);
        if ($beforeValue !== null && $beforeValue !== '' && $beforeAmount !== $afterAmount) {
            $parts[] = sprintf('%s: %.2f → %.2f', $label, $beforeAmount, $afterAmount);
            return;
        }

        if ($afterAmount > 0) {
            $parts[] = sprintf('%s: %.2f', $label, $afterAmount);
        }
    }


    /**
     * 添加设备
     * @param array $data
     * @return int
     */
    public function add(array $data): int
    {
        $data['status'] = RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK;
        
        $data['category_id'] = (int)($data['category_id'] ?? 0);
        
        $model = new RecycleDevice();
        $model->save($data);
        $this->incrementCategorySelectCount((int)$data['category_id']);
        return $model->id;
    }

    private function incrementCategorySelectCount(int $categoryId): void
    {
        if ($categoryId <= 0) {
            return;
        }
        (new RecycleDeviceModelDictService())->incrementSelectCount($categoryId);
    }


    /**
     * sign更新设备信息
     * @param int $id 设备ID
     * @param array $data 更新数据
     * @return bool
     */
    public function signUpdate(int $id, array $data): bool
    {
        $model = RecycleDevice::find($id);
        if (empty($model)) {
            return false;
        }
        $saved = $model->save($data);
        if ($saved && isset($data['category_id'])) {
            $this->incrementCategorySelectCount((int)$data['category_id']);
        }
        return $saved;
    }

    /**
     * 更新设备价格
     * @param int $id 设备ID
     * @param array $data 价格数据
     * @return bool
     * @throws CommonException
     */
    public function updatePrice(int $id, array $data): bool
    {
        try {
            $device = RecycleDevice::findOrEmpty($id);
            if ($device->isEmpty()) {
                throw new CommonException('DEVICE_NOT_FOUND');
            }

            // 记录原始状态
            $originalStatus = $device->status;

            // 更新设备价格信息
            $updateData = [
                'final_price' => $data['final_price'],
                'price_uid' => $data['price_uid'] ?? $this->uid,
                'price_at' => time(),
                'update_at' => time()
            ];

            if (isset($data['price_remark'])) {
                $updateData['price_remark'] = $data['price_remark'];
            }

            // 如果设备当前状态允许，更新为待确认状态
            if (in_array($originalStatus, [
                RecycleOrderDict::DEVICE_STATUS_CHECKED,
                RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM
            ])) {
                $updateData['status'] = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
            }

            $device->save($updateData);

            // 记录设备定价日志
            $priceData = [
                'old_status' => $originalStatus,
                'initial_price' => $device->initial_price,
                'final_price' => $data['final_price'],
                'price_reason' => $data['price_remark'] ?? '',
                'order_id' => $device->order_id
            ];
            $this->logService->logDevicePrice($device->id, $priceData, $data['price_remark'] ?? '');

            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }
    /**
     * 更新设备信息
     * @param int $id
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function update(int $id, array $data): bool
    {
        
       
        // 开启事务
        Db::startTrans();
        try {
            $device = RecycleDevice::where([['id', '=', $id], ['site_id', '=', $this->site_id]])->lock(true)->findOrEmpty();
            if ($device->isEmpty()) {
                throw new CommonException('DEVICE_NOT_FOUND');
            }
            if (isset($data['info']) || isset($data['summary']) || isset($data['device_readings'])
                || isset($data['imei']) || isset($data['imei2']) || isset($data['sn']) || isset($data['serial_number'])) {
                $oldInfo = DeviceReadingArchive::decode($device->info);
                $inputInfo = DeviceReadingArchive::decode($data['info'] ?? []);
                // 原始证据只经专用契约追加，普通 info 更新不可覆盖或删除原文。
                unset($inputInfo['device_readings']);
                $summary = DeviceSummaryHelper::normalizeSummary($data['summary'] ?? $inputInfo['sign_summary'] ?? []);
                $identity = array_replace($device->toArray(), $data);
                if (array_key_exists('serial_number', $data)) $data['sn'] = (string)$data['serial_number'];
                $identity = array_replace($identity, $data);
                $categoryPath = DeviceSummaryHelper::normalizeCategoryPath($inputInfo['goods_category'] ?? $oldInfo['goods_category'] ?? [], (int)$identity['category_id']);
                $data['info'] = DeviceSummaryHelper::buildInfo(array_replace($oldInfo, $inputInfo), $categoryPath, $summary, $identity, (int)$this->site_id);
                $data = array_replace($data, DeviceSummaryHelper::reservedColumns($summary, $identity));
            }
            unset($data['summary'], $data['device_readings'], $data['serial_number'], $data['battery_health'], $data['battery_cycle']);
            
            $currentStatus = $device->status;
            $targetStatus = $data['status'] ?? $currentStatus;
            
            // 检查状态流转是否合法
            if ($currentStatus != $targetStatus && !RecycleOrderDict::isValidStatusTransition($currentStatus, $targetStatus, 'device')) {
                throw new CommonException('INVALID_STATUS_TRANSITION');
            }


           
            
           // 处理质检和定价逻辑
            if (isset($data['final_price']) && $data['final_price'] > 0) {

            
                // 如果设置了最终价格，且当前状态是质检中或已质检，则自动将状态更新为待确认
                if ($currentStatus == RecycleOrderDict::DEVICE_STATUS_CHECKING || 
                    $currentStatus == RecycleOrderDict::DEVICE_STATUS_CHECKED) {
                    $data['status'] = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
                    $targetStatus = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
                
            
                    // 获取设备所属的订单ID
                    $orderId = $device['order_id'];
            
                    // 查询订单下所有设备的数量
                    $totalDevices = $this->model->where([
                        ['order_id', '=', $orderId],
                    ])->count();
                    
                    // 查询订单下状态为待确认(4)的设备数量
                    $pendingConfirmDevices = $this->model->where([
                        ['order_id', '=', $orderId],
                        ['status', '=', RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM],
                    ])->count();
                    
                    // 查询订单下状态为已回收(5)的设备数量
                    $recycledDevices = $this->model->where([
                        ['order_id', '=', $orderId],
                        ['status', '=', RecycleOrderDict::DEVICE_STATUS_RECYCLED],
                    ])->count();
                    
                    // 统计当前设备之外的非待确认设备数量
                    $nonPendingConfirmDevices = $totalDevices - $pendingConfirmDevices;
                    
                    // 当前设备要更新为待确认状态，所以非待确认设备数量减1
                    if ($currentStatus != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM) {
                        $nonPendingConfirmDevices -= 1;
                    }
                    
                    // 计算待确认和已回收的设备总数
                    $confirmedAndRecycledDevices = $pendingConfirmDevices + $recycledDevices;
                    // 如果当前设备将更新为待确认，则数量加1
                    if ($currentStatus != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM) {
                        $confirmedAndRecycledDevices += 1;
                    }
                    
                    // 如果该设备更新为待确认后，所有设备都将是待确认状态
                    // 或者所有设备都是待确认或已回收状态
                    if ($nonPendingConfirmDevices <= 0 || $confirmedAndRecycledDevices >= $totalDevices) {
                        try {
                            // 查询当前订单状态
                            $currentOrder = RecycleOrder::findOrEmpty($orderId);
                            $currentOrderStatus = $currentOrder->isEmpty() ? 0 : $currentOrder->status;
                            
                            // 更新订单状态为待确认
                            $orderModel = new RecycleOrder();
                            $updateResult = $orderModel->where([
                                ['id', '=', $orderId],
                                ['site_id', '=', $this->site_id]
                            ])->update([
                                'status' => RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM,
                                'update_at' => time()
                            ]);
                            
                        // 提示: 用户处理已完成定价的设备
                            Log::record('【确认通知】Order->handle 获取订单信息: '.$orderId , 'notice');
                        // 触发订单同意通知
                            $this->notifyService->orderAgreeNotify(['order_id' => $orderId, "site_id"=>$this->site_id]);
                            
                            
                        } catch (\Exception $e) {
                        Log::record('更新订单状态异常: ' . $e->getMessage(), 'error');
                        }
                    } else {
                        // 记录设备状态统计，方便调试
                    Log::record('设备状态: 非待确认数=' . $nonPendingConfirmDevices . 
                            ', 待确认和已回收总数=' . $confirmedAndRecycledDevices . 
                            ', 总设备数=' . $totalDevices, 'debug');
                    }
                } 
            
            } else {
                    
                    Log::record('未设置最终价格，跳过状态更新逻辑 - 设备ID: ' . $id, 'debug');
            }
            
            // 记录状态变更日志
            if (isset($data['status'])) {
                $this->addDeviceLog($device->id, $currentStatus, $data['status'], $data['remark'] ?? '', $device->order_id);
            }
            // 判断之前是否完成了定价 也就是 final_price > 0 
            // 如果是重新定价 需要将之前的定价的数据 存到 before_price 字段中 并能完成数据的更新
            $isRepricing = false;
            if (!empty($device->final_price) && $device->final_price > 0) {
                $isRepricing = true;
                // 如果before_price字段为空或为0，才保存原始价格
                if (empty($device->before_price) || $device->before_price == 0) {
                    $data['before_price'] = $device->final_price;
                }else{
                    $data['before_price'] =  $device->before_price.','.$device->final_price;
                }
                // 如果不等于 0 则把 before_price之前的值和  $device->final_price 以 , 拼接
               
            }
            

            // 重新定价会把重新定价的uid 追加到price_uid
            if (isset($data['final_price'])) {
                $data['price_uid'] = $this->uid;
                $data['price_at'] = time(); // 添加定价时间
            }
            
            // 更新设备信息
            $device->save($data);
            
            // 同步更新订单状态（如果需要）
            if (isset($data['status']) || isset($data['final_price'])) {
                $this->syncOrderStatus($device->order_id, $targetStatus);
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 删除设备
     * @param int $id
     * @return bool
     */
    public function delete(int $id)
    {
        
        return (new RecycleDevice())->where([['id', '=', $id]])->delete();
    }

    /**
     * 批量删除设备
     * @param array $ids
     * @return bool
     */
    public function deleteByIds(array $ids): bool
    {
        return (new RecycleDevice())->whereIn('id', $ids)->delete();
    }

    /**
     * 开始质检设备
     * @param int $id 设备ID
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function startCheck(int $id, string $remark = ''): bool
    {
        // 获取设备信息
        $device = RecycleDevice::findOrEmpty($id);
        if ($device->isEmpty()) {
            throw new CommonException('DEVICE_NOT_FOUND');
        }
        
        // 获取订单信息
        $order = RecycleOrder::findOrEmpty($device->order_id);
        if ($order->isEmpty()) {
            throw new CommonException('ORDER_NOT_FOUND');
        }
        
        // 检查订单状态，确保只有已签收的订单才能进行质检
        if ($order->status == RecycleOrderDict::ORDER_STATUS_PENDING_SIGN) {
            throw new CommonException('请先签收订单后再进行质检');
        }
        
        // 更新设备状态为质检中
        $device->status = RecycleOrderDict::DEVICE_STATUS_CHECKING;
        $device->check_uid = $this->uid;
        $device->save();
        
        // 记录质检开始日志
        if (!isset($this->logService)) {
            $this->logService = new CoreRecycleDeviceLogService();
        }
        $this->logService->logDeviceCheckStart($id, $remark);
        
        return true;
    }

    /**
     * 完成质检设备
     * @param int $id 设备ID
     * @param array $checkData 质检数据
     * @param string $remark 备注
     * @param string $action 操作类型：check=完成质检，save_draft=暂存质检
     * @return bool
     * @throws CommonException
     */
    public function completeCheck(int $id, array $checkData, string $remark = '', string $action = 'check', int $nextAssigneeUid = 0)
    {


        // 开启事务
        Db::startTrans();
        try {
            $device = RecycleDevice::findOrEmpty($id);
            if ($device->isEmpty()) {
                throw new CommonException('DEVICE_NOT_FOUND');
            }

            // 获取订单信息
            $order = RecycleOrder::findOrEmpty($device->order_id);
            if ($order->isEmpty()) {
                throw new CommonException('ORDER_NOT_FOUND');
            }

            // 检查订单状态，确保只有已签收的订单才能进行质检
            if ($order->status == RecycleOrderDict::ORDER_STATUS_PENDING_SIGN) {
                throw new CommonException('请先签收订单后再进行质检');
            }

            // 检查当前状态，如果不是质检中状态，则自动开始质检
            if ($device->status != RecycleOrderDict::DEVICE_STATUS_CHECKING) {
                // 如果是待质检状态，则自动开始质检
                if ($device->status == RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK) {
                    // 先将设备状态更新为质检中
                    $device->status = RecycleOrderDict::DEVICE_STATUS_CHECKING;
                    $device->save();

                    // 记录质检开始日志
                    if (!isset($this->logService)) {
                        $this->logService = new CoreRecycleDeviceLogService();
                    }
                    $this->logService->logDeviceCheckStart($device->id, '自动开始质检');
                } else {
                    throw new CommonException('DEVICE_STATUS_ERROR');
                }
            }

            // 根据 action 确定目标状态
            if ($action === 'save_draft') {
                // 暂存质检：保持质检中状态
                $targetStatus = RecycleOrderDict::DEVICE_STATUS_CHECKING;
            } else {
                // 完成质检：根据check_status确定目标状态
                $targetStatus = RecycleOrderDict::DEVICE_STATUS_CHECKED;
                if (isset($checkData['check_status'])) {
                    if ($checkData['check_status'] == 2) { // 假设2表示退回
                        $targetStatus = RecycleOrderDict::DEVICE_STATUS_RETURNED;
                    } else if (isset($checkData['final_price']) && $checkData['final_price'] > 0) {
                        $targetStatus = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;

                    }
                    // 移除check_status，避免保存到数据库
                    unset($checkData['check_status']);
                } else if (isset($checkData['final_price']) && $checkData['final_price'] > 0) {
                    $targetStatus = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
                }
            }



            // 更新设备质检信息
            $updateData = [
                'status' => $targetStatus,
                'check_uid'=>  $this->uid,
                'remark' => $remark,
            ];

            // 只有完成质检时才更新 check_at
            if ($action !== 'save_draft') {
                $updateData['check_at'] = time();
            }

            // if model
            if (isset($checkData['model'])) {
                $updateData['model'] = $checkData['model'];
            }

            // 从 info 中自动提取 system_version 和 warranty_info（如果前端未手动填写）
            if (isset($checkData['info']) && is_array($checkData['info'])) {
                $infoData = $checkData['info'];
                // 系统版本
                if (empty($checkData['system_version']) && !empty($infoData['osVersion'])) {
                    $checkData['system_version'] = $infoData['osVersion'];
                }
                // 保修信息
                if (empty($checkData['warranty_info']) && !empty($infoData['coverage'])) {
                    $coverage = $infoData['coverage'];
                    $coverageStatus = $coverage['status'] ?? '';
                    if ($coverageStatus === 'Out Of Warranty') {
                        $checkData['warranty_info'] = '过保';
                    } elseif ($coverageStatus === 'Not Activated') {
                        $checkData['warranty_info'] = '未激活';
                    } elseif (!empty($coverage['date'])) {
                        $checkData['warranty_info'] = $coverage['date'];
                    } elseif (in_array($coverageStatus, ['In Warranty', 'Active'], true)) {
                        $checkData['warranty_info'] = '在保';
                    }
                }
                // 内存
                if (empty($checkData['capacity']) && !empty($infoData['capacity'])) {
                    $checkData['capacity'] = $infoData['capacity'];
                }
                // 颜色
                if (empty($checkData['color']) && !empty($infoData['color'])) {
                    $checkData['color'] = $infoData['color'];
                }
            }


            // 如果有最终价格 则 更新 'price_uid'=>  $this->uid,
            if (isset($checkData['final_price']) && $checkData['final_price'] > 0) {
                $updateData['price_uid'] =  $this->uid;
            }

            // 合并质检数据
            if (!empty($checkData)) {
                $updateData = array_merge($updateData, $checkData);
            }
            if (isset($checkData['info'])) {
                // Model 已声明 $json=['info']，save() 时会自动 json_encode，无需手动编码
                $oldInfo = DeviceReadingArchive::decode($device->info);
                $newInfo = DeviceReadingArchive::decode($checkData['info']);
                unset($newInfo['device_readings']);
                foreach (['battery', 'battery_num'] as $key) {
                    if (!array_key_exists($key, (array)($newInfo['check_meta'] ?? [])) && array_key_exists($key, (array)($oldInfo['check_meta'] ?? []))) {
                        $newInfo['check_meta'][$key] = $oldInfo['check_meta'][$key];
                    }
                }
                $updateData['info'] = array_replace($oldInfo, $newInfo);
            }

            $checkMeta = $this->extractCheckMeta($checkData);
            $templateId = (int)($checkData['check_template_id'] ?? ($checkMeta['template_id'] ?? 0));
            if ($templateId > 0) {
                $updateData['check_template_id'] = $templateId;
            }
            foreach (['check_result', 'check_result_seller', 'check_result_buyer'] as $resultField) {
                if (isset($updateData[$resultField])) {
                    $updateData[$resultField] = $this->normalizeCheckResultText((string)$updateData[$resultField], $checkMeta);
                }
            }

            $device->save($updateData);

            // 记录日志
            if (!isset($this->logService)) {
                $this->logService = new CoreRecycleDeviceLogService();
            }

            if ($action === 'save_draft') {
                // 暂存质检：状态保持质检中，不再记录为“开始质检”
                $this->logService->logDeviceCheckDraft($device->id, $remark);
            } else {
                // 完成质检：记录质检完成
                $this->logService->logDeviceCheckComplete($device->id, $checkData, $remark);
            }

            // 同步更新订单状态
            try {
                $this->syncOrderStatus($device->order_id, $targetStatus);
            } catch (\Exception $e) {
              Log::record('【调试日志】completeCheck同步订单状态异常: ' . $e->getMessage(), 'error');
            }
            
            // 触发质检完成事件（通过事件处理自动打印）
            try {
                $eventData = [
                    'device_id' => $id,
                    'site_id' => $this->site_id,
                    'status' => $targetStatus,
                    'order_id' => $device->order_id,
                    'uid' => $this->uid
                ];
                event('AfterDeviceCheckComplete', $eventData);
                Log::record('【质检完成】已触发质检完成事件: ' . json_encode($eventData), 'info');
            } catch (\Exception $e) {
                Log::record('【质检完成】触发事件异常: ' . $e->getMessage(), 'error');
            }

            // 根据打印场景配置自动打印标签。生产场景要求暂存质检数据后立即打印标签。
            try {
                $printResult = (new RecyclePrintSceneService())->autoPrintAfterDeviceCheck($id);
                Log::record('【自动打印】质检' . ($action === 'save_draft' ? '暂存' : '完成') . '场景执行结果: ' . json_encode([
                    'device_id' => $id,
                    'action' => $action,
                    'success' => $printResult['success'] ?? false,
                    'can_print' => $printResult['can_print'] ?? false,
                    'message' => $printResult['message'] ?? '',
                ], JSON_UNESCAPED_UNICODE), 'info');
            } catch (\Exception $e) {
                Log::record('【自动打印】异常: ' . $e->getMessage(), 'error');
            }
            
            Db::commit();
            if ($action !== 'save_draft') {
                try {
                    $nextStage = RecycleStageDict::stageOf((int)$targetStatus);
                    if ($nextStage !== '') (new TaskService())->assignPreferredOrDefault($id, $nextStage, $nextAssigneeUid);
                } catch (\Throwable $e) {
                    Log::warning('质检后分配下一环节任务失败', ['device_id' => $id, 'message' => $e->getMessage()]);
                }
            }
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 提取质检元数据
     * @param array $checkData
     * @return array
     */
    private function extractCheckMeta(array $checkData): array
    {
        $info = $checkData['info'] ?? [];
        if (is_string($info)) {
            $decoded = json_decode($info, true);
            $info = is_array($decoded) ? $decoded : [];
        }

        $checkMeta = $info['check_meta'] ?? [];
        if (is_string($checkMeta)) {
            $decoded = json_decode($checkMeta, true);
            $checkMeta = is_array($decoded) ? $decoded : [];
        }

        return is_array($checkMeta) ? $checkMeta : [];
    }

    /**
     * 规范化质检结果文本：去重，并移除未开启锁项的自动生成文案
     * @param string $text
     * @param array $checkMeta
     * @return string
     */
    private function normalizeCheckResultText(string $text, array $checkMeta = []): string
    {
        if ($text === '') {
            return '';
        }

        $hasActivationLockMeta = array_key_exists('activation_lock', $checkMeta) || array_key_exists('activationLock', $checkMeta);
        $hasMdmLockMeta = array_key_exists('mdm_lock', $checkMeta) || array_key_exists('mdmLock', $checkMeta);
        $activationLockSelected = $this->truthyCheckMetaValue($checkMeta['activation_lock'] ?? $checkMeta['activationLock'] ?? false);
        $mdmLockSelected = $this->truthyCheckMetaValue($checkMeta['mdm_lock'] ?? $checkMeta['mdmLock'] ?? false);
        $items = preg_split('/[;\r\n]+/u', $text) ?: [];
        $results = [];

        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            if ($hasActivationLockMeta && !$activationLockSelected && $item === '激活锁开启') {
                continue;
            }
            if ($hasMdmLockMeta && !$mdmLockSelected && $item === '监管锁开启') {
                continue;
            }
            if (!in_array($item, $results, true)) {
                $results[] = $item;
            }
        }

        return implode(";\n", $results);
    }

    /**
     * 判断质检元数据中的布尔值
     * @param mixed $value
     * @return bool
     */
    private function truthyCheckMetaValue($value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 'true'
            || $value === '开启'
            || $value === '有锁'
            || $value === 'On';
    }

    /**
     * 确认设备价格
     * @param int $id 设备ID
     * @param float $price 价格
     * @param string $remark 备注
     * @param float|null $sellPrice 卖货价格
     * @return bool
     * @throws CommonException
     */
    public function confirmPrice(int $id, float $price, string $remark = '', ?float $sellPrice = null, array $refurbishment = []): bool
    {
        $capability = new RecycleErpCapabilityService();
        $erpManaged = $capability->isPaymentManaged($this->site_id, 0, [$id]);
        $placement = $erpManaged ? $capability->validateInboundPlacement(
            $this->site_id,
            (int)($refurbishment['target_warehouse_id'] ?? 0),
            (int)($refurbishment['target_location_id'] ?? 0),
            '',
            true
        ) : [];
        if (!empty($placement)) {
            $refurbishment['target_warehouse_id'] = $placement['warehouse_id'];
            $refurbishment['target_warehouse_name'] = $placement['warehouse_name'];
            $refurbishment['target_location_id'] = $placement['location_id'];
            $refurbishment['target_location_name'] = $placement['location_name'];
        }

        // 开启事务
        Db::startTrans();
        try {
            $device = $this->model->where('site_id', $this->site_id)->where('id', $id)->lock(true)->find();
            if (empty($device)) {
                throw new CommonException('设备不存在');
            }
            
            // 检查当前状态是否允许确认价格
            if (
                $device->status != RecycleOrderDict::DEVICE_STATUS_CHECKING &&
                $device->status != RecycleOrderDict::DEVICE_STATUS_CHECKED &&
                $device->status != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM
            ) {
                throw new CommonException('当前设备状态不允许确认价格');
            }
            
            

            $old_status = $device->status;
            $oldPrice = $device->final_price;
            $device->final_price = $price;
            if ($sellPrice !== null) {
                $device->sell_price = $sellPrice;
            }
            $refurbishmentData = $this->normalizeRefurbishmentDecision($refurbishment);
            $saleDestination = $this->normalizeSaleDestination($refurbishment['sale_destination'] ?? RecycleOrderDict::SALE_DESTINATION_MALL);
            $device->remark = $remark;
            $device->price_uid = $this->uid;
            $device->price_at = time(); // 添加定价时间
            $device->sale_destination = $saleDestination;
            // ERP 联动时已在事务前完成仓库/库位强校验；独立运行时保持固定渠道兼容。
            if (isset($refurbishment['target_warehouse_id'])) {
                $device->target_warehouse_id = (int)$refurbishment['target_warehouse_id'];
            }
            if (isset($refurbishment['target_warehouse_name'])) {
                $device->target_warehouse_name = (string)$refurbishment['target_warehouse_name'];
            }
            if (isset($refurbishment['target_location_id'])) {
                $device->target_location_id = (int)$refurbishment['target_location_id'];
            }
            if (isset($refurbishment['target_location_name'])) {
                $device->target_location_name = (string)$refurbishment['target_location_name'];
            }
            $device->refurbishment_required = $refurbishmentData['required'];
            $device->refurbishment_assignee_uid = $refurbishmentData['assignee_uid'];
            $device->refurbishment_assignee_name = $refurbishmentData['assignee_name'];
            $device->refurbishment_reason = $refurbishmentData['reason'];
            $device->refurbishment_items = $refurbishmentData['items'];
            $device->refurbishment_estimated_cost = $refurbishmentData['estimated_cost'];
            $device->status = RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM;
            $device->update_time = time();
            
            // 检查是否为重新定价
            $isRepricing = !empty($oldPrice) && $oldPrice != $price;
            if ($isRepricing) {
                $device->reprice_time = time();
            }
            $device->save();
            
            // 记录价格确认日志
            if (!isset($this->logService)) {
                $this->logService = new CoreRecycleDeviceLogService();
            }
            $priceData = [
                'old_status' => $old_status,
                'initial_price' => $device->initial_price,
                'final_price' => $price,
                'old_price' => $oldPrice,
                'price_change' => $price - ($oldPrice ?? 0),
                'is_repricing' => $isRepricing,
                'refurbishment_required' => $refurbishmentData['required'],
                'refurbishment_assignee_uid' => $refurbishmentData['assignee_uid'],
                'refurbishment_reason' => $refurbishmentData['reason'],
                'refurbishment_items' => $refurbishmentData['items'],
                'refurbishment_estimated_cost' => $refurbishmentData['estimated_cost'],
                'sale_destination' => $saleDestination,
                'order_id' => $device->order_id
            ];
            $this->logService->logDevicePrice($id, $priceData, $remark);

            // 记录整备负责人被选次数（用于下拉"常用优先"排序）；故障隔离，不影响定价
            if (!empty($refurbishmentData['required']) && (int)($refurbishmentData['assignee_uid'] ?? 0) > 0) {
                try {
                    $pickStat = new \addon\hsx_recycle\app\service\core\recycle_device\CoreRecyclePickStatService();
                    $pickStat->record(\addon\hsx_recycle\app\service\core\recycle_device\CoreRecyclePickStatService::SCENE_REFURB_ASSIGNEE, (int)$refurbishmentData['assignee_uid']);
                } catch (\Throwable $e) {
                }
            }
            
            // 尝试同步更新订单状态
            $this->syncOrderStatus($device->order_id, RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM);
            
            // 检查该订单下的所有设备是否都已确认价格
            $allDevices = $this->model->where('order_id', $device->order_id)->select();
            $allConfirmed = true;
            
            foreach ($allDevices as $dev) {
                if ($dev->status != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM && 
                    $dev->status != RecycleOrderDict::DEVICE_STATUS_RECYCLED) {
                    $allConfirmed = false;
                    break;
                }
            }
            
            $order = RecycleOrder::where([
                ['id', '=', $device->order_id],
                ['site_id', '=', $this->site_id],
            ])->findOrEmpty();
            $flowMode = $order->isEmpty()
                ? RecycleOrderDict::FLOW_MODE_ORDER
                : (new RecycleOrderFlowModeService())->getOrderFlowMode($order->toArray());

            if ($flowMode === RecycleOrderDict::FLOW_MODE_DEVICE) {
                $this->notifyService->orderAgreeNotify([
                    'order_id' => $device->order_id,
                    'site_id' => $this->site_id,
                    'device_ids' => [(int)$device->id],
                    'scene' => 'device_confirm',
                ]);
            } elseif ($allConfirmed) {
                $this->notifyService->orderAgreeNotify([
                    'order_id' => $device->order_id,
                    'site_id' => $this->site_id,
                    'scene' => 'order_confirm',
                ]);
            }
            
            Db::commit();
            try {
                (new TaskService())->assignPreferredOrDefault($id, RecycleStageDict::STAGE_CONFIRM, (int)($refurbishment['next_assignee_uid'] ?? 0));
            } catch (\Throwable $e) {
                Log::warning('定价后分配确认任务失败', ['device_id' => $id, 'message' => $e->getMessage()]);
            }
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    private function normalizeRefurbishmentDecision(array $data): array
    {
        $required = (int)($data['refurbishment_required'] ?? 0) === 1 ? 1 : 0;
        $assigneeUid = $required ? (int)($data['refurbishment_assignee_uid'] ?? 0) : 0;
        $assigneeName = '';

        if ($required && $assigneeUid <= 0) {
            throw new CommonException('请选择整备负责人');
        }
        if ($assigneeUid > 0) {
            $allowed = SysUserRole::where([
                ['site_id', '=', $this->site_id],
                ['uid', '=', $assigneeUid],
                ['status', '=', 1],
            ])->count();
            $user = SysUser::where([['uid', '=', $assigneeUid]])->field('uid,username,real_name')->findOrEmpty();
            if (!$allowed || $user->isEmpty()) {
                throw new CommonException('整备负责人不存在或不属于当前站点');
            }
            $assigneeName = (string)($user->real_name ?: $user->username ?: ('员工#' . $assigneeUid));
        }

        $items = $data['refurbishment_items'] ?? [];
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($items)) {
            $items = [];
        }

        $items = array_values(array_filter(array_map(function ($item) {
            if (is_array($item)) {
                $key = trim((string)($item['item_key'] ?? $item['key'] ?? ''));
                $name = trim((string)($item['item_name'] ?? $item['name'] ?? ''));
                $type = trim((string)($item['item_type'] ?? $item['type'] ?? 'other'));
            } else {
                $key = '';
                $name = trim((string)$item);
                $type = 'other';
            }
            if ($name === '') {
                return null;
            }
            return ['item_key' => $key, 'item_name' => $name, 'item_type' => $type ?: 'other'];
        }, $items)));

        return [
            'required' => $required,
            'assignee_uid' => $assigneeUid,
            'assignee_name' => $assigneeName,
            'reason' => $required ? trim((string)($data['refurbishment_reason'] ?? '')) : '',
            'items' => $required ? json_encode($items, JSON_UNESCAPED_UNICODE) : '[]',
            'estimated_cost' => $required ? round((float)($data['refurbishment_estimated_cost'] ?? 0), 2) : 0,
        ];
    }

    private function normalizeSaleDestination($value): string
    {
        $destination = trim((string)$value);
        $allowed = [
            RecycleOrderDict::SALE_DESTINATION_MALL,
            RecycleOrderDict::SALE_DESTINATION_PEER,
            RecycleOrderDict::SALE_DESTINATION_HOLD,
        ];
        if ($destination === '') {
            return RecycleOrderDict::SALE_DESTINATION_MALL;
        }
        if (!in_array($destination, $allowed, true)) {
            throw new CommonException('销售去向不正确');
        }
        return $destination;
    }

    /**
     * 回收设备
     * @param int $id
     * @param string $remark
     * @return bool
     */
    public function recycle(int $id, string $remark = '', bool $autoErpSync = true): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            $device = $this->model->find($id);
            if (empty($device)) {
                throw new CommonException('设备不存在');
            }
            
            // 只有待确认状态的设备可以回收
            if ($device->status != RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM) {
                throw new CommonException('当前设备状态不允许回收');
            }
            
            $old_status = $device->status;
            $device->status = RecycleOrderDict::DEVICE_STATUS_RECYCLED;
            $device->confirm_status = RecycleOrderDict::CONFIRM_STATUS_CONFIRMED;
            $device->confirm_time = time();
            $device->confirm_member_id = (int)($device->member_id ?? 0);
            $device->confirm_remark = $remark;
            $device->settlement_mode = RecycleOrderDict::DISPOSE_TYPE_RECYCLE;
            $device->dispose_type = RecycleOrderDict::DISPOSE_TYPE_RECYCLE;
            $device->dispose_status = RecycleOrderDict::DISPOSE_STATUS_RECYCLED;
            $device->update_time = time();
            $device->save();
            
            // 记录回收日志
            if (!isset($this->logService)) {
                $this->logService = new CoreRecycleDeviceLogService();
            }
            $this->logService->logDeviceRecycle($id, $remark);
            
            // 尝试同步更新订单状态
            $this->syncOrderStatus($device->order_id, RecycleOrderDict::DEVICE_STATUS_RECYCLED);

            Db::commit();

            if ((int)($device->refurbishment_required ?? 0) === 1) {
                try {
                    (new RecyclePrintSceneService())->autoPrintAfterRefurbishmentRequired((int)$device->id);
                } catch (\Throwable $printException) {
                    Log::warning('整备标签自动打印失败：' . $printException->getMessage(), [
                        'site_id' => $this->site_id,
                        'device_id' => (int)$device->id,
                    ]);
                }
            }

            // 确认回收即自动同步到 ERP(装了 ERP 时):设备带着定价选定的仓位进入 ERP，
            // 由 ERP 侧 A1 自动确认入库 → 进中台拍照定价。批量回收时由 batchRecycle 统一同步。
            if ($autoErpSync) {
                $this->dispatchAfterRecycle([(int)$device->id]);
            }

            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 已确认回收后的统一下游编排入口。
     *
     * 管理员确认、客户确认、批量确认都必须调用这里，避免只更新回收状态、
     * 却漏发 ERP 入库与财务应付事实。下游采用幂等键，可安全重复调用补偿。
     */
    public function dispatchAfterRecycle(array $deviceIds): void
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if (empty($deviceIds)) {
            return;
        }

        $this->autoSyncErpInbound($deviceIds);
        $this->autoEmitPayable($deviceIds);

        foreach ($deviceIds as $deviceId) {
            try {
                // 先让 ERP 落采购与应付，再通知财务，避免用户打开时目标账目尚不存在。
                (new TaskService())->assignPreferredOrDefault($deviceId, RecycleStageDict::STAGE_PAY);
            } catch (\Throwable $e) {
                Log::warning('确认回收后分配待打款任务失败', [
                    'site_id' => $this->site_id,
                    'device_id' => $deviceId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * 确认回收后自动同步设备到 ERP（故障隔离：ERP 未安装/同步失败都不影响回收）
     * @param array $deviceIds
     * @return void
     */
    private function autoSyncErpInbound(array $deviceIds): void
    {
        $deviceIds = array_values(array_filter(array_map('intval', $deviceIds)));
        if (empty($deviceIds)) {
            return;
        }
        try {
            $ownershipService = new RecyclePaymentOwnershipService();
            $ownership = $ownershipService->inspect($this->site_id, 0, $deviceIds);
            foreach ($ownership['devices'] as $deviceId => $device) {
                try {
                    if ($device['owner'] === 'local') {
                        $ownershipService->claim($this->site_id, 0, [(int)$deviceId], 'local');
                        continue;
                    }
                    if ($device['owner'] !== 'self_erp') throw new CommonException(RecyclePaymentOwnershipService::message('unknown'));
                    Log::info('确认回收开始同步ERP', ['site_id' => $this->site_id, 'device_id' => $deviceId]);
                    $result = (new RecycleDeviceErpSyncService())->dispatch([(int)$deviceId], ['self_erp']);
                    Log::info('确认回收同步ERP成功', ['site_id' => $this->site_id, 'device_id' => $deviceId,
                        'event_id' => (string)($result['event_id'] ?? ''), 'device_count' => (int)($result['device_count'] ?? 0)]);
                } catch (\Throwable $deviceError) {
                    // 一台异常不能阻断同一订单内其他已确认设备；失败原因同时由健康查询展示。
                    Log::error('确认回收后自动同步ERP失败：' . $deviceError->getMessage(), [
                        'site_id' => $this->site_id, 'device_id' => $deviceId,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('确认回收后自动同步ERP失败：' . $e->getMessage(), [
                'site_id' => $this->site_id,
                'device_ids' => $deviceIds,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * 确认回收即生成"应付"事实(我欠客户=回收价)，发给财务(ERP财务中心)。
     *
     * 解耦：只发标准事件 FinancePayableCreated，财务侧幂等落库；财务不在则无监听=空操作。
     * 故障隔离：发事件失败不影响回收。往来单位锚 = member_id(个人客户)，
     * 金额 = final_price(定价确认的回收价)，source_device_id = 设备ID(可精确追到哪台机)。
     * 与《应付与结算契约》一致。
     * @param array $deviceIds
     */
    private function autoEmitPayable(array $deviceIds): void
    {
        $deviceIds = array_values(array_filter(array_map('intval', $deviceIds)));
        if (empty($deviceIds)) {
            return;
        }
        try {
            $ownership = (new RecyclePaymentOwnershipService())->inspect($this->site_id, 0, $deviceIds);
            $devices = $this->model->where('site_id', $this->site_id)->where('id', 'in', $deviceIds)->select();
            foreach ($devices as $device) {
                if (($ownership['devices'][(int)$device->id]['owner'] ?? 'unknown') !== 'self_erp') continue;
                $amount = round((float)($device->final_price ?? 0), 2);
                $memberId = (int)($device->member_id ?? 0);
                if ($amount <= 0 || $memberId <= 0) {
                    continue; // 没定价或无客户的不发
                }
                $orderNo = '';
                $memberName = '';
                try {
                    $order = RecycleOrder::where('id', (int)$device->order_id)->find();
                    if (!empty($order)) {
                        $orderNo = (string)($order->order_no ?? '');
                        $memberName = (string)($order->customer_name ?? $order->member_name ?? $order->nickname ?? '');
                    }
                } catch (\Throwable $ignore) {
                }
                event('FinancePayableCreated', [
                    'event'             => 'finance.payable.created.v1',
                    'event_id'          => 'recycle_payable_' . (int)$device->id, // 幂等：每台一笔
                    'site_id'           => (int)$this->site_id,
                    'counterparty_id'   => $memberId,
                    'counterparty_name' => $memberName,
                    'amount'            => $amount,
                    'source_type'       => 'recycle_device',
                    'source_no'         => $orderNo !== '' ? $orderNo : ('DEV' . (int)$device->id),
                    'source_device_id'  => (int)$device->id,
                    'occurred_at'       => time(),
                    'operator_uid'      => (int)$this->uid,
                    'operator_name'     => (string)($this->username ?? ''),
                    'remark'            => '确认回收生成应付',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('确认回收生成应付失败：' . $e->getMessage(), [
                'site_id' => $this->site_id,
                'device_ids' => $deviceIds,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }


    /**
     * getList
     * 获取设备列表（不分页）
     * @param array $where 查询条件
     * @param string $field 查询字段
     * @param string $order 排序规则
     * @return array
     */
    public function getList(array $where = [], string $field = '*', string $order = ''): array
    {
        $search_model = new RecycleDevice();
        return $search_model->withSearch(['order_id', 'device_name', 'imei', 'model', 'status', 'create_at'], $where)
            ->field($field)
            ->order(empty($order) ? 'create_at desc' : $order)
            ->append(['status_name', 'check_images_thumb_small', 'check_images_seller_thumb_small', 'check_images_buyer_thumb_small'])
            ->select()
            ->toArray();
    }

    /**
     * 退回单个设备（原有方法修改，返回退货单ID）
     * 
     * @param int $id 设备ID
     * @param string $remark 备注信息 
     * @return array 包含return_order_id的数组
     */
    public function returnDevice(int $id, string $remark = ''): array
    {
        Db::startTrans();
        try {
            // 获取设备信息
            $device = RecycleDevice::where('id', $id)->find();
            if (!$device) {
                throw new CommonException('设备不存在');
            }
            
            // 确保设备有订单关联
            if (empty($device->order_id)) {
                throw new CommonException('设备未关联订单');
            }
            
            // 获取订单信息
            $order = RecycleOrder::where('id', $device->order_id)->find();
            if (!$order) {
                throw new CommonException('关联的订单不存在');
            }
            
            // 保存设备原始状态用于日志记录
            $oldStatus = $device->status;
            
            // 检查是否已经存在退货单
            $existingReturnOrder = RecycleReturnOrder::where('order_id', $device->order_id)->find();
            if ($existingReturnOrder) {
                // 如果已经存在退货单，直接使用
                $returnOrderId = $existingReturnOrder->id;
                Log::info("找到现有退货单ID: {$returnOrderId} 关联订单ID: {$device->order_id}");
            } else {
                // 创建新的退货单
                $returnOrderData = [
                    'order_id' => $device->order_id,
                    'order_no' => $order->order_no,
                    'site_id' => $order->site_id,
                    'member_id' => $order->member_id,
                    'status' => RecycleReturnOrderDict::ORDER_STATUS_PENDING, // 使用RecycleReturnOrderDict中的常量
                    'create_at' => time(),
                    
                ];
                
                $returnOrderModel = new RecycleReturnOrder();
                $returnOrderId = $returnOrderModel->insertGetId($returnOrderData);
                
                if (!$returnOrderId) {
                    throw new CommonException('创建退货单记录失败');
                }
                
                Log::info("创建新退货单ID: {$returnOrderId} 关联订单ID: {$device->order_id}");
            }
            
            // 更新设备状态为退回中
            $device->status = RecycleReturnOrderDict::DEVICE_STATUS_RETURNING; // 使用RecycleOrderDict中的常量
            $device->settlement_mode = RecycleOrderDict::DISPOSE_TYPE_RETURN;
            $device->dispose_type = RecycleOrderDict::DISPOSE_TYPE_RETURN;
            $device->dispose_status = RecycleOrderDict::DISPOSE_STATUS_RETURNED;
            $device->return_order_id = $returnOrderId;
            $device->return_time = time();
            $device->return_remark = $remark;
            
            if (!$device->save()) {
                throw new CommonException('更新设备状态失败');
            }
            
            // 记录设备退回日志
            if (!isset($this->logService)) {
                $this->logService = new CoreRecycleDeviceLogService();
            }
            $this->logService->logDeviceReturn($id, $remark, $remark);
            
            // 添加设备到退货单关联表
            $returnDeviceModel = new RecycleReturnDevice();
            $returnDeviceData = [
                'return_order_id' => $returnOrderId,
                'device_id' => $id,
                'create_time' => time(),
                'remark' => $remark,
            ];
            
            if (!$returnDeviceModel->save($returnDeviceData)) {
                throw new CommonException('关联设备到退货单失败');
            }
            
            // 同步更新订单状态
            $this->syncOrderStatus($device->order_id, RecycleReturnOrderDict::DEVICE_STATUS_RETURNING);
            
            Db::commit();
            return ['return_order_id' => $returnOrderId];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("退回设备失败（设备ID: {$id}）: " . $e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 更新设备状态
     * @param int $id 设备ID
     * @param int $status 状态
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function updateStatus(int $id, int $status, string $remark = ''): bool
    {
        Db::startTrans();
        try {
            $device = RecycleDevice::findOrEmpty($id);
            if ($device->isEmpty()) {
                throw new CommonException('DEVICE_NOT_FOUND');
            }
            
            // 检查状态流转是否合法
            if (!RecycleOrderDict::isValidStatusTransition($device->status, $status, 'device')) {
                throw new CommonException('INVALID_STATUS_TRANSITION');
            }
            
            // 更新设备状态
            $device->status = $status;
            $device->final_status = 1;
            $device->save();
            
            // 记录日志
            $this->addDeviceLog($id, $status, $remark);
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 批量回收设备
     * @param array $ids 设备ID数组
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function batchRecycle(array $ids, string $remark = ''): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            foreach ($ids as $id) {
                // 批量模式下逐台跳过同步，待整批提交后统一同步，避免在外层事务内产生跨插件副作用
                $this->recycle((int)$id, $remark, false);
            }

            Db::commit();
            // 整批提交成功后再统一同步到 ERP + 生成应付
            $this->dispatchAfterRecycle(array_map('intval', $ids));
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 批量退回设备
     * @param array $ids 设备ID数组
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function batchReturn(array $ids, string $remark = ''): bool
    {
        if (empty($ids)) {
            throw new CommonException('请选择要退回的设备');
        }
        
        // 开启事务
        Db::startTrans();
        try {
            $success = 0;
            $errors = [];
            
            // 按订单ID分组处理设备
            $devicesByOrder = [];
            
            // 1. 先获取所有设备信息并按订单ID分组
            $devices = RecycleDevice::whereIn('id', $ids)->select();
            foreach ($devices as $device) {
                $deviceId = $device->id;
                $orderId = $device->order_id;
                
                if (empty($orderId)) {
                    $errors[] = "设备ID: {$deviceId}, 错误: 设备未关联订单";
                    continue;
                }
                
                if (!isset($devicesByOrder[$orderId])) {
                    $devicesByOrder[$orderId] = [];
                }
                $devicesByOrder[$orderId][] = $device;
            }
            
            // 2. 检查每个订单是否已有退货单
            $returnOrderByOrderId = [];
            $orderIds = array_keys($devicesByOrder);
            
            if (!empty($orderIds)) {
                // 查询已存在的退货单
                $existingReturnOrders = RecycleReturnOrder::whereIn('order_id', $orderIds)->select();
                foreach ($existingReturnOrders as $returnOrder) {
                    $returnOrderByOrderId[$returnOrder->order_id] = $returnOrder;
                }
            }
            
            // 3. 按订单处理设备退回
            foreach ($devicesByOrder as $orderId => $orderDevices) {
                try {
                    // 检查此订单是否已有退货单
                    $returnOrderId = null;
                    if (isset($returnOrderByOrderId[$orderId])) {
                        // 使用已存在的退货单
                        $returnOrderId = $returnOrderByOrderId[$orderId]->id;
                        Log::info("使用已存在的退货单 ID: {$returnOrderId} 处理订单 ID: {$orderId} 的设备");
                    }
                    
                    // 处理当前订单下的所有设备
                    foreach ($orderDevices as $device) {
                        if ($returnOrderId) {
                            // 添加到已有退货单
                            $this->appendDeviceToReturnOrder($device->id, $returnOrderId, $remark);
                        } else {
                            // 创建新的退货单
                            $returnOrderId = $this->createReturnOrderForDevice($device->id, $remark);
                            // 记录新创建的退货单，以便后续设备使用
                            if ($returnOrderId) {
                                $returnOrderByOrderId[$orderId] = (object)['id' => $returnOrderId];
                            }
                        }
                        $success++;
                    }
                } catch (\Exception $e) {
                    // 记录订单级别的处理失败，但继续处理其他订单
                    $errors[] = "订单ID: {$orderId}, 错误: " . $e->getMessage();
                    Log::error("订单退回失败: 订单ID {$orderId}, 错误: " . $e->getMessage());
                }
            }
            
            // 如果所有设备都失败，则抛出异常
            if ($success === 0 && !empty($errors)) {
                $errorMsg = '所有设备退回失败: ' . implode('; ', $errors);
                Log::error($errorMsg);
                throw new CommonException($errorMsg);
            }
            
            // 如果部分成功，记录日志
            if (!empty($errors)) {
                $warningMsg = '部分设备退回失败: ' . implode('; ', $errors);
                Log::warning($warningMsg);
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 批量更新设备状态
     * @param array $ids 设备ID数组
     * @param int $status 目标状态
     * @param string $remark 备注
     * @return bool
     * @throws CommonException
     */
    public function batchUpdateStatus(array $ids, int $status, string $remark = ''): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            foreach ($ids as $id) {
                $this->updateStatus((int)$id, $status, $remark);
            }
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 添加设备日志
     * @param int $deviceId 设备ID
     * @param int $fromStatus 原状态
     * @param int $toStatus 新状态
     * @param string $remark 备注
     * @param int $orderId 订单ID
     * @return int 日志ID
     */
    public function addDeviceLog(int $deviceId, int $fromStatus, int $toStatus, string $remark = '', int $orderId = 0): int
    {

        $log = new RecycleDeviceLog();
        $log->save([
            'site_id' => $this->site_id,
            'device_id' => $deviceId,
            'order_id' => $orderId,
            'operator_id' => $this->uid ?? 0,
            'operator_name' => '',
            'action' => 'status_change',
            'old_status' => $fromStatus,
            'new_status' => $toStatus,
            'remark' => $remark ?? '',
            'create_at' => time()
        ]);
        return $log->id;
    }

    /**
     * 同步更新订单状态
     * @param int $orderId 订单ID
     * @param int $deviceStatus 设备状态
     * @return bool
     */
    protected function syncOrderStatus(int $orderId, int $deviceStatus): bool
    {
        
        // 获取订单信息
        $order = RecycleOrder::findOrEmpty($orderId);
        if ($order->isEmpty()) {
            return f已闭环e;
        }
        
        // 获取订单下所有设备
        $devices = RecycleDevice::where('order_id', $orderId)->select();
        if ($devices->isEmpty()) {
            return false;
        }
        
        // 统计各状态设备数量
        $deviceStatusCounts = [
            RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK => 0,
            RecycleOrderDict::DEVICE_STATUS_CHECKING => 0,
            RecycleOrderDict::DEVICE_STATUS_CHECKED => 0,
            RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM => 0,
            RecycleOrderDict::DEVICE_STATUS_RECYCLED => 0,
            RecycleOrderDict::DEVICE_STATUS_RETURNED => 0,
            RecycleOrderDict::DEVICE_STATUS_CONSIGNED => 0
        ];
        
        $allDevicesPriced = true; // 检查所有设备是否都已定价
        $allDevicesChecked = true; // 检查所有设备是否都已完成质检
        $allDevicesInFinalState = true; // 检查所有设备是否都处于终态（回收或退回）
        
        foreach ($devices as $device) {
            // 统计各状态设备数量
            if (isset($deviceStatusCounts[$device->status])) {
                $deviceStatusCounts[$device->status]++;
            }
            
            // 检查非退回、非代卖设备是否都已定价
            if (!in_array((int)$device->status, [RecycleOrderDict::DEVICE_STATUS_RETURNED, RecycleOrderDict::DEVICE_STATUS_CONSIGNED], true) && empty($device->final_price)) {
                $allDevicesPriced = false;
            }
            
            // 检查设备是否都已完成质检（已质检、待确认、已回收或已退回的设备视为已完成质检）
            if ($device->status == RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK || 
                $device->status == RecycleOrderDict::DEVICE_STATUS_CHECKING) {
                $allDevicesChecked = false;
            }
            
            // 检查设备是否处于终态（已回收、已退回或已转代卖）
            if ($device->status != RecycleOrderDict::DEVICE_STATUS_RECYCLED && 
                $device->status != RecycleOrderDict::DEVICE_STATUS_RETURNED &&
                $device->status != RecycleOrderDict::DEVICE_STATUS_CONSIGNED) {
                $allDevicesInFinalState = false;
            }
        }
        
        // 计算待确认和已回收的设备总数
        $confirmedAndRecycledDevices = $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] + 
                                      $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED];
                                      
        // 记录日志
      Log::record('syncOrderStatus - 订单ID: ' . $orderId . ', 设备总数: ' . $devices->count() . 
            ', 待确认设备数: ' . $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] . 
            ', 已回收设备数: ' . $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] . 
            ', 待确认和已回收总数: ' . $confirmedAndRecycledDevices, 'debug');
        
        // 记录设备状态统计
        $statusSummary = "设备状态统计: ";
        foreach ($deviceStatusCounts as $status => $count) {
            if ($count > 0) {
                $statusSummary .= RecycleOrderDict::getDeviceStatus($status) . ": {$count}台, ";
            }
        }
        $this->addDeviceLog(0, 0, 0, rtrim($statusSummary, ", "), $orderId);
        
        // 计算订单应处于的状态
        $orderStatus = $order->status; // 默认保持当前状态
        
        // 检查是否所有设备都是待确认或已回收状态
        if ($confirmedAndRecycledDevices == $devices->count()) {
            // 如果所有设备都是待确认或已回收状态，将订单状态更新为待确认
            if ($order->status != RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM && 
                $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] > 0) {
                $orderStatus = RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM;
                $this->addDeviceLog(0, 0, 0, "所有设备都已处于待确认或已回收状态，订单进入待确认状态", $orderId);
                // 直接更新订单状态并返回
                $order->status = $orderStatus;
                $order->save();
               
              // 提示: 用户处理已完成定价的设备
                 Log::record('【确认通知】Order->handle 获取订单信息: '.$orderId , 'notice');
              // 触发订单同意通知
                $this->notifyService->orderAgreeNotify(['order_id' => $orderId, "site_id"=>$this->site_id]);
                return true;
            }
        }
        
        // 优先处理所有设备都处于终态的情况
        if ($allDevicesInFinalState) {
            // 如果所有设备都已退回，订单进入已关闭状态
            if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RETURNED] == $devices->count()) {
                $orderStatus = RecycleOrderDict::ORDER_STATUS_CLOSED;
                $this->addDeviceLog(0, 0, 0, "所有设备都已退回，订单进入已关闭状态", $orderId);
                
                // 尝试创建退货订单（如果还没有为这些设备创建过）
                $this->createReturnOrderForAllDevices($orderId, "所有设备退回，系统自动创建退货单");
            } 
            // 如果部分设备已回收，部分已退回，订单进入待打款状态
            else if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] > 0) {
                $orderStatus = RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT;
                $this->addDeviceLog(0, 0, 0, "所有设备都处于终态，有{$deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED]}台设备已回收，订单进入待打款状态", $orderId);
            }
            // 如果没有普通回收设备，但存在代卖设备，主回收订单处理已完成
            else if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_CONSIGNED] > 0) {
                $orderStatus = RecycleOrderDict::ORDER_STATUS_COMPLETED;
                $this->addDeviceLog(0, 0, 0, "所有设备都处于终态，有{$deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_CONSIGNED]}台设备已转代卖，订单进入已完成状态", $orderId);
            }
            
            // 直接更新订单状态并返回，不再执行后续的逻辑
            if ($orderStatus != $order->status) {
                $order->status = $orderStatus;
                $order->update_time = time();
                $order->save();
                $this->addDeviceLog(0, 0, 0, "订单状态从 " . RecycleOrderDict::ORDER_STATUS_TEXT[$order->status] . " 变更为 " . RecycleOrderDict::ORDER_STATUS_TEXT[$orderStatus], $orderId);
                return true;
            }
        }
        
        // 根据业务流程处理订单状态更新
        switch ($deviceStatus) {
            case RecycleOrderDict::DEVICE_STATUS_CHECKING:
                // 如果有一个设备进入质检中，订单就进入质检中状态
                if ($order->status < RecycleOrderDict::ORDER_STATUS_CHECKING) {
                    $orderStatus = RecycleOrderDict::ORDER_STATUS_CHECKING;
                    $this->addDeviceLog(0, 0, 0, "有设备进入质检中状态，订单进入质检中状态", $orderId);
                }
                break;
                
            case RecycleOrderDict::DEVICE_STATUS_CHECKED:
            case RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM:
                // 如果还有设备未完成质检，订单保持质检中状态
                if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK] > 0 || 
                    $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_CHECKING] > 0) {
                    if ($order->status < RecycleOrderDict::ORDER_STATUS_CHECKING) {
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_CHECKING;
                        $this->addDeviceLog(0, 0, 0, "存在未完成质检的设备，订单进入质检中状态", $orderId);
                    }
                    break;
                }
                
                // 所有设备都已完成质检
                if ($allDevicesChecked) {
                    // 如果当前订单状态低于已质检，更新为已质检
                    if ($order->status < RecycleOrderDict::ORDER_STATUS_CHECKED) {
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_CHECKED;
                        $this->addDeviceLog(0, 0, 0, "所有设备已完成质检，订单进入已质检状态", $orderId);
                        break;
                    }
                    
                    // 如果所有设备都已定价，检查是否可以进入待确认状态
                    if ($allDevicesPriced) {
                        // 计算非退回设备数量
                        $nonReturnedDevices = $devices->count() - $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RETURNED];
                        
                        // 只有当所有非退回设备都处于待确认状态时，订单才能进入待确认状态
                        if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM] == $nonReturnedDevices) {
                            // 如果当前订单状态是已质检，可以直接过渡到待确认状态
                            if ($order->status == RecycleOrderDict::ORDER_STATUS_CHECKED) {
                                $orderStatus = RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM;
                                $this->addDeviceLog(0, 0, 0, "所有非退回设备都已定价并处于待确认状态，订单进入待确认状态", $orderId);
                                
                                // 提示: 用户处理已完成定价的设备
                                  Log::record('【确认通知】Order->handle 获取订单信息: '.$orderId , 'notice');
                              // 触发订单同意通知
                                $this->notifyService->orderAgreeNotify(['order_id' => $orderId, "site_id"=>$this->site_id]);
                            }
                        }
                    }
                }
                break;
                
            case RecycleOrderDict::DEVICE_STATUS_RECYCLED:
                // 检查是否所有设备都已回收或已退回
                $totalConfirmedDevices = $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] + 
                                        $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RETURNED];
                
                // 如果所有设备都已确认（回收或退回）
                if ($totalConfirmedDevices == $devices->count()) {
                    // 如果至少有一个设备是已回收状态，订单进入待打款状态
                    if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] > 0) {
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT;
                        $this->addDeviceLog(0, 0, 0, "所有设备都已确认，且至少有一个设备已回收，订单进入待打款状态", $orderId);
                    } else {
                        // 如果所有设备都已退回，没有已回收的设备，订单进入已关闭状态
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_CLOSED;
                        $this->addDeviceLog(0, 0, 0, "所有设备都已退回，订单进入已关闭状态", $orderId);
                    }
                }
                break;
                
            case RecycleOrderDict::DEVICE_STATUS_RETURNED:
                // 检查是否所有设备都已退回
                if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RETURNED] == $devices->count()) {
                    // 如果所有设备都已退回，订单进入已关闭状态
                    $orderStatus = RecycleOrderDict::ORDER_STATUS_CLOSED;
                    $this->addDeviceLog(0, 0, 0, "所有设备都已退回，订单进入已关闭状态", $orderId);
                    
                    // 为所有设备创建退货订单
                   $this->createReturnOrderForAllDevices($orderId, "所有设备退回，系统自动创建退货单");
                } else {
                    // 如果不是所有设备都已退回，需要检查其他设备的状态
                    // 如果所有非退回设备都已完成质检

                    if ($allDevicesChecked) {
                        // 如果当前订单状态低于已质检，更新为已质检
                        if ($order->status < RecycleOrderDict::ORDER_STATUS_CHECKED) {
                            $orderStatus = RecycleOrderDict::ORDER_STATUS_CHECKED;
                            $this->addDeviceLog(0, 0, 0, "所有非退回设备已完成质检，订单进入已质检状态", $orderId);
                        }
                    }
                }
                break;
            case RecycleOrderDict::DEVICE_STATUS_CONSIGNED:
                $terminalDevices = $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED]
                    + $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RETURNED]
                    + $deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_CONSIGNED];
                if ($terminalDevices == $devices->count()) {
                    if ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_RECYCLED] > 0) {
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT;
                    } elseif ($deviceStatusCounts[RecycleOrderDict::DEVICE_STATUS_CONSIGNED] > 0) {
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_COMPLETED;
                    } else {
                        $orderStatus = RecycleOrderDict::ORDER_STATUS_CLOSED;
                    }
                }
                break;
        }
        
        // 如果订单状态需要更新
        if ($orderStatus != $order->status) {
            // 记录订单状态变更
            $this->addDeviceLog(0, $order->status, $orderStatus, "订单状态从 " . RecycleOrderDict::ORDER_STATUS_TEXT[$order->status] . " 变更为 " . RecycleOrderDict::ORDER_STATUS_TEXT[$orderStatus], $orderId);
            
            // 更新订单状态
            $order->status = $orderStatus;
            $order->update_time = time();
            $order->save();
        }
        
        return true;
    }
    
    /**
     * 为订单的所有设备创建退货订单
     * @param int $orderId 订单ID
     * @param string $remark 备注
     * @return bool
     */
    protected function createReturnOrderForAllDevices(int $orderId, string $remark = '',): bool
    {

        try {
            // 获取订单下的所有设备
            $deviceIds = RecycleDevice::where('order_id', $orderId)
                ->column('id');
            $member_id = RecycleDevice::where('order_id', $orderId)
            ->column('member_id');
                
                
            if (empty($deviceIds)) {
                return false;
            }
            
            // 检查是否已经为这些设备创建了退货单
            $existingReturnDevices = (new RecycleReturnDevice())
                ->whereIn('device_id', $deviceIds)
                ->count();
                
            // 如果已经有退货单，不再重复创建
            if ($existingReturnDevices > 0) {
                $this->addDeviceLog(0, 0, 0, "系统检测到已存在退货单，不再重复创建", $orderId);
                return true;
            }
            
            // 创建退货订单
            $returnOrderService = new \addon\hsx_recycle\app\service\admin\order\RecycleReturnOrderService();
            $returnOrderData = [
                'site_id' => $this->site_id,
                'order_id' => $orderId,
                'device_ids' => $deviceIds,
                'comment' => $remark,
                'return_address' => '',
                'operator_id' => $this->uid,
                'operator_name' => $this->username,
                'member_id' => $member_id,
            ];
            
            $result = $returnOrderService->create($returnOrderData);
            if (!$result || (isset($result['code']) && $result['code'] != 0)) {
                // 如果创建退货订单失败，记录日志但不影响流程
                $errorMsg = isset($result['msg']) ? $result['msg'] : '未知错误';
                Log::error("为订单 {$orderId} 的所有设备创建退货单失败: {$errorMsg}");
                return false;
            }
            
            $this->addDeviceLog(0, 0, 0, "成功为订单 {$orderId} 的所有设备创建退货单", $orderId);
            return true;
        } catch (\Exception $e) {
            Log::error("创建退货单异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 将设备添加到已有退货单
     * 
     * @param int $deviceId 设备ID
     * @param int $returnOrderId 退货单ID
     * @param string $remark 备注信息
     * @return bool
     */
    protected function appendDeviceToReturnOrder(int $deviceId, int $returnOrderId, string $remark = ''): bool
    {
        Db::startTrans();
        try {
            // 获取设备信息
            $device = RecycleDevice::where('id', $deviceId)->find();
            if (!$device) {
                throw new CommonException('设备不存在');
            }
            
            // 保存设备原始状态用于日志记录
            $oldStatus = $device->status;
            
            // 更新设备状态
            $device->status = RecycleReturnOrderDict::DEVICE_STATUS_RETURNING;
            $device->return_order_id = $returnOrderId;
            $device->return_time = time();
            $device->return_remark = $remark;

            
            if (!$device->save()) {
                throw new CommonException('更新设备状态失败');
            }
            
            // 记录设备状态变更日志
            $this->addDeviceLog($deviceId, $oldStatus, RecycleReturnOrderDict::DEVICE_STATUS_RETURNING, $remark, $device->order_id);
            
            // 添加设备到退货单关联表
            $returnDeviceModel = new RecycleReturnDevice();
            $returnDeviceData = [
                'return_order_id' => $returnOrderId,
                'device_id' => $deviceId,
                'create_at' => time(),
                
            ];
            
            if (!$returnDeviceModel->save($returnDeviceData)) {
                throw new CommonException('关联设备到退货单失败');
            }
            
            // 同步更新订单状态
            $this->syncOrderStatus($device->order_id, RecycleReturnOrderDict::DEVICE_STATUS_RETURNING);
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("添加设备到退货单失败（设备ID: {$deviceId}, 退货单ID: {$returnOrderId}）: " . $e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }
    
    /**
     * 为设备创建新的退货单
     * 
     * @param int $deviceId 设备ID
     * @param string $remark 备注信息
     * @return int 新创建的退货单ID
     */
    protected function createReturnOrderForDevice(int $deviceId, string $remark = ''): int
    {
        try {
            // 先获取设备信息，检查是否已有关联的退货单
            $device = RecycleDevice::where('id', $deviceId)->find();
            if (!$device) {
                throw new CommonException('设备不存在');
            }
            
            // 如果设备已经关联了退货单，直接返回该退货单ID
            if (!empty($device->return_order_id)) {
                Log::info("设备ID: {$deviceId} 已关联退货单ID: {$device->return_order_id}，直接使用");
                return $device->return_order_id;
            }
            
            // 获取订单信息
            $order = RecycleOrder::where('id', $device->order_id)->find();
            if (!$order) {
                throw new CommonException("关联的订单不存在，订单ID: {$device->order_id}");
            }
            
            // 调用returnDevice创建退货单
            $result = $this->returnDevice($deviceId, $remark);
            
            if (empty($result) || !isset($result['return_order_id'])) {
                Log::error("创建退货单失败，结果: " . json_encode($result));
                throw new CommonException('创建退货单失败，无法获取退货单ID');
            }
            
            Log::info("成功为设备ID: {$deviceId} 创建退货单ID: {$result['return_order_id']}");
            return $result['return_order_id'];
        } catch (\Exception $e) {
            Log::error("为设备创建退货单失败（设备ID: {$deviceId}）: " . $e->getMessage());
            throw new CommonException('创建退货单失败: ' . $e->getMessage());
        }
    }
    // getImeiInfo
    /**
     * 查询IMEI/SN信息
     * @param string $imei IMEI或序列号
     * @return array
     */
    public function getImeiInfo(string $imei)
    {

   
        try {
            // 使用新的设备查询服务
            $queryService = new \addon\hsx_recycle\app\service\admin\device_query\DeviceQueryService();
         
            $result = $queryService->queryDevice($imei  , $this->site_id);
        
            // var_dump($result);
            // die;
  
            if ($result['success']  && !empty($result['data'])) {

             
                $data = $result['data'];

          
                $name = '';
                
                // 组合设备名称
                if (isset($data['model'])) {
                    $name = $data['model'];
                }
                
                if (isset($data['fmi'])) {
                    $name .= ' ' . $data['fmi'];
                }
                
                return ['name' => trim($name)];
            } else {
                return ['name' => ''];
            }
        } catch (\Exception $e) {
            // 如果新服务出错，回退到原来的查询方式
            return $this->getImeiInfoFallback($imei);
        }
    }

    /**
     * 获取IMEI信息（使用配置化查询服务）
     * @param string $imei
     * @return array
     */
    private function getImeiInfoFallback(string $imei)
    {
        try {
            // 使用新的设备查询服务
            $deviceQueryService = new \addon\hsx_recycle\app\service\admin\device_query\DeviceQueryService();
            $result = $deviceQueryService->queryDeviceInfo($imei, 'imei');
            
           

            if ($result && isset($result['query_result'])) {
                $data = $result['query_result'];
                
                // 构建设备名称
                $name = '';
                if (!empty($data['model'])) {
                    $name = $data['model'];
                    if (!empty($data['capacity'])) {
                        $name .= ' ' . $data['capacity'];
                    }
                    if (!empty($data['color'])) {
                        $name .= ' ' . $data['color'];
                    }
                }
                
                return ['name' => $name ?: ''];
            }
            
            return ['name' => ''];
        } catch (\Exception $e) {
            // 记录错误日志
            trace('IMEI查询失败: ' . $e->getMessage());
            return ['name' => ''];
        }
    }


    // printDeviceLabel
    public function printDeviceLabel(int $id)
    {
        // 查询和这个设备相关的所有信息
        $device = RecycleDevice::where('id', $id)
        ->with('checkUser')
        ->find();
        if (!$device) {
            throw new CommonException('设备不存在');
        }

        // 使用简化版打印服务
        $printerService = new \addon\hsx_recycle\app\service\admin\printer\RecyclePrinterService();
        return $printerService->printDeviceLabel($id);
    }

}
