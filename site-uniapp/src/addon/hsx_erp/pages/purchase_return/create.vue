<template>
    <view class="erp-page">
        <ErpPageHeader title="采购退货" />
        <scroll-view scroll-y style="height:calc(100vh - 180rpx)">
            <view style="padding-bottom:120rpx">
                <view class="return-workflow">
                    <view class="return-workflow__title">业务管理员确认退机</view>
                    <text class="return-workflow__tip">请在机器已经实际交还供货方后操作。系统自动作废未付款或生成退款应收；财务只负责后续确认到账。</text>
                </view>

                <!-- 原采购单信息 -->
                <view class="form-card" style="margin-top:24rpx">
                    <view class="form-card-title">退货来源</view>
                    <view class="field">
                        <text class="label"><text class="req">*</text>原采购单</text>
                        <text class="value" :class="purchaseNo ? '' : 'placeholder'">
                            {{ purchaseNo || '未选择' }}
                        </text>
                    </view>
                    <view class="field field--last">
                        <text class="label">供货方</text>
                        <text class="value">{{ partyName || '-' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">原采购员</text>
                        <text class="value">{{ purchaserName || '-' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">采购时间</text>
                        <text class="value">{{ formatDateTime(purchaseAt) }}</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">退货操作人</text>
                        <text class="value">{{ operatorName }}</text>
                    </view>
                </view>

                <!-- 退货设备选择 -->
                <view class="section-head">
                    <text class="section-title">1. 核对要退给供货方的设备</text>

                </view>
                <view v-if="loadingAssets" class="loading-center">
                    <u-loading-icon size="28" />
                </view>
                <view v-else-if="loadError" class="load-error-card">
                    <u-icon name="info-circle" color="#dc2626" size="20" />
                    <text>{{ loadError }}</text>
                    <u-button size="small" plain type="primary" text="重新加载" @click="loadAssets" />
                </view>
                <template v-else>
                    <u-checkbox-group v-model="selectedAssetIds" placement="column" @change="onAssetSelectionChange">
                        <view
                            v-for="item in availableAssets"
                            :key="item.id"
                            class="erp-card"
                            :class="{ 'device-selected': isSelected(item.id), 'device-blocked': item.return_flow?.returnable === false }"
                            @click="toggleSelect(item)"
                        >
                        <view class="erp-card__head">
                            <view class="device-check-row">
                                <u-checkbox
                                    :name="Number(item.id)"
                                    :disabled="item.return_flow?.returnable === false"
                                    @click.stop
                                />
                                <text class="card-title">{{ item.model }}</text>
                            </view>
                            <u-tag :text="item.return_flow?.returnable === false ? '不可退货' : '可以退货'" :type="item.return_flow?.returnable === false ? 'info' : 'success'" plain plainFill size="mini" />
                        </view>
                        <text class="card-meta">{{ deviceIdentityLine(item) }}</text>
                        <text class="card-meta">采购结算本金 ¥{{ money(item.payable_amount) }} · 当前总成本 ¥{{ money(item.total_cost) }}</text>
                        <text v-if="Number(item.refurbish_cost)" class="card-meta">其中整备费用 ¥{{ money(item.refurbish_cost) }}</text>
                        <text class="card-meta">已付款 ¥{{ money(item.paid_amount) }} · 未付款 ¥{{ money(item.unpaid_amount) }}</text>
                        <view class="item-flow-tip" :class="{ 'item-flow-tip--refund': item.return_flow?.requires_refund }">{{ item.return_flow?.description || '-' }}</view>
                        <!-- 已选时展示退货价和原因输入 -->
                        <template v-if="isSelected(item.id)">
                            <view class="return-inputs">
                                <view v-if="Number(item.paid_amount) > 0" class="field" style="border-top:1rpx solid #f3f4f6;margin-top:12rpx">
                                    <text class="label">协商退回金额</text>
                                    <u-input
                                        v-model="getSelectedItem(item.id).return_cost"
                                        type="number"
                                        :placeholder="money(item.return_flow?.default_return_amount || item.payable_amount)"
                                        :customStyle="{ textAlign: 'right', flex: '1' }"
                                        @click.stop
                                    />
                                </view>
                                <text v-if="Number(item.paid_amount) > 0" class="return-amount-help">默认按采购结算本金退回；如供货方少退，请填写双方确认的实际退款金额。</text>
                                <view v-else class="unpaid-direct-tip">该设备未形成结算，确认后直接作废设备应付，不填写退款金额。</view>
                                <view class="field field--last">
                                    <text class="label">退货原因</text>
                                    <u-input
                                        v-model="getSelectedItem(item.id).reason"
                                        placeholder="可选"
                                        :customStyle="{ textAlign: 'right', flex: '1' }"
                                        @click.stop
                                    />
                                </view>
                            </view>
                        </template>
                        </view>
                    </u-checkbox-group>
                    <view v-if="!availableAssets.length && !loadingAssets" class="empty-tip">
                        该采购单暂无可退货的在库设备
                    </view>
                </template>

                <!-- 退货财务分流由后端统一判断 -->
                <view class="form-card" v-if="selectedItems.length">
                    <view class="form-card-title">2. 系统处理结果</view>
                    <view class="return-policy" :class="{ 'return-policy--refund': selectedRequiresRefund }">
                        <text class="return-policy__title">{{ selectedRequiresRefund ? '退货后等待财务收款' : '退货后无需财务处理' }}</text>
                        <text>{{ returnPolicyText }}</text>
                    </view>
                    <view class="return-result-grid">
                        <view class="return-result-item">
                            <text>库存</text>
                            <strong>{{ selectedItems.length }} 台退出库存</strong>
                        </view>
                        <view class="return-result-item">
                            <text>未付款部分</text>
                            <strong>冲销应付 ¥{{ money(selectedOffsetTotal) }}</strong>
                        </view>
                        <view class="return-result-item" :class="{ warning: selectedRequiresRefund }">
                            <text>已付款部分</text>
                            <strong>{{ selectedRequiresRefund ? `生成退款应收 ¥${money(selectedRefundTotal)}` : '无需财务处理' }}</strong>
                        </view>
                    </view>
                    <template v-if="selectedRequiresRefund">
                        <view class="settlement-choice">
                            <text class="settlement-choice__label">退款怎么处理</text>
                            <view class="settlement-tabs">
                                <view class="settlement-tab" :class="{ active: form.refund_mode === 'cash' }" @click="form.refund_mode = 'cash'">当场收款</view>
                                <view class="settlement-tab" :class="{ active: form.refund_mode === 'receivable' }" @click="form.refund_mode = 'receivable'">记账待收</view>
                            </view>
                            <text class="settlement-choice__tip">{{ form.refund_mode === 'cash' ? '供货方已退款，选择实际到账账户后直接完成。' : '供货方暂未退款，生成应收交财务后续收款或折账。' }}</text>
                        </view>
                        <view v-if="form.refund_mode === 'cash'" class="field" @click="showAccountPicker = true">
                            <text class="label"><text class="req">*</text>到账账户</text>
                            <text class="value" :class="form.capital_account_id ? '' : 'placeholder'">{{ selectedAccountName || '请选择实际到账账户' }}</text>
                            <u-icon name="arrow-right" color="#94a3b8" size="16" />
                        </view>
                        <view v-if="form.refund_mode === 'cash'" class="voucher-wrap">
                            <ErpVoucherUploader v-model="form.voucher_urls" title="收款凭证（选填）" />
                        </view>
                    </template>
                    <view class="field field--last">
                        <text class="label">备注</text>
                        <u-input
                            v-model="form.remark"
                            placeholder="可选备注"
                            :customStyle="{ textAlign: 'right', flex: '1' }"
                        />
                    </view>
                </view>

                <!-- 最终确认 -->
                <view class="form-card" v-if="selectedItems.length">
                    <view class="form-card-title">3. 确认已经退机</view>
                    <view class="field">
                        <text class="label">已选设备</text>
                        <text class="value" style="color:#3b6ef5">{{ selectedItems.length }} 台</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">退款应收</text>
                        <text class="value" style="color:#ea580c;font-weight:600">¥{{ money(selectedRefundTotal) }}</text>
                    </view>
                    <view class="handover-check" @click="toggleHandoverConfirmed">
                        <u-checkbox-group v-model="handoverSelection" @change="onHandoverSelectionChange">
                            <u-checkbox name="handover" @click.stop />
                        </u-checkbox-group>
                        <text>我已核对设备，并确认机器已经交还供货方</text>
                    </view>
                    <text class="handover-check__tip">确认后设备立即退出库存，不能普通撤销。</text>
                </view>

            </view>
        </scroll-view>

        <!-- 底部提交 -->
        <view class="float-bar">
            <u-button @click="goBack" :customStyle="{flex:'1'}">取消</u-button>
            <u-button
                type="primary"
                :loading="submitting"
                :disabled="!canSubmit"
                @click="submit"
                :customStyle="{flex:'2'}"
            >{{ submitButtonText }}</u-button>
        </view>

        <u-picker
            :show="showAccountPicker"
            :columns="[accountColumns]"
            keyName="label"
            @confirm="onAccountConfirm"
            @cancel="showAccountPicker = false"
            @close="showAccountPicker = false"
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createErpPurchaseReturn, getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import { scanErpCode } from '@/addon/hsx_erp/hooks/useErpScan'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import request from '@/utils/request'
import useUserStore from '@/stores/user'
import { erpDeviceIdentityLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'
import { formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'

const purchaseOrderId = ref(0)
const purchaseNo = ref('')
const partyName = ref('')
const purchaserName = ref('')
const purchaseAt = ref(0)
const availableAssets = ref<any[]>([])
const loadingAssets = ref(false)
const loadError = ref('')
const submitting = ref(false)
const goBack = () => uni.navigateBack()
const selectedItems = ref<any[]>([])
const selectedAssetIds = ref<number[]>([])
const deviceIdentityLine = (row: any) => erpDeviceIdentityLine(row)
const targetAssetId = ref(0)
const handoverConfirmed = ref(false)
const handoverSelection = ref<string[]>([])
const userStore = useUserStore()
const operatorName = computed(() => {
    const user: any = userStore.userInfo || {}
    return user.real_name || user.name || user.username || user.nickname || '当前登录管理员'
})

const form = ref({ refund_mode: 'receivable', capital_account_id: 0, voucher_urls: '', remark: '' })
const capitalAccounts = ref<any[]>([])
const showAccountPicker = ref(false)
const accountColumns = computed(() => capitalAccounts.value.map(item => ({
    ...item,
    label: `${item.account_name}${Number(item.is_default) === 1 ? '（默认）' : ''}`,
})))
const selectedAccountName = computed(() => capitalAccounts.value.find(item => Number(item.id) === Number(form.value.capital_account_id))?.account_name || '')

async function loadCapitalAccounts() {
    try {
        const res: any = await getMobileCapitalAccounts()
        capitalAccounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
        const preferred = capitalAccounts.value.find(item => Number(item.is_default) === 1) || capitalAccounts.value[0]
        if (!form.value.capital_account_id && preferred) form.value.capital_account_id = Number(preferred.id)
    } catch {
        capitalAccounts.value = []
    }
}

function onAccountConfirm(event: any) {
    const selected = event?.value?.[0]
    if (selected?.id) form.value.capital_account_id = Number(selected.id)
    showAccountPicker.value = false
}

function onHandoverSelectionChange(values: Array<string | number>) {
    handoverConfirmed.value = (values || []).includes('handover')
}

function toggleHandoverConfirmed() {
    handoverConfirmed.value = !handoverConfirmed.value
    handoverSelection.value = handoverConfirmed.value ? ['handover'] : []
}

onLoad(async (query: any) => {
    purchaseOrderId.value = Number(query?.purchase_order_id || 0)
    targetAssetId.value = Number(query?.asset_id || 0)
    purchaseNo.value = safeRepeatedDecode(query?.purchase_no || '')
    partyName.value = safeRepeatedDecode(query?.party_name || '')
    await Promise.all([purchaseOrderId.value ? loadAssets() : Promise.resolve(), loadCapitalAccounts()])
})

async function loadAssets() {
    loadingAssets.value = true
    loadError.value = ''
    try {
        const res: any = await request.get(`erp/purchase/${purchaseOrderId.value}`)
        const data = res?.data || {}
        if (!data?.id) throw new Error('采购单不存在或已失效，请返回采购管理重新选择')
        purchaseNo.value = String(data.purchase_no || purchaseNo.value)
        partyName.value = String(data.party_name || partyName.value)
        purchaserName.value = String(data.purchaser_name || '')
        purchaseAt.value = Number(data.purchase_at || data.create_at || 0)
        // 只取 in_stock 的设备（从 items 里找 asset_id，再过滤 status）
        availableAssets.value = (data.items || []).filter((i: any) =>
            i.status === 'in_stock' || i.asset_status === 'in_stock'
        ).map((i: any) => ({
            id: i.asset_id || i.id,
            asset_id: i.asset_id || i.id,
            model: i.model,
            imei: i.imei,
            sn: i.sn,
            asset_no: i.asset_no,
            spec: i.spec,
            total_cost: Number(i.total_cost || i.purchase_cost || 0),
            purchase_cost: Number(i.purchase_cost || 0),
            adjust_cost: Number(i.adjust_cost || 0),
            refurbish_cost: Number(i.refurbish_cost || 0),
            refurbish_status: i.refurbish_status || 'none',
            payable_amount: Number(i.payable_amount || i.purchase_cost || 0),
            paid_amount: Number(i.paid_amount || 0),
            unpaid_amount: Number(i.unpaid_amount || 0),
            return_flow: i.return_flow || null,
            warehouse_name: i.warehouse_name || data.warehouse_name || '',
            purchase_item_id: i.id,
        }))
        const target = availableAssets.value.find((item: any) => Number(item.id) === targetAssetId.value)
        if (target && !isSelected(target.id)) toggleSelect(target)
        if (targetAssetId.value > 0 && !target) {
            uni.showToast({ title: '目标设备已不可退，请重新选择在库设备', icon: 'none' })
        }
    } catch (e: any) {
        availableAssets.value = []
        selectedItems.value = []
        loadError.value = e?.message || e?.msg || '采购单加载失败，请返回采购管理重新选择'
    } finally { loadingAssets.value = false }
}

const isSelected = (id: number) => selectedItems.value.some(s => s.asset_id === id)
const getSelectedItem = (id: number) => selectedItems.value.find(s => s.asset_id === id)!

function toggleSelect(item: any) {
    if (item.return_flow?.returnable === false) {
        uni.showToast({ title: item.return_flow?.block_reason || '该设备不能走采购退货', icon: 'none', duration: 2600 })
        return
    }
    const idx = selectedItems.value.findIndex(s => s.asset_id === item.id)
    if (idx >= 0) {
        selectedItems.value.splice(idx, 1)
        selectedAssetIds.value = selectedAssetIds.value.filter(id => Number(id) !== Number(item.id))
    } else {
        selectedItems.value.push({
            asset_id: item.id,
            model: item.model,
            imei: item.imei,
            asset_no: item.asset_no,
            return_cost: Number(item.return_flow?.default_return_amount || item.payable_amount || item.purchase_cost || 0),
            payable_amount: Number(item.payable_amount || item.purchase_cost || 0),
            reason: '',
            paid_amount: Number(item.paid_amount || 0),
            unpaid_amount: Number(item.unpaid_amount || 0),
            return_flow: item.return_flow || null,
        })
        selectedAssetIds.value = [...selectedAssetIds.value, Number(item.id)]
    }
    handoverConfirmed.value = false
    handoverSelection.value = []
}

function onAssetSelectionChange(values: Array<string | number>) {
    const selected = new Set((values || []).map(Number))
    const existing = new Set(selectedItems.value.map(item => Number(item.asset_id)))
    availableAssets.value.forEach(item => {
        const id = Number(item.id)
        if (selected.has(id) && !existing.has(id)) addSelectedItem(item)
    })
    selectedItems.value = selectedItems.value.filter(item => selected.has(Number(item.asset_id)))
    selectedAssetIds.value = Array.from(selected)
    handoverConfirmed.value = false
    handoverSelection.value = []
}

function addSelectedItem(item: any) {
    if (item.return_flow?.returnable === false || isSelected(Number(item.id))) return
    selectedItems.value.push({
        asset_id: Number(item.id),
        model: item.model,
        imei: item.imei,
        asset_no: item.asset_no,
        return_cost: Number(item.return_flow?.default_return_amount || item.payable_amount || item.purchase_cost || 0),
        payable_amount: Number(item.payable_amount || item.purchase_cost || 0),
        reason: '',
        paid_amount: Number(item.paid_amount || 0),
        unpaid_amount: Number(item.unpaid_amount || 0),
        return_flow: item.return_flow || null,
    })
}

async function scanSelectAsset() {
    try {
        const code = await scanErpCode()
        const item = availableAssets.value.find(i => scanMatch(i, code))
        if (!item) {
            uni.showToast({ title: '当前采购单未找到该设备', icon: 'none' })
            return
        }
        if (!isSelected(item.id)) toggleSelect(item)
        uni.showToast({ title: '已选中设备', icon: 'success' })
    } catch (e: any) {
        if (e?.errMsg?.includes('cancel')) return
        uni.showToast({ title: e?.message || '扫码失败', icon: 'none' })
    }
}

const scanMatch = (item: any, code: string) => ['imei', 'sn', 'asset_no'].some(key => String(item?.[key] || '').trim() === code)

const totalReturn = computed(() => selectedItems.value.reduce((s, i) => s + Number(i.return_cost || 0), 0))
const selectedFlows = computed(() => selectedItems.value.map(item => calculateReturnFlow(item)))
const selectedOffsetTotal = computed(() => selectedFlows.value.reduce((sum, flow) => sum + flow.offset_amount, 0))
const selectedRefundTotal = computed(() => selectedFlows.value.reduce((sum, flow) => sum + flow.refund_amount, 0))
const selectedRequiresRefund = computed(() => selectedRefundTotal.value > 0.0001)
const submitButtonText = computed(() => selectedRequiresRefund.value
    ? (form.value.refund_mode === 'cash'
        ? `确认退货并收款 ¥${money(selectedRefundTotal.value)}`
        : `确认退货并记账 ¥${money(selectedRefundTotal.value)}`)
    : '确认退货并冲销应付')
const returnPolicyText = computed(() => selectedRequiresRefund.value
    ? `冲销未付款 ¥${money(selectedOffsetTotal.value)}，并生成供货方退款应收 ¥${money(selectedRefundTotal.value)}。`
    : `所选设备未形成需要追回的款项，确认后直接作废设备应付，不生成退款应收。`)
const canSubmit = computed(() => selectedItems.value.length > 0 && purchaseOrderId.value > 0 && selectedItems.value.every(item => {
    const amount = Number(item.return_cost || 0)
    return amount > 0 && amount <= Number(item.payable_amount || 0) + 0.0001
}) && handoverConfirmed.value
    && (!selectedRequiresRefund.value || form.value.refund_mode !== 'cash' || form.value.capital_account_id > 0))

function calculateReturnFlow(item: any) {
    const supplierAmount = Math.max(0, Number(item.payable_amount || 0))
    const paidAmount = Math.max(0, Number(item.paid_amount || 0))
    const unpaidAmount = Math.max(0, supplierAmount - paidAmount)
    const returnAmount = paidAmount <= 0.0001
        ? supplierAmount
        : Math.max(0, Number(item.return_cost || 0))
    const offsetAmount = Math.min(unpaidAmount, returnAmount)
    return {
        offset_amount: offsetAmount,
        refund_amount: Math.max(0, returnAmount - offsetAmount),
    }
}

async function submit() {
    if (!canSubmit.value || submitting.value) return
    submitting.value = true
    const confirmed = await confirmSubmit()
    if (!confirmed) {
        submitting.value = false
        return
    }
    try {
        const res: any = await createErpPurchaseReturn({
            purchase_order_id: purchaseOrderId.value,
            refund_mode: selectedRequiresRefund.value ? form.value.refund_mode : 'none',
            capital_account_id: form.value.refund_mode === 'cash' ? form.value.capital_account_id : 0,
            voucher_urls: form.value.refund_mode === 'cash' ? form.value.voucher_urls : '',
            remark: form.value.remark,
            items: selectedItems.value.map(i => ({
                asset_id: i.asset_id,
                return_cost: Number(i.return_cost),
                reason: i.reason || '',
            }))
        })
        showSubmitResult(res?.data || {})
    } catch (e: any) {
        uni.showToast({ title: e?.message || '提交失败', icon: 'none' })
    } finally { submitting.value = false }
}

function confirmSubmit(): Promise<boolean> {
    const devices = selectedItems.value.map(item => item.imei || item.asset_no || item.model || '-').join('、')
    const content = selectedRequiresRefund.value
        ? (form.value.refund_mode === 'cash'
            ? `设备：${devices}\n确认后立即退出库存。退款 ¥${money(selectedRefundTotal.value)} 已当场到账，将记入“${selectedAccountName.value || '-'}”。`
            : `设备：${devices}\n确认后立即退出库存。供货方暂欠 ¥${money(selectedRefundTotal.value)}，将生成退款应收。`)
        : `设备：${devices}\n确认后立即退出库存，对应设备应付作废，无需财务退款。`
    return new Promise(resolve => {
        uni.showModal({
            title: '确认设备已经交还供货方',
            content,
            confirmText: selectedRequiresRefund.value ? (form.value.refund_mode === 'cash' ? '确认退货并收款' : '确认退货并记账') : '确认退货冲销应付',
            cancelText: '再检查一下',
            success: result => resolve(!!result.confirm),
            fail: () => resolve(false),
        })
    })
}

function showSubmitResult(result: any) {
    const receivable = result?.refund_receivable || null
    const returnNo = String(result?.return_no || '')
    if (!selectedRequiresRefund.value) {
        uni.showModal({
            title: '直接退货已完成',
            content: `设备已退出库存，对应设备应付已作废。${returnNo ? `\n退货单：${returnNo}` : ''}\n本次无需财务退款处理。`,
            showCancel: false,
            confirmText: '返回采购单',
            success: () => uni.navigateBack(),
        })
        return
    }
    const refundAmount = Number(receivable?.amount || selectedRefundTotal.value)
    if (form.value.refund_mode === 'cash') {
        uni.showModal({
            title: '退货及收款已完成',
            content: `设备已退出库存，退款 ¥${money(refundAmount)} 已进入“${selectedAccountName.value || '所选账户'}”。${returnNo ? `\n退货单：${returnNo}` : ''}\n本次无需财务再次处理。`,
            showCancel: false,
            confirmText: '完成',
            success: () => uni.navigateBack(),
        })
        return
    }
    uni.showModal({
        title: '已退机，等待供货方退款',
        content: `设备已退出库存，已生成退款应收 ¥${money(refundAmount)}。${returnNo ? `\n退货单：${returnNo}` : ''}\n下一步：到“应收款”确认实际到账。`,
        confirmText: '去确认退款',
        cancelText: '稍后处理',
        success: modal => {
            if (modal.confirm) {
                const receivableId = Number(receivable?.id || 0)
                const url = receivableId > 0
                    ? `/addon/hsx_erp/pages/receivable/detail?id=${receivableId}&action=receipt`
                    : `/addon/hsx_erp/pages/receivable/list?keyword=${encodeURIComponent(returnNo)}`
                uni.redirectTo({ url })
            } else {
                uni.navigateBack()
            }
        },
    })
}

const money = (v: any) => Number(v || 0).toFixed(2)
const formatDateTime = (ts: any) => {
    return formatErpTime(ts)
}

function safeRepeatedDecode(value: any) {
    let result = String(value || '')
    for (let i = 0; i < 2; i += 1) {
        try {
            const decoded = decodeURIComponent(result)
            if (decoded === result) break
            result = decoded
        } catch { break }
    }
    return result
}
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.device-selected { border: 2rpx solid var(--primary-color, #3b6ef5); background: #f0f5ff; }
.device-blocked { border-color:#e2e8f0; background:#f8fafc; opacity:.78; }
.section-head { display:flex; align-items:center; justify-content:space-between; padding:16rpx 28rpx 8rpx; }
.section-head .section-title { padding:0; }
.device-check-row { display: flex; align-items: center; gap: 12rpx; }
.return-inputs { padding: 0; }
.return-workflow { margin:24rpx 24rpx 0; padding:22rpx; border-radius:20rpx; background:linear-gradient(135deg,#eff6ff,#fff7ed); }
.return-workflow__title { color:#0f172a; font-size:26rpx; font-weight:700; }
.return-workflow__steps { display:flex; align-items:flex-start; margin-top:18rpx; }
.workflow-step { display:flex; flex:0 0 auto; flex-direction:column; align-items:center; gap:6rpx; color:#94a3b8; font-size:19rpx; }
.workflow-step text { display:flex; width:36rpx; height:36rpx; align-items:center; justify-content:center; border-radius:50%; background:#e2e8f0; color:#64748b; font-weight:700; }
.workflow-step.active,.workflow-step.done { color:#2563eb; }
.workflow-step.active text,.workflow-step.done text { background:#2563eb; color:#fff; }
.workflow-line { flex:1; height:2rpx; margin:18rpx 10rpx 0; background:#cbd5e1; }
.return-workflow__tip { display:block; margin-top:18rpx; color:#475569; font-size:21rpx; line-height:1.55; }
.return-amount-help { display:block; padding:0 0 12rpx; color:#94a3b8; font-size:20rpx; line-height:1.45; }
.unpaid-direct-tip { margin:12rpx 0; padding:14rpx 16rpx; border-radius:12rpx; background:#f0fdf4; color:#166534; font-size:21rpx; line-height:1.5; }
.item-flow-tip { margin-top:12rpx; padding:12rpx 14rpx; border-radius:12rpx; background:#f8fafc; color:#64748b; font-size:22rpx; line-height:1.45; }
.item-flow-tip--refund { background:#fff7ed; color:#c2410c; }
.return-policy { display:flex; flex-direction:column; gap:8rpx; margin:0 0 8rpx; padding:18rpx; border-radius:14rpx; background:#f0fdf4; color:#166534; font-size:23rpx; line-height:1.5; }
.return-policy--refund { background:#fff7ed; color:#c2410c; }
.return-policy__title { color:#0f172a; font-weight:650; }
.return-result-grid { display:grid; grid-template-columns:1fr; gap:10rpx; margin:14rpx 0; }
.return-result-item { display:flex; align-items:center; justify-content:space-between; gap:16rpx; padding:13rpx 16rpx; border-radius:12rpx; background:#f8fafc; font-size:21rpx; }
.return-result-item text { color:#64748b; }
.return-result-item strong { color:#166534; font-weight:650; text-align:right; }
.return-result-item.warning strong { color:#c2410c; }
.settlement-choice { margin:14rpx 0 4rpx; padding:18rpx; border:1rpx solid #dbeafe; border-radius:14rpx; background:#f8fbff; }
.settlement-choice__label { display:block; color:#0f172a; font-size:23rpx; font-weight:650; }
.settlement-tabs { display:grid; grid-template-columns:1fr 1fr; gap:10rpx; margin-top:14rpx; }
.settlement-tab { padding:15rpx 12rpx; border:1rpx solid #cbd5e1; border-radius:12rpx; background:#fff; color:#64748b; font-size:22rpx; text-align:center; }
.settlement-tab.active { border-color:#3b6ef5; background:#eff6ff; color:#2563eb; font-weight:650; }
.settlement-choice__tip { display:block; margin-top:12rpx; color:#64748b; font-size:20rpx; line-height:1.5; }
.voucher-wrap { padding:16rpx 0 6rpx; }
.loading-center { display: flex; justify-content: center; padding: 48rpx; }
.load-error-card { display:flex; flex-direction:column; align-items:center; gap:14rpx; margin:16rpx 24rpx; padding:28rpx; border:1rpx solid #fecaca; border-radius:18rpx; background:#fff7f7; color:#b91c1c; font-size:22rpx; line-height:1.5; text-align:center; }
.empty-tip { text-align: center; color: #94a3b8; font-size: 26rpx; padding: 48rpx 0; }
.handover-check { display:flex; align-items:flex-start; gap:12rpx; margin-top:18rpx; padding:18rpx; border:1rpx solid #fed7aa; border-radius:14rpx; background:#fffaf5; color:#7c2d12; font-size:23rpx; line-height:1.5; }
.handover-check text { flex:1; }
.handover-check__tip { display:block; margin-top:8rpx; color:#9a3412; font-size:20rpx; }
</style>
