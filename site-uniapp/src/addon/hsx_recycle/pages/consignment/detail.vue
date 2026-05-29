<template>
    <view class="detail-page">
        <view v-if="loading" class="loading-box">加载中...</view>
        <template v-else-if="detail">
            <view class="header-card">
                <view class="text-[34rpx] font-bold text-[#fff]">{{ detail.status_name }}</view>
                <view class="mt-[12rpx] text-[24rpx] text-[rgba(255,255,255,0.85)]">
                    {{ detail.consignment_no }}
                </view>
            </view>

            <view class="section-card">
                <view class="section-title">基础信息</view>
                <view class="info-row"><text>来源订单</text><text>{{ detail.source_order_no || '-' }}</text></view>
                <view class="info-row"><text>客户</text><text>{{ detail.member?.nickname || detail.customer_name || '-' }}</text></view>
                <view class="info-row"><text>手机号</text><text @click.stop="makePhoneCall(detail.member?.mobile || detail.customer_phone)">{{ detail.member?.mobile || detail.customer_phone || '-' }}</text></view>
                <view class="info-row"><text>创建时间</text><text>{{ formatTime(detail.create_time) }}</text></view>
            </view>

            <view class="section-card">
                <view class="section-title">设备信息</view>
                <view class="info-row"><text>设备型号</text><text>{{ detail.device_model || getSourceDevice('model') || '-' }}</text></view>
                <view class="info-row"><text>IMEI</text><text>{{ detail.device_imei || getSourceDevice('imei') || '-' }}</text></view>
                <view class="info-row"><text>备用串号</text><text>{{ getSourceDevice('imei2') || getSourceDevice('sn') || '-' }}</text></view>
                <view class="info-row"><text>容量/颜色</text><text>{{ getSourceDevice('capacity') || '-' }} {{ getSourceDevice('color') || '' }}</text></view>
            </view>

            <view class="section-card">
                <view class="section-title">金额信息</view>
                <view class="info-row"><text>原回收报价</text><text>¥{{ formatMoney(detail.quote_price) }}</text></view>
                <view class="info-row"><text>客户期望价</text><text>¥{{ formatMoney(detail.expected_price) }}</text></view>
                <view class="info-row"><text>最低结算价</text><text>¥{{ formatMoney(detail.min_settlement_price) }}</text></view>
                <view class="info-row"><text>挂牌价</text><text>¥{{ formatMoney(detail.listing_price) }}</text></view>
                <view class="info-row"><text>成交价</text><text>¥{{ formatMoney(detail.sold_price) }}</text></view>
                <view class="info-row"><text>客户结算</text><text class="text-[#18a058]">¥{{ formatMoney(detail.settlement_amount) }}</text></view>
                <view class="info-row"><text>服务收益</text><text>¥{{ formatMoney(detail.service_fee) }}</text></view>
            </view>

            <view v-if="detail.logs?.length" class="section-card">
                <view class="section-title">操作日志</view>
                <view v-for="item in detail.logs" :key="item.id" class="log-item">
                    <view class="text-[24rpx] text-[#222]">{{ actionName(item.action) }}</view>
                    <view class="text-[22rpx] text-[#666] mt-[6rpx]">{{ item.remark || '无备注' }}</view>
                    <view class="text-[20rpx] text-[#999] mt-[8rpx]">{{ formatTime(item.create_time) }}</view>
                </view>
            </view>

            <view style="height: 120rpx;"></view>

            <view v-if="actions.length" class="action-footer">
                <view
                    v-for="action in actions"
                    :key="action.type"
                    class="action-footer__btn"
                    :class="{ 'action-footer__btn--primary': action.primary, 'action-footer__btn--danger': action.danger }"
                    @click="handleAction(action.type)"
                >
                    {{ action.label }}
                </view>
            </view>
        </template>

        <ListingPopup
            ref="listingPopupRef"
            v-model:visible="listingPopupVisible"
            :defaultPrice="detail?.listing_price"
            @submit="handleListingSubmit"
        />

        <SoldPopup
            ref="soldPopupRef"
            v-model:visible="soldPopupVisible"
            @submit="handleSoldSubmit"
        />

        <SettlePopup
            ref="settlePopupRef"
            v-model:visible="settlePopupVisible"
            :defaultAmount="detail?.settlement_amount"
            @submit="handleSettleSubmit"
        />

        <ClosePopup
            ref="closePopupRef"
            v-model:visible="closePopupVisible"
            @submit="handleCloseSubmit"
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import {
    closeConsignment,
    getConsignmentOrderInfo,
    markConsignmentSold,
    pushConsignmentNotify,
    settleConsignment,
    updateConsignmentListing
} from '@/addon/hsx_recycle/api/consignment'
import { formatMoney, formatTime, makePhoneCall } from '@/addon/hsx_recycle/utils/helper'
import ListingPopup from './components/ListingPopup.vue'
import SoldPopup from './components/SoldPopup.vue'
import SettlePopup from './components/SettlePopup.vue'
import ClosePopup from './components/ClosePopup.vue'
import { useRecyclePrintActions } from '@/addon/hsx_recycle/hooks/useRecyclePrintActions'

