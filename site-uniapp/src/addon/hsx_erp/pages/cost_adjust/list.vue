<template>
    <view class="cost-list-page">
        <RecyclePageHeader title="成本调整" />
        <view class="page-header">
                    
                    <u-search
                        v-model="keyword"
                        placeholder="型号 / 资产号 / IMEI / SN"
                        :showAction="false"
                        bgColor="#f1f5f9"
                        height="34"
                        :customStyle="{ marginTop: '16rpx' }"
                        @search="reload"
                        @clear="reload"
                    ></u-search>
                    <u-tabs
                        :list="statusTabs"
                        :current="currentIndex"
                        lineColor="#3b6ef5"
                        :activeStyle="{ color: '#0f172a', fontWeight: '600' }"
                        :inactiveStyle="{ color: '#64748b' }"
                        lineWidth="40"
                        :customStyle="{ marginTop: '8rpx' }"
                        @click="onTabClick"
                    ></u-tabs>
                </view>     
        <z-paging
            ref="pagingRef"
            v-model="list"
            @query="queryList"
            :fixed="true"
            :auto="true"
            :default-page-size="15"
            :paging-style="pagingStyle"
        >
            <!-- 顶部：说明 + 搜索 + 状态分段（z-paging 自动吸顶、内容下移） -->
            <template #top>
                 
            </template>

            <template #empty>
                <u-empty mode="list" text="没有匹配的设备" icon="https://cdn.uviewui.com/uview/empty/list.png"></u-empty>
            </template>

            <view class="list-content">
                <view
                    v-for="row in list"
                    :key="row.id"
                    class="asset-card"
                    @click="goAdjust(row)"
                >
                    <view class="asset-card__head">
                        <text class="asset-title">{{ row.model || '未命名机型' }}</text>
                        <u-tag
                            :text="statusLabel(row.inventory_status)"
                            :type="statusType(row.inventory_status)"
                            plain
                            plainFill
                            size="mini"
                        ></u-tag>
                    </view>
                    <view class="asset-meta">资产 {{ row.asset_no || '-' }} · IMEI {{ row.imei || '-' }}</view>
                    <view class="asset-meta" v-if="row.warehouse_name">
                        仓库 {{ row.warehouse_name }}<text v-if="row.location_name"> / {{ row.location_name }}</text>
                    </view>
                    <view class="asset-card__foot">
                        <view class="cost-box">
                            <text class="cost-label">当前成本</text>
                            <text class="cost-value">¥{{ formatMoney(row.current_cost) }}</text>
                        </view>
                        <view class="adjust-entry">调整<text class="adjust-arrow">›</text></view>
                    </view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getErpAssetList } from '@/addon/hsx_erp/api/asset'
import { INVENTORY_STATUS_MAP } from '@/addon/hsx_erp/api/dict'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
// 胶囊筛选行高度上调，让设备列表落在胶囊按钮下方、留出间距
const { pageHeaderStyle, pagingStyle } = useListHeader(104)
// 详情页调整成本后返回需要刷新
const dirty = ref(false)

const statusTabs = [
    { name: '在库可调', value: 'onhand' },
    { name: '已售订正', value: 'sold' },
    { name: '全部', value: '' }
]
const currentIndex = ref(0)
const status = computed(() => statusTabs[currentIndex.value].value)

const statusLabel = (s: string) => INVENTORY_STATUS_MAP[s] || s || '-'
const statusType = (s: string) => {
    if (s === 'in_stock' || s === 'available_for_sale') return 'success'
    if (s === 'outbound' || s === 'locked') return 'primary'
    if (s === 'lost' || s === 'inbound_rejected') return 'error'
    return 'warning'
}
const formatMoney = (v: any) => Number(v || 0).toFixed(2)

const reload = () => pagingRef.value?.reload()

const onTabClick = (item: any) => {
    if (currentIndex.value === item.index) return
    currentIndex.value = item.index
    reload()
}

// z-paging 查询：自动管理下拉刷新 / 上拉加载
const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getErpAssetList({
            keyword: keyword.value,
            inventory_status: status.value,
            page: pageNo,
            limit: pageSize
        })
        const data = res?.data || {}
        pagingRef.value?.complete(data.list || data.data || [])
    } catch (e) {
        pagingRef.value?.complete(false)
    }
}

const goAdjust = (row: any) => {
    dirty.value = true
    // 直接把行数据带到详情页：秒开、无需再请求详情接口
    const q = [
        `id=${ row.id }`,
        `model=${ encodeURIComponent(row.model || '') }`,
        `asset_no=${ encodeURIComponent(row.asset_no || '') }`,
        `imei=${ encodeURIComponent(row.imei || '') }`,
        `status=${ encodeURIComponent(row.inventory_status || '') }`,
        `cost=${ row.current_cost ?? '' }`,
        `wh=${ encodeURIComponent(row.warehouse_name || '') }`,
        `loc=${ encodeURIComponent(row.location_name || '') }`
    ].join('&')
    uni.navigateTo({ url: `/addon/hsx_erp/pages/cost_adjust/detail?${ q }` })
}

onShow(() => {
    if (dirty.value) {
        dirty.value = false
        reload()
    }
})
</script>

<style lang="scss" scoped>
.cost-list-page {
    min-height: 100vh;
    background: #f6f7fb;
}

.page-header {
    padding: 20rpx 24rpx 8rpx;
    background: #fff;
    box-shadow: 0 2rpx 12rpx rgba(15, 23, 42, 0.04);

    .header-tip {
        font-size: 22rpx;
        color: #94a3b8;
        line-height: 1.5;
    }
}

.list-content {
    padding: 20rpx 24rpx 40rpx;
}

.asset-card {
    background: #fff;
    border-radius: 20rpx;
    padding: 28rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);

    &__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16rpx;
    }
    &__foot {
        margin-top: 22rpx;
        padding-top: 22rpx;
        border-top: 1rpx solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
}

.asset-title {
    font-size: 32rpx;
    font-weight: 600;
    color: #0f172a;
    flex: 1;
}
.asset-meta {
    margin-top: 10rpx;
    font-size: 24rpx;
    color: #94a3b8;
}

.cost-box {
    display: flex;
    flex-direction: column;

    .cost-label { font-size: 22rpx; color: #94a3b8; }
    .cost-value {
        margin-top: 4rpx;
        font-size: 38rpx;
        font-weight: 700;
        color: #0f172a;
    }
}
.adjust-entry {
    font-size: 26rpx;
    color: #3b6ef5;
    font-weight: 500;
    display: flex;
    align-items: center;

    .adjust-arrow { font-size: 32rpx; margin-left: 6rpx; }
}
</style>
