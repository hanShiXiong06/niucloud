<template>
	<view class="bg-gray-50 min-h-screen" :style="themeColor()">

		<mescroll-body ref="mescrollRef" top="" @init="mescrollInit" @down="downCallback" @up="getOrderListFn">
			<view class="z-10 backdrop-blur-sm bg-white/100" v-if="statusLoading">
				<scroll-view scroll-x="true" class="scroll-Y">
					<view class="flex whitespace-nowrap px-3 py-3">
						<view v-for="(item, index) in orderStateList" :class="[
							'relative px-1 py-2 mx-1.5 transition-all duration-300 text-sm font-medium tab-item !bg-gray-0',
							orderState === item.status.toString()
								? 'text-[#0057FE] active'
								: 'text-[#666666] hover:bg-gray-0'
						]" @click="orderStateFn(item.status)">
							{{ item.name }}
						</view>
					</view>
				</scroll-view>
			</view>
			<view class="tk-card fl items-center">
				<up-input prefixIcon="search" prefixIconStyle="font-size: 22px;color: #909399" clearable
					v-model="keyword" placeholder="订单号/快递号/手机号/姓名" @change="reload()"></up-input>
			</view>
			<view v-if="list.length > 0" class="space-y-3 p-2" v-for="(item, index) in list" :key="index">
				<view
					class="bg-[#EEF4FF] flex items-center justify-between rounded-[16rpx] shadow-sm p-3 transition-all duration-300 hover:shadow-md">
					<view class="flex items-center space-x-2">
						<image :src="img(item.orderInfo?.delivery_arry?.logo)" mode="aspectFill"
							class="w-5 h-5 rounded-full" />
						<view class="text-[24rpx]">{{ item.orderInfo?.delivery_arry?.name }}</view>
					</view>
					<view class="text-[#828282] text-[24rpx]">
						下单时间:{{ item.create_time }}
					</view>
				</view>
				<view
					class="bg-white rounded-[16rpx] shadow-sm p-3 transition-all duration-300 hover:shadow-md !mt-[-12rpx]">
					<!-- 订单头部 -->
					<view class="flex justify-between items-center mb-4">
						<view class="space-y-2">
							<view class="text-[#828282] text-[24rpx]">
								订单号:{{ item.order_id }}
							</view>
							<view v-if="item.orderInfo.delivery_id" class="flex items-center cursor-pointer group"
								@click="copy(item.orderInfo.delivery_id)">
								<text class="text-[#0057FE] mr-2 truncate w-[200px]">运单号: {{ item.orderInfo.delivery_id
								}}</text>
								<up-icon class="border rounded p-1 opacity-0 group-hover:opacity-100 transition-opacity"
									name="cut" color="#0057FE" size="14">
								</up-icon>
							</view>
						</view>
						<view class="text-red text-[24rpx]" v-if="item.order_discount_money > 0">优惠券:-{{
							item.order_discount_money }}</view>
						<view class="text-[#0057FE] text-[28rpx] font-550">{{ (item.order_money -
							item.order_discount_money).toFixed(2)
						}}元</view>
					</view>

					<!-- 寄收地址区域 -->

					<view class="flex flex-col items-center justify-center min-h-full w-full">
						<view class="flex items-center justify-center w-full max-w-2xl px-4">
							<!-- 起点信息 -->
							<view class="flex flex-col items-center w-[40%]">
								<text class="font-bold text-[32rpx]">
									{{ JSON.parse(item.orderInfo.start_address).address.split('-')[0] }}
								</text>
								<text class="text-[#828282] text-xs mt-2 tk-sltext">
									{{ JSON.parse(item.orderInfo.start_address).name }}
								</text>
							</view>

							<!-- 中间状态部分 -->
							<view class="flex flex-col items-center px-8  w-[20%]">
								<image :src="img('addon/tk_jhkd/icon/orderline.png')" mode="aspectFill"
									class="w-20 h-2 rounded-full" />
								<view :class="[
									'text-sm font-medium py-1.5 rounded-full transition-colors ',
									item.order_status_data.name === '已关闭'
										? 'text-red-600'
										: 'text-blue-600'
								]">
									{{ item.order_status_data.name }}
								</view>
							</view>

							<!-- 终点信息 -->
							<view class="flex flex-col items-center  w-[40%]">
								<text class="font-bold text-[32rpx]">
									{{ JSON.parse(item.orderInfo.end_address).address.split('-')[0] }}
								</text>
								<text class="text-[#828282] text-xs mt-2 tk-sltext">
									{{ JSON.parse(item.orderInfo.end_address).name }}
								</text>
							</view>
						</view>
					</view>

					<!-- 超重信息 -->
					<view
						v-if="item.addorderInfo && item.deliveryRealInfo.fee_weight > 0 && item.addorderInfo.order_status == 0"
						class="mt-4 bg-blue-50 rounded-xl p-2 space-y-2">
						<view class="space-y-2">
							<view v-if="item.deliveryRealInfo.fee_weight > item.orderInfo.weight"
								class="text-sm text-blue-700">
								超重: {{ Math.ceil(item.deliveryRealInfo.fee_weight - item.orderInfo.weight) }}kg;
								￥{{ Math.ceil(item.deliveryRealInfo.fee_weight - item.orderInfo.weight) *
									(item.orderInfo.price_rule.add ?? 3) }}
							</view>
							<template v-if="item.deliveryRealInfo.fee_blockList">
								<view v-for="(item1, index1) in item.deliveryRealInfo.fee_blockList" :key="index1"
									class="text-sm text-blue-700">
									{{ item1.name }}: ￥{{ item1.fee }}
								</view>
							</template>
						</view>
						<view class="text-right">
							<text class="text-red-600 font-bold text-lg">需补差价: ￥{{ item.addorderInfo.order_money
							}}</text>
						</view>
					</view>

					<view v-else-if="item.addorderInfo && item.addorderInfo.order_status == 1"
						class="mt-4 bg-green-50 rounded-xl p-4">
						<text class="text-green-700 font-medium">已补差价：￥{{ item.addorderInfo.order_money }}</text>
					</view>
					<view class="line-box mt-2 mb-2"></view>
					<!-- 操作按钮 -->
					<view class="flex justify-end items-center pt-2 border-t border-gray-100">
						<view class="flex space-x-2.5 ml-2">
							<button
								class="px-4 py-2 flex items-center justify-center text-[#333333] bg-[#F2F2F2] rounded-[29rpx] text-[28rpx]"
								@click="goto('/addon/tk_jhkd/pages/orderdetail?id=' + item.id)">
								查看详情
							</button>
							<button v-if="item.addorderInfo && item.addorderInfo.order_status == 0"
								class="px-4 py-2 flex items-center justify-center text-sm bg-primary text-white rounded-[29rpx] text-[28rpx]"
								@click="payAdd(item.addorderInfo.id)">
								补差价
							</button>
							<button v-for="(item1, index1) in item.order_status_arr?.member_action" :key="index1"
								:class="[
									'px-4 py-2 flex items-center justify-center rounded-[29rpx] text-[28rpx]',
									item1.class === 'gopay' ? 'actcolor' : ''
								]" @click="clickbut(item1.class, item)">
								{{ item1.name }}
							</button>
						</view>
					</view>
				</view>
			</view>

			<mescroll-empty :option="{ icon: img('static/resource/images/empty.png') }" v-if="!list.length && loading">
			</mescroll-empty>
		</mescroll-body>
		<up-back-top :scroll-top="scrollTop" top="875" bottom="120" right="20"></up-back-top>
		<pay ref="payRef" @close="payLoading = false"></pay>
	</view>

	<up-modal :show="orders.show" @confirm="confirm(item1.class, item)" ref="uModal" @cancel="orders.show = false"
		:showCancelButton="true" :content="orders.msg" :asyncClose="true">
	</up-modal>
	<view class="fixed-tip bg-[#fef6e4] " :style="{ bottom: `${bottomDistance}px` }">
		<view class="p-4 text-center !text-[#7D563C] !text-[24rpx]">注意：下单1分钟后才能取消订单哦</view>
	</view>
	<tabbar addon="tk_jhkd" />

