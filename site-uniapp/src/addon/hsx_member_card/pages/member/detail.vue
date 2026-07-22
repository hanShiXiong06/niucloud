<template>
    <view class="mc-page member-detail-page">
        <view class="profile-card mc-surface">
            <view class="profile-avatar">{{ String(member.display_name || '客').slice(0, 1) }}</view>
            <view class="profile-copy"><strong>{{ member.display_name || '会员' }}</strong><text>{{ member.mobile_masked || '—' }}</text><small>{{ member.member_no || '暂无会员号' }}</small></view>
            <view class="profile-action" @click="call"><u-icon name="phone" color="#2563eb" size="20" /></view>
        </view>
        <view class="profile-metrics mc-surface">
            <view><strong>{{ member.card_count || 0 }}</strong><text>全部卡</text></view>
            <view><strong class="green">{{ member.available_card_count || 0 }}</strong><text>可用卡</text></view>
            <view><strong>{{ redemptions.length }}</strong><text>核销记录</text></view>
        </view>

        <view class="detail-tabs mc-surface">
            <view :class="{ active: tab === 'cards' }" @click="tab = 'cards'">会员卡</view>
            <view :class="{ active: tab === 'records' }" @click="tab = 'records'">消费记录</view>
        </view>

        <view v-if="tab === 'cards'">
            <view v-for="card in cards" :key="card.id" class="owned-card mc-surface">
                <view class="owned-card__head"><view><strong>{{ card.product_name }}</strong><text>{{ card.card_no }}</text></view><u-tag :text="card.status_text" :type="cardType(card.status)" plain plainFill size="mini" /></view>
                <view v-for="item in card.items || []" :key="item.id" class="benefit-row">
                    <view><strong>{{ item.item_name }}</strong><text>{{ card.validity_text }}</text></view>
                    <view class="remaining"><strong>{{ item.usage_mode === 'unlimited' ? '不限次' : item.remaining_times }}</strong><text>{{ item.usage_mode === 'unlimited' ? '使用次数' : `共 ${item.granted_times} 次` }}</text></view>
                </view>
                <view class="owned-card__meta"><text>开卡人 {{ card.issuer_name || '—' }}</text><text>{{ time(card.create_at) }}</text></view>
            </view>
            <view v-if="!cards.length" class="mc-empty"><u-empty text="该会员暂未购卡" mode="list" /></view>
        </view>

        <view v-else>
            <view v-for="row in redemptions" :key="row.id" class="record-card mc-surface">
                <view class="record-icon" :class="{ muted: row.status !== 'success' }"><u-icon :name="row.status === 'success' ? 'checkmark-circle' : 'reload'" :color="row.status === 'success' ? '#16a34a' : '#64748b'" size="19" /></view>
                <view class="record-copy"><strong>{{ row.item_name }}</strong><text>{{ row.status === 'success' ? `核销 1 次 · 剩余 ${row.after_remaining} 次` : '本次核销已冲正' }}</text><small>{{ row.operator_name || '—' }} · {{ time(row.occurred_at) }}</small></view>
                <text class="record-amount">¥{{ money(row.recognized_amount) }}</text>
            </view>
            <view v-if="!redemptions.length" class="mc-empty"><u-empty text="暂无消费记录" mode="list" /></view>
        </view>

        <view class="detail-bottom">
            <view><MemberCardButton plain type="primary" icon="order" text="购买次卡" @click="buy" /></view>
            <view><MemberCardButton type="primary" icon="checkmark" text="次卡核销" @click="redeem" /></view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getCardMember } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const id = ref(0)
const tab = ref('cards')
const data = ref<any>({})
const member = computed(() => data.value.member || {})
const cards = computed(() => data.value.cards || [])
const redemptions = computed(() => data.value.redemptions || [])
const load = async () => { if (id.value) data.value = ((await getCardMember(id.value)) as any)?.data || {} }
const money = (value: any) => Number(value || 0).toFixed(2)
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }).slice(0, 16) : '—'
const cardType = (status: string) => ({ active: 'success', pending: 'primary', frozen: 'warning' }[status] || 'info') as any
const call = () => member.value.mobile && uni.makePhoneCall({ phoneNumber: String(member.value.mobile) })
const buy = () => uni.navigateTo({ url: `/addon/hsx_member_card/pages/order/create?member_id=${id.value}` })
const redeem = () => uni.navigateTo({ url: `/addon/hsx_member_card/pages/card/search?mobile=${encodeURIComponent(member.value.mobile || '')}` })
onLoad((options: any) => { id.value = Number(options?.id || 0) })
onShow(load)
</script>

