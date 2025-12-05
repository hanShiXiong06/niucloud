<?php

namespace addon\ai_image\app\service\core;

use app\dict\member\MemberAccountTypeDict;
use app\model\member\Member;
use app\service\core\member\CoreMemberAccountService;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Db;
use function DI\create;

class ChatStreamService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取基本配置
     * @return mixed
     */
    public function getConfig($site_id)
    {
        return (new ConfigService())->getConfigSite($site_id);
    }

    public function sendText($data)
    {
        //进行对话封装
        $config = $this->getConfig($this->site_id);
        $chat_point = $config['chat_point'] ?? 0;
        $point=(new Member())->where(['member_id'=>$this->member_id])->value('point');
        if($point<$chat_point){
            throw new CommonException('积分不足');
        }
        $req_site_id = $this->site_id;
        $prompt = '将上面提示词进行优化成专业的图像生成提示词,描述可以使用英文,关键字需要使用上面提示词的关键字';
        $content = $data['prompt'] . $prompt;
        $msg = [["role" => "user", "content" => $content]];
        $res = (new Openai($this->getConfig($req_site_id)))->sendText($msg);
        //进行会员消耗点数扣除
        if ($chat_point > 0) {
            (new CoreMemberAccountService())->addLog($this->site_id, $this->member_id, MemberAccountTypeDict::POINT, -$chat_point, 'ai_image_chat', 'AI会话消耗积分', '');
        }
        return $res['msg'];

    }

}
