<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            placeholder="客户/销售单号"
            :tabs="tabs"
            @search="handleSearch"
            @tab-change="onTab"
        />

        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15">
            <template #empty><u-empty mode="list" text="暂无应收记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card">
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.party_name }}</text>
                        <u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta">单据：{{ row.batch_no || row.sale_no || row.source_no || '-' }}</view>
                    <view class="card-meta" v-if="row.salesman_name">销售员：{{ row.salesman_name }}</view>
                    <view class="card-meta" v-if="row.item_count">共 {{ row.item_count }} 台设备</view>
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
                    <view v-if="row.status !== 'settled' && row.status !== 'void' && Number(row.remain_amount) > 0" style="margin-top:16rpx;display:flex;gap:12rpx">
                        <u-button type="primary" size="small" @click="openDetailReceipt(row)">逐台收款</u-button>
                        <u-button type="success" size="small" plain @click="openReceipt(row)">整体收款</u-button>
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
                    <text class="pay-info__sub">{{ receiptRow.batch_no || receiptRow.sale_no }}</text>
                    <text class="pay-info__remain">剩余应收：¥{{ money(receiptRow.remain_amount) }}</text>
                </view>
                <view class="pay-form">
                    <view class="pay-form__item">
                        <text class="pay-label">本次收款金额</text>
                        <u-input v-model="receiptForm.amount" type="number" :placeholder="'最多 ¥'+money(receiptRow.remain_amount)" :customStyle="inputStyle" />
                    </view>
                    <view class="pay-form__item">
                        <text class="pay-label">收款账户</text>
                        <u-select v-model="receiptForm.capital_account_id" :list="accountOptions" />
                    </view>
                    <view class="pay-form__item">
                        <text class="pay-label">备注</text>
                        <u-input v-model="receiptForm.remark" placeholder="如：微信/现金/银行卡" :customStyle="inputStyle" />
                    </view>
                </view>
                <view class="action-bar">
                    <u-button @click="receiptVisible = false" :customStyle="{flex:'1'}">取消</u-button>
                    <u-button type="primary" :loading="receipting" :disabled="!canReceipt" @click="submitReceipt" :customStyle="{flex:'2'}">确认收款并记账</u-button>
                </view>
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
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobileReceivableList, confirmMobileSaleReceipt, getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpReceiptConfirmModal from '@/addon/hsx_erp/components/ErpReceiptConfirmModal.vue'

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)

const tabs = [
    { label: '待收款', value: 'pending' },
    { label: '部分收款', value: 'partial' },
    { label: '全部', value: '' },
]
const activeTab = ref('pending')

const accounts = ref<any[]>([])
const accountOptions = computed(() =>
    accounts.value.map(a => ({ value: a.id, label: `${a.account_name}（余额 ¥${money(a.balance)}）` }))
)

const receiptVisible = ref(false)
const receipting = ref(false)
const receiptRow = ref<any>(null)
const receiptForm = ref({ amount: 0, capital_account_id: 0, remark: '' })
const detailReceiptVisible = ref(false)
const detailReceiptRow = ref<any>(null)
const canReceipt = computed(() =>
    Number(receiptForm.value.amount) > 0 &&
    Number(receiptForm.value.amount) <= Number(receiptRow.value?.remain_amount || 0) + 0.001 &&
    receiptForm.value.capital_account_id > 0
)
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
            page: pageNo, limit: pageSize
        })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
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

function openDetailReceipt(row: any) {
    detailReceiptRow.value = row
    detailReceiptVisible.value = true
}

async function submitReceipt() {
    if (!canReceipt.value || !receiptRow.value) return
    receipting.value = true
    try {
        await confirmMobileSaleReceipt(receiptRow.value.id, {
            amount: Number(receiptForm.value.amount),
            capital_account_id: receiptForm.value.capital_account_id,
            remark: receiptForm.value.remark || '手机端确认收款',
        })
        uni.showToast({ title: '收款已确认', icon: 'success' })
        receiptVisible.value = false
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '收款失败，请重试', icon: 'none' })
    } finally { receipting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
const statusLabel = (s: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', void: '已作废' }[s] || s || '-')
const statusType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.pay-popup { padding: 32rpx; padding-bottom: 0; }
.pay-popup__title { font-size: 34rpx; font-weight: 700; color: #0f172a; margin-bottom: 20rpx; }
.pay-info { background: #f8fafc; border-radius: 12rpx; padding: 20rpx; margin-bottom: 24rpx; }
.pay-info__name { font-size: 30rpx; font-weight: 600; color: #0f172a; display: block; }
.pay-info__sub { font-size: 24rpx; color: #64748b; margin-top: 4rpx; display: block; }
.pay-info__remain { font-size: 28rpx; color: #2563eb; font-weight: 600; margin-top: 8rpx; display: block; }
.pay-form__item { margin-bottom: 20rpx; }
.pay-label { font-size: 26rpx; color: #374151; display: block; margin-bottom: 8rpx; }
</style>
