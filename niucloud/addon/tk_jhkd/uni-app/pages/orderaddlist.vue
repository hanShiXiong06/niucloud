<template>
	<view class="bg-[#f8f8f8] min-h-screen overflow-hidden" :style="themeColor()">
		<view class="z-10 mb-2" v-if="statusLoading">
			<scroll-view scroll-x="true" class="scroll-Y box-border px-[24rpx] bg-white">
				<view class="flex whitespace-nowrap justify-around">
					<view :class="[
						'text-sm leading-[90rpx]',
						{ 'class-select': orderState === item.status.toString() },
					]" @click="orderStateFn(item.status)" v-for="(item, index) in orderStateList">{{ item.name }}</view>
				</view>
			</scroll-view>
		</view>

		<mescroll-body ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getOrderListFn">
			<view v-if="list.length > 0" class="tk-card" v-for="(item, index) in list" key="index">
				<view class="text-xs font-weight" @click="
					goto('/addon/tk_jhkd/pages/orderdetail?id=' + item.orderInfo.id)
					">订单号:{{ item.order_id }}
				</view>
				<view class="fb flex justify-between">
					<view class="text-xs font-weight">

						<view v-if="item.orderInfo.delivery_id" class="flex items-center"
							@click="copy(item.orderInfo.delivery_id)">
							<text class="mr-2 text-primary text-xs mt-1">运单号:{{ item.orderInfo.delivery_id }}
							</text>
							<view>
								<up-icon class="border-1 rounded" name="cut" color="#4541c7" size="16"></up-icon>
							</view>
						</view>
					</view>


				</view>
				<view v-if="item.orderInfo" class="flex justify-center mt-3">
					<view class="flex items-center">
						<view class="mr-2">
							<text class="qu-tag text-sm p-2 rounded-xl">寄</text>
						</view>
						<view class="flex flex-col items-center">
							<view class="text-sm font-bold flex">
								<view class="">{{ item.orderInfo.start_address.address.split('-')[0] }}
								</view>
							</view>
							<view class="tk-sltext text-xs text-[#4b4b4b]">
								{{ item.orderInfo.start_address.name }}
							</view>

						</view>
					</view>
					<view class="flex items-center ml-4 mr-4">
						<view class="flex">

							<view>
								<up-icon name="more-dot-fill" color="#63625f" size="28"></up-icon>
							</view>
							<view>
								<up-icon name="more-dot-fill" color="#63625f" size="28"></up-icon>
							</view>
							<view>
								<up-icon name="arrow-right" color="#63625f" size="28"></up-icon>
							</view>
						</view>

					</view>
					<view class="flex items-center">
						<view class="mr-4">
							<text class="song-tag text-sm p-2 rounded-xl">收</text>
						</view>
						<view class="">

							<view class="flex flex-col items-center">
								<view class="text-sm font-bold flex">
									<view class="">{{ item.orderInfo.end_address.address.split('-')[0] }}
									</view>
								</view>
								<view class="tk-sltext text-xs text-[#4b4b4b]">
									{{ item.orderInfo.end_address.name }}
								</view>

							</view>
						</view>
					</view>
				</view>
				<view class="flex mt-4 items-center justify-between">
					<view class="text-xs text-slate-600">下单时间:{{ item.orderInfo.create_time }}</view>
				</view>
				<view class="line-box1"></view>


				<view v-if="item.deliveryRealInfo.fee_weight > 0 && item.order_status == 0">

					<view
						class="flex items-center mt-1 mb-2 flex text-xs font-bold p-2 text-[24rpx] bg-[#e5fffb] rounded-lg text-red bg-opacity-50">
						<view v-if="item.deliveryRealInfo.fee_weight > item.orderInfo.weight" class="">
							超重:
							{{ Math.ceil(item.deliveryRealInfo.fee_weight - item.orderInfo.weight) }}kg;￥{{
								Math.ceil(item.deliveryRealInfo.fee_weight - item.orderInfo.weight) *
								item.orderInfo.price_rule.add ?? 3 }};
						</view>
						<block v-if="item.deliveryRealInfo.fee_blockList"
							v-for="(item1, index1) in item.deliveryRealInfo.fee_blockList">
							<view class="">
								{{ item1.name }}:￥{{ item1.fee }}
							</view>
						</block>
					</view>
					<view class='flex justify-end mt-1 items-center'>
						<view class='font-bold text-red text-[28rpx]'>
							需补差价:￥{{ item.order_money }}</view>
					</view>

				</view>
				<view v-else>
					<view class="flex">

						<view v-if="item.order_status == 1"
							class="flex text-xs font-bold ml-2 p-2 text-[24rpx] bg-[#e5fffb] rounded-lg text-red bg-opacity-50">
							已补差价：￥
							{{ item.order_money }}
						</view>
					</view>
				</view>
				<view class="line-box1"></view>
				<view class="fb items-center">
					<view class="text-xs">{{ item.create_time }}</view>
					<view v-if="item.order_status == 0" class="tk-tag" @click="gopay(item)">立即支付</view>
					<view v-if="item.order_status == 1" class="tk-tag" @click="del(item.id)">删除订单</view>
				</view>
			</view>
			<mescroll-empty :option="{ icon: img('static/resource/images/empty.png') }"
				v-if="!list.length && loading"></mescroll-empty>
		</mescroll-body>
		<pay ref="payRef" @close="payLoading = false"></pay>
	</view>
	<tabbar addon="tk_jhkd" />
