<template>
    <view class="cost-list-page">
        <ErpListHeader
            v-model="keyword"
            placeholder="型号 / IMEI / SN"
            :show-scan="true"
            :compact-mp="true"
            @search="reload"
        >
            <template #below>
                <ErpQuickFilterBar :items="quickFilters" @change="onQuickFilter" />
            </template>
        </ErpListHeader>
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
                    :class="{ 'asset-card--disabled': !canAdjust(row) }"
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
                    <view class="asset-meta">IMEI {{ row.imei || '未录入' }}</view>
                    <view class="asset-meta" v-if="row.warehouse_name">
                        仓库 {{ row.warehouse_name }}<text v-if="row.location_name"> / {{ row.location_name }}</text>
                    </view>
                    <view class="asset-time">{{ erpTimeLine(row, ['stock_in_at', 'occurred_at']) }}</view>
                    <view class="asset-card__foot">
                        <view class="cost-box">
                            <text class="cost-label">当前成本</text>
                            <text class="cost-value">¥{{ formatMoney(row.current_cost) }}</text>
                        </view>
                        <view v-if="canAdjust(row)" class="adjust-entry">调整<text class="adjust-arrow">›</text></view>
                        <view v-else class="adjust-entry adjust-entry--disabled">不可调整</view>
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
import { dictLabel, dictTabs, dictType, ERP_DICT_FALLBACK, isCostAdjustAllowed, loadErpDicts, type ErpDictMap } from '@/addon/hsx_erp/api/dict'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine } from '@/addon/hsx_erp/hooks/useErpTime'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpQuickFilterBar from '@/addon/hsx_erp/components/ErpQuickFilterBar.vue'
import { showErpError } from '@/addon/hsx_erp/utils/error'


const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const erpDicts = ref<ErpDictMap>(ERP_DICT_FALLBACK)
const { pagingStyle } = useListHeader({ tabs: true, compactMp: true, h5TopRpx: 178 })
// 详情页调整成本后返回需要刷新
const dirty = ref(false)

const statusTabs = computed(() => dictTabs(erpDicts.value, 'asset_status', true))
const activeTab = ref('')
const refurbishStatus = ref('')
const listingStatus = ref('')
const status = computed(() => activeTab.value)
const quickFilters = computed(() => [
    { key: 'status', label: '设备状态', title: '设备状态', value: activeTab.value, options: statusTabs.value },
    { key: 'refurbish_status', label: '整备状态', title: '整备状态', value: refurbishStatus.value, options: dictTabs(erpDicts.value, 'refurbish_status', true) },
    { key: 'listing_status', label: '上架状态', title: '上架状态', value: listingStatus.value, options: dictTabs(erpDicts.value, 'listing_status', true) },
])

const statusLabel = (s: string) => dictLabel(erpDicts.value, 'asset_status', s)
const statusType = (s: string) => dictType(erpDicts.value, 'asset_status', s)
const formatMoney = (v: any) => Number(v || 0).toFixed(2)
const canAdjust = (row: any) => isCostAdjustAllowed(row?.inventory_status)

const reload = () => pagingRef.value?.reload()

const onTabClick = (value: string) => {
    activeTab.value = value
    reload()
}
const onQuickFilter = ({ key, value }: { key: string; value: string | number }) => {
    if (key === 'status') return onTabClick(String(value))
    if (key === 'refurbish_status') refurbishStatus.value = String(value)
    if (key === 'listing_status') listingStatus.value = String(value)
    reload()
}

// z-paging 查询：自动管理下拉刷新 / 上拉加载
const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getErpAssetList({
            keyword: keyword.value,
            inventory_status: status.value,
            refurbish_status: refurbishStatus.value,
            listing_status: listingStatus.value,
            page: pageNo,
            limit: pageSize
        })
        const data = res?.data || {}
        pagingRef.value?.complete(data.list || data.data || [])
    } catch (e) {
        pagingRef.value?.complete(false)
        showErpError(e, '成本调整列表加载失败，请检查网络后重试')
    }
}

const goAdjust = (row: any) => {
    if (!canAdjust(row)) {
        uni.showToast({ title: `当前状态「${statusLabel(row?.inventory_status)}」不可调整成本`, icon: 'none' })
        return
    }
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
    loadErpDicts().then((dicts) => {
        erpDicts.value = dicts
        if (!statusTabs.value.some(item => item.value === activeTab.value)) activeTab.value = ''
    })
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

.asset-card--disabled {
    opacity: 0.78;
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
.asset-time {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #94a3b8;
    line-height: 1.45;
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
.adjust-entry--disabled {
    color: #94a3b8;
}
</style>
