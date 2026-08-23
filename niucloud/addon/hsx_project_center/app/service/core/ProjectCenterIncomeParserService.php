<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

use addon\hsx_project_center\app\model\ProjectCenterIncomeBoard;
use core\exception\CommonException;
use think\facade\Db;

/** 将群内复制的收益排行文本解析成纯展示数据。 */
final class ProjectCenterIncomeParserService
{
    public function replace(int $siteId, int $projectId, string $text, int $date = 0): array
    {
        $date = $date > 0 ? $date : $this->detectDate($text);
        if ($date <= 0) $date = (int)date('Ymd');
        $rows = $this->parse($text);
        if ($rows === []) throw new CommonException('未识别到收益排行，请保留“Top序号、门店、收益金额”');
        $now = time();
        Db::transaction(function () use ($siteId, $projectId, $date, $rows, $now) {
            ProjectCenterIncomeBoard::where([['site_id', '=', $siteId], ['project_id', '=', $projectId], ['stat_date', '=', $date]])->delete();
            foreach ($rows as $row) {
                ProjectCenterIncomeBoard::create(array_merge($row, [
                    'site_id' => $siteId, 'project_id' => $projectId, 'stat_date' => $date,
                    'sort' => 10000 - (int)$row['rank_no'], 'status' => 1, 'create_at' => $now, 'update_at' => $now,
                ]));
            }
        });
        return ['stat_date' => $date, 'count' => count($rows), 'rows' => $rows];
    }

    public function parse(string $text): array
    {
        $result = [];
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (!preg_match('/Top\s*0*(\d+)\s*[:：]\s*(.+?)\s+收益\s*([0-9]+(?:\.[0-9]+)?)/ui', $line, $match)) continue;
            $rank = (int)$match[1];
            if ($rank <= 0) continue;
            $result[$rank] = [
                'rank_no' => $rank, 'store_name' => mb_substr(trim($match[2]), 0, 150),
                'income_amount' => round((float)$match[3], 2), 'source_text' => mb_substr($line, 0, 500),
            ];
        }
        ksort($result);
        return array_values($result);
    }

    private function detectDate(string $text): int
    {
        if (preg_match('/(20\d{6})/u', $text, $match)) return (int)$match[1];
        return 0;
    }
}
