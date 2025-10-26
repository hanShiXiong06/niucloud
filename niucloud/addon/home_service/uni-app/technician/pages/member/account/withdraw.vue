<template>
	<view :style="themeColor()" class="withdraw-page">
		<!-- 顶部装饰背景 -->
		<view class="top-decoration"></view>

		<scroll-view :scroll-y="true" class="w-screen h-screen bg-[var(--page-bg-color)]"
			v-if="!pageLoading && config.is_open == 1">
			<view class="sidebar-margin pt-[var(--top-m)]">
				<!-- 余额和金额输入卡片 -->
				<view class="card-template money-card">
					<view class="font-500 text-[30rpx] text-[#333] leading-[42rpx]">{{ t('availableBalance') }}</view>
					<view class="flex items-end justify-between mt-[16rpx]">
						<view class="flex items-baseline">
							<text
								class="text-[28rpx] text-[var(--text-color-light6)] leading-[40rpx]">{{ t('currency') }}</text>
							<text
								class="text-[56rpx] font-semibold text-[#333] ml-[2rpx]">{{ moneyFormat(cashOutMoney) }}</text>
						</view>
						<view class="text-[24rpx] text-primary leading-[36rpx] withdrawal-btn" @click="allMoney">
							{{ t('allMoney') }}
						</view>
					</view>

					<!-- 金额输入区域 -->
					<view class="amount-input-container">
						<text class="pt-[4rpx] text-[34rpx] text-[#333] iconfont iconrenminbiV6xx price-font "></text>
						<input type="digit"
							class="h-[76rpx] leading-[76rpx] pl-[10rpx] flex-1 font-500 text-[44rpx]  amount-input"
							v-model="applyData.apply_money" maxlength="7"
							:placeholder="applyData.apply_money?'':(t('enterAmount'))" placeholder-class="apply-price"
							:adjust-position="false" @blur="onAmountBlur" /> <!-- 添加失焦事件 -->
						<text v-if="Number(serviceMoney)"
							class="text-[24rpx] ml-[25rpx] text-[var(--text-color-light6)] mr-[20rpx]">{{ t('fee') }}{{ serviceMoney }}</text>
						<text @click="clearMoney" v-if="applyData.apply_money"
							class="nc-iconfont nc-icon-cuohaoV6xx1 !text-[32rpx] text-[var(--text-color-light9)] clear-btn"></text>
					</view>
					<view class="pt-[12rpx] flex items-center justify-between px-[4rpx]">
						<view class="text-[24rpx] text-[var(--text-color-light6)] leading-[36rpx]">
							<text>{{ t('minAmount') }}{{ t('currency') }}{{ moneyFormat(config.min) }}</text>
							<text>{{ t('feeRate') }}{{ config.rate + '%' }}</text>
						</view>
					</view>
				</view>

				<!-- 提现方式卡片 -->
				<view class="mt-[20rpx] card-template payment-methods-card">
					<view class="font-500 text-[30rpx] text-[#333] leading-[42rpx] mb-[30rpx]">{{ t('paymentMethod') }}
					</view>

					<!-- 提现到微信 -->
					<view class="payment-method-item mb-[20rpx]"
						v-if="config.transfer_type.includes('wechatpay') && openId"
						:class="{'selected-wechat': applyData.transfer_type == 'wechatpay'}" @click="transferWeixin">
						<view>
							<image class="h-[60rpx] w-[60rpx] align-middle"
								:src="img('static/resource/images/member/apply_withdrawal/wechat.png')" mode="widthFix"
								@error="this.src=img('static/resource/images/diy/shop_default.jpg')" />
						</view>
						<view class="flex-1 px-[20rpx]">
							<view class="text-[28rpx] text-[#333] leading-[40rpx] mb-[6rpx]">{{ t('wechatPay') }}</view>
							<view class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
								{{ t('wechatPayDesc') }}
							</view>
						</view>
						<view class="flex items-center">
							<view class="check-icon" v-if="applyData.transfer_type == 'wechatpay'"></view>
							<text
								class="nc-iconfont nc-icon-youV6xx text-[28rpx] text-[var(--text-color-light9)] p-[10rpx]"></text>
						</view>
					</view>

					<!-- 提现到微信收款码 -->
					<view class="payment-method-item mb-[20rpx]" v-if="config.transfer_type.includes('wechat_code')"
						:class="{'selected-wechat': applyData.transfer_type == 'wechat_code'}"
						@click="transferWeixinCode">
						<view>
							<image class="h-[60rpx] w-[60rpx] align-middle"
								:src="img('static/resource/images/member/apply_withdrawal/wechat_code.png')"
								mode="widthFix" @error="this.src=img('static/resource/images/diy/shop_default.jpg')" />
						</view>
						<view class="flex-1 px-[20rpx]">
							<view class="text-[28rpx] text-[#333] leading-[40rpx] mb-[6rpx]">{{ t('wechatCode') }}
							</view>
							<view v-if="wechatCodeInfo"
								class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
								{{ t('wechatCodeDesc', { nickname: wechatCodeInfo.nickname }) }}
							</view>
							<view v-else class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
								{{ t('noWechatCode') }}
							</view>
						</view>
						<view class="flex items-center">
							<view class="check-icon" v-if="applyData.transfer_type == 'wechat_code' && wechatCodeInfo">
							</view>
							<button hover-class="none" class="add-account-btn"
								v-if="!wechatCodeInfo && !wechatCodeLoading"
								@click="redirect({ url: '/addon/home_service/technician/pages/member/account/withdraw_list', param: { type: 'wechat_code'}})">{{ t('add') }}</button>
							<text v-else
								class="nc-iconfont nc-icon-youV6xx text-[28rpx] text-[var(--text-color-light9)] p-[10rpx]"
								@click.stop="redirect({ url: '/addon/home_service/technician/pages/member/account/withdraw_list', param: { type: 'wechat_code', mode: 'select' }, mode: 'redirectTo' })"></text>
						</view>
					</view>

					<!-- 提现到支付宝 -->
					<view class="payment-method-item mb-[20rpx]" v-if="config.transfer_type.includes('alipay')"
						:class="{'selected-wechat': applyData.transfer_type == 'alipay'}" @click="transferAlipay">
						<view>
							<image class="h-[60rpx] w-[60rpx] align-middle"
								:src="img('static/resource/images/member/apply_withdrawal/alipay-icon.png')"
								mode="widthFix" />

						</view>
						<view class="flex-1 px-[20rpx]">
							<view class="text-[28rpx] text-[#333] leading-[40rpx] mb-[6rpx]">{{ t('alipay') }}</view>
							<view v-if="alipayAccountInfo"
								class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
								{{ t('account') }}
								{{ alipayAccountInfo.account_no.substring(0, 3) + '****' + alipayAccountInfo.account_no.substring(alipayAccountInfo.account_no.length - 4) }}
							</view>
							<view v-else class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
								{{ t('addAlipay') }}
							</view>
						</view>
						<view class="flex items-center">
							<view class="check-icon" v-if="applyData.transfer_type == 'alipay' && alipayAccountInfo">
							</view>
							<button hover-class="none" class="add-account-btn"
								v-if="!alipayAccountInfo && !alipayLoading"
								@click="redirect({ url: '/addon/home_service/technician/pages/member/account/withdraw_list', param: { type: 'alipay'}})">{{ t('add') }}</button>
							<text v-else
								class="nc-iconfont nc-icon-youV6xx text-[28rpx] text-[var(--text-color-light9)] p-[10rpx]"
								@click.stop="redirect({ url: '/addon/home_service/technician/pages/member/account/withdraw_list', param: { type: 'alipay', mode: 'select' }, mode: 'redirectTo' })"></text>
						</view>
					</view>

					<!-- 提现到银行卡 -->
					<view class="payment-method-item mb-[20rpx]" v-if="config.transfer_type.includes('bank')"
						:class="{'selected-wechat': applyData.transfer_type == 'bank'}" @click="transferBank">
						<view>
							<image class="h-[42rpx] w-[60rpx] align-middle"
								:src="img('static/resource/images/member/apply_withdrawal/bank-icon.png')"
								mode="widthFix" />

						</view>
						<view class="flex-1 px-[20rpx]">
							<view class="text-[28rpx] text-[#333] leading-[40rpx] mb-[6rpx]">{{ t('bank') }}</view>
							<view v-if="bankAccountInfo"
								class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
								<view class="truncate max-w-[440rpx]">
									<text>{{ t('bankDesc', { bankName: bankAccountInfo.bank_name }) }}</text>
									<text
										class="text-[#333]">{{ bankAccountInfo.account_no.substring(bankAccountInfo.account_no.length - 4) }}</text>
								</view>
							</view>
							<view v-else class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
								{{ t('addBank') }}
							</view>
						</view>
						<view class="flex items-center">
							<view class="check-icon" v-if="applyData.transfer_type == 'bank' && bankAccountInfo"></view>
							<button hover-class="none" class="add-account-btn" v-if="!bankAccountInfo && !bankLoading"
								@click="redirect({ url: '/addon/home_service/technician/pages/member/account/withdraw_list', param: { type: 'bank'}})">{{ t('add') }}</button>
							<text v-else
								class="nc-iconfont nc-icon-youV6xx text-[28rpx] text-[var(--text-color-light9)] p-[10rpx]"
								@click.stop="redirect({ url: '/addon/home_service/technician/pages/member/account/withdraw_list', param: { type: 'bank', mode: 'select' }, mode: 'redirectTo' })"></text>
						</view>
					</view>
				</view>

				<view class="tab-bar-placeholder"></view>
				<!-- 底部固定操作栏 -->
				<view class="fixed bottom-[0] tab-bar left-0 right-0 px-[var(--sidebar-m)] bg-[var(--page-bg-color)]">
					<button
						class="h-[80rpx] !text-[#fff] leading-[80rpx] !bg-[var(--technician-bg-one)] rounded-[10rpx] text-[26rpx] withdraw-submit-btn"
						:loading="loading" @click="cashOut">{{ t('submit') }}</button>
					<button
						class="h-[80rpx] !text-[#000] leading-[80rpx] !bg-[#f6f6f6] rounded-[50rpx] text-[26rpx] withdraw-submit-btn"
						@click="redirect({url:'/addon/home_service/technician/pages/member/cash/cash_out'})">提现记录</button>
				</view>
			</view>
		</scroll-view>

		<!-- 空状态 -->
		<view v-else-if="!pageLoading && config.is_open == 0" class="empty-page mt-0">
			<image class="mb-[10rpx]"
				:src="img('addon/home_service/withdraw_close.png')" mode="widthFix"></image>
			<text class="text-[30rpx] text-[#999]">提现设置未开启</text>
		</view>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed, onMounted, watch } from 'vue'
	import { onLoad, onShow } from '@dcloudio/uni-app' // 保留 UniApp 原生生命周期
	import { t } from '@/locale'
	import { img, redirect, moneyFormat } from '@/utils/common'

	import { getTechnicianInfo } from '@/addon/home_service/technician/api/technician'
	import { getCashOutConfig, getFirstCashOutAccountInfo, getCashoutAccountInfo, cashOutApply } from '@/addon/home_service/technician/api/account'

	// 1. 替换 useRoute：用 ref 定义 query，在 onLoad 中接收参数（UniApp 原生方式）
	const query = ref<any>({}) // 存储页面参数
	// 页面加载状态
	const pageLoading = ref(true)
	const loading = ref(false)

	// 师傅信息
	const technicianInfo = ref<any>(null)
	// 使用computed属性获取可提现金额，优先从commission获取
	const cashOutMoney = computed(() => {
		return technicianInfo.value ?
			(technicianInfo.value.commission !== undefined ?
				Number(technicianInfo.value.commission || 0) :
				Number(technicianInfo.value.money || 0)) : 0
	})

	// 提现配置
	const config = ref<any>({ min: 0, rate: 0, is_open: 0, transfer_type: [] })
	const openId = ref('')

	// 提现数据
	const applyData = ref({
		transfer_type: '',
		apply_money: '',
		account_id: 0
	})

	// 服务费用
	const serviceMoney = computed(() => {
		if (!applyData.value.apply_money || applyData.value.apply_money <= 0) return 0
		return (Number(applyData.value.apply_money) * Number(config.value.rate) / 100).toFixed(2)
	})

	// 计算是否可以提交
	const canSubmit = computed(() => {
		// 检查金额
		if (!applyData.value.apply_money || Number(applyData.value.apply_money) <= 0) return false
		if (!uni.$u.test.amount(applyData.value.apply_money)) return false
		if (Number(applyData.value.apply_money) > Number(cashOutMoney.value)) return false
		if (Number(applyData.value.apply_money) < Number(config.value.min)) return false

		// 检查提现方式
		if (!applyData.value.transfer_type) return false

		// 根据提现方式检查账号
		if (applyData.value.transfer_type === 'alipay' && !alipayAccountInfo.value) return false
		if (applyData.value.transfer_type === 'bank' && !bankAccountInfo.value) return false
		if (applyData.value.transfer_type === 'wechat_code' && !wechatCodeInfo.value) return false

		return true
	})

	// 支付宝/银行卡/微信收款码账号信息
	const alipayLoading = ref(false)
	const alipayAccountInfo : any = ref(null)
	const bankLoading = ref(false)
	const bankAccountInfo : any = ref(null)
	const wechatCodeLoading = ref(false)
	const wechatCodeInfo : any = ref(null)

	// 选择微信提现
	const transferWeixin = () => {
		applyData.value.transfer_type = 'wechatpay'
		applyData.value.account_id = 0
	}

	// 选择微信收款码提现 - 增强提示
	const transferWeixinCode = () => {
		if (!wechatCodeInfo.value) {
			redirect({
				url: '/addon/home_service/technician/pages/member/account/withdraw_list',
				param: { type: 'wechat_code' }
			})
			return
		}
		applyData.value.transfer_type = 'wechat_code'
		applyData.value.account_id = wechatCodeInfo.value.account_id || 0
	}

	// 选择支付宝提现 - 增强提示
	const transferAlipay = () => {
		if (!alipayAccountInfo.value) {
			uni.showToast({ title: t('addAlipay') || '请添加支付宝账号', icon: 'none' });
			redirect({
				url: '/addon/home_service/technician/pages/member/account/withdraw_list',
				param: { type: 'alipay' }
			})
			return
		}
		applyData.value.transfer_type = 'alipay'
		applyData.value.account_id = alipayAccountInfo.value.account_id || 0
	}

	// 选择银行卡提现 - 增强提示
	const transferBank = () => {
		if (!bankAccountInfo.value) {
			uni.showToast({ title: t('addBank') || '请添加银行卡', icon: 'none' });
			redirect({
				url: '/addon/home_service/technician/pages/member/account/withdraw_list',
				param: { type: 'bank' }
			})
			return
		}
		applyData.value.transfer_type = 'bank'
		applyData.value.account_id = bankAccountInfo.value.account_id || 0
	}

	// 全部提现
	const allMoney = () => {
		if (cashOutMoney.value > 0) {
			applyData.value.apply_money = String(cashOutMoney.value)
		}
	}

	// 清空金额
	const clearMoney = () => {
		applyData.value.apply_money = ''
	}

	// 验证函数
	const verify = () => {
		// 检查是否选择了提现方式
		if (!applyData.value.transfer_type) {
			uni.showToast({ title: t('noMethod') || '请选择提现方式', icon: 'none' });
			return false;
		}

		// 检查金额是否为空或0
		if (!applyData.value.apply_money || applyData.value.apply_money <= 0) {
			uni.showToast({ title: t('pleaseInputAmount') || '请输入提现金额', icon: 'none' });
			return false;
		}

		// 检查金额格式
		if (!uni.$u.test.amount(applyData.value.apply_money)) {
			uni.showToast({ title: t('amountFormatError') || '金额格式错误', icon: 'none' });
			return false;
		}

		if (parseFloat(applyData.value.apply_money) > parseFloat(cashOutMoney.value)) {
			uni.showToast({ title: t('exceedLimit') || '提现金额超过可用余额', icon: 'none' })
			return false
		}
		if (parseFloat(applyData.value.apply_money) < parseFloat(config.value.min)) {
			uni.showToast({ title: t('belowMin') || '提现金额低于最低限额', icon: 'none' })
			return false
		}

		// 根据提现方式检查账号
		if (applyData.value.transfer_type === 'alipay' && !alipayAccountInfo.value) {
			uni.showToast({ title: t('addAlipay') || '请添加支付宝账号', icon: 'none' });
			return false;
		}
		if (applyData.value.transfer_type === 'bank' && !bankAccountInfo.value) {
			uni.showToast({ title: t('addBank') || '请添加银行卡', icon: 'none' });
			return false;
		}
		if (applyData.value.transfer_type === 'wechat_code' && !wechatCodeInfo.value) {
			uni.showToast({ title: t('addWechat') || '请添加微信收款码', icon: 'none' });
			return false;
		}

		return true;
	}

	// 提现申请 - 增强提示
	const cashOut = () => {
		// 额外增加一次手动验证，确保在任何情况下都能显示提示
		if (!applyData.value.transfer_type) {
			uni.showToast({ title: t('noMethod') || '请选择提现方式', icon: 'none' })
			return
		}

		if (uni.$u.test.isEmpty(applyData.value.apply_money) || Number(applyData.value.apply_money) <= 0) {
			uni.showToast({ title: t('pleaseInputAmount') || '请输入提现金额', icon: 'none' })
			return
		}

		if (verify()) {
			if (loading.value) return
			loading.value = true

			// 提现逻辑
			cashOutApply(applyData.value).then((res : any) => {
				loading.value = false
				if (res.code === 1) {
					// 提现成功后重新获取师傅信息以更新余额
					getTechnicianInfo().then((techRes : any) => {
						if (techRes.code === 1 && techRes.data) {
							technicianInfo.value = techRes.data
						}
					})

					redirect({
						url: '/addon/home_service/technician/pages/member/cash/cash_out',
					})
				} else {
					uni.showToast({ title: res.msg || t('withdrawFailed'), icon: 'none' })
				}
			}).catch((error) => {
				loading.value = false
				console.error('提现失败:', error)
				uni.showToast({ title: t('networkError') || '网络错误，请重试', icon: 'none' })
			})
		}
	}

	// 添加金额失焦事件处理
	const onAmountBlur = () => {
		if (applyData.value.apply_money) {
			verify();
		}
	}

	// 添加实时验证提示
	watch([() => applyData.apply_money, () => applyData.transfer_type], () => {
		// 实时提示逻辑（可根据需求补充）
	}, { immediate: true })

	// 2. 核心修改：用 onLoad 接收页面参数（UniApp 原生语法）
	onLoad((options) => {
		query.value = options; // 将页面参数赋值给 query（替代原 route.query）
		// 初始化数据（依赖参数的逻辑需放在 onLoad 中，确保参数已获取）
		initPageData();
	})

	// 3. 初始化页面数据（抽离为函数，避免代码冗余）
	const initPageData = () => {
		// 获取提现配置
		getCashOutConfig().then((res : any) => {
			if (res.code === 1) {
				config.value = res.data
				// 如果有默认提现方式，赋值给 applyData
				if (config.value.default_type) {
					applyData.value.transfer_type = config.value.default_type
				}
			}
			pageLoading.value = false
		}).catch(() => {
			pageLoading.value = false
			uni.showToast({ title: t('configError') || '获取提现配置失败', icon: 'none' })
		})

		// 获取师傅信息
		getTechnicianInfo().then((res : any) => {
			if (res.code === 1 && res.data) {
				technicianInfo.value = res.data
				openId.value = res.data.openid || ''
			}
		}).catch(() => {
			uni.showToast({ title: t('getInfoError') || '获取师傅信息失败', icon: 'none' })
		})

		// 获取各提现方式的账号信息（依赖 query 参数，需在 onLoad 后执行）
		getWechatCodeInfo()
		getAlipayAccountInfo()
		getBankAccountInfo()
	}

	// 增强 onShow 事件，确保每次页面显示都能刷新数据
	onShow(() => {
		// 获取师傅信息
		getTechnicianInfo().then((res : any) => {
			if (res.code === 1 && res.data) {
				technicianInfo.value = res.data
				// 初始化金额为全部余额
				allMoney()
			}
		})

		// 重新获取各提现方式的账号信息（依赖 query 参数，用最新的 query 重新请求）
		getAlipayAccountInfo()
		getBankAccountInfo()
		getWechatCodeInfo()

		// 重置提现数据
		applyData.value.transfer_type = config.value.default_type || ''
		applyData.value.apply_money = ''
	})

	// 主题颜色 - 修改为蓝色
	const themeColor = () => {
		return {
			'--technician-bg-one': '#1890FF'
		}
	}

	// 获取微信收款码信息（依赖 query 参数，用 query.value 替代原 route.query）
	const getWechatCodeInfo = () => {
		const data = { account_type: 'wechat_code', account_id: 0 }
		let request = getFirstCashOutAccountInfo

		// 用 query.value 获取参数（替代原 route.query）
		if (query.value.type && query.value.type == 'wechat_code' && query.value.account_id) {
			request = getCashoutAccountInfo
			data.account_id = query.value.account_id
		}
		wechatCodeLoading.value = true
		request(data).then((res : any) => {
			if (res.data && res.data.account_id) {
				wechatCodeInfo.value = res.data
				// 初始化赋值
				if (applyData.value.transfer_type == 'wechat_code' && !applyData.value.account_id) {
					applyData.value.account_id = wechatCodeInfo.value.account_id;
				}
			}
			wechatCodeLoading.value = false
		}).catch(() => {
			wechatCodeLoading.value = false
		})
	}

	// 获取支付宝提现账号信息（依赖 query 参数）
	const getAlipayAccountInfo = () => {
		const data = { account_type: 'alipay', account_id: 0 }
		let request = getFirstCashOutAccountInfo

		if (query.value.type && query.value.type == 'alipay' && query.value.account_id) {
			request = getCashoutAccountInfo
			data.account_id = query.value.account_id
		}
		alipayLoading.value = true
		request(data).then((res : any) => {
			if (res.data && res.data.account_id) {
				alipayAccountInfo.value = res.data
				// 初始化赋值
				if (applyData.value.transfer_type == 'alipay' && !applyData.value.account_id) {
					applyData.value.account_id = alipayAccountInfo.value.account_id;
				}
			}
			alipayLoading.value = false
		}).catch(() => {
			alipayLoading.value = false
		})
	}

	// 获取银行卡提现账号信息（依赖 query 参数）
	const getBankAccountInfo = () => {
		const data = { account_type: 'bank', account_id: 0 }
		let request = getFirstCashOutAccountInfo

		if (query.value.type && query.value.type == 'bank' && query.value.account_id) {
			request = getCashoutAccountInfo
			data.account_id = query.value.account_id
		}
		bankLoading.value = true
		request(data).then((res : any) => {
			if (res.data && res.data.account_id) {
				bankAccountInfo.value = res.data
				// 初始化赋值
				if (applyData.value.transfer_type == 'bank' && !applyData.value.account_id) {
					applyData.value.account_id = bankAccountInfo.value.account_id;
				}
			}
			bankLoading.value = false
		}).catch(() => {
			bankLoading.value = false
		})
	}
