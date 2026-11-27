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
                    <view class="refresh-btn" v-if="showRefresh" @click.stop="loadItems(true)">
                        <up-icon name="reload" size="18" color="#6b7280"></up-icon>
                    </view>
                </view>

                <view
                    v-if="showCategoryTabs && primaryCategoryTabs.length"
                    class="category-tabs-placeholder"
                    :style="categoryTabsPlaceholderStyle"
                ></view>
                <view
                    v-if="showCategoryTabs && primaryCategoryTabs.length"
                    :id="categoryTabsId"
                    class="category-tabs"
                    :class="{ fixed: categoryTabsFixed }"
                    :style="categoryTabsStyle"
                >
                    <view class="category-tabs__primary">
                        <quotation-category-tabs
                            :list="primaryCategoryTabList"
                            :current="activePrimaryCategoryIndex"
                            :variant="tabStyleType"
                            :themeColor="tabThemeColor"
                            :activeBgColor="tabActiveBgColor"
                            :inactiveBgColor="tabInactiveBgColor"
                            :activeTextColor="tabActiveTextColor"
                            :inactiveTextColor="tabInactiveTextColor"
                            :height="tabHeight"
                            :radius="tabRadius"
                            :fontSize="tabFontSize"
                            :fontWeight="tabFontWeight"
                            :sidePadding="tabSidePadding"
                            :showScrollCue="showTabScrollCue"
                            @change="handlePrimaryCategoryTabChange"
                        ></quotation-category-tabs>
                    </view>
                    <template v-if="secondaryCategoryTabs.length">
                        <view class="category-tabs__secondary-panel">
                            <quotation-category-tabs
                                class="category-tabs__secondary-tabs"
                                :list="secondaryCategoryTabList"
                                :current="activeSecondaryCategoryIndex"
                                :variant="secondaryTabStyleType"
                                :themeColor="secondaryTabThemeColor"
                                :activeBgColor="secondaryTabActiveBgColor"
                                :inactiveBgColor="secondaryTabInactiveBgColor"
                                :activeTextColor="secondaryTabActiveTextColor"
                                :inactiveTextColor="secondaryTabInactiveTextColor"
                                :height="secondaryTabHeight"
                                :radius="secondaryTabRadius"
                                :fontSize="secondaryTabFontSize"
                                :fontWeight="secondaryTabFontWeight"
                                :sidePadding="secondaryTabSidePadding"
                                :showScrollCue="showTabScrollCue"
                                @change="handleSecondaryCategoryTabChange"
                            ></quotation-category-tabs>
                        </view>
                    </template>
                </view>

                <view v-if="loading" class="state-box">
                    <text class="state-text">报价加载中...</text>
                </view>

                <view v-else-if="displayList.length === 0" class="state-box empty">
                    <text class="state-title">暂无报价</text>
                    <text class="state-text">请先在报价插件中导入报价并开启展示</text>
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
                                <view v-if="showHotBadge(item)" class="hot-badge hot-badge--graphic" :style="hotBadgeBoxStyle">
                                    <image v-if="hotBadgeImage" class="hot-badge__image" :src="img(hotBadgeImage)" mode="aspectFit"></image>
                                    <text v-else class="hot-badge__text" :style="hotBadgeTextStyle">热门</text>
                                </view>
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
                                    <view v-if="showHotBadge(item)" class="hot-badge" :style="hotBadgeBoxStyle">
                                        <image v-if="hotBadgeImage" class="hot-badge__image" :src="img(hotBadgeImage)" mode="aspectFit"></image>
                                        <text v-else class="hot-badge__text" :style="hotBadgeTextStyle">热门</text>
                                    </view>
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
import { computed, getCurrentInstance, nextTick, onMounted, ref, watch } from 'vue'
import { onPageScroll } from '@dcloudio/uni-app'
import useDiyStore from '@/app/stores/diy'
import useSystemStore from '@/stores/system'
import useQuotationCacheStore, { buildQuotationCacheKey } from '@/addon/recycle_quote_spider/stores/quotation-cache'
import { img, pxToRpx, redirect } from '@/utils/common'
import { getQuoteSpiderCategoryTree, getQuoteSpiderFeatured, type QuoteSpiderCategory, type QuoteSpiderItem } from '@/addon/recycle_quote_spider/api/quotation'
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
const systemStore = useSystemStore()
const quotationCacheStore = useQuotationCacheStore()
const instance = getCurrentInstance()
const loading = ref(false)
const items = ref<QuoteSpiderItem[]>([])
const categories = ref<QuoteSpiderCategory[]>([])
const activePrimaryCategoryId = ref(0)
const activeSecondaryCategoryId = ref(0)
const pageScrollTop = ref(0)
const categoryTabsFixed = ref(false)
const categoryTabsMeasured = ref(false)
const categoryTabsHeight = ref(0)
const categoryTabsTriggerTop = ref(0)
const componentBottomTop = ref(0)
const categoryTabsLeft = ref(0)
const categoryTabsWidth = ref(0)
const isDecorateMode = computed(() => diyStore.mode === 'decorate')

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
    const value = Number(diyComponent.value.limit ?? 6)
    if (!Number.isFinite(value)) return 6
    if (value <= 0) return 0
    return Math.floor(value)
})
const onlyHot = computed(() => diyComponent.value.onlyHot === true || diyComponent.value.onlyHot === 1)
const showCategoryTabs = computed(() => diyComponent.value.showCategoryTabs !== false)
const stickyTabsEnabled = computed(() => diyComponent.value.stickyTabs === true || diyComponent.value.stickyTabs === 1)
const stickyTabsOffsetMode = computed(() => diyComponent.value.stickyTabsOffsetMode || 'auto')
const stickyTabsOffset = computed(() => {
    const value = Number(diyComponent.value.stickyTabsOffset ?? 0)
    return Number.isFinite(value) ? Math.max(0, Math.min(value, 260)) : 0
})
const flatGroupMode = computed(() => diyComponent.value.flatGroupMode || 'level2')
const showGroupCount = computed(() => diyComponent.value.showGroupCount !== false)
const showRefresh = computed(() => diyComponent.value.showRefresh !== false)
const displayStyle = computed(() => diyComponent.value.displayStyle || 'list')
const titleColor = computed(() => diyComponent.value.titleColor || '#1f2937')
const subtitleColor = computed(() => diyComponent.value.subtitleColor || '#6b7280')
const buttonColor = computed(() => diyComponent.value.buttonColor || '#3b82f6')
const groupTitleColor = computed(() => diyComponent.value.groupTitleColor || '#1f2937')
const groupCountColor = computed(() => diyComponent.value.groupCountColor || '#6b7280')
const showGroupTitle = computed(() => diyComponent.value.showGroupTitle !== false)
const groupTitleSize = computed(() => {
    const value = Number(diyComponent.value.groupTitleSize || 22)
    return Number.isFinite(value) ? Math.max(16, Math.min(value, 40)) : 22
})
const groupTitleWeight = computed(() => {
    const value = Number(diyComponent.value.groupTitleWeight || 500)
    return [400, 500, 600, 700].includes(value) ? value : 500
})
const groupTitleAlign = computed(() => diyComponent.value.groupTitleAlign === 'center' ? 'center' : 'left')
const groupTitleBgColor = computed(() => diyComponent.value.groupTitleBgColor || 'transparent')
const groupTitleRadius = computed(() => {
    const value = Number(diyComponent.value.groupTitleRadius ?? 0)
    return Number.isFinite(value) ? Math.max(0, Math.min(value, 48)) : 0
})
const groupTitlePaddingX = computed(() => {
    const value = Number(diyComponent.value.groupTitlePaddingX ?? 0)
    return Number.isFinite(value) ? Math.max(0, Math.min(value, 48)) : 0
})
const groupTitlePaddingY = computed(() => {
    const value = Number(diyComponent.value.groupTitlePaddingY ?? 0)
    return Number.isFinite(value) ? Math.max(0, Math.min(value, 32)) : 0
})
const itemTitleColor = computed(() => diyComponent.value.itemTitleColor || '#1f2937')
const itemMetaColor = computed(() => diyComponent.value.itemMetaColor || '#6b7280')
const itemTitleSize = computed(() => {
    const value = Number(diyComponent.value.itemTitleSize || 28)
    return Number.isFinite(value) ? Math.max(20, Math.min(value, 36)) : 28
})
const itemImageRadius = computed(() => {
    const value = Number(diyComponent.value.itemImageRadius ?? 20)
    return Number.isFinite(value) ? Math.max(0, Math.min(value, 50)) : 20
})
const showHotBadgeConfig = computed(() => diyComponent.value.showHotBadge !== false)
const hotBadgeImage = computed(() => String(diyComponent.value.hotBadgeImage || '').trim())
const hotBadgeSize = computed(() => {
    const value = Number(diyComponent.value.hotBadgeSize || 38)
    return Number.isFinite(value) ? Math.max(24, Math.min(value, 80)) : 38
})
const hotBadgeBoxStyle = computed(() => {
    if (hotBadgeImage.value) {
        return `width:${hotBadgeSize.value}rpx;height:${hotBadgeSize.value}rpx;`
    }
    return ''
})
const hotBadgeTextStyle = computed(() => {
    const fontSize = Math.max(18, Math.round(hotBadgeSize.value * 0.46))
    return `font-size:${fontSize}rpx;line-height:${Math.max(26, fontSize + 8)}rpx;`
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
const previewCategories: QuoteSpiderCategory[] = [
    {
        id: 101,
        source_id: 1,
        parent_id: 0,
        name: '环保回收',
        level: 1,
        sort: 1,
        is_show: 1,
        children: [
            { id: 1101, source_id: 1, parent_id: 101, name: '手机数码', level: 2, sort: 1, is_show: 1, children: [] },
            { id: 1102, source_id: 1, parent_id: 101, name: '电脑办公', level: 2, sort: 2, is_show: 1, children: [] },
            { id: 1103, source_id: 1, parent_id: 101, name: '智能穿戴', level: 2, sort: 3, is_show: 1, children: [] }
        ]
    },
    {
        id: 102,
        source_id: 1,
        parent_id: 0,
        name: '家电回收',
        level: 1,
        sort: 2,
        is_show: 1,
        children: [
            { id: 1201, source_id: 1, parent_id: 102, name: '厨房电器', level: 2, sort: 1, is_show: 1, children: [] },
            { id: 1202, source_id: 1, parent_id: 102, name: '生活电器', level: 2, sort: 2, is_show: 1, children: [] }
        ]
    }
]
const displayCategories = computed(() => {
    return isDecorateMode.value && categories.value.length === 0 ? previewCategories : categories.value
})
const primaryCategoryTabs = computed(() => displayCategories.value.filter(item => Number(item.level || 0) <= 1 || Number(item.parent_id || 0) === 0))
const activePrimaryCategory = computed(() => {
    return primaryCategoryTabs.value.find(item => Number(item.id || 0) === activePrimaryCategoryId.value) || primaryCategoryTabs.value[0] || null
})
const activePrimaryCategoryName = computed(() => String(activePrimaryCategory.value?.name || ''))
const secondaryCategoryTabs = computed(() => {
    const children = activePrimaryCategory.value?.children || []
    return children.filter(item => Number(item.is_show ?? 1) === 1)
})
const primaryCategoryTabList = computed(() => {
    return primaryCategoryTabs.value.map(item => ({
        name: String(item.name || '未命名分类'),
        id: Number(item.id || 0),
        level: 1
    }))
})
const secondaryCategoryTabList = computed(() => {
    return secondaryCategoryTabs.value.map(item => ({
        name: String(item.name || '未命名分类'),
        id: Number(item.id || 0),
        level: 2
    }))
})
const activePrimaryCategoryIndex = computed(() => {
    const index = primaryCategoryTabs.value.findIndex(item => Number(item.id || 0) === activePrimaryCategoryId.value)
    return index >= 0 ? index : 0
})
const activeSecondaryCategoryIndex = computed(() => {
    if (!activeSecondaryCategoryId.value) return -1
    return secondaryCategoryTabs.value.findIndex(item => Number(item.id || 0) === activeSecondaryCategoryId.value)
})
const activeRequestCategoryId = computed(() => {
    return activeSecondaryCategoryId.value || activePrimaryCategoryId.value || Number(activePrimaryCategory.value?.id || 0)
})
const tabStyleType = computed(() => {
    const value = diyComponent.value.tabStyleType || 'pill'
    return ['pill', 'card', 'underline'].includes(value) ? value : 'pill'
})
const tabThemeColor = computed(() => diyComponent.value.tabThemeColor || buttonColor.value)
const tabActiveBgColor = computed(() => diyComponent.value.tabActiveBgColor || '')
const tabInactiveBgColor = computed(() => diyComponent.value.tabInactiveBgColor || '')
const tabActiveTextColor = computed(() => diyComponent.value.tabActiveTextColor || '')
const tabInactiveTextColor = computed(() => diyComponent.value.tabInactiveTextColor || '#6b7280')
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
const secondaryTabCustom = computed(() => diyComponent.value.secondaryTabCustom === true || diyComponent.value.secondaryTabCustom === 1)
const secondaryTabStyleType = computed(() => {
    if (!secondaryTabCustom.value) return tabStyleType.value
    const value = diyComponent.value.secondaryTabStyleType || tabStyleType.value
    return ['pill', 'card', 'underline'].includes(value) ? value : tabStyleType.value
})
const secondaryTabThemeColor = computed(() => secondaryTabCustom.value ? (diyComponent.value.secondaryTabThemeColor || tabThemeColor.value) : tabThemeColor.value)
const secondaryTabActiveBgColor = computed(() => secondaryTabCustom.value ? (diyComponent.value.secondaryTabActiveBgColor || '') : tabActiveBgColor.value)
const secondaryTabInactiveBgColor = computed(() => secondaryTabCustom.value ? (diyComponent.value.secondaryTabInactiveBgColor || '') : tabInactiveBgColor.value)
const secondaryTabActiveTextColor = computed(() => secondaryTabCustom.value ? (diyComponent.value.secondaryTabActiveTextColor || '') : tabActiveTextColor.value)
const secondaryTabInactiveTextColor = computed(() => secondaryTabCustom.value ? (diyComponent.value.secondaryTabInactiveTextColor || '#6b7280') : tabInactiveTextColor.value)
const secondaryTabHeight = computed(() => {
    if (!secondaryTabCustom.value) return tabHeight.value
    const value = Number(diyComponent.value.secondaryTabHeight || tabHeight.value)
    return Number.isFinite(value) ? Math.max(44, Math.min(value, 96)) : tabHeight.value
})
const secondaryTabRadius = computed(() => {
    if (!secondaryTabCustom.value) return tabRadius.value
    const value = Number(diyComponent.value.secondaryTabRadius ?? tabRadius.value)
    return Number.isFinite(value) ? Math.max(0, Math.min(value, 48)) : tabRadius.value
})
const secondaryTabFontSize = computed(() => {
    if (!secondaryTabCustom.value) return tabFontSize.value
    const value = Number(diyComponent.value.secondaryTabFontSize || tabFontSize.value)
    return Number.isFinite(value) ? Math.max(20, Math.min(value, 34)) : tabFontSize.value
})
const secondaryTabFontWeight = computed(() => {
    if (!secondaryTabCustom.value) return tabFontWeight.value
    const value = Number(diyComponent.value.secondaryTabFontWeight || tabFontWeight.value)
    return [400, 500, 600, 700].includes(value) ? value : tabFontWeight.value
})
const secondaryTabSidePadding = computed(() => {
    if (!secondaryTabCustom.value) return tabSidePadding.value
    const value = Number(diyComponent.value.secondaryTabSidePadding || tabSidePadding.value)
    return Number.isFinite(value) ? Math.max(8, Math.min(value, 40)) : tabSidePadding.value
})
const showTabScrollCue = computed(() => diyComponent.value.showTabScrollCue !== false)
const categoryTabsId = computed(() => `spider-quotation-tabs-${diyComponent.value.id || props.index}`)
const categoryTabsStyle = computed(() => {
    if (!stickyTabsEnabled.value || isDecorateMode.value) {
        return ''
    }

    if (!categoryTabsFixed.value) return ''
    return `position:fixed;top:${stickyTabsTopPx.value}px;left:${categoryTabsLeft.value}px;width:${categoryTabsWidth.value}px;z-index:99;`
})
const categoryTabsPlaceholderStyle = computed(() => {
    if (!categoryTabsFixed.value || !categoryTabsHeight.value) return ''
    return `height:${categoryTabsHeight.value}px;`
})
const autoStickyOffset = computed(() => {
    const info = systemStore.menuButtonInfo || {}
    const topPx = Number(info.top || 0) + Number(info.height || 0) + 8
    if (!topPx || !systemStore.systemInfo?.screenWidth) return 0
    return Math.ceil(pxToRpx(topPx))
})
const stickyTabsTopRpx = computed(() => {
    if (stickyTabsOffsetMode.value === 'top') return 0
    if (stickyTabsOffsetMode.value === 'manual') return stickyTabsOffset.value
    return autoStickyOffset.value + stickyTabsOffset.value
})
const stickyTabsTopPx = computed(() => {
    const screenWidth = Number(systemStore.systemInfo?.screenWidth || 375)
    return Math.round(stickyTabsTopRpx.value * screenWidth / 750)
})
const groupTitleStyle = computed(() => {
    return `color:${groupTitleColor.value};font-size:${groupTitleSize.value}rpx;line-height:${Math.max(groupTitleSize.value + 10, 30)}rpx;font-weight:${groupTitleWeight.value};background:${groupTitleBgColor.value};border-radius:${groupTitleRadius.value}rpx;padding:${groupTitlePaddingY.value}rpx ${groupTitlePaddingX.value}rpx;`
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
        category_id: 1101,
        brand: '苹果',
        tab: 'iPhone',
        name: 'iPhone 实时报价',
        parent_name: '环保回收 / 手机数码',
        category_path: '环保回收 / 手机数码',
        title: 'iPhone 实时报价',
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
        category_id: 1101,
        brand: '安卓',
        tab: '旗舰机',
        name: '安卓旗舰报价',
        parent_name: '环保回收 / 手机数码',
        category_path: '环保回收 / 手机数码',
        title: '安卓旗舰报价',
        quote_type: 'manual',
        is_image_quote: 0,
        image: '',
        timage: '',
        bimage: '',
        icon: '',
        is_hot: 0,
        model_count: 28,
        last_sync_at_text: '今日 10:00'
    },
    {
        id: 3,
        source_id: 1,
        category_id: 1102,
        brand: '苹果电脑',
        tab: 'MacBook',
        name: 'MacBook 报价',
        parent_name: '环保回收 / 电脑办公',
        category_path: '环保回收 / 电脑办公',
        title: 'MacBook 报价',
        quote_type: 'manual',
        is_image_quote: 0,
        image: '',
        timage: '',
        bimage: '',
        icon: '',
        is_hot: 1,
        model_count: 18,
        last_sync_at_text: '今日 09:30'
    },
    {
        id: 4,
        source_id: 1,
        category_id: 1103,
        brand: '智能手表',
        tab: 'Watch',
        name: '智能手表报价',
        parent_name: '环保回收 / 智能穿戴',
        category_path: '环保回收 / 智能穿戴',
        title: '智能手表报价',
        quote_type: 'manual',
        is_image_quote: 0,
        image: '',
        timage: '',
        bimage: '',
        icon: '',
        is_hot: 0,
        model_count: 12,
        last_sync_at_text: '昨日 18:20'
    },
    {
        id: 5,
        source_id: 1,
        category_id: 1201,
        brand: '厨房电器',
        tab: '厨电',
        name: '厨房电器报价',
        parent_name: '家电回收 / 厨房电器',
        category_path: '家电回收 / 厨房电器',
        title: '厨房电器报价',
        quote_type: 'manual',
        is_image_quote: 0,
        image: '',
        timage: '',
        bimage: '',
        icon: '',
        is_hot: 1,
        model_count: 9,
        last_sync_at_text: '今日 11:10'
    },
    {
        id: 6,
        source_id: 1,
        category_id: 1202,
        brand: '生活电器',
        tab: '家电',
        name: '生活电器报价',
        parent_name: '家电回收 / 生活电器',
        category_path: '家电回收 / 生活电器',
        title: '生活电器报价',
        quote_type: 'manual',
        is_image_quote: 0,
        image: '',
        timage: '',
        bimage: '',
        icon: '',
        is_hot: 0,
        model_count: 16,
        last_sync_at_text: '今日 08:45'
    }
])

const displayList = computed(() => {
    let list = isDecorateMode.value && items.value.length === 0 ? mockList.value : items.value
    if (isDecorateMode.value) {
        if (onlyHot.value) {
            list = list.filter(item => Number(item.is_hot || 0) === 1)
        }
        if (showCategoryTabs.value && activeRequestCategoryId.value) {
            const categoryIds = collectPreviewCategoryIds(activeRequestCategoryId.value)
            if (categoryIds.length) {
                list = list.filter(item => categoryIds.includes(Number(item.category_id || 0)))
            }
        }
    }
    return limit.value > 0 ? list.slice(0, limit.value) : list
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

const wrapStyle = computed(() => {
    return 'position:relative;'
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
        style += 'background:var(--bg-card);'
    }
    if (bgUrl) {
        style += `background-image:url('${img(bgUrl)}');background-size:cover;background-repeat:no-repeat;background-position:center;`
    }
    style += `border-top-left-radius:${topRounded}rpx;border-top-right-radius:${topRounded}rpx;`
    style += `border-bottom-left-radius:${bottomRounded}rpx;border-bottom-right-radius:${bottomRounded}rpx;`
    style += 'border:1rpx solid var(--line);'
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

function showHotBadge(item: QuoteSpiderItem) {
    return showHotBadgeConfig.value && Number(item.is_hot || 0) === 1
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
    return Boolean(group.title) && showGroupTitle.value
}

function collectPreviewCategoryIds(categoryId: number): number[] {
    if (!isDecorateMode.value) {
        return [categoryId]
    }

    const ids: number[] = []
    const walk = (list: QuoteSpiderCategory[]) => {
        for (const item of list) {
            const id = Number(item.id || 0)
            if (id === categoryId) {
                ids.push(id)
                collectChildIds(item.children || [])
                return
            }
            walk(item.children || [])
        }
    }
    const collectChildIds = (list: QuoteSpiderCategory[]) => {
        for (const item of list) {
            const id = Number(item.id || 0)
            if (id) ids.push(id)
            collectChildIds(item.children || [])
        }
    }

    walk(displayCategories.value)
    return ids.length ? ids : [categoryId]
}

async function loadCategories() {
    if (isDecorateMode.value) {
        categories.value = []
        activePrimaryCategoryId.value = Number(previewCategories[0]?.id || 0)
        activeSecondaryCategoryId.value = 0
        return
    }

    const params = {
        source_id: sourceId.value || ''
    }
    const cacheKey = buildQuotationCacheKey('spider-category-tree', params)
    const cached = quotationCacheStore.get<QuoteSpiderCategory[]>(cacheKey)
    if (cached) {
        categories.value = cached
        if (showCategoryTabs.value && activePrimaryCategoryId.value === 0 && primaryCategoryTabs.value.length) {
            activePrimaryCategoryId.value = Number(primaryCategoryTabs.value[0].id || 0)
        }
        return
    }

    try {
        const res = await getQuoteSpiderCategoryTree(params) as any
        const list = res.code === 1 && Array.isArray(res.data) ? res.data : []
        categories.value = list
        quotationCacheStore.set(cacheKey, list)
        if (showCategoryTabs.value && activePrimaryCategoryId.value === 0 && primaryCategoryTabs.value.length) {
            activePrimaryCategoryId.value = Number(primaryCategoryTabs.value[0].id || 0)
        }
    } catch (error) {
        categories.value = []
        activePrimaryCategoryId.value = 0
        activeSecondaryCategoryId.value = 0
    }
}

function switchPrimaryCategory(categoryId: number) {
    if (activePrimaryCategoryId.value === categoryId && activeSecondaryCategoryId.value === 0) return
    activePrimaryCategoryId.value = categoryId
    activeSecondaryCategoryId.value = 0
    loadItems()
}

function switchSecondaryCategory(categoryId: number) {
    if (activeSecondaryCategoryId.value === categoryId) return
    activeSecondaryCategoryId.value = categoryId
    loadItems()
}

function handlePrimaryCategoryTabChange(tab: any) {
    const index = Number(tab?.index ?? tab ?? 0)
    const category = primaryCategoryTabs.value[index]
    const categoryId = Number(category?.id ?? tab?.id ?? tab?.value ?? 0)
    if (!categoryId) return
    switchPrimaryCategory(categoryId)
}

function handleSecondaryCategoryTabChange(tab: any) {
    const index = Number(tab?.index ?? tab ?? 0)
    const category = secondaryCategoryTabs.value[index]
    const categoryId = Number(category?.id ?? tab?.id ?? tab?.value ?? 0)
    if (!categoryId) return
    switchSecondaryCategory(categoryId)
}

async function loadItems(forceRefresh = false) {
    if (isDecorateMode.value) {
        items.value = []
        return
    }

    const params = {
        source_id: sourceId.value || '',
        category_id: showCategoryTabs.value && activeRequestCategoryId.value ? activeRequestCategoryId.value : '',
        limit: limit.value,
        only_hot: onlyHot.value ? 1 : ''
    }
    const cacheKey = buildQuotationCacheKey('spider-featured', params)
    if (!forceRefresh) {
        const cached = quotationCacheStore.get<QuoteSpiderItem[]>(cacheKey)
        if (cached) {
            items.value = cached
            return
        }
    }

    loading.value = items.value.length === 0
    try {
        const res = await getQuoteSpiderFeatured(params) as any
        const list = res.code === 1 && Array.isArray(res.data) ? res.data : []
        items.value = list
        quotationCacheStore.set(cacheKey, list)
    } catch (error) {
        if (items.value.length === 0) items.value = []
    } finally {
        loading.value = false
    }
}

function openQuotation(item: QuoteSpiderItem) {
    if (diyStore.mode === 'decorate') return
    if (isImageQuotation(item)) {
        const imageUrl = resolveQuotationPreviewImage(item)
        if (!imageUrl) {
            uni.showToast({ title: '暂无报价图片', icon: 'none' })
            return
        }
        uni.previewImage({
            urls: [img(imageUrl)],
            current: 0
        })
        return
    }
    redirect({
        url: `/addon/recycle_quote_spider/pages/price/show_price?id=${item.id}`
    })
}

function isImageQuotation(item: QuoteSpiderItem): boolean {
    return Number(item.is_image_quote || 0) === 1 && Boolean(resolveQuotationPreviewImage(item))
}

function resolveQuotationPreviewImage(item: QuoteSpiderItem): string {
    return item.bimage || item.image || item.timage || ''
}

onMounted(() => {
    loadCategories().finally(loadItems)
    scheduleMeasureStickyTabs()
})

watch(
    () => diyStore.scrollTop,
    (value) => {
        if (Number.isFinite(Number(value))) {
            handlePageScroll(Number(value))
        }
    }
)

watch(
    () => [
        stickyTabsEnabled.value,
        stickyTabsTopPx.value,
        showCategoryTabs.value,
        primaryCategoryTabs.value.length,
        secondaryCategoryTabs.value.length,
        activePrimaryCategoryId.value,
        activeSecondaryCategoryId.value,
        tabHeight.value,
        tabFontSize.value,
        tabSidePadding.value,
        secondaryTabHeight.value,
        secondaryTabFontSize.value,
        secondaryTabSidePadding.value,
        secondaryTabCustom.value
    ],
    () => {
        categoryTabsMeasured.value = false
        categoryTabsFixed.value = false
        scheduleMeasureStickyTabs()
    }
)

onPageScroll((event) => {
    handlePageScroll(Number(event.scrollTop || 0))
})

function handlePageScroll(scrollTop: number) {
    pageScrollTop.value = Math.max(0, scrollTop)
    if (!stickyTabsEnabled.value || isDecorateMode.value || !showCategoryTabs.value || !primaryCategoryTabs.value.length) {
        categoryTabsFixed.value = false
        return
    }

    if (!categoryTabsMeasured.value) {
        scheduleMeasureStickyTabs()
        return
    }

    const top = pageScrollTop.value
    const fixedBottom = top + stickyTabsTopPx.value + categoryTabsHeight.value
    categoryTabsFixed.value = top >= categoryTabsTriggerTop.value && fixedBottom < componentBottomTop.value
}

function scheduleMeasureStickyTabs() {
    if (isDecorateMode.value || !stickyTabsEnabled.value || !showCategoryTabs.value || !primaryCategoryTabs.value.length) return
    nextTick(() => {
        setTimeout(() => {
            measureStickyTabs()
        }, 80)
    })
}

function measureStickyTabs() {
    if (!instance || categoryTabsFixed.value) return
    const query = uni.createSelectorQuery().in(instance.proxy)
    query.select(`#${categoryTabsId.value}`).boundingClientRect()
    query.select('.quotation-wrap').boundingClientRect()
    query.exec((rects: any[]) => {
        const tabsRect = rects?.[0]
        const wrapRect = rects?.[1]
        if (!tabsRect || !wrapRect) return

        const scrollTop = pageScrollTop.value
        categoryTabsLeft.value = Number(tabsRect.left || 0)
        categoryTabsWidth.value = Number(tabsRect.width || systemStore.systemInfo?.screenWidth || 375)
        categoryTabsHeight.value = Number(tabsRect.height || 0)
        categoryTabsTriggerTop.value = scrollTop + Number(tabsRect.top || 0) - stickyTabsTopPx.value
        componentBottomTop.value = scrollTop + Number(wrapRect.top || 0) + Number(wrapRect.height || 0)
        categoryTabsMeasured.value = true
        handlePageScroll(scrollTop)
    })
}
</script>

<style lang="scss" scoped>
.quotation-wrap {
    --bg-main: #f3f4f6;
    --bg-card: #ffffff;
    --bg-soft: #f7f7f8;
    --line: #e5e7eb;
    --text-main: #1f2937;
    --text-sub: #6b7280;
    --brand: #3b82f6;
    --brand-deep: #4f46e5;
    --notice-bg: #fff8ed;
    --notice-text: #f59e0b;
    --button-bg: #111827;
    --button-text: #ffffff;
    box-sizing: border-box;
}

.quotation-card {
    position: relative;
    overflow: hidden;
    box-shadow: 0 10rpx 24rpx rgba(31, 41, 55, 0.08);
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
    background: var(--bg-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-sub);
    font-size: 28rpx;
}

.category-tabs {
    width: 100%;
    padding: 0 18rpx 18rpx;
    box-sizing: border-box;
    background: var(--bg-card);
}

.category-tabs.fixed {
    box-shadow: 0 8rpx 22rpx rgba(15, 23, 42, 0.08);
}

.category-tabs__primary {
    padding: 2rpx 0 4rpx;
}

.category-tabs__secondary-panel {
    position: relative;
    margin-top: 12rpx;
    padding: 14rpx 14rpx 12rpx;
    border-radius: 18rpx;
    background: linear-gradient(180deg, var(--bg-soft) 0%, var(--bg-card) 100%);
    border: 1rpx solid var(--line);
    box-shadow: inset 0 1rpx 0 rgba(255, 255, 255, 0.9);
}

.category-tabs__relation {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 8rpx;
    margin-bottom: 10rpx;
}

.category-tabs__parent-name {
    min-width: 0;
    max-width: 420rpx;
    color: var(--text-main);
    font-size: 24rpx;
    line-height: 34rpx;
    font-weight: 800;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.category-tabs__arrow {
    flex-shrink: 0;
    color: var(--text-sub);
    font-size: 22rpx;
    line-height: 32rpx;
    font-weight: 600;
}

.category-tabs__secondary-tabs {
    position: relative;
    z-index: 1;
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
    max-width: 100%;
    box-sizing: border-box;
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
    border-top: 1rpx solid var(--line);
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
    color: var(--text-main);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hot-badge {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hot-badge--graphic {
    position: absolute;
    top: 8rpx;
    right: 14rpx;
    z-index: 2;
}

.hot-badge__image {
    display: block;
    width: 100%;
    height: 100%;
}

.hot-badge__text {
    display: block;
    padding: 2rpx 10rpx;
    border-radius: 999rpx;
    background: var(--notice-bg);
    color: var(--notice-text);
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 4rpx 10rpx rgba(225, 29, 72, 0.12);
}

.dataset-meta {
    margin-top: 8rpx;
    display: flex;
    align-items: center;
    font-size: 23rpx;
    line-height: 32rpx;
    color: var(--text-sub);
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
    border-top: 1rpx solid var(--line);
}

.quotation-nav-item {
    position: relative;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 14rpx 8rpx;
}

.quotation-nav-img {
    overflow: hidden;
    background: var(--bg-soft);

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
    color: var(--text-main);
    font-weight: 600;
}

.state-text {
    margin-top: 6rpx;
    font-size: 24rpx;
    line-height: 34rpx;
    color: var(--text-sub);
}
</style>
