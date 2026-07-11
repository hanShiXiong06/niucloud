<template>
    <view class="erp-page">
        <ErpPageHeader :title="isCompensation ? '售后补差' : '销售退货'" />
        <scroll-view scroll-y style="height:calc(100vh - 180rpx)">
            <view style="padding-bottom:120rpx">

                <!-- 原销售单信息 -->
                <view class="form-card" style="margin-top:24rpx">
                    <view class="form-card-title">{{ isCompensation ? '售后对象' : '销售单信息' }}</view>
                    <view class="field">
                        <text class="label">原销售单</text>
                        <text class="value" :class="saleNo ? '' : 'placeholder'">{{ saleNo || '未选择' }}</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">客户</text>
                        <text class="value">{{ partyName || '-' }}</text>
                    </view>
                </view>

                <!-- 退货设备 -->
                <view class="section-head">
                    <text class="section-title">{{ isCompensation ? '选择补差设备' : '选择退货设备' }}</text>
                    <!-- <u-button size="mini" plain type="primary" @click="scanSelectAsset">扫码定位</u-button> -->
                </view>
                <view v-if="loadingAssets" class="loading-center"><u-loading-icon size="28" /></view>
                <template v-else>
                    <u-checkbox-group v-model="selectedAssetIds" placement="column" @change="onAssetSelectionChange">
                        <view
                            v-for="item in availableAssets"
                            :key="item.id"
                            class="erp-card"
                            :class="{ 'device-selected': isSelected(item.id) }"
                            @click="toggleSelect(item)"
                        >
                        <view class="erp-card__head">
                            <view class="device-check-row">
                                <u-checkbox :name="Number(item.id)" @click.stop />
                                <text class="card-title">{{ item.model }}</text>
                            </view>
                            <u-tag text="已售" type="primary" plain plainFill size="mini" />
                        </view>
                        <text class="card-meta">{{ deviceIdentityLine(item) }}</text>
                        <text class="card-meta">售价 ¥{{ money(item.sale_price) }} · 成本 ¥{{ money(item.cost) }}</text>
                        <template v-if="isSelected(item.id)">
                            <view class="return-inputs">
                                <view class="field" style="border-top:1rpx solid #f3f4f6;margin-top:12rpx">
                                <text class="label">{{ isCompensation ? '补差金额' : '退货价' }}</text>
                                    <u-input v-model="getSelected(item.id).return_price" type="number" :placeholder="money(item.sale_price)" :customStyle="{ textAlign:'right', flex:'1' }" @click.stop />
                                </view>
                                <view class="field field--last">
                                <text class="label">{{ isCompensation ? '补差原因' : '退货原因' }}</text>
                                    <u-input v-model="getSelected(item.id).reason" placeholder="可选" :customStyle="{ textAlign:'right', flex:'1' }" @click.stop />
                                </view>
                            </view>
                        </template>
                        </view>
                    </u-checkbox-group>
                    <view v-if="!availableAssets.length && !loadingAssets" class="empty-tip">该销售单暂无可处理的设备</view>
                </template>

                <view class="form-card">
                    <view class="form-card-title">{{ isCompensation ? '补差付款设置' : '退款设置' }}</view>
                    <view class="refund-mode-grid">
                        <view
                            v-for="mode in refundModes"
                            :key="mode.value"
                            class="refund-mode-card"
                            :class="{ 'refund-mode-card--on': form.refund_mode === mode.value }"
                            @click="form.refund_mode = mode.value"
                        >
                            <view class="refund-mode-card__head">
                                <text>{{ mode.label }}</text>
                                <u-icon v-if="form.refund_mode === mode.value" name="checkmark-circle-fill" color="#3b6ef5" size="18" />
                                <view v-else class="choice-circle" />
                            </view>
                            <text class="refund-mode-card__desc">{{ mode.desc }}</text>
                        </view>
                    </view>
                    <view class="refund-policy" :class="{ 'refund-policy--finance': form.refund_mode === 'payable' }">
                        {{ refundPolicyText }}
                    </view>
                    <view v-if="form.refund_mode === 'cash'" class="field account-field" @click="accountPickerVisible = true">
                        <text class="label required">出款账户</text>
                        <view class="account-value">
                            <text :class="selectedAccountLabel ? 'value' : 'placeholder'">{{ selectedAccountLabel || '请选择实际退款账户' }}</text>
                            <u-icon name="arrow-right" color="#cbd5e1" size="15" />
                        </view>
                    </view>
                    <view v-else class="field">
                        <text class="label">财务处理</text>
                        <text class="value">提交后逐台生成客户应付</text>
                    </view>
                    <view v-if="!isCompensation" class="field">
                        <text class="label">回库规则</text>
                        <text class="value">自动退回设备原仓位</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">备注</text>
                        <u-input v-model="form.remark" placeholder="可选" :customStyle="{ textAlign:'right', flex:'1' }" />
                    </view>
                </view>
                <view v-if="form.refund_mode === 'cash'" class="form-card"><ErpVoucherUploader v-model="form.voucher_urls" :title="isCompensation ? '补差付款凭证' : '退款凭证'" @uploading="voucherUploading = $event" /></view>

                <view class="form-card" v-if="selectedItems.length">
                    <view class="field"><text class="label">已选</text><text class="value" style="color:#3b6ef5">{{ selectedItems.length }} 台</text></view>
                    <view class="field field--last"><text class="label">{{ isCompensation ? '补差合计' : '退款合计' }}</text><text class="value" style="color:#ea580c;font-weight:600">¥{{ money(totalReturn) }}</text></view>
                </view>
            </view>
        </scroll-view>

        <view class="float-bar">
            <u-button @click="goBack" :customStyle="{flex:'1'}">取消</u-button>
            <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit" :customStyle="{flex:'2'}">{{ isCompensation ? '确认售后补差' : '确认退货并处理退款' }}</u-button>
        </view>

        <ErpCapitalAccountPopup
            v-model:show="accountPickerVisible"
            v-model="form.capital_account_id"
            :accounts="accounts"
            :title="isCompensation ? '选择补差出款账户' : '选择退款出款账户'"
            subtitle="提交后将从该账户记录实际支出"
        />

    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createAndConfirmErpSaleReturn, createMobileSaleCompensation, getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import { scanErpCode } from '@/addon/hsx_erp/hooks/useErpScan'
