<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="publish-page" v-if="isFeatureEnabled">
        <view class="form-section">
            <view class="section-title">约伴组局</view>
            <view class="form-item">
                <text class="label required">约伴组局标题</text>
                <input v-model="form.title" placeholder="如：周末羽毛球局" maxlength="40" />
            </view>
            <view class="form-item">
                <text class="label required">活动类型</text>
                <input v-model="form.activity_type" placeholder="如：运动/桌游/电影/学习" maxlength="20" />
            </view>
            <view class="form-item">
                <text class="label required">活动时间</text>
                <input v-model="form.activity_time" placeholder="如：周六 19:00" maxlength="40" />
            </view>
            <view class="form-item">
                <text class="label">活动地点</text>
                <input v-model="form.location" placeholder="请输入集合地点" maxlength="60" />
            </view>
            <view class="form-item">
                <text class="label">人数需求</text>
                <input v-model="form.people_count" type="number" placeholder="如：4" maxlength="4" />
            </view>
            <view class="form-item">
                <text class="label">费用说明</text>
                <input v-model="form.budget" placeholder="如：AA 30元/人" maxlength="40" />
            </view>
        </view>

        <view class="form-section">
            <view class="section-title">详细描述</view>
            <view class="form-item">
                <textarea v-model="form.content" placeholder="可填写活动流程、注意事项、报名要求等" maxlength="500" :auto-height="true" />
            </view>
            <view class="form-item">
                <text class="label required">发布费</text>
                <view class="price-input">
                    <text class="yen">¥</text>
                    <input v-model="form.price" type="digit" placeholder="0.00" />
                </view>
            </view>
            <view class="form-item">
                <text class="label">展示图片</text>
                <xy-upload v-model="imageStr" :maxCount="6" />
            </view>
        </view>

        <view class="form-section">
            <xy-order-yinsi-field v-model="yinsiText" />
        </view>

        <view class="submit-section">
            <button class="submit-btn" :disabled="!canSubmit" @click="submitPublish">发布约伴</button>
        </view>
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import xyUpload from '../../components/xy-upload.vue'
import XyOrderYinsiField from '../../components/xy-order-yinsi-field.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_companion')
const imageStr = ref('')
const yinsiText = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const form = ref({
    title: '',
    activity_type: '',
    activity_time: '',
    location: '',
    people_count: '',
    budget: '',
    content: '',
    price: ''
})

const canSubmit = computed(() => !!form.value.title && !!form.value.activity_type && !!form.value.activity_time && parseFloat(form.value.price) > 0)

onMounted(() => {
    loadConfig()
    tryBindFenxiao()
})

const submitPublish = async () => {
    if (!canSubmit.value) return
    const ext = {
        activity_type: form.value.activity_type,
        activity_time: form.value.activity_time,
        location: form.value.location,
        people_count: form.value.people_count,
        budget: form.value.budget
    }
    try {
        uni.showLoading({ title: '提交中...' })
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'COMPANION',
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || '',
            goods_name: form.value.title,
            task_desc: form.value.content,
            goods_image: imageStr.value,
            total_fee: parseFloat(form.value.price),
            remark: form.value.content,
            yinsi_text: yinsiText.value,
            ext: JSON.stringify(ext)
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
        uni.showToast({ title: e.msg || '网络错误', icon: 'none' })
    }
}

const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({ url: `/addon/sd_xiaoyuan/pages/order/detail?id=${currentOrderId.value}` })
    }, 1200)
}

const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.publish-page { min-height: 100vh; background: #f5f5f5; padding: 20rpx; padding-bottom: 200rpx; }
.form-section { background: #fff; border-radius: 20rpx; padding: 28rpx; margin-bottom: 20rpx; }
.section-title { font-size: 30rpx; font-weight: bold; color: #333; margin-bottom: 24rpx; padding-left: 16rpx; border-left: 8rpx solid #c0fe95; line-height: 1; }
.form-item { margin-bottom: 24rpx; .label { display: block; font-size: 28rpx; color: #333; margin-bottom: 12rpx; font-weight: 500; &.required::before { content: '*'; color: #ff4d4f; margin-right: 4rpx; } } input { display: block; width: 100%; height: 80rpx; padding: 0 20rpx; box-sizing: border-box; background: #f8f8f8; border-radius: 12rpx; font-size: 28rpx; } textarea { width: 100%; min-height: 200rpx; padding: 20rpx; box-sizing: border-box; background: #f8f8f8; border-radius: 12rpx; font-size: 28rpx; } }
.price-input { display: flex; align-items: center; background: #f8f8f8; border-radius: 12rpx; padding: 0 20rpx; .yen { font-size: 32rpx; color: #ff6b00; font-weight: bold; margin-right: 8rpx; } input { flex: 1; width: auto; height: 80rpx; padding: 0; background: transparent; font-size: 32rpx; font-weight: bold; color: #ff6b00; } }
.submit-section { position: fixed; bottom: 0; left: 0; right: 0; padding: 20rpx 30rpx; padding-bottom: calc(20rpx + env(safe-area-inset-bottom)); background: #fff; box-shadow: 0 -2rpx 20rpx rgba(0, 0, 0, 0.05); }
.submit-btn { width: 100%; height: 80rpx; display: flex; align-items: center; justify-content: center; background: linear-gradient(to top, #aaf69b, #d1ff7c); color: #000; border: none; border-radius: 40rpx; font-size: 30rpx; font-weight: bold; &::after { border: none; } &[disabled] { background: #ccc; color: #999; } }
</style>
