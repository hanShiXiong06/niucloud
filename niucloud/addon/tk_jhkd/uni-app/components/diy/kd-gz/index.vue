<template>
	<view v-if="showgz == true || diyStore.mode == 'decorate'" class="fixed-tip"
		:style="{ bottom: `${bottomDistance}px` }">
		<view
			class="flex items-center justify-between !bg-[#8f8f8f] ml-4 mr-4 mb-2 leading-4 rounded-full px-2 py-2 pl-2">
			<view class="flex items-center">
				<view>
					<up-icon @click="showgz = false" name="close" color="#ffffff" size="18"></up-icon>
				</view>
				<view class="ml-2 text-white text-[28rpx]">{{ diyComponent.text }}</view>
			</view>
			<view>
				<button
					class="px-3 py-2 flex items-center justify-center text-[#ffffff] bg-[#0057FE] rounded-[29rpx] text-[28rpx]"
					:style="{ background: diyComponent.btbackground, }" @click="gzEvent()">
					去关注
				</button>
			</view>
		</view>
	</view>
</template>



<script setup lang="ts">

import { ref, computed, watch, onMounted, nextTick, getCurrentInstance } from 'vue';

import useDiyStore from '@/app/stores/diy';

import { goto } from '@/addon/tk_jhkd/utils/ts/goto';

import { img, diyRedirect } from '@/utils/common';
const showgz = ref(true)

const bottomDistance = ref(0)

// 计算底部距离

const calculateBottomDistance = () => {

	// #ifdef H5
	bottomDistance.value = 50 // H5端底部导航栏高度
	// #endif
	// #ifdef MP-WEIXIN
	const systemInfo = uni.getSystemInfoSync()
	// 计算实际底部安全距离加上导航栏高度
	bottomDistance.value = systemInfo.safeAreaInsets?.bottom + 56
	// #endif
	// #ifdef APP-PLUS
	const systemInfo = uni.getSystemInfoSync()
	bottomDistance.value = systemInfo.safeAreaInsets?.bottom + 50
	// #endif

}
const gzEvent = () => {
	if (diyComponent.value.link && diyComponent.value.link.title != '') {
		// 设置缓存时间为当前时间戳
		const currentTime = Date.now();
		uni.setStorageSync('showgz', {
			value: true,
			timestamp: currentTime,
			expireTime: 60 * 60 * 24 * diyComponent.value.min // 3分钟的毫秒数
		})
		diyRedirect(diyComponent.value.link)
		showgz.value = false
	} else {
		uni.$u.toast('商家还没配置消息推送公众号')
	}
}

onMounted(() => {
	calculateBottomDistance()
	// 检查缓存是否过期
	const gzCache = uni.getStorageSync('showgz')
	if (gzCache) {
		const currentTime = Date.now()
		if (currentTime - gzCache.timestamp > gzCache.expireTime) {
			// 缓存已过期，显示提示
			showgz.value = true
			// 清除过期缓存
			uni.removeStorageSync('showgz')
		} else {
			showgz.value = false
		}
	} else {
		showgz.value = true
	}
})
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

		query.select('.diy-rich-text').boundingClientRect((data: any) => {

			height.value = data.height;

		}).exec();

	})

}

</script>



<style lang="scss" scoped>
.fixed-tip {

	@apply fixed left-0 right-0 z-50;

}
</style>