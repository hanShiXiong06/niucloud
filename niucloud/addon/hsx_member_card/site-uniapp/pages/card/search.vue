<template>
    <view class="mc-page redeem-page">
        <view class="search-panel mc-surface">
            <view class="search-panel__head">
                <view class="mc-icon-box"><u-icon name="phone" color="#2563eb" size="21" /></view>
                <view><text class="search-title">手机号快速核销</text><text class="search-sub">后四位查询，当面核对姓名</text></view>
            </view>
            <view class="input-wrap"><u-input v-model="mobile" type="number" maxlength="11" border="none" placeholder="完整手机号或后四位" prefixIcon="phone" /></view>
            <view class="input-wrap"><u-input v-model="name" border="none" placeholder="多人命中时填写姓名（可选）" prefixIcon="account" /></view>
            <MemberCardButton type="primary" icon="search" text="查询可用会员卡" :loading="loading" @click="search" />
            <view class="safe-tip"><u-icon name="info-circle" color="#64748b" size="14" /><text>核销前请让客户说出购卡姓名，避免误用他人权益</text></view>
        </view>

        <view v-if="searched && !candidates.length" class="mc-empty"><u-empty text="没有找到可核销会员卡" mode="search" /></view>
        <view v-if="candidates.length" class="mc-section-head">
            <view class="mc-section-head__main"><text class="mc-section-head__title">查询结果</text><text class="mc-section-head__sub">{{ candidates.length }} 位客户</text></view>
        </view>

        <view v-for="candidate in candidates" :key="candidate.member_id" class="candidate mc-surface">
            <view class="candidate-head">
                <view class="candidate-avatar">{{ String(candidate.holder_name || '客').slice(0, 1) }}</view>
                <view class="candidate-copy"><text>请口头核对姓名</text><strong>{{ candidate.holder_name }}</strong><small>{{ candidate.mobile_masked }}</small></view>
                <u-tag :text="`可用 ${candidate.available_card_count} 张`" :type="candidate.available_card_count ? 'success' : 'info'" plain plainFill size="mini" />
            </view>
            <view v-for="card in candidate.cards" :key="card.card_id" class="card" :class="{ disabled: !card.available }">
                <view class="card-icon"><u-icon name="order" :color="card.available ? '#2563eb' : '#94a3b8'" size="20" /></view>
                <view class="card-main">
                    <strong>{{ card.product_name }}</strong>
                    <text>{{ card.item_name }} · {{ card.validity_text }}</text>
                    <view class="remaining"><u-icon name="checkmark-circle" color="#16a34a" size="14" /><text>{{ card.usage_mode === 'unlimited' ? '不限次' : `剩余 ${card.remaining_times} 次` }}</text></view>
                </view>
                <view class="card-action">
                    <MemberCardButton type="primary" compact :disabled="!card.available" :text="card.recommended ? '核销 1 次' : '核销'" @click="openConfirm(candidate, card)" />
                </view>
            </view>
        </view>

        <u-popup :show="confirmVisible" mode="bottom" :safe-area-inset-bottom="true" round="20" @close="confirmVisible = false">
            <view v-if="selected" class="mc-sheet confirm-sheet">
                <view class="mc-sheet-head">
                    <view><text class="mc-sheet-title">确认核销 1 次</text><text class="mc-sheet-sub">核销后系统将扣减服务次数</text></view>
                    <u-icon name="close" color="#94a3b8" size="20" @click="confirmVisible = false" />
                </view>
                <view class="verify-card">
                    <view class="verify-card__icon"><u-icon name="account" color="#2563eb" size="22" /></view>
                    <view><text>请让客户说出购卡姓名</text><strong>{{ selected.candidate.holder_name }}</strong><small>{{ selected.candidate.mobile_masked }} · {{ selected.card.product_name }}</small></view>
                </view>
                <view class="check-row" @click="confirmed = !confirmed">
                    <u-checkbox :checked="confirmed" @change="confirmed = $event" />
                    <text>我已当面核对手机号与姓名，信息一致</text>
                </view>
                <u-textarea v-model="remark" height="100rpx" placeholder="本次服务说明（选填）" />
                <view class="mc-actions">
                    <MemberCardButton text="取消" @click="confirmVisible = false" />
                    <MemberCardButton type="primary" icon="checkmark" text="确认核销" :disabled="!confirmed" :loading="redeeming" @click="redeem" />
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { memberCardRequestId, redeemMemberCard, searchMemberCards } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const mobile = ref('')
const name = ref('')
const loading = ref(false)
const searched = ref(false)
const candidates = ref<any[]>([])
const confirmVisible = ref(false)
const selected = ref<any>(null)
const confirmed = ref(false)
const remark = ref('')
const redeeming = ref(false)

