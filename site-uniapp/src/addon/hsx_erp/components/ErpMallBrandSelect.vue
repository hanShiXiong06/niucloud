<template>
    <view class="mall-brand">
        <view class="mall-brand__entry" :class="{ 'is-disabled': disabled }" role="button" @click="open"><text class="mall-brand__label">商城品牌</text><text class="mall-brand__value" :class="{ placeholder: !currentBrand }">{{ currentBrand?.brand_name || (Number(modelValue) > 0 ? (disabled ? '原品牌待核对' : '原品牌已失效，请重选') : '选择品牌（选填）') }}</text><u-icon name="arrow-right" size="13" color="#94a3b8" /></view>
        <u-popup :show="show" mode="bottom" round="20" :z-index="10140" :safe-area-inset-bottom="true" @close="close">
            <view v-if="show" class="brand-picker" @touchmove.stop.prevent="noop">
                <view class="brand-picker__head"><text class="brand-picker__title">选择商城品牌</text><view class="brand-picker__close" role="button" aria-label="关闭品牌选择" @click="close"><u-icon name="close" size="21" color="#64748b" /></view></view>
                <view class="brand-picker__search"><u-icon name="search" size="18" color="#94a3b8" /><input v-model="keyword" class="brand-picker__input" placeholder="搜索品牌" confirm-type="search" maxlength="100" @confirm="hideKeyboard" /><view v-if="keyword" class="brand-picker__clear" role="button" aria-label="清空搜索" @click="keyword = ''"><u-icon name="close-circle-fill" size="17" color="#94a3b8" /></view></view>
                <text v-if="Number(modelValue) > 0 && !currentBrand" class="brand-picker__warning">原品牌已失效，请重新选择或清空。</text>
                <scroll-view :key="keyword" scroll-y class="brand-picker__body" :show-scrollbar="false" @touchmove.stop="noop" @scrolltolower="loadMore">
                    <view v-for="brand in visibleOptions" :key="brand.brand_id" class="brand-picker__item" :class="{ 'is-selected': Number(modelValue) === brand.brand_id }" role="radio" :aria-checked="Number(modelValue) === brand.brand_id" @click="choose(brand.brand_id)"><text>{{ brand.brand_name }}</text><u-icon v-if="Number(modelValue) === brand.brand_id" name="checkmark" size="17" color="#2563eb" /></view>
                    <text v-if="!filteredOptions.length" class="brand-picker__empty">{{ keyword ? '没有匹配品牌，试试其他关键词' : '商城暂无可选品牌' }}</text><view v-if="hasMore" class="brand-picker__more" @click="loadMore">继续显示</view>
                </scroll-view>
                <view class="brand-picker__foot"><text v-if="Number(modelValue) > 0" class="brand-picker__remove" @click="choose(0)">清空品牌</text><view class="brand-picker__done" role="button" @click="close">完成</view></view>
            </view>
        </u-popup>
    </view>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
