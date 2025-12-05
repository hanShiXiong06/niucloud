<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\ai_image\app\service\api\aiimagecreate;

use addon\ai_image\app\model\aiimagecreate\AiimageCreate;
use addon\ai_image\app\service\core\ConfigService;
use addon\ai_image\app\service\core\DuomiService;
use addon\ai_image\app\service\core\FetchService;
use app\dict\member\MemberAccountTypeDict;
use app\model\member\Member;
use addon\ai_image\app\model\aiimagemodel\AiimageModel;

use app\service\core\member\CoreMemberAccountService;
use core\base\BaseApiService;
use core\exception\CommonException;


/**
 * 作品列服务层
 * Class AiimageCreateService
 * @package addon\ai_image\app\service\admin\aiimagecreate
 */
class AiimageCreateService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new AiimageCreate();
    }

    public function getStat()
    {
        if ($this->member_id == 0) {
            return [
                'create_count' => 0,
                'building_count' => 0,
                'success_count' => 0,
                'fail_count' => 0,
                'point' => 0,
            ];
        }
        $create_count = $this->model->where(['site_id' => $this->site_id, 'member_id' => $this->member_id])->count();
        $building_count = $this->model->where(['site_id' => $this->site_id, 'member_id' => $this->member_id, 'status' => 0])->count();
        $success_count = $this->model->where(['site_id' => $this->site_id, 'member_id' => $this->member_id, 'status' => 1])->count();
        $fail_count = $this->model->where(['site_id' => $this->site_id, 'member_id' => $this->member_id])
            ->where([['status', 'in', [2, 3]]])
            ->count();
        $member = (new Member())->where([['member_id', '=', $this->member_id]])->findOrEmpty()->toArray();
        $point = $member['point'];
        return [
            'create_count' => $create_count,
            'building_count' => $building_count,
            'success_count' => $success_count,
            'fail_count' => $fail_count,
            'point' => $point,
        ];

    }

    /**
     * 获取作品列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,model_id,prompt,image_urls,aspect_ratio,images,status,point,is_self,msg,platform,channel,create_time';
        $order = 'create_time desc';

        $search_model = $this->model->where([['site_id', "=", $this->site_id]])
            ->where([
                ['member_id', '=', $this->member_id]
            ])
            ->withSearch(["member_id", "model_id", "status", "create_time"], $where)->with(['member', 'aiimageModel'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取作品列信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $info = $this->model->where([['id', "=", $id]])->with(['member', 'aiimageModel'])->findOrEmpty()->toArray();
        //判断状态进行生成查询
        $config = (new ConfigService())->getConfig();
        $result = (new DuomiService($config))->query($info['task_id']);
        if ($result['code'] == 200 && $info['status'] == 0) {
            if ($result['data']['state'] == 'succeeded') {
               $url= (new FetchService())
                    ->save(
                        $result['data']['data']['images'][0]['url'],
                        $this->site_id,
                        'ai_image/' . $this->site_id . '/' . date('Y') . date('m') . date('d')
                    );
                $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
                    ->update([
                        'status' => 1,
                        'images' => $url
                    ]);
            }
            if ($result['data']['state'] == 'error') {
                $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
                    ->update([
                        'state' => 'error',
                        'status' => 2,
                        'msg' => $result['data']['msg']
                    ]);
            }
            $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        }
        return $info;
    }

    /**
     * 添加作品列
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $data['status'] = 0;
        $config = (new ConfigService())->getConfig();
        $point = (new Member())->where(['member_id' => $this->member_id])->value('point');
        if ($data['model_id'] > 0) {
            $model = (new AiimageModel())->where([['id', '=', $data['model_id']]])->findOrEmpty();
            if ($model->isEmpty()) throw new CommonException('模型不存在');
            $data['point'] = $model['point'];
            $data['channel']=$model['model'];
            $data['platform']='duomi';
            if ($model['is_prompt'] == 0) {
                $data['prompt'] = $model['prompt'];
            }
            if ($model['is_upload_image'] == 0) {
                $data['image_urls'] = [];
            }
        } else {
            $data['point'] = $config['create_point'];
            $data['channel']='';
        }
        if ($data['image_urls']) {
            foreach ($data['image_urls'] as $key => $value) {
                $data['image_urls'][$key] = $this->getFileUrl($value);
            }
        }
        if ($point < $data['point']) {
            throw new CommonException('积分不足');
        }
        //提交接口人物
        $result = (new DuomiService($config))->create($data);
        $data['task_id'] = $result['data']['task_id'];
        $data['is_self'] = 1;
        $res = $this->model->create($data);
        if ($data['point'] > 0) {
            (new CoreMemberAccountService())->addLog($this->site_id, $this->member_id, MemberAccountTypeDict::POINT, -$data['point'], 'ai_image_create', 'AI设计创作消耗积分', '');
        }
        return $res->id;

    }

    public function getFileUrl($url)
    {
        if ($url == '') return '';
        $domain = $this->getDomainUrl();
        if (strpos($url, $domain) === 0) {
            return $url;
        }
        if (strpos($url, 'http') === false) {
            return $domain . '/' . ltrim($url, '/');
        }
        return $url;
    }

    public function getDomainUrl()
    {
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $domain = $_SERVER['HTTP_HOST'];
        if ($isSecure) {
            $url = 'https://' . $domain;
        } else {
            $url = 'http://' . $domain;
        }
        return $url;
    }

    /**
     * 作品列编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除作品列
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }

    public function getMemberAll()
    {
        $memberModel = new Member();
        return $memberModel->where([["site_id", "=", $this->site_id]])->select()->toArray();
    }

    public function getAiimageModelAll()
    {
        $aiimageModelModel = new AiimageModel();
        return $aiimageModelModel->where([["site_id", "=", $this->site_id]])->select()->toArray();
    }

}
