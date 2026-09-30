<template>
    <div class="material-fields">
        <div class="material-fields__grid">
            <el-form-item v-for="field in selectionFields" :key="field.key" :label="field.label">
                <el-select :model-value="modelValue[field.key]" filterable clearable :placeholder="`选择商城${field.label}`"
                    :no-data-text="`商城尚未配置${field.label}选项`" @update:model-value="value => changeChoice(field.key, value || '')">
                    <el-option v-if="isUnmatched(field.key)" :value="modelValue[field.key]" :label="`${modelValue[field.key]}（原值，待对应）`" disabled />
                    <el-option v-for="value in choices[field.key]" :key="value" :value="value" :label="value" />
                </el-select>
                <p v-if="isUnmatched(field.key)" class="material-fields__note material-fields__note--warning">原值未对应商城选项；请选择标准值，不修改则保留原值。</p>
                <p v-if="!choices[field.key].length" class="material-fields__note">{{ field.key === 'condition_grade' ? '请管理员在商城成色管理中配置后刷新。' : '请先选择下方参数模板；若仍无选项，请管理员在商城规格或参数中配置后刷新。' }}</p>
            </el-form-item>
            <el-form-item label="电池健康度（%）"><el-input-number :model-value="modelValue.battery_health" :min="0" :max="100" :precision="0" :controls="false" placeholder="未知留空" @update:model-value="value => patch({ battery_health: value })" /></el-form-item>
            <el-form-item label="保修到期日"><el-date-picker :model-value="modelValue.warranty_date" type="date" value-format="YYYY-MM-DD" placeholder="未知留空" clearable @update:model-value="value => patch({ warranty_date: value || '' })" /></el-form-item>
        </div>
        <p class="material-fields__note">电池和保修填写实际值，商城自动计算筛选区间，不用手动填写区间名称。</p>
        <div class="material-fields__heading"><strong>商品参数</strong><span>沿用商城商品编辑页的模板</span></div>
        <el-form-item label="参数模板">
            <el-select :model-value="modelValue.attr_ids" multiple filterable clearable collapse-tags collapse-tags-tooltip placeholder="选择商城已有模板（选填）" @update:model-value="changeTemplates">
                <el-option v-for="template in templates" :key="template.attr_id" :label="template.attr_name" :value="Number(template.attr_id)" />
                <el-option v-for="id in missingTemplateIds" :key="id" :value="id" label="原模板已移除（保留已有资料）" disabled />
            </el-select>
            <p v-if="!templates.length" class="material-fields__note">商城暂无参数模板，请管理员在“商品参数”中配置后刷新。</p>
        </el-form-item>
        <div class="material-fields__grid">
            <el-form-item v-for="field in fields" :key="parameterKey(field)" :label="field.attr_value_name">
                <el-input v-if="field.type === 'text'" :model-value="parameterDisplay(parameterRow(field)?.attr_child_value_name)" maxlength="30" clearable placeholder="按商城模板填写实际内容" @update:model-value="value => changeParameter(field, value)" />
                <el-select v-else :model-value="parameterValue(field)" :multiple="field.type === 'checkbox'" filterable clearable collapse-tags collapse-tags-tooltip placeholder="从商城选项中选择" @update:model-value="value => changeParameter(field, value)">
                    <el-option v-for="child in asArray(field.child)" :key="String(child.id)" :value="String(child.id)" :label="child.name" />
                    <el-option v-for="child in unmatchedParameterOptions(field, parameterRow(field))" :key="child.id" :value="child.id" :label="`${child.name}（原选项已移除）`" disabled />
                </el-select>
                <p v-if="unmatchedParameterOptions(field, parameterRow(field)).length" class="material-fields__note material-fields__note--warning">原选项已变更，请重新选择或清空后保存。</p>
            </el-form-item>
        </div>
        <el-collapse v-if="preservedParameters.length">
            <el-collapse-item title="查看保留的原有参数" name="preserved">
                <div v-for="row in preservedParameters" :key="parameterKey(row)" class="material-fields__history"><span>{{ row.attr_value_name }}</span><span>{{ parameterDisplay(row.attr_child_value_name) || '未填写' }}</span></div>
            </el-collapse-item>
        </el-collapse>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { asArray, materialOptions, mergeParameters, parameterDisplay, parameterFields, parameterKey, selectionFields, setParameterValue, standardField, unmatchedParameterOptions } from './material-options'