const loading = ref(true)
const detail = ref<any>(null)
const consignmentId = ref<number | string>('')

const listingPopupVisible = ref(false)
const soldPopupVisible = ref(false)
const settlePopupVisible = ref(false)
const closePopupVisible = ref(false)

const listingPopupRef = ref()
const soldPopupRef = ref()
const settlePopupRef = ref()
const closePopupRef = ref()

const statusValue = computed(() => Number(detail.value?.status || 0))
const {
    loadManualPrintActions,
    getVisiblePrintActions,
    executePrintAction
} = useRecyclePrintActions('consignment')

const actions = computed(() => {
    const result: Array<{ type: string, label: string, primary?: boolean, danger?: boolean }> = []

    if (!detail.value) return result

    // 状态 < 4 可以设置挂牌价
    if (statusValue.value < 4) {
        result.push({ type: 'listing', label: '设置挂牌价', primary: true })
    }

    // 状态 0,1,2 可以登记售出
    if ([0, 1, 2].includes(statusValue.value)) {
        result.push({ type: 'sold', label: '登记售出', primary: true })
    }

    // 状态 3 可以结算
    if (statusValue.value === 3) {
        result.push({ type: 'settle', label: '结算客户', primary: true })
    }

    // 状态 < 4 可以取消
    if (statusValue.value < 4) {
        result.push({ type: 'close', label: '取消代卖', danger: true })
    }

    // 推送通知
    result.push({ type: 'notify', label: '推送通知' })

    getVisiblePrintActions(detail.value).forEach((action: any) => {
        result.push({
            type: `print:${ action.scene_key }`,
            label: action.button_text || action.scene_name || '打印'
        })
    })

    return result
})

const loadDetail = async () => {
    loading.value = true
    try {
        const res: any = await getConsignmentOrderInfo(consignmentId.value)
        detail.value = res.data || null
    } finally {
        loading.value = false
    }
}

const getSourceDevice = (key: string) => {
    return detail.value?.sourceDevice?.[key] || detail.value?.source_device?.[key] || ''
}

const actionName = (action: string) => {
    const map: Record<string, string> = {
        create: '转入代卖',
        listing: '设置挂牌价',
        sold: '登记成交',
        settle: '结算客户',
        cancel: '取消代卖',
        notify: '推送通知',
        return: '退回客户'
    }
    return map[action] || action
}

const handleAction = (type: string) => {
    if (type.startsWith('print:')) {
        const sceneKey = type.replace('print:', '')
        const action = getVisiblePrintActions(detail.value).find((item: any) => item.scene_key === sceneKey)
        if (!action) {
            uni.showToast({ title: '打印场景不可用', icon: 'none' })
            return
        }
        executePrintAction(action, { consignment_id: consignmentId.value, biz_id: consignmentId.value })
        return
    }

    switch (type) {
        case 'listing':
            listingPopupVisible.value = true
            break
        case 'sold':
            soldPopupVisible.value = true
            break
        case 'settle':
            settlePopupVisible.value = true
            break
        case 'close':
            closePopupVisible.value = true
            break
        case 'notify':
            submitNotify()
            break
    }
}

