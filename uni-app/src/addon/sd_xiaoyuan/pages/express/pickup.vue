<template>
    <view class="express-page">
        <!-- 顶部背景 -->
        <view class="header-bg">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="navbar" :style="{ height: navBarHeight + 'px' }">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                </view>
                <text class="title">取快递</text>
                <view class="placeholder"></view>
            </view>
            <view class="header-content">
                <view class="header-left">
                    <text class="service-tag">SERVICE</text>
                    <text class="main-title">取快递</text>
                    <view class="sub-tag">
                        <text>· 下单立享优质服务 ·</text>
                    </view>
                </view>
                <view class="header-right">
                    <image src="/static/images/express-owl.png" mode="aspectFit"></image>
                </view>
            </view>
        </view>

        <!-- 表单内容 -->
        <scroll-view scroll-y class="form-content">
            <!-- 选择快递站 -->
            <view class="form-card" @click="showStationPopup = true">
                <view class="card-icon station">
                    <u-icon name="map" size="20" color="#52c41a"></u-icon>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!selectedStation">选择需要前往的快递站</text>
                    <text class="value" v-else>{{ selectedStation.name }}</text>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 包裹规格选择 -->
            <view class="section-title">选择包裹规格</view>
            <view class="package-list">
                <view 
                    class="package-card" 
                    v-for="item in packageList" 
                    :key="item.id"
                >
                    <view class="package-info">
                        <text class="name">{{ item.name }}</text>
                        <text class="desc">{{ item.description }}</text>
                        <text class="price">¥{{ item.price.toFixed(2) }}</text>
                    </view>
                    <view class="package-count">
                        <view class="count-btn minus" @click.stop="decreasePackage(item)">-</view>
                        <text class="count">{{ getPackageCount(item.id) }}</text>
                        <view class="count-btn plus" @click.stop="increasePackage(item)">+</view>
                    </view>
                </view>
            </view>

            <!-- 收货地址 -->
            <view class="form-card" @click="selectAddress">
                <view class="card-icon address">
                    <text>🏠</text>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!receiveAddress">填写您的详细地址哦~</text>
                    <text class="value" v-else>{{ receiveAddress }}</text>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 期望送达时间 -->
            <view class="form-card" @click="showTimePicker = true">
                <view class="card-icon time">
                    <text>⏰</text>
                </view>
                <view class="card-content">
                    <text class="label">期望送达时间</text>
                </view>
                <text class="time-value">{{ expectTime || '选择时间' }}</text>
            </view>

            <!-- 图片上传 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon image">
                        <text>🖼️</text>
                    </view>
                    <text class="label">图片上传</text>
                    <text class="hint">最多上传3张图片</text>
                </view>
                <xy-upload v-model="imageStr" :maxCount="3" />
            </view>

            <!-- 取件码 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon code">
                        <text>🔢</text>
                    </view>
                    <text class="label">取件码</text>
                </view>
                <textarea 
                    class="code-input" 
                    v-model="pickupCode" 
                    placeholder="输入取件码或粘贴取件短信"
                    :maxlength="200"
                ></textarea>
            </view>

            <!-- 底部占位 -->
            <view style="height: 300rpx;"></view>
        </scroll-view>

        <!-- 底部提交栏 -->
        <view class="submit-bar">
            <view class="price-info">
                <text class="label">合计：</text>
                <text class="price">¥{{ totalPrice.toFixed(2) }}</text>
            </view>
            <button class="submit-btn" @click="submitOrder">立即下单</button>
        </view>

        <!-- 快递站选择弹窗 -->
        <u-popup :show="showStationPopup" mode="bottom" round="20" @close="showStationPopup = false">
            <view class="station-popup">
                <view class="popup-header">
                    <view class="search-box">
                        <u-icon name="search" size="16" color="#999"></u-icon>
                        <input type="text" v-model="stationKeyword" placeholder="输入关键字搜索" />
                    </view>
                </view>
                <scroll-view scroll-y class="station-list">
                    <view 
                        class="station-item" 
                        v-for="item in filteredStations" 
                        :key="item.id"
                        @click="selectStation(item)"
                    >
                        <view class="station-logo">
                            <image v-if="item.logo" :src="item.logo" mode="aspectFit"></image>
                            <text v-else class="logo-text">驿站</text>
                        </view>
                        <view class="station-info">
                            <text class="name">{{ item.name }}</text>
                            <text class="address">{{ item.address }}</text>
                        </view>
                    </view>
                </scroll-view>
                <view class="popup-footer">
                    <button class="cancel-btn" @click="showStationPopup = false">取消</button>
                    <button class="confirm-btn" @click="confirmStation">确认</button>
                </view>
            </view>
        </u-popup>

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
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { getExpressStations, getPackagePrices, createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import xyUpload from '../../components/xy-upload.vue'

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const showStationPopup = ref(false)
const showTimePicker = ref(false)
const stationKeyword = ref('')
const selectedTime = ref(Date.now())
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const stationList = ref<any[]>([])
const packageList = ref<any[]>([])
const selectedStation = ref<any>(null)
const selectedPackages = ref<any[]>([])
const receiveAddress = ref('')
const expectTime = ref('')
const imageStr = ref('')
const pickupCode = ref('')

onMounted(() => {
    tryBindFenxiao()
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    loadStations()
    loadPackages()
})

const filteredStations = computed(() => {
    if (!stationKeyword.value) return stationList.value
    return stationList.value.filter(item => 
        item.name.includes(stationKeyword.value) || 
        item.address.includes(stationKeyword.value)
    )
})

const totalPrice = computed(() => {
    return selectedPackages.value.reduce((sum, item) => {
        return sum + (item.price * item.count)
    }, 0)
})

const loadStations = async () => {
    try {
        const res: any = await getExpressStations()
        if (res.code === 1) {
            stationList.value = res.data.list || res.data || []
        }
    } catch (e) {
        // 使用默认数据
        stationList.value = [
            { id: 1, name: '菜鸟驿站（嘉庚北区）', address: '福建省漳州市福建省漳州市龙海区菜鸟驿站 (韵达快递)', logo: '' },
            { id: 2, name: '菜鸟驿站（嘉庚北区）', address: '福建省漳州市福建省漳州市龙海区菜鸟驿站 (极兔速递)', logo: '' },
        ]
    }
}

const loadPackages = async () => {
    try {
        const res: any = await getPackagePrices()
        if (res.code === 1) {
            packageList.value = res.data.list || res.data || []
        }
    } catch (e) {
        // 使用默认数据
        packageList.value = [
            { id: 1, size: 'small', name: '小件', description: '小礼盒大小', price: 2 },
            { id: 2, size: 'medium', name: '中件', description: '标准鞋盒大小', price: 3 },
            { id: 3, size: 'large', name: '中大件', description: '微波炉/中型收纳箱大小', price: 5 },
            { id: 4, size: 'xlarge', name: '大件', description: '24-26寸行李箱大小', price: 8 },
        ]
    }
}

const getPackageCount = (id: number) => {
    const item = selectedPackages.value.find(p => p.id === id)
    return item ? item.count : 0
}

const increasePackage = (item: any) => {
    const index = selectedPackages.value.findIndex(p => p.id === item.id)
    if (index === -1) {
        selectedPackages.value.push({ ...item, count: 1 })
    } else {
        selectedPackages.value[index].count++
    }
}

const decreasePackage = (item: any) => {
    const index = selectedPackages.value.findIndex(p => p.id === item.id)
    if (index !== -1) {
        if (selectedPackages.value[index].count > 0) {
            selectedPackages.value[index].count--
            if (selectedPackages.value[index].count === 0) {
                selectedPackages.value.splice(index, 1)
            }
        }
    }
}

const selectStation = (item: any) => {
    selectedStation.value = item
    showStationPopup.value = false
}

const confirmStation = () => {
    showStationPopup.value = false
}

const selectAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select?type=receive',
        success: (res) => {
            res.eventChannel.on('selectAddress', (address: any) => {
                receiveAddress.value = address.address
            })
        }
    })
}

