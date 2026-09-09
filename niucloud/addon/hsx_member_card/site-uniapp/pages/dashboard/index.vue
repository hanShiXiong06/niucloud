<template>
    <view class="mc-page">
        <view class="mc-content">
            <view class="mc-workbench-head">
                <view>
                    <view class="mc-workbench-head__label">门店经营</view>
                    <view class="mc-workbench-head__title">会员服务</view>
                </view>
                <view class="mc-workbench-head__date">{{ todayLabel }}</view>
            </view>
            <view class="mc-workbench-hero">
                <view class="mc-workbench-hero__caption">日常业务</view>
                <view class="mc-workbench-actions">
                    <view
                        class="mc-workbench-action"
                        hover-class="mc-workbench-action--pressed"
                        @click="go('order/create')"
                    >
                        <view class="mc-workbench-action__icon"
                            ><u-icon name="plus-circle" color="#fff" size="25"
                        /></view>
                        <view class="mc-workbench-action__title">给客户开卡</view>
                        <view class="mc-workbench-action__desc">选择权益 · 登记收款</view>
                    </view>
                    <view
                        class="mc-workbench-action"
                        hover-class="mc-workbench-action--pressed"
                        @click="go('card/search')"
                    >
                        <view class="mc-workbench-action__icon"><u-icon name="scan" color="#fff" size="25" /></view>
                        <view class="mc-workbench-action__title">核销服务</view>
                        <view class="mc-workbench-action__desc">查找会员 · 确认服务</view>
                    </view>
                </view>
            </view>
            <view class="mc-card mc-overview">
                <view class="mc-between">
                    <view class="mc-grow">
                        <MemberCardSegmented v-model="statMode" :options="modes" />
                    </view>
                    <view class="mc-refresh" @click="load">
                        <u-loading-icon v-if="loading" size="18" />
                        <u-icon v-else name="reload" color="#65758b" size="20" />
                        <text>刷新</text>
                    </view>
                </view>
                <u-tabs
                    :key="calendarShow ? 'calendar-open' : period"
                    :list="periods"
                    keyName="label"
                    :current="periods.findIndex((item) => item.value === period)"
                    :scrollable="false"
                    :lineWidth="16"
                    :lineHeight="3"
                    lineColor="#2868ce"
                    :activeStyle="{ color: '#1c3556', fontWeight: '600', fontSize: '13px' }"
                    :inactiveStyle="{ color: '#758196', fontSize: '13px' }"
                    :itemStyle="{ padding: '0 6px', height: '46px' }"
                    @change="periodChanged"
                />
                <view v-if="period === 'custom'" class="mc-link" @click="calendarShow = true">
                    {{ customRangeText }}
                </view>
                <MemberCardState v-if="error" :error="error" action="重新加载" @action="load" />
                <view v-else class="mc-metrics">
                    <view v-for="item in metrics" :key="item.key" class="mc-stat">
                        <view class="mc-small">{{ item.label }}</view>
                        <view class="mc-stat__value">
                            {{
                                loading || !overview
                                    ? '—'
                                    : item.currency
                                      ? '¥' + money(overview[item.key])
                                      : quantity(overview[item.key])
                            }}
                        </view>
                    </view>
                </view>
                <MemberCardCollapse title="数据口径与说明">
                    <view v-for="item in metrics" :key="item.key" class="mc-detail-row"
                        ><text>{{ item.label }}</text
                        ><text>{{ item.help }}</text></view
                    >
                    <view class="mc-sub">
                        开卡总额、实收和待收按所选期间创建的订单统计；实收为这些订单的累计收款，不是本期账户流水。核销和退款按发生时间统计。有效卡是当前数量；确认收入不等于利润。
                    </view>
                </MemberCardCollapse>
            </view>
            <view class="mc-section-heading">业务记录</view>
            <view class="mc-card mc-card--flat">
                <u-cell-group :border="false">
                    <u-cell
                        v-for="(entry, index) in records"
                        :key="entry.path"
                        :title="entry.label"
                        :label="entry.help"
                        :border="index < records.length - 1"
                        isLink
                        center
                        :titleStyle="{ fontSize: '15px', fontWeight: '500', color: '#25364e' }"
                        @click="go(entry.path)"
                    >
                        <template #icon
                            ><view class="mc-cell-icon"><u-icon :name="entry.icon" color="#3e68a1" size="22" /></view
                        ></template>
                    </u-cell>
                </u-cell-group>
            </view>
            <view class="mc-section-heading">常用管理</view>
            <view class="mc-card mc-card--flat">
                <u-grid :col="3" :border="false">
                    <u-grid-item v-for="entry in manageActions" :key="entry.path" @click="go(entry.path)">
                        <view class="mc-manage-icon"><u-icon :name="entry.icon" color="#4f6380" size="24" /></view>
                        <text class="mc-manage-label">{{ entry.label }}</text>
                    </u-grid-item>
                </u-grid>
            </view>
            <u-calendar
                :show="calendarShow"
                mode="range"
                title="选择统计日期"
                :defaultDate="customDates"
                :monthNum="12"
                @confirm="onCalendarConfirm"
                @close="calendarShow = false"
            />
        </view>
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { computed, ref } from 'vue'
import { onShow, onUnload } from '@dcloudio/uni-app'
import { getMemberCardDashboard } from '../../api'
import MemberCardSegmented from '../../components/MemberCardSegmented.vue'
import MemberCardState from '../../components/MemberCardState.vue'
import MemberCardCollapse from '../../components/MemberCardCollapse.vue'
import { money, quantity, errorText } from '../../utils/presentation'
const overview = ref<any>(null)
const today = new Date()
const todayLabel = `${today.getMonth() + 1}月${today.getDate()}日`
const error = ref('')
const loading = ref(false)
const statMode = ref('sale')
const period = ref('today')
const calendarShow = ref(false)
const customDates = ref<string[]>([])
const modes = [
    { label: '开卡数据', value: 'sale' },
    { label: '核销数据', value: 'redeem' }
]
const periods = [
    { label: '今日', value: 'today' },
    { label: '昨日', value: 'yesterday' },
    { label: '本月', value: 'month' },
    { label: '上月', value: 'last_month' },
    { label: '自定义', value: 'custom' }
]
const manageActions = [
    { label: '会员管理', icon: 'account', path: 'member/list' },
    { label: '卡种设置', icon: 'order', path: 'product/list' },
    { label: '收款与耗材', icon: 'setting', path: 'config/payment' }
]
const records = [
    { label: '开卡订单', help: '查看收款进度、处理异常订单', icon: 'order', path: 'order/list' },
    { label: '核销记录', help: '查看服务明细，处理误核销', icon: 'checkmark-circle', path: 'redemption/list' }
]
const metrics = computed(() =>
    statMode.value === 'sale'
        ? [
              { key: 'card_sale_amount', label: '开卡总额', currency: true, help: '本期开卡金额' },
              { key: 'actual_received_amount', label: '实际收款', currency: true, help: '本期开卡订单累计实收' },
              { key: 'pending_receivable_amount', label: '待收款', currency: true, help: '所选期间开卡待收金额' },
              { key: 'active_card_count', label: '有效会员卡', currency: false, help: '当前数量 · 张' }
          ]
        : [
              { key: 'redemption_count', label: '核销次数', currency: false, help: '本期成功核销 · 次' },
              { key: 'recognized_amount', label: '确认收入', currency: true, help: '按卡权益规则确认' },
              { key: 'refund_amount', label: '退款金额', currency: true, help: '本期已完成退款' },
              { key: 'active_card_count', label: '有效会员卡', currency: false, help: '当前数量 · 张' }
          ]
)
const customRangeText = computed(() => customDates.value.join(' 至 '))
const dayStart = (date: Date) =>
    Math.floor(new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime() / 1000)
