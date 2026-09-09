<template>
    <view class="mc-page">
        <z-paging ref="paging" v-model="rows" @query="query">
            <template #top>
                <MemberCardListHeader
                    v-model="keyword"
                    :activeTab="status"
                    :tabs="tabs"
                    placeholder="姓名 / 手机号 / 订单号 / 卡种"
                    @search="reload"
                    @tab-change="changeTab"
                />
            </template>
            <template #empty>
                <MemberCardState text="暂无匹配的开卡订单" :error="listError" @action="reload" />
            </template>
            <view class="mc-content">
                <view v-for="row in rows" :key="row.id" class="mc-card mc-record-card" @click="open(row.id)">
                    <view class="mc-between">
                        <view class="mc-grow">
                            <view class="mc-title">{{ row.holder_name || '未命名客户' }}</view>
                            <view class="mc-sub">{{ phone(row.holder_mobile) }}</view>
                        </view>
                        <text class="mc-badge" :class="'mc-badge--' + financeTone(row.finance_status)">
                            {{ financeLabel(row.finance_status) }}
                        </text>
                    </view>
                    <view class="mc-record-body">
                        <view>{{ row.product_name }}</view>
                        <view class="mc-between" style="margin-top: 12rpx">
                            <text class="mc-money">¥{{ money(row.order_amount) }}</text>
                            <text class="mc-small">已收 ¥{{ money(row.paid_amount) }}</text>
                        </view>
                    </view>
                    <view
                        v-if="row.last_error || row.finance_status === 'failed'"
                        class="mc-sub"
                        style="color: #ac3939; margin-top: 16rpx"
                    >
                        收款处理未完成，点开查看原因与下一步
                    </view>
                    <view class="mc-record-foot">
                        <text class="mc-small">{{ dateTime(row.create_at) }} · {{ row.issuer_name || '—' }}</text>
                        <text class="mc-link">详情 ›</text>
                    </view>
                </view>
            </view>
            <template #bottom>
                <MemberCardActionBar>
                    <view class="mc-actionbar__summary mc-small">共 {{ total }} 笔订单</view>
                    <view class="mc-actionbar__button">
                        <MemberCardButton type="primary" icon="plus" text="给客户开卡" @click="goCreate" />
                    </view>
                </MemberCardActionBar>
            </template>
        </z-paging>
        <MemberCardOrderDetail
            :show="detailVisible"
            :orderId="orderId"
            @update:show="detailVisible = $event"
            @changed="refresh"
        />
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getCardOrders } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardListHeader from '../../components/MemberCardListHeader.vue'
import MemberCardState from '../../components/MemberCardState.vue'
import MemberCardActionBar from '../../components/MemberCardActionBar.vue'
import MemberCardOrderDetail from '../../components/MemberCardOrderDetail.vue'
import { useMemberCardList } from '../../hooks/useMemberCardList'
import { money, phone, dateTime, financeLabel, financeTone } from '../../utils/presentation'
const keyword = ref(''),
    status = ref(''),
    detailVisible = ref(false),
    orderId = ref(0)
const tabs = [
    { label: '全部', value: '' },
    { label: '待收款', value: 'pending' },
    { label: '部分收款', value: 'partial' },
    { label: '已结清', value: 'settled' },
    { label: '待处理', value: 'failed' },
    { label: '已退款', value: 'refunded' },
    { label: '已作废', value: 'void' }
]
const { paging, rows, total, listError, query, reload, refresh } = useMemberCardList(getCardOrders, () => ({
    keyword: keyword.value.trim(),
    finance_status: status.value
}))
const changeTab = (value: string) => {
    status.value = value
    reload()
}
const open = (id: number) => {
    orderId.value = Number(id)
    detailVisible.value = true
}
const goCreate = () => uni.navigateTo({ url: '/addon/hsx_member_card/pages/order/create' })
onLoad((options: any) => {
    if (tabs.some((tab) => tab.value === options?.finance_status)) status.value = options.finance_status
    if (Number(options?.order_id) > 0) open(Number(options.order_id))
})
</script>
<style lang="scss">
// 小程序从页面样式入口加载，避免脚本样式被当前页面的样式块覆盖。
// #ifndef H5
@import '../../styles/mobile.scss';
// #endif
</style>
