<template>
    <OfflineOrderProcessPopup v-if="settlementAction" :show="show" :order="order" :action="settlementAction" @close="emit('close')" @success="done('处理成功')" />
    <u-popup v-else :show="show" mode="bottom" round="20" :closeOnClickOverlay="!submitting" @close="close">
        <view class="order-action-panel" :style="themeColor()">
            <view class="panel-head">
                <view><text class="panel-title">{{ title }}</text><text class="panel-subtitle">{{ order?.order_no }} · ¥{{ money(order?.order_money) }}</text></view>
                <view class="panel-close" @click="close"><u-icon name="close" size="20" /></view>
            </view>
            <scroll-view scroll-y class="panel-body">
                <view v-if="loading" class="panel-state">正在核对订单资料…</view>
                <view v-if="error" class="panel-error">{{ error }}</view>
                <view v-if="uncertain" class="panel-notice">结果尚未确认，不能按失败重复提交。请刷新原订单核对处理记录。</view>
                <template v-if="!loading">
                    <view v-if="action === 'return'" class="panel-notice">确认实际收回后恢复上架，未收款冲销原应收；已收款生成 ERP 待退款，由财务实际转账后确认。此处不会自动退款。</view>
                    <view v-if="action === 'refund'" class="panel-notice panel-notice--danger">将向支付渠道申请原路退款，可能实际退还资金。退款不等于设备已收回；收回实物后还需确认收回。</view>
                    <view v-if="action === 'received'" class="panel-notice">仅在已核对串号并实际收回设备后确认，商品将恢复可售，不重复生成收付款。</view>
                    <view v-if="action === 'close'" class="panel-notice">仅关闭尚未支付的订单并释放占用库存。系统会先核对支付结果，已付款或支付结果不明时不会直接关闭。</view>
                    <view v-if="action === 'finish'" class="panel-notice">请确认客户已收到本订单设备。确认后订单进入已完成，不等于新增收款。</view>
                    <view v-if="action === 'contacted'" class="panel-notice">确认已联系客户并沟通到店安排。此动作不会收款、挂账或交付设备。</view>
                    <template v-if="action === 'delivery'">
                        <view class="panel-notice">{{ order?.delivery_type === 'store' ? '请核对客户身份及串号，确认本次所选设备已当面交付。' : '仅登记已实际交件的物流信息，不会在这里自动叫件或购买面单。需要申请/打印面单时请使用电脑端。' }}</view>
                        <view v-if="order?.delivery_type === 'express'" class="panel-section">
                            <text class="field-label">发货方式</text>
                            <view class="delivery-modes">
                                <view v-for="type in deliveryOptions" :key="type.value" :class="{ active: deliveryType === type.value }" @click="deliveryType = type.value">{{ type.label }}</view>
                            </view>
                            <template v-if="deliveryType === 'express'">
                                <picker :range="companies" range-key="company_name" :value="companyIndex" @change="companyId = Number(companies[Number($event.detail.value)]?.company_id || 0)">
                                    <view class="field-select">{{ companies.find(item => Number(item.company_id) === companyId)?.company_name || '选择物流公司' }} <text>›</text></view>
                                </picker>
                                <view class="field-input"><input v-model="expressNumber" placeholder="填写或扫描真实物流单号" :maxlength="50" :adjust-position="true" :cursor-spacing="100" /><view @click="scanExpress"><u-icon name="scan" size="22" /></view></view>
                            </template>
                        </view>
                        <view v-else-if="order?.delivery_type === 'local_delivery'" class="panel-error">同城配送需选择门店及配送员，请在电脑端完成调度；此处不会假装发货成功。</view>
                    </template>

                    <view v-if="['delivery', 'return', 'refund', 'received'].includes(action)" class="panel-section">
                        <view class="section-heading"><text>选择本次设备</text><text>{{ selectedIds.length }} 件</text></view>
                        <view v-for="goods in lines" :key="goods.order_goods_id" class="selection-line" :class="{ 'selection-line--disabled': !eligible(goods) }" @click="toggle(goods)">
                            <u-icon :name="selectedIds.includes(Number(goods.order_goods_id)) ? 'checkmark-circle-fill' : 'circle'" :color="selectedIds.includes(Number(goods.order_goods_id)) ? 'var(--primary-color)' : '#aeb7c4'" size="22" />
                            <view class="selection-copy"><text class="selection-name">{{ goods.goods_name }}</text><text>{{ goods.sku_name }}</text><text class="imei">{{ goods.imei || goodsIdentity(goods) || '未记录串号，请核对实物' }}</text><text v-if="!eligible(goods)" class="line-warning">{{ goods.requires_location ? '原仓库缺失，请到 ERP 退货单选择实际收货仓库' : goods.reason || '当前状态不支持此操作' }}</text></view>
                            <text class="selection-money">¥{{ money(lineAmount(goods)) }}</text>
                        </view>
                        <view v-if="!lines.length" class="panel-state">没有可处理设备，请刷新原订单核对</view>
                    </view>
                    <view v-if="action === 'return' && selectedIds.length" class="panel-total"><text>冲销未收 ¥{{ money(offsetTotal) }}</text><text>待财务退款 ¥{{ money(refundTotal) }}</text></view>
                    <view v-if="action === 'refund'" class="panel-total">本次原路退款申请 ¥{{ money(onlineRefundTotal) }}</view>
                    <view v-if="action === 'return' && lines.some(item => item.requires_location)" class="panel-link" @click="openErpReturns">到 ERP 销售退货核对仓库 ›</view>

                    <view v-if="['return', 'refund', 'reject_voucher', 'remark', 'delivery'].includes(action)" class="panel-section">
                        <text class="field-label">{{ ['return', 'refund', 'reject_voucher'].includes(action) ? '处理原因（必填）' : '商家备注（选填）' }}</text>
                        <textarea v-model="remark" :maxlength="action === 'refund' ? 200 : 255" class="panel-textarea" placeholder="填写真实沟通或处理情况" :adjust-position="true" :cursor-spacing="100" />
                    </view>
                    <view v-if="!['remark', 'contacted', 'reject_voucher'].includes(action)" class="physical-confirm" @click="confirmed = !confirmed">
                        <u-icon :name="confirmed ? 'checkmark-circle-fill' : 'circle'" :color="confirmed ? 'var(--primary-color)' : '#aeb7c4'" size="22" />
                        <text>{{ confirmationText }}</text>
                    </view>
                </template>
            </scroll-view>
            <view class="panel-foot">
                <u-button :customStyle="{ flex: 1 }" :disabled="submitting" @click="close">取消</u-button>
                <u-button :customStyle="{ flex: 2 }" :type="['return', 'refund', 'close', 'reject_voucher'].includes(action) ? 'error' : 'primary'" :loading="submitting" :disabled="loading || (!uncertain && blocked)" @click="submit">{{ uncertain ? '刷新原单核对' : submitLabel }}</u-button>
            </view>
        </view>
    </u-popup>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import OfflineOrderProcessPopup from './OfflineOrderProcessPopup.vue'
