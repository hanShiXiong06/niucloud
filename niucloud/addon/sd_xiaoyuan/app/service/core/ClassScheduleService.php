<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\ClassSchedule;
use addon\sd_xiaoyuan\app\model\SchoolClass;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\file\UploadedFile;

/**
 * 班级课表管理服务
 */
class ClassScheduleService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ClassSchedule();
    }

    /**
     * 获取班级课表列表(分页)
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,school_id,class_id,semester,week_day,section_start,section_end,course_code,course_name,teacher,classroom,location,credit,start_week,end_week,week_type,color,weeks_text,create_time';
        $order = 'week_day asc,section_start asc';

        $searchFields = [];
        if (!empty($where['class_id'])) $searchFields[] = 'class_id';
        if (!empty($where['semester'])) $searchFields[] = 'semester';

        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch($searchFields, $where)->field($field)->order($order);
        return $this->pageQuery($search_model);
    }

    /**
     * 获取班级课表(不分页，用于用户端)
     */
    public function getListByClass(int $class_id, string $semester)
    {
        return $this->model->where([
            ['site_id', '=', $this->site_id],
            ['class_id', '=', $class_id],
            ['semester', '=', $semester]
        ])->order('week_day asc,section_start asc')->select()->toArray();
    }

    /**
     * 添加课程
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        if (empty($data['color'])) {
            $data['color'] = $this->getRandomColor();
        }
        $data['create_time'] = time();
        $data['update_time'] = time();

        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 编辑课程
     */
    public function edit(int $id, array $data)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('课程不存在');
        }

        $data['update_time'] = time();
        $info->save($data);
        return true;
    }

    /**
     * 删除课程
     */
    public function del(int $id)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('课程不存在');
        }

        $info->delete();
        return true;
    }

    /**
     * 清空班级课表
     */
    public function clear(int $class_id, string $semester)
    {
        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['class_id', '=', $class_id],
            ['semester', '=', $semester]
        ])->delete();
        return true;
    }

    /**
     * 从上传文件导入课表
     */
    public function importFromFile(int $school_id, UploadedFile $file)
    {
        $ext = strtolower($file->getOriginalExtension());
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            $rows = $this->parseExcel($file->getRealPath());
        } elseif (in_array($ext, ['csv', 'tsv', 'txt'])) {
            $rows = $this->parseCsv($file->getRealPath());
        } else {
            throw new CommonException('不支持的文件格式，请上传 xlsx/xls/csv/tsv 文件');
        }

        if (empty($rows)) {
            throw new CommonException('文件内容为空或解析失败');
        }

        return $this->importSchedule(['school_id' => $school_id, 'rows' => $rows]);
    }

    /**
     * 解析 Excel 文件
     */
    private function parseExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if (count($data) < 2) return [];

        $headers = array_map('trim', $data[0]);
        $rows = [];
        for ($i = 1; $i < count($data); $i++) {
            $row = [];
            foreach ($headers as $idx => $h) {
                $row[$h] = isset($data[$i][$idx]) ? trim((string)$data[$i][$idx]) : '';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * 解析 CSV/TSV 文件（自动检测编码和分隔符）
     */
    private function parseCsv(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false || empty($content)) return [];

        // 检测编码，非 UTF-8 则转换
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'BIG5', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        // 去除 BOM
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)), function ($l) {
            return trim($l) !== '';
        });
        $lines = array_values($lines);

        if (count($lines) < 2) return [];

        // 自动检测分隔符
        $separator = (substr_count($lines[0], "\t") >= 2) ? "\t" : ',';

        $headers = array_map(function ($h) {
            return trim($h, " \t\n\r\0\x0B\"'");
        }, explode($separator, $lines[0]));

        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            $values = explode($separator, $lines[$i]);
            if (count($values) < 2) continue;

            $row = [];
            foreach ($headers as $idx => $h) {
                $row[$h] = isset($values[$idx]) ? trim($values[$idx], " \t\n\r\0\x0B\"'") : '';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * 导入课表
     */
    public function importSchedule(array $params)
    {
        $school_id = intval($params['school_id'] ?? 0);
        $rows = $params['rows'] ?? [];

        if (empty($rows)) {
            throw new CommonException('导入数据为空');
        }

        $classModel = new SchoolClass();
        $stat = ['class_count' => 0, 'course_count' => 0, 'skip_count' => 0];
        $classCache = [];

        foreach ($rows as $row) {
            $grade = trim($row['nj'] ?? '');
            $bjdm = trim($row['bjdm'] ?? '');
            $bjmc = trim($row['bjmc'] ?? '');
            $courseName = trim($row['kcmc'] ?? '');

            if (empty($bjmc) || empty($courseName)) {
                $stat['skip_count']++;
                continue;
            }

            // 构建学期
            $xn = trim($row['xn'] ?? '');
            $xq = trim($row['xq_meta'] ?? '');
            $semester = $xn ? $xn . '-' . $xq : '';
            if (empty($semester)) {
                $stat['skip_count']++;
                continue;
            }

            // 查找或创建班级
            $cacheKey = $bjdm ?: $bjmc . '_' . $grade;
            if (!isset($classCache[$cacheKey])) {
                $condition = [['site_id', '=', $this->site_id], ['school_id', '=', $school_id]];
                if ($bjdm) {
                    $condition[] = ['code', '=', $bjdm];
                } else {
                    $condition[] = ['name', '=', $bjmc];
                    $condition[] = ['grade', '=', $grade];
                }

                $classInfo = $classModel->where($condition)->find();
                if (empty($classInfo)) {
                    $classInfo = $classModel->create([
                        'site_id' => $this->site_id,
                        'school_id' => $school_id,
                        'name' => $bjmc,
                        'code' => $bjdm,
                        'grade' => $grade,
                        'status' => 1,
                        'create_time' => time(),
                        'update_time' => time(),
                    ]);
                    $stat['class_count']++;
                }
                $classCache[$cacheKey] = $classInfo['id'];
            }
            $classId = $classCache[$cacheKey];

            // 解析节次 jcxx: "1月2日" → section_start=1, section_end=2
            $jcxx = trim($row['jcxx'] ?? '');
            $sectionStart = 1;
            $sectionEnd = 1;
            if (preg_match('/(\d+)\D+(\d+)/', $jcxx, $m)) {
                $sectionStart = intval($m[1]);
                $sectionEnd = intval($m[2]);
            }

            // 解析周次 skzs: "1-14周"
            $skzs = trim($row['skzs'] ?? '');
            $startWeek = 1;
            $endWeek = 16;
            if (preg_match('/(\d+)\s*-\s*(\d+)/', $skzs, $m)) {
                $startWeek = intval($m[1]);
                $endWeek = intval($m[2]);
            }

            $weekDay = intval($row['weekday'] ?? 1);
            if ($weekDay < 1 || $weekDay > 7) $weekDay = 1;

            $this->model->create([
                'site_id' => $this->site_id,
                'school_id' => $school_id,
                'class_id' => $classId,
                'semester' => $semester,
                'week_day' => $weekDay,
                'section_start' => $sectionStart,
                'section_end' => $sectionEnd,
                'course_code' => trim($row['kcdm'] ?? ''),
                'course_name' => $courseName,
                'teacher' => trim($row['rkjs'] ?? ''),
                'classroom' => trim($row['skbj'] ?? ''),
                'location' => trim($row['skdd'] ?? ''),
                'credit' => floatval($row['xf'] ?? 0),
                'start_week' => $startWeek,
                'end_week' => $endWeek,
                'week_type' => 0,
                'color' => $this->getRandomColor(),
                'weeks_text' => $skzs,
                'create_time' => time(),
                'update_time' => time(),
            ]);
            $stat['course_count']++;
        }

        return $stat;
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
