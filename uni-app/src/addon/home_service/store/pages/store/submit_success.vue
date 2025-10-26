<template>
	<view class="bg-[#ffffff] min-h-screen flex flex-col items-center justify-center px-[40rpx]">
		<!-- 成功图标 -->
		<view class="w-[260rpx] h-[260rpx] rounded-full flex items-center justify-center mb-[60rpx]">
			<image class="w-[260rpx] h-[260rpx]" mode="aspectFit" v-if="submitStatus == 0"
				:src="img('addon/home_service/user/settle/submit-wait.png')">
			</image>
			<image class="w-[260rpx] h-[260rpx]" mode="aspectFit" v-if="submitStatus == -1"
				:src="img('addon/home_service/user/settle/submit-error.png')">
			</image>
			<image class="w-[260rpx] h-[260rpx]" mode="aspectFit" v-if="submitStatus == 1"
				:src="img('addon/home_service/user/settle/submit-success.png')">
			</image>
		</view>

		<!-- 标题 -->
		<view class="text-[40rpx] text-gray-800 mb-[40rpx]" v-if="submitStatus == 0">{{ t('reviewingTitle') }}</view>
		<view class="text-[40rpx] text-gray-800 mb-[40rpx]" v-if="submitStatus == -1">{{ t('rejectedTitle') }}</view>
		<view class="text-[40rpx] text-gray-800 mb-[40rpx]" v-if="submitStatus == 1">{{ t('successTitle') }}</view>

		<!-- 描述 -->
		<view class="text-[24rpx] text-[#999] text-center mb-[60rpx]" v-if="submitStatus == 0">{{ t('description') }}
		</view>
		<view class="text-[24rpx] text-[#999] text-center mb-[60rpx]" v-if="submitStatus == 1">{{
			t('descriptionSuccess') }}</view>
		<view class="text-[24rpx] text-[#999] text-center mb-[60rpx]" v-if="submitStatus == -1">{{
			infoData.audit_remark }}</view>
		<view class="w-full space-y-[20rpx] flex flex-col items-center justify-center ">
			<view class="bg-[#F6F6F6] rounded-[100rpx] flex items-center justify-between p-[35rpx] mb-[10rpx] w-[80%]">
				<view class="flex items-center mr-[35rpx]">
					<image class="w-[30rpx] h-[30rpx] mr-[15rpx]" mode="aspectFit" v-if="submitStatus == 0"
						:src="img('addon/home_service/user/settle/submit-wait-dui.png')">
					</image>
					<image class="w-[30rpx] h-[30rpx] mr-[15rpx]" mode="aspectFit" v-else
						:src="img('addon/home_service/user/settle/submit-success-dui.png')">
					</image>
					{{ t('applicationSubmitted') }}
				</view>
				<view class="text-[#999] text-[25rpx]">
					{{ infoData.create_time }}
				</view>
			</view>

			<view class="bg-[#F6F6F6] rounded-[100rpx] flex items-center justify-between p-[35rpx] w-[80%]"  v-if="submitStatus != 0" >
				<view class="flex items-center mr-[35rpx]">
					<image class="w-[30rpx] h-[30rpx] mr-[15rpx]" mode="aspectFit" v-if="submitStatus != 0"
						:src="img('addon/home_service/user/settle/submit-success-dui.png')">
					</image>
					<view class="border-1 border-solid border-[#ccc] mr-[15rpx] w-[28rpx] h-[28rpx] rounded-[50%]"
						v-else>
					</view>
					{{ t('awaitingSettlement') }}
				</view>


				<view class="text-[#999] text-[25rpx]" v-if="infoData.audit_time ">
					{{ (infoData.audit_time) }}
				</view>
			</view>
		</view>


		<view class="w-full footer bg-[#fff]">
			<view
				class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
				<button hover-class="none"
					class=" !text-[#fff] !bg-[var(--store-bg-one)] h-[80rpx] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500"
					@click="goBack" :class="{ 'opacity-50': btnDisabled }">{{ submitStatus == -1 ? '重新申请' : '返回' }}
				</button>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/locale'
import { img, redirect, timeStampTurnTime } from '@/utils/common'
import { onLoad } from '@dcloudio/uni-app'
import { getStoreApply } from '@/addon/home_service/store/api/store'
const submitStatus = ref(2)
const infoData = ref({})
const btnDisabled = ref(false)

// 返回首页或重新申请
const goBack = () => {
	if (submitStatus.value == -1) {
		// 审核被拒绝时，跳转到settle页面重新申请，并添加一个特殊参数
		redirect({
			url: '/addon/home_service/store/pages/store/settle', // 修改为在pages.json中注册的路径
			param: { fromReapply: true }
		})
		return false;
	} else {
		// 其他情况（待审核、审核通过）返回会员首页
		redirect({ url: '/addon/home_service/store/pages/member/index' }) // 同样修改为注册路径
	}
}

// 页面显示时执行
onLoad((data: any) => {
  // 先使用URL参数中的状态作为初始值
  submitStatus.value = data.status
  // 调用门店接口获取最新数据
  getStoreApply().then((res) => {
    infoData.value = res.data
    // 从接口返回的数据中更新状态，确保显示最新的审核状态
    if (res.data && res.data.audit_status !== undefined) {
      submitStatus.value = res.data.audit_status
    }
  })
})
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
// 样式

// 适配安全区域
page {
	padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
	padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
}

// 审核提示区域样式
.tips-card {
	background: #fff;
	border-radius: 20rpx;
	box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.footer {
	height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
	height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
</style>