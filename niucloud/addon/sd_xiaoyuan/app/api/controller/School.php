<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\SchoolService;
use addon\sd_xiaoyuan\app\service\core\SchoolClassService;
use core\base\BaseApiController;
use think\Response;

/**
 * 学校接口
 */
class School extends BaseApiController
{
    /**
     * 获取学校列表
     */
    public function list(): Response
    {
        // Debug: Log controller access
        \think\facade\Log::info('School API list method called');
        
        $data = $this->request->params([
            ['name', ''],
            ['province', ''],
            ['city', ''],
        ]);
        
        // Debug: Log request data
       $list = (new SchoolService())->getList($data);
     
         return success($list);
    }

    /**
     * 获取学校列表 (别名方法，兼容路由)
     */
    public function lists(): Response
    {
        return $this->list();
    }

    /**
     * 获取学校详情
     */
    public function info(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $info = (new SchoolService())->getInfo((int)$id);
        return success($info);
    }

    /**
     * 获取学校校区列表
     */
    public function campusList(): Response
    {
        $school_id = $this->request->param('school_id', 0);
        if (empty($school_id)) {
            return fail('参数错误');
        }

        $list = (new SchoolService())->getCampusList((int)$school_id);
        return success($list);
    }

    /**
     * 获取学校年级列表
     */
    public function grades(): Response
    {
        $school_id = $this->request->param('school_id', 0);
        if (empty($school_id)) {
            return fail('参数错误');
        }

        $list = (new SchoolClassService())->getGrades((int)$school_id);
        return success($list);
    }

    /**
     * 获取班级列表
     */
    public function classes(): Response
    {
        $data = $this->request->params([
            ['school_id', 0],
            ['grade', ''],
        ]);

        if (empty($data['school_id'])) {
            return fail('参数错误');
        }

        $list = (new SchoolClassService())->getList($data);
        return success($list);
    }
}
