<template>
    <view class="erp-page">
        <RecyclePageHeader title="应付款" />
        <view class="page-search">
            <u-search v-model="keyword" placeholder="供应商/采购单号" :showAction="false" bgColor="#f1f5f9" height="34" @search="reload" @clear="reload" />
            <u-tabs :list="tabs" :current="tabIndex" lineColor="#3b6ef5"
                :activeStyle="{color:'#0f172a',fontWeight:'600'}" :inactiveStyle="{color:'#64748b'}"
                lineWidth="40" @click="onTab" />
        </view>

        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :paging-style="pagingStyle">
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
                    <view v-if="row.finance_status !== 'settled' && Number(row.remain_amount) > 0" style="margin-top:16rpx">
                        <u-button type="primary" size="small" @click="openPay(row)">确认付款</u-button>
                    </view>
                </view>
            </view>
        </z-paging>

        <!-- 付款弹窗 -->
        <u-popup v-model="payVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24">
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
                    <view class="pay-form__item">
                        <text class="pay-label">付款账户</text>
                        <u-select v-model="payForm.capital_account_id" :list="accountOptions" />
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
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { getMobilePayableList, confirmMobilePurchasePayment, getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const { pagingStyle } = useListHeader(96)

const tabs = [
    { name: '待付款', value: 'pending' },
    { name: '部分付款', value: 'partial' },
    { name: '全部', value: '' },
]
const tabIndex = ref(0)
const curStatus = computed(() => tabs[tabIndex.value].value)

const accounts = ref<any[]>([])
const accountOptions = computed(() =>
    accounts.value.map(a => ({ value: a.id, label: `${a.account_name}（余额 ¥${money(a.balance)}）` }))
)

const payVisible = ref(false)
const paying = ref(false)
const payRow = ref<any>(null)
const payForm = ref({ amount: 0, capital_account_id: 0, remark: '' })
const canPay = computed(() =>
    Number(payForm.value.amount) > 0 &&
    Number(payForm.value.amount) <= Number(payRow.value?.remain_amount || 0) + 0.001 &&
    payForm.value.capital_account_id > 0
)
const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '12rpx 16rpx' }

onMounted(async () => {
    try {
        const res: any = await getMobileCapitalAccounts()
        accounts.value = res?.data?.data || []
    } catch {}
})

const reload = () => pagingRef.value?.reload()
const onTab = (item: any) => { if (tabIndex.value === item.index) return; tabIndex.value = item.index; reload() }

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobilePayableList({
            keyword: keyword.value, status: curStatus.value,
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
</style>
