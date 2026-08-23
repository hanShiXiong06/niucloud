<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            placeholder="付款对象 / 设备 IMEI"
            :show-filter="true"
            :filter-count="filterCount"
            :compact-mp="true"
            @search="handleSearch"
            @filter="filterVisible = true"
        >
            <template #below>
                <ErpQuickFilterBar :items="quickFilters" @change="onQuickFilter" />
            </template>
        </ErpListHeader>

        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :paging-style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无应付记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="String(row.party_id)+'_'+String(row.source_type)+'_'+String(row.purchase_order_id)" class="erp-card payable-card">
                    <view class="payable-card__head">
                        <view class="payable-title">
                            <text class="card-title payable-title__name">{{ erpPartyDisplayName(row) }}</text>
                            <text class="payable-title__sub">剩余应付 ¥{{ money(row.remain_amount) }}</text>
                        </view>
                        <u-tag :text="statusLabel(row.finance_status)" :type="statusType(row.finance_status)" plain plainFill size="mini" />
                    </view>

                    <ErpFinanceSourceSummary :row="row" direction="payable" compact />

                    <view class="payable-chips">
                        <view v-if="row.task_assignee_name" class="payable-chip">财务负责人 {{ row.task_assignee_name }}</view>
                        <view v-if="row.purchaser_name" class="payable-chip">业务操作人 {{ row.purchaser_name }}</view>
                        <view v-if="row.warehouse_name" class="payable-chip">{{ row.warehouse_name }}</view>
                        <view v-if="row.payable_count" class="payable-chip muted">{{ row.payable_count }} 笔应付</view>
                    </view>

                    <view class="payable-time">{{ erpTimeLine(row, ['latest_at', 'first_at', 'paid_at', 'pay_at', 'stock_in_at']) }}</view>

                    <view class="erp-card__foot payable-money">
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
                    <view class="detail-entry" @click="openDetail(row)">查看关联设备与结算明细 <text>›</text></view>
                    <view v-if="row.finance_status !== 'settled' && Number(row.remain_amount) > 0" class="card-actions" @click.stop @tap.stop>
                        <view class="card-action-btn main">
                            <u-button type="primary" size="small" text="打款" @click.stop="openDetailPay(row)" />
                        </view>
                        <view v-if="row.can_offset" class="card-action-btn">
                            <u-button type="warning" size="small" plain text="折账" @click.stop="openOffset(row)" />
                        </view>
                    </view>
                    <view v-if="row.can_offset" class="offset-tip">
                        <text>可折账</text>
                        <text>应付 ¥{{ money(row.offset_payable_remain) }} / 应收 ¥{{ money(row.offset_receivable_remain) }}</text>
                    </view>
                </view>
            </view>
        </z-paging>

        <!-- 统一打款弹窗：已付设备只读，未付设备可选 -->
        <ErpPayConfirmModal
            v-if="detailPayRow"
            v-model:show="detailPayVisible"
            :party-id="detailPayRow?.party_id"
            :party-name="detailPayRow?.party_name"
            :purchase-order-id="detailPayRow?.purchase_order_id"
            :source-type="detailPayRow?.source_type || ''"
            :source-row="detailPayRow"
            :accounts="accounts"
            @success="() => { refreshAfterSettlement(); detailPayRow = null }"
        />

        <ErpOffsetConfirmModal
            v-if="offsetRow"
            v-model:show="offsetVisible"
            :party-id="offsetRow?.party_id"
            :party-name="offsetRow?.party_name"
            :accounts="accounts"
            @success="() => { refreshAfterSettlement(); offsetRow = null }"
        />

        <ErpFilterPopup
            v-model:show="filterVisible"
            v-model="filters"
            title="筛选应付款"
            :fields="filterFields"
            @confirm="applyFilter"
            @reset="resetFilter"
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'

