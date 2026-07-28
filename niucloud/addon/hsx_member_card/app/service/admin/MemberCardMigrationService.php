<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\dict\MemberCardDict;
use addon\hsx_member_card\app\job\MemberCardMigrationImport;
use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\model\MemberCardExternalBinding;
use addon\hsx_member_card\app\model\MemberCardItem;
use addon\hsx_member_card\app\model\MemberCardMigrationItem;
use addon\hsx_member_card\app\model\MemberCardMigrationTask;
use addon\hsx_member_card\app\model\MemberCardOrder;
use addon\hsx_member_card\app\model\MemberCardProduct;
use addon\hsx_member_card\app\model\MemberCardProductItem;
use addon\hsx_member_card\app\model\MemberCardRedemption;
use addon\hsx_member_card\app\service\core\MemberCardLegacyClient;
use addon\hsx_member_card\app\support\MemberCardMigrationCipher;
use addon\hsx_member_card\app\support\MemberCardNumber;
use app\dict\member\MemberRegisterChannelDict;
use app\dict\member\MemberRegisterTypeDict;
use app\model\member\Member;
use app\service\core\member\CoreMemberService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 会员卡第三方期初建账及重复同步。
 *
 * 历史开卡数据直接写入会员卡事实表，不触发 ERP 财务、收款和员工绩效事件。
 */
final class MemberCardMigrationService extends BaseAdminService
{
    private const SOURCE_KEY = 'dianddnet';
    private const MAX_PAGES = 2000;

