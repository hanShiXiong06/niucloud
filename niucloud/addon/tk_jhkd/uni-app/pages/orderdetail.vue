<template>
	<view class="bg-gray-50 min-h-screen" v-if="form.orderInfo" :style="themeColor()">
		<view class="bg-white shadow-sm p-4">
			<view v-if="form.payInfo" class="flex items-center mb-4 justify-between">
				<view class="flex items-center">
					<view class="font-bold text-[36rpx]">{{ form.payInfo.status_name }}</view>
					<view class="font-bold text-[36rpx] text-[#FE0000] ml-2">{{ (form.order_money -
						form.order_discount_money).toFixed(2) }}元</view>
					<view v-if="form.order_discount_money > 0" class="text-[#FE0000] text-[28rpx] ml-2">
						优惠券:-{{ form.order_discount_money }}
					</view>
				</view>
				<view class="bg-primary-50 text-primary  rounded text-[28rpx]" v-if="form.order_status_arr">
					{{ form.order_status_arr.name }}
				</view>
			</view>
			<!-- 揽件员信息 -->
			<view class="flex justify-between items-center">
				<view class="w-[70%]">
					<view v-if="form.orderInfo && pickInfo && form.orderInfo.order_status == 1"
						class="border-b border-gray-100 pb-3 mb-3">
						<view class="flex items-center flex-wrap gap-2">
							<text v-if="pickInfo.courierName" class="text-gray-600 text-sm">
								揽件员：{{ pickInfo.courierName }}
							</text>
							<view class="flex items-center" v-if="pickInfo.courierPhone">
								<text class="text-gray-600 text-sm" @click="telPicker(pickInfo.courierPhone)">
									联系电话：{{ pickInfo.courierPhone }}
								</text>
								<up-icon name="phone" color="#4B5563" size="18"
									@click="telPicker(pickInfo.courierPhone)" class="ml-1" />
							</view>
						</view>
						<view v-if="pickInfo && pickInfo.pickUpCode" class="mt-2">
							<text class="text-gray-800 font-medium">取件码：{{ pickInfo.pickUpCode }}</text>
						</view>
					</view>
					<!-- 快递公司信息 -->
					<view class="flex items-center" v-show="form.orderInfo && form.orderInfo.delivery_arry">
						<image :src="img(form.orderInfo?.delivery_arry?.logo)" mode="aspectFill"
							class="w-10 h-10 rounded-full" />
						<view class="ml-2">
							<view class="text-[#333333] text-medium text-[30rpx] text-[#333333]">
								{{ form.orderInfo?.delivery_arry?.name }}
							</view>
							<view class="flex items-center">
								<view class="text-gray-500 text-xs mt-1" @click="copy(form.orderInfo?.delivery_id)">
									运单号:{{ form.orderInfo?.delivery_id || '等待快递公司返回' }}
								</view>
								<view class="ml-2" v-if="form.orderInfo?.delivery_id">
									<view class="text-primary text-xs mt-1" @click="copy(form.orderInfo?.delivery_id)">
										复制
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>
				<view>
					<button @click="expressShowEvent()"
						class="px-4 py-2 flex items-center justify-center text-[#333333] bg-[#F2F2F2] rounded-[29rpx] text-[28rpx]">
						物流轨迹
					</button>
				</view>
			</view>

		</view>
		<!-- 订单信息卡片 -->
		<view class="bg-white rounded-lg shadow-sm mx-3 mt-4 p-4">
			<!-- 取件地址 -->
			<view class="mt-1">
				<view class="flex">
					<view class="">
						<image :src="img('addon/tk_jhkd/icon/ji.png')" style="width: 62rpx;height: 70rpx;"
							mode="aspectFit"></image>
					</view>
					<view class="ml-2">
						<view class="flex items-center">
							<view class="font-bold text-[32rpx] text-[#333333]">
								{{ JSON.parse(form.orderInfo.start_address).name }}
							</view>
							<view class="font-bold text-[32rpx] text-[#333333] ml-2">
								{{ JSON.parse(form.orderInfo.start_address).mobile }}
							</view>
						</view>

						<view class="font-medium text-gray-800 mt-1 text-[#828282] text-[28rpx]">
							{{ JSON.parse(form.orderInfo.start_address).address }}
						</view>
						<view class="font-medium text-[#828282] text-[28rpx] mt-1">
							{{
								JSON.parse(form.orderInfo.start_address).full_address }}
						</view>
					</view>
				</view>
			</view>
			<view class="line-box !mt-4 !mb-4"></view>
			<!-- 收件地址 -->
			<view class="mt-4">
				<view class="flex items-start">
					<view class="">
						<image :src="img('addon/tk_jhkd/icon/shou.png')" style="width: 62rpx;height: 70rpx;"
							mode="aspectFit"></image>
					</view>
					<view class="ml-2">
						<view class="flex items-center">
							<view class="font-bold text-[32rpx] text-[#333333]">
								{{ JSON.parse(form.orderInfo.end_address).name }}
							</view>
							<view class="font-bold text-[32rpx] text-[#333333] ml-2">
								{{ JSON.parse(form.orderInfo.end_address).mobile }}
							</view>
						</view>

						<view class="font-medium text-gray-800 mt-1 text-[#828282] text-[28rpx]">
							{{ JSON.parse(form.orderInfo.end_address).address }}
						</view>
						<view class="font-medium text-[#828282] text-[28rpx] mt-1">
							{{
								JSON.parse(form.orderInfo.end_address).full_address }}
						</view>
					</view>
				</view>
			</view>
		</view>
		<!-- 计费信息 -->
		<view class="bg-white rounded-lg shadow-sm mx-3 mt-3">
			<up-collapse @change="change001" @close="close001" @open="open001">
				<up-collapse-item title="计费信息" name="Docs guide">
					<view class="p-4 space-y-2">
						<view v-if="form.orderInfo" class="flex flex-wrap gap-4">
							<view class="text-gray-600 text-sm">
								首重：{{ form.orderInfo.price_rule.first }}元/{{ form.orderInfo.price_rule.start }}kg
							</view>
							<view class="text-gray-600 text-sm">
								续重：{{ form.orderInfo.price_rule.add }}元/kg
							</view>
						</view>

						<view v-if="form.deliveryRealInfo" class="space-y-2">
							<view class="flex flex-wrap gap-4">
								<view v-if="form.deliveryRealInfo.fee_weight > 0" class="text-gray-600 text-sm">
									计费重量：{{ form.deliveryRealInfo.fee_weight }}kg
								</view>
								<view v-if="form.deliveryRealInfo.volume > 0" class="text-gray-600 text-sm">
									体积：{{ form.deliveryRealInfo.volume }}cm³
								</view>
							</view>

							<view v-if="form.deliveryRealInfo && form.deliveryRealInfo.fee_blockList.length > 0"
								class="space-y-1">
								<view v-for="(item, index) in form.deliveryRealInfo.fee_blockList"
									class="flex justify-between text-gray-600 text-sm">
									<text v-if="item.fee > 0">{{ item.name }}</text>
									<text v-if="item.fee > 0">{{ item.fee }}元</text>
								</view>
							</view>
						</view>
					</view>
				</up-collapse-item>
			</up-collapse>
		</view>
		<view class="bg-white rounded-lg shadow-sm mx-3 mt-4 p-4">
			<view class="flex justify-between items-center">
				<view class="text-[#333333] text-[28rpx]">订单号</view>
				<view class="text-[#828282] text-[28rpx]">{{ form.order_id }}</view>
			</view>
			<view class="flex justify-between items-center mt-6">
				<view class="text-[#333333] text-[28rpx]">下单时间</view>
				<view class="text-[#828282] text-[28rpx]">{{ form.create_time }}</view>
			</view>
			<view class="flex justify-between items-center mt-6">
				<view class="text-[#333333] text-[28rpx]">物品名称</view>
				<view class="text-[#828282] text-[28rpx]">{{ form.orderInfo.goods }}</view>
			</view>
			<view class="flex justify-between items-center mt-6" v-if="form.orderInfo.bj_price > 0">
				<view class="text-[#333333] text-[28rpx]">物品保价</view>
				<view class="text-[#828282] text-[28rpx]">￥{{ form.orderInfo.bj_price }}</view>
			</view>
			<view class="flex justify-between items-center mt-6">
				<view class="text-[#333333] text-[28rpx]">下单重量</view>
				<view class="text-[#828282] text-[28rpx]">{{ form.orderInfo.weight }}kg</view>
			</view>
			<view class="flex justify-between items-center mt-6">
				<view class="text-[#333333] text-[28rpx]">下单体积</view>
				<view class="text-[#828282] text-[28rpx]">{{ form.orderInfo.long }}cmx{{ form.orderInfo.width }}cmx{{
					form.orderInfo.height }}cm </view>
			</view>
			<view class="flex justify-between items-center mt-6">
				<view class="text-[#333333] text-[28rpx]">下单备注</view>
				<view class="text-[#828282] text-[28rpx]">{{ form.remark }}</view>
			</view>
		</view>
		<view class="h-8"></view>
		<up-popup class="safe-area-inset-bottom" :round="10" @close="expressshow = false" closeable="true"
			:show="expressshow">
			<!-- 快递公司信息 -->
			<view class="flex items-center p-4" v-show="form.orderInfo && form.orderInfo.delivery_arry">
				<image :src="img(form.orderInfo?.delivery_arry?.logo)" mode="aspectFill"
					class="w-10 h-10 rounded-full" />
				<view class="ml-2">
					<view class="text-[#333333] text-medium text-[30rpx] text-[#333333]">
						{{ form.orderInfo?.delivery_arry?.name }}
					</view>
					<view class="flex items-center">
						<view class="text-gray-500 text-xs mt-1" @click="copy(form.orderInfo?.delivery_id)">
							运单号:{{ form.orderInfo?.delivery_id || '等待快递公司返回' }}
						</view>
						<view class="ml-2" v-if="form.orderInfo?.delivery_id">
							<view class="text-primary text-xs mt-1" @click="copy(form.orderInfo?.delivery_id)">
								复制
							</view>
						</view>
					</view>
				</view>
			</view>
			<!-- 物流轨迹 -->
			<scroll-view class="h-[820rpx] mb-[60rpx]" scroll-y="true">
				<view v-if="form.orderInfo?.delivery_id" class="bg-white rounded-lg shadow-sm mx-3 mt-3 p-4 mb-20">
					<view class="p-2" v-if="deliveryInfo && deliveryInfo.length > 0">
						<up-steps dot="true" direction="column">
							<up-steps-item v-for="(item, index) in deliveryInfo" @click="copy(item.desc)" :key="index"
								:title="item.desc" :desc="item.time" />
						</up-steps>
					</view>
					<view v-else class="text-center flex flex-col items-center">
						<view class="text-gray-500 text-sm text-center py-2">暂无物流信息</view>
						<view class="text-gray-400 text-xs text-center flex flex-wrap w-[300rpx]">
							若长期未更新，请前往对应平台官网查询或联系客服查询
						</view>
					</view>
				</view>
			</scroll-view>
		</up-popup>
		<pay ref="payRef" @close="payLoading = false"></pay>
		<!-- 添加悬浮按钮组 -->
		<view class="float-button-group" :style="{ bottom: '260rpx', right: '30rpx' }">
			<view class="flex flex-col gap-2" :class="{ 'button-group-hidden': !isExpanded }">
				<button
					class="float-button bg-gray-500/70 backdrop-blur-sm text-white transform transition-all duration-300"
					@click="goHome">
					<up-icon name="home" color="#FFFFFF" size="22"></up-icon>
				</button>
				<button
					class="float-button bg-gray-500/70 backdrop-blur-sm text-white transform transition-all duration-300"
					@click="contactService">
					<up-icon name="server-man" color="#FFFFFF" size="22"></up-icon>
				</button>
			</view>
			<button class="float-button bg-gray-400/70 backdrop-blur-sm text-white mt-2" @click="toggleExpand">
				<up-icon :name="isExpanded ? 'arrow-down' : 'more-dot-fill'" color="#FFFFFF" size="22"></up-icon>
			</button>
		</view>
	</view>
	<view class="w-full footer !bg-white">
		<view
			class="py-[var(--top-m)] flex justify-center px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border !bg-white">
			<!-- 操作按钮 -->
			<button v-if="form.order_status_arr" v-for="(item1, index) in form.order_status_arr.member_action"
				class="leading-[80rpx] flex items-center justify-center m-2 text-[#333333] bg-[#F2F2F2] rounded-[29rpx] text-[28rpx] w-[100%]"
				:class="[
					item1.class === 'gopay'
						? 'actcolor'
						: ''
				]" @click="clickbut(item1.class, item)">
				{{ item1.name }}
			</button>

		</view>
	</view>



