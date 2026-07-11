<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            :tabs="tabs"
            placeholder="客户 / IMEI / 退货单号"
            :show-scan="false"
            :show-filter="true"
            :filter-count="filterCount"
            @search="reload"
            @tab-change="onTab"
            @filter="filterVisible = true"
        />
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无退货记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card" @click="goDetail(row)">
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.party_name }}</text>
                        <u-tag :text="statusLabel(row.status, row.business_type)" :type="statusType(row.status)" plain plainFill size="mini" />
                    </view>
                    <view class="business-type" :class="{ 'business-type--compensation': isCompensation(row) }">{{ businessTypeLabel(row.business_type) }}</view>
                    <view class="card-meta">退货单：{{ row.return_no }}</view>
                    <view class="card-meta">原销售单：{{ row.sale_no || '-' }}</view>
                    <view v-if="row.first_item" class="device-summary"><strong>{{ row.first_item.model || '未填写设备名称' }}</strong><text>IMEI {{ row.first_item.imei || '-' }}{{ row.item_count > 1 ? ` · 共 ${row.item_count} 台` : '' }}</text></view>
                    <view class="card-meta">{{ isCompensation(row) ? '补差金额' : '退款金额' }}：¥{{ money(row.total_amount) }}</view>
                    <view class="card-meta">操作人：{{ row.operator_name || '-' }}</view>
                    <view class="accounting-line" :class="{ 'accounting-line--cash': row.refund_mode === 'cash' }">
                        <text>账务处理</text>
                        <strong>{{ accountingLabel(row) }}</strong>
                    </view>
                    <view class="card-meta" v-if="row.remark">备注：{{ row.remark }}</view>
                    <view class="card-time">{{ erpTimeLine(row, ['returned_at', 'return_at', 'confirmed_at']) }}</view>
                    <view class="status-row" style="margin-top:16rpx" @click.stop>
                        <u-button v-if="row.status === 'pending'" type="primary" size="small" :loading="confirming === row.id" @click="doConfirm(row)">{{ confirmActionLabel(row) }}</u-button>
                        <u-button v-if="row.can_cancel" type="error" size="small" plain :loading="cancelling === row.id" @click="doCancel(row)">撤销退货</u-button>
                        <u-button type="primary" size="small" plain @click="goDetail(row)">查看详情</u-button>
                    </view>
                </view>
            </view>
        </z-paging>

        <view class="fab fab--label" @click="goCreate">
            <u-icon name="plus" color="#fff" size="22" />
            <text class="fab__text">新建销退</text>
        </view>

        <ErpCapitalAccountPopup
            v-model:show="confirmAccountPickerVisible"
            v-model="confirmAccountId"
            :accounts="accounts"
            title="选择现场退款账户"
            subtitle="该旧单未指定账户；选择后再确认收货与退款"
            @change="onConfirmAccountSelected"
        />
        <u-popup :show="cashVoucherVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="28rpx" @close="cashVoucherVisible = false">
            <view class="cash-voucher-popup">
                <view class="cash-voucher-popup__head">
                    <view><text class="cash-voucher-popup__title">核对现场退款</text><text class="cash-voucher-popup__sub">{{ pendingConfirmRow?.party_name || '-' }} · ¥{{ money(pendingConfirmRow?.total_amount) }}</text></view>
                    <u-icon name="close" size="20" color="#94a3b8" @click="cashVoucherVisible = false" />
                </view>
                <ErpVoucherUploader v-model="cashVoucherUrls" title="退款凭证" hint="建议上传转账截图或回单，便于后续审计" @uploading="voucherUploading = $event" />
                <view class="cash-voucher-popup__actions">
                    <u-button @click="cashVoucherVisible = false">取消</u-button>
                    <u-button type="primary" :disabled="voucherUploading" @click="submitCashConfirm">确认收货并退款</u-button>
                </view>
            </view>
        </u-popup>
        <ErpFilterPopup v-model:show="filterVisible" v-model="filters" title="筛选销售退货" :fields="filterFields" @confirm="reload" @reset="resetFilter" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobileSaleReturnList, confirmMobileSaleReturn, cancelMobileSaleReturn, getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpCapitalAccountPopup from '@/addon/hsx_erp/components/ErpCapitalAccountPopup.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'
import ErpFilterPopup from '@/addon/hsx_erp/components/ErpFilterPopup.vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine } from '@/addon/hsx_erp/hooks/useErpTime'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'
import { cloneErpSubmitSnapshot, confirmErpPopupAction } from '@/addon/hsx_erp/hooks/useErpPopupConfirm'

const { pagingStyle } = useListHeader(126)


