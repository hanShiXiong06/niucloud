<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\admin;

use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterReviewLog;
use app\model\member\Member;
use app\service\core\diy_form\CoreDiyFormRecordsService;
use core\exception\CommonException;
use ZipArchive;

/**
 * 将审核通过时的资料快照打包交付给运营。
 *
 * 快照是唯一可信来源；仅在历史数据没有快照时回退读取当前表单记录。
 * 图片下载失败不会阻断导出，而是写入“图片链接.txt”方便人工补齐。
 */
final class ProjectCenterApplicationArchiveService
{
    private const MAX_IMAGE_BYTES = 15728640;
    private const MAX_ARCHIVE_IMAGE_BYTES = 104857600;

    public function build(int $siteId, int $applicationId): array
    {
        if (!class_exists(ZipArchive::class)) throw new CommonException('服务器未安装 ZipArchive 扩展，暂时无法打包资料');

        $application = ProjectCenterApplication::where([
            ['site_id', '=', $siteId], ['id', '=', $applicationId],
        ])->findOrEmpty();
        if ($application->isEmpty()) throw new CommonException('资料工单不存在');

        $applicationData = $application->toArray();
        $project = ProjectCenterProject::where([
            ['site_id', '=', $siteId], ['id', '=', (int)$applicationData['project_id']],
        ])->findOrEmpty();
        $group = (int)($applicationData['group_id'] ?? 0) > 0
            ? ProjectCenterGroup::where([
                ['site_id', '=', $siteId], ['id', '=', (int)$applicationData['group_id']],
            ])->findOrEmpty()
            : null;
        $member = Member::where([
            ['site_id', '=', $siteId], ['member_id', '=', (int)$applicationData['member_id']],
        ])->field('member_id,nickname,username,mobile')->findOrEmpty();

        $snapshot = $this->submissionSnapshot($siteId, $applicationData);
        $fields = $this->snapshotFields($snapshot);
        $groupData = $group && !$group->isEmpty() ? $group->toArray() : [];
        $memberData = $member->isEmpty() ? [] : $member->toArray();
        $projectData = $project->isEmpty() ? [] : $project->toArray();

        $directory = $this->exportDirectory();
        $identity = (string)($groupData['store_name'] ?? $groupData['member_name'] ?? $memberData['nickname'] ?? $memberData['username'] ?? $memberData['mobile'] ?? '客户');
        $baseName = $this->safeName((string)$applicationData['application_no'] . '_' . $identity, '客户资料');
        $fileName = $baseName . '.zip';
        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new CommonException('资料包创建失败，请检查运行目录权限');
        }

