<template>
    <view class="waterfall-card" @click="emit('click')">
        <PhoneGoodsCover :src="item.goods_cover_thumb_mid" :grade="item.condition_grade" variant="grid" />
        <view class="waterfall-card__content">
            <view class="waterfall-card__title multi-hidden">
                <view v-if="item.goods_brand" class="brand-tag" :style="diyGoods.baseTagStyle(item.goods_brand)">
                    {{ item.goods_brand.brand_name }}
                </view>
                {{ item.goods_name }}
            </view>
            <PhoneGoodsMeta :subtitle="item.sub_title" :imei="item.goodsSku?.sku_no" compact />
            <PhoneGoodsSaleState :state="item.sale_state" />
            <view class="waterfall-card__price">
                <view class="waterfall-card__amount">
                    <text class="waterfall-card__currency">￥</text>
                    <text class="waterfall-card__integer">{{ priceParts[0] }}</text>
                    <text class="waterfall-card__decimal">.{{ priceParts[1] }}</text>
                </view>
                <image v-if="priceBadge" class="waterfall-card__price-badge" :src="priceBadge" mode="heightFix" />
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { img } from '@/utils/common'
import { useGoods } from '@/addon/phone_shop/hooks/useGoods'
import PhoneGoodsCover from '@/addon/phone_shop/components/PhoneGoodsCover.vue'
import PhoneGoodsMeta from '@/addon/phone_shop/components/PhoneGoodsMeta.vue'
import PhoneGoodsSaleState from '@/addon/phone_shop/components/PhoneGoodsSaleState.vue'

const props = withDefaults(defineProps<{
    item?: Record<string, any>
}>(), {
    item: () => ({})
})

const emit = defineEmits<{
    (event: 'click'): void
}>()

const diyGoods = useGoods()
const priceParts = computed(() => Number(diyGoods.goodsPrice(props.item) || 0).toFixed(2).split('.'))
const priceBadge = computed(() => {
    const type = diyGoods.priceType(props.item)
    if (type === 'member_price') return img('addon/phone_shop/VIP.png')
    if (type === 'newcomer_price') return img('addon/phone_shop/newcomer.png')
    if (type === 'discount_price') return img('addon/phone_shop/discount.png')
    return ''
})
</script>

<style scoped lang="scss">
@import '@/addon/phone_shop/styles/common.scss';

.waterfall-card {
    width: 100%;
    min-width: 0;
    overflow: hidden;
    box-sizing: border-box;
    border-radius: var(--rounded-mid);
    background: #fff;
}

.waterfall-card__content {
    min-width: 0;
    padding: 16rpx 20rpx 22rpx;
    box-sizing: border-box;
}

.waterfall-card__title {
    max-height: 80rpx;
    overflow: hidden;
    color: #303133;
    font-size: 28rpx;
    line-height: 40rpx;
}

.waterfall-card__price {
    min-width: 0;
    margin-top: 18rpx;
    display: flex;
    align-items: baseline;
    overflow: hidden;
}

.waterfall-card__amount {
    min-width: 0;
    display: flex;
    align-items: baseline;
    color: var(--price-text-color);
    white-space: nowrap;
    flex-shrink: 1;
}

.waterfall-card__currency,
.waterfall-card__decimal {
    font-size: 23rpx;
    font-weight: 500;
}

.waterfall-card__integer {
    font-size: 40rpx;
    font-weight: 600;
    line-height: 48rpx;
}

.waterfall-card__price-badge {
    max-width: 72rpx;
    height: 28rpx;
    margin-left: 6rpx;
    flex-shrink: 0;
}
</style>
