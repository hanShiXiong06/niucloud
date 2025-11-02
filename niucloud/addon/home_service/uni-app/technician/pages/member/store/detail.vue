<template>
	<view class="bg-[var(--page-bg-color)] overflow-hidden component-class" :style="themeColor()" v-if="!loading">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" isBack />
		<!-- #endif -->
		<!-- 主体内容区域 -->
		<view class="" >
			<!-- 基本信息 -->
			<view class="p-[25rpx] bg-white m-[24rpx] rounded-lg pb-[10rpx]">
				<view class="flex items-center">
					<image :src="img('/addon/home_service/store/member/store_info/exclamation_mark.png')"
						class="w-5 h-5 mr-2" mode="aspectFit"></image>
					<text class="text-base font-bold">{{ t('basicInfo') }}</text>
				</view>
				<view class=" rounded-lg overflow-hidden">
					<view class="flex justify-between items-center py-[20rpx] border-bottom-style">
						<text class="text-gray-600">{{ t('storeName') }}</text>
						<text class="text-gray-900">{{ storeInfo.store_name }}</text>
					</view>
					<view class="flex justify-between items-center py-[20rpx]">
						<text class="text-gray-600">{{ t('storeStaff') }}</text>
						<text class="text-gray-900">{{ storeInfo.technician_count }}</text>
					</view>
				</view>
			</view>

			<!-- 联系方式 -->
			<view class="p-[25rpx] bg-white m-[24rpx] rounded-lg pb-[10rpx]">
				<view class="flex items-center mb-4">
					<view class="flex items-center">
						<image :src="img('/addon/home_service/store/member/store_info/telephone.png')"
							class="w-5 h-5 mr-2" mode="aspectFit"></image>
						<text class="text-base font-medium">{{ t('contactInfo') }}</text>
					</view>
				</view>
				<view class=" rounded-lg overflow-hidden">
					<view class="flex justify-between items-center py-[20rpx] border-bottom-style">
						<text class="text-gray-600">{{ t('manager') }}</text>
						<view class="flex items-center">
							<text class="text-gray-900 mr-2">{{ storeInfo.contact_name }}</text>
						</view>
					</view>
					<view class="flex justify-between items-center py-[20rpx]">
						<text class="text-gray-600">{{ t('contactPhone') }}</text>
						<view class="flex items-center">
							<text class="text-gray-900 mr-2">{{ storeInfo.mobile }}</text>
						</view>
					</view>
				</view>
			</view>

			<!-- 地址信息 -->
			<view class="p-[25rpx] bg-white m-[24rpx] rounded-lg pb-[25rpx]">
				<view class="flex items-center mb-4">
					<image :src="img('/addon/home_service/store/member/store_info/address.png')" class="w-5 h-5 mr-2"
						mode="aspectFit"></image>
					<text class="text-base font-medium">{{ t('addressInfo') }}</text>
				</view>
				<view class=" rounded-lg overflow-hidden">
					<view class="mb-[25rpx]">
						<text class="text-gray-900">{{ storeInfo.full_address }}</text>
					</view>
					<!-- 地图展示 - 使用uni-app地图组件 -->
					<view class="h-[200px] w-full">
						<map class="map-body w-full h-[600rpx]" :latitude="storeInfo.lat" :longitude="storeInfo.lng" :markers="covers"></map>
					</view>
				</view>
			</view>

			<!-- 认证状态 -->
			<view class="p-[25rpx] bg-white m-[24rpx] rounded-lg pb-[15rpx]">
				<view class="flex items-center mb-4">
					<image :src="img('/addon/home_service/store/member/store_info/renzen.png')" class="w-5 h-5 mr-2"
						mode="aspectFit"></image>
					<text class="text-base font-medium">{{ t('authenticationStatus') }}</text>
				</view>
				<view class=" rounded-lg overflow-hidden">
					<view class="py-[20rpx] border-bottom-style">
						<view class="flex items-center justify-between">
							<view class="w-8 h-5 flex-shrink-0 mr-2">
								<image :src="img('/addon/home_service/store/member/store_info/personal_profile.png')"
									class="w-full h-full" mode="aspectFit"></image>
							</view>
							<view class="flex-grow">
								<text class="text-gray-600 block">{{ t('businessLicense') }}</text>
								<text
									class="text-[#999999] block">{{ storeInfo.license_img ? t('completed') : t('uncompleted') }}</text>
							</view>
							<view class="w-5 h-5 flex-shrink-0">
								<image :src="img('/addon/home_service/store/member/store_info/check_mark.png')"
									class="w-full h-full text-green-500" mode="aspectFit" v-if="storeInfo.license_img">
								</image>
							</view>
						</view>
					</view>
					<view class="py-[20rpx] border-bottom-style">
						<view class="flex items-center justify-between">
							<view class="w-8 h-5 flex-shrink-0 mr-2">
								<image :src="img('/addon/home_service/store/member/store_info/my.png')"
									class="w-full h-full" mode="aspectFit"></image>
							</view>
							<view class="flex-grow">
								<text class="text-gray-600 block">{{ t('idAuthentication') }}</text>
								<text
									class="text-[#999999] block">{{ storeInfo.id_card_front ? t('completed') : t('uncompleted') }}</text>
							</view>
							<view class="w-5 h-5 flex-shrink-0">
								<image :src="img('/addon/home_service/store/member/store_info/check_mark.png')"
									class="w-full h-full text-green-500" mode="aspectFit"
									v-if="storeInfo.id_card_front"></image>
							</view>
						</view>
					</view>
					<view class="py-[20rpx]">
						<view class="flex items-center justify-between">
							<view class="w-8 h-5 flex-shrink-0 mr-2">
								<image :src="img('/addon/home_service/store/member/store_info/address2.png')"
									class="w-full h-full" mode="aspectFit"></image>
							</view>
							<view class="flex-grow">
								<text class="text-gray-600 block">{{ t('addressAuthentication') }}</text>
								<text
									class="text-[#999999] block">{{ storeInfo.full_address ? t('completed') : t('uncompleted') }}</text>
							</view>
							<view class="w-5 h-5 flex-shrink-0">
								<image :src="img('/addon/home_service/store/member/store_info/check_mark.png')"
									class="w-full h-full text-green-500" mode="aspectFit" v-if="storeInfo.full_address">
								</image>
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
	import { ref, onMounted } from 'vue'
	import { t } from '@/locale'
	import { redirect, img } from '@/utils/common'
	import { getMyStore } from '@/addon/home_service/technician/api/store'
	import { topTabar } from '@/utils/topTabbar';
	// 系统状态管理
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '机构资料', topStatusBar: { textColor: '#333' } })
	const loading = ref<boolean>(true);
	// 门店信息数据
	const storeInfo = ref<any>({
		store_name: '',
		contact_name: '',
		mobile: '',
		full_address: '',
		lat: '',
		lng: '',
		technician_count: 0,
		license_img: '',
		id_card_front: ''
	})

	// 地图相关数据
	const latitude = ref<number>(0)
	const longitude = ref<number>(0)
	const markers = ref<any>([])
	const covers = ref<any>([])

	// 获取门店信息
	const getStoreInfoData = () => {
		loading.value = true
		getMyStore().then((res : any) => {
			loading.value = false
			if (res.code === 1 && res.data) {
				// 检查是否没有store_id（即没有入驻门店）
				if (!res.data.store_id) {
					uni.showModal({
						title: '提示',
						content: '没有入驻门店',
						showCancel: false,
						success: function (res) {
							if (res.confirm) {


 redirect({
        url: '/addon/home_service/technician/pages/member/index',
        param: {}
    })
							}
						}
					})
					return
				}
				
				storeInfo.value = res.data

				// 更新地图数据
				if (res.data.lat && res.data.lng) {
					latitude.value = parseFloat(res.data.lat)
					longitude.value = parseFloat(res.data.lng)
					covers.value = [{
						id: 1,
						latitude: parseFloat(res.data.lat),
						longitude: parseFloat(res.data.lng),
						title: res.data.store_name || '门店位置',
						iconPath: '/static/resource/images/diy/shop_default.jpg',
						width: 30,
						height: 30
					}]
				}
			}
		}).catch(() => {
			loading.value = false
			uni.showToast({
				title: t('fetchFailed'),
				icon: 'none'
			})
		})
	}

	// 主题颜色处理
	const themeColor = () => {
		return {
			'--page-bg-color': '#F5F5F5'
		}
	}

	// 页面加载时获取数据
	onMounted(() => {
		getStoreInfoData()
	})
</script>

<style lang="scss" scoped>
</style>
<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>