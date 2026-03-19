<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\ScheduleService;
use core\base\BaseApiController;

/**
 * 课表控制器
 */
class Schedule extends BaseApiController
{
    /**
     * 获取课表
     */
    public function index()
    {
        $semester = $this->request->param('semester', '');
        $service = new ScheduleService();
        $data = $service->getSchedule($semester);
        return success($data);
    }

    /**
     * 添加课程
     */
    public function add()
    {
        $data = $this->request->params([
            ['semester', ''],
            ['week_day', 1],
            ['section', 1],
            ['course_name', ''],
            ['teacher', ''],
            ['classroom', ''],
            ['start_week', 1],
            ['end_week', 16],
            ['week_type', 0],
            ['color', ''],
            ['remark', ''],
            ['school_id', 0]
        ]);

        $service = new ScheduleService();
        $data = $service->addCourse($data);
        return success($data);
    }

    /**
     * 获取课程详情
     */
    public function detail()
    {
        $id = $this->request->param('id');
        $service = new ScheduleService();
        $data = $service->getCourseDetail($id);
        return success($data);
    }

    /**
     * 更新课程
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $data = $this->request->params([
            ['week_day', null],
            ['section', null],
            ['course_name', null],
            ['teacher', null],
            ['classroom', null],
            ['start_week', null],
            ['end_week', null],
            ['week_type', null],
            ['color', null],
            ['remark', null],
            ['school_id', null]
        ]);

        $service = new ScheduleService();
        $service->updateCourse($id, $data);
        return success();
    }

    /**
     * 删除课程
     */
    public function delete()
    {
        $id = $this->request->param('id');
        $service = new ScheduleService();
        $service->deleteCourse($id);
        return success();
    }

    /**
     * 清空课表
     */
    public function clear()
    {
        $semester = $this->request->param('semester', '');
        $service = new ScheduleService();
        $service->clearSchedule($semester);
        return success();
    }
}
