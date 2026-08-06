<template>
    <view class="min-h-screen bg-[#f5f7fb] px-[24rpx] py-[28rpx] box-border">
        <view class="rounded-[24rpx] bg-gradient-to-br from-[#315cf5] to-[#5169e8] p-[32rpx] text-white shadow-sm">
            <view class="flex items-center"><text class="nc-iconfont nc-icon-huiyuanV6xx text-[42rpx]"></text><text class="ml-[16rpx] text-[34rpx] font-600">同行身份申请</text></view>
            <view class="mt-[14rpx] text-[25rpx] leading-[40rpx] opacity-90">提交真实经营资料，审核通过后即可使用商品素材转发功能。</view>
        </view>
        <view v-if="loading" class="py-[160rpx]"><u-loading-icon text="正在加载申请信息" /></view>
        <view v-else-if="access.allowed" class="mt-[24rpx] bg-white rounded-[24rpx] py-[80rpx] text-center">
            <u-icon name="checkmark-circle-fill" color="#19be6b" size="58" /><view class="text-[32rpx] font-600 mt-[22rpx]">同行身份已开通</view><view class="text-[25rpx] text-[#8a94a6] mt-[12rpx]">现在可以返回商城使用商品转发功能</view>
            <view class="mx-[36rpx] mt-[36rpx]"><u-button type="primary" shape="circle" text="返回商城" @click="goShop" /></view>
        </view>
        <view v-else-if="access.status === 'pending'" class="mt-[24rpx] bg-white rounded-[24rpx] py-[80rpx] text-center">
            <u-icon name="clock-fill" color="#f59a23" size="58" /><view class="text-[32rpx] font-600 mt-[22rpx]">资料审核中</view><view class="text-[25rpx] text-[#8a94a6] mt-[12rpx]">负责人会收到提醒，审核结果将通过系统通知发送给您</view>
        </view>
        <template v-else>
            <view v-if="access.status === 'rejected'" class="mt-[24rpx] rounded-[18rpx] bg-[#fff1f0] px-[24rpx] py-[20rpx] text-[25rpx] text-[#d84a3a]">上次审核未通过：{{ access.review_reason || '请补充经营资料后重新提交' }}</view>
            <view class="mt-[24rpx] rounded-[24rpx] bg-white overflow-hidden">
                <view class="px-[28rpx] pt-[28rpx]"><view class="text-[30rpx] font-600 text-[#26334d]">经营资料</view><view class="text-[24rpx] text-[#8a94a6] mt-[8rpx]">以下资料来自商家配置的申请表单</view></view>
                <view class="px-[12rpx] pb-[24rpx]"><diy-form v-if="access.form_id" ref="formRef" :form_id="access.form_id" form_border="none" /></view>
            </view>
            <view class="h-[150rpx]"></view>
            <view class="fixed left-0 right-0 bottom-0 bg-white px-[28rpx] pt-[18rpx] pb-[calc(18rpx+env(safe-area-inset-bottom))] shadow-[0_-6rpx_24rpx_rgba(35,55,95,.08)]">
                <view><u-button type="primary" shape="circle" :loading="submitting" text="提交审核" @click="submit" /></view>
                <view class="text-center text-[22rpx] text-[#9aa3b3] mt-[10rpx]">提交后将立即通知 {{ access.reviewer_name || '审核负责人' }}</view>
            </view>
        </template>
    </view>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import DiyForm from '@/addon/components/diy-form/index.vue'
import { addFormRecord } from '@/app/api/diy_form'
import { applyGoodsForward, getGoodsForwardAccess } from '@/addon/phone_shop/api/forward'
const loading = ref(true); const submitting = ref(false); const access = ref<any>({}); const formRef = ref<any>()
const loadAccess = async () => { loading.value = true; try { access.value = (await getGoodsForwardAccess()).data || {} } finally { loading.value = false } }
const submit = async () => { if (!formRef.value?.verify()) return; submitting.value = true; try { const formRes:any = await addFormRecord(formRef.value.getData()); const recordId = Number(formRes.data?.record_id || formRes.data?.id || formRes.data); if (!recordId) throw new Error('表单记录创建失败'); await applyGoodsForward({ form_record_id: recordId }); formRef.value.clearStorage(); uni.showToast({ title: '申请已提交', icon: 'success' }); await loadAccess() } catch (e:any) { uni.showToast({ title: e?.message || '提交失败，请重试', icon: 'none' }) } finally { submitting.value = false } }
const goShop = () => uni.redirectTo({ url: '/addon/phone_shop/pages/goods/category' })
onMounted(loadAccess)
</script>
