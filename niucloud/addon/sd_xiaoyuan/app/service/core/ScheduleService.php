<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Schedule;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 课表服务
 */
class ScheduleService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取课表
     */
    public function getSchedule($semester = '')
    {
        if (empty($semester)) {
            $semester = $this->getCurrentSemester();
        }

        $record = (new Schedule())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['semester', '=', $semester]
        ])->find();

        // 初始化空课表
        $schedule = [];
        for ($i = 1; $i <= 7; $i++) {
            $schedule[$i] = [];
        }

        $list = [];
        if ($record && !empty($record['schedule_data'])) {
            $scheduleData = json_decode($record['schedule_data'], true);
            if (is_array($scheduleData)) {
                foreach ($scheduleData as $item) {
                    $weekDay = $item['week_day'] ?? 1;
                    $schedule[$weekDay][] = $item;
                    $list[] = $item;
                }
            }
        }

        return [
            'semester' => $semester,
            'week_start' => $record['week_start'] ?? null,
            'total_weeks' => $record['total_weeks'] ?? 20,
            'schedule' => $schedule,
            'list' => $list
        ];
    }

    /**
     * 添加课程
     */
    public function addCourse($data)
    {
        $semester = $data['semester'] ?? $this->getCurrentSemester();

        // 查找或创建课表记录
        $record = (new Schedule())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['semester', '=', $semester]
        ])->find();

        // 新课程数据
        $newCourse = [
            'id' => uniqid(),
            'week_day' => intval($data['week_day']),
            'school_id' => intval($data['school_id']),
            'section' => intval($data['section']),
            'course_name' => $data['course_name'],
            'teacher' => $data['teacher'] ?? '',
            'classroom' => $data['classroom'] ?? '',
            'start_week' => intval($data['start_week'] ?? 1),
            'end_week' => intval($data['end_week'] ?? 16),
            'week_type' => intval($data['week_type'] ?? 0),
            'color' => $data['color'] ?? $this->getRandomColor(),
            'remark' => $data['remark'] ?? ''
        ];

        if ($record) {
            // 更新现有记录
            $scheduleData = json_decode($record['schedule_data'], true) ?: [];
            $scheduleData[] = $newCourse;
            $record->save([
                'schedule_data' => json_encode($scheduleData, JSON_UNESCAPED_UNICODE),
                'update_time' => time()
            ]);
        } else {
            // 创建新记录，school_id从前端传递
            $schedule = new Schedule();
            $schedule->save([
                'site_id' => $this->site_id,
                'member_id' => $this->member_id,
                'school_id' => $data['school_id'] ?? 0,
                'semester' => $semester,
                'schedule_data' => json_encode([$newCourse], JSON_UNESCAPED_UNICODE),
                'total_weeks' => 20,
                'create_time' => time(),
                'update_time' => time()
            ]);
        }

        return ['id' => $newCourse['id']];
    }

    /**
     * 更新课程
     */
    public function updateCourse($id, $data)
    {
        $semester = $data['semester'] ?? $this->getCurrentSemester();

        $record = (new Schedule())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['semester', '=', $semester]
        ])->find();

        if (empty($record) || empty($record['schedule_data'])) {
            throw new CommonException('课程不存在');
        }

        $scheduleData = json_decode($record['schedule_data'], true) ?: [];
        $found = false;

        foreach ($scheduleData as &$course) {
            if ($course['id'] == $id) {
                $course['week_day'] = intval($data['week_day'] ?? $course['week_day']);
                $course['section'] = intval($data['section'] ?? $course['section']);
                $course['course_name'] = $data['course_name'] ?? $course['course_name'];
                $course['teacher'] = $data['teacher'] ?? $course['teacher'];
                $course['classroom'] = $data['classroom'] ?? $course['classroom'];
                $course['start_week'] = intval($data['start_week'] ?? $course['start_week']);
                $course['end_week'] = intval($data['end_week'] ?? $course['end_week']);
                $course['week_type'] = intval($data['week_type'] ?? $course['week_type']);
                $course['color'] = $data['color'] ?? $course['color'];
                $course['remark'] = $data['remark'] ?? $course['remark'];
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new CommonException('课程不存在');
        }

        $record->save([
            'schedule_data' => json_encode($scheduleData, JSON_UNESCAPED_UNICODE),
            'update_time' => time()
        ]);

        return true;
    }

    /**
     * 删除课程
     */
    public function deleteCourse($id)
    {
        $semester = $this->getCurrentSemester();

        $record = (new Schedule())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['semester', '=', $semester]
        ])->find();

        if (empty($record) || empty($record['schedule_data'])) {
            throw new CommonException('课程不存在');
        }

        $scheduleData = json_decode($record['schedule_data'], true) ?: [];
        $newData = array_filter($scheduleData, function($course) use ($id) {
            return $course['id'] != $id;
        });

        if (count($newData) == count($scheduleData)) {
            throw new CommonException('课程不存在');
        }

        $record->save([
            'schedule_data' => json_encode(array_values($newData), JSON_UNESCAPED_UNICODE),
            'update_time' => time()
        ]);

        return true;
    }

    /**
     * 清空课表
     */
    public function clearSchedule($semester = '')
    {
        if (empty($semester)) {
            $semester = $this->getCurrentSemester();
        }

        $record = (new Schedule())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['semester', '=', $semester]
        ])->find();

        if ($record) {
            $record->save([
                'schedule_data' => json_encode([], JSON_UNESCAPED_UNICODE),
                'update_time' => time()
            ]);
        }

        return true;
    }

    /**
     * 获取当前学期
     */
    private function getCurrentSemester()
    {
        $month = date('n');
        $year = date('Y');
        
        if ($month >= 9) {
            return $year . '-' . ($year + 1) . '-1';
        } else if ($month >= 2) {
            return ($year - 1) . '-' . $year . '-2';
        } else {
            return ($year - 1) . '-' . $year . '-1';
        }
    }

    /**
     * 获取课程详情
     */
    public function getCourseDetail($id)
    {
        $semester = $this->getCurrentSemester();

        $record = (new Schedule())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['semester', '=', $semester]
        ])->find();

        if (empty($record) || empty($record['schedule_data'])) {
            throw new CommonException('课程不存在');
        }

        $scheduleData = json_decode($record['schedule_data'], true) ?: [];
        
        foreach ($scheduleData as $course) {
            if ($course['id'] == $id) {
                return $course;
            }
        }

        throw new CommonException('课程不存在');
    }

    /**
     * 获取随机颜色
     */
    private function getRandomColor()
    {
        $colors = [
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4',
            '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F',
            '#BB8FCE', '#85C1E9', '#F8B500', '#00CED1'
        ];
        return $colors[array_rand($colors)];
    }
}
