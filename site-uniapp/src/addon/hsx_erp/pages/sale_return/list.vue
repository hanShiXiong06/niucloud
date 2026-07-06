<template>
    <view class="erp-page">
        <ErpListHeader
            v-model:activeTab="activeTab"
            :tabs="tabs"
            :showSearch="false"
            @tab-change="onTab"
        />
        <z-paging ref="pagingRef" v-model="list" @query="queryList" :fixed="true"
            :default-page-size="15" :style="pagingStyle">
            <template #empty><u-empty mode="list" text="暂无退货记录" /></template>
            <view class="list-wrap">
                <view v-for="row in list" :key="row.id" class="erp-card">
                    <view class="erp-card__head">
                        <text class="card-title">{{ row.party_name }}</text>
                        <u-tag :text="statusLabel(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                    </view>
                    <view class="card-meta">退货单：{{ row.return_no }}</view>
                    <view class="card-meta">原销售单：{{ row.sale_no || '-' }}</view>
                    <view class="card-meta">退款金额：¥{{ money(row.total_amount) }} · {{ refundLabel(row.refund_mode) }}</view>
                    <view class="card-meta" v-if="row.remark">备注：{{ row.remark }}</view>
                    <view class="card-time">{{ erpTimeLine(row, ['returned_at', 'return_at', 'confirmed_at']) }}</view>
                    <view v-if="row.status === 'pending'" class="status-row" style="margin-top:16rpx">
                        <u-button type="primary" size="small" :loading="confirming === row.id" @click="doConfirm(row)">财务确认退货</u-button>
                        <u-button type="error" size="small" plain @click="doCancel(row)">撤销</u-button>
                    </view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'

import { getMobileSaleReturnList, confirmMobileSaleReturn, cancelMobileSaleReturn } from '@/addon/hsx_erp/api/erp'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import { erpTimeLine } from '@/addon/hsx_erp/hooks/useErpTime'

const { pagingStyle } = useListHeader(126)


const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const confirming = ref(0)

const tabs = [
    { label: '待确认', value: 'pending' },
    { label: '已确认', value: 'confirmed' },
    { label: '已撤销', value: 'cancelled' },
    { label: '全部', value: '' },
]
const activeTab = ref('pending')
const reload = () => pagingRef.value?.reload()
const onTab = (val: string) => { activeTab.value = val; reload() }

onShow(() => reload())

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getMobileSaleReturnList({ status: activeTab.value, page: pageNo, limit: pageSize })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}

async function doConfirm(row: any) {
    uni.showModal({
        title: '财务确认退货',
        content: `确认后设备将回到库存，应收账款同步处理。\n金额：¥${money(row.total_amount)}`,
        confirmText: '确认', cancelText: '取消',
        success: async (res) => {
            if (!res.confirm) return
            confirming.value = row.id
            try {
                await confirmMobileSaleReturn(row.id)
                uni.showToast({ title: '退货已确认', icon: 'success' })
                reload()
            } catch (e: any) {
                uni.showToast({ title: e?.message || '确认失败', icon: 'none' })
            } finally { confirming.value = 0 }
        }
    })
}

async function doCancel(row: any) {
    uni.showModal({
        title: '撤销退货单',
        content: '撤销后退货单关闭，不影响销售单和库存。',
        confirmText: '确认撤销', cancelText: '取消',
        success: async (res) => {
            if (!res.confirm) return
            try {
                await cancelMobileSaleReturn(row.id)
                uni.showToast({ title: '已撤销', icon: 'success' })
                reload()
            } catch (e: any) { uni.showToast({ title: e?.message || '撤销失败', icon: 'none' }) }
        }
    })
}

const money = (v: any) => Number(v || 0).toFixed(2)
const statusLabel = (s: string) => ({ pending: '待财务确认', confirmed: '已确认', cancelled: '已撤销' }[s] || s || '-')
const statusType = (s: string) => ({ pending: 'warning', confirmed: 'success', cancelled: 'info' }[s] || 'info')
const refundLabel = (s: string) => ({ cash: '现金退回', offset: '应付冲减' }[s] || s || '-')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
</style>
