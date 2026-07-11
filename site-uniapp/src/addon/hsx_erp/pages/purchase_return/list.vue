<template>
    <view class="erp-page">
        <ErpListHeader
            v-model="keyword"
            v-model:activeTab="activeTab"
            :tabs="tabs"
            placeholder="供货商 / 退货单号 / 采购单号"
            :show-scan="false"
            @search="reload"
            @tab-change="onTab"
        />
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无退货记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card" :class="{ 'direct-return-card': row.refund_mode === 'none' && row.status === 'confirmed' }">
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.party_name }}</text>
                        <u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta">退货单：{{ row.return_no }}</view>
                    <view class="card-meta">原采购单：{{ row.purchase_no || '-' }}</view>
                    <view class="card-meta">退货金额：¥{{ money(row.total_amount) }}</view>
                    <view class="accounting-line" :class="{ 'accounting-line--refund': row.refund_mode !== 'none' }">
                        <text>账务处理</text>
                        <strong>{{ accountingLabel(row) }}</strong>
                    </view>
                    <view v-if="row.refund_mode === 'none' && row.status === 'confirmed'" class="direct-return-tip">未发生实际付款，设备已退出库存，对应设备应付已冲销；无需生成退款应收或资金流水。</view>
                    <view v-else-if="row.status === 'confirmed' && row.refund_mode === 'cash' && row.process_status !== 'refunded'" class="refund-next">
                        <view>
                            <text class="refund-next__title">下一步：确认供应商退款到账</text>
                            <text class="refund-next__sub">退款应收已生成，请到应收款选择收款账户并确认到账。</text>
                        </view>
                        <u-button type="warning" size="small" plain text="去应收款" @click.stop="goRefundReceivable(row)" />
                    </view>
                    <view class="card-meta" v-if="row.remark">备注：{{ row.remark }}</view>
                    <view class="card-time">{{ erpTimeLine(row, ['returned_at', 'return_at', 'confirmed_at']) }}</view>
                    <view v-if="row.status === 'pending'" class="status-row" style="margin-top:16rpx">
                        <u-button type="primary" size="small" plain :loading="confirming === row.id" @click="doConfirm(row)">确认旧退货单</u-button>
                        <u-button type="error" size="small" plain :loading="cancelling === row.id" @click="doCancel(row)">取消退货单</u-button>
                    </view>
                </view>
            </view>
        </z-paging>
        <view class="fab fab--label" @click="goCreate">
            <u-icon name="plus" color="#fff" size="22" />
            <text class="fab__text">新建采退</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobilePurchaseReturnList, confirmMobilePurchaseReturn, cancelMobilePurchaseReturn } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine } from '@/addon/hsx_erp/hooks/useErpTime'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'

const { pagingStyle } = useListHeader(126)

const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const confirming = ref(0)
const cancelling = ref(0)
const keyword = ref('')

const tabs = [
    { label: '全部', value: '' },
    { label: '待确认', value: 'pending' },
    { label: '已完成退货', value: 'confirmed' },
    { label: '已取消', value: 'cancelled' },
]
const activeTab = ref('')
const reload = () => pagingRef.value?.reload()
const onTab = (val: string) => { activeTab.value = val; reload() }
const goCreate = () => uni.navigateTo({ url: '/addon/hsx_erp/pages/purchase/list?mode=return' })

onShow(reload)

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobilePurchaseReturnList({ keyword: keyword.value, status: activeTab.value, page: pageNo, limit: pageSize })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}

async function doConfirm(row: any) {
    if (confirming.value) return
    confirming.value = Number(row.id || 0)
    const confirmed = await confirmErpSensitiveAction({
        title: '确认旧退货单',
        content: `这是旧流程遗留的待确认单。确认后设备将退出库存，并按单台付款事实冲销应付或生成退款应收。\n退货金额：¥${money(row.total_amount)}`,
        confirmText: '确认退货',
        cancelText: '返回检查',
    })
    if (!confirmed) {
        confirming.value = 0
        return
    }
    try {
        await confirmMobilePurchaseReturn(row.id)
        uni.showToast({ title: '退货已确认', icon: 'success' })
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '确认失败', icon: 'none' })
    } finally { confirming.value = 0 }
}

async function doCancel(row: any) {
    if (cancelling.value) return
    cancelling.value = Number(row.id || 0)
    const confirmed = await confirmErpSensitiveAction({
        title: '取消退货单',
        content: '取消后不会执行设备出库和账务处理；采购单与库存保持原状。',
        confirmText: '确认取消',
        cancelText: '暂不取消',
    })
    if (!confirmed) {
        cancelling.value = 0
        return
    }
    try {
        await cancelMobilePurchaseReturn(row.id)
        uni.showToast({ title: '退货单已取消', icon: 'success' })
        reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '取消失败', icon: 'none' })
    } finally {
        cancelling.value = 0
    }
}

function goRefundReceivable(row: any) {
    uni.navigateTo({
        url: `/addon/hsx_erp/pages/receivable/list?keyword=${encodeURIComponent(row.return_no || '')}`
    })
}

const money = (v: any) => Number(v || 0).toFixed(2)
const statusLabel = (s: string) => ({ pending: '待确认', confirmed: '已完成退货', cancelled: '已取消' }[s] || s || '-')
const statusType = (s: string) => ({ pending: 'warning', confirmed: 'success', cancelled: 'info' }[s] || 'info')
function accountingLabel(row: any) {
    if (row.status === 'cancelled') return '已取消，未执行库存与账务处理'
    if (row.status === 'pending') return '待确认，尚未执行库存与账务处理'
    if (row.refund_mode === 'none') return '未付款，已冲销应付'
    if (row.refund_mode === 'offset') return '已转财务折账处理'
    const process = String(row.process_status || '')
    if (process === 'refunded') return '供货方退款已到账'
    if (process === 'partial_refund') return '供货方已部分退款'
    return '已生成退款应收，等待到账'
}
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.direct-return-card { border:2rpx solid #bbf7d0; background:#f7fff9; }
.direct-return-tip { margin-top:12rpx; padding:12rpx 14rpx; border-radius:12rpx; background:#dcfce7; color:#166534; font-size:22rpx; line-height:1.45; }
.accounting-line { display:flex; align-items:center; justify-content:space-between; gap:16rpx; margin-top:14rpx; padding:13rpx 15rpx; border-radius:12rpx; background:#f0fdf4; color:#166534; font-size:21rpx; }
.accounting-line text { color:#64748b; }
.accounting-line strong { font-weight:650; text-align:right; }
.accounting-line--refund { background:#fff7ed; color:#c2410c; }
.refund-next { display:flex; align-items:center; justify-content:space-between; gap:16rpx; margin-top:14rpx; padding:14rpx 16rpx; border-radius:14rpx; background:#fff7ed; }
.refund-next > view { display:flex; min-width:0; flex:1; flex-direction:column; gap:4rpx; }
.refund-next__title { color:#9a3412; font-size:22rpx; font-weight:650; }
.refund-next__sub { color:#c2410c; font-size:19rpx; line-height:1.4; }
</style>