const search = async () => {
    if (!/^\d{4}$|^1\d{10}$/.test(mobile.value)) return uni.showToast({ title: '请输入完整手机号或后四位', icon: 'none' })
    loading.value = true
    try {
        candidates.value = ((await searchMemberCards({ mobile_keyword: mobile.value, name: name.value })) as any)?.data?.candidates || []
        searched.value = true
    } finally {
        loading.value = false
    }
}
const openConfirm = (candidate: any, card: any) => {
    selected.value = { candidate, card }
    confirmed.value = false
    remark.value = ''
    confirmVisible.value = true
}
const redeem = async () => {
    if (!confirmed.value) return
    const modal = await uni.showModal({ title: '核销确认', content: '确认贴膜服务已经完成？核销后将扣减 1 次并记录当前操作人。', confirmText: '确认核销' })
    if (!modal.confirm) return
    redeeming.value = true
    try {
        const result: any = (await redeemMemberCard(selected.value.card.card_id, {
            request_id: memberCardRequestId('redeem'), card_item_id: selected.value.card.item_id, verification_confirmed: 1, remark: remark.value,
        }))?.data
        uni.showToast({ title: result?.message || '核销成功', icon: 'success' })
        confirmVisible.value = false
        await search()
    } finally {
        redeeming.value = false
    }
}
</script>

<style scoped lang="scss">
@import '../../styles/member-card-mobile.scss';

.search-panel { padding: 26rpx; }
.search-panel__head { display: flex; margin-bottom: 20rpx; align-items: center; gap: 15rpx; }
.search-panel__head > view:last-child { display: flex; flex-direction: column; gap: 5rpx; }
.search-title { font-size: 31rpx; font-weight: 750; }
.search-sub { color: #64748b; font-size: 21rpx; }
.input-wrap { margin-bottom: 14rpx; padding: 0 14rpx; border: 1rpx solid #dfe6ef; border-radius: 14rpx; background: #f8fafc; }
.safe-tip { display: flex; margin-top: 15rpx; align-items: center; gap: 8rpx; color: #64748b; font-size: 20rpx; }
.candidate { margin-bottom: 18rpx; overflow: hidden; }
.candidate-head { display: flex; padding: 22rpx; align-items: center; gap: 14rpx; background: #f8fafc; }
.candidate-avatar { display: flex; width: 62rpx; height: 62rpx; flex: 0 0 62rpx; align-items: center; justify-content: center; border-radius: 50%; background: #dbeafe; color: #2563eb; font-size: 26rpx; font-weight: 700; }
.candidate-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 4rpx; }
.candidate-copy text, .candidate-copy small, .card-main > text { color: #64748b; font-size: 20rpx; }
.candidate-copy strong { font-size: 28rpx; }
.card { display: flex; padding: 22rpx; align-items: center; gap: 14rpx; border-top: 1rpx solid #edf1f6; }
.card.disabled { background: #f8fafc; opacity: .55; }
.card-icon { display: flex; width: 52rpx; height: 52rpx; flex: 0 0 52rpx; align-items: center; justify-content: center; border-radius: 13rpx; background: #eff6ff; }
.card-main { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 6rpx; }
.remaining { display: flex; align-items: center; gap: 6rpx; color: #16a34a; }
.remaining text { font-size: 21rpx; }
.card-action { width: 150rpx; flex: 0 0 150rpx; }
.verify-card { display: flex; padding: 22rpx; align-items: center; gap: 16rpx; border: 1rpx solid #bfdbfe; border-radius: 16rpx; background: #eff6ff; }
.verify-card__icon { display: flex; width: 60rpx; height: 60rpx; align-items: center; justify-content: center; border-radius: 15rpx; background: #dbeafe; }
.verify-card > view:last-child { display: flex; min-width: 0; flex-direction: column; gap: 6rpx; }
.verify-card text, .verify-card small { color: #64748b; font-size: 21rpx; }
.verify-card strong { font-size: 34rpx; }
.check-row { display: flex; margin: 22rpx 0; align-items: center; gap: 10rpx; color: #334155; font-size: 23rpx; }
</style>
