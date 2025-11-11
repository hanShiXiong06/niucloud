<template>
	<u-navbar title="公告信息" bgColor="#f6f6f6" leftIconSize="15px">
	</u-navbar>
	<u-sticky :customNavHeight="navHeight">
		<view class=" flex w-full  p-[30rpx] py-[20rpx] z-index-999 box-border bg-[#f6f6f6]">
			<view v-for="(item,index) in statusList" :key="index"
				class="mr-[50rpx] flex items-center flex-wrap justify-center relative" @click="changeStatus(index)">
				<view class="mr-[10rpx] flex flex-col items-center relative">
					<view class="" :class="index == statusIndex ? 'text-[var(--technician-bg-one)] font-bold' : ''">
						{{item.name}}
					</view>
					<view class="w-[70rpx] h-[6rpx] bg-[var(--technician-bg-one)] absolute bottom-[-10rpx]"
						v-if="statusIndex == index">
					</view>
				</view>
				<view
					class="text-[20rpx] w-[35rpx] h-[35rpx] flex items-center justify-center text-[#004FFF] leading-1 rounded-style bg-[#E5EDFF]"
					v-if="index == 0">
					{{item.count}}
				</view>
				<view
					class="text-[20rpx] w-[35rpx] h-[35rpx] text-[#FF4400] flex items-center justify-center rounded-style bg-[#FFF0EA]"
					v-else-if="index == 1">
					{{item.count}}
				</view>
				<view class="absolute w-[12rpx] h-[12rpx] bg-[#FF0000] right-0 top-[5rpx] rounded-[50%]" v-else>
				</view>
			</view>
		</view>
	</u-sticky>

	<view class="bg-[#f6f6f6]">
		<view class="mescroll-box">
			<mescroll-body ref="mescrollRef" :down="{ use: false }" @init="mescrollInit" :top="navHeight + 10 + 'px'" @up="getListFn">
				<view class="mx-[30rpx] py-[20rpx] px-[30rpx] bg-[#fff] rounded-[10rpx] mb-[30rpx]"
					v-for="(item,index) in noticeList" :key="index" v-if="noticeList.length">
					<view class="flex justify-between">
						<image :src="img('/addon/home_service/technician/notice_1.png')"
							class="w-[100rpx] h-[100rpx] rounded-[50%] mr-[15rpx] block" mode="aspectFit">
						</image>
						<view class="flex-1 flex flex-col justify-between pt-[10rpx]">
							<view class="flex justify-between">
								<view class="text-[30rpx] font-bold truncate w-[420rpx]">
									{{item.content}}
								</view>
								<view class="text-[24rpx] text-[#999999] flex justify-end items-end">
									17:00
								</view>
							</view>
							<view class="flex justify-between item-center">
								<view class="text-[26rpx] text-[#666666] w-[450rpx] text-ell leading-4">
									<text class="text-[var(--technician-bg-one)]">[{{item.title}}]</text>
									<text>{{item.content}}</text>
								</view>
								<view
									class="text-[20rpx] flex items-center justify-center w-[30rpx] h-[30rpx] bg-[#EF000C] text-[#fff] rounded-[50%]">
									2
								</view>
							</view>

						</view>
					</view>
				</view>
				<view class="pl-[20rpx] pt-[0rpx]" style="width: calc(100% - 182rpx)">
					<mescroll-empty :option="{ icon: img('static/resource/images/empty.png'), tip: t('nothingMore') }"
						v-if="!noticeList.length && !loading && listLoading" class="part"></mescroll-empty>
				</view>
			</mescroll-body>
		</view>
		<loading-page :loading="loading"></loading-page>
		<tabbar :value="2"></tabbar>
	</view>
</template>

<script setup lang="ts">
	import { img, redirect, getToken } from '@/utils/common'
	import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	import { ref, computed, onMounted } from 'vue'
	import tabbar from '@/addon/home_service/technician/components/tabbar/tabbar.vue'
	import useSystemStore from '@/stores/system';
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { getNoticeList } from '@/addon/home_service/technician/api/notice'
	import { t } from '@/locale'
	const systemStore = useSystemStore()
	const platform = systemStore.systemInfo.platform;
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	const noticeList = ref([])
	const loading = ref<boolean>(true) //页面加载动画
	const listLoading = ref<boolean>(false) //列表加载动画
	const statusIndex = ref(0)
	const changeStatus = (e : any) => {
		statusIndex.value = e
	}
	
	// 1. 小程序菜单按钮（胶囊）信息：用于计算适配距离
	const menuButtonInfo = ref({});
	menuButtonInfo.value = systemStore.menuButtonInfo
	// 2. 导航栏核心适配数据
	// 导航栏顶部内边距（小程序：胶囊top值；H5：默认状态栏高度）
	const navPaddingTop = computed(() => {
		// #ifdef MP-WEIXIN
		return menuButtonInfo.value.top || 10; // 小程序：胶囊top，空值兜底10px
		// #endif
		// #ifdef H5
		return 10; // H5：固定高度（可根据设计调整）
		// #endif
	});
	const navPaddingBottom = computed(() => {
		// #ifdef MP-WEIXIN
		return 0; // 小程序：胶囊top，空值兜底10px
		// #endif
		// #ifdef H5
		return 10; // H5：固定高度（可根据设计调整）
		// #endif
	});
	
	// 导航栏总高度（小程序：胶囊高度 + 胶囊top + 底部预留8px；H5：固定高度）
	const navHeight = computed(() => {
		// #ifdef MP-WEIXIN
		return (menuButtonInfo.value.height || 32) + (menuButtonInfo.value.top || 10);
		// #endif
		// #ifdef H5
		return 44; // H5：固定高度（可根据设计调整）
		// #endif
	});
	
	// 胶囊宽度（小程序）
	const navRight = computed(() => {
		// #ifdef MP-WEIXIN
		return (menuButtonInfo.value.width || 30);
		// #endif
	});
	// 胶囊高度（小程序）
	const navJIiaonanHeight = computed(() => {
		// #ifdef MP-WEIXIN
		return (menuButtonInfo.value.height || 30);
		// #endif
	});
	const statusList = ref([
		{
			name: '订单提醒',
			count: 6
		},
		{
			name: '账单通知',
			count: 22
		},
		{
			name: '系统通知',
			count: 1
		}
	])
	// 获取项目列表
	const getListFn = (mescroll : mescrollStructure) => {
		loading.value = true
		listLoading.value = false
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
		}
		getNoticeList(data).then((res : acceptingDataStructure) => {
			let newArr = res.data
			//设置列表数据
			if (mescroll.num == 1) {
				noticeList.value = [] //如果是第一页需手动制空列表
			}
			noticeList.value = noticeList.value.concat(newArr)
			loading.value = false
			mescroll.endSuccess(newArr.length)
			if (!noticeList.value.length) listLoading.value = true
		}).catch(() => {
			loading.value = false
			listLoading.value = true
			mescroll.endErr() // 请求失败, 结束加载
		})
	}
</script>
<style>
	.rounded-style {
		border-radius: 20rpx 20rpx 20rpx 0;
	}

	.text-ell {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 1;
		overflow: hidden;
	}
</style>
<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>