</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { t } from "@/locale";
import { img, redirect, copy } from "@/utils/common";
import {
	getOrderList,
	getOrderStatus,
	applyRefund,
	deleteOrder,
	closeOrder,
} from "@/addon/tk_jhkd/api/order";
import MescrollBody from "@/components/mescroll/mescroll-body/mescroll-body.vue";
import MescrollEmpty from "@/components/mescroll/mescroll-empty/mescroll-empty.vue";
import useMescroll from "@/components/mescroll/hooks/useMescroll";
import { onLoad, onPageScroll, onReachBottom } from "@dcloudio/uni-app";
import { goto } from "@/addon/tk_jhkd/utils/ts/goto";
import { confirm } from "@/addon/tk_jhkd/utils/ts/alert";
// import { configStore } from "@/stores/config";
// const configStore = useConfigStore()
// const themeColor = computed(() => configStore.getThemeColor)
// console.log(themeColor.value)
const bottomDistance = ref(0)
const tipText = ref('这是一条固定在底部导航栏上方的提示信息')
const keyword = ref('')
const reload = () => {
	getMescroll().resetUpScroll();
}
// 计算底部距离
const calculateBottomDistance = () => {
	// #ifdef H5
	bottomDistance.value = 50 // H5端底部导航栏高度
	// #endif

	// #ifdef MP-WEIXIN
	const systemInfo = uni.getSystemInfoSync()
	// 计算实际底部安全距离加上导航栏高度
	bottomDistance.value = systemInfo.safeAreaInsets?.bottom + 56
	// #endif

	// #ifdef APP-PLUS
	const systemInfo = uni.getSystemInfoSync()
	bottomDistance.value = systemInfo.safeAreaInsets?.bottom + 50
	// #endif
}

