<template>
    <u-popup :show="show" mode="bottom" round="22" :closeOnClickOverlay="!submitting" @close="close">
        <view class="process-sheet" :style="themeColor()">
            <view class="sheet-head">
                <view class="head-copy">
                    <text class="sheet-title">{{ title }}</text>
                    <text class="sheet-subtitle">{{ subtitle }}</text>
                </view>
                <view class="close-button" @click="close"><u-icon name="close" color="#64748b" size="19" /></view>
            </view>

            <scroll-view scroll-y class="sheet-body">
                <view class="order-summary">
                    <view class="summary-row">
                        <view class="customer-copy">
                            <text class="customer-name">{{ customerName }}</text>
                            <text class="customer-mobile">{{ order?.taker_mobile || order?.member?.mobile || '未留手机号' }}</text>
                        </view>
                        <text class="order-amount">¥{{ money(order?.order_money) }}</text>
                    </view>
                    <text class="order-no">订单 {{ order?.order_no || '-' }}</text>
                </view>

                <template v-if="isSettlement">
                    <view class="form-card">
                        <view class="field-head">
                            <text class="field-title">{{ goodsCount > 1 ? '一口打包成交价' : '本次实际成交价' }}</text>
                            <text class="required-tag">必填</text>
                        </view>
                        <view class="money-input">
                            <text class="currency">¥</text>
                            <input v-model="form.deal_total" type="digit" class="amount-input" placeholder="0.00" />
                        </view>
                        <view v-if="priceDiff !== 0" class="price-change" :class="priceDiff < 0 ? 'price-change--discount' : 'price-change--increase'">
                            {{ priceDiff < 0 ? '本次优惠' : '本次加价' }} ¥{{ Math.abs(priceDiff).toFixed(2) }}
                        </view>
                        <text class="field-hint" v-if="goodsCount > 1">共 {{ goodsCount }} 件商品，系统按原成交金额比例分摊；分摊价将作为后续退款上限。</text>
                        <text class="field-hint" v-else>议价后的成交价会同步到订单及 ERP，并作为后续退款上限。</text>
                    </view>

                    <view v-if="action === 'confirm_paid'" class="form-card">
                        <view class="select-row" @click="accountSheet = true">
                            <view class="select-copy">
                                <text class="field-title">实际到账账户</text>
                                <text class="select-value" :class="{ muted: !selectedAccount }">{{ selectedAccount?.name || '请选择 ERP 资金账户' }}</text>
                            </view>
                            <u-icon name="arrow-right" color="#94a3b8" size="16" />
                        </view>
                        <view v-if="!accounts.length" class="account-warning">
                            <u-icon name="info-circle" color="#d97706" size="16" />
                            <text>暂无可用账户，请先在 ERP 中启用资金账户。</text>
                        </view>
                        <view class="voucher-wrap">
                            <view class="field-head">
                                <text class="field-title">收款凭证</text>
                                <text class="required-tag">必填</text>
                            </view>
                            <upload-img v-model="form.voucher_urls" :max-count="6" :multiple="true" />
                            <text class="field-hint">可直接拍照或上传转账截图，作为真实收款留痕。</text>
                        </view>
                    </view>
                </template>

                <view class="form-card">
                    <view class="field-head">
                        <text class="field-title">{{ action === 'close_unreachable' ? '关闭原因' : '处理备注' }}</text>
                        <text class="optional-tag">{{ action === 'close_unreachable' ? '必填' : '选填' }}</text>
                    </view>
                    <textarea
                        v-model="form.remark"
                        class="remark-input"
                        maxlength="255"
                        :placeholder="action === 'close_unreachable' ? '例如：多次联系无人接听或客户主动取消' : '可填写收款方式、议价或沟通结果'"
                    />
                </view>
                <view class="safe-space" />
            </scroll-view>

            <view class="sheet-foot">
                <view class="submit-button">
                    <u-button :type="action === 'close_unreachable' ? 'error' : 'primary'" shape="circle" :loading="submitting" :text="submitText" @click="submit" />
                </view>
            </view>
        </view>
    </u-popup>

    <u-action-sheet
        :show="accountSheet"
        title="选择实际到账账户"
        :actions="accountActions"
        cancelText="取消"
        @close="accountSheet = false"
        @select="selectAccount"
    />
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import UploadImg from '@/components/upload-img/upload-img.vue'
import { getOfflineCapitalAccounts, processOfflineOrder } from '@/addon/phone_shop/api/order'

