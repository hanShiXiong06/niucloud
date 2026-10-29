<template>
    <div class="mall-brand">
        <el-select :model-value="Number(modelValue) || undefined" :disabled="disabled" filterable clearable placeholder="选择商城品牌（选填）" class="mall-brand__select" @update:model-value="choose">
            <el-option v-for="brand in options" :key="brand.brand_id" :value="brand.brand_id" :label="brand.brand_name" />
            <el-option v-if="invalidSelected" :value="Number(modelValue)" label="原品牌已失效，请重选" disabled />
        </el-select>
        <div v-if="invalidSelected && !disabled" class="mall-brand__warning">原品牌已失效，请重新选择或清空。</div>
        <div v-else-if="!options.length && !disabled" class="mall-brand__note">商城暂无可选品牌，可稍后补充。</div>
    </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
type MallBrand = { brand_id: number; brand_name: string }
const props = withDefaults(defineProps<{ modelValue: number; brands: MallBrand[]; disabled?: boolean }>(), { disabled: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: number): void; (event: 'change', value: MallBrand): void }>()
const options = computed(() => {
    const rows = (Array.isArray(props.brands) ? props.brands : []).filter(row => row && Number.isSafeInteger(Number(row.brand_id)) && Number(row.brand_id) > 0 && typeof row.brand_name === 'string' && row.brand_name.trim())
    const seen = new Set<number>(), conflicts = new Set(rows.filter(row => rows.some(other => Number(other.brand_id) === Number(row.brand_id) && other.brand_name.trim() !== row.brand_name.trim())).map(row => Number(row.brand_id)))
    return rows.filter(row => { const id = Number(row.brand_id); if (seen.has(id) || conflicts.has(id)) return false; seen.add(id); return true }).map(row => ({ brand_id: Number(row.brand_id), brand_name: row.brand_name.trim() }))
})
const invalidSelected = computed(() => Number(props.modelValue) > 0 && !options.value.some(brand => brand.brand_id === Number(props.modelValue)))
function choose(value: unknown) {
    if (props.disabled) return
    const id = value == null || value === '' ? 0 : Number(value)
    if (!Number.isSafeInteger(id) || id < 0) return
    const brand = options.value.find(option => option.brand_id === id)
    if (id > 0 && !brand) return
    emit('update:modelValue', id); emit('change', brand || { brand_id: 0, brand_name: '' })
}
</script>
<style scoped>
.mall-brand,.mall-brand__select{width:100%;min-width:0}.mall-brand__warning,.mall-brand__note{margin-top:6px;color:var(--el-text-color-secondary);font-size:12px;line-height:1.6}.mall-brand__warning{color:var(--el-color-warning-dark-2)}
</style>
