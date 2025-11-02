<template>
	<view class="bg-[#f6f6f6]">
		<!-- #ifdef MP-WEIXIN -->
		 	<u-navbar title="首页" :autoBack="false" bgColor="#111" :placeholder="true" :left-arrow="false" left-icon="">
			</u-navbar>
		<!-- #endif -->
		<view class="bg-[#111] px-[20rpx] pb-1 items-center fixed w-full z-index-99999 box-border">
			<!-- 新任务标题 -->
			<view class="flex justify-between" v-if="orderStatusList.length">
				<view class="w-[450rpx]">
					<u-tabs :list="orderStatusList" @change="changeOrderStatus" :current="orderCurrentIndex" keyName="label" :lineWidth="30" :inactiveStyle="{color:'#efefef'}"
						:activeStyle="{color:'#fff',fontWeight:'bold'}"></u-tabs>
				</view>
				<view class="flex items-center">
					<!-- 筛选按钮 -->
					<view class="flex items-center mr-[20rpx]" @click="open()">
						<image :src="img('/addon/home_service/store/shaixuan.png')" class="block w-[34rpx] h-[34rpx]"
							mode="aspectFit"></image>
						<view class="text-white text-[26rpx] ml-2">{{t('sort')}}</view>
					</view>
					<view class="h-[28rpx] w-[1px] bg-gradient-to-b from-white to-gray-700"></view>
					<!-- 刷新按钮 -->
					<view class="flex items-center ml-[20rpx]" @click="clacOrderList">
						<image :src="img('/addon/home_service/store/shuaxin.png')" class="block w-[34rpx] h-[34rpx]"
							mode="aspectFit"></image>
						<view class="text-white text-[26rpx] ml-2">{{t('calcation')}}</view>
					</view>
				</view>
			</view>
		</view>
		<view class="mescroll-box bg-[#f8f8f8]">
			<!-- #ifdef H5 -->
			<mescroll-body ref="mescrollRef" :top="navHeight + 'px'" :down="{ use: false }" @init="mescrollInit"
				@up="getListFn">
				<view v-if="orderCurrent =='in_progress'" :style="{top:navHeight + 'px'}"
					class="fixed z-index-99 h-[86rpx] leading-[86rpx] text-[24rpx] flex w-[100vw] left-0 items-center px-[20rpx] box-border justify-between bg-[#f6f6f6] py-[20rpx]">
			<!-- #endif -->
			<!-- #ifndef H5 -->
			<mescroll-body ref="mescrollRef" :top="(navHeight - 25) + 'px'" :down="{ use: false }" @init="mescrollInit"
				@up="getListFn">
				<view v-if="orderCurrent =='in_progress'" :style="{top:navHeight + 55 + 'px'}"
					class="fixed h-[86rpx] leading-[86rpx] text-[24rpx] flex w-[100vw] left-0 items-center px-[20rpx] box-border justify-between bg-[#f6f6f6] py-[20rpx]">
			<!-- #endif -->
				
					<view class="flex items-center">
						<view class="p-[25rpx] rounded-[46rpx] mr-[30rpx] bg-[#fff] flex items-center leading-1 border-1 border-[#fff] border-solid border-box" @click="changeServiceActive(serviceIndex)" :style="{borderColor:serviceActiveIndex == serviceIndex ? '#111' : ''}" v-for="(serviceItem,serviceIndex) in serviceStatus" :key="serviceIndex">
							{{serviceItem.name}}
						</view>
					</view>
				</view>
				<view class="bg-[#fff] my-[20rpx] mx-[20rpx] rounded-[16rpx] p-[20rpx]"
					v-for="(item, index) in indexList" :key="index" @click.stop="goDetail(item)"
					:class="[{'margin-top-style': orderCurrent == 'in_progress' && index == 0}]">
					<view class="flex justify-between">
						<view class="flex items-center">
							<view class="flex items-center mr-[15rpx]" v-if="item.buy_type == 'reservation'">
								<image :src="img('/addon/home_service/technician/yuyue-icon.png')"
									class="w-[70rpx] mr-[5rpx]" mode="widthFix"></image>
							</view>
							<view class="flex items-center mr-[15rpx]" v-if="item.is_abnormal == 1">
								<image :src="img('/addon/home_service/technician/error-icon.png')"
									class="w-[70rpx] mr-[5rpx]" mode="widthFix"></image>
							</view>
							<view class="flex items-center mr-[15rpx]" v-if="item.is_card_order == 1">
								<image :src="img('/addon/home_service/technician/cika-icon.png')"
									class="w-[70rpx] mr-[5rpx]" mode="widthFix"></image>
							</view>
							<view class="">
								{{item.reserve_service_time}}
							</view>
						</view>
						<view class="!text-[#ff0000] price-font block truncate max-w-[270rpx]"
						   >
						   <text class="text-[24rpx] font-500">收入</text>
						    <text class="text-[24rpx] font-400">￥</text>
						    <text class="text-[40rpx] font-500">{{ parseFloat(item.store_commission).toFixed(2).split('.')[0] }}</text>
						    <text class="text-[24rpx] font-500">.{{ parseFloat(item.store_commission).toFixed(2).split('.')[1] }}</text>
						</view>
					</view>
					<view class="flex items-center" v-if="item.time_reminder &&  item.time_reminder.text">
						<view class="flex items-center border-1 border-solid p-[8rpx] rounded-[5rpx]"
							:style="{borderColor:item.time_reminder.color}">
							<text class="iconfont iconshijian text-[26rpx] leading-1 mr-[10rpx]"
								:style="{color:item.time_reminder.color}"></text>
							<view class="text-[22rpx] " :style="{color:item.time_reminder.color}">
								{{item.time_reminder.text}}
							</view>
						</view>
					</view>
					<view class="flex mt-[20rpx]">
						<view
							class="flex flex-col items-center justify-between bg-[#F6F6F6] px-[5rpx] py-[30rpx] rounded-[50rpx]">
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
							<view class="" >
								<view class="text-[30rpx] font-bold truncate w-72 leading-[50rpx]">
									{{item.item[0]?.item_name}}
								</view>
								<view class="!text-[24rpx] text-[#666666] mt-[10rpx] truncate w-72 ">
									{{item.member_message || '暂无备注信息'}}
								</view>
							</view>
						</view>
					</view>

					<view class="mt-[20rpx] mb-[10rpx] flex" v-if="item.order_status_info && orderCurrent">
						<button v-for="(btnItem,btnIndex) in item.order_status_info.action" :key="btnIndex"
							@click.stop="handleOrderAction(item, btnItem.key)"
							:style="{
							  color: btnItem.key == 'action_save_check' || btnItem.key == 'action_dispatch' 
							    ? 'white' 
							    : '', // 非这两个key时用默认文字色
							  backgroundColor: btnItem.key == 'action_dispatch' 
							    ? '#00CB7A' 
							    : 'var(--store-bg-one)', // dispatch用特定色，其他用变量色
							  border: btnItem.key == 'action_save_check' || btnItem.key == 'action_dispatch' 
							    ? 'none' 
							    : '1px solid var(--store-bg-one)' // 仅非特殊key时显示边框
							}"
							class="text-[26rpx] h-[80rpx] leading-[80rpx] flex items-center justify-center rounded-[20rpx] flex-1 mx-[10rpx]">
							{{btnItem.name}}
						</button>
					</view>
					<view class="mt-[20rpx] mb-[10rpx]" v-if="!orderCurrent">
						<button @click.stop="grabOrderFn(item)"
							class="text-[26rpx] h-[80rpx] leading-[80rpx] flex items-center justify-center rounded-[20rpx] flex-1 mx-[10rpx] bg-[#FBD700]">
							立即抢单
						</button>
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
		<u-popup :show="showPopup" @close="close" @open="open" mode="top"
			:custom-style="{ top: '88rpx', height: '60%' }" :overlayStyle="{ 
			    top: showPopup?'calc(88rpx)' :'',
			    bottom:showPopup? '0' : '',               
			    background: showPopup ? 'rgba(0, 0, 0, 0.5)' : ''
			  }">
		<!-- #endif -->
			<!-- #ifdef MP-WEIXIN -->
			<u-popup :show="showPopup" @close="close" @open="open" mode="top"
				:custom-style="{ top: navHeight + 48 + 'px', height: '60%' }" :overlayStyle="{ 
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
								:class="typeIndex == idx ? 'activeStyle' : ''" v-for="(item, idx) in typeList"
								:key="idx" @click="handleTypeClick(idx)">
								{{item.category_name}}
							</view>
						</view>
					</view>

					<!-- 服务距离区域 -->
					<view class="mb-4">
						<text class="block text-[30rpx] font-bold mb-[30rpx]">{{t('serviceDistance')}}</text>
						<view class="flex flex-wrap gap-2">
							<view class="px-[42rpx] py-[20rpx] rounded-[10rpx] text-[26rpx] bg-[#F6F6F6]"
								:class="distanceIndex == idx ? 'activeStyle' : ''" v-for="(item, idx) in distanceList"
								:key="idx" @click="handleDistanceClick(idx)">
								{{item.text}}
							</view>
						</view>
					</view>

					<!-- 操作按钮区域 -->
					<view class="flex gap-2 mt-4">
						<button type="default"
							class="box-border !w-[40%] mp-border !rounded-[15rpx] !bg-[#fff] leading-[80rpx] h-[80rpx] flex items-center justify-center text-[26rpx]"
							@click="reset">
							<image :src="img('/addon/home_service/store/reset.png')"
								class="block w-[30rpx] pb-[4rpx] h-[30rpx] mr-[10rpx]" mode="aspectFit"></image>
							{{t('reset')}}
						</button>
						<u-button type="primary" class="!w-[60%] !rounded-[15rpx]"
							@click="confirm">{{t('confrim')}}</u-button>
					</view>
				</view>
			</u-popup>
			<tabbar :value="0"></tabbar>
	</view>
