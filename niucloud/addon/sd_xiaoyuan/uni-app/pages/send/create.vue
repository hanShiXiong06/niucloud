<template>
    <view class="send-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <!-- 顶部背景 -->
        <view class="header-bg">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="navbar" :style="{ height: navBarHeight + 'px' }">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                </view>
                <text class="title">帮我送</text>
                <view class="placeholder"></view>
            </view>
        </view>

        <!-- 表单内容 -->
        <scroll-view scroll-y class="form-content">
            <!-- 取货地址 -->
            <view class="form-card" @click="selectPickupAddress">
                <view class="card-icon">
                    <u-icon name="map" size="24" color="#52c41a"></u-icon>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!pickupAddress">从哪里取货?</text>
                    <view v-else>
                        <text class="addr-tag pickup">起</text>
                        <text class="value">{{ pickupAddress }}</text>
                    </view>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 送达地址 -->
            <view class="form-card" @click="selectReceiveAddress">
                <view class="card-icon">
                    <u-icon name="map-fill" size="24" color="#1890ff"></u-icon>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!receiveAddress">送到哪里?</text>
                    <view v-else>
                        <text class="addr-tag receive">终</text>
                        <text class="value">{{ receiveAddress }}</text>
                    </view>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 任务描述 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon">
                        <u-icon name="bag" size="24" color="#ff9500"></u-icon>
                    </view>
                    <text class="label">任务描述</text>
                </view>
                <textarea 
                    class="content-input" 
                    v-model="goodsDesc" 
                    placeholder="请描述需要送的物品，如：文件、快递、外卖等"
                    :maxlength="500"
                ></textarea>
                
                <!-- 快捷标签 -->
                <view class="quick-tags">
                    <view 
                        class="tag" 
                        v-for="tag in quickTags" 
                        :key="tag"
                        :class="{ active: selectedTags.includes(tag) }"
                        @click="toggleTag(tag)"
                    >{{ tag }}</view>
                </view>
            </view>

            <!-- 物品图片 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon">
                        <u-icon name="photo" size="24" color="#13c2c2"></u-icon>
                    </view>
                    <text class="label">物品图片</text>
                    <text class="hint">可选，方便接单员识别</text>
                </view>
                <xy-upload v-model="imageStr" :maxCount="6" />
            </view>

            <!-- 时间 -->
            <view class="form-card" @click="showTimePicker = true">
                <view class="card-icon">
                    <u-icon name="clock" size="24" color="#e91e63"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">期望送达时间</text>
                </view>
                <text class="time-value">{{ expectTime || '选择时间' }}</text>
            </view>

            <!-- 是否加急 -->
            <view class="form-card">
                <view class="card-icon">
                    <u-icon name="bell" size="24" color="#f44336"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">加急服务</text>
                    <text class="hint">加急费用+{{ urgentFeeConfig }}元</text>
                </view>
                <u-switch v-model="isUrgent" activeColor="#13c2c2"></u-switch>
            </view>

            <!-- 跑腿费 -->
            <view class="form-card">
                <view class="card-icon">
                    <u-icon name="red-packet" size="24" color="#ff6b00"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">跑腿费</text>
                </view>
                <view class="fee-input-wrap">
                    <text class="fee-unit">¥</text>
                    <input class="fee-input" type="digit" :value="tipFeeInput" @input="onTipFeeInput" placeholder="0" />
                </view>
            </view>

            <!-- 备注 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon">
                        <u-icon name="edit-pen" size="24" color="#666"></u-icon>
                    </view>
                    <text class="label">备注</text>
                    <text class="hint">可选</text>
                </view>
                <textarea 
                    class="content-input small" 
                    v-model="remark" 
                    placeholder="其他需要说明的事项"
                    :maxlength="200"
                ></textarea>
            </view>

            <xy-order-yinsi-field v-model="yinsiText" />

            <!-- 底部占位 -->
            <view style="height: 300rpx;"></view>
        </scroll-view>

        <!-- 底部提交栏 -->
        <view class="submit-bar">
            <view class="price-info">
                <view class="fee-detail">
                    <text class="label">基础费用 ¥{{ baseFee }}</text>
                    <text class="label urgent" v-if="urgentFee > 0"> + 加急 ¥{{ urgentFee }}</text>
                </view>
                <text class="price">¥{{ totalFee }}</text>
            </view>
            <button class="submit-btn2" @click="submitOrder">立即下单</button>
        </view>

        <!-- 时间选择器 -->
        <u-datetime-picker
            :show="showTimePicker"
            v-model="selectedTime"
            mode="datetime"
            @confirm="onTimeConfirm"
            @cancel="showTimePicker = false"
        ></u-datetime-picker>
        
        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { createOrder, getConfig } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import xyUpload from '../../components/xy-upload.vue'
import XyOrderYinsiField from '../../components/xy-order-yinsi-field.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_send')

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const showTimePicker = ref(false)
const selectedTime = ref(Date.now())

