<template>
    <view class="class-create-page">
        <view class="header-bg">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="navbar">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                </view>
                <text class="title">代上课</text>
                <view class="placeholder"></view>
            </view>
            <view class="header-content">
                <view class="header-left">
                    <text class="main-title">帮上课</text>
                    <view class="sub-tag"><text>· 签到·笔记·全程代课 ·</text></view>
                </view>
                <view class="header-right">
                    <u-icon name="bookmark-fill" size="56" color="#5c6bc0"></u-icon>
                </view>
            </view>
        </view>

        <view class="form-container">
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="file-text-fill" size="20" color="#5c6bc0"></u-icon>
                    <text class="section-title">课程名称</text>
                </view>
                <view class="input-wrap">
                    <input class="form-input" v-model="formData.class_subject" maxlength="50" placeholder="如：高等数学、大学英语" />
                </view>
            </view>

            <view class="form-section">
                <view class="section-header">
                    <u-icon name="map-fill" size="20" color="#5c6bc0"></u-icon>
                    <text class="section-title">教学楼类型</text>
                </view>
                <view class="chip-list">
                    <view class="chip-item" :class="{ active: formData.building_type === 'TEACHING' }" @click="formData.building_type = 'TEACHING'">教学楼</view>
                    <view class="chip-item" :class="{ active: formData.building_type === 'LIBRARY' }" @click="formData.building_type = 'LIBRARY'">图书馆</view>
                    <view class="chip-item" :class="{ active: formData.building_type === 'LAB' }" @click="formData.building_type = 'LAB'">实验楼</view>
                    <view class="chip-item" :class="{ active: formData.building_type === 'OTHER' }" @click="formData.building_type = 'OTHER'">其他</view>
                </view>
            </view>

            <view class="form-section">
                <view class="section-header">
                    <u-icon name="home-fill" size="20" color="#5c6bc0"></u-icon>
                    <text class="section-title">上课地点</text>
                </view>
                <view class="input-wrap">
                    <input class="form-input" v-model="formData.class_location" maxlength="80" placeholder="教室号/楼层，如：教三201" />
                </view>
            </view>

            <view class="form-section">
                <view class="section-header">
                    <u-icon name="checkmark-circle-fill" size="20" color="#5c6bc0"></u-icon>
                    <text class="section-title">服务内容</text>
                </view>
                <view class="chip-list">
                    <view class="chip-item" :class="{ active: formData.class_duty === 'SIGNIN' }" @click="formData.class_duty = 'SIGNIN'">代为签到</view>
                    <view class="chip-item" :class="{ active: formData.class_duty === 'NOTE' }" @click="formData.class_duty = 'NOTE'">课堂笔记</view>
                    <view class="chip-item" :class="{ active: formData.class_duty === 'FULL' }" @click="formData.class_duty = 'FULL'">全程代课</view>
                </view>
            </view>

            <view class="form-section">
                <view class="section-header">
                    <u-icon name="clock-fill" size="20" color="#5c6bc0"></u-icon>
                    <text class="section-title">课时时长</text>
                </view>
                <view class="chip-list three">
                    <view class="chip-item" :class="{ active: formData.class_duration === '45' }" @click="formData.class_duration = '45'">45分钟</view>
                    <view class="chip-item" :class="{ active: formData.class_duration === '90' }" @click="formData.class_duration = '90'">90分钟</view>
                    <view class="chip-item" :class="{ active: formData.class_duration === '120' }" @click="formData.class_duration = '120'">2小时</view>
                </view>
            </view>

            <view class="form-section">
                <view class="section-header">
                    <u-icon name="calendar-fill" size="20" color="#5c6bc0"></u-icon>
                    <text class="section-title">上课时间</text>
                </view>
                <view class="time-input-group" @click="showTimePicker = true">
                    <view class="time-display">
                        <text class="time-txt" :class="{ isPh: !formData.class_time }">{{ formData.class_time || '请选择上课时间' }}</text>
                    </view>
                    <u-icon name="calendar" size="20" color="#999"></u-icon>
                </view>
            </view>

            <view class="form-section">
                <view class="section-header">
                    <u-icon name="chat-fill" size="20" color="#5c6bc0"></u-icon>
                    <text class="section-title">备注说明</text>
                </view>
                <textarea class="form-textarea" v-model="formData.remark" placeholder="教师要求、座位偏好、需携带物品等" maxlength="200" :auto-height="true" />
            </view>

            <view class="form-section">
                <view class="section-header">
                    <u-icon name="red-packet" size="20" color="#5c6bc0"></u-icon>
                    <text class="section-title">服务价格</text>
                </view>
                <view class="price-input-group">
                    <text class="currency">¥</text>
                    <input class="form-input price-input" v-model="formData.total_fee" type="digit" placeholder="0.00" />
                    <text class="unit">元</text>
                </view>
            </view>

            <xy-order-yinsi-field v-model="yinsiText" />
            <view class="bottom-space"></view>
        </view>

        <view class="submit-bar">
            <view class="submit-btn2" @tap.stop="submitOrder">立即发布</view>
        </view>

        <u-datetime-picker :show="showTimePicker" v-model="selectedTime" mode="datetime" @confirm="onTimeConfirm" @cancel="showTimePicker = false" />
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import XyOrderYinsiField from '../../components/xy-order-yinsi-field.vue'
import { guardPublishPageOnEnter, ensureXiaoyuanSchoolSelected, getXiaoyuanSchoolId, getXiaoyuanSchoolCampus } from '../../composables/useXiaoyuanDefaultSchool'

