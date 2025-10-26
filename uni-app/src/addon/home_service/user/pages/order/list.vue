<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="false" />
	<!-- #endif -->
	<view class="fixed w-[100vw] z-index-9999 bg-[#f6f6f6]" :style="{top:topStyle + 'px'}">
		<view :style="themeColor()">
			<u-tabs :list="orderStateList" @change="orderStateFn" :current="orderStateIndex"
				:activeStyle="{ color: 'var(--primary-color)' }" lineColor="var(--primary-color)"></u-tabs>
		</view>
	</view>
	<view class="bg-[#f8f8f8] min-h-screen overflow-hidden" :style="themeColor()">
		<mescroll-body ref="mescrollRef" top="54px" @init="mescrollInit" :down="{ use: false }" @up="getOrderListFn">
			<template v-for="(item, index) in list" :key="item.order_id">
				<view class="mx-3 mb-3 bg-white p-3 rounded-lg">
					<view class="flex justify-between items-center text-sm text-gray-500">
						<view>预约时间：{{ item.reserve_service_time }}</view>
						<text :style="{color:(item.order_status_info.status == 'wait_pay' || item.order_status_info.status =='wait_check') ? '#ff0000' : ''}">{{ item.order_status_info.name }}</text>
					</view>
					<view class="bg-[#f6f6f6] p-[25rpx] rounded-lg mt-[10rpx]"
						v-if="item.order_status_info?.status == 'finish' && item.is_evaluate == 0">
						<view class="text-[26rpx] flex items-center">
							<u-icon name="checkmark-circle" class="mr-[5rpx]"></u-icon>
							<text>订单已完成 <text class="text-[var(--primary-color)]"
									@click="handleOrderAction(item,'action_order_review_share')">去评价</text></text>
						</view>
						<view class="flex justify-between text-[#999999] text-[26rpx] mt-[20rpx]">
							<view>
								已验收完成
							</view>
							<view>
							</view>
						</view>
					</view>
					<block v-if="item.order_status_info?.status == 'wait_service' ">
						<view class="bg-[#f6f6f6] p-[25rpx] rounded-lg mt-[10rpx]" v-if="item.take_photos">
							<view class="text-[26rpx] flex items-center">
								<u-icon name="camera-fill" class="mr-[5rpx]"></u-icon>
								<text>师傅已打卡 <text class="text-[var(--primary-color)]"></text></text>
							</view>
							<view class="flex justify-between text-[#999999] text-[26rpx] mt-[20rpx]">
								<view>
									师傅：{{item.technician?.real_name}}
								</view>
								<view>
									{{item.take_photos_time}}
								</view>
							</view>
						</view>
						<block v-else>
							<view class="bg-[#f6f6f6] p-[25rpx] rounded-lg mt-[10rpx]" v-if="item.technician?.real_name">
								<view class="text-[26rpx] flex items-center">
									<u-icon name="file-text" class="mr-[5rpx]"></u-icon>
									<text>师傅已接单 <text class="text-[var(--primary-color)]"></text></text>
								</view>
								<view class="flex justify-between text-[#999999] text-[26rpx] mt-[20rpx]">
									<view>
										师傅：{{item.technician?.real_name}}
									</view>
									<view>
										{{item.reserve_service_time}}
									</view>
								</view>
							</view>
						</block>
					</block>
					<view class="bg-[#f6f6f6] p-[25rpx] rounded-lg mt-[10rpx]"
						v-if="item.order_status_info?.status == 'wait_check'">
						<view class="text-[26rpx] flex items-center">
							<u-icon name="checkmark-circle" class="mr-[5rpx]"></u-icon>
							<text>服务完成，待验收 <text class="text-[var(--primary-color)]"></text></text>
						</view>
						<view class="flex justify-between text-[#999999] text-[26rpx] mt-[20rpx]">
							<view>
								师傅：{{item.technician?.real_name}}
							</view>
							<view>
							</view>
						</view>
					</view>
					<view class="bg-[#f6f6f6] p-[25rpx] rounded-lg mt-[10rpx]"
						v-if="item.order_status_info?.status == 'in_service' && item.add_item_list?.length">
						<view class="text-[26rpx] flex items-center">
							<u-icon name="tags-fill" class="mr-[5rpx]"></u-icon>
							<text>附加服务订单已提交 <text class="text-[var(--primary-color)]"></text></text>
						</view>
						<view class="flex justify-between text-[#999999] text-[26rpx] mt-[20rpx]">
							<view>
								师傅：{{item.technician?.real_name}}
							</view>
							<view>
							</view>
						</view>
					</view>
					<view>
						<view class="order-goods-item flex mt-[30rpx]" v-for="(goodsItem, goodsIndex) in item.item"
							:key="goodsIndex" @click="toDetail(item)">
							<view class="w-[160rpx] h-[160rpx] flex-2">
								<up-image class="rounded-[10rpx] overflow-hidden" width="160rpx" height="160rpx"
									:src="img(goodsItem.item_image_thumb_small ? goodsItem.item_image_thumb_small : '')"
									model="aspectFill" shape="radius" radius="16rpx">
									<template #error>
										<u-icon name="photo" color="#999" size="50"></u-icon>
									</template>
								</up-image>
							</view>
							<view class="ml-[20rpx] flex flex-1 flex-col justify-between">
								<view class="flex justify-between items-center">
									<text
										class="text-[28rpx] text-item  leading-[40rpx] max-h-[80rpx] w-[360rpx] multi-hidden">{{ goodsItem.item_name }}</text>
									<text class="text-right text-[24rpx]">x{{ goodsItem.num }}</text>
								</view>
								<view class="text-[#999999] text-[24rpx]">{{goodsItem.sku_name}}</view>
								<!-- <view class="text-[var(--price-text-color)] text-[28rpx] font-bold" v-if="item.order_status != 'wait_pay'">
									<text class="text-[22rpx] leading-[28rpx] text-[#646464]">{{ t('payMoney') }}</text>
									<text class="text-[20rpx] price-font">￥</text>
									<text class="text-[40rpx] price-font">{{ Number(( item.total_pay_money)).toFixed(0) }}</text>
									<text
										class="price-font text-[22rpx]">.{{ Number(( item.total_pay_money)).toFixed(2).split('.')[1] }}</text>
									<text class="text-[#999]" v-if="item.unit">/{{ item.unit }}</text>
								</view> -->
								<view class="text-[28rpx]">
									<text class="text-[22rpx] leading-[28rpx] ">订单金额：</text>
									<text class="text-[20rpx] price-font text-[var(--price-text-color)]  font-bold">￥</text>
									 
									<text class="text-[40rpx] price-font text-[var(--price-text-color)]  font-bold">{{ Number(( item.order_money)).toString().split('.')[0] }}</text>
									<text
										class="price-font text-[22rpx] text-[var(--price-text-color)]  font-bold">.{{ Number(( item.order_money)).toFixed(2).split('.')[1] }}</text>
									<text class="text-[#999]" v-if="item.unit">/{{ item.unit }}</text>
								</view>
							</view>
						</view>
					</view>
					<view class="flex justify-end mt-3">
						<view 
						class=" ml-[20rpx] text-[24rpx] border-1 border-solid border-[#cccccc] p-[25rpx] flex items-center leading-1 rounded-[10rpx]"
							size="small" @click="handleOrderAction(item, btnItem.key)"
							v-for="(btnItem, btnIndex) in item.order_status_info.action"
							:style="{borderColor:btnItem.color,color:btnItem.color}" :key="btnIndex">{{btnItem.name}}</view>
					</view>
				</view>
			</template>
			<mescroll-empty :option="{'icon': img('static/resource/images/empty.png'),'tip': t('nothingMore')}"
				v-if="!list.length && !loading"></mescroll-empty>
		</mescroll-body>

		<order-popup :show="popupState.visible" :order="popupState.order"
			:action-key="popupState.actionKey" @close="closePopup()"
			@confirm="handlePopupConfirm"></order-popup>
		<loading-page :loading="loading"></loading-page>
		<tabbar />
		<pay ref="payRef"></pay>
	</view>
