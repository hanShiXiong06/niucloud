<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\api;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\service\core\ProjectCenterDistributionRuleService;
use addon\hsx_project_center\app\service\core\ProjectCenterInviteService;
use app\dict\sys\PosterDict;
use app\dict\sys\StorageDict;
use app\model\member\Member;
use app\model\sys\Poster;
use app\service\core\upload\CoreFileService;
use app\service\core\upload\CoreStorageService;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Cache;
use think\facade\Log;
use Throwable;

/** 项目中心专用海报生成、缓存与云存储服务，不修改框架通用海报链路。 */
final class ProjectCenterDistributionPosterService extends BaseApiService
{
    private const POSTER_TYPE = 'hsx_project_center_distribution';
    private const CACHE_TTL = 2592000;
    private const CACHE_VERSION = '1';

    public function generate(int $projectId, string $inviteCredential, int $posterId = 0): array
    {
        $siteId = (int)$this->site_id;
        $memberId = (int)$this->member_id;
        $inviteCredential = trim($inviteCredential);
        if ($projectId <= 0 || $inviteCredential === '') {
            throw new CommonException('项目推广海报参数不完整，请重新打开分享入口');
        }

        $project = ProjectCenterProject::where([
            ['site_id', '=', $siteId], ['id', '=', $projectId],
            ['status', '=', ProjectCenterDict::PROJECT_ENABLED], ['distribution_enabled', '=', 1],
        ])->findOrEmpty();
        if ($project->isEmpty()) throw new CommonException('该项目当前未开放邀请推广');

        $invite = (new ProjectCenterInviteService())->findActive($siteId, $projectId, $inviteCredential);
        if ((int)$invite->inviter_member_id !== $memberId) {
            throw new CommonException('该推广凭证不属于当前会员，请重新进入分享页面');
        }
        $capability = (new ProjectCenterDistributionRuleService())->memberCapability($siteId, $memberId, 1, $project);
        if (empty($capability['eligible'])) {
            throw new CommonException('当前会员推广资格不可用：' . (string)($capability['reason'] ?? '请联系管理员'));
        }

        $member = Member::where([
            ['site_id', '=', $siteId], ['member_id', '=', $memberId], ['status', '=', 1],
        ])->field('member_id,nickname')->findOrEmpty();
        if ($member->isEmpty()) throw new CommonException('分享会员不存在或已停用');

        $storageConfig = $this->effectiveStorageConfig($siteId);
        $cacheKey = $this->cacheKey(
            $siteId,
            $projectId,
            $posterId,
            $inviteCredential,
            (string)$member->nickname,
            (int)($project->update_at ?? 0),
            $storageConfig
        );
        $cached = $this->readCache($cacheKey);
        if ($cached !== []) return $cached + ['cached' => true];

        $localResult = (string)poster(
            $siteId,
            $posterId,
            self::POSTER_TYPE,
            ['project_id' => $projectId, 'invite' => $inviteCredential],
            (string)$this->channel,
            true
        );
        if ($localResult === '') throw new CommonException('海报生成失败，请稍后重试');

        $result = $this->persist($siteId, $cacheKey, $localResult, $storageConfig);
        $this->writeCache($cacheKey, $result);
        return $result + ['cached' => false];
    }

