<template>
    <view class="mc-page mc-page--action">
        <view class="mc-content">
            <MemberCardState
                v-if="loading || error"
                :loading="loading"
                :error="error"
                action="重新加载"
                @action="load"
            />
            <template v-else>
                <view class="mc-card">
                    <view class="mc-row">
                        <view class="mc-avatar">{{ String(member.display_name || '客').slice(0, 1) }}</view>
                        <view class="mc-grow">
                            <view class="mc-title">{{ member.display_name || '会员' }}</view>
                            <view class="mc-sub">{{ member.mobile_masked || '未留手机号' }}</view>
                        </view>
                        <view v-if="member.mobile" class="mc-link" @click="call">联系客户</view>
                    </view>
                    <view class="mc-summary mc-grid">
                        <view>
                            <view class="mc-small">全部会员卡</view>
                            <view class="mc-money">{{ member.card_count || 0 }}</view>
                        </view>
                        <view>
                            <view class="mc-small">有效 / 待激活</view>
                            <view class="mc-money">{{ member.available_card_count || 0 }}</view>
                        </view>
                    </view>
                    <MemberCardCollapse title="会员资料">
                        <view class="mc-detail-row">
                            <text>会员号</text>
                            <text selectable @click="copyText(member.member_no)">{{ member.member_no || '—' }}</text>
                        </view>
                        <view class="mc-detail-row">
                            <text>手机号</text>
                            <text selectable @click="copyText(member.mobile)">{{ member.mobile || '—' }}</text>
                        </view>
                    </MemberCardCollapse>
                </view>
                <view class="mc-tabs">
                    <view class="mc-tab" :class="{ 'mc-tab--on': tab === 'cards' }" @click="tab = 'cards'">会员卡</view>
                    <view class="mc-tab" :class="{ 'mc-tab--on': tab === 'records' }" @click="tab = 'records'">
                        最近服务
                    </view>
                </view>
                <template v-if="tab === 'cards'">
                    <view v-for="card in cards" :key="card.id" class="mc-card">
                        <view class="mc-between">
                            <text class="mc-title mc-grow">{{ card.product_name }}</text>
                            <text class="mc-badge" :class="card.status === 'active' ? 'mc-badge--success' : ''">
                                {{ card.status_text || '状态待确认' }}
                            </text>
                        </view>
                        <view class="mc-sub">{{ card.validity_text }}</view>
                        <view v-for="item in card.items || []" :key="item.id" class="mc-summary">
                            <view class="mc-between">
                                <text class="mc-grow">{{ item.item_name }}</text>
                                <text>
                                    {{
                                        item.usage_mode === 'unlimited'
                                            ? '不限次'
                                            : '剩余 ' + item.remaining_times + ' 次'
                                    }}
                                </text>
                            </view>
                            <view v-if="item.bound_imei || item.bound_model" class="mc-sub">
                                {{
                                    item.bound_imei ? '绑定 IMEI：' + item.bound_imei : '适用型号：' + item.bound_model
                                }}
                            </view>
                        </view>
                        <MemberCardCollapse title="开卡信息">
                            <view class="mc-detail-row">
                                <text>卡号</text>
                                <text selectable @click="copyText(card.card_no)">{{ card.card_no }}</text>
                            </view>
                            <view class="mc-detail-row">
                                <text>开卡人</text>
                                <text>{{ card.issuer_name || '—' }}</text>
                            </view>
                            <view class="mc-detail-row">
                                <text>开卡时间</text>
                                <text>{{ dateTime(card.create_at) }}</text>
                            </view>
                            <view v-if="card.order_id" class="mc-link" @click="viewOrder(card.order_id)">
                                查看开卡订单 ›
                            </view>
                        </MemberCardCollapse>
                    </view>
                    <MemberCardState v-if="!cards.length" text="该会员暂未购卡" />
                </template>
                <template v-else>
                    <view class="mc-small" style="margin-bottom: 16rpx">
                        展示最近 {{ redemptions.length }} 条记录，最多 100 条
                    </view>
                    <view v-for="row in redemptions" :key="row.id" class="mc-card">
                        <view class="mc-between">
                            <text class="mc-title mc-grow">{{ row.item_name }}</text>
                            <text class="mc-badge" :class="row.status === 'success' ? 'mc-badge--success' : ''">
                                {{
                                    row.status === 'success'
                                        ? '核销成功'
                                        : row.status === 'reversed'
                                          ? '已冲正'
                                          : '状态待确认'
                                }}
                            </text>
                        </view>
                        <view class="mc-sub">
                            本次{{ row.status === 'reversed' ? '撤回' : '确认' }}收入 ¥{{
                                money(row.recognized_amount)
                            }}
                        </view>
                        <view class="mc-sub">{{ row.operator_name || '—' }} · {{ dateTime(row.occurred_at) }}</view>
                    </view>
                    <MemberCardState v-if="!redemptions.length" text="暂无服务记录" />
                </template>
            </template>
        </view>
        <MemberCardActionBar v-if="!loading && !error" fixed>
            <view class="mc-actionbar__button"><MemberCardButton text="为客户开卡" @click="buy" /></view>
            <view class="mc-actionbar__button">
                <MemberCardButton type="primary" text="核销服务" @click="redeem" />
            </view>
        </MemberCardActionBar>
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { computed, ref } from 'vue'
import { onLoad, onShow, onUnload } from '@dcloudio/uni-app'
import { getCardMember } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardState from '../../components/MemberCardState.vue'
import MemberCardActionBar from '../../components/MemberCardActionBar.vue'
import MemberCardCollapse from '../../components/MemberCardCollapse.vue'
import { money, dateTime, copyText, errorText, memberCardVersion } from '../../utils/presentation'
const id = ref(0),
    tab = ref('cards'),
    data = ref<any>({}),
    loading = ref(true),
    error = ref('')
