<template>
    <view class="order-create">
        <!-- 任务类型 -->
        <view class="task-type-bar">
            <text class="task-type-name">{{ taskTypeName }}</text>
        </view>

        <!-- 取件地址 -->
        <view class="address-section">
            <view class="section-title">
                <text class="dot pickup"></text>
                <text>取件信息</text>
            </view>
            <view class="address-card" @click="choosePickupAddress">
                <view class="address-info" v-if="formData.pickup_address">
                    <text class="name">{{ formData.pickup_name }}</text>
                    <text class="mobile">{{ formData.pickup_mobile }}</text>
                    <text class="address">{{ formData.pickup_address }}</text>
                </view>
                <view class="address-empty" v-else>
                    <text>请选择取件地址</text>
                </view>
                <text class="iconfont icon-arrow-right"></text>
            </view>
        </view>

        <!-- 收件地址 -->
        <view class="address-section">
            <view class="section-title">
                <text class="dot receive"></text>
                <text>收件信息</text>
            </view>
            <view class="address-card" @click="chooseReceiveAddress">
                <view class="address-info" v-if="formData.receive_address">
                    <text class="name">{{ formData.receive_name }}</text>
                    <text class="mobile">{{ formData.receive_mobile }}</text>
                    <text class="address">{{ formData.receive_address }}</text>
                </view>
                <view class="address-empty" v-else>
                    <text>请选择收件地址</text>
                </view>
                <text class="iconfont icon-arrow-right"></text>
            </view>
        </view>

        <!-- 快递信息(仅代取快递显示) -->
        <view class="express-section" v-if="taskType === 'EXPRESS'">
            <view class="section-title">快递信息</view>
            <view class="form-item">
                <text class="label">快递公司</text>
                <input v-model="formData.express_company" placeholder="请输入快递公司" />
            </view>
            <view class="form-item">
                <text class="label">快递单号</text>
                <input v-model="formData.express_no" placeholder="请输入快递单号" />
            </view>
            <view class="form-item">
                <text class="label">取件码</text>
                <input v-model="formData.pickup_code" placeholder="请输入取件码" />
            </view>
        </view>

        <!-- 商品信息(代买服务显示) -->
        <view class="goods-section" v-if="taskType === 'BUY'">
            <view class="section-title">商品信息</view>
            <view class="form-item">
                <text class="label">商品名称</text>
                <input v-model="formData.goods_name" placeholder="请输入商品名称" />
            </view>
            <view class="form-item">
                <text class="label">商品图片</text>
                <xy-upload v-model="formData.goods_image" :maxCount="1" />
            </view>
        </view>

        <!-- 任务描述 -->
        <view class="desc-section">
            <view class="section-title">任务描述</view>
            <textarea 
                v-model="formData.task_desc" 
                placeholder="请描述您的需求，如：帮我取一个中等大小的快递"
                maxlength="200"
            ></textarea>
        </view>

        <!-- 备注 -->
        <view class="remark-section">
            <view class="section-title">备注信息</view>
            <textarea 
                v-model="formData.remark" 
                placeholder="其他需要告知骑手的信息"
                maxlength="100"
            ></textarea>
        </view>

        <view class="remark-section">
            <xy-order-yinsi-field v-model="formData.yinsi_text" />
        </view>

        <!-- 加急选项 -->
        <view class="options-section">
            <view class="option-item" @click="toggleUrgent">
                <text>加急配送</text>
                <view class="option-right">
                    <text class="price">+¥{{ urgentFee }}</text>
                    <switch :checked="formData.is_urgent === 1" color="#c0fe95" />
                </view>
            </view>
        </view>

        <!-- 费用明细 -->
        <view class="fee-section">
            <view class="section-title">费用明细</view>
            <view class="fee-item">
                <text>基础费用</text>
                <text>¥{{ feeInfo.base_fee }}</text>
            </view>
            <view class="fee-item" v-if="feeInfo.distance_fee > 0">
                <text>距离费用</text>
                <text>¥{{ feeInfo.distance_fee }}</text>
            </view>
            <view class="fee-item" v-if="feeInfo.weight_fee > 0">
                <text>重量费用</text>
                <text>¥{{ feeInfo.weight_fee }}</text>
            </view>
            <view class="fee-item" v-if="formData.is_urgent">
                <text>加急费用</text>
                <text>¥{{ feeInfo.urgent_fee }}</text>
            </view>
            <view class="fee-total">
                <text>合计</text>
                <text class="total-price">¥{{ totalFee }}</text>
            </view>
        </view>

        <!-- 底部占位符 -->
        <view style="height: 200rpx;"></view>

        <!-- 底部提交 -->
        <view class="submit-bar">
            <view style="flex:1"></view>
            <button class="submit-btn2" @click="submitOrder">提交订单</button>
        </view>

        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { createOrder, calculateFee } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import { onLoad } from '@dcloudio/uni-app'
