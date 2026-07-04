<template>
    <view class="erp-page">
        <RecyclePageHeader title="应收款" />
        <view class="page-search">
            <u-search v-model="keyword" placeholder="客户/销售单号" :showAction="false" bgColor="#f1f5f9" height="34" @search="reload" @clear="reload" />
            <u-tabs :list="tabs" :current="tabIndex" lineColor="#3b6ef5" :activeStyle="{color:'#0f172a',fontWeight:'600'}" :inactiveStyle="{color:'#64748b'}" lineWidth="40" @click="onTab" />
        </view>

        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true" :default-page-size="15" :paging-style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无应收记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card">
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.party_name }}</text>
                        <u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta">单据：{{ row.source_no || row.sale_no || '-' }}</view>
                    <view class="card-meta">销售员：{{ row.salesman_name || '-' }}</view>
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
                            <text class="amt-label">未收</text>
                            <text class="amt-value orange">¥{{ money(row.remain_amount) }}</text>
                        </view>
                    </view>
                    <view v-if="row.status !== 'settled' && row.status !== 'void'" class="mt-16rpx">
                        <u-button type="primary" size="small" @click="openReceipt(row)">确认收款</u-button>
                    </view>
                </view>
            </view>
        </z-paging>

        <!-- 收款弹窗 -->
        <u-popup v-model="receiptVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24">
            <view v-if="receiptRow" class="pay-popup">
                <view class="pay-popup__title">确认收款</view>
                <view class="pay-info">
                    <text class="pay-info__name">{{ receiptRow.party_name }}</text>
                    <text class="pay-info__remain">剩余应收：¥{{ money(receiptRow.remain_amount) }}</text>
                </view>
                <view class="pay-form">
                    <view class="pay-form__item">
                        <text class="pay-label">收款金额</text>
                        <u-input v-model="receiptForm.amount" type="number" placeholder="输入收款金额" :customStyle="inputStyle" />
                    </view>
                    <view class="pay-form__item">
                        <text class="pay-label">收款账户</text>
                        <u-select v-model="receiptForm.capital_account_id" :list="accountOptions" />
                    </view>
                    <view class="pay-form__item">
                        <text class="pay-label">备注</text>
                        <u-input v-model="receiptForm.remark" placeholder="可选备注" :customStyle="inputStyle" />
                    </view>
                </view>
                <view class="action-bar">
                    <u-button @click="receiptVisible = false" :customStyle="{flex:'1'}">取消</u-button>
                    <u-button type="primary" :loading="receipting" :disabled="!canReceipt" @click="submitReceipt" :customStyle="{flex:'2'}">确认收款</u-button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { getMobileReceivableList, confirmMobileSaleReceipt, getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const { pagingStyle } = useListHeader(96)

const tabs = [{ name: '全部', value: '' }, { name: '待收款', value: 'pending' }, { name: '部分收款', value: 'partial' }]
const tabIndex = ref(1)
const curStatus = computed(() => tabs[tabIndex.value].value)

const accounts = ref<any[]>([])
const accountOptions = computed(() => accounts.value.map(a => ({ value: a.id, label: `${a.account_name}（¥${money(a.balance)}）` })))

const receiptVisible = ref(false)
const receipting = ref(false)
const receiptRow = ref<any>(null)
const receiptForm = ref({ amount: 0, capital_account_id: 0, remark: '' })
const canReceipt = computed(() => Number(receiptForm.value.amount) > 0 && receiptForm.value.capital_account_id > 0)
const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx' }

onMounted(async () => {
    const res: any = await getMobileCapitalAccounts()
    accounts.value = res?.data?.data || []
})

const reload = () => pagingRef.value?.reload()
const onTab = (item: any) => { if (tabIndex.value === item.index) return; tabIndex.value = item.index; reload() }

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobileReceivableList({ keyword: keyword.value, status: curStatus.value, page: pageNo, limit: pageSize })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}

function openReceipt(row: any) {
    receiptRow.value = row
    receiptForm.value = { amount: Number(row.remain_amount || 0), capital_account_id: accounts.value[0]?.id || 0, remark: '' }
    receiptVisible.value = true
}

async function submitReceipt() {
    if (!canReceipt.value || !receiptRow.value) return
    receipting.value = true
    try {
        await confirmMobileSaleReceipt(receiptRow.value.id, receiptForm.value)
        uni.showToast({ title: '收款已确认', icon: 'success' })
        receiptVisible.value = false
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '收款失败', icon: 'error' })
    } finally { receipting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
const statusLabel = (s: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', void: '已撤销' }[s] || s)
const statusType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.mt-16rpx { margin-top: 16rpx; }
.pay-popup { padding: 32rpx; padding-bottom: 0; }
.pay-popup__title { font-size: 34rpx; font-weight: 700; color: #0f172a; margin-bottom: 24rpx; }
.pay-info { background: #f8fafc; border-radius: 12rpx; padding: 20rpx; margin-bottom: 24rpx; }
.pay-info__name { font-size: 30rpx; font-weight: 600; color: #0f172a; display: block; }
.pay-info__remain { font-size: 24rpx; color: #2563eb; margin-top: 4rpx; display: block; }
.pay-form__item { margin-bottom: 20rpx; }
.pay-label { font-size: 26rpx; color: #374151; display: block; margin-bottom: 8rpx; }
</style>
