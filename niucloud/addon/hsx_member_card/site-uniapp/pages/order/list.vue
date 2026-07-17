<template>
    <view class="list-page">
        <view class="fixed-head">
            <view class="search-row"><u-search v-model="keyword" placeholder="姓名 / 手机号 / 订单号 / 卡种" :showAction="false" bgColor="#f1f5f9" @search="reload" @clear="reload" /></view>
            <view class="mc-tabs"><u-tabs :list="tabList" :current="currentTab" lineColor="#2563eb" :lineWidth="24" :activeStyle="tabActiveStyle" :inactiveStyle="tabInactiveStyle" :itemStyle="tabItemStyle" @click="changeTab" /></view>
        </view>
        <z-paging ref="paging" v-model="rows" :fixed="true" :paging-style="pagingStyle" @query="query">
            <template #empty><view class="mc-empty"><u-empty text="暂无开卡订单" mode="list" /></view></template>
            <view class="mc-list-wrap">
                <view v-for="row in rows" :key="row.id" class="order-card mc-surface">
                    <view class="order-head">
                        <view class="customer-avatar">{{ String(row.holder_name || '客').slice(0, 1) }}</view>
                        <view class="customer-copy"><strong>{{ row.holder_name }}</strong><text>{{ mask(row.holder_mobile) }}</text></view>
                        <u-tag :text="financeText(row.finance_status)" :type="financeType(row.finance_status)" plain plainFill size="mini" />
                    </view>
                    <view class="product-row">
                        <view class="product-icon"><u-icon name="order" color="#2563eb" size="19" /></view>
                        <view><text>{{ row.product_name }}</text><small>开卡金额</small></view>
                        <strong>¥{{ money(row.order_amount) }}</strong>
                    </view>
                    <view class="order-meta">
                        <view class="mc-meta-line"><u-icon name="file-text" color="#94a3b8" size="14" /><text>{{ row.order_no }}</text></view>
                        <view class="mc-meta-line"><u-icon name="account" color="#94a3b8" size="14" /><text>开单 {{ row.issuer_name || '—' }}</text></view>
                        <view class="mc-meta-line"><u-icon name="clock" color="#94a3b8" size="14" /><text>{{ time(row.create_at) }}</text></view>
                    </view>
                    <view v-if="row.last_error" class="error-tip"><u-icon name="warning" color="#dc2626" size="15" /><text>{{ row.last_error }}</text></view>
                </view>
            </view>
        </z-paging>
        <view class="fab fab--label" @click="goCreate"><u-icon name="plus" color="#ffffff" size="20" /><text>快速开卡</text></view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getCardOrders } from '../../api'

const keyword = ref('')
const status = ref('')
const rows = ref<any[]>([])
const paging = ref<any>()
const tabs = [
    { name: '全部', value: '' }, { name: '待收款', value: 'pending' }, { name: '部分收款', value: 'partial' },
    { name: '已结清', value: 'settled' }, { name: '失败', value: 'failed' },
]
const tabList = tabs.map(item => ({ name: item.name }))
const currentTab = computed(() => Math.max(0, tabs.findIndex(item => item.value === status.value)))
const pagingStyle = computed(() => ({ top: '178rpx', bottom: '0' }))
const tabActiveStyle = { color: '#2563eb', fontWeight: '700', fontSize: '26rpx' }
const tabInactiveStyle = { color: '#64748b', fontSize: '26rpx' }
const tabItemStyle = 'padding-left: 22rpx; padding-right: 22rpx; height: 78rpx;'
const reload = () => paging.value?.reload()
const changeTab = (item: any) => { status.value = tabs[item.index]?.value || ''; reload() }
const query = async (page: number, limit: number) => {
    try {
        const result: any = await getCardOrders({ keyword: keyword.value, finance_status: status.value, page, limit })
        paging.value?.complete(result?.data?.data || [])
    } catch { paging.value?.complete(false) }
}
const money = (value: any) => Number(value || 0).toFixed(2)
const mask = (value: string) => /^\d{11}$/.test(value || '') ? `${value.slice(0, 3)}****${value.slice(-4)}` : (value || '—')
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }).slice(0, 16) : '—'
const financeText = (value: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', failed: '处理失败', refunded: '已退款' }[value] || value)
const financeType = (value: string) => ({ settled: 'success', failed: 'error', partial: 'warning', pending: 'warning' }[value] || 'info') as any
const goCreate = () => uni.navigateTo({ url: '/addon/hsx_member_card/pages/order/create' })
onShow(reload)
</script>

<style scoped lang="scss">
@import '../../styles/member-card-mobile.scss';
.list-page { min-height: 100vh; background: #f5f7fb; }
.fixed-head { position: fixed; z-index: 10; top: 0; right: 0; left: 0; border-bottom: 1rpx solid #e6ebf2; background: #fff; }
.search-row { padding: 16rpx 24rpx 0; }
.order-card { margin-bottom: 16rpx; padding: 22rpx; }
.order-head { display: flex; align-items: center; gap: 13rpx; }
.customer-avatar { display: flex; width: 58rpx; height: 58rpx; flex: 0 0 58rpx; align-items: center; justify-content: center; border-radius: 50%; background: #dbeafe; color: #2563eb; font-weight: 700; }
.customer-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 4rpx; }
.customer-copy strong { font-size: 27rpx; }
.customer-copy text { color: #64748b; font-size: 21rpx; }
.product-row { display: flex; margin: 18rpx 0; padding: 17rpx 0; align-items: center; gap: 13rpx; border-top: 1rpx solid #edf1f6; border-bottom: 1rpx solid #edf1f6; }
.product-icon { display: flex; width: 50rpx; height: 50rpx; align-items: center; justify-content: center; border-radius: 12rpx; background: #eff6ff; }
.product-row > view:nth-child(2) { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 5rpx; }
.product-row small { color: #94a3b8; font-size: 20rpx; }
.product-row strong { color: #ea580c; font-size: 28rpx; }
.order-meta { display: grid; gap: 9rpx; }
.error-tip { display: flex; margin-top: 14rpx; padding: 13rpx 15rpx; align-items: flex-start; gap: 8rpx; border-radius: 11rpx; background: #fef2f2; color: #dc2626; font-size: 21rpx; }
.fab { position: fixed; z-index: 12; right: 28rpx; bottom: 44rpx; display: flex; height: 82rpx; padding: 0 27rpx; align-items: center; justify-content: center; gap: 9rpx; border-radius: 41rpx; background: #2563eb; color: #fff; box-shadow: 0 12rpx 28rpx rgba(37, 99, 235, .24); }
.fab text { font-size: 24rpx; font-weight: 650; }
</style>