import request from '@/utils/request'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import ErpCapitalAccountPopup from '@/addon/hsx_erp/components/ErpCapitalAccountPopup.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'
import { erpDeviceIdentityLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'

const saleOrderId = ref(0)
const saleNo = ref('')
const partyName = ref('')
const availableAssets = ref<any[]>([])
const loadingAssets = ref(false)
const submitting = ref(false)
const voucherUploading = ref(false)
const businessType = ref<'return' | 'compensation'>('return')
const isCompensation = computed(() => businessType.value === 'compensation')
const accounts = ref<any[]>([])
const accountPickerVisible = ref(false)
const goBack = () => uni.navigateBack()
const selectedItems = ref<any[]>([])
const selectedAssetIds = ref<number[]>([])
const targetAssetId = ref(0)
const form = ref({ refund_mode: 'payable', capital_account_id: 0, voucher_urls: '', remark: '' })
const refundModes = [
    { label: '现场处理', value: 'cash', desc: '从指定账户立即付款并记录资金流水' },
    { label: '转财务处理', value: 'payable', desc: '立即生成客户应付，由财务付款或折账' },
]
const deviceIdentityLine = (row: any) => erpDeviceIdentityLine(row)

onLoad(async (query: any) => {
    businessType.value = String(query?.mode || '') === 'compensation' ? 'compensation' : 'return'
    saleOrderId.value = Number(query?.sale_order_id || 0)
    targetAssetId.value = Number(query?.asset_id || 0)
    saleNo.value = decodeURIComponent(query?.sale_no || '')
    partyName.value = decodeURIComponent(query?.party_name || '')
    await Promise.all([
        saleOrderId.value ? loadAssets() : Promise.resolve(),
        loadAccounts(),
    ])
})

async function loadAccounts() {
    try {
        const res: any = await getMobileCapitalAccounts()
        accounts.value = res?.data?.list || []
    } catch {
        accounts.value = []
    }
}

async function loadAssets() {
    loadingAssets.value = true
    try {
        const res: any = await request.get(`erp/sale/${saleOrderId.value}`)
        const data = res?.data || {}
        availableAssets.value = (data.items || [])
            .filter((i: any) => i.status === 'sold')
            .map((i: any) => ({ id: i.asset_id || i.id, asset_id: i.asset_id || i.id, sale_item_id: i.id, model: i.model, imei: i.imei, spec: i.spec, sale_price: i.sale_price, cost: i.cost }))
        const target = availableAssets.value.find((item: any) => Number(item.id) === targetAssetId.value)
        if (target && !isSelected(Number(target.id))) toggleSelect(target)
    } finally { loadingAssets.value = false }
}

const isSelected = (id: number) => selectedItems.value.some(s => s.asset_id === id)
const getSelected = (id: number) => selectedItems.value.find(s => s.asset_id === id)!

function toggleSelect(item: any) {
    const idx = selectedItems.value.findIndex(s => s.asset_id === item.id)
    if (idx >= 0) {
        selectedItems.value.splice(idx, 1)
        selectedAssetIds.value = selectedAssetIds.value.filter(id => Number(id) !== Number(item.id))
    } else {
        addSelectedItem(item)
        selectedAssetIds.value = [...selectedAssetIds.value, Number(item.id)]
    }
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
}

function addSelectedItem(item: any) {
    if (isSelected(Number(item.id))) return
    selectedItems.value.push({
        asset_id: Number(item.id),
        sale_item_id: Number(item.sale_item_id || 0),
        return_price: Number(item.sale_price || 0),
        reason: '',
    })
}

async function scanSelectAsset() {
    try {
        const code = await scanErpCode()
        const item = availableAssets.value.find(i => scanMatch(i, code))
        if (!item) {
            uni.showToast({ title: '当前销售单未找到该设备', icon: 'none' })
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

const totalReturn = computed(() => selectedItems.value.reduce((s, i) => s + Number(i.return_price || 0), 0))
const selectedAccountLabel = computed(() => {
    const account = accounts.value.find(item => Number(item.id) === Number(form.value.capital_account_id))
    return account ? `${account.account_name}（余额 ¥${money(account.balance)}）` : ''
})
const refundPolicyText = computed(() => form.value.refund_mode === 'cash'
    ? (isCompensation.value ? '提交后立即从所选账户支付补差，并同步减少该设备销售毛利。' : '本次操作即代表设备已经交回；系统立即回库并从所选账户退款。')
    : (isCompensation.value ? '提交后设备仍由客户持有，系统逐台生成补差应付并减少毛利。' : '本次操作即代表设备已经交回；系统立即回库并逐台生成退款应付。'))
const canSubmit = computed(() =>
    selectedItems.value.length > 0 &&
    saleOrderId.value > 0 &&
    (form.value.refund_mode !== 'cash' || form.value.capital_account_id > 0) && !voucherUploading.value
)

async function submit() {
    if (submitting.value) return
    if (!canSubmit.value) {
        uni.showToast({
            title: !selectedItems.value.length ? '请选择退货设备' : '现场退款必须选择出款账户',
            icon: 'none'
        })
        return
    }
    submitting.value = true
    const financeText = form.value.refund_mode === 'cash'
        ? `将从【${selectedAccountLabel.value}】立即付款并记账。`
        : '将立即逐台生成客户应付，由财务付款或折账。'
    const confirmed = await confirmErpSensitiveAction({
        title: isCompensation.value ? '确认售后补差' : '确认销售退货',
        content: isCompensation.value
            ? `补差设备：${selectedItems.value.length} 台\n补差金额：¥${money(totalReturn.value)}\n设备继续由客户持有，补差会减少设备毛利。${financeText}`
            : `退货设备：${selectedItems.value.length} 台\n退货金额：¥${money(totalReturn.value)}\n本次确认代表设备已经交回，提交后立即回到原仓位。${financeText}`,
        confirmText: '确认提交',
    })
    if (!confirmed) {
        submitting.value = false
        return
    }
    try {
        const payload = {
            sale_order_id: saleOrderId.value,
            refund_mode: form.value.refund_mode,
            capital_account_id: form.value.refund_mode === 'cash' ? form.value.capital_account_id : 0,
            voucher_urls: form.value.voucher_urls,
            remark: form.value.remark,
            items: selectedItems.value.map(i => ({ asset_id: i.asset_id, return_price: Number(i.return_price), reason: i.reason || '' }))
        }
        if (isCompensation.value) await createMobileSaleCompensation(payload)
        else await createAndConfirmErpSaleReturn(payload)
        uni.showToast({ title: isCompensation.value ? '补差已提交财务处理' : '退货与账务处理已完成', icon: 'success' })
        setTimeout(() => uni.navigateBack(), 1500)
    } catch (e: any) {
        uni.showToast({ title: e?.message || '提交失败', icon: 'none' })
    } finally { submitting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.device-selected { border: 2rpx solid var(--primary-color, #3b6ef5); background: #f0f5ff; }
.section-head { display:flex; align-items:center; justify-content:space-between; padding:16rpx 28rpx 8rpx; }
.section-head .section-title { padding:0; }
.device-check-row { display: flex; align-items: center; gap: 12rpx; }
.loading-center { display: flex; justify-content: center; padding: 48rpx; }
.empty-tip { text-align: center; color: #94a3b8; font-size: 26rpx; padding: 48rpx 0; }
.refund-mode-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14rpx; padding:16rpx 0; }
.refund-mode-card { min-width:0; padding:18rpx; border:2rpx solid #e2e8f0; border-radius:16rpx; background:#f8fafc; }
.refund-mode-card--on { border-color:#3b6ef5; background:#eff6ff; }
.refund-mode-card__head { display:flex; align-items:center; justify-content:space-between; gap:10rpx; color:#0f172a; font-size:24rpx; font-weight:700; }
.choice-circle { width:30rpx; height:30rpx; box-sizing:border-box; border:3rpx solid #cbd5e1; border-radius:50%; flex:none; }
.refund-mode-card__desc { display:block; margin-top:9rpx; color:#64748b; font-size:20rpx; line-height:1.45; }
.refund-policy { margin:4rpx 0 14rpx; padding:14rpx 16rpx; border-radius:12rpx; background:#fff7ed; color:#9a3412; font-size:21rpx; line-height:1.55; }
.refund-policy--finance { background:#eff6ff; color:#1d4ed8; }
.account-field { cursor:pointer; }
.account-value { display:flex; min-width:0; flex:1; align-items:center; justify-content:flex-end; gap:10rpx; }
.account-value .value,.account-value .placeholder { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.label.required::before { content:'*'; margin-right:4rpx; color:#dc2626; }
</style>