const handleListingSubmit = async (data: any) => {
    listingPopupRef.value?.setSubmitting(true)
    try {
        await updateConsignmentListing(consignmentId.value, {
            listing_price: Number(data.listing_price || 0),
            remark: data.remark
        })
        uni.showToast({ title: '挂牌价已更新', icon: 'success' })
        listingPopupVisible.value = false
        loadDetail()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || '操作失败', icon: 'none' })
    } finally {
        listingPopupRef.value?.setSubmitting(false)
    }
}

const handleSoldSubmit = async (data: any) => {
    soldPopupRef.value?.setSubmitting(true)
    try {
        await markConsignmentSold(consignmentId.value, {
            sold_price: Number(data.sold_price || 0),
            settlement_amount: Number(data.settlement_amount || 0),
            remark: data.remark
        })
        uni.showToast({ title: '已登记售出', icon: 'success' })
        soldPopupVisible.value = false
        loadDetail()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || '操作失败', icon: 'none' })
    } finally {
        soldPopupRef.value?.setSubmitting(false)
    }
}

const handleSettleSubmit = async (data: any) => {
    settlePopupRef.value?.setSubmitting(true)
    try {
        await settleConsignment(consignmentId.value, {
            settlement_amount: Number(data.settlement_amount || 0),
            remark: data.remark
        })
        uni.showToast({ title: '结算完成', icon: 'success' })
        settlePopupVisible.value = false
        loadDetail()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || '操作失败', icon: 'none' })
    } finally {
        settlePopupRef.value?.setSubmitting(false)
    }
}

const handleCloseSubmit = async (data: any) => {
    closePopupRef.value?.setSubmitting(true)
    try {
        await closeConsignment(consignmentId.value, { remark: data.remark })
        uni.showToast({ title: '代卖已取消', icon: 'success' })
        closePopupVisible.value = false
        loadDetail()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || '操作失败', icon: 'none' })
    } finally {
        closePopupRef.value?.setSubmitting(false)
    }
}

const submitNotify = async () => {
    try {
        await pushConsignmentNotify(consignmentId.value)
        uni.showToast({ title: '已推送通知', icon: 'success' })
    } catch (error: any) {
        uni.showToast({ title: error?.msg || '推送失败', icon: 'none' })
    }
}

onLoad((option: any) => {
    consignmentId.value = option?.id || ''
    loadManualPrintActions()
    if (consignmentId.value) loadDetail()
})
</script>

<style scoped lang="scss">
.detail-page {
    min-height: 100vh;
    background: #f5f7fa;
    padding: 20rpx;
    padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}

.loading-box {
    padding: 160rpx 0;
    text-align: center;
    color: #909399;
    font-size: 26rpx;
}

.header-card {
    background: linear-gradient(135deg, #2979ff 0%, #4c94ff 100%);
    border-radius: 20rpx;
    padding: 30rpx;
}

.section-card {
    margin-top: 20rpx;
    background: #fff;
    border-radius: 18rpx;
    padding: 24rpx;
}

.section-title {
    font-size: 28rpx;
    font-weight: 600;
    color: #222;
    margin-bottom: 18rpx;
}

.info-row {
    display: flex;
    justify-content: space-between;
    gap: 20rpx;
    padding: 12rpx 0;
    font-size: 24rpx;
    color: #666;
    border-bottom: 1rpx solid #f4f4f4;
}

.info-row:last-child {
    border-bottom: 0;
}

.log-item {
    padding: 18rpx 0;
    border-bottom: 1rpx solid #f5f5f5;
}

.log-item:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.action-footer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    padding: 20rpx 24rpx calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    border-top: 1rpx solid #e5e7eb;
}

.action-footer__btn {
    flex: 1;
    min-width: 140rpx;
    height: 72rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12rpx;
    font-size: 26rpx;
    font-weight: 500;
    background: #f5f7fa;
    color: #333;
    border: 1rpx solid #e5e7eb;
}

.action-footer__btn--primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.action-footer__btn--danger {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #dc2626;
}
</style>
