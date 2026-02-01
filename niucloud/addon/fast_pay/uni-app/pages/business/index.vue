<template>
	<view :style="themeColor()">
	</view>
	<up-popup :round="10" :show="showpay" @close="close" @open="open" mode="center">
		<view class="cardloading">
			<up-loading-icon class="mt-4 mb-2" show="true" mode="circle" inactive-color="#29DB6F"
				timing-function="linear"></up-loading-icon>
			<view>正在支付</view>
		</view>
	</up-popup>
</template>

<script setup lang="ts">
	import { ref } from 'vue';
	import { t } from '@/locale'
	import { onLoad } from '@dcloudio/uni-app'
	import { getPayInfo, partnerpay } from '@/addon/fast_pay/api/pay'
	import { img, redirect, isWeixinBrowser, moneyFormat } from '@/utils/common'
	import wechat from '@/utils/wechat'
	const payInfo = ref<AnyObject | null>(null)
	const repeat = ref(false)
	const type = ref('')
	const loading = ref(false)
	const showpay = ref(true)
	const trade_type = ref()
	const trade_id = ref()
	let requestNum = 0
	const getPayInfoEvent = async (trade_type, trade_id) => {
		getPayInfo(trade_type, trade_id)
			.then((res : any) => {
				let { data } = res
				payInfo.value = data
				if (uni.$u.test.isEmpty(data)) {
					uni.showToast({ title: t('pay.notObtainedInfo'), icon: 'none' })
					return
				}
				if (data.money == 0) {
					toPayResult()
					return
				}

				type.value = data.pay_type_list[0] ? data.pay_type_list[0].key : ''
				repeat.value = false
				goPay()
			})
			.catch(() => {
				repeat.value = false
				showpay.value = false
				toPayResult()
			})
	}
	//调起微信支付
	const goPay = async () => {
		partnerpay({
			trade_type: payInfo.value?.trade_type,
			trade_id: payInfo.value?.trade_id,
			type: 'fastpay_partner_wechatpay',
			openid: uni.getStorageSync('openid') || ''
		}).then(res => {

			// #ifndef H5
			uni.requestPayment({
				provider: 'wxpay',
				...res.data,
				success: (res) => {
					toPayResult()
				},
				fail: (res) => {
					loading.value = false
				}
			})
			// #endif
			// #ifdef H5
			if (isWeixinBrowser()) {
				res.data.timestamp = res.data.timeStamp
				delete res.data.timeStamp
				wechat.pay({
					...res.data,
					success: () => {
						toPayResult()
					},
					cancel: () => {
						loading.value = false
					}
				})
			} else {
				uni.setStorageSync('paymenting', { trade_type: payInfo.value?.trade_type, trade_id: payInfo.value?.trade_id })
				location.href = res.data.h5_url
			}
			// #endif
			showpay.value = false
			// checkPayInfo()
			//toPayResult()

		}).catch(() => {
			loading.value = false
		})
	}


	const toPayResult = () => {
		redirect({ url: '/addon/fast_pay/pages/partner/result', param: { trade_id: trade_id.value, trade_type: trade_type.value }, mode: 'redirectTo' })
	}

	onLoad((option) => {
		if (option.trade_type && option.trade_id) {
			trade_type.value = option.trade_type
			trade_id.value = option.trade_id
			getPayInfoEvent(option.trade_type, option.trade_id)
		} else {
			uni.$u.toast('参数错误')
			return
		}

	})
</script>
<style lang="scss" scoped>
	.cardloading {
		background: rgba(246, 255, 243, 0.2);
		padding: 20px 40px;
		border-radius: 10px;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;

		view {
			margin-top: 10px;
			font-size: 14px;
			color: #999;
		}
	}
</style>