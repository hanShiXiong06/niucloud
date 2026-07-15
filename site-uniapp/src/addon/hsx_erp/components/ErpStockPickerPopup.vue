<!--
  ErpStockPickerPopup - 待售库存选择弹窗（对齐PC端 erp/sale/stock 接口）

  用法：
    <ErpStockPickerPopup
      v-model:show="showStockPicker"
      :excludeIds="selectedAssets.map(a => a.id)"
      :scanTrigger="stockPickerScanTrigger"
      @select="onAssetSelected"   // 返回单台设备 { id, model, imei, ... }
    />
-->
<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="popup-wrap">
            <view class="popup-header">
                <text class="popup-title">选择库存设备</text>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>

            <view class="popup-search">
                <u-search
                    v-model="keyword"
                    placeholder="型号 / IMEI / 资产号 / 来源"
                    :showAction="false"
                    bgColor="#f1f5f9"
                    height="34"
                    @search="search"
                    @clear="search"
                />
                <view class="popup-scan" @click="scanAndSearch">
                    <u-icon name="scan" color="#3b6ef5" size="21" />
                </view>
            </view>

            <view class="filter-row">
                <view class="filter-chip filter-chip--warehouse" :class="{ active: filterWarehouseName }" @click="showWarehouseFilter = true">
                    <u-icon name="home" size="14" :color="filterWarehouseName ? '#3b6ef5' : '#64748b'" />
                    <text>{{ warehouseFilterText || '仓库' }}</text>
                </view>
                <view class="filter-chip filter-chip--catalog" :class="{ active: catalogProductId }">
                    <ErpCatalogProductPopup
                        v-model="catalogProductId"
                        :selected-label="catalogProductName"
                        label="型号"
                        placeholder="商品型号"
                        layout="chip"
                        :required="false"
                        :clearable="true"
                        @change="onCatalogProductChange"
                    />
                </view>
                <view v-if="hasFilter" class="filter-clear" @click="clearFilters">
                    <u-icon name="reload" color="#64748b" size="14" />
                    <text>重置</text>
                </view>
            </view>

            <!-- 扫码提示 -->
            <view class="scan-hint" v-if="!keyword">
                <text class="scan-hint__text">支持扫描 IMEI 快速定位设备</text>
            </view>

            <scroll-view scroll-y class="popup-list" @scrolltolower="loadMore">
                <view v-if="loading && !list.length" class="popup-loading">
                    <u-loading-icon size="24" />
                </view>
                <template v-else>
                    <view
                        v-for="row in list"
                        :key="row.id"
                        class="stock-item"
                        @click="select(row)"
                    >
                        <view class="stock-item__head">
                            <view class="stock-title">
                                <text class="stock-item__model">{{ row.model || '-' }}</text>
                                <text class="stock-item__spec">{{ row.spec || '无规格' }}</text>
                            </view>
                            <view class="stock-pick">
                                <text>选择</text>
                                <u-icon name="arrow-right" color="#3b6ef5" size="15" />
                            </view>
                        </view>
                        <view class="stock-ident">
                            <text class="ident-label">IMEI</text>
                            <text class="ident-value">{{ row.imei || '-' }}</text>
                        </view>
                        <view class="stock-tags">
                            <view class="stock-tag">
                                <u-icon name="home" color="#64748b" size="12" />
                                <text>{{ row.warehouse_name || '-' }}{{ row.location_name ? ' / ' + row.location_name : '' }}</text>
                            </view>
                            <!-- <view v-if="row.catalog_product_name || row.category_name" class="stock-tag">
                                <u-icon name="grid" color="#64748b" size="12" />
                                <text>{{ row.catalog_product_name || row.category_name }}</text>
                            </view> -->
                            <view v-if="row.party_name" class="stock-tag muted">
                                <text>来源 {{ row.party_name }}</text>
                            </view>
                        </view>
                        <view class="stock-money">
                            <view class="money-box">
                                <text class="money-label">成本</text>
                                <text class="money-value">¥{{ money(row.total_cost) }}</text>
                            </view>
                            <view class="money-box">
                                <text class="money-label">建议售价</text>
                                <text class="money-value blue">¥{{ money(suggestPrice(row)) }}</text>
                            </view>
                            <view class="money-box">
                                <text class="money-label">预估毛利</text>
                                <text class="money-value" :class="suggestProfit(row) >= 0 ? 'green' : 'red'">¥{{ money(suggestProfit(row)) }}</text>
                            </view>
                        </view>
                    </view>
                    <view v-if="!list.length && !loading" class="popup-empty">
                        <u-empty mode="search" text="暂无待售设备" :image-size="60" />
                    </view>
                    <view v-if="loading && list.length" class="popup-loading-more">
                        <u-loading-icon size="20" />
                    </view>
                </template>
            </scroll-view>
        </view>
        <ErpWarehousePopup
            v-model:show="showWarehouseFilter"
            v-model:warehouse-id="filterWarehouseId"
            v-model:warehouse-name="filterWarehouseName"
            v-model:location-id="filterLocationId"
            v-model:location-name="filterLocationName"
            @change="search"
        />
    </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import request from '@/utils/request'
