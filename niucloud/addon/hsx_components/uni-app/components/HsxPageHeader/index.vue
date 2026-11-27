<script lang="ts">
export default { name: 'HsxPageHeader' }
</script>

<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import HsxIcon from '../HsxIcon/index.vue'
import HsxSearchBar from '../HsxSearchBar/index.vue'

type SearchMode = 'auto' | 'inline' | 'below'

const props = withDefaults(defineProps<{
    modelValue?: string | number
    title?: string
    subtitle?: string
    showBack?: boolean
    searchable?: boolean
    searchMode?: SearchMode
    searchPlaceholder?: string
    autoSearch?: boolean
    searchDebounce?: number
    showFilter?: boolean
    filterCount?: number
    fixed?: boolean
    sticky?: boolean
    fill?: boolean
    safeAreaTop?: boolean
    border?: boolean
    elevated?: boolean
    background?: string
    color?: string
    zIndex?: string | number
    bottomHeight?: number
    homeUrl?: string
}>(), {
    modelValue: '',
    title: '',
    subtitle: '',
    showBack: true,
    searchable: false,
    searchMode: 'auto',
    searchPlaceholder: '搜索关键词',
    autoSearch: false,
    searchDebounce: 400,
    showFilter: false,
    filterCount: 0,
    fixed: false,
    sticky: false,
    fill: true,
    safeAreaTop: true,
    border: true,
    elevated: false,
    background: 'var(--hsx-mobile-bg-surface, #fff)',
    color: 'var(--hsx-mobile-text-primary, #172033)',
    zIndex: 90,
    bottomHeight: 0,
    homeUrl: '/app/pages/index/index'
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void
    (event: 'search', value: string, trigger: string): void
    (event: 'clear'): void
    (event: 'filter'): void
    (event: 'back'): void
}>()

const slots = useSlots()
const hasSearchLeading = computed(() => Boolean(slots['search-leading']))
const hasSearchTrailing = computed(() => Boolean(slots['search-trailing']))
const layout = useAdaptiveContext()
const systemStatusTop = (() => {
    if (typeof uni === 'undefined' || typeof uni.getSystemInfoSync !== 'function') return 0
    try { return Number((uni.getSystemInfoSync() as any)?.statusBarHeight || 0) } catch { return 0 }
})()
const capsuleMetrics = (() => {
    if (typeof uni === 'undefined' || typeof (uni as any).getMenuButtonBoundingClientRect !== 'function') return null
    try { return (uni as any).getMenuButtonBoundingClientRect() as any } catch { return null }
})()

const statusTop = computed(() => props.safeAreaTop ? Math.max(systemStatusTop, layout.safeAreaInsets.value.top) : 0)
const capsuleRight = computed(() => {
    if (!capsuleMetrics?.left) return layout.safeAreaInsets.value.right
    return Math.max(layout.safeAreaInsets.value.right, layout.windowWidth.value - Number(capsuleMetrics.left) + 8)
})
const mainHeight = computed(() => Math.max(48, Number(capsuleMetrics?.height || 0) + 10))
const resolvedSearchMode = computed<'inline' | 'below'>(() => {
    if (props.searchMode !== 'auto') return props.searchMode
    if (!props.title) return 'inline'
    return layout.isCompact.value ? 'below' : 'inline'
})
const showInlineSearch = computed(() => props.searchable && resolvedSearchMode.value === 'inline')
const showBelowSearch = computed(() => props.searchable && resolvedSearchMode.value === 'below')
const headerStyle = computed(() => ({
    paddingTop: `${statusTop.value}px`,
    background: props.background,
    color: props.color,
    zIndex: props.zIndex
}))
const mainStyle = computed(() => ({
    minHeight: `${mainHeight.value}px`,
    paddingRight: `${Math.max(12, capsuleRight.value)}px`
}))
const placeholderStyle = computed(() => ({
    height: `${statusTop.value + mainHeight.value + (showBelowSearch.value ? 60 : 0) + Math.max(0, props.bottomHeight)}px`
}))

function handleBack() {
    emit('back')
    if (typeof uni === 'undefined') return
    let canGoBack = false
    try { canGoBack = typeof getCurrentPages === 'function' && getCurrentPages().length > 1 } catch { canGoBack = false }
    if (canGoBack) {
        uni.navigateBack({ delta: 1, fail: () => uni.reLaunch({ url: props.homeUrl }) })
    } else {
        uni.reLaunch({ url: props.homeUrl })
    }
}

function handleSearch(value: string, trigger: string) {
    emit('search', value, trigger)
}
</script>

<template>
    <view
        class="hsx-page-header"
        :class="{
            'is-fixed': fixed,
            'is-sticky': sticky && !fixed,
            'is-bordered': border,
            'is-elevated': elevated,
            'has-below-search': showBelowSearch
        }"
        :style="headerStyle"
    >
        <view class="hsx-page-header__main" :style="mainStyle">
            <view class="hsx-page-header__left">
                <slot name="left" :back="handleBack">
                    <view v-if="showBack" class="hsx-page-header__back" role="button" aria-label="返回" @click="handleBack">
                        <HsxIcon name="back" :size="22" />
                    </view>
                </slot>
            </view>

            <view class="hsx-page-header__center">
                <slot v-if="$slots.center" name="center" />
                <view v-else-if="showInlineSearch" class="hsx-page-header__inline-search">
                    <slot v-if="title" name="title">
                        <view class="hsx-page-header__heading hsx-page-header__heading--inline">
                            <text class="hsx-page-header__title">{{ title }}</text>
                            <text v-if="subtitle" class="hsx-page-header__subtitle">{{ subtitle }}</text>
                        </view>
                    </slot>
                    <HsxSearchBar
                        :model-value="modelValue"
                        :placeholder="searchPlaceholder"
                        :auto-search="autoSearch"
                        :debounce="searchDebounce"
                        :show-filter="showFilter"
                        :filter-count="filterCount"
                        size="small"
                        @update:model-value="emit('update:modelValue', $event)"
                        @search="handleSearch"
                        @clear="emit('clear')"
                        @filter="emit('filter')"
                    >
                        <template v-if="hasSearchLeading" #leading><slot name="search-leading" /></template>
                        <template v-if="hasSearchTrailing" #trailing><slot name="search-trailing" /></template>
                    </HsxSearchBar>
                </view>
                <slot v-else name="title">
                    <view class="hsx-page-header__heading">
                        <text class="hsx-page-header__title">{{ title }}</text>
                        <text v-if="subtitle" class="hsx-page-header__subtitle">{{ subtitle }}</text>
                    </view>
                </slot>
            </view>

            <view class="hsx-page-header__right"><slot name="right" /></view>
        </view>

        <view v-if="showBelowSearch" class="hsx-page-header__search">
            <HsxSearchBar
                :model-value="modelValue"
                :placeholder="searchPlaceholder"
                :auto-search="autoSearch"
                :debounce="searchDebounce"
                :show-filter="showFilter"
                :filter-count="filterCount"
                @update:model-value="emit('update:modelValue', $event)"
                @search="handleSearch"
                @clear="emit('clear')"
                @filter="emit('filter')"
            >
                <template v-if="hasSearchLeading" #leading><slot name="search-leading" /></template>
                <template v-if="hasSearchTrailing" #trailing><slot name="search-trailing" /></template>
            </HsxSearchBar>
        </view>

        <view v-if="$slots.bottom" class="hsx-page-header__bottom"><slot name="bottom" /></view>
    </view>
    <view v-if="fixed && fill" class="hsx-page-header__placeholder" :style="placeholderStyle" />
