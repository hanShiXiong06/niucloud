<!--
  ErpWarehousePopup - 仓库 + 库位两步选择弹窗

  用法：
    <ErpWarehousePopup
      v-model:show="showWhPicker"
      v-model:warehouse-id="form.warehouse_id"
      v-model:warehouse-name="form.warehouse_name"
      v-model:location-id="form.location_id"
      v-model:location-name="form.location_name"
      @change="onWarehouseChange"
    />
-->
<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="popup-wrap">
            <view class="popup-header">
                <view>
                    <text class="popup-title">选择仓库</text>
                    <text class="popup-subtitle">{{ allowWarehouseOnly ? '可查看整个仓库，也可精确到库位' : '先选仓库，再选库位' }}</text>
                </view>
                <view class="popup-header__actions">
                    <text v-if="allowClear && warehouseId" class="popup-clear" @click="clearSelection">不限仓库</text>
                    <u-icon name="close" size="20" color="#94a3b8" @click="close" />
                </view>
            </view>

            <view v-if="loading" class="popup-loading"><u-loading-icon size="24" /></view>
            <view v-else-if="!warehouses.length" class="popup-empty">暂无仓库，请先在基础设置中创建</view>
            <view v-else class="warehouse-tree">
                <scroll-view scroll-y class="warehouse-pane">
                    <view
                        v-for="wh in warehouses"
                        :key="wh.id"
                        class="warehouse-row"
                        :class="{ active: wh.id === activeWarehouseId }"
                        @click="selectWarehouse(wh)"
                    >
                        <view class="warehouse-row__main">
                            <text class="warehouse-row__name">{{ wh.warehouse_name }}</text>
                            <text class="warehouse-row__meta">
                                {{ typeLabel(wh.warehouse_type) || '仓库' }}
                                <text v-if="wh.locations?.length"> · {{ wh.locations.length }} 个库位</text>
                            </text>
                            <text class="warehouse-row__manager">负责人 {{ wh.manager_effective_name || '未设置' }}</text>
                        </view>
                        <u-icon v-if="wh.id === activeWarehouseId" name="arrow-right" size="15" color="#3b6ef5" />
                    </view>
                </scroll-view>

                <scroll-view scroll-y class="location-pane">
                    <view v-if="currentWarehouse" class="location-head">
                        <text class="location-head__title">{{ currentWarehouse.warehouse_name }}</text>
                        <text class="location-head__sub">{{ currentLocations.length ? '请选择库位' : '该仓库暂无库位' }}</text>
                    </view>

                    <view
                        v-if="currentWarehouse && allowWarehouseOnly"
                        class="location-row location-row--all"
                        :class="{ active: Number(locationId) === 0 && Number(warehouseId) === Number(currentWarehouse.id) }"
                        @click="selectWholeWarehouse"
                    >
                        <view class="location-row__main">
                            <text class="location-row__name">全部库位</text>
                            <text class="location-row__manager">查看该仓库内的全部设备</text>
                        </view>
                        <u-icon v-if="Number(locationId) === 0 && Number(warehouseId) === Number(currentWarehouse.id)" name="checkmark-circle-fill" color="#3b6ef5" size="20" />
                    </view>

                    <view
                        v-for="loc in currentLocations"
                        :key="loc.id"
                        class="location-row"
                        :class="{ active: loc.id === locationId }"
                        @click="selectLocation(loc)"
                    >
                        <view class="location-row__main">
                            <text class="location-row__name">{{ loc.location_name }}</text>
                            <text class="location-row__manager">
                                负责人 {{ loc.manager_effective_name || '未设置' }}
                                <text v-if="loc.manager_source === 'warehouse'"> · 继承仓库</text>
                            </text>
                        </view>
                        <u-icon v-if="loc.id === locationId" name="checkmark-circle-fill" color="#3b6ef5" size="20" />
                    </view>

                    <view v-if="currentWarehouse && !currentLocations.length" class="location-empty">
                        <u-empty mode="data" text="暂无库位" :image-size="56" />
                        <text class="location-empty__tip">请先给该仓库维护库位</text>
                    </view>
                </scroll-view>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import request from '@/utils/request'

const props = withDefaults(defineProps<{
    show: boolean
    warehouseId?: number
    warehouseName?: string
    locationId?: number
    locationName?: string
    allowWarehouseOnly?: boolean
    allowClear?: boolean
}>(), {
    show: false,
    warehouseId: 0,
    warehouseName: '',
    locationId: 0,
    locationName: '',
    allowWarehouseOnly: false,
    allowClear: false,
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'update:warehouseId', v: number): void
    (e: 'update:warehouseName', v: string): void
    (e: 'update:locationId', v: number): void
    (e: 'update:locationName', v: string): void
    (e: 'change', warehouse: any, location: any): void
}>()

const warehouses = ref<any[]>([])
const loading = ref(false)
const activeWarehouseId = ref(0)

const currentWarehouse = computed(() =>
    warehouses.value.find((item: any) => Number(item.id) === Number(activeWarehouseId.value)) || null
)

