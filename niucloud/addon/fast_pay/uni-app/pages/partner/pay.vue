<template>
	<view :style="themeColor()">
		<view class="tk-card flex justify-between items-center" v-if="config">
			<view>
				<view class="font-bold text-[30rpx] mt-1">{{config.business_name}}</view>
				<view class="text-[#21231E] text-[18rpx] mt-2">付款给商户</view>
			</view>
			<view>
				<up-icon :name="img(config.business_logo)" size="42"></up-icon>
			</view>
		</view>
		<view class="p-4 pl-8 pr-8">
			<view>
				<view>金额</view>
				<view class="flex items-center mt-3 mb-2">
					<view class="text--[48rpx] font-bold mr-2">￥</view>
					<input type="digit"
						class="h-[76rpx] leading-[76rpx] pl-[10rpx] flex-1 font-bold text-[54rpx] bg-[#fff]"
						v-model="price" maxlength="7" placeholder-class="apply-price" :adjust-position="false"
						@input="onInput" autofocus />
				</view>
				<view class="line-box"></view>
				<view class="mt-4 text-[#297bff]" @click="open()">添加备注</view>
			</view>
		</view>
		<view class="h-[66rpx]"></view>
		<view class="b-tabbar safe-area-inset-bottom flex justify-between">

			<view class="flex flex-1">
				<button class="w-[100%] !h-[72rpx] leading-[72rpx] text-[26rpx] rounded-[50rpx]"
					style="color:#ffffff;backgroundColor:#07C160;borderColor:#07C160" @click="goPay()">立即支付</button>
			</view>
		</view>
	</view>
	<up-popup :round="10" :show="showremark" @close="close" @open="open" mode="bottom">
		<view class="pl-4 pr-4 mb-4 mt-4">
			<view class="font-bold text-[28rpx]">添加备注</view>
			<view class="flex items-center mt-4">
				<up-input placeholder=" " border="surround" v-model="remark" :clearable="true"></up-input>
			</view>
			<view class="flex mt-4 ">
				<button class="w-[100%] !h-[72rpx] leading-[72rpx] text-[26rpx] rounded-[10rpx] mr-2"
					style="color:#000000;backgroundColor:#d8d8d8;borderColor:#29DB6F" @click="close()">取消</button>
				<button class="w-[100%] !h-[72rpx] leading-[72rpx] text-[26rpx] rounded-[10rpx]"
					style="color:#ffffff;backgroundColor:#29DB6F;borderColor:#29DB6F" @click="close()">确认</button>
			</view>
		</view>
	</up-popup>
	<pay ref="payRef" @close="payLoading = false">
	</pay>
</template>

<script setup lang="ts">
	import { ref, computed } from 'vue';
	import { t } from '@/locale'
	import { onLoad } from '@dcloudio/uni-app'
	import { img, redirect, isWeixinBrowser, moneyFormat, getToken } from '@/utils/common'
	import { createOrder } from '@/addon/fast_pay/api/pay'
	import { getConfig } from '@/addon/fast_pay/api/config'
	import { useLogin } from '@/hooks/useLogin';
	import useMemberStore from '@/stores/member'
	const memberStore = useMemberStore()
	const userInfo = computed(() => memberStore.info)

	const price = ref('')
	const remark = ref('')
	const showremark = ref(false)
	const payRef = ref(null)
	const payLoading = ref(false)
	const config = ref()
	const getConfigInfo = async () => {
		const res = await getConfig()
		config.value = res.data
	}
	getConfigInfo()
	const close = () => {
		showremark.value = false
	}
	const open = () => {
		showremark.value = true
	}

	const onInput = (event) => {
		let value = event.detail.value;

		// 检查金额是否为有效数字
		if (isNaN(value)) {
			uni.showToast({
				title: '请输入有效金额',
				icon: 'none'
			});
			price.value = '';
			return;
		}

		// 限制只能输入最多两位小数
		if (value.indexOf('.') != -1) {
			if (value.split('.')[1].length > 2) {
				uni.showToast({
					title: '最多输入两位小数',
					icon: 'none'
				});
				price.value = '';
				return;
			}
		}


		// 如果金额以小数点开头，则补充0
		if (value.startsWith('.')) {
			value = '0' + value;
		}

		// 检查金额是否大于等于0
		if (parseFloat(value) < 0) {
			uni.showToast({
				title: '金额必须大于或等于0',
				icon: 'none'
			});
			price.value = '';
			return;
		}

		price.value = value;
	}


	const goPay = async () => {
		if (!price.value) {
			uni.showToast({
				title: '请输入金额',
				icon: 'none'
			})
			return
		}
		// 检测是否登录
		if (!userInfo.value) {
			let pid = uni.getStorageSync('pid');
			if (pid && pid > 0) {
				useLogin().setLoginBack({ url: '/addon/fast_pay/pages/pay/pay?mid=' + pid })
				return false
			} else {
				useLogin().setLoginBack({ url: '/addon/fast_pay/pages/pay/pay' })
				return false
			}
		}
		// 再次验证金额
		if (!/^(0|[1-9]\d*)(\.\d{1,2})?$/.test(price.value)) {
			uni.showToast({
				title: '金额必须大于0且最多只能有两位小数',
				icon: 'none'
			});
			price.value = ''; // 验证失败，清空价格
			return;
		}

		const res = await createOrder({
			price: price.value,
			remark: remark.value
		})
		payLoading.value = true;
		payRef.value?.open(res.data.trade_type, res.data.trade_id, '/addon/fast_pay/pages/pay/pay');
	}

	onLoad((options) => {
		if (options.price) {
			price.value = options.price
		}
		if (!getToken()) {
			const login = useLogin()
			// 第三方平台自动登录
			// #ifdef MP
			login.getAuthCode()
			// #endif
			// #ifdef H5
			if (isWeixinBrowser()) {
				data.query.code ? login.authLogin(data.query.code) : login.getAuthCode('snsapi_userinfo')
			}
			// #endif
		}
	})
</script>
<style lang="scss" scoped>
	.tk-card {
		background-color: rgba(252, 249, 249, 0.9);
		margin: 24rpx;
		border-radius: 12rpx;
		padding: 24rpx;
		box-shadow: 0 1px 1px 0 rgba(234, 234, 234, 0.2), 0 2px 2px 0 rgba(231, 231, 231, 0.2);
	}

	.cardloading {
		background: rgba(246, 255, 243, 0.2);
		padding: 10px 40px;
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

	.line-box {
		background-color: #EEEEEE;
		height: 3rpx;
		width: 100%;
	}

	.b-tabbar {
		position: fixed;
		bottom: 12rpx;
		left: 0;
		right: 0;
		margin: 0rpx 24rpx;
		border-radius: 12rpx;
		padding: 12rpx;
		background: rgba(245, 250, 245, 0.8);
	}



	.b-tabbar-back {
		background: rgba(245, 250, 245, 0.9);
		box-shadow: inset 2px 2px 4px rgba(0, 0, 0, 0.5), 2px 2px 4px rgba(0, 0, 0, 0.5);
		border-radius: 12rpx;
	}
</style>