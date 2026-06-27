<template>
    <el-form inline class="erp-filter-bar" @submit.prevent>
        <el-form-item v-for="f in fields" :key="f.key" :label="f.label || ''">
            <!-- 静态下拉(业务类型/状态/经手人等):能选不输入 -->
            <el-select
                v-if="f.type === 'select'"
                v-model="model[f.key]"
                :placeholder="f.placeholder || '全部'"
                clearable
                :filterable="f.filterable !== false"
                :style="widthStyle(f, 160)"
                @change="onChange"
            >
                <el-option v-for="o in (f.options || [])" :key="o.value" :label="o.label" :value="o.value" />
            </el-select>

            <!-- 实体异步检索(主体/对接人/供应商):远程搜索,选中取 ID -->
            <CounterpartySelect
                v-else-if="f.type === 'entity'"
                v-model="model[f.key]"
                :value-field="f.valueField || 'counterparty_id'"
                :role-type="f.roleType || ''"
                :placeholder="f.placeholder || '搜索姓名 / 手机号'"
                :style="widthStyle(f, 200)"
                @update:modelValue="onChange"
            />

            <!-- IMEI:默认精确,可配模糊 -->
            <el-input
                v-else-if="f.type === 'imei'"
                v-model.trim="model[f.key]"
                :placeholder="f.placeholder || (f.match === 'fuzzy' ? '串号(支持模糊)' : '串号精确查询')"
                clearable
                :style="widthStyle(f, 180)"
                @keyup.enter="onSearch"
                @clear="onChange"
            />

            <!-- 普通文本模糊(单号等) -->
            <el-input
                v-else-if="f.type === 'text'"
                v-model.trim="model[f.key]"
                :placeholder="f.placeholder || ''"
                clearable
                :style="widthStyle(f, 200)"
                @keyup.enter="onSearch"
                @clear="onChange"
            />

            <!-- 时间段 -->
            <el-date-picker
                v-else-if="f.type === 'daterange'"
                v-model="model[f.key]"
                type="daterange"
                value-format="X"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                :style="widthStyle(f, 260)"
                @change="onChange"
            />

            <!-- 金额/数值区间 -->
            <template v-else-if="f.type === 'amountrange'">
                <el-input
                    v-model="model[f.minKey || 'amount_min']"
                    :placeholder="f.minPlaceholder || '最低'"
                    clearable
                    style="width: 90px"
                    @keyup.enter="onSearch"
                />
                <span class="mx-1 text-gray-400">~</span>
                <el-input
                    v-model="model[f.maxKey || 'amount_max']"
                    :placeholder="f.maxPlaceholder || '最高'"
                    clearable
                    style="width: 90px"
                    @keyup.enter="onSearch"
                />
            </template>
        </el-form-item>

        <el-form-item v-if="showActions">
            <el-button type="primary" @click="onSearch">查询</el-button>
            <el-button @click="onReset">重置</el-button>
        </el-form-item>
    </el-form>
</template>

<script lang="ts" setup>
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'

/**
 * ERP 通用检索栏(schema 驱动,可复用)。
 * 设计原则:能选的用 select(实体走异步远程)、能精确的不模糊(IMEI 默认精确)、统一布局。
 * 纯组件,不耦合接口:选项(经手人/业务类型等)由父级通过 field.options 传入。
 *
 * 用法:
 *   <ErpFilterBar v-model="search" :fields="fields" @search="load" @reset="onReset" />
 * fields 每项: { key, label, type, placeholder?, options?, valueField?, roleType?, match?, width?, minKey?, maxKey? }
 *   type: 'select' | 'entity' | 'imei' | 'text' | 'daterange' | 'amountrange'
 */
interface FilterField {
    key: string
    label?: string
    type: 'select' | 'entity' | 'imei' | 'text' | 'daterange' | 'amountrange'
    placeholder?: string
    options?: Array<{ label: string; value: any }>
    valueField?: string
    roleType?: string
    match?: 'exact' | 'fuzzy'
    filterable?: boolean
    width?: number
    minKey?: string
    maxKey?: string
    minPlaceholder?: string
    maxPlaceholder?: string
}

const props = withDefaults(defineProps<{
    modelValue: Record<string, any>
    fields: FilterField[]
    showActions?: boolean
}>(), {
    showActions: true,
})

const emit = defineEmits<{
    (e: 'update:modelValue', v: Record<string, any>): void
    (e: 'search'): void
    (e: 'reset'): void
    (e: 'change'): void
}>()

// 直接在传入对象上读写(父级是 reactive 对象),双向同步靠引用
const model = props.modelValue

const widthStyle = (f: FilterField, fallback: number) => `width:${f.width || fallback}px`

// select/日期变更:即时同步,并抛 change(父级可选择即时刷新)
const onChange = () => {
    emit('update:modelValue', model)
    emit('change')
}
// 回车/查询按钮:抛 search
const onSearch = () => {
    emit('update:modelValue', model)
    emit('search')
}
const onReset = () => {
    emit('reset')
}
</script>

<style scoped>
.erp-filter-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 0;
}
.erp-filter-bar :deep(.el-form-item) {
    margin-right: 12px;
    margin-bottom: 8px;
}
</style>