const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const confirming = ref(0)
const cancelling = ref(0)
const keyword = ref('')
const accounts = ref<any[]>([])
const confirmAccountPickerVisible = ref(false)
const confirmAccountId = ref(0)
const pendingConfirmRow = ref<any>(null)
const cashVoucherVisible = ref(false)
const cashVoucherUrls = ref('')
const voucherUploading = ref(false)
const filterVisible = ref(false)
const filters = ref<Record<string, any>>({})
const filterFields = [
    { key: 'party_id', label: '客户', type: 'party', roleType: 'customer', labelKey: 'party_name', placeholder: '请选择客户' },
    { key: 'imei', label: 'IMEI / 串号', type: 'text', placeholder: '输入 IMEI 或资产号' },
    { key: 'operator_id', label: '操作人', type: 'staff', labelKey: 'operator_name', placeholder: '请选择操作人' },
    { key: 'date', label: '退货日期', type: 'dateRange', startKey: 'start_at', endKey: 'end_at' },
] as any[]
const filterCount = computed(() => Object.entries(filters.value).filter(([key, value]) => !key.endsWith('_name') && value !== '' && value !== null && value !== undefined).length)

const tabs = [
    { label: '全部', value: '' },
    { label: '待确认收货', value: 'pending' },
    { label: '已完成退货', value: 'confirmed' },
    { label: '已取消', value: 'cancelled' },
]
const activeTab = ref('')
const reload = () => pagingRef.value?.reload()
const onTab = (val: string) => { activeTab.value = val; reload() }
const goCreate = () => uni.navigateTo({ url: '/addon/hsx_erp/pages/sale/list?mode=return' })

onShow(async () => {
    await loadAccounts()
    reload()
})

async function loadAccounts() {
    try {
        const res: any = await getMobileCapitalAccounts()
        accounts.value = res?.data?.list || []
    } catch {
        accounts.value = []
    }
}

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobileSaleReturnList({ keyword: keyword.value, status: activeTab.value, ...filterParams(), page: pageNo, limit: pageSize })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}

function resetFilter() { filters.value = {}; reload() }
function filterParams() {
    const params: Record<string, any> = { ...filters.value }
    Object.keys(params).forEach(key => { if (key.endsWith('_name')) delete params[key] })
    if (typeof params.start_at === 'string' && params.start_at) params.start_at = Math.floor(new Date(params.start_at + ' 00:00:00').getTime() / 1000)
    if (typeof params.end_at === 'string' && params.end_at) params.end_at = Math.floor(new Date(params.end_at + ' 23:59:59').getTime() / 1000)
    return params
}
function goDetail(row: any) { uni.navigateTo({ url: `/addon/hsx_erp/pages/sale_return/detail?id=${Number(row.id || 0)}` }) }

async function doConfirm(row: any) {
    if (confirming.value) return
    const storedAccountId = Number(row.capital_account_id || 0)
    const storedAccountAvailable = accounts.value.some(item => Number(item.id) === storedAccountId)
    if (row.refund_mode === 'cash' && (!storedAccountId || !storedAccountAvailable)) {
        if (!accounts.value.length) {
            uni.showToast({ title: '暂无可用资金账户，不能执行现场退款', icon: 'none' })
            return
        }
        pendingConfirmRow.value = row
        confirmAccountId.value = 0
        confirmAccountPickerVisible.value = true
        return
    }
    if (row.refund_mode === 'cash') {
        openCashVoucher(row, storedAccountId)
        return
    }
    await confirmReturn(row, storedAccountId)
}

async function doCancel(row: any) {
    if (cancelling.value) return
    cancelling.value = Number(row.id || 0)
    const confirmed = await confirmErpSensitiveAction({
        title: '取消退货单',
        content: row.status === 'confirmed'
            ? '仅在退款应付尚未付款或折账时可撤销。撤销后设备恢复原销售关系，退款应付作废。'
            : '取消后不会执行设备回库，也不会生成退款应付或资金流水。',
        confirmText: '确认取消',
        cancelText: '暂不取消',
    })
    if (!confirmed) {
        cancelling.value = 0
        return
    }
    try {
        await cancelMobileSaleReturn(row.id, { remark: '移动端撤销退货' })
        uni.showToast({ title: '退货单已取消', icon: 'success' })
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '取消失败', icon: 'none' })
    } finally {
        cancelling.value = 0
    }
}

async function onConfirmAccountSelected(account: any) {
    const pending = pendingConfirmRow.value
    if (!pending) return
    openCashVoucher(cloneErpSubmitSnapshot(pending), Number(account?.id || 0))
}

function openCashVoucher(row: any, capitalAccountId: number) {
    pendingConfirmRow.value = { ...row, __capital_account_id: capitalAccountId }
    cashVoucherUrls.value = ''
    confirmAccountPickerVisible.value = false
    cashVoucherVisible.value = true
}

