<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Schedule;
use addon\sd_xiaoyuan\app\model\ClassSchedule;
use addon\sd_xiaoyuan\app\model\School;
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
    public function getSchedule($semester = '', $school_id = 0)
    {
        if (empty($semester)) {
            $semester = $this->getCurrentSemester();
        }

        $record = (new Schedule())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['semester', '=', $semester]
        ])->find();

        // 无记录 → 检查学校是否有班级课表
        if (!$record) {
            if ($school_id > 0) {
                // 检查该学校是否有班级课表数据
                $hasClassSchedule = (new ClassSchedule())->where([
                    ['site_id', '=', $this->site_id],
                    ['school_id', '=', $school_id],
                    ['semester', '=', $semester]
                ])->count();
                if ($hasClassSchedule > 0) {
                    // 学校有课表，让前端引导用户选班级
                    return [
                        'need_init' => true,
                        'has_class_schedule' => true,
                        'semester' => $semester,
                        'week_start' => null,
                        'total_weeks' => 20,
                        'is_custom' => 0,
                        'schedule' => $this->emptySchedule(),
                        'list' => []
                    ];
                }
            }
            // 学校没有课表或没有学校，自动创建空的自定义课表
            (new Schedule())->create([
                'member_id' => $this->member_id,
                'site_id' => $this->site_id,
                'semester' => $semester,
                'is_custom' => 1,
                'class_id' => 0,
                'school_id' => $school_id,
                'schedule_data' => '{}',
                'total_weeks' => 20,
                'create_time' => time(),
                'update_time' => time(),
            ]);
            return [
                'semester' => $semester,
                'week_start' => null,
                'total_weeks' => 20,
                'is_custom' => 1,
                'schedule' => $this->emptySchedule(),
                'list' => []
            ];
        }

        // 已自定义 → 检查是否为空课表，空课表且学校有班级课表时引导选择
        if ($record['is_custom'] == 1) {
            $scheduleData = json_decode($record['schedule_data'] ?: '{}', true);
            $isEmpty = empty($scheduleData) || $scheduleData === [];
            if ($isEmpty && $school_id > 0) {
                $hasClassSchedule = (new ClassSchedule())->where([
                    ['site_id', '=', $this->site_id],
                    ['school_id', '=', $school_id],
                    ['semester', '=', $semester]
                ])->count();
                if ($hasClassSchedule > 0) {
                    return [
                        'need_init' => true,
                        'has_class_schedule' => true,
                        'semester' => $semester,
                        'week_start' => $record['week_start'] ?? null,
                        'total_weeks' => $record['total_weeks'] ?? 20,
                        'is_custom' => 0,
                        'schedule' => $this->emptySchedule(),
                        'list' => []
                    ];
                }
            }
            return $this->formatPersonalSchedule($record, $semester);
        }

        // 使用班级课表 → 从 class_schedule 表查询
        if ($record['class_id'] > 0) {
            $courses = (new ClassSchedule())->where([
                ['site_id', '=', $this->site_id],
                ['class_id', '=', $record['class_id']],
                ['semester', '=', $semester]
            ])->select()->toArray();

            // 获取学校的学期时间配置
            $schoolInfo = null;
            if ($record['school_id'] > 0) {
                $schoolInfo = (new School())->where('id', $record['school_id'])->field('semester_start,semester_end,sections')->find();
            }

            return $this->formatClassSchedule($courses, $record, $semester, $schoolInfo);
        }

        return [
            'semester' => $semester,
            'week_start' => $record['week_start'] ?? null,
            'total_weeks' => $record['total_weeks'] ?? 20,
            'is_custom' => 1,
            'schedule' => $this->emptySchedule(),
            'list' => []
        ];
    }

    /**
     * 格式化个人课表
     */
    private function formatPersonalSchedule($record, $semester)
    {
        $schedule = $this->emptySchedule();
        $list = [];

        if (!empty($record['schedule_data'])) {
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
            'class_id' => $record['class_id'] ?? 0,
            'is_custom' => $record['is_custom'] ?? 0,
            'schedule' => $schedule,
            'list' => $list
        ];
    }

    /**
     * 格式化班级课表
     */
    private function formatClassSchedule($courses, $record, $semester, $schoolInfo = null)
    {
        $schedule = $this->emptySchedule();
        $list = [];

        foreach ($courses as $course) {
            $item = [
                'id' => 'cls_' . $course['id'],
                'week_day' => $course['week_day'],
                'section' => $course['section_start'],
                'section_start' => $course['section_start'],
                'section_end' => $course['section_end'],
                'course_name' => $course['course_name'],
                'course_code' => $course['course_code'] ?? '',
                'teacher' => $course['teacher'] ?? '',
                'classroom' => $course['classroom'] ?? '',
                'location' => $course['location'] ?? '',
                'credit' => $course['credit'] ?? 0,
                'start_week' => $course['start_week'] ?? 1,
                'end_week' => $course['end_week'] ?? 16,
                'week_type' => $course['week_type'] ?? 0,
                'color' => $course['color'] ?? '#4ECDC4',
                'remark' => '',
                'is_class_course' => true,
            ];
            $weekDay = $course['week_day'];
            $schedule[$weekDay][] = $item;
            $list[] = $item;
        }

        // 优先使用学校配置的学期时间
        $weekStart = $record['week_start'] ?? null;
        $semesterEnd = null;
        $totalWeeks = $record['total_weeks'] ?? 20;

        if ($schoolInfo) {
            if (!empty($schoolInfo['semester_start'])) {
                $weekStart = $schoolInfo['semester_start'];
            }
            if (!empty($schoolInfo['semester_end'])) {
                $semesterEnd = $schoolInfo['semester_end'];
            }
            if ($weekStart && $semesterEnd) {
                $diff = strtotime($semesterEnd) - strtotime($weekStart);
                if ($diff > 0) {
                    $totalWeeks = (int)ceil($diff / (7 * 86400));
                }
            }
        }

        // 解析学校课节时间
        $sections = [];
        if ($schoolInfo && !empty($schoolInfo['sections'])) {
            $sections = json_decode($schoolInfo['sections'], true) ?: [];
        }

        return [
            'semester' => $semester,
            'week_start' => $weekStart,
            'semester_end' => $semesterEnd,
            'total_weeks' => $totalWeeks,
            'class_id' => $record['class_id'] ?? 0,
            'is_custom' => 0,
            'sections' => $sections,
            'schedule' => $schedule,
            'list' => $list
        ];
    }

    /**
     * 空课表结构
     */
    private function emptySchedule()
    {
        $schedule = [];
        for ($i = 1; $i <= 7; $i++) {
            $schedule[$i] = [];
        }
        return $schedule;
    }

    /**
     * 绑定班级课表
     */
    public function bindClass($data)
    {
        $semester = $data['semester'] ?? $this->getCurrentSemester();
        $class_id = intval($data['class_id']);
        $school_id = intval($data['school_id'] ?? 0);

        $record = (new Schedule())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['semester', '=', $semester]
        ])->find();

        if ($record) {
            $record->save([
                'class_id' => $class_id,
                'school_id' => $school_id,
                'is_custom' => 0,
                'schedule_data' => json_encode([], JSON_UNESCAPED_UNICODE),
                'update_time' => time()
            ]);
        } else {
            (new Schedule())->save([
                'site_id' => $this->site_id,
                'member_id' => $this->member_id,
                'school_id' => $school_id,
                'class_id' => $class_id,
                'semester' => $semester,
                'is_custom' => 0,
                'schedule_data' => json_encode([], JSON_UNESCAPED_UNICODE),
                'total_weeks' => 20,
                'create_time' => time(),
                'update_time' => time()
            ]);
        }

        return true;
    }

    /**
     * 班级课表转私有(复制班级课表数据到schedule_data)
     */
    private function convertToCustom($record, $semester)
    {
        if ($record['is_custom'] == 1) return;
        if ($record['class_id'] <= 0) return;

        $courses = (new ClassSchedule())->where([
            ['site_id', '=', $this->site_id],
            ['class_id', '=', $record['class_id']],
            ['semester', '=', $semester]
        ])->select()->toArray();

        $scheduleData = [];
        foreach ($courses as $course) {
            $scheduleData[] = [
                'id' => uniqid(),
                'week_day' => $course['week_day'],
                'section' => $course['section_start'],
                'course_name' => $course['course_name'],
                'teacher' => $course['teacher'] ?? '',
                'classroom' => $course['classroom'] ?? '',
                'start_week' => $course['start_week'] ?? 1,
                'end_week' => $course['end_week'] ?? 16,
                'week_type' => $course['week_type'] ?? 0,
                'color' => $course['color'] ?? $this->getRandomColor(),
                'remark' => ''
            ];
        }

        $record->save([
            'schedule_data' => json_encode($scheduleData, JSON_UNESCAPED_UNICODE),
            'is_custom' => 1,
            'update_time' => time()
        ]);

        return $scheduleData;
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
            // 如果是班级课表，先转为私有
            if ($record['is_custom'] == 0 && $record['class_id'] > 0) {
                $scheduleData = $this->convertToCustom($record, $semester);
            } else {
                $scheduleData = json_decode($record['schedule_data'], true) ?: [];
            }
            $scheduleData[] = $newCourse;
            $record->save([
                'schedule_data' => json_encode($scheduleData, JSON_UNESCAPED_UNICODE),
                'is_custom' => 1,
                'update_time' => time()
            ]);
        } else {
            // 创建新记录
            $schedule = new Schedule();
            $schedule->save([
                'site_id' => $this->site_id,
                'member_id' => $this->member_id,
                'school_id' => $data['school_id'] ?? 0,
                'semester' => $semester,
                'is_custom' => 1,
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

        if (empty($record)) {
            throw new CommonException('课程不存在');
        }

        // 如果是班级课表，先转为私有
        if ($record['is_custom'] == 0 && $record['class_id'] > 0) {
            $this->convertToCustom($record, $semester);
            $record = (new Schedule())->where([
                ['member_id', '=', $this->member_id],
                ['site_id', '=', $this->site_id],
                ['semester', '=', $semester]
            ])->find();
        }

        if (empty($record['schedule_data'])) {
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

        if (empty($record)) {
            throw new CommonException('课程不存在');
        }

        // 如果是班级课表，先转为私有
        if ($record['is_custom'] == 0 && $record['class_id'] > 0) {
            $this->convertToCustom($record, $semester);
            $record = (new Schedule())->where([
                ['member_id', '=', $this->member_id],
                ['site_id', '=', $this->site_id],
                ['semester', '=', $semester]
            ])->find();
        }

        if (empty($record['schedule_data'])) {
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
