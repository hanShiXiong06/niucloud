<template>
	<view class="bg-[#f6f6f6]">
		<technician-header bgColor="#111111" titleColor="#fff" v-model:statusIndex="currentStatus"
			@changeStatus="changeStatus" />
		<view class="bg-[#111] px-[20rpx] pb-1 items-center fixed w-full z-index-999 box-border"
			v-if="orderStatusList.length">
			<!-- #ifdef MP-WEIXIN -->
			<view class="w-full h-[20rpx] bg-[#111]"></view>
			<!-- #endif -->
			<!-- 新任务标题 -->
			<view class="flex justify-between flex-1">
				<view class="flex justify-between flex-1 pr-[40rpx]">
					<view class="text-[#d6d6d6] flex flex-col items-center justify-center"
						v-for="(item,index) in orderStatusList" :key="index" @click="changeOrderStatus(index)">
						<view class="leading-[70rpx] relative text-[28rpx]"
							:class="[{'text-white font-bold': index == orderStatusIndex}]">
							{{item.title}}
							<view
								class="absolute top-[0rpx] bg-[#FF0000] right-[-25rpx] leading-1 p-[12rpx] rounded-[50%] text-[20rpx] !text-white"
								v-if="item.key == 'abnormal_order' && item.abnormal_count">
								{{item.abnormal_count}}
							</view>
						</view>
						<view class="w-[80rpx] h-[4rpx] " :class="[{'bg-white': index == orderStatusIndex}]"></view>
					</view>
				</view>

				<view class="flex items-center">
					<view class="h-[28rpx] w-[1px] bg-gradient-to-b from-white to-gray-700"></view>
					<!-- 刷新按钮 -->
					<view class="flex items-center ml-[20rpx]" @click="clacOrderList">
						<image :src="img('/addon/home_service/technician/shuaxin.png')"
							class="block w-[34rpx] h-[34rpx] block" mode="aspectFit"></image>
						<view class="text-white text-[26rpx] ml-2">{{t('calcation')}}</view>
					</view>
				</view>
			</view>
			<view
				v-if="(timeOutOrder.timeout_count || timeOutOrder.about_to_timeout_count) && orderStatusList[orderStatusIndex].key == 'wait_service'"
				class="absolute bottom-[-66rpx] h-[66rpx] leading-[66rpx] text-[24rpx] flex w-full left-0 bg-[#FFF0F0] items-center px-[20rpx] box-border justify-between">
				<view class="flex items-center">
					<image :src="img('/addon/home_service/technician/tanhao.png')"
						class="block w-[28rpx] h-[28rpx] mr-[20rpx]" mode="aspectFit"></image>
					<text>
						<block v-if="timeOutOrder.timeout_count">
							您有{{timeOutOrder.timeout_count}}个订单已超时
						</block>
						<block v-if="timeOutOrder.timeout_count && timeOutOrder.about_to_timeout_count">
							,
						</block>
						<block v-if="timeOutOrder.about_to_timeout_count">
							{{timeOutOrder.about_to_timeout_count}}个订单即将超时,请尽快处理!
						</block>
					</text>
				</view>
				<view @click="closeTimeOut">
					<image :src="img('/addon/home_service/technician/close.png')" class="block w-[28rpx] h-[28rpx]"
						mode="aspectFit"></image>
				</view>
			</view>
		</view>
		<view class="mescroll-box bg-[#f8f8f8]">
			<mescroll-body ref="mescrollRef" :top="navHeight + 'px'" :down="{ use: false }" @init="mescrollInit"
				@up="getListFn">
				<view class="bg-[#fff] mb-[20rpx] mx-[20rpx] rounded-[16rpx] p-[20rpx]" @click.stop="redirect({url:'/addon/home_service/technician/pages/order/detail',param:{order_id:item.order_id}})"
					v-for="(item, index) in orderList" :key="index"
					:class="[{'margin-top-style': (index== 0 && orderStatusList[orderStatusIndex].key == 'wait_service' && (timeOutOrder.timeout_count || timeOutOrder.about_to_timeout_count))}]">
					<view class="flex justify-between items-center">
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
						<view class="text-[26rpx]" :class="[{'text-[#111]':item.order_status_info.status == 'in_service'},{'text-[#999]':item.order_status_info.status == 'wait_check'},
							{'text-[#FF0000]':item.order_status_info.status == 'abnormal_order'}]">
							{{item.order_status_info.name}}
						</view>
					</view>
					<view class="text-[24rpx] text-[#999999] mb-[20rpx] mt-[10rpx]">
						{{t('orderNoTit')}}：{{item.order_no}}
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
					<view class="flex mt-[20rpx] w-[100%] box-border">
						<view
							class="flex flex-col items-center justify-between bg-[#F6F6F6] px-[10rpx] py-[30rpx] rounded-[50rpx]">
							<view class="text-[24rpx]">
								<view class="text-center mb-[5rpx]">
									{{t('user')}}
								</view>
							</view>
							<view class="w-[2rpx] h-[30rpx] bg-[#ccc] my-[30rpx]">
							</view>
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
								{{t('services')}}
							</view>
						</view>
						<view class="flex flex-col ml-[20rpx] justify-between pt-[20rpx] pb-[20rpx] w-[70%] box-border">
							<view class="" @click.stop="callPhone(item.taker_mobile)">
								<view class="flex items-center text-[32rpx] font-bold truncate leading-[1.2]">
									<text>
										{{item.taker_name}}
									</text>
									<text class="mx-[10rpx]">
										{{maskPhone(item.taker_mobile)}}
									</text>
									<image :src="img('/addon/home_service/technician/call-phone.png')"
										class="w-[25rpx] ml-[5rpx]" mode="widthFix"></image>
								</view>
							</view>
							<view class="">
								<view class="text-[30rpx] font-bold truncate">
									{{item.taker_address}}
								</view>
								<view class="text-[24rpx] text-[#666666] mt-[15rpx] truncate ">
									{{item.taker_full_address}}
								</view>
							</view>
							<view class="" >
								<view class="text-[30rpx] font-bold truncate leading-5">
									{{item.item[0]?.item_name}}
								</view>
								<view class="text-[24rpx] text-[#666666] mt-[15rpx] truncate " >
									{{item.member_message || '暂无备注信息'}}
								</view>
							</view>
						</view>
						<view class="flex-1 flex items-center justify-end text-[26rpx] text-[var(--technician-bg-one)]"
							@click.stop="openMapNavigation(item)">
							{{t('Navigation')}} <text class="iconfont iconarrow-right text-[26rpx]"></text>
						</view>
					</view>
					<view v-if="Number(item.technician_total_money)"
						class="flex justify-between items-center  p-[20rpx] bg-[#F6F9FF] rounded-[14rpx] my-[20rpx]">
						<view class="flex flex-col flex-1">
							<view>
								<text class="text-[30rpx] font-bold">{{t('ProjectedRevenue')}}</text>
								<text v-if="!item.store_id"
									class="border-1 border-solid border-[var(--technician-bg-one)] text-[var(--technician-bg-one)] text-[22rpx] ml-[15rpx] rounded-[3rpx] px-[8rpx] leading-1">自抢</text>
							</view>
							<view class="text-[24rpx] text-[#999999] mt-[10rpx]">
								{{t('orderCommsion')}}￥{{item.technician_commission}}<text
									v-if="Number(item.technician_total_item_money)">+附加服务收入￥{{item.technician_total_item_money}}</text>
							</view>
						</view>
						<view class="text-[#FF0000] text-[34rpx] font-bold" v-if="Number(item.technician_total_money)">
							￥{{item.technician_total_money}}
						</view>
					</view>
					<view class="mt-[20rpx]"
						v-if="(orderStatusIndex == 0) && item.order_status_info &&item.order_status_info.action.length">
						<view v-for="(btnItem,btnIndex) in item.order_status_info.action">
						<button  v-if="btnItem.key != 'edit_reserve_service_time'"
							@click.stop="handleOrderAction(item, btnItem.key)" 
							class="text-[28rpx] h-[80rpx] leading-[80rpx] flex items-center justify-center rounded-[20rpx] "
							:style="{
							  backgroundColor: btnItem.key === 'action_depart' ||  btnItem.key === 'action_photo_taken'
							    ? 'var(--technician-bg-two)' 
							    : (btnItem.key === 'action_action_start')
							      ? 'var(--technician-bg-one)' 
							      : '',
							  color: btnItem.key === 'action_action_start'
							    ? 'white' 
							    : ''
							}">
							<block v-if="btnItem.key == 'action_depart'">
								<image :src="img('/addon/home_service/technician/gouout.png')"
									class="w-[30rpx] h-[30rpx] mr-[15rpx] block" mode="aspectFit">
								</image> {{t('confirmGoOut')}}
							</block>
							<block v-else-if="btnItem.key == 'action_action_start'">
								<image :src="img('/addon/home_service/technician/start_services.png')"
									class="w-[30rpx] h-[30rpx]  mr-[15rpx] block" mode="aspectFit">
								</image> {{t('speakServices')}}
							</block>
							<block v-else-if="btnItem.key == 'action_photo_taken'">
								<image :src="img('/addon/home_service/technician/zhaoxiangji.png')"
									class="w-[30rpx] h-[30rpx] mr-[15rpx] block" mode="aspectFit">
								</image> {{t('callCard')}}
							</block>
						</button>
						</view>
					</view>
					<view class="mt-[20rpx] flex" v-else-if=" item.order_status_info">
						<button v-for="(btnItem,btnIndex) in item.order_status_info.action" :key="btnIndex"
							@click.stop="handleOrderAction(item, btnItem.key)"
							:style="{
							  color: btnItem.key == 'action_save_check' ? 'white' : 'initial',
							  backgroundColor: btnItem.key == 'action_save_check' 
							    ? 'var(--technician-bg-one)' 
							    : 'var(--primary-color-light)',
							  border: btnItem.key == 'action_save_check' 
							    ? 'none' 
							    : '1px solid var(--technician-bg-one)'
							}"
							class="text-[26rpx] h-[80rpx] leading-[80rpx] flex items-center justify-center rounded-[20rpx] flex-1 mx-[10rpx]">
							{{btnItem.name}}
						</button>
					</view>
				</view>
				<view class="pl-[20rpx] pt-[0rpx]" style="width: calc(100% - 182rpx)"
					:class="[{'margin-top-style': ( (timeOutOrder.timeout_count || timeOutOrder.about_to_timeout_count))}]">
					<mescroll-empty :option="{ icon: img('static/resource/images/empty.png'), tip: t('nothingMore') }"
						v-if="!orderList.length && !loading && listLoading" class="part"></mescroll-empty>
				</view>
			</mescroll-body>
		</view>
		<loading-page :loading="loading"></loading-page>

		<!-- 通用弹窗组件 -->
		<order-popup :show="popupState.visible" :order="popupState.order"
		  :action-key="popupState.actionKey" @close="closePopup()"
		  @confirm="handlePopupConfirm"></order-popup>

		<tabbar :value="1"></tabbar>
	</view>