import { closeOrder, confirmOrderDeviceReceived, deliverOrder, finishOrder, getDeliveryCompanies, getOrderDeliveryTypes, processOrderAction, refundOrder, setOrderRemark } from '@/addon/phone_shop/api/order'
import { goodsIdentity, money, orderGoods } from '@/addon/phone_shop/utils/orderWorkflow'
const props = defineProps<{ show: boolean; order: Record<string, any> | null; action: string }>()
const emit = defineEmits(['close', 'changed'])
const loading = ref(false), submitting = ref(false), error = ref(''), uncertain = ref(false), confirmed = ref(false), remark = ref('')
const lines = ref<any[]>([]), selectedIds = ref<number[]>([]), previewToken = ref('')
const deliveryType = ref('express'), companies = ref<any[]>([]), companyId = ref(0), expressNumber = ref(''), deliveryOptions = ref<Array<{ value: string; label: string }>>([])
let version = 0
const settlementAction = computed(() => ['confirm_paid', 'confirm_credit', 'close_unreachable'].includes(props.action) ? props.action as 'confirm_paid' | 'confirm_credit' | 'close_unreachable' : null)
const title = computed(() => ({ delivery: '发货 / 交付', return: '收回设备并处理原账', refund: '申请原路退款', received: '确认设备已收回', close: '关闭未付款订单', finish: '确认订单完成', contacted: '标记已联系', reject_voucher: '驳回付款凭证', remark: '商家备注' } as Record<string, string>)[props.action] || '处理订单')
const submitLabel = computed(() => ({ delivery: props.order?.delivery_type === 'store' ? '确认已交付' : '已交件，确认发货', return: '确认收回并处理原账', refund: '确认申请原路退款', received: '确认收回并恢复可售', close: '确认关闭订单', finish: '确认完成', contacted: '确认已联系', reject_voucher: '确认驳回', remark: '保存备注' } as Record<string, string>)[props.action] || '确认')
const confirmationText = computed(() => ({ delivery: '我已核对所选设备并实际交付/交件', return: '我已核对所选设备串号并实际收回实物', received: '我已核对串号并实际收回这台设备', refund: '我已核对退款设备及金额，确认发起原路退款', close: '我已确认客户不再购买，并同意核对支付后关闭', finish: '我已确认客户收到设备，可以完成订单' } as Record<string, string>)[props.action] || '我已核对本次操作')
const chosen = computed(() => lines.value.filter(item => selectedIds.value.includes(Number(item.order_goods_id)) && eligible(item)))
const offsetTotal = computed(() => chosen.value.reduce((sum, item) => sum + Number(item.offset_amount || 0), 0))
const refundTotal = computed(() => chosen.value.reduce((sum, item) => sum + Number(item.refund_amount || 0), 0))
const onlineRefundTotal = computed(() => {
    const total = chosen.value.reduce((sum, item) => sum + Math.max(0, Number(item.goods_money || 0) - Number(item.discount_money || 0)), 0)
    return total + (chosen.value.length && chosen.value.length === lines.value.filter(eligible).length ? Number(props.order?.delivery_money || 0) : 0)
})
const companyIndex = computed(() => Math.max(0, companies.value.findIndex(item => Number(item.company_id) === companyId.value)))
const offlineStore = computed(() => props.order?.delivery_type === 'store' && ['offline_cash', 'offline_credit'].includes(props.order?.payment_mode))
const blocked = computed(() => (['delivery', 'return', 'refund', 'received'].includes(props.action) && !chosen.value.length)
    || (props.action === 'delivery' && props.order?.delivery_type === 'local_delivery')
    || (props.action === 'delivery' && props.order?.delivery_type === 'express' && !deliveryOptions.value.length))
