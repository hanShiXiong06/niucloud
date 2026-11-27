<template>
    <view class="message-shell" :class="message.role">
        <view class="message-bubble">
            <view v-if="message.loading" class="thinking-state">
                <view class="thinking-symbol"><view v-for="dot in 3" :key="dot" class="thinking-dot" :style="{ animationDelay: `${(dot - 1) * 140}ms` }" /></view>
                <view class="thinking-copy"><text class="thinking-title">{{ message.loadingPhase || '正在理解你的需求' }}</text><text class="thinking-hint">价格、库存与质检信息以本站实时数据为准</text></view>
            </view>
            <view v-else-if="message.role === 'assistant'" class="assistant-answer">
                <AiMarkdownContent :content="message.content" />
                <view v-if="message.streaming" class="streaming-state"><view class="streaming-bars"><view v-for="bar in 3" :key="bar" class="streaming-bar" :style="{ animationDelay: `${(bar - 1) * 110}ms` }" /></view><text>正在回答</text></view>
            </view>
            <text v-else selectable class="user-content">{{ message.content }}</text>
            <view v-if="message.role === 'assistant' && message.content && !message.streaming" class="message-tools">
                <view class="message-tool" @click="copy"><u-icon name="file-text" size="15" color="#667085" /><text>复制</text></view>
                <view v-if="voiceEnabled" class="message-tool" @click="$emit('speak', message)"><u-icon name="volume" size="15" color="#667085" /><text>{{ speaking ? '停止' : '朗读' }}</text></view>
            </view>
        </view>

        <AiBlockRenderer v-if="message.role === 'assistant' && message.blocks?.length" :blocks="message.blocks" />

        <view v-if="message.role === 'assistant' && message.resources?.length" class="resource-section">
            <view class="resource-head"><text>匹配到的在售设备</text><text>{{ message.resources.length }} 台</text></view>
            <scroll-view scroll-x :show-scrollbar="false" class="product-strip">
                <view class="product-strip-inner">
                    <view v-for="item in message.resources" :key="`${item.goods_id}_${item.sku_id || ''}`" class="product-card" @click="$emit('open-product', item)">
                        <image v-if="item.image" class="product-image" :src="img(item.image)" mode="aspectFill" />
                        <view class="product-name">{{ item.name }}</view>
                        <view class="product-meta">{{ [item.memory, item.condition].filter(Boolean).join(' · ') || item.sku_name }}</view>
                        <view v-if="item.facts_label" class="product-facts">{{ item.facts_label }}</view>
                        <view class="product-price-line"><text class="product-price-label">{{ item.current_price_label || '当前价' }}</text><text class="product-price">¥{{ formatPrice(item.current_price ?? item.price) }}</text></view>
                        <view class="product-bottom"><text v-if="showRegularPrice(item)" class="product-regular">普通价 ¥{{ formatPrice(item.regular_price) }}</text><text class="product-stock">库存 {{ item.stock }}</text></view>
                    </view>
                </view>
            </scroll-view>
        </view>

        <view v-if="message.role === 'assistant' && message.actions?.length" class="message-actions">
            <view v-for="action in message.actions" :key="action.id" class="message-action" @click="$emit('action', action)"><text>{{ action.label }}</text><u-icon name="arrow-right" size="14" color="#2563eb" /></view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { img } from '@/utils/common'
import AiMarkdownContent from './AiMarkdownContent.vue'
import AiBlockRenderer from './AiBlockRenderer.vue'

const props = defineProps<{ message: any; voiceEnabled?: boolean; speaking?: boolean }>()
defineEmits(['speak', 'open-product', 'action'])
const formatPrice = (value: any) => Number(value || 0).toFixed(0)
const showRegularPrice = (item: any) => Number(item?.regular_price || 0) > 0 && Math.abs(Number(item.regular_price) - Number(item.current_price ?? item.price ?? 0)) > 0.001
const copy = () => uni.setClipboardData({ data: String(props.message?.content || ''), success: () => uni.showToast({ title: '已复制', icon: 'none' }) })
</script>

