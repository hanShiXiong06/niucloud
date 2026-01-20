<?php
declare (strict_types=1);

namespace addon\tk_jhkd\app\listener\poster;

use app\model\member\Member;
use app\service\core\sys\CoreSysConfigService;

/**
 * 聚合快递海报
 */
class JhkdPoster
{
    /**
     * 商品海报
     * @param $data
     * @return array
     */
    public function handle($data)
    {
        $type = $data['type'];
        if ($type == 'tk_jhkd_poster') {
            // 商品 海报模板数据

            $site_id = $data['site_id'];
            $param = $data['param'];
            $member_id = $param['member_id'] ?? 0;
            $mode = $param['mode'] ?? '';
            if ($mode == 'preview') {
                // 预览模式
                $return_data = [
                    'nickname' => '会员昵称',
                    'headimg' => 'static/resource/images/default_headimg.jpg',
                    'url' => [
                        'url' => (new CoreSysConfigService())->getSceneDomain($site_id)['wap_url'],
                        'page' => 'addon/tk_jhkd/pages/ordersubmit',
                        'data' => [['key' => 'mid', 'value' => $member_id]],
                    ],
                ];
                return $return_data;
            }

            $return_data = [
                'url' => [
                    'url' => (new CoreSysConfigService())->getSceneDomain($site_id)['wap_url'],
                    'page' => 'addon/tk_jhkd/pages/ordersubmit',
                    'data' => [['key' => 'mid', 'value' => $member_id]],
                ],
            ];
            $member_info = [];
            if ($member_id > 0) {
                //查询会员信息
                $member_info = ( new Member() )->where([ [ 'member_id', '=', $member_id ], [ 'site_id', '=', $site_id ] ])->findOrEmpty();

                if (!empty($member_info)) {
                    if (empty($member_info[ 'headimg' ]) || !is_file($member_info[ 'headimg' ])) {
                        $member_info[ 'headimg' ] = 'static/resource/images/default_headimg.jpg';
                    }
                }
            }

            if (!empty($member_info)) {
                $return_data['nickname'] = mb_strlen($member_info['nickname']) > 10 ? mb_substr($member_info['nickname'], 0, 7, 'utf-8') . '...' : $member_info['nickname'];
                $return_data['headimg'] = $member_info['headimg'];
            }
            return $return_data;
        }
    }
}
