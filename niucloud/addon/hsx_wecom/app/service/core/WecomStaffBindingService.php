<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\model\WecomAuthorizationIntent;
use addon\hsx_wecom\app\model\WecomCorpAuthorization;
use addon\hsx_wecom\app\model\WecomProviderSuite;
use addon\hsx_wecom\app\model\WecomStaffBinding;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\exception\CommonException;
use think\facade\Db;

/** 扫码仅提供候选身份；有配置权限的管理员核对后才能建立收件人关系。 */
final class WecomStaffBindingService
{
    private const TTL = 600;
    private const VERSION = 2;

    public function start(int $siteId, int $uid): array
    {
        return Db::transaction(function () use ($siteId, $uid): array {
            [$suite, $authorization] = $this->context($siteId, $uid, true);
            // 同一 Suite 的绑定写入统一先锁通道，避免重新生成、扫码与确认并发互相覆盖。
            $previous = WecomAuthorizationIntent::where([
                ['site_id', '=', $siteId], ['uid', '=', $uid],
                ['purpose', '=', 'member_bind'], ['used_at', '=', 0],
            ])->lock(true)->select();
            foreach ($previous as $intent) {
                $this->finish($intent, 'failed', '已生成新的绑定入口，请使用最新二维码');
            }
            $state = bin2hex(random_bytes(24));
            $expiresAt = time() + self::TTL;
            $intent = WecomAuthorizationIntent::create([
                'state' => $state, 'purpose' => 'member_bind', 'site_id' => $siteId, 'uid' => $uid,
                'provider_suite_id' => (int)$suite->id, 'pre_auth_code' => '', 'return_url' => '',
                'meta_json' => [
                    'binding_version' => self::VERSION, 'status' => 'waiting',
                    'identity' => $this->identity($suite, $authorization),
                ],
                'expires_at' => $expiresAt, 'used_at' => 0, 'create_at' => time(),
            ]);
            return [
                'url' => (new WecomProviderCredentialService())->memberOauthUrl($suite, $authorization, $state),
                'intent_id' => (int)$intent->id, 'expires_at' => $expiresAt, 'expires_in' => self::TTL,
            ];
        });
    }

    public function status(int $siteId, int $uid, int $intentId): array
    {
        $intent = $this->scopedIntent($siteId, $uid, $intentId);
        try {
            // 到期时确认请求可能尚未提交；用同一锁顺序等待它完成，避免误报 expired。
            return Db::transaction(function () use ($siteId, $uid, $intentId): array {
                [$suite, $authorization] = $this->context($siteId, $uid, true);
                $intent = $this->scopedIntent($siteId, $uid, $intentId, true);
                $this->assertIdentity($intent, $suite, $authorization);
                $result = $this->result($intent);
                if ($result['status'] === 'bound') $this->assertBound($intent, $authorization);
                return $result;
            });
        } catch (CommonException $e) {
            return ['status' => 'failed', 'expires_at' => (int)$intent->expires_at,
                'expires_in' => max(0, (int)$intent->expires_at - time()), 'message' => $e->getMessage()];
        }
    }

    public function confirm(int $siteId, int $uid, int $intentId): array
    {
        return Db::transaction(function () use ($siteId, $uid, $intentId): array {
            [$suite, $authorization] = $this->context($siteId, $uid, true);
            $intent = $this->scopedIntent($siteId, $uid, $intentId, true);
            $this->assertIdentity($intent, $suite, $authorization);
            $result = $this->result($intent);
            if ($result['status'] === 'bound') {
                $this->assertBound($intent, $authorization);
                return $result; // 重复确认返回原结果，不再次覆盖已建立的关系。
            }
            if ($result['status'] !== 'scanned') throw new CommonException($result['message']);
            $meta = $this->meta($intent);
            $candidate = $meta['candidate'] ?? [];
            $recipientId = trim((string)($candidate['recipient_id'] ?? ''));
            if ($recipientId === '') throw new CommonException('尚未取得员工身份，请重新扫码');
            $this->assertRecipientFree($siteId, $uid, $recipientId);
            $binding = WecomStaffBinding::where([
                ['site_id', '=', $siteId], ['uid', '=', $uid],
            ])->lock(true)->findOrEmpty();
            $data = [
                'corp_authorization_id' => (int)$authorization->id,
                'wecom_userid' => $recipientId,
                'open_userid' => (string)($candidate['open_userid'] ?? ''),
                'id_scope' => (string)$candidate['id_scope'],
                'bind_source' => 'oauth', 'verified_at' => time(),
                // 重新绑定不得偷偷打开原先关闭的员工通知。
                'status' => $binding->isEmpty() ? 1 : (int)$binding->status,
                'update_at' => time(),
            ];
            if ($binding->isEmpty()) {
                WecomStaffBinding::create(array_merge($data, [
                    'site_id' => $siteId, 'uid' => $uid, 'create_at' => time(),
                ]));
            } else {
                $binding->save($data);
            }
            $this->finish($intent, 'bound', '员工身份已确认，绑定成功');
            return $this->result($intent);
        });
    }

