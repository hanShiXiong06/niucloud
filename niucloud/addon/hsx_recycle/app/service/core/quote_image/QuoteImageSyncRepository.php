<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\quote_image;

use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * 报价图片同步的站点隔离存储。
 * 配置和有界运行状态复用 sys_config；报价版本复用原有历史表。
 */
class QuoteImageSyncRepository
{
    public const CONFIG_KEY = 'HSX_RECYCLE_QUOTE_IMAGE_SYNC';
    public const STATE_KEY = 'HSX_RECYCLE_QUOTE_IMAGE_SYNC_STATE';

    public function readConfig(int $siteId): array
    {
        return $this->readValue($siteId, self::CONFIG_KEY);
    }

    public function readState(int $siteId): array
    {
        return $this->readValue($siteId, self::STATE_KEY);
    }

    public function writeConfig(int $siteId, array $value): void
    {
        $this->writeValue($siteId, self::CONFIG_KEY, $value);
    }

    public function writeState(int $siteId, array $value): void
    {
        $this->writeValue($siteId, self::STATE_KEY, $value);
    }

    /** 短状态锁；调用者不要在此锁内进行远程抓取。 */
    public function locked(int $siteId, string $kind, callable $operation): mixed
    {
        return $this->withNamedLock($siteId, $kind, 2, $operation, false);
    }

    /** 同一运行的重复队列消息不并行执行；忙时不报失败，交由原任务继续。 */
    public function work(int $siteId, string $runId, callable $operation): mixed
    {
        return $this->withNamedLock($siteId, 'work:' . $runId, 0, $operation, true);
    }

    public function transaction(callable $operation): mixed
    {
        return Db::transaction($operation);
    }

    /** 只提供本站拥有的报价分类，公共分类和其他站点不可作为同步目标。 */
    public function categories(int $siteId): array
    {
        $this->assertSite($siteId);
        return Db::name('recycle_category')->master(true)
            ->where('site_id', $siteId)
            ->field('category_id,site_id,category_name,category_full_name,images')
            ->order('sort desc,category_id asc')->select()->toArray();
    }

    public function category(int $siteId, int $id, bool $forUpdate = false): array
    {
        $this->assertSite($siteId);
        if ($id <= 0) throw new CommonException('请选择本站有效的报价分类');
        $query = Db::name('recycle_category')->master(true)
            ->where('site_id', $siteId)->where('category_id', $id);
        if ($forUpdate) $query->lock(true);
        $category = $query->field('category_id,site_id,category_name,category_full_name,images')->find();
        if (!$category) throw new CommonException('报价分类已删除或不属于本站，请重新设置映射');
        return $category;
    }

    /**
     * 必须在调用者 transaction 中执行，可与同一连接的运行检查点一起提交。
     * 不经分类 edit()，避免意外覆盖名称、封面、权限、排序或显示状态。
     */
    public function replaceImages(int $siteId, int $id, string $expectedImages, string $newImages,
        string $remark, int $uid, string $operator): void
    {
        if (trim($newImages) === '') throw new CommonException('未取得有效报价图片，原报价已保留');
        $category = $this->category($siteId, $id, true);
        $oldImages = (string)($category['images'] ?? '');
        if ($oldImages !== $expectedImages) {
            throw new CommonException('该分类报价已被人工修改，未覆盖现有图片，请暂停映射并核对后重试');
        }
        if ($oldImages === $newImages) return;

        $now = time();
        $base = [
            'site_id' => $siteId,
            'category_id' => $id,
            'operator_id' => max(0, $uid),
            'operator_name' => $this->limitText($operator, 100),
            'create_time' => $now,
        ];
        if ($oldImages !== '') {
            $latest = Db::name('recycle_category_quote_history')->master(true)
                ->where('site_id', $siteId)->where('category_id', $id)
                ->order('create_time desc,id desc')->value('images');
            if ((string)$latest !== $oldImages) {
                Db::name('recycle_category_quote_history')->insert($base + [
                    'images' => $oldImages,
                    'remark' => $this->limitText('自动采集前备份；' . $remark, 255),
                ]);
            }
        }
        Db::name('recycle_category')->where('site_id', $siteId)->where('category_id', $id)
            ->update(['images' => $newImages, 'update_time' => $now]);
        Db::name('recycle_category_quote_history')->insert($base + [
            'images' => $newImages,
            'remark' => $this->limitText($remark, 255),
        ]);
    }

