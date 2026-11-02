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

namespace addon\phone_shop\app\service\admin\goods;

use addon\phone_shop\app\dict\goods\LabelDict;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\Label;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;


/**
 * 商品标签服务层
 * Class LabelService
 * @package addon\phone_shop\app\service\admin\goods
 */
class LabelService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Label();
    }

    /**
     * 获取商品标签列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $status = $this->statusValue($where['status'] ?? '', true);
        $field = 'label_id, label_name, group_id, style_type, color_json, icon, status, memo, sort, create_time, update_time';
        $order = 'label_id desc';
        if (!empty($where[ 'order' ])) {
            $order = $where[ 'order' ] . ' ' . $where[ 'sort' ];
        }

        $search_model = $this->model->where([ [ 'site_id', '=', $this->site_id ]])->withSearch([ "label_name", 'group_id' ], $where)->field($field)
            ->with([
                'group' => function($query) {
                    $query->field('group_id, group_name');
                },
            ])
            ->order($order);
        if ($status !== null) $search_model->where('status', $status);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取商品标签列表
     * @param array $where
     * @param string $field
     * @return array
     */
    public function getList(array $where = [], $field = 'label_id, label_name, group_id, style_type, color_json, icon, status, memo, sort, create_time, update_time')
    {
        $order = 'sort desc,label_id desc';
        return $this->model->where([ [ 'site_id', '=', $this->site_id ],['status','=',LabelDict::ENABLE]])->withSearch([ "label_name" ], $where)->field($field)
            ->with([
                'group' => function($query) {
                    $query->field('group_id, group_name');
                },
            ])->order($order)->select()->toArray();
    }

    /**
     * 获取商品标签信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'label_id, label_name, group_id, style_type, color_json, icon, status, memo, sort, create_time, update_time';

        $info = $this->model->field($field)->where([ [ 'label_id', '=', $id ], [ 'site_id', '=', $this->site_id ] ])
            ->with([
                'group' => function($query) {
                    $query->field('group_id, group_name');
                },
            ])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加商品标签
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data[ 'site_id' ] = $this->site_id;
        $data[ 'create_time' ] = time();
        $brandInfo = $this->model->where([ [ 'site_id', '=', $this->site_id ], [ 'label_name', '=', $data[ 'label_name' ] ] ])->findOrEmpty()->toArray();
        if ($brandInfo) {
            throw new AdminException('SHOP_GOODS_LABEL_NAME_REPEAT');
        }
        $res = $this->model->create($data);
        return $res->label_id;
    }

    /**
     * 商品标签编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $data[ 'update_time' ] = time();
        $labelInfo = $this->model->where([ [ 'site_id', '=', $this->site_id ], [ 'label_name', '=', $data[ 'label_name' ] ] ])->findOrEmpty()->toArray();
        if ($labelInfo && $labelInfo[ 'label_id' ] != $id) {
            throw new AdminException('SHOP_GOODS_LABEL_NAME_REPEAT');
        }

        $this->model->where([ [ 'label_id', '=', $id ], [ 'site_id', '=', $this->site_id ] ])->update($data);
        return true;
    }

    /**
     * 删除商品标签
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        // 检测商品标签分组是否被使用
        $goods_model = new Goods();
        $count = $goods_model->where([
            [ 'site_id', '=', $this->site_id ],
        ])->withSearch([ 'label_ids' ], [ 'label_ids' => $id ])->count();
        if ($count) {
            throw new AdminException('SHOP_GOODS_LABEL_IN_USE');
        }
        $model = $this->model->where([ [ 'label_id', '=', $id ], [ 'site_id', '=', $this->site_id ] ])->find();
        $res = $model->delete();
        return $res;
    }

    /**
     * 修改排序
     * @param $data
     * @return Label
     */
    public function modifySort($data)
    {
        return $this->model->where([
            [ 'label_id', '=', $data[ 'label_id' ] ],
            [ 'site_id', '=', $this->site_id ]
        ])->update([ 'sort' => $data[ 'sort' ] ]);
    }

    /**
     * 修改状态
     * @param $data
     * @return array
     */
    public function modifyStatus($data)
    {
        $status = $this->statusValue($data['status'] ?? null);
        $values = array_key_exists('label_ids', $data) && $data['label_ids'] !== null
            ? $data['label_ids'] : [$data['label_id'] ?? null];
        if (!is_array($values) || count($values) < 1 || count($values) > 100) {
            throw new AdminException('每次请选择 1 至 100 个标签');
        }
        $ids = [];
        foreach ($values as $value) {
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
                || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new AdminException('标签选择无效，请刷新后重新选择');
            }
            $ids[] = (int)$value;
        }
        if (count(array_unique($ids)) !== count($ids)) throw new AdminException('标签选择不能重复');
        sort($ids, SORT_NUMERIC);

        return Db::transaction(function () use ($ids, $status) {
            $rows = $this->model->where('site_id', $this->site_id)->whereIn('label_id', $ids)
                ->field('label_id,status')->order('label_id asc')->lock(true)->select()->toArray();
            if (count($rows) !== count($ids)) throw new AdminException('部分标签已不存在或不属于本站，未修改任何标签，请刷新后重新选择');
            $changedIds = array_column(array_filter($rows, static fn($row) => (int)$row['status'] !== $status), 'label_id');
            if ($changedIds !== []) {
                $this->model->where('site_id', $this->site_id)->whereIn('label_id', $changedIds)
                    ->update(['status' => $status, 'update_time' => time()]);
            }
            return ['requested_count' => count($ids), 'changed_count' => count($changedIds), 'status' => $status];
        });
    }

    /** 空筛选不限制状态；关闭值 0 不能用 empty() 忽略。 */
    private function statusValue($value, bool $allowEmpty = false): ?int
    {
        if ($allowEmpty && ($value === '' || $value === null)) return null;
        if (!in_array($value, [0, 1, '0', '1'], true)) throw new AdminException('标签状态只能选择启用或停用');
        return (int)$value;
    }

    /**
     * 复制商品标签
     * @param int $id
     * @return Label|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function copy(int $id)
    {
        $field = 'site_id, label_name, group_id, style_type, color_json, icon, memo, sort';
        $label_info = $this->model->field($field)->where('label_id',$id)->find()->toArray();
        $label_info['label_name'] =$label_info['label_name'].'_副本';
        $label_info['status'] =0;
        return $this->model->create($label_info);
    }

}
