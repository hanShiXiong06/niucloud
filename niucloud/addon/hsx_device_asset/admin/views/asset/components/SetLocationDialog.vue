<template>
    <el-dialog v-model="visible" title="设置库位" width="460px" @open="loadAll">
        <div v-loading="loading">
            <el-alert
                v-if="loaded && !erpConnected"
                type="warning"
                :closable="false"
                show-icon
                title="未检测到 ERP 仓库"
                description="库位来自 ERP，请先在 ERP 中创建仓库与库位。"
                class="mb-3"
            />
            <template v-else>
                <div class="sl-device">
                    <div class="sl-device__name">{{ asset?.model || asset?.asset_no || ('资产 #' + (asset?.id || '')) }}</div>
                    <div class="sl-device__meta">IMEI {{ asset?.imei || '-' }}</div>
                </div>
                <el-tree-select
                    v-model="targetValue"
                    :data="treeData"
                    node-key="value"
                    :props="{ label: 'label', children: 'children', disabled: 'disabled' }"
                    :render-after-expand="false"
                    check-strictly
                    default-expand-all
                    placeholder="选择 仓库 / 库位"
                    class="sl-cascader"
                    clearable
                />
                <div class="sl-tip">仅未开启「允许调入」的仓库被禁用（与调拨规则一致，可在 ERP 仓库管理里配置）。归位后负责该库位的员工即可在移动端「我的待办」看到这台设备。</div>
            </template>
        </div>
        <template #footer>
            <el-button @click="visible = false">取消</el-button>
            <el-button type="primary" :loading="saving" :disabled="!erpConnected" @click="handleSave">保存</el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getAssignWarehouseTree, setAssetLocation } from '@/addon/hsx_device_asset/api/device_asset'

interface LocationItem { id: number; location_name: string }
interface WarehouseItem { id: number; warehouse_name: string; business_type?: string; locations?: LocationItem[] }

const props = defineProps<{ modelValue: boolean; asset: Record<string, any> | null }>()
const emit = defineEmits(['update:modelValue', 'success'])

const visible = computed({
    get: () => props.modelValue,
    set: (v: boolean) => emit('update:modelValue', v)
})

const loading = ref(false)
const loaded = ref(false)
const saving = ref(false)
const erpConnected = ref(false)
const warehouses = ref<WarehouseItem[]>([])
const targetValue = ref<string>('')

const warehouseTypeLabel = (t?: string) =>
    (({ mall: '二手机仓', peer: '同行仓', consignment: '代卖仓', hold: '暂存仓' }) as Record<string, string>)[String(t || '')] || ''

// 树形：仓库为父、库位为子。与调拨一致：目标仓未开启「允许调入」则禁用(仓库管理里配置)。
const treeData = computed(() => warehouses.value.map((w) => {
    const t = String(w.business_type || '')
    const blocked = Number((w as any).allow_inbound ?? 1) !== 1
    const tl = warehouseTypeLabel(t)
    return {
        value: 'w:' + w.id,
        label: w.warehouse_name + (tl ? `（${tl}）` : '') + (blocked ? ' · 不可放入' : ''),
        disabled: blocked,
        children: (w.locations || []).map((l) => ({ value: `l:${w.id}:${l.id}`, label: l.location_name, disabled: blocked }))
    }
}))

const loadAll = async () => {
    loading.value = true
    try {
        const res: any = await getAssignWarehouseTree()
        warehouses.value = res.data?.warehouses || []
        erpConnected.value = !!res.data?.erp_connected
        // 反显当前库位
        const wid = Number(props.asset?.warehouse_id || 0)
        const lid = Number(props.asset?.location_id || 0)
        targetValue.value = (wid > 0 && lid > 0) ? `l:${wid}:${lid}` : (wid > 0 ? `w:${wid}` : '')
    } catch (e) {
        // 请求层已提示
    } finally {
        loaded.value = true
        loading.value = false
    }
}

const handleSave = async () => {
    const val = targetValue.value || ''
    if (!val.startsWith('l:')) {
        ElMessage.warning('请选择到具体库位')
        return
    }
    const parts = val.split(':')
    const warehouseId = Number(parts[1])
    const locationId = Number(parts[2])
    const w = warehouses.value.find((item) => item.id === warehouseId)
    const l = w?.locations?.find((item) => item.id === locationId)
    saving.value = true
    try {
        await setAssetLocation(Number(props.asset?.id), {
            warehouse_id: warehouseId,
            warehouse_name: w?.warehouse_name || '',
            location_id: locationId,
            location_name: l?.location_name || ''
        })
        ElMessage.success('库位已更新')
        visible.value = false
        emit('success')
    } catch (e) {
        // 请求层已提示
    } finally {
        saving.value = false
    }
}

watch(visible, (v) => { if (!v) { loaded.value = false } })
</script>

<style lang="scss" scoped>
.sl-device {
    margin-bottom: 14px;
    padding: 10px 12px;
    background: var(--el-fill-color-lighter);
    border-radius: 8px;
}
.sl-device__name {
    font-size: 14px;
    font-weight: 600;
    color: var(--el-text-color-primary);
}
.sl-device__meta {
    margin-top: 3px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
.sl-cascader {
    width: 100%;
}
.sl-tip {
    margin-top: 12px;
    font-size: 12px;
    line-height: 1.6;
    color: var(--el-text-color-secondary);
}
</style>
