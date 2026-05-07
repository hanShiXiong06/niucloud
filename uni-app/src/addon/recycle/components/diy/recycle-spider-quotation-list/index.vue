<template>
    <view class="quotation-wrap" :style="wrapStyle">
        <view class="quotation-card" :style="cardStyle">
            <view v-if="showMaskLayer" class="quotation-mask" :style="maskLayerStyle"></view>
            <view class="quotation-content">
                <view v-if="showHeader" class="quotation-header">
                    <view class="title-block">
                        <text class="title" :style="{ color: titleColor }">{{ title }}</text>
                        <text class="subtitle" :style="{ color: subtitleColor }">{{ subtitle }}</text>
                    </view>
                    <view class="refresh-btn" v-if="showRefresh" @click.stop="loadItems">
                        <up-icon name="reload" size="18" color="#4b5563"></up-icon>
                    </view>
                </view>

                <view
                    v-if="showCategoryTabs && categoryTabs.length"
                    class="category-tabs"
                >
                    <quotation-category-tabs
                        :list="categoryTabList"
                        :current="activeCategoryIndex"
                        :variant="tabStyleType"
                        :themeColor="tabThemeColor"
                        :activeBgColor="tabActiveBgColor"
                        :inactiveBgColor="tabInactiveBgColor"
                        :activeTextColor="tabActiveTextColor"
                        :inactiveTextColor="tabInactiveTextColor"
                        :borderColor="tabBorderColor"
                        :height="tabHeight"
                        :radius="tabRadius"
                        :fontSize="tabFontSize"
                        :fontWeight="tabFontWeight"
                        :sidePadding="tabSidePadding"
                        :showScrollCue="showTabScrollCue"
                        @change="handleCategoryTabChange"
                    ></quotation-category-tabs>
                </view>

                <view v-if="loading" class="state-box">
                    <text class="state-text">报价加载中...</text>
                </view>

                <view v-else-if="displayList.length === 0" class="state-box empty">
                    <text class="state-title">暂无报价</text>
                    <text class="state-text">请先在报价爬虫插件中同步并开启展示</text>
                </view>

                <view v-else-if="displayStyle === 'graphic'" class="quotation-group-list">
                    <view v-for="group in displayGroups" :key="group.key" class="quotation-group">
                        <view v-if="showGroupHeader(group)" class="quotation-group-head" :class="{ center: groupTitleAlign === 'center' }">
                            <text class="group-title-text" :style="groupTitleStyle">{{ group.title }}</text>
                            <text v-if="showGroupCount" :style="groupCountStyle">{{ group.items.length }} 项</text>
                        </view>
                        <view class="quotation-nav">
                            <view
                                v-for="item in group.items"
                                :key="item.id"
                                class="quotation-nav-item"
                                :style="navItemStyle"
                                @click="openQuotation(item)"
                            >
                                <view class="quotation-nav-img" :style="navImageStyle">
                                    <image :src="img(resolveItemImage(item))" mode="aspectFill" :style="itemImageRadiusStyle"></image>
                                </view>
                                <text class="quotation-nav-title" :style="itemTitleStyle">{{ displayItemName(item) }}</text>
                            </view>
                        </view>
                    </view>
                </view>

                <view v-else class="dataset-list">
                    <view v-for="group in displayGroups" :key="group.key" class="dataset-group">
                        <view v-if="showGroupHeader(group)" class="dataset-group-head" :class="{ center: groupTitleAlign === 'center' }">
                            <text class="group-title-text" :style="groupTitleStyle">{{ group.title }}</text>
                            <text v-if="showGroupCount" :style="groupCountStyle">{{ group.items.length }} 项</text>
                        </view>
                        <view
                            v-for="item in group.items"
                            :key="item.id"
                            class="dataset-item"
                            @click="openQuotation(item)"
                        >
                            <view class="dataset-main">
                                <view class="dataset-title-row">
                                    <text class="dataset-title" :style="itemTitleStyle">{{ displayItemName(item) }}</text>
                                    <text v-if="item.is_hot" class="hot-tag">热门</text>
                                </view>
                                <view class="dataset-meta" :style="itemMetaStyle">
                                    <text>{{ item.last_sync_at_text || '待同步' }}</text>
                                    <text class="dot">·</text>
                                    <text>{{ item.model_count || 0 }} 个型号</text>
                                </view>
                            </view>
                            <view class="dataset-action" :style="{ color: buttonColor, borderColor: buttonColor }">
                                <text>{{ actionText }}</text>
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </view>
    </view>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue'
