<template>
    <view class="publish-page">
        <!-- 任务类型 -->
        <view class="section">
            <view class="section-title">任务类型</view>
            <view class="type-grid">
                <view class="type-item" :class="{ active: formData.task_type === item.value }" v-for="item in typeOptions" :key="item.value" @click="formData.task_type = item.value">
                    <u-icon :name="item.icon" size="40" :color="formData.task_type === item.value ? '#333' : '#999'"></u-icon>
                    <text>{{ item.label }}</text>
                </view>
            </view>
        </view>

        <!-- 基本信息 -->
        <view class="section">
            <view class="section-title">任务信息</view>
            <view class="form-item">
                <text class="form-label">标题</text>
                <input v-model="formData.title" placeholder="请输入任务标题" class="form-input" />
            </view>
            <view class="form-item">
                <text class="form-label">描述</text>
                <textarea v-model="formData.content" placeholder="详细描述您的需求" class="form-textarea" :maxlength="500" />
            </view>
            <view class="form-item">
                <text class="form-label">图片</text>
                <xy-upload v-model="formData.images" :maxCount="3" />
            </view>
        </view>

        <!-- 快递信息（仅快递代取） -->
        <view class="section" v-if="formData.task_type === 'EXPRESS'">
            <view class="section-title">快递信息</view>
            <view class="form-item">
                <text class="form-label">快递公司</text>
                <input v-model="formData.express_company" placeholder="如：顺丰、中通" class="form-input" />
            </view>
            <view class="form-item">
                <text class="form-label">快递单号</text>
                <input v-model="formData.express_no" placeholder="请输入快递单号" class="form-input" />
            </view>
            <view class="form-item">
                <text class="form-label">取件码</text>
                <input v-model="formData.pickup_code" placeholder="请输入取件码" class="form-input" />
            </view>
        </view>

        <!-- 地址信息 -->
        <view class="section">
            <view class="section-title">地址信息</view>
            <view class="form-item">
                <text class="form-label">取件/出发地址</text>
                <input v-model="formData.pickup_address" placeholder="请输入地址" class="form-input" />
            </view>
            <view class="form-item">
                <text class="form-label">送达地址</text>
                <input v-model="formData.delivery_address" placeholder="请输入送达地址" class="form-input" />
            </view>
        </view>

        <!-- 联系方式 -->
        <view class="section">
            <view class="section-title">联系方式</view>
            <view class="form-item">
                <text class="form-label">联系人</text>
                <input v-model="formData.contact_name" placeholder="请输入联系人" class="form-input" />
            </view>
            <view class="form-item">
                <text class="form-label">手机号</text>
                <input v-model="formData.contact_mobile" placeholder="请输入手机号" type="number" class="form-input" />
            </view>
        </view>

        <!-- 赏金设置 -->
        <view class="section">
            <view class="section-title">赏金设置</view>
            <view class="form-item">
                <text class="form-label">赏金(元)</text>
                <input v-model="formData.reward" placeholder="请输入赏金" type="digit" class="form-input" />
            </view>
            <view class="form-item">
                <text class="form-label">小费(元)</text>
                <input v-model="formData.tip" placeholder="选填，给接单员额外奖励" type="digit" class="form-input" />
            </view>
            <view class="form-item switch-item">
                <text class="form-label">加急</text>
                <switch :checked="formData.is_urgent === 1" @change="formData.is_urgent = $event.detail.value ? 1 : 0" color="#c0fe95" />
            </view>
        </view>

        <!-- 提交按钮 -->
        <view class="submit-area">
            <view class="submit-btn" :class="{ disabled: submitting }" @click="handleSubmit">
                <text>{{ submitting ? '发布中...' : '发布任务' }}</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref } from 'vue'
import { publishTask } from '../../api/xiaoyuan'
import xyUpload from '../../components/xy-upload.vue'

