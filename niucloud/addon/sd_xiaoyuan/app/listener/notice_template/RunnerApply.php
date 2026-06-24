<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\notice_template;

use addon\sd_xiaoyuan\app\model\runner\Runner;
use addon\sd_xiaoyuan\app\service\core\NoticeWechatService;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 骑手入驻申请通知管理员
 */
class RunnerApply extends BaseNoticeTemplate
{
    private $key = 'sd_xiaoyuan_runner_apply';

    /**
     * 组装公众号模板变量
     */
    public function handle(array $params)
    {
        if ($this->key != $params['key']) {
            return;
        }
        $runner_id = (int)($params['data']['runner_id'] ?? 0);
        $member_id = (int)($params['data']['member_id'] ?? 0);
        if (!$runner_id || !$member_id) {
            return;
        }
        $runner = (new Runner())->where('id', $runner_id)->find();
        if (empty($runner)) {
            return;
        }
        $time_val = $runner->getData('update_time') ?: $runner->getData('create_time');
        if (is_numeric($time_val) && (int)$time_val > 946684800) {
            $time_ts = (int)$time_val;
        } elseif (!empty($time_val) && strtotime((string)$time_val)) {
            $time_ts = strtotime((string)$time_val);
        } else {
            $time_ts = time();
        }
        $admin_url = (new NoticeWechatService())->getAdminRunnerListUrl((int)$runner['site_id']);
        return $this->toReturn(
            [
                '__wechat_page' => $admin_url,
                '__weapp_page' => '',
                'real_name' => str_sub2((string)$runner['real_name'], 20, false),
                'mobile' => (string)$runner['mobile'],
                'apply_time' => date('Y-m-d H:i:s', $time_ts),
                'url' => $admin_url,
            ],
            [
                'member_id' => $member_id,
            ]
        );
    }
}
