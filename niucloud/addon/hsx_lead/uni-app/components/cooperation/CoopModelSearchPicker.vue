<template>
    <CoopTaskSheet :show="show" :title="title" :subtitle="subtitle" full @close="$emit('close')">
        <view class="coop-model-picker">
            <CooperationSearch v-model="keyword" placeholder="搜索品牌或型号" :show-action="true" action-text="搜索" @search="load" @custom="load" @clear="load" />
            <view v-if="chosen.length" class="coop-picker-selected"><view class="coop-minor">已选 {{ chosen.length }} 个型号<text v-if="limit"> / 最多 {{ limit }} 个</text></view><DemandModels :items="chosen" removable @remove="removeSelected" /></view>
            <view v-if="selectionError || overLimit" class="coop-selection-error" role="alert">{{ overLimit || selectionError }}</view><CoopState v-if="loading || error" :loading="loading" :error="error" @retry="load" />
            <template v-else><view class="coop-picker-result-title">搜索结果<text>{{ limit ? `最多 ${limit} 个` : '可多选' }}</text></view><view v-for="item in rows" :key="String(item.id)" class="coop-picker-result" @tap="toggle(item)"><view class="coop-picker-model"><view>{{ item.title }}</view><text>{{ item.brand }}</text></view><CoopSelectionMark :selected="isSelected(item)" /></view><CoopState v-if="!rows.length" title="没找到匹配型号" hint="试试完整型号名称，或联系平台补充型号资料" icon="search" /></template>
        </view>
        <template #footer><u-button type="primary" shape="circle" :text="`确认选择（${chosen.length}）`" :disabled="loading || !!error || !!overLimit" @click="confirm" /></template>
    </CoopTaskSheet>
</template>
<script setup lang="ts">
import CooperationSearch from './CooperationSearch.vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import DemandModels from './DemandModels.vue'
import { normalizeDemandModelLimit, uniqueDemandModels, demandModelLimitError, toggleDemandModel } from '../../utils/demand-models'
import { cooperationCatalog, coopError, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopState from './CoopState.vue'
import CoopSelectionMark from './CoopSelectionMark.vue'
const props = withDefaults(defineProps<{ show: boolean; modelValue: CoopRow[]; title?: string; subtitle?: string; max?: number }>(), { title: '选择意向型号', subtitle: '直接搜索并多选客户需要的型号', max: 5 })
const emit = defineEmits<{ (event: 'close'): void; (event: 'update:modelValue', value: CoopRow[]): void }>()
const keyword = ref(''), rows = ref<CoopRow[]>([]), chosen = ref<CoopRow[]>([]), loading = ref(false), error = ref('')
const selectionError = ref(''), limit = computed(() => normalizeDemandModelLimit(props.max)), overLimit = computed(() => demandModelLimitError(chosen.value, limit.value))
function removeSelected(id: string) { chosen.value = chosen.value.filter(item => String(item.id) !== String(id)); selectionError.value = '' }
let sequence = 0, timer: ReturnType<typeof setTimeout> | undefined
function isSelected(row: CoopRow) { return chosen.value.some(item => String(item.id) === String(row.id)) }
function toggle(row: CoopRow) { const result = toggleDemandModel(chosen.value, row, limit.value); selectionError.value = result.error; if (!result.error) chosen.value = result.items }
async function load() { if (timer) { clearTimeout(timer); timer = undefined }; if (!props.show) return; const token = ++sequence; loading.value = true; error.value = ''; try { const response: any = await cooperationCatalog(keyword.value.trim()); if (token === sequence && props.show) rows.value = Array.isArray(response.data) ? response.data : response.data?.items || response.data?.data || [] } catch (cause) { if (token === sequence && props.show) error.value = coopError(cause, '型号读取失败') } finally { if (token === sequence) loading.value = false } }
function confirm() { if (loading.value || error.value || overLimit.value) return; emit('update:modelValue', chosen.value.map(item => ({ ...item }))); emit('close') }
watch(() => props.show, show => { if (show) { chosen.value = uniqueDemandModels(props.modelValue); selectionError.value = ''; keyword.value = ''; void load() } else { sequence++; loading.value = false; if (timer) clearTimeout(timer) } })
watch(keyword, () => { if (timer) clearTimeout(timer); timer = setTimeout(load, 300) }, { flush: 'sync' })
onBeforeUnmount(() => { sequence++; if (timer) clearTimeout(timer) })
</script>
<style scoped lang="scss">.coop-model-picker { padding: 26rpx 30rpx; background: #fff; min-height: 100%; }.coop-picker-selected { padding: 30rpx 0 34rpx; border-bottom: 1rpx solid #edf0f3; }.coop-minor { font-size: 23rpx; color: #88919c; margin-bottom: 17rpx; }.coop-picker-result-title { display: flex; justify-content: space-between; margin: 32rpx 0 10rpx; font-size: 28rpx; font-weight: 600; }.coop-picker-result-title>text { font-size: 23rpx; color: #949ba6; font-weight: 400; }.coop-picker-result { display: flex; gap: 20rpx; align-items: center; min-height: 112rpx; padding: 12rpx 0; box-sizing: border-box; border-bottom: 1rpx solid #edf0f3; }.coop-picker-model { min-width: 0; flex: 1; font-size: 28rpx; color: #363f4b; font-weight: 600; line-height: 1.6; word-break: break-word; }.coop-picker-model>text { color: #949ca7; font-size: 23rpx; font-weight: 400; }.coop-selection-error { padding: 10px 0; color: #a35d46; font-size: 12px; line-height: 1.7; }.coop-picker-selected .coop-minor { line-height: 1.7; }</style>
