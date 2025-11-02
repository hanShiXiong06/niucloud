<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="false" />
	<!-- #endif -->
	<image :src="img('/addon/home_service/store/member/index-bg.png')" class="block w-[100vw] block fixed top-0"
		mode="widthFix"></image>
	<view class="relative z-index-99" v-if="!loading">
		<view class="py-[20rpx]  px-[30rpx] pb-[40rpx]">
			<view class="flex items-center justify-between">
				<view class="mr-[24rpx]">
					<u--image :src="img(storeInfo.headimg)" width="100rpx" height="100rpx" radius="100rpx"
						mode="aspectFill">
						<template #error>
							<image :src="img('static/resource/images/default_headimg.png')"
								class="w-[100rpx] h-[100rpx] rounded-full  border-2 border-white" mode="aspectFill">
							</image>
						</template>
					</u--image>
				</view>
				<view class="flex-1 flex flex-col justify-around h-[96rpx]">
					<view class="flex items-center">
						<view class="text-[34rpx] font-bold">
							{{storeInfo.contact_name}}
						</view>
					</view>
					<view class="text-[26rpx] flex items-center">
						<text>ID:{{storeInfo.store_id}}</text>
						<text class="w-[2rpx] h-[20rpx] bg-[#e3e3e3] mx-[20rpx]"></text>
						<text>{{storeInfo.store_name}}</text>
					</view>
				</view>
				<view class="" @click="redirect({url:'/addon/home_service/user/pages/member/index'})">
					<u-icon name="home" size="25"></u-icon>
				</view>
			</view>
		</view>
		<view class="bg-[#fff] mx-[30rpx] rounded-[25rpx]">
			<view class="flex justify-between bg-[#fff] rounded-[25rpx] p-[30rpx]">
				<view class="flex flex-col items-center"
					@click="redirect({url:'/addon/home_service/store/pages/member/store_info'})">
					<view class="">
						<image :src="img('/addon/home_service/store/member-index-icon1.png')"
							class="block w-[60rpx] h-[60rpx] mr-[10rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx] mt-[20rpx]">
						{{t('storeInfo')}}
					</view>
				</view>
				<view class="flex flex-col items-center"
					@click="redirect({url:'/addon/home_service/store/pages/member/evaluate'})">
					<view class="">
						<image :src="img('/addon/home_service/store/member-index-icon2.png')"
							class="block w-[60rpx] h-[60rpx] mr-[10rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx] mt-[20rpx]">
						{{t('evaluteSet')}}
					</view>
				</view>
				<view class="flex flex-col items-center"
					@click="redirect({url:'/addon/home_service/store/pages/store/settle'})">
					<view class="">
						<image :src="img('/addon/home_service/store/member-index-icon3.png')"
							class="block w-[60rpx] h-[60rpx] mr-[10rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx] mt-[20rpx]">
						{{t('registerStore')}}
					</view>
				</view>

			</view>
		</view>
		<view class="px-[30rpx] bg-[#fff] m-[30rpx] rounded-[25rpx]">
			<view class="flex justify-between" @click="redirect({ url: '/app/pages/member/contact' })">
				<view class="flex items-center">
					<image :src="img('/addon/home_service/store/member-index-icon5.png')"
						class="block w-[36rpx] h-[36rpx] mr-[10rpx]" mode="aspectFit"></image>
				</view>
				<view class="flex justify-between flex-1 items-center py-[29rpx] border-bottom-style">
					<text class="ml-[30rpx]">{{t('callConcat')}}</text>
					<text class="iconfont iconarrow-right text-[26rpx]"></text>
				</view>
			</view>
			<!-- <view class="flex justify-between">
				<view class="flex items-center">
					<image :src="img('/addon/home_service/store/member-index-icon6.png')"
						class="block w-[36rpx] h-[36rpx] mr-[10rpx]" mode="aspectFit"></image>
				</view>
				<view class="flex justify-between flex-1 items-center py-[29rpx] border-bottom-style">
					<text class="ml-[30rpx]">{{t('messageCenter')}}</text>
					<text class="iconfont iconarrow-right text-[26rpx]"></text>
				</view>
			</view> -->
			<view class="flex justify-between"
				@click="redirect({url:'/addon/home_service/store/pages/member/help/help'})">
				<view class="flex items-center">
					<image :src="img('/addon/home_service/store/member-index-icon7.png')"
						class="block w-[36rpx] h-[36rpx] mr-[10rpx]" mode="aspectFit"></image>
				</view>
				<view class="flex justify-between flex-1 items-center py-[29rpx]">
					<text class="ml-[30rpx]">{{t('helpCenter')}}</text>
					<text class="iconfont iconarrow-right text-[26rpx]"></text>
				</view>
			</view>
		</view>


	</view>
	<tabbar :value="3"></tabbar>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { img, redirect, getToken } from '@/utils/common'
	import tabbar from '@/addon/home_service/store/components/tabbar/tabbar'
	import { ref, computed, onMounted } from 'vue'
	import { getStoreInfo } from '@/addon/home_service/store/api/store'
	import { t } from '@/locale'
	import { onShow, onPageScroll } from '@dcloudio/uni-app'
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '机构个人中心', topStatusBar: { textColor: '#333' ,rollBgColor:'transparent'} })
	const loading = ref<boolean>(true);
	const scrollTop = ref(0)
	onPageScroll((e : any) => {
		scrollTop.value = e.scrollTop
	})
	const storeInfo = ref({})
	const getStoreInfoFn = () => {
		loading.value = true
		getStoreInfo().then((res) => {
			storeInfo.value = res.data
			if (res.data && res.data.store_id) {
			} else {
				uni.showToast({
					title: '您还未成为技师，请先申请成为技师',
					icon: 'none'
				})
				setTimeout(() => {
					redirect({ url: '/addon/home_service/user/pages/settle/store' })
				}, 1500)
			}
			loading.value = false
		}).catch((err) => {
			uni.showToast({
				title: '您还未申请门店，请先申请入驻门店',
				icon: 'none'
			})
			setTimeout(() => {
				redirect({ url: '/addon/home_service/user/pages/settle/store' })
			}, 1500)
		})
	}


	onShow(() => {
		if (getToken()) {
			getStoreInfoFn()
		}
	})
</script>

<style>
	page {
		background-color: #f6f6f6;
	}

	/deep/ .uni-swiper-dot {
		width: 8rpx;
		height: 8rpx;
		margin-right: 5rpx;
	}

	/deep/ .uni-swiper-dot-active {
		width: 20rpx;
		height: 8rpx;
		border-radius: 10rpx;
		background-color: var(--store-bg-one);
	}

	.border-bottom-style {
		border-bottom: 2rpx solid #f5f5f5;
	}
</style>

<style lang="scss">
	@import '@/addon/home_service/store/style/index.scss';
</style>