<?php

namespace addon\home_service\app\upgrade\v110;

use app\model\diy\Diy;

class Upgrade
{

    public function handle()
    {
        $this->handleDiyData();
    }

    /**
     * 处理自定义数据
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function handleDiyData()
    {
        $diy_model = new Diy();
        $field = 'id,value';
        $list = $diy_model->where([['type', '=', 'DIY_HOME_SERVICE_INDEX']])->whereOr([['type', '=', 'DIY_HOME_SERVICE_MEMBER_INDEX']])->field($field)->select()->toArray();

        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $diy_data = json_decode($v['value'], true);

                if (!isset($diy_data['global']['copyright'])) {
                    $diy_data['global']['copyright'] = [
                        'control' => true,
                        'isShow' => false
                    ];
                }
                if (!isset($diy_data['global']['bottomTabBar']['designNav'])) {
                    $diy_data['global']['bottomTabBar']['designNav'] = ['title'=>'','key'=>''];
                }

                $diy_data = json_encode($diy_data);
                $diy_model->where([['id', '=', $v['id']]])->update(['value' => $diy_data]);
            }
        }

    }

}
