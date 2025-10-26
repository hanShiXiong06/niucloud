<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\api\technician;

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\model\notice\Notice;
use addon\home_service\app\service\core\notice\CoreNoticeService;
use core\base\BaseApiService;

/**
 * 通知服务层
 * Class NoticeService
 * @package addon\o2o\app\service\api\notice
 */
class NoticeService extends BaseApiService
{


    use TechnicianTrait;


    public function __construct()
    {
        parent::__construct();
        $this->checkTechnician();
    }

    /**
     * 获取通知信息
     * @param $data
     * @return array
     */
    public function getPage($data)
    {
        $where = [
            'user_source' => NoticeDict::TECHNICIAN,
            'notice_source' => $data['notice_source'],
            'uid' => $this->technician_id,
            'site_id' => $this->site_id,
        ];
        return (new CoreNoticeService())->getPage($where);
    }

    /**
     * 获取通知信息
     * @return array
     */
    public function getNoticeSource()
    {
        $notice_dict = NoticeDict::getNoticeSource();

        $result = [];
        foreach ($notice_dict as $key => $value) {
            $query = (new Notice())->where([
                ['site_id', '=', $this->site_id],
                ['uid', '=', $this->technician_id],
                ['user_source', '=', NoticeDict::TECHNICIAN],
                ['notice_source', '=', $key]
            ]);

            switch ($key) {
                case 'order':
                    // sum 未读数量
                    $count = (int)$query->sum('unread_count');
                    break;

                case 'bill':
                    // count 数量
                    $count = '';
                    break;

                case 'system':
                default:
                    $count = 0;
                    break;
            }
            $result[] = [
                'key' => $key,
                'value' => $value,
                'count' => $count ?? 0,
            ];
        }
        return $result;
    }




}
