<!--
  ErpListHeader - ERP 列表页统一头部（对齐 phone_shop top-bar 风格）

  用法：
    <ErpListHeader
      v-model="keyword"
      :tabs="[{label:'全部',value:''},{label:'待付款',value:'pending'}]"
      v-model:activeTab="activeTab"
      @search="reload"
    />
-->
<template>
    <view class="erp-top-bar" :style="{ paddingTop: topPadding }">
        <!-- 标题行（可选，有标题时显示标题+右侧slot） -->
        <view v-if="title" class="title-row">
            <text class="title-text">{{ title }}</text>
            <slot name="right" />
        </view>

        <!-- 搜索框（胶囊式，对齐 phone_shop） -->
        <view v-if="showSearch" class="search-pill">
            <u-icon name="search" color="#9098A3" size="18" />
            <input
                class="search-input-text"
                v-model="localKeyword"
                :placeholder="placeholder"
                placeholder-class="search-ph"
                confirm-type="search"
                @confirm="onSearch"
            />
            <u-icon v-if="localKeyword" name="close-circle-fill" color="#c4c4c4" size="18"
                @click="localKeyword = ''; onSearch()" />
        </view>

        <!-- 状态 Tab（胶囊式，对齐 phone_shop .tab） -->
        <view v-if="tabs && tabs.length" class="tab-row">
            <view
                v-for="tab in tabs"
                :key="tab.value"
                class="erp-tab"
                :class="{ 'erp-tab--on': localTab === tab.value }"
                @click="onTab(tab.value)"
            >{{ tab.label }}</view>
            <slot name="tab-extra" />
        </view>
    </view>

    <!-- 占位高度（防止内容被 fixed 头部遮住） -->
    <view :style="{ height: placeholderHeight }" />
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'

const props = withDefaults(defineProps<{
    modelValue?: string        // keyword v-model
    activeTab?: string         // active tab v-model
    placeholder?: string
    tabs?: { label: string; value: string }[]
    title?: string
    showSearch?: boolean
}>(), {
    modelValue: '',
    activeTab: '',
    placeholder: '搜索',
    tabs: () => [],
    title: '',
    showSearch: true,
})

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void
    (e: 'update:activeTab', v: string): void
    (e: 'search'): void
    (e: 'tab-change', v: string): void
}>()

const { pageHeaderStyle } = useListHeader(0)

// 顶部安全距离
const topPadding = computed(() => {
    const statusBarHeight = (uni as any).getSystemInfoSync?.()?.statusBarHeight || 0
    return `calc(${statusBarHeight}px + 16rpx)`
})

// 计算占位高度
const placeholderHeight = computed(() => {
    const statusBarHeight = (uni as any).getSystemInfoSync?.()?.statusBarHeight || 0
    const titleH = props.title ? 80 : 0
    const searchH = props.showSearch ? 72 + 20 : 0  // pill + gap
    const tabH = props.tabs?.length ? 60 + 16 : 0   // tab + gap
    const safeTop = statusBarHeight + 16
    return `${safeTop + titleH + searchH + tabH + 14}px`
})

const localKeyword = ref(props.modelValue)
const localTab = ref(props.activeTab)

watch(() => props.modelValue, v => { localKeyword.value = v })
watch(() => props.activeTab, v => { localTab.value = v })
watch(localKeyword, v => emit('update:modelValue', v))

function onSearch() {
    emit('update:modelValue', localKeyword.value)
    emit('search')
}

function onTab(val: string) {
    localTab.value = val
    emit('update:activeTab', val)
    emit('tab-change', val)
}
</script>

<style scoped lang="scss">
.erp-top-bar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 100;
    background: var(--page-bg-color, #f3f4f6);
    padding: 0 24rpx 16rpx;
    box-sizing: border-box;
}

.title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8rpx 0 16rpx;
}
.title-text {
    font-size: 36rpx;
    font-weight: 700;
    color: #0f172a;
}

.search-pill {
    height: 72rpx;
    background: #fff;
    border-radius: 36rpx;
    display: flex;
    align-items: center;
    padding: 0 28rpx;
    gap: 12rpx;
    box-shadow: 0 2rpx 8rpx rgba(0,0,0,0.04);
}
.search-input-text {
    flex: 1;
    font-size: 28rpx;
    color: #0f172a;
    background: transparent;
}
.search-ph { color: #c4c8cf; font-size: 28rpx; }

.tab-row {
    display: flex;
    align-items: center;
    gap: 14rpx;
    margin-top: 16rpx;
    overflow-x: auto;
    white-space: nowrap;
    &::-webkit-scrollbar { display: none; }
}
.erp-tab {
    padding: 8rpx 26rpx;
    font-size: 26rpx;
    color: #666;
    background: #fff;
    border-radius: 28rpx;
    border: 2rpx solid transparent;
    flex-shrink: 0;
    transition: all 0.15s;
}
.erp-tab--on {
    color: var(--primary-color, #3b6ef5);
    background: var(--primary-color-light, #eff3ff);
    border-color: var(--primary-color, #3b6ef5);
    font-weight: 600;
}
</style>
