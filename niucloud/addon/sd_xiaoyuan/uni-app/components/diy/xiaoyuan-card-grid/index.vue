<template>
    <view :style="warpCss" v-if="diyComponent.isShow">
        <view class="diy-xiaoyuan-card-grid">
            <view
                class="grid-item"
                v-for="item in displayList"
                :key="item.key"
                :class="'item-' + item.key.toLowerCase()"
                @click="onItemClick(item)"
            >
                <view class="item-deco">
                    <u-icon :name="item.icon" size="72" color="rgba(255,255,255,0.55)"></u-icon>
                </view>
                <view class="item-body">
                    <view class="item-top">
                        <text class="item-name">{{ item.name }}</text>
                        <text class="item-sub">{{ item.subtitle }}</text>
                    </view>
                    <view class="item-bottom" :class="{ 'only-action': !item.priceText }">
                        <text class="item-price" v-if="item.priceText">{{ item.priceText }}</text>
                        <text class="item-action">{{ item.actionText }}</text>
                    </view>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { redirect } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'
import { getCardList } from '../../../api/xiaoyuan'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()
const cardMap = ref<Record<string, any>>({})

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index]
    }
    return props.component
})

const warpCss = computed(() => {
    let style = ''
    if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;'
    if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;'
    return style
})

const cardIconMap: Record<string, string> = {
    EXPRESS: 'bag',
    ERRAND: 'car',
    PRINT: 'file-text',
}

const defaultCardList = [
    { cardType: 'EXPRESS', name: '快递卡', subtitle: '代取快递更省心', isShow: true },
    { cardType: 'ERRAND', name: '跑腿卡', subtitle: '校园跑腿一键下单', isShow: true },
    { cardType: 'PRINT', name: '打印卡', subtitle: '代打印省时省力', isShow: true },
]

const displayList = computed(() => {
    const showApply = diyComponent.value.showApply !== false
    const configList = Array.isArray(diyComponent.value.list) && diyComponent.value.list.length
        ? diyComponent.value.list
        : defaultCardList
    const list = configList
        .filter((item: any) => item.isShow !== false)
        .map((item: any) => buildCardItem(item.cardType, item.name, item.subtitle, cardIconMap[item.cardType] || 'bag'))
    if (showApply) {
        list.push({
            key: 'APPLY',
            name: diyComponent.value.applyTitle || '申请接单',
            subtitle: diyComponent.value.applySubtitle || '成为校园跑腿员',
            priceText: '',
            actionText: 'GO',
            icon: 'account-fill',
            url: '/addon/sd_xiaoyuan/pages/runner/apply',
        })
    }
    return list
})

const mockPriceMap: Record<string, number> = {
    EXPRESS: 9.9,
    ERRAND: 19.9,
    PRINT: 5.9,
}

const buildCardItem = (type: string, diyName: string, diySub: string, icon: string) => {
    const card = cardMap.value[type] || {}
    const price = card.price !== undefined ? Number(card.price) : 0
    let priceText = ''
    if (price > 0) {
        priceText = '¥' + price.toFixed(2)
    } else if (diyStore.mode == 'decorate' && mockPriceMap[type]) {
        priceText = '¥' + mockPriceMap[type].toFixed(2)
    }
    return {
        key: type,
        name: diyName || card.name || '',
        subtitle: diySub || card.subtitle || '',
        priceText,
        actionText: '购买',
        icon,
        url: '/addon/sd_xiaoyuan/pages/card/buy?type=' + type,
    }
}

const onItemClick = (item: any) => {
    if (diyStore.mode == 'decorate') return
    if (item.url) redirect({ url: item.url })
}

onMounted(async () => {
    const res: any = await getCardList()
    if (res.code === 1 && Array.isArray(res.data)) {
        const map: Record<string, any> = {}
        res.data.forEach((row: any) => {
            map[row.card_type] = row
        })
        cardMap.value = map
    }
})
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-card-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16rpx;
    padding: 0 10rpx;
}

.grid-item {
    position: relative;
    border-radius: 20rpx;
    padding: 24rpx;
    min-height: 180rpx;
    box-sizing: border-box;
    overflow: hidden;
}

.item-deco {
    position: absolute;
    right: 8rpx;
    top: 50%;
    transform: translateY(-50%);
    z-index: 0;
    pointer-events: none;
}

.item-body {
    position: relative;
    z-index: 1;
    min-height: 132rpx;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.item-express {
    background: linear-gradient(135deg, #e8ffcc, #c0fe95);
}

.item-errand {
    background: linear-gradient(135deg, #fff3e0, #ffd591);
}

.item-print {
    background: linear-gradient(135deg, #f6ffed, #b7eb8f);
}

.item-apply {
    background: linear-gradient(135deg, #f0ffe8, #aaf69b);
}

.item-name {
    display: block;
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    max-width: 72%;
}

.item-sub {
    display: block;
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #666;
    max-width: 72%;
}

.item-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 20rpx;
    padding-right: 0;
}

.item-bottom.only-action {
    justify-content: flex-end;
}

.item-price {
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
    flex-shrink: 0;
}

.item-action {
    margin-left: auto;
    padding: 6rpx 22rpx;
    background: rgba(0, 0, 0, 0.75);
    color: #fff;
    font-size: 22rpx;
    border-radius: 999rpx;
    flex-shrink: 0;
}

.item-bottom:not(.only-action) .item-action {
    margin-left: 12rpx;
}
</style>
