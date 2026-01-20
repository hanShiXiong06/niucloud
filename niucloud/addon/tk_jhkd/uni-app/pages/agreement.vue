<template>
	<view class="tk-card items-center" v-if="detailData">
		<view class="font-weight text-1xl">{{detailData.title}}</view>
		<view class="text-xs fl mt-1">
			<view class="text-xs mr-2">更新时间</view>
			<view class="">{{detailData.update_time}}</view>
		</view>
	</view>
	<view class="tk-card">
		<up-parse :content="agreementContent"></up-parse>
	</view>
</template>

<script setup lang="ts">
	import { ref } from 'vue'
	import { onLoad } from '@dcloudio/uni-app'
	import {
		getAgreement
	} from '@/addon/tk_jhkd/api/tkjhkd'

	let agreementType = ref('') // 协议类型
	const agreementContent = ref('') // 协议内容
	const detailData = ref()
	const getData = async (type) => {
		const res = await getAgreement(type)
		agreementContent.value = res.data.content
		detailData.value = res.data
	}

	onLoad((options : any) => {
		if (options.type) {
			agreementType = options.type
			getData(agreementType)
		}
	})
</script>

<style lang="scss" scoped>
	@import '@/addon/tk_jhkd/utils/styles/common.scss';
</style>