</template>

<script setup lang="ts">
import { ref } from "vue";
import { t } from "@/locale";
import { img, redirect } from "@/utils/common";
import {
	getOrderList,
	getOrderStatus,
	deleteOrder,
} from "@/addon/tk_jhkd/api/orderadd";
import MescrollBody from "@/components/mescroll/mescroll-body/mescroll-body.vue";
import MescrollEmpty from "@/components/mescroll/mescroll-empty/mescroll-empty.vue";
import useMescroll from "@/components/mescroll/hooks/useMescroll.js";
import { onLoad, onPageScroll, onReachBottom } from "@dcloudio/uni-app";
import { goto } from "@/addon/tk_jhkd/utils/ts/goto";
const payRef = ref(null);
const payLoading = ref(false);
const { mescrollInit, downCallback, getMescroll } = useMescroll(
	onPageScroll,
	onReachBottom
);
let list = ref<Array<Object>>([]);
let loading = ref<boolean>(false);
let statusLoading = ref<boolean>(false);
let orderState = ref("");
let orderStateList = ref([]);
const listData = ref([]);
onLoad((option) => {
	orderState.value = option.status || "0";
	getOrderStatusFn();
});

//支付
const gopay = (item) => {
	payLoading.value = true;
	payRef.value?.open(
		"jhkdOrderAddPay",
		item.id,
		"/addon/tk_jhkd/pages/orderaddlist"
	);
};
//删除
const del = async (id) => {
	await deleteOrder(id);
	getMescroll().resetUpScroll();
};

const getOrderListFn = (mescroll) => {
	loading.value = false;
	let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
		order_status: orderState.value,
	};
	console.log(orderState.value);
	getOrderList(data)
		.then((res) => {
			let newArr = res.data.data as Array<Object>;
			//设置列表数据
			if (mescroll.num == 1) {
				list.value = []; //如果是第一页需手动制空列表
			}
			list.value = list.value.concat(newArr);
			mescroll.endSuccess(newArr.length);
			loading.value = true;
		})
		.catch(() => {
			loading.value = true;
			mescroll.endErr(); // 请求失败, 结束加载
		});
};
const getOrderStatusFn = () => {
	statusLoading.value = false;
	orderStateList.value = [];
	getOrderStatus()
		.then((res) => {
			Object.values(res.data).forEach((item, index) => {
				orderStateList.value.push(item);
			});
			statusLoading.value = true;
		})
		.catch(() => {
			statusLoading.value = true;
		});
};
const orderStateFn = (status) => {
	orderState.value = status.toString();
	list.value = [];
	getMescroll().resetUpScroll();
};
</script>
<style lang="scss" scoped>
@import "@/addon/tk_jhkd/utils/styles/common.scss";

.line-box1 {
	background-color: #e3e3e3;
	height: 2rpx;
	width: 100%;
	margin-top: 12rpx;
	margin-bottom: 12rpx;
}

.class-select {
	position: relative;
	font-weight: bold;

	&::after {
		content: "";
		position: absolute;
		bottom: 0;
		height: 6rpx;
		background-color: #4541c7;
		width: 90%;
		left: 50%;
		transform: translateX(-50%);
	}
}

.detail-tag {
	background-color: aliceblue;
	padding: 4rpx 16rpx;
	font-size: 16rpx;
}

.qu-tag {
	background-color: #b9b9b9;
	color: #ffffff;
	padding: 4rpx 8rpx;
	border-radius: 16rpx;
}

.song-tag {
	background-color: #4451c2;
	color: #ffffff;
	padding: 4rpx 8rpx;
	border-radius: 16rpx;
}
</style>