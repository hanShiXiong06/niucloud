<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\service\api\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationData;
use addon\recycle\app\dict\quotation\QuotationDict;
use core\base\BaseApiService;

/**
 * 报价查询服务（移动端）
 * Class QuotationPriceService
 * @package addon\recycle\app\service\api\quotation
 */
class QuotationPriceService extends BaseApiService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleQuotationData();
    }

    /**
     * 获取报价数据列表
     * @param array $where
     * @return array
     */
    public function getList(array $where = []): array
    {
        // 默认查询当前最新报价
        if (!isset($where['is_current']) || $where['is_current'] === '') {
            $where['is_current'] = QuotationDict::IS_CURRENT_YES;
        }

        // 如果没有指定日期，查询今天的报价
        if (empty($where['price_date'])) {
            $where['price_date'] = date('Y-m-d');
        }

        $field = 'id,quotation_id,price_name,goods_id,goods_name,capacity,prices,add_value_info,price_date,create_at,update_at';
        
        $list = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['quotation_id', 'price_name', 'goods_name', 'capacity', 'price_date', 'is_current'], $where)
            ->with(['deductionConfig'])  // 加载关联的扣费配置
            ->field($field)
            ->order('goods_name asc, capacity asc')
            ->select()
            ->toArray();

        // 处理价格数据
        foreach ($list as &$item) {
            // 解析 prices JSON 字段
            if (is_string($item['prices'])) {
                $item['prices'] = json_decode($item['prices'], true) ?: [];
            }

            // 转换为前端需要的格式
            $formattedPrices = [];
            foreach ($item['prices'] as $configName => $price) {
                $formattedPrices[$configName] = [
                    'original' => $price,
                    'final' => $price,
                ];
            }
            $item['prices'] = $formattedPrices;
        }

        return $list;
    }

    /**
     * 获取报价类型列表
     * @return array
     */
    public function getPriceTypes(): array
    {
        $priceTypes = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['is_current', '=', QuotationDict::IS_CURRENT_YES]
            ])
            ->field('quotation_id, price_name')
            ->group('quotation_id, price_name')
            ->select()
            ->toArray();

        return $priceTypes;
    }
}

