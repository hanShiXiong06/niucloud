<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
	<!-- #endif -->
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden component-class" :style="themeColor()">
		<!-- 顶部导航栏 -->
		<view class="fixed  left-0 right-0 z-200"  :style="{top:topStyle + 'px'}">
			<view class="tab-style-1 py-[20rpx] bg-[#fff] border-0 border-solid border-b-[1rpx] border-[#f6f6f6]">
				<view class="tab-left text-[28rpx]">
					<text>共</text>
					<text class="text-primary">{{ totalCount }}</text>
					<text>条</text>
				</view>
				<view class="tab-right !items-center">
					<view @click="handleManage" class="text-[#333] text-[28rpx]">{{ isEdit ? '删除' : '批量删除' }}</view>
				</view>
			</view>
		</view>

		<!-- 商品列表区域 -->
		<scroll-view class="pt-[76rpx] pb-[168rpx]" scroll-y>
			<!-- 按日期分组的商品列表 -->
			<view v-for="(group, date) in groupedHistoryList" :key="date" class="bg-[#fff] mb-[20rpx]" >
				<view class="px-[20rpx] py-[16rpx] bg-[#f8f8f8] text-[24rpx] text-[#111] font-weight-[800]">
					{{ formatDate(date) }}
				</view>
				<!-- 修复网格布局，确保一排3个商品 -->
				<view class="px-[20rpx] py-[20rpx] flex flex-wrap -mx-[10rpx]" 
					:class="isEdit ? 'edit-mode-active' : ''">
					<view v-for="(item, index) in group" :key="item.goods_id + index" @click="redirect({url:'/addon/home_service/user/pages/goods/detail',param:{goods_id:item.goods_id}})"
						class="w-[33.33%] px-[10rpx] mb-[20rpx]">
						<view class="relative goods-item" :class="isGoodsSelected(item.goods_id) ? 'selected' : ''">
							<!-- 美化图片显示，调整比例为正方形，增加阴影和圆角 -->
							<view
								class="w-[100%] h-[220rpx] square-container rounded-[12rpx] overflow-hidden shadow-md bg-[#f8f8f8] transition-all duration-300">
								 <image
									:src="img(item.goods_cover_thumb_mid)"
									mode="scaleToFill"
									class="w-[100%] h-[100%]"
								 />
								<!-- <up-image width="220rpx" height="220rpx"  :src="img(item.goods_cover_thumb_mid)" class="absolute top-0 left-0">
									<template #error>
										<u-icon name="photo" color="#ddd" size="60"></u-icon>
									</template>
								</up-image> -->
							</view>
							<view class="mt-[12rpx]">
								<!-- 商品标题 - 添加两行截断 -->
								<view class="text-[26rpx] text-gray-800 line-clamp-2 leading-[36rpx] mb-[8rpx]">
									{{ item.goods?.goods_name || item.goods_name }}
								</view>
								<!-- 优化价格显示样式，调整大小关系 -->
								<view class="flex items-center">
									<view class="text-red-500 font-bold flex items-baseline">
										<text class="text-[22rpx] leading-[28rpx]">¥</text>
										<text
											class="text-[28rpx]">{{ formatPriceBeforeDecimal(item.member_price || item.price || item.goods?.price || '0.00') }}</text>
										<text class="text-[22rpx]">.</text>
										<text
											class="text-[22rpx]">{{ formatPriceAfterDecimal(item.member_price || item.price || item.goods?.price || '0.00') }}</text>
											<text class="price-font text-[22rpx] text-[#999] line-through font-400 ml-[10rpx]"
												v-if="item.goods_original_price && item.goods_original_price != item.member_price"><text
													class="text-[22rpx] price-font">￥</text>{{ Number(item.goods_original_price).toFixed(2) }}</text>
									</view>
								</view>
							</view>

							<!-- 管理模式下的选择框 - 美化版本 -->
							<view v-if="isEdit" class="absolute -top-[10rpx] -right-[10rpx] select-checkbox-wrapper"
								@click.stop="toggleSelectGoods(item)">
								<view class="select-checkbox" :class="isGoodsSelected(item.goods_id) ? 'checked' : ''">
									<view v-if="isGoodsSelected(item.goods_id)" class="check-icon">
										<u-icon name="checkmark" size="20" color="#fff"></u-icon>
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 空状态 - 使用默认图片路径 -->
			<view v-if="!loading && Object.keys(groupedHistoryList).length === 0"
				class="flex flex-col items-center justify-center py-[200rpx]">
				<u-empty :icon="img('static/resource/images/order_empty.png')" text="暂无足迹" />
			</view>
		</scroll-view>
	</view>
