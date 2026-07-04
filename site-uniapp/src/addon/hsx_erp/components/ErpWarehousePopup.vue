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
                <text class="popup-title">{{ step === 'warehouse' ? '选择仓库' : '选择库位' }}</text>
                <view class="popup-header__right">
                    <text v-if="step === 'location'" class="back-btn" @click="step = 'warehouse'">← 返回</text>
                    <u-icon name="close" size="20" color="#94a3b8" @click="close" />
                </view>
            </view>

            <!-- 第一步：选仓库 -->
            <scroll-view v-if="step === 'warehouse'" scroll-y class="popup-list">
                <view v-if="loading" class="popup-loading"><u-loading-icon size="24" /></view>
                <template v-else>
                    <view
                        v-for="wh in warehouses"
                        :key="wh.id"
                        class="wh-item"
                        :class="{ selected: wh.id === warehouseId }"
                        @click="selectWarehouse(wh)"
                    >
                        <view class="wh-item__main">
                            <view>
                                <text class="wh-item__name">{{ wh.warehouse_name }}</text>
                                <text class="wh-item__type">{{ typeLabel(wh.warehouse_type) }}</text>
                            </view>
                            <view class="wh-item__right">
                                <text class="wh-item__loc-count" v-if="wh.locations?.length">
                                    {{ wh.locations.length }} 个库位
                                </text>
                                <u-icon name="arrow-right" size="16" color="#94a3b8" />
                            </view>
                        </view>
                    </view>
                    <view class="popup-empty" v-if="!warehouses.length">暂无仓库，请先在基础设置中创建</view>
                </template>
            </scroll-view>

            <!-- 第二步：选库位 -->
            <scroll-view v-else scroll-y class="popup-list">
                <view class="wh-banner">
                    <text class="wh-banner__name">{{ currentWarehouse?.warehouse_name }}</text>
                </view>
                <view
                    v-for="loc in currentLocations"
                    :key="loc.id"
                    class="wh-item"
                    :class="{ selected: loc.id === locationId }"
                    @click="selectLocation(loc)"
                >
                    <view class="wh-item__main">
                        <text class="wh-item__name">{{ loc.location_name }}</text>
                        <u-icon v-if="loc.id === locationId" name="checkmark-circle-fill" color="#3b6ef5" size="20" />
                    </view>
                </view>
                <!-- 无库位时直接确认仓库 -->
                <view v-if="!currentLocations.length" class="popup-empty">
                    <text>该仓库暂无库位</text>
                    <u-button type="primary" size="small" style="margin-top:24rpx" @click="confirmNoLocation">直接使用该仓库</u-button>
                </view>
            </scroll-view>
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
}>(), {
    show: false,
    warehouseId: 0,
    warehouseName: '',
    locationId: 0,
    locationName: '',
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'update:warehouseId', v: number): void
    (e: 'update:warehouseName', v: string): void
    (e: 'update:locationId', v: number): void
    (e: 'update:locationName', v: string): void
    (e: 'change', warehouse: any, location: any): void
}>()

const step = ref<'warehouse' | 'location'>('warehouse')
const warehouses = ref<any[]>([])
const loading = ref(false)
const currentWarehouse = ref<any>(null)

const currentLocations = computed(() =>
    (currentWarehouse.value?.locations || []).filter((l: any) => l.status === 1)
)

watch(() => props.show, (v) => { if (v) { step.value = 'warehouse'; loadWarehouses() } })

async function loadWarehouses() {
    loading.value = true
    try {
        const res: any = await request.get('erp/warehouse/options')
        warehouses.value = Array.isArray(res?.data) ? res.data : (res?.data?.data || [])
    } catch {
        warehouses.value = []
    } finally {
        loading.value = false }
}

function selectWarehouse(wh: any) {
    currentWarehouse.value = wh
    emit('update:warehouseId', wh.id)
    emit('update:warehouseName', wh.warehouse_name)
    // 如果有库位，进入第二步；否则直接确认
    if (currentLocations.value.length > 0) {
        step.value = 'location'
        // 自动选第一个库位
        const first = currentLocations.value[0]
        emit('update:locationId', first.id)
        emit('update:locationName', first.location_name)
    } else {
        emit('update:locationId', 0)
        emit('update:locationName', '')
        emit('change', wh, null)
        close()
    }
}

function selectLocation(loc: any) {
    emit('update:locationId', loc.id)
    emit('update:locationName', loc.location_name)
    emit('change', currentWarehouse.value, loc)
    close()
}

function confirmNoLocation() {
    emit('change', currentWarehouse.value, null)
    close()
}

function close() { emit('update:show', false) }

const typeLabel = (t: string) => ({ second_hand: '二手仓', peer: '同行仓', consignment: '代卖仓', abnormal: '异常仓' }[t] || t || '')
</script>

<style scoped lang="scss">
.popup-wrap { height: 65vh; display: flex; flex-direction: column; }
.popup-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 28rpx 32rpx 16rpx;
}
.popup-header__right { display: flex; align-items: center; gap: 24rpx; }
.popup-title { font-size: 32rpx; font-weight: 700; color: #0f172a; }
.back-btn { font-size: 26rpx; color: #3b6ef5; }
.popup-list { flex: 1; overflow-y: auto; padding: 0 24rpx; }
.popup-loading { display: flex; justify-content: center; padding: 48rpx; }
.popup-empty { text-align: center; padding: 48rpx 0; color: #94a3b8; font-size: 26rpx; }
.wh-banner { background: #eff6ff; border-radius: 8rpx; padding: 12rpx 16rpx; margin-bottom: 16rpx; }
.wh-banner__name { font-size: 26rpx; color: #3b6ef5; font-weight: 600; }
.wh-item {
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f1f5f9;
    &.selected { background: #eff6ff; border-radius: 8rpx; padding: 20rpx 12rpx; }
}
.wh-item__main { display: flex; align-items: center; justify-content: space-between; }
.wh-item__name { font-size: 28rpx; color: #0f172a; }
.wh-item__type { font-size: 22rpx; color: #94a3b8; margin-left: 8rpx; }
.wh-item__right { display: flex; align-items: center; gap: 8rpx; }
.wh-item__loc-count { font-size: 24rpx; color: #64748b; }
</style>