import { scanErpCode } from '@/addon/hsx_erp/hooks/useErpScan'
import { firstPositiveErpAmount } from '@/addon/hsx_erp/hooks/useErpAmounts'
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import ErpCatalogProductPopup from '@/addon/hsx_erp/components/ErpCatalogProductPopup.vue'

const props = withDefaults(defineProps<{
    show: boolean
    excludeIds?: number[]
    scanTrigger?: number
}>(), {
    show: false,
    excludeIds: () => [],
    scanTrigger: 0,
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'select', v: any): void
}>()

const keyword = ref('')
const list = ref<any[]>([])
const loading = ref(false)
const page = ref(1)
const hasMore = ref(true)
const showWarehouseFilter = ref(false)
const filterWarehouseId = ref(0)
const filterWarehouseName = ref('')
const filterLocationId = ref(0)
const filterLocationName = ref('')
const catalogProductId = ref<any>('')
const catalogProductName = ref('')
const hasFilter = computed(() => Number(filterWarehouseId.value || 0) > 0 || Number(catalogProductId.value || 0) > 0)
const warehouseFilterText = computed(() => {
    if (!filterWarehouseName.value) return ''
    return filterLocationName.value ? `${filterWarehouseName.value} / ${filterLocationName.value}` : filterWarehouseName.value
})

watch(() => props.show, (v) => {
    if (v) { keyword.value = ''; page.value = 1; list.value = []; hasMore.value = true; search() }
})
watch(() => props.scanTrigger, async () => {
    if (props.show) await scanAndSearch()
})

async function search() {
    page.value = 1
    list.value = []
    hasMore.value = true
    await loadPage()
}

async function loadMore() {
    if (!hasMore.value || loading.value) return
    page.value++
    await loadPage()
}

async function loadPage() {
    loading.value = true
    try {
        // 使用 PC 端同款接口：erp/sale/stock（只返回整备完成的在库设备）
        const res: any = await request.get('erp/sale/stock', {
            keyword: keyword.value,
            warehouse_id: filterWarehouseId.value || 0,
            location_id: filterLocationId.value || 0,
            catalog_product_id: catalogProductId.value || 0,
            page: page.value,
            limit: 15,
        })
        const data = res?.data?.data || []
        // 过滤掉已选的
        const excludeSet = new Set(props.excludeIds)
        const filtered = data.filter((row: any) => !excludeSet.has(row.id))
        if (page.value === 1) {
            list.value = filtered
        } else {
            list.value.push(...filtered)
        }
        hasMore.value = data.length >= 15
    } catch {
        hasMore.value = false
    } finally { loading.value = false }
}

function onCatalogProductChange(payload: any) {
    catalogProductId.value = payload?.catalog_product_id || payload?.site_product_id || ''
    catalogProductName.value = payload?.product_name || payload?.label || ''
    search()
}

function clearFilters() {
    filterWarehouseId.value = 0
    filterWarehouseName.value = ''
    filterLocationId.value = 0
    filterLocationName.value = ''
    catalogProductId.value = ''
    catalogProductName.value = ''
    search()
}

async function scanAndSearch() {
    try {
        keyword.value = await scanErpCode()
        await search()
        const exact = list.value.find((row: any) => isScanMatch(row, keyword.value))
        if (exact) {
            select(exact)
        } else if (list.value.length === 1) {
            select(list.value[0])
        } else if (!list.value.length) {
            uni.showToast({ title: '未找到可销售设备', icon: 'none' })
        }
    } catch (e: any) {
        if (e?.errMsg?.includes('cancel')) return
        uni.showToast({ title: e?.message || '扫码失败', icon: 'none' })
    }
}

function isScanMatch(row: any, code: string) {
    const value = String(code || '').trim()
    return ['imei', 'sn', 'asset_no'].some(key => String(row?.[key] || '').trim() === value)
}

function select(row: any) {
    emit('select', row)
    close()
}