const typeOptions = [
    { value: 'EXPRESS', label: '快递代取', icon: 'gift' },
    { value: 'TAKEOUT', label: '外卖代拿', icon: 'bag' },
    { value: 'BUY', label: '帮我购买', icon: 'shopping-cart' },
    { value: 'QUEUE', label: '排队占座', icon: 'clock' },
    { value: 'PRINT', label: '打印服务', icon: 'file-text' },
    { value: 'ERRAND', label: '跑腿代办', icon: 'man-add' },
    { value: 'OTHER', label: '其他', icon: 'more-circle' },
]

const formData = ref({
    task_type: 'EXPRESS',
    title: '',
    content: '',
    images: '',
    pickup_address: '',
    pickup_lng: '',
    pickup_lat: '',
    delivery_address: '',
    delivery_lng: '',
    delivery_lat: '',
    contact_name: '',
    contact_mobile: '',
    express_company: '',
    express_no: '',
    pickup_code: '',
    reward: '',
    tip: '',
    is_urgent: 0,
    deadline: 0
})

const submitting = ref(false)

const handleSubmit = async () => {
    if (submitting.value) return

    if (!formData.value.task_type) { uni.showToast({ title: '请选择任务类型', icon: 'none' }); return }
    if (!formData.value.title) { uni.showToast({ title: '请输入任务标题', icon: 'none' }); return }
    if (!formData.value.reward) { uni.showToast({ title: '请输入赏金', icon: 'none' }); return }
    if (!formData.value.contact_name) { uni.showToast({ title: '请输入联系人', icon: 'none' }); return }
    if (!formData.value.contact_mobile) { uni.showToast({ title: '请输入手机号', icon: 'none' }); return }

    submitting.value = true
    try {
        const submitData = {
            ...formData.value,
            images: formData.value.images,
            reward: parseFloat(formData.value.reward) || 0,
            tip: parseFloat(formData.value.tip) || 0
        }
        const res: any = await publishTask(submitData)
        if (res.code === 1) {
            uni.showToast({ title: '发布成功' })
            setTimeout(() => {
                uni.navigateBack()
            }, 1500)
        } else {
            uni.showToast({ title: res.msg || '发布失败', icon: 'none' })
        }
    } catch (e) {
        uni.showToast({ title: '发布失败', icon: 'none' })
    } finally {
        submitting.value = false
    }
}
</script>

<style lang="scss" scoped>
.publish-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 140rpx;
}

.section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
}

.section-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
}

.type-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.type-item {
    width: calc(25% - 12rpx);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20rpx 0;
    border-radius: 12rpx;
    background: #f5f5f5;
    border: 2rpx solid transparent;

    text {
        font-size: 22rpx;
        color: #999;
        margin-top: 8rpx;
    }

    &.active {
        background: #f0ffe0;
        border-color: #c0fe95;

        text { color: #333; font-weight: bold; }
    }
}

.form-item {
    margin-bottom: 20rpx;

    &:last-child { margin-bottom: 0; }
}

.form-label {
    display: block;
    font-size: 26rpx;
    color: #666;
    margin-bottom: 10rpx;
}

.form-input {
    width: 100%;
    padding: 20rpx;
    background: #f5f5f5;
    border-radius: 12rpx;
    font-size: 28rpx;
    box-sizing: border-box;
}

.form-textarea {
    width: 100%;
    height: 200rpx;
    padding: 20rpx;
    background: #f5f5f5;
    border-radius: 12rpx;
    font-size: 28rpx;
    box-sizing: border-box;
}

.switch-item {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .form-label { margin-bottom: 0; }
}

.image-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.img-item {
    width: 180rpx;
    height: 180rpx;
    border-radius: 12rpx;
    overflow: hidden;
    position: relative;

    image { width: 100%; height: 100%; }

    .img-del {
        position: absolute;
        top: 6rpx;
        right: 6rpx;
        width: 36rpx;
        height: 36rpx;
        background: rgba(0, 0, 0, 0.5);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.img-add {
    width: 180rpx;
    height: 180rpx;
    border-radius: 12rpx;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2rpx dashed #ddd;
}

.submit-area {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
}

.submit-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 26rpx;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    border-radius: 16rpx;

    text { font-size: 32rpx; font-weight: bold; color: #000; }

    &.disabled { opacity: 0.6; }
}
</style>