</template>

<script setup lang="ts">
	import { img, redirect, getToken } from '@/utils/common'
	import OrderMethods from '@/addon/home_service/technician/pages/order/js/orderMethods';
	import orderPopup from '@/addon/home_service/technician/components/orderPopup/orderPopup.vue';
	import { ref, computed, onMounted } from 'vue'
	import TechnicianHeader from '@/addon/home_service/technician/components/technician-header/technician-header.vue'
	import tabbar from '@/addon/home_service/technician/components/tabbar/tabbar.vue'
	import useSystemStore from '@/stores/system';
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { uploadImage } from '@/app/api/system'
	import { getAllTechnicianOrderList, getOrderStatus } from '@/addon/home_service/technician/api/order'
	import { t } from '@/locale'
import { popupState, closePopup, confirmPopup } from '@/addon/home_service/technician/pages/order/js/popupStatus'

	// 系统状态管理
	const systemStore = useSystemStore()

	// 处理订单按钮点击
	const handleOrderAction = (order : any, key : string) => {
		OrderMethods.orderClickFunction(
			order,
			key,
			() => clacOrderList(), // 刷新列表的回调
		);
	};

	// 弹窗确认回调
	const handlePopupConfirm = (popupData ?: any) => {
		confirmPopup(popupData)
	};

	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app'

	// 1. 小程序菜单按钮（胶囊）信息：用于计算适配距离
	const menuButtonInfo = ref({});
	menuButtonInfo.value = systemStore.menuButtonInfo

	// 导航栏总高度
	const navHeight = computed(() => {
		// #ifdef MP-WEIXIN
		return (menuButtonInfo.value.height || 32) + (menuButtonInfo.value.top || 10) -25;
		// #endif
		// #ifdef H5
		return 50; // H5：固定高度
		// #endif
	});

	const clacOrderList = () => {
		orderList.value = []
		getMescroll().resetUpScroll()
	}

	const currentStatus = ref(1)
	const typeList = ref([])
	const orderStatusList = ref([])
	const orderStatusIndex = ref(0)

	const getOrderStatusFn = () => {
		getOrderStatus().then((res) => {
			Object.keys(res.data).forEach((item, index) => {
				orderStatusList.value[index] = { title: '', key: '' }
				orderStatusList.value[index].title = res.data[item].name
				orderStatusList.value[index].key = item
				orderStatusList.value[index].abnormal_count = res.data[item].abnormal_count
				if (item == 'wait_service') {
					timeOutOrder.value = res.data[item]
				}

			})
			getMescroll().resetUpScroll()
		})
	}
	getOrderStatusFn()

	const openMapNavigation = (data : any) => {
		console.log(data.taker_latitude)
		console.log(data.taker_longitude)
		console.log(data.taker_address)
		console.log(data.taker_full_address)
		console.log(data)
		uni.openLocation({
			latitude: Number(data.taker_latitude),
			longitude: Number(data.taker_longitude),
			name: data.taker_address,
			address: data.taker_full_address,
			scale: 18,
			success: () => {
				console.log('地图打开成功');
			},
			fail: (err) => {
				uni.showToast({
					title: '打开地图失败，请检查是否安装地图应用',
					icon: 'none',
					duration: 2000
				});
			}
		});
	}

	const closeTimeOut = () => {
		timeOutOrder.value.timeout_count = 0
		timeOutOrder.value.about_to_timeout_count = 0
	}

	const timeOutOrder = ref({})

	const changeOrderStatus = (e : any) => {
		orderList.value = []
		orderStatusIndex.value = e
		getMescroll().resetUpScroll()
	}

	// 服务距离数据
	const distanceList = ref([
		{ text: '不限', active: false },
		{ text: '3km', active: true },
		{ text: '5km', active: false },
		{ text: '10km', active: false },
	])

	const changeStatus = (e : any) => {
		currentStatus.value = e
	}

	// 服务类型切换
	const typeIndex = ref(0)
	const handleTypeClick = (idx : number) => {
		typeIndex.value = idx
	}

	// 距离切换
	const distanceIndex = ref(0)
	const handleDistanceClick = (idx : number) => {
		distanceIndex.value = idx
	}

	// 重置筛选条件
	const reset = () => {
		typeIndex.value = 0
		distanceIndex.value = 0
	}

	// 确认筛选
	const confirm = () => {
		// 筛选逻辑
	}

	// 拨打电话
	const callPhone = (e : any) => {
		uni.makePhoneCall({
			phoneNumber: e,
		});
	}

	// 手机号脱敏
	const maskPhone = (phone : any) => {
		if (!/^1[3-9]\d{9}$/.test(phone)) {
			return phone;
		}
		return phone.replace(/^(\d{3})\d{4}(\d{4})$/, '$1****$2');
	}

	const orderList = ref([])
	const loading = ref<boolean>(true) //页面加载动画
	const listLoading = ref<boolean>(false) //列表加载动画

	// 获取项目列表
	const getListFn = (mescroll : any) => {
		if (!orderStatusList.value.length) return
		loading.value = true
		listLoading.value = false
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
			order_status: orderStatusList.value[orderStatusIndex.value].key,
			lng: systemStore.diyAddressInfo?.longitude,
			lat: systemStore.diyAddressInfo?.latitude
		}
		getAllTechnicianOrderList(data).then((res : any) => {
			let newArr = res.data.data
			if (mescroll.num == 1) {
				orderList.value = []
			}
			orderList.value = orderList.value.concat(newArr)
			loading.value = false
			mescroll.endSuccess(newArr.length)
			if (!orderList.value.length) listLoading.value = true
		}).catch(() => {
			loading.value = false
			listLoading.value = true
			mescroll.endErr()
		})
	}
</script>
<style>
	.boder-bottom {
		border-bottom: 4rpx solid #ffffff;
	}

	.margin-top-style {
		margin-top: 66rpx !important;
	}

	.activeStyle {
		background: var(--primary-color);
		color: #fff;
	}
</style>
<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>