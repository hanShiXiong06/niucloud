<template>
    <view v-if="help.data.value || help.loading.value || help.error.value" class="coop-payment-help" :style="layoutStyle">
        <button v-if="help.data.value" class="coop-payment-help__entry" role="button" aria-label="查看支付帮助" @tap.stop="help.open">
            <view class="coop-payment-help__mark" aria-hidden="true"><text>?</text></view>
            <view class="coop-payment-help__entry-copy"><text class="coop-payment-help__title">{{ help.data.value.title || '支付帮助' }}</text><view class="coop-payment-help__preview">{{ help.data.value.content }}</view></view>
            <text class="coop-payment-help__arrow" aria-hidden="true">›</text>
        </button>
        <view v-else-if="help.loading.value" class="coop-payment-help__loading" role="status"><u-loading-icon size="14" /><text>正在读取支付帮助</text></view>
        <view v-else-if="help.error.value" class="coop-payment-help__error" role="status"><text>支付帮助暂不可用</text><button role="button" @tap.stop="help.refresh">重新加载</button></view>
        <view class="coop-payment-help__mount">
            <u-popup :show="help.opened.value" mode="bottom" :round="14" :z-index="popupZIndex" :safe-area-inset-bottom="false" @close="help.close">
                <view v-if="help.data.value" class="coop-payment-help__sheet" @touchmove.stop.prevent>
                    <view class="coop-payment-help__head"><text>{{ help.data.value.title || '支付帮助' }}</text><button role="button" aria-label="关闭支付帮助" @tap.stop="help.close"><text class="coop-payment-help__close-mark" aria-hidden="true">×</text></button></view>
                    <scroll-view scroll-y class="coop-payment-help__body" @touchmove.stop>
                        <view class="coop-payment-help__content">{{ help.data.value.content }}</view>
                        <view class="coop-payment-help__qr"><image v-if="!imageFailed" :key="imageRevision" :src="img(help.data.value.qr_image)" mode="aspectFit" :show-menu-by-longpress="true" role="button" aria-label="客服二维码，点击查看大图" @tap.stop="preview" @error="imageFailed=true" /><view v-else class="coop-payment-help__image-error"><text>二维码暂未加载</text><button role="button" @tap.stop="reloadImage">重新加载图片</button></view><text v-if="!imageFailed">点按查看大图</text></view>
                        <view v-if="help.data.value.remark" class="coop-payment-help__detail"><view class="coop-payment-help__detail-head"><text>添加好友备注</text><button role="button" @tap.stop="copyRemark">复制备注</button></view><text class="coop-payment-help__text">{{ help.data.value.remark }}</text></view>
                        <view v-if="orderNumber" class="coop-payment-help__detail"><view class="coop-payment-help__detail-head"><text>当前付款单号</text><button role="button" @tap.stop="copyOrder">复制单号</button></view><text class="coop-payment-help__text">{{ orderNumber }}</text></view>
                        <view class="coop-payment-help__safe-end" aria-hidden="true" />
                    </scroll-view>
                </view>
            </u-popup>
        </view>
    </view>
