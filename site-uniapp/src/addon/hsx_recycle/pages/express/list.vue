<template>
    <view class="page-wrap">
        <view class="fixed left-0 top-0 right-0 z-10 bg-[#fff]">
            <view class="px-[20rpx] py-[16rpx]">
                <view class="search-box">
                    <input
                        v-model="keyword"
                        class="flex-1 text-[26rpx]"
                        maxlength="50"
                        placeholder="搜索运单号、订单号、姓名、手机号"
                        confirm-type="search"
                        @confirm="reloadList"
                    />
                    <text v-if="keyword" class="nc-iconfont nc-icon-cuohaoV6xx1 text-[24rpx] text-[#999] mr-[8rpx]" @click="keyword = ''; reloadList()"></text>
                    <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 text-[26rpx]" @click="reloadList()"></text>
                </view>
            </view>
            <scroll-view scroll-x class="border-b border-[#f0f0f0]">
                <view class="flex whitespace-nowrap px-[20rpx] py-[16rpx]">
                    <view
                        v-for="item in statusTabs"
                        :key="item.value"
                        class="mr-[28rpx] text-[24rpx]"
                        :style="currentStatus === item.value ? 'color: var(--hsx-primary); font-weight: 700;' : 'color: #666;'"
                        @click="switchStatus(item.value)"
                    >
                        {{ item.label }}
                    </view>
                </view>
            </scroll-view>
        </view>

        <z-paging
            ref="pagingRef"
            v-model="list"
            :auto="true"
            :fixed="true"
            :default-page-size="10"
            top="156rpx"
            @query="queryList"
        >
            <view class="px-[20rpx] pt-[20rpx]">
                <view v-if="stats" class="stats-card">
                    <view class="stats-item">
                        <text class="stats-label">运单数</text>
                        <text class="stats-value">{{ stats.total_count || 0 }}</text>
                    </view>
                    <view class="stats-item">
                        <text class="stats-label">预估费用</text>
                        <text class="stats-value">¥{{ formatMoney(stats.total_estimated_cost) }}</text>
                    </view>
                    <view class="stats-item">
                        <text class="stats-label">实际费用</text>
                        <text class="stats-value">¥{{ formatMoney(stats.total_actual_cost) }}</text>
                    </view>
                </view>

                <view
                    v-for="item in list"
                    :key="item.id"
                    class="card-item"
                    @click="toDetail(item.id)"
                >
                    <view class="flex items-center justify-between">
                        <view class="text-[28rpx] font-bold text-[#222]">{{ item.delivery_id || item.order_no || '未生成运单号' }}</view>
                        <text class="text-[24rpx]" :class="getStatusClass(item.order_status)">{{ item.status_text || '-' }}</text>
                    </view>
                    <view class="mt-[12rpx] text-[24rpx] text-[#666] leading-[38rpx]">
                        <view>平台订单：{{ item.order_no || '-' }}</view>
                        <view>快递产品：{{ item.product_name || '-' }}</view>
                        <view>寄件人：{{ item.sender_name || '-' }} {{ item.sender_mobile || '' }}</view>
                        <view>收件人：{{ item.receiver_name || '-' }} {{ item.receiver_mobile || '' }}</view>
                    </view>
                    <view class="amount-grid">
                        <view class="amount-item">
                            <text class="amount-label">预估重量</text>
                            <text class="amount-value">{{ Number(item.estimated_weight || 0) }}kg</text>
                        </view>
                        <view class="amount-item">
                            <text class="amount-label">实际重量</text>
                            <text class="amount-value">{{ Number(item.actual_weight || 0) }}kg</text>
                        </view>
                        <view class="amount-item">
                            <text class="amount-label">预估费用</text>
                            <text class="amount-value">¥{{ formatMoney(item.estimated_cost) }}</text>
                        </view>
                        <view class="amount-item">
                            <text class="amount-label">实际费用</text>
                            <text class="amount-value">¥{{ formatMoney(item.actual_cost) }}</text>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { redirect } from '@/utils/common'
import { getExpressOrderRecordList, getExpressOrderStatistics, getExpressOrderStatusOptions } from '@/addon/hsx_recycle/api/express'
import { formatMoney } from '@/addon/hsx_recycle/utils/helper'
import { useRecyclePaging } from '@/addon/hsx_recycle/hooks/useRecyclePaging'

const { pagingRef, list, reload, complete } = useRecyclePaging()
const stats = ref<any>(null)
const keyword = ref('')
const currentStatus = ref('')
const initialized = ref(false)
const statusTabs = ref<Array<{ label: string, value: string }>>([{ label: '全部', value: '' }])

const loadStatusTabs = async () => {
    try {
        const res: any = await getExpressOrderStatusOptions()
        const rows = Array.isArray(res?.data) ? res.data : []
        statusTabs.value = [
            { label: '全部', value: '' },
            ...rows.map((item: any) => ({
                label: item?.label || item?.name || item?.title || String(item?.value || ''),
                value: String(item?.value ?? '')
            }))
        ]
    } catch (error) {
        statusTabs.value = [{ label: '全部', value: '' }]
    }
}

const loadStats = async () => {
    const res: any = await getExpressOrderStatistics({})
    stats.value = res.data || null
}

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getExpressOrderRecordList({
            page: pageNo,
            limit: pageSize,
            keyword: keyword.value,
            order_status: currentStatus.value
        })
        complete(res.data?.data || [])
    } catch (e) {
        complete(false)
    }
}

const reloadList = () => reload()

const switchStatus = (value: string) => {
    currentStatus.value = value
    reloadList()
}

const toDetail = (id: number | string) => {
    redirect({ url: '/addon/hsx_recycle/pages/express/detail', param: { id } })
}

const getStatusClass = (status: string) => {
    if (status === 'delivered') return 'text-[#18a058]'
    if (status === 'cancelled') return 'text-[#909399]'
    if (status === 'exception') return 'text-[#d03050]'
    if (status === 'in_transit') return 'text-[#f0a020]'
    return 'text-[var(--hsx-primary)]'
}

onShow(async () => {
    await loadStatusTabs()
    loadStats()
    if (initialized.value) {
        reload()
        return
    }
    initialized.value = true
})
</script>

<style scoped lang="scss">
.page-wrap {
    min-height: 100vh;
    background: #f5f7fa;
}

.search-box {
    height: 64rpx;
    background: #f5f7fa;
    border-radius: 32rpx;
    display: flex;
    align-items: center;
    padding: 0 20rpx;
}

.stats-card,
.card-item {
    background: #fff;
    border-radius: 18rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.stats-card {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16rpx;
}

.stats-item {
    display: flex;
    flex-direction: column;
    gap: 8rpx;
}

.stats-label,
.amount-label {
    font-size: 22rpx;
    color: #8a8f99;
}

.stats-value,
.amount-value {
    font-size: 26rpx;
    font-weight: 600;
    color: #222;
}

.amount-grid {
    margin-top: 16rpx;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16rpx;
    background: #f8fafc;
    border-radius: 16rpx;
    padding: 18rpx;
}

.amount-item {
    display: flex;
    flex-direction: column;
    gap: 8rpx;
}
</style>