import pay from '@/components/pay/pay.vue'
import xyUpload from '../../components/xy-upload.vue'
import XyOrderYinsiField from '../../components/xy-order-yinsi-field.vue'

const taskType = ref('')
const taskTypeName = ref('')
const urgentFee = ref(5)
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const formData = ref({
    task_type: '',
    school_id: 0,
    campus: '',
    pickup_name: '',
    pickup_mobile: '',
    pickup_address: '',
    pickup_lng: '',
    pickup_lat: '',
    receive_name: '',
    receive_mobile: '',
    receive_address: '',
    receive_lng: '',
    receive_lat: '',
    express_company: '',
    express_no: '',
    pickup_code: '',
    goods_name: '',
    goods_image: '',
    task_desc: '',
    remark: '',
    yinsi_text: '',
    distance: 0,
    weight: 0,
    is_urgent: 0,
    is_appointment: 0,
    appointment_time: 0,
    tip_fee: 0
})

const feeInfo = ref({
    base_fee: 3,
    distance_fee: 0,
    weight_fee: 0,
    urgent_fee: 0,
    total_fee: 3
})

const taskTypeMap: Record<string, string> = {
    'EXPRESS': '代取快递',
    'BUY': '代买服务',
    'ERRAND': '跑腿服务',
    'QUEUE': '代排队',
    'CLASS': '代上课',
    'PRINT': '代打印',
    'SEAT': '代占座'
}

const totalFee = computed(() => {
    return feeInfo.value.total_fee.toFixed(2)
})

onLoad((options: any) => {
    tryBindFenxiao()
    taskType.value = options?.task_type || 'EXPRESS'
    formData.value.task_type = taskType.value
    taskTypeName.value = taskTypeMap[taskType.value] || '跑腿服务'
    
    const cachedSchool = uni.getStorageSync('current_school')
    if (cachedSchool) {
        formData.value.school_id = cachedSchool.id || 0
        formData.value.campus = cachedSchool.campus || ''
    }
    
    loadFee()
    loadUrgentFee()
})

const toggleUrgent = () => {
    formData.value.is_urgent = formData.value.is_urgent ? 0 : 1
    loadFee()
}

const loadUrgentFee = async () => {
    try {
        const res: any = await calculateFee({ distance: 0, weight: 0, is_urgent: 1 })
        if (res.code === 1 && res.data.urgent_fee > 0) {
            urgentFee.value = res.data.urgent_fee
        }
    } catch (e) { /* ignore */ }
}

const loadFee = async () => {
    try {
        const res: any = await calculateFee({
            distance: formData.value.distance,
            weight: formData.value.weight,
            is_urgent: formData.value.is_urgent
        })
        if (res.code === 1) {
            feeInfo.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const choosePickupAddress = () => {
    uni.$once('onAddressSelect', (address: any) => {
        formData.value.pickup_name = address.name
        formData.value.pickup_mobile = address.mobile
        formData.value.pickup_address = address.address
        formData.value.pickup_lng = address.lng
        formData.value.pickup_lat = address.lat
        calculateDistance()
    })
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select?type=pickup'
    })
}

const chooseReceiveAddress = () => {
    uni.$once('onAddressSelect', (address: any) => {
        formData.value.receive_name = address.name
        formData.value.receive_mobile = address.mobile
        formData.value.receive_address = address.address
        formData.value.receive_lng = address.lng
        formData.value.receive_lat = address.lat
        calculateDistance()
    })
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select?type=receive'
    })
}

