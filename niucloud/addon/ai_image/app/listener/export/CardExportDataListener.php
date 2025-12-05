<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\ai_image\app\listener\export;

use addon\ai_image\app\model\aiimagecard\AiimageCard as Card;

/**
 * 卡密导出数据源查询
 */
class CardExportDataListener
{

    public function handle($param)
    {
        if ($param['type'] == 'ai_image_card') {
            $model = new Card();
            $where = $param['where'];
            $order = 'create_time desc';
            $search_model = $model->where([['site_id', "=", $param['site_id']]])
              //  ->where(['status' => 0])
                ->withSearch(["pid", "num", "is_use", "is_export"], $where)->with(['member'])->order($order);
            if ($param['page']['page'] > 0 && $param['page']['limit'] > 0) {
                $list['data'] = $search_model->page($param['page']['page'], $param['page']['limit'] == 1 ? 100 : $param['page']['limit'])->select()->toArray();
            } else {
                $list['data'] = $search_model->select()->toArray();
            }
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['is_use'] = $v['is_use'] == 1 ? '已使用' : '未使用';
                $list['data'][$k]['is_export'] = $v['is_export'] == 1 ? '未分配' : '已分配';
            }
            $reslist = $list['data'];
            // 新增批量更新逻辑
            if (count($list['data'])>0) {
                $ids = array_column($list['data'], 'id');
                $model->whereIn('id', $ids)->update(['is_export' => 1]);
            }
            return $reslist;
        }
        return [];
    }
}