const currentLocations = computed(() =>
    (currentWarehouse.value?.locations || []).filter((l: any) => Number(l.status) === 1)
)

watch(() => props.show, (v) => { if (v) loadWarehouses() })

async function loadWarehouses() {
    loading.value = true
    try {
        const res: any = await request.get('erp/warehouse/options')
        warehouses.value = Array.isArray(res?.data) ? res.data : (res?.data?.data || [])
        const current = warehouses.value.find((item: any) => Number(item.id) === Number(props.warehouseId))
        activeWarehouseId.value = Number(current?.id || warehouses.value[0]?.id || 0)
    } catch {
        warehouses.value = []
    } finally {
        loading.value = false }
}

function selectWarehouse(wh: any) {
    activeWarehouseId.value = Number(wh.id || 0)
}

function selectLocation(loc: any) {
    const wh = currentWarehouse.value
    if (!wh) return
    emit('update:warehouseId', wh.id)
    emit('update:warehouseName', wh.warehouse_name)
    emit('update:locationId', loc.id)
    emit('update:locationName', loc.location_name)
    emit('change', wh, loc)
    close()
}

function selectWholeWarehouse() {
    const wh = currentWarehouse.value
    if (!wh) return
    emit('update:warehouseId', Number(wh.id || 0))
    emit('update:warehouseName', String(wh.warehouse_name || ''))
    emit('update:locationId', 0)
    emit('update:locationName', '')
    emit('change', wh, null)
    close()
}

function clearSelection() {
    emit('update:warehouseId', 0)
    emit('update:warehouseName', '')
    emit('update:locationId', 0)
    emit('update:locationName', '')
    emit('change', null, null)
    close()
}

function close() { emit('update:show', false) }

const typeLabel = (t: string) => ({
    owned: '自有仓',
    second_hand: '二手仓',
    peer: '同行仓',
    consignment: '代卖仓',
    exception: '异常仓',
    abnormal: '异常仓'
}[t] || t || '')
</script>

<style scoped lang="scss">
.popup-wrap { height: 65vh; display: flex; flex-direction: column; }
.popup-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 28rpx 32rpx 20rpx;
    border-bottom: 1rpx solid #f1f5f9;
}
.popup-title { font-size: 32rpx; font-weight: 700; color: #0f172a; display:block; }
.popup-subtitle { display:block; font-size:24rpx; color:#94a3b8; margin-top:4rpx; }
.popup-header__actions { display:flex; align-items:center; gap:24rpx; }
.popup-clear { color:#2563eb; font-size:24rpx; }
.popup-loading { display: flex; justify-content: center; padding: 48rpx; }
.popup-empty { text-align: center; padding: 48rpx 0; color: #94a3b8; font-size: 26rpx; }
.warehouse-tree {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 260rpx minmax(0, 1fr);
    background: #fff;
}
.warehouse-pane {
    height: 100%;
    background: #f8fafc;
    border-right: 1rpx solid #eef2f7;
}
.location-pane {
    height: 100%;
    background: #fff;
    padding: 0 24rpx 32rpx;
    box-sizing: border-box;
}
.warehouse-row {
    min-height: 126rpx;
    padding: 18rpx 18rpx 18rpx 24rpx;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8rpx;
    color: #64748b;
    border-left: 6rpx solid transparent;
}
.warehouse-row.active {
    background: #fff;
    color: #0f172a;
    border-left-color: #3b6ef5;
}
.warehouse-row__main { min-width:0; flex:1; }
.warehouse-row__name {
    display:block;
    font-size: 27rpx;
    font-weight: 600;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.warehouse-row__meta {
    display:block;
    margin-top:6rpx;
    font-size: 22rpx;
    color: #94a3b8;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.warehouse-row__manager {
    display:block;
    margin-top:4rpx;
    font-size: 21rpx;
    color: #64748b;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.location-head {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #fff;
    padding: 22rpx 0 14rpx;
    border-bottom: 1rpx solid #f1f5f9;
}
.location-head__title { display:block; font-size:29rpx; font-weight:700; color:#0f172a; }
.location-head__sub { display:block; margin-top:4rpx; font-size:23rpx; color:#94a3b8; }
.location-row {
    min-height: 88rpx;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16rpx;
    border-bottom:1rpx solid #f1f5f9;
}
.location-row.active .location-row__name { color:#3b6ef5; font-weight:600; }
.location-row--all { background:#f8fafc; margin-top:12rpx; padding:0 16rpx; border-radius:12rpx; border-bottom:0; }
.location-row__main { min-width:0; flex:1; }
.location-row__name { font-size:28rpx; color:#0f172a; }
.location-row__manager { display:block; margin-top:6rpx; font-size:22rpx; color:#94a3b8; }
.location-empty { padding: 48rpx 0; text-align:center; }
.location-empty__tip { display:block; margin-top:18rpx; font-size:24rpx; color:#94a3b8; }
</style>
