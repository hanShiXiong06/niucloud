<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 待上架货源控制器
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\adminapi\controller\intake;

use core\base\BaseAdminController;
use addon\phone_shop\app\service\admin\intake\DeviceIntakeService;
use addon\phone_shop\app\service\core\intake\CoreDeviceIntakeService;
use addon\phone_shop\app\service\core\upgrade\SchemaSyncService;

/**
 * 待上架货源（中台定价设备）
 * Class DeviceIntake
 * @package addon\phone_shop\app\adminapi\controller\intake
 */
class DeviceIntake extends BaseAdminController
{
    /**
     * 货源分页列表
     */
    public function pages()
    {
        $data = $this->request->params([
            [ 'status', '' ],
            [ 'erp_asset_id', '' ],
            [ 'model_name', '' ],
            [ 'keyword', '' ],
            [ 'material_status', '' ],
        ]);
        return success((new DeviceIntakeService())->getPage($data));
    }

    /**
     * 货源详情
     */
    public function info(int $id)
    {
        return success((new DeviceIntakeService())->getInfo($id));
    }

    /** 资料待办仅允许修改展示属性，交易、库存及 ERP 资产信息不接受前端覆盖。 */
    public function materialInfo(int $id)
    {
        return success((new DeviceIntakeService())->materialInfo($id));
    }

    public function saveMaterial(int $id)
    {
        $data = $this->request->params([
            ['action', 'save'], ['revision', -1], ['goods_name', ''], ['sub_title', ''],
            ['memory_group', ''], ['device_color', ''], ['condition_grade', ''],
            ['battery_health', -1], ['warranty_expire_time', 0],
            ['attr_ids', null], ['attr_format', null],
        ]);
        return success('资料已保存', (new DeviceIntakeService())->saveMaterial($id, $data));
    }

    /**
     * 标记尚未建品的货源（2忽略 / 0恢复）
     */
    public function setStatus()
    {
        $data = $this->request->params([
            [ 'intake_id', 0 ],
            [ 'status', 0 ],
            [ 'goods_id', 0 ],
        ]);
        (new DeviceIntakeService())->setStatus((int) $data[ 'intake_id' ], (int) $data[ 'status' ], (int) $data[ 'goods_id' ]);
        return success();
    }

    /**
     * 待建品数量（菜单角标）
     */
    public function pendingCount()
    {
        return success([ 'count' => (new DeviceIntakeService())->getPendingCount() ]);
    }

    /** 回收设备上架资料的协作角色，由 ERP 规则 Hook 提供。 */
    public function materialPolicy()
    {
        $policy = ['owner' => 'phone_shop', 'owner_label' => '商城运营专员', 'can_phone_shop_operate' => 1];
        foreach ((array)event('HsxErpListingMaterialPolicy', ['site_id' => (int)$this->request->siteId()]) as $result) {
            if (!is_array($result) || !isset($result['owner'])) continue;
            $policy = array_merge($policy, $result);
            break;
        }
        return success($policy);
    }

    /**
     * 由货源建品并上架
     */
    public function build()
    {
        foreach ((array)event('HsxErpListingMaterialPolicy', ['site_id' => (int)$this->request->siteId()]) as $result) {
            if (is_array($result) && isset($result['can_phone_shop_operate']) && (int)$result['can_phone_shop_operate'] !== 1) {
                if ((int)($result['enabled'] ?? 1) !== 1) throw new \core\exception\AdminException('ERP 商城渠道已关闭，当前不能新建并上架商品；已有资料待办仍可处理');
                throw new \core\exception\AdminException('当前配置由 ERP 库存人员完善资料，请在 ERP 库存中心操作');
            }
        }
        $data = $this->request->params([
            [ 'intake_id', 0 ],
            [ 'goods_name', '' ],
            [ 'sub_title', '' ],
            [ 'brand_id', 0 ],
            [ 'goods_category', [] ],
            [ 'category_name', '' ],
            [ 'attr_ids', [] ],
            [ 'attr_format', [] ],
            [ 'label_ids', [] ],
            [ 'service_ids', [] ],
            [ 'condition_grade', '' ],
            [ 'memory', '' ],
            [ 'device_color', '' ],
            [ 'battery_health', '' ],
            [ 'warranty_expire_time', '' ],
            [ 'warranty_expire_date', '' ],
            [ 'delivery_type', [] ],
            [ 'price', 0 ],
            [ 'pricing_base_price', null ],
            [ 'market_price', 0 ],
            [ 'cost_price', 0 ],
            [ 'goods_desc', '' ],
            [ 'status', 1 ],
        ]);
        $goods_id = (new DeviceIntakeService())->build($data);
        return success('建品成功', [ 'goods_id' => $goods_id ]);
    }