</template>

<script setup lang="ts">
	import { ref ,computed } from 'vue'
	import { img, redirect,getToken  } from '@/utils/common'
	import { getOrderStatus, getOrderList, cancelOrder, deleteOrder,getRefundNo } from '@/addon/home_service/user/api/order'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { onLoad, onPageScroll, onReachBottom, onShow } from '@dcloudio/uni-app'
	import OrderMethods from '@/addon/home_service/user/pages/order/js/orderMethods';
	import orderPopup from '@/addon/home_service/user/components/orderPopup/orderPopup.vue';
	import useSystemStore from '@/stores/system';
	import { t } from '@/locale'
	import useConfigStore from "@/stores/config";
	import { topTabar } from '@/utils/topTabbar';
import { popupState, closePopup, confirmPopup } from '@/addon/home_service/user/pages/order/js/popupStatus'
	// 系统状态管理
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '订单列表', topStatusBar: { textColor: '#333' } })
	const systemStore = useSystemStore()
	
	const menuButtonInfo = ref({});
	menuButtonInfo.value = systemStore.menuButtonInfo
	// 计算 top 样式：区分 H5 和 小程序
	const topStyle = computed(() => {
	  let top = 0;
	  // 微信小程序：按胶囊信息计算导航栏总高度，作为 top 值
	  // #ifdef MP-WEIXIN
	  // 胶囊高度（默认 32px） + 胶囊顶部到状态栏的距离（默认 10px）
	  top = (menuButtonInfo.value.height || 32) + (menuButtonInfo.value.top || 10);
	  // #endif
	
	  // H5：固定 top 值（根据设计稿调整，这里默认 50px）
	  // #ifdef H5
	  top = 0;
	  // #endif
	
	  // 返回 style 对象：top 单位为 px（适配所有端）
	  return top
	});
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	const list = ref<Array<Object>>([]);
	const loading = ref<boolean>(true);
	const statusLoading = ref<boolean>(false);
	const orderState = ref('')
	const orderStateList = ref([]);
	// 弹窗确认回调
	const handlePopupConfirm = (popupData ?: any) => {
		confirmPopup(popupData);
	};
	onLoad((option) => {
		orderState.value = option.order_status || "";
		getOrderStatusFn();
	
		uni.setNavigationBarTitle({
			title: '订单列表'
		});
	});
	onShow(() => {
		if (getMescroll()) {
			getMescroll().resetUpScroll()
		}
		uni.setNavigationBarTitle({
			title: '订单列表'
		});
	})
	const orderStateIndex = ref(0)
	// 获取订单状态
	const getOrderStatusFn = () => {
		statusLoading.value = false;
		orderStateList.value = [];
		let obj = { name: '全部', status: '' };
		orderStateList.value.push(obj);

		getOrderStatus().then((res) => {
			Object.values(res.data).forEach((item, index) => {
				orderStateList.value.push(item);
			});
			orderStateList.value.forEach((item, index) => {
				if (orderState.value == item.status) {
					orderStateIndex.value = index
				}
			})
			statusLoading.value = true;
		}).catch(() => {
			statusLoading.value = true;
		})
	}
	// 商品价格
	let goodsPrice = (data : any) => {
		let price = '0.00'
		if (getToken()) {
			price = data.member_price || '0.00' // 会员价
		} else {
			price = data.price || '0.00'
		}
		return parseFloat(price).toFixed(2)
	}
	// 处理订单按钮点击
	const handleOrderAction = (data : any, key : string) => {
		if(key == 'action_refund'){
				getRefundNo(data.order_id).then((res : any) => {
					if(res.data.status == "wait_refund" || res.data.status =='refund_refuse' ){
						redirect({ url: '/addon/home_service/user/pages/order/refund/detail', param: { refund_no: res.data.refund_id } })
					}else{
						redirect({ url: '/addon/home_service/user/pages/order/refund/apply', param: { order_id: data.order_id } })
					}
				})
				return
		}
		if (key == 'action_item_pay' || key == 'action_pay') {
			if (key == 'action_item_pay') {
				payRef.value?.open('home_service_item', data.wait_pay_batch_id, `/addon/home_service/user/pages/order/detail?order_id=${data.order_id}`);
			} else {
				payRef.value?.open(data.order_type, data.order_id, `/addon/home_service/user/pages/order/detail?order_id=${data.order_id}`);
			}
		} else {
			OrderMethods.orderClickFunction(
				data,
				key,
				() => calcOrderList(), // 刷新列表的回调
			);
		}
	};
	const calcOrderList = () => {
		getMescroll().resetUpScroll()
	}
	// 切换状态
	const orderStateFn = (e : any) => {
		orderState.value = e.status
		list.value = [];
		getMescroll().resetUpScroll();
	};
	// 订单列表
	const getOrderListFn = (mescroll) => {
		loading.value = true;
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
			order_status: orderState.value
		};

		getOrderList(data).then((res) => {
			let newArr = (res.data.data as Array<Object>);
			//设置列表数据
			if (mescroll.num == 1) {
				list.value = []; //如果是第一页需手动制空列表
			}
			list.value = list.value.concat(newArr);
			mescroll.endSuccess(newArr.length);
			loading.value = false;
		}).catch(() => {
			loading.value = false;
			mescroll.endErr(); // 请求失败, 结束加载
		})
	}

	const toDetail = (res) => {
		redirect({ url: '/addon/home_service/user/pages/order/detail', param: { order_id: res.order_id } })
	}

	// 支付
	const payRef = ref(null)
	// 删除订单
	const deleteFn = (data : any) => {
		uni.showModal({
			title: '提示',
			content: '您确定要删除该订单吗？',
			confirmColor: useConfigStore().themeColor['--primary-color'],
			success: res => {
				if (res.confirm) {
					deleteOrder(data.order_id).then((res) => {
						getMescroll().resetUpScroll()
					}).catch(() => {
						getMescroll().resetUpScroll()
					})
				}
			}
		})

	}
</script>

<style lang="scss" scoped>
	.class-select {
		position: relative;
		font-weight: bold;

		&::after {
			content: "";
			position: absolute;
			bottom: 0;
			height: 6rpx;
			background-color: $u-primary;
			width: 90%;
			left: 50%;
			transform: translateX(-50%);
		}
	}

	:deep(.u-tabbar__placeholder) {
		display: none !important;
	}
	:deep(.mescroll-empty) {
		margin-top: 0rpx !important;
	}
	/* #ifdef H5 */
	.u-sticky {
		top: 0 !important;
	}
	/* #endif */
</style>