<?php

namespace addon\fast_pay\app\adminapi\controller\config;

use addon\fast_pay\app\service\core\config\ConfigService;
use core\base\BaseAdminController;


class Config extends BaseAdminController
{
    /**
     * @Notes 生成收款码海报
     * @Interface poster
     * @return \think\Response
     * @author: TK
     * @Time: 2024/8/7   上午8:15
     */
    public function poster()
    {
        $data = $this->request->params([
            ['id', 0], // 海报id
            ['type', 'fast_pay_poster'], // 海报类型
            ['param', []], // 数据参数
            ['channel', '']
        ]);
        return success('生成成功', (new ConfigService())->poster($data));
    }

    /**
     * 接口数据
     */
    public function getConfig()
    {
        return success("操作成功", (new ConfigService())->getConfig());
    }

    public function setConfig()
    {
        $data = $this->request->params([
            ['business_name', '商家收银台'],
            ['business_logo', '商家LOGO'],
            ['banener', ''],
            ["desc", '商家描述'],
            ["type", '0'],//支付后跳转类型    0视频号首页  1视频号视频  2 内置页面
            ["finderUserName", ''],    //视频号首页ID
            ["feedId", ''],    //视频ID
            ["page", ''],    //内置页面跳转
        ]);
        (new ConfigService())->setConfig($data);
        return success('操作成功');
    }

    public function getAdminConfig()
    {
        return success("操作成功", (new ConfigService())->getAdminConfig());
    }

    public function setAdminConfig()
    {
        $data = $this->request->params(
            [
                ['app_id', ''],
                ['mch_id', ''],
                ['mch_secret_key', ''],
                ['mch_secret_cert', ''],
                ['mch_public_cert_path', ''],
            ]);
        (new ConfigService())->setAdminConfig($data);
        return success('操作成功');
    }
    public function getBusinessConfig()
    {
        return success("操作成功", (new ConfigService())->getBusinessPayConfig());
    }

    public function setBusinessConfig()
    {
        $data = $this->request->params(
            [
                ['app_id', ''],
                ['mch_id', ''],
                ['mch_secret_key', ''],
                ['mch_secret_cert', ''],
                ['mch_public_cert_path', ''],
            ]);
        (new ConfigService())->setBusinessConfig($data);
        return success('操作成功');
    }
}