</template>

<style scoped lang="scss">
.hsx-page-header { position: relative; width: 100%; box-sizing: border-box; color: var(--hsx-mobile-text-primary, #172033); background: var(--hsx-mobile-bg-surface, #fff); }
.hsx-page-header.is-fixed { position: fixed; top: 0; right: 0; left: 0; }
.hsx-page-header.is-sticky { position: sticky; top: 0; }
.hsx-page-header.is-bordered { border-bottom: 1px solid var(--hsx-mobile-border, #e6ebf2); }
.hsx-page-header.is-elevated { box-shadow: 0 8px 24px rgba(15, 23, 42, .09); }
.hsx-page-header__main { display: grid; width: 100%; min-width: 0; box-sizing: border-box; grid-template-columns: minmax(40px, auto) minmax(0, 1fr) minmax(40px, auto); align-items: center; gap: 8px; padding: 0 12px; }
.hsx-page-header__left,
.hsx-page-header__right { display: flex; min-width: 40px; align-items: center; }
.hsx-page-header__right { justify-content: flex-end; gap: 6px; }
.hsx-page-header__back { display: flex; width: 40px; height: 40px; flex: none; align-items: center; justify-content: center; border-radius: 50%; }
.hsx-page-header__back:active { background: var(--hsx-mobile-bg-muted, #f5f7fa); }
.hsx-page-header__center { min-width: 0; }
.hsx-page-header__inline-search { display: flex; min-width: 0; align-items: center; gap: 14px; }
.hsx-page-header__inline-search > :deep(.hsx-search-bar) { min-width: 180px; flex: 1; }
.hsx-page-header__heading { display: flex; min-width: 0; align-items: center; justify-content: center; flex-direction: column; }
.hsx-page-header__heading--inline { max-width: 170px; flex: none; align-items: flex-start; }
.hsx-page-header__heading--inline .hsx-page-header__title,
.hsx-page-header__heading--inline .hsx-page-header__subtitle { text-align: left; }
.hsx-page-header__title,
.hsx-page-header__subtitle { display: block; width: 100%; overflow: hidden; text-align: center; text-overflow: ellipsis; white-space: nowrap; }
.hsx-page-header__title { color: inherit; font-size: var(--hsx-mobile-font-title, 18px); font-weight: 700; line-height: 1.35; }
.hsx-page-header__subtitle { margin-top: 1px; color: var(--hsx-mobile-text-secondary, #8a94a5); font-size: var(--hsx-mobile-font-caption, 12px); }
.hsx-page-header__search { padding: 6px 12px 10px; }
.hsx-page-header__bottom { min-width: 0; }
.hsx-page-header__placeholder { width: 100%; }
@media (min-width: 600px) {
    .hsx-page-header__main { grid-template-columns: minmax(48px, auto) minmax(280px, 720px) minmax(48px, auto); justify-content: center; padding-right: 20px; padding-left: 20px; }
    .hsx-page-header__left,
    .hsx-page-header__right { min-width: 48px; }
}
</style>
