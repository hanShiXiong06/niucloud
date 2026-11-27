<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\category;

use addon\hsx_recycle\app\service\core\quote_image\QuoteImageSyncService as Sync;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/** 复用报价分类的查看/编辑权限，覆盖升级不依赖新增菜单初始化。 */
class QuoteImageSyncService extends BaseAdminService
{
    private function assertAccess(bool $write = false): void
    {
        if ((int)$this->site_id <= 0) throw new CommonException('请进入业务站点配置报价采集，不能修改公共报价库');
        $auth = new AuthService();
        if (AuthService::isSuperAdmin()) return;
        $role = $auth->getAuthRole((int)$this->site_id);
        if (!empty($role['is_admin'])) return;
        $rules = $auth->getAuthApiList();
        $allowed = $write
            ? array_intersect(['recycle/recycle_category/<id>', 'recycle/recycle_category/:id'], $rules['put'] ?? [])
            : array_intersect(['recycle/recycle_category', 'recycle/recycle_category_tree'], $rules['get'] ?? []);
        if (!$allowed) throw new CommonException($write ? '需要报价分类编辑权限才能配置或执行采集' : '需要报价分类查看权限');
    }
    public function info(): array { $this->assertAccess(); return (new Sync())->view((int)$this->site_id); }
    public function status(): array { $this->assertAccess(); return (new Sync())->status((int)$this->site_id); }
    public function discover(string $url): array { $this->assertAccess(true); return (new Sync())->discover($url); }
    public function save(array $data): array { $this->assertAccess(true); return (new Sync())->save((int)$this->site_id, $data); }
    public function run(): array { $this->assertAccess(true); return (new Sync())->start((int)$this->site_id, 'manual', (int)$this->uid, (string)$this->username); }
    public function cancel(string $run): array { $this->assertAccess(true); return (new Sync())->cancel((int)$this->site_id, $run); }
}
