<template>
	<view :style="themeColor()" v-if="!loading">
		<view class="bg-[#f7f7f7] min-h-screen overflow-hidden body-bottom">
			<!-- 自定义头部 -->
			<view class="flex items-center left-0 right-0 z-10 bg-transparent detail-head" :class="{'!bg-[#fff]' :detailHeadBgChange, 'fixed': true}" :style="navbarInnerStyle">
				<view class="flex-center h-[60rpx] rounded-[30rpx] box-border arrow-left px-[40rpx] leading-[1]" :style="navbarInnerArrowStyle">
					<text class="nc-iconfont nc-icon-zuoV6xx text-[18px]" @click="backToPrevious()"></text>
					<text class="w-[2rpx] h-[26rpx] bg-[#999] mx-[14rpx]"></text>
					<text class="nc-iconfont nc-icon-liebiao-xiV6xx1 text-[16px]" @click="topNav = true"></text>
				</view>
				<view class="flex items-center ml-auto">
				</view>
			</view>
			<view class="fixed top-0 left-0 right-0 bottom-0 z-100 bg-transparent" @click="topNav = false" v-if="topNav">
				<view class="search-box w-[202rpx] bg-[#fff] rounded-[12rpx] relative" :style="fixedInnerStyle">
					<view class="px-[20rpx] flex-center" @click="redirect(item.url)" v-for="(item,index) in menuContents" :key="index">
						<text class="text-[30rpx] mr-[10rpx]" :class="item.iconfont"></text>
						<text class="pl-[14rpx] py-[20rpx] flex-1 text-[24rpx] text-[#333] border-0 border-[#ddd] border-b-[1rpx] border-solid">{{ item.name }}</text>
					</view>
				</view>
			</view>
			<view class="relative">
				<u-swiper @change="e => currentNum = e.current + 1" :list="detail.card_cover_thumb_small" indicator
					indicatorMode="dot" indicatorActiveColor="var(--primary-color)" indicatorInactiveColor="#ffffff"
					:autoplay="false" height="100vw" radius="0" @click="swiperClick">
				</u-swiper>
				<view
					class="absolute right-[20rpx] z-index-9 bottom-[20rpx] text-[#ffffff] text-[20rpx] bg-black/50 p-[10rpx] rounded-[30rpx]">
					{{currentNum}} / {{detail.card_cover_thumb_small?.length}}
				</view>
			</view>
			<view class="chunk-wrap pt-2 pb-3 rounded-lg relative mt-[-10rpx] !bg-[#f6f6f6] !mb-[2rpx]">
				<view class="flex items-center justify-between mt-2 ">
					<view class="flex items-end">
						<view class="text-[var(--price-text-color)] text-[28rpx] font-bold flex items-end">
							<text class="text-[22rpx] price-font">￥</text>
							<text class="price-font text-[46rpx] leading-5">{{ Number(detail.price).toFixed(0) }}</text>
							<text
								class="price-font text-[24rpx]">.{{ Number(detail.price).toFixed(2).split('.')[1] }}</text>
							<text v-if="detail.sku_unit">/{{ detail.sku_unit }}</text>
							<text class="price-font text-[24rpx] text-[#999] line-through font-400 ml-[10rpx]"><text
									class="text-[20rpx] price-font">￥</text>{{ Number(detail.original_price).toFixed(2) }}</text>
						</view>
					</view>
					<view class="text-[24rpx] text-[#999999] flex items-center">
						<image :src="img('/addon/home_service/user/goods/flash.png')" class="w-[30rpx] h-[30rpx] block"
							mode="aspectFit"></image>
						{{t('timeWarning')}}
					</view>
				</view>
				<view class="font-bold multi-hidden mt-[20rpx] text-[32rpx] my-[10rpx] leading-5">
					{{ detail.card_name }}
				</view>
				<view class="flex mt-[10rpx]" v-if="detail.guarantee_list && detail.guarantee_list.length">
					<view
						class="border-1 border-solid border-[#CCCCCC] text-[22rpx] mr-[15rpx] px-[10rpx] leading-5 rounded-[8rpx] text-[#444444]"
						v-for="(item,index) in detail.guarantee_list" :key="index">
						{{item.guarantee_title}}
					</view>
				</view>
			</view>
			<view class="px-[24rpx]">
				<view class="rounded-lg bg-[#fff]">
					<view class="flex items-center h-[88rpx] px-[20rpx]  ">
						<text class=" text-[26rpx] leading-[42rpx] font-500 mr-[20rpx] text-[#666]">须知</text>
						<view class="flex-1 text-[#343434]  text-[26rpx]  leading-[42rpx] font-500 text-right mr-[10rpx] ">
							部分使用后不可退款，服务期内随时可用
						</view>
					</view>
					<view class="flex items-center h-[88rpx] px-[20rpx]  ">
						<text class=" text-[26rpx] leading-[42rpx] font-500 mr-[20rpx] text-[#666]">方式</text>
						<view class="flex-1 text-[#343434]  text-[26rpx]  leading-[42rpx] font-500 text-right mr-[10rpx] ">
							提前预约
						</view>
					</view>
					<view @click="openServicesSafePopup" v-if="detail.guarantee_list?.length >= 1"
						class="flex items-center h-[88rpx] px-[20rpx] mb-[20rpx]">
						<text
							class=" text-[26rpx] leading-[42rpx] font-500 mr-[20rpx] text-[#666]">{{ t('serviceSafe') }}</text>
						<view class="flex-1 text-[#343434]  text-[26rpx]  leading-[42rpx] font-500 text-right mr-[10rpx] ">
							{{ detail.guarantee_list[0].guarantee_title }}
						</view>
						<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light6)]"></text>
					</view>
				</view>
			</view>
			<view class="px-[24rpx]">
				<view class="my-[24rpx] py-[30rpx] px-[25rpx] bg-[#fff] rounded-lg">
					<view class="text-[30rpx] text-[#111] pb-[25rpx] border-bottom-style mb-[25rpx]">
						服务详情
					</view>
					<view @click="buyFn" v-for="(item,index) in detail.skuList" class="flex items-center mt-[20rpx]">
						<view class=" text-[26rpx] leading-[42rpx] mr-[20rpx] flex items-center">
							<view class="w-[10rpx] h-[10rpx] rounded-[50%] mr-[15rpx] bg-[#848484]"></view>
							{{ item.sku_name }}
						</view>
						<view class="flex-1 text-[#999999] text-sm leading-[42rpx] font-500 text-right mr-[10rpx] ">
							{{ item.max_use_times }}{{item.sku_unit}}
						</view>
					</view>
				</view>


				<view class="my-[24rpx] py-[30rpx] px-[25rpx] bg-[#fff] rounded-lg">
					<view class="text-[30rpx] text-[#111] pb-[25rpx] border-bottom-style mb-[25rpx]">
						一键下单 按约上门
					</view>
					<view class="flex justify-between  mb-[40rpx]">
						<view class="flex flex-col justify-center items-center">
							<view class="position-style"
								:style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx] "
									:src="img('addon/home_service/user/card/detail-step-1.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								01
							</view>
							<view class="text-[26rpx]">
								提交订单
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style"
								:style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-2.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								02
							</view>
							<view class="text-[26rpx]">
								预约时间
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style"
								:style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-3.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								03
							</view>
							<view class="text-[26rpx]">
								支付订单
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style"
								:style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-blue.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-4.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								04
							</view>
							<view class="text-[26rpx]">
								开始服务
							</view>
						</view>
					</view>
					<view class="flex  justify-between">
						<view class="flex flex-col justify-center items-center">
							<view class="position-style"
								:style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-blue.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-8.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								05
							</view>
							<view class="text-[26rpx]">
								完成评价
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style"
								:style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-blue.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-7.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								06
							</view>
							<view class="text-[26rpx]">
								客户验收
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style"
								:style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-6.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								07
							</view>
							<view class="text-[26rpx]">
								结束服务
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style"
								:style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-5.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								08
							</view>
							<view class="text-[26rpx]">
								附加费用
							</view>
						</view>
					</view>
				</view>
				<view class="chunk-wrap pt-[34rpx] pb-[34rpx] scheduling rounded-lg">
					<view class="text-[30rpx] text-[#111] mb-[35rpx]">
						购买须知
					</view>
					<view class="mt-[24rpx]">
						<view class="flex items-center">
							<image class="w-[32rpx] h-[32rpx] mr-[10rpx] "
								:src="img('addon/home_service/goods/card_1.png')" />
							<view class="text-[28rpx]">
								卡片类型
							</view>
						</view>
						<view class="ml-[45rpx] text-[24rpx] text-[#999999] mt-[15rpx] mt-[15rpx]">
							{{detail.valid_type_name}}
						</view>
					</view>
					<view class="mt-[24rpx]">
						<view class="flex items-center">
							<image class="w-[32rpx] h-[32rpx] mr-[10rpx]"
								:src="img('addon/home_service/goods/card_2.png')" />
							<view class="text-[28rpx]">
								可用时间
							</view>
						</view>
						<view class="ml-[45rpx] text-[24rpx] text-[#999999] mt-[15rpx]">
							商家营业时间
						</view>
					</view>
					<view class="mt-[24rpx]">
						<view class="flex">
							<image class="w-[32rpx] h-[32rpx] mr-[10rpx]"
								:src="img('addon/home_service/goods/card_3.png')" />
							<view class="text-[28rpx]">
								预约规则
							</view>
						</view>
						<view class="ml-[45rpx] text-[24rpx] text-[#999999] mt-[15rpx]">
							提前预约
						</view>
					</view>
					<view class="mt-[24rpx]">
						<view class="flex">
							<image class="w-[32rpx] h-[32rpx] mr-[10rpx]"
								:src="img('addon/home_service/goods/card_5.png')" />
							<view class="text-[28rpx]">
								购买限制
							</view>
						</view>
						<view class="ml-[45rpx] text-[24rpx] text-[#999999] mt-[15rpx]">
							暂无信息
						</view>
					</view>
					<view class="mt-[24rpx]">
						<view class="flex">
							<image class="w-[32rpx] h-[32rpx] mr-[10rpx]"
								:src="img('addon/home_service/goods/card_1.png')" />
							<view class="text-[28rpx]">
								退款规则
							</view>
						</view>
						<view class="ml-[45rpx] text-[24rpx] text-[#999999] mt-[15rpx]">
							有效期内未使用，过期不退
						</view>
					</view>
				</view>
				<view class="chunk-wrap pt-[34rpx] pb-[24rpx] scheduling rounded-lg">
					<view class="text-[30rpx] text-[#111] mb-[25rpx]">
						次卡详情
					</view>
					<view class="mt-[24rpx]">
						<view class="scheduling-content mt-2">
							<u-parse :content="detail.card_content" :tagStyle="{img: 'vertical-align: top;'}"
								v-if="detail.card_content"></u-parse>
							<view v-else class="h-[380rpx] flex">
								<view class="mx-auto">
									<image class="w-[280rpx] h-[280rpx]"
										:src="img('addon/home_service/goods/empty01.png')" />
									<view class="text-center text-[#c1c1c1] text-[24rpx]">暂无介绍</view>
								</view>
							</view>
						</view>
					</view>
				</view>
				<view class="h-[148rpx] w-screen"></view>
				<view class="flex justify-between bg-white px-3 py-2 fixed bottom-0 left-0 right-0 body-bottom">
					<view class="flex items-center">
						<view class="flex flex-col items-center mr-[44rpx]"
							@click="redirect({ url: '/addon/home_service/user/pages/index', mode: 'reLaunch' })">
							<image class="w-[43rpx] h-[43rpx] " :src="img('addon/home_service/service/index.png')"
								mode="aspectFill" />
							<text class="text-[24rpx] text-[#454545] mt-1.5">{{ t('index') }}</text>
						</view>
						<view class="flex flex-col items-center mr-[44rpx]"
							@click="redirect({ url: '/app/pages/member/contact' })">
							<image class="w-[44rpx] h-[44rpx]" :src="img('addon/home_service/service/service.png')"
								mode="aspectFill" />
							<text class="text-[24rpx] text-[#454545] mt-1">{{ t('service') }}</text>
						</view>
					</view>
					<u-button :customStyle="{ marginLeft:'8rpx', borderRadius:'38rpx',flex: '1'}"
						color="var( --primary-color)" size="16" :text="t('orderNow')"
						@click="create(detail)"></u-button>
				</view>
			</view>
		</view>
	</view>
	<view :style="themeColor()">
		<u-popup :show="servicesSafePopupOpen" mode="bottom" :closeable="true" closeIconSize="40rpx"
			@close="servicesSafePopupOpen=false" :round="10">
			<view class=" bg-white rounded-[24rpx] p-[30rpx]">
				<view class=" text-center text-32rpx font-bold text-[#333333] mb-6">{{t('serviceSafe')}}</view>
				<view class="popup-body">
					<view class="service-item flex items-center p-[15rpx] bg-[#fcfcfc] rounded-xl mb-4"
						v-for="(item,index) in detail.guarantee_list">
						<view
							class="service-icon w-10 h-10 rounded-full flex items-center justify-center text-white text-28rpx mr-4">
							<image class="w-[44rpx] h-[44rpx]" :src="img(item.guarantee_image)" mode="aspectFill" />
						</view>
						<view class="service-text text-30rpx text-[#333333]">
							<view>
								{{item.guarantee_title}}
							</view>
							<view class="text-[24rpx] text-[#999] mt-[15rpx]">
								{{item.guarantee_content}}
							</view>
						</view>
					</view>
				</view>
			</view>
		</u-popup>
		<pay ref="payRef" @close="payClose"></pay>
		<loading-page :loading="loading"></loading-page>
	</view>
