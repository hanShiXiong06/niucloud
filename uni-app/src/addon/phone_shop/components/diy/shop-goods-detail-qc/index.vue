<template>
    <view :style="warpCss" class="overflow-hidden">
        <view class="qc-card card-template" :class="'qc-layout-' + layoutStyle" v-if="hasQc">
            <!-- 头部:盾形徽标 + 标题/副标题 + 检测通过徽标 -->
            <view class="qc-head" :style="headBgStyle">
                <view class="qc-head-left">
                    <view class="qc-shield" :style="{ background: themeColor }">
                        <text class="nc-iconfont nc-icon-wanchengV6xx text-[34rpx] text-[#fff]"></text>
                    </view>
                    <view class="qc-head-text">
                        <text class="qc-title" :style="{ color: titleColor }">{{ title }}</text>
                        <text class="qc-subtitle" v-if="subTitle">{{ subTitle }}</text>
                    </view>
                </view>
                <view v-if="showBadge" class="qc-badge" :style="badgeStyle">
                    <text class="nc-iconfont nc-icon-wanchengV6xx text-[22rpx] mr-[6rpx]"></text>
                    <text>已检测 {{ items.length }} 项</text>
                </view>
            </view>

            <!-- 摘要条(突出项) -->
            <view v-if="showSummaryBar && summaryFields.length" class="qc-summary">
                <view v-for="(s, i) in summaryFields" :key="'s' + i" class="qc-chip" :style="chipStyle(s.severity)">
                    <text class="qc-chip-name">{{ s.field_name }}</text>
                    <text class="qc-chip-val" :style="{ color: sevColor(s.severity) }">{{ s.label }}</text>
                </view>
            </view>

            <!-- 需关注(异常/一般)置顶 -->
            <view v-if="flagged.length" class="qc-flagged">
                <view class="qc-flagged-title">
                    <text class="nc-iconfont nc-icon-jingementmm text-[26rpx] mr-[8rpx]"></text>
                    <text>需关注（{{ flagged.length }}）</text>
                </view>
                <view v-for="(it, i) in flagged" :key="'f' + i" class="qc-flagged-row">
                    <text class="qc-tag" :style="tagStyle(it.severity)">{{ it.severity === 'abnormal' ? '异常' : '一般' }}</text>
                    <text class="qc-flagged-text">{{ labelOf(it) }}</text>
                </view>
            </view>
            <!-- 全部正常 -->
            <view v-else-if="items.length" class="qc-allok" :style="{ color: themeColor, background: themeColor + '12' }">
                <text class="nc-iconfont nc-icon-wanchengV6xx text-[26rpx] mr-[8rpx]"></text>
                <text>整机各项检测正常,成色真实</text>
            </view>

            <!-- 完整报告(折叠) -->
            <view v-if="items.length" class="qc-all">
                <view v-if="qcOpen" class="qc-all-list">
                    <view v-for="(it, i) in displayItems" :key="'a' + i" class="qc-row">
                        <text class="qc-dot" :style="dotStyle(it.severity)"></text>
                        <text class="qc-row-name">{{ it.field_name }}</text>
                        <text class="qc-row-val" :style="it.severity !== 'normal' ? { color: sevColor(it.severity) } : {}">{{ valOf(it) }}</text>
                    </view>
                </view>
                <view class="qc-toggle" :style="{ color: themeColor }" @click="qcOpen = !qcOpen">
                    <text class="text-[24rpx] mr-[8rpx]">{{ qcOpen ? '收起完整报告' : ('查看完整质检报告 · 共 ' + items.length + ' 项') }}</text>
                    <text class="nc-iconfont !text-[22rpx]" :class="{ 'nc-icon-xiaV6xx': !qcOpen, 'nc-icon-shangV6xx-1': qcOpen }"></text>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { img } from '@/utils/common';
import useDiyStore from '@/app/stores/diy';
import useGoodsDetailStore from '@/addon/phone_shop/stores/goodsDetail'

