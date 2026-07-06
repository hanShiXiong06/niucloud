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
                <view class="filter-chip" :class="{ active: filterWarehouseName }" @click="showWarehouseFilter = true">
                    <u-icon name="home" size="14" :color="filterWarehouseName ? '#3b6ef5' : '#64748b'" />
                    <text>{{ warehouseFilterText || '仓库' }}</text>
                </view>
                <view class="filter-chip" :class="{ active: categoryId }">
                    <CategoryPopup v-model="categoryId" @change="onCategoryChange" />
                </view>
                <view v-if="hasFilter" class="filter-clear" @click="clearFilters">清空</view>
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
                            <text class="stock-item__model">{{ row.model || '-' }}</text>
                            <text class="stock-item__cost">成本 ¥{{ money(row.total_cost) }}</text>
                        </view>
                        <text class="stock-item__sub">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</text>
                        <text class="stock-item__sub" v-if="row.category_name">分类：{{ row.category_name }}</text>
                        <text class="stock-item__sub">{{ row.warehouse_name }}{{ row.location_name ? ' / '+row.location_name : '' }}</text>
                        <text class="stock-item__sub" v-if="row.party_name">来源：{{ row.party_name }}</text>
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
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import CategoryPopup from '@/addon/phone_shop/components/category-popup.vue'

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
const categoryId = ref<any>('')
const categoryPath = ref<any[]>([])
const hasFilter = computed(() => Number(filterWarehouseId.value || 0) > 0 || Number(categoryId.value || 0) > 0)
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
            category_id: categoryId.value || 0,
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

function onCategoryChange(payload: any) {
    categoryId.value = payload?.category_id || ''
    categoryPath.value = payload?.category_path || []
    search()
}

function clearFilters() {
    filterWarehouseId.value = 0
    filterWarehouseName.value = ''
    filterLocationId.value = 0
    filterLocationName.value = ''
    categoryId.value = ''
    categoryPath.value = []
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
.filter-row { display:flex; align-items:center; gap:12rpx; padding:0 24rpx 12rpx; overflow-x:auto; white-space:nowrap; }
.filter-row::-webkit-scrollbar { display:none; }
.filter-chip { min-height:56rpx; display:flex; align-items:center; gap:8rpx; padding:0 18rpx; border-radius:28rpx; background:#f8fafc; border:1rpx solid #e2e8f0; color:#64748b; font-size:24rpx; flex-shrink:0; }
.filter-chip.active { color:#3b6ef5; background:#eff6ff; border-color:#bfdbfe; }
.filter-chip :deep(.field) { min-height:54rpx; }
.filter-chip :deep(.label) { display:none; }
.filter-chip :deep(.value) { justify-content:center; font-size:24rpx; color:inherit; }
.filter-chip :deep(.value--ph) { color:#64748b; }
.filter-clear { font-size:24rpx; color:#ef4444; padding:0 8rpx; flex-shrink:0; }
.scan-hint { padding: 0 32rpx 12rpx; }
.scan-hint__text { font-size: 24rpx; color: #94a3b8; }
.popup-list { flex: 1; overflow-y: auto; padding: 0 24rpx; box-sizing: border-box; }
.popup-loading { display: flex; justify-content: center; padding: 48rpx; }
.popup-loading-more { display: flex; justify-content: center; padding: 20rpx; }
.popup-empty { padding: 32rpx 0; }
.stock-item {
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f1f5f9;
    &:active { background: #f8fafc; }
}
.stock-item__head { display: flex; justify-content: space-between; margin-bottom: 6rpx; }
.stock-item__model { font-size: 28rpx; font-weight: 600; color: #0f172a; }
.stock-item__cost { font-size: 26rpx; color: #2563eb; }
.stock-item__sub { font-size: 24rpx; color: #64748b; display: block; margin-top: 4rpx; }
</style>
