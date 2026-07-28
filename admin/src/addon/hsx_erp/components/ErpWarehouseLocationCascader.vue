<template>
    <el-cascader
        :model-value="selectedPath"
        :options="options"
        :props="cascaderProps"
        :placeholder="placeholder"
        :clearable="clearable"
        :filterable="filterable"
        :disabled="disabled"
        :show-all-levels="true"
        class="erp-warehouse-location-cascader"
        popper-class="erp-warehouse-location-cascader__popper"
        @change="handleChange"
    />
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    warehouses?: any[]
    warehouseId?: number | string
    locationId?: number | string
    filterTypes?: string[]
    placeholder?: string
    clearable?: boolean
    filterable?: boolean
    disabled?: boolean
}>(), {
    warehouses: () => [],
    warehouseId: 0,
    locationId: 0,
    filterTypes: () => [],
    placeholder: '选择仓库 / 库位',
    clearable: true,
    filterable: true,
    disabled: false
})

const emit = defineEmits<{
    (event: 'change', payload: {
        warehouse_id: number
        warehouse_name: string
        location_id: number
        location_name: string
    }): void
}>()

const cascaderProps = {
    value: 'value',
    label: 'label',
    children: 'children',
    emitPath: true,
    checkStrictly: false,
    expandTrigger: 'hover'
} as const

const availableWarehouses = computed(() => {
    if (!props.filterTypes.length) return props.warehouses
    return props.warehouses.filter((warehouse: any) => props.filterTypes.includes(String(warehouse?.warehouse_type || '')))
})

const options = computed(() => availableWarehouses.value.map((warehouse: any) => {
    const locations = Array.isArray(warehouse?.locations) ? warehouse.locations : []
    return {
        value: Number(warehouse?.id || 0),
        label: String(warehouse?.warehouse_name || '未命名仓库'),
        disabled: !locations.length,
        children: locations.map((location: any) => ({
            value: Number(location?.id || 0),
            label: String(location?.location_name || '未命名库位')
        }))
    }
}))

const selectedPath = computed(() => {
    const warehouseId = Number(props.warehouseId || 0)
    const locationId = Number(props.locationId || 0)
    return warehouseId && locationId ? [warehouseId, locationId] : []
})

function handleChange(value: any) {
    const path = Array.isArray(value) ? value : []
    const warehouseId = Number(path[0] || 0)
    const locationId = Number(path[1] || 0)
    const warehouse = availableWarehouses.value.find((item: any) => Number(item?.id) === warehouseId)
    const location = (warehouse?.locations || []).find((item: any) => Number(item?.id) === locationId)

    emit('change', {
        warehouse_id: warehouseId,
        warehouse_name: String(warehouse?.warehouse_name || ''),
        location_id: locationId,
        location_name: String(location?.location_name || '')
    })
}
</script>

<style scoped>
.erp-warehouse-location-cascader {
    width: 100%;
}
</style>

<style>
.erp-warehouse-location-cascader__popper .el-cascader-menu {
    min-width: 180px;
}

.erp-warehouse-location-cascader__popper .el-cascader-node__label {
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
