<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS --> 
	<u-navbar :title="t('myCard')" :fixed="true" placeholder @leftClick="rightClick">
	</u-navbar>
	<!-- #endif -->
	<view :style="themeColor()" >
		<view class="flex justify-between fixed w-full z-index-99 bg-[#fff] p-[24rpx] px-[56rpx] box-border"
			v-if="cardStateList?.length">
			<view class="relative flex justify-center items-center flex-col leading-[1.8] text-[26rpx]"
				v-for="(item,index) in cardStateList" :key="index" @click="cardStateFn(item)"
				:style="{color:item.status == currentState ? 'var(--primary-color)' : ''}">
				{{item.name}}
				<view class="w-[40rpx] h-[6rpx] rounded-lg text-[28rpx]"
					:class="item.status == currentState ? 'ac-bg' : 'bg-fff'"></view>
			</view>
		</view>
		<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()">
			<!-- 主体内容区域 -->
			<mescroll-body ref="mescrollRef" @init="mescrollInit" :down="{ use: false }" @up="getcardListFn" top="104"
				class="mescroll-body">
				<!-- 订单列表 -->
				<template v-for="(item, index) in cardList" :key="item.order_no">
					<view class="">
						<view class="bg-[#fff] m-[24rpx] p-[24rpx] rounded-lg">
							<view class="flex justify-between items-center border-b border-gray-100">
								<view class="flex items-center text-sm text-[#111] flex-1">
									<text>{{ item.card?.card_name}}</text>
									<text class="text-[26rpx] text-[#999999] ml-[15rpx]">{{ item.create_time }}</text>
								</view>
								<view class="text-[26rpx]" :class="getOrderStatusClass(item)">
									{{item.status_name}}
								</view>
							</view>
							<view class="text-[26rpx] text-[#666666] mt-[10rpx]">
								使用期限：{{item.expire_time}}
							</view>
							<view class="pt-[20rpx]">
								<u-line-progress :percentage="calcProgress(item)" :showText="false" height="8">
								</u-line-progress>
								<view class=" text-[24rpx] my-[10rpx] flex justify-between mb-[30rpx]">
									<view class="text-[#666666]">
										共{{item.total_num}}次
									</view>
									<view class="text-[#ff0000]">
										{{item.total_use_num}}/{{item.total_num}}
									</view>
								</view>
							</view>
							<view class="flex mt-[15rpx] justify-end">
								<button size="small"
									@click="redirect({url:'/addon/home_service/user/pages/card/use_detail',param:{id:item.id}})"
									class="!mx-0 !text-[26rpx] py-[20rpx] !ml-[15rpx] !w-auto bg-[#fff] leading-[1] !text-[var(--primary-color)] border-style !px-[55rpx] !border-[var(--primary-color)] !rounded-[10rpx]"
									:class="item.total_use_num != item.total_num ? 'w-[40%]' : 'flex-1'">
									查看明细
								</button>
								<button size="small"
									@click="redirect({url:'/addon/home_service/user/pages/card/use_card',param:{id:item.id}})"
									v-if="item.total_use_num != item.total_num"
									class="!mx-0 !ml-[15rpx] !text-[26rpx] py-[20rpx] !w-auto !text-[#fff] leading-[1] !px-[55rpx] !bg-[var(--primary-color)] !border-[var(--primary-color)] !rounded-[10rpx] flex-1">
									预约使用
								</button>
							</view>
						</view>
					</view>
				</template>

				<!-- 空状态 -->
				<mescroll-empty :option="{ 
          'icon': img('static/resource/images/empty.png'), 
          'tip': t('nothingMore') 
        }" v-if="!cardList.length && !loading"></mescroll-empty>
			</mescroll-body>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import {
		ref,
		computed
	} from 'vue'
	import {
		onLoad,
		onShow,
		onPageScroll,
		onReachBottom
	} from '@dcloudio/uni-app'
	import {
		t
	} from '@/locale'
	import {
		img,
		redirect
	} from '@/utils/common'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import useConfigStore from '@/stores/config'
	import {
		getMyCardList,
		getMyCardStatus
	} from '@/addon/home_service/user/api/card'
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	// 配置状态管理
	const configStore = useConfigStore()

	// mescroll 相关
	const {
		mescrollInit,
		getMescroll
	} = useMescroll(onPageScroll, onReachBottom)
	const mescrollRef = ref(null)
	const calcProgress = (data: any) => {
		return data.total_use_num / data.total_num * 100
	}
	// 数据状态
	const cardList = ref < Array < any >> ([])
	const loading = ref < boolean > (true)
	const currentState = ref("")
	const cardStateList = ref([])

	const getStatusFn = () => {
		getMyCardStatus().then((res) => {
			cardStateList.value = res.data
			cardStateList.value.unshift({
				name: '全部',
				status: ''
			})
		})
	}
	onLoad(() => {
		// 初始化时加载第一页数据
		getStatusFn()
		getcardListFn({
			num: 1,
			size: 10
		})
	})
	onShow(() => {
		if (getMescroll()) {
			getMescroll().resetUpScroll()
		}
	})
	// 切换状态
	const cardStateFn = (e: any) => {
		currentState.value = e.status
		cardList.value = []
		getMescroll().resetUpScroll()
	}

	// 获取订单状态样式类
	const getOrderStatusClass = (item: any) => {
		if (item.status === 'wait_use') {
			return 'text-[#4CAF50]'
		}
		return currentState.value === 'expire' ? 'text-[#FF0000]' : 'text-[#b8b8b8]'
	}


	// 获取发票订单列表
	const getcardListFn = (mescroll: any) => {
		loading.value = true

		// 构建API请求参数
		const params = {
			page: mescroll.num,
			limit: mescroll.size,
			status: currentState.value,
			city_id: systemStore.diyAddressInfo?.city_id
		}
		getMyCardList(params).then((res: any) => {
			// 后续处理逻辑保持不变
			if (res.code === 1 && res.data && res.data.data) {
				const newData = res.data.data.map((item: any) => ({
					...item,
					checked: false
				}));

				if (mescroll.num === 1) {
					cardList.value = [];
				}

				cardList.value = cardList.value.concat(newData);
				mescroll.endSuccess(newData.length);
			} else {
				mescroll.endSuccess(0);
			}
			loading.value = false;
		}).catch(() => {
			loading.value = false;
		});
	}
	const rightClick = () => {
			console.log('rightClick')
		redirect({url:'/addon/home_service/user/pages/member/index'
	})
	}
</script>

<style lang="scss" scoped>
	.ac-bg {
		background-color: var(--primary-color);
	}

	.bg-fff {
		background-color: #fff;
	}

	.border-style {
		border: 2rpx solid var(--primary-color)
	}
</style>
