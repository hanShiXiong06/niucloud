<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden order-list" :style="themeColor()">
        <view class="fixed left-0 top-0 right-0 z-10085">
			<view class="px-[20rpx] py-[14rpx] bg-[#fff] relative z-10084">
				<view class="h-[60rpx] bg-[#f6f6f6] rounded-[30rpx] flex items-center">
					<view class="search-input !px-[20rpx]">
						<input class="input" maxlength="50" type="text" v-model="keyword" placeholder="请输入搜索关键词" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="searchTypeFn()">
						<text v-if="keyword" class="nc-iconfont nc-icon-cuohaoV6xx1 clear mt-[4rpx] mr-[10rpx]" @click="keyword=''"></text>
						<text class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn !mr-0 w-[40rpx] text-center" @click="searchTypeFn()"></text>
					</view>
				</view>
			</view>
			<scroll-view :scroll-x="true" class="tab-style-2">
				<view class="tab-content">
					<view :class="getRefundTabClass(item.status)" @click="refundStateFn(item.status)" v-for="(item, index) in refundStateList">{{ item.name }}</view>
				</view>
			</scroll-view>
		</view>
		<mescroll-body ref="mescrollRef" top="176rpx" @init="mescrollInit" :down="{ use: false }" @up="getorderRefundFn">
			<view class="sidebar-margin pt-[var(--top-m)]" v-if="list.length">
				<template v-for="(item, index) in list" :key="index">
					<view class="mb-[var(--top-m)] card-template" @click.stop="toLink(item)">
						<view class="flex justify-between items-center mb-[20rpx]">
							<view class="flex items-center" v-if="item.member"  @click.stop="redirect({url: '/app/pages/member/detail', param: {member_id: item.member_id}})">
								<u-avatar :default-url="img('static/resource/images/default_headimg.png')" :src="img(item.member.headimg)" :size="'30rpx'" leftIcon="none" />
								<text  class="text-[28rpx] ml-[10rpx]">{{ item.member.nickname }}</text>
								<text class="nc-iconfont nc-icon-youV6xx text-[24rpx] text-[#999] relative top-[4rpx]"></text>
							</view>
							<view class="flex items-center">
								<text class="text-[26rpx] font-400 text-[#999]">{{ item.create_time  }}</text>
								<text class="bg-[#ccc] w-[2rpx] h-[20rpx] mx-[10rpx]"></text>
								<text class="text-[#303133] text-[26rpx] leading-[34rpx]">{{ item.status_name }}</text>
							</view>
						</view>
						<view class="flex box-border mb-[20rpx]" v-for="(subitem, index) in [item.order_goods]" :key="index">
							<up-image width="150rpx" height="150rpx" :radius="'var(--goods-rounded-big)'" :src="img(subitem.goods_image_thumb_small ? subitem.goods_image_thumb_small : '')" mode="aspectFill">
								<template #error>
									<image class="w-[150rpx] h-[150rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
								</template>
							</up-image>
							<view class="ml-[20rpx] flex flex-1 flex-col box-border">
								<view class="flex justify-between items-baseline">
									<view class="max-w-[360rpx] text-[28rpx] leading-[40rpx] font-400 truncate text-[#303133]">{{ subitem.goods_name }}</view>
									<view class="text-right leading-[42rpx] ml-[10rpx] price-font">
										<text class="text-[22rpx]">￥</text>
										<text class="text-[32rpx] font-500">{{parseFloat(subitem.goods_money).toFixed(2).split('.')[0] }}</text>
										<text class="text-[22rpx] font-500">.{{parseFloat(subitem.goods_money).toFixed(2).split('.')[1] }}</text>
									</view>
								</view>
								<view class="flex  justify-between items-baseline text-[#303133] mt-[14rpx]">
									<view>
										<view class="text-[24rpx] text-[var(--text-color-light6)] font-400 truncate leading-[34rpx] max-w-[369rpx] mb-[10rpx]" v-if="subitem.sku_name">{{ subitem.sku_name }}</view>
									</view>
									<text class="text-right text-[26rpx] font-400 w-[90rpx] leading-[36rpx]">x{{ subitem.num }}</text>
								</view>
							</view>
						</view>
						<view class="flex justify-end items-center mb-[20rpx]">
							<view class="flex items-baseline">
								<view class="text-[22rpx] font-400 leading-[30rpx] text-[#303133]">实付金额：</view>
								<view class="leading-[1] text-[var(--price-text-color)]">
									<text class="ext-[22rpx] leading-[26rpx] price-font">￥</text>
									<text class="text-[36rpx] font-500 leading-[40rpx] price-font">{{ parseFloat(item.order_goods.order_goods_money).toFixed(2).split('.')[0]  }}</text>
									<text class="text-[22rpx] font-500 leading-[28rpx] price-font">.{{ parseFloat(item.order_goods.order_goods_money).toFixed(2).split('.')[1]  }}</text>
								</view>
							</view>
						</view>
						<view class="bg-[var(--page-bg-color)] p-[20rpx] rounded-[10rpx]">
							<view class="text-[28rpx] font-500 mb-[30rpx]">{{ item.status_name }}</view>
							<view class="text-[26rpx] mb-[30rpx] flex items-center">
								<text class="w-[120rpx]">退款编号</text>
								<text class="text-[#999]">{{ item.order_refund_no }}</text>
							</view>
							<view class="text-[26rpx] mb-[30rpx] flex items-center">
								<text class="w-[120rpx]">退款原因</text>
								<text class="text-[#999]">{{ item.reason }}</text>
							</view>
							<view class="text-[26rpx]  mb-[30rpx] flex items-center">
								<text class="w-[120rpx]">退款金额</text>
								<text class="text-[#999]">￥{{ item.apply_money }}</text>
							</view>
							<view class="text-[26rpx] flex items-center">
								<text class="w-[120rpx]">退款方式</text>
								<text class="text-[#999]">{{ item.refund_type_name }}</text>
							</view>
						</view>
						<view class="flex justify-end pt-[26rpx]">
							<view class="list-grey-solid-btn bg-[var(--page-bg-color)]  mr-[14rpx]">查看详情</view>
						</view>
					</view>
				</template>
			</view>
			<mescroll-empty :option="{tip : '暂无订单'}" v-if="!list.length && loading"></mescroll-empty>
		</mescroll-body>
	</view>
