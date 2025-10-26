<template>
	<view :style="warpCss" class="p-[10rpx]" v-if="goodsList.length  || diyStore.mode == 'decorate'">
		<view class="diy-text relative ">
			<view class=" pt-[10rpx] pb-[20rpx] flex items-center justify-between text-[#fff]">
				<view @click="diyStore.toRedirect(diyComponent.link)">
					<view class="truncate leading-[1] text-[28rpx] pl-[14rpx] font-bold text-[#fff]"  :style="{ fontSize: diyComponent.fontSize * 2 + 'rpx',color : diyComponent.textColor,fontWeight: (diyComponent.fontWeight == 'normal' ? 500 : diyComponent.fontWeight) }">
						新人抢购
						<!-- <text class="text-[26rpx] ml-[10rpx]">购买次卡更划算</text> -->
					</view>
				</view>
				<view class="flex items-center">
					<view  @click="redirect({ url: '/addon/home_service/user/pages/goods/list?goods_ids='+diyComponent.goods_ids})" :style="{color : diyComponent.textColor}" class="flex items-center">
						<text class="max-w-[200rpx] truncate text-[24rpx] mr-[5rpx] leading-4" >更多</text>
						<text class="nc-iconfont nc-icon-youV6xx text-[24rpx] leading-4"></text>
					</view>
				</view>
			</view>
		</view>
		<view class="bg-[#fff] grid grid-cols-4 gap-4 px-[20rpx] py-[25rpx] rounded-[20rpx] box-border" v-if="goodsList.length">
			<view class="flex flex-col" v-for="(item,index) in goodsList" v-show="index < 4" @click="redirect({url:'/addon/home_service/user/pages/goods/detail',param:{sku_id:item.goodsSku.sku_id}})" >
				<view>
					<up-image radius="5" width="140rpx" height="140rpx" :src="img(item.goods_cover_thumb_mid || '')" model="aspectFill">
					    <template #error> 
					        <image class="w-[140rpx] h-[140rpx] overflow-hidden" :src="img('static/resource/images/diy/figure.png')" mode="aspectFill" />
					    </template>
					</up-image>
				</view>
				<view class="text-[28rpx] flex items-center justify-between w-[140rpx] mt-[15rpx]">
					<view class="max-w-[140rpx] text-[26rpx] truncate ">
						{{item.goods_name}}
					</view>
					<view class="iconfont iconarrow-right !text-[24rpx]">
					</view>
				</view>
				<view class="text-[26rpx] text-[var(--price-text-color)] price-font block mt-[15rpx]">
					{{item.member_price|| item.price}}元
				</view>
			</view>
		</view>
		
		<view class="bg-[#fff] grid grid-cols-4 gap-4 px-[20rpx] py-[25rpx] rounded-[20rpx] box-border" v-if="!goodsList.length">
			<view class="flex flex-col" v-for="(item,index) in modeuleData" v-show="index < 4" @click="redirect({url:'/addon/home_service/user/pages/goods/detail',param:{sku_id:item.goodsSku.sku_id}})">
				<view>
					<up-image radius="5" width="140rpx" height="140rpx" :src="img(item.goods_cover_thumb_mid || '')" model="aspectFill">
					    <template #error> 
					        <image class="w-[140rpx] h-[140rpx] overflow-hidden" :src="img('static/resource/images/diy/figure.png')" mode="aspectFill" />
					    </template>
					</up-image>
				</view>
				<view class="text-[28rpx] font-bold flex items-center justify-between w-[140rpx] mt-[15rpx]">
					<view class="max-w-[140rpx] text-[26rpx] truncate ">
						{{item.goods_name}}
					</view>
					<view class="iconfont iconarrow-right !text-[24rpx]">
					</view>
				</view>
				<view class="text-[26rpx] text-[var(--price-text-color)] price-font block mt-[15rpx]">
					{{item.member_price|| item.price}}元
				</view>
			</view>
		</view>
	</view>
</template>

<script lang="ts" setup>
	import { ref, computed, watch,onMounted } from 'vue';
	import useDiyStore from '@/app/stores/diy';
	import { getGoodsComponents } from '@/addon/home_service/api/diy';
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
	const modeuleData = ref([
		{
			goods_cover_thumb_mid:'',
			goods_name:'商品名称',
			price:'100.00'
		},
		{
			goods_cover_thumb_mid:'',
			goods_name:'商品名称',
			price:'100.00'
		},
		{
			goods_cover_thumb_mid:'',
			goods_name:'商品名称',
			price:'100.00'
		},
		{
			goods_cover_thumb_mid:'',
			goods_name:'商品名称',
			price:'100.00'
		}
	])
	const goodsList = ref<Array<any>>([]);
	const getGoodsListFn = () => {
	    let data = {
	        num: diyComponent.value.num,
	        goods_ids: diyComponent.value.goods_ids,
			city_id:systemStore.diyAddressInfo?.city_id
	    }
	    getGoodsComponents(data).then((res) => {
	        goodsList.value = res.data;
	    });
	}
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
