<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

final class Context
{
    public function __construct(public readonly int $site, public readonly int $uid = 0, public readonly int $memberId = 0)
    {
        if ($site <= 0 || ($uid <= 0 && $memberId <= 0)) throw new CommonException('请先登录当前站点');
    }
    public function manager(): void
    {
        // uid is only constructed by the authenticated admin controller after the plugin Gate.
        if ($this->uid <= 0) throw new CommonException('此操作仅限有权限的站点管理员');
    }
    public function merchant(): array
    {
        if ($this->memberId <= 0) throw new CommonException('请使用已绑定商户的会员身份登录');
        $member=Db::name('member')->where('site_id',$this->site)->where('member_id',$this->memberId)
            ->where('status',1)->where('is_del',0)->where('delete_time',0)->field('member_id')->find();
        if (!$member) throw new CommonException('会员身份已停用或失效，请联系平台管理员');
        $row = Data::query('merchant',$this->site)->where('member_id',$this->memberId)->where('status',1)->find();
        if (!$row) throw new CommonException('当前会员尚未绑定启用中的商户，请联系平台管理员');
        return $row;
    }
    public function task(int $id, bool $lock = false): array
    {
        $row = Data::row('task',$this->site,$id,$lock);
        if ($this->uid <= 0 && (int)$row['merchant_id'] !== (int)$this->merchant()['id']) {
            throw new CommonException('工单不存在，或不属于当前商户');
        }
        return $row;
    }
    public function audit(string $entity, int $id, string $action, array $safe = []): void
    {
        Data::audit($this->site,$this->uid,$this->memberId,$entity,$id,$action,$safe);
    }
}
