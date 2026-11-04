<template>
    <view class="discovery">
        <view class="discovery__hero">
            <view class="discovery__intro">
                <view class="discovery__copy">
                    <text class="discovery__eyebrow">本地生意，一起做</text>
                    <view class="discovery__title"><text>把客户交给</text><text>合适的门店</text></view>
                    <text class="discovery__description">按客户所在地找交付伙伴</text>
                </view>
                <image class="discovery__art" :src="storeArt" mode="aspectFit" aria-hidden="true" />
            </view>
            <u-button type="primary" shape="circle" text="查找交付门店" :custom-style="buttonStyle" @click="navigate('browse')">
                <view class="discovery__primary-label"><u-icon name="map" size="18" color="#fff" /><text>查找交付门店</text><u-icon name="arrow-right" size="13" color="#fff" /></view>
            </u-button>
        </view>

        <view class="discovery__section-head"><text>我的经营资料</text></view>
        <view class="discovery__shortcuts">
            <button class="discovery__shortcut discovery__shortcut--inventory" aria-label="查看我的货盘" @tap="navigate('inventory')">
                <view class="discovery__shortcut-top"><image :src="inventoryArt" mode="aspectFit" aria-hidden="true" /><u-icon name="arrow-right" size="13" color="#8d9caf" /></view>
                <text class="discovery__shortcut-title">我的货盘</text>
                <view class="discovery__metric"><text class="discovery__metric-number">{{ inventoryCount }}</text><text>条库存参考</text></view>
            </button>
            <button class="discovery__shortcut discovery__shortcut--capability" aria-label="管理我的交付能力" @tap="navigate('capability')">
                <view class="discovery__shortcut-top"><image :src="capabilityArt" mode="aspectFit" aria-hidden="true" /><u-icon name="arrow-right" size="13" color="#8d9caf" /></view>
                <text class="discovery__shortcut-title">交付能力</text>
                <view class="discovery__metric"><text class="discovery__metric-number">{{ modelCount }}</text><text>款已登记型号</text></view>
            </button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { interactionFeedback } from '../../utils/interaction-feedback'
// Plugin-local assets are bundled by the app build, not fetched from a demo server.
import storeArt from '../../static/cooperation/visuals/store-discovery.png'
import inventoryArt from '../../static/cooperation/visuals/inventory.png'
import capabilityArt from '../../static/cooperation/visuals/delivery-capability.png'

const props = defineProps<{ profile: Record<string, any> }>()
const emit = defineEmits<{ (event: 'browse'): void; (event: 'inventory'): void; (event: 'capability'): void }>()
const count = (value: unknown) => Number.isFinite(Number(value)) && Number(value) > 0 ? Math.floor(Number(value)) : 0
const inventoryCount = computed(() => count(props.profile.inventory_count))
const modelCount = computed(() => count(props.profile.manual_model_count))
const buttonStyle = { height: '46px', fontSize: '15px', fontWeight: '600', border: '0', boxShadow: '0 5px 12px rgba(52,120,204,.12)' }
function navigate(destination: 'browse' | 'inventory' | 'capability') {
    interactionFeedback('navigate')
    if (destination === 'browse') emit('browse')
    else if (destination === 'inventory') emit('inventory')
    else emit('capability')
}
</script>

<style scoped lang="scss">
.discovery { padding: 24rpx 28rpx 40rpx; color: #293d59; }
.discovery__hero { padding: 28rpx; border-radius: 20rpx; background: linear-gradient(115deg,#edf5ff 0%,#f5f9ff 72%,#eaf4ff 100%); border: 1rpx solid #e0ebfa; overflow: hidden; animation: discovery-enter 220ms ease-out both; }
.discovery__intro { display: flex; align-items: center; gap: 12rpx; min-height: 236rpx; margin-bottom: 20rpx; }
.discovery__copy { min-width: 0; flex: 1; position: relative; z-index: 1; }
.discovery__eyebrow { display: block; font-size: 21rpx; line-height: 1.7; color: #6585ad; letter-spacing: 1rpx; }
.discovery__title { display: block; margin-top: 10rpx; font-size: 38rpx; line-height: 1.4; font-weight: 650; color: #263f62; }
.discovery__title>text { display: block; white-space: nowrap; }
.discovery__description { display: block; margin-top: 16rpx; font-size: 23rpx; line-height: 1.7; color: #768ba6; white-space: nowrap; }
.discovery__art { width: 230rpx; height: 230rpx; flex: none; }
.discovery__primary-label { display: flex; align-items: center; justify-content: center; gap: 14rpx; width: 100%; transition: transform 150ms ease-out; }
.discovery__primary-label>text { flex: none; }
.discovery__hero :deep(.u-button:active) { transform: translateY(1px); }
.discovery__section-head { margin: 30rpx 4rpx 18rpx; font-size: 25rpx; line-height: 1.7; font-weight: 550; color: #667c98; }
.discovery__shortcuts { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 20rpx; }
.discovery__shortcut { min-width: 0; width: 100%; box-sizing: border-box; margin: 0; padding: 18rpx 24rpx 26rpx; border-radius: 16rpx; text-align: left; background: #fff; line-height: 1.6; font-family: inherit; border: 1rpx solid #e9eef5; transition: transform 150ms ease-out, background-color 150ms ease-out; animation: discovery-enter 220ms ease-out 50ms both; }
.discovery__shortcut::after { border: 0; }
.discovery__shortcut:active { transform: scale(.985); background: #f5f8fd; }
.discovery__shortcut--capability { animation-delay: 90ms; }
.discovery__shortcut-top { display: flex; justify-content: space-between; align-items: center; gap: 12rpx; height: 118rpx; margin-bottom: 12rpx; }
.discovery__shortcut-top>image { display: block; width: 116rpx; height: 116rpx; flex: none; }
.discovery__shortcut-title { display: block; color: #344a67; font-size: 29rpx; line-height: 1.65; font-weight: 600; white-space: nowrap; }
.discovery__metric { display: flex; align-items: baseline; flex-wrap: wrap; gap: 7rpx; color: #8b9aaf; font-size: 21rpx; line-height: 1.8; margin-top: 3rpx; }
.discovery__metric>text { white-space: nowrap; }
.discovery__metric-number { color: #6687b4; font-weight: 650; font-size: 27rpx; font-variant-numeric: tabular-nums; }
@keyframes discovery-enter { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
@media(max-width:340px) { .discovery { padding-left: 24rpx; padding-right: 24rpx; }.discovery__hero { padding: 24rpx; }.discovery__art { width: 196rpx; height: 210rpx; }.discovery__title { font-size: 36rpx; }.discovery__intro { gap: 6rpx; }.discovery__description { font-size: 22rpx; }.discovery__shortcuts { gap: 16rpx; }.discovery__shortcut { padding-left: 20rpx; padding-right: 20rpx; } }
@media(prefers-reduced-motion:reduce) { .discovery,.discovery view,.discovery text,.discovery button,.discovery image { animation: none !important; transition: none !important; }.discovery__shortcut:active { transform: none; }.discovery__hero :deep(.u-button:active) { transform: none; } }
</style>