    private function cacheKey(
        int $siteId,
        int $projectId,
        int $posterId,
        string $credential,
        string $nickname,
        int $projectUpdateAt,
        array $storageConfig
    ): string {
        $template = Poster::where([
            ['site_id', '=', $siteId], ['type', '=', self::POSTER_TYPE], ['status', '=', PosterDict::ON],
        ])->when($posterId > 0, fn($query) => $query->where('id', '=', $posterId))
            ->when($posterId <= 0, fn($query) => $query->where('is_default', '=', 1))
            ->field('id,update_time,value')->findOrEmpty()->toArray();
        $identity = [
            self::CACHE_VERSION, $siteId, $projectId, $posterId, $credential, $nickname,
            $projectUpdateAt, $template,
            (string)$this->channel,
            $storageConfig['storage_type'] ?? StorageDict::LOCAL,
            $storageConfig['domain'] ?? '',
        ];
        return 'hsx_project_center_poster_' . md5((string)json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function effectiveStorageConfig(int $siteId): array
    {
        try {
            $service = new CoreStorageService();
            $config = $service->getDefaultStorage($siteId) ?: [];
            if (($config['storage_type'] ?? StorageDict::LOCAL) === StorageDict::LOCAL) {
                $config = $service->getDefaultStorage(0) ?: ['storage_type' => StorageDict::LOCAL];
            }
            return $config;
        } catch (Throwable $e) {
            Log::warning('[项目中心海报] 读取存储配置失败，继续使用本地存储：' . $e->getMessage());
            return ['storage_type' => StorageDict::LOCAL];
        }
    }

    private function isCloud(array $storageConfig): bool
    {
        return in_array($storageConfig['storage_type'] ?? StorageDict::LOCAL, [
            StorageDict::QINIU, StorageDict::ALI, StorageDict::TENCENT,
        ], true);
    }

    private function persist(int $siteId, string $cacheKey, string $localResult, array $storageConfig): array
    {
        if (!$this->isCloud($storageConfig)) {
            return ['url' => $localResult, 'storage' => StorageDict::LOCAL];
        }
        $localPath = $this->resolveLocalPath($localResult);
        if ($localPath === '') {
            Log::warning('[项目中心海报] 未找到生成后的本地文件，返回原地址：' . $localResult);
            return ['url' => $localResult, 'storage' => 'local_fallback'];
        }

        try {
            if (empty($storageConfig['domain'])) throw new \RuntimeException('云存储访问域名未配置');
            $content = file_get_contents($localPath);
            if ($content === false || $content === '') throw new \RuntimeException('读取生成海报失败');
            $cloudKey = 'upload/hsx_project_center/poster/' . $siteId . '/' . md5($cacheKey) . '.png';
            $driver = (new CoreFileService())->driver($siteId);
            $driver->base64(base64_encode($content), $cloudKey);
            $cloudUrl = (string)$driver->getUrl($cloudKey);
            if ($cloudUrl === '') throw new \RuntimeException('云存储未返回访问地址');
            if (is_file($localPath) && !@unlink($localPath)) {
                Log::warning('[项目中心海报] 云端上传成功，本地临时文件删除失败：' . $localPath);
            }
            return ['url' => $cloudUrl, 'storage' => (string)$storageConfig['storage_type']];
        } catch (Throwable $e) {
            Log::warning('[项目中心海报] 上传云存储失败，已保留本地海报兜底：site_id=' . $siteId . '，原因：' . $e->getMessage());
            return ['url' => $localResult, 'storage' => 'local_fallback'];
        }
    }

    private function resolveLocalPath(string $path): string
    {
        if ($path === '' || preg_match('#^https?://#i', $path)) return '';
        $relative = ltrim(str_replace('\\', '/', $path), '/');
        foreach (array_unique(array_filter([$path, $relative, public_path($relative)])) as $candidate) {
            if (is_file($candidate)) return $candidate;
        }
        return '';
    }

    private function readCache(string $key): array
    {
        try {
            $cached = Cache::get($key, []);
            if (!is_array($cached) || empty($cached['url'])) return [];
            if (($cached['storage'] ?? '') === StorageDict::LOCAL && $this->resolveLocalPath((string)$cached['url']) === '') {
                Cache::delete($key);
                return [];
            }
            return $cached;
        } catch (Throwable $e) {
            Log::warning('[项目中心海报] 读取缓存失败，将重新生成：' . $e->getMessage());
            return [];
        }
    }

    private function writeCache(string $key, array $result): void
    {
        try {
            Cache::set($key, $result, self::CACHE_TTL);
        } catch (Throwable $e) {
            Log::warning('[项目中心海报] 写入缓存失败，不影响本次海报使用：' . $e->getMessage());
        }
    }
}