    public function start(array $data): array
    {
        $phone = trim((string)($data['phone'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $client = new MemberCardLegacyClient();
        $credential = $client->login($phone, $password);
        // 登录后立即丢弃密码；任务中只保存加密 Token。
        $productPage = $client->productPage($credential, 1);
        $customerPage = $client->customerPage($credential, 1);
        $now = time();
        $activeTask = MemberCardMigrationTask::where([
            ['site_id', '=', (int)$this->site_id],
            ['source_key', '=', self::SOURCE_KEY],
            ['source_store_id', '=', (string)($credential['store_id'] ?? '')],
            ['status', 'in', ['pending', 'queued', 'processing']],
            ['update_at', '>', $now - 7200],
        ])->order('id desc')->findOrEmpty();
        if (!$activeTask->isEmpty()) {
            throw new CommonException('该旧平台门店已有同步任务正在执行，请勿重复提交');
        }
        $task = MemberCardMigrationTask::create([
            'site_id' => (int)$this->site_id,
            'source_key' => self::SOURCE_KEY,
            'source_account' => $this->maskMobile($phone),
            'source_store_id' => (string)($credential['store_id'] ?? ''),
            'credential_ciphertext' => MemberCardMigrationCipher::encrypt($this->encode($credential)),
            'status' => 'pending',
            'queue_enabled' => env('queue.state', false) ? 1 : 0,
            'product_total' => (int)$productPage['total'],
            'customer_total' => (int)$customerPage['total'],
            'summary_json' => [
                'store_name' => (string)($credential['store_name'] ?? ''),
                'remote_account_name' => (string)($credential['real_name'] ?? ''),
                'product_preview' => array_slice($productPage['records'], 0, 5),
                'customer_preview' => array_map(fn(array $row): array => $this->safeCustomerPreview($row), array_slice($customerPage['records'], 0, 5)),
            ],
            'result_json' => [],
            'message' => '旧平台连接成功，等待同步',
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'create_at' => $now,
            'update_at' => $now,
        ]);

        $result = $this->dispatch((int)$task->id, (int)$this->site_id);
        $result['connection'] = [
            'store_name' => (string)($credential['store_name'] ?? ''),
            'product_total' => (int)$productPage['total'],
            'customer_total' => (int)$customerPage['total'],
        ];
        return $result;
    }

    public function lists(array $where): array
    {
        $query = MemberCardMigrationTask::where([['site_id', '=', (int)$this->site_id]]);
        $status = trim((string)($where['status'] ?? ''));
        if ($status !== '') $query->where('status', '=', $status);
        $page = $query->field($this->taskSafeFields())->order('id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        foreach (($page[$key] ?? []) as &$row) $row = $this->formatTask($row);
        unset($row);
        return $page;
    }

    public function info(int $id): array
    {
        return $this->formatTask($this->findTask($id)->hidden(['credential_ciphertext'])->toArray());
    }

    public function items(int $id, array $where): array
    {
        $this->findTask($id);
        $query = MemberCardMigrationItem::where([
            ['site_id', '=', (int)$this->site_id],
            ['task_id', '=', $id],
        ]);
        foreach (['entity_type', 'status'] as $field) {
            $value = trim((string)($where[$field] ?? ''));
            if ($value !== '') $query->where($field, '=', $value);
        }
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') $query->whereLike('display_name|mobile|external_id|message', '%' . $keyword . '%');
        return $query->order('id asc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 20))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
    }

    public function run(int $taskId, int $siteId): void
    {
        $task = $this->findTaskForSite($taskId, $siteId);
        if ((string)$task->status === 'completed') return;
        $credential = $this->decode(MemberCardMigrationCipher::decrypt((string)$task->credential_ciphertext));
        if (!is_array($credential) || empty($credential['token_value'])) {
            throw new CommonException('同步凭据无法读取，请重新发起同步');
        }
        $this->updateTask($taskId, $siteId, [
            'status' => 'processing',
            'start_at' => time(),
            'finish_at' => 0,
            'message' => '正在同步卡项',
            'error_message' => '',
        ]);

        $stats = [
            'processed_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'reused_count' => 0,
            'skipped_count' => 0,
            'conflict_count' => 0,
            'error_count' => 0,
            'product_total' => 0,
            'customer_total' => 0,
            'card_total' => 0,
        ];
        $newMemberEvents = [];
        try {
            $client = new MemberCardLegacyClient();
            $productMap = $this->syncProducts($client, $credential, $taskId, $siteId, $stats);
            $this->updateTask($taskId, $siteId, ['message' => '正在同步客户和持卡余额']);
            $this->syncCustomers($client, $credential, $taskId, $siteId, $productMap, $stats, $newMemberEvents);

            foreach ($newMemberEvents as $eventData) {
                try {
                    event('MemberRegister', $eventData);
                } catch (\Throwable $eventError) {
                    Log::warning('会员卡期初同步注册后置事件失败', [
                        'member_id' => $eventData['member_id'] ?? 0,
                        'message' => $eventError->getMessage(),
                    ]);
                }
            }

            $status = $stats['error_count'] > 0 || $stats['conflict_count'] > 0 ? 'partial' : 'completed';
            $message = $status === 'completed' ? '期初会员卡同步完成' : '同步完成，存在需要处理的异常或冲突';
            $this->updateTask($taskId, $siteId, array_merge($stats, [
                'status' => $status,
                'result_json' => [
                    'account_summary' => [
                        'created' => $stats['created_count'],
                        'updated' => $stats['updated_count'],
                        'reused' => $stats['reused_count'],
                        'skipped' => $stats['skipped_count'],
                        'conflict' => $stats['conflict_count'],
                        'failed' => $stats['error_count'],
                    ],
                    'guidance' => [
                        '新客户默认密码为 123456，请提醒客户首次登录后修改',
                        '期初卡不会重复产生 ERP 收款、应收或员工绩效',
                        '旧平台仅提供累计已用次数，期初同步不会生成历史逐笔核销明细',
                        '存在本地核销或余额调整的卡不会被旧平台余额覆盖，请在冲突明细中人工核对',
                    ],
                ],
                'message' => $message,
                'credential_ciphertext' => '',
                'finish_at' => time(),
            ]));
        } catch (\Throwable $e) {
            $this->updateTask($taskId, $siteId, array_merge($stats, [
                'status' => 'failed',
                'message' => '同步失败，请检查旧平台账号或网络后重新发起',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'credential_ciphertext' => '',
                'finish_at' => time(),
            ]));
            throw $e;
        }
    }

    private function syncProducts(
        MemberCardLegacyClient $client,
        array $credential,
        int $taskId,
        int $siteId,
        array &$stats
    ): array {
        $map = [];
        foreach ($this->remotePages(fn(int $page): array => $client->productPage($credential, $page)) as $page) {
            $stats['product_total'] = max($stats['product_total'], (int)$page['total']);
            foreach ($page['records'] as $remote) {
                $externalId = trim((string)($remote['id'] ?? ''));
                if ($externalId === '') {
                    $this->recordFailure($taskId, $siteId, 'product', '', (string)($remote['name'] ?? ''), '', '卡项缺少唯一ID', $remote, $stats);
                    continue;
                }
                try {
                    $result = $this->upsertProduct($taskId, $siteId, $remote);
                    $map[$externalId] = $result['local_id'];
                    $this->recordItem($taskId, $siteId, 'product', $externalId, (string)($remote['name'] ?? ''), '', $result, $remote, $stats);
                } catch (\Throwable $e) {
                    $this->recordFailure($taskId, $siteId, 'product', $externalId, (string)($remote['name'] ?? ''), '', $e->getMessage(), $remote, $stats);
                }
            }
            $this->updateTask($taskId, $siteId, $stats);
        }
        return $map;
    }

    private function syncCustomers(
        MemberCardLegacyClient $client,
        array $credential,
        int $taskId,
        int $siteId,
        array &$productMap,
        array &$stats,
        array &$newMemberEvents
    ): void {
        foreach ($this->remotePages(fn(int $page): array => $client->customerPage($credential, $page)) as $page) {
            $stats['customer_total'] = max($stats['customer_total'], (int)$page['total']);
            foreach ($page['records'] as $remote) {
                $externalId = trim((string)($remote['id'] ?? ''));
                $mobile = preg_replace('/\D+/', '', (string)($remote['phone'] ?? ''));
                $name = $this->customerName((string)($remote['name'] ?? ''), $mobile);
                if ($externalId === '' || !preg_match('/^1\d{10}$/', $mobile)) {
                    $this->recordFailure($taskId, $siteId, 'customer', $externalId, $name, $mobile, '客户ID或手机号不正确', $this->safeCustomerSnapshot($remote), $stats);
                    continue;
                }
                try {
                    $customer = $this->upsertCustomer($taskId, $siteId, $remote, $newMemberEvents);
                    $this->recordItem($taskId, $siteId, 'customer', $externalId, $name, $mobile, $customer, $this->safeCustomerSnapshot($remote), $stats);
                } catch (\Throwable $e) {
                    $this->recordFailure($taskId, $siteId, 'customer', $externalId, $name, $mobile, $e->getMessage(), $this->safeCustomerSnapshot($remote), $stats);
                    continue;
                }

                foreach ((array)($remote['cardList'] ?? []) as $remoteCard) {
                    if (!is_array($remoteCard)) continue;
                    $stats['card_total']++;
                    $remoteProductId = trim((string)($remoteCard['cardId'] ?? ''));
                    if (!isset($productMap[$remoteProductId])) {
                        try {
                            $fallback = $this->upsertProduct($taskId, $siteId, [
                                'id' => $remoteProductId,
                                'name' => (string)($remoteCard['cardName'] ?? $remoteCard['name'] ?? '历史卡项'),
                                'price' => $remoteCard['price'] ?? 0,
                                'longExpire' => true,
                                'usableNum' => $remoteCard['useNum'] ?? 0,
                                'expireNum' => 0,
                                'enabled' => false,
                                'remark' => '由客户持卡关系补建',
                            ]);
                            $productMap[$remoteProductId] = $fallback['local_id'];
                            $stats['product_total']++;
                            $this->recordItem(
                                $taskId,
                                $siteId,
                                'product',
                                $remoteProductId,
                                (string)($remoteCard['cardName'] ?? $remoteCard['name'] ?? '历史卡项'),
                                '',
                                $fallback,
                                [
                                    'id' => $remoteProductId,
                                    'name' => (string)($remoteCard['cardName'] ?? $remoteCard['name'] ?? '历史卡项'),
                                    'price' => $remoteCard['price'] ?? 0,
                                    'usableNum' => $remoteCard['useNum'] ?? 0,
                                    'source' => 'customer_card_fallback',
                                ],
                                $stats
                            );
                        } catch (\Throwable $e) {
                            $this->recordFailure($taskId, $siteId, 'card', (string)($remoteCard['id'] ?? ''), (string)($remoteCard['cardName'] ?? ''), $mobile, '关联卡项创建失败：' . $e->getMessage(), $remoteCard, $stats);
                            continue;
                        }
                    }
                    try {
                        $card = $this->upsertCard($taskId, $siteId, $remoteCard, $remote, (int)$customer['local_id'], (int)$productMap[$remoteProductId]);
                        $this->recordItem($taskId, $siteId, 'card', (string)($remoteCard['id'] ?? ''), (string)($remoteCard['cardName'] ?? ''), $mobile, $card, $remoteCard, $stats);
                    } catch (\Throwable $e) {
                        $this->recordFailure($taskId, $siteId, 'card', (string)($remoteCard['id'] ?? ''), (string)($remoteCard['cardName'] ?? ''), $mobile, $e->getMessage(), $remoteCard, $stats);
                    }
                }
            }
            $this->updateTask($taskId, $siteId, $stats);
        }
    }

    private function upsertProduct(int $taskId, int $siteId, array $remote): array
    {
        $externalId = trim((string)($remote['id'] ?? ''));
        if ($externalId === '') throw new CommonException('卡项缺少唯一ID');
        $hash = $this->hash($remote);
        $binding = $this->binding($siteId, 'product', $externalId);
        $product = $binding->isEmpty() ? null : MemberCardProduct::where([
            ['site_id', '=', $siteId], ['id', '=', (int)$binding->local_id],
        ])->findOrEmpty();
        if ($product !== null && !$product->isEmpty() && (string)$binding->payload_hash === $hash) {
            return ['action' => 'skipped', 'status' => 'success', 'local_type' => 'product', 'local_id' => (int)$product->id, 'payload_hash' => $hash, 'message' => '卡项未变化'];
        }

        $name = mb_substr(trim((string)($remote['name'] ?? '')), 0, 100);
        if ($name === '') throw new CommonException('卡项名称不能为空');
        $price = number_format(max(0, (float)($remote['price'] ?? 0)), 2, '.', '');
        $permanent = (bool)($remote['longExpire'] ?? false);
        $duration = max(0, (int)($remote['expireNum'] ?? 0));
        $now = time();
        $values = [
            'product_name' => $name,
            'sale_price' => $price,
            'market_price' => $price,
            'effective_mode' => 'immediate',
            'validity_mode' => $permanent || $duration <= 0 ? 'permanent' : 'duration',
            'duration_value' => $permanent ? 0 : $duration,
            'duration_unit' => 'day',
            'usage_notice' => mb_substr(trim((string)($remote['remark'] ?? '')), 0, 1000),
            'status' => !empty($remote['enabled']) ? MemberCardDict::PRODUCT_ENABLED : MemberCardDict::PRODUCT_DISABLED,
            'update_at' => $now,
        ];
        $action = 'updated';
        Db::transaction(function () use (&$product, $siteId, $externalId, $remote, $values, $now, &$action): void {
            if ($product === null || $product->isEmpty()) {
                $product = MemberCardProduct::create(array_merge($values, [
                    'site_id' => $siteId,
                    'product_no' => MemberCardNumber::make('CP'),
                    'cover_url' => '',
                    'fixed_start_at' => 0,
                    'fixed_end_at' => 0,
                    'sort' => 0,
                    'create_uid' => 0,
                    'create_name' => '期初同步',
                    'update_uid' => 0,
                    'update_name' => '期初同步',
                    'create_at' => $this->timestamp((string)($remote['createTime'] ?? '')) ?: $now,
                ]));
                $action = 'created';
            } else {
                $product->save(array_merge($values, ['update_name' => '期初同步']));
            }
            $times = max(0, (int)($remote['usableNum'] ?? 0));
            $item = MemberCardProductItem::where([
                ['site_id', '=', $siteId], ['product_id', '=', (int)$product->id], ['item_code', '=', 'film_service'],
            ])->findOrEmpty();
            $itemValues = [
                'item_name' => (string)$product->product_name,
                'usage_mode' => 'limited',
                'total_times' => $times,
                'daily_limit' => 0,
                'reference_price' => $times > 0 ? number_format((float)$product->sale_price / $times, 2, '.', '') : 0,
                'recognition_mode' => 'average',
                'recognition_amount' => 0,
                'sort' => 0,
                'status' => 1,
                'update_at' => $now,
            ];
            if ($item->isEmpty()) {
                MemberCardProductItem::create(array_merge($itemValues, [
                    'site_id' => $siteId, 'product_id' => (int)$product->id, 'item_code' => 'film_service', 'create_at' => $now,
                ]));
            } else {
                $item->save($itemValues);
            }
        });
        $this->saveBinding($binding, $siteId, 'product', $externalId, 'product', (int)$product->id, $hash, $taskId, $this->timestamp((string)($remote['createTime'] ?? '')));
        return ['action' => $action, 'status' => 'success', 'local_type' => 'product', 'local_id' => (int)$product->id, 'payload_hash' => $hash, 'message' => $action === 'created' ? '新增卡项' : '更新卡项'];
    }

    private function upsertCustomer(int $taskId, int $siteId, array $remote, array &$newMemberEvents): array
    {
        $externalId = trim((string)($remote['id'] ?? ''));
        $mobile = preg_replace('/\D+/', '', (string)($remote['phone'] ?? ''));
        if ($externalId === '' || !preg_match('/^1\d{10}$/', $mobile)) throw new CommonException('客户ID或手机号不正确');
        $snapshot = $this->safeCustomerSnapshot($remote);
        $hash = $this->hash($snapshot);
        $binding = $this->binding($siteId, 'customer', $externalId);
        $member = $binding->isEmpty() ? null : Member::where([
            ['site_id', '=', $siteId], ['member_id', '=', (int)$binding->local_id], ['is_del', '=', 0],
        ])->findOrEmpty();
        if ($member === null || $member->isEmpty()) {
            $members = Member::where([['site_id', '=', $siteId], ['mobile', '=', $mobile], ['is_del', '=', 0]])->select();
            if ($members->count() > 1) throw new CommonException('手机号对应多个会员账号，请先合并');
            $member = $members->isEmpty() ? null : $members->first();
        }
        $name = $this->customerName((string)($remote['name'] ?? ''), $mobile);
        $action = 'reused';
        if ($member === null || $member->isEmpty()) {
            $now = time();
            $member = Member::create([
                'site_id' => $siteId,
                'member_no' => '',
                'username' => $mobile,
                'mobile' => $mobile,
                'password' => create_password('123456'),
                'nickname' => $name,
                'member_label' => [],
                'register_type' => MemberRegisterTypeDict::MANUAL,
                'register_channel' => MemberRegisterChannelDict::MANUAL,
                'login_type' => 'h5',
                'status' => 1,
                'sex' => (string)($remote['gender'] ?? '') === '女' ? 2 : 1,
                'remark' => mb_substr('会员卡期初同步；来源：' . (string)($remote['fromSource'] ?? ''), 0, 255),
                'create_time' => $this->timestamp((string)($remote['createTime'] ?? '')) ?: $now,
                'update_time' => $now,
            ]);
            CoreMemberService::setMemberNo($siteId, (int)$member->member_id);
            $member->refresh();
            $action = 'created';
            $newMemberEvents[] = [
                'site_id' => $siteId,
                'member_id' => (int)$member->member_id,
                'member_no' => (string)$member->member_no,
                'username' => $mobile,
                'mobile' => $mobile,
                'nickname' => $name,
                'register_type' => MemberRegisterTypeDict::MANUAL,
                'register_channel' => MemberRegisterChannelDict::MANUAL,
            ];
        } elseif (trim((string)$member->nickname) === '' || (string)$member->nickname === $mobile) {
            $member->save(['nickname' => $name, 'update_time' => time()]);
            $action = 'updated';
        } elseif (!$binding->isEmpty() && (string)$binding->payload_hash === $hash) {
            $action = 'skipped';
        }
        $this->saveBinding($binding, $siteId, 'customer', $externalId, 'member', (int)$member->member_id, $hash, $taskId, $this->timestamp((string)($remote['createTime'] ?? '')));
        return ['action' => $action, 'status' => 'success', 'local_type' => 'member', 'local_id' => (int)$member->member_id, 'payload_hash' => $hash, 'message' => $action === 'created' ? '创建新客户，初始密码为 123456' : ($action === 'updated' ? '按手机号关联并补充客户姓名' : '按手机号关联已有客户')];
    }

    private function upsertCard(
        int $taskId,
        int $siteId,
        array $remote,
        array $remoteCustomer,
        int $memberId,
        int $productId
    ): array {
        $externalId = trim((string)($remote['id'] ?? ''));
        if ($externalId === '') throw new CommonException('持卡记录缺少唯一ID');
        $hash = $this->hash($remote);
        $binding = $this->binding($siteId, 'card', $externalId);
        $card = $binding->isEmpty() ? null : MemberCard::where([
            ['site_id', '=', $siteId], ['id', '=', (int)$binding->local_id],
        ])->findOrEmpty();
        if ($card !== null && !$card->isEmpty() && (string)$binding->payload_hash === $hash) {
            return ['action' => 'skipped', 'status' => 'success', 'local_type' => 'card', 'local_id' => (int)$card->id, 'payload_hash' => $hash, 'message' => '持卡余额未变化'];
        }
        if ($card !== null && !$card->isEmpty() && $this->localCardChangedSinceSync($binding, $card, $siteId, $externalId)) {
            return ['action' => 'conflict', 'status' => 'conflict', 'local_type' => 'card', 'local_id' => (int)$card->id, 'payload_hash' => $hash, 'message' => '本地已发生核销或余额调整，未用旧平台余额覆盖'];
        }

        $member = Member::where([['site_id', '=', $siteId], ['member_id', '=', $memberId]])->findOrEmpty();
        $product = MemberCardProduct::where([['site_id', '=', $siteId], ['id', '=', $productId]])->findOrEmpty();
        $productItem = MemberCardProductItem::where([['site_id', '=', $siteId], ['product_id', '=', $productId], ['status', '=', 1]])->findOrEmpty();
        if ($member->isEmpty() || $product->isEmpty() || $productItem->isEmpty()) throw new CommonException('持卡记录关联的客户或卡项不存在');
        $mobile = (string)$member->mobile;
        $holderName = trim((string)$member->nickname) !== '' ? (string)$member->nickname : $mobile;
        $granted = max(0, (int)($remote['useNum'] ?? 0));
        $used = max(0, min($granted, (int)($remote['usedNum'] ?? 0)));
        $remaining = max(0, min($granted, isset($remote['canUseNum']) ? (int)$remote['canUseNum'] : $granted - $used));
        if ($used + $remaining > $granted) $used = max(0, $granted - $remaining);
        $price = number_format(max(0, (float)($remote['price'] ?? $product->sale_price)), 2, '.', '');
        $createdAt = $this->timestamp((string)($remote['createTime'] ?? '')) ?: time();
        $validEnd = (string)$product->validity_mode === 'permanent' ? 0 : $this->timestamp((string)($remote['expireDate'] ?? ''));
        $status = $remaining <= 0 ? MemberCardDict::CARD_EXHAUSTED : ($validEnd > 0 && $validEnd < time() ? MemberCardDict::CARD_EXPIRED : MemberCardDict::CARD_ACTIVE);
        $operator = mb_substr(trim((string)($remote['recordAccountName'] ?? '期初同步')), 0, 60);
        $snapshot = [
            'source' => self::SOURCE_KEY,
            'external_card_id' => $externalId,
            'external_customer_id' => (string)($remoteCustomer['id'] ?? ''),
            'product' => $product->toArray(),
            'item' => $productItem->toArray(),
            'remote' => $remote,
        ];
        $action = 'updated';
        Db::transaction(function () use (
            &$card, $siteId, $memberId, $productId, $product, $productItem, $mobile, $holderName,
            $granted, $used, $remaining, $price, $createdAt, $validEnd, $status, $operator, $snapshot, $externalId, &$action
        ): void {
            if ($card === null || $card->isEmpty()) {
                $order = MemberCardOrder::create([
                    'site_id' => $siteId,
                    'order_no' => MemberCardNumber::make('MC'),
                    'request_id' => 'migration:' . self::SOURCE_KEY . ':card:' . $externalId,
                    'member_id' => $memberId,
                    'holder_name' => $holderName,
                    'holder_mobile' => $mobile,
                    'holder_mobile_last4' => substr($mobile, -4),
                    'party_id' => 0,
                    'party_name' => $holderName,
                    'product_id' => $productId,
                    'product_no' => (string)$product->product_no,
                    'product_name' => (string)$product->product_name,
                    'product_snapshot' => $this->encode($snapshot),
                    'order_amount' => $price,
                    'paid_amount' => $price,
                    'refunded_amount' => 0,
                    'settlement_mode' => 'opening',
                    'capital_account_id' => 0,
                    'capital_account_name' => '期初余额',
                    'business_status' => MemberCardDict::ORDER_ACTIVE,
                    'finance_status' => 'settled',
                    'issuer_uid' => 0,
                    'issuer_name' => $operator,
                    'confirmed_at' => $createdAt,
                    'cancelled_at' => 0,
                    'remark' => '第三方会员卡期初同步，不重复产生 ERP 财务',
                    'last_error' => '',
                    'create_at' => $createdAt,
                    'update_at' => time(),
                ]);
                $card = MemberCard::create([
                    'site_id' => $siteId,
                    'card_no' => MemberCardNumber::make('CARD'),
                    'order_id' => (int)$order->id,
                    'order_no' => (string)$order->order_no,
                    'member_id' => $memberId,
                    'holder_name' => $holderName,
                    'holder_mobile' => $mobile,
                    'holder_mobile_last4' => substr($mobile, -4),
                    'product_id' => $productId,
                    'product_no' => (string)$product->product_no,
                    'product_name' => (string)$product->product_name,
                    'rule_snapshot' => $this->encode($snapshot),
                    'status' => $status,
                    'finance_status' => 'settled',
                    'effective_mode' => 'immediate',
                    'activated_at' => $createdAt,
                    'first_used_at' => 0,
                    'valid_start_at' => $createdAt,
                    'valid_end_at' => $validEnd,
                    'freeze_reason' => '',
                    'issuer_uid' => 0,
                    'issuer_name' => $operator,
                    'create_at' => $createdAt,
                    'update_at' => time(),
                ]);
                MemberCardItem::create([
                    'site_id' => $siteId,
                    'card_id' => (int)$card->id,
                    'product_item_id' => (int)$productItem->id,
                    'item_code' => (string)$productItem->item_code,
                    'item_name' => (string)$productItem->item_name,
                    'usage_mode' => 'limited',
                    'granted_times' => $granted,
                    'used_times' => $used,
                    'remaining_times' => $remaining,
                    'reversed_times' => 0,
                    'daily_limit' => 0,
                    'allocated_amount' => $price,
                    'recognized_amount' => $granted > 0 ? number_format((float)$price * $used / $granted, 2, '.', '') : 0,
                    'status' => 1,
                    'create_at' => $createdAt,
                    'update_at' => time(),
                ]);
                $action = 'created';
            } else {
                $card->save([
                    'holder_name' => $holderName,
                    'status' => $status,
                    'valid_end_at' => $validEnd,
                    'rule_snapshot' => $this->encode($snapshot),
                    'update_at' => time(),
                ]);
                MemberCardOrder::where([['site_id', '=', $siteId], ['id', '=', (int)$card->order_id]])->update([
                    'holder_name' => $holderName,
                    'order_amount' => $price,
                    'paid_amount' => $price,
                    'product_snapshot' => $this->encode($snapshot),
                    'update_at' => time(),
                ]);
                MemberCardItem::where([['site_id', '=', $siteId], ['card_id', '=', (int)$card->id]])->update([
                    'granted_times' => $granted,
                    'used_times' => $used,
                    'remaining_times' => $remaining,
                    'allocated_amount' => $price,
                    'recognized_amount' => $granted > 0 ? number_format((float)$price * $used / $granted, 2, '.', '') : 0,
                    'update_at' => time(),
                ]);
            }
        });
        $this->saveBinding($binding, $siteId, 'card', $externalId, 'card', (int)$card->id, $hash, $taskId, $createdAt);
        return ['action' => $action, 'status' => 'success', 'local_type' => 'card', 'local_id' => (int)$card->id, 'payload_hash' => $hash, 'message' => $action === 'created' ? '创建期初会员卡' : '更新旧平台持卡余额'];
    }

    private function recordItem(
        int $taskId,
        int $siteId,
        string $entityType,
        string $externalId,
        string $displayName,
        string $mobile,
        array $result,
        array $snapshot,
        array &$stats
    ): void {
        $action = (string)($result['action'] ?? 'failed');
        $status = (string)($result['status'] ?? ($action === 'conflict' ? 'conflict' : 'success'));
        $values = [
            'site_id' => $siteId,
            'task_id' => $taskId,
            'source_key' => self::SOURCE_KEY,
            'entity_type' => $entityType,
            'external_id' => $externalId,
            'display_name' => mb_substr(trim($displayName), 0, 150),
            'mobile' => mb_substr($mobile, 0, 30),
            'action' => $action,
            'status' => $status,
            'local_type' => (string)($result['local_type'] ?? ''),
            'local_id' => (int)($result['local_id'] ?? 0),
            'payload_hash' => (string)($result['payload_hash'] ?? $this->hash($snapshot)),
            'snapshot_json' => $snapshot,
            'message' => mb_substr((string)($result['message'] ?? ''), 0, 1000),
            'update_at' => time(),
        ];
        $item = MemberCardMigrationItem::where([
            ['task_id', '=', $taskId], ['entity_type', '=', $entityType], ['external_id', '=', $externalId],
        ])->findOrEmpty();
        $item->isEmpty() ? MemberCardMigrationItem::create(array_merge($values, ['create_at' => time()])) : $item->save($values);
        $stats['processed_count']++;
        $counter = [
            'created' => 'created_count',
            'updated' => 'updated_count',
            'reused' => 'reused_count',
            'skipped' => 'skipped_count',
            'conflict' => 'conflict_count',
            'failed' => 'error_count',
        ][$action] ?? 'error_count';
        $stats[$counter]++;
    }

    private function recordFailure(
        int $taskId,
        int $siteId,
        string $entityType,
        string $externalId,
        string $displayName,
        string $mobile,
        string $message,
        array $snapshot,
        array &$stats
    ): void {
        if ($externalId === '') $externalId = 'invalid:' . substr($this->hash($snapshot), 0, 32);
        $this->recordItem($taskId, $siteId, $entityType, $externalId, $displayName, $mobile, [
            'action' => 'failed',
            'status' => 'failed',
            'local_type' => '',
            'local_id' => 0,
            'payload_hash' => $this->hash($snapshot),
            'message' => $message,
        ], $snapshot, $stats);
    }

    private function binding(int $siteId, string $entityType, string $externalId): MemberCardExternalBinding
    {
        return MemberCardExternalBinding::where([
            ['site_id', '=', $siteId],
            ['source_key', '=', self::SOURCE_KEY],
            ['entity_type', '=', $entityType],
            ['external_id', '=', $externalId],
        ])->findOrEmpty();
    }

    private function localCardChangedSinceSync(
        MemberCardExternalBinding $binding,
        MemberCard $card,
        int $siteId,
        string $externalId
    ): bool {
        if (MemberCardRedemption::where([
            ['site_id', '=', $siteId], ['card_id', '=', (int)$card->id], ['status', '=', 'success'],
        ])->count() > 0) return true;
        if ($binding->isEmpty() || (int)$binding->last_task_id <= 0) return false;
        $previous = MemberCardMigrationItem::where([
            ['site_id', '=', $siteId],
            ['task_id', '=', (int)$binding->last_task_id],
            ['entity_type', '=', 'card'],
            ['external_id', '=', $externalId],
        ])->findOrEmpty();
        if ($previous->isEmpty()) return false;
        $snapshot = is_array($previous->snapshot_json) ? $previous->snapshot_json : [];
        $item = MemberCardItem::where([
            ['site_id', '=', $siteId], ['card_id', '=', (int)$card->id], ['status', '=', 1],
        ])->findOrEmpty();
        if ($item->isEmpty()) return true;
        $expectedGranted = max(0, (int)($snapshot['useNum'] ?? 0));
        $expectedUsed = max(0, min($expectedGranted, (int)($snapshot['usedNum'] ?? 0)));
        $expectedRemaining = max(0, min($expectedGranted, isset($snapshot['canUseNum']) ? (int)$snapshot['canUseNum'] : $expectedGranted - $expectedUsed));
        return (int)$item->granted_times !== $expectedGranted
            || (int)$item->used_times !== $expectedUsed
            || (int)$item->remaining_times !== $expectedRemaining;
    }

    private function saveBinding(
        MemberCardExternalBinding $binding,
        int $siteId,
        string $entityType,
        string $externalId,
        string $localType,
        int $localId,
        string $hash,
        int $taskId,
        int $externalUpdatedAt
    ): void {
        $values = [
            'local_type' => $localType,
            'local_id' => $localId,
            'payload_hash' => $hash,
            'external_updated_at' => $externalUpdatedAt,
            'last_task_id' => $taskId,
            'last_sync_at' => time(),
            'status' => 'active',
            'update_at' => time(),
        ];
        if ($binding->isEmpty()) {
            MemberCardExternalBinding::create(array_merge($values, [
                'site_id' => $siteId,
                'source_key' => self::SOURCE_KEY,
                'entity_type' => $entityType,
                'external_id' => $externalId,
                'create_at' => time(),
            ]));
        } else {
            $binding->save($values);
        }
    }

    /** @return \Generator<int,array> */
    private function remotePages(callable $loader): \Generator
    {
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $result = $loader($page);
            yield $result;
            if ($page >= (int)$result['pages'] || count($result['records']) === 0) return;
        }
        throw new CommonException('旧平台分页数量异常，已停止同步');
    }

    private function dispatch(int $taskId, int $siteId): array
    {
        $queueEnabled = (bool)env('queue.state', false);
        $this->updateTask($taskId, $siteId, [
            'status' => $queueEnabled ? 'queued' : 'processing',
            'queue_enabled' => $queueEnabled ? 1 : 0,
            'message' => $queueEnabled ? '同步任务已进入后台队列' : '正在同步旧平台数据',
        ]);
        if ($queueEnabled) {
            $pushed = MemberCardMigrationImport::dispatch(['taskId' => $taskId, 'siteId' => $siteId]);
            if ($pushed !== false) return ['task_id' => $taskId, 'async' => true, 'message' => '连接成功，数据正在后台同步'];
            $this->updateTask($taskId, $siteId, ['status' => 'processing', 'queue_enabled' => 0, 'message' => '队列推送失败，已切换为当前请求执行']);
        }
        $this->run($taskId, $siteId);
        return ['task_id' => $taskId, 'async' => false, 'message' => '同步完成', 'task' => $this->info($taskId)];
    }

    private function updateTask(int $taskId, int $siteId, array $values): void
    {
        $values['update_at'] = time();
        MemberCardMigrationTask::where([['id', '=', $taskId], ['site_id', '=', $siteId]])->update($values);
    }

    private function findTask(int $id): MemberCardMigrationTask
    {
        return $this->findTaskForSite($id, (int)$this->site_id);
    }

    private function findTaskForSite(int $id, int $siteId): MemberCardMigrationTask
    {
        $task = MemberCardMigrationTask::where([['id', '=', $id], ['site_id', '=', $siteId]])->findOrEmpty();
        if ($task->isEmpty()) throw new CommonException('同步任务不存在');
        return $task;
    }

    private function taskSafeFields(): string
    {
        return 'id,site_id,source_key,source_account,source_store_id,status,queue_enabled,product_total,customer_total,card_total,processed_count,created_count,updated_count,reused_count,skipped_count,conflict_count,error_count,summary_json,result_json,message,error_message,operator_uid,operator_name,start_at,finish_at,create_at,update_at';
    }

    private function formatTask(array $row): array
    {
        unset($row['credential_ciphertext']);
        $total = (int)($row['product_total'] ?? 0) + (int)($row['customer_total'] ?? 0) + (int)($row['card_total'] ?? 0);
        $row['progress'] = $total > 0 ? min(100, round((int)($row['processed_count'] ?? 0) / $total * 100, 1)) : 0;
        $row['status_text'] = [
            'pending' => '等待同步',
            'queued' => '队列等待中',
            'processing' => '同步中',
            'completed' => '同步完成',
            'partial' => '部分完成',
            'failed' => '同步失败',
        ][(string)($row['status'] ?? '')] ?? (string)($row['status'] ?? '');
        return $row;
    }

    private function safeCustomerPreview(array $remote): array
    {
        return [
            'id' => (string)($remote['id'] ?? ''),
            'name' => (string)($remote['name'] ?? ''),
            'phone' => $this->maskMobile((string)($remote['phone'] ?? '')),
            'card_count' => count((array)($remote['cardList'] ?? [])),
        ];
    }

    private function safeCustomerSnapshot(array $remote): array
    {
        unset($remote['cardList']);
        return $remote;
    }

    private function customerName(string $name, string $mobile): string
    {
        $name = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\p{C}]+/u', '', $name);
        $name = trim((string)$name);
        if ($name === '' || !preg_match('/[\p{L}\p{N}]/u', $name)) return $mobile;
        return mb_substr($name, 0, 100);
    }

    private function maskMobile(string $mobile): string
    {
        return preg_match('/^1\d{10}$/', $mobile) ? substr($mobile, 0, 3) . '****' . substr($mobile, -4) : mb_substr($mobile, 0, 40);
    }

    private function hash(array $payload): string
    {
        $normalize = function (&$value) use (&$normalize): void {
            if (!is_array($value)) return;
            foreach ($value as &$child) $normalize($child);
            unset($child);
            if (!$this->isList($value)) ksort($value);
        };
        $normalize($payload);
        return hash('sha256', $this->encode($payload));
    }

    private function timestamp(string $value): int
    {
        if (trim($value) === '') return 0;
        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : $timestamp;
    }

    private function isList(array $value): bool
    {
        if ($value === []) return true;
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function encode(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new CommonException('同步数据序列化失败');
        return $json;
    }

    private function decode(string $value): array
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
