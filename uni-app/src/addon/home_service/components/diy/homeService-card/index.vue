<template>
	<view :style="warpCss" class="p-[25rpx]" v-if="cardInfo.card_id || diyStore.mode == 'decorate'">
		<view class="diy-text relative ">
			<view class="pb-[20rpx] flex items-center justify-between text-[#fff]">
				<view >
					<view class="max-w-[200rpx] truncate leading-[1] text-[30rpx] text-[#fff]"  :style="{ fontSize: diyComponent.fontSize * 2 + 'rpx',color : diyComponent.textColor,fontWeight: (diyComponent.fontWeight == 'normal' ? 500 : diyComponent.fontWeight) }">
						次卡管理
					</view>
				</view>
				<view class="flex items-center">
					<view  @click="redirect({ url: '/addon/home_service/user/pages/card/my_card'})" :style="{color : diyComponent.textColor}" class="flex items-center">
						<text class="max-w-[200rpx] truncate text-[24rpx]" >全部</text>
						<text class="nc-iconfont nc-icon-youV6xx text-[24rpx]"></text>
					</view>
				</view>
			</view>
		</view>
		<view class="bg-[#fff] flex p-[24rpx] rounded-[20rpx] box-border justify-between" @click="redirect({url:'/addon/home_service/user/pages/card/use_card',param:{id:cardInfo.id}})">
			<view class="w-[100rpx] h-[100rpx] mr-[15rpx]">
				<up-image width="100%" height="100rpx" radius="5" :src="img(cardInfo.card_image)" mode="aspectFill">
					<template #error>
						<u-icon name="photo" color="#999" size="50"></u-icon>
					</template>
				</up-image>
			</view>
			<view class="w-[82%] flex flex-col justify-around">
				<view class="flex items-center">
					<view class="multi-hidden flex-1 whitespace-nowrap mr-[15rpx] pb-[5rpx] text-[30rpx]">{{cardInfo.card_name || '次卡名称'}}</view>
					<view class="text-[#999] text-[24rpx]" v-if="cardInfo.expire_time">{{timeStampTurnTime(timeTurnTimeStamp(cardInfo.expire_time),'yearMonthDay')}}到期</view>
					<view class="text-[#999] text-[24rpx]" v-else>期限:永久</view>
				</view>
				<view class="mt-auto flex justify-between items-center">
					<view class="text-[28rpx]  ">
						<!-- <text>
							￥{{goodsPrice(item) }}
						</text> -->
						<text class="!text-[var(--price-text-color)] font-600 price-font text-[35rpx]">
							<text class="text-[20rpx]">￥</text>{{cardInfo.price || '100.00'}}
						</text>
						<text class="text-[22rpx] text-[#999] line-through ml-[5rpx]">
							￥{{cardInfo.original_price || '商品名称'}}
						</text>
					</view>
					<text class="text-[26rpx] text-[#ff0000]">
						剩余{{cardInfo.remain_count || 10}}次
					</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script lang="ts" setup>
	import { ref, computed, watch,onMounted } from 'vue';
	import useDiyStore from '@/app/stores/diy';
	import { img,redirect ,timeTurnTimeStamp,timeStampTurnTime} from '@/utils/common';
	import { getFirstCard } from '@/addon/home_service/api/diy'
	const props = defineProps(['component', 'index']);
	const diyStore = useDiyStore();
	const diyComponent = computed(() => {
		if (diyStore.mode == 'decorate') {
			return diyStore.value[props.index];
		} else {
			return props.component;
		}
	})

	onMounted(() => {
		refresh();
		getFirstCardFn()
	});
	
	const cardInfo = ref({})
	const getFirstCardFn = () =>{
		getFirstCard().then((res)=>{
			cardInfo.value = res.data
		})
	}
	// 商品价格
	let goodsPrice = (data:any) =>{
		let price = "0.00";
		if(data.member_discount && getToken() && data.goodsSku.member_price != data.goodsSku.price){
			price = data.goodsSku.member_price || '0.00' // 会员价
		}else{
			price = data.goodsSku.price || '0.00'
		}
		return parseFloat(price).toFixed(2);
	}
	
	watch(() => diyComponent.value, (newValue, oldValue) => {
      refresh();
  },{deep: true})

	const refresh = () => {
      // 装修模式
      if (diyStore.mode == 'decorate') {
      }
  }

	const warpCss = computed(() => {
	    let style = '';
	    if(diyComponent.value.componentStartBgColor) {
	        if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`;
	        else style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
	    }
		if (diyComponent.value.bgUrl) {
			style += 'background-image:url(' + img(diyComponent.value.bgUrl) + ');';
			style += 'background-size: 100% 100%;';
			style += 'background-repeat: no-repeat;';
		}
		if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		return style;
	})
	

	const toList = (status:any) => {
		redirect({ url: '/addon/home_service/user/pages/order/list', param: { order_status: status } })
	}
</script>

<style>
</style>
