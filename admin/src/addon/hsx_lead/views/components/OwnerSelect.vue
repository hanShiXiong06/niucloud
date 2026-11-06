<template>
    <div class="lead-owner-select">
        <el-select :model-value="modelValue === '' || modelValue == null ? undefined : Number(modelValue)" filterable remote clearable :remote-method="scheduleSearch" :loading="loading" :disabled="disabled" :teleported="teleported" :placeholder="placeholder" aria-label="搜索归属员工" @focus="opened(true)" @visible-change="opened" @change="select">
            <el-option v-if="includeUnassigned" label="未分配" :value="0" />
            <el-option v-for="item in visibleOptions" :key="item.uid" :label="item.name" :value="item.uid" :disabled="Number(item.uid) === excludeUid" />
            <template #footer><div class="lead-owner-select__footer"><span>{{ loading ? '正在查询…' : '已显示 ' + rows.length + ' / ' + total + ' 人' }}</span><el-button v-if="rows.length < total" link type="primary" :loading="loading" @click.stop="loadMore">加载更多</el-button><el-button v-else-if="error" link type="primary" @click.stop="load(false)">重试</el-button></div></template>
        </el-select>
        <p v-if="error" class="lead-error" role="alert">{{ error }}</p>
    </div>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { getCooperationOwners } from '../../api/cooperation'
import { errorText, type Row } from '../../composables/useLeadUi'
const props = withDefaults(defineProps<{ modelValue?: number | string; selectedName?: string; includeUnassigned?: boolean; excludeUid?: number; disabled?: boolean; teleported?: boolean; placeholder?: string }>(), { modelValue: '', selectedName: '', includeUnassigned: false, excludeUid: -1, disabled: false, teleported: true, placeholder: '输入姓名或账号搜索员工' })
const emit = defineEmits<{ (event: 'update:modelValue', value: number | ''): void; (event: 'selected', value: Row | null): void }>()
const rows = ref<Row[]>([]), total = ref(0), page = ref(0), loading = ref(false), error = ref(''), keyword = ref(''), selected = ref<Row | null>(null)
const visibleOptions = computed(() => { const value = Number(props.modelValue); if (!value || rows.value.some(row => Number(row.uid) === value)) return rows.value; return [{ uid: value, name: selected.value?.uid === value ? selected.value.name : props.selectedName || '已选择员工' }, ...rows.value] })
let sequence = 0, disposed = false, timer: ReturnType<typeof setTimeout> | undefined
async function load(append = false) {
    if (disposed || props.disabled || append && loading.value) return
    const token = ++sequence, next = append ? page.value + 1 : 1, q = keyword.value
    loading.value = true; error.value = ''
    try {
        const response = await getCooperationOwners({ q, page: next, limit: 20 })
        if (disposed || token !== sequence) return
        const data = response.data || {}, incoming = (Array.isArray(data.data) ? data.data : []).filter((row: Row) => Number.isSafeInteger(Number(row.uid)) && Number(row.uid) > 0).map((row: Row) => ({ uid: Number(row.uid), name: String(row.name || '未命名员工') }))
        rows.value = append ? [...rows.value, ...incoming.filter((row: Row) => !rows.value.some(old => old.uid === row.uid))] : incoming
        total.value = Math.max(rows.value.length, Number(data.total || 0)); page.value = next
    } catch (cause) { if (!disposed && token === sequence) { if (!append) rows.value = []; error.value = errorText(cause, '员工候选读取失败，请重试。') } }
    finally { if (!disposed && token === sequence) loading.value = false }
}
function scheduleSearch(value: string) { keyword.value = value.trim(); sequence++; loading.value = false; if (timer) clearTimeout(timer); timer = setTimeout(() => { timer = undefined; void load(false) }, 250) }
function opened(value: boolean) { if (value && !rows.value.length && !loading.value) { if (timer) clearTimeout(timer); timer = undefined; void load(false) } }
function loadMore() { if (!loading.value && rows.value.length < total.value) void load(true) }
function select(value: any) { const uid = value === '' || value === undefined || value === null ? '' : Number(value); if (uid !== '' && (!Number.isSafeInteger(uid) || uid < 0 || uid === props.excludeUid)) return; selected.value = uid === '' ? null : uid === 0 ? { uid: 0, name: '未分配' } : visibleOptions.value.find(item => item.uid === uid) || null; emit('update:modelValue', uid); emit('selected', selected.value) }
onBeforeUnmount(() => { disposed = true; sequence++; if (timer) clearTimeout(timer) })
</script>
<style scoped>
.lead-owner-select, .lead-owner-select :deep(.el-select) { width: 100%; }.lead-owner-select__footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; font-size: 12px; color: var(--el-text-color-secondary); }.lead-owner-select .lead-error { margin: 8px 0 0; font-size: 12px; line-height: 1.6; }
</style>
