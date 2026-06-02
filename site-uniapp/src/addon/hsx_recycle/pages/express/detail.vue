<template>
    <view class="detail-page">
        <view v-if="loading" class="loading-box">加载中...</view>
        <template v-else-if="detail">
            <view class="header-card">
                <view class="text-[34rpx] font-bold text-[#fff]">{{ detail.status_text || '-' }}</view>
                <view class="mt-[12rpx] text-[24rpx] text-[rgba(255,255,255,0.85)]">{{ detail.delivery_id || detail.order_no || '-' }}</view>
            </view>

            <view class="section-card">
                <view class="section-title">运单信息</view>
                <view class="info-row"><text>平台订单</text><text>{{ detail.order_no || '-' }}</text></view>
                <view class="info-row"><text>快递公司</text><text>{{ detail.provider_name || detail.express_company || '-' }}</text></view>
                <view class="info-row"><text>快递产品</text><text>{{ detail.product_name || '-' }}</text></view>
                <view class="info-row"><text>支付状态</text><text>{{ detail.payment_status_text || '-' }}</text></view>
                <view class="info-row"><text>创建时间</text><text>{{ formatTime(detail.create_at) }}</text></view>
                <view class="info-row"><text>更新时间</text><text>{{ formatTime(detail.update_at) }}</text></view>
            </view>

            <view class="section-card">
                <view class="section-title">寄收件信息</view>
                <view class="info-row"><text>寄件人</text><text>{{ detail.sender_name || '-' }} {{ detail.sender_mobile || '' }}</text></view>
                <view class="info-row"><text>寄件地址</text><text class="text-right flex-1">{{ joinAddress(detail, 'sender') }}</text></view>
                <view class="info-row"><text>收件人</text><text>{{ detail.receiver_name || '-' }} {{ detail.receiver_mobile || '' }}</text></view>
                <view class="info-row"><text>收件地址</text><text class="text-right flex-1">{{ joinAddress(detail, 'receiver') }}</text></view>
            </view>

            <view class="section-card">
                <view class="section-title">重量与费用</view>
                <view class="info-row"><text>预估重量</text><text>{{ Number(detail.estimated_weight || 0) }} kg</text></view>
                <view class="info-row"><text>实际重量</text><text>{{ Number(detail.actual_weight || 0) }} kg</text></view>
                <view class="info-row"><text>预估费用</text><text>¥{{ formatMoney(detail.estimated_cost) }}</text></view>
                <view class="info-row"><text>实际费用</text><text>¥{{ formatMoney(detail.actual_cost) }}</text></view>
            </view>

            <view class="section-card">
                <view class="section-title">更新实际费用</view>
                <input v-model="actualForm.actual_weight" class="field-input" type="number" placeholder="请输入实际重量（kg）" />
                <input v-model="actualForm.actual_cost" class="field-input mt-[16rpx]" type="number" placeholder="请输入实际费用（元）" />
                <view class="action-btn primary" @click="submitActualInfo">保存实际费用</view>
            </view>
        </template>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getExpressOrderRecordInfo, updateExpressOrderActualInfo } from '@/addon/hsx_recycle/api/express'
import { formatMoney, formatTime, joinAddress } from '@/addon/hsx_recycle/utils/helper'

const loading = ref(true)
const detail = ref<any>(null)
const recordId = ref<number | string>('')

const actualForm = reactive({
    actual_weight: '',
    actual_cost: ''
})

const loadDetail = async () => {
    loading.value = true
    try {
        const res: any = await getExpressOrderRecordInfo(recordId.value)
        detail.value = res.data || null
        actualForm.actual_weight = detail.value?.actual_weight ? String(detail.value.actual_weight) : ''
        actualForm.actual_cost = detail.value?.actual_cost ? String(detail.value.actual_cost) : ''
    } finally {
        loading.value = false
    }
}

const submitActualInfo = async () => {
    await updateExpressOrderActualInfo({
        id: Number(recordId.value),
        actual_weight: Number(actualForm.actual_weight || 0),
        actual_cost: Number(actualForm.actual_cost || 0)
    })
    uni.showToast({ title: '已更新实际费用', icon: 'none' })
    loadDetail()
}

onLoad((option: any) => {
    recordId.value = option?.id || ''
    if (recordId.value) loadDetail()
})
</script>

<style scoped lang="scss">
.detail-page {
    min-height: 100vh;
    background: #f5f7fa;
    padding: 20rpx;
}

.loading-box {
    padding: 160rpx 0;
    text-align: center;
    color: #909399;
    font-size: 26rpx;
}

.header-card {
    background: linear-gradient(135deg, #7c3aed 0%, #9f67ff 100%);
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

.field-input {
    width: 100%;
    box-sizing: border-box;
    background: #f8fafc;
    border-radius: 14rpx;
    padding: 20rpx;
    font-size: 26rpx;
}

.action-btn {
    margin-top: 18rpx;
    height: 76rpx;
    border-radius: 16rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28rpx;
    font-weight: 600;
}

.action-btn.primary {
    background: #2979ff;
    color: #fff;
}
</style>
