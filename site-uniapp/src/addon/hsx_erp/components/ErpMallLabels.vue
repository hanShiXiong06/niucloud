<template>
    <view class="mall-labels">
        <view class="mall-labels__entry" :class="{ 'is-disabled': disabled }" role="button" @click="open">
            <text class="mall-labels__title">商城标签</text><text class="mall-labels__summary" :class="{ placeholder: !selected.length }">{{ selectedSummary || '选择标签（选填）' }}</text><u-icon name="arrow-right" size="13" color="#94a3b8" />
        </view>
        <text v-if="unavailable.length" class="mall-labels__warning">{{ unavailable.length }} 个原标签已失效，已保留，请核对。</text>
        <text v-else-if="!options.length" class="mall-labels__note">商城暂无可选标签，可稍后补充。</text>
        <u-popup :show="show" mode="bottom" round="20" :z-index="10140" :safe-area-inset-bottom="true" @close="close">
            <view v-if="show" class="label-picker" @touchmove.stop.prevent="noop">
                <view class="label-picker__head"><view class="label-picker__heading"><text class="label-picker__title">选择商城标签</text><text class="label-picker__subtitle">可多选 · 已选 {{ selected.length }} 个</text></view><view class="label-picker__close" role="button" aria-label="关闭标签选择" @click="close"><u-icon name="close" size="21" color="#64748b" /></view></view>
                <view class="label-picker__search"><u-icon name="search" size="18" color="#94a3b8" /><input v-model="keyword" class="label-picker__input" placeholder="搜索标签" confirm-type="search" maxlength="100" @confirm="hideKeyboard" /><view v-if="keyword" class="label-picker__clear" role="button" aria-label="清空搜索" @click="keyword = ''"><u-icon name="close-circle-fill" size="17" color="#94a3b8" /></view></view>
                <scroll-view v-if="groups.length > 1" scroll-x class="label-picker__group-scroll" :show-scrollbar="false" @touchmove.stop="noop"><view class="label-picker__groups"><view class="label-picker__group" :class="{ 'is-active': !activeGroup }" @click="activeGroup = ''">全部</view><view v-for="group in groups" :key="group.key" class="label-picker__group" :class="{ 'is-active': activeGroup === group.key }" @click="activeGroup = group.key">{{ group.name }}</view></view></scroll-view>
                <view v-if="unavailable.length" class="label-picker__warning"><text>{{ unavailable.length }} 个原标签已失效，已保留。</text><text class="label-picker__link" @click="removeUnavailable">移除失效标签</text></view>
                <scroll-view :key="`${activeGroup}-${keyword}`" scroll-y class="label-picker__body" :show-scrollbar="false" @touchmove.stop="noop" @scrolltolower="loadMore">
                    <view v-if="visibleOptions.length" class="label-picker__choices"><view v-for="label in visibleOptions" :key="label.label_id" class="label-picker__choice" :class="{ 'is-selected': selected.includes(label.label_id), 'is-long': label.label_name.length > 14 }" role="checkbox" :aria-checked="selected.includes(label.label_id)" @click="toggle(label.label_id)"><text>{{ label.label_name }}</text><u-icon v-if="selected.includes(label.label_id)" name="checkmark" size="15" color="#2563eb" /></view></view>
                    <text v-else class="label-picker__empty">{{ keyword ? '没有匹配标签，试试其他关键词' : activeGroup ? '此分组暂无标签' : '商城暂无可选标签' }}</text>
                    <view v-if="hasMore" class="label-picker__more" @click="loadMore">继续显示</view>
                </scroll-view>
                <view class="label-picker__foot"><text class="label-picker__count">已选 {{ selected.length }} 个</text><view class="label-picker__done" role="button" @click="close">完成</view></view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
type MallLabel = { label_id: number; label_name: string; group_id?: number; group_name?: string }
const props = withDefaults(defineProps<{ modelValue: number[]; labels: MallLabel[]; disabled?: boolean }>(), { disabled: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: number[]): void }>()
const show = ref(false), keyword = ref(''), activeGroup = ref(''), visibleCount = ref(40)
const cleanIds = (value: unknown) => [...new Set((Array.isArray(value) ? value : []).filter(id => typeof id === 'number' || typeof id === 'string').map(Number).filter(id => Number.isSafeInteger(id) && id > 0))]
const selected = computed(() => cleanIds(props.modelValue))
const options = computed(() => {
    const rows = (Array.isArray(props.labels) ? props.labels : []).filter(row => row && Number.isSafeInteger(Number(row.label_id)) && Number(row.label_id) > 0 && typeof row.label_name === 'string' && row.label_name.trim())
    const seen = new Set<number>(), conflicts = new Set(rows.filter(row => rows.some(other => Number(other.label_id) === Number(row.label_id) && other.label_name.trim() !== row.label_name.trim())).map(row => Number(row.label_id)))
    return rows.filter(row => { const id = Number(row.label_id); if (seen.has(id) || conflicts.has(id)) return false; seen.add(id); return true }).map(row => ({ ...row, label_id: Number(row.label_id), label_name: row.label_name.trim(), groupKey: String(row.group_id || row.group_name || 'default') }))
})
const unavailable = computed(() => selected.value.filter(id => !options.value.some(label => label.label_id === id)))
const selectedSummary = computed(() => [selected.value.map(id => options.value.find(label => label.label_id === id)?.label_name).filter(Boolean).join('、'), unavailable.value.length ? `${unavailable.value.length} 个失效标签` : ''].filter(Boolean).join(' · '))
const groups = computed(() => {
    const result: { key: string; name: string }[] = []
    for (const label of options.value) if (!result.some(group => group.key === label.groupKey)) result.push({ key: label.groupKey, name: label.group_name || '其他标签' })
    return result
})
const filteredOptions = computed(() => options.value.filter(label => (!activeGroup.value || label.groupKey === activeGroup.value) && label.label_name.toLocaleLowerCase().includes(keyword.value.trim().toLocaleLowerCase())))
const visibleOptions = computed(() => filteredOptions.value.slice(0, visibleCount.value)), hasMore = computed(() => visibleCount.value < filteredOptions.value.length)
function noop() {}
function hideKeyboard() { if (typeof uni !== 'undefined' && typeof uni.hideKeyboard === 'function') uni.hideKeyboard() }
function open() { if (props.disabled) return; keyword.value = ''; activeGroup.value = ''; visibleCount.value = 40; show.value = true }
function close() { show.value = false; hideKeyboard() }
function toggle(id: number) {
    if (props.disabled || !options.value.some(label => label.label_id === Number(id))) return
    emit('update:modelValue', selected.value.includes(id) ? selected.value.filter(value => value !== id) : [...selected.value, id])
}
function removeUnavailable() { if (!props.disabled) emit('update:modelValue', selected.value.filter(id => !unavailable.value.includes(id))) }
function loadMore() { if (hasMore.value) visibleCount.value += 40 }
watch([keyword, activeGroup], () => { visibleCount.value = 40 })
watch(groups, next => { if (activeGroup.value && !next.some(group => group.key === activeGroup.value)) activeGroup.value = '' })
</script>

