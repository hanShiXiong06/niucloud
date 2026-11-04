<template>
    <view>
        <view class="coop-balance-card">
            <view class="coop-muted">会员佣金账户 · 可提现收益</view>
            <view class="coop-balance">{{ coopMoney(wallet.available) }}</view>
            <view class="coop-withdraw-entry"><u-button type="primary" shape="circle" text="申请提现" :disabled="loading || Number(wallet.available || 0) <= 0" @click="withdraw" /></view>
            <view class="coop-income-summary"><view><text>提现处理中</text><text>{{ coopMoney(wallet.frozen) }}</text></view><view><text>累计佣金</text><text>{{ coopMoney(wallet.total_income) }}</text></view><view><text>已提现</text><text>{{ coopMoney(wallet.withdrawn) }}</text></view></view>
        </view>
        <view class="coop-wallet-note">本账户包含其他业务佣金。协作净收益也进入这里；提现条件与手续费以会员中心申请页为准。</view>
        <view class="coop-form-section coop-wallet-links"><view class="coop-menu-row" @tap="cashOutRecords"><text>提现记录</text><u-icon name="arrow-right" size="14" color="#929ba8" /></view><view class="coop-menu-row" @tap="commissionAccount"><text>会员佣金总账</text><u-icon name="arrow-right" size="14" color="#929ba8" /></view></view>
        <CoopState v-if="loading || error" :loading="loading" :error="error" @retry="$emit('reload')" />
        <view v-else class="coop-form-section">
            <view class="coop-section-title">协作收益明细</view>
            <view v-for="row in entries" :key="row.id" class="coop-wallet-entry"><view><view>{{ row.customer_name ? row.customer_name + '的交付' : '协作佣金收益' }}</view><text>{{ row.status === 'paid' ? '已支付佣金形成的发布方净收益' : '款项状态待核对，不代表已到账' }}</text><text>{{ coopDate(row.pay_time || row.paid_at || row.create_time) }}</text></view><text :class="{ credit: row.status === 'paid' }">{{ row.status === 'paid' ? '+' : '' }}{{ coopMoney(row.net_amount ?? row.amount) }}</text></view>
            <view v-if="!entries.length" class="coop-muted">暂无已支付的协作收益</view>
            <view v-if="hasMore" class="coop-loadmore" @tap="!loading && $emit('more')">{{ loading ? '正在读取更早记录…' : '加载更早的协作收益' }}</view>
        </view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { redirect } from '@/utils/common'
import { coopDate, coopMoney, type CoopRow } from '../../api/cooperation'
import CoopState from './CoopState.vue'
const props = defineProps<{ wallet: CoopRow; loading?: boolean; error?: string }>()
defineEmits<{ (event: 'reload' | 'more'): void }>()
const entries = computed(() => Array.isArray(props.wallet.entries) ? props.wallet.entries : props.wallet.entries?.data || [])
const hasMore = computed(() => entries.value.length < Number(props.wallet.entries?.total || 0))
function withdraw() { uni.setStorageSync('cashOutAccountType', 'commission'); redirect({ url: '/app/pages/member/apply_cash_out' }) }
function cashOutRecords() { uni.setStorageSync('cashOutAccountType', 'commission'); redirect({ url: '/app/pages/member/cash_out' }) }
function commissionAccount() { redirect({ url: '/app/pages/member/commission' }) }
</script>
<style scoped lang="scss">
@import './cooperation-shared';
@include hsx-coop-form-section;
@include hsx-coop-section-title;
@include hsx-coop-muted;
@include hsx-coop-menu-row;
@include hsx-coop-loadmore;
.coop-balance-card { padding: 40rpx 30rpx 26rpx; background: #fff; text-align: center; }
.coop-balance { font-size: 76rpx; font-weight: 650; line-height: 1.6; color: #242930; margin: 12rpx 0; }
.coop-withdraw-entry { width: 250rpx; margin: 0 auto 32rpx; }
.coop-income-summary { display: flex; gap: 20rpx; padding-top: 28rpx; border-top: 1rpx solid #edf0f3; }
.coop-income-summary>view { display: flex; flex-direction: column; gap: 13rpx; flex: 1; font-size: 27rpx; color: #5e6a78; }
.coop-income-summary>view>text:first-child { font-size: 21rpx; color: #929da9; }
.coop-wallet-note { font-size: 23rpx; line-height: 1.8; color: #939daa; text-align: center; padding: 22rpx 30rpx; }
.coop-wallet-entry { display: flex; align-items: flex-start; justify-content: space-between; gap: 20rpx; font-size: 26rpx; padding: 22rpx 0; border-bottom: 1rpx solid #edf0f3; line-height: 1.7; color: #586778; }
.coop-wallet-entry>view { display: flex; flex-direction: column; gap: 6rpx; min-width: 0; }
.coop-wallet-entry>view>text { color: #949eab; font-size: 21rpx; word-break: break-word; }
.coop-wallet-entry>text { flex: none; font-size: 25rpx; }
.coop-wallet-entry>text.credit { color: #537561; }
.coop-wallet-links { padding-top: 0; padding-bottom: 0; }.coop-wallet-links .coop-menu-row:first-child { border-top: 0; }
</style>
