<template>
	<view :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<template v-if="!loading">
			<view v-if="detail" class="bg-[#f7f7f7] min-h-screen overflow-hidden">
				<view class="h-[800rpx] w-full">
					<map class="map-body w-full h-[800rpx]" :latitude="detail.taker_latitude" :longitude="detail.taker_longitude" :markers="covers"></map>
				</view>
				<view class="bg-[#fff] my-[30rpx] mx-[30rpx] rounded-[16rpx] p-[30rpx] mt-[-60rpx] relative z-index-999"
					>
					<view class="flex justify-between mb-[10rpx]">
						<view class="flex items-center">
							<view class="flex items-center rounded-l-[50rpx] rounded-r-[6rpx] mr-[10rpx]">
								<view class="flex items-center mr-[5rpx]" v-if="detail.buy_type == 'reservation'">
									<image :src="img('/addon/home_service/technician/yuyue-icon.png')"
										class="w-[70rpx] mr-[5rpx]" mode="widthFix"></image>
								</view>
								<view class="flex items-center mr-[5rpx]" v-if="detail.is_abnormal == 1">
									<image :src="img('/addon/home_service/technician/error-icon.png')"
										class="w-[70rpx] mr-[5rpx]" mode="widthFix"></image>
								</view>
								<view class="flex items-center mr-[5rpx]" v-if="detail.is_card_order == 1">
									<image :src="img('/addon/home_service/technician/cika-icon.png')"
										class="w-[70rpx] mr-[5rpx]" mode="widthFix"></image>
								</view>
							</view>
					
							<view class="text-[26rpx] text-[#666666] flex items-center pt-[2rpx]">
								{{detail?.reserve_service_time}}
							</view>
						</view>
						<text class="text-[28rpx] font-bold text-[#ff0000]">待接单</text>
					</view>
					<view class="text-[24rpx] text-[var(--technician-bg-one)] pl-[10rpx]">
						{{passedTime}}
					</view>
					<view
						v-if="(detail?.timeout_count || detail?.about_to_timeout_count) && orderStatusList[orderStatusIndex].key == 'wait_service'"
						class="absolute bottom-[-66rpx] h-[66rpx] leading-[66rpx] text-[24rpx] flex w-full left-0 bg-[#FFF0F0] items-center px-[20rpx] box-border justify-between">
						<view class="flex items-center">
							<image :src="img('/addon/home_service/technician/tanhao.png')"
								class="block w-[26rpx] h-[26rpx] mr-[20rpx]" mode="aspectFit"></image>
							<text>
								<block v-if="detail?.timeout_count">
									{{ t('youHave') }}{{detail?.timeout_count}}{{ t('ordersOverdue') }}
								</block>
								<block v-if="detail?.timeout_count && detail?.about_to_timeout_count">
									,
								</block>
								<block v-if="detail?.about_to_timeout_count">
									{{detail?.about_to_timeout_count}}{{ t('ordersAboutToOverdue') }}
								</block>
							</text>
						</view>
						<view @click="closeTimeOut">
							<image :src="img('/addon/home_service/technician/close.png')"
								class="block w-[26rpx] h-[26rpx]" mode="aspectFit"></image>
						</view>
					</view>
					<view class="flex mt-[20rpx]">
						<view class="flex flex-col items-center justify-between px-[5rpx] py-[10rpx] rounded-[50rpx]">
							<view class="text-[24rpx]">
								<view class="w-[10rpx] h-[10rpx] rounded-[50%] bg-[#000]">
								</view>
							</view>
							<view class="w-[2rpx] h-[30rpx] bg-[#ccc] my-[10rpx]">
							</view>
							<view class="w-[10rpx] h-[10rpx] rounded-[50%] bg-[#000]">
							</view>
						</view>
						<view class="flex items-center justify-between flex-1">
							<view class="flex flex-col ml-[20rpx] justify-between">
								<view class="">
									<view class="text-[30rpx] font-bold   leading-[50rpx]">
										{{detail?.taker_name}} {{maskPhone(detail?.taker_mobile)}}
										<image :src="img('/addon/home_service/technician/call-phone.png')"
											@click="callPhone(detail?.taker_mobile)" class="w-[30rpx] ml-[5rpx]"
											mode="widthFix"></image>
									</view>
								</view>
								<view class="flex justify-between items-center mt-[15rpx]">
									<view class="">
										<view class="text-[30rpx] font-bold   leading-[40rpx]">
											{{detail?.taker_address}}
										</view>
										<view class="text-[24rpx] text-[#666666]  ">
											{{detail?.taker_full_address}}
										</view>
									</view>
								</view>
							</view>
							<view>
								<view
									class="flex-1 flex items-center justify-end text-[26rpx] text-[var(--technician-bg-one)] w-[100rpx]"
									@click.stop="openMapNavigation(detail)">
									{{t('Navigation')}} <text class="iconfont iconarrow-right text-[26rpx]"></text>
								</view>
							</view>
						</view>
					</view>
				</view>

				<view class="mt-[30rpx]">
					<view class="bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-lg">
						<view class="flex justify-between">
							<view class="text-[30rpx] font-bold">{{ t('serviceItems') }}</view>
							<view class="flex justify-between items-end text-[26rpx]">
								<view class="text-[22rpx] text-[var(--price-text-color)] price-font">
									{{ t('realMoney') }}</view>
								<view
									class="text-[32rpx] font-bold leading-[35rpx] text-[var(--price-text-color)] price-font">
									<text class="!text-[22rpx]">￥</text>{{ detail.pay_money }}</view>
							</view>
						</view>
						<view
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>{{ detail?.item[0].item_name}}</view>
							<view class=" price-font"><text
									class="!text-[24rpx]">￥</text>{{ detail?.item[0].item_money }}</view>
						</view>
						<view class="bg-[#F6F6F6] p-[30rpx] text-[24rpx] text-[#999999] my-[24rpx] rounded-[10rpx]"
							>
							{{ t('remarkPrefix') }}{{detail.member_message || '暂无备注信息'}}
						</view>
						<view v-if="detail.discount_money"
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>{{ t('couponMoney') }}</view>
							<view class="price-font"><text
									class="!text-[24rpx]">￥</text>{{ detail.discount_money }}
							</view>
						</view>
					</view>
				</view>

				<view class="mt-[30rpx] ">
					<view class="bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-lg">
						<view class="flex justify-between">
							<view class="text-[30rpx] font-bold">{{ t('orderIncome') }}</view>
							<view class="flex justify-between items-end text-[26rpx]">
								<view
									class="text-[32rpx] font-bold leading-[35rpx]  text-[var(--price-text-color)] price-font">
									<text class="!text-[22rpx]">￥</text>{{ detail.store_commission }}</view>
							</view>
						</view>
						<view class="bg-[#F6F6F6] p-[30rpx] text-[24rpx] text-[#999999] mt-[24rpx] rounded-[10rpx]"
							>
							{{ t('serviceFeeDesc') }}
						</view>
					</view>
				</view>

				<view class="mt-[30rpx] mb-[80rpx]">
					<view class="bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-[10rpx]">
						<view class="text-[30rpx] font-bold">
							{{ t('otherInformation') }}
						</view>
						<view
							class="flex justify-between text-[26rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[30rpx]">
							<view>{{ t('onOrder') }}</view>
							<view class="flex items-center">{{ detail.order_no }} <text @click="copy(detail?.order_no)"
									class="text-[#4a83ff] text-[26rpx] ml-[10rpx]">复制</text></view>
						</view>
						<view
							class="flex justify-between text-[26rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[30rpx]">
							<view>{{ t('createTime') }}</view>
							<view>{{ detail.create_time }}</view>
						</view>
						<view v-if="detail.pay_time"
							class="flex justify-between text-[26rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[30rpx]">
							<view>{{ t('payTime') }}</view>
							<view>{{ detail.pay_time }}</view>
						</view>
					</view>
				</view>
			</view>
			<view class="w-screen h-screen flex flex-col justify-center items-center" v-else>
				<u-empty :icon="img('static/resource/images/order_empty.png')" :text="t('orderInfoNotObtained')" />
			</view>
			
			<!-- 提交按钮 -->
			<view class="w-full footer bg-[#fff]">
				<view
					class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full bg-[#fff] fixed bottom-0 left-0 right-0 box-border">
					<button hover-class="none"
						class=" !text-[#111] !text-[#fff] !bg-[#FBD700] h-[80rpx] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500"
						@click="submitOrder(detail)"
						:class="{'opacity-50': btnDisabled}">我要抢单
					</button>
				</view>
			</view>
		</template>
		<u-popup :show="showImage" @close="showImage = false" @open="showImage = true" mode="center" zIndex="9"
			round="15">
			<view class="text-[32rpx] font-bold text-center pt-[30rpx]">
				{{ t('checkInPhotos') }}
			</view>
			<view class="px-[30rpx] py-[30rpx] pb-[20rpx]  grid grid-cols-4 gap-4 ">
				<view v-for="(imageUrl, imgIndex) in detail.take_photos" :key="imgIndex" class=" rounded-lg">
					<u--image :src="img(imageUrl)" width="140rpx" height="140rpx" radius="10rpx"
						@click="imgListPreview(imageUrl, imgIndex)">
						<view slot="error" style="font-size: 24rpx;">{{ t('loadFailed') }}</view>
					</u--image>
				</view>
			</view>
		</u-popup>
		<loading-page :loading="loading"></loading-page>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed, onMounted, onUnmounted } from 'vue'
	import { onLoad, onShow } from '@dcloudio/uni-app'
	import { img, redirect, copy } from '@/utils/common'
	import { getGrapOrderDetail} from '@/addon/home_service/store/api/order'
	import { grabOrder  } from '@/addon/home_service/store/api/order'
	import { t } from '@/locale'
	import OrderMethods from '@/addon/home_service/store/pages/order/js/OrderMethods';
	import useSystemStore from '@/stores/system';
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '抢单', topStatusBar: { textColor: '#333' } })
	const passedTime = ref('')
	const systemStore = useSystemStore()
	const showImage = ref(false)
	const  covers =ref([])
	// 处理订单按钮点击
	const handleOrderAction = (order : any, key : string) => {
		OrderMethods.orderClickFunction(
			order,
			key,
			() => getOrderDetailFu(), // 刷新列表的回调
		);
	};
	const submitOrder = (e:any) =>{
		uni.showModal({
			title:'抢单提示',
			content:`订单服务时间为${e.reserve_service_time},请提前合理安排工作时间，避免超时！`,
			confirmText:'立即抢单',
			success: function (res) {
				loading.value = true
				if (res.confirm) {
					grabOrder(e.order_id).then((res)=>{
						redirect({url:'/addon/home_service/store/pages/index',param:{order_status:'wait_dispatch'}})
						loading.value = false
					}).catch((err)=>{
						loading.value = false
					})
				} else if (res.cancel) {
					loading.value = false
					console.log('用户点击取消');
				}
			}
		})
	}
	// 弹窗确认回调
	const handlePopupConfirm = (popupData ?: any) => {
		// 调用弹窗确认回调函数
		if (typeof systemStore.popup.onConfirm === 'function') {
			systemStore.popup.onConfirm(popupData);
		}
	};

	// 订单ID
	let orderId = 0
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

	// 订单详情数据
	const detail = ref<AnyObject | null>(null)

	// 加载状态
	const loading = ref(false)

	// 时间相关变量（组件级作用域，确保计时连续）
	const elapsedMs = ref<number>(0) // 已过毫秒数
	let timer : NodeJS.Timeout | null = null // 定时器实例
	const targetTime = ref('') // 目标时间戳（服务开始时间）

	// 订单状态列表（用于超时提示判断）
	const orderStatusList = ref([
		{ key: 'wait_service', title: '待服务' },
		{ key: 'in_service', title: '服务中' },
		{ key: 'finish', title: '已完成' },
		{ key: 'abnormal_order', title: '异常订单' }
	])
	const orderStatusIndex = ref(0)

	// 超时提示控制
	const timeOutOrder = ref({
		timeout_count: 0,
		about_to_timeout_count: 0
	})

	// 格式化已过时长为 "天 时:分:秒"
	const formattedElapsedTime = computed<string>(() => {
		const totalSeconds = Math.floor(elapsedMs.value / 1000)
		const days = Math.floor(totalSeconds / 86400)
		const hours = Math.floor((totalSeconds % 86400) / 3600)
		const minutes = Math.floor((totalSeconds % 3600) / 60)
		const seconds = totalSeconds % 60

		// 补零函数
		const padZero = (num : number) => num.toString().padStart(2, '0')

		return days > 0
			? `${days}天 ${padZero(hours)}:${padZero(minutes)}:${padZero(seconds)}`
			: `${padZero(hours)}:${padZero(minutes)}:${padZero(seconds)}`
	})

	// 计算已过时间
	const calculateElapsedTime = () => {
		const currentTime = new Date().getTime()
		elapsedMs.value = Math.max(0, currentTime - targetTime.value)
	}

	// 控制定时器
	const controlTimer = (isRunning : boolean) => {
		// 清除现有定时器
		if (timer) {
			clearInterval(timer)
			timer = null
		}
		// 启动新定时器
		if (isRunning) {
			calculateElapsedTime() // 立即计算一次
			timer = setInterval(calculateElapsedTime, 1000)
		} else {
			elapsedMs.value = 0 // 停止时重置
		}
	}

	// 获取订单详情
	const getOrderDetailFu = () => {
		loading.value = true
		getGrapOrderDetail(orderId).then((res) => {
			detail.value = res.data

			// 更新超时提示数据
			if (detail.value?.timeout_count !== undefined) {
				timeOutOrder.value.timeout_count = detail.value.timeout_count
			}
			if (detail.value?.about_to_timeout_count !== undefined) {
				timeOutOrder.value.about_to_timeout_count = detail.value.about_to_timeout_count
			}
			covers.value = [
				  {
					id: 1,
					latitude: detail.value.taker_latitude,
					longitude: detail.value.taker_longitude,
					// 自定义标记点图标
					iconPath: '/static/images/marker.png',
					width: 20,
					height: 28
					// 移除价格标签配置
				  }
			]
			// 根据订单状态控制定时器
			const isInService = detail.value?.order_status === 'in_service'
			controlTimer(isInService)
			// -------------------------- 测试使用 --------------------------
			const targetTime = detail.value?.pay_time;
			// 1. 转换为时间戳
			const timestampMs = timeStrToTimestamp(targetTime); // 毫秒级时间戳（如 1753582453000）
			const timestampS = timeStrToTimestamp(targetTime, true); // 秒级时间戳（如 1753582453）
			
			passedTime.value = calcPassedTime(targetTime);
			loading.value = false
			getStatus()
		}).catch((err) => {
			console.error('获取订单详情失败:', err)
			loading.value = false
			controlTimer(false)
		})
	}
	// 关闭超时提示
	const closeTimeOut = () => {
		timeOutOrder.value.timeout_count = 0
		timeOutOrder.value.about_to_timeout_count = 0
	}

	// 打开地图导航
	const openMapNavigation = (data : any) => {
		uni.openLocation({
			latitude: Number(data.latitude),
			longitude: Number(data.longitude),
			name: data.taker_address,
			address: data.taker_full_address,
			scale: 18,
			success: () => {
				console.log('地图打开成功');
			},
			fail: (err) => {
				uni.showToast({
					title: t('mapOpenFailed'),
					icon: 'none',
					duration: 2000
				});
			}
		});
	}

	// 订单按钮点击事件
	const payRef = ref(null)
	const orderBtnFn = (type = '', data = '') => {
		if (type === 'pay') {
			payRef.value?.open(
				detail.value.order_type,
				detail.value.order_id,
				`/addon/home_service/pages/order/detail?order_id=${detail.value.order_id}`
			)
		} else if (type === 'item_pay') {
			payRef.value?.open(
				'o2o_item',
				data.order_item_id,
				`/addon/home_service/pages/order/detail?order_id=${detail.value.order_id}`
			)
		} else if (type === 'cancel') {
			cancel(detail.value)
		} else if (type === 'delete') {
			deleteFn(detail.value)
		} else if (type === 'index') {
			redirect({
				url: '/addon/home_service/pages/index',
				mode: 'reLaunch'
			})
		}
	}

	// 手机号脱敏
	const maskPhone = (phone : any) => {
		if (!/^1[3-9]\d{9}$/.test(phone)) {
			return phone;
		}
		return phone.replace(/^(\d{3})\d{4}(\d{4})$/, '$1****$2');
	}

	// 拨打电话
	const callPhone = (e : any) => {
		if (!e) return
		uni.makePhoneCall({
			phoneNumber: e,
		});
	}

	// 取消订单
	const cancel = (item : any) => {
		uni.showModal({
			title: t('prompt'),
			content: t('confirmCancelOrder'),
			confirmColor: useConfigStore().themeColor['--store-bg-one'],
			success: (res) => {
				if (res.confirm) {
					cancelOrder(item.order_id).then((res) => {
						uni.showToast({ title: res.msg || t('cancelSuccess'), icon: 'none' })
						getOrderDetailFu()
					}).catch((err) => {
						uni.showToast({ title: err.msg || t('cancelFailed'), icon: 'none' })
					})
				}
			}
		})
	}

	// 删除订单
	const deleteFn = (data : any) => {
		uni.showModal({
			title: t('prompt'),
			content: t('confirmDeleteOrder'),
			confirmColor: useConfigStore().themeColor['--store-bg-one'],
			success: (res) => {
				if (res.confirm) {
					deleteOrder(data.order_id).then((res) => {
						uni.showToast({ title: res.msg || t('deleteSuccess'), icon: 'none' })
						redirect({ url: '/addon/home_service/pages/order/list' })
					}).catch((err) => {
						uni.showToast({ title: err.msg || t('deleteFailed'), icon: 'none' })
					})
				}
			}
		})
	}

	// 申请退款
	const refundApplyFn = (orderItemId) => {
		redirect({
			url: '/addon/home_service/pages/refund/apply',
			param: {
				order_id: detail.value.order_id,
				order_item_id: orderItemId
			}
		})
	}

	// 日期格式转换（月日时分）
	function dataTurnTime(timeStamp) {
		const time = new Date(timeStamp).getTime();
		if (time && time > 0) {
			const date = new Date(time)
			let m = date.getMonth() + 1;
			m = m < 10 ? '0' + m : m
			let d = date.getDate();
			d = d < 10 ? '0' + d : d
			let h = date.getHours();
			h = h < 10 ? '0' + h : h
			let minute = date.getMinutes();
			minute = minute < 10 ? '0' + minute : minute
			return m + '-' + d + ' ' + h + ':' + minute
		} else {
			return ''
		}
	}

	// 订单状态步骤条
	const current = ref(0)
	function getStatus() {
		if (detail.value?.order_status_info?.status === 'dispatch') {
			current.value = 0
		} else if (detail.value?.order_status_info?.status === 'wait_service') {
			current.value = 1
		} else if (detail.value?.order_status_info?.status === 'in_service') {
			current.value = 2
		} else if (detail.value?.order_status_info?.status === 'finish') {
			current.value = 3
		} else {
			current.value = 0
		}
	}

	// 跳转项目详情
	const toLink = (data : any) => {
		if (data.item_type === 'reservation' || data.item_type === 'buy') {
			redirect({
				url: `/addon/home_service/pages/goods/detail`,
				param: {
					sku_id: data.item_id
				}
			})
		}
	}

	// 联系技师
	const callPhoto = (tel) => {
		if (!tel) return
		uni.makePhoneCall({
			phoneNumber: tel
		})
	}

	// 查看服务项
	const showServiceFn = (data) => {
		redirect({
			url: '/addon/home_service/pages/master/task/show',
			param: {
				order_id: data.order_id,
				order_item_id: data.order_item_id,
				item_name: data.item_name,
				price: data.item_money,
				item_images: data.item_images
			}
		})
	}

	// 页面加载时获取订单ID并加载详情
	onLoad((option : any) => {
		orderId = option.order_id || 0
		getOrderDetailFu()
	})
	// onShow(()=>{
	// 	getOrderDetailFu()
	// })
	// 组件挂载时处理定时器
	onMounted(() => {
		// 如果页面初始化时订单已在服务中，启动定时器
		if (detail.value?.order_status === 'in_service') {
			controlTimer(true)
		}
	})

	// 组件卸载时清理定时器
	onUnmounted(() => {
		controlTimer(false)
	})
	
	/**
	 * 1. 将时间字符串转为时间戳（支持毫秒级/秒级）
	 * @param {string} timeStr - 目标时间字符串（格式：YYYY-MM-DD HH:mm:ss）
	 * @param {boolean} isSecond - 是否返回秒级时间戳（默认 false，返回毫秒级）
	 * @returns {number|null} 时间戳（转换失败返回 null）
	 */
	function timeStrToTimestamp(timeStr, isSecond = false) {
	  // 生成 Date 对象（解析 "YYYY-MM-DD HH:mm:ss" 格式）
	  const targetDate = new Date(timeStr);
	  
	  // 校验日期是否有效（避免 Invalid Date 情况）
	  if (isNaN(targetDate.getTime())) {
	    console.error("时间格式错误，请使用 'YYYY-MM-DD HH:mm:ss' 格式");
	    return null;
	  }
	  
	  // 返回毫秒级或秒级时间戳
	  return isSecond ? Math.floor(targetDate.getTime() / 1000) : targetDate.getTime();
	}
	
	/**
	 * 2. 计算目标时间到现在已过去的时间（返回易读格式）
	 * @param {string} timeStr - 目标时间字符串（格式：YYYY-MM-DD HH:mm:ss）
	 * @returns {string} 已过去时间（如 "1天2小时3分钟4秒"、"目标时间尚未到达"）
	 */
	function calcPassedTime(timeStr) {
	  // 1. 获取目标时间戳（毫秒级）和当前时间戳
	  const targetTimestamp = timeStrToTimestamp(timeStr);
	  const nowTimestamp = new Date().getTime();
	  
	  // 校验时间戳是否有效
	  if (targetTimestamp === null) return "时间解析失败";
	  
	  // 2. 计算时间差（毫秒）
	  const timeDiff = nowTimestamp - targetTimestamp;
	  
	  // 3. 处理目标时间在未来的情况
	  if (timeDiff < 0) {
	    // 计算未来还需多久（可选逻辑，按需保留）
	    const futureDiff = Math.abs(timeDiff);
	    const futureStr = formatTimeDiff(futureDiff);
	    return `目标时间尚未到达，还需 ${futureStr}`;
	  }
	  
	  // 4. 处理时间差为 0 的情况
	  if (timeDiff === 0) return "刚刚";
	  
	  // 5. 格式化时间差（转为天、时、分、秒）
	  return `${formatTimeDiff(timeDiff)}前发布`;
	}
	
	/**
	 * 辅助函数：将毫秒级时间差转为 "天时分秒" 格式
	 * @param {number} timeDiff - 时间差（毫秒）
	 * @returns {string} 格式化后的时间差
	 */
	function formatTimeDiff(timeDiff) {
	  // 定义时间单位（毫秒换算）
	  const second = 1000;       // 1秒 = 1000毫秒
	  const minute = second * 60; // 1分钟 = 60秒
	  const hour = minute * 60;   // 1小时 = 60分钟
	  const day = hour * 24;      // 1天 = 24小时
	  
	  // 计算各单位的数值
	  const days = Math.floor(timeDiff / day);
	  const hours = Math.floor((timeDiff % day) / hour);
	  const minutes = Math.floor((timeDiff % hour) / minute);
	  const seconds = Math.floor((timeDiff % minute) / second);
	  
	  // 拼接结果（只保留非 0 的单位，避免 "0天0小时3分钟" 这类冗余）
	  const parts = [];
	  if (days > 0) parts.push(`${days}天`);
	  if (hours > 0) parts.push(`${hours}小时`);
	  if (minutes > 0) parts.push(`${minutes}分钟`);
	  if (seconds > 0 || parts.length === 0) parts.push(`${seconds}秒`); // 若前面都是 0，至少显示秒
	  
	  return parts.join("");
	}
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
	.bg-linear {
		background: linear-gradient(360deg, #f8f8f8 0%, $u-primary 100%);
	}

	.task-steps :deep(.u-text) {
		justify-content: center !important;
	}

	.line-feed {
		word-wrap: break-word;
		word-break: break-all;
	}

	.friend-pay {
		&::after {
			content: '';
			display: block;
			width: 20rpx;
			height: 20rpx;
			background-color: #f2f2f2;
			position: absolute;
			right: 30rpx;
			top: 0;
			transform: translateY(-50%) rotate(45deg);
			border-radius: 4rpx;
		}
	}

	.border-style {
		border: 2rpx solid #4a6bff;
	}
	
	// 底部安全区域适配
	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
</style>