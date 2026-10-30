<template>
    <section>
        <el-alert :title="task.demand_mode === 'all' || task.lead?.demand_mode === 'all' ? '分别记录本批实际卖出的设备；没有卖出的项保持 0，剩余需求继续跟进。' : '任选一款：本批只选一种机型；部分成交后，后续只能继续该款或结束剩余。'" type="info" :closable="false" class="lead-banner" />
        <HsxTable :columns="columns" :data="modelValue" row-key="catalog_id"><template #device="{ row }"><div class="lead-cell"><strong>{{ row.title }}</strong><small>需求 {{ row.quantity }} · 已核实成交 {{ row.confirmed_quantity }} · 剩余 {{ row.remaining_quantity }}</small><small v-if="locked(row)">已锁定其他备选，本项不可继续申报</small></div></template><template #quantity="{ row }"><el-input-number v-input-number-a11y :model-value="row.sale_quantity" :min="0" :max="Number(row.remaining_quantity)" :precision="0" controls-position="right" :disabled="locked(row) || Number(row.remaining_quantity) <= 0" style="width: 100px" @update:model-value="update(row, 'sale_quantity', $event)" /></template><template #price="{ row }"><el-input-number v-input-number-a11y :model-value="row.unit_price" :min="0.01" :max="99999999" :precision="2" :controls="false" :disabled="!Number(row.sale_quantity)" placeholder="实际单价" style="width: 115px" @update:model-value="update(row, 'unit_price', $event)" /></template><template #subtotal="{ row }">{{ money(Number(row.sale_quantity || 0) * Number(row.unit_price || 0)) }}</template></HsxTable>
        <div class="lead-summary" style="margin-top: 16px"><span>本批金额预览<b>{{ money(saleTotal(modelValue)) }}</b></span><span>最终金额由服务器按售出数量 × 实际单价重新计算</span></div>
    </section>
</template>
<script setup lang="ts">
import { vInputNumberA11y } from '../../directives/inputNumberA11y'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import { saleTotal } from '../../composables/deal'
import { money, type Row } from '../../composables/useLeadUi'
const props = defineProps<{ modelValue: Row[], task: Row }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: Row[]): void }>()
const columns = [{ prop: 'title', label: '客户需求 / 成交进度', slot: 'device', minWidth: 230 }, { prop: 'sale_quantity', label: '本批售出', slot: 'quantity', width: 120 }, { prop: 'unit_price', label: '实际单价（元）', slot: 'price', width: 135 }, { prop: 'subtotal', label: '小计', slot: 'subtotal', width: 110 }]
function locked(row: Row) { return !!props.task.any_selected_catalog_id && String(row.catalog_id) !== String(props.task.any_selected_catalog_id) }
function update(row: Row, key: string, value: any) {
    const item = props.modelValue.find(item => String(item.catalog_id) === String(row.catalog_id))
    if (!item) return
    // Keep editable row identity stable while emitting the updated parent list.
    item[key] = value
    emit('update:modelValue', [...props.modelValue])
}
</script>
