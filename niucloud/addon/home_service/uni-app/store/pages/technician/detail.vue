<template>
	<view class="bg-[var(--page-bg-color)]  overflow-hidden position-size" v-if="!loading"
		:style="{backgroundImage:'url(' + img('addon/home_service/store/technician/detail-bg.png') + ')'}">
		<view :style="themeColor()">
			<!-- #ifdef MP-WEIXIN -->
			<u-navbar :title="t('technicianDetail')" :autoBack="true" leftIconSize="0"
			:bgColor="scrollTop > 50 ? '#ffffff' : 'transparent'" placeholder></u-navbar>
			<!-- #endif -->
			<!-- #ifndef MP-WEIXIN -->
			<view class="h-[40rpx] w-[100vw]"></view>
			<!-- #endif -->
			<!-- 师傅基本信息区 - 正确实现背景图片 -->
			<view class="relative pt-[15rpx] px-[25rpx]">
				<view class="relative z-10 flex items-start">
					<!-- 师傅照片 -->
					<u--image :src="img(technician.headimg)" width="210rpx" height="210rpx" radius="100rpx"
						mode="aspectFill">
						<template #error>
							<image :src="img('static/resource/images/default_headimg.png')"
								class="w-[210rpx] h-[210rpx] rounded-full  border-2 border-white" mode="aspectFill">
							</image>
						</template>
					</u--image>

					<!-- 师傅基本信息 -->
					<view class="ml-[15px] flex-1">
						<!-- 姓名与状态 -->
						<view class="flex items-center mb-[10rpx]">
							<text
								class="text-[30rpx] font-bold text-[var(--text-color-primary)] max-w-[170px] leading-5">{{technician.real_name}}</text>
							<text
								:class="technician.status == '1' ? 'text-xs bg-green-500 text-white' : 'text-xs bg-gray-400 text-white'"
								class="px-2.5 py-0.5 rounded-[5rpx] ml-[15rpx]">
								{{ technician.status == '1' ? t('working') : t('resting') }}
							</text>
						</view>

						<!-- 从业信息与地址 -->
						<text class="text-xs text-gray-500 mb-[10rpx] block">{{t('phoneNumber')}}{{technician.mobile}}</text>
						<text
							class="text-xs text-gray-500 block">{{technician.full_address}}</text>
						<!-- 服务类型标签 -->
						<view class="relative z-10 flex flex-wrap"
							v-if="technician?.category_name && technician?.category_name.length">
							<text v-for="(skill, idx) in technician?.category_name"
								class="text-[24rpx] bg-[#ffffff] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]"
								v-show="idx < 2">{{ skill.category_name }}</text>
							<text v-if="technician.category_name.length >= 3 && !showMoreTag"
								@click="showMoreTag = true"
								class="text-[24rpx] bg-[#ffffff] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]">...</text>
							<text v-for="(skill, idx) in technician.category_name"
								class="text-[24rpx] bg-[#ffffff] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]"
								v-show="idx >= 2 && showMoreTag">{{ skill.category_name }}</text>
							<text v-if="showMoreTag" @click="showMoreTag = false"
								class="text-[24rpx] bg-[#ffffff] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx] flex items-center leading-1"><text
									class="iconfont iconshangV6xx-1"></text></text>
						</view>
					</view>
				</view>

			</view>

			<!-- 工作数据统计区 - 正确实现背景图片 -->
			<view class="relative bg-white mt-[20rpx] p-4 mx-[24rpx] rounded-lg" style="
			    background: linear-gradient( 180deg, #FFFFFF 0%, transparent 100%);
			">
				<view class="relative z-10 flex justify-center">
					<!-- 四个数据卡片水平排列 -->
					<view class="w-[80px] text-center">
						<text class="text-[40rpx] font-bold block mb-1">{{ technician.order_count }} <text
								class="text-[20rpx]">单</text></text>
						<text class="text-xs text-gray-500">{{t('completedOrders')}}</text>
					</view>
					<view class="w-[80px] text-center mx-[10px]">
						<text class="text-[40rpx] font-bold block mb-1">{{ technician.abnormal_count }}</text>
						<text class="text-xs text-gray-500">{{t('abnormalOrders')}}</text>
					</view>
					<view class="w-[80px] text-center mx-[10px]">
						<text class="text-[40rpx] font-bold block mb-1">{{ technician.refund_count }}</text>
						<text class="text-xs text-gray-500">{{t('refundOrders')}}</text>
					</view>
					<view class="w-[80px] text-center">
						<text class="text-[40rpx] font-bold block mb-1">{{ technician.positive_rate }}</text>
						<text class="text-xs text-gray-500">{{t('positiveRate')}}</text>
					</view>
				</view>
			</view>
			<view class="px-[24rpx] pb-[30rpx] bg-[#f6f6f6] pt-[15rpx]">
				<!-- 个人简介区 -->
				<view class="bg-white p-4 rounded-lg">
					<!-- 个人简介标题 -->

					<view class="mb-[20rpx] relative">
						<text class="text-[32rpx] font-bold text-[var(--text-color-primary)]">{{t('personalProfile')}}</text>
						<!-- 背景条图片 -->
						<view class="absolute bottom-[-10rpx] left-0 ">
							<image class="w-[50rpx] h-[20rpx] block"
								:src="img('addon/home_service/store/technician/background_bar.png')" mode="widthFix">
							</image>
						</view>

					</view>

					<!-- 基本信息项 -->
					<view class="personal-info-container">
						<!-- 师傅基本信息 -->
						<view class="mb-[15px]">
							<view class="py-[10px] border-b border-gray-100">
								<text class="text-[30rpx] text-gray-700 block mb-[5px]">{{t('name')}}</text>
								<text class="text-[26rpx] text-gray-900">{{technician.real_name}}</text>
							</view>
							<!-- <view class="py-[10px] border-b border-gray-100">
								<text class="text-[30rpx] text-gray-700 block mb-[5px]">身份证号</text>
								<text class="text-[26rpx] text-gray-900">{{technician.real_name}}</text>
							</view> -->
							<view class="py-[10px] border-b border-gray-100">
								<text class="text-[30rpx] text-gray-700 block mb-[5px]">{{t('phone')}}</text>
								<text class="text-[26rpx] text-gray-900">{{technician.mobile}}</text>
							</view>
						</view>

						<!-- 资质证书 - 修改为图片展示 -->
						<view class="mb-[25px]">
							<text class="text-[30rpx] text-gray-700 block mb-[15px] block">{{t('qualificationCertificate')}}</text>
							<!-- 资质证书图片展示区域 -->
							<view class="grid grid-cols-4 gap-4">
								<view v-for="(item,index) in technician.certificate" :key="index">
									<u--image :src="img(item)" width="130rpx" height="130rpx" radius="10rpx">
										<view slot="error" style="font-size: 24rpx;">{{t('loadFailed')}}</view>
									</u--image>
								</view>
							</view>
						</view>

						<!-- 自我介绍 -->
						<view>
							<text class="text-[30rpx] text-gray-700 block mb-[15px] block">{{t('selfIntroduction')}}</text>
							<text class="text-sm whitespace-pre-line">{{ technician.intro || t('noIntroduction') }}</text>
						</view>
					</view>
				</view>

				<!-- 技能专长区 -->
				<view class="bg-white mt-[24rpx] p-4 rounded-lg">
					<!-- 技能专长标题 -->
					<view class="mb-[20rpx] relative">
						<text class="text-[32rpx] font-bold text-[var(--text-color-primary)]">{{t('skills')}}</text>
						<!-- 背景条图片 -->
						<view class="absolute bottom-[-10rpx] left-0 ">
							<image class="w-[50rpx] h-[20rpx] block"
								:src="img('addon/home_service/store/technician/background_bar.png')" mode="widthFix">
							</image>
						</view>

					</view>

					<!-- 技能卡片区域 - 左右留出空白 -->
					<view class="mx-auto rounded-xl">
						<!-- 技能卡片 -->
						<view class="space-y-[15px]">
							<view class="bg-[#F6FCFF] p-[25rpx] rounded-lg "
								v-for="(skillItem, index) in technician.category_name" :key="index">
								<text
									class="text-sm !font-bold text-[#1773FF] skill-name">{{ skillItem.category_name }}</text>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, computed, onMounted } from 'vue'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import { onLoad, onPageScroll } from '@dcloudio/uni-app'
	// 修复导入路径，使用正确的API文件位置
	import { getTechnicianDetail } from '@/addon/home_service/store/api/technician'
	const technician_id = ref('')
	// 师傅详情数据
	const loading = ref<boolean>(true);
	const technician = ref<any>({})
	const scrollTop = ref(0)
	onPageScroll((e : any) => {
		scrollTop.value = e.scrollTop
	})
	const getTechnicianDetailFn = () => {
		loading.value = true
		getTechnicianDetail(technician_id.value).then((res) => {
			loading.value = false
			technician.value = res.data
		}).catch((err)=>{
			loading.value = false
		})
	}
	onLoad((data) => {
		technician_id.value = data.technician_id || ''
		getTechnicianDetailFn()
	})
	const showMoreTag = ref(false)
	// 返回上一页
	const goBack = () => {
		uni.navigateBack()
	}
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
	/* 基础样式 */
	.body-bottom {
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	/* 技能卡片样式 */
	.skill-card {
		border-radius: 16px;
	}

	/* 技能名称样式 */
	.skill-name {
		color: #1773FF;
	}

	.position-size {
		background-size: 100%;
	}
</style>