</template>

<script setup lang="ts">
	import { ref, reactive, computed,getCurrentInstance } from 'vue';
	import { onLoad, onShow, onUnload,onPageScroll  } from '@dcloudio/uni-app'
	import { useLogin } from '@/hooks/useLogin';
	import { img, redirect, getToken, handleOnloadParams } from '@/utils/common';
	import { getCardDetail, orderCreate } from '@/addon/home_service/user/api/card';
	import useMemberStore from '@/stores/member'
	import { t } from '@/locale';
	import nsGoodsSku from '@/addon/home_service/user/components/ns-goods-sku/ns-goods-sku.vue'
	import uniTable from '@/addon/home_service/user/components/uni-table/components/uni-table/uni-table.vue'
	import uniTr from '@/addon/home_service/user/components/uni-table/components/uni-tr/uni-tr.vue'
	import uniTh from '@/addon/home_service/user/components/uni-table/components/uni-th/uni-th.vue'
	import uniTd from '@/addon/home_service/user/components/uni-table/components/uni-td/uni-td.vue'
	import { useShare } from '@/hooks/useShare'
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	const detail = ref<Record<string, any>>({});
	const loading = ref<boolean>(true);
	const memberStore = useMemberStore()
	const goodsSkuRef = ref(null)
	const payRef = ref(null)
	// 分享
	const { setShare } = useShare()
	const currentNum = ref(1)
	/************ 自定义头部-start ****************/
	const topNav = ref(false);
	let platform = systemStore.systemInfo.platform;
	// 返回上一页
	const backToPrevious = () => {
		if (getCurrentPages().length > 1) {
			uni.navigateBack({
				delta: 1
			});
		} else {
			redirect({
				url: '/addon/home_service/user/pages/index',
				mode: 'reLaunch'
			});
		}
	}
	// 导航栏内部盒子的样式
	const navbarInnerStyle = computed(() => {
		let style = '';
		// #ifdef MP
		let rightButtonWidth = systemStore.menuButtonInfo.width ? systemStore.menuButtonInfo.width * 2 + 'rpx' : '70rpx';
		style += 'height:' + systemStore.menuButtonInfo.height + 'px;';
		style += 'padding-right:calc(' + rightButtonWidth + ' + 30rpx);';
		style += 'padding-left:calc(' + rightButtonWidth + ' + 30rpx);';
		style += 'padding-top:' + systemStore.menuButtonInfo.top + 'px;';
		style += 'padding-bottom: 8px;';
		style += 'font-size: 32rpx;';
		if (platform == 'ios') {
			style += 'font-weight: 500;';
		} else if (platform == 'android') {
			style += 'font-size: 36rpx;';
		}
		// #endif
		
		// #ifdef H5
		style += 'height: 100rpx;';
		style += 'padding-right: 30rpx;';
		style += 'padding-left: 30rpx;';
		style += 'font-size: 32rpx;';
		if (platform == 'ios') {
			style += 'font-weight: 500;';
		} else if (platform == 'android') {
			style += 'font-size: 36rpx;';
		}
		// #endif
		
		// #ifdef APP-PLUS
		style += 'height: 80rpx;';
		style += 'padding-right: 30rpx;';
		style += 'padding-left: 30rpx;';
		style += 'padding-top:' + systemStore.systemInfo.statusBarHeight + 'px;';
		// #endif
		
		return style;
	})
	
	// 导航栏内部盒子的样式
	const navbarInnerArrowStyle = computed(() => {
		let style = '';
		// #ifdef MP
		style += 'position: absolute;';
		style += 'left:calc( 100vw - ' + systemStore.menuButtonInfo.right + 'px);';
		if (platform == 'ios') {
			style += 'font-weight: 700;';
		}
		// #endif
		return style;
	})
	
	// 导航栏头部卡片样式
	const fixedInnerStyle = computed(() => {
		let style = '';
		// #ifdef MP
		style += 'top:' + (systemStore.menuButtonInfo.height + systemStore.menuButtonInfo.top + 8) + 'px;';
		style += 'left:calc( 100vw - ' + systemStore.menuButtonInfo.right + 'px);';
		// #endif
		// #ifdef H5
		style += 'top: 100rpx;';
		style += 'left: 30rpx;';
		// #endif
		// #ifdef APP-PLUS
		style += 'top:' + (systemStore.systemInfo.statusBarHeight + uni.upx2px(100)) + 'px;';
		style += 'left: 30rpx;';
		// #endif
		return style;
	})
	
	
	// 会员信息
	const userInfo = computed(() => memberStore.info)
	const servicesSafePopupOpen = ref(false)
	const openServicesSafePopup = () => {
		servicesSafePopupOpen.value = true
	}
	// 头部滚动
	const instance = getCurrentInstance();
	let swiperHeight = 0
	let detailHead = 0
	const detailHeadBgChange = ref(false)
	onPageScroll((e) => {
		if (swiperHeight == 0 || detailHead == 0) return;
		let height = swiperHeight - detailHead - 20;
		detailHeadBgChange.value = false;
		if (e.scrollTop >= height) {
			detailHeadBgChange.value = true;
		}
	})
	// 菜单列表
	const menuList = {
		index: {
			name: '首页',
			iconfont: 'nc-iconfont nc-icon-shouyeV6xx11',
			url: { url: '/addon/home_service/user/pages/index', mode: 'reLaunch' }
		},
		member: {
			name: '个人中心',
			iconfont: 'nc-iconfont nc-icon-a-wodeV6xx-36',
			url: { url: '/addon/home_service/user/pages/member/index' }
		},
		// collect: {
		// 	name: '我的收藏',
		// 	iconfont: 'nc-iconfont nc-icon-guanzhuV6xx',
		// 	url: { url: '/addon/home_service/user/pages/goods/collect' }
		// }
	}
	const menuContents = computed(() => {
		const list = []
		const menu = ['index', 'search', 'member']
		menu.forEach((item: any) => {
			if (menuList[item]) {
				list.push(menuList[item])
			}
		})
		return list
	})
	
	// 购买套餐
	const buyPackage = (packageItem) => {
		// 实现购买套餐的逻辑
		uni.showToast({ title: `购买${packageItem.name}`, icon: 'none' })
	}

	/**
	 * 订单创建
	 */
	const create = () => {
		let data = {
			card_id: cardId.value,
			city_id: systemStore.diyAddressInfo?.city_id
		}
		orderCreate(data).then(({ data }) => {
			payRef.value?.open(data.trade_type, data.trade_id, `/addon/home_service/user/pages/card/my_card`)
		}).catch((res) => {
		})
	}
	const cardId = ref('')
	onLoad((option) => {
		// #ifdef MP-WEIXIN
		// 处理小程序场景值参数
		option = handleOnloadParams(option);
		// #endif
		cardId.value = option.card_id
		loading.value = true
		if (getToken()) {
			memberStore.getMemberInfo()
		}
		let params = {
			card_id: cardId.value,
			city_id: systemStore.diyAddressInfo?.city_id
		}
		getCardDetail(params).then((res) => {
			loading.value = false
			if (!res.data || JSON.stringify(res.data) === '{}') {
				uni.showToast({ title: '找不到该商品', icon: 'none' })
				setTimeout(() => {
					redirect({ url: '/addon/home_service/user/pages/index', mode: 'reLaunch' })
				}, 600)
				return false
			}
			detail.value = res.data
			// console.log(detail.value.card_name, 'detail.value')
			uni.setNavigationBarTitle({
				title: detail.value.card_name
			})
			if (detail.value.card_cover_thumb_small) {
				detail.value.card_cover_thumb_small = [detail.value.card_cover_thumb_small]
				console.log(detail.value.card_cover_thumb_small)
				detail.value.card_cover_thumb_small.forEach((item, index) => {
					detail.value.card_cover_thumb_small[index] = img(item);
				})
			}
		}).catch((err) => {
			uni.showToast({ title: '找不到该次卡', icon: 'none' })
			setTimeout(() => {
				redirect({ url: '/addon/home_service/user/pages/index', mode: 'reLaunch' })
			}, 600)
			return false
		})
	})

	// 订单计算创建
	let orderData = {
		sku: {
			num: 1,
			sku_id: ''
		}
	}

	// 跳转订单预约
	const toOrder = (data) => {
		if (!getToken()) {
			useLogin().setLoginBack({ url: '/addon/home_service/user/pages/goods/detail', param: { sku_id: data.sku_id } })
			return false;
		}
		if (!data.goods.status) {
			return false
		}
		orderData.sku.sku_id = data.sku_id
		uni.setStorageSync('o2oCreateData', orderData);
		if (data.goods.buy_type == 'buy') {
			buyFn()
		} else {
			redirect({ url: '/addon/home_service/user/pages/order/payment', param: { id: data.goods_id } })
		}
	}

	const buyFn = () => {
		goodsSkuRef.value.open()
	}

	const specSelectFn = (id) => {
		detail.value.skuList.forEach((item, index) => {
			if (item.sku_id == id) {
				Object.assign(detail.value, item);
			}
		})
	}
	const swiperClick = (index : any) => {
		if (typeof index == 'number') imgListPreview(detail.value.card_cover_thumb_small, index)
	}
	//预览图片
	const imgListPreview = (item : any, index : any) => {
		if (Array.isArray(item)) {
			if (!item.length) return false
			var urlList = item;
			uni.previewImage({
				indicator: "number",
				current: index,
				loop: true,
				urls: urlList
			})
		} else {
			if (item === '') return false
			var urlList = []
			urlList.push(img(item))  //push中的参数为 :src="item.img_url" 中的图片地址
			uni.previewImage({
				indicator: "number",
				loop: true,
				urls: urlList
			})
		}

	}

	// 价格类型
	const priceType = ref('') //''=>原价，discount_price=>折扣价，member_price=>会员价
	// 关闭预览图片
	onUnload(() => {
		// #ifdef  H5 || APP
		try {
			uni.closePreviewImage()
		} catch (e) {

		}
		// #endif
	})