function close() { emit('update:show', false) }
const money = (v: any) => Number(v || 0).toFixed(2)
function suggestPrice(row: any) {
    return firstPositiveErpAmount(row.retail_price, row.estimate_sale_price, row.sale_price, row.total_cost)
}
function suggestProfit(row: any) {
    return suggestPrice(row) - Number(row.total_cost || 0)
}
</script>

<style scoped lang="scss">
.popup-wrap { height: 80vh; display: flex; flex-direction: column; }
.popup-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 28rpx 32rpx 16rpx;
}
.popup-title { font-size: 32rpx; font-weight: 700; color: #0f172a; }
.popup-search { padding: 0 24rpx 12rpx; display: flex; align-items: center; gap: 12rpx; }
.popup-search :deep(.u-search) { flex: 1; }
.popup-scan { width: 68rpx; height: 68rpx; border-radius: 50%; background: #eff3ff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.filter-row { display:flex; align-items:center; gap:10rpx; min-width:0; padding:0 24rpx 12rpx; overflow:hidden; }
.filter-chip { min-width:0; min-height:56rpx; display:flex; align-items:center; gap:8rpx; padding:0 16rpx; border-radius:28rpx; background:#f8fafc; border:1rpx solid #e2e8f0; color:#64748b; font-size:23rpx; box-sizing:border-box; }
.filter-chip.active { color:#3b6ef5; background:#eff6ff; border-color:#bfdbfe; }
.filter-chip--warehouse { max-width:42%; flex:0 1 auto; }
.filter-chip--warehouse text { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.filter-chip--catalog { flex:1; padding-right:10rpx; overflow:hidden; }
.filter-chip :deep(.field) { width:100%; min-width:0; min-height:54rpx; }
.filter-chip :deep(.label) { color:inherit; }
.filter-chip :deep(.value) { color:inherit; }
.filter-chip :deep(.value--ph) { color:#64748b; }
.filter-clear { min-height:56rpx; display:flex; align-items:center; gap:4rpx; color:#64748b; font-size:21rpx; flex-shrink:0; }
.scan-hint { padding: 0 32rpx 12rpx; }
.scan-hint__text { font-size: 24rpx; color: #94a3b8; }
.popup-list { flex: 1; overflow-y: auto; padding: 0 24rpx; box-sizing: border-box; }
.popup-loading { display: flex; justify-content: center; padding: 48rpx; }
.popup-loading-more { display: flex; justify-content: center; padding: 20rpx; }
.popup-empty { padding: 32rpx 0; }
.stock-item { padding: 18rpx 20rpx; margin-bottom: 14rpx; border-radius: 16rpx; background: #fff; border: 2rpx solid #eef2f7; box-shadow: 0 2rpx 10rpx rgba(15, 23, 42, .035); &:active { background: #f8fbff; border-color: #bfdbfe; } }
.stock-item__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14rpx; }
.stock-title { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4rpx; }
.stock-item__model { font-size: 28rpx; font-weight: 700; color: #0f172a; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.stock-item__spec { font-size: 22rpx; color: #64748b; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.stock-pick { height: 44rpx; padding: 0 14rpx; border-radius: 22rpx; background: #eff6ff; color: #3b6ef5; display: flex; align-items: center; gap: 4rpx; font-size: 22rpx; font-weight: 600; flex-shrink: 0; }
.stock-ident { margin-top: 10rpx; display: flex; align-items: center; gap: 8rpx; min-width: 0; }
.ident-label { font-size: 20rpx; color: #94a3b8; flex-shrink: 0; }
.ident-value { font-size: 23rpx; color: #334155; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.stock-tags { display: flex; flex-wrap: wrap; gap: 8rpx; margin-top: 10rpx; }
.stock-tag { max-width: 100%; min-height: 38rpx; padding: 0 10rpx; border-radius: 19rpx; background: #f8fafc; color: #64748b; display: flex; align-items: center; gap: 6rpx; font-size: 21rpx; box-sizing: border-box; }
.stock-tag text { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.stock-tag.muted { background: #f1f5f9; color: #94a3b8; }
.stock-money { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8rpx; margin-top: 12rpx; padding-top: 10rpx; border-top: 2rpx solid #f1f5f9; }
.money-box { min-width: 0; display: flex; flex-direction: column; gap: 4rpx; }
.money-label { font-size: 20rpx; color: #94a3b8; }
.money-value { font-size: 24rpx; font-weight: 700; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.money-value.blue { color: #2563eb; }
.money-value.green { color: #16a34a; }
.money-value.red { color: #dc2626; }
</style>
