<?php
declare ( strict_types = 1 );

namespace addon\sd_xiaoyuan\app\listener\poster;

use app\model\member\Member;
use app\service\core\sys\CoreSysConfigService;

/**
 * 校园帮邀请海报数据
 */
class InvitePoster
{
    /**
     * 邀请海报
     * @param $data
     * @return array
     */
    public function handle($data)
    {
        $type = $data['type'];

        if ($type == 'xiaoyuan_invite') {
            $site_id = $data['site_id'];
            $param = input('');
            // 兼容多种参数名获取member_id
            $member_id = $param['member_id'] ?? $param['id'] ?? $param['mid'] ?? $param['posterId'] ?? 0;
            $member_id = (int)$member_id;
            $mode = $data['mode'] ?? ($param['mode'] ?? '');

            // 获取配置
            $coreConfigService = new \app\service\core\sys\CoreConfigService();
            $config_info = $coreConfigService->getConfig($site_id, 'SD_XIAOYUAN_CONFIG');
            $config = $config_info['value'] ?? [];
            $poster_bg_image = $config['invite_poster_bg'] ?? 'addon/sd_xiaoyuan/poster/invite_bg.png';
 
            if ($mode == 'preview') {
                $url_data = [
                    ['key' => 'mid', 'value' => $member_id]
                ];

                return [
                    'nickname' => '会员昵称',
                    'headimg' => 'static/resource/images/default_headimg.png',
                    'custom_image' => $poster_bg_image,
                    'slogan' => '校园帮 · 互助共赢',
                    'url' => [
                        'url' => (new CoreSysConfigService())->getSceneDomain($site_id)['wap_url'],
                        'page' => 'addon/sd_xiaoyuan/pages/index/index',
                        'data' => $url_data,
                    ],
                ];
            }

            $url_data = [
                ['key' => 'mid', 'value' => $member_id]
            ];

            $member_info = [];
            $headimg_path = 'static/resource/images/default_headimg.png';
            
            if ($member_id > 0) {
                $member = (new Member())->where([
                    ['member_id', '=', $member_id],
                    ['site_id', '=', $site_id]
                ])->findOrEmpty();
                if (!$member->isEmpty()) {
                    $member_info = $member->toArray();
                    $headimg = $member_info['headimg'] ?? '';
                    
                    if (!empty($headimg)) {
                        // 直接返回头像路径，让CorePosterService处理远程图片下载
                        $headimg_path = $headimg;
                    }
                }
            }

            $return_data = [
                'slogan' => '校园帮 · 互助共赢',
                'custom_image' => $poster_bg_image,
                'headimg' => $headimg_path,
                'url' => [
                    'url' => (new CoreSysConfigService())->getSceneDomain($site_id)['wap_url'],
                    'page' => 'addon/sd_xiaoyuan/pages/index/index',
                    'data' => $url_data,
                ]
            ];

            if (!empty($member_info)) {
                $return_data['nickname'] = mb_strlen($member_info['nickname']) > 10 ? mb_substr($member_info['nickname'], 0, 7, 'utf-8') . '...' : $member_info['nickname'];
            }

            return $return_data;
        }

        return [];
    }
}
