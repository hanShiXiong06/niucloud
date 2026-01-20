<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\adminapi\controller\config;

use addon\tk_jhkd\app\service\admin\platform\PlatformService;
use core\base\BaseAdminController;
use core\exception\AdminException;
use think\Response;

class Platform extends BaseAdminController
{

    /**
     * 配置列表
     */
    public function platformList()
    {
        $res = (new PlatformService())->getList();
        return success($res);
    }

    /**
     * 配置详情
     * @param $sms_type
     * @return Response
     */
    public function platformConfig($type)
    {
        $res = (new PlatformService())->getConfig($type);
        return success($res);
    }

    /**
     * 配置修改
     * @return Response
     */
    public function editPlatform($type)
    {
        //参数获取
        $platform_type_list = event('JhkdPlatformType')[0];
        $list1=[];
        foreach ($platform_type_list as $k => $v){
            foreach ($v as $k1=>$v1){
                $list1[$v1['type']][]=$v1;
            }
        }
        $platform_type_list=$list1;
        if(!array_key_exists($type, $platform_type_list)) throw new AdminException('PLATFORM_TYPE_NOT_EXIST');
        //数据验证
        $data = [
            ['is_use', 0]
        ];
        foreach ($platform_type_list[$type][0]['params'] as $k_param => $v_param) {
            $data[] = [$k_param, ''];
        }
        $request_data = $this->request->params($data);
        (new PlatformService())->setConfig($type, $request_data);
        return success();
    }

}
