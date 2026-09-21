<template>
    <view class="mall-category">
        <view class="mall-category__entry" @click="open">
            <text class="mall-category__label">商城分类 *</text>
            <text class="mall-category__value" :class="{ placeholder: !selected }">{{ selected || '请选择商城分类' }}</text>
            <u-icon name="arrow-right" size="14" color="#94a3b8" />
        </view>
        <text class="mall-category__hint">客户按此分类查找商品，不修改 ERP 分类。</text>
        <u-popup :show="show" mode="bottom" round="16" :z-index="10100" @close="show = false">
            <view class="category-picker">
                <view class="category-picker__head"><text>选择商城分类</text><u-icon name="close" @click="show = false" /></view>
                <u-search v-model="keyword" placeholder="搜索型号或分类名称" :show-action="false" />
                <view v-if="loading" class="category-picker__empty">正在加载本站商城分类…</view>
                <view v-else-if="error" class="category-picker__empty" @click="load">{{ error }} · 点击重试</view>
                <scroll-view v-else scroll-y class="category-picker__list">
                    <view v-for="item in matches" :key="item.value" class="category-picker__item" @click="choose(item)">
                        <text>{{ item.label }}</text><u-icon v-if="item.value === modelValue" name="checkmark" color="#2563eb" />
                    </view>
                    <view v-if="!matches.length" class="category-picker__empty">{{ options.length ? '没有匹配的分类，请换个关键词' : '暂无可选分类，请先在商城创建并启用分类' }}</view>
                    <view v-if="filtered.length > matches.length" class="category-picker__empty">仅显示前 80 项，输入关键词可缩小范围</view>
                </scroll-view>
                <view class="category-picker__foot" @click="load">刷新分类</view>
            </view>
        </u-popup>
    </view>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { getErpListingCatalog } from '@/addon/hsx_erp/api/erp'
const props = defineProps<{ modelValue: number; selectedLabel?: string }>()
const emit = defineEmits(['update:modelValue', 'change'])
const show = ref(false)
const loading = ref(false)
const error = ref('')
const keyword = ref('')
const options = ref<any[]>([])
const selected = computed(() => options.value.find(item => item.value === props.modelValue)?.label || props.selectedLabel || '')
const filtered = computed(() => options.value.filter(item => String(item.label).toLowerCase().includes(keyword.value.trim().toLowerCase())))
const matches = computed(() => filtered.value.slice(0, 80))
async function load() {
    if (loading.value) return
    loading.value = true; error.value = ''
    try { const res: any = await getErpListingCatalog(); options.value = res?.data?.options || [] }
    catch (e: any) { options.value = []; error.value = e?.message || e?.msg || '分类加载失败' }
    finally { loading.value = false }
}
function open() { show.value = true; keyword.value = ''; void load() }
function choose(item: any) { emit('update:modelValue', item.value); emit('change', item); show.value = false }
</script>
<style scoped lang="scss">
.mall-category{margin:20rpx 0;background:#fff;border:1rpx solid #e2e8f0;border-radius:16rpx;padding:20rpx}.mall-category__entry{display:flex;align-items:center;gap:16rpx;min-height:60rpx}.mall-category__label{flex-shrink:0;font-size:28rpx;color:#334155;font-weight:600}.mall-category__value{flex:1;min-width:0;text-align:right;font-size:26rpx;color:#0f172a;line-height:1.5;word-break:break-all}.placeholder{color:#94a3b8}.mall-category__hint{display:block;margin-top:10rpx;font-size:23rpx;color:#64748b}.category-picker{padding:24rpx}.category-picker__head{display:flex;justify-content:space-between;align-items:center;margin-bottom:24rpx;font-weight:600}.category-picker__list{height:50vh;margin-top:16rpx}.category-picker__item{display:flex;justify-content:space-between;gap:20rpx;padding:24rpx 8rpx;border-bottom:1rpx solid #f1f5f9;font-size:27rpx;line-height:1.5}.category-picker__empty{padding:40rpx 12rpx;font-size:26rpx;color:#64748b;text-align:center}.category-picker__foot{padding:20rpx;text-align:center;color:#2563eb;font-size:26rpx}
</style>
