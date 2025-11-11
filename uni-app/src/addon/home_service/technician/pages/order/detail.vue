<template>
	<view :style="themeColor()" class="pb-[100rpx]">
		<template v-if="!loading">
			<!-- #ifdef MP-WEIXIN || APP-PLUS -->
			<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
			<!-- #endif -->
			<view v-if="detail" class="bg-[#f7f7f7] min-h-screen overflow-hidden pt-[25rpx]">
				<view class="px-3 pt-5">
					<view class="text-[42rpx] flex items-baseline">
						<text class="font-bold">{{ t('order') }}{{ detail?.order_status_info?.name }}</text>
					</view>
					<view class="py-[20rpx]">
						<view class="text-[24rpx] text-[#999999] flex items-center mb-[10rpx]"
							v-if="detail?.order_status == 'wait_service'">
							<u-icon name="person-delete-fill" color="#999999"
								class="mr-[5rpx]"></u-icon>{{ t('orderNotStarted') }}
						</view>
						<view v-else>
							<view class="text-[24rpx] text-[#999999] flex items-center mb-[15rpx]" v-if="detail?.take_photos && detail?.take_photos.length">
								<u-icon name="car" color="#999999"
									class="mr-[5rpx]"></u-icon>{{ t('servicePersonDeparted') }}
							</view>
							<view class="text-[24rpx] text-[#999999] flex items-center justify-between mb-[10rpx]"
								v-if="detail?.take_photos && detail?.take_photos.length">
								<view class="flex items-center">
									<u-icon name="camera" color="#999999"
										class="mr-[5rpx]"></u-icon>{{ t('servicePersonCheckedIn') }}
								</view>
								<view class="border-style text-[#4a83ff] px-[15rpx] py-[5rpx] rounded-5rpx"
									@click="showImage = true">
									{{ t('viewCheckInPhotos') }}
								</view>
							</view>
							<view class="text-[24rpx] text-[#999999] flex items-center justify-between mb-[10rpx]"
								v-if="detail?.check_photos && detail?.check_photos.length">
								<view class="flex items-center">
									<u-icon name="photo" color="#999999" class="mr-[5rpx]"></u-icon>服务已完成，完成拍照
								</view>
								<view class="border-style text-[#4a83ff] px-[15rpx] py-[5rpx] rounded-5rpx"
									@click="openCheckPhotos">
									查看完成图片
								</view>
							</view>
						</view>
					</view>
				</view>

				<!-- 实时计时显示区域 -->
				<view class="text-[26rpx] mb-[30rpx] mt-[15rpx]" v-if="detail?.order_status == 'in_service'">
					<view class="text-[#fff] flex justify-center">
						<view v-for="(item,index) in formattedElapsedTime.split(':')" :key="index"
							class="flex justify-enter items-center">
							<view
								class="bg-[#FF000D] text-[45rpx] font-bold mx-[15rpx] min-w-[60rpx] text-center flex items-center justify-center h-[60rpx] px-[14rpx] py-[8rpx] rounded-lg">
								{{item}}
							</view>
							<view v-if="index != formattedElapsedTime.split(':').length - 1"
								class="text-[#FF000D] text-[45rpx] font-bold">:</view>
						</view>
					</view>
					<view class="flex justify-center mt-[34rpx] text-[26rpx] text-[#999999]">
						{{ t('totalServiceDuration') }}
					</view>
				</view>

				<view class="bg-[#fff] my-[30rpx] mx-[30rpx] rounded-[16rpx] p-[30rpx] mt-[0rpx]">
					<view class="flex justify-between mb-[10rpx]">
						<view class="flex items-center">
							<view class="flex items-center bg-[#00CE2D] rounded-l-[50rpx] rounded-r-[6rpx] mr-[20rpx]"
								v-if="detail.item[0]?.buy_type == 'reservation'">
								<image :src="img('/addon/home_service/technician/white-time.png')"
									class="block w-[20rpx] h-[20rpx] p-[5rpx] bg-[#04E24A] rounded-[50%] mr-[5rpx]"
									mode="aspectFit"></image>
								<view class="text-[20rpx] text-[#fff]  pr-[8rpx]">
									{{ t('pre') }}
								</view>
							</view>
							<view class="text-[26rpx] text-[#666666] flex items-center pt-[2rpx]">
								{{detail?.reserve_service_time}}
							</view>
						</view>
						<text class="text-[26rpx] font-bold">{{detail?.order_status_info.name}}</text>
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
								<view class="flex justify-between items-center">
									<view>
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
									class="flex-1 flex items-center justify-end text-[26rpx] text-[var(--primary-color)] w-[100rpx]"
									@click.stop="openMapNavigation(detail)">
									{{t('Navigation')}} <text class="iconfont iconarrow-right text-[26rpx]"></text>
								</view>
							</view>
						</view>
					</view>
				</view>

				<!-- errand_items 渲染  hsx-->
				<view class="mt-[30rpx]" v-if="detail?.errand_items?.length > 0">
					<view class="bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-lg">
						<view class="flex justify-between">
							<view class="text-[30rpx] font-bold">跑腿订单</view>
						</view>
						<view class="flex flex-col mt-2" v-for="(item,index) in detail.errand_items" :key="index">
							<view class="flex  justify-between items-center">
								<view class="text-[#666666] text-[24rpx] leading-[35rpx]">{{item.sku_name}}  取件码: <up-tag size="mini" :text="item.pickup_code"></up-tag></view>
								<view class="text-[#666666] text-[24rpx] leading-[35rpx]"> ￥{{item.price}}</view>
							</view>
						</view>
					</view>
				</view>
				<!--  hsx -->
				<view class="mt-[30rpx]" v-else>
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
						<view v-if="detail.item && detail.item?.length"
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>{{ detail.item[0].item_name}}</view>
							<view class="price-font"><text
									class="!text-[24rpx]">￥</text>{{ detail?.item[0].item_money }}</view>
						</view>
						<view class="bg-[#F6F6F6] p-[30rpx] text-[24rpx] my-[24rpx] rounded-[10rpx]">
							{{ t('remarkPrefix') }}{{detail.member_message ||'暂无备注'}}
						</view>
						<view v-if="detail.discount_money && Number(detail.discount_money)"
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>{{ t('couponMoney') }}</view>
							<view class=" price-font"><text class="!text-[24rpx]">￥</text>{{ detail.discount_money }}
							</view>
						</view>
					</view>
				</view>

				<view class="mt-[30rpx]">
					<view class="bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-lg">
						<view class="flex justify-between">
							<view class="text-[30rpx] font-bold">{{ t('orderIncome') }}</view>
							<view class="flex justify-between items-end text-[26rpx]">
								<view
									class="text-[32rpx] font-bold leading-[35rpx]  text-[var(--price-text-color)] price-font">
									<text class="!text-[22rpx]">￥</text>{{ detail.technician_sum_commission }}</view>
							</view>
						</view>
						<view v-if="detail?.order_status == 'in_service'"
							@click="redirect({url:'/addon/home_service/technician/pages/order/additional',param:{order_id:orderId}})"
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>{{ t('additionalService') }}</view>
							<view
								class="text-[var(--price-text-color)] price-font text-[24rpx] text-[#999999] font-bold">
								<text class="iconfont iconarrow-right  text-[26rpx]"></text></view>
						</view>
						<view
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>{{ t('serviceFee') }}</view>
							<view class=" price-font"><text
									class="!text-[24rpx]">￥</text>{{ detail.technician_commission }}</view>
						</view>
						<view v-if="detail?.technician_additional_commission>0"
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>{{ t('additionalServiceFee') }}</view>
							<view class="price-font"><text class="!text-[24rpx]"
									v-if="detail.technician_additional_commission">￥</text>{{ detail.technician_additional_commission }}
							</view>
						</view>
						<view class="bg-[#F6F6F6] p-[25rpx] text-[24rpx] my-[24rpx] rounded-[10rpx]">
							<view>{{ t('serviceFeeDescription') }}</view>
							<view class="mt-[15rpx]" v-if="detail?.order_status == 'in_service'">
								{{ t('additionalServiceFeeDescription') }}
							</view>
						</view>
					</view>
				</view>

				<view class="mt-[30rpx]">
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

				<view class="h-[160rpx] w-full"></view>

				<view
					class="flex z-2 justify-between items-center bg-[#fff] fixed left-0 right-0 bottom-0 min-h-[100rpx] px-1 flex-wrap pb-ios">
					<view class="flex ml-[30rpx] w-[70rpx] flex-col justify-center items-center"
						@click="redirect({url:'/addon/home_service/technician/pages/index'})">
						<text class="nc-iconfont nc-icon-shouye-xiaolianV6xx text-[36rpx]"></text>
						<text class="text-xs mt-1">{{ t('index') }}</text>
					</view>
					<view class="flex justify-end mr-[30rpx]" v-if="detail.order_status_info">
						<view v-for="(btnItem, btnIndex) in detail.order_status_info.action" :key="btnIndex"
							class="inline-block text-[26rpx] leading-[56rpx] px-[30rpx] border-[3rpx] text-[#fff] bg-[var(--primary-color)] rounded-full ml-[10rpx]"
							@click="handleOrderAction(detail,btnItem.key)">{{ btnItem.name }}</view>
					</view>
				</view>
			</view>

			<view class="w-screen h-screen flex flex-col justify-center items-center" v-else>
				<u-empty :icon="img('static/resource/images/order_empty.png')" :text="t('orderInfoNotObtained')" />
			</view>

			<!-- 刷新按钮 -->
			<view
				class="fixed bottom-[calc(160rpx+env(safe-area-inset-bottom))] right-[30rpx] rounded-full bg-[#fff] w-[80rpx] h-[80rpx] flex flex-col items-center justify-center shadow-xl"
				@click="getOrderDetailFu">
				<text class="nc-iconfont nc-icon-shuaxinV6xx text-[36rpx]"></text>
				<text class="text-[22rpx] mt-[6rpx]">{{ t('refresh') }}</text>
			</view>
		</template>
		<u-popup :show="showImage" @close="showImage = false" @open="showImage = true" mode="center" zIndex="9"
			round="15">
			<view class="text-[32rpx] font-bold text-center py-[30rpx]">
				{{ t('checkInPhotos') }}
			</view>
			<view class="px-[30rpx] py-[30rpx] pb-[20rpx] !pb-[40rpx] grid grid-cols-4 gap-4 ">
				<view v-for="(imageUrl, imgIndex) in detail.take_photos" :key="imgIndex" class=" rounded-lg mb-[24rpx]">
					<u--image :src="img(imageUrl)" width="140rpx" height="140rpx" radius="10rpx"
						@click="imgListPreview(imageUrl, imgIndex)">
						<view slot="error" style="font-size: 24rpx;">{{ t('loadFailed') }}</view>
					</u--image>
				</view>
			</view>
		</u-popup>
		<u-popup :show="showCheckImage" @close="showCheckImage = false" @open="showCheckImage = true" mode="center"
			zIndex="9" round="15">
			<view class="text-[32rpx] font-bold text-center py-[30rpx]">
				完成照片
			</view>
			<view class="px-[30rpx] py-[30rpx] pb-[20rpx] grid grid-cols-4 gap-4 ">
				<view v-for="(imageUrl, imgIndex) in detail.check_photos" :key="imgIndex"
					class=" rounded-lg mb-[24rpx]">
					<u--image :src="img(imageUrl)" width="140rpx" height="140rpx" radius="10rpx"
						@click="imgListPreview(imageUrl, imgIndex)">
						<view slot="error" style="font-size: 24rpx;">{{ t('loadFailed') }}</view>
					</u--image>
				</view>
			</view>
		</u-popup>
		<order-popup :show="popupState.visible" :order="popupState.order" :action-key="popupState.actionKey"
			@close="closePopup()" @confirm="handlePopupConfirm"></order-popup>
		<pay ref="payRef"></pay>
		<loading-page :loading="loading"></loading-page>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed, onMounted, onUnmounted } from 'vue'
	import { onLoad, onShow } from '@dcloudio/uni-app'
	import { img, redirect, copy, timeStampTurnTime } from '@/utils/common'
	import { grabOrderDetail } from '@/addon/home_service/technician/api/order'
	import { t } from '@/locale'
	import OrderMethods from '@/addon/home_service/technician/pages/order/js/orderMethods';
	import orderPopup from '@/addon/home_service/technician/components/orderPopup/orderPopup.vue';
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '订单详情', topStatusBar: { textColor: '#333' } })
	import { popupState, closePopup, confirmPopup } from '@/addon/home_service/technician/pages/order/js/popupStatus'
	const showImage = ref(false)
	const showCheckImage = ref(false)
	// 处理订单按钮点击
	const handleOrderAction = (order : any, key : string) => {
		OrderMethods.orderClickFunction(
			order,
			key,
			() => getOrderDetailFu(), // 刷新列表的回调
		);
	};

	// 弹窗确认回调
	const handlePopupConfirm = (popupData ?: any) => {
		confirmPopup(popupData);
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
	const targetTime = ref<number>(0) // 目标时间戳（服务开始时间）

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
			? `${days}天: ${padZero(hours)}:${padZero(minutes)}:${padZero(seconds)}`
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
		grabOrderDetail(orderId).then((res) => {
			detail.value = res.data

			// 更新超时提示数据
			if (detail.value?.timeout_count !== undefined) {
				timeOutOrder.value.timeout_count = detail.value.timeout_count
			}
			if (detail.value?.about_to_timeout_count !== undefined) {
				timeOutOrder.value.about_to_timeout_count = detail.value.about_to_timeout_count
			}

			// 设置目标时间（服务开始时间）
			if (detail.value?.service_time) {
				targetTime.value = new Date(detail.value.service_time).getTime()
			} else {
				targetTime.value = new Date().getTime()
			}

			// 根据订单状态控制定时器
			const isInService = detail.value?.order_status === 'in_service'
			controlTimer(isInService)

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
			confirmColor: useConfigStore().themeColor['--primary-color'],
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
			title: '提示',
			content: '您确定要删除该订单吗？',
			confirmColor: useConfigStore().themeColor['--primary-color'],
			success: (res) => {
				if (res.confirm) {
					deleteOrder(data.order_id).then((res) => {
						uni.showToast({ title: res.msg || '删除成功', icon: 'none' })
						redirect({ url: '/addon/home_service/pages/order/list' })
					}).catch((err) => {
						uni.showToast({ title: err.msg || '删除失败', icon: 'none' })
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

	// 预览完成照片
	const openCheckPhotos = () => {
		showCheckImage.value = true
	}
</script>

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
</style>
<style lang="scss">
	@import '@/addon/home_service/technician/style/index.scss';
</style>