const onTimeConfirm = (e: any) => {
    const date = new Date(e.value)
    expectTime.value = `${date.getMonth() + 1}-${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`
    showTimePicker.value = false
}

const submitOrder = async () => {
    if (!selectedStation.value) {
        uni.showToast({ title: '请选择快递站', icon: 'none' })
        return
    }
    if (selectedPackages.value.length === 0) {
        uni.showToast({ title: '请选择包裹规格', icon: 'none' })
        return
    }
    if (!receiveAddress.value) {
        uni.showToast({ title: '请填写收货地址', icon: 'none' })
        return
    }

    const ext = {
        station_id: selectedStation.value.id,
        station_name: selectedStation.value.name,
        packages: selectedPackages.value,
        pickup_code: pickupCode.value,
        expect_time: expectTime.value,
        images: imageStr.value
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'EXPRESS',
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || '',
            receive_address: receiveAddress.value,
            goods_name: '快递包裹',
            task_desc: '取快递',
            images: imageStr.value,
            total_fee: totalPrice.value,
            remark: '',
            ext: JSON.stringify(ext)
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

const goBack = () => {
    uni.navigateBack()
}

// 支付成功回调
const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: '/addon/sd_xiaoyuan/pages/order/list'
        })
    }, 1500)
}

// 支付失败回调
const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.express-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.header-bg {
    background: linear-gradient(135deg, #d4f5c4, #a8e6cf);
    padding-bottom: 40rpx;
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

.header-content {
    display: flex;
    justify-content: space-between;
    padding: 20rpx 30rpx;
    
    .header-left {
        .service-tag {
            font-size: 24rpx;
            color: #666;
            opacity: 0.6;
        }
        
        .main-title {
            display: block;
            font-size: 48rpx;
            font-weight: bold;
            color: #333;
            margin: 10rpx 0;
        }
        
        .sub-tag {
            display: inline-block;
            background: linear-gradient(135deg, #52c41a, #73d13d);
            padding: 8rpx 20rpx;
            border-radius: 30rpx;
            
            text {
                font-size: 22rpx;
                color: #fff;
            }
        }
    }
    
    .header-right {
        image {
            width: 160rpx;
            height: 160rpx;
        }
    }
}

.form-content {
    height: calc(100vh - 400rpx);
    padding: 20rpx;
}

.form-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    margin-bottom: 20rpx;
    display: flex;
    align-items: center;
    
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
        font-size: 28rpx;
    }
    
    .card-content {
        flex: 1;
        
        .placeholder {
            color: #999;
            font-size: 28rpx;
        }
        
        .value {
            color: #333;
            font-size: 28rpx;
        }
        
        .label {
            color: #333;
            font-size: 28rpx;
        }
    }
    
    .time-value {
        color: #52c41a;
        font-size: 28rpx;
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
        }
        
        .hint {
            font-size: 24rpx;
            color: #999;
        }
    }
}

