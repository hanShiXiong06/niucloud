<template>
	<view class="bg-[#fff] min-h-screen overflow-hidden" v-if="!loading" :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<view class="">
			<view class="relative">
				<image class="w-full h-[300rpx] block" mode="widthFix"
					:src="img('addon/home_service/user/settle/store/technician_settle.png')"></image>
			</view>

			<!-- 平台优势区域 -->
			<view class="pt-[35rpx]">
				<view class="flex">
					<view
						class="w-[10rpx] h-[35rpx] mr-[15rpx] bg-[#004FFF] rounded-tr-[5rpx] rounded-br-[5rpx]">
					</view>
					<view class="text-[32rpx] mb-[30rpx]"><text class="font-bold">{{ t('ourAdvantages') }}</text></view>
				</view>

				<!-- 多品类覆盖 -->
				<view class="p-[25rpx]">
					<view class="flex items-start mb-[32rpx] ">
						<view
							class="rounded-full bg-blue-100 flex items-center justify-center mr-[20rpx] flex-shrink-0 p-[15rpx] bg-gradient-to-b from-gray-100 to-white">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/store/rules.png')"></image>
						</view>
						<view class="flex flex-col justify-around h-[90rpx]">
							<view class="text-[28rpx] font-bold mb-[8rpx]">{{ t('multipleCategories') }}</view>
							<view class="text-[24rpx] text-[#999999] leading-4">{{ t('multipleCategoriesDesc') }}</view>
						</view>
					</view>

					<!-- 客户充足 -->
					<view class="flex items-start mb-[32rpx]">
						<view
							class="rounded-full flex items-center justify-center mr-[20rpx] flex-shrink-0 p-[15rpx] bg-gradient-to-b from-gray-100 to-white">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/store/job_seeker.png')"></image>
						</view>
						<view class="flex flex-col justify-around h-[90rpx]">
							<view class="text-[28rpx] font-bold mb-[8rpx]">{{ t('sufficientCustomers') }}</view>
							<view class="text-[24rpx] text-[#999999] leading-4">{{ t('sufficientCustomersDesc') }}
							</view>
						</view>
					</view>

					<!-- 商家赋能 -->
					<view class="flex items-start mb-[32rpx]">
						<view
							class="rounded-full bg-purple-100 flex items-center justify-center mr-[20rpx] flex-shrink-0 p-[15rpx] bg-gradient-to-b from-gray-100 to-white">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/store/merchant_empowerment.png')"></image>
						</view>
						<view class="flex flex-col justify-around h-[90rpx]">
							<view class="text-[28rpx] font-bold mb-[8rpx]">{{ t('merchantEmpowerment') }}</view>
							<view class="text-[24rpx] text-[#999999] leading-4">{{ t('merchantEmpowermentDesc') }}
							</view>
						</view>
					</view>

					<!-- 平台帮扶 -->
					<view class="flex items-start">
						<view
							class="rounded-full bg-orange-100 flex items-center justify-center mr-[20rpx] flex-shrink-0 p-[15rpx] bg-gradient-to-b from-gray-100 to-white">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/store/platform_support.png')"></image>
						</view>
						<view class="flex flex-col justify-around h-[90rpx]">
							<view class="text-[28rpx] font-bold mb-[8rpx]">{{ t('platformSupport') }}</view>
							<view class="text-[24rpx] text-[#999999] leading-4">{{ t('platformSupportDesc') }}</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 入驻流程区域 -->
			<view class="bg-white p-[40rpx] mt-[20rpx]">
				<view class="flex ml-[-40rpx]">
					<view
						class="w-[10rpx] h-[35rpx] mr-[15rpx] bg-[#004FFF] rounded-tr-[5rpx] rounded-br-[5rpx]">
					</view>
					<view class="text-[30rpx] mb-[30rpx]"><text class="font-bold">{{ t('settlementProcess') }}</text>
					</view>
				</view>
				<view class="flex items-center justify-between">
					<!-- 步骤1 -->
					<view class="flex flex-col items-center">
						<view
							class="w-[80rpx] h-[80rpx] rounded-full bg-gradient-to-b from-gray-100 to-white flex items-center justify-center text-white mb-[10rpx]">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/settle1.png')"></image>
						</view>
						<view class="text-[24rpx] text-center text-[#666]">{{ t('submitData') }}</view>
					</view>

					<!-- 连接线 -->
					<view class="flex-1 h-[6rpx] bg-gray-200 mx-[10rpx]"></view>

					<!-- 步骤2 -->
					<view class="flex flex-col items-center">
						<view
							class="w-[80rpx] h-[80rpx] rounded-full bg-gradient-to-b from-gray-100 to-white flex items-center justify-center text-white mb-[10rpx]">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/store/review.png')"></image>
						</view>
						<view class="text-[24rpx] text-center text-[#666]">{{ t('dataReview') }}</view>
					</view>

					<!-- 连接线 -->
					<view class="flex-1 h-[6rpx] bg-gray-200 mx-[10rpx]"></view>

					<!-- 步骤3 -->
					<view class="flex flex-col items-center">
						<view
							class="w-[80rpx] h-[80rpx] rounded-full bg-gradient-to-b from-gray-100 to-white flex items-center justify-center text-white mb-[10rpx]">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/store/check.png')"></image>
						</view>
						<view class="text-[24rpx] text-center text-[#666]">{{ t('settlementComplete') }}</view>
					</view>
				</view>
			</view>

			<!-- 底部按钮区域 -->
			<view class="w-full footer">
				<view
					class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
					<button hover-class="none"
						class="!bg-[var(--primary-color)] text-[#fff] h-[80rpx] leading-[80rpx] rounded-[20rpx] text-[26rpx] mx-0 flex-1 font-500"
						@click="goFillForm">
						{{ t('goFill') }}</button>
				</view>
			</view>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref } from 'vue'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import { onLoad } from '@dcloudio/uni-app'
	import LoadingPage from '@/components/loading-page/loading-page.vue'
	import { getStoreApply } from '@/addon/home_service/user/api/settle'
	import { topTabar } from '@/utils/topTabbar';
	// 系统状态管理
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '申请入驻', topStatusBar: { textColor: '#333' } })
	const loading = ref<boolean>(true);

	// 去填写按钮点击事件
	const goFillForm = () => {
		// 跳转到填写表单页面
		redirect({
			url: '/addon/home_service/user/pages/settle/store_form'
		})
	}
	const sbumitStatus = ref(0)
	const getStoreApplyFn = () => {
		loading.value = true;
		getStoreApply().then((res) => {
			if (res.data && res.data.member_id) {
				sbumitStatus.value = res.data.audit_status
				redirect({
					url: '/addon/home_service/user/pages/settle/submit_success',
					mode: 'reLaunch',
					param: { status: res.data.audit_status, formType: 'store' }
				})
				setTimeout(() => {
					loading.value = false;
				}, 1000)
			} else {
				setTimeout(() => {
					loading.value = false;
				}, 1000)
			}
		})
	}
	onLoad(() => {
		getStoreApplyFn()
	})
</script>

<style lang="scss" scoped>
	// 样式

	// 适配安全区域
	page {
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	// 头部样式
	.fixed-top {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		z-index: 10;
	}

	.footer {
		height: calc(80rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(80rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
</style>