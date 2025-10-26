<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()" v-if="!loading">
		<view class="m-[25rpx]">
			<view class="flex justify-between items-center">
				<view>
					<view class="text-[32rpx] font-bold">
						{{cardInfo.status == 'wait_use' ? '待使用' : '已用完'}}
					</view>
					<view v-if="cardInfo.expire_time" class="text-[26rpx] mt-[25rpx] text-[#999999]">
						有效期至：{{cardInfo.expire_time}}
					</view>
				</view>
				<view class="text-[var(--primary-color)]">
					{{cardInfo.total_use_num}}/{{cardInfo.total_num}}
				</view>
			</view>
			<view class="flex items-center mt-[25rpx] bg-[#fff] p-[25rpx] rounded-lg" @click="redirect({url:'/addon/home_service/user/pages/card/detail',param:{card_id:cardInfo.card_id}})">
				<view class="flex flex-1">
					<view>
						<image class="w-[180rpx] h-[180rpx] rounded-lg block"
							:src="img(cardInfo.card?.card_image)" />
					</view>
					<view class="flex flex-col justify-between ml-[25rpx]">
						<view class="font-bold">
							{{cardInfo.card?.card_name}}
						</view>
						<view class="flex items-center justify-between">
							<view class="text-red-500 font-bold">
								<text class="text-xs leading-[20rpx]">¥</text>
								<text class="text-lg">{{ formatPriceBeforeDecimal(cardInfo.card?.price) }}</text>
								<text class="text-xs">.</text>
								<text class="text-xs">{{ formatPriceAfterDecimal(cardInfo.card?.price) }}</text>
							</view>
						</view>
					</view>
				</view>
				<view class="text-[24rpx] text-[#999999]">
					x1
				</view>
			</view>
			<view class="bg-[#fff] rounded-lg my-[24rpx] p-[24rpx]">
				<view class="font-bold text-[30rpx] rounded-lg">预约使用记录</view>
				<view class="py-[35rpx] border-bottom-style" v-for="(item,index) in cardInfo.useRecords" v-if="cardInfo.useRecords?.length"
					:key="index">
					<view class="">
						<view class="flex justify-between mb-[30rpx]">
							<view class="text-[28rpx]">
								{{item.order?.order_name}}
							</view>
							<view class="text-[26rpx]" :style="{color:item.order?.order_status == 'wait_dispatch' ? '#ff0000' : ''}">
								{{item.order?.order_status_info?.name}}
							</view>
						</view>
						<view class="text-[26rpx] text-[#999999] my-[15rpx]">
							订单编号：{{item.order?.order_no}}
						</view>
						<view class="flex bg-[#f6f6f6] py-[24rpx] px-[24rpx] rounded-lg">
							<view class="w-[50%]">
								<view class="text-[#999999] text-[26rpx]">
									预约时间：
								</view>
								<view class="text-[28rpx] mt-[24rpx]">
									{{item.order?.reserve_service_time}}
								</view>
							</view>
							<view v-if="item.order?.technician_name">
								<view class="text-[#999999] text-[26rpx]">
									服务师傅：
								</view>
								<view class="text-[28rpx] mt-[24rpx]">
									{{item.order?.technician_name}}
								</view>
							</view>
						</view>
					</view>
				</view>
				<view v-else>
					<view class="bg-[#fff] py-[30rpx]">
						<u-empty :icon="img('static/resource/images/empty.png')" :text="t('orderInfoNotObtained')" />
					</view>
				</view>
			</view>
			
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, computed } from 'vue'
	import { onLoad,onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import useConfigStore from '@/stores/config'
	import { getCardUseList } from '@/addon/home_service/user/api/card'
	// 价格格式化函数 - 处理小数部分
	const formatPriceAfterDecimal = (price : string | number) : string => {
		if (!price) return '00'
		const priceStr = String(price)
		const index = priceStr.indexOf('.')
		if (index !== -1) {
			// 截取小数部分，并确保是2位
			return (priceStr.substring(index + 1) + '00').substring(0, 2)
		}
		return '00'
	}
	onShow(()=>{
		if(id.value){
			getcardListFn({ num: 1, size: 10 })
		}
	})
	// 价格格式化函数 - 处理整数部分
	const formatPriceBeforeDecimal = (price : string | number) : string => {
		if (!price) return '0'
		const priceStr = String(price)
		const index = priceStr.indexOf('.')
		if (index !== -1) {
			return priceStr.substring(0, index)
		}
		return priceStr
	}
	// 配置状态管理
	const configStore = useConfigStore()

	// mescroll 相关
	const { mescrollInit, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	const mescrollRef = ref(null)
	const calcProgress = (data : any) => {
		return data.use_num / data.num * 100
	}
	// 数据状态
	const cardList = ref<Array<any>>([])
	const loading = ref<boolean>(true)
	const currentState = ref("")
	const cardStateList = ref([])
	const id = ref('')
	onLoad((option) => {
		id.value = option.id
		getcardListFn({ num: 1, size: 10 })
	})

	// 切换状态
	const cardStateFn = (e : any) => {
		currentState.value = e.status
		cardList.value = []
		getMescroll().resetUpScroll()
	}

	// 获取订单状态样式类
	const getOrderStatusClass = (item : any) => {
		if (item.status === 'wait_use') {
			return 'text-[#4CAF50]'
		}
		return currentState.value === 'expire' ? 'text-[#FF0000]' : 'text-[#b8b8b8]'
	}

	const cardInfo = ref({})
	// 获取发票订单列表
	const getcardListFn = () => {
		loading.value = true
		const params = {
			member_card_id: id.value
		}
		getCardUseList(params).then((res : any) => {
			cardInfo.value = res.data
			loading.value = false;
		}).catch(() => {
			loading.value = false;
		});
	}
</script>

<style lang="scss" scoped>
	.border-bottom-style {
		border-bottom: 2rpx solid #f6f6f6;
	}
</style>