<style scoped lang="scss">
@import '../../styles/member-card-mobile.scss';
.member-detail-page { padding-bottom: 150rpx; }
.profile-card { display: flex; padding: 24rpx; align-items: center; gap: 15rpx; }
.profile-avatar { display: flex; width: 72rpx; height: 72rpx; flex: 0 0 72rpx; align-items: center; justify-content: center; border-radius: 50%; background: #dbeafe; color: #2563eb; font-size: 29rpx; font-weight: 700; }
.profile-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 5rpx; }.profile-copy strong { font-size: 30rpx; }.profile-copy text { color: #475569; font-size: 23rpx; }.profile-copy small { color: #94a3b8; font-size: 19rpx; }
.profile-action { display: flex; width: 62rpx; height: 62rpx; align-items: center; justify-content: center; border-radius: 16rpx; background: #eff6ff; }
.profile-metrics { display: grid; grid-template-columns: repeat(3, 1fr); margin-top: 16rpx; }.profile-metrics view { display: flex; padding: 19rpx 10rpx; flex-direction: column; align-items: center; gap: 5rpx; }.profile-metrics view + view { border-left: 1rpx solid #edf1f6; }.profile-metrics strong { font-size: 29rpx; }.profile-metrics strong.green { color: #16a34a; }.profile-metrics text { color: #94a3b8; font-size: 19rpx; }
.detail-tabs { display: grid; grid-template-columns: repeat(2, 1fr); margin: 20rpx 0 16rpx; overflow: hidden; }.detail-tabs view { position: relative; padding: 23rpx; color: #64748b; text-align: center; font-size: 25rpx; }.detail-tabs view.active { color: #2563eb; font-weight: 700; }.detail-tabs view.active::after { position: absolute; right: 36%; bottom: 0; left: 36%; height: 5rpx; border-radius: 3rpx; background: #2563eb; content: ''; }
.owned-card { margin-bottom: 16rpx; padding: 22rpx; }.owned-card__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 15rpx; }.owned-card__head > view { display: flex; min-width: 0; flex-direction: column; gap: 5rpx; }.owned-card__head strong { font-size: 28rpx; }.owned-card__head text { color: #94a3b8; font-size: 19rpx; }
.benefit-row { display: flex; margin-top: 18rpx; padding: 18rpx; align-items: center; justify-content: space-between; gap: 18rpx; border-radius: 14rpx; background: #f8fafc; }.benefit-row > view:first-child { display: flex; min-width: 0; flex-direction: column; gap: 6rpx; }.benefit-row text { color: #64748b; font-size: 20rpx; }.remaining { display: flex; flex: 0 0 auto; flex-direction: column; align-items: flex-end; gap: 3rpx; }.remaining strong { color: #2563eb; font-size: 30rpx; }
.owned-card__meta { display: flex; margin-top: 16rpx; justify-content: space-between; gap: 15rpx; color: #94a3b8; font-size: 19rpx; }
.record-card { display: flex; margin-bottom: 14rpx; padding: 21rpx; align-items: center; gap: 13rpx; }.record-icon { display: flex; width: 52rpx; height: 52rpx; flex: 0 0 52rpx; align-items: center; justify-content: center; border-radius: 13rpx; background: #ecfdf3; }.record-icon.muted { background: #f1f5f9; }.record-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 5rpx; }.record-copy strong { font-size: 26rpx; }.record-copy text { color: #475569; font-size: 21rpx; }.record-copy small { color: #94a3b8; font-size: 18rpx; }.record-amount { color: #16a34a; font-size: 24rpx; font-weight: 700; }
.detail-bottom { position: fixed; z-index: 20; right: 0; bottom: 0; left: 0; display: grid; grid-template-columns: 1fr 1.25fr; gap: 14rpx; padding: 16rpx 24rpx calc(16rpx + env(safe-area-inset-bottom)); border-top: 1rpx solid #e6ebf2; background: rgba(255,255,255,.97); }
</style>