const dateTimestamp = (value: string, end = false) => {
    const [y, m, d] = value.split('-').map(Number)
    return Math.floor(new Date(y, m - 1, d, end ? 23 : 0, end ? 59 : 0, end ? 59 : 0).getTime() / 1000)
}
const range = () => {
    const now = new Date()
    if (period.value === 'yesterday') {
        const start = dayStart(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1))
        return [start, start + 86399]
    }
    if (period.value === 'month')
        return [
            Math.floor(new Date(now.getFullYear(), now.getMonth(), 1).getTime() / 1000),
            Math.floor(now.getTime() / 1000)
        ]
    if (period.value === 'last_month')
        return [
            Math.floor(new Date(now.getFullYear(), now.getMonth() - 1, 1).getTime() / 1000),
            Math.floor(new Date(now.getFullYear(), now.getMonth(), 1).getTime() / 1000) - 1
        ]
    if (period.value === 'custom' && customDates.value.length)
        return [dateTimestamp(customDates.value[0]), dateTimestamp(customDates.value[1], true)]
    return [dayStart(now), Math.floor(now.getTime() / 1000)]
}
let sequence = 0
const load = async () => {
    const ticket = ++sequence
    loading.value = true
    error.value = ''
    try {
        const [start_at, end_at] = range()
        const result: any = await getMemberCardDashboard({ start_at, end_at })
        if (ticket === sequence) overview.value = result?.data || null
    } catch (e) {
        if (ticket === sequence) error.value = errorText(e, '数据加载失败，请重试')
    } finally {
        if (ticket === sequence) loading.value = false
    }
}
const switchPeriod = (value: string) => {
    if (value === 'custom') {
        calendarShow.value = true
        return
    }
    period.value = value
    void load()
}
const periodChanged = (item: { index: number }) => {
    const option = periods[item.index]
    if (option) switchPeriod(option.value)
}
const onCalendarConfirm = (selected: string[]) => {
    const dates = (selected || []).filter(Boolean)
    calendarShow.value = false
    if (!dates.length) return
    customDates.value = [dates[0], dates[dates.length - 1]]
    period.value = 'custom'
    void load()
}
const go = (path: string) => uni.navigateTo({ url: '/addon/hsx_member_card/pages/' + path })
onShow(load)
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
