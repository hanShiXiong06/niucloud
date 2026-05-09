<template>
    <view v-if="list.length" class="quote-category-tabs" :class="[`quote-category-tabs--${variant}`, { 'has-cue': showScrollCue }]" :style="wrapStyle">
        <scroll-view
            class="quote-category-tabs__scroll"
            scroll-x
            :show-scrollbar="false"
            enable-flex
        >
            <view class="quote-category-tabs__track" :style="trackStyle">
                <view
                    v-for="(item, index) in list"
                    :key="item.id || index"
                    class="quote-category-tabs__chip"
                    :class="{ active: Number(index) === Number(current) }"
                    :style="getChipStyle(Number(index))"
                    @click="handleChange(item, Number(index))"
                >
                    <text class="quote-category-tabs__text" :style="getTextStyle(Number(index))">{{ item.name }}</text>
                    <text v-if="item.levelLabel" class="quote-category-tabs__level" :style="getLevelStyle(Number(index))">{{ item.levelLabel }}</text>
                    <view
                        v-if="variant === 'underline' && Number(index) === Number(current)"
                        class="quote-category-tabs__line"
                        :style="lineStyle"
                    ></view>
                </view>
            </view>
        </scroll-view>
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
        default: '#3b82f6'
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
        default: '#6b7280'
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
const themeColorValue = computed(() => props.themeColor || '#3b82f6')
const activeBgColorValue = computed(() => props.activeBgColor || (variant.value === 'card' ? '#ffffff' : themeColorValue.value))
const inactiveBgColorValue = computed(() => props.inactiveBgColor || (variant.value === 'underline' ? 'transparent' : '#f7f7f8'))
const activeTextColorValue = computed(() => props.activeTextColor || (variant.value === 'pill' ? '#ffffff' : themeColorValue.value))
const inactiveTextColorValue = computed(() => props.inactiveTextColor || '#6b7280')
const itemHeight = computed(() => Math.max(Number(props.height || 64), 44))
const itemRadius = computed(() => Math.max(Number(props.radius || 32), 0))
const itemFontSize = computed(() => Math.max(Number(props.fontSize || 26), 20))
const itemFontWeight = computed(() => [400, 500, 600, 700].includes(Number(props.fontWeight)) ? Number(props.fontWeight) : 600)
const itemSidePadding = computed(() => Math.max(Number(props.sidePadding || 18), 8))

const wrapStyle = computed(() => {
    let style = 'position:relative;box-sizing:border-box;'
    if (variant.value === 'card') {
        style += `background:${inactiveBgColorValue.value};border-radius:${itemRadius.value + 10}rpx;padding:8rpx;border:1rpx solid #e5e7eb;`
    } else if (variant.value === 'pill') {
        style += 'padding:2rpx 0;'
    } else {
        style += 'padding:0;'
    }
    return style
})

const trackStyle = computed(() => {
    const height = variant.value === 'underline' ? itemHeight.value + 8 : itemHeight.value + 4
    return `min-height:${height}rpx;`
})

const cueStyle = computed(() => {
    return `height:${itemHeight.value + 12}rpx;background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.96) 42%,rgba(255,255,255,1));`
})

const lineStyle = computed(() => {
    return `width:34rpx;height:5rpx;border-radius:999rpx;background:${themeColorValue.value};`
})

function getChipStyle(index: number) {
    const active = Number(index) === Number(props.current)
    if (variant.value === 'underline') {
        return [
            `height:${itemHeight.value}rpx`,
            `padding:0 ${itemSidePadding.value}rpx`,
            'position:relative;display:flex;align-items:center;justify-content:center;box-sizing:border-box;flex-shrink:0'
        ].join(';') + ';'
    }

    const bgColor = active ? activeBgColorValue.value : inactiveBgColorValue.value
    const shadow = active
        ? (variant.value === 'card' ? 'box-shadow:0 8rpx 22rpx rgba(31,41,55,.10);' : 'box-shadow:0 8rpx 18rpx rgba(59,130,246,.20);')
        : 'box-shadow:0 4rpx 10rpx rgba(15,23,42,.04);'
    const border = active ? `border:1rpx solid ${activeBgColorValue.value};` : 'border:1rpx solid #e5e7eb;'

    return [
        `height:${itemHeight.value}rpx`,
        `padding:0 ${itemSidePadding.value}rpx`,
        `border-radius:${itemRadius.value}rpx`,
        `background:${bgColor}`,
        border,
        'display:flex;align-items:center;justify-content:center;box-sizing:border-box;flex-shrink:0',
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

function getLevelStyle(index: number) {
    const active = Number(index) === Number(props.current)
    return [
        `color:${active ? activeTextColorValue.value : inactiveTextColorValue.value}`,
        'font-size:18rpx',
        'line-height:24rpx',
        'opacity:.68',
        'margin-left:6rpx'
    ].join(';') + ';'
}

function handleChange(item: Record<string, any>, index: number) {
    emit('change', {
        ...item,
        index
    })
}
</script>

<style lang="scss" scoped>
.quote-category-tabs {
    width: 100%;
    overflow: hidden;
}

.quote-category-tabs__scroll {
    width: 100%;
    white-space: nowrap;
}

.quote-category-tabs__track {
    display: flex;
    align-items: center;
    gap: 12rpx;
    width: max-content;
    box-sizing: border-box;
}

.quote-category-tabs__chip {
    transition: all .18s ease;
    position: relative;
}

.quote-category-tabs__chip::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: rgba(15, 23, 42, 0);
    transition: background .16s ease;
    pointer-events: none;
}

.quote-category-tabs__chip:active::after {
    background: rgba(15, 23, 42, 0.08);
}

.quote-category-tabs__line {
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%);
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
    border-bottom: 1rpx solid #e5e7eb;
}
</style>
