<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\listener\poster;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryConfigDict;
use addon\hsx_phone_query\app\model\HsxPhoneQueryCategory;
use addon\hsx_phone_query\app\model\HsxPhoneQueryInfo;
use addon\hsx_phone_query\app\service\core\report\QueryResultFormatter;
use app\model\member\Member;
use app\service\core\sys\CoreConfigService;
use app\service\core\sys\CoreSysConfigService;

/**
 * 手机查询报告海报数据
 */
class QueryReportPoster
{
    public function handle($data = []): array
    {
        if (!is_array($data)) {
            return [];
        }

        if (($data['type'] ?? '') !== 'hsx_phone_query_report') {
            return [];
        }

        $siteId = (int)($data['site_id'] ?? 0);
        $param = is_array($data['param'] ?? null) ? $data['param'] : [];
        $resultId = (int)($param['result_id'] ?? $param['id'] ?? $param['posterId'] ?? 0);
        $inviteMemberId = (int)($param['invite_member_id'] ?? $param['member_id'] ?? 0);

        $config = $this->getDisplayConfig($siteId);
        $record = $this->getRecord($siteId, $resultId);
        if (empty($record)) {
            return [];
        }
        $info = $this->decodeInfo($record['info'] ?? []);
        $serviceName = $this->getServiceName($siteId, (int)($record['type_id'] ?? 0));
        $queryTime = $this->formatTime($record['create_time'] ?? '');
        $display = (new QueryResultFormatter())->format($info, [
            'type_name' => $serviceName,
            'create_time' => $queryTime,
        ]);
        $member = $this->getMember($siteId, $inviteMemberId);
        $title = $display['title'] ?: ($serviceName ?: '设备查询报告');
        $summaryLines = $this->buildSummaryLines($display['summary'] ?? [], $info);

        return [
            'brand_name' => $config['brand_name'] ?: '手机查询报告',
            'report_title' => $this->limitText($title, 28),
            'service_name' => $serviceName ?: '设备查询',
            'query_code' => (string)($record['sn'] ?? ''),
            'query_code_text' => 'IMEI/SN：' . (string)($record['sn'] ?? ''),
            'query_time' => $queryTime,
            'summary_text' => $display['summary_text'] ?: $this->buildSummaryText($info),
            'summary_line_1' => $summaryLines[0] ?? '',
            'summary_line_2' => $summaryLines[1] ?? '',
            'summary_line_3' => $summaryLines[2] ?? '',
            'summary_line_4' => $summaryLines[3] ?? '',
            'device_image' => 'addon/hsx_phone_query/resource/icon.png',
            'support_text' => $config['support_text'] ?: '查询结果仅供交易验机参考',
            'watermark_text' => $config['watermark']['text'] ?? '仅供参考',
            'footer_text' => $config['share']['footer'] ?? '报告由系统自动生成',
            'customer_phone' => $config['share']['customer_phone'] ?? '',
            'nickname' => $member['nickname'] ?? '',
            'headimg' => $member['headimg'] ?? 'static/resource/images/default_headimg.png',
            'url' => [
                'url' => (new CoreSysConfigService())->getSceneDomain($siteId)['wap_url'] ?? '',
                'page' => 'addon/hsx_phone_query/pages/detail',
                'data' => [
                    ['key' => 'id', 'value' => $resultId],
                    ['key' => 'invite_member_id', 'value' => $inviteMemberId],
                ],
            ],
        ];
    }

    private function formatTime($value): string
    {
        if (empty($value)) {
            return '';
        }
        if (is_numeric($value)) {
            $timestamp = (int)$value;
            return $timestamp > 0 ? date('Y-m-d H:i', $timestamp) : '';
        }

        $timestamp = strtotime((string)$value);
        return $timestamp ? date('Y-m-d H:i', $timestamp) : (string)$value;
    }

