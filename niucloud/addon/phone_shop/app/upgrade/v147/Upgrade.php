<?php

namespace addon\phone_shop\app\upgrade\v147;

use addon\phone_shop\app\model\goods\Goods;
use think\facade\Log;

class Upgrade
{

    public function handle()
    {
        $this->handleGoodsData();
    }

    /**
     * 处理商品数据，参数模板结构调整
     */
    private function handleGoodsData()
    {
        $list = ( new Goods() )->onlyTrashed()->where([ [ 'attr_ids', '<>', '' ] ])->field('goods_id,attr_ids')->select()->toArray();

        try {
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    if (gettype($v[ 'attr_ids' ]) != 'array') {
                        if (!empty($v[ 'attr_ids' ])) {
                            $list[ $k ][ 'attr_ids' ] = [ $v[ 'attr_ids' ] ];
                        } else {
                            $list[ $k ][ 'attr_ids' ] = [];
                        }
                        ( new Goods() )->onlyTrashed()->where([ [ 'goods_id', '=', $v[ 'goods_id' ] ] ])->update([ 'attr_ids' => $list[ $k ][ 'attr_ids' ] ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::write('商城插件升级v1.4.7版本，发生错误，Line：' . $e->getLine() . '，Message：' . $e->getMessage() . '，File：' . $e->getFile());
        }
    }

}
