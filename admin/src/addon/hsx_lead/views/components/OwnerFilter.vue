<template>
    <el-popover v-model:visible="visible" trigger="click" placement="bottom-start" :width="320" popper-class="hsx-lead-filter-popper" @show="begin">
        <template #reference><el-button class="lead-filter-bar__chip" :class="{ 'is-active': active }" :aria-expanded="visible" aria-haspopup="dialog"><span>归属员工</span><strong v-if="active">{{ appliedName }}</strong><el-icon><ArrowDown /></el-icon></el-button></template>
        <section role="dialog" aria-label="归属员工筛选" @keydown.esc.stop.prevent="visible = false"><header class="lead-owner-filter-heading"><strong>按归属员工检索</strong><p>只筛选内部归属，不改变承接门店。</p></header><OwnerSelect v-model="draft" :selected-name="draftName" include-unassigned :teleported="false" placeholder="全部员工；可搜索姓名或账号" @selected="draftName = $event?.name || ''" /><footer class="lead-owner-filter-footer"><el-button link @click="clear">不限归属</el-button><span /><el-button @click="visible = false">取消</el-button><el-button type="primary" @click="apply">应用</el-button></footer></section>
    </el-popover>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { ArrowDown } from '@element-plus/icons-vue'
import OwnerSelect from './OwnerSelect.vue'
const props = withDefaults(defineProps<{ modelValue?: number | string; selectedName?: string }>(), { modelValue: '', selectedName: '' })
const emit = defineEmits<{ (event: 'apply', value: { owner_uid: number | '' }): void }>()
const visible = ref(false), draft = ref<number | string>(''), draftName = ref(''), applied = ref('')
const active = computed(() => props.modelValue !== '' && props.modelValue != null)
const appliedName = computed(() => Number(props.modelValue) === 0 ? '未分配（历史资料）' : props.selectedName || applied.value || '指定员工')
function begin() { draft.value = props.modelValue; draftName.value = appliedName.value }
function apply() { applied.value = draftName.value; visible.value = false; emit('apply', { owner_uid: draft.value === '' ? '' : Number(draft.value) }) }
function clear() { draft.value = ''; draftName.value = ''; apply() }
</script>
<style scoped>
.lead-filter-bar__chip.el-button { margin: 0; height: 34px; padding: 0 11px; border-radius: 7px; border-color: var(--hsx-border-color, #dde4ed); background: var(--hsx-bg-surface, #fff); }.lead-filter-bar__chip :deep(>span) { gap: 6px; }.lead-filter-bar__chip strong { max-width: 140px; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }.lead-filter-bar__chip.is-active { color: var(--el-color-primary); background: var(--el-color-primary-light-9); border-color: var(--el-color-primary-light-7); }
.lead-owner-filter-heading strong { font-size: 14px; }.lead-owner-filter-heading p { font-size: 12px; color: var(--el-text-color-secondary); line-height: 1.7; margin: 7px 0 15px; }.lead-owner-filter-footer { display: flex; align-items: center; gap: 8px; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--el-border-color-lighter); }.lead-owner-filter-footer > span { flex: 1; }.lead-owner-filter-footer .el-button + .el-button { margin-left: 0; }
</style>
