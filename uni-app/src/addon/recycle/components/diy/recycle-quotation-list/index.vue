<template>
    <view class="quotation-wrap" :style="wrapStyle">
        <view class="quotation-card" :style="cardStyle">
            <view v-if="showMaskLayer" class="quotation-mask" :style="maskLayerStyle"></view>
            <view class="quotation-content">
                <view class="quotation-header">
                    <view class="title-block">
                        <text class="title" :style="{ color: titleColor }">{{ title }}</text>
                        <text class="subtitle" :style="{ color: subtitleColor }">{{ subtitle }}</text>
                    </view>
                    <view class="refresh-btn" v-if="showRefresh" @click.stop="loadDatasets">
                        <text class="iconfont iconrefresh"></text>
                    </view>
                </view>

                <view v-if="loading" class="state-box">
                    <text class="state-text">报价单加载中...</text>
                </view>

                <view v-else-if="displayList.length === 0" class="state-box empty">
                    <text class="state-title">暂无报价单</text>
                    <text class="state-text">请先在后台同步并启用报价单</text>
                </view>

                <view v-else-if="displayStyle === 'graphic'" class="quotation-nav">
                    <view
                        v-for="item in displayList"
                        :key="item.dataset_id || item.id"
                        class="quotation-nav-item"
                        :style="navItemStyle"
                        @click="openQuotation(item)"
                    >
                        <view
                            class="quotation-nav-img"
                            :style="navImageStyle"
                        >
                            <image :src="img(navImageUrl || 'static/resource/images/diy/figure.png')" mode="aspectFill"></image>
                        </view>
                        <text class="quotation-nav-title" :style="{ color: titleColor }">{{ item.title || item.dataset_name || item.price_name }}</text>
                    </view>
                </view>

                <view v-else class="dataset-list">
                    <view
                        v-for="item in displayList"
                        :key="item.dataset_id || item.id"
                        class="dataset-item"
                        @click="openQuotation(item)"
                    >
                        <view class="dataset-main">
                            <text class="dataset-title">{{ item.title || item.dataset_name || item.price_name }}</text>
                            <view class="dataset-meta">
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
</template>

<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue';
import useDiyStore from '@/app/stores/diy';
import { img, redirect } from '@/utils/common';
import { getQuotationV2Types, type QuotationV2Type } from '@/addon/recycle/api/quotation';

const props = defineProps({
    component: {
        type: Object,
        default: () => ({})
    },
    index: {
        type: Number,
        default: 0
    }
});

const diyStore = useDiyStore();
const loading = ref(false);
const datasets = ref<QuotationV2Type[]>([]);

const diyComponent = computed(() => {
    if (diyStore.mode === 'decorate') {
        return diyStore.value[props.index] || props.component;
    }
    return props.component;
});

const title = computed(() => diyComponent.value.title || '今日报价');
const subtitle = computed(() => diyComponent.value.subtitle || '实时同步回收报价单');
const actionText = computed(() => diyComponent.value.actionText || '查看');
const limit = computed(() => {
    const value = Number(diyComponent.value.limit || 5);
    return Number.isFinite(value) && value > 0 ? Math.min(value, 20) : 5;
});
const showRefresh = computed(() => diyComponent.value.showRefresh !== false);
const displayStyle = computed(() => diyComponent.value.displayStyle || 'list');
const titleColor = computed(() => diyComponent.value.titleColor || '#111827');
const subtitleColor = computed(() => diyComponent.value.subtitleColor || '#6B7280');
const buttonColor = computed(() => diyComponent.value.buttonColor || '#2563EB');
const navImageUrl = computed(() => diyComponent.value.navImageUrl || '');
const navRowCount = computed(() => {
    const value = Number(diyComponent.value.navRowCount || 4);
    return [3, 4, 5].includes(value) ? value : 4;
});
const navImageSize = computed(() => {
    const value = Number(diyComponent.value.navImageSize || 40);
    return Number.isFinite(value) && value > 0 ? value : 40;
});
const navAroundRadius = computed(() => {
    const value = Number(diyComponent.value.navAroundRadius ?? 20);
    return Number.isFinite(value) && value >= 0 ? value : 20;
});
const navItemStyle = computed(() => {
    return `width:${100 / navRowCount.value}%;`;
});
const navImageStyle = computed(() => {
    return `width:${navImageSize.value * 2}rpx;height:${navImageSize.value * 2}rpx;border-radius:${navAroundRadius.value * 2}rpx;`;
});

