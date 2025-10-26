<template>
	<u-tabbar :value="props.value" @change="tabChange" :fixed="true" :placeholder="true" :safeAreaInsetBottom="true" activeColor="#000"
		class="!pt-[20rpx]">
		<u-tabbar-item text="任务"
			:icon="props.value == 0 ? img('/addon/home_service/store/store_index_active.png') : img('/addon/home_service/store/store-index.png')"></u-tabbar-item>
		<u-tabbar-item text="门店"
			:icon="props.value == 1 ? img('/addon/home_service/store/store-icon.png') : img('/addon/home_service/store/store-two.png')"></u-tabbar-item>
		<u-tabbar-item text="技工"
			:icon="props.value == 2 ? img('/addon/home_service/store/technican-active.png') : img('/addon/home_service/store/technican.png')"></u-tabbar-item>
		<u-tabbar-item text="我的"
			:icon="props.value == 3 ? img('/addon/home_service/store/member-active.png') : img('/addon/home_service/store/store-my.png')"></u-tabbar-item>
	</u-tabbar>
</template>

<script setup lang="ts">
	import { reactive, computed, watch, nextTick, getCurrentInstance } from 'vue'
	import { redirect, currRoute, currShareRoute, img } from '@/utils/common'
	import useConfigStore from '@/stores/config'
	import { cloneDeep } from 'lodash-es'

	const props = defineProps({
		color: {
			type: Object,
			default: () => {
				return {
					backgroundColor: '', // 背景色
					textColor: '', // 文字颜色
					textHoverColor: '', // 文字选中颜色
				};
			}
		},
		value: {
			type: Number,
			default: 0
		}
	})
	const tabChange = (e : any) => {
		if (e == 0) {
			redirect({ url: '/addon/home_service/store/pages/index',mode: 'reLaunch' })
		}
		if (e == 1) {
			redirect({ url: '/addon/home_service/store/pages/store/index' ,mode: 'reLaunch'})
		}
		if (e == 2) {
			redirect({ url: '/addon/home_service/store/pages/technician/index',mode: 'reLaunch' })
		}
		if (e == 3) {
			redirect({ url: '/addon/home_service/store/pages/member/index',mode: 'reLaunch' })
		}
	}
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style scoped>
	/deep/ .u-tabbar__content__item-wrapper{
		padding-top: 15rpx;
		z-index:9999;
		position:relative;
	}
	/deep/ .u-tabbar--fixed{
		z-index:99 !important;
	}
</style>