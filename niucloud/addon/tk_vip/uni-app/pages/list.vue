<template>
	<view class="bg-[#f8f8f8] min-h-screen" :style="themeColor()">
		<!-- 主内容区 -->
		<mescroll-body ref="mescrollRef" top="44rpx" @init="mescrollInit" @down="downCallback" @up="getOrderListFn">

			<!-- 列表内容 -->
			<view v-if="list.length > 0" class="mx-4 mt-3">
				<view class="bg-white rounded-lg shadow-sm mb-3 p-4" v-for="(item, index) in list" :key='index'>
					<view class="flex items-center justify-between">
						<view class="flex items-center">
							<view class="font-bold text-base mr-2">{{ item.body }}</view>
							<view class="px-2 py-1 bg-red-50 text-xs text-red-500 rounded-md">{{ item.level_id_name }}
							</view>
						</view>
						<view class="text-[#f43034] font-bold">￥{{ item.order_money }}</view>
					</view>

					<view class="my-3 border-t border-gray-100"></view>

					<view class="flex items-center justify-between">
						<view class="text-xs text-gray-400">
							{{ item.status_name }}
						</view>
						<view class="text-xs text-gray-400 flex items-center">
							<up-icon name="calendar" size="14" color="#999"></up-icon>
							<text class="ml-1">{{ item.create_time }}</text>
						</view>
					</view>
				</view>
			</view>

			<!-- 空数据显示 -->
			<view v-if="!list.length && loading" class="mt-20">
				<mescroll-empty
					:option="{ 'icon': img('static/resource/images/empty.png'), tip: '暂无购买记录' }"></mescroll-empty>
			</view>
		</mescroll-body>

		<!-- 底部返回按钮 -->
		<view @click="redirect({ url: '/addon/tk_vip/pages/index', mode: 'reLaunch' })"
			class="fixed bottom-48 right-4 z-50 rounded-full bg-white shadow-lg p-3">
			<up-icon name="arrow-left-double" color="#333" size="24"></up-icon>
		</view>

		<!-- 支付组件 -->
		<pay ref="payRef" @close="payLoading = false"></pay>
	</view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { img, redirect } from '@/utils/common';
import { getOrderList } from '@/addon/tk_vip/api/order';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';

// 定义订单列表项的接口
interface OrderItem {
	body: string;
	order_money: string | number;
	level_id_name: string;
	create_time: string;
	[key: string]: any;
}

const payRef = ref(null);
const payLoading = ref(false);
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
let list = ref<Array<OrderItem>>([]);
let loading = ref<boolean>(false);
let statusLoading = ref<boolean>(false);
let orderState = ref('');
let orderStateList = ref([]);
const listData = ref([]);

onLoad((option: any) => {
	orderState.value = option?.status || "";
});

// 获取订单列表
const getOrderListFn = (mescroll: any) => {
	loading.value = false;
	let data: Record<string, any> = {
		page: mescroll.num,
		limit: mescroll.size,
		status: orderState.value
	};

	getOrderList(data).then((res: any) => {
		let newArr = (res.data.data as Array<OrderItem>);
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
	});
};
</script>

<style lang="scss" scoped>
@import '@/addon/tk_vip/utils/styles/common.scss';

.text-primary {
	color: var(--primary-color);
}

:deep(.mescroll-empty) {
	padding-top: 120rpx;
}

@keyframes fadeIn {
	from {
		opacity: 0;
		transform: translateY(10px);
	}

	to {
		opacity: 1;
		transform: translateY(0);
	}
}

.bg-white {
	animation: fadeIn 0.3s ease-in-out;
}
</style>