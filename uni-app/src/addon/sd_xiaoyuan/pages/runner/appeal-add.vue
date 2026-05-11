<template>
    <view class="appeal-add">
        <view class="form-section">
            <view class="form-item">
                <text class="label required">关联订单</text>
                <view class="order-select" @click="selectOrder">
                    <text v-if="formData.order_id">订单 {{ formData.order_no }}</text>
                    <text class="placeholder" v-else>请选择关联订单</text>
                    <text class="iconfont icon-arrow-right"></text>
                </view>
            </view>
            <view class="form-item">
                <text class="label required">申诉类型</text>
                <view class="type-list">
                    <view 
                        class="type-item" 
                        :class="{ active: formData.appeal_type === type.key }"
                        v-for="type in appealTypes"
                        :key="type.key"
                        @click="formData.appeal_type = type.key"
                    >{{ type.name }}</view>
                </view>
            </view>
            <view class="form-item">
                <text class="label required">申诉原因</text>
                <textarea 
                    v-model="formData.reason" 
                    placeholder="请详细描述您的申诉原因"
                    maxlength="500"
                ></textarea>
                <text class="count">{{ formData.reason.length }}/500</text>
            </view>
            <view class="form-item">
                <text class="label">图片凭证</text>
                <xy-upload v-model="formData.images" :maxCount="5" />
            </view>
        </view>

        <button class="submit-btn" :disabled="!canSubmit" @click="submitAppeal">提交申诉</button>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { addAppeal } from '../../api/runner'
import xyUpload from '../../components/xy-upload.vue'

const formData = ref({
    order_id: 0,
    order_no: '',
    appeal_type: 'ORDER_ISSUE',
    reason: '',
    images: [] as string[]
})

const appealTypes = [
    { key: 'ORDER_ISSUE', name: '订单问题' },
    { key: 'FEE_ISSUE', name: '费用问题' },
    { key: 'USER_ISSUE', name: '用户问题' },
    { key: 'OTHER', name: '其他' }
]

const canSubmit = computed(() => {
    return formData.value.order_id && formData.value.appeal_type && formData.value.reason.length >= 10
})

const selectOrder = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/runner/my-orders?select=1',
        events: {
            selectOrder: (order: any) => {
                formData.value.order_id = order.id
                formData.value.order_no = order.order_no
            }
        }
    })
}

const submitAppeal = async () => {
    if (!formData.value.order_id) {
        uni.showToast({ title: '请选择关联订单', icon: 'none' })
        return
    }
    if (formData.value.reason.length < 10) {
        uni.showToast({ title: '申诉原因至少10个字', icon: 'none' })
        return
    }
    
    try {
        uni.showLoading({ title: '提交中...' })
        const res: any = await addAppeal({
            order_id: formData.value.order_id,
            appeal_type: formData.value.appeal_type,
            reason: formData.value.reason,
            images: formData.value.images
        })
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '提交成功', icon: 'success' })
            setTimeout(() => {
                uni.navigateBack()
            }, 1500)
        } else {
            uni.showToast({ title: res.msg || '提交失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.appeal-add {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
    padding-bottom: 150rpx;
}

.form-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
}

.form-item {
    margin-bottom: 20rpx;
    
    &:last-child {
        margin-bottom: 0;
    }
    
    .label {
        display: block;
        font-size: 28rpx;
        color: #333;
        margin-bottom: 16rpx;
        
        &.required::before {
            content: '*';
            color: #ff4d4f;
            margin-right: 8rpx;
        }
    }
}

.order-select {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 72rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 0 24rpx;
    
    text {
        font-size: 28rpx;
        color: #333;
    }
    
    .placeholder {
        color: #999;
    }
    
    .icon-arrow-right {
        color: #ccc;
    }
}

.type-list {
    display: flex;
    flex-wrap: wrap;
    gap: 20rpx;
}

.type-item {
    padding: 16rpx 30rpx;
    background: #f8f8f8;
    border-radius: 30rpx;
    font-size: 26rpx;
    color: #666;
    border: 2rpx solid transparent;
    
    &.active {
        background: #32CD32;
        color: #fff;
        border-color: #32CD32;
        font-weight: bold;
    }
}

textarea {
    width: 100%;
    height: 200rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 20rpx;
    font-size: 28rpx;
    box-sizing: border-box;
}

.count {
    display: block;
    text-align: right;
    font-size: 24rpx;
    color: #999;
    margin-top: 8rpx;
}

.image-list {
    display: flex;
    flex-wrap: wrap;
    gap: 20rpx;
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
        background: rgba(0, 0, 0, 0.5);
        color: #fff;
        text-align: center;
        line-height: 40rpx;
        font-size: 28rpx;
    }
}

.add-btn {
    width: 160rpx;
    height: 160rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    
    .iconfont {
        font-size: 60rpx;
        color: #ccc;
    }
}

.tip {
    display: block;
    font-size: 24rpx;
    color: #999;
    margin-top: 12rpx;
}

.submit-btn {
    position: fixed;
    bottom: 30rpx;
    left: 30rpx;
    right: 30rpx;
    height: 88rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border: none;
    border-radius: 44rpx;
    font-size: 28rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    
    &[disabled] {
        background: #ccc;
        color: #fff;
        box-shadow: none;
    }
}
</style>
