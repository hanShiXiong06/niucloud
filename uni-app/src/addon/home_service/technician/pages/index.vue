<template>
	<view class="bg-[#f6f6f6]">
		<technician-header bgColor="#111111" titleColor="#fff" v-model:statusIndex="currentStatus" @changeStatus="changeStatus" />
		<view class="bg-[#111] px-[20rpx] pb-1 items-center fixed w-full z-index-999 box-border">
			<!-- #ifdef MP-WEIXIN -->
			<view class="w-full h-[20rpx] bg-[#111]"></view>
			<!-- #endif -->
			<!-- 新任务标题 -->
			<view class="flex justify-between">
				<view class="text-white border-b-2 border-white boder-bottom font-semibold leading-[70rpx] text-[28rpx]">
					{{t('newJob')}}
				</view>
				<view class="flex items-center">
					<!-- 筛选按钮 -->
					<view class="flex items-center mr-[20rpx]" @click="open()">
						<image :src="img('/addon/home_service/technician/shaixuan.png')"
							class="block w-[34rpx] h-[34rpx]" mode="aspectFit"></image>
						<view class="text-white text-[26rpx] ml-2">{{t('sort')}}</view>
					</view>
					<view class="h-[28rpx] w-[1px] bg-gradient-to-b from-white to-gray-700"></view>
					<!-- 刷新按钮 -->
					<view class="flex items-center ml-[20rpx]" @click="clacOrderList">
						<image :src="img('/addon/home_service/technician/shuaxin.png')"
							class="block w-[34rpx] h-[34rpx]" mode="aspectFit"></image>
						<view class="text-white text-[26rpx] ml-2">{{t('calcation')}}</view>
					</view>
				</view>
			</view>
		</view>
		<view class="mescroll-box bg-[#f8f8f8]" >
			<mescroll-body ref="mescrollRef" :top="navHeight + 'px'" :down="{ use: false }" @init="mescrollInit"
				@up="getListFn">
				<view class="bg-[#fff] my-[20rpx] mx-[20rpx] rounded-[16rpx] p-[20rpx]" @click.stop="grabOrderFn(item)"
					v-for="(item, index) in indexList" :key="index">
					<view class="flex justify-between">
						<view class="flex items-center">
							<view class="flex items-center bg-[#00CE2D] rounded-l-[50rpx] rounded-r-[6rpx] mr-[20rpx]" v-if="item.buy_type == 'reservation'">
								<image :src="img('/addon/home_service/technician/white-time.png')" class="w-[20rpx] h-[20rpx] p-[5rpx] bg-[#04E24A] rounded-[50%] mr-[5rpx]" mode="aspectFit"></image>
								<view class="text-[20rpx] text-[#fff]  pr-[8rpx]">
									预
								</view>
							</view>
							<view class="">
								{{item.reserve_service_time}}
							</view>
						</view>
						<view class="text-[#FF000B]">
							<text class="text-[24rpx]">{{t('money')}}</text>
							<text class="text-[24rpx]">￥</text>
							<text class="text-[36rpx]">{{item.technician_commission}}</text>
						</view>
					</view>
					<view class="flex mt-[20rpx]">
						<view class="flex flex-col items-center justify-between bg-[#F6F6F6] px-[5rpx] py-[30rpx] rounded-[50rpx]">
							<view class="text-[24rpx]">
								<view class="text-center mb-[5rpx]">
									{{item.distance}}
								</view>
								<view class="text-center">
									km
								</view>
							</view>
							<view class="w-[2rpx] h-[30rpx] bg-[#ccc] my-[30rpx]">
							</view>
							<view class="text-[24rpx]">
								服务
							</view>
						</view>
						<view class="flex flex-col ml-[20rpx] justify-between pt-[10rpx] pb-[10rpx]">
							<view class="">
								<view class="text-[30rpx] font-bold truncate w-72 leading-[50rpx]">
									{{item.taker_address}}
								</view>
								<view class="text-[24rpx] text-[#666666] truncate w-72">
									{{item.taker_full_address}}
								</view>
							</view>
							<view class="">
								<view class="text-[30rpx] font-bold truncate w-72 leading-[50rpx]">
									{{ item.errand_items.length!= 0 ? '跑腿服务 '+ item.errand_items.length+' 件' :item.item[0]?.item_name }}
								</view>
								<view class="text-[24rpx] text-[#666666] mt-[10rpx] truncate w-72 ">
									{{item.member_message || '暂无备注信息'}}
								</view>
							</view>

						</view>
					</view>
					
					<view class="mt-[20rpx] mb-[10rpx]">
						<button type="" class="text-[28rpx] h-[80rpx] leading-[80rpx] text-white !bg-[var(--technician-bg-one)]"
							@click.stop="grabOrderFn(item)">{{t('getOrder')}}</button>
					</view>
				</view>
				<view class="pl-[20rpx] pt-[0rpx]" style="width: calc(100% - 182rpx)">
					<mescroll-empty :option="{ icon: img('static/resource/images/empty.png'), tip: t('nothingMore') }"
						v-if="!indexList.length && !loading && listLoading" class="part"></mescroll-empty>
				</view>
			</mescroll-body>
		</view>
		<!-- <mescroll-empty v-if="!indexList.length && !loading"
			:option="{ icon: img('static/resource/images/empty.png'), tip: t('nothingMore') }"></mescroll-empty> -->
		<loading-page :loading="loading"></loading-page>


		<!-- 筛选弹窗 -->
		<!-- #ifdef H5 -->
		<u-popup :show="showPopup" @close="close" @open="open" mode="top"  zIndex="998"
		
			:custom-style="{ top: '168rpx', height: '60%' }" :overlayStyle="{ 
			    top: showPopup?'calc(88rpx + 10%)' :'',
			    bottom:showPopup? '0' : '',               
			    background: showPopup ? 'rgba(0, 0, 0, 0.5)' : ''
			  }">
		<!-- #endif -->
			<!-- #ifdef MP-WEIXIN -->
		<u-popup :show="showPopup" @close="close" @open="open" mode="top" zIndex="998"
			:custom-style="{ top: navHeight + 70 + 'px', height: '60%' }" :overlayStyle="{ 
			top: showPopup?'calc(88rpx + 10%)' :'',
			bottom:showPopup? '0' : '',               
			background: showPopup ? 'rgba(0, 0, 0, 0.5)' : ''
		  }">
		<!-- #endif -->
			<view class="bg-white p-4">
				<!-- 服务类型区域 -->
				<view class="mb-4">
					<text class="block text-[30rpx] font-bold mb-[30rpx]">{{t('serviceType')}}</text>
					<view class="flex flex-wrap gap-2">
						<view class="px-[42rpx] py-[20rpx] rounded-[10rpx] text-[26rpx] bg-[#F6F6F6]"
							:class="typeIndex == idx ? 'activeStyle' : ''"
							v-for="(item, idx) in typeList" :key="idx" @click="handleTypeClick(idx)">
							{{item.category_name}}
							<text class="pl-[15rpx]">{{item.order_count}}</text>
						</view>
					</view>
				</view>

				<!-- 服务距离区域 -->
				<view class="mb-4">
					<text class="block text-[30rpx] font-bold mb-[30rpx]">{{t('serviceDistance')}}</text>
					<view class="flex flex-wrap gap-2">
						<view class="px-[42rpx] py-[20rpx] rounded-[10rpx] text-[26rpx] bg-[#F6F6F6]"
							:class="distanceIndex == idx ? 'activeStyle' : ''"
							v-for="(item, idx) in distanceList" :key="idx" @click="handleDistanceClick(idx)">
							{{item.text}}
						</view>
					</view>
				</view>

				<!-- 操作按钮区域 -->
				<view class="flex gap-2 mt-4">
					<button type="default"
						class="box-border !w-[40%] mp-border !rounded-[15rpx] !bg-[#fff] leading-[80rpx] h-[80rpx] flex items-center justify-center text-[26rpx]"
						@click="reset">
						<image :src="img('/addon/home_service/technician/reset.png')"
							class="block w-[30rpx] pb-[4rpx] h-[30rpx] mr-[10rpx]" mode="aspectFit"></image>
						{{t('reset')}}
					</button>
					<u-button type="primary" class="!w-[60%] !rounded-[15rpx]" @click="confirm">{{t('confrim')}}</u-button>
				</view>
			</view>
		</u-popup>
		<tabbar :value="0"></tabbar>
	</view>