const statusBarHeight = ref(0)
const payRef = ref<any>(null)
const showTimePicker = ref(false)
const selectedTime = ref(Date.now())
const currentOrderId = ref(0)
const yinsiText = ref('')

const formData = ref({
    class_subject: '',
    class_location: '',
    building_type: 'TEACHING',
    class_duty: 'SIGNIN',
    class_duration: '90',
    class_time: '',
    remark: '',
    total_fee: '',
    task_type: 'CLASS'
})

onMounted(async () => {
    await guardPublishPageOnEnter()
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
})

onLoad(() => {
    tryBindFenxiao()
    const now = new Date()
    now.setHours(now.getHours() + 1)
    selectedTime.value = now.getTime()
    formData.value.class_time = formatDateTime(now)
})

const goBack = () => uni.navigateBack()

const formatDateTime = (date: Date) => {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    const h = String(date.getHours()).padStart(2, '0')
    const mi = String(date.getMinutes()).padStart(2, '0')
    return `${y}-${m}-${d} ${h}:${mi}`
}

const onTimeConfirm = (e: any) => {
    selectedTime.value = e.value
    formData.value.class_time = formatDateTime(new Date(e.value))
    showTimePicker.value = false
}

const validateForm = () => {
    const subject = String(formData.value.class_subject || '').trim()
    const location = String(formData.value.class_location || '').trim()
    const time = String(formData.value.class_time || '').trim()
    const feeStr = String(formData.value.total_fee || '').trim()
    const fee = parseFloat(feeStr)
    if (!subject) return '请填写课程名称'
    if (!location) return '请填写上课地点'
    if (!time) return '请选择上课时间'
    if (!feeStr || Number.isNaN(fee) || fee <= 0) return '请输入服务价格'
    return ''
}

const submitOrder = async () => {
    const tip = validateForm()
    if (tip) {
        uni.showToast({ title: tip, icon: 'none', duration: 2000 })
        return
    }
    const schoolOk = await ensureXiaoyuanSchoolSelected()
    if (!schoolOk) return
    uni.showLoading({ title: '提交中...' })
    const dutyMap: Record<string, string> = { SIGNIN: '代为签到', NOTE: '课堂笔记', FULL: '全程代课' }
    const ext = {
        class_subject: formData.value.class_subject,
        class_location: formData.value.class_location,
        building_type: formData.value.building_type,
        class_duty: formData.value.class_duty,
        class_duty_text: dutyMap[formData.value.class_duty] || '代上课',
        class_duration: formData.value.class_duration,
        class_time: formData.value.class_time
    }
    const orderData = {
        task_type: 'CLASS',
        school_id: getXiaoyuanSchoolId(),
        campus: getXiaoyuanSchoolCampus(),
        goods_name: '代上课服务',
        task_desc: formData.value.class_subject,
        total_fee: parseFloat(formData.value.total_fee),
        remark: formData.value.remark,
        yinsi_text: yinsiText.value,
        ext: JSON.stringify(ext)
    }
    const res: any = await createOrder(orderData)
    uni.hideLoading()
    if (res.code === 1) {
        currentOrderId.value = res.data.id
        payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
    } else {
        uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
    }
}