import useDiyStore from '@/app/stores/diy'
import { img, redirect } from '@/utils/common'
import { getQuoteSpiderCategoryTree, getQuoteSpiderFeatured, type QuoteSpiderCategory, type QuoteSpiderItem } from '@/addon/recycle/api/quotation'
import QuotationCategoryTabs from './components/QuotationCategoryTabs.vue'

const props = defineProps({
    component: {
        type: Object,
        default: () => ({})
    },
    index: {
        type: Number,
        default: 0
    }
})

const diyStore = useDiyStore()
const loading = ref(false)
const items = ref<QuoteSpiderItem[]>([])
const categories = ref<QuoteSpiderCategory[]>([])
const activeCategoryId = ref(0)

const diyComponent = computed(() => {
    if (diyStore.mode === 'decorate') {
        return diyStore.value[props.index] || props.component
    }
    return props.component
})

const title = computed(() => diyComponent.value.title || '实时报价')
const subtitle = computed(() => diyComponent.value.subtitle || '按数据源同步展示回收报价')
const showHeader = computed(() => diyComponent.value.showHeader !== false)
const actionText = computed(() => diyComponent.value.actionText || '查看')
const sourceId = computed(() => Number(diyComponent.value.sourceId || 0))
const limit = computed(() => {
    const value = Number(diyComponent.value.limit || 6)
    if (!Number.isFinite(value)) return 6
    if (value <= 0) return 100
    return Math.min(value, 100)
})
const onlyHot = computed(() => diyComponent.value.onlyHot === true || diyComponent.value.onlyHot === 1)
const showCategoryTabs = computed(() => diyComponent.value.showCategoryTabs !== false)
const categoryTabDepth = computed(() => {
    const value = Number(diyComponent.value.categoryTabDepth ?? 0)
    return Number.isFinite(value) && value >= 0 ? value : 0
})
const flatGroupMode = computed(() => diyComponent.value.flatGroupMode || 'level2')
const showGroupCount = computed(() => diyComponent.value.showGroupCount !== false)
const showRefresh = computed(() => diyComponent.value.showRefresh !== false)
const displayStyle = computed(() => diyComponent.value.displayStyle || 'list')
const titleColor = computed(() => diyComponent.value.titleColor || '#111827')
const subtitleColor = computed(() => diyComponent.value.subtitleColor || '#6B7280')
const buttonColor = computed(() => diyComponent.value.buttonColor || '#2563EB')
const groupTitleColor = computed(() => diyComponent.value.groupTitleColor || '#111827')
const groupCountColor = computed(() => diyComponent.value.groupCountColor || '#94A3B8')
const groupTitleSize = computed(() => {
    const value = Number(diyComponent.value.groupTitleSize || 22)
    return Number.isFinite(value) ? Math.max(16, Math.min(value, 40)) : 22
})
const groupTitleWeight = computed(() => {
    const value = Number(diyComponent.value.groupTitleWeight || 500)
    return [400, 500, 600, 700].includes(value) ? value : 500
})
const groupTitleAlign = computed(() => diyComponent.value.groupTitleAlign === 'center' ? 'center' : 'left')
const itemTitleColor = computed(() => diyComponent.value.itemTitleColor || '#111827')
const itemMetaColor = computed(() => diyComponent.value.itemMetaColor || '#6B7280')
const itemTitleSize = computed(() => {
    const value = Number(diyComponent.value.itemTitleSize || 28)
    return Number.isFinite(value) ? Math.max(20, Math.min(value, 36)) : 28
})
const itemImageRadius = computed(() => {
    const value = Number(diyComponent.value.itemImageRadius ?? 20)
    return Number.isFinite(value) ? Math.max(0, Math.min(value, 50)) : 20
})
const navRowCount = computed(() => {
    const value = Number(diyComponent.value.navRowCount || 4)
    return [3, 4, 5].includes(value) ? value : 4
})
const navImageSize = computed(() => {
    const value = Number(diyComponent.value.navImageSize || 40)
    return Number.isFinite(value) && value > 0 ? value : 40
})
const navItemStyle = computed(() => `width:${100 / navRowCount.value}%;`)
const navImageStyle = computed(() => {
    return `width:${navImageSize.value * 2}rpx;height:${navImageSize.value * 2}rpx;border-radius:${itemImageRadius.value * 2}rpx;`
})
const flatCategories = computed(() => flattenCategories(categories.value))
const categoryTabs = computed(() => {
    const depth = categoryTabDepth.value
    return depth > 0
        ? flatCategories.value.filter(item => Number(item.level || 0) <= depth)
        : flatCategories.value
})
const categoryTabList = computed(() => {
    return categoryTabs.value.map(item => ({
        name: String(item.name || '未命名分类'),
        id: Number(item.id || 0)
    }))
})
const activeCategoryIndex = computed(() => {
    const index = categoryTabs.value.findIndex(item => Number(item.id || 0) === activeCategoryId.value)
    return index >= 0 ? index : 0
})
const tabStyleType = computed(() => {
    const value = diyComponent.value.tabStyleType || 'pill'
    return ['pill', 'card', 'underline'].includes(value) ? value : 'pill'
})
const tabThemeColor = computed(() => diyComponent.value.tabThemeColor || buttonColor.value)
const tabActiveBgColor = computed(() => diyComponent.value.tabActiveBgColor || '')
const tabInactiveBgColor = computed(() => diyComponent.value.tabInactiveBgColor || '')
const tabActiveTextColor = computed(() => diyComponent.value.tabActiveTextColor || '')
const tabInactiveTextColor = computed(() => diyComponent.value.tabInactiveTextColor || '#475569')
const tabBorderColor = computed(() => diyComponent.value.tabBorderColor || '')
const tabHeight = computed(() => {
    const value = Number(diyComponent.value.tabHeight || 64)
    return Number.isFinite(value) ? Math.max(44, Math.min(value, 96)) : 64
})
const tabRadius = computed(() => {
    const value = Number(diyComponent.value.tabRadius ?? 32)
    return Number.isFinite(value) ? Math.max(0, Math.min(value, 48)) : 32
})
const tabFontSize = computed(() => {
    const value = Number(diyComponent.value.tabFontSize || 26)
    return Number.isFinite(value) ? Math.max(20, Math.min(value, 34)) : 26
})
const tabFontWeight = computed(() => {
    const value = Number(diyComponent.value.tabFontWeight || 600)
    return [400, 500, 600, 700].includes(value) ? value : 600
})
const tabSidePadding = computed(() => {
    const value = Number(diyComponent.value.tabSidePadding || 18)
    return Number.isFinite(value) ? Math.max(8, Math.min(value, 40)) : 18
})
const showTabScrollCue = computed(() => diyComponent.value.showTabScrollCue !== false)
const groupTitleStyle = computed(() => {
    return `color:${groupTitleColor.value};font-size:${groupTitleSize.value}rpx;line-height:${Math.max(groupTitleSize.value + 10, 30)}rpx;font-weight:${groupTitleWeight.value};`
})
const groupCountStyle = computed(() => {
    return `color:${groupCountColor.value};font-size:${Math.max(groupTitleSize.value - 3, 18)}rpx;`
})
const itemTitleStyle = computed(() => {
    return `color:${itemTitleColor.value};font-size:${itemTitleSize.value}rpx;line-height:${Math.max(itemTitleSize.value + 10, 30)}rpx;`
})
const itemMetaStyle = computed(() => {
    return `color:${itemMetaColor.value};font-size:${Math.max(itemTitleSize.value - 5, 20)}rpx;line-height:${Math.max(itemTitleSize.value + 4, 28)}rpx;`
})
const itemImageRadiusStyle = computed(() => `border-radius:${itemImageRadius.value * 2}rpx;`)