onMounted(() => {
	calculateBottomDistance()
})
// import { checkAddPayEvent } from "@/addon/tk_jhkd/utils/ts/common"
// checkAddPayEvent()
const payAdd = (e) => {
	payLoading.value = true;
	payRef.value?.open(
		"jhkdOrderAddPay",
		e,
		"/addon/tk_jhkd/pages/orderlist"
	);
};
interface Order {
	id: string;
	order_id: string;
	orderInfo: {
		delivery_id: string;
		start_address: string;
		end_address: string;
		goods: string;
		weight: number;
		long: number;
		width: number;
		height: number;
	};
	order_status_data: {
		name: string;
	};
	order_status_arr: {
		member_action: Array<{ class: string; name: string }>[];
	};
}

const payRef = ref(null);
const payLoading = ref(false);
const orders = ref<{ show: boolean; msg: string }>({
	show: false,
	msg: "",
});
const { mescrollInit, downCallback, getMescroll } = useMescroll(
	onPageScroll,
	onReachBottom
);
const list = ref<Order[]>([]);
const loading = ref<boolean>(false);
const statusLoading = ref<boolean>(false);
const orderState = ref<string>("");
const orderStateList = ref<Array<{ name: string; status: string }>>([]);
const scrollTop = ref(0)

onLoad((option: { status: string }) => {
	orderState.value = option.status || "";
	getOrderStatusFn();
});