</template>

<style lang="scss" scoped>
	.component-class {
		padding-bottom: constant(safe-area-inset-bottom);
		padding-bottom: env(safe-area-inset-bottom);
	}

	/* 确保网格布局正确显示 */
	.flex-wrap {
		display: flex;
		flex-wrap: wrap;
	}

	.w-\[33\.33\%\] {
		width: 33.33%;
		box-sizing: border-box;
	}

	.-mx-\[10rpx\] {
		margin-left: -10rpx;
		margin-right: -10rpx;
	}

	.px-\[10rpx\] {
		padding-left: 10rpx;
		padding-right: 10rpx;
	}
/* 商品卡片样式优化 - 正方形（小程序兼容版） */
.square-container {
  position: relative;
  width: 100%;
}

.square-container::before {
  content: '';
  display: block;
  padding-top: 100%; /* 1:1 正方形比例 */
}

/* 移除组合选择器，改为单独定义子元素样式 */
.square-container > view,
.square-container > image,
.square-container > text {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

/* 如果知道具体子元素类名，直接指定会更高效 */
.square-container .square-content {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}
	/* 阴影效果 */
	.shadow-md {
		box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.06);
	}

	.shadow-sm {
		box-shadow: 0 1rpx 5rpx rgba(0, 0, 0, 0.05);
	}

	/* 商品名称显示优化 */
	.line-clamp-2 {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	/* 过渡动画 */
	.transition-all {
		transition-property: all;
	}

	.duration-300 {
		transition-duration: 300ms;
	}

	/* 价格加粗样式 */
	.font-bold {
		font-weight: bold;
	}
</style>

<script setup lang="ts">
	import { ref, computed, onMounted } from 'vue'
	import { onShow } from '@dcloudio/uni-app'
	import { getBrowseHistory, deleteBrowseHistory } from '@/addon/home_service/user/api/member'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '我的足迹', topStatusBar: { textColor: '#333' ,rollBgColor:"#ffffff"} })
	const menuButtonInfo = ref({});
	menuButtonInfo.value = systemStore.menuButtonInfo
	// 计算 top 样式：区分 H5 和 小程序
	const topStyle = computed(() => {
	  let top = 0;
	  // 微信小程序：按胶囊信息计算导航栏总高度，作为 top 值
	  // #ifdef MP-WEIXIN
	  // 胶囊高度（默认 32px） + 胶囊顶部到状态栏的距离（默认 10px）
	  top = (menuButtonInfo.value.height || 32) + (menuButtonInfo.value.top || 10);
	  // #endif
	
	  // H5：固定 top 值（根据设计稿调整，这里默认 50px）
	  // #ifdef H5
	  top = 0;
	  // #endif
	
	  // 返回 style 对象：top 单位为 px（适配所有端）
	  return top
	});
	// 总数
	const totalCount = ref<number>(0)
	// 历史记录数据
	const historyList = ref<any[]>([])
	// 加载状态
	const loading = ref<boolean>(false)
	// 编辑状态
	const isEdit = ref<boolean>(false)
	// 新增：已选择的商品ID数组
	const selectedGoodsIds = ref<number[]>([])
	// 新增：原始历史记录列表备份
	const originalHistoryList = ref<any[]>([])

	// 获取历史记录数据
	const getHistoryList = () => {
		console.log('getHistoryList called, fetching data...')
		loading.value = true

		let data = {
			page: 1,
			limit: 20,
			city_id:systemStore.diyAddressInfo?.city_id
		}

		console.log('Requesting browse history with params:', data)
		getBrowseHistory(data).then((res : any) => {
			console.log('API response:', res)
			if (res.code === 1) {
				totalCount.value = res.data.total
				const newData = res.data.data

				// 按浏览时间降序排序
				newData.sort((a : any, b : any) => {
					return new Date(b.browse_time).getTime() - new Date(a.browse_time).getTime()
				})

				historyList.value = newData
				// 打印数据结构以便调试
				if (newData.length > 0) {
					console.log('Sample data structure:', JSON.stringify(newData[0], null, 2))
				}
			} else {
				uni.showToast({
					title: res.msg || '获取数据失败',
					icon: 'none'
				})
			}
			loading.value = false
		}).catch((error) => {
			console.error('API request failed:', error)
			loading.value = false
			uni.showToast({
				title: '网络请求失败',
				icon: 'none'
			})
		})
	}

	// 价格格式化函数 - 参考evaluate.vue
	const formatPriceBeforeDecimal = (price : string | number) : string => {
		// 增加对无效价格的处理
		if (!price || price === 'discount' || isNaN(Number(price))) {
			return '0'
		}
		const priceStr = typeof price === 'number' ? price.toFixed(2) : price
		const parts = priceStr.split('.')
		return parts[0] || '0'
	}

	const formatPriceAfterDecimal = (price : string | number) : string => {
		// 增加对无效价格的处理
		if (!price || price === 'discount' || isNaN(Number(price))) {
			return '00'
		}
		const priceStr = typeof price === 'number' ? price.toFixed(2) : price
		const parts = priceStr.split('.')
		return parts[1] || '00'
	}

	// 日期分组的历史记录
	const groupedHistoryList = computed(() => {
		const groups : Record<string, any[]> = {}
		historyList.value.forEach(item => {
			// 提取日期部分
			const date = item.browse_time.split(' ')[0]
			if (!groups[date]) {
				groups[date] = []
			}
			groups[date].push(item)
		})
		return groups
	})

	// 格式化日期显示 - 直接显示月日格式
	const formatDate = (dateString : string) => {
		const date = new Date(dateString)
		const month = date.getMonth() + 1
		const day = date.getDate()

		return `${month}月${day}日`
	}

	// 管理功能
	const handleManage = () => {
		if (isEdit.value && selectedGoodsIds.value.length > 0) {
			// 如果当前是编辑状态且有选中的商品，点击完成会触发批量删除
			confirmBatchDelete()
		} else {
			// 切换编辑状态
			isEdit.value = !isEdit.value
			// 编辑状态切换时清空选中项
			selectedGoodsIds.value = []
		}
	}

	// 新增：判断商品是否被选中
	const isGoodsSelected = (goodsId : number) : boolean => {
		return selectedGoodsIds.value.includes(goodsId)
	}

	// 新增：切换商品选择状态
	const toggleSelectGoods = (item : any) => {
		const index = selectedGoodsIds.value.indexOf(item.goods_id)
		if (index > -1) {
			// 已选中，取消选择
			selectedGoodsIds.value.splice(index, 1)
		} else {
			// 未选中，添加选择
			selectedGoodsIds.value.push(item.goods_id)
		}
	}

	// 新增：确认批量删除
	const confirmBatchDelete = () => {
		uni.showModal({
			title: '提示',
			content: `确定要删除这${selectedGoodsIds.value.length}条浏览记录吗？`,
			confirmColor: themeColor()['--primary-color'],
			success: (res) => {
				if (res.confirm) {
					loading.value = true
					// 调用删除API
					deleteBrowseHistory({ goods_ids: selectedGoodsIds.value }).then((res : any) => {
						loading.value = false
						if (res.code === 1) {
							// 删除成功，更新前端数据
							historyList.value = historyList.value.filter(history =>
								!selectedGoodsIds.value.includes(history.goods_id)
							)
							totalCount.value = Math.max(0, totalCount.value - selectedGoodsIds.value.length)
							selectedGoodsIds.value = []
							isEdit.value = false
							uni.showToast({
								title: '删除成功',
								icon: 'success'
							})
						} else {
							uni.showToast({
								title: res.msg || '删除失败',
								icon: 'none'
							})
						}
					}).catch((error) => {
						console.error('Delete API request failed:', error)
						loading.value = false
						uni.showToast({
							title: '网络请求失败',
							icon: 'none'
						})
					})
				} else if (res.cancel && isEdit.value) {
					// 取消删除，但保持编辑状态
					selectedGoodsIds.value = []
				}
			}
		})
	}

	// 删除单条记录（保留原有功能，但增加对编辑状态的检查）
	const deleteHistoryItem = (item : any, date : string, index : number) => {
		// 只有在非编辑状态下才允许单独删除
		if (!isEdit.value) {
			uni.showModal({
				title: '提示',
				content: '确定要删除这条浏览记录吗？',
				confirmColor: themeColor()['--primary-color'],
				success: (res) => {
					if (res.confirm) {
						loading.value = true
						// 调用删除API
						deleteBrowseHistory({ goods_ids: [item.goods_id] }).then((res : any) => {
							loading.value = false
							if (res.code === 1) {
								// 删除成功，更新前端数据
								historyList.value = historyList.value.filter(history =>
									history.goods_id !== item.goods_id || history.browse_time !== item.browse_time
								)
								totalCount.value = Math.max(0, totalCount.value - 1)
								uni.showToast({
									title: '删除成功',
									icon: 'success'
								})
							} else {
								uni.showToast({
									title: res.msg || '删除失败',
									icon: 'none'
								})
							}
						}).catch((error) => {
							console.error('Delete API request failed:', error)
							loading.value = false
							uni.showToast({
								title: '网络请求失败',
								icon: 'none'
							})
						})
					}
				}
			})
		}
	}

	// 页面显示时触发
	onShow(() => {
		console.log('Page onShow, loading data...')
		getHistoryList()
	})

	// 页面挂载时的处理
	onMounted(() => {
		console.log('Page mounted, loading data...')
		getHistoryList()
	})

	// 主题颜色
	const themeColor = () => {
		return {
			'--primary-color': '#ff6b35',
			'--page-bg-color': '#f8f8f8',
			'--text-color': '#333333',
			'--text-secondary-color': '#666666',
			'--border-color': '#f6f6f6',
			'--rounded-mid': '8rpx'
		}
	}
