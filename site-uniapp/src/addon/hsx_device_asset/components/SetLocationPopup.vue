<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="set-loc">
            <view class="set-loc__head">
                <view class="set-loc__title">设置库位</view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 set-loc__close" @click="handleClose"></text>
            </view>

            <view class="set-loc__device">
                <view class="set-loc__device-name">{{ asset?.model || asset?.asset_no || ('资产 #' + (asset?.id || '')) }}</view>
                <view class="set-loc__device-meta">IMEI {{ asset?.imei || '-' }}</view>
                <view v-if="asset?.location_name" class="set-loc__device-meta">当前库位：{{ asset.location_name }}</view>
            </view>

            <scroll-view scroll-y class="set-loc__body">
                <view v-if="!erpConnected && loaded" class="set-loc__empty">未检测到 ERP 仓库，请先在 ERP 中创建仓库与库位。</view>
                <template v-else>
                    <view class="set-loc__label">仓库</view>
                    <view class="loc-chips">
                        <view
                            v-for="w in warehouses"
                            :key="w.id"
                            class="loc-chip"
                            :class="{ 'loc-chip--active': currentWarehouseId === w.id }"
                            @click="selectWarehouse(w)"
                        >{{ w.warehouse_name }}</view>
                    </view>

                    <template v-if="currentWarehouseId">
                        <view class="set-loc__label">库位</view>
                        <view v-if="currentLocations.length" class="loc-chips">
                            <view
                                v-for="loc in currentLocations"
                                :key="loc.id"
                                class="loc-chip"
                                :class="{ 'loc-chip--active': form.location_id === loc.id }"
                                @click="selectLocation(loc)"
                            >{{ loc.location_name }}</view>
                        </view>
                        <view v-else class="set-loc__empty">该仓库暂无库位</view>
                    </template>
                </template>
            </scroll-view>

            <view class="set-loc__footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1, marginRight: '20rpx' }">取消</u-button>
                <u-button type="primary" :loading="saving" :disabled="!form.location_id" @click="handleSave" :customStyle="{ flex: 2 }">保存</u-button>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { getAssignWarehouseTree, setAssetLocation } from '@/addon/hsx_device_asset/api/device_asset'

interface LocationItem { id: number; location_name: string }
interface WarehouseItem { id: number; warehouse_name: string; locations?: LocationItem[] }

const props = defineProps<{ show: boolean; asset: Record<string, any> | null }>()
const emit = defineEmits(['update:show', 'success'])

const loaded = ref(false)
const saving = ref(false)
const erpConnected = ref(false)
const warehouses = ref<WarehouseItem[]>([])
const currentWarehouseId = ref<number | ''>('')
const form = ref({ warehouse_id: 0, warehouse_name: '', location_id: 0, location_name: '' })

const currentLocations = computed<LocationItem[]>(() => {
    return warehouses.value.find((w) => w.id === currentWarehouseId.value)?.locations || []
})

const loadTree = async () => {
    try {
        const res: any = await getAssignWarehouseTree()
        warehouses.value = res?.data?.warehouses || []
        erpConnected.value = !!res?.data?.erp_connected
        // 回填当前库位
        const wid = Number(props.asset?.warehouse_id || 0)
        const lid = Number(props.asset?.location_id || 0)
        if (wid > 0) {
            currentWarehouseId.value = wid
            form.value = {
                warehouse_id: wid,
                warehouse_name: props.asset?.warehouse_name || '',
                location_id: lid,
                location_name: props.asset?.location_name || ''
            }
        } else {
            currentWarehouseId.value = ''
            form.value = { warehouse_id: 0, warehouse_name: '', location_id: 0, location_name: '' }
        }
    } catch (e) {
        // 请求层已提示
    } finally {
        loaded.value = true
    }
}

const selectWarehouse = (w: WarehouseItem) => {
    currentWarehouseId.value = w.id
    form.value.warehouse_id = w.id
    form.value.warehouse_name = w.warehouse_name || ''
    // 切换仓库清空已选库位
    form.value.location_id = 0
    form.value.location_name = ''
}

const selectLocation = (loc: LocationItem) => {
    form.value.location_id = loc.id
    form.value.location_name = loc.location_name || ''
}

const handleSave = async () => {
    if (!form.value.location_id) {
        uni.showToast({ title: '请选择库位', icon: 'none' })
        return
    }
    if (!props.asset?.id) return
    saving.value = true
    try {
        await setAssetLocation(props.asset.id, { ...form.value })
        uni.showToast({ title: '库位已更新', icon: 'success' })
        emit('update:show', false)
        emit('success')
    } finally {
        saving.value = false
    }
}

const handleClose = () => emit('update:show', false)

watch(() => props.show, (v) => { if (v) { loaded.value = false; loadTree() } })
</script>

<style lang="scss" scoped>
.set-loc {
    display: flex;
    flex-direction: column;
    max-height: 78vh;
    background: #fff;
}
.set-loc__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 32rpx var(--popup-sidebar-m, 30rpx) 20rpx;
    border-bottom: 2rpx solid var(--hsx-border);
}
.set-loc__title {
    font-size: 32rpx;
    font-weight: 600;
    color: var(--hsx-text-strong);
}
.set-loc__close {
    font-size: 32rpx;
    color: var(--hsx-text-placeholder);
}
.set-loc__device {
    padding: 20rpx var(--popup-sidebar-m, 30rpx);
    background: var(--hsx-fill-light);
    margin: 20rpx var(--popup-sidebar-m, 30rpx) 0;
    border-radius: var(--hsx-radius-sm);
}
.set-loc__device-name {
    font-size: 28rpx;
    font-weight: 500;
    color: var(--hsx-text-strong);
}
.set-loc__device-meta {
    margin-top: 4rpx;
    font-size: 24rpx;
    color: var(--hsx-text-secondary);
}
.set-loc__body {
    padding: 20rpx var(--popup-sidebar-m, 30rpx);
    flex: 1;
}
.set-loc__label {
    margin: 16rpx 0 14rpx;
    font-size: 26rpx;
    font-weight: 500;
    color: var(--hsx-text-regular);
}
.loc-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}
.loc-chip {
    padding: 14rpx 26rpx;
    border-radius: 999rpx;
    background: var(--hsx-fill-light);
    border: 1rpx solid var(--hsx-border);
    color: var(--hsx-text-regular);
    font-size: 24rpx;
}
.loc-chip--active {
    background: var(--hsx-primary-50);
    border-color: var(--hsx-primary);
    color: var(--hsx-primary);
    font-weight: 500;
}
.set-loc__empty {
    padding: 30rpx 0;
    font-size: 24rpx;
    color: var(--hsx-text-placeholder);
}
.set-loc__footer {
    display: flex;
    align-items: center;
    padding: 20rpx var(--popup-sidebar-m, 30rpx);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    border-top: 2rpx solid var(--hsx-border);
}
</style>
