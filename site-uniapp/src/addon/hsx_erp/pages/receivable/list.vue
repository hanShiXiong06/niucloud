<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            placeholder="往来单位/销售单号"
            :tabs="tabs"
            :show-filter="true"
            :filter-count="filterCount"
            @search="handleSearch"
            @tab-change="onTab"
            @filter="filterVisible = true"
        />

        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无应收记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card" @click="goDetail(row)">
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.party_name }}</text>
                        <u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta">类型：{{ row.source_label || sourceLabel(row.source_type) }}</view>
                    <view class="card-meta">单据：{{ row.batch_no || row.sale_no || row.source_no || '-' }}</view>
                    <view class="card-meta" v-if="row.purchase_no">原采购单：{{ row.purchase_no }}</view>
                    <view class="card-meta" v-if="row.salesman_name">销售员：{{ row.salesman_name }}</view>
                    <view class="card-meta" v-if="row.item_count">共 {{ row.item_count }} 台设备</view>
                    <view class="card-meta" v-if="row.return_remark">说明：{{ row.return_remark }}</view>
                    <view class="card-time">{{ erpTimeLine(row, ['received_at', 'receipt_at', 'sale_at']) }}</view>
                    <view class="erp-card__foot">
                        <view class="amount-box">
                            <text class="amt-label">应收合计</text>
                            <text class="amt-value blue">¥{{ money(row.amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">已收</text>
                            <text class="amt-value green">¥{{ money(row.settled_amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">剩余应收</text>
                            <text class="amt-value orange">¥{{ money(row.remain_amount) }}</text>
                        </view>
                    </view>
                    <view v-if="row.status !== 'settled' && row.status !== 'void' && Number(row.remain_amount) > 0" class="card-actions">
                        <u-button type="primary" size="small" @click.stop="openDetailReceipt(row)">明细收款</u-button>
                        <u-button type="success" size="small" plain @click.stop="openReceipt(row)">整体收款</u-button>
                        <u-button v-if="row.can_offset" type="warning" size="small" plain @click.stop="openOffset(row)">折账</u-button>
                    </view>
                    <view v-if="row.can_offset" class="offset-tip">
                        可折账：应付 ¥{{ money(row.offset_payable_remain) }} / 应收 ¥{{ money(row.offset_receivable_remain) }}
                    </view>
                </view>
            </view>
        </z-paging>

        <!-- 收款弹窗 -->
        <u-popup :show="receiptVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24" @close="receiptVisible = false">
            <view v-if="receiptRow" class="pay-popup">
                <view class="popup-head">
                    <view>
                        <text class="popup-title">确认收款</text>
                        <text class="popup-subtitle">{{ receiptRow.party_name }}</text>
                    </view>
                    <view class="popup-close" @click="receiptVisible = false">
                        <u-icon name="close" color="#64748b" size="20" />
                    </view>
                </view>

                <view class="pay-summary">
                    <view class="summary-main">
                        <text class="summary-label">剩余应收</text>
                        <text class="summary-amount blue">¥{{ money(receiptRow.remain_amount) }}</text>
                    </view>
                    <u-tag :text="statusLabel(receiptRow.status)" :type="statusType(receiptRow.status)" plain plainFill size="mini" />
                    <text class="summary-sub">{{ receiptRow.source_label || sourceLabel(receiptRow.source_type) }} · {{ receiptRow.batch_no || receiptRow.sale_no || '-' }}</text>
                    <text v-if="receiptRow.purchase_no" class="summary-sub">原采购单：{{ receiptRow.purchase_no }}</text>
                </view>

                <view class="pay-form">
                    <view class="pay-form__item">
                        <view class="form-label-row">
                            <text class="pay-label required">本次收款金额</text>
                            <text class="fill-max" @click="fillReceiptMax">全额</text>
                        </view>
                        <u-input
                            v-model="receiptForm.amount"
                            type="number"
                            :placeholder="'最多 ¥'+money(receiptRow.remain_amount)"
                            :customStyle="inputStyle"
                            @blur="capReceiptAmount"
                        />
                    </view>
                    <view class="pay-form__item" @click="receiptAccountPickerVisible = true">
                        <text class="pay-label required">收款账户</text>
                        <view class="account-select" :class="{ 'account-select--on': receiptForm.capital_account_id }">
                            <text :class="receiptForm.capital_account_id ? 'account-text' : 'account-placeholder'">
                                {{ selectedReceiptAccountLabel || '点击选择账户' }}
                            </text>
                            <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                        </view>
                    </view>
                    <view class="pay-form__item">
                        <text class="pay-label">备注</text>
                        <u-input v-model="receiptForm.remark" placeholder="如：微信/现金/银行卡" :customStyle="inputStyle" />
                    </view>
                </view>
                <view class="action-bar">
                    <view class="action-btn action-btn--minor">
                        <u-button @click="receiptVisible = false">取消</u-button>
                    </view>
                    <view class="action-btn action-btn--major">
                        <u-button type="primary" :loading="receipting" :disabled="!canReceipt" @click="submitReceipt">
                            确认收款 ¥{{ money(receiptForm.amount) }}
                        </u-button>
                    </view>
                </view>
            </view>
        </u-popup>

        <u-popup :show="receiptAccountPickerVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24" @close="receiptAccountPickerVisible = false">
            <view class="account-popup">
                <view class="popup-head compact">
                    <text class="popup-title">选择收款账户</text>
                    <view class="popup-close" @click="receiptAccountPickerVisible = false">
                        <u-icon name="close" color="#64748b" size="20" />
                    </view>
                </view>
                <u-cell-group v-if="accounts.length" :border="false">
                    <u-cell v-for="a in accounts" :key="a.id" :title="a.account_name" :label="'余额 ¥' + money(a.balance)" @click="selectReceiptAccount(a)">
                        <template #value>
                            <u-icon
                                v-if="Number(a.id) === Number(receiptForm.capital_account_id)"
                                name="checkmark-circle-fill"
                                color="#3b6ef5"
                                size="20"
                            />
                        </template>
                    </u-cell>
                </u-cell-group>
                <u-empty v-else mode="data" text="暂无可用资金账户" />
            </view>
        </u-popup>

        <!-- 逐台收款弹窗（对齐PC端，含可调售价） -->
        <ErpReceiptConfirmModal
            v-if="detailReceiptRow"
            v-model:show="detailReceiptVisible"
            :receivable-id="detailReceiptRow?.id"
            :party-name="detailReceiptRow?.party_name"
            :accounts="accounts"
            @success="() => { reload(); detailReceiptRow = null }"
        />

        <ErpOffsetConfirmModal
            v-if="offsetRow"
            v-model:show="offsetVisible"
            :party-id="offsetRow?.party_id"
            :party-name="offsetRow?.party_name"
            :accounts="accounts"
            @success="() => { reload(); offsetRow = null }"
        />

        <ErpFilterPopup
            v-model:show="filterVisible"
            v-model="filters"
            title="筛选应收款"
            :fields="filterFields"
            @confirm="applyFilter"
            @reset="resetFilter"
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobileReceivableList, confirmMobileSaleReceipt, getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpReceiptConfirmModal from '@/addon/hsx_erp/components/ErpReceiptConfirmModal.vue'
import ErpOffsetConfirmModal from '@/addon/hsx_erp/components/ErpOffsetConfirmModal.vue'
import ErpFilterPopup from '@/addon/hsx_erp/components/ErpFilterPopup.vue'

import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine } from '@/addon/hsx_erp/hooks/useErpTime'

const { pagingStyle } = useListHeader(126)

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)

const tabs = [
    { label: '待收款', value: 'pending' },
    { label: '部分收款', value: 'partial' },
    { label: '全部', value: '' },
]
const activeTab = ref('pending')
const filterVisible = ref(false)
const filters = ref<Record<string, any>>({})
const filterFields = [
    { key: 'party_id', label: '往来单位', type: 'party', roleType: 'all', labelKey: 'party_name', placeholder: '请选择客户/供应商' },
    { key: 'source_no', label: '单据号', type: 'text', placeholder: '输入销售单号/退货单号' },
    { key: 'm_no', label: '会员号', type: 'text', placeholder: '输入会员号' },
    { key: 'contact_mobile', label: '联系人手机', type: 'text', placeholder: '输入手机号' },
    { key: 'salesman_uid', label: '销售员', type: 'staff', labelKey: 'salesman_name', placeholder: '请选择销售员' },
    { key: 'amount', label: '应收金额', type: 'range', minKey: 'min_amount', maxKey: 'max_amount' },
    { key: 'remain', label: '剩余应收', type: 'range', minKey: 'min_remain', maxKey: 'max_remain' },
    { key: 'can_offset', label: '折账状态', type: 'select', options: [
        { label: '全部', value: '' },
        { label: '可折账', value: 1 },
    ] },
    { key: 'date', label: '发生日期', type: 'dateRange', startKey: 'start_at', endKey: 'end_at' },
] as any[]
const filterCount = computed(() => Object.entries(filters.value).filter(([key, v]) => !key.endsWith('_name') && v !== '' && v !== undefined && v !== null).length)

const accounts = ref<any[]>([])

const receiptVisible = ref(false)
const receiptAccountPickerVisible = ref(false)
const receipting = ref(false)
const receiptRow = ref<any>(null)
const receiptForm = ref({ amount: 0, capital_account_id: 0, remark: '' })
const detailReceiptVisible = ref(false)
const detailReceiptRow = ref<any>(null)
const offsetVisible = ref(false)
const offsetRow = ref<any>(null)
const receiptRemain = computed(() => Number(receiptRow.value?.remain_amount || 0))
const canReceipt = computed(() =>
    Number(receiptForm.value.amount) > 0 &&
    receiptForm.value.capital_account_id > 0
)
const selectedReceiptAccountLabel = computed(() => {
    const a = accounts.value.find(a => Number(a.id) === Number(receiptForm.value.capital_account_id))
    return a ? `${a.account_name}（余额 ¥${money(a.balance)}）` : ''
})
const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '12rpx 16rpx' }

onMounted(async () => {
    try {
        const res: any = await getMobileCapitalAccounts()
        accounts.value = res?.data?.list || []
    } catch {}
})

const reload = () => pagingRef.value?.reload()
const handleSearch = () => reload()
const onTab = (val: string) => { activeTab.value = val; reload() }

onShow(() => reload())

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobileReceivableList({
            keyword: keyword.value, status: activeTab.value,
            ...filterParams(),
            page: pageNo, limit: pageSize
        })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}

function applyFilter() { reload() }
function resetFilter() { filters.value = {}; reload() }
function filterParams() {
    const params: Record<string, any> = { ...filters.value }
    Object.keys(params).forEach(key => { if (key.endsWith('_name')) delete params[key] })
    dateToRange(params)
    return params
}
function dateToRange(params: Record<string, any>) {
    if (typeof params.start_at === 'string' && params.start_at) params.start_at = Math.floor(new Date(params.start_at + ' 00:00:00').getTime() / 1000)
    if (typeof params.end_at === 'string' && params.end_at) params.end_at = Math.floor(new Date(params.end_at + ' 23:59:59').getTime() / 1000)
}

function openReceipt(row: any) {
    receiptRow.value = row
    receiptForm.value = {
        amount: Number(Number(row.remain_amount || 0).toFixed(2)),
        capital_account_id: accounts.value[0]?.id || 0,
        remark: ''
    }
    receiptVisible.value = true
}

function goDetail(row: any) {
    uni.navigateTo({ url: `/addon/hsx_erp/pages/receivable/detail?id=${row.id}` })
}

function selectReceiptAccount(account: any) {
    receiptForm.value.capital_account_id = Number(account.id || 0)
    receiptAccountPickerVisible.value = false
}

function openDetailReceipt(row: any) {
    detailReceiptRow.value = row
    detailReceiptVisible.value = true
}

function openOffset(row: any) {
    if (!row.can_offset) {
        uni.showToast({ title: '该往来单位暂无可折账应付', icon: 'none' })
        return
    }
    offsetRow.value = row
    offsetVisible.value = true
}

function fillReceiptMax() {
    receiptForm.value.amount = Number(Number(receiptRemain.value || 0).toFixed(2))
}

function capReceiptAmount() {
    const amount = Number(receiptForm.value.amount || 0)
    const max = Number(receiptRemain.value || 0)
    if (amount > max + 0.0001) {
        receiptForm.value.amount = Number(max.toFixed(2))
        uni.showToast({ title: `不能超过剩余应收 ¥${money(max)}`, icon: 'none' })
        return false
    }
    if (amount < 0) {
        receiptForm.value.amount = 0
        return false
    }
    return true
}

async function submitReceipt() {
    if (!receiptRow.value) return
    if (!canReceipt.value) {
        uni.showToast({ title: receiptForm.value.capital_account_id ? '请输入收款金额' : '请选择收款账户', icon: 'none' })
        return
    }
    if (!capReceiptAmount()) return
    receipting.value = true
    try {
        await confirmMobileSaleReceipt(receiptRow.value.id, {
            amount: Number(receiptForm.value.amount),
            capital_account_id: receiptForm.value.capital_account_id,
            remark: receiptForm.value.remark || '手机端确认收款',
        })
        uni.showToast({ title: '收款已确认', icon: 'success' })
        receiptVisible.value = false
        receiptAccountPickerVisible.value = false
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '收款失败，请重试', icon: 'none' })
    } finally { receipting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
const sourceLabel = (s: string) => ({ sale: '销售收款', purchase_return: '采购退货退款' }[s] || s || '应收款')
const statusLabel = (s: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', void: '已作废' }[s] || s || '-')
const statusType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.card-actions { margin-top:16rpx; display:flex; gap:12rpx; }
.offset-tip { margin-top:12rpx; font-size:23rpx; color:#b45309; background:#fff7ed; border-radius:12rpx; padding:12rpx 16rpx; }
.pay-popup { max-height:82vh; background:#fff; display:flex; flex-direction:column; overflow:hidden; }
.popup-head { min-height:96rpx; padding:0 28rpx; display:flex; align-items:center; justify-content:space-between; gap:20rpx; border-bottom:1rpx solid #eef2f7; }
.popup-head.compact { border-bottom:0; }
.popup-title { display:block; font-size:34rpx; font-weight:700; color:#0f172a; }
.popup-subtitle { display:block; margin-top:4rpx; font-size:24rpx; color:#64748b; }
.popup-close { width:56rpx; height:56rpx; border-radius:28rpx; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.pay-summary { margin:24rpx 28rpx 20rpx; padding:22rpx; border-radius:18rpx; background:#f8fafc; display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:10rpx 16rpx; align-items:center; }
.summary-main { min-width:0; }
.summary-label { display:block; font-size:23rpx; color:#64748b; }
.summary-amount { display:block; margin-top:6rpx; font-size:38rpx; color:#ea580c; font-weight:800; }
.summary-amount.blue { color:#2563eb; }
.summary-sub { grid-column:1 / 3; font-size:23rpx; color:#94a3b8; }
.pay-form { padding:0 28rpx 8rpx; }
.pay-form__item { margin-bottom:22rpx; }
.form-label-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:10rpx; }
.pay-label { font-size:26rpx; color:#374151; display:block; margin-bottom:10rpx; font-weight:600; }
.form-label-row .pay-label { margin-bottom:0; }
.pay-label.required::before { content:'*'; color:#dc2626; margin-right:4rpx; }
.fill-max { font-size:24rpx; color:#3b6ef5; }
.account-select { display:flex; align-items:center; justify-content:space-between; gap:16rpx; background:#f8fafc; border-radius:12rpx; border:2rpx solid transparent; padding:0 18rpx; min-height:72rpx; box-sizing:border-box; }
.account-select--on { background:#f8fbff; border-color:#3b6ef5; }
.account-text { flex:1; min-width:0; font-size:26rpx; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.account-placeholder { flex:1; min-width:0; font-size:26rpx; color:#94a3b8; }
.account-popup { min-height:36vh; max-height:74vh; padding-bottom:calc(20rpx + env(safe-area-inset-bottom)); background:#fff; }
.action-btn { min-width:0; }
.action-btn--minor { flex:1; }
.action-btn--major { flex:2; }
</style>