const props = defineProps(['component', 'index', 'value']);
const diyStore = useDiyStore();
const emits = defineEmits(['update:componentIsShow']);

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        // 装修预览:占位数据,让商家看到大致样子
        const obj = {
            goods: {
                qc_report: JSON.stringify({
                    summary_fields: [
                        { field_name: '存储容量', label: '256G', severity: 'normal' },
                        { field_name: '机身颜色', label: '银色', severity: 'normal' },
                        { field_name: '健康度', label: '100%', severity: 'normal' }
                    ],
                    result_items: [
                        { field_name: '屏幕显示', value: '显示完美', severity: 'normal' },
                        { field_name: '后摄维修情况', value: '后摄像头无维修和缺失', severity: 'abnormal' },
                        { field_name: '电池维修情况', value: '电池无维修', severity: 'normal' }
                    ],
                    abnormal_items: [
                        { field_name: '后摄维修情况', value: '后摄像头无维修和缺失', severity: 'abnormal' }
                    ],
                    severity_summary: { abnormal: 1, general: 0, normal: 2 }
                })
            }
        }
        return Object.assign({}, obj, diyStore.value[props.index]);
    } else {
        return Object.assign({}, props.component, useGoodsDetailStore().goodsDetail);
    }
})

// 解析 qc_report(后端存的是 JSON 字符串)
const qc = computed(() => {
    const g: any = diyComponent.value.goods || {};
    let raw = g.qc_report;
    if (!raw) return {};
    if (typeof raw === 'string') { try { return JSON.parse(raw) } catch (e) { return {} } }
    return raw || {};
})
const items = computed(() => Array.isArray(qc.value.result_items) ? qc.value.result_items : []);
const summaryFields = computed(() => Array.isArray(qc.value.summary_fields) ? qc.value.summary_fields : []);
const flagged = computed(() => {
    if (Array.isArray(qc.value.abnormal_items) && qc.value.abnormal_items.length) return qc.value.abnormal_items;
    return items.value.filter((r: any) => r.severity === 'abnormal' || r.severity === 'general');
});
const hasQc = computed(() => items.value.length > 0 || summaryFields.value.length > 0);
const qcOpen = ref(diyComponent.value.defaultExpand === 'all');

// 可配置项(带默认值)
const themeColor = computed(() => diyComponent.value.themeColor || '#1A6DFF');
const titleColor = computed(() => diyComponent.value.titleColor || '#1D2129');
const title = computed(() => diyComponent.value.title || '官方质检报告');
const subTitle = computed(() => diyComponent.value.subTitle || '');
const showBadge = computed(() => diyComponent.value.showBadge !== false);
const showSummaryBar = computed(() => diyComponent.value.showSummaryBar !== false);
const showNormalItems = computed(() => diyComponent.value.showNormalItems !== false);
const layoutStyle = computed(() => diyComponent.value.layoutStyle || 'professional');
const displayItems = computed(() => showNormalItems.value ? items.value : flagged.value);
const headBgStyle = computed(() => `background:linear-gradient(135deg, ${ themeColor.value }14, ${ themeColor.value }02);`);
const badgeStyle = computed(() => `color:${ themeColor.value };background:${ themeColor.value }14;`);

const valOf = (it: any) => (Array.isArray(it.labels) && it.labels.length) ? it.labels.join('、') : (it.value || '');
const labelOf = (it: any) => {
    const v = valOf(it);
    return (it.field_name || '') + (v ? (': ' + v) : '');
}
const sevColor = (s: string) => s === 'abnormal' ? '#f56c6c' : (s === 'general' ? '#e6a23c' : '#67c23a');
const chipStyle = (s: string) => `border:1rpx solid ${ sevColor(s) }33;background:${ sevColor(s) }14;`;
const tagStyle = (s: string) => `background:${ sevColor(s) };`;
const dotStyle = (s: string) => `background:${ sevColor(s) };`;