async function submitCashConfirm() {
    const row = pendingConfirmRow.value
    if (!row || voucherUploading.value) return
    await confirmReturn(row, Number(row.__capital_account_id || 0), true, cashVoucherUrls.value)
}

async function confirmReturn(row: any, capitalAccountId: number, fromPopup = false, voucherUrls = '') {
    if (confirming.value) return
    const account = accounts.value.find(item => Number(item.id) === Number(capitalAccountId))
    const financeText = row.refund_mode === 'cash'
        ? `已收款部分将从【${account?.account_name || '原单指定账户'}】逐台现场退款并记账。`
        : '已收款部分将逐台生成客户退款应付，由财务付款或折账。'
    const confirmOptions = {
        title: '确认实际收到退货设备',
        content: `确认后设备回到各自原仓位，不能普通撤销。${financeText}\n退货金额：¥${money(row.total_amount)}`,
        confirmText: confirmActionLabel(row),
        cancelText: '返回核对',
    }
    confirming.value = Number(row.id || 0)
    const confirmed = fromPopup
        ? await confirmErpPopupAction({
            ...confirmOptions,
            closePopup: () => { confirmAccountPickerVisible.value = false; cashVoucherVisible.value = false },
            reopenPopup: () => {
                pendingConfirmRow.value = row
                cashVoucherVisible.value = true
            },
        })
        : await confirmErpSensitiveAction(confirmOptions)
    if (!confirmed) {
        confirming.value = 0
        return
    }
    try {
        await confirmMobileSaleReturn(row.id, { capital_account_id: capitalAccountId, voucher_urls: voucherUrls })
        uni.showToast({ title: '退货收货已确认', icon: 'success' })
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '确认失败', icon: 'none' })
    } finally { confirming.value = 0 }
}

const money = (v: any) => Number(v || 0).toFixed(2)
const isCompensation = (row: any) => row?.business_type === 'after_sale_compensation'
const statusLabel = (s: string, businessType = '') => businessType === 'after_sale_compensation'
    ? (s === 'cancelled' ? '补差已取消' : '补差已确认')
    : ({ pending: '待确认收货', confirmed: '已完成退货', cancelled: '已取消' }[s] || s || '-')
const statusType = (s: string) => ({ pending: 'warning', confirmed: 'success', cancelled: 'info' }[s] || 'info')
const businessTypeLabel = (s: string) => s === 'after_sale_compensation' ? '售后补差' : '退货退款'
const confirmActionLabel = (row: any) => row?.refund_mode === 'cash' ? '确认收货并退款' : '确认收货并变应付'
function accountingLabel(row: any) {
    if (row.status === 'cancelled') return '未回库，未产生后续退款'
    if (isCompensation(row)) return row.refund_mode === 'cash' ? '现场补差已记账' : '已生成设备级客户应付'
    if (row.status === 'pending') return row.refund_mode === 'cash'
        ? '收货后从指定账户现场退款'
        : '收货后生成设备级客户应付'
    if (row.refund_mode === 'cash') return '现场退款已处理并记账'
    if (row.refund_mode === 'offset') return '历史折账退货单'
    return '已生成设备级客户应付'
}
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.business-type { display:inline-flex; margin:4rpx 0 8rpx; padding:5rpx 12rpx; border-radius:999rpx; background:#eff6ff; color:#2563eb; font-size:19rpx; font-weight:650; }
.business-type--compensation { background:#fff7ed; color:#c2410c; }
.accounting-line { display:flex; align-items:center; justify-content:space-between; gap:16rpx; margin-top:14rpx; padding:13rpx 15rpx; border-radius:12rpx; background:#eff6ff; color:#1d4ed8; font-size:21rpx; }
.accounting-line text { color:#64748b; }
.accounting-line strong { font-weight:650; text-align:right; }
.accounting-line--cash { background:#fff7ed; color:#c2410c; }
.cash-voucher-popup { padding:28rpx 30rpx calc(28rpx + env(safe-area-inset-bottom)); background:#fff; }
.cash-voucher-popup__head { display:flex; align-items:flex-start; justify-content:space-between; gap:20rpx; margin-bottom:22rpx; }
.cash-voucher-popup__title,.cash-voucher-popup__sub { display:block; }.cash-voucher-popup__title { color:#0f172a; font-size:31rpx; font-weight:700; }.cash-voucher-popup__sub { margin-top:6rpx; color:#64748b; font-size:23rpx; }
.cash-voucher-popup__actions { display:grid; grid-template-columns:1fr 2fr; gap:16rpx; margin-top:24rpx; }
.device-summary{display:flex;align-items:center;justify-content:space-between;gap:14rpx;margin:12rpx 0;padding:13rpx 15rpx;border-radius:12rpx;background:#f8fafc}.device-summary strong{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#334155;font-size:23rpx}.device-summary text{flex:none;color:#64748b;font-size:20rpx}
</style>