<style scoped lang="scss">
.label-picker__input{border:0;outline:0;padding:0;background:transparent;box-sizing:border-box}
.mall-labels{min-width:0;margin:14rpx 0}.mall-labels__entry{display:flex;align-items:center;gap:14rpx;min-height:86rpx;border-bottom:1rpx solid #edf1f5}.mall-labels__title{font-size:27rpx;color:#334155;flex-shrink:0}.mall-labels__summary{flex:1;min-width:0;text-align:right;color:#0f172a;font-size:26rpx;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.placeholder{color:#94a3b8}.mall-labels__note,.mall-labels__warning{display:block;margin-top:8rpx;color:#64748b;font-size:23rpx;line-height:1.6}.mall-labels__warning{color:#b45309}.is-disabled{opacity:.55}
.label-picker{display:flex;flex-direction:column;height:74vh;max-height:calc(100vh - 96rpx);overflow:hidden;background:#fff;border-radius:24rpx 24rpx 0 0;color:#0f172a;box-sizing:border-box}.label-picker__head{display:flex;align-items:center;justify-content:space-between;flex-shrink:0;padding:24rpx 16rpx 12rpx 28rpx}.label-picker__heading{display:flex;flex-direction:column;gap:6rpx;min-width:0}.label-picker__title{font-size:17px;font-weight:650;line-height:1.4}.label-picker__subtitle{font-size:12px;color:#64748b;line-height:1.5}.label-picker__close{display:flex;align-items:center;justify-content:center;flex-shrink:0;width:86rpx;height:86rpx}.label-picker__search{display:flex;align-items:center;gap:12rpx;margin:0 28rpx 14rpx;padding:0 20rpx;background:#f1f5f9;border-radius:14rpx;flex-shrink:0}.label-picker__input{flex:1;min-width:0;height:76rpx;font-size:27rpx}.label-picker__clear{display:flex;align-items:center;justify-content:center;min-width:62rpx;height:76rpx}
.label-picker__group-scroll{width:100%;flex-shrink:0;height:82rpx;white-space:nowrap}.label-picker__groups{display:inline-flex;align-items:center;gap:12rpx;padding:6rpx 28rpx 16rpx}.label-picker__group{padding:12rpx 24rpx;border:1rpx solid #e2e8f0;border-radius:30rpx;font-size:25rpx;color:#64748b;line-height:1.4}.label-picker__group.is-active{color:#2563eb;border-color:#bfdbfe;background:#eff6ff}.label-picker__warning{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8rpx 16rpx;padding:12rpx 28rpx;color:#b45309;font-size:23rpx;line-height:1.6;flex-shrink:0}.label-picker__link{color:#2563eb;padding:10rpx 0}.label-picker__body{height:0;min-height:0;flex:1;overscroll-behavior:contain;box-sizing:border-box}.label-picker__choices{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16rpx;padding:18rpx 28rpx 24rpx}.label-picker__choice{display:flex;align-items:center;justify-content:space-between;gap:12rpx;min-height:88rpx;padding:16rpx 20rpx;border:1rpx solid #e2e8f0;border-radius:14rpx;background:#f8fafc;font-size:27rpx;line-height:1.5;box-sizing:border-box}.label-picker__choice>text{min-width:0;overflow-wrap:anywhere}.label-picker__choice.is-selected{border-color:#93c5fd;background:#eff6ff;color:#1d4ed8}.label-picker__choice.is-long{grid-column:1/-1}.label-picker__empty{display:block;text-align:center;color:#94a3b8;padding:70rpx 28rpx;font-size:26rpx;line-height:1.8}.label-picker__more{text-align:center;padding:24rpx;font-size:26rpx;color:#2563eb}.label-picker__foot{display:flex;align-items:center;gap:24rpx;flex-shrink:0;padding:18rpx 28rpx;border-top:1rpx solid #e2e8f0;background:#fff}.label-picker__count{color:#64748b;font-size:25rpx;flex-shrink:0}.label-picker__done{display:flex;align-items:center;justify-content:center;flex:1;min-height:82rpx;border-radius:14rpx;background:#2563eb;color:#fff;font-size:28rpx;font-weight:600}
</style>
