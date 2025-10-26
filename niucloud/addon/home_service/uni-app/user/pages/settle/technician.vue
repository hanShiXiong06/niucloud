<template>
	<view class="bg-[#fff] min-h-screen overflow-hidden" v-if="!loading" :style="themeColor()">
		<view class="">
			<view class="relative">
				<image class="w-full h-[300rpx] block" mode="widthFix"
					:src="img('addon/home_service/user/settle_technician_banner.png')"></image>
			</view>

			<!-- 技能选择区域 -->
			<view class="bg-white mt-[40rpx] min-h-[50rpx]">
				<view class="flex">
					<view
						class="w-[10rpx] h-[35rpx] mr-[15rpx] bg-[var(--primary-color)] rounded-tr-[5rpx] rounded-br-[5rpx]">
					</view>
					<view class="text-[30rpx] mb-[30rpx]"><text class="font-bold">{{ t('selectSkillsTitle') }}</text>
						<text class="">{{ t('multipleChoice') }}</text></view>
				</view>

				<view class="flex flex-wrap px-[25rpx] mb-[15rpx] ">
					<view v-for="(skill, index) in categoryList" :key="skill.category_id"
						class="bg-[#f5f5f5] ] my-[15rpx]  rounded-[12rpx] text-[28rpx] flex flex-col items-center justify-center py-[10rpx] px-[45rpx] border-style mr-[25rpx]"
						:style="{background:selectedSkills.includes(skill.category_id) ? '#e9ffe1' : '',color:selectedSkills.includes(skill.category_id) ? 'var(--primary-color)' : '', borderColor:selectedSkills.includes(skill.category_id) ? 'var(--primary-color)' : ''}"
						@click="toggleSkill(skill.category_id)">
						<text class="text-center text-[26rpx] py-[25rpx] leading-1">{{ skill.category_name }}</text>
					</view>
				</view>
			</view>

			<!-- 平台优势区域 -->
			<view class="">
				<view class="flex">
					<view
						class="w-[10rpx] h-[35rpx] mr-[15rpx] bg-[var(--primary-color)] rounded-tr-[5rpx] rounded-br-[5rpx]">
					</view>
					<view class="text-[30rpx] mb-[30rpx]"><text class="font-bold">{{ t('ourAdvantages') }}</text></view>
				</view>

				<!-- 多品类覆盖 -->
				<view class="p-[25rpx]">
					<view class="flex items-start mb-[32rpx] ">
						<view
							class="rounded-full bg-blue-100 flex items-center justify-center mr-[20rpx] flex-shrink-0 p-[15rpx] bg-gradient-to-b from-gray-100 to-white">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/banner1.png')"></image>
						</view>
						<view class="flex flex-col justify-around h-[90rpx]">
							<view class="text-[28rpx] mb-[8rpx]">{{ t('multipleCategories') }}</view>
							<view class="text-[24rpx] text-[#999999] leading-4">{{ t('multipleCategoriesDesc') }}</view>
						</view>
					</view>

					<!-- 客户充足 -->
					<view class="flex items-start mb-[32rpx]">
						<view
							class="rounded-full flex items-center justify-center mr-[20rpx] flex-shrink-0 p-[15rpx] bg-gradient-to-b from-gray-100 to-white">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/banner2.png')"></image>
						</view>
						<view class="flex flex-col justify-around h-[90rpx]">
							<view class="text-[28rpx] mb-[8rpx]">{{ t('sufficientCustomers') }}</view>
							<view class="text-[24rpx] text-[#999999] leading-4">{{ t('sufficientCustomersDesc') }}
							</view>
						</view>
					</view>

					<!-- 商家赋能 -->
					<view class="flex items-start mb-[32rpx]">
						<view
							class="rounded-full bg-purple-100 flex items-center justify-center mr-[20rpx] flex-shrink-0 p-[15rpx] bg-gradient-to-b from-gray-100 to-white">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/banner3.png')"></image>
						</view>
						<view class="flex flex-col justify-around h-[90rpx]">
							<view class="text-[28rpx] mb-[8rpx]">{{ t('merchantEmpowerment') }}</view>
							<view class="text-[24rpx] text-[#999999] leading-4">{{ t('merchantEmpowermentDesc') }}
							</view>
						</view>
					</view>

					<!-- 平台帮扶 -->
					<view class="flex items-start">
						<view
							class="rounded-full bg-orange-100 flex items-center justify-center mr-[20rpx] flex-shrink-0 p-[15rpx] bg-gradient-to-b from-gray-100 to-white">
							<image class="w-[60rpx] h-[60rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/settle1.png')"></image>
						</view>
						<view class="flex flex-col justify-around h-[90rpx]">
							<view class="text-[28rpx] mb-[8rpx]">{{ t('platformSupport') }}</view>
							<view class="text-[24rpx] text-[#999999] leading-4">{{ t('platformSupportDesc') }}</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 入驻流程区域 -->
			<view class="bg-white p-[40rpx] mt-[20rpx]">
				<view class="flex ml-[-40rpx]">
					<view
						class="w-[10rpx] h-[35rpx] mr-[15rpx] bg-[var(--primary-color)] rounded-tr-[5rpx] rounded-br-[5rpx]">
					</view>
					<view class="text-[30rpx] mb-[30rpx]"><text class="font-bold">{{ t('settlementProcess') }}</text></view>
				</view>
				<view class="flex items-center justify-between">
					<!-- 步骤1 -->
					<view class="flex flex-col items-center">
						<view
							class="w-[70rpx] h-[70rpx]  rounded-full bg-gradient-to-b from-gray-100 to-white flex items-center justify-center text-white mb-[10rpx]">
							<image class="w-[40rpx] h-[40rpx] " mode="aspectFit"
								:src="img('addon/home_service/user/settle/settle1.png')"></image>
						</view>
						<view class="text-[24rpx] text-center text-[#666] mt-[15rpx]">{{ t('submitData') }}</view>
					</view>

					<!-- 连接线 -->
					<view class="flex-1 h-[2rpx] flex items-center mx-[30rpx] mb-[40rpx]">
						<view class="w-[8rpx] h-[8rpx] rounded-[50%] bg-[#D9D9D9]"></view>
						<view class="w-[12rpx] h-[12rpx] rounded-[50%] mx-[10rpx] bg-[#D9D9D9]"></view>
						<view class="w-[8rpx] h-[8rpx] rounded-[50%] bg-[#D9D9D9]"></view>
					</view>

					<!-- 步骤2 -->
					<view class="flex flex-col items-center">
						<view
							class="w-[70rpx] h-[70rpx] mb-[15rpx] rounded-full bg-gradient-to-b from-gray-100 to-white flex items-center justify-center text-white mb-[10rpx]">
							<image class="w-[40rpx] h-[40rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/settle2.png')"></image>
						</view>
						<view class="text-[24rpx] text-center text-[#666]  mt-[15rpx]">{{ t('dataReview') }}</view>
					</view>

					<!-- 连接线 -->
					<view class="flex-1 h-[2rpx] flex items-center mx-[30rpx] mb-[40rpx]">
						<view class="w-[8rpx] h-[8rpx] rounded-[50%] bg-[#D9D9D9]"></view>
						<view class="w-[12rpx] h-[12rpx] rounded-[50%] mx-[10rpx] bg-[#D9D9D9]"></view>
						<view class="w-[8rpx] h-[8rpx] rounded-[50%] bg-[#D9D9D9]"></view>
					</view>

					<!-- 步骤3 -->
					<view class="flex flex-col items-center">
						<view
							class="w-[70rpx] h-[70rpx] mb-[15rpx] rounded-full bg-gradient-to-b from-gray-100 to-white flex items-center justify-center text-white mb-[10rpx]">
							<image class="w-[40rpx] h-[40rpx]" mode="aspectFit"
								:src="img('addon/home_service/user/settle/settle3.png')"></image>
						</view>
						<view class="text-[24rpx] text-center text-[#666]  mt-[15rpx]">{{ t('settlementComplete') }}</view>
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
	import { onLoad,onShow } from '@dcloudio/uni-app'
	import { getSettleCategoryList } from '@/addon/home_service/user/api/settle'
	import { getTechnicianApply } from '@/addon/home_service/user/api/settle'
	const loading = ref<boolean>(true);
	const categoryList = ref([])
	const getSettleCategoryListFn = () => {
		let params = {
			is_settled: 1,
			pid: 0
		}
		getSettleCategoryList(params).then((res) => {
			categoryList.value = res.data
		}).catch(() => {
		})
	}
	getSettleCategoryListFn()
	// 技能列表数据

	// 选中的技能ID列表
	const selectedSkills = ref<number[]>([])

	// 切换技能选中状态
	const toggleSkill = (categoryId : number) => {
		const skillIndex = selectedSkills.value.indexOf(categoryId)
		if (skillIndex > -1) {
			// 取消选中
			selectedSkills.value.splice(skillIndex, 1)
		} else {
			// 选中
			selectedSkills.value.push(categoryId)
		}
	}

	// 去填写按钮点击事件
	const goFillForm = () => {
		// 检查是否选择了技能
		if (selectedSkills.value.length === 0) {
			uni.showToast({
				title: t('selectAtLeastOneSkill'),
				icon: 'none'
			})
			return
		}
		console.log(selectedSkills.value)
		// 跳转到填写表单页面
		redirect({
			url: '/addon/home_service/user/pages/settle/technician_form',
			param: {
				selectedSkills: selectedSkills.value.join(',')
			}
		})
	}
	const sbumitStatus = ref(0)
	const getTechnicianApplyFn = () => {
		loading.value = true;
		getTechnicianApply().then((res) => {
			sbumitStatus.value = res.data.audit_status
			if (sbumitStatus.value == 0 || sbumitStatus.value == 1 || sbumitStatus.value == -1) {
				setTimeout(()=>{
					loading.value = false;
				},1000)
				redirect({ url: '/addon/home_service/user/pages/settle/submit_success',mode:'reLaunch', param: { status: sbumitStatus.value, formType: 'technician' } })
			}
			setTimeout(()=>{
				loading.value = false;
			},1000)
		})
	}
	onLoad(() => {
		getTechnicianApplyFn()
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

	.active-bg-text {
		background-color: #e9ffe1;
		color: var(--primary-color);
		border:2rpx solid var(--primary-color) !important;
	}
	.border-style{
		border:2rpx solid #ffffff;
	}
</style>