const mockList = computed<QuoteSpiderItem[]>(() => [
    {
        id: 1,
        source_id: 1,
        category_id: 1,
        brand: '苹果',
        tab: 'iPhone',
        name: 'iPhone 实时报价',
        parent_name: '手机报价',
        title: '手机报价 iPhone 实时报价',
        quote_type: 'manual',
        is_image_quote: 0,
        image: '',
        timage: '',
        bimage: '',
        icon: '',
        is_hot: 1,
        model_count: 36,
        last_sync_at_text: '今日 10:00'
    },
    {
        id: 2,
        source_id: 1,
        category_id: 2,
        brand: '安卓',
        tab: '旗舰机',
        name: '安卓旗舰报价',
        parent_name: '手机报价',
        title: '手机报价 安卓旗舰报价',
        quote_type: 'manual',
        is_image_quote: 0,
        image: '',
        timage: '',
        bimage: '',
        icon: '',
        is_hot: 0,
        model_count: 28,
        last_sync_at_text: '今日 10:00'
    }
])

const displayList = computed(() => {
    const list = diyStore.mode === 'decorate' && items.value.length === 0 ? mockList.value : items.value
    return list.slice(0, limit.value)
})
const displayGroups = computed(() => {
    if (flatGroupMode.value === 'none') {
        return [{
            key: 'all',
            title: '',
            items: displayList.value
        }]
    }

    const groups: Array<{ key: string; title: string; items: QuoteSpiderItem[] }> = []
    const groupMap = new Map<string, { key: string; title: string; items: QuoteSpiderItem[] }>()

    for (const item of displayList.value) {
        const title = displayGroupTitle(item)
        if (!groupMap.has(title)) {
            const group = { key: title, title, items: [] as QuoteSpiderItem[] }
            groupMap.set(title, group)
            groups.push(group)
        }
        groupMap.get(title)?.items.push(item)
    }

    return groups
})

