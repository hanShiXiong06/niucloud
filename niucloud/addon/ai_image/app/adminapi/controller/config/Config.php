<?php

namespace addon\ai_image\app\adminapi\controller\config;

use addon\ai_image\app\service\core\CloudService;
use core\base\BaseAdminController;
use addon\ai_image\app\service\core\ConfigService;


class Config extends BaseAdminController
{

    /**
     * Author: TK
     * Notes:  获取配置信息
     * @return \think\Response
     * 2025/10/15 22:39
     */
    public function getConfig()
    {
        return success("操作成功", (new ConfigService())->getConfig());
    }

    /**
     * Author: TK
     * Notes:  设置配置信息
     * @return \think\Response
     * 2025/10/15 22:40
     */

    public function setConfig()
    {
        $data = $this->request->params([
            ['ai_model', ''],
            ['ai_key', ''],
            ['ai_host', ''],
            ["duomi_key", ''],
            ["alias_name",'积分'],//别名
            ["chat_point", ''],//对话消耗
            ["create_point", ''],//创建消耗
            ["give_point", ''],//新用户赠送
            ["share_point", ''],//邀请新用户赠送
            ["create_point", ''],//创建
            ["platform", 'duomi'],
            ["notice", 'AI生成'],
            ['ios_pay', '0'],
            ['ios_notice', '暂时不运行IOS设备支付'],
            ['is_scan', 0],
            ['public_key', ''],
            ['private_key', ''],
            ['org_id', ''],
            ['pc_pay', 0],
            ['pc_notice', '暂时不运行PC端支付'],
            ['mno', ''],
        ]);
        (new CloudService())->auth();
        (new ConfigService())->setConfig($data);
        return success('操作成功');
    }
    public function getSxfConfig()
    {
        return success("操作成功", (new ConfigService())->getSxfConfig());
    }

    /**
     * Author: TK
     * Notes:  设置配置信息
     * @return \think\Response
     * 2025/10/15 22:40
     */
    public function setSxfConfig()
    {
        $data = $this->request->params([
            ['public_key', ''],
            ['private_key', ''],
            ['org_id', ''],
        ]);
        (new ConfigService())->setSxfConfig($data);
        return success('操作成功');
    }
}
