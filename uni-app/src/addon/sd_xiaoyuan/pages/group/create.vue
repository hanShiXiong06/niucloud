<template>
    <view class="create-page">
        <view class="form-section">
            <view class="section-title">拼单类型</view>
            <view class="type-list">
                <view 
                    class="type-item" 
                    :class="{ active: formData.group_type === type.value }"
                    v-for="type in typeOptions" 
                    :key="type.value"
                    @click="formData.group_type = type.value"
                >
                    <text>{{ type.label }}</text>
                </view>
            </view>
        </view>

        <view class="form-section">
            <view class="section-title">拼单信息</view>
            <view class="form-item">
                <text class="label">标题</text>
                <input v-model="formData.title" placeholder="如：一起拼奶茶呀~" maxlength="50" />
            </view>
            <view class="form-item">
                <text class="label">店铺名称</text>
                <input v-model="formData.shop_name" placeholder="请输入店铺名称" />
            </view>
            <view class="form-item">
                <text class="label">详细说明</text>
                <textarea v-model="formData.content" placeholder="描述一下拼单详情，如菜单、要求等" maxlength="500" />
            </view>
        </view>

        <view class="form-section">
            <view class="section-title">拼单设置</view>
            <view class="form-item">
                <text class="label">最少人数</text>
                <input v-model="formData.min_members" type="number" placeholder="最少几人成团" />
            </view>
            <view class="form-item">
                <text class="label">最多人数</text>
                <input v-model="formData.max_members" type="number" placeholder="最多几人" />
            </view>
            <view class="form-item">
                <text class="label">人均价格</text>
                <input v-model="formData.per_price" type="digit" placeholder="每人大约多少钱" />
            </view>
            <view class="form-item">
                <text class="label">配送费</text>
                <input v-model="formData.delivery_fee" type="digit" placeholder="配送费(可选)" />
            </view>
            <view class="form-item">
                <text class="label">截止时间</text>
                <picker mode="time" :value="deadlineTime" @change="onTimeChange">
                    <view class="picker-value">{{ deadlineTime || '选择截止时间' }}</view>
                </picker>
            </view>
        </view>

        <view class="form-section">
            <view class="section-title">配送地址</view>
            <view class="form-item">
                <text class="label">配送地址</text>
                <input v-model="formData.delivery_address" placeholder="送到哪里" />
            </view>
        </view>

        <view class="form-section">
            <view class="section-title">我的点单</view>
            <view class="form-item">
                <text class="label">点单内容</text>
                <textarea v-model="formData.order_content" placeholder="你要点什么，如：珍珠奶茶 大杯 少冰" maxlength="200" />
            </view>
        </view>

        <view class="submit-bar">
            <view style="flex:1"></view>
            <button class="submit-btn" @click="submitOrder">发起拼单</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { createGroupOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'

onMounted(() => {
    tryBindFenxiao()
})

const formData = ref({
    group_type: 'FOOD',
    title: '',
    content: '',
    shop_name: '',
    delivery_address: '',
    delivery_lng: '',
    delivery_lat: '',
    min_members: 2,
    max_members: 10,
    per_price: 0,
    delivery_fee: 0,
    deadline: 0,
    order_content: '',
    images: ''
})

const deadlineTime = ref('')

const typeOptions = [
    { label: '拼奶茶', value: 'TEA' },
    { label: '拼外卖', value: 'FOOD' },
    { label: '拼水果', value: 'FRUIT' },
    { label: '拼车', value: 'RIDE' },
    { label: '其他', value: 'OTHER' }
]

const onTimeChange = (e: any) => {
    deadlineTime.value = e.detail.value
    const now = new Date()
    const [h, m] = e.detail.value.split(':')
    now.setHours(parseInt(h), parseInt(m), 0, 0)
    if (now.getTime() < Date.now()) {
        now.setDate(now.getDate() + 1)
    }
    formData.value.deadline = Math.floor(now.getTime() / 1000)
}

const submitOrder = async () => {
    if (!formData.value.title) {
        uni.showToast({ title: '请输入标题', icon: 'none' })
        return
    }
    if (!formData.value.group_type) {
        uni.showToast({ title: '请选择拼单类型', icon: 'none' })
        return
    }

    // 获取当前选择的学校
    const currentSchool = uni.getStorageSync('current_school')
    
    uni.showLoading({ title: '提交中...' })
    try {
        const res: any = await createGroupOrder({
            ...formData.value,
            min_members: parseInt(String(formData.value.min_members)),
            max_members: parseInt(String(formData.value.max_members)),
            per_price: parseFloat(String(formData.value.per_price)),
            delivery_fee: parseFloat(String(formData.value.delivery_fee)),
            school_id: currentSchool?.id || 0,
            campus: currentSchool?.campus || ''
        })
        uni.hideLoading()
        if (res.code === 1) {
            uni.showToast({ title: '发起成功', icon: 'success' })
            setTimeout(() => {
                uni.redirectTo({ url: `/addon/sd_xiaoyuan/pages/group/detail?id=${res.data.id}` })
            }, 1500)
        } else {
            uni.showToast({ title: res.msg || '发起失败', icon: 'none', duration: 2000 })
        }
    } catch (e: any) {
        uni.hideLoading()
        const errMsg = e?.data?.msg || e?.msg || e?.message || '网络错误'
        uni.showToast({ title: errMsg, icon: 'none', duration: 2000 })
    }
}
</script>

<style lang="scss" scoped>
.create-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 140rpx;
}

.form-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;

    .section-title {
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 20rpx;
    }
}

.type-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;

    .type-item {
        padding: 14rpx 28rpx;
        background: #f5f5f5;
        border-radius: 30rpx;
        font-size: 26rpx;
        color: #666;

        &.active {
            background: linear-gradient(135deg, #ff5722, #ff9800);
            color: #fff;
            font-weight: bold;
        }
    }
}

.form-item {
    display: flex;
    align-items: flex-start;
    padding: 16rpx 0;
    border-bottom: 1rpx solid #f5f5f5;

    &:last-child { border-bottom: none; }

    .label {
        width: 160rpx;
        font-size: 28rpx;
        color: #333;
        flex-shrink: 0;
        line-height: 60rpx;
    }

    input {
        flex: 1;
        font-size: 28rpx;
        height: 60rpx;
    }

    textarea {
        flex: 1;
        font-size: 28rpx;
        min-height: 120rpx;
    }

    .picker-value {
        flex: 1;
        font-size: 28rpx;
        color: #666;
        line-height: 60rpx;
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;z-index: 22;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -2rpx 12rpx rgba(0,0,0,0.05);

    .submit-btn {
        width: 100%;
        height: 88rpx;
        background: linear-gradient(135deg, #ff5722, #ff9800);
        color: #fff;
        font-size: 32rpx;
        font-weight: bold;
        border-radius: 44rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
    }
}
</style>