</template>

<script setup lang="ts">
	import { img, redirect, getToken } from '@/utils/common'
	import { ref, computed, onMounted } from 'vue'
	import storeHeader from '@/addon/home_service/store/components/store-header/store-header.vue'
	import { getStoreInfo } from '@/addon/home_service/store/api/store'
	import tabbar from '@/addon/home_service/store/components/tabbar/tabbar.vue'
	import useSystemStore from '@/stores/system';
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import OrderMethods from '@/addon/home_service/store/pages/order/js/OrderMethods';
	import { getOrderList, getOrderStatus,getCategoryOrderList ,getGrapOrderList } from '@/addon/home_service/store/api/order'
	import { t } from '@/locale'
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	const systemStore = useSystemStore()
	// 控制弹窗显示/隐藏
	const orderCurrentIndex = ref(0)
	const showPopup = ref(false)
	// 1. 小程序菜单按钮（胶囊）信息：用于计算适配距离
	const menuButtonInfo = ref({});
	const orderCurrent = ref('')
	menuButtonInfo.value = systemStore.menuButtonInfo
	// 导航栏总高度（小程序：胶囊高度 + 胶囊top + 底部预留8px；H5：固定高度）
	const navHeight = computed(() => {
		// #ifdef MP-WEIXIN
		return (menuButtonInfo.value.height || 32) + (menuButtonInfo.value.top || 10);
		// #endif
		// #ifdef H5
		return 50; // H5：固定高度（可根据设计调整）
		// #endif
	});
	const storeInfo = ref({})
	const getStoreInfoFn = () =>{
		loading.value = true
		getStoreInfo().then((res)=>{
			storeInfo.value = res.data
			getMescroll().resetUpScroll()
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
			loading.value = false
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
	onLoad((option)=>{
  getStoreInfoFn()
  // 直接传递接收到的order_status参数给getOrderStatusFn函数
  getOrderStatusFn(option.order_status)
})
const orderStatusList = ref([])
const getOrderStatusFn = (data:any) =>{
  getOrderStatus().then((res)=>{
    // 清空现有列表，避免重复添加
    orderStatusList.value = []
    Object.keys(res.data).forEach((item,index)=>{
      let obj = {
        label:res.data[item].name,
        value:item
      }
      orderStatusList.value.push(obj)
    })
    orderStatusList.value.unshift({
      label:'新任务',
      value:''
    })
    
    // 查找并选中对应的订单状态tab
    const targetIndex = orderStatusList.value.findIndex(item => item.value === data)
    if(targetIndex !== -1){
      orderCurrentIndex.value = targetIndex
      orderCurrent.value = data
    } else {
      // 如果没找到对应状态，默认选中第一个
      orderCurrentIndex.value = 0
      orderCurrent.value = ''
    }
    
    // 设置服务状态信息
    orderStatusList.value.forEach((item)=>{
      if(item.value == 'in_progress'){
        serviceStatus.value = res.data[item.value].action
      }
    })
    
    // 重置滚动加载，显示对应状态的订单列表
    getMescroll().resetUpScroll()
  })
}
	const goDetail = (e:any) =>{
		if(!orderCurrent.value){
			redirect({url:'/addon/home_service/store/pages/order/grapOrder',param:{order_id:e.order_id}})
		}else{
			redirect({url:'/addon/home_service/store/pages/order/detail',param:{order_id:e.order_id}})
		}
	}
	// 处理订单按钮点击
	const handleOrderAction = (order : any, key : string) => {
		OrderMethods.orderClickFunction(
			order,
			key,
			() => clacOrderList(), // 刷新列表的回调
		);
	};
	
	const grabOrderFn = (e:any) =>{
		redirect({url:'/addon/home_service/store/pages/order/grapOrder',param:{order_id:e.order_id}})
	}
	const list1 = [{
		name: '新任务',
	}, {
		name: '待派单',
	}, {
		name: '进行中'
	}, {
		name: '异常订单'
	}]
	const currentStatus = ref(1)
	// 服务类型数据（含选中状态）
	const typeList = ref([])
	const serviceActiveIndex = ref(0)
	const serviceStatus = ref([])
	// 服务距离数据（含选中状态）
	const distanceList = ref([
		{ text: '不限', key: '' },
		{ text: '3km', key: '3' },
		{ text: '5km', key: '5' },
		{ text: '10km', key: '10' },
	])
	const changeStatus = (e : any) => {
		currentStatus.value = e
	}
	const close = () => {
		showPopup.value = false
	};
	const changeServiceActive = (e:any) =>{
		serviceActiveIndex.value = e
		getMescroll().resetUpScroll()
	}
	const open = () => {
		showPopup.value = true
	};
	const clacOrderList = () => {
		getMescroll().resetUpScroll()
	}
	const typeIndex = ref(0)
	// 切换服务类型选中状态
	const handleTypeClick = (idx : number) => {
		typeIndex.value = idx
	}
	const changeOrderStatus = (e:any) =>{
		orderCurrent.value= e.value
		getMescroll().resetUpScroll()
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
	const getCategoryOrderListFn = () => {
		getCategoryOrderList().then((res) => {
			typeList.value = res.data
			typeList.value.unshift({
				category_name: '全部',
				category_id: 'all'
			})
			getMescroll().resetUpScroll()
		})
	}
	getCategoryOrderListFn()
	// 获取订单列表
	const getListFn = (mescroll : mescrollStructure) => {
		if (!typeList.value.length) return;
		loading.value = true
		listLoading.value = false
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
			category_id: typeList.value[typeIndex.value].category_id,
			order_status: orderCurrent.value || '',
			distance: distanceList.value[distanceIndex.value].key,
			lng: storeInfo.value?.lng,
			lat:storeInfo.value?.lat
		}
		// 当处于服务中状态时，添加sub_status参数
		if(orderCurrent.value == 'in_progress'){
			data = {
				...data,
				true_order_status: serviceStatus.value[serviceActiveIndex.value].status
			}
		}
		if(orderCurrent.value){
			getOrderList(data).then((res : acceptingDataStructure) => {
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
		}else{
			getGrapOrderList(data).then((res : acceptingDataStructure) => {
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
		
	}
</script>
<style>
	.boder-bottom {
		border-bottom: 4rpx solid #ffffff;
	}

	.activeStyle {
		background: var(--store-bg-one);
		color: #fff;
	}

	.mp-border {
		border: 2rpx solid #cccccc !important;
	}

	/deep/ .u-tabs__wrapper__nav__line {
		background: #fff !important;
	}
	/deep/ .u-navbar__content__title{
		color:#fff !important
	}
	.margin-top-style {
		margin-top: 86rpx !important;
	}
	.serviceActive{
		border: 2rpx solid #000;
		box-sizing: border-box;
	}
	/deep/ .u-navbar--fixed{
		z-index:99999999 !important
	}
</style>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>