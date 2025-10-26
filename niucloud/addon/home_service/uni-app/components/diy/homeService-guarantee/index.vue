<template>
	<view :style="warpCss" class="p-[25rpx] pb-[50rpx]">
		<view class="flex justify-between items-center">
			<view class="flex items-center">
				<image class="w-[30rpx] h-[30rpx] mr-[5rpx]" :src="img('addon/home_service/diy/index/phone-cion.png')" />
				<text class="text-[22rpx]">12小时内专属回电</text>
			</view>
			<view class="flex items-center">
				<image class="w-[30rpx] h-[30rpx] mr-[5rpx]" :src="img('addon/home_service/diy/index/fast-icon.png')" />
				<text  class="text-[22rpx]">最快36分钟送达</text>
			</view>
			<view class="flex items-center">
				<image class="w-[30rpx] h-[30rpx] mr-[5rpx]" :src="img('addon/home_service/diy/index/money-cion.png')" />
				<text class="text-[22rpx]">乱收费双倍赔</text>
			</view>
		</view>
	</view>
</template>

<script lang="ts" setup>
	import { ref, computed, watch,onMounted } from 'vue';
	import useDiyStore from '@/app/stores/diy';
	import { img,redirect } from '@/utils/common';
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
	});
	
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
			style += 'background-size: 100%;';
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
