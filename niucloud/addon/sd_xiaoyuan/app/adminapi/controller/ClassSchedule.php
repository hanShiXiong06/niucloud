<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\ClassScheduleService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 班级课表管理控制器
 */
class ClassSchedule extends BaseAdminController
{
    /**
     * 课表列表(分页)
     */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['class_id', 0],
            ['semester', ''],
            ['page', 1],
            ['limit', 10],
        ]);

        $list = (new ClassScheduleService())->getPage($data);
        return success($list);
    }

    /**
     * 添加课程
     */
    public function add(): Response
    {
        $data = $this->request->params([
            ['school_id', 0],
            ['class_id', 0],
            ['semester', ''],
            ['week_day', 1],
            ['section_start', 1],
            ['section_end', 1],
            ['course_code', ''],
            ['course_name', ''],
            ['teacher', ''],
            ['classroom', ''],
            ['location', ''],
            ['credit', 0],
            ['start_week', 1],
            ['end_week', 16],
            ['week_type', 0],
            ['color', ''],
            ['weeks_text', ''],
        ]);

        if (empty($data['course_name'])) {
            return fail('请填写课程名称');
        }
        if (empty($data['class_id'])) {
            return fail('请选择班级');
        }
        if (empty($data['semester'])) {
            return fail('请填写学期');
        }

        $id = (new ClassScheduleService())->add($data);
        return success(['id' => $id]);
    }

    /**
     * 编辑课程
     */
    public function edit(): Response
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['school_id', 0],
            ['class_id', 0],
            ['semester', ''],
            ['week_day', 1],
            ['section_start', 1],
            ['section_end', 1],
            ['course_code', ''],
            ['course_name', ''],
            ['teacher', ''],
            ['classroom', ''],
            ['location', ''],
            ['credit', 0],
            ['start_week', 1],
            ['end_week', 16],
            ['week_type', 0],
            ['color', ''],
            ['weeks_text', ''],
        ]);

        if (empty($id)) {
            return fail('参数错误');
        }

        (new ClassScheduleService())->edit((int)$id, $data);
        return success('编辑成功');
    }

    /**
     * 删除课程
     */
    public function del(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }

        (new ClassScheduleService())->del((int)$id);
        return success('删除成功');
    }

    /**
     * 清空班级课表
     */
    public function clear(): Response
    {
        $class_id = $this->request->param('class_id', 0);
        $semester = $this->request->param('semester', '');

        if (empty($class_id) || empty($semester)) {
            return fail('参数错误');
        }

        (new ClassScheduleService())->clear((int)$class_id, $semester);
        return success('清空成功');
    }

    /**
     * 导入课表（文件上传方式）
     */
    public function import(): Response
    {
        $school_id = intval($this->request->param('school_id', 0));
        if (empty($school_id)) {
            return fail('请选择学校');
        }

        $file = $this->request->file('file');
        if (empty($file)) {
            return fail('请上传文件');
        }

        $stat = (new ClassScheduleService())->importFromFile($school_id, $file);
        return success($stat);
    }
}
