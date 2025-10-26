<template>
	<u-tabbar :value="props.value" @change="tabChange" :fixed="true" :placeholder="true" :safeAreaInsetBottom="true"
		class="!pt-[20rpx]">
		<u-tabbar-item text="抢单"
			:icon="props.value == 0 ? img('/addon/home_service/technician/index-active.png') : img('/addon/home_service/technician/tabbar-index-no.png')"></u-tabbar-item>
		<u-tabbar-item text="任务"
			:icon="props.value == 1 ? img('/addon/home_service/technician/renwu-active.png') : img('/addon/home_service/technician/renwu.png')"></u-tabbar-item>
		<u-tabbar-item text="消息"
			:icon="props.value == 2 ? img('/addon/home_service/technician/message-active.png') : img('/addon/home_service/technician/tabbar-message.png')"></u-tabbar-item>
		<u-tabbar-item text="我的"
			:icon="props.value == 3 ? img('/addon/home_service/technician/my-active.png') : img('/addon/home_service/technician/my.png')"></u-tabbar-item>
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
			redirect({ url: '/addon/home_service/technician/pages/index',mode: 'reLaunch' })
		}
		if (e == 1) {
			redirect({ url: '/addon/home_service/technician/pages/order/index',mode: 'reLaunch' })
		}
		if (e == 2) {
			redirect({ url: '/addon/home_service/technician/pages/notice/index',mode: 'reLaunch' })
		}
		if (e == 3) {
			redirect({ url: '/addon/home_service/technician/pages/member/index' ,mode: 'reLaunch'})
		}
	}
</script>

<style scoped>
	/deep/ .u-tabbar__content__item-wrapper{
		padding-top: 15rpx;
	}
	/deep/ .u-tabbar--fixed{
		z-index:99 !important;
	}
</style>