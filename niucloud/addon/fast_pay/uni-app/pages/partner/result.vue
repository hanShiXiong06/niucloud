<template>
	<view :style="themeColor()">
		<view class="w-screen h-screen flex flex-col items-center" v-if="payInfo">
			<view class="flex-1 flex flex-col items-center w-full pt-[100rpx]">
				<text class="iconfont text-2xl"
					:class="payInfo.status==2 ? 'text-primary iconduigou' : 'iconzhifushibai text-[#29DB6F]'"></text>
				<view class=" text-sm">{{ payInfo.status == 2 ? t('pay.paySuccess') : t('pay.payFail') }}</view>
				<view class="text-xl font-bold pt-[30rpx]">
					<text class="text-base">{{ t('currency') }}</text>
					<text>{{ moneyFormat(payInfo.money) }}</text>
				</view>
			</view>
			<view class="pb-[200rpx] w-[240rpx]">
				<up-button type="primary" :text="payInfo.status == 2 ? t('complete') : t('close')" :plain="true"
					@click="complete"></up-button>
			</view>
		</view>
		<up-modal :show="loading" :showCancelButton="true" :confirmText="t('pay.completePay')"
			:cancelText="t('pay.incompletePay')" @cancel="complete" confirmColor="var(--primary-color)">
			<view class="py-[20rpx]">
				<up-loading-icon :text="t('pay.getting')" textSize="16" mode="circle" :vertical="true"></up-loading-icon>
			</view>
		</up-modal>
	</view>
</template>

<script setup lang="ts">
	import { ref } from 'vue'
	import { onLoad } from '@dcloudio/uni-app'
	import { getPayInfo as getPayInfoApi } from '@/addon/fast_pay/api/pay'
	import { getConfig } from '@/addon/fast_pay/api/config'
	import { t } from '@/locale'
	import { diyRedirect, currRoute, getToken, redirect, moneyFormat } from '@/utils/common';
	import { useLogin } from '@/hooks/useLogin';
	import { getFirstPage } from '@/utils/pages'
	const openVideoIndex = (finderUserName) => {
		uni.openChannelsUserProfile({
			finderUserName: finderUserName
		});
	}
	const openVideoVideo = (finderUserName, feedId) => {
		uni.openChannelsActivity({
			finderUserName: finderUserName,
			feedId: feedId
		});
	}
	const toRedirect = (data : any) => {
		if (Object.keys(data).length) {
			if (!data.name) return;
			if (currRoute() == 'app/pages/member/index' && !getToken()) {
				useLogin().setLoginBack({ url: data.url })
				return;
			}
			diyRedirect(data);
		}
	}
	const payInfo = ref<AnyObject | null>(null)
	const loading = ref(false)
	const trade_type = ref()
	const trade_id = ref()
	let requestNum = 0
	const config = ref()
	const getConfigInfo = async () => {
		const res = await getConfig()
		config.value = res.data
	}
	onLoad((data : any) => {
		getConfigInfo()
		trade_id.value = data.trade_id
		trade_type.value = data.trade_type
		getPayInfo()
	})

	/**
	 * 获取支付信息
	 */
	const getPayInfo = () => {
		getPayInfoApi(trade_type.value, trade_id.value).then((res : responseResult) => {
			if (!uni.$u.test.isEmpty(res.data)) {
				if (res.data.status == 1 && requestNum < 60) {
					loading.value = true
					requestNum++
					setTimeout(() => {
						getPayInfo()
					}, 1000)
					return
				}
				payInfo.value = res.data
				loading.value = false
				uni.setNavigationBarTitle({
					title: payInfo.value.status == 2 ? t('pay.paySuccess') : t('pay.payFail')
				})

				if (payInfo.value.status == 2) {
					//支付成功执行方法
					// #ifdef MP-WEIXIN
					if (config.value.type == 0 && config.value.finderUserName != '') {
						openVideoIndex(config.value.finderUserName)
					}
					if (config.value.type == 1 && config.value.finderUserName != '' && config.value.feedId != '') {
						openVideoVideo(config.value.finderUserName, config.value.feedId)
					}
					if (config.value.type == 2 && config.value.page != '') {
						toRedirect(config.value.page)
					}
					if (config.value.page != '') {
						toRedirect(config.value.page)
					}
					// #endif
					// #ifdef H5
					if (config.value.page != '') {
						toRedirect(config.value.page)
					}
					// #endif
				}


			}
		})
	}

	const complete = () => {
		const payReturn = decodeURIComponent(uni.getStorageSync('payReturn'))
		if (payReturn) redirect({ url: payReturn, mode: 'reLaunch' })
		else redirect({ url: getFirstPage(), param: { code: payInfo.value?.out_trade_no }, mode: 'reLaunch' })
	}
</script>

<style lang="scss" scoped></style>