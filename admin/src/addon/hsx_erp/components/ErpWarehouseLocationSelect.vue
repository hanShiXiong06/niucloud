<template>
    <div class="erp-wh-select flex gap-2">
        <!-- 仓库下拉 -->
        <el-select
            v-model="selectedWarehouseId"
            :placeholder="warehousePlaceholder"
            :style="{ width: warehouseWidth }"
            :disabled="disabled"
            filterable
            clearable
            @change="onWarehouseChange"
        >
            <el-option
                v-for="w in warehouseOptions"
                :key="w.id"
                :label="w.warehouse_name"
                :value="w.id"
            >
                <span>{{ w.warehouse_name }}</span>
                <span v-if="w.warehouse_type" class="ml-2 text-xs text-gray-400">{{ warehouseTypeLabel(w.warehouse_type) }}</span>
            </el-option>
        </el-select>

        <!-- 库位下拉（只有有库位时才显示） -->
        <el-select
            v-if="showLocation && locationOptions.length > 0"
            v-model="selectedLocationId"
            :placeholder="locationPlaceholder"
            :style="{ width: locationWidth }"
            :disabled="disabled || !selectedWarehouseId"
            filterable
            clearable
            @change="onLocationChange"
        >
            <el-option
                v-for="l in locationOptions"
                :key="l.id"
                :label="l.location_name"
                :value="l.id"
            />
        </el-select>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import request from '@/utils/request'

interface Location {
    id: number
    location_name: string
    status: number
}
interface Warehouse {
    id: number
    warehouse_name: string
    warehouse_type?: string
    locations?: Location[]
    status: number
}

const props = withDefaults(defineProps<{
    /** 仓库ID */
    warehouseId?: number | null
    /** 库位ID */
    locationId?: number | null
    disabled?: boolean
    /** 是否显示库位选择 */
    showLocation?: boolean
    warehousePlaceholder?: string
    locationPlaceholder?: string
    warehouseWidth?: string
    locationWidth?: string
    /** 过滤仓库类型，如 ['second_hand', 'peer'] */
    filterTypes?: string[]
}>(), {
    warehouseId: null,
    locationId: null,
    disabled: false,
    showLocation: true,
    warehousePlaceholder: '选择仓库',
    locationPlaceholder: '选择库位',
    warehouseWidth: '160px',
    locationWidth: '130px',
    filterTypes: () => [],
})

const emit = defineEmits<{
    (e: 'update:warehouseId', val: number | null): void
    (e: 'update:locationId', val: number | null): void
    (e: 'change', warehouse: Warehouse | null, location: Location | null): void
}>()

const allWarehouses = ref<Warehouse[]>([])
const selectedWarehouseId = ref<number | null>(props.warehouseId)
const selectedLocationId = ref<number | null>(props.locationId)

const warehouseOptions = computed(() => {
    if (!props.filterTypes?.length) return allWarehouses.value
    return allWarehouses.value.filter(w => props.filterTypes!.includes(w.warehouse_type || ''))
})

const locationOptions = computed(() => {
    if (!selectedWarehouseId.value) return []
    const wh = allWarehouses.value.find(w => w.id === selectedWarehouseId.value)
    return (wh?.locations || []).filter(l => l.status === 1)
})

async function loadWarehouses() {
    const res = await request.get('erp/warehouse/options')
    allWarehouses.value = res.data || []
}

function onWarehouseChange(id: number | null) {
    selectedLocationId.value = null
    emit('update:warehouseId', id)
    emit('update:locationId', null)
    fireChange()
    // 自动选第一个库位
    if (id && locationOptions.value.length > 0) {
        selectedLocationId.value = locationOptions.value[0].id
        emit('update:locationId', selectedLocationId.value)
        fireChange()
    }
}

function onLocationChange(id: number | null) {
    emit('update:locationId', id)
    fireChange()
}

function fireChange() {
    const wh = allWarehouses.value.find(w => w.id === selectedWarehouseId.value) || null
    const loc = locationOptions.value.find(l => l.id === selectedLocationId.value) || null
    emit('change', wh, loc)
}

function warehouseTypeLabel(type: string) {
    const map: Record<string, string> = {
        second_hand: '二手仓',
        peer: '同行仓',
        consignment: '代卖仓',
        abnormal: '异常仓',
    }
    return map[type] || type
}

// 同步外部 v-model 变化
watch(() => props.warehouseId, (v) => { selectedWarehouseId.value = v })
watch(() => props.locationId, (v) => { selectedLocationId.value = v })

onMounted(() => { loadWarehouses() })
</script>
