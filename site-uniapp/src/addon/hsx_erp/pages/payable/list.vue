<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            placeholder="供应商/采购单号"
            :tabs="tabs"
            @search="handleSearch"
            @tab-change="onTab"
        />

        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无应付记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="String(row.party_id)+'_'+String(row.purchase_order_id)" class="erp-card">
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.party_name }}</text>
                        <u-tag :text="statusLabel(row.finance_status)" :type="statusType(row.finance_status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta">采购批次：{{ row.batch_no || row.purchase_no || '-' }}</view>
                    <view class="card-meta" v-if="row.purchaser_name">采购员：{{ row.purchaser_name }}</view>
                    <view class="card-meta" v-if="row.warehouse_name">仓库：{{ row.warehouse_name }}</view>
                    <view class="card-time">{{ erpTimeLine(row, ['paid_at', 'pay_at', 'stock_in_at']) }}</view>
                    <view class="erp-card__foot">
                        <view class="amount-box">
                            <text class="amt-label">应付合计</text>
                            <text class="amt-value">¥{{ money(row.amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">已付</text>
                            <text class="amt-value green">¥{{ money(row.settled_amount) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">剩余应付</text>
                            <text class="amt-value orange">¥{{ money(row.remain_amount) }}</text>
                        </view>
                    </view>
                    <view v-if="row.finance_status !== 'settled' && Number(row.remain_amount) > 0" style="margin-top:16rpx;display:flex;gap:12rpx">
                        <u-button type="primary" size="small" @click="openDetailPay(row)">逐台付款</u-button>
                        <u-button type="success" size="small" plain @click="openPay(row)">整体付款</u-button>
                    </view>
                </view>
            </view>
        </z-paging>

        <!-- 付款弹窗 -->
        <u-popup :show="payVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24" @close="payVisible = false">
            <view v-if="payRow" class="pay-popup">
                <view class="pay-popup__title">确认付款</view>
                <view class="pay-info">
                    <text class="pay-info__name">{{ payRow.party_name }}</text>
                    <text class="pay-info__sub">{{ payRow.batch_no || payRow.purchase_no }}</text>
                    <text class="pay-info__remain">剩余应付：¥{{ money(payRow.remain_amount) }}</text>
                </view>
                <view class="pay-form">
                    <view class="pay-form__item">
                        <text class="pay-label">本次付款金额</text>
                        <u-input v-model="payForm.amount" type="number" :placeholder="'最多 ¥'+money(payRow.remain_amount)" :customStyle="inputStyle" />
                    </view>
                    <view class="pay-form__item" @click="payAccountPickerVisible = true">
                        <text class="pay-label">付款账户</text>
                        <view class="account-select">
                            <text :class="payForm.capital_account_id ? 'account-text' : 'account-placeholder'">
                                {{ selectedPayAccountLabel || '点击选择账户' }}
                            </text>
                            <text class="account-arrow">›</text>
                        </view>
                    </view>
                    <view class="pay-form__item">
                        <text class="pay-label">备注</text>
                        <u-input v-model="payForm.remark" placeholder="如：转账/现金/核对单号" :customStyle="inputStyle" />
                    </view>
                </view>
                <view class="action-bar">
                    <u-button @click="payVisible = false" :customStyle="{flex:'1'}">取消</u-button>
                    <u-button type="primary" :loading="paying" :disabled="!canPay" @click="submitPay" :customStyle="{flex:'2'}">确认付款并记账</u-button>
                </view>
            </view>
        </u-popup>

        <u-popup :show="payAccountPickerVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24" @close="payAccountPickerVisible = false">
            <view class="account-popup">
                <view class="account-popup__title">选择付款账户</view>
                <view
                    v-for="a in accounts"
                    :key="a.id"
                    class="account-item"
                    :class="{ selected: a.id === payForm.capital_account_id }"
                    @click="selectPayAccount(a)"
                >
                    <text class="account-item__name">{{ a.account_name }}</text>
                    <text class="account-item__balance">余额 ¥{{ money(a.balance) }}</text>
                </view>
                <view v-if="!accounts.length" class="account-empty">暂无可用资金账户</view>
            </view>
        </u-popup>

        <!-- 逐台付款弹窗（对齐PC端） -->
        <ErpPayConfirmModal
            v-if="detailPayRow"
            v-model:show="detailPayVisible"
            :party-id="detailPayRow?.party_id"
            :party-name="detailPayRow?.party_name"
            :purchase-order-id="detailPayRow?.purchase_order_id"
            :accounts="accounts"
            @success="() => { reload(); detailPayRow = null }"
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobilePayableList, confirmMobilePurchasePayment, getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpPayConfirmModal from '@/addon/hsx_erp/components/ErpPayConfirmModal.vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine } from '@/addon/hsx_erp/hooks/useErpTime'

const { pagingStyle } = useListHeader(126)

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)

const tabs = [
    { label: '待付款', value: 'pending' },
    { label: '部分付款', value: 'partial' },
    { label: '全部', value: '' },
]
const activeTab = ref('pending')

const accounts = ref<any[]>([])
const accountOptions = computed(() =>
    accounts.value.map(a => ({ value: a.id, label: `${a.account_name}（余额 ¥${money(a.balance)}）` }))
)

const payVisible = ref(false)
const payAccountPickerVisible = ref(false)
const paying = ref(false)
const payRow = ref<any>(null)
const payForm = ref({ amount: 0, capital_account_id: 0, remark: '' })
// 逐台付款modal
const detailPayVisible = ref(false)
const detailPayRow = ref<any>(null)
const canPay = computed(() =>
    Number(payForm.value.amount) > 0 &&
    Number(payForm.value.amount) <= Number(payRow.value?.remain_amount || 0) + 0.001 &&
    payForm.value.capital_account_id > 0
)
const selectedPayAccountLabel = computed(() => {
    const a = accounts.value.find(a => Number(a.id) === Number(payForm.value.capital_account_id))
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
        const res: any = await getMobilePayableList({
            keyword: keyword.value, status: activeTab.value,
            page: pageNo, limit: pageSize
        })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}

function openPay(row: any) {
    payRow.value = row
    payForm.value = {
        amount: Number(Number(row.remain_amount || 0).toFixed(2)),
        capital_account_id: accounts.value[0]?.id || 0,
        remark: ''
    }
    payVisible.value = true
}

function openDetailPay(row: any) {
    detailPayRow.value = row
    detailPayVisible.value = true
}

function selectPayAccount(account: any) {
    payForm.value.capital_account_id = Number(account.id || 0)
    payAccountPickerVisible.value = false
}

async function submitPay() {
    if (!canPay.value || !payRow.value) return
    paying.value = true
    try {
        // 调用"整体付款"接口，自动分配到该供应商各批次欠款
        await confirmMobilePurchasePayment(payRow.value.party_id, {
            amount: Number(payForm.value.amount),
            capital_account_id: payForm.value.capital_account_id,
            remark: payForm.value.remark || '手机端确认付款',
        })
        uni.showToast({ title: '付款已确认', icon: 'success' })
        payVisible.value = false
        payAccountPickerVisible.value = false
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '付款失败，请重试', icon: 'none' })
    } finally { paying.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
const statusLabel = (s: string) => ({ pending: '待付款', partial: '部分付款', settled: '已结清' }[s] || s || '-')
const statusType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.pay-popup { padding: 32rpx; padding-bottom: 0; }
.pay-popup__title { font-size: 34rpx; font-weight: 700; color: #0f172a; margin-bottom: 20rpx; }
.pay-info { background: #f8fafc; border-radius: 12rpx; padding: 20rpx; margin-bottom: 24rpx; }
.pay-info__name { font-size: 30rpx; font-weight: 600; color: #0f172a; display: block; }
.pay-info__sub { font-size: 24rpx; color: #64748b; margin-top: 4rpx; display: block; }
.pay-info__remain { font-size: 28rpx; color: #ea580c; font-weight: 600; margin-top: 8rpx; display: block; }
.pay-form__item { margin-bottom: 20rpx; }
.pay-label { font-size: 26rpx; color: #374151; display: block; margin-bottom: 8rpx; }
.account-select { display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border-radius:8rpx; padding:16rpx; min-height:72rpx; box-sizing:border-box; }
.account-text { font-size:26rpx; color:#0f172a; }
.account-placeholder { font-size:26rpx; color:#94a3b8; }
.account-arrow { font-size:32rpx; color:#94a3b8; }
.account-popup { padding:32rpx; }
.account-popup__title { font-size:30rpx; font-weight:700; color:#0f172a; margin-bottom:20rpx; }
.account-item { display:flex; justify-content:space-between; gap:20rpx; padding:22rpx 0; border-bottom:1rpx solid #f1f5f9; }
.account-item.selected { color:#3b6ef5; }
.account-item__name { font-size:28rpx; }
.account-item__balance { font-size:24rpx; color:#64748b; }
.account-empty { text-align:center; color:#94a3b8; font-size:26rpx; padding:40rpx 0; }
</style>
