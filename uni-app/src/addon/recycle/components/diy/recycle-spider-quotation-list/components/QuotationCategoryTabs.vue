<template>
    <view v-if="list.length" class="quote-category-tabs" :class="[`quote-category-tabs--${variant}`, { 'has-cue': showScrollCue }]" :style="wrapStyle">
        <up-tabs
            :list="list"
            keyName="name"
            :current="current"
            :scrollable="true"
            :lineWidth="lineWidthValue"
            :lineHeight="lineHeightValue"
            :lineColor="lineColorValue"
            :activeStyle="activeTextStyle"
            :inactiveStyle="inactiveTextStyle"
            :itemStyle="itemStyle"
            @change="handleChange"
        >
            <template #content="{ item, index }">
                <view class="quote-category-tabs__chip" :style="getChipStyle(Number(index))">
                    <text class="quote-category-tabs__text" :style="getTextStyle(Number(index))">{{ item.name }}</text>
                </view>
            </template>
        </up-tabs>
        <view v-if="showScrollCue && list.length > 3" class="quote-category-tabs__cue" :style="cueStyle">
            <up-icon name="arrow-right" size="14" :color="inactiveTextColorValue"></up-icon>
        </view>
    </view>
</template>

<script lang="ts" setup>
import { computed } from 'vue'

const props = defineProps({
    list: {
        type: Array as () => Array<Record<string, any>>,
        default: () => []
    },
    current: {
        type: Number,
        default: 0
    },
    variant: {
        type: String,
        default: 'pill'
    },
    themeColor: {
        type: String,
        default: '#2563EB'
    },
    activeBgColor: {
        type: String,
        default: ''
    },
    inactiveBgColor: {
        type: String,
        default: ''
    },
    activeTextColor: {
        type: String,
        default: ''
    },
    inactiveTextColor: {
        type: String,
        default: '#475569'
    },
    borderColor: {
        type: String,
        default: ''
    },
    height: {
        type: Number,
        default: 64
    },
    radius: {
        type: Number,
        default: 32
    },
    fontSize: {
        type: Number,
        default: 26
    },
    fontWeight: {
        type: Number,
        default: 600
    },
    sidePadding: {
        type: Number,
        default: 18
    },
    showScrollCue: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['change'])

const variant = computed(() => ['pill', 'card', 'underline'].includes(props.variant) ? props.variant : 'pill')
const themeColorValue = computed(() => props.themeColor || '#2563EB')
const activeBgColorValue = computed(() => props.activeBgColor || (variant.value === 'card' ? '#ffffff' : themeColorValue.value))
const inactiveBgColorValue = computed(() => props.inactiveBgColor || (variant.value === 'underline' ? 'transparent' : '#F1F5F9'))
const activeTextColorValue = computed(() => props.activeTextColor || (variant.value === 'pill' ? '#ffffff' : themeColorValue.value))
const inactiveTextColorValue = computed(() => props.inactiveTextColor || '#475569')
const borderColorValue = computed(() => props.borderColor || (variant.value === 'card' ? '#E2E8F0' : 'transparent'))
const lineWidthValue = computed(() => variant.value === 'underline' ? 34 : 0)
const lineHeightValue = computed(() => variant.value === 'underline' ? 5 : 0)
const lineColorValue = computed(() => variant.value === 'underline' ? themeColorValue.value : 'transparent')
const itemHeight = computed(() => Math.max(Number(props.height || 64), 44))
const itemRadius = computed(() => Math.max(Number(props.radius || 32), 0))
const itemFontSize = computed(() => Math.max(Number(props.fontSize || 26), 20))
const itemFontWeight = computed(() => [400, 500, 600, 700].includes(Number(props.fontWeight)) ? Number(props.fontWeight) : 600)
const itemSidePadding = computed(() => Math.max(Number(props.sidePadding || 18), 8))

const wrapStyle = computed(() => {
    let style = 'position:relative;box-sizing:border-box;'
    if (variant.value === 'card') {
        style += `background:${inactiveBgColorValue.value};border:1rpx solid ${borderColorValue.value};border-radius:${itemRadius.value + 10}rpx;padding:8rpx;`
    } else if (variant.value === 'pill') {
        style += 'padding:2rpx 0;'
    } else {
        style += 'padding:0;'
    }
    return style
})

const itemStyle = computed(() => {
    const height = variant.value === 'underline' ? itemHeight.value + 8 : itemHeight.value + 4
    return `height:${height}rpx;padding-left:${Math.max(itemSidePadding.value - 6, 4)}rpx;padding-right:${Math.max(itemSidePadding.value - 6, 4)}rpx;`
})

const activeTextStyle = computed(() => ({
    color: activeTextColorValue.value,
    fontWeight: String(itemFontWeight.value),
    fontSize: `${itemFontSize.value}rpx`
}))

const inactiveTextStyle = computed(() => ({
    color: inactiveTextColorValue.value,
    fontWeight: '500',
    fontSize: `${itemFontSize.value}rpx`
}))

const cueStyle = computed(() => {
    return `height:${itemHeight.value + 12}rpx;background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.96) 42%,rgba(255,255,255,1));`
})

function getChipStyle(index: number) {
    const active = Number(index) === Number(props.current)
    if (variant.value === 'underline') {
        return [
            `height:${itemHeight.value}rpx`,
            `padding:0 ${itemSidePadding.value}rpx`,
            'display:flex;align-items:center;justify-content:center;box-sizing:border-box'
        ].join(';') + ';'
    }

    const bgColor = active ? activeBgColorValue.value : inactiveBgColorValue.value
    const borderColor = active && variant.value === 'card' ? '#ffffff' : borderColorValue.value
    const shadow = active
        ? (variant.value === 'card' ? 'box-shadow:0 8rpx 22rpx rgba(15,23,42,.10);' : 'box-shadow:0 8rpx 18rpx rgba(37,99,235,.20);')
        : ''

    return [
        `height:${itemHeight.value}rpx`,
        `padding:0 ${itemSidePadding.value}rpx`,
        `border-radius:${itemRadius.value}rpx`,
        `background:${bgColor}`,
        `border:1rpx solid ${borderColor}`,
        'display:flex;align-items:center;justify-content:center;box-sizing:border-box',
        shadow
    ].join(';') + ';'
}

function getTextStyle(index: number) {
    const active = Number(index) === Number(props.current)
    return [
        `color:${active ? activeTextColorValue.value : inactiveTextColorValue.value}`,
        `font-size:${itemFontSize.value}rpx`,
        `line-height:${Math.max(itemFontSize.value + 10, 30)}rpx`,
        `font-weight:${active ? itemFontWeight.value : 500}`,
        'white-space:nowrap'
    ].join(';') + ';'
}

function handleChange(tab: any) {
    emit('change', tab)
}
</script>

<style lang="scss" scoped>
.quote-category-tabs {
    width: 100%;
    overflow: hidden;
}

.quote-category-tabs__chip {
    transition: all .18s ease;
}

.quote-category-tabs__cue {
    position: absolute;
    top: 0;
    right: 0;
    width: 56rpx;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 4rpx;
    box-sizing: border-box;
}

.quote-category-tabs--underline {
    border-bottom: 1rpx solid #f1f5f9;
}
</style>