</template>

<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { img,copy, redirect } from '@/utils/common';
import { orderRefund, getRefundStatus } from '@/addon/mall/api/refund';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';


const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);


const list = ref<Array<Object>>([]);
const loading = ref<boolean>(false);
const statusLoading = ref<boolean>(false);
const refundState = ref('')
const refundStateList = ref<any>([]);
const keyword = ref('');

const getRefundTabClass = (status: any) => {
	const base = 'tab-items mr-[40rpx]'
	return refundState.value === status.toString() ? `${ base } class-select` : base
}

onLoad((option: any) => {
	refundState.value = option.status || '';
	getrefundStatusFn();
});

const getrefundStatusFn = () => {
	statusLoading.value = false;
	refundStateList.value = [];
	let obj = { name: '全部', status: '' };
	refundStateList.value.push(obj);

	getRefundStatus().then((res: any) => {
		for (let key in res.data) {
			let data = {
				name: res.data[key],
				status: key
			}
			refundStateList.value.push(data);
		}
		
		statusLoading.value = true;
	}).catch(() => {
		statusLoading.value = true;
	})
}

const getorderRefundFn = (mescroll:any) => {
	loading.value = false;
	let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
		order_refund_no: keyword.value,
		status: refundState.value
	};	

	orderRefund(data).then((res: any) => {
		let newArr = (res.data.data as Array<Object>);
		//设置列表数据
		if (mescroll.num == 1) {
			list.value = []; //如果是第一页需手动制空列表
		}
		list.value = list.value.concat(newArr);
		mescroll.endSuccess(newArr.length);

		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

const  refundStateFn = (status:any) => {
	refundState.value = status;
	getMescroll().resetUpScroll();
}

const searchTypeFn = () => {
	list.value = [];
	getMescroll().resetUpScroll();
	
}

const toLink = (data: any) => {
	redirect({ url: '/addon/mall/pages/refund/detail', param: { refund_id: data.refund_id } })
}



</script>
<style>
.order-list .mescroll-body {
	padding-bottom: constant(safe-area-inset-bottom) !important;
	padding-bottom: env(safe-area-inset-bottom) !important;
}
</style>
<style lang="scss" scoped>

</style>
