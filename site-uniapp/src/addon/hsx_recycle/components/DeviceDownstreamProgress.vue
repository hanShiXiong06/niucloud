<template>
    <view class="ddp">
        <view class="ddp__header">
            <text class="ddp__title">流转进度</text>
            <text class="ddp__sub">回收完成后，设备在 ERP / 数据中台的去向</text>
        </view>

        <view class="ddp__track">
            <view
                v-for="(n, i) in nodes"
                :key="n.key"
                class="ddp__node"
                :class="{ 'is-done': i <= activeIndex, 'is-current': i === currentIndex }"
            >
                <view v-if="i < nodes.length - 1" class="ddp__line" :class="{ 'is-done': i < activeIndex }"></view>
                <view class="ddp__dot">
                    <text v-if="i < activeIndex" class="nc-iconfont nc-icon-duiV6xx ddp__dot-check"></text>
                    <text v-else class="ddp__dot-idx">{{ i + 1 }}</text>
                </view>
                <text class="ddp__label">{{ n.label }}</text>
                <text v-if="n.hint" class="ddp__hint">{{ n.hint }}</text>
            </view>
        </view>

        <view v-if="isReturned" class="ddp__foot">设备已退回，不进入仓储流转。</view>
        <view v-else-if="!recycleDone" class="ddp__foot">尚未完成回收，完成打款后进入仓储流转。</view>
        <view v-else-if="stage === 0" class="ddp__foot">已回收，等待 ERP 入库后开始流转。</view>
        <view v-else-if="stagedAt" class="ddp__foot">最近更新：{{ formatTime(stagedAt) }}</view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * 设备下游流转进度——忠实对齐 admin DownstreamProgress.vue。
 *
 * 关键：进度绑定到「具体那条设备记录」的 downstream_* 镜像字段（后端以 source_device_id 为锚回流），
 * 因此同一串号的多次交易互不串台。本组件只读传入的设备字段，绝不按 IMEI 聚合。
 *
 * 阶段值(downstream_stage)：0未流转 / 10已入库 / 20转中台 / 30已定价 / 40已售下架
 */
const props = defineProps<{
    stage?: number | string
    salePrice?: number | string
    stagedAt?: number | string
    erpAssetId?: number | string
    payStatus?: number | string
    disposeStatus?: number | string
}>()

const stage = computed(() => Number(props.stage || 0))
const stagedAt = computed(() => Number(props.stagedAt || 0))

// 处置状态：1已回收 / 2已退回 / 3已转代卖
const isReturned = computed(() => Number(props.disposeStatus || 0) === 2)
// 回收完成（进入仓储流转的前提）：已打款 / 已回收处置 / 已有下游阶段；退回的不算
const recycleDone = computed(() =>
    !isReturned.value &&
    (Number(props.payStatus || 0) === 1 || Number(props.disposeStatus || 0) === 1 || stage.value > 0)
)

const salePriceText = computed(() => {
    const v = Number(props.salePrice || 0)
    return v > 0 ? `定价 ¥${ v }` : ''
})

const nodes = computed(() => [
    { key: 'recycle', label: '回收完成', hint: '' },
    { key: 'stocked', label: '已入库', hint: '' },
    { key: 'photo', label: '转中台', hint: '' },
    { key: 'priced', label: '已定价', hint: salePriceText.value },
    { key: 'sold', label: '已售/下架', hint: '待商城接入' }
])

// stage: 0/10/20/30/40 → 已达节点下标；未完成回收时无任何节点点亮(-1)
const activeIndex = computed(() => {
    if (!recycleDone.value) return -1
    const s = stage.value
    if (s >= 40) return 4
    if (s >= 30) return 3
    if (s >= 20) return 2
    if (s >= 10) return 1
    return 0
})

// 当前(高亮待办)节点：已回收按已达阶段；未回收时指向首个节点「回收完成」
const currentIndex = computed(() => (recycleDone.value ? activeIndex.value : 0))

const formatTime = (ts: number) => {
    if (!ts) return ''
    const d = new Date(ts * 1000)
    const p = (n: number) => (n < 10 ? '0' + n : '' + n)
    return `${ d.getFullYear() }-${ p(d.getMonth() + 1) }-${ p(d.getDate()) } ${ p(d.getHours()) }:${ p(d.getMinutes()) }`
}
</script>

<style scoped lang="scss">
.ddp {
    background: #fff;
    border: 1rpx solid var(--hsx-border);
    border-radius: var(--hsx-radius);
    padding: 28rpx 24rpx;
}

.ddp__header {
    display: flex;
    flex-direction: column;
    margin-bottom: 28rpx;
}

.ddp__title {
    font-size: 28rpx;
    font-weight: 600;
    color: var(--hsx-text-strong);
}

.ddp__sub {
    margin-top: 4rpx;
    font-size: 20rpx;
    color: var(--hsx-text-placeholder);
}

.ddp__track {
    display: flex;
    align-items: flex-start;
}

.ddp__node {
    position: relative;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

/* 连接线：位于圆点垂直中心，从本节点中点延伸到下一节点中点 */
.ddp__line {
    position: absolute;
    top: 24rpx;
    left: 50%;
    width: 100%;
    height: 3rpx;
    background: #e8eaf0;
    z-index: 0;
}

.ddp__line.is-done {
    background: var(--hsx-primary);
}

.ddp__dot {
    width: 48rpx;
    height: 48rpx;
    border-radius: 50%;
    background: #f1f3f7;
    color: var(--hsx-text-placeholder);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22rpx;
    font-weight: 600;
    z-index: 1;
}

.ddp__node.is-done .ddp__dot {
    background: var(--hsx-primary);
    color: #fff;
}

.ddp__node.is-current .ddp__dot {
    box-shadow: 0 0 0 8rpx rgba(79, 70, 229, 0.14);
}

.ddp__dot-check {
    font-size: 24rpx;
    color: #fff;
}

.ddp__label {
    margin-top: 12rpx;
    font-size: 20rpx;
    line-height: 28rpx;
    color: var(--hsx-text-secondary);
}

.ddp__node.is-done .ddp__label {
    color: var(--hsx-text-regular);
    font-weight: 500;
}

.ddp__hint {
    margin-top: 4rpx;
    font-size: 18rpx;
    line-height: 24rpx;
    color: var(--hsx-text-placeholder);
}

.ddp__foot {
    margin-top: 24rpx;
    font-size: 20rpx;
    color: var(--hsx-text-placeholder);
}
</style>