const props = defineProps<{
    show: boolean
    order?: Record<string, any> | null
    action: 'confirm_paid' | 'confirm_credit' | 'close_unreachable'
}>()

const emit = defineEmits(['close', 'success'])
const submitting = ref(false)
const accounts = ref<any[]>([])
const accountSheet = ref(false)
const form = reactive({ deal_total: '', capital_account_id: 0, voucher_urls: '', remark: '' })

const isSettlement = computed(() => ['confirm_paid', 'confirm_credit'].includes(props.action))
const customerName = computed(() => props.order?.member?.nickname || props.order?.taker_name || '到店客户')
const goodsCount = computed(() => (props.order?.order_goods || []).filter((item: any) => Number(item.is_gift || 0) !== 1).reduce((total: number, item: any) => total + Number(item.num || 1), 0))
const priceDiff = computed(() => Number(form.deal_total || 0) - Number(props.order?.order_money || 0))
const selectedAccount = computed(() => accounts.value.find(item => Number(item.id) === Number(form.capital_account_id)))
const accountActions = computed(() => accounts.value.map(item => ({ ...item, name: `${ item.name || '资金账户' }${ item.type_name ? ` · ${ item.type_name }` : '' }` })))
const title = computed(() => props.action === 'confirm_paid' ? '确认线下收款' : props.action === 'confirm_credit' ? '确认客户挂账' : '关闭线下订单')
const subtitle = computed(() => props.action === 'confirm_paid' ? '记录真实到账后进入待交付' : props.action === 'confirm_credit' ? '生成 ERP 应收后进入待交付' : '关闭后设备会解除锁定')
const submitText = computed(() => props.action === 'confirm_paid' ? '确认已收款' : props.action === 'confirm_credit' ? '确认挂账' : '确认关闭订单')

watch(() => props.show, async visible => {
    if (!visible || !props.order) return
    form.deal_total = Number(props.order.order_money || 0).toFixed(2)
    form.capital_account_id = 0
    form.voucher_urls = ''
    form.remark = ''
    if (props.action !== 'confirm_paid') return
    try {
        const response: any = await getOfflineCapitalAccounts()
        accounts.value = Array.isArray(response?.data) ? response.data : []
        const defaultAccount = accounts.value.find(item => Number(item.is_default) === 1) || accounts.value[0]
        form.capital_account_id = Number(defaultAccount?.id || 0)
    } catch {
        accounts.value = []
    }
})

function money(value: any) { return Number(value || 0).toFixed(2) }
function close() { if (!submitting.value) emit('close') }
function selectAccount(item: any) { form.capital_account_id = Number(item?.id || 0); accountSheet.value = false }

async function submit() {
    if (!props.order || submitting.value) return
    if (isSettlement.value && Number(form.deal_total) <= 0) return uni.showToast({ title: '请输入有效成交价', icon: 'none' })
    if (props.action === 'confirm_paid' && !form.capital_account_id) return uni.showToast({ title: '请选择实际到账账户', icon: 'none' })
    const vouchers = String(form.voucher_urls || '').split(',').map(item => item.trim()).filter(Boolean)
    if (props.action === 'confirm_paid' && !vouchers.length) return uni.showToast({ title: '请上传至少一张收款凭证', icon: 'none' })
    if (props.action === 'close_unreachable' && !form.remark.trim()) return uni.showToast({ title: '请填写关闭原因', icon: 'none' })

    submitting.value = true
    try {
        await processOfflineOrder({
            order_id: Number(props.order.order_id),
            action: props.action,
            capital_account_id: form.capital_account_id,
            deal_total: isSettlement.value ? Number(form.deal_total) : null,
            remark: form.remark.trim(),
            close_reason: props.action === 'close_unreachable' ? form.remark.trim() : '',
            voucher_urls: vouchers,
        })
        emit('success')
    } finally {
        submitting.value = false
    }
}
</script>