</script>

<style lang="scss" scoped>
	.withdraw-page {
		width: 100%;
		height: 100vh;
		background-color: var(--page-bg-color);
		position: relative;
	}

	.top-decoration {
		width: 100%;
		height: 300rpx;
		background: linear-gradient(135deg, #1890FF, #40A9FF);
		position: absolute;
		top: 0;
		left: 0;
		z-index: -1;
	}

	.card-template {
		width: 100%;
		padding: 30rpx;
		background-color: #FFFFFF;
		border-radius: var(--rounded-mid);
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
	}

	.money-card {
		position: relative;
		overflow: hidden;
	}

	.money-card::after {
		content: '';
		position: absolute;
		right: 0;
		top: 0;
		width: 300rpx;
		height: 300rpx;
		background: linear-gradient(135deg, rgba(24, 144, 255, 0.08), transparent);
		border-radius: 0 0 0 300rpx;
		z-index: 0;
	}

	.withdrawal-btn {
		padding: 8rpx 20rpx;
		background-color: rgba(24, 144, 255, 0.08);
		border-radius: 40rpx;
		position: relative;
		z-index: 1;
	}

	.amount-input-container {
		margin-top: 30rpx;
		padding: 10rpx 20rpx;
		background-color: #F8F8F8;
		border-radius: 12rpx;
		display: flex;
		align-items: center;
		position: relative;
		z-index: 1;
	}

	.amount-input {
		font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', sans-serif;
	}

	.apply-price {
		font-size: 44rpx;
		color: var(--text-color-light9);
	}

	.clear-btn {
		padding: 0 20rpx;
	}

	.payment-methods-card {
		margin-top: 20rpx;
	}

	.payment-method-item {
		display: flex;
		align-items: center;
		padding: 25rpx 0;
		border-bottom: 1rpx solid #EEEEEE;
	}

	.payment-method-item:last-child {
		border-bottom: none;
	}

	.selected-wechat {
		background-color: rgba(24, 144, 255, 0.03);
		border-radius: 12rpx;
		padding: 25rpx;
		margin: 0 -10rpx;
	}

	.check-icon {
		width: 32rpx;
		height: 32rpx;
		background-color: var(--technician-bg-one);
		border-radius: 50%;
		position: relative;
		margin-right: 10rpx;
	}

	.check-icon::after {
		content: '';
		position: absolute;
		top: 50%;
		left: 50%;
		width: 18rpx;
		height: 10rpx;
		border: 2rpx solid white;
		border-top: none;
		border-right: none;
		transform: translate(-50%, -60%) rotate(-45deg);
	}

	.add-account-btn {
		padding: 8rpx 24rpx;
		background-color: rgba(24, 144, 255, 0.08);
		color: var(--technician-bg-one);
		border-radius: 40rpx;
		font-size: 24rpx;
		line-height: 36rpx;
	}

	.tab-bar {
		padding-top: 20rpx;
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
		background-color: var(--page-bg-color);
		box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.05);
	}

	.withdraw-submit-btn {
		width: 100%;
		font-size: 28rpx;
		font-weight: 500;
		transition: all 0.3s ease;
		margin-bottom: 20rpx;
		/* 增加按钮间距，避免重叠 */
	}

	.button-disabled {
		opacity: 0.6;
		pointer-events: none;
	}

	.empty-page {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		height: 100vh;
		background-color: var(--page-bg-color);
	}

	/* 适配安全区域 */
	@media (env(safe-area-inset-bottom)) {
		.tab-bar {
			padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
		}
	}

	.tab-bar-placeholder {
		height: 140rpx;
	}
</style>

<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>