const clickbut = (action: string, item: Order) => {
	if (action === "gopay") {
		gopay(item);
	} else if (action === "del") {
		confirm("确定要删除订单吗？", del, item);
	} else if (action === "refund") {
		confirm("确定要申请退款吗？", refund, item);
	} else if (action === "close") {
		confirm("确定要关闭订单吗？", close, item);
	}
};
//支付
const gopay = (item: any) => {
	payLoading.value = true;
	payRef.value?.open(
		'jhkdOrderPay',
		item.id,
		"/addon/tk_jhkd/pages/orderlist?status=" + orderState.value
	);
};
const del = async (item: Order) => {
	await deleteOrder(item.id);
	getMescroll().resetUpScroll();
};
const close = async (item: Order) => {
	await closeOrder(item.id);
	getMescroll().resetUpScroll();
};
const refund = async (item: Order) => {
	const params = {
		id: item.id,
		close_reason: "用户主动退款",
	};
	await applyRefund(params);
	getMescroll().resetUpScroll();
};

const getOrderListFn = (mescroll: any) => {
	loading.value = false;
	const data = {
		page: mescroll.num,
		limit: mescroll.size,
		order_status: orderState.value,
		keyword: keyword.value
	};
	getOrderList(data)
		.then((res) => {
			const newArr = res.data.data as Order[];
			if (mescroll.num === 1) {
				list.value = [];
			}
			list.value = list.value.concat(newArr);
			mescroll.endSuccess(newArr.length);
			loading.value = true;
		})
		.catch(() => {
			loading.value = true;
			mescroll.endErr();
		});
};

const getOrderStatusFn = () => {
	statusLoading.value = false;
	orderStateList.value = [];
	const obj = { name: "全部", status: "" };
	orderStateList.value.push(obj);
	getOrderStatus()
		.then((res) => {
			Object.values(res.data).forEach((item) => {
				orderStateList.value.push(item);
			});
			statusLoading.value = true;
		})
		.catch(() => {
			statusLoading.value = true;
		});
};

const orderStateFn = (status: string) => {
	orderState.value = status.toString();
	list.value = [];
	getMescroll().resetUpScroll();
};

onPageScroll((e) => {
	scrollTop.value = e.scrollTop
})
</script>

<style lang="scss" scoped>
@import "@/addon/tk_jhkd/utils/styles/common.scss";

.actcolor {
	background: #0057FE !important;
	color: #ffffff !important;
}

.fixed-tip {
	@apply fixed left-0 right-0 z-50;
}

.line-box {
	background-color: #F2F2F2;
	height: 1rpx;
	width: 100%;
}

.tab-item {
	position: relative;

	&.active::after {
		content: '';
		position: absolute;
		left: 0;
		bottom: 0;
		width: 50%;
		height: 4px;
		background-color: #0057FE;
		border-radius: 6rpx;
		transform: translateX(50%); // 添加这行来实现居中
	}
}

.line-box1 {
	background-color: #e3e3e3;
	height: 2rpx;
	width: 100%;
	margin-top: 12rpx;
	margin-bottom: 12rpx;
}


.qu-tag {
	background: #fba92d;
	color: #ffffff;
	height: 28rpx;
	line-height: 28rpx;
	text-align: center;
	width: 32rpx;
	display: inline-block;
}

.song-tag {
	background: #4541c7;
	color: #ffffff;
	height: 28rpx;
	line-height: 28rpx;
	text-align: center;
	width: 32rpx;
	display: inline-block;
}

.tk-card {
	padding: 20rpx;
	margin-top: 24rpx;
	background: #fff;
	box-shadow: 0rpx 1rpx 4rpx rgba(0, 0, 0, 0.1);
	margin-bottom: 10rpx;
}

.tk-tag1 {
	background: #4541c7;
	color: #ffffff;
	border: 1rpx solid #4541c7;
	font-size: 22rpx;
	padding: 5rpx 20rpx;
	border-radius: 10rpx;
}

.tk-tag {
	color: #4541c7;
	border: 1rpx solid #4541c7;
	font-size: 22rpx;
	padding: 5rpx 20rpx;
	border-radius: 10rpx;
}
</style>