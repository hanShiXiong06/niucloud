<script lang="ts">export default { name: 'HsxEntityPicker', inheritAttrs: false }</script>

<script setup lang="ts">
import { computed } from 'vue'
import HsxIcon from '../HsxIcon/index.vue'
import HsxTreeTablePicker from '../HsxTreeTablePicker/index.vue'
import type { AnyRecord, HsxEntityFieldMap, HsxTableColumn, ProFormField } from '../../types'
import type { HsxTreeTablePickerRequest, HsxTreeTablePickerTab } from '../HsxTreeTablePicker/types'

type PickerValue = AnyRecord[] | AnyRecord | null

const props = withDefaults(defineProps<{
    modelValue?: PickerValue
    visible?: boolean
    title?: string
    placeholder?: string
    multiple?: boolean
    disabled?: boolean
    clearable?: boolean
    rowKey?: string
    fieldMap?: HsxEntityFieldMap
    columns?: HsxTableColumn[]
    querySchema?: ProFormField[]
    request?: HsxTreeTablePickerRequest
    data?: AnyRecord[]
    treeData?: AnyRecord[]
    treeNodeKey?: string
    treeProps?: AnyRecord
    tabs?: HsxTreeTablePickerTab[]
    pageSize?: number
    pageSizes?: number[]
    width?: string | number
    bodyHeight?: string
    maxDisplay?: number
}>(), {
    modelValue: null,
    visible: false,
    title: '选择人员',
    placeholder: '请选择',
    multiple: true,
    disabled: false,
    clearable: true,
    rowKey: 'id',
    fieldMap: () => ({ key: 'id', title: 'name', subtitle: 'account', description: 'department', status: 'status' }),
    columns: () => [],
    querySchema: () => [],
    data: () => [],
    treeData: () => [],
    treeNodeKey: 'id',
    treeProps: () => ({ label: 'label', children: 'children' }),
    tabs: () => [],
    pageSize: 10,
    pageSizes: () => [10, 20, 50],
    width: 'min(94vw, 1480px)',
    bodyHeight: 'min(72vh, 820px)',
    maxDisplay: 3
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: PickerValue): void
    (event: 'update:visible', value: boolean): void
    (event: 'confirm', value: PickerValue, rows: AnyRecord[]): void
    (event: 'clear'): void
}>()

const selectedRows = computed(() => Array.isArray(props.modelValue) ? props.modelValue : props.modelValue ? [props.modelValue] : [])
const resolvedColumns = computed<HsxTableColumn[]>(() => props.columns.length ? props.columns : [
    { prop: props.fieldMap.title || 'name', label: '姓名', minWidth: 120 },
    { prop: props.fieldMap.subtitle || 'account', label: '账号', minWidth: 130 },
    { prop: props.fieldMap.description || 'department', label: '所属组织', minWidth: 180 },
    { prop: 'mobile', label: '手机号', minWidth: 140 }
])
const resolvedQuerySchema = computed<ProFormField[]>(() => props.querySchema.length ? props.querySchema : [
    { prop: 'keyword', component: 'input', placeholder: '搜索姓名、账号或手机号' }
])

function labelOf(row: AnyRecord) { return String(row?.[props.fieldMap.title || 'name'] ?? row?.label ?? row?.[props.rowKey] ?? '') }
function open() { if (!props.disabled) emit('update:visible', true) }
function clear(event: MouseEvent) {
    event.stopPropagation()
    const value = props.multiple ? [] : null
    emit('update:modelValue', value)
    emit('clear')
}
function confirm(value: PickerValue, rows: AnyRecord[]) {
    emit('update:modelValue', value)
    emit('confirm', value, rows)
}
</script>

<template>
    <div class="hsx-entity-picker">
        <slot name="trigger" :rows="selectedRows" :open="open" :clear="clear">
            <div class="hsx-entity-picker__trigger" :class="{ 'is-disabled': disabled, 'is-empty': !selectedRows.length }" role="button" :tabindex="disabled ? -1 : 0" @click="open" @keydown.enter="open">
                <div class="hsx-entity-picker__values">
                    <span v-if="!selectedRows.length" class="hsx-entity-picker__placeholder">{{ placeholder }}</span>
                    <span v-for="row in selectedRows.slice(0, maxDisplay)" v-else :key="row[rowKey]" class="hsx-entity-picker__chip">{{ labelOf(row) }}</span>
                    <span v-if="selectedRows.length > maxDisplay" class="hsx-entity-picker__more">+{{ selectedRows.length - maxDisplay }}</span>
                </div>
                <button v-if="clearable && selectedRows.length && !disabled" type="button" class="hsx-entity-picker__clear" @click="clear"><HsxIcon name="element CircleClose" :size="15" /></button>
                <HsxIcon v-else name="element ArrowDown" :size="14" />
            </div>
        </slot>

        <HsxTreeTablePicker
            v-bind="$attrs"
            :visible="visible"
            :model-value="modelValue"
            :title="title"
            :multiple="multiple"
            :row-key="rowKey"
            :columns="resolvedColumns"
            :query-schema="resolvedQuerySchema"
            :request="request"
            :data="data"
            :tree-data="treeData"
            :tree-node-key="treeNodeKey"
            :tree-props="treeProps"
            :tabs="tabs"
            :page-size="pageSize"
            :page-sizes="pageSizes"
            :width="width"
            :body-height="bodyHeight"
            @update:visible="emit('update:visible', $event)"
            @confirm="confirm"
        >
            <template v-for="(_, name) in $slots" #[name]="slotProps"><slot :name="name" v-bind="slotProps || {}" /></template>
        </HsxTreeTablePicker>
    </div>
</template>

<style scoped>
.hsx-entity-picker { width: 100%; min-width: 0; }
.hsx-entity-picker__trigger { display: flex; width: 100%; min-height: 34px; box-sizing: border-box; align-items: center; gap: 8px; padding: 4px 10px; border: 1px solid var(--el-border-color); border-radius: var(--el-border-radius-base); color: var(--hsx-text-secondary); background: var(--hsx-bg-surface); cursor: pointer; transition: border-color var(--hsx-motion-fast); }
.hsx-entity-picker__trigger:hover,
.hsx-entity-picker__trigger:focus-visible { border-color: var(--hsx-color-primary); outline: none; }
.hsx-entity-picker__trigger.is-disabled { opacity: .56; cursor: not-allowed; }
.hsx-entity-picker__values { display: flex; min-width: 0; flex: 1; flex-wrap: wrap; align-items: center; gap: 5px; }
.hsx-entity-picker__placeholder { color: var(--el-text-color-placeholder); }
.hsx-entity-picker__chip,
.hsx-entity-picker__more { max-width: 180px; overflow: hidden; padding: 2px 7px; border-radius: 5px; color: var(--hsx-text-primary); background: var(--hsx-bg-muted); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.hsx-entity-picker__clear { display: inline-flex; padding: 0; border: 0; color: var(--hsx-text-secondary); background: transparent; cursor: pointer; }
</style>
