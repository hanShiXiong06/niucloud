<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
	<!-- #endif -->
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" v-if="!loading" :style="themeColor()">
		<!-- 内容区域 -->
		<view class="content p-3">
			<view class="bg-white rounded-lg shadow-sm p-4">
				<!-- 显示标题 -->
				<view class="title text-[45rpx] font-medium mb-4">{{ helpDetail.name }}</view>

				<!-- 修复富文本内容显示问题 -->
				<view class="rich-content">
					<!-- 修改tag-style为正确的格式 -->
					<u-parse :content="helpDetail.content || ''"
						:tag-style="{p: 'margin: 0 0 16px 0', img: 'max-width: 100%'}"></u-parse>
				</view>

				<!-- 显示其他相关信息 -->
				<view class="mt-4">
					<view class="info-item flex justify-between items-center py-2 border-t border-gray-100">
						<text class="text-gray-500">{{ t('viewsCount') }}</text>
						<text>{{ helpDetail.views_count || 0 }}</text>
					</view>
					<view class="info-item flex justify-between items-center py-2 border-t border-gray-100">
						<text class="text-gray-500">{{ t('updateTime') }}</text>
						<text>{{ helpDetail.update_time }}</text>
					</view>
				</view>
			</view>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref } from 'vue'
	import { onLoad } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { getTechnicianHelpDetail } from '@/addon/home_service/user/api/member'
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '帮助详情', topStatusBar: { textColor: '#333', rollBgColor: "#ffffff" } })
	const helpDetail = ref<any>(null)
	const loading = ref<boolean>(false)

	// onLoad接参方式 - 符合项目规范
	onLoad((data : any) => {
		// 从页面参数中获取help_id
		const helpId = data.helpId || data.id || 0

		// 检查ID是否存在且有效
		if (helpId) {
			fetchHelpDetail(parseInt(helpId))
		} else {
			// 处理没有ID的情况
			uni.showToast({
				title: t('missingId'),
				icon: 'none'
			})
		}
	})

	// 获取帮助详情数据
	const fetchHelpDetail = (id : number) => {
		loading.value = true

		getTechnicianHelpDetail(id).then((res : any) => {
			console.log('API响应数据:', res) // 添加日志查看完整响应
			// 根据API实际返回的code值来判断成功条件
			if (res.code === 1 && res.data) {
				// 确保content是字符串类型
				const data = { ...res.data }
				if (typeof data.content !== 'string') {
					data.content = String(data.content || '')
				}
				helpDetail.value = data
				console.log('富文本内容:', helpDetail.value.content) // 打印富文本内容确认数据
			} else {
				uni.showToast({
					title: res.msg || '获取数据失败',
					icon: 'none'
				})
			}
			loading.value = false
		}).catch(() => {
			uni.showToast({
				title: t('networkError'),
				icon: 'none'
			})
		}).finally(() => {
			loading.value = false
		})
	}

	// 返回上一页
	const back = () => {
		uni.navigateBack()
	}
</script>

<style lang="scss" scoped>
	.content {
		min-height: calc(100vh - 64px);
	}

	.info-item {
		font-size: 14px;
	}

	.rich-content {
		font-size: 14px;
		line-height: 1.6;
	}
</style>