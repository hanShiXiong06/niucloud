<template>
	<!-- 导航栏外层容器：控制显示时机（仅基础展示，无业务判断） -->
	<view class="base-nav-wrap">
		<!-- 导航栏主体：fixed定位，适配两端 -->
		<view class="base-nav px-[20rpx]" :style="{ 
        backgroundColor: props.bgColor, 
        height: navHeight + 'px',
        paddingTop: navPaddingTop + 'px',
		paddingBottom:navPaddingBottom + 'px'
      }">
			<!-- #ifdef H5 -->
			<!-- 导航栏内容区：居中标题 -->
			<view class=" flex justify-between bg-[#111] w-[100%] h-[100%]">
				<view class="text-[#fff] bg-[#2A2A2A] flex items-center px-[20rpx] rounded-[40rpx]" @click="statusShow = true">
					<block  v-if="!statusLoading">
						<view class="" v-if="props.statusIndex == 1">
							<u-icon name="checkmark-circle-fill" color="#36B73F" size="15"></u-icon>
						</view>
						<image v-else :src="img('/addon/home_service/technician/time.png')" class="w-[14px] h-[14px] p-[0.5px]"
							mode="aspectFit"></image>
						<text class="px-[10rpx] text-[26rpx] leading-1">{{columns[0][props.statusIndex].name}}</text>
						<image :src="img('/addon/home_service/technician/choose.png')" class="w-[22rpx] h-[22rpx]"
							mode="aspectFit"></image>
					</block>
					<view v-else class="flex items-center">
						<u-loading-icon text="加载中" mode="circle" inactive-color="#666666" size="14" textSize="12"></u-loading-icon>
					</view>
				</view>
				
				<view class="" @click="redirect({url:'/addon/home_service/technician/pages/notice/index'})">
					<view class="p-[15rpx] box-border bg-[#2A2A2A] rounded-[50%] relative">
						<text v-if="statusContent"
							class="absolute text-[#fff] bg-[#FF0000] rounded-[50%] text-[20rpx] p-[2rpx] w-[30rpx] h-[30rpx] flex items-center justify-center right-[-10rpx] top-[-10rpx]">{{statusContent}}</text>
						<image :src="img('/addon/home_service/technician/message-icon.png')"
							:style="{ height: navHeight - 35 + 'px',width: navHeight - 35 + 'px'}"
							class="block w-[100%] h-[100%]" mode="aspectFit"></image>
					</view>
				</view>
			</view>
			<!-- #endif -->
			<!-- #ifdef MP-WEIXIN -->
			<view class=" flex justify-between bg-[#111] w-[100%] h-[100%]">
				<view class="text-[#fff] bg-[#2A2A2A] flex items-center px-[20rpx] rounded-[40rpx]"  @click="statusShow = true" v-if="!statusLoading">
					<!-- <image :src="img('/addon/home_service/technician/time.png')" class="w-[25rpx] h-[25rpx]"
						mode="aspectFit"></image>
					<text class="px-[10rpx] text-[26rpx] leading-1">{{columns[0][props.statusIndex].name}}</text>
					<image :src="img('/addon/home_service/technician/choose.png')" class="w-[22rpx] h-[22rpx]"
						mode="aspectFit"></image> -->
				<view class="" v-if="props.statusIndex == 1">
							<u-icon name="checkmark-circle-fill" color="#36B73F" size="15"></u-icon>
						</view>
						<image v-else :src="img('/addon/home_service/technician/time.png')" class="w-[14px] h-[14px] p-[0.5px]"
							mode="aspectFit"></image>
						<text class="px-[10rpx] text-[26rpx] leading-1">{{columns[0][props.statusIndex].name}}</text>
						<image :src="img('/addon/home_service/technician/choose.png')" class="w-[22rpx] h-[22rpx]"
							mode="aspectFit"></image>
					 
				</view>
				<u-loading-icon v-else></u-loading-icon>
				<view class="" :style="{marginRight:navRight + 15 + 'px'}" @click="redirect({url:'/addon/home_service/technician/pages/notice/index'})" v-if="statusContent">
					<view class="p-[8px] box-border bg-[#2A2A2A] rounded-[50%] relative">
						<text
							class="absolute text-[#fff] bg-[#FF0000] rounded-[50%] text-[20rpx] p-[2rpx] w-[30rpx] h-[30rpx] flex items-center justify-center right-[-10rpx] top-[-10rpx]">{{statusContent}}</text>
						<image :src="img('/addon/home_service/technician/message-icon.png')"
							:style="{ height: navJIiaonanHeight - 16 + 'px',width: navJIiaonanHeight - 16 + 'px'}"
							class="block w-[100%] h-[100%]" mode="aspectFit"></image>
					</view>
				</view>
			</view>
			<!-- #endif -->
		</view>
		<u-picker :show="statusShow"  :defaultIndex="[statusIndex]" :columns="columns" keyName="name" :closeOnClickOverlay="true" @cancel="closeStatus" @confirm="confirmStatus"></u-picker>
		<!-- 导航栏占位：解决fixed定位导致的页面塌陷 -->
		<view class="base-nav__placeholder" :style="{ height: navHeight + 'px' }"></view>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed, onMounted } from 'vue';
	import { img, redirect, getToken } from '@/utils/common'
	import { setTechnicianStatus,getTechnicianInfo} from '@/addon/home_service/technician/api/technician'
	import { getNoticeStatus } from '@/addon/home_service/technician/api/notice'
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	const platform = systemStore.systemInfo.platform;
	const emits = defineEmits(['close'])
	const columns = ref([
		[{ name: '休息中', key: 0 },
		{ name: '工作中', key: 1 }]
	])
	const statusLoading = ref(true)
	const closeStatus = () =>{
		statusShow.value = false
	}
	const statusContent = ref(0)
	const getNoticeStatusFn = () =>{
		getNoticeStatus().then((res)=>{
			statusContent.value = res.data[0].count
		})
	}
	getNoticeStatusFn()
	const getTechnicianInfoFn = () =>{
		statusLoading.value  = true
		getTechnicianInfo().then((res)=>{
			statusLoading.value = false
			emits('changeStatus',Number(res.data.status));
		})
	}
	getTechnicianInfoFn()
	const confirmStatus = (e:any) =>{
		const newIndex = e.indexs[0]
		setTechnicianStatus({value:newIndex}).then((res)=>{
			uni.showToast({
				title:res.msg,
				icon:'none'
			})
		}).catch((err)=>{
			uni.showToast({
				title:res.msg,
				icon:'none'
			})
		})
		emits('changeStatus',newIndex);
		closeStatus()
	}
	const statusShow = ref(false)
	// 组件props：支持基础自定义（标题、颜色、字体大小）
	const props = defineProps({
		// 导航栏标题（必传）
		// title: {
		// 	type: String,
		// 	required: true,
		// 	default: '基础导航栏'
		// },
		// 导航栏背景色
		bgColor: {
			type: String,
			default: '#ffffff' // 默认白色背景
		},
		// 标题文字颜色
		titleColor: {
			type: String,
			default: '#333333' // 默认深灰文字
		},
		// 标题字体大小（单位：rpx，适配多端）
		titleSize: {
			type: String,
			default: '32rpx' // 基础适配字体大小
		},
		// 当前状态
		statusIndex: {
			type: Number,
			default: 0 // 默认休息中
		}
	});

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
		return 50; // H5：固定高度（可根据设计调整）
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
</script>

<style scoped lang="scss">
	/* 外层容器：仅控制显示，无额外样式 */
	.base-nav-wrap {
		width: 100%;
	}

	/* 导航栏主体：fixed定位，全宽 */
	.base-nav {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		z-index: 999; // 确保在页面内容上方
		box-sizing: border-box; // 避免padding撑大高度
	}

	/* 导航栏内容区：居中布局 */
	.base-nav__content {
		width: 100%;
		height: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	/* 导航栏标题：基础样式 */
	.base-nav__title {
		font-weight: 500;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap; // 标题过长时省略号显示
	}

	/* 占位元素：高度与导航栏一致，解决fixed塌陷 */
	.base-nav__placeholder {
		width: 100%;
		box-sizing: border-box;
	}
</style>