</script>

<style lang="scss" scoped>
	/* 新增：优化删除选择框样式 - 美化版本 */
	.select-checkbox-wrapper {
		width: 52rpx;
		height: 52rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 10;
	}

	.select-checkbox {
		width: 44rpx;
		height: 44rpx;
		border: 3rpx solid #dcdcdc;
		border-radius: 50%;
		background-color: rgba(255, 255, 255, 0.95);
		box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.15);
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		position: relative;
		overflow: hidden;
	}

	.select-checkbox:active {
		transform: scale(0.92);
	}

	.select-checkbox.checked {
		border-color: #ff6b35;
		background-color: #ff6b35;
		box-shadow: 0 4rpx 16rpx rgba(255, 107, 53, 0.4);
	}

	.check-icon {
		width: 100%;
		height: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		animation: checkScale 0.3s ease-out;
	}

	@keyframes checkScale {
		0% {
			transform: scale(0.6);
			opacity: 0;
		}

		50% {
			transform: scale(1.1);
		}

		100% {
			transform: scale(1);
			opacity: 1;
		}
	}

	/* 为商品卡片添加选中状态的背景效果 */
	.goods-item {
		transition: all 0.3s ease;
	}

	.goods-item.selected {
		// background-color: rgba(255, 107, 53, 0.05);
	}

	/* 优化编辑状态下的整体视觉效果 */
	.edit-mode-active {
		.select-checkbox-wrapper {
			transform: scale(1);
		}
	}
</style>