const mockList = computed<QuotationV2Type[]>(() => [
    {
        id: 1,
        dataset_id: 1,
        quotation_id: 114,
        price_name: '靓机/小花',
        dataset_name: '靓机/小花',
        title: '靓机/小花',
        last_sync_at: 0,
        last_sync_at_text: '今日 10:00',
        last_sync_status_name: '已同步',
        price_count: 128,
        model_count: 32
    },
    {
        id: 2,
        dataset_id: 2,
        quotation_id: 115,
        price_name: '花机/内爆',
        dataset_name: '花机/内爆',
        title: '花机/内爆',
        last_sync_at: 0,
        last_sync_at_text: '今日 10:00',
        last_sync_status_name: '已同步',
        price_count: 96,
        model_count: 24
    }
]);

const displayList = computed(() => {
    const list = diyStore.mode === 'decorate' && datasets.value.length === 0 ? mockList.value : datasets.value;
    return list.slice(0, limit.value);
});

const wrapStyle = computed(() => {
    const margin = diyComponent.value.margin || { top: 10, bottom: 10, both: 12 };
    let style = 'position:relative;';
    style += `margin:${Number(margin.top || 0) * 2}rpx ${Number(margin.both || 0) * 2}rpx ${Number(margin.bottom || 0) * 2}rpx;`;
    return style;
});

const cardStyle = computed(() => {
    const startColor = diyComponent.value.componentStartBgColor || '';
    const endColor = diyComponent.value.componentEndBgColor || '';
    const angle = diyComponent.value.componentGradientAngle || 'to bottom';
    const bgUrl = diyComponent.value.componentBgUrl || '';
    const topRounded = Number(diyComponent.value.topRounded || 0) * 2;
    const bottomRounded = Number(diyComponent.value.bottomRounded || 0) * 2;
    let style = '';

    if (startColor && endColor) {
        style += `background:linear-gradient(${angle},${startColor},${endColor});`;
    } else if (startColor) {
        style += `background-color:${startColor};`;
    } else {
        style += 'background:transparent;';
    }
    if (bgUrl) {
        style += `background-image:url('${img(bgUrl)}');background-size:cover;background-repeat:no-repeat;background-position:center;`;
    }
    style += `border-top-left-radius:${topRounded}rpx;border-top-right-radius:${topRounded}rpx;`;
    style += `border-bottom-left-radius:${bottomRounded}rpx;border-bottom-right-radius:${bottomRounded}rpx;`;
    return style;
});

const showMaskLayer = computed(() => Boolean(diyComponent.value.componentBgUrl));

const maskLayerStyle = computed(() => {
    const alpha = Number(diyComponent.value.componentBgAlpha || 0) / 10;
    return `background:rgba(0,0,0,${alpha});`;
});

async function loadDatasets() {
    if (diyStore.mode === 'decorate') {
        datasets.value = [];
        return;
    }

    loading.value = true;
    try {
        const res = await getQuotationV2Types({ limit: limit.value }) as any;
        datasets.value = res.code === 1 && Array.isArray(res.data) ? res.data : [];
    } catch (error) {
        datasets.value = [];
    } finally {
        loading.value = false;
    }
}

function openQuotation(item: QuotationV2Type) {
    if (diyStore.mode === 'decorate') return;
    const titleText = encodeURIComponent(item.title || item.dataset_name || item.price_name || '报价查询');
    redirect({
        url: `/addon/recycle/pages/price/show_price?source=v2&dataset_id=${item.dataset_id}&quotation_id=${item.quotation_id}&title=${titleText}`
    });
}

onMounted(() => {
    loadDatasets();
});
</script>

<style lang="scss" scoped>
.quotation-wrap {
    box-sizing: border-box;
}

.quotation-card {
    position: relative;
    overflow: hidden;
}

.quotation-mask {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
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

.dataset-list {
    padding: 0 18rpx 18rpx;
}

.quotation-nav {
    display: flex;
    flex-wrap: wrap;
    padding: 10rpx 12rpx 20rpx;
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

.dataset-title {
    display: block;
    font-size: 29rpx;
    line-height: 40rpx;
    font-weight: 600;
    color: #111827;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
