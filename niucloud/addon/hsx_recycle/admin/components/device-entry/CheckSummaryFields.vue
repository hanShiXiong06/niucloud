<template>
    <div class="summary-grid">
        <div v-for="field in fields" :key="field.field_key" class="summary-item">
            <label class="summary-item__label">
                <span v-if="field.is_required" class="summary-required">*</span>
                {{ field.field_name }}
                <span v-if="field.unit" class="summary-unit">({{ field.unit }})</span>
            </label>
            <el-select
                v-if="field.component === 'select' || (field.component === 'radio' && field.selection_mode !== 'multiple' && (field.options || []).length > 4)"
                v-model="modelValue[field.field_key]"
                :placeholder="field.placeholder || '请选择'"
                clearable
                class="summary-control"
            >
                <el-option v-for="opt in field.options" :key="String(opt.value)" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-radio-group v-else-if="field.component === 'radio'" v-model="modelValue[field.field_key]">
                <el-radio v-for="opt in field.options" :key="String(opt.value)" :label="opt.value">{{ opt.label }}</el-radio>
            </el-radio-group>
            <el-checkbox-group
                v-else-if="field.component === 'checkbox' || field.selection_mode === 'multiple'"
                v-model="modelValue[field.field_key]"
            >
                <el-checkbox v-for="opt in field.options" :key="String(opt.value)" :label="opt.value">{{ opt.label }}</el-checkbox>
            </el-checkbox-group>
            <el-switch v-else-if="field.component === 'switch'" v-model="modelValue[field.field_key]" />
            <el-input-number
                v-else-if="field.component === 'number'"
                v-model="modelValue[field.field_key]"
                :controls="false"
                :placeholder="field.placeholder || '请输入'"
                class="summary-control"
            />
            <el-input
                v-else-if="field.component === 'textarea'"
                v-model="modelValue[field.field_key]"
                type="textarea"
                :rows="2"
                :placeholder="field.placeholder || '请输入'"
                class="summary-control"
            />
            <el-input
                v-else
                v-model="modelValue[field.field_key]"
                :placeholder="field.placeholder || '请输入'"
                clearable
                class="summary-control"
            />
        </div>
        <el-empty v-if="!fields.length" description="该型号未配置质检摘要字段" :image-size="60" />
    </div>
</template>

<script setup lang="ts">
import type { CheckSummaryField } from './types'

withDefaults(defineProps<{
    /** 摘要字段定义 */
    fields?: CheckSummaryField[]
    /** 字段录入值 { field_key: value }，v-model（就地修改对象属性） */
    modelValue?: Record<string, any>
}>(), {
    fields: () => [],
    modelValue: () => ({})
})

defineEmits<{ (e: 'update:modelValue', value: Record<string, any>): void }>()
</script>

<style lang="scss" scoped>
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px 20px;
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.summary-item__label {
    font-size: 13px;
    color: #606266;
}

.summary-required {
    color: #f56c6c;
    margin-right: 2px;
}

.summary-unit {
    color: #909399;
}

.summary-control {
    width: 100%;
}
</style>