.section-title {
    font-size: 28rpx;
    color: #333;
    font-weight: bold;
    margin: 30rpx 0 20rpx;
}

.package-list {
    display: flex;
    flex-direction: column;
    gap: 20rpx;
}

.package-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: auto;
    
    .package-info {
        flex: 1;
        
        .name {
            display: block;
            font-size: 32rpx;
            color: #333;
            font-weight: bold;
        }
        
        .desc {
            display: block;
            font-size: 24rpx;
            color: #999;
            margin: 8rpx 0;
        }
        
        .price {
            font-size: 30rpx;
            color: #ff6b00;
            font-weight: bold;
        }
    }
    
    .package-count {
        display: flex;
        align-items: center;
        
        .count-btn {
            width: 56rpx;
            height: 56rpx;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32rpx;
            font-weight: bold;
            
            &.minus {
                background: #f0f0f0;
                color: #999;
            }
            
            &.plus {
                background: #52c41a;
                color: #fff;
            }
        }
        
        .count {
            width: 60rpx;
            text-align: center;
            font-size: 32rpx;
            color: #333;
            font-weight: bold;
        }
    }
}

.image-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    width: 100%;
}

.image-item {
    width: 160rpx;
    height: 160rpx;
    border-radius: 12rpx;
    overflow: hidden;
    position: relative;
    
    image {
        width: 100%;
        height: 100%;
    }
    
    .delete-btn {
        position: absolute;
        top: 0;
        right: 0;
        width: 40rpx;
        height: 40rpx;
        background: rgba(0,0,0,0.5);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24rpx;
    }
    
    &.add {
        background: #f5f5f5;
        border: 2rpx dashed #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.code-input {
    width: 100%;
    height: 120rpx;
    background: #f5f5f5;
    border-radius: 12rpx;
    padding: 20rpx;
    font-size: 28rpx;
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;z-index: 22;
    justify-content: space-between;
    padding: 20rpx 30rpx;
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    
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
    
    .submit-btn {
        background: linear-gradient(135deg, #52c41a, #73d13d);
        color: #fff;
        font-size: 30rpx;
        padding: 10rpx 80rpx;
        border-radius: 16rpx;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 80rpx;
    }
}

.station-popup {
    padding: 30rpx;
    max-height: 70vh;
    
    .popup-header {
        margin-bottom: 20rpx;
        
        .search-box {
            display: flex;
            align-items: center;
            background: #f5f5f5;
            border-radius: 16rpx;
            padding: 16rpx 24rpx;
            
            input {
                flex: 1;
                margin-left: 16rpx;
                font-size: 28rpx;
            }
        }
    }
    
    .station-list {
        max-height: 50vh;
    }
    
    .station-item {
        display: flex;
        align-items: center;
        padding: 24rpx;
        border: 2rpx solid #f0f0f0;
        border-radius: 12rpx;
        margin-bottom: 16rpx;
        
        &:active {
            border-color: #52c41a;
            background: #f6ffed;
        }
        
        .station-logo {
            width: 80rpx;
            height: 80rpx;
            background: #fff3e0;
            border-radius: 8rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20rpx;
            
            image {
                width: 60rpx;
                height: 60rpx;
            }
            
            .logo-text {
                font-size: 20rpx;
                color: #ff6b00;
                font-weight: bold;
            }
        }
        
        .station-info {
            flex: 1;
            
            .name {
                display: block;
                font-size: 28rpx;
                color: #333;
                font-weight: bold;
            }
            
            .address {
                display: block;
                font-size: 24rpx;
                color: #999;
                margin-top: 8rpx;
            }
        }
    }
    
    .popup-footer {
        display: flex;
        gap: 20rpx;
        margin-top: 20rpx;
        
        button {
            flex: 1;
            height: 80rpx;
            border-radius: 16rpx;
            font-size: 28rpx;
            
            &.cancel-btn {
                background: #f5f5f5;
                color: #666;
            }
            
            &.confirm-btn {
                background: linear-gradient(135deg, #52c41a, #73d13d);
                color: #fff;
            }
        }
    }
}
</style>