<style lang="scss" scoped>
.message-shell { display: flex; flex-direction: column; align-items: flex-start; margin: 20rpx 0; }.message-shell.user { align-items: flex-end; }
.message-bubble { box-sizing: border-box; max-width: 88%; padding: 20rpx 24rpx; border: 1rpx solid #e4e7ec; border-radius: 8rpx; background: #fff; color: #172033; }.user .message-bubble { border-color: var(--primary-color); background: var(--primary-color); color: #fff; }
.user-content { font-size: 27rpx; line-height: 1.65; white-space: pre-wrap; word-break: break-word; }
.thinking-state { display: flex; min-width: 390rpx; min-height: 72rpx; align-items: center; gap: 18rpx; animation: answer-enter .2s ease-out; }.thinking-symbol { display: flex; flex: 0 0 54rpx; height: 54rpx; align-items: center; justify-content: center; gap: 5rpx; border-radius: 8rpx; background: #eef4ff; }.thinking-dot { width: 7rpx; height: 7rpx; border-radius: 50%; background: #2563eb; animation: thinking-dot 1s ease-in-out infinite; }.thinking-copy { display: flex; min-width: 0; flex-direction: column; }.thinking-title { color: #344054; font-size: 24rpx; font-weight: 600; }.thinking-hint { margin-top: 6rpx; color: #8a94a6; font-size: 19rpx; line-height: 1.4; }
.assistant-answer { animation: answer-enter .2s ease-out; }.streaming-state { display: flex; height: 34rpx; align-items: center; gap: 9rpx; margin-top: 12rpx; color: #7b8698; font-size: 19rpx; }.streaming-bars { display: flex; height: 22rpx; align-items: center; gap: 4rpx; }.streaming-bar { width: 4rpx; height: 8rpx; border-radius: 2rpx; background: #2563eb; animation: streaming-bar .72s ease-in-out infinite alternate; }
.message-tools { display: flex; align-items: center; gap: 28rpx; margin-top: 16rpx; padding-top: 14rpx; border-top: 1rpx solid #edf0f3; }.message-tool { display: flex; align-items: center; gap: 7rpx; color: #667085; font-size: 21rpx; }
.resource-section { width: 100%; margin-top: 14rpx; }.resource-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12rpx; color: #475467; font-size: 22rpx; }.resource-head text:last-child { color: #98a2b3; }
.product-strip { width: 100%; }.product-strip-inner { display: flex; gap: 14rpx; }.product-card { box-sizing: border-box; flex: 0 0 286rpx; padding: 14rpx; border: 1rpx solid #e3e7ec; border-radius: 8rpx; background: #fff; animation: resource-enter .24s ease-out; }.product-image { width: 100%; height: 176rpx; margin-bottom: 12rpx; border-radius: 6rpx; background: #f3f5f7; }.product-name { height: 70rpx; overflow: hidden; font-size: 25rpx; font-weight: 600; line-height: 35rpx; }.product-meta, .product-facts { margin-top: 7rpx; overflow: hidden; color: #778195; font-size: 21rpx; text-overflow: ellipsis; white-space: nowrap; }.product-facts { color: #2563eb; }.product-price-line { display: flex; align-items: baseline; gap: 8rpx; margin-top: 13rpx; }.product-price-label { color: #b42318; font-size: 19rpx; }.product-price { color: #d92d20; font-size: 28rpx; font-weight: 700; }.product-bottom { display: flex; min-height: 28rpx; align-items: flex-end; justify-content: space-between; margin-top: 5rpx; }.product-regular, .product-stock { color: #98a2b3; font-size: 19rpx; }.product-regular { text-decoration: line-through; }
.message-actions { display: flex; flex-wrap: wrap; gap: 12rpx; width: 100%; margin-top: 14rpx; }.message-action { display: flex; align-items: center; gap: 6rpx; padding: 13rpx 17rpx; border: 1rpx solid #cfdaf4; border-radius: 8rpx; background: #fff; color: #2563eb; font-size: 22rpx; }
@keyframes thinking-dot { 0%, 70%, 100% { opacity: .35; transform: translateY(0); } 35% { opacity: 1; transform: translateY(-6rpx); } }
@keyframes streaming-bar { from { height: 7rpx; opacity: .45; } to { height: 20rpx; opacity: 1; } }
@keyframes answer-enter { from { opacity: .35; transform: translateY(5rpx); } to { opacity: 1; transform: translateY(0); } }
@keyframes resource-enter { from { opacity: 0; transform: translateX(12rpx); } to { opacity: 1; transform: translateX(0); } }
</style>