        $assetFiles = [];
        $assetLinks = [];
        $assetManifest = [];
        $downloadedBytes = 0;
        try {
            foreach ($fields as $index => $field) {
                $urls = $this->imageUrls($field);
                foreach ($urls as $imageIndex => $url) {
                    $label = $this->safeName((string)($field['field_name'] ?? '图片'), '图片');
                    $assetKey = trim((string)($field['field_key'] ?? ''));
                    $safeAssetKey = $this->safeName($assetKey, sprintf('field_%03d', $index + 1));
                    // 稳定序号 + 字段键 + 字段名，避免重名字段相互覆盖，也方便运营按表单定位图片。
                    $baseAssetName = sprintf('%03d_%s_%s_%02d', $index + 1, $safeAssetKey, $label, $imageIndex + 1);
                    if ($downloadedBytes >= self::MAX_ARCHIVE_IMAGE_BYTES) {
                        $assetLinks[] = $label . '：' . $url . '（资料包图片总量超过 100MB，保留原链接）';
                        $assetManifest[] = ['field_key' => $assetKey, 'source_url' => $url, 'archive_path' => '', 'status' => 'size_limit'];
                        continue;
                    }
                    $download = $this->downloadImage($url, $directory, $baseAssetName);
                    if ($download === null) {
                        $assetLinks[] = $label . '：' . $url;
                        $assetManifest[] = ['field_key' => $assetKey, 'source_url' => $url, 'archive_path' => '', 'status' => 'download_failed'];
                        continue;
                    }
                    [$tempPath, $extension, $bytes] = $download;
                    if ($downloadedBytes + $bytes > self::MAX_ARCHIVE_IMAGE_BYTES) {
                        @unlink($tempPath);
                        $assetLinks[] = $label . '：' . $url . '（资料包图片总量超过 100MB，保留原链接）';
                        $assetManifest[] = ['field_key' => $assetKey, 'source_url' => $url, 'archive_path' => '', 'status' => 'size_limit'];
                        continue;
                    }
                    $archivePath = '图片/' . $baseAssetName . '.' . $extension;
                    $zip->addFile($tempPath, $archivePath);
                    $assetFiles[] = $tempPath;
                    $downloadedBytes += $bytes;
                    $assetManifest[] = ['field_key' => $assetKey, 'source_url' => $url, 'archive_path' => $archivePath, 'status' => 'downloaded'];
                }
            }

            $manifest = [
                'schema_version' => 'project_application.v1',
                'generated_at' => date('Y-m-d H:i:s'),
                'application' => [
                    'id' => (int)$applicationData['id'],
                    'application_no' => (string)$applicationData['application_no'],
                    'status' => (string)$applicationData['status'],
                    'submit_version' => (int)$applicationData['submit_version'],
                    'submitted_at' => (int)$applicationData['submitted_at'],
                    'approved_at' => (int)$applicationData['approved_at'],
                ],
                'project' => [
                    'id' => (int)($projectData['id'] ?? 0),
                    'title' => (string)($projectData['title'] ?? ''),
                ],
                'group' => [
                    'id' => (int)($groupData['id'] ?? 0),
                    'group_no' => (string)($groupData['group_no_full'] ?? $groupData['group_no'] ?? ''),
                    'store_name' => (string)($groupData['store_name'] ?? ''),
                ],
                'member' => [
                    'member_id' => (int)($memberData['member_id'] ?? $applicationData['member_id']),
                    'name' => (string)($groupData['member_name'] ?? $memberData['nickname'] ?? $memberData['username'] ?? ''),
                    'mobile' => (string)($groupData['member_mobile'] ?? $memberData['mobile'] ?? ''),
                ],
                'fields' => array_values($fields),
                'assets' => $assetManifest,
            ];
            $zip->addFromString('资料.txt', $this->textDocument($manifest));
            $zip->addFromString('manifest.json', (string)json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            if ($assetLinks !== []) $zip->addFromString('图片链接.txt', implode(PHP_EOL, $assetLinks));
        } catch (\Throwable $e) {
            $zip->close();
            foreach ($assetFiles as $path) @unlink($path);
            @unlink($filePath);
            throw $e;
        }

        if (!$zip->close()) {
            foreach ($assetFiles as $path) @unlink($path);
            @unlink($filePath);
            throw new CommonException('资料包写入失败，请稍后重试');
        }
        foreach ($assetFiles as $path) @unlink($path);
        $this->assertReadableArchive($filePath);
        return ['path' => $filePath, 'name' => $fileName];
    }

    /**
     * 在交给 HTTP 下载响应之前做一次真实 ZIP 校验。
     * 避免磁盘写满、扩展异常或运行目录问题生成一个有文件名但无法解压的空包。
     */
    private function assertReadableArchive(string $filePath): void
    {
        if (!is_file($filePath) || (int)filesize($filePath) < 22) {
            @unlink($filePath);
            throw new CommonException('资料包生成不完整，请检查服务器磁盘空间和运行目录权限');
        }
        $checker = new ZipArchive();
        $opened = $checker->open($filePath, ZipArchive::CHECKCONS);
        if ($opened !== true) {
            @unlink($filePath);
            throw new CommonException('资料包完整性校验失败，请稍后重试');
        }
        $hasText = $checker->locateName('资料.txt') !== false;
        $hasManifest = $checker->locateName('manifest.json') !== false;
        $checker->close();
        if (!$hasText || !$hasManifest) {
            @unlink($filePath);
            throw new CommonException('资料包缺少交付清单，请稍后重试');
        }
    }

    private function submissionSnapshot(int $siteId, array $application): array
    {
        $log = ProjectCenterReviewLog::where([
            ['site_id', '=', $siteId],
            ['application_id', '=', (int)$application['id']],
            ['submit_version', '=', (int)$application['submit_version']],
            ['action', 'in', ['submit', 'resubmit']],
        ])->order('id desc')->findOrEmpty();
        $snapshot = $log->isEmpty() ? [] : (array)$log->form_snapshot_json;
        if ($snapshot !== []) return $snapshot;

        $fallback = (new CoreDiyFormRecordsService())->getInfo([
            'site_id' => $siteId, 'record_id' => (int)$application['form_record_id'],
        ]);
        return is_array($fallback) ? $fallback : [];
    }

