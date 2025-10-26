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

namespace addon\home_service\app\service\core\notice;


use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\model\notice\Notice;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 通知服务层
 * Class CoreNoticeService
 * @package addon\o2o\app\service\api\notice
 */
class CoreNoticeService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Notice();
    }

    /**
     * 获取通知信息
     * @param $where
     * @return array
     */
    public function getPage($where)
    {
        $search_model = $this->model->field('order_id,title,content,type,unread_count,create_time')->where($where);

        if ($where['notice_source'] == NoticeDict::ORDER){
            $search_model = $search_model->with(['order' =>function($query){
                $query->field('order_id,order_no,order_name,reserve_service_time_stamp');
            }]);
        }elseif ($where['notice_source'] == NoticeDict::BILL){
            $search_model = $search_model->append(['account']);
        }
        $search_model = $search_model->order('create_time desc');

        return $this->pageQuery($search_model,function($item, $key){
            $currentTime = time();
            $reserveTime = $item['order']['reserve_service_time_stamp'] ?? null;

            if (!$reserveTime) return; // 没有预约时间，直接跳过

            switch ($item['type']) {
                case NoticeDict::ABOUT_TO_TIMEOUT: // 即将超时
                    $timeDiff = $reserveTime - $currentTime;
                    break;
                case NoticeDict::TIMEOUT: // 已超时
                    $timeDiff = $currentTime - $reserveTime;
                    break;
                default:
                    return; // 其他类型不处理
            }
            list($days, $hours, $minutes) = $this->getTime($timeDiff);
            $time = ($days ? "{$days}天{$minutes}分钟" : ($hours ? "{$hours}小时{$minutes}分钟" : "{$minutes}分钟"));
            $item['content'] = str_replace("{time}", $time, $item['content']);
        });
    }

    public function getTime(int $diff, bool $withSeconds = false): array {
        $days = floor($diff / 86400);
        $remaining = $diff % 86400;
        $hours = floor($remaining / 3600);
        $remaining %= 3600;
        $minutes = floor($remaining / 60);
        $seconds = $remaining % 60;
        return $withSeconds ? [$days, $hours, $minutes, $seconds] : [$days, $hours, $minutes];
    }

    /**
     * 添加通知信息
     * @param $data
     */
    public function addNotice($data)
    {
        $save_data = [
            'user_source'=> $data['user_source'] ?? '',
            'notice_source'=> $data['notice_source'] ?? '',
            'uid'=> $data['uid'] ?? '',
            'title'=> $data['title'] ?? '',
            'content'=> $data['content'] ?? '',
            'order_id'=> $data['order_id'] ?? 0,
        ];
        if (empty($save_data['user_source']) || empty($save_data['notice_source']) || empty($save_data['uid'])) throw new CommonException('参数错误');
        $this->model->create($save_data);
        return true;
    }

    /**
     * 添加师傅通知信息
     * @param $site_id
     * @param $technician_notice_text
     * @param $user_source
     * @param $notice_source
     * @param $type
     * @param $uid
     * @param $order_id
     * @param $params
     */
    public function addTechnicianNotice($site_id,$technician_notice_text, $user_source, $notice_source, $type, $uid, $order_id = 0)
    {
        $where = [
            'user_source'=> $user_source,
            'notice_source'=> $notice_source,
            'uid'=> $uid,
            'order_id'=> $order_id,
        ];

        $save_data = array_merge($where,[
            'site_id'=> $site_id,
            'type'=> $type,
            'title'=> $technician_notice_text['title'] ?? '',
            'content'=> $technician_notice_text['content'] ?? '',
        ]);
        if (empty($save_data['user_source']) || empty($save_data['notice_source']) || empty($save_data['uid'])) throw new CommonException('参数错误');
        if ($save_data['notice_source'] == NoticeDict::ORDER){
            $notice_info = $this->model->where($where)->findOrEmpty();
            if (!$notice_info->isEmpty()){
                $notice_info->unread_count += 1;
                $notice_info->save($save_data);
            }else{
                $notice_info->unread_count = 1;
                $this->model->create($save_data);
            }
        }else{
            $this->model->create($save_data);
        }

        return true;
    }


}