const pickupAddress = ref('')
const pickupAddressId = ref(0)
const pickupLng = ref('')
const pickupLat = ref('')
const receiveAddress = ref('')
const receiveAddressId = ref(0)
const receiveLng = ref('')
const receiveLat = ref('')
const goodsDesc = ref('')
const imageStr = ref('')
const expectTime = ref('')
const isUrgent = ref(false)
const tipFeeInput = ref('')
const urgentFeeConfig = ref(5)
const remark = ref('')
const yinsiText = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const quickTags = ['文件', '快递', '外卖', '钥匙', '证件', '书本', '衣物', '其他']
const selectedTags = ref<string[]>([])

const baseFee = computed(() => {
    const fee = parseFloat((tipFeeInput.value || '').trim())
    return Number.isFinite(fee) && fee > 0 ? fee : 0
})
const urgentFee = computed(() => isUrgent.value ? urgentFeeConfig.value : 0)
const totalFee = computed(() => baseFee.value + urgentFee.value)

onMounted(() => {
    loadConfig()
    loadDefaultFee()
    tryBindFenxiao()
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    uni.$on('onAddressSelect', onAddressSelect)
})

const loadDefaultFee = async () => {
    const res: any = await getConfig().catch(() => null)
    const fee = Number(res?.data?.base_fee)
    if (Number.isFinite(fee) && fee > 0 && !tipFeeInput.value) {
        tipFeeInput.value = String(fee)
    } else if (!tipFeeInput.value) {
        tipFeeInput.value = '3'
    }
    const urgent = Number(res?.data?.urgent_fee)
    if (Number.isFinite(urgent) && urgent >= 0) {
        urgentFeeConfig.value = urgent
    }
}

const onTipFeeInput = (e: any) => {
    const raw = String(e?.detail?.value ?? '')
    let val = raw.replace(/[^\d.]/g, '')
    const firstDot = val.indexOf('.')
    if (firstDot >= 0) {
        val = val.slice(0, firstDot + 1) + val.slice(firstDot + 1).replace(/\./g, '')
    }
    const parts = val.split('.')
    if (parts[1] !== undefined) {
        parts[1] = parts[1].slice(0, 2)
        val = `${parts[0]}.${parts[1]}`
    }
    if (val && !val.startsWith('0.') && /^0\d+/.test(val)) {
        val = String(parseInt(val, 10))
    }
    tipFeeInput.value = val
}

onUnmounted(() => {
    uni.$off('onAddressSelect', onAddressSelect)
})

const onAddressSelect = (address: any) => {
    if (address.type === 'pickup') {
        pickupAddress.value = address.address
        pickupAddressId.value = address.id
        pickupLng.value = address.lng || ''
        pickupLat.value = address.lat || ''
    } else {
        receiveAddress.value = address.address
        receiveAddressId.value = address.id
        receiveLng.value = address.lng || ''
        receiveLat.value = address.lat || ''
    }
}

const toggleTag = (tag: string) => {
    const index = selectedTags.value.indexOf(tag)
    if (index === -1) {
        selectedTags.value.push(tag)
        if (!goodsDesc.value.includes(tag)) {
            goodsDesc.value += (goodsDesc.value ? '、' : '') + tag
        }
    } else {
        selectedTags.value.splice(index, 1)
    }
}

const selectPickupAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select?type=pickup'
    })
}

const selectReceiveAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select?type=receive'
    })
}

const onTimeConfirm = (e: any) => {
    const date = new Date(e.value)
    expectTime.value = `${date.getMonth() + 1}-${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`
    showTimePicker.value = false
}

const submitOrder = async () => {
    if (!pickupAddress.value) {
        uni.showToast({ title: '请选择取货地点', icon: 'none' })
        return
    }
    if (!receiveAddress.value) {
        uni.showToast({ title: '请选择送达地址', icon: 'none' })
        return
    }
    if (!goodsDesc.value) {
        uni.showToast({ title: '请描述物品信息', icon: 'none' })
        return
    }
    if (baseFee.value <= 0) {
        uni.showToast({ title: '请输入正确的跑腿费', icon: 'none' })
        return
    }

    const ext = {
        expect_time: expectTime.value,
        quick_tags: selectedTags.value,
        images: imageStr.value
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'ERRAND',
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || '',
            pickup_name: '',
            pickup_mobile: '',
            pickup_address: pickupAddress.value,
            pickup_lng: pickupLng.value,
            pickup_lat: pickupLat.value,
            receive_name: '',
            receive_mobile: '',
            receive_address: receiveAddress.value,
            receive_lng: receiveLng.value,
            receive_lat: receiveLat.value,
            goods_name: goodsDesc.value,
            task_desc: goodsDesc.value,
            images: imageStr.value,
            is_urgent: isUrgent.value ? 1 : 0,
            total_fee: totalFee.value,
            base_fee: baseFee.value,
            urgent_fee: urgentFee.value,
            remark: remark.value,
            yinsi_text: yinsiText.value,
            ext: JSON.stringify(ext)
        })
        uni.hideLoading()
        if (res.code === 1) {
            currentOrderId.value = res.data.id
            payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
        } else {
            uni.showToast({ title: res.msg || '下单失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        console.error('下单错误:', e)
        uni.showToast({ title: e.msg || e.message || '网络错误', icon: 'none' })
    }
}

const goBack = () => {
    uni.navigateBack()
}

const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: `/addon/sd_xiaoyuan/pages/order/detail?id=${currentOrderId.value}`
        })
    }, 1500)
}