</template>

<script setup lang="ts">
import {
	ref,
	reactive
} from 'vue'
import {
	onLoad
} from '@dcloudio/uni-app'
import {
	getOrderDetail, applyRefund, deleteOrder, getDeliveryInfo, closeOrder
} from '@/addon/tk_jhkd/api/order'
import {
	goback, goto
} from '@/addon/tk_jhkd/utils/ts/goto';
import {
	timeChange
} from '@/addon/tk_jhkd/utils/ts/common';
import { img, copy } from '@/utils/common'
const expressshow = ref(false)
const expressShowEvent = () => {
	expressshow.value = true
	if (form.orderInfo.delivery_id) {
		getTrance(form.orderInfo.delivery_id)
	} else {
		uni.$u.toast('等待快递公司返回单号')
		expressshow.value = false
		return
	}
}
const payRef = ref(null)
const payLoading = ref(false)
const form = reactive({})
const deliveryInfo = ref()
const pickInfo = ref()
const clickbut = (action) => {
	if (action == 'gopay') {
		gopay()
	}
	if (action == 'del') {
		del()
	}
	if (action == 'refund') {
		refund()
	}
	if (action == 'close') {
		close()
	}
}
//联系快递
const telPicker = (phone) => {
	uni.makePhoneCall({
		phoneNumber: phone
	});
}
//关闭
const close = async () => {
	await closeOrder(form.id)
	goto('/addon/tk_jhkd/pages/orderlist')
}
//删除
const del = async () => {
	await deleteOrder(form.id)
	goto('/addon/tk_jhkd/pages/orderlist')
}
//退款
const refund = async () => {
	let params: object = {
		id: form.id,
		close_reason: '用户主动退款'
	};
	await applyRefund(params)
	goto('/addon/tk_jhkd/pages/orderlist')
}
//评价
const evaluate = () => {
	console.log('evaluate')
}
//再来一单
const again = () => {
	console.log('again')
}
const gopay = () => {
	payLoading.value = true
	payRef.value?.open('jhkdOrderPay', form.id, '/tk_jhkd/pages/orderdetail?id=' + form.id)
}
const getTrance = async (deliveryid) => {
	const data = await getDeliveryInfo(deliveryid)
	deliveryInfo.value = data.data
}
const getData = async (id: number) => {
	const data = await getOrderDetail(id)
	if (data.data.order_status_arr == null) {
		uni.$u.toast('订单不存在或删除')
		setTimeout(function () {
			goto('/addon/tk_jhkd/pages/orderlist');
		}, 1000);
	}
	Object.assign(form, data.data)
	if (form.orderInfo.courier_context) {
		pickInfo.value = JSON.parse(form.orderInfo.courier_context)
	}
}
// 悬浮按钮相关变量和方法
const isExpanded = ref(false)