<style scoped lang="scss">
.process-sheet { height: 84vh; background: #f5f7fa; display: flex; flex-direction: column; color: #1e293b; }
.sheet-head { flex-shrink: 0; padding: 28rpx 30rpx 22rpx; background: #fff; display: flex; align-items: flex-start; justify-content: space-between; }
.head-copy { min-width: 0; flex: 1; }
.sheet-title, .sheet-subtitle { display: block; }
.sheet-title { font-size: 32rpx; line-height: 1.35; font-weight: 700; }
.sheet-subtitle { margin-top: 6rpx; color: #94a3b8; font-size: 22rpx; }
.close-button { width: 56rpx; height: 56rpx; margin-left: 20rpx; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; }
.sheet-body { flex: 1; min-height: 0; padding: 20rpx 24rpx; box-sizing: border-box; }
.order-summary { padding: 22rpx; border-radius: 18rpx; background: linear-gradient(135deg, #eef5ff, #f7faff); border: 1rpx solid #dbeafe; }
.summary-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 18rpx; }
.customer-copy { min-width: 0; flex: 1; }
.customer-name, .customer-mobile, .order-no { display: block; }
.customer-name { font-size: 28rpx; font-weight: 700; }
.customer-mobile { margin-top: 5rpx; color: #64748b; font-size: 22rpx; }
.order-amount { flex-shrink: 0; color: var(--primary-color); font-size: 32rpx; font-weight: 750; }
.order-no { margin-top: 14rpx; padding-top: 14rpx; border-top: 1rpx solid #dbeafe; color: #94a3b8; font-size: 21rpx; }
.form-card { margin-top: 18rpx; padding: 22rpx; border-radius: 18rpx; background: #fff; }
.field-head { display: flex; align-items: center; justify-content: space-between; gap: 12rpx; }
.field-title { color: #334155; font-size: 26rpx; font-weight: 650; }
.required-tag, .optional-tag { padding: 5rpx 11rpx; border-radius: 14rpx; color: #2563eb; background: #eff6ff; font-size: 19rpx; }
.optional-tag { color: #94a3b8; background: #f8fafc; }
.money-input { height: 90rpx; margin-top: 16rpx; padding: 0 20rpx; border: 2rpx solid #dbe5f1; border-radius: 14rpx; display: flex; align-items: center; }
.currency { color: #475569; font-size: 30rpx; font-weight: 650; }
.amount-input { flex: 1; min-width: 0; margin-left: 10rpx; color: #0f172a; font-size: 38rpx; font-weight: 750; }
.field-hint { display: block; margin-top: 12rpx; color: #94a3b8; font-size: 20rpx; line-height: 1.55; }
.price-change { display: inline-flex; margin-top: 12rpx; padding: 6rpx 12rpx; border-radius: 14rpx; font-size: 20rpx; }
.price-change--discount { color: #15803d; background: #f0fdf4; }
.price-change--increase { color: #b45309; background: #fffbeb; }
.select-row { min-height: 82rpx; display: flex; align-items: center; justify-content: space-between; gap: 18rpx; }
.select-copy { min-width: 0; flex: 1; }
.select-value { display: block; margin-top: 8rpx; color: #1e293b; font-size: 25rpx; }
.select-value.muted { color: #94a3b8; }
.account-warning { margin-top: 12rpx; padding: 14rpx 16rpx; border-radius: 12rpx; background: #fffbeb; color: #b45309; display: flex; align-items: flex-start; gap: 9rpx; font-size: 20rpx; line-height: 1.5; }
.voucher-wrap { margin-top: 18rpx; padding-top: 20rpx; border-top: 1rpx solid #eef2f7; }
.remark-input { width: 100%; height: 150rpx; margin-top: 15rpx; padding: 18rpx; border-radius: 14rpx; background: #f7f9fc; color: #334155; font-size: 24rpx; line-height: 1.55; box-sizing: border-box; }
.safe-space { height: 28rpx; }
.sheet-foot { flex-shrink: 0; padding: 18rpx 26rpx calc(18rpx + env(safe-area-inset-bottom)); background: #fff; border-top: 1rpx solid #eef2f7; }
.submit-button { width: 100%; }
</style>