const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({ url: `/addon/sd_xiaoyuan/pages/order/detail?id=${currentOrderId.value}` })
    }, 1500)
}

const onPayFail = () => uni.showToast({ title: '支付失败', icon: 'none' })
</script>

<style lang="scss" scoped>
$main: #5c6bc0;
$main-light: #e8eaf6;

.class-create-page {
    min-height: 100vh;
    background: #f5f6fa;
    padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}
.header-bg {
    background: linear-gradient(135deg, #e8eaf6, #c5cae9);
    padding-bottom: 24rpx;
}
.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16rpx 30rpx;
    .back-btn, .placeholder { width: 56rpx; }
    .title { font-size: 30rpx; font-weight: 600; color: #333; }
}
.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12rpx 30rpx 8rpx;
    .main-title { display: block; font-size: 40rpx; font-weight: 600; color: #333; margin-bottom: 8rpx; }
    .sub-tag {
        display: inline-block;
        background: linear-gradient(135deg, $main, #7986cb);
        padding: 6rpx 16rpx;
        border-radius: 24rpx;
        text { font-size: 20rpx; color: #fff; }
    }
}
.form-container { padding: 20rpx 24rpx; }
.bottom-space { height: 24rpx; }
.form-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 16rpx;
    box-shadow: 0 2rpx 12rpx rgba(92, 107, 192, 0.06);
    .section-header {
        display: flex;
        align-items: center;
        gap: 10rpx;
        margin-bottom: 16rpx;
        .section-title { font-size: 28rpx; font-weight: 500; color: #333; }
    }
}
.chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    .chip-item {
        padding: 12rpx 28rpx;
        background: #f5f5f5;
        border-radius: 8rpx;
        font-size: 26rpx;
        color: #666;
        border: 2rpx solid transparent;
        line-height: 1.3;
        &.active {
            border-color: $main;
            background: $main-light;
            color: $main;
            font-weight: 500;
        }
    }
}
.input-wrap {
    width: 100%;
}
.form-input {
    width: 100%;
    height: 72rpx;
    box-sizing: border-box;
    padding: 0 20rpx;
    border: 2rpx solid #f0f0f0;
    border-radius: 12rpx;
    font-size: 28rpx;
    background: #f8f8f8;
    &.price-input {
        flex: 1;
        font-size: 30rpx;
        font-weight: 600;
        color: $main;
        background: transparent;
        border: none;
        padding: 0;
        height: 72rpx;
    }
}
.form-textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 20rpx;
    border: 2rpx solid #f0f0f0;
    border-radius: 12rpx;
    font-size: 28rpx;
    min-height: 100rpx;
    background: #f8f8f8;
}
.time-input-group {
    display: flex;
    align-items: center;
    gap: 12rpx;
}
.time-display {
    flex: 1;
    min-height: 72rpx;
    padding: 0 20rpx;
    border: 2rpx solid #f0f0f0;
    border-radius: 12rpx;
    background: #f8f8f8;
    display: flex;
    align-items: center;
    .time-txt { font-size: 28rpx; color: #333; &.isPh { color: #c0c0c0; } }
}
.price-input-group {
    display: flex;
    align-items: center;
    gap: 12rpx;
    padding: 0 20rpx;
    border: 2rpx solid #f0f0f0;
    border-radius: 12rpx;
    background: #f8f8f8;
    .currency { font-size: 28rpx; color: $main; font-weight: 600; }
    .unit { font-size: 26rpx; color: #666; flex-shrink: 0; }
}
.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 22;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 16rpx 30rpx calc(16rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.05);
    .submit-btn2 {
        background: linear-gradient(135deg, $main, #7986cb);
        color: #fff;
        font-size: 26rpx;
        padding: 0 48rpx;
        height: 68rpx;
        line-height: 68rpx;
        border-radius: 34rpx;
        font-weight: 500;
        text-align: center;
    }
}
</style>
