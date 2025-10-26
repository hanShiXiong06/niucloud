<template>
	<view class="w-screen h-screen bg-[var(--page-bg-color)] overflow-hidden" :style="themeColor()">
		<scroll-view scroll-y="true">
			<view class="sidebar-margin card-template top-mar account pb-[20rpx]">
				<template v-if="formData.account_type == 'bank'">
					<view class="text-center text-[32rpx] font-500 mt-[10rpx] text-[#333] leading-[42rpx]">
						{{ formData.account_id ? t('editBankCard') : t('addBankCard') }}</view>
					<view class="text-center text-[24rpx] mt-[16rpx] text-[var(--text-color-light9)]">
						{{ formData.account_id ? t('editBankCardTips') : t('addBankCardTips') }}</view>
					<view class="mt-[70rpx] px-[10rpx]">
						<u-form labelPosition="left" :model="formData" errorType='toast' :rules="rules" ref="formRef">
							<view>
								<u-form-item :label="t('bankRealname')" prop="realname" labelWidth="200rpx">
									<u-input v-model.trim="formData.realname" fontSize="28rpx" maxlength="30"
										border="none" clearable :placeholder="t('bankRealnamePlaceholder')" />
								</u-form-item>
							</view>
							<view class="mt-[16rpx]">
								<u-form-item :label="t('bankName')" prop="bank_name" labelWidth="200rpx">
									<u-input v-model.trim="formData.bank_name" fontSize="28rpx" maxlength="30"
										border="none" clearable :placeholder="t('bankNamePlaceholder')" />
								</u-form-item>
							</view>
							<view class="mt-[16rpx]">
								<u-form-item :label="t('bankAccountNo')" prop="account_no" labelWidth="200rpx">
									<u-input v-model.trim="formData.account_no" fontSize="28rpx" maxlength="30"
										border="none" clearable :placeholder="t('bankAccountNoPlaceholder')" />
								</u-form-item>
							</view>
						</u-form>
					</view>
				</template>

				<template v-if="formData.account_type == 'alipay'">
					<view class="text-center text-[32rpx] font-500 mt-[20rpx] text-[#333] leading-[42rpx]">
						{{ formData.account_id ? t('editAlipayAccount') : t('addAlipayAccount') }}</view>
					<view class="text-center text-[28rpx] mt-[16rpx] text-[var(--text-color-light9)] leading-[36rpx]">
						{{ formData.account_id ? t('editAlipayAccountTips') : t('addAlipayAccountTips') }}</view>

					<view class="mt-[70rpx] px-[10rpx]">
						<u-form labelPosition="left" :model="formData" labelWidth="200rpx" errorType='toast'
							:rules="rules" ref="formRef">
							<view>
								<u-form-item :label="t('alipayRealname')" prop="realname">
									<u-input v-model.trim="formData.realname" maxlength="30" border="none"
										fontSize="28rpx" clearable :placeholder="t('alipayRealnamePlaceholder')" />
								</u-form-item>
							</view>
							<view class="mt-[16rpx]">
								<u-form-item :label="t('alipayAccountNo')" prop="account_no">
									<u-input v-model.trim="formData.account_no" border="none" maxlength="30"
										fontSize="28rpx" clearable :placeholder="t('alipayAccountNoPlaceholder')" />
								</u-form-item>
							</view>
							<view class="mt-[16rpx]">
								<u-form-item label="收款码" prop="transfer_payment_code">
									<view class="relative w-[160rpx] h-[160rpx]" v-if="formData.transfer_payment_code">
										<image class="w-[160rpx] h-[160rpx]" :src="img(formData.transfer_payment_code)"
											mode="aspectFill"
											@click="previewImageFn(img(formData.transfer_payment_code))" />
										<view
											class="absolute top-0 right-0 bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]"
											@click="collectionCodeDeleteFn">
											<text
												class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-[#fff]"></text>
										</view>
									</view>
									<u-upload v-else @afterRead="collectionCodeAfterRead"
										@delete="collectionCodeDeleteFn" :maxCount="1"  :maxSize="10485760"
										@oversize="oversizeTit"></u-upload>
								</u-form-item>
							</view>
						</u-form>
					</view>
				</template>

				<template v-if="formData.account_type == 'wechat_code'">
					<view class="text-center text-[32rpx] font-500 mt-[20rpx] text-[#333] leading-[42rpx]">
						{{ formData.account_id ? t('editWechatCodeAccount') : t('addWechatCodeAccount') }}</view>
					<view class="text-center text-[28rpx] mt-[16rpx] text-[var(--text-color-light9)] leading-[36rpx]">
						{{ formData.account_id ? t('editWechatCodeAccountTips') : t('addWechatCodeAccountTips') }}
					</view>

					<view class="mt-[70rpx] px-[10rpx]">
						<u-form labelPosition="left" :model="formData" labelWidth="200rpx" errorType='toast'
							:rules="rules" ref="formRef">
							<view>
								<u-form-item :label="t('wechatCodeRealname')" prop="realname">
									<u-input v-model.trim="formData.realname" maxlength="30" border="none"
										fontSize="28rpx" clearable :placeholder="t('wechatCodeRealnamePlaceholder')" />
								</u-form-item>
							</view>
							<view class="mt-[16rpx]">
								<u-form-item :label="t('wechatCodeAccountNo')" prop="account_no">
									<u-input v-model.trim="formData.account_no" border="none" maxlength="30"
										fontSize="28rpx" clearable :placeholder="t('wechatCodeAccountNoPlaceholder')" />
								</u-form-item>
							</view>
							<view class="mt-[16rpx]">
								<u-form-item label="收款码" prop="transfer_payment_code">
									<view class="relative w-[160rpx] h-[160rpx]" v-if="formData.transfer_payment_code">
										<image class="w-[160rpx] h-[160rpx] rounded-[8rpx]"
											:src="img(formData.transfer_payment_code)" mode="aspectFill"
											@click="previewImageFn(img(formData.transfer_payment_code))" />
										<view
											class="absolute top-0 right-0 bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]"
											@click="collectionCodeDeleteFn">
											<text
												class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-[#fff]"></text>
										</view>
									</view>
									<u-upload v-else @afterRead="collectionCodeAfterRead"
										@delete="collectionCodeDeleteFn" :maxCount="1" :maxSize="10485760"
										@oversize="oversizeTit"></u-upload>
								</u-form-item>
							</view>
						</u-form>
					</view>
				</template>
			</view>
			<view class="common-tab-bar-placeholder"></view>
			<view class="common-tab-bar fixed left-[var(--sidebar-m)] right-[var(--sidebar-m)] bottom-[0]">
				<!-- 保存按钮 - 参考withdraw_list.vue的按钮实现 -->
				<view class="save-button-container">
					<button :loading="loading" hover-class="none" class="save-button" @click="handleSave">
						<text class="save-text">{{ t('save') }}</text>
					</button>
				</view>
			</view>
		</scroll-view>

		<u-modal :show="deleteConfirm" :content="t('deleteConfirm')" :confirmText="t('confirm')"
			:cancelText="t('cancel')" :showCancelButton="true" @confirm="handleDelete" @cancel="deleteConfirm = false"
			confirmColor="var(--store-bg-one)"></u-modal>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed, reactive } from 'vue'
	import { onLoad } from '@dcloudio/uni-app'
	import { editCashoutAccount, addCashoutAccount, getCashoutAccountInfo, deleteCashoutAccount } from '@/addon/home_service/store/api/account'
	import { t } from '@/locale'
	import { uploadImage } from '@/app/api/system'
	import { redirect, img } from '@/utils/common'

	const loading = ref(false)
	const formRef : any = ref(null)
	const mode = ref('get')
	const deleteConfirm = ref(false)
	const formData = reactive<AnyObject>({
		account_id: 0,
		account_type: 'bank',
		bank_name: '',
		realname: '',
		account_no: '',
		transfer_payment_code: ''
	})
	const oversizeTit = () => {
		uni.showToast({ title: '图片体积过大，请压缩后上传', icon: 'none' })
	}

	const rules = computed(() => {
		return {
			'realname': {
				type: 'string',
				required: true,
				message: formData.account_type == 'bank' ? t('bankRealnamePlaceholder') :
					(formData.account_type == 'wechat_code' ? t('wechatCodeRealnamePlaceholder') : t('alipayRealnamePlaceholder')),
				trigger: ['blur', 'change'],
			},
			'bank_name': {
				type: 'string',
				required: formData.account_type == 'bank',
				message: t('bankNamePlaceholder'),
				trigger: ['blur', 'change'],
			},
			'transfer_payment_code': {
				validator(rule, value, callback) {
					if (!value || !value.length) {
						let tips = formData.account_type == 'alipay' ? t('alipayAccountImgPlaceholder') : t('wechatCodeAccountImgPlaceholder');
						callback(new Error(tips))
					} else {
						callback()
					}
				}
			}
		}
	})

	onLoad((data : any) => {
		data.type && (formData.account_type = data.type)
		data.mode && (mode.value = data.mode)
		if (data.id) {
			formData.account_id = data.id || ''
			if (formData.account_id) {
				uni.setNavigationBarTitle({
					title: t('editAccountTitle')
				})
			} else {
				uni.setNavigationBarTitle({
					title: t('addAccountTitle')
				})
			}

			getCashoutAccountInfo({ account_id: data.id }).then((res : any) => {
				if (res.data) {
					Object.keys(formData).forEach((key : string) => {
						if (res.data[key] != undefined) formData[key] = res.data[key]
					})
				}
			})
		}
	})

	const handleSave = () => {
		const save = formData.account_id ? editCashoutAccount : addCashoutAccount

		formRef.value.validate().then(() => {
			if (loading.value) return
			loading.value = true

			save(formData).then((res) => {
				if (mode.value == 'get') redirect({
					url: '/addon/home_service/store/pages/store/account/withdraw_list',
					param: { type: formData.account_type, mode: mode.value }
				})
				else redirect({
					url: '/addon/home_service/store/pages/store/account/withdraw',
					param: {
						account_id: formData.account_id ? formData.account_id : res.data.id,
						type: formData.account_type
					},
					mode: 'redirectTo'
				})
			}).catch(() => {
				loading.value = false
			})
		})
	}

	const collectionCodeAfterRead = (e) => {
		uploadImage({
			filePath: e.file.url,
			name: 'file'
		}).then(res => {
			if (res.data) {
				formData.transfer_payment_code = '';
				formData.transfer_payment_code = res.data.url;
			}
		}).catch(() => {
		})
	}

	// 删除图片
	const collectionCodeDeleteFn = (e) => {
		formData.transfer_payment_code = '';
	}

	const handleDelete = () => {
		deleteCashoutAccount(formData.account_id).then(() => {
			redirect({ url: '/addon/home_service/store/pages/store/account/withdraw_list', mode: 'redirectTo' })
		})
	}

	// 预览
	const previewImageFn = (url : any) => {
		uni.previewImage({
			current: 0,
			urls: [url]
		});
	}
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
	:deep(.account .u-form-item .u-form-item__body) {
		padding: 20rpx 0;
	}

	.account :deep(.u-form-item__body__left__content__label) {
		font-size: 28rpx !important;
		color: #333;
	}

	/* 美化收款码图片样式 */
	.account :deep(.u-form-item__body__right) {
		padding: 10rpx 0;
	}

	/* 表单容器美化 */
	.card-template {
		background-color: #fff;
		border-radius: 16rpx;
		box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);
		margin: 30rpx;
	}

	/* 顶部标题区域样式优化 - 修复SCSS语法错误 */
	.text-center[class*="text-"] {
		padding: 10rpx 0;
	}

	/* 保存按钮容器 - 参考withdraw_list.vue的底部按钮实现 */
	.save-button-container {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background: #FFFFFF;
		padding: 20rpx 40rpx;
		box-shadow: 0 -4rpx 16rpx rgba(0, 0, 0, 0.06);
		/* 安全区域适配 */
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	/* 保存按钮 - 参考withdraw_list.vue的按钮样式 */
	.save-button {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		background: var(--store-bg-one, #3E82FC);
		color: #FFFFFF;
		height: 80rpx;
		line-height: 80rpx;
		border-radius: 40rpx;
		font-size: 28rpx;
		font-weight: 500;
	}

	.save-text {
		font-size: 28rpx;
		font-weight: 500;
	}

	/* 优化按钮触摸反馈 */
	.save-button:active {
		opacity: 0.9;
		transform: scale(0.98);
	}
</style>