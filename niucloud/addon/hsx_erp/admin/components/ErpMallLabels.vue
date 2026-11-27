<template>
    <div class="mall-labels">
        <el-select :model-value="selected" :disabled="disabled" multiple filterable clearable collapse-tags collapse-tags-tooltip placeholder="选择商城标签（选填）" class="mall-labels__select" @update:model-value="change">
            <el-option-group v-for="group in groups" :key="group.key" :label="group.name">
                <el-option v-for="label in group.labels" :key="label.label_id" :label="label.label_name" :value="label.label_id" />
            </el-option-group>
            <el-option v-for="(id, index) in unavailable" :key="id" :value="id" :label="`原标签已移除${unavailable.length > 1 ? ` ${index + 1}` : ''}`" disabled />
        </el-select>
        <div v-if="unavailable.length" class="mall-labels__warning"><span>{{ unavailable.length }} 个原标签已失效，已保留，请核对。</span><el-button link type="primary" :disabled="disabled" @click="removeUnavailable">移除失效标签</el-button></div>
        <div v-else-if="!options.length" class="mall-labels__note">商城暂无可选标签，可稍后补充。</div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
type MallLabel = { label_id: number; label_name: string; group_id?: number; group_name?: string }
const props = withDefaults(defineProps<{ modelValue: number[]; labels: MallLabel[]; disabled?: boolean }>(), { disabled: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: number[]): void }>()
const cleanIds = (value: unknown) => [...new Set((Array.isArray(value) ? value : []).filter(id => typeof id === 'number' || typeof id === 'string').map(Number).filter(id => Number.isSafeInteger(id) && id > 0))]
const selected = computed(() => cleanIds(props.modelValue))
const options = computed(() => {
    const rows = (Array.isArray(props.labels) ? props.labels : []).filter(row => row && Number.isSafeInteger(Number(row.label_id)) && Number(row.label_id) > 0 && typeof row.label_name === 'string' && row.label_name.trim())
    const seen = new Set<number>(), conflicts = new Set(rows.filter(row => rows.some(other => Number(other.label_id) === Number(row.label_id) && other.label_name.trim() !== row.label_name.trim())).map(row => Number(row.label_id)))
    return rows.filter(row => { const id = Number(row.label_id); if (seen.has(id) || conflicts.has(id)) return false; seen.add(id); return true }).map(row => ({ ...row, label_id: Number(row.label_id), label_name: row.label_name.trim() }))
})
const unavailable = computed(() => selected.value.filter(id => !options.value.some(label => label.label_id === id)))
const groups = computed(() => {
    const result: { key: string; name: string; labels: MallLabel[] }[] = []
    for (const label of options.value) {
        const key = String(label.group_id || label.group_name || 'default'), name = label.group_name || '其他标签'
        let group = result.find(item => item.key === key)
        if (!group) { group = { key, name, labels: [] }; result.push(group) }
        group.labels.push(label)
    }
    return result
})
function change(value: unknown) {
    if (props.disabled) return
    emit('update:modelValue', cleanIds(value).filter(id => options.value.some(label => label.label_id === id) || selected.value.includes(id)))
}
function removeUnavailable() { change(selected.value.filter(id => !unavailable.value.includes(id))) }
</script>

<style scoped>
.mall-labels{width:100%;min-width:0}.mall-labels__select{width:100%}.mall-labels__note,.mall-labels__warning{font-size:12px;line-height:1.6;margin-top:6px;color:var(--el-text-color-secondary)}.mall-labels__warning{display:flex;align-items:center;flex-wrap:wrap;gap:4px 10px;color:var(--el-color-warning-dark-2)}
</style>