    /**
     * 建品预览:按"清洗映射引擎+站点配置"算出 6 字段默认,供表单预填(不落库)
     */
    public function preview()
    {
        $data = $this->request->params([
            [ 'intake_id', 0 ],
            [ 'overrides', [] ],
        ]);
        $res = (new DeviceIntakeService())->previewMapping(
            (int) $data[ 'intake_id' ],
            is_array($data[ 'overrides' ]) ? $data[ 'overrides' ] : []
        );
        return success($res);
    }

    /**
     * 上架映射配置(扩展口):读取
     */
    public function mappingConfig()
    {
        return success((new \addon\phone_shop\app\service\core\intake\CoreListingMappingService())->getConfig($this->request->siteId()));
    }

    /**
     * 上架映射配置(扩展口):保存
     */
    public function saveMappingConfig()
    {
        $params = $this->request->params([
            [ 'title_template', '' ],
            [ 'subtitle_template', '' ],
            [ 'warranty_text', '' ],
            [ 'default_service_ids', [] ],
            [ 'default_label_ids', [] ],
            [ 'default_delivery_type', [] ],
            [ 'memory_aliases', [] ],
            [ 'qc_report_enabled', true ],
            [ 'qc_report_title', '' ],
            [ 'qc_hidden_keys', [] ],
        ]);
        (new \addon\phone_shop\app\service\core\intake\CoreListingMappingService())->setConfig($this->request->siteId(), $params);
        return success('保存成功');
    }

    /**
     * 手动同步表结构（给老库 ALTER 补缺列，等价于 reinstall 跑的迁移）。可重复执行。
     */
    public function syncSchema()
    {
        $report = (new SchemaSyncService())->run();
        return success('同步完成', $report);
    }

    /**
     * 【临时-造测试数据】灌入几条假货源，验证列表/详情。上线前删除本方法及其路由。
     */
    public function seedTest()
    {
        $siteId = $this->request->siteId();
        $samples = [
            [ 'model_name' => 'iPhone 13', 'brand_name' => 'Apple', 'memory' => '128G', 'color' => '午夜色', 'condition_grade' => '99新', 'imei' => '350000000000101', 'sale_price' => 3200, 'peer_price' => 3000, 'cost_price' => 2800, 'qc_info' => [ '屏幕' => '无划痕', '电池效率' => '89%', '功能' => '全好' ] ],
            [ 'model_name' => 'iPhone 12', 'brand_name' => 'Apple', 'memory' => '64G', 'color' => '蓝色', 'condition_grade' => '95新', 'imei' => '350000000000102', 'sale_price' => 2300, 'peer_price' => 2150, 'cost_price' => 2000, 'qc_info' => [ '屏幕' => '细微划痕', '电池效率' => '82%', '功能' => '全好' ] ],
            [ 'model_name' => 'HUAWEI Mate 40', 'brand_name' => 'HUAWEI', 'memory' => '256G', 'color' => '亮黑色', 'condition_grade' => '9成新', 'imei' => '860000000000103', 'sale_price' => 2600, 'peer_price' => 2400, 'cost_price' => 2200, 'qc_info' => [ '屏幕' => '良好', '功能' => '指纹偶尔失灵' ] ],
        ];
        $service = new CoreDeviceIntakeService();
        $n = 0;
        foreach ($samples as $i => $s) {
            $s[ 'site_id' ] = $siteId;
            $s[ 'erp_asset_id' ] = 700100 + $i + 1; // 幂等键，重复点不会重复造
            $s[ 'images' ] = [ 'https://vip.123pan.cn/1832133965/tiantai/1714718469ea4817c16b440e5fd6370c4d106770bb_ott.jpg' ]; // 临时模拟图
            $service->saveFromEvent([
                'event_name' => 'device_asset.price.completed.v1',
                'device_id'  => 900100 + $i + 1,
                'payload'    => $s,
            ]);
            $n++;
        }
        return success([ 'seeded' => $n ]);
    }
}
