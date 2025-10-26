<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" v-if="!loading">
		<!-- 门店列表 -->
		<!-- #ifdef MP-WEIXIN -->
		 	<u-navbar title="切换账号" autoBack bgColor="#ffffff" :placeholder="true">
			</u-navbar>
		<!-- #endif -->
		<view class="mescroll-body bg-[#f5f5f5] safe-area-padding">
			<view class="py-3">
				<!-- 门店卡片 -->
				<view v-for="(store, index) in storeList" :key="store.store_id"
				:class="store.is_default === 1 ? 'border-style-active' : 'border-style'"
					class="bg-white mx-4 mb-4 rounded-lg overflow-hidden relative store-card"
					@tap="switchStore(store.store_id)">
					<!-- 卡片头部 -->
					<view class="p-4">
						<view class="flex items-start justify-between mb-2">
							<!-- 左侧：门店头像和名称 -->
							<view class="flex items-center">
								<!-- 门店头像（小尺寸） -->
								<image class="w-12 h-12 rounded-md mr-3"
									:src="img(store.headimg || 'static/resource/images/diy/shop_default.jpg')"
									mode="aspectFill" @error="handleImageError(store)" />

								<!-- 当前门店标记 -->
								<view v-if="store.is_default === 1"
									class="absolute left-0 top-0 bg-[#004FFF] text-white text-xs px-2 py-1 z-10 rounded-br-lg">
									{{ t('defaultStore') }}
								</view>

								<!-- 门店名称和服务类型 -->
								<view class="flex flex-col justify-between h-12">
									<view class="text-base font-bold">
										{{ store.store_name || t('storeNamePlaceholder') }}
									</view>
								</view>
							</view>

						</view>

						<!-- 门店地址和位置图标 - 与距离对齐 -->
						<view class="text-gray-600 text-sm mb-2">
							<view class="flex items-center">
								<text class="flex-1">
									{{ store.full_address || t('addressPlaceholder') }}
								</text>
								<image class="w-8 h-8 ml-1 flex-shrink-0"
									:src="img('addon/home_service/store/member/store_lists/position.png')"
									mode="aspectFit" />
							</view>
						</view>

						<!-- 营业时间和距离 -->
						<view class="flex items-center justify-between text-gray-500 text-sm">
							<view class="flex items-center">
								<image class="w-5 h-5 ml-1 mr-1.5"
									:src="img('addon/home_service/store/member/store_lists/time.png')"
									mode="aspectFit" />
								<text>{{ store.business_hours || t('businessHoursPlaceholder') }}</text>
							</view>
							<view class="flex items-center text-gray-500 text-sm">
								<text>{{ store.distance || '?' }}m</text>
							</view>
						</view>
					</view>
					<!-- 门店数据统计 -->
					<view class="mx-4 mb-4 p-1 border-t border-gray-100 bg-[#F6F6F6] rounded-24">
						<view class="flex items-center justify-between w-full space-x-2">
							<!-- 待分配 -->
							<view class="flex flex-col items-center flex-1">
								<view class="text-lg font-bold text-red-500">{{ store.wait_service_count || '0' }}
								</view>
								<view class="text-xs text-gray-500">{{ t('waitService') }}</view>
							</view>

							<!-- 超时 -->
							<view class="flex flex-col items-center flex-1">
								<view class="text-lg font-bold text-yellow-500">{{ store.abnormal_count || '0' }}</view>
								<view class="text-xs text-gray-500">{{ t('timeout') }}</view>
							</view>

							<!-- 售后 -->
							<view class="flex flex-col items-center flex-1">
								<view class="text-lg font-bold text-blue-500">{{ store.refund_count || '0' }}</view>
								<view class="text-xs text-gray-500">{{ t('afterSale') }}</view>
							</view>

							<!-- 今日单量和月收入 -->
							<view class="flex-2 data-stats-card rounded-24">
								<view class="grid grid-cols-2 gap-1">
									<view class="flex flex-col items-center justify-center py-1">
										<view class="text-lg font-bold text-gray-800">{{ store.today_count || '0' }}
										</view>
										<view class="text-xs text-gray-500">{{ t('todayOrders') }}</view>
									</view>
									<view class="flex flex-col items-center justify-center py-1">
										<view class="text-lg font-bold text-orange-500">¥{{ store.month_income || '0' }}
										</view>
										<view class="text-xs text-gray-500">{{ t('monthIncome') }}</view>
									</view>
								</view>
							</view>
						</view>
					</view>

					<!-- 选择按钮 -->
					<view class="absolute right-4 top-1/2 transform -translate-y-1/2">
						<view class="w-5 h-5 rounded-full border-2 border-gray-300"
							:class="selectedStoreId === store.store_id ? 'border-blue-500 bg-blue-500' : ''"
							@tap.stop="selectStore(store.store_id)">
							<view v-if="selectedStoreId === store.store_id"
								class="w-2.5 h-2.5 rounded-full bg-white mx-auto mt-0.5">
							</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 加载中 -->
			<loading-page v-if="loading" />

			<!-- 无数据提示 -->
			<view v-else-if="storeList.length === 0" class="text-center py-10 text-gray-500">
				<text>{{ t('noStoreData') }}</text>
			</view>
		</view>

		<!-- 底部操作栏 -->
		<view class="fixed bottom-0 left-0 right-0 bg-white p-4 border-t border-gray-200 bottom-safe-area">
			<button class="w-full py-[30rpx] bg-[var(--store-bg-one)] text-white rounded-md text-[26rpx] font-medium"
				@tap="confirmSelection">{{ t('confirmSelection') }}</button>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, onMounted } from 'vue'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import { getStoreList, storeSwitch } from '@/addon/home_service/store/api/store'
	import useSystemStore from '@/stores/system'
	import { getStoreInfo } from '@/addon/home_service/store/api/store'
	const storeInfo = ref({})
	const getStoreInfoFn = () =>{
		getStoreInfo().then((res)=>{
			storeInfo.value = res.data
			getStoreListFn()
			if(res.data && res.data.store_id){
			}else{
				uni.showToast({
					title:'您还未申请门店，请先申请入驻门店',
					icon:'none'
				})
				setTimeout(()=>{
					redirect({url:'/addon/home_service/user/pages/settle/store'})
				},1500)
			}
		}).catch((err)=>{
			uni.showToast({
				title:'您还未申请门店，请先申请入驻门店',
				icon:'none'
			})
			setTimeout(()=>{
				redirect({url:'/addon/home_service/user/pages/settle/store'})
			},1500)
		})
	}
	getStoreInfoFn()
	const systemStore = useSystemStore()

	// 门店列表数据
	const storeList = ref<any[]>([])
	// 选中的门店ID
	const selectedStoreId = ref<string>('')
	// 加载状态
	const loading = ref<boolean>(true)

	// 处理图片加载错误
	const handleImageError = (store : any) => {
		// 设置默认图片路径
		store.headimg = 'static/resource/images/diy/shop_default.jpg'
	}

	// 获取门店列表
	const getStoreListFn = () => {
		loading.value = true
		let param = {
			lng: storeInfo.value?.lng,
			lat: storeInfo.value?.lat,
			store_name:''
		}
		getStoreList(param).then((res : any) => {
			loading.value = false
			if (res.code === 1 && res.data && Array.isArray(res.data)) {
				// 处理返回的门店数据
				storeList.value = res.data

				// 默认选中第一个门店
				if (storeList.value.length > 0 && !selectedStoreId.value) {
					selectedStoreId.value = storeList.value[0].store_id
				}
			} else {
				uni.showToast({ title: res.msg || t('getStoreListFailed'), icon: 'none' })
			}
		}).catch((error) => {
			loading.value = false
			uni.showToast({ title: t('getStoreListFailed'), icon: 'none' })
			console.error('获取门店列表错误:', error)
		})
	}
	// 切换选中的门店
	const selectStore = (storeId : string) => {
		selectedStoreId.value = storeId
	}

	// 切换门店并跳转
	const switchStore = (storeId : string) => {
		loading.value = true
		selectedStoreId.value = storeId
		// 调用门店切换API
		storeSwitch(storeId).then((res : any) => {
			if (res.code === 1) {
				uni.showToast({ title: t('storeSwitchSuccess'), icon: 'none' })
				setTimeout(() => {
					redirect({
						url: '/addon/home_service/store/pages/index',
						param: {}
					})
					loading.value = false
				}, 1500)
			} else {
				uni.showToast({ title: res.msg || t('storeSwitchFailed'), icon: 'none' })
			}
		}).catch(() => {
			loading.value = false
			uni.showToast({ title: t('storeSwitchFailed'), icon: 'none' })
		})
	}

	// 确认选择门店
	const confirmSelection = () => {
		if (selectedStoreId.value) {
			// 调用门店切换API
			storeSwitch(selectedStoreId.value).then((res : any) => {
				if (res.code === 1) {
					uni.showToast({ title: t('storeSwitchSuccess'), icon: 'success' })
					// 延迟跳转到index.vue页面
					setTimeout(() => {
						redirect({
							url: '/addon/home_service/store/pages/index',
							param: {}
						})
					}, 1500)
				} else {
					uni.showToast({ title: res.msg || t('storeSwitchFailed'), icon: 'none' })
				}
			}).catch(() => {
				uni.showToast({ title: t('storeSwitchFailed'), icon: 'none' })
			})
		}
	}
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
	.mescroll-body {
		padding-bottom: calc(100rpx + constant(safe-area-inset-bottom)) !important;
		padding-bottom: calc(100rpx + env(safe-area-inset-bottom)) !important;
	}

	.fixed-bottom {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		z-index: 999;
	}

	/* 门店卡片样式 */
	.store-card {
		position: relative;
		transition: all 0.3s ease;
	}

	/* 圆角样式类 */
	.rounded-24 {
		border-radius: 24px;
	}

	/* 今日单量和月收入卡片样式 */
	.data-stats-card {
		background: #FFFFFF;
		border-radius: 20px;
		padding: 6px;
		box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
	}

	/* 安全区域内边距 */
	.safe-area-padding {
		padding-bottom: constant(safe-area-inset-bottom);
		padding-bottom: env(safe-area-inset-bottom);
	}
	.border-style-active{
		border: 2rpx solid var(--store-bg-one);
	}
	.border-style{
		border: 2rpx solid #fff;
	}
</style>