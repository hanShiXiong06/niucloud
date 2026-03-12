<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="help-create-page" v-if="isFeatureEnabled">
        <u-navbar 
            title="帮帮忙" 
            :safeAreaInsetTop="true"
            :placeholder="true"
            bgColor="#c0fe95"
        ></u-navbar>

        <!-- 顶部装饰 -->
        <view class="header-decoration">
            <view class="decoration-text">
                <text class="sub">· 代拿·跑腿·有偿 · · ·</text>
            </view>
            <image class="decoration-img" src="/static/images/help-heart.png" mode="aspectFit"></image>
        </view>

        <view class="form-container">
            <!-- 事件类型 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="grid" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">事件类型</text>
                </view>
                <view class="type-list">
                    <view 
                        class="type-item" 
                        :class="{ active: formData.help_type === 'ERRAND' }"
                        @click="formData.help_type = 'ERRAND'"
                    >跑腿帮忙</view>
                    <view 
                        class="type-item" 
                        :class="{ active: formData.help_type === 'ONLINE' }"
                        @click="formData.help_type = 'ONLINE'"
                    >线上帮忙</view>
                </view>
            </view>

            <!-- 图片上传 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="photo" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">图片上传(0/3)</text>
                    <text class="optional">非必填</text>
                </view>
                <xy-upload v-model="imageStr" :maxCount="3" />
            </view>

            <!-- 备注说明 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="edit-pen" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">备注说明</text>
                </view>
                <textarea 
                    v-model="formData.remark" 
                    placeholder="说明需要帮助的内容"
                    :maxlength="200"
                    class="remark-input"
                ></textarea>
            </view>

            <!-- 地址选择 -->
            <view class="form-section address-section" @click="selectAddress">
                <view class="section-header">
                    <u-icon name="map" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">选择哪个宿舍或其他地址</text>
                </view>
                <u-icon name="arrow-right" size="16" color="#999"></u-icon>
            </view>
            <view class="address-info" v-if="selectedAddress">
                <text>{{ selectedAddress.name }} {{ selectedAddress.mobile }}</text>
                <text class="address-detail">{{ selectedAddress.address }}</text>
            </view>

            <!-- 赏金 -->
            <view class="form-section reward-section">
                <view class="section-header">
                    <u-icon name="red-packet" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">赏金</text>
                </view>
                <view class="reward-input">
                    <input 
                        type="digit" 
                        v-model="formData.reward" 
                        placeholder="输入金额"
                        class="amount-input"
                    />
                </view>
            </view>
        </view>

        <!-- 底部提交 -->
        <view class="submit-bar">
            <view style="flex:1"></view>
            <button class="submit-btn2" @click="submitHelp">发布求助</button>
        </view>

        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { createHelpOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import xyUpload from '../../components/xy-upload.vue'
import pay from '@/components/pay/pay.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_help')

const imageStr = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)
const formData = ref({
    help_type: 'ERRAND',
    gender_limit: 'ALL',
    remark: '',
    address_id: 0,
    reward: ''
})

const selectedAddress = ref<any>(null)

onMounted(() => {
    loadConfig()
    tryBindFenxiao()
    uni.$on('onAddressSelect', (address: any) => {
        selectedAddress.value = address
        formData.value.address_id = address.id
    })
})

const selectAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select'
    })
}

const submitHelp = async () => {
    if (!formData.value.remark) {
        uni.showToast({ title: '请填写备注说明', icon: 'none' })
        return
    }
    if (!formData.value.reward || parseFloat(formData.value.reward) <= 0) {
        uni.showToast({ title: '请输入赏金金额', icon: 'none' })
        return
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createHelpOrder({
            help_type: formData.value.help_type,
            gender_limit: formData.value.gender_limit,
            images: imageStr.value,
            remark: formData.value.remark,
            address_id: formData.value.address_id,
            reward: parseFloat(formData.value.reward),
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || ''
        })
        uni.hideLoading()
        if (res.code === 1) {
            currentOrderId.value = res.data.id
            payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
        } else {
            uni.showToast({ title: res.msg || '发布失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        uni.showToast({ title: e.message || '发布失败', icon: 'none' })
    }
}

const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: '/addon/sd_xiaoyuan/pages/order/list'
        })
    }, 1500)
}

const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.help-create-page {
    min-height: 100vh;
    background: linear-gradient(to bottom, #c0fe95 0%, #f5f5f5 30%);
    padding-bottom: 120rpx;
}

.header-decoration {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx 30rpx;
    
    .decoration-text {
        .sub {
            font-size: 26rpx;
            color: #666;
        }
    }
    
    .decoration-img {
        width: 120rpx;
        height: 120rpx;
    }
}

.form-container {
    padding: 0 30rpx;
}

.form-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    width: auto;
    
    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 20rpx;
        
        .section-title {
            font-size: 28rpx;
            color: #333;
            margin-left: 12rpx;
            font-weight: 500;
        }
        
        .optional {
            margin-left: auto;
            font-size: 24rpx;
            color: #1890ff;
        }
    }
}

.type-list {
    display: flex;
    gap: 20rpx;
    
    .type-item {
        padding: 16rpx 32rpx;
        background: #f5f5f5;
        border-radius: 8rpx;
        font-size: 26rpx;
        color: #666;
        
        &.active {
            background: #1890ff;
            color: #fff;
        }
    }
}

.image-upload {
    .image-list {
        display: flex;
        flex-wrap: wrap;
        gap: 16rpx;
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
            top: 8rpx;
            right: 8rpx;
            width: 32rpx;
            height: 32rpx;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    }
    
    .add-image {
        width: 160rpx;
        height: 160rpx;
        border: 2rpx dashed #ddd;
        border-radius: 12rpx;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.remark-input {
    width: 100%;
    height: 200rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 20rpx;
    font-size: 28rpx;
    box-sizing: border-box;
}

.address-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    
    .section-header {
        margin-bottom: 0;
    }
}

.address-info {
    background: #fff;
    border-radius: 16rpx;
    padding: 20rpx 24rpx;
    margin-top: -10rpx;
    margin-bottom: 20rpx;
    
    text {
        display: block;
        font-size: 28rpx;
        color: #333;
    }
    
    .address-detail {
        font-size: 24rpx;
        color: #999;
        margin-top: 8rpx;
    }
}

.reward-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    
    .section-header {
        margin-bottom: 0;
    }
    
    .reward-input {
        .amount-input {
            width: 200rpx;
            text-align: right;
            font-size: 28rpx;
            color: #333;
        }
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;z-index: 22;
    padding: 20rpx 30rpx;
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    display: flex;
    align-items: center;
    justify-content: flex-end;
    
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
}
</style>
