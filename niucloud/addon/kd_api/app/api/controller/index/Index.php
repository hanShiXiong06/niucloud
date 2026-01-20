<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\kd_api\app\api\controller\index;
use addon\kd_api\app\service\api\IndexService;
use core\base\BaseApiController;
use think\Response;


/**
 * 代理商配置
 */
class Index extends BaseApiController
{
    /**
     * 获取配置
     * @return Response
     */
    public function getConfig()
    {
        return success((new IndexService())->getConfig());
    }

    public function resetKey()
    {
        return success('密钥已重置', (new IndexService())->resetKey());
    }

    public function saveCallbackUrl()
    {
        $data = $this->request->params([
            ["callback_url", ""]
        ]);
        return success('回调地址已保存', (new IndexService())->saveCallbackUrl($data));
    }
    public function getOrder(){
        $data = $this->request->params([
            ["member_id",""],
            ["order_id",""],
            ["title",""],
            ["status",""],
            ["is_js",""],
            ["sid",""],
            ["pub_id",""],
            ["create_time",["",""]]
        ]);
        return success((new IndexService())->getOrder($data));
    }
}
