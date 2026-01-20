<template>
	<view :style="warpCss">
		<view :style="maskLayer"></view>
		<view class="" :style="{ padding:diyComponent.padding* 2 + 'rpx', }">
			<up-row gutter="12" class="flex item-center">
				<up-col span="2">
					<view class="">
						<text class="tag">寄</text>
					</view>

					<view class="mt-[4rpx] mb-[4rpx]">
						<up-line :color="diyComponent.songbackground" direction="col" length="98rpx"
							border-style="dotted"></up-line>
					</view>
					<view class="">
						<text class="tag" style="color: #ffffff;"
							:style="{ background:diyComponent.songbackground, }">收</text>
					</view>

				</up-col>
				<up-col span="10">
					<view class="fb flex item-center">
						<view class="w-[380rpx]">
							<text class="font-bold" :style="{ color:diyComponent.qsfontcolor, }">从哪里取？</text>
							<text class="tk-sltext" :style="{ color:diyComponent.slfontcolor, }">请选择取件地址</text>
						</view>
						<view class="flex items-center">
							<text class="bt text-sm"
								:style="{ background:diyComponent.btbackground,color:diyComponent.btfontcolor, }"
								@click="goto('/addon/tk_jhkd/pages/ordersubmit')">{{diyComponent.btname}}</text>
						</view>

					</view>
					<view class="fb flex item-center mt-[38rpx]">
						<view class="w-[380rpx]">
							<view class="font-bold" :style="{ color:diyComponent.qsfontcolor, }">送去哪里？</view>
							<view class="tk-sltext" :style="{ color:diyComponent.slfontcolor, }">请选择送件地址</view>

						</view>
						<view class="flex items-center">
							<text class="bt text-sm"
								:style="{ background:diyComponent.btbackground,color:diyComponent.btfontcolor, }"
								@click="goto('/addon/tk_jhkd/pages/ordersubmit')">{{diyComponent.btname}}</text>
						</view>

					</view>
				</up-col>
			</up-row>
		</view>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed, watch, onMounted, nextTick, getCurrentInstance } from 'vue';
	import useDiyStore from '@/app/stores/diy';
	import { goto } from '@/addon/tk_jhkd/utils/ts/goto';
	import { img } from '@/utils/common';

	const props = defineProps(['component', 'index', 'pullDownRefreshCount']);

	const diyStore = useDiyStore();

	const diyComponent = computed(() => {
		if (diyStore.mode == 'decorate') {
			return diyStore.value[props.index];
		} else {
			return props.component;
		}
	})

	const warpCss = computed(() => {
		var style = '';
		style += 'position:relative;';
		if (diyComponent.value.componentStartBgColor) {
			if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`;
			else style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
		}

		if (diyComponent.value.componentBgUrl) {
			style += `background-image:url('${img(diyComponent.value.componentBgUrl)}');`;
			style += 'background-size: cover;background-repeat: no-repeat;';
		}

		if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		return style;
	})

	// 背景图加遮罩层
	const maskLayer = computed(() => {
		var style = '';
		if (diyComponent.value.componentBgUrl) {
			style += 'position:absolute;top:0;width:100%;';
			style += `background: rgba(0,0,0,${diyComponent.value.componentBgAlpha / 10});`;
			style += `height:${height.value}px;`;

			if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
			if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
			if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
			if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		}

		return style;
	});

	watch(
		() => props.pullDownRefreshCount,
		(newValue, oldValue) => {
			// 处理下拉刷新业务
		}
	)

	onMounted(() => {
		refresh();
		// 装修模式下刷新
		if (diyStore.mode == 'decorate') {
			watch(
				() => diyComponent.value,
				(newValue, oldValue) => {
					if (newValue && newValue.componentName == 'RichText') {
						refresh();
					}
				}
			)
		}
	});

	const instance = getCurrentInstance();
	const height = ref(0)

	const refresh = () => {
		nextTick(() => {
			const query = uni.createSelectorQuery().in(instance);
			query.select('.diy-rich-text').boundingClientRect((data : any) => {
				height.value = data.height;
			}).exec();
		})
	}
</script>

<style lang="scss" scoped>
	@import '@/addon/tk_jhkd/utils/styles/common.scss';

	.tag {
		background: #dddddd;
		padding: 6rpx 12rpx;
		text-align: center;
		border-radius: 8rpx;
		font-weight: bold;
	}

	.bt {
		padding: 4rpx 12rpx;
		border-radius: 12rpx;
		// border: #effeed solid 2rpx;
	}
</style>