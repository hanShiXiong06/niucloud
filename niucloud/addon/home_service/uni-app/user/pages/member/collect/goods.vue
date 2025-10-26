<template>
	
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<u-navbar :title="t('pageTitle')" autoBack :fixed="true" placeholder>
	</u-navbar>
	<!-- #endif -->
	
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden component-class" :style="themeColor()">
		<!-- 顶部导航栏 -->
		<view class="sticky top-0 z-10 bg-white shadow-sm">
			<!-- 分类切换 -->
			<view class="flex border-b border-[#f0f0f0]">
				<view v-for="(tab, index) in tabs" :key="index" class="flex-1 py-[28rpx] text-center relative"
					@click="switchTab(index)">
					<text
						:class="['text-[28rpx]', currentTab === index ? 'text-[var(--primary-color)] font-medium' : 'text-[#333]']">
						{{ tab.name }}
					</text>
					<view v-if="currentTab === index"
						class="absolute bottom-0 left-1/3 right-1/3 h-[4rpx] bg-[var(--primary-color)] rounded-full">
					</view>
				</view>
			</view>
		</view>

		<!-- 主体内容区域 -->
		<mescroll-body ref="mescrollRef" top="0" @init="mescrollInit" :down="{ use: false }" @up="getGoodsCollectList" v-if="currentTab==0">
			<view class="py-[var(--top-m)] px-[30rpx]" v-if="goodsList.length">
				<!-- 商品列表 -->
				<u-swipe-action ref="swipeActive">
					<template v-for="(item, index) in goodsList" :key="item.id">
						<view class="mb-[20rpx] rounded-lg overflow-hidden w-full" @click.stop="redirect({url:'/addon/home_service/user/pages/goods/detail',param:{goods_id:item.goods_id}})">
							<u-swipe-action-item :options="swipeOptions" @click.stop="handleSwipeAction(item)">
								<!-- 增加padding使白色背景比循环体大一些 -->
								<view class="flex bg-white rounded-[26rpx] overflow-hidden p-[20rpx]">
									<!-- 商品图片 -->
									<image class="w-[200rpx] h-[200rpx] rounded-lg"
										:src="img(item.goods.goods_cover || 'static/resource/images/diy/shop_default.jpg')"
										:mode="'aspectFill'"
										@error="() => { item.goods.goods_cover = 'static/resource/images/diy/shop_default.jpg' }" />
									<!-- 商品信息 -->
									<view class="flex-1 flex flex-col p-[0_20rpx] ">
										<view class="overflow-hidden h-[100%] flex flex-col justify-between">
											<!-- 商品名称 - 确保与图片顶部对齐 -->
											<view class="mb-[28rpx]">
												<text class="text-[28rpx] text-[#333] leading-[40rpx] line-clamp-2">
													{{ item.goods_name }}
												</text>
											</view>
											<!-- 副标题 -->
											<view
												class="text-[24rpx] text-[var(--text-color-light6)] leading-[34rpx] mb-[28rpx]">
												{{ item.goods?.goods_subtitle }}
											</view>
											<!-- 价格和销量显示在副标下方同一行 -->
											<view class="flex items-center justify-between">
												<view class="text-red-500 font-bold">
													<text class="text-xs leading-[20rpx]">¥</text>
													<text
														class="text-lg">{{ formatPriceBeforeDecimal(item.member_price || item.price) }}</text>
													<text class="text-xs">.</text>
													<text
														class="text-xs">{{ formatPriceAfterDecimal(item.member_price || item.price) }}</text>
												</view>
												<view class="text-[24rpx] text-[var(--text-color-light6)]">
													{{ t('sold') }}{{ item.goods?.sale_num || 0 }}+
												</view>
											</view>
										</view>
									</view>
								</view>
							</u-swipe-action-item>
						</view>
					</template>
				</u-swipe-action>
			</view>

			<!-- 空状态 -->
			<mescroll-empty v-else :icon="'none'" :loading="loading" />
		</mescroll-body>
		
		<!-- 师傅收藏列表 Tab -->
		<mescroll-body ref="mescrollRef" top="0" @init="mescrollInit" :down="{ use: false }" @up="getTechnicianCollectListFn" v-else-if="currentTab==1">
			<view class="py-[var(--top-m)] px-[30rpx]" v-if="technicianList.length">
				<u-swipe-action ref="swipeActive">
					<template v-for="(item, index) in technicianList" :key="item.id">
						<view class="mb-[20rpx] bg-white rounded-lg overflow-hidden w-full">
							<u-swipe-action-item :options="swipeOptions" @click.stop="handleTechnicianSwipeAction(index)">
								<view class="flex overflow-hidden p-[20rpx]">
									<view class="relative mr-[20rpx] z-20">
										<image class="w-[120rpx] h-[120rpx] rounded-full"
											:src="img(item.technician?.headimg || 'static/resource/images/addon/home_service/user/technician_default.png')"
											:mode="'aspectFill'"
											@error="() => { item.technician.headimg = 'static/resource/images/addon/home_service/user/technician_default.png' }" />
										<text
											:class="item.technician?.status == '1' ? 'text-xs bg-green-500 text-white' : 'text-xs bg-gray-400 text-white'"
											class="px-2.5 py-0.5 rounded-full absolute top-[100rpx] left-1/2 transform -translate-x-1/2 z-30 whitespace-nowrap">
											{{ item.technician?.status == '1' ? t('working') : t('resting') }}
										</text>
									</view>
									<view class="flex-1 flex flex-col justify-center p-[0_20rpx]">
										<view class="flex items-center justify-between mb-[8rpx]">
											<text class="text-[28rpx] text-[#333] font-medium">{{ item.technician?.real_name }}</text>
											<view class="flex items-center">
												<u-icon name="star-fill" size="15" color="#FF3502" class="mr-0.5"></u-icon>
												<text class="text-[#FF3502] text-[26rpx] font-bold">{{ item.technician?.evaluate_avg_scores || '0.0' }}</text>
											</view>
										</view>
										<view class="text-[24rpx] text-[var(--text-color-light6)] mb-[8rpx]">
											服务{{item.technician?.order_num}}单
										</view>
										<view class="flex flex-wrap mb-[8rpx]">
											<view class="relative z-10 flex flex-wrap">
												<text v-for="(category, idx) in item.technician?.category_name" :key="category.category_id" class="text-[24rpx] bg-[#F6F6F6] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]" v-show="idx < 2">{{ category.category_name }}</text>
												<text v-if="item.technician?.category_name?.length >= 3 && !item.showMoreTag" @click.stop="item.showMoreTag = true" class="text-[24rpx] bg-[#F6F6F6] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]">...</text>
												<text v-for="(category, idx) in item.technician?.category_name" :key="category.category_id" class="text-[24rpx] bg-[#F6F6F6] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]" v-show="idx >= 2 && item.showMoreTag">{{ category.category_name }}</text>
												<text v-if="item.showMoreTag" @click.stop="item.showMoreTag = false" class="text-[24rpx] bg-[#F6F6F6] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx] flex items-center leading-1"><text class="iconfont iconshangV6xx-1"></text></text>
											</view>
										</view>
									</view>
								</view>
							</u-swipe-action-item>
						</view>
					</template>
				</u-swipe-action>
			</view>
			<mescroll-empty v-else :icon="'none'" :loading="loading" />
		</mescroll-body>

	</view>