function flattenCategories(list: QuoteSpiderCategory[]): QuoteSpiderCategory[] {
    const result: QuoteSpiderCategory[] = []
    list.forEach(item => {
        result.push(item)
        result.push(...flattenCategories(item.children || []))
    })
    return result
}

const wrapStyle = computed(() => {
    const margin = diyComponent.value.margin || { top: 10, bottom: 10, both: 12 }
    let style = 'position:relative;'
    style += `margin:${Number(margin.top || 0) * 2}rpx ${Number(margin.both || 0) * 2}rpx ${Number(margin.bottom || 0) * 2}rpx;`
    return style
})

const cardStyle = computed(() => {
    const startColor = diyComponent.value.componentStartBgColor || ''
    const endColor = diyComponent.value.componentEndBgColor || ''
    const angle = diyComponent.value.componentGradientAngle || 'to bottom'
    const bgUrl = diyComponent.value.componentBgUrl || ''
    const topRounded = Number(diyComponent.value.topRounded || 0) * 2
    const bottomRounded = Number(diyComponent.value.bottomRounded || 0) * 2
    let style = ''

    if (startColor && endColor) {
        style += `background:linear-gradient(${angle},${startColor},${endColor});`
    } else if (startColor) {
        style += `background-color:${startColor};`
    } else {
        style += 'background:#ffffff;'
    }
    if (bgUrl) {
        style += `background-image:url('${img(bgUrl)}');background-size:cover;background-repeat:no-repeat;background-position:center;`
    }
    style += `border-top-left-radius:${topRounded}rpx;border-top-right-radius:${topRounded}rpx;`
    style += `border-bottom-left-radius:${bottomRounded}rpx;border-bottom-right-radius:${bottomRounded}rpx;`
    return style
})

const showMaskLayer = computed(() => Boolean(diyComponent.value.componentBgUrl))

const maskLayerStyle = computed(() => {
    const alpha = Number(diyComponent.value.componentBgAlpha || 0) / 10
    return `background:rgba(0,0,0,${alpha});`
})

function resolveItemImage(item: QuoteSpiderItem) {
    return item.icon || item.image || item.timage || item.bimage || 'static/resource/images/diy/figure.png'
}

function displayItemName(item: QuoteSpiderItem) {
    return item.name || item.title || '报价单'
}

function displayCategoryPath(item: QuoteSpiderItem) {
    return String(item.category_path || item.parent_name || '').trim()
}

function displayGroupTitle(item: QuoteSpiderItem) {
    if (flatGroupMode.value === 'none') return ''
    const parts = displayCategoryPath(item).split('/').map(part => part.trim()).filter(Boolean)
    if (flatGroupMode.value === 'level1') return parts[0] || '未分类'
    if (flatGroupMode.value === 'level2') return parts.slice(0, 2).join(' / ') || parts[0] || '未分类'
    return parts.join(' / ') || '未分类'
}

function showGroupHeader(group: { title: string }) {
    return Boolean(group.title)
}

async function loadCategories() {
    if (diyStore.mode === 'decorate') {
        categories.value = []
        activeCategoryId.value = 0
        return
    }

    try {
        const res = await getQuoteSpiderCategoryTree({
            source_id: sourceId.value || ''
        }) as any
        categories.value = res.code === 1 && Array.isArray(res.data) ? res.data : []
        if (showCategoryTabs.value && activeCategoryId.value === 0 && categoryTabs.value.length) {
            activeCategoryId.value = Number(categoryTabs.value[0].id || 0)
        }
    } catch (error) {
        categories.value = []
        activeCategoryId.value = 0
    }
}

function switchCategory(categoryId: number) {
    if (activeCategoryId.value === categoryId) return
    activeCategoryId.value = categoryId
    loadItems()
}

function handleCategoryTabChange(tab: any) {
    const index = Number(tab?.index ?? tab ?? 0)
    const category = categoryTabs.value[index]
    const categoryId = Number(category?.id ?? tab?.id ?? tab?.value ?? 0)
    if (!categoryId) return
    switchCategory(categoryId)
}

