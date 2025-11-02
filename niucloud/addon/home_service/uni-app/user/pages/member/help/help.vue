<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
	<!-- #endif -->
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden component-class" :style="themeColor()"
		v-if="!loading">
		<!-- 顶部导航栏 -->
		<!-- 主体内容区域 -->
		<view class="px-[30rpx] py-[20rpx]">
			<!-- 操作答疑部分 -->
			<view class="mb-[30rpx]">
				<view class="bg-[#ffffff] rounded-[20rpx] overflow-hidden">

					<!-- 动态渲染is_default为1的分类内容 -->
					<template v-if="defaultCategory">
						<view class="px-[30rpx] py-[20rpx] text-[30rpx] font-bold">
							{{ defaultCategory.category_name }}
						</view>

						<view v-for="(item, index) in defaultCategory.help_list" :key="item.help_id"
							class="py-[30rpx] px-[30rpx] flex justify-between items-center"
							:class="{ 'border-b border-[#f0f0f0]': index < defaultCategory.help_list.length - 1 }"
							@click="redirectToProblemDetail(item.help_id)">
							<view class="text-[28rpx]">
								{{ item.name }}
							</view>
							<image :src="img('addon/home_service/technician/arrow-right.png')"
								class="w-[24rpx] h-[24rpx]" mode="aspectFit"
								@error="handleImageError($event, 'arrow-right')"></image>
						</view>
					</template>
					<view class=" h-[500rpx] flex flex-col justify-center items-center" v-else>
						<u-empty :icon="img('static/resource/images/order_empty.png')"
							:text="t('orderInfoNotObtained')" />
					</view>
				</view>
			</view>

			<!-- 常见问题部分 - Tab形式 -->
			<view class="mb-[30rpx]">
				<view class="bg-[#ffffff] rounded-[20rpx] overflow-hidden">
					<view class="px-[30rpx] py-[20rpx] text-[30rpx] font-bold">
						操作答疑
					</view>

					<!-- 动态渲染is_default为0的分类内容 -->
					<template v-if="nonDefaultCategories.length > 0">
						<!-- Tab导航栏 -->
						<view class="flex border-b border-[#f0f0f0] overflow-x-auto whitespace-nowrap">
							<view v-for="category in nonDefaultCategories" :key="category.category_id"
								class="px-[30rpx] py-[20rpx] text-[28rpx] relative"
								@click="selectCategory(category.category_id)">
								{{ category.category_name }}
								<!-- 选中状态下划线 -->
								<view v-if="selectedCategoryId === category.category_id"
									class="absolute bottom-0 left-1/4 right-1/4 h-[4rpx] bg-[var(--primary-color)] rounded-full">
								</view>
							</view>
						</view>

						<!-- 选中分类的数据列表 -->
						<template v-if="selectedCategoryData">
							<template
								v-if="selectedCategoryData.help_list && selectedCategoryData.help_list.length > 0">
								<view v-for="(item, index) in selectedCategoryData.help_list" :key="item.help_id"
									class="py-[30rpx] px-[30rpx] flex justify-between items-center border-b border-[#f0f0f0]"
									@click="redirectToProblemDetail(item.help_id)">
									<view class="text-[28rpx]">
										{{ item.name }}
									</view>
									<image :src="img('addon/home_service/technician/arrow-right.png')"
										class="w-[24rpx] h-[24rpx]" mode="aspectFit"
										@error="handleImageError($event, 'arrow-right')"></image>
								</view>
							</template>
							<view class=" h-[500rpx] flex flex-col justify-center items-center" v-else>
								<u-empty :icon="img('static/resource/images/order_empty.png')"
									:text="t('orderInfoNotObtained')" />
							</view>
						</template>
					</template>
					<view class=" h-[500rpx] flex flex-col justify-center items-center" v-else>
						<u-empty :icon="img('static/resource/images/order_empty.png')"
							:text="t('orderInfoNotObtained')" />
					</view>
				</view>
			</view>


			<!-- 联系客服按钮 -->
			<view class="mt-[40rpx] mb-[60rpx]">
				<button
					class=" h-[88rpx] rounded-full flex items-center justify-center text-[30rpx] font-medium mx-auto border border-gray-200 bg-white text-[#004FFF]"
					@click="redirect({ url: '/app/pages/member/contact' })">
					<image class="w-[40rpx] h-[40rpx] mr-[12rpx]" :src="img('addon/home_service/technician/phone.png')"
						@error="handleImageError($event, 'phone')" />
					联系客服
				</button>
			</view>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, computed, onMounted, watch } from 'vue'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import { getTechnicianHelpData } from '@/addon/home_service/user/api/member'
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '帮助中心', topStatusBar: { textColor: '#333', rollBgColor: "#ffffff" } })
	const loading = ref<boolean>(false)
	// 响应式数据
	const helpData = ref<any[]>([])
	const selectedCategoryId = ref<number | null>(null)

	// 计算属性 - 获取is_default为1的分类
	const defaultCategory = computed(() => {
		return helpData.value.find(item => item.is_default === 1) || null
	})

	// 计算属性 - 获取is_default为0的分类
	const nonDefaultCategories = computed(() => {
		return helpData.value.filter(item => item.is_default === 0) || []
	})

	// 计算属性 - 获取当前选中分类的数据
	const selectedCategoryData = computed(() => {
		if (!selectedCategoryId.value) return null
		return nonDefaultCategories.value.find(item => item.category_id === selectedCategoryId.value) || null
	})

	// 主题颜色计算
	const themeColor = () => {
		return {
			'--primary-color': '#4a6bff'
		}
	}

	// 跳转到问题详情
	const redirectToProblemDetail = (helpId : number) => {
		redirect({
			url: '/addon/home_service/user/pages/member/help/detail',
			param: { helpId: helpId }
		})
	}

	// 选择分类
	const selectCategory = (categoryId : number) => {
		selectedCategoryId.value = categoryId
	}

	// 图片加载失败处理
	const handleImageError = (event : any, type : string) => {
		// 设置默认图片路径
		let defaultImage = 'static/resource/images/diy/shop_default.jpg'
		event.target.src = img(defaultImage)
	}

	// 生命周期钩子 - 获取帮助中心数据
	onMounted(() => {
		fetchHelpData()
	})

	// 监听分类数据变化，自动选择第一个分类
	watch(nonDefaultCategories, (newCategories) => {
		if (newCategories.length > 0 && !selectedCategoryId.value) {
			selectedCategoryId.value = newCategories[0].category_id
		}
	}, { immediate: true })

	// 获取帮助中心数据
	const fetchHelpData = () => {
		loading.value = true
		getTechnicianHelpData().then((res : any) => {
			loading.value = false
			if (res.code === 1 && res.data) {
				helpData.value = res.data
			}
		}).catch(() => {
			loading.value = false
			console.error('获取帮助中心数据失败')
		})
	}
</script>

<style lang="scss" scoped>
	page {
		background-color: #f6f6f6;
	}

	.body-bottom {
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	// Tab导航栏样式优化
	.overflow-x-auto {
		-webkit-overflow-scrolling: touch;
		scrollbar-width: none;
		/* Firefox */
	}

	.overflow-x-auto::-webkit-scrollbar {
		display: none;
		/* Chrome, Safari, Edge */
	}

	.whitespace-nowrap {
		white-space: nowrap;
	}
</style>