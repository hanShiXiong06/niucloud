<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
	<!-- #endif -->
	<view class="bg-[#f5f5f5] min-h-screen" v-if="!loading" :style="themeColor()">
		<!-- 内容区域 -->
		<view class="content p-4">
			<!-- 反馈标题 -->
			<view class="bg-white px-4 py-3 mb-3 rounded-lg">
				<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">{{ t('feedbackTitle') }}</text>
				<input type="text" :placeholder="t('pleaseInputFeedbackTitle')" v-model="form.title" maxlength="50"
					class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
			</view>

			<!-- 反馈内容 -->
			<view class="bg-white px-4 py-3 mb-3 rounded-lg">
				<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">{{ t('feedbackContent') }}</text>
				<textarea :placeholder="t('pleaseDescribeIssue')" v-model="form.content" maxlength="500"
					class="w-full bg-[#F6F6F6] px-[20rpx] py-[20rpx] rounded-lg min-h-[200rpx]"
					show-confirm-bar="false"></textarea>
			</view>

			<!-- 上传图片 -->
			<view class="bg-white px-4 py-3 mb-3 rounded-lg">
				<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">{{ t('uploadImage') }}</text>
				<view class="flex items-start flex-wrap">
					<upload-img v-model="form.images" :max-count="5" :multiple="true" />
				</view>
			</view>

			<!-- 提交按钮 -->
			<view class="w-full footer">
				<view
					class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
					<button hover-class="none"
						class="!bg-[var(--primary-color)] !text-[#fff] !rounded-lg  !text-[#fff] !bg-[var(--primary-color)] h-[80rpx] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500"
						@click="submitForm" :disabled="operateLoading" :loading="operateLoading"
						:class="{'opacity-50': operateLoading}">{{ t('submitFeedback') }}
					</button>
				</view>
			</view>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref } from 'vue'
	import { t } from '@/locale'
	import { redirect } from '@/utils/common'
	import { onLoad } from '@dcloudio/uni-app'
	import uploadImg from '@/addon/home_service/user/components/upload-img/upload-img.vue'
	import { submitFeedback } from '@/addon/home_service/user/api/member'
	import { topTabar } from '@/utils/topTabbar';
	// 系统状态管理
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '反馈', topStatusBar: { textColor: '#333' } })
	const loading = ref<boolean>(false);
	const operateLoading = ref<boolean>(false);

	// 表单数据
	const form = ref({
		title: '',
		content: '',
		images: [] as string[]
	})

	// 页面加载
	onLoad(() => {
		loading.value = false;
	})

	// 返回上一页
	const back = () => {
		uni.navigateBack();
	}

	// 表单提交
	const submitForm = () => {
		// 表单验证
		if (!form.value.title.trim()) {
			uni.showToast({ title: t('pleaseInputFeedbackTitle'), icon: 'none' })
			return
		}
		if (!form.value.content.trim()) {
			uni.showToast({ title: t('feedbackContent'), icon: 'none' })
			return
		}
		if (form.value.content.length < 10) {
			uni.showToast({ title: t('user.pages.member.feedback.feedback.feedbackContentMinLength'), icon: 'none' })
			return
		}

		// 准备提交数据
		const submitData = {
			title: form.value.title,
			content: form.value.content,
			images: form.value.images.join(',') // 将数组转换为逗号分隔的字符串
		}

		// 实际提交
		operateLoading.value = true
		submitFeedback(submitData).then((res : any) => {
			operateLoading.value = false
			if (res.code === 1) {
				uni.showToast({ title: t('feedbackSubmitSuccess'), icon: 'none' })
				// 延时返回上一页
				setTimeout(() => {
					back()
				}, 1500)
			} else {
				uni.showToast({ title: res.msg || t('feedbackSubmitFail'), icon: 'none' })
			}
		}).catch(() => {
			operateLoading.value = false
			uni.showToast({ title: t('networkError'), icon: 'none' })
		})
	}
</script>

<style lang="scss" scoped>
	.content {
		min-height: calc(100vh - 64px);
	}

	textarea {
		font-size: 14px;
		line-height: 1.5;
		box-sizing: border-box;
	}

	input {
		font-size: 14px;
		line-height: 1.5;
		box-sizing: border-box;
	}

	button {
		font-size: 16px;
		letter-spacing: 0.5px;
	}

	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
</style>