async function loadItems() {
    if (diyStore.mode === 'decorate') {
        items.value = []
        return
    }

    loading.value = true
    try {
        const res = await getQuoteSpiderFeatured({
            source_id: sourceId.value || '',
            category_id: showCategoryTabs.value && activeCategoryId.value ? activeCategoryId.value : '',
            limit: limit.value,
            only_hot: onlyHot.value ? 1 : ''
        }) as any
        items.value = res.code === 1 && Array.isArray(res.data) ? res.data : []
    } catch (error) {
        items.value = []
    } finally {
        loading.value = false
    }
}

function openQuotation(item: QuoteSpiderItem) {
    if (diyStore.mode === 'decorate') return
    const titleText = encodeURIComponent(displayItemName(item) || '报价查询')
    redirect({
        url: `/addon/recycle/pages/price/show_price?source=spider&item_id=${item.id}&title=${titleText}`
    })
}

onMounted(() => {
    loadCategories().finally(loadItems)
})
</script>

<style lang="scss" scoped>
.quotation-wrap {
    box-sizing: border-box;
}

.quotation-card {
    position: relative;
    overflow: hidden;
    box-shadow: 0 2rpx 10rpx rgba(15, 23, 42, 0.04);
}

.quotation-mask {
    position: absolute;
    inset: 0;
    z-index: 0;
}

.quotation-content {
    position: relative;
    z-index: 1;
}

.quotation-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 26rpx 26rpx 18rpx;
}

.title-block {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.title {
    font-size: 32rpx;
    line-height: 44rpx;
    font-weight: 700;
}

.subtitle {
    margin-top: 6rpx;
    font-size: 24rpx;
    line-height: 34rpx;
}

.refresh-btn {
    width: 56rpx;
    height: 56rpx;
    border-radius: 28rpx;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4b5563;
    font-size: 28rpx;
}

.category-tabs {
    width: 100%;
    padding: 0 18rpx 16rpx;
    box-sizing: border-box;
}

.dataset-list {
    padding: 0 18rpx 18rpx;
}

.dataset-group + .dataset-group {
    margin-top: 12rpx;
}

.dataset-group-head,
.quotation-group-head {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    padding: 14rpx 8rpx 8rpx;
}

.dataset-group-head.center,
.quotation-group-head.center {
    justify-content: center;
    text-align: center;
}

.dataset-group-head.center text:last-child,
.quotation-group-head.center text:last-child {
    position: absolute;
    right: 8rpx;
}

.group-title-text {
    min-width: 0;
}

.dataset-group-head text:last-child,
.quotation-group-head text:last-child {
    flex-shrink: 0;
    font-weight: 500;
}

.dataset-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 106rpx;
    padding: 18rpx 8rpx;
    border-top: 1rpx solid #f1f5f9;
}

.dataset-item:first-child {
    border-top: none;
}

.dataset-main {
    min-width: 0;
    flex: 1;
}

.dataset-title-row {
    display: flex;
    align-items: center;
    gap: 10rpx;
    min-width: 0;
}

.dataset-title {
    display: block;
    min-width: 0;
    font-size: 29rpx;
    line-height: 40rpx;
    font-weight: 600;
    color: #111827;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hot-tag {
    flex-shrink: 0;
    padding: 2rpx 10rpx;
    border-radius: 999rpx;
    background: #fff1f2;
    color: #e11d48;
    font-size: 20rpx;
    line-height: 28rpx;
}

.dataset-meta {
    margin-top: 8rpx;
    display: flex;
    align-items: center;
    font-size: 23rpx;
    line-height: 32rpx;
    color: #6b7280;
}

.dot {
    margin: 0 10rpx;
}

.dataset-action {
    flex-shrink: 0;
    min-width: 104rpx;
    height: 52rpx;
    line-height: 50rpx;
    text-align: center;
    border: 1rpx solid;
    border-radius: 26rpx;
    font-size: 24rpx;
    font-weight: 600;
    margin-left: 20rpx;
}

.quotation-nav {
    display: flex;
    flex-wrap: wrap;
    padding: 10rpx 12rpx 16rpx;
}

.quotation-group + .quotation-group {
    border-top: 1rpx solid #f1f5f9;
}

.quotation-nav-item {
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 14rpx 8rpx;
}

.quotation-nav-img {
    overflow: hidden;
    background: #f3f4f6;

    image {
        width: 100%;
        height: 100%;
        display: block;
    }
}

.quotation-nav-title {
    width: 100%;
    margin-top: 14rpx;
    font-size: 24rpx;
    line-height: 34rpx;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.state-box {
    padding: 34rpx 24rpx 42rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.state-title {
    font-size: 28rpx;
    line-height: 40rpx;
    color: #111827;
    font-weight: 600;
}

.state-text {
    margin-top: 6rpx;
    font-size: 24rpx;
    line-height: 34rpx;
    color: #6b7280;
}
</style>