import { getMobilePayableList, getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpPayConfirmModal from '@/addon/hsx_erp/components/ErpPayConfirmModal.vue'
import ErpOffsetConfirmModal from '@/addon/hsx_erp/components/ErpOffsetConfirmModal.vue'
import ErpFilterPopup from '@/addon/hsx_erp/components/ErpFilterPopup.vue'
import ErpFinanceSourceSummary from '@/addon/hsx_erp/components/ErpFinanceSourceSummary.vue'
import ErpQuickFilterBar from '@/addon/hsx_erp/components/ErpQuickFilterBar.vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine } from '@/addon/hsx_erp/hooks/useErpTime'
import { erpFinanceSourceFilterOptions } from '@/addon/hsx_erp/hooks/useErpFinanceSource'
import { useErpFinanceOptions } from '@/addon/hsx_erp/hooks/useErpFinanceOptions'
import { useErpSaleChannels } from '@/addon/hsx_erp/hooks/useErpSaleChannels'
import { erpPartyDisplayName } from '@/addon/hsx_erp/hooks/useErpPartyText'

const { pagingStyle } = useListHeader({ tabs: true, compactMp: true })

const keyword = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)

const tabs = [
    { label: '待付款', value: 'pending' },
    { label: '部分付款', value: 'partial' },
    { label: '全部', value: '' },
]
const activeTab = ref('pending')
const quickFilters = computed(() => [
    { key: 'status', label: '付款状态', title: '付款状态', value: activeTab.value, options: tabs },
    { key: 'finance_type_key', label: '支出类型', title: '支出类型', value: filters.value.finance_type_key || '', options: [{ label: '全部类型', value: '' }, ...categoryOptions('expense')] },
    { key: 'business_source_key', label: '业务来源', title: '业务来源', value: filters.value.business_source_key || '', options: [{ label: '全部来源', value: '' }, ...sourceOptions('expense')] },
])
const filterVisible = ref(false)
const filters = ref<Record<string, any>>({})
const { load: loadFinanceOptions, categoryOptions, sourceOptions } = useErpFinanceOptions()
const { options: channelOptions, load: loadSaleChannels } = useErpSaleChannels()
const filterFields = computed(() => [
    { key: 'party_id', label: '付款对象', type: 'party', roleType: 'all', labelKey: 'party_name', placeholder: '请选择供应商/客户/服务商' },
    { key: 'source_type', label: '业务场景', type: 'select', options: [{ label: '全部', value: '' }, ...erpFinanceSourceFilterOptions('payable')] },
    { key: 'finance_type_key', label: '支出类型', type: 'select', options: [{ label: '全部', value: '' }, ...categoryOptions('expense')] },
    { key: 'business_source_key', label: '业务来源', type: 'select', options: [{ label: '全部', value: '' }, ...sourceOptions('expense')] },
    { key: 'channel_code', label: '业务渠道', type: 'select', options: [{ label: '全部', value: '' }, ...channelOptions.value.map(item => ({ label: item.name, value: item.key }))] },
    { key: 'source_no', label: '来源单号', type: 'text', placeholder: '输入采购单/退货单/整备单号' },
    { key: 'm_no', label: '会员号', type: 'text', placeholder: '输入会员号' },
    { key: 'contact_mobile', label: '联系人手机', type: 'text', placeholder: '输入手机号' },
    { key: 'amount', label: '应付金额', type: 'range', minKey: 'min_amount', maxKey: 'max_amount' },
    { key: 'remain', label: '剩余应付', type: 'range', minKey: 'min_remain', maxKey: 'max_remain' },
    { key: 'can_offset', label: '折账状态', type: 'select', options: [
        { label: '全部', value: '' },
        { label: '可折账', value: 1 },
    ] },
    { key: 'date', label: '发生日期', type: 'dateRange', startKey: 'start_at', endKey: 'end_at' },
] as any[])
const filterCount = computed(() => Object.entries(filters.value).filter(([key, v]) => !key.endsWith('_name') && v !== '' && v !== undefined && v !== null).length)
const onQuickFilter = ({ key, value }: { key: string; value: string | number }) => {
    if (key === 'status') return onTab(String(value))
    filters.value = { ...filters.value, [key]: value }
    reload()
}

