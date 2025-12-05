<?php
declare(strict_types=1);

namespace addon\phone_shop\app\model\goods;

use addon\phone_shop\app\model\goods\Memory;
use core\base\BaseModel;

/**
 * 内存规格分组模型
 * Class MemoryGroup
 * @package addon\phone_shop\app\model\goods
 */
class MemoryGroup extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'group_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'phone_shop_memory_group';

    /**
     * 设置字段信息
     * @var array
     */
    protected $schema = [
        'group_id'     => 'int',
        'site_id'      => 'int',
        'group_name'   => 'string',
        'memory_ids'   => 'string',
        'sort'         => 'int',
        'create_time'  => 'int',
        'update_time'  => 'int'
    ];

    /**
     * 追加属性
     * @var array
     */
    protected $append = [
        'memory_list',

    ];

    /**
     * 搜索器-规格名称
     * @param $query
     * @param $value
     */
    public function searchGroupNameAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where([['group_name', 'like', '%' . $value . '%']]);
        }
    }

    /**
     * 搜索器-站点ID
     * @param $query
     * @param $value
     */
    public function searchSiteIdAttr($query, $value)
    {
        if (!empty($value)) {
            if (is_array($value)) {
                $query->where([['site_id', 'in', $value]]);
            } else {
                $query->where([['site_id', '=', $value]]);
            }
        }
    }

    /**
     * 获取器-内存规格列表
     * @param $value
     * @param $data
     * @return array
     */
    public function getMemoryListAttr($value, $data)
    {
        try {
            $memory_ids= $data['memory_ids'] ?? '';
            if (empty($memory_ids)) {
                return [];
            }

            $memory_id_array = array_filter(explode(',', $memory_ids));
            if (empty($memory_id_array)) {
                return [];
            }

            return (new Memory())->where([
                ['spec_id', 'in', $memory_id_array],
                ['site_id', 'in', [$data['site_id'], 0]]
            ])->field('spec_id, spec_name, sort, site_id')
                ->order('sort desc, spec_id desc')
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 获取器-内存规格名称列表
     * @param $value
     * @param $data
     * @return array
     */
    public function getMemoryNamesAttr($value, $data)
    {
        $memory_list = $this->getMemoryListAttr($value, $data);
        return array_column($memory_list, 'spec_name');
    }

    /**
     * 修改器-内存规格ID集合
     * @param $value
     * @return string
     */
    public function setMemoryIdsAttr($value)
    {
        if (is_array($value)) {
            $value = implode(',', array_unique(array_filter($value)));
        }
        return $value;
    }

    /**
     * 获取分组下的内存规格ID数组
     * @return array
     */
    public function getMemoryIds(): array
    {
        $memory_ids= $this->getData('memory_ids');
        if (empty($memory_ids)) {
            return [];
        }
        return array_filter(explode(',', $memory_ids));
    }
}