    private function buildSummaryLines(array $summary, array $info): array
    {
        $lines = [];
        foreach ($summary as $item) {
            $label = trim((string)($item['label'] ?? ''));
            $value = trim((string)($item['value'] ?? ''));
            if ($label === '' || $value === '' || $label === '查询项目') {
                continue;
            }
            $lines[] = $this->limitText($label . '：' . $value, 28);
            if (count($lines) >= 4) {
                break;
            }
        }

        if (count($lines) < 4) {
            foreach ($info as $key => $value) {
                if (count($lines) >= 4) {
                    break;
                }
                if (in_array((string)$key, ['image', 'img', 'picture', 'pic', 'product_image'], true) || !$this->hasValue($value)) {
                    continue;
                }
                $lines[] = $this->limitText((string)$key . '：' . $this->stringify($value), 28);
            }
        }

        while (count($lines) < 4) {
            $lines[] = '';
        }

        return $lines;
    }

    private function getDisplayConfig(int $siteId): array
    {
        $saved = (new CoreConfigService())->getConfigValue($siteId, HsxPhoneQueryConfigDict::CONFIG_KEY);
        $saved = is_array($saved) ? $saved : [];
        $config = HsxPhoneQueryConfigDict::normalizeConfig($saved);

        return HsxPhoneQueryConfigDict::normalizeDisplayConfig($config['display_config'] ?? []);
    }

    private function getRecord(int $siteId, int $resultId): array
    {
        if ($siteId <= 0 || $resultId <= 0) {
            return [];
        }

        return (new HsxPhoneQueryInfo())->where([
            ['site_id', '=', $siteId],
            ['id', '=', $resultId],
        ])->field('id,sn,type_id,info,create_time,member_id')->findOrEmpty()->toArray();
    }

    private function getServiceName(int $siteId, int $typeId): string
    {
        if ($siteId <= 0 || $typeId <= 0) {
            return '';
        }

        return (string)(new HsxPhoneQueryCategory())->where([
            ['site_id', '=', $siteId],
            ['id', '=', $typeId],
        ])->value('name');
    }

    private function getMember(int $siteId, int $memberId): array
    {
        if ($siteId <= 0 || $memberId <= 0) {
            return [];
        }

        $member = (new Member())->where([
            ['site_id', '=', $siteId],
            ['member_id', '=', $memberId],
        ])->field('nickname,headimg')->findOrEmpty()->toArray();

        if (!empty($member['nickname']) && mb_strlen($member['nickname'], 'UTF-8') > 10) {
            $member['nickname'] = mb_substr($member['nickname'], 0, 7, 'UTF-8') . '...';
        }
        if (empty($member['headimg'])) {
            $member['headimg'] = 'static/resource/images/default_headimg.png';
        }

        return $member;
    }

    private function decodeInfo($info): array
    {
        if (is_array($info)) {
            return $info;
        }
        if (!is_string($info) || trim($info) === '') {
            return [];
        }

        $decoded = json_decode($info, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function pickFirst(array $info, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($info[$key]) && $this->hasValue($info[$key])) {
                return is_array($info[$key]) ? $this->stringify($info[$key]) : (string)$info[$key];
            }
        }

        return '';
    }

    private function buildSummaryText(array $info): string
    {
        $summary = [];
        foreach (['capacity' => '容量', '容量' => '容量', 'color' => '颜色', '颜色' => '颜色', 'warranty' => '保修', 'coverage' => '保修', 'activationlock' => '激活锁', 'icloud' => 'ID状态', 'simlock' => '网络锁', 'mdm' => '监管锁'] as $key => $label) {
            if (!isset($info[$key]) || !$this->hasValue($info[$key])) {
                continue;
            }
            $summary[] = $label . '：' . $this->stringify($info[$key]);
        }

        if (empty($summary)) {
            foreach ($info as $key => $value) {
                if (count($summary) >= 4) {
                    break;
                }
                if (in_array((string)$key, ['image', 'img', 'picture', 'pic', 'product_image'], true) || !$this->hasValue($value)) {
                    continue;
                }
                $summary[] = (string)$key . '：' . $this->stringify($value);
            }
        }

        return $this->limitText(implode('  ', $summary), 86);
    }

    private function stringify($value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $key => $item) {
                if ($this->hasValue($item)) {
                    $parts[] = (is_string($key) ? $key . '：' : '') . $this->stringify($item);
                }
            }

            return implode('，', $parts);
        }

        return (string)$value;
    }

    private function hasValue($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_array($value)) {
            return !empty($value);
        }

        return true;
    }

    private function limitText(string $text, int $length): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return mb_substr($text, 0, max(1, $length - 1), 'UTF-8') . '...';
    }
}