</template>

<script setup lang="ts">
	import { img, redirect, getToken } from '@/utils/common'
	import { ref, computed, onMounted } from 'vue'
	import TechnicianHeader from '@/addon/home_service/technician/components/technician-header/technician-header.vue'
	import tabbar from '@/addon/home_service/technician/components/tabbar/tabbar.vue'
	import useSystemStore from '@/stores/system';
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { getTechnicianOrderList, getCategoryOrderList, grabOrder,getCategorygrabdistanceList} from '@/addon/home_service/technician/api/order'
	import { t } from '@/locale'
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	const systemStore = useSystemStore()
	// 控制弹窗显示/隐藏
	const showPopup = ref(false)
	// 1. 小程序菜单按钮（胶囊）信息：用于计算适配距离
	const menuButtonInfo = ref({});
	menuButtonInfo.value = systemStore.menuButtonInfo
	// 导航栏总高度（小程序：胶囊高度 + 胶囊top + 底部预留8px；H5：固定高度）
	const navHeight = computed(() => {
		// #ifdef MP-WEIXIN
		return (menuButtonInfo.value.height || 32) + (menuButtonInfo.value.top || 10) - 25;
		// #endif
		// #ifdef H5
		return 50; // H5：固定高度（可根据设计调整）
		// #endif
	});
	const currentStatus = ref(1)
	// 服务类型数据（含选中状态）
	const typeList = ref([])

	// 服务距离数据（含选中状态）
	const distanceList = ref([])
	const getCategorygrabdistanceListFn = () =>{
		getCategorygrabdistanceList().then((res)=>{
			Object.keys(res.data).forEach((item,index)=>{
				let obj ={}
				obj.text = res.data[item]
				obj.key = item
				distanceList.value.push(obj)
			})
			
		})
	}
	getCategorygrabdistanceListFn()
	const changeStatus = (e:any) =>{
		currentStatus.value = e
	}
	const close = () => {
		showPopup.value = false
	};

	const open = () => {
		showPopup.value = true
 	};
	const clacOrderList = () =>{
		getMescroll().resetUpScroll()
	}
	const typeIndex = ref(0)
	// 切换服务类型选中状态
	const handleTypeClick = (idx : number) => {
		typeIndex.value = idx
	}
	const distanceIndex = ref(0)
	const handleDistanceClick = (idx : number) => {
		distanceIndex.value = idx
	}
	// 重置筛选条件
	const reset = () => {
		typeIndex.value = 0
		distanceIndex.value = 0
	}

	// 确认筛选（关闭弹窗 + 处理筛选结果）
	const confirm = () => {
		getMescroll().resetUpScroll()
		showPopup.value = false
	}

	const indexList = ref([])
	const loading = ref<boolean>(true) //页面加载动画
	const listLoading = ref<boolean>(false) //列表加载动画
	const getCategoryOrderListFn = () =>{
		getCategoryOrderList().then((res)=>{
			typeList.value = res.data
			getMescroll().resetUpScroll()
		})
	}
	getCategoryOrderListFn()
	
	const grabOrderFn = (e:any) =>{
		redirect({url:'/addon/home_service/technician/pages/order/grabOrder',param:{order_id:e.order_id}})
	}
	
	// 获取订单列表
	const getListFn = (mescroll : mescrollStructure) => {
		if(!typeList.value.length ) return;
		loading.value = true
		listLoading.value = false
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
			category_id:typeIndex.value ? typeList.value[typeIndex.value].category_id : '',
			order_status:'wait_dispatch',
			distance:distanceIndex.value ?distanceList.value[distanceIndex.value].key : '',
			lng:systemStore.diyAddressInfo?.longitude,
			lat:systemStore.diyAddressInfo?.latitude
		}
		  getTechnicianOrderList(data).then((res: acceptingDataStructure) => {
		      let newArr = res.data.data
		      //设置列表数据
		      if (mescroll.num == 1) {
		        indexList.value = [] //如果是第一页需手动制空列表
		      }
		      indexList.value = indexList.value.concat(newArr)
		      loading.value = false
		      mescroll.endSuccess(newArr.length)
		      if (!indexList.value.length) listLoading.value = true
		    }).catch(() => {
		      loading.value = false
		      listLoading.value = true
		      mescroll.endErr() // 请求失败, 结束加载
		    })
	}
</script>
<style>
	.boder-bottom {
		border-bottom: 4rpx solid #ffffff;
	}
	.activeStyle{
		background: var(--technician-bg-one);
		color: #fff;
	}
	.mp-border{
		border: 2rpx solid #cccccc !important;
	}
</style>
<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>