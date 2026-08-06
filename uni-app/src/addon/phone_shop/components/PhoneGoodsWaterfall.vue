<template>
    <view class="phone-goods-waterfall">
        <view class="phone-goods-waterfall__column">
            <view v-for="entry in columns.left" :key="entry.key" class="phone-goods-waterfall__item">
                <slot :item="entry.item" :index="entry.index" />
            </view>
        </view>
        <view class="phone-goods-waterfall__column phone-goods-waterfall__column--right">
            <view v-for="entry in columns.right" :key="entry.key" class="phone-goods-waterfall__item">
                <slot :item="entry.item" :index="entry.index" />
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface WaterfallEntry {
    item: Record<string, any>
    index: number
    key: string | number
}

const props = withDefaults(defineProps<{
    items?: Array<Record<string, any>>
    itemKey?: string
    estimateHeight?: (item: Record<string, any>, index: number) => number
}>(), {
    items: () => [],
    itemKey: 'goods_id'
})

const columns = computed(() => {
    const left: WaterfallEntry[] = []
    const right: WaterfallEntry[] = []
    let leftHeight = 0
    let rightHeight = 0

    props.items.forEach((item, index) => {
        const estimated = Math.max(1, Number(props.estimateHeight?.(item, index) || 1))
        const entry: WaterfallEntry = {
            item,
            index,
            key: item?.[props.itemKey] ?? index
        }

        if (leftHeight <= rightHeight) {
            left.push(entry)
            leftHeight += estimated
        } else {
            right.push(entry)
            rightHeight += estimated
        }
    })

    return { left, right }
})
</script>

<style scoped lang="scss">
.phone-goods-waterfall {
    width: 100%;
    display: flex;
    align-items: flex-start;
    box-sizing: border-box;
}

.phone-goods-waterfall__column {
    width: 0;
    min-width: 0;
    flex: 1;
}

.phone-goods-waterfall__column--right {
    margin-left: 12rpx;
}

.phone-goods-waterfall__item {
    width: 100%;
    min-width: 0;
    margin-top: var(--top-m);
}
</style>