import type { MaterialForm } from './material-options'
const props = defineProps<{ modelValue: MaterialForm; catalog: Record<string, any> }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: MaterialForm): void }>()
const patch = (values: Partial<MaterialForm>) => emit('update:modelValue', { ...props.modelValue, ...values })
const templates = computed(() => asArray(props.catalog.templates))
const choices = computed(() => materialOptions(props.catalog, props.modelValue.attr_ids))
const fields = computed(() => parameterFields(props.catalog, props.modelValue.attr_ids))
const missingTemplateIds = computed(() => props.modelValue.attr_ids.filter(id => !templates.value.some(row => Number(row.attr_id) === id)))
const preservedParameters = computed(() => props.modelValue.attr_format.filter(row => !fields.value.some(field => parameterKey(field) === parameterKey(row))))
const isUnmatched = (key: 'memory_group' | 'device_color' | 'condition_grade') => !!props.modelValue[key] && !choices.value[key].includes(props.modelValue[key])
const parameterRow = (field: any) => props.modelValue.attr_format.find(row => parameterKey(row) === parameterKey(field))
const parameterValue = (field: any) => {
    const value = parameterRow(field)?.attr_child_value_id
    return field.type === 'checkbox' ? asArray(value).map(String) : value == null ? '' : String(value)
}
const changeTemplates = (ids: number[]) => patch({ attr_ids: ids, attr_format: mergeParameters(parameterFields(props.catalog, ids), ids, props.modelValue.attr_format) })
// 同一含义的筛选属性与模板参数一起更新，避免出现“筛选银色、详情黑色”。
const changeChoice = (key: string, value: string) => {
    const rows = props.modelValue.attr_format.map(row => {
        const field = fields.value.find(field => parameterKey(field) === parameterKey(row) && field.type === 'radio' && standardField(field.attr_value_name) === key)
        if (!field) return row
        const updated = { ...row }
        const child = asArray(field.child).find(child => child.name === value)
        setParameterValue(updated, field, child ? String(child.id) : '')
        return updated
    })
    patch({ [key]: value, attr_format: rows })
}
const changeParameter = (field: any, value: string | string[]) => {
    const key = field.type === 'radio' ? standardField(field.attr_value_name) : ''
    if (key) {
        const name = asArray(field.child).find(child => String(child.id) === String(value))?.name || ''
        changeChoice(key, name)
        return
    }
    const rows = props.modelValue.attr_format.map(row => {
        if (parameterKey(row) !== parameterKey(field)) return row
        const updated = { ...row }
        if (field.type === 'text') updated.attr_child_value_name = value as string
        else setParameterValue(updated, field, value ?? '')
        return updated
    })
    patch({ attr_format: rows })
}
</script>

<style scoped>
.material-fields__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 16px; }
.material-fields :deep(.el-date-editor), .material-fields :deep(.el-input-number), .material-fields :deep(.el-select) { width: 100%; }
.material-fields__note { width: 100%; margin: 5px 0 0; color: var(--el-text-color-secondary); font-size: 12px; line-height: 19px; }
.material-fields__note--warning { color: var(--el-color-warning-dark-2); }
.material-fields__heading { display: flex; flex-wrap: wrap; gap: 8px; align-items: baseline; margin: 20px 0 12px; }
.material-fields__heading span, .material-fields__history { color: var(--el-text-color-secondary); font-size: 12px; }
.material-fields__history { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; }
@media (max-width: 520px) { .material-fields__grid { grid-template-columns: 1fr; } }
</style>