</template>

<script setup lang="ts">
	import { ref, computed, onMounted } from 'vue'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import { getCollectList, cancelCollect, getTechnicianCollectList, cancelTechnicianCollect } from '@/addon/home_service/user/api/collect'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	// 价格格式化函数 - 处理整数部分
	const formatPriceBeforeDecimal = (price : string | number) : string => {
		if (!price) return '0'
		const priceStr = String(price)
		const index = priceStr.indexOf('.')
		if (index !== -1) {
			return priceStr.substring(0, index)
		}
		return priceStr
	}

	// 价格格式化函数 - 处理小数部分
	const formatPriceAfterDecimal = (price : string | number) : string => {
		if (!price) return '00'
		const priceStr = String(price)
		const index = priceStr.indexOf('.')
		if (index !== -1) {
			// 截取小数部分，并确保是2位
			return (priceStr.substring(index + 1) + '00').substring(0, 2)
		}
		return '00'
	}

	// 标签页数据
	const tabs = ref([
		{ name: t('goodsCollect'), type: 'goods' },
		{ name: t('technicianCollect'), type: 'technician' }
	])
	const currentTab = ref(0)

	// 列表相关数据
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	const goodsList = ref<Array<any>>([])
	const technicianList = ref<Array<any>>([])
	const loading = ref<boolean>(false)
	const optionLoading = ref(false)

	// 滑动操作选项
	const swipeOptions = ref([
		{ text: t('delete'), style: { backgroundColor: '#EF000C', width: '160rpx', height: '100%' } }
	])

	// 切换标签页
	const switchTab = (index : number) => {
		console.log(index)
		if (currentTab.value === index) return
		currentTab.value = index
		if (index == 0) {
			resetGoodsList()
		} else if (index == 1) {
			resetTechnicianList()
		}
	}

	// 获取收藏列表（使用Promise.then链式调用）
	const getGoodsCollectList = (mescroll : any) => {
		loading.value = false
		let data : object = {
			page: mescroll.num,
			pageSize: 10,
			city_id:systemStore.diyAddressInfo?.city_id
		};

		getCollectList(data).then((res : any) => {
			if (mescroll.num === 1) {
				goodsList.value = []
			}
			goodsList.value = goodsList.value.concat(res.data.data)
			mescroll.endSuccess(res.data.data.length)
		}).catch(() => {
			loading.value = true
			mescroll.endErr()
		}).finally(() => {
			loading.value = true
		})
	}

	// 处理滑动操作 - 取消收藏（使用Promise.then链式调用）
	const handleSwipeAction = (item : any) => {
		if (optionLoading.value) return
		optionLoading.value = true

		cancelCollect({ goods_ids: item.goods_id }).then((res : any) => {
			if (res.code === 1) {
				const index = goodsList.value.findIndex(goods => goods.id === item.id)
				if (index !== -1) {
					goodsList.value.splice(index, 1)
				}
				uni.showToast({ title: t('cancelCollectSuccess'), icon: 'success' })
			} else {
				uni.showToast({ title: res.msg || t('cancelCollectFail'), icon: 'none' })
			}
		}).catch(() => {
			uni.showToast({ title: t('cancelCollectFail'), icon: 'none' })
		}).finally(() => {
			optionLoading.value = false
		})
	}

	// 获取师傅收藏列表
	const getTechnicianCollectListFn = (mescroll : any) => {
		loading.value = false;
		let data : object = {
			page: mescroll.num,
			pageSize: 10
		};
		getTechnicianCollectList(data).then((res : any) => {
			const newArr = (res.data.data as Array<any>);
			if (mescroll.num == 1) {
				technicianList.value = [];
			}
			if (newArr.length) {
				newArr.forEach((item) => { item.showMoreTag = false })
			}
			technicianList.value = technicianList.value.concat(newArr);
			mescroll.endSuccess(newArr.length);
			loading.value = true;
		}).catch(() => {
			loading.value = true;
			mescroll.endErr();
		})
	}

	// 处理师傅滑动操作 - 取消师傅收藏
	const handleTechnicianSwipeAction = (index : number) => {
		if (optionLoading.value) return
		optionLoading.value = true
		const item = technicianList.value[index]
		cancelTechnicianCollect({ technician_ids: item.technician_id }).then(() => {
			technicianList.value.splice(index, 1)
		}).catch(() => {
			uni.showToast({ title: t('cancelCollectFail'), icon: 'none' })
		}).finally(() => {
			optionLoading.value = false
		})
	}

	// 返回上一页
	const back = () => {
		uni.navigateBack()
	}

	// 重置列表（拆分为商品/师傅）
	const resetGoodsList = () => {
		goodsList.value = []
		if (getMescroll()) {
			getMescroll().resetUpScroll()
		}
	}
	const resetTechnicianList = () => {
		technicianList.value = []
		if (getMescroll()) {
			getMescroll().resetUpScroll()
		}
	}

	// 监听页面显示，重置列表
	onMounted(() => {
		if(currentTab.value === 0){
			resetGoodsList()
		}else{
			resetTechnicianList()
		}
	})

	// 主题色处理
	const themeColor = () => {
		return {
			'--primary-color': '#07C160',
			'--price-text-color': '#EF000C',
			'--text-color-light6': '#999999',
			'--page-bg-color': '#F5F5F5',
			'--top-m': '20rpx',
			'--rounded-big': '20rpx'
		}
	}
</script>

<style lang="scss" scoped>
	:deep(.mescroll-empty) {
		min-height: 60vh;
	}

	:deep(.u-swipe-action-item__right) {
		padding: 2rpx;
	}

	:deep(.u-swipe-action-item__right__button__wrapper) {
		padding: 0 10rpx !important;
	}

	:deep(.u-swipe-action-item__right__button__wrapper__text) {
		font-size: 24rpx !important;
	}

	.order-list .mescroll-body {
		padding-bottom: constant(safe-area-inset-bottom) !important;
		padding-bottom: env(safe-area-inset-bottom) !important;
	}
</style>