const toggleExpand = () => {
	isExpanded.value = !isExpanded.value
}

const goHome = () => {
	goto('/addon/tk_jhkd/pages/index')
}

const contactService = () => {
	goto('/app/pages/member/contact')
}

onLoad((options: any) => {
	if (options.id) {
		getData(options.id)
	}
})
</script>

<style lang="scss" scoped>
@import '@/addon/tk_jhkd/utils/styles/common.scss';

.actcolor {
	background: #0057FE !important;
	color: #ffffff !important;
}

.float-button-group {
	position: fixed;
	z-index: 999;
}

.button-group-hidden {
	transform: translateY(20rpx);
	opacity: 0;
	pointer-events: none;
}

.float-button {
	width: 80rpx;
	height: 80rpx;
	border-radius: 9999px;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
	transition: all 0.3s ease;

	&:active {
		transform: scale(0.95);
	}
}


.class-select {
	position: relative;
	font-weight: bold;

	&::after {
		content: "";
		position: absolute;
		bottom: 0;
		height: 6rpx;
		background-color: var(--primary-color);
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

.order-num-card {
	background-color: rgba(0, 85, 255, 0);
	padding: 12rpx;
	border-radius: 8rpx;
}

:root,
.primary-btn-bg {
	// background: linear-gradient( 94deg, #FB7939 0%, #FE120E 99%), #EF000C;
	background: linear-gradient(94deg, var(--primary-help-color) 0%, var(--primary-color) 99%), var(--primary-color);
}
</style>