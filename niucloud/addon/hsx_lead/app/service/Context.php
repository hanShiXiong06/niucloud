<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

final class Context
{
    private int $site;
    private int $uid;
    private int $memberId;
    private ?OwnershipScope $ownership=null;

    public function __construct(int $site, int $uid = 0, int $memberId = 0)
    {
        if (isset($this->site)) throw new \Error('Cannot reinitialize read-only context identity');
        if ($site <= 0 || ($uid <= 0 && $memberId <= 0)) throw new CommonException('请先登录当前站点');
        $this->site = $site;
        $this->uid = $uid;
        $this->memberId = $memberId;
    }

    /** PHP 8.0-compatible read-only identity; callers keep the existing property API. */
    public function __get(string $name): int
    {
        return match ($name) {
            'site' => $this->site,
            'uid' => $this->uid,
            'memberId' => $this->memberId,
            default => throw new \Error('Undefined context property: ' . $name),
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['site', 'uid', 'memberId'], true);
    }

    public function __set(string $name, #[\SensitiveParameter] mixed $value): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        throw new \Error('Cannot modify read-only context property: ' . $name);
    }

    public function __unset(string $name): void
    {
        throw new \Error('Cannot unset read-only context property: ' . $name);
    }
    public function manager(): void
    {
        $this->ownership()->operator();
    }
    public function ownership(): OwnershipScope { return $this->ownership??=new OwnershipScope($this); }
    public function permission(string $key): void { $this->ownership()->permission($key); }
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
    /** Explicit ERP owner authorization. A member never inherits administrator powers. */
    public function connectionMerchant(int $id,bool $lock=false): array
    {
        if ($this->uid>0) {$this->manager();return Data::row('merchant',$this->site,$id,$lock);}
        $own=$this->merchant();if ((int)$own['id']!==$id) throw new CommonException('只能管理当前会员绑定门店的ERP');
        $row=Data::row('merchant',$this->site,$id,$lock);
        if ((int)$row['member_id']!==$this->memberId||(int)$row['status']!==1) throw new CommonException('门店绑定关系已变化，请重新登录');
        $partner=Db::name('hsx_lead_partner')->where('site_id',$this->site)->where('merchant_id',$id)->find();
        if (!$partner||(int)$partner['blocked_at']>0) throw new CommonException('当前门店不能继续授权或同步ERP');
        return $row;
    }
    public function audit(string $entity, int $id, string $action, array $safe = []): void
    {
        Data::audit($this->site,$this->uid,$this->memberId,$entity,$id,$action,$safe);
    }
}
