<template>
	<view :style="warpCss" class="p-[20rpx]" v-if="goodsList.length  || diyStore.mode == 'decorate'">
		<view class="flex justify-between">
			<view @click="redirect({url:'/addon/home_service/user/pages/card/list'})">
				<image
				    :src="img(diyComponent.leftBg)" mode="heightFix" class="w-[180rpx] h-[265rpx]"
				    />
			</view>
			<view class="grid grid-cols-3 gap-3 px-[20rpx] bg-[#fff] py-[25rpx] rounded-[20rpx] box-border flex-1 ml-[25rpx]" v-if="goodsList.length">
				<view class="flex flex-col justify-between" v-for="(item,index) in goodsList" @click="redirect({url:'/addon/home_service/user/pages/card/detail',param:{card_id:item.card_id}})">
					<block v-if="index < 3">
						<view>
							<up-image radius="5" width="130rpx" height="130rpx" :src="img(item.card_image || '')" model="aspectFill">
							    <template #error>
							        <image class="w-[140rpx] h-[140rpx] overflow-hidden" :src="img('static/resource/images/diy/figure.png')" mode="aspectFill" />
							    </template>
							</up-image>
						</view>
						<view class="text-[28rpx] flex items-center justify-between w-[140rpx] mt-[15rpx]">
							<view class="max-w-[140rpx] text-[26rpx] truncate ">
								{{item.card_name}}
							</view>
						</view>
						<view class="text-[26rpx] text-[var(--price-text-color)] price-font block mt-[15rpx]">
							{{item.price}}元
						</view>
					</block>
				</view>
			</view>
			
			<view class="grid grid-cols-3 gap-3 px-[20rpx] bg-[#fff] py-[25rpx] rounded-[20rpx] box-border flex-1 ml-[25rpx]" v-if="!goodsList.length">
				<view class="flex flex-col" v-for="(item,index) in modeuleData" >
					<block v-if="index < 3">
						<view>
							<up-image radius="5" width="130rpx" height="130rpx" :src="img(item.card_image || '')" model="aspectFill">
							    <template #error>
							        <image class="w-[140rpx] h-[140rpx] overflow-hidden" :src="img('static/resource/images/diy/figure.png')" mode="aspectFill" />
							    </template>
							</up-image>
						</view>
						<view class="text-[28rpx] flex items-center justify-between w-[140rpx] mt-[15rpx]">
							<view class="max-w-[140rpx] text-[26rpx] truncate ">
								{{item.card_name}}
							</view>
						</view>
						<view class="text-[26rpx] text-[var(--price-text-color)] price-font block mt-[15rpx]">
							{{item.price}}元
						</view>
					</block>
				</view>
			</view>
		</view>
	</view>
</template>

<script lang="ts" setup>
	import { ref, computed, watch,onMounted } from 'vue';
	import useDiyStore from '@/app/stores/diy';
	import { getCardComponents } from '@/addon/home_service/api/diy';
	import { img,redirect } from '@/utils/common';
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	const props = defineProps(['component', 'index']);
	const diyStore = useDiyStore();
	const diyComponent = computed(() => {
		if (diyStore.mode == 'decorate') {
			return diyStore.value[props.index];
		} else {
			return props.component;
		}
	})
	
	const goodsList = ref<Array<any>>([]);
	const getGoodsListFn = () => {
	    let data = {
	      source: diyComponent.value.source,
	      num: (diyComponent.value.source == 'all' || diyComponent.value.source == 'category') ? diyComponent.value.num : '',
	      card_ids:diyComponent.value.source == 'all' ? '' : diyComponent.value.goods_ids,
		  city_id:systemStore.diyAddressInfo?.city_id
	    }
	    getCardComponents(data).then((res) => {
	        goodsList.value = res.data;
	    });
	}
	
	const modeuleData = ref([
		{
			goods_cover_thumb_mid:'',
			card_name:'商品名称',
			price:'100.00'
		},
		{
			goods_cover_thumb_mid:'',
			card_name:'商品名称',
			price:'100.00'
		},
		{
			goods_cover_thumb_mid:'',
			card_name:'商品名称',
			price:'100.00'
		}
	])
	onMounted(() => {
		refresh();
	});
	
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
	  getGoodsListFn();
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
