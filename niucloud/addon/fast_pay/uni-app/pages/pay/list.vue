<template>
	<view class="bg-[#f8f8f8] min-h-screen overflow-hidden">
		<mescroll-body ref="mescrollRef" top="64rpx" @init="mescrollInit" @down="downCallback" @up="getOrderListFn">
			<view v-if="list.length > 0" class="tk-card" v-for="(item, index) in list" key=index>
				<view class="flex items-center">
					<view>订单号：</view>
					<view class="font-bold text-sm">{{item.order_id}}</view>
				</view>
				<view class="flex justify-between mt-2">
					<view class="font-bold text-[34rpx]">￥{{item.order_money}}</view>
					<view>
						<up-tag v-if="item.order_status==10" text="已支付" size="mini"></up-tag>
						<up-tag v-if="item.order_status!=10" text="未支付" plain size="mini"></up-tag>
					</view>
				</view>
			</view>
			<mescroll-empty :option="{'icon': img('static/resource/images/empty.png')}"
				v-if="!list.length && loading"></mescroll-empty>
		</mescroll-body>
	</view>
</template>

<script setup lang="ts">
	import { ref } from 'vue';
	import { getOrderList } from '@/addon/fast_pay/api/pay';
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
	import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';

	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
	let list = ref<Array<Object>>([]);
	let loading = ref<boolean>(false);


	const getOrderListFn = (mescroll) => {
		loading.value = false;
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
		};
		getOrderList(data).then((res) => {
			let newArr = (res.data.data as Array<Object>);
			//设置列表数据
			if (mescroll.num == 1) {
				list.value = []; //如果是第一页需手动制空列表
			}
			list.value = list.value.concat(newArr);
			console.log(list.value);
			mescroll.endSuccess(newArr.length);
			loading.value = true;
		}).catch(() => {
			loading.value = true;
			mescroll.endErr(); // 请求失败, 结束加载
		})
	}
</script>
<style lang="scss" scoped>
	.tk-card {
		background-color: rgba(252, 249, 249, 0.9);
		margin: 24rpx;
		border-radius: 12rpx;
		padding: 24rpx;
		box-shadow: 0 1px 1px 0 rgba(234, 234, 234, 0.2), 0 2px 2px 0 rgba(231, 231, 231, 0.2);
	}
</style>