    private function snapshotFields(array $snapshot): array
    {
        $raw = $snapshot['recordsFieldList'] ?? $snapshot['records_field_list'] ?? [];
        if ($raw instanceof \think\Collection) $raw = $raw->toArray();
        $fields = [];
        foreach ((array)$raw as $key => $field) {
            if ($field instanceof \think\Model) $field = $field->toArray();
            if (!is_array($field)) continue;
            $value = $field['handle_field_value'] ?? $field['field_value'] ?? '';
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
            }
            $fields[] = [
                'field_key' => (string)($field['field_key'] ?? $key),
                'field_type' => (string)($field['field_type'] ?? ''),
                'field_name' => (string)($field['field_name'] ?? '资料项'),
                'value' => $value,
                'display_value' => $this->displayValue($field, $value),
            ];
        }
        return $fields;
    }

    private function displayValue(array $field, $value): string
    {
        $fieldType = (string)($field['field_type'] ?? '');
        if ($fieldType === 'ProjectFormLocation' && is_array($value)) {
            $address = trim((string)($value['full_address'] ?? $value['name'] ?? ''));
            $coordinate = isset($value['latitude'], $value['longitude'])
                ? sprintf('%.6f, %.6f', (float)$value['latitude'], (float)$value['longitude']) : '';
            return trim($address . ($coordinate !== '' ? '（坐标：' . $coordinate . '）' : ''));
        }
        if (str_contains(strtolower($fieldType), 'image')) {
            $count = count($this->imageUrls(['field_type' => $fieldType, 'value' => $value]));
            return $count > 0 ? '共 ' . $count . ' 张（图片已按字段名称归档）' : '未上传图片';
        }
        $render = $field['render_value'] ?? '';
        if (is_scalar($render) && trim((string)$render) !== '') return trim((string)$render);
        if (is_array($value)) {
            $labels = $this->readableLabels($value);
            if ($labels !== []) return implode('、', array_values(array_unique($labels)));
            return '-';
        }
        if (is_bool($value)) return $value ? '是' : '否';
        return trim((string)$value);
    }

    /**
     * 将单选/多选的结构化值转换为业务人员能直接阅读的文本。
     * 例如 [{id: xxx, text: 未开通}] 只显示“未开通”，不暴露内部选项 ID。
     */
    private function readableLabels($value): array
    {
        if (!is_array($value)) {
            if (is_string($value) && trim($value) !== '') return [trim($value)];
            if (is_int($value) || is_float($value)) return [(string)$value];
            return [];
        }
        foreach (['text', 'label', 'name', 'title'] as $key) {
            if (isset($value[$key]) && is_scalar($value[$key]) && trim((string)$value[$key]) !== '') {
                return [trim((string)$value[$key])];
            }
        }
        $labels = [];
        foreach ($value as $item) $labels = array_merge($labels, $this->readableLabels($item));
        return $labels;
    }

    private function imageUrls(array $field): array
    {
        $type = strtolower((string)($field['field_type'] ?? ''));
        if (!str_contains($type, 'image')) return [];
        $urls = [];
        $this->collectUrls($field['value'] ?? [], $urls);
        return array_values(array_unique($urls));
    }

    private function collectUrls($value, array &$urls): void
    {
        if (is_array($value)) {
            foreach ($value as $item) $this->collectUrls($item, $urls);
            return;
        }
        if (!is_string($value)) return;
        foreach (preg_split('/[\r\n,]+/', trim($value)) ?: [] as $item) {
            $item = trim($item);
            if ($item === '') continue;
            // FormImage 某些版本会把 {url,status,message} 整体保存；只收集真实图片地址，
            // 避免把 success/uploading 等状态文本写进“图片链接.txt”。
            $path = (string)(parse_url($item, PHP_URL_PATH) ?: $item);
            $isImage = preg_match('/\.(?:jpe?g|png|gif|webp|bmp)(?:$|\?)/i', $item) === 1;
            $isAttachment = str_contains(str_replace('\\', '/', $path), 'upload/attachment/');
            if (preg_match('#^https?://#i', $item) === 1 || $isImage || $isAttachment) $urls[] = $item;
        }
    }

    private function textDocument(array $manifest): string
    {
        $lines = [
            '项目资料交付单',
            str_repeat('=', 32),
            '项目：' . ($manifest['project']['title'] ?: '-'),
            '工单号：' . $manifest['application']['application_no'],
            '群编号：' . ($manifest['group']['group_no'] ?: '未绑定群'),
            '门店：' . ($manifest['group']['store_name'] ?: '-'),
            '客户：' . ($manifest['member']['name'] ?: '-'),
            '手机号：' . ($manifest['member']['mobile'] ?: '-'),
            '提交时间：' . $this->dateText((int)$manifest['application']['submitted_at']),
            '审核通过：' . $this->dateText((int)$manifest['application']['approved_at']),
            '', '客户提交资料', str_repeat('-', 32),
        ];
        foreach ($manifest['fields'] as $field) {
            $lines[] = (string)$field['field_name'] . '：' . ((string)$field['display_value'] !== '' ? (string)$field['display_value'] : '-');
        }
        $lines[] = '';
        $lines[] = '说明：本资料包依据客户第 ' . $manifest['application']['submit_version'] . ' 次提交并审核通过时的快照生成。';
        return implode(PHP_EOL, $lines);
    }

    private function downloadImage(string $url, string $directory, string $baseName): ?array
    {
        $url = trim($url);
        if ($url === '') return null;
        if (!preg_match('#^https?://#i', $url)) {
            $local = public_path() . ltrim(str_replace('\\', '/', $url), '/');
            if (!is_file($local) || filesize($local) <= 0 || filesize($local) > self::MAX_IMAGE_BYTES || @getimagesize($local) === false) return null;
            $extension = $this->imageExtension($local, $url);
            $temp = $directory . DIRECTORY_SEPARATOR . uniqid('asset_', true) . '.' . $extension;
            if (!copy($local, $temp)) return null;
            return [$temp, $extension, (int)filesize($temp)];
        }

        $currentUrl = $url;
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            if (!$this->isSafeRemoteUrl($currentUrl)) return null;
            $temp = $directory . DIRECTORY_SEPARATOR . uniqid('asset_', true) . '.download';
            $handle = @fopen($temp, 'wb');
            if ($handle === false) return null;
            $size = 0;
            $location = '';
            $ch = curl_init($currentUrl);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Niucloud ProjectCenter Export/1.0',
                CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$location): int {
                    if (stripos($header, 'Location:') === 0) $location = trim(substr($header, 9));
                    return strlen($header);
                },
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($handle, &$size): int {
                    $length = strlen($chunk);
                    $size += $length;
                    if ($size > self::MAX_IMAGE_BYTES) return 0;
                    return (int)fwrite($handle, $chunk);
                },
            ]);
            $ok = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($handle);

            if ($code >= 300 && $code < 400 && $location !== '') {
                @unlink($temp);
                $currentUrl = $this->resolveUrl($currentUrl, $location);
                continue;
            }
            if ($ok === false || $code < 200 || $code >= 300 || $size <= 0 || $size > self::MAX_IMAGE_BYTES || @getimagesize($temp) === false) {
                @unlink($temp);
                return null;
            }
            $extension = $this->imageExtension($temp, $currentUrl);
            $renamed = $directory . DIRECTORY_SEPARATOR . uniqid($baseName . '_', true) . '.' . $extension;
            if (!rename($temp, $renamed)) {
                @unlink($temp);
                return null;
            }
            return [$renamed, $extension, $size];
        }
        return null;
    }

    private function isSafeRemoteUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || $host === 'localhost') return false;
        $ips = gethostbynamel($host) ?: [];
        if ($ips === []) return false;
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return false;
        }
        return true;
    }

    private function resolveUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) return $location;
        $parts = parse_url($base);
        $origin = (string)($parts['scheme'] ?? 'https') . '://' . (string)($parts['host'] ?? '');
        if (isset($parts['port'])) $origin .= ':' . (int)$parts['port'];
        if (str_starts_with($location, '/')) return $origin . $location;
        $path = (string)($parts['path'] ?? '/');
        return $origin . rtrim(str_replace('\\', '/', dirname($path)), '/') . '/' . $location;
    }

    private function imageExtension(string $path, string $source): string
    {
        $type = @exif_imagetype($path);
        $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', IMAGETYPE_BMP => 'bmp'];
        if (isset($map[$type])) return $map[$type];
        $extension = strtolower(pathinfo((string)(parse_url($source, PHP_URL_PATH) ?: ''), PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) ? ($extension === 'jpeg' ? 'jpg' : $extension) : 'jpg';
    }

    private function exportDirectory(): string
    {
        $directory = runtime_path() . 'temp' . DIRECTORY_SEPARATOR . 'hsx_project_center' . DIRECTORY_SEPARATOR . 'application_archive';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new CommonException('资料包临时目录创建失败');
        foreach ((array)glob($directory . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_file($file) && (int)filemtime($file) < time() - 86400) @unlink($file);
        }
        return $directory;
    }

    private function safeName(string $value, string $fallback): string
    {
        // 使用 ~ 作为分隔符，避免待过滤的 / 与反斜杠组合导致 PCRE 提前结束表达式。
        // 控制字符使用 Unicode 码点，兼容 PHP 8 的 UTF-8 正则处理。
        $value = preg_replace('~[\\\\/:*?"<>|\x{0000}-\x{001F}]+~u', '_', trim($value)) ?: '';
        $value = trim($value, " ._\t\n\r\0\x0B");
        return mb_substr($value !== '' ? $value : $fallback, 0, 80);
    }

    private function dateText(int $timestamp): string
    {
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '-';
    }
}
