<?php

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleDeductionConfig;
use core\base\BaseAdminService;
use think\facade\Cache;

/**
 * 扣费配置服务层
 */
class DeductionConfigService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeductionConfig();
    }

    /**
     * 获取扣费配置分页列表
     */
    public function getPage(array $where = [])
    {
        $field = 'id, site_id, config_name, model_id, price_id, remark_text, sort, is_enable, create_at, update_at';
        $order = 'sort asc, id desc';

        $searchModel = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['config_name', 'model_id', 'price_id', 'is_enable'], $where)
            ->field($field)
            ->order($order);

        $list = $this->pageQuery($searchModel);
        return $list;
    }

    /**
     * 获取扣费配置列表（不分页）
     */
    public function getList(array $where = [])
    {
        $field = 'id, site_id, config_name, model_id, price_id, remark_text, sort, is_enable';
        $order = 'sort asc, id desc';

        $list = $this->model->where([['site_id', '=', $this->site_id], ['is_enable', '=', 1]])
            ->withSearch($where)
            ->field($field)
            ->order($order)
            ->select()
            ->toArray();

        return $list;
    }

    /**
     * 获取扣费配置详情
     */
    public function getInfo(int $id)
    {
        $field = 'id, site_id, config_name, model_id, price_id, remark_text, sort, is_enable';

        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->field($field)->findOrEmpty()->toArray();

        return $info;
    }

    /**
     * 添加扣费配置
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_at'] = time();
        $data['update_at'] = time();
        $data['model_id'] = implode(',', $data['model_id']);
        $data['price_id'] = implode(',', $data['price_id']);
        // remark_text
        $data['remark_text'] = $data['remark_text'];

        $res = $this->model->create($data);
        
        // 清除缓存
        $this->clearCache();
        
        return $res->id;
    }

    /**
     * 编辑扣费配置
     */
    public function edit(int $id, array $data)
    {

        $data['update_at'] = time();
        $data['remark_text'] = $data['remark_text'];
        $data['model_id'] = $data['model_id'] ?? ''; 
        $data['price_id'] = $data['price_id'] ?? '';

        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update($data);

        // 清除缓存
        $this->clearCache();

        return true;
    }

    /**
     * 删除扣费配置
     */
    public function del(int $id)
    {
        $res = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->delete();

        // 清除缓存
        $this->clearCache();

        return $res;
    }

    /**
     * 修改状态
     */
    public function modifyStatus(int $id, int $is_enable)
    {
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update(['is_enable' => $is_enable, 'update_at' => time()]);

        // 清除缓存
        $this->clearCache();

        return true;
    }

    /**
     * 根据型号ID和报价类型ID查找匹配的扣费配置
     * @param int $goodsId 型号ID
     * @param string $priceTypeId 报价类型ID（114 或 115）
     * @return int|null 返回匹配的配置ID，没有则返回null
     */
    public function getMatchingConfigId(int $goodsId, string $priceTypeId): ?int
    {
        // 如果报价类型ID为空，直接返回null
        if (empty($priceTypeId)) {
            return null;
        }

        // 查询所有启用的配置
        $configs = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['is_enable', '=', 1]
        ])
        ->field('id, model_id, price_id')
        ->select()
        ->toArray();

        foreach ($configs as $config) {
            // 检查 model_id 是否匹配（支持逗号分隔的多个ID）
            $modelIds = array_filter(array_map('trim', explode(',', $config['model_id'])));
            $modelMatch = in_array((string)$goodsId, $modelIds);

            // 检查 price_id 是否匹配（报价类型ID：114 或 115）
            $priceTypeMatch = trim($config['price_id']) === $priceTypeId;

            // 两者都匹配则返回该配置ID
            if ($modelMatch && $priceTypeMatch) {
                return $config['id'];
            }
        }

        return null;
    }

    /**
     * 清除缓存
     */
    private function clearCache()
    {
        $cacheTag = 'deduction_config_' . $this->site_id;
        Cache::tag($cacheTag)->clear();
    }
}

