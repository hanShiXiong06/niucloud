<template>
    <view class="diy-quote-search" :style="wrapStyle">
        <QuoteSearchField v-model="keyword" :placeholder="component.placeholder || '搜索型号，查回收价'"
            :button-color="component.buttonColor || '#2563eb'" :disabled="isDecorate" @search="openSearch" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import useDiyStore from '@/app/stores/diy'
import { redirect } from '@/utils/common'
import QuoteSearchField from '@/addon/recycle_quote_spider/components/QuoteSearchField.vue'
import { normalizeSearchKeyword, SEARCH_PAGE } from '@/addon/recycle_quote_spider/utils/quoteSearch'

const props = withDefaults(defineProps<{ component: Record<string, any>; index?: number }>(), { index: 0 })
const diyStore = useDiyStore()
const isDecorate = computed(() => diyStore.mode === 'decorate')
const component = computed(() => isDecorate.value ? (diyStore.value[props.index] || props.component) : props.component)
const keyword = ref('')
const spacing = (value: unknown, fallback: number) => Math.max(0, Math.min(100, Number(value ?? fallback) || 0)) * 2
const wrapStyle = computed(() => ({
    padding: `${spacing(component.value.margin?.top, 10)}rpx ${spacing(component.value.margin?.both, 12)}rpx ${spacing(component.value.margin?.bottom, 10)}rpx`,
    backgroundColor: component.value.componentStartBgColor || '#ffffff'
}))
function openSearch(value: string) {
    if (isDecorate.value) return
    redirect({ url: SEARCH_PAGE, param: {
        keyword: encodeURIComponent(normalizeSearchKeyword(value)),
        source_id: Math.max(0, Number(component.value.sourceId) || 0)
    } })
}
</script>

<style scoped>
.diy-quote-search { box-sizing: border-box; width: 100%; }
</style>