type MallBrand = { brand_id: number; brand_name: string }
const props = withDefaults(defineProps<{ modelValue: number; brands: MallBrand[]; disabled?: boolean }>(), { disabled: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: number): void; (event: 'change', value: MallBrand): void }>()
const show = ref(false), keyword = ref(''), visibleCount = ref(40)
const options = computed(() => {
    const rows = (Array.isArray(props.brands) ? props.brands : []).filter(row => row && Number.isSafeInteger(Number(row.brand_id)) && Number(row.brand_id) > 0 && typeof row.brand_name === 'string' && row.brand_name.trim())
    const seen = new Set<number>(), conflicts = new Set(rows.filter(row => rows.some(other => Number(other.brand_id) === Number(row.brand_id) && other.brand_name.trim() !== row.brand_name.trim())).map(row => Number(row.brand_id)))
    return rows.filter(row => { const id = Number(row.brand_id); if (seen.has(id) || conflicts.has(id)) return false; seen.add(id); return true }).map(row => ({ brand_id: Number(row.brand_id), brand_name: row.brand_name.trim() }))
})
const currentBrand = computed(() => options.value.find(brand => brand.brand_id === Number(props.modelValue)))
const filteredOptions = computed(() => options.value.filter(brand => brand.brand_name.toLocaleLowerCase().includes(keyword.value.trim().toLocaleLowerCase())))
const visibleOptions = computed(() => filteredOptions.value.slice(0, visibleCount.value)), hasMore = computed(() => visibleCount.value < filteredOptions.value.length)
function noop() {}
function hideKeyboard() { if (typeof uni !== 'undefined' && typeof uni.hideKeyboard === 'function') uni.hideKeyboard() }
function open() { if (props.disabled) return; keyword.value = ''; visibleCount.value = 40; show.value = true }
function close() { show.value = false; hideKeyboard() }
function choose(value: unknown) {
    if (props.disabled) return
    const id = value == null || value === '' ? 0 : Number(value)
    if (!Number.isSafeInteger(id) || id < 0) return
    const brand = options.value.find(option => option.brand_id === id)
    if (id > 0 && !brand) return
    emit('update:modelValue', id); emit('change', brand || { brand_id: 0, brand_name: '' }); close()
}
function loadMore() { if (hasMore.value) visibleCount.value += 40 }
watch(keyword, () => { visibleCount.value = 40 })
</script>
<style scoped lang="scss">
.brand-picker__input{border:0;outline:0;padding:0;background:transparent;box-sizing:border-box}
.mall-brand{min-width:0;margin:14rpx 0}.mall-brand__entry{display:flex;align-items:center;gap:14rpx;min-height:86rpx;border-bottom:1rpx solid #edf1f5}.mall-brand__label{font-size:27rpx;color:#334155;flex-shrink:0}.mall-brand__value{flex:1;min-width:0;text-align:right;color:#0f172a;font-size:26rpx;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.placeholder{color:#94a3b8}.is-disabled{opacity:.55}.brand-picker{display:flex;flex-direction:column;height:70vh;max-height:calc(100vh - 96rpx);overflow:hidden;background:#fff;border-radius:24rpx 24rpx 0 0;color:#0f172a;box-sizing:border-box}.brand-picker__head{display:flex;align-items:center;justify-content:space-between;flex-shrink:0;padding:20rpx 16rpx 12rpx 28rpx}.brand-picker__title{font-size:17px;font-weight:650}.brand-picker__close{display:flex;align-items:center;justify-content:center;flex-shrink:0;width:86rpx;height:86rpx}.brand-picker__search{display:flex;align-items:center;gap:12rpx;margin:0 28rpx 14rpx;padding:0 20rpx;background:#f1f5f9;border-radius:14rpx;flex-shrink:0}.brand-picker__input{flex:1;min-width:0;height:76rpx;font-size:27rpx}.brand-picker__clear{display:flex;align-items:center;justify-content:center;min-width:62rpx;height:76rpx}.brand-picker__warning{display:block;padding:10rpx 28rpx;color:#b45309;font-size:24rpx;line-height:1.6;flex-shrink:0}.brand-picker__body{height:0;min-height:0;flex:1;overscroll-behavior:contain;box-sizing:border-box}.brand-picker__item{display:flex;align-items:center;justify-content:space-between;gap:20rpx;min-height:100rpx;margin:0 28rpx;padding:18rpx 0;box-sizing:border-box;border-bottom:1rpx solid #edf1f5;font-size:28rpx;line-height:1.6}.brand-picker__item>text{flex:1;min-width:0;overflow-wrap:anywhere}.brand-picker__item.is-selected{color:#1d4ed8}.brand-picker__empty{display:block;text-align:center;color:#94a3b8;padding:70rpx 28rpx;font-size:26rpx;line-height:1.8}.brand-picker__more{text-align:center;padding:24rpx;font-size:26rpx;color:#2563eb}.brand-picker__foot{display:flex;align-items:center;gap:24rpx;flex-shrink:0;padding:18rpx 28rpx;border-top:1rpx solid #e2e8f0;background:#fff}.brand-picker__remove{color:#64748b;font-size:25rpx;flex-shrink:0;padding:20rpx 0}.brand-picker__done{display:flex;align-items:center;justify-content:center;flex:1;min-height:82rpx;border-radius:14rpx;background:#2563eb;color:#fff;font-size:28rpx;font-weight:600}
</style>