const calculateDistance = () => {
    if (formData.value.pickup_lng && formData.value.receive_lng) {
        const lat1 = parseFloat(formData.value.pickup_lat)
        const lng1 = parseFloat(formData.value.pickup_lng)
        const lat2 = parseFloat(formData.value.receive_lat)
        const lng2 = parseFloat(formData.value.receive_lng)
        
        const radLat1 = lat1 * Math.PI / 180.0
        const radLat2 = lat2 * Math.PI / 180.0
        const a = radLat1 - radLat2
        const b = lng1 * Math.PI / 180.0 - lng2 * Math.PI / 180.0
        let s = 2 * Math.asin(Math.sqrt(Math.pow(Math.sin(a / 2), 2) + Math.cos(radLat1) * Math.cos(radLat2) * Math.pow(Math.sin(b / 2), 2)))
        s = s * 6378.137
        formData.value.distance = Math.round(s * 100) / 100
        
        loadFee()
    }
}

const submitOrder = async () => {
    if (!formData.value.pickup_address) {
        uni.showToast({ title: '请选择取件地址', icon: 'none' })
        return
    }
    if (!formData.value.receive_address) {
        uni.showToast({ title: '请选择收件地址', icon: 'none' })
        return
    }
    
    try {
        uni.showLoading({ title: '提交中...' })
        
        // 设置费用信息
        formData.value.distance = Math.max(formData.value.distance, 1) // 确保最小距离为1km
        formData.value.weight = Math.max(formData.value.weight, 1) // 确保最小重量为1kg
        
        const res: any = await createOrder({
            ...formData.value,
            tip_fee: totalFee.value // 使用计算出的总费用作为tip_fee
        })
        uni.hideLoading()
        
        if (res.code === 1) {
            currentOrderId.value = res.data.id
            // 使用框架支付组件
            payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
        } else {
            uni.showToast({ title: res.msg || '下单失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

// 支付成功回调
const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: `/addon/sd_xiaoyuan/pages/order/detail?id=${currentOrderId.value}`
        })
    }, 1500)
}

// 支付失败回调
const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.order-create {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 150rpx;
}

.task-type-bar {
    background: linear-gradient(135deg, #c0fe95, #88f78d);
    padding: 24rpx;
    
    .task-type-name {
        color: #333;
        font-size: 30rpx;
        font-weight: bold;
    }
}

.address-section, .express-section, .goods-section, .desc-section, .remark-section, .options-section, .fee-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
}

.section-title {
    display: flex;
    align-items: center;
    font-size: 30rpx;
    font-weight: bold;
    margin-bottom: 20rpx;
    
    .dot {
        width: 16rpx;
        height: 16rpx;
        border-radius: 50%;
        margin-right: 16rpx;
        
        &.pickup {
            background: #c0fe95;
        }
        
        &.receive {
            background: #000;
        }
    }
}

.address-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
}

.address-info {
    flex: 1;
    
    .name, .mobile {
        font-size: 28rpx;
        color: #333;
        margin-right: 20rpx;
    }
    
    .address {
        display: block;
        font-size: 26rpx;
        color: #666;
        margin-top: 10rpx;
    }
}

.address-empty {
    color: #999;
    font-size: 28rpx;
}

.form-item {
    display: flex;
    align-items: center;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child {
        border-bottom: none;
    }
    
    .label {
        width: 160rpx;
        font-size: 28rpx;
        color: #333;
    }
    
    input {
        flex: 1;
        font-size: 28rpx;
    }
}

.upload-btn {
    width: 160rpx;
    height: 160rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    
    image {
        width: 100%;
        height: 100%;
        border-radius: 12rpx;
    }
    
    .iconfont {
        font-size: 60rpx;
        color: #ccc;
    }
}

textarea {
    width: 100%;
    height: 160rpx;
    font-size: 28rpx;
    padding: 20rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    box-sizing: border-box;
}

.option-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    
    .option-right {
        display: flex;
        align-items: center;
        
        .price {
            color: #ff6b00;
            margin-right: 20rpx;
        }
    }
}

.fee-item {
    display: flex;
    justify-content: space-between;
    padding: 16rpx 0;
    font-size: 28rpx;
    color: #666;
}

.fee-total {
    display: flex;
    justify-content: space-between;
    padding-top: 20rpx;
    border-top: 1rpx solid #f0f0f0;
    margin-top: 10rpx;
    
    .total-price {
        font-size: 30rpx;
        color: #ff6b00;
        font-weight: bold;
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;z-index: 22;
    background: #fff;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 -2rpx 20rpx rgba(0, 0, 0, 0.05);
    
    .price-info {
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
        border: none;
        border-radius: 40rpx;
        padding: 16rpx 60rpx;
        font-size: 28rpx;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 80rpx;
    }
}
</style>