function eligible(goods: any): boolean {
    if (props.action === 'return') return Boolean(goods.can_return) && !goods.requires_location
    if (props.action === 'received') return Boolean(goods.can_confirm_received)
    if (props.action === 'refund') return Number(goods.status) === 1 && Number(goods.is_enable_refund) === 1
    if (props.action === 'delivery') return Number(goods.status) === 1 && goods.delivery_status === 'wait_delivery' && goods.return_state !== 'returned'
    return true
}
function lineAmount(goods: any) { return props.action === 'return' ? goods.return_amount : props.action === 'refund' ? Number(goods.goods_money || 0) - Number(goods.discount_money || 0) : Number(goods.price || 0) * Number(goods.num || 1) }
function toggle(goods: any) {
    if (submitting.value || !eligible(goods)) return
    if (props.action === 'delivery' && offlineStore.value) { uni.showToast({ title: '线下到店交付按整单确认，请核对全部设备', icon: 'none' }); return }
    const id = Number(goods.order_goods_id)
    if (props.action === 'received') { selectedIds.value = selectedIds.value.includes(id) ? [] : [id]; return }
    selectedIds.value = selectedIds.value.includes(id) ? selectedIds.value.filter(item => item !== id) : [...selectedIds.value, id]
}
function close() { if (!submitting.value) { ++version; emit('close') } }
function done(message: string) { uni.showToast({ title: message, icon: 'none' }); emit('close'); emit('changed') }
function openErpReturns() { uni.navigateTo({ url: '/addon/hsx_erp/pages/sale_return/list' }) }
function scanExpress() {
    // #ifdef H5
    uni.showToast({ title: '浏览器请手动填写物流单号', icon: 'none' })
    // #endif
    // #ifndef H5
    uni.scanCode({ success: result => { expressNumber.value = String(result.result || '').trim().slice(0, 50) } })
    // #endif
}
watch(() => [props.show, props.action, props.order?.order_id], async () => {
    const token = ++version
    if (!props.show || !props.order || settlementAction.value) return
    loading.value = true; error.value = ''; uncertain.value = false; confirmed.value = false; remark.value = props.action === 'remark' ? String(props.order.shop_remark || '') : ''
    lines.value = orderGoods(props.order); selectedIds.value = []; previewToken.value = ''; companyId.value = 0; expressNumber.value = ''; companies.value = []; deliveryOptions.value = []
    try {
        if (props.action === 'return') {
            const response: any = await processOrderAction({ order_id: props.order.order_id, action: 'return_preview' })
            if (token !== version) return
            lines.value = Array.isArray(response.data?.items) ? response.data.items : []
            previewToken.value = String(response.data?.preview_token || '')
        }
        if (props.action === 'delivery' && props.order.delivery_type === 'express') {
            const [companyResponse, typeResponse]: any[] = await Promise.all([getDeliveryCompanies(), getOrderDeliveryTypes('express')])
            if (token !== version) return
            companies.value = Array.isArray(companyResponse.data) ? companyResponse.data : []
            deliveryOptions.value = Object.entries(typeResponse.data || {}).filter(([value]) => ['express', 'none_express'].includes(value)).map(([value, label]) => ({ value, label: String(label) }))
            deliveryType.value = deliveryOptions.value[0]?.value || ''
        } else deliveryType.value = String(props.order.delivery_type || '')
        const ids = lines.value.filter(eligible).map(item => Number(item.order_goods_id))
        selectedIds.value = props.action === 'received' ? ids.slice(0, 1) : ids
    } catch (reason: any) { if (token === version) error.value = reason?.msg || reason?.message || '资料加载失败，请关闭后重新核对' }
    finally { if (token === version) loading.value = false }
}, { immediate: true })
async function submit() {
    if (submitting.value || loading.value || !props.order) return
    if (uncertain.value) { emit('close'); emit('changed'); return }
    if (blocked.value) return
    if (!['remark', 'contacted', 'reject_voucher'].includes(props.action) && !confirmed.value) return uni.showToast({ title: '请先勾选操作确认', icon: 'none' })
    if (['return', 'refund', 'reject_voucher'].includes(props.action) && !remark.value.trim()) return uni.showToast({ title: '请填写处理原因', icon: 'none' })
    if (props.action === 'delivery' && deliveryType.value === 'express' && (!companyId.value || !expressNumber.value.trim())) return uni.showToast({ title: '请选择物流公司并填写单号', icon: 'none' })
    if (props.action === 'return' && !previewToken.value) return uni.showToast({ title: '原账核对结果缺失，请关闭重试', icon: 'none' })
    submitting.value = true; error.value = ''
    const orderId = Number(props.order.order_id)
    try {
        let message = '处理已提交，请核对订单最新状态'
        if (props.action === 'close') { await closeOrder(orderId); message = '订单已关闭' }
        else if (props.action === 'finish') { await finishOrder(orderId); message = '订单已完成' }
        else if (props.action === 'remark') { await setOrderRemark(orderId, remark.value.trim()); message = '备注已保存' }
        else if (props.action === 'contacted' || props.action === 'reject_voucher') {
            await processOrderAction({ order_id: orderId, action: props.action, remark: remark.value.trim(), close_reason: remark.value.trim() }); message = props.action === 'contacted' ? '已记录联系情况' : '凭证已驳回'
        } else if (props.action === 'return') {
            const response: any = await processOrderAction({ order_id: orderId, action: 'return_received', order_goods_ids: selectedIds.value, reason: remark.value.trim(), received: true, preview_token: previewToken.value })
            message = response.data?.message || `设备已收回，¥${money(response.data?.refund_amount)} 待财务退款`
        } else if (props.action === 'received') { await confirmOrderDeviceReceived(selectedIds.value[0]); message = '设备已收回，已恢复可售' }
        else if (props.action === 'refund') {
            await refundOrder({ order_goods_ids: selectedIds.value, shop_active_refund_money: onlineRefundTotal.value.toFixed(2), shop_active_refund_remark: remark.value.trim() })
            message = '退款申请已提交，请核对支付退款结果'
        } else if (props.action === 'delivery') {
            if (offlineStore.value) await processOrderAction({ order_id: orderId, action: 'confirm_delivery', remark: remark.value.trim() || '已核对客户并当面交付整单设备' })
            else await deliverOrder({ order_id: orderId, order_goods_ids: selectedIds.value, delivery_ids: [], delivery_type: deliveryType.value, delivery_way: 'manual_write', express_company_id: companyId.value, express_number: deliveryType.value === 'express' ? expressNumber.value.trim() : '', remark: remark.value.trim() })
            message = offlineStore.value ? '已记录整单到店交付' : props.order.delivery_type === 'store' ? '已记录交付，请核对订单后确认完成' : '已登记发货'
        }
        done(message)
    } catch (reason: any) {
        uncertain.value = !reason || typeof reason.code !== 'number'
        error.value = reason?.msg || reason?.message || '提交响应未确认，请刷新订单核对'
        confirmed.value = false
    } finally { submitting.value = false }
}
</script>
<style scoped lang="scss">
.order-action-panel { height: 88vh; display: flex; flex-direction: column; background: #fff; color: #263244; }
.panel-head { flex-shrink: 0; padding: 28rpx 28rpx 22rpx; display: flex; justify-content: space-between; border-bottom: 1rpx solid #edf0f4; gap: 18rpx; }
.panel-title, .panel-subtitle { display: block; }.panel-title { font-size: 32rpx; font-weight: 650; }.panel-subtitle { margin-top: 8rpx; font-size: 22rpx; color: #7b8797; overflow-wrap: anywhere; }
.panel-close { padding: 6rpx 0 12rpx 20rpx; }.panel-body { flex: 1; min-height: 0; padding: 20rpx 26rpx; box-sizing: border-box; }
.panel-state { padding: 30rpx 0; color: #7b8797; text-align: center; font-size: 26rpx; }.panel-error { padding: 18rpx; margin-bottom: 18rpx; background: #fff1f0; color: #b42318; line-height: 1.6; font-size: 24rpx; }
.panel-notice { padding: 18rpx 20rpx; margin-bottom: 20rpx; background: #f2f6fc; color: #4e6078; font-size: 24rpx; line-height: 1.65; }.panel-notice--danger { background: #fff5e8; color: #8f581d; }
.panel-section { margin-bottom: 24rpx; }.field-label, .section-heading { display: flex; justify-content: space-between; margin-bottom: 16rpx; font-size: 27rpx; font-weight: 600; }.section-heading text+text { color: #8590a2; font-weight: 400; }
.selection-line { display: flex; align-items: center; gap: 16rpx; padding: 22rpx 0; border-bottom: 1rpx solid #edf0f4; }.selection-line--disabled { opacity: .62; }.selection-copy { flex: 1; min-width: 0; font-size: 24rpx; color: #8590a2; }.selection-copy text { display: block; line-height: 1.5; }.selection-copy .selection-name { color: #263244; font-size: 27rpx; }.selection-copy .imei { font-family: monospace; overflow-wrap: anywhere; }.selection-copy .line-warning { color: #b9781c; margin-top: 8rpx; }.selection-money { flex-shrink: 0; font-size: 26rpx; }
.panel-total { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 16rpx; padding: 20rpx 0; font-size: 27rpx; font-weight: 600; }.panel-link { padding: 20rpx 0; color: var(--primary-color); font-size: 25rpx; }
.panel-textarea { width: 100%; height: 160rpx; padding: 18rpx; background: #f5f7fa; font-size: 26rpx; line-height: 1.5; box-sizing: border-box; }.physical-confirm { display: flex; align-items: flex-start; gap: 14rpx; padding: 18rpx 0 32rpx; font-size: 26rpx; line-height: 1.5; }.physical-confirm text { flex: 1; }
.panel-foot { flex-shrink: 0; display: flex; gap: 18rpx; padding: 20rpx 26rpx calc(20rpx + env(safe-area-inset-bottom)); border-top: 1rpx solid #edf0f4; }
.delivery-modes { display: flex; gap: 18rpx; margin-bottom: 18rpx; }.delivery-modes view { padding: 14rpx 22rpx; border: 1rpx solid #dce2ec; border-radius: 10rpx; font-size: 26rpx; }.delivery-modes .active { color: var(--primary-color); border-color: var(--primary-color); }.field-select { display: flex; justify-content: space-between; padding: 22rpx; background: #f5f7fa; font-size: 26rpx; margin-bottom: 16rpx; }.field-input { display: flex; gap: 16rpx; align-items: center; padding: 18rpx; background: #f5f7fa; }.field-input input { flex: 1; min-width: 0; font-size: 26rpx; }
</style>