const member = computed(() => data.value.member || {})
const cards = computed(() => data.value.cards || [])
const redemptions = computed(() => data.value.redemptions || [])
let seen = memberCardVersion(),
    initialized = false,
    sequence = 0
const load = async () => {
    if (!id.value) {
        loading.value = false
        error.value = '未指定会员，请返回会员列表重新选择'
        return
    }
    const ticket = ++sequence
    loading.value = true
    error.value = ''
    try {
        const result: any = await getCardMember(id.value)
        if (ticket !== sequence) return
        if (!result?.data?.member?.member_id) throw new Error('missing member')
        data.value = result.data
        seen = memberCardVersion()
        initialized = true
    } catch (e) {
        if (ticket === sequence) error.value = errorText(e, '会员资料加载失败，请重试')
    } finally {
        if (ticket === sequence) loading.value = false
    }
}
const call = () => {
    if (member.value.mobile) uni.makePhoneCall({ phoneNumber: String(member.value.mobile) })
}
const buy = () => uni.navigateTo({ url: '/addon/hsx_member_card/pages/order/create?member_id=' + id.value })
const redeem = () =>
    uni.navigateTo({
        url: '/addon/hsx_member_card/pages/card/search?mobile=' + encodeURIComponent(member.value.mobile || '')
    })
const viewOrder = (orderId: number) =>
    uni.navigateTo({ url: '/addon/hsx_member_card/pages/order/list?order_id=' + orderId })
onLoad((options: any) => {
    id.value = Number(options?.id || 0)
    void load()
})
onShow(() => {
    if (initialized && seen !== memberCardVersion()) void load()
})
onUnload(() => {
    sequence++
})
</script>
<style lang="scss">
// 小程序从页面样式入口加载，避免脚本样式被当前页面的样式块覆盖。
// #ifndef H5
@import '../../styles/mobile.scss';
// #endif
</style>