const accounts = ref<any[]>([])

// 同一入库单只保留一个设备级打款入口。
const detailPayVisible = ref(false)
const detailPayRow = ref<any>(null)
const offsetVisible = ref(false)
const offsetRow = ref<any>(null)

onMounted(async () => {
    loadFinanceOptions().catch(() => undefined)
    loadSaleChannels().catch(() => undefined)
    await loadAccounts()
})

onLoad((query: Record<string, any>) => {
    const status = String(query?.status ?? 'pending').trim()
    const sourceNo = String(query?.source_no || '').trim()
    const routeKeyword = String(query?.keyword || '').trim()
    activeTab.value = ['', 'pending', 'partial'].includes(status) ? status : 'pending'
    if (sourceNo) {
        try { filters.value = { ...filters.value, source_no: decodeURIComponent(sourceNo) } }
        catch { filters.value = { ...filters.value, source_no: sourceNo } }
    }
    if (routeKeyword) {
        try { keyword.value = decodeURIComponent(routeKeyword) }
        catch { keyword.value = routeKeyword }
    }
})

async function loadAccounts() {
    try {
        const res: any = await getMobileCapitalAccounts()
        accounts.value = res?.data?.list || []
    } catch {}
}

const reload = () => pagingRef.value?.reload()
const handleSearch = () => reload()
const onTab = (val: string) => { activeTab.value = val; reload() }