    public function cancel(int $siteId, int $uid, int $intentId): array
    {
        return Db::transaction(function () use ($siteId, $uid, $intentId): array {
            [$suite, $authorization] = $this->context($siteId, $uid, true);
            $intent = $this->scopedIntent($siteId, $uid, $intentId, true);
            $this->assertIdentity($intent, $suite, $authorization);
            if ($this->result($intent)['status'] === 'bound') {
                throw new CommonException('身份已经确认，不能通过撤销入口解除绑定；可关闭员工通知');
            }
            $this->finish($intent, 'failed', '管理员已撤销本次绑定入口，请重新生成');
            return $this->result($intent);
        });
    }

    public function complete(string $channel, string $state, string $code): array
    {
        $intent = $this->callbackIntent($channel, $state);
        [$suite, $authorization] = $this->context((int)$intent->site_id, (int)$intent->uid);
        $this->assertIdentity($intent, $suite, $authorization);
        $result = $this->result($intent);
        if ($result['status'] === 'bound') $this->assertBound($intent, $authorization);
        if ($result['status'] !== 'waiting') return $result;
        try {
            // 网络请求不持有数据库锁；返回后必须重新检查入口、员工和企业授权。
            $info = (new WecomProviderCredentialService())->userInfo3rd($suite, $code);
        } catch (\Throwable $e) {
            return $this->failCallback($intent, '未能获取企业微信身份，请让管理员重新生成二维码后在企业微信中扫码');
        }
        return Db::transaction(function () use ($intent, $info): array {
            [$suite, $authorization] = $this->context((int)$intent->site_id, (int)$intent->uid, true);
            $intent = $this->scopedIntent((int)$intent->site_id, (int)$intent->uid, (int)$intent->id, true);
            $this->assertIdentity($intent, $suite, $authorization);
            $result = $this->result($intent);
            if ($result['status'] === 'bound') $this->assertBound($intent, $authorization);
            if ($result['status'] !== 'waiting') return $result;
            $corpId = trim((string)($info['CorpId'] ?? $info['corpid'] ?? $info['corp_id'] ?? ''));
            $userId = trim((string)($info['UserId'] ?? $info['userid'] ?? ''));
            $openUserId = trim((string)($info['open_userid'] ?? $info['OpenUserId'] ?? ''));
            $recipientId = $openUserId !== '' ? $openUserId : $userId;
            if ($corpId === '' || !hash_equals((string)$authorization->auth_corpid, $corpId)) {
                $this->finish($intent, 'failed', '扫码企业与已授权企业不一致，请切换到正确企业后重新生成并扫码');
                return $this->result($intent);
            }
            // wecom_userid 沿用现有 varchar(100)，不能让 OAuth 成功后在确认时数据库截断。
            if ($recipientId === '' || strlen($recipientId) > 100 || preg_match('/[\x00-\x1f\x7f]/', $recipientId)) {
                $this->finish($intent, 'failed', '未识别到有效员工身份，请在企业微信中重新扫码');
                return $this->result($intent);
            }
            try {
                $this->assertRecipientFree((int)$intent->site_id, (int)$intent->uid, $recipientId);
            } catch (CommonException $e) {
                $this->finish($intent, 'failed', $e->getMessage());
                return $this->result($intent);
            }
            $meta = $this->meta($intent);
            $meta['status'] = 'scanned';
            $meta['scanned_at'] = time();
            $meta['candidate'] = [
                'corp_name' => (string)$authorization->corp_name,
                'recipient_id' => $recipientId, 'open_userid' => $openUserId,
                'id_scope' => $openUserId !== '' ? 'open_userid' : 'userid',
            ];
            $intent->save(['meta_json' => $meta]);
            return $this->result($intent);
        });
    }

