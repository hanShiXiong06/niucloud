<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="publish-page" v-if="isFeatureEnabled">
        <view class="form-section">
            <view class="section-title">岗位信息</view>
            <view class="form-item">
                <text class="label required">招聘标题</text>
                <input v-model="form.title" placeholder="如：图书馆整理兼职" maxlength="40" />
            </view>
            <view class="form-item">
                <text class="label required">岗位类型</text>
                <input v-model="form.job_type" placeholder="如：促销/家教/地推" maxlength="20" />
            </view>
            <view class="form-item">
                <text class="label required">薪资说明</text>
                <input v-model="form.salary" placeholder="如：20元/小时，日结" maxlength="30" />
            </view>
            <view class="form-item">
                <text class="label">工作时间</text>
                <input v-model="form.work_time" placeholder="如：周一到周五 18:00-21:00" maxlength="60" />
            </view>
            <view class="form-item">
                <text class="label">工作地点</text>
                <input v-model="form.location" placeholder="请输入工作地点" maxlength="60" />
            </view>
            <view class="form-item">
                <text class="label">联系电话</text>
                <input v-model="form.contact" placeholder="请输入联系方式" maxlength="30" />
            </view>
            <view class="form-item">
                <text class="label">招聘人数</text>
                <input v-model="form.recruit_count" type="number" placeholder="如：3" maxlength="4" />
            </view>
        </view>

        <view class="form-section">
            <view class="section-title">详情描述</view>
            <view class="form-item">
                <textarea v-model="form.content" placeholder="描述岗位职责、要求、结算方式等" maxlength="500" :auto-height="true" />
            </view>
            <view class="form-item">
                <text class="label required">信息费</text>
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
            <button class="submit-btn" :disabled="!canSubmit" @click="submitPublish">发布兼职</button>
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

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_parttime')
const imageStr = ref('')
const yinsiText = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const form = ref({
    title: '',
    job_type: '',
    salary: '',
    work_time: '',
    location: '',
    contact: '',
    recruit_count: '',
    content: '',
    price: ''
})

const canSubmit = computed(() => !!form.value.title && !!form.value.job_type && !!form.value.salary && parseFloat(form.value.price) > 0)

onMounted(() => {
    loadConfig()
    tryBindFenxiao()
})

const submitPublish = async () => {
    if (!canSubmit.value) return
    const ext = {
        job_type: form.value.job_type,
        salary: form.value.salary,
        work_time: form.value.work_time,
        location: form.value.location,
        contact: form.value.contact,
        recruit_count: form.value.recruit_count
    }
    try {
        uni.showLoading({ title: '提交中...' })
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'PARTTIME',
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