onShow(() => reload())

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobilePayableList({
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

function openDetailPay(row: any) {
    detailPayRow.value = row
    detailPayVisible.value = true
}

function openDetail(row: any) {
    const query = [
        `party_id=${Number(row.party_id || 0)}`,
        `source_type=${encodeURIComponent(String(row.source_type || ''))}`,
        `purchase_order_id=${Number(row.purchase_order_id || 0)}`,
        `party_name=${encodeURIComponent(String(row.party_name || ''))}`,
        `member_name=${encodeURIComponent(String(row.member_name || ''))}`,
    ].join('&')
    uni.navigateTo({ url: `/addon/hsx_erp/pages/payable/detail?${query}` })
}

function openOffset(row: any) {
    if (!row.can_offset) {
        uni.showToast({ title: '该往来单位暂无可折账应收', icon: 'none' })
        return
    }
    offsetRow.value = row
    offsetVisible.value = true
}

async function refreshAfterSettlement() {
    await loadAccounts()
    reload()
}

const money = (v: any) => Number(v || 0).toFixed(2)
const statusLabel = (s: string) => ({ pending: '待付款', partial: '部分付款', settled: '已结清' }[s] || s || '-')
const statusType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success' }[s] || 'info')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';

.payable-card {
    padding: 22rpx 26rpx;
}
.popup-source-wrap { margin:0 28rpx 20rpx; }
.detail-entry { display:flex; align-items:center; justify-content:flex-end; gap:8rpx; margin-top:14rpx; padding-top:14rpx; border-top:1rpx solid #f1f5f9; color:#2563eb; font-size:23rpx; }

.payable-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18rpx;
}

.payable-title {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 8rpx;
}

.payable-title__name,
.payable-title__sub,
.payable-line__value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.payable-title__name {
    display: block;
    line-height: 1.35;
}

.payable-title__sub {
    display: block;
    font-size: 26rpx;
    font-weight: 700;
    line-height: 1.35;
    color: #ea580c;
}

.payable-line {
    display: flex;
    align-items: center;
    gap: 12rpx;
    min-width: 0;
    margin-top: 14rpx;
    font-size: 24rpx;
    line-height: 1.35;
}

.payable-line__label {
    flex-shrink: 0;
    color: #94a3b8;
}

.payable-line__value {
    flex: 1;
    min-width: 0;
    color: #475569;
}

.payable-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx;
    margin-top: 12rpx;
}

.payable-chip {
    max-width: 100%;
    height: 38rpx;
    padding: 0 12rpx;
    border-radius: 19rpx;
    background: #f8fafc;
    color: #64748b;
    font-size: 21rpx;
    line-height: 38rpx;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    box-sizing: border-box;
}

.payable-chip.muted {
    color: #94a3b8;
}

.payable-time {
    margin-top: 10rpx;
    font-size: 22rpx;
    line-height: 1.35;
    color: #94a3b8;
}

.payable-money {
    justify-content: space-between;
    gap: 10rpx;
    margin-top: 14rpx;
    padding-top: 14rpx;
}

.payable-money .amount-box {
    flex: 1;
    min-width: 0;
}

.payable-money .amt-label,
.payable-money .amt-value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.payable-money .amt-value {
    font-size: 27rpx;
}

.card-actions {
    margin-top: 18rpx;
    display: flex;
    gap: 12rpx;
}

.card-action-btn {
    flex: 1;
    min-width: 0;
}

.card-action-btn.main {
    flex: 1.1;
}

.pay-popup {
    height: 82vh;
    max-height: 1080rpx;
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.popup-scroll { flex:1; min-height:0; width:100%; box-sizing:border-box; }
.popup-head {
    min-height: 96rpx;
    padding: 0 28rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
    border-bottom: 1rpx solid #eef2f7;
}
.popup-head.compact {
    border-bottom: none;
}
.popup-title {
    display: block;
    font-size: 34rpx;
    font-weight: 700;
    color: #0f172a;
}
.popup-subtitle {
    display: block;
    margin-top: 4rpx;
    font-size: 24rpx;
    color: #64748b;
}
.popup-close {
    width: 56rpx;
    height: 56rpx;
    border-radius: 28rpx;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.pay-summary {
    margin: 24rpx 28rpx 20rpx;
    padding: 22rpx;
    border-radius: 18rpx;
    background: #f8fafc;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10rpx 16rpx;
    align-items: center;
}
.summary-main {
    min-width: 0;
}
.summary-label {
    display: block;
    font-size: 23rpx;
    color: #64748b;
}
.summary-amount {
    display: block;
    margin-top: 6rpx;
    font-size: 38rpx;
    color: #ea580c;
    font-weight: 800;
}
.summary-sub {
    grid-column: 1 / 3;
    font-size: 23rpx;
    color: #94a3b8;
}
.pay-form {
    padding: 0 28rpx 8rpx;
}
.pay-form__item {
    margin-bottom: 22rpx;
}
.form-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10rpx;
}
.pay-label {
    font-size: 26rpx;
    color: #374151;
    display: block;
    margin-bottom: 10rpx;
    font-weight: 600;
}
.form-label-row .pay-label {
    margin-bottom: 0;
}
.pay-label.required::before {
    content: '*';
    color: #dc2626;
    margin-right: 4rpx;
}
.fill-max {
    font-size: 24rpx;
    color: #3b6ef5;
}
.account-select {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    background: #f8fafc;
    border-radius: 12rpx;
    border: 2rpx solid transparent;
    padding: 0 18rpx;
    min-height: 72rpx;
    box-sizing: border-box;
}
.account-select--on {
    background: #f8fbff;
    border-color: #3b6ef5;
}
.account-text {
    flex: 1;
    min-width: 0;
    font-size: 26rpx;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.account-placeholder {
    flex: 1;
    min-width: 0;
    font-size: 26rpx;
    color: #94a3b8;
}
.account-popup {
    height: 60vh;
    max-height: 820rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}
.account-popup__body { flex:1; min-height:0; width:100%; }
.offset-tip {
    margin-top: 12rpx;
    font-size: 23rpx;
    color: #b45309;
    background: #fff7ed;
    border-radius: 12rpx;
    padding: 12rpx 16rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12rpx;
}
.action-btn {
    min-width: 0;
}
.action-btn--minor {
    flex: 1;
}
.action-btn--major {
    flex: 2;
}
</style>
