<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()">
		<!-- 主体内容区域 -->
		<mescroll-body ref="mescrollRef" @init="mescrollInit" :down="{ use: false }" @up="getcardListFn"
			class="mescroll-body">
			<!-- 订单列表 -->
			<template v-for="(item, index) in cardList" :key="item.order_no">
				<view class="">
					<view class="bg-[#fff] m-[24rpx] p-[24rpx] rounded-lg">
						<view class="flex justify-between items-center">
							<view class="text-[28rpx] font-bold ">
								{{item.goodsSku?.sku_name}}
							</view>
							<view :class="item.use_num == item.num ? 'text-[#999999]' : ''" class="font-bold">
								{{item.use_num == item.num ? '已用完' : item.use_num + '/' +  item.num}}
							</view>
						</view>
						<view class="py-[20rpx]">
							<button @click="goUse(item)" v-if="item.num != item.use_num"
								class="!mx-0 text-[28rpx] leading-[2.3] !w-auto !text-[#fff] !px-[55rpx] !bg-[var(--primary-color)] !border-[var(--primary-color)] !rounded-[10rpx] flex-1">
								预约使用
							</button>
							<button  v-else disabled
								class="!mx-0 text-[28rpx] leading-[2.3] !w-auto !text-[#a8a8a8] !px-[55rpx] !bg-[#f6f6f6]  !border-[#f6f6f6] !rounded-[10rpx] flex-1">
								已使用
							</button>
						</view>
					</view>
				</view>
			</template>
			<view class="m-[24rpx] bg-[#fff] p-[24rpx] rounded-lg" v-if="cardList.length">
				<view class="flex justify-between pb-[20rpx]"
					@click="redirect({url:'/addon/home_service/user/pages/card/use_detail',param:{id:id}})">
					<view class="font-bold text-[30rpx]">
						次卡服务说明
					</view>
					<view class="flex text-[#999999] items-center text-[26rpx]">
						使用记录
						<text class="iconfont iconarrow-right text-[24rpx]"></text>
					</view>
				</view>
				<view >
					<view class="flex items-center mt-[25rpx]">
						<view class="w-[12rpx] mr-[15rpx] h-[12rpx] bg-[#acacac] rounded-[50%]"></view>
						<view class="text-[26rpx]">有效期:{{cardList[0].expire_time}}</view>
					</view>
					<!-- <view class="flex items-center mt-[25rpx]">
						<view class="w-[12rpx] mr-[15rpx] h-[12rpx] bg-[#acacac] rounded-[50%]"></view>
						<view class="text-[26rpx]">本卡包含基础清洗项目，次数独立计算；</view>
					</view>
					<view class="flex items-center mt-[25rpx]">
						<view class="w-[12rpx] mr-[15rpx] h-[12rpx] bg-[#acacac] rounded-[50%]"></view>
						<view class="text-[26rpx]">每次服务后提供7天质保,质保期内出现问题免费返修;</view>
					</view>
					<view class="flex items-center mt-[25rpx]">
						<view class="w-[12rpx] mr-[15rpx] h-[12rpx] bg-[#acacac] rounded-[50%]"></view>
						<view class="text-[26rpx]">服务需提前6小时预约，节假日需提前1天预约；</view>
					</view>
					<view class="flex items-center mt-[25rpx]">
						<view class="w-[12rpx] mr-[15rpx] h-[12rpx] bg-[#acacac] rounded-[50%]"></view>
						<view class="text-[26rpx]">完全未使用可退款；</view>
					</view> -->
					<view class="flex items-center mt-[25rpx]">
						<view class="w-[12rpx] mr-[15rpx] h-[12rpx] bg-[#acacac] rounded-[50%]"></view>
						<view class="text-[26rpx] leading-[1.4]">次卡下单后支持退款，退款完成后将自动扣减对应已购买次数。</view>
					</view>
					<view class="flex items-center mt-[25rpx]">
						<view class="w-[12rpx] mr-[15rpx] h-[12rpx] bg-[#acacac] rounded-[50%]"></view>
						<view class="text-[26rpx] leading-[1.3]">次卡有效期届满后，未使用次数将自动失效，不支持折现或退款。</view>
					</view>
		
				</view>
			
			</view>
			<!-- 空状态 -->
			<mescroll-empty :option="{ 
          'icon': img('static/resource/images/empty.png'), 
          'tip': t('nothingMore') 
        }" v-if="!cardList.length && !loading"></mescroll-empty>
		</mescroll-body>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, computed } from 'vue'
	import { onLoad,onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { img,redirect } from '@/utils/common'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import useConfigStore from '@/stores/config'
	import { getCardItem} from '@/addon/home_service/user/api/card'

	// 配置状态管理
	const configStore = useConfigStore()
	onShow(()=>{
		if(getMescroll()){
			getMescroll().resetUpScroll()
		}
	})
	// mescroll 相关
	const { mescrollInit, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	const mescrollRef = ref(null)
	const calcProgress = (data:any) =>{
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
	const goUse = (data:any) =>{
		const param = {
		    sku_id: data.goods_sku_id,
		    num: 1,
		};
		
		uni.setStorage({
			key: 'o2oCreateData',
			data: {
				card_data:{
					member_card_id:data.member_card_id,
					member_card_item_id:data.item_id,
				},
				sku: param
			},
			success: () => {
				redirect({ url: '/addon/home_service/user/pages/order/payment', param: { id: data.goods_id } })
			}
		});
	}
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


	// 获取发票订单列表
	const getcardListFn = (mescroll : any) => {
		loading.value = true

		// 构建API请求参数
		const params = {
			page: mescroll.num,
			limit: mescroll.size,
			member_card_id:id.value
		}
		getCardItem(params).then((res : any) => {
			// 后续处理逻辑保持不变
			if (res.code === 1 && res.data && res.data.data) {
				const newData = res.data.data.map((item : any) => ({
					...item,
					checked: false
				}));

				if (mescroll.num === 1) {
					cardList.value = [];
				}
				cardList.value = cardList.value.concat(newData);
				console.log(cardList.value)
				mescroll.endSuccess(newData.length);
			} else {
				mescroll.endSuccess(0);
			}
			loading.value = false;
		}).catch(() => {
			loading.value = false;
		});
	}
</script>

<style lang="scss" scoped>
</style>