    /** 在成功提交事务后调用。 */
    public function clearCategoryCache(int $siteId): void
    {
        $this->assertSite($siteId);
        Cache::tag('hsx_recycle_category_' . $siteId)->clear();
    }

    /** 只扫描已配置、仍正常且仍有回收应用的站点。 */
    public function configuredSites(): array
    {
        $ids = Db::name('sys_config')->master(true)->where('config_key', self::CONFIG_KEY)
            ->where('site_id', '>', 0)->column('site_id');
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];
        $sites = Db::name('site')->master(true)->whereIn('site_id', $ids)->where('status', 1)
            ->field('site_id,addons,app,initalled_addon,expire_time')->select()->toArray();
        $result = [];
        foreach ($sites as $site) {
            if ((int)($site['expire_time'] ?? 0) > 0 && (int)$site['expire_time'] <= time()) continue;
            foreach (['addons', 'app', 'initalled_addon'] as $field) {
                $addons = $site[$field] ?? [];
                if (is_string($addons)) $addons = json_decode($addons, true);
                if (is_array($addons) && in_array('hsx_recycle', $addons, true)) {
                    $result[] = (int)$site['site_id'];
                    break;
                }
            }
        }
        sort($result);
        return $result;
    }

    private function readValue(int $siteId, string $key): array
    {
        $this->assertSite($siteId);
        $value = Db::name('sys_config')->master(true)->where('site_id', $siteId)
            ->where('config_key', $key)->order('id asc')->value('value');
        if (is_string($value)) $value = json_decode($value, true);
        return is_array($value) ? $value : [];
    }

    private function writeValue(int $siteId, string $key, array $value): void
    {
        $this->assertSite($siteId);
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        // 原表 value 为 TEXT；拒绝截断状态，不能把进度写坏后伪装成成功。
        if (strlen($json) > 60000) throw new CommonException('同步配置或运行记录过大，请减少映射数量后重试');
        // 原表没有(site_id,config_key)唯一索引，首次创建同样需要数据库连接级锁。
        $this->locked($siteId, 'config', function () use ($siteId, $key, $json): void {
            $query = Db::name('sys_config')->master(true)->where('site_id', $siteId)->where('config_key', $key);
            $id = $query->order('id asc')->value('id');
            $data = ['value' => $json, 'update_time' => time()];
            if ($id) {
                Db::name('sys_config')->where('id', (int)$id)->where('site_id', $siteId)->update($data);
            } else {
                Db::name('sys_config')->insert($data + [
                    'site_id' => $siteId, 'config_key' => $key, 'create_time' => time(),
                    'addon' => 'hsx_recycle', 'status' => 1,
                ]);
            }
            try {
                Cache::tag(CoreConfigService::$cache_tag_name . $siteId)->clear();
            } catch (\Throwable $e) {
                // 本服务始终直读主库；不能把已经写入的权威状态误报为写入失败。
                Log::warning('报价采集配置缓存清理失败', ['site_id' => $siteId, 'reason' => $e->getMessage()]);
            }
        });
    }

    private function withNamedLock(int $siteId, string $kind, int $timeout, callable $operation, bool $skipBusy): mixed
    {
        $this->assertSite($siteId);
        $connection = Db::connect();
        // 与后续主库操作共用连接，不能从尚未初始化的 getPdo() 或只读从库加锁。
        $connection->query('SELECT 1', [], true);
        $pdo = $connection->getPdo();
        $name = 'hsx_quote_image_' . sha1($siteId . '|' . $kind);
        $statement = $pdo->prepare('SELECT GET_LOCK(?, ?)');
        $statement->execute([$name, $timeout]);
        if ((int)$statement->fetchColumn() !== 1) {
            if ($skipBusy) return null;
            throw new CommonException('报价采集状态正在更新，请稍后重试');
        }
        try {
            return $operation();
        } finally {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$name]);
            } catch (\Throwable $e) {
                Log::warning('报价采集锁释放异常', ['site_id' => $siteId, 'reason' => $e->getMessage()]);
            }
        }
    }

    private function assertSite(int $siteId): void
    {
        if ($siteId <= 0) throw new CommonException('报价采集仅支持有效业务站点，不能修改公共报价库');
    }

    private function limitText(string $text, int $limit): string
    {
        if (function_exists('mb_substr')) return mb_substr($text, 0, $limit, 'UTF-8');
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($characters) ? implode('', array_slice($characters, 0, $limit)) : substr($text, 0, $limit);
    }
}