const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.send-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.header-bg {
    background: linear-gradient(135deg, #e6fffb, #b5f5ec);
    padding-bottom: 20rpx;
}

.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20rpx 30rpx;
    
    .back-btn, .placeholder {
        width: 60rpx;
    }
    
    .title {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
    }
}

.form-content {
    height: calc(100vh - 200rpx);
    width: auto;
    padding: 20rpx;
}

.form-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    margin-bottom: 20rpx;
    display: flex;
    align-items: center;
    width: auto;
    
    &.column {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-icon {
        width: 50rpx;
        height: 50rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20rpx;
    }
    
    .card-content {
        flex: 1;
        
        .placeholder {
            color: #999;
            font-size: 28rpx;
        }
        
        .value, .label {
            color: #333;
            font-size: 28rpx;
        }
        
        .hint {
            font-size: 24rpx;
            color: #999;
            margin-left: 16rpx;
        }
        
        .addr-tag {
            display: inline-block;
            width: 36rpx;
            height: 36rpx;
            border-radius: 50%;
            font-size: 20rpx;
            color: #fff;
            text-align: center;
            line-height: 36rpx;
            margin-right: 12rpx;
            
            &.pickup { background: #52c41a; }
            &.receive { background: #1890ff; }
        }
    }
    
    .card-header {
        display: flex;
        align-items: center;
        width: 100%;
        margin-bottom: 20rpx;
        
        .label {
            flex: 1;
            font-size: 28rpx;
            color: #333;
            font-weight: bold;
        }
        
        .hint {
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .time-value {
        color: #13c2c2;
        font-size: 28rpx;
    }
}

.content-input {
    width: 94%;
    min-height: 150rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 20rpx;
    font-size: 28rpx;
    margin-bottom: 20rpx;
    
    &.small {
        min-height: 100rpx;
        margin-bottom: 0;
    }
}

.quick-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    
    .tag {
        padding: 12rpx 24rpx;
        background: #f5f5f5;
        border-radius: 30rpx;
        font-size: 26rpx;
        color: #666;
        border: 2rpx solid transparent;
        
        &.active {
            background: #e6fffb;
            color: #13c2c2;
            border-color: #13c2c2;
        }
    }
}

.weight-options {
    display: flex;
    gap: 12rpx;
    
    .weight-btn {
        padding: 10rpx 20rpx;
        background: #f5f5f5;
        border-radius: 20rpx;
        font-size: 24rpx;
        color: #666;
        
        &.active {
            background: #13c2c2;
            color: #fff;
        }
    }
}

.fee-input-wrap {
    display: flex;
    align-items: center;
    background: #f5f5f5;
    border-radius: 8rpx;
    padding: 8rpx 16rpx;
    
    .fee-unit {
        font-size: 28rpx;
        color: #ff6b00;
        font-weight: bold;
    }
    
    .fee-input {
        width: 120rpx;
        text-align: center;
        font-size: 32rpx;
        color: #333;
        font-weight: bold;
        background: transparent;
    }
}

.fee-control {
    display: flex;
    align-items: center;
    
    .fee-btn {
        width: 50rpx;
        height: 50rpx;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28rpx;
        
        &.minus {
            background: #f5f5f5;
            color: #666;
        }
        
        &.plus {
            background: #13c2c2;
            color: #fff;
        }
    }
    
    .fee-value {
        width: 80rpx;
        text-align: center;
        font-size: 32rpx;
        color: #333;
        font-weight: bold;
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;z-index: 22;
    align-items: center;
    justify-content: space-between;
    padding: 20rpx 30rpx;
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    
    .price-info {
        flex: 1;
        
        .label {
            font-size: 26rpx;
            color: #666;
        }
        
        .price {
            font-size: 40rpx;
            color: #ff6b00;
            font-weight: bold;
        }
    }
    
    .submit-btn2 {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        font-size: 28rpx;
        padding: 16rpx 60rpx;
        border-radius: 40rpx;
        border: none;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 80rpx;
    }
    
    .fee-detail {
        display: flex;
        align-items: center;
        margin-bottom: 4rpx;
        
        .urgent {
            color: #f97316;
        }
    }
}
</style>
