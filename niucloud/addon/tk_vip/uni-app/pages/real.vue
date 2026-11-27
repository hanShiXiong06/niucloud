<template>
	<view class="real-page" v-if="deatailData">
		<view class="page-header">
			<text class="header-title">实名认证</text>
			<up-tag :text="deatailData.status_name" :type="deatailData.status == 1 ? 'primary' : 'error'" size="mini"
				class="status-tag" />
		</view>
		<view class="page-subtitle">
			<text class="subtitle-text">请准确填写信息，以便我们给你提供更好的服务</text>
		</view>

		<view class="real-card">
			<view class="form-container">
				<up-form labelStyle="{fontWeight:'bold'}" labelPosition="left" labelWidth="80" :model="deatailData"
					:rules="rules" ref="form1">
					<up-form-item label="真实姓名" prop="real_name" borderBottom ref="item1" class="is-required form-item">
						<up-input :focus="(deatailData.status == 10 || deatailData.status == 2) ? true : false"
							:color="cssVar.textColorSecondary" v-model="deatailData.real_name" class="custom-input"
							placeholder="请输入您的真实姓名"></up-input>
					</up-form-item>
					<up-form-item type="idcard" label="身份证号" prop="card_num" borderBottom ref="item2"
						class="is-required form-item">
						<up-input :color="cssVar.textColorSecondary" v-model="deatailData.card_num" class="custom-input"
							placeholder="请输入您的身份证号"></up-input>
					</up-form-item>
					<up-form-item label="手机号码" prop="mobile" borderBottom ref="item1" class="is-required form-item">
						<up-input :color="cssVar.textColorSecondary" v-model="deatailData.mobile" class="custom-input"
							placeholder="请输入您的手机号"></up-input>
					</up-form-item>
				</up-form>
			</view>

			<view class="upload-container"
				v-if="realConfig && realConfig.is_upload_card && realConfig.is_upload_card == 1 && (deatailData.status == 10 || deatailData.status == 2)">
				<view class="upload-title-row">
					<text class="upload-title">上传证件照片</text>
					<text class="upload-subtitle">用于身份验证，信息已加密</text>
				</view>

				<view class="upload-cards-container">
					<view class="upload-card">
						<text class="upload-card-title">身份证国徽面</text>
						<view class="upload-wrapper">
							<upload-img class="upload-component" :max-count="1" v-model="deatailData.card_img_front"
								:multiple="true" @upload="handleUpload" @complete="handleUploadComplete" />
						</view>
					</view>

					<view class="upload-card">
						<text class="upload-card-title">身份证人像面</text>
						<view class="upload-wrapper">
							<upload-img class="upload-component" :max-count="1" v-model="deatailData.card_img_back"
								:multiple="true" @upload="handleUpload" @complete="handleUploadComplete" />
						</view>
					</view>
				</view>
			</view>

			<view v-if="deatailData.status == 10 || deatailData.status == 2" class="submit-section">
				<up-button :color="cssVar.primaryColor" type="primary" shape="circle" text="提交审核" @click="save()"
					:disabled="btnDisabled" class="submit-button"></up-button>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app'
import { redirect } from '@/utils/common'
import { getRealConfig, checkReal, submitReal, getRealInfo } from '@/addon/tk_vip/api/real'
import uploadImg from '@/addon/tk_vip/pages/components/upload-img'
const form1 = ref()
const realInfo = ref()
const realConfig = ref()
const isUploading = ref(false)

getRealConfig().then((res) => {
	realConfig.value = res.data
})
const redirectPage = ref('/addon/tk_vip/pages/index')
const deatailData = ref({
	real_name: '',
	card_num: '',
	mobile: '',
	card_img_back: [],
	card_img_front: [],
	status: "",
	status_name: ""
})
const rules = {
	real_name: [
		{ required: true, message: '请输入姓名', trigger: 'blur' }
	],
	card_num: [
		{ required: true, message: '请输入身份证号码', trigger: 'blur' },
		{ validator: validateCardNum, trigger: 'blur' }
	],
	mobile: [
		{ required: true, message: '请输入手机号码', trigger: 'blur' },
		{ validator: validateMobile, trigger: 'blur' }
	],
	card_img_front: [
		{ required: true, message: '请上传身份证国徽面', trigger: 'blur' },
	],
	card_img_back: [
		{ required: true, message: '请上传身份证人像面', trigger: 'blur' },
	],
}
function validateCardNum(rule, value, callback) {
	if (!value) {
		return callback(new Error('请输入身份证号码'));
	}
	// 正则表达式修改为匹配正确的身份证格式
	if (!/^[1-9]\d{5}[1-9]\d{3}((0[1-9])|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X)$/.test(value)) {
		callback(new Error('请输入正确的身份证号码'));
	} else {
		callback();
	}
}

// 自定义手机号码验证函数
function validateMobile(rule: any, value: any, callback: any) {
	if (!value) {
		return callback(new Error('请输入电话号码'));
	}
	if (!/^1[3456789]\d{9}$/.test(value)) {
		callback(new Error('请输入正确的电话号码'));
	} else {
		callback();
	}
}
const getRealInfoFn = async () => {
	const res = await getRealInfo()
	deatailData.value.real_name = res.data.real_name
	deatailData.value.card_num = res.data.card_num
	deatailData.value.mobile = res.data.mobile
	deatailData.value.status = res.data.status
	deatailData.value.status_name = res.data.status_name
}
const save = async () => {
	if (realConfig.value.is_upload_card == 1 && deatailData.value.card_img_front.length == 0) {
		uni.$u.toast('请上传身份证国徽面')
		return
	}
	if (realConfig.value.is_upload_card == 1 && deatailData.value.card_img_back.length == 0) {
		uni.$u.toast('请上传身份证人像面')
		return
	}
	const valid = await form1.value.validate();
	if (valid) {
		await submitReal(deatailData.value)
		getRealInfoFn()
		redirect({ url: redirectPage.value })
	}
	else {
		uni.showToast({
			title: '请填写必填项',
			icon: 'none'
		});
	}
}