</script>

<style lang="scss" scoped>
	.chunk-wrap {
		@apply bg-white px-4 mb-3;

		.chunk-head {
			height: 84rpx;
			@apply flex justify-between items-center border-0 border-b border-solid border-[#F2F2F2] box-border;

			text {
				&:first-of-type {
					@apply font-bold;
				}

				&:last-of-type {
					@apply text-[24rpx] text-[var(--text-color-light9)];
				}

				.iconfont {
					@apply inline-block;
					margin-left: 2rpx;
				}
			}
		}
	}

	.member-price {
		background: linear-gradient(90deg, #FEF3E7 0%, #FFFFFF 100%);
	}

	.text-color {
		color: $u-primary;
	}

	.bg-color {
		background-color: $u-primary;
	}

	.word-all {
		word-break: keep-all;
	}

	.text-scale {
		transform: scale(0.8);
	}

	.class-select {
		position: relative;
		font-weight: bold;
		color: var(--primary-color);

		&::after {
			content: "";
			position: absolute;
			bottom: 0;
			height: 6rpx;
			border-radius: 3rpx;
			background-color: $u-primary;
			width: 60rpx;
			left: 50%;
			transform: translateX(-50%);
		}
	}

	.scheduling-content :deep(.uni-table) {
		min-width: 100% !important;
	}

	:deep(.scheduling-content img) {
		vertical-align: middle;
	}

	/* 服务保障弹框样式 */
	.popup-content {
		animation: popupIn 0.3s ease-out;
	}

	@keyframes popupIn {
		from {
			opacity: 0;
			transform: scale(0.9) translateY(-20rpx);
		}

		to {
			opacity: 1;
			transform: scale(1) translateY(0);
		}
	}

	.service-item {
		transition: all 0.2s ease;
	}

	.service-item:active {
		background-color: #f0f0f0;
	}

	.border-bottom-style {
		border-bottom: 2rpx solid #f5f5f5
	}

	.position-style {
		background-size: 100%;
		background-repeat: no-repeat;
	}
	.arrow-left {
		background: rgba(255, 255, 255, 0.6);
		border: 1rpx solid rgba(0, 0, 0, 0.1);
	}
	.cf-arrow-left{
		background: rgba(17, 17, 17, 0.50);
	    border: 0.03125rem solid rgba(0, 0, 0, 0.1);
	}
	.body-bottom {
		padding-bottom: calc( 20rpx + env(safe-area-inset-bottom, 0));
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom, 0));
	}
</style>