    private function context(int $siteId, int $uid, bool $lock = false): array
    {
        if ($siteId <= 0 || $uid <= 0) throw new CommonException('请在具体站点中选择有效员工');
        $suite = WecomProviderSuite::where('status', '=', 'enabled')->order('id asc')->lock($lock)->findOrEmpty();
        if ($suite->isEmpty()) throw new CommonException('平台尚未启用企业微信服务商通道');
        $authorization = WecomCorpAuthorization::where([
            ['site_id', '=', $siteId], ['provider_suite_id', '=', (int)$suite->id], ['status', '=', 'authorized'],
        ])->lock($lock)->findOrEmpty();
        if ($authorization->isEmpty()) throw new CommonException('本站企业授权已失效，请先完成企业授权');
        $role = SysUserRole::where([
            ['site_id', '=', $siteId], ['uid', '=', $uid], ['status', '=', 1],
        ])->lock($lock)->findOrEmpty();
        $user = SysUser::where([['uid', '=', $uid], ['status', '=', 1]])->lock($lock)->findOrEmpty();
        if ($role->isEmpty() || $user->isEmpty()) throw new CommonException('该员工不属于本站或已经停用，无法继续绑定');
        $config = (new WecomConfigService())->get($siteId);
        if (($config['connection_mode'] ?? 'self_built') !== 'provider') {
            throw new CommonException('本站已切换为自建应用，当前服务商绑定入口不可使用');
        }
        return [$suite, $authorization];
    }

    private function scopedIntent(int $siteId, int $uid, int $id, bool $lock = false): WecomAuthorizationIntent
    {
        $intent = WecomAuthorizationIntent::where([
            ['id', '=', $id], ['site_id', '=', $siteId], ['uid', '=', $uid], ['purpose', '=', 'member_bind'],
        ])->lock($lock)->findOrEmpty();
        if ($id <= 0 || $intent->isEmpty()) throw new CommonException('绑定入口不存在或不属于当前站点员工');
        return $intent;
    }

    private function callbackIntent(string $channel, string $state): WecomAuthorizationIntent
    {
        if (!preg_match('/^[a-f0-9]{48}$/D', $state)) throw new CommonException('绑定入口无效，请让管理员重新生成二维码');
        $suite = WecomProviderSuite::where([['channel_code', '=', $channel], ['status', '=', 'enabled']])->findOrEmpty();
        if ($suite->isEmpty()) throw new CommonException('服务商通道不可用，请联系管理员');
        $intent = WecomAuthorizationIntent::where([
            ['state', '=', $state], ['provider_suite_id', '=', (int)$suite->id], ['purpose', '=', 'member_bind'],
        ])->findOrEmpty();
        if ($intent->isEmpty()) throw new CommonException('绑定入口无效，请让管理员重新生成二维码');
        return $intent;
    }

    private function identity(WecomProviderSuite $suite, WecomCorpAuthorization $authorization): array
    {
        return [
            'suite_record_id' => (int)$suite->id, 'suite_id' => (string)$suite->suite_id,
            'provider_corp_id' => (string)$suite->provider_corp_id,
            'authorization_id' => (int)$authorization->id, 'corp_id' => (string)$authorization->auth_corpid,
            'agent_id' => (int)$authorization->agent_id, 'authorized_at' => (int)$authorization->authorized_at,
        ];
    }

    private function assertIdentity(WecomAuthorizationIntent $intent, WecomProviderSuite $suite, WecomCorpAuthorization $authorization): void
    {
        $meta = $this->meta($intent);
        if ((int)($meta['binding_version'] ?? 0) !== self::VERSION
            || (int)$intent->provider_suite_id !== (int)$suite->id
            || ($meta['identity'] ?? []) !== $this->identity($suite, $authorization)) {
            throw new CommonException('企业授权或绑定规则已变化，请让管理员重新生成二维码');
        }
    }

    private function assertRecipientFree(int $siteId, int $uid, string $recipientId): void
    {
        $duplicate = WecomStaffBinding::where([
            ['site_id', '=', $siteId], ['wecom_userid', '=', $recipientId], ['uid', '<>', $uid],
        ])->lock(true)->findOrEmpty();
        if (!$duplicate->isEmpty()) throw new CommonException('该企业微信身份已绑定本站其他员工，请先由管理员核对');
    }

    private function assertBound(WecomAuthorizationIntent $intent, WecomCorpAuthorization $authorization): void
    {
        $binding = WecomStaffBinding::where([
            ['site_id', '=', (int)$intent->site_id], ['uid', '=', (int)$intent->uid],
            ['corp_authorization_id', '=', (int)$authorization->id],
            ['wecom_userid', '=', (string)($this->meta($intent)['candidate']['recipient_id'] ?? '')],
        ])->findOrEmpty();
        if ($binding->isEmpty()) throw new CommonException('员工绑定已变更，请刷新员工列表核对');
    }

    private function meta(WecomAuthorizationIntent $intent): array
    {
        return is_array($intent->meta_json) ? $intent->meta_json : [];
    }

    private function finish(WecomAuthorizationIntent $intent, string $status, string $message): void
    {
        $meta = $this->meta($intent);
        $meta['status'] = $status;
        $meta['message'] = $message;
        if ($status !== 'bound') unset($meta['candidate']);
        $intent->save(['meta_json' => $meta, 'used_at' => time()]);
    }

    private function result(WecomAuthorizationIntent $intent): array
    {
        $meta = $this->meta($intent);
        $status = (string)($meta['status'] ?? 'failed');
        if (!in_array($status, ['waiting', 'scanned', 'bound', 'failed'], true)) $status = 'failed';
        if (in_array($status, ['waiting', 'scanned'], true) && (int)$intent->expires_at <= time()) $status = 'expired';
        if ((int)$intent->used_at > 0 && in_array($status, ['waiting', 'scanned'], true)) $status = 'failed';
        $messages = [
            'waiting' => '请让该员工使用已授权企业的企业微信扫码',
            'scanned' => '员工已扫码，请管理员与本人核对企业和身份后确认绑定',
            'bound' => '员工身份已确认，绑定成功',
            'expired' => '绑定入口已过期，请管理员重新生成二维码',
            'failed' => '本次绑定未完成，请管理员重新生成二维码',
        ];
        $result = ['status' => $status, 'expires_at' => (int)$intent->expires_at,
            'expires_in' => max(0, (int)$intent->expires_at - time()),
            'message' => $status === 'failed' ? (string)($meta['message'] ?? $messages[$status]) : $messages[$status]];
        if (in_array($status, ['scanned', 'bound'], true) && is_array($meta['candidate'] ?? null)) {
            $id = (string)($meta['candidate']['recipient_id'] ?? '');
            $masked = strlen($id) > 8 ? substr($id, 0, 3) . '****' . substr($id, -4) : '****' . substr($id, -2);
            $result['candidate'] = [
                'corp_name' => (string)($meta['candidate']['corp_name'] ?? ''),
                'recipient_id' => $masked, 'identity_label' => '企业微信身份 ' . $masked,
            ];
        }
        return $result;
    }

    private function failCallback(WecomAuthorizationIntent $intent, string $message): array
    {
        return Db::transaction(function () use ($intent, $message): array {
            [$suite, $authorization] = $this->context((int)$intent->site_id, (int)$intent->uid, true);
            $intent = $this->scopedIntent((int)$intent->site_id, (int)$intent->uid, (int)$intent->id, true);
            $this->assertIdentity($intent, $suite, $authorization);
            if ($this->result($intent)['status'] === 'waiting') $this->finish($intent, 'failed', $message);
            return $this->result($intent);
        });
    }
}