const handleUpload = () => {
	isUploading.value = true
}

const handleUploadComplete = () => {
	isUploading.value = false
}

onLoad((option) => {
	if (option.page) {
		redirectPage.value = option.page
	}
	getRealInfoFn()
})

// 添加CSS变量
const cssVar = {
	primaryColor: '#3b82f6',
	primaryColorLight: '#60a5fa',
	primaryColorDark: '#2563eb',
	textColorPrimary: '#1e293b',
	textColorSecondary: '#64748b',
	textColorTertiary: '#94a3b8',
	backgroundColor: '#f8fafc',
	borderColor: '#e2e8f0',
	cardBackground: '#ffffff',
	shadowColor: 'rgba(0, 0, 0, 0.05)',
	successColor: '#22c55e',
	warningColor: '#f59e0b',
	errorColor: '#ef4444'
}
</script>

<style lang="scss">
.real-page {
	min-height: 100vh;
	background: var(--background-color);
	padding: 20rpx;
	padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
}

.page-header {
	display: flex;
	align-items: center;
	margin-bottom: 12rpx;
	padding: 0 8rpx;

	.header-title {
		font-size: 36rpx;
		font-weight: 700;
		color: var(--text-color-primary);
	}

	.status-tag {
		margin-left: 12rpx;
	}
}

.page-subtitle {
	padding: 0 8rpx;
	margin-bottom: 20rpx;

	.subtitle-text {
		font-size: 26rpx;
		color: var(--text-color-secondary);
		line-height: 1.4;
	}
}

.real-card {
	background: var(--card-background);
	border-radius: 20rpx;
	padding: 24rpx 20rpx;
	box-shadow: 0 8rpx 24rpx var(--shadow-color);
	margin-bottom: 20rpx;
}

.form-container {
	background: var(--card-background);
	border-radius: 16rpx;
	overflow: hidden;
	margin-bottom: 24rpx;

	.form-item {
		margin-bottom: 16rpx;
	}

	.custom-input {
		font-size: 24rpx;
		height: 50rpx;
		border-radius: 8rpx;
	}

	// 添加必填项星号样式
	:deep(.up-form-item) {
		&.is-required {
			.up-form-item__label {
				position: relative;
				padding-right: 20rpx;
				font-size: 28rpx;
				color: var(--text-color-primary);

				&::after {
					content: '*';
					position: absolute;
					right: 0;
					top: 50%;
					transform: translateY(-50%);
					color: var(--error-color);
					font-size: 28rpx;
					font-weight: bold;
				}
			}
		}
	}
}

.upload-container {
	background: var(--card-background);
	border-radius: 16rpx;
	overflow: hidden;
	margin-bottom: 24rpx;
	padding: 8rpx 0;
}

.upload-title-row {
	display: flex;
	flex-direction: column;
	padding: 12rpx 20rpx 16rpx;

	.upload-title {
		font-size: 28rpx;
		font-weight: 600;
		color: var(--text-color-primary);
		margin-bottom: 6rpx;
	}

	.upload-subtitle {
		font-size: 24rpx;
		color: var(--text-color-tertiary);
	}
}

.upload-cards-container {
	display: flex;
	flex-direction: column;
	gap: 16rpx;
	padding: 0 20rpx;
}

.upload-card {
	background: var(--background-color);
	padding: 16rpx;
	border-radius: 12rpx;

	.upload-card-title {
		position: relative;
		display: block;
		font-size: 26rpx;
		font-weight: 500;
		color: var(--text-color-secondary);
		margin-bottom: 16rpx;
		padding-right: 20rpx;

		&::after {
			content: '*';
			position: absolute;
			right: 0;
			top: 50%;
			transform: translateY(-50%);
			color: var(--error-color);
			font-size: 24rpx;
			font-weight: bold;
		}
	}

	.upload-wrapper {
		position: relative;
		margin-top: 6rpx;
		z-index: 88;
	}
}

.submit-section {
	margin-top: 32rpx;
	display: flex;
	justify-content: center;

	.submit-button {
		min-width: 70%;
		height: 80rpx;
		font-size: 30rpx;
		font-weight: 500;
		letter-spacing: 2rpx;
		box-shadow: 0 8rpx 16rpx rgba(59, 130, 246, 0.2);
	}
}

.upload-loading {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(255, 255, 255, 0.9);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	z-index: 100;
	border-radius: 12rpx;

	.loading-spinner {
		width: 60rpx;
		height: 60rpx;
		border: 4rpx solid var(--primary-color);
		border-top-color: transparent;
		border-radius: 50%;
		animation: spin 1s linear infinite;
		margin-bottom: 16rpx;
	}

	.loading-text {
		font-size: 24rpx;
		color: var(--text-color-secondary);
	}
}

@keyframes spin {
	from {
		transform: rotate(0deg);
	}

	to {
		transform: rotate(360deg);
	}
}

:root {
	--primary-color: #3b82f6;
	--primary-color-light: #60a5fa;
	--primary-color-dark: #2563eb;
	--text-color-primary: #1e293b;
	--text-color-secondary: #64748b;
	--text-color-tertiary: #94a3b8;
	--background-color: #f8fafc;
	--border-color: #e2e8f0;
	--card-background: #ffffff;
	--shadow-color: rgba(0, 0, 0, 0.08);
	--success-color: #22c55e;
	--warning-color: #f59e0b;
	--error-color: #ef4444;
}
</style>