const warpCss = computed(() => {
    let style = '';
    style += 'position:relative;';
    if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${ diyComponent.value.componentGradientAngle },${ diyComponent.value.componentStartBgColor },${ diyComponent.value.componentEndBgColor });`;
    else style += 'background-color:' + (diyComponent.value.componentStartBgColor || diyComponent.value.componentEndBgColor) + ';';
    if (diyComponent.value.componentBgUrl) {
        style += `background-image:url('${ img(diyComponent.value.componentBgUrl) }');`;
        style += 'background-size: cover;background-repeat: no-repeat;';
    }
    if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
    if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
    if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
    if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
    return style;
})

watch(() => diyComponent.value.defaultExpand, (value) => {
    qcOpen.value = value === 'all';
}, { immediate: true });

onMounted(() => {
    if (diyStore.mode != 'decorate') {
        watch(
            () => diyComponent.value,
            (newValue: any) => {
                if (newValue && newValue.componentName == 'ShopGoodsDetailQc') {
                    nextTick(() => {
                        emits('update:componentIsShow', !!(hasQc.value && diyComponent.value.isShow))
                    })
                }
            },
            { immediate: true }
        )
    }
});
</script>

<style lang="scss" scoped>
.card-template {
    background-color: transparent !important;
    border-radius: 0 !important;
}
/* 头部 */
.qc-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20rpx 24rpx;
    border-radius: 16rpx;
    margin-bottom: 22rpx;
}
.qc-head-left {
    display: flex;
    align-items: center;
}
.qc-shield {
    width: 64rpx;
    height: 64rpx;
    border-radius: 16rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 18rpx;
    box-shadow: 0 6rpx 16rpx rgba(0, 0, 0, 0.12);
}
.qc-head-text {
    display: flex;
    flex-direction: column;
}
.qc-title {
    font-size: 30rpx;
    font-weight: 700;
    line-height: 40rpx;
}
.qc-subtitle {
    font-size: 22rpx;
    color: var(--text-color-light9);
    margin-top: 4rpx;
}
.qc-badge {
    display: flex;
    align-items: center;
    font-size: 22rpx;
    font-weight: 500;
    padding: 8rpx 18rpx;
    border-radius: 100rpx;
}
/* 摘要条 */
.qc-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 14rpx;
    margin-bottom: 22rpx;
}
.qc-chip {
    display: flex;
    align-items: center;
    padding: 10rpx 20rpx;
    border-radius: 12rpx;
    font-size: 24rpx;
}
.qc-chip-name {
    color: var(--text-color-light9);
    margin-right: 10rpx;
}
.qc-chip-val {
    font-weight: 600;
}
/* 需关注 */
.qc-flagged {
    background: #fff5f5;
    border: 1rpx solid #ffe0e0;
    border-radius: 16rpx;
    padding: 20rpx 24rpx;
    margin-bottom: 18rpx;
}
.qc-flagged-title {
    display: flex;
    align-items: center;
    color: #f53f3f;
    font-weight: 600;
    font-size: 26rpx;
    margin-bottom: 14rpx;
}
.qc-flagged-row {
    display: flex;
    align-items: center;
    margin-top: 10rpx;
}
.qc-tag {
    flex-shrink: 0;
    color: #fff;
    font-size: 20rpx;
    border-radius: 6rpx;
    padding: 3rpx 12rpx;
    margin-right: 14rpx;
}
.qc-flagged-text {
    font-size: 26rpx;
    color: #333;
}
/* 全部正常 */
.qc-allok {
    display: flex;
    align-items: center;
    font-size: 26rpx;
    font-weight: 500;
    padding: 18rpx 22rpx;
    border-radius: 16rpx;
    margin-bottom: 18rpx;
}
/* 完整报告 */
.qc-all-list {
    border-top: 1rpx solid var(--border-color, #f0f0f0);
    padding-top: 12rpx;
    margin-top: 4rpx;
}
.qc-row {
    display: flex;
    align-items: center;
    padding: 12rpx 0;
    border-bottom: 1rpx solid rgba(0, 0, 0, 0.04);
}
.qc-dot {
    width: 12rpx;
    height: 12rpx;
    border-radius: 50%;
    margin-right: 16rpx;
    flex-shrink: 0;
}
.qc-row-name {
    font-size: 26rpx;
    color: var(--text-color-light9);
    width: 200rpx;
    flex-shrink: 0;
}
.qc-row-val {
    font-size: 26rpx;
    color: #333;
    flex: 1;
}
.qc-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 18rpx;
    font-weight: 500;
}
.qc-layout-simple {
    .qc-head { padding: 8rpx 0 18rpx; margin-bottom: 12rpx; background: transparent !important; border-radius: 0; }
    .qc-shield { width: 52rpx; height: 52rpx; border-radius: 50%; box-shadow: none; }
    .qc-summary { gap: 10rpx; margin-bottom: 14rpx; }
    .qc-chip { padding: 7rpx 14rpx; font-size: 22rpx; }
}
.qc-layout-card {
    padding: 22rpx;
    border: 1rpx solid rgba(26, 109, 255, .12);
    border-radius: 22rpx !important;
    background: linear-gradient(145deg, #fff, #f7faff) !important;
    box-shadow: 0 10rpx 30rpx rgba(35, 65, 110, .07);
    .qc-head { margin-bottom: 16rpx; }
    .qc-summary { margin-bottom: 16rpx; }
}
</style>
