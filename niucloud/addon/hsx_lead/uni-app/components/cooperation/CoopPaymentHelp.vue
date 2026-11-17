<template>
    <view v-if="help.data.value || help.loading.value || help.error.value" class="coop-payment-help" :style="layoutStyle">
        <button v-if="help.data.value" class="coop-payment-help__entry" role="button" aria-label="查看支付帮助" @tap.stop="open">
            <view class="coop-payment-help__mark" aria-hidden="true"><text>?</text></view>
            <text class="coop-payment-help__title">{{ help.data.value.title || '支付帮助' }}</text>
            <text class="coop-payment-help__arrow" aria-hidden="true">›</text>
        </button>
        <view v-else-if="help.loading.value" class="coop-payment-help__loading" role="status"><u-loading-icon size="14" /><text>正在读取支付帮助</text></view>
        <view v-else-if="help.error.value" class="coop-payment-help__error" role="status"><text>支付帮助暂不可用</text><button role="button" @tap.stop="help.refresh">重新加载</button></view>
        <view class="coop-payment-help__mount">
            <u-popup :show="help.opened.value" mode="bottom" :round="14" :z-index="popupZIndex" :safe-area-inset-bottom="false" @close="help.close">
                <view v-if="help.data.value" class="coop-payment-help__sheet" @touchmove.stop.prevent>
                    <view class="coop-payment-help__head"><text>{{ help.data.value.title || '支付帮助' }}</text><button role="button" aria-label="关闭支付帮助" @tap.stop="help.close"><text class="coop-payment-help__close-mark" aria-hidden="true">×</text></button></view>
                    <scroll-view scroll-y class="coop-payment-help__body" @touchmove.stop>
                        <view v-if="paymentIssue || orderNumber" class="coop-payment-help__safety" role="status">{{ safetyMessage }}</view>
                        <view class="coop-payment-help__instructions">
                            <view class="coop-payment-help__content" :class="{ 'is-collapsed': contentCanCollapse && !contentExpanded }">{{ help.data.value.content }}</view>
                            <button v-if="contentCanCollapse" class="coop-payment-help__expand" :aria-expanded="contentExpanded" @tap.stop="contentExpanded = !contentExpanded">{{ contentExpanded ? '收起说明' : '查看完整说明' }}<u-icon :name="contentExpanded ? 'arrow-up' : 'arrow-down'" size="10" color="var(--primary-color,#007aff)" /></button>
                        </view>
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
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { img } from '@/utils/common'
import { usePaymentHelp } from '../../composables/usePaymentHelp'
const props = withDefaults(defineProps<{ active?: boolean; order?: Record<string, any>; inset?: number; spacing?: number; paymentIssue?: boolean }>(), { active: true, order: () => ({}), inset: 0, spacing: 0, paymentIssue: false })
const emit = defineEmits<{ (event: 'ready', value: boolean): void }>()
const help = usePaymentHelp(() => props.active)
const available = computed(() => props.active && !!help.data.value?.enabled && !help.loading.value && !help.error.value)
watch(available, value => emit('ready', value), { immediate: true })
onBeforeUnmount(() => emit('ready', false))
const safetyMessage = '若已扣款或付款结果尚未确认，请先核对原付款单，不要重复付款。管理员可协助核对，添加好友不会自动确认收款或开通权益。'
const contentExpanded = ref(false)
const contentCanCollapse = computed(() => {
    const content = String(help.data.value?.content || '')
    return content.length > 110 || content.split('\n').length > 3
})
watch(() => help.data.value?.content, () => { contentExpanded.value = false })
function open(): boolean {
    if (!available.value || !help.current()) { help.close(); return false }
    contentExpanded.value = false
    help.open()
    return help.opened.value
}
defineExpose({ open })
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
.coop-payment-help { flex:none;min-width:0;width:100%;box-sizing:border-box; }
.coop-payment-help__entry { display:flex;align-items:center;gap:10px;width:100%;min-height:48px;padding:10px 12px;margin:0;border:1px solid #f0e5cf;border-radius:8px;background:#fffbf3;text-align:left;line-height:1.5;box-sizing:border-box; }
.coop-payment-help__entry::after,.coop-payment-help__head>button::after,.coop-payment-help__detail-head>button::after,.coop-payment-help__error>button::after,.coop-payment-help__expand::after { border:0; }
.coop-payment-help__mark { display:flex;align-items:center;justify-content:center;width:22px;height:22px;flex:none; }
.coop-payment-help__mark>text { display:flex;align-items:center;justify-content:center;width:18px;height:18px;border:1.5px solid #957341;border-radius:50%;font-size:12px;font-weight:600;line-height:18px;color:#957341; }
.coop-payment-help__title { flex:1;min-width:0;font-size:13px;font-weight:500;color:#795c33;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.coop-payment-help__arrow { flex:none;font-size:22px;line-height:1;color:#9b845f; }
.coop-payment-help__loading,.coop-payment-help__error { display:flex;align-items:center;gap:8px;min-height:44px;padding:0 2px;font-size:12px;line-height:1.5;color:#697586; }
.coop-payment-help__error { justify-content:space-between; }
.coop-payment-help__error>button { padding:0 8px;margin:0;min-height:44px;font-size:12px;line-height:44px;background:transparent;color:var(--primary-color,#007aff); }
.coop-payment-help__mount { flex:none;width:100%;height:0;min-height:0;overflow:visible; }
.coop-payment-help__sheet { display:flex;flex-direction:column;width:100%;height:82vh;max-height:82vh;background:#fff;border-radius:14px 14px 0 0;overflow:hidden;box-sizing:border-box; }
.coop-payment-help__head { display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid #edf0f4;flex:none; }
.coop-payment-help__head>text { flex:1;min-width:0;overflow-wrap:break-word;font-size:17px;font-weight:600;color:#20252d;line-height:1.5; }
.coop-payment-help__head>button { display:flex;align-items:center;justify-content:center;flex:none;width:44px;height:44px;padding:0;margin:-4px -8px -4px 0;background:transparent;border:0; }
.coop-payment-help__close-mark { font-size:28px;font-weight:300;line-height:1;color:#697586; }
.coop-payment-help__body { flex:1 1 0;min-height:0;height:0;width:100%;box-sizing:border-box; }
.coop-payment-help__safety { margin:16px 20px 0;padding:12px;border-radius:8px;background:#f4f7fb;color:#49637f;font-size:12px;line-height:1.7;overflow-wrap:break-word; }
.coop-payment-help__instructions { margin:16px 20px 0; }
.coop-payment-help__content { font-size:14px;line-height:1.7;color:#414851;white-space:pre-wrap;overflow-wrap:break-word; }
.coop-payment-help__content.is-collapsed { display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden;max-height:71.4px; }
.coop-payment-help__expand { display:flex;align-items:center;gap:6px;min-height:44px;padding:0;margin:0;background:transparent;color:var(--primary-color,#007aff);font-size:12px;line-height:44px; }
.coop-payment-help__qr { display:flex;flex-direction:column;align-items:center;gap:8px;margin:20px; }
.coop-payment-help__qr>image { display:block;width:188px;height:188px;max-width:100%;background:#fff; }
.coop-payment-help__qr>text { font-size:12px;color:#697586;line-height:1.5; }
.coop-payment-help__image-error { display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;width:188px;height:188px;max-width:100%;background:#f6f8fa;border-radius:8px;color:#697586;font-size:13px; }
.coop-payment-help__image-error>button { margin:0;padding:0 12px;min-height:44px;font-size:12px;line-height:44px;background:#fff;color:var(--primary-color,#007aff);border:1px solid #e5e9ef;border-radius:6px; }
.coop-payment-help__image-error>button::after { border:0; }
.coop-payment-help__detail { margin:0 20px;padding:8px 0 16px;border-top:1px solid #edf0f4;box-sizing:border-box; }
.coop-payment-help__detail-head { display:flex;align-items:center;justify-content:space-between;gap:12px;color:#697586;font-size:12px;line-height:1.5; }
.coop-payment-help__detail-head>button { flex:none;padding:0 2px;min-height:44px;line-height:44px;margin:0;font-size:12px;color:var(--primary-color,#007aff);background:transparent; }
.coop-payment-help__text { display:block;color:#303947;font-size:14px;line-height:1.7;white-space:pre-wrap;overflow-wrap:break-word;user-select:text; }
.coop-payment-help__safe-end { height:16px;height:calc(16px + env(safe-area-inset-bottom)); }
</style>