</template>
<script lang="ts">
export default { options: { virtualHost: true } }
</script>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { img } from '@/utils/common'
import { usePaymentHelp } from '../../composables/usePaymentHelp'
const props = withDefaults(defineProps<{ active?: boolean; order?: Record<string, any>; inset?: number; spacing?: number }>(), { active: true, order: () => ({}), inset: 0, spacing: 0 })
const help = usePaymentHelp(() => props.active)
// Native virtualHost components do not reliably inherit a parent's class. Keep
// the reusable spacing on this component's own rendered root on every platform.
const pixels = (value: number) => `${Number.isFinite(Number(value)) ? Math.round(Math.min(32, Math.max(0, Number(value)))) : 0}px`
const layoutStyle = computed(() => ({ paddingLeft: pixels(props.inset), paddingRight: pixels(props.inset), marginTop: pixels(props.spacing), marginBottom: pixels(props.spacing) }))
const imageFailed = ref(false), imageRevision = ref(0)
watch(() => help.data.value?.qr_image, () => { imageFailed.value = false; imageRevision.value++ })
const orderNumber = computed(() => typeof props.order?.order_no === 'string' ? props.order.order_no.trim() : '')
let popupZIndex = 10095
// #ifdef H5
popupZIndex = 999
// #endif
function preview() { if (!help.current() || !help.opened.value || !help.data.value) { help.close(); return }; const source = img(help.data.value.qr_image); uni.previewImage({ urls: [source], current: source }) }
function copy(value: string) {
    if (!help.current() || !help.opened.value || !value) { help.close(); return }
    uni.setClipboardData({ data: value, success() { uni.showToast({ title: '已复制', icon: 'success' }) }, fail() { uni.showToast({ title: '复制失败，请长按文字复制', icon: 'none' }) } })
}
function copyRemark() { copy(help.data.value?.remark || '') }
function copyOrder() { copy(orderNumber.value) }
function reloadImage() { if (help.current()) { imageFailed.value = false; imageRevision.value++ } else help.close() }
</script>
<style scoped lang="scss">
.coop-payment-help__preview{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;max-height:38.4px;font-size:12px;line-height:1.6;color:#8b7049;white-space:pre-line;overflow-wrap:break-word}
.coop-payment-help__mark>text{display:flex;align-items:center;justify-content:center;width:18px;height:18px;border:1.5px solid #a36d22;border-radius:50%;font-size:13px;font-weight:650;line-height:18px;color:#a36d22}.coop-payment-help__arrow{flex:none;font-size:23px;line-height:1;color:#9b7745}.coop-payment-help__close-mark{font-size:28px;font-weight:300;line-height:1;color:#6f7783}
.coop-payment-help__image-error{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;width:188px;height:188px;max-width:100%;background:#f6f8fa;border-radius:8px;color:#7b838e;font-size:13px}.coop-payment-help__image-error>button{margin:0;padding:0 12px;min-height:40px;font-size:12px;line-height:40px;background:#fff;color:var(--primary-color,#007aff);border:1px solid #e5e9ef;border-radius:6px}.coop-payment-help__image-error>button::after{border:0}
.coop-payment-help{flex:none;min-width:0;width:100%;box-sizing:border-box}.coop-payment-help__entry{display:flex;align-items:center;gap:10px;width:100%;padding:12px;margin:0;border:1px solid #f0dfbf;border-radius:10px;background:#fff9ed;text-align:left;line-height:1.5;box-sizing:border-box}.coop-payment-help__entry::after,.coop-payment-help__head>button::after,.coop-payment-help__detail-head>button::after,.coop-payment-help__error>button::after{border:0}.coop-payment-help__mark{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#f9edce;flex:none}.coop-payment-help__entry-copy{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.coop-payment-help__title{font-size:14px;font-weight:600;color:#79531e;overflow-wrap:break-word}.coop-payment-help__loading,.coop-payment-help__error{display:flex;align-items:center;gap:8px;padding:10px 2px;font-size:12px;line-height:1.5;color:#777f8a}.coop-payment-help__error{justify-content:space-between}.coop-payment-help__error>button{padding:0 8px;margin:0;min-height:40px;font-size:12px;line-height:40px;background:transparent;color:var(--primary-color,#007aff)}.coop-payment-help__mount{flex:none;width:100%;height:0;min-height:0;overflow:visible}.coop-payment-help__sheet{display:flex;flex-direction:column;width:100%;height:82vh;max-height:82vh;background:#fff;border-radius:14px 14px 0 0;overflow:hidden;box-sizing:border-box}.coop-payment-help__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid #eef0f3;flex:none}.coop-payment-help__head>text{flex:1;min-width:0;overflow-wrap:break-word;font-size:17px;font-weight:650;color:#202124;line-height:1.5}.coop-payment-help__head>button{display:flex;align-items:center;justify-content:center;flex:none;width:44px;height:44px;padding:0;margin:-4px -8px -4px 0;background:transparent;border:0}.coop-payment-help__body{flex:1 1 0;min-height:0;height:0;width:100%;box-sizing:border-box}.coop-payment-help__content{padding:18px 20px 0;font-size:14px;line-height:1.8;color:#414851;white-space:pre-wrap;overflow-wrap:break-word}.coop-payment-help__qr{display:flex;flex-direction:column;align-items:center;gap:10px;margin:18px 20px}.coop-payment-help__qr>image{display:block;width:188px;height:188px;max-width:100%;border:1px solid #edf0f4;border-radius:8px;background:#fff}.coop-payment-help__qr>text{font-size:12px;color:#858c96;line-height:1.5}.coop-payment-help__detail{margin:14px 20px;padding:12px;background:#f6f8fa;border-radius:8px;box-sizing:border-box}.coop-payment-help__detail-head{display:flex;align-items:center;justify-content:space-between;gap:12px;color:#7b838e;font-size:12px;line-height:1.5}.coop-payment-help__detail-head>button{flex:none;padding:0 2px;min-height:40px;line-height:40px;margin:-6px 0;font-size:12px;color:var(--primary-color,#007aff);background:transparent}.coop-payment-help__text{display:block;margin-top:8px;color:#333d4a;font-size:14px;line-height:1.7;white-space:pre-wrap;overflow-wrap:break-word;user-select:text}.coop-payment-help__safe-end{height:16px;height:calc(16px + env(safe-area-inset-bottom))}
</style>
