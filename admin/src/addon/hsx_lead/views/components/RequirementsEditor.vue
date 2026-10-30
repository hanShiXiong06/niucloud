<template>
    <section class="lead-compact-requirements">
        <div class="lead-batch-bar"><span class="lead-muted">已勾选 {{ selectedIds.length }} 项；仅点击应用后覆盖</span><div class="lead-batch-group"><el-checkbox v-model="batch.quantity_enabled">数量</el-checkbox><el-input-number v-input-number-a11y v-model="batch.quantity" :min="1" :max="999" :precision="0" size="small" :disabled="!batch.quantity_enabled" /></div><div class="lead-batch-group lead-batch-budget"><el-checkbox v-model="batch.budget_enabled">单台预算</el-checkbox><el-input-number v-input-number-a11y v-model="batch.budget_min" :min="0" :precision="2" size="small" placeholder="最低" :disabled="!batch.budget_enabled" /><span>至</span><el-input-number v-input-number-a11y v-model="batch.budget_max" :min="0" :precision="2" size="small" placeholder="最高" :disabled="!batch.budget_enabled" /></div><el-button size="small" :disabled="!selectedIds.length || (!batch.quantity_enabled && !batch.budget_enabled)" @click="applyBatch">应用到已勾选项</el-button></div>
        <HsxTable :columns="columns" :data="modelValue" row-key="catalog_id" empty-text="先从上方设备清单勾选意向机型" @selection-change="selectedIds = $event.map(item => String(item.catalog_id))"><template #device="{ row }"><div class="lead-cell"><strong>{{ row.title }}</strong><small>{{ row.brand }}</small></div></template><template #quantity="{ row }"><el-input-number v-input-number-a11y :model-value="row.quantity" :min="1" :max="999" :precision="0" controls-position="right" size="small" style="width: 100px" @update:model-value="update(row, 'quantity', $event)" /></template><template #budget="{ row }"><div class="lead-compact-budget"><el-input-number v-input-number-a11y :model-value="row.budget_min" :min="0" :precision="2" :controls="false" size="small" placeholder="不限" @update:model-value="update(row, 'budget_min', $event)" /><span>–</span><el-input-number v-input-number-a11y :model-value="row.budget_max" :min="0" :precision="2" :controls="false" size="small" placeholder="不限" @update:model-value="update(row, 'budget_max', $event)" /></div></template><template #more="{ row }"><div class="lead-actions"><el-popover placement="left" :width="320" trigger="click"><template #reference><el-button link type="primary">{{ row.remark ? '已填要求' : '补充要求' }}</el-button></template><p class="lead-muted">{{ row.title }} · 本项补充要求</p><el-input :model-value="row.remark" type="textarea" :rows="4" maxlength="1000" placeholder="颜色、成色、到店时间等（可不填）" @update:model-value="update(row, 'remark', $event)" /></el-popover><el-button link type="danger" @click="remove(row)">移除</el-button></div></template></HsxTable>
        <p class="lead-muted" style="margin-top: 12px">数量是客户需求台数，不代表库存已确认。预算按单台记录，留空为未确定。</p>
    </section>
</template>
<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { vInputNumberA11y } from '../../directives/inputNumberA11y'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import type { Row } from '../../composables/useLeadUi'
const props = defineProps<{ modelValue: Row[] }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: Row[]): void }>()
const selectedIds = ref<string[]>([]), batch = reactive({ quantity_enabled: false, quantity: 1, budget_enabled: false, budget_min: undefined as number | undefined, budget_max: undefined as number | undefined })
const columns = [{ type: 'selection' as const, width: 42 }, { prop: 'title', label: '意向机型', slot: 'device', minWidth: 180 }, { prop: 'quantity', label: '台数', slot: 'quantity', width: 115 }, { prop: 'budget', label: '单台预算（元）', slot: 'budget', minWidth: 210 }, { prop: 'more', label: '更多', slot: 'more', width: 155 }]
function update(row: Row, key: string, value: any) {
    const item = props.modelValue.find(item => String(item.catalog_id) === String(row.catalog_id))
    if (!item) return
    item[key] = value
    emit('update:modelValue', [...props.modelValue])
}
function remove(row: Row) { emit('update:modelValue', props.modelValue.filter(item => String(item.catalog_id) !== String(row.catalog_id))); selectedIds.value = selectedIds.value.filter(id => id !== String(row.catalog_id)) }
async function applyBatch() { if (!selectedIds.value.length) return; if (batch.budget_enabled && Number(batch.budget_max) > 0 && Number(batch.budget_min) > Number(batch.budget_max)) { ElMessage.warning('最高预算不能小于最低预算'); return }; try { await ElMessageBox.confirm(`将覆盖已勾选 ${selectedIds.value.length} 项的${[batch.quantity_enabled ? '数量' : '', batch.budget_enabled ? '单台预算' : ''].filter(Boolean).join('和')}，其他项保持不变。`, '确认批量应用', { type: 'warning', confirmButtonText: '应用到已勾选项' }) } catch { return }; emit('update:modelValue', props.modelValue.map(item => selectedIds.value.includes(String(item.catalog_id)) ? { ...item, ...(batch.quantity_enabled ? { quantity: batch.quantity } : {}), ...(batch.budget_enabled ? { budget_min: batch.budget_min, budget_max: batch.budget_max } : {}) } : item)); ElMessage.success('仅已勾选的需求项已更新') }
</script>
