<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
	<!-- #endif -->
	<view class="flex justify-between fixed w-full z-index-99 bg-[#fff] p-[24rpx] px-[56rpx] box-border"
		:style="themeColor()">
		<view class="relative flex justify-center items-center flex-col !text-[28rpx] !leading-[2]" v-for="(item,index) in invoiceStateList"
			:key="index" @click="invoiceStateFn(item)">
			{{item.name}}
			<view class="w-[40rpx] h-[6rpx] rounded-lg"
			:style="{backgroundColor:item.status == currentState ? 'var(--primary-color)' : '#fff'}"
				></view>
		</view>
	</view>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()">
		<!-- 主体内容区域 -->
		<mescroll-body ref="mescrollRef" @init="mescrollInit" :down="{ use: false }" @up="getInvoiceListFn" top="104"
			class="mescroll-body">
			<!-- 订单列表 -->
			<template v-for="(item, index) in invoiceList" :key="item.order_no">
				<view class="flex m-[25rpx]" v-if="currentState == 'pending'">
					<view class="flex-shrink-0 flex items-center mr-[25rpx]">
						<view class="select-checkbox-wrapper" @click="handleSelect(item.order_id)">
							<view class="select-checkbox" :class="item.checked ? 'checked' : ''">
								<view v-if="item.checked" class="check-icon">
									<u-icon name="checkmark" size="20" color="#fff"></u-icon>
								</view>
							</view>
						</view>
					</view>
					<view class="bg-white rounded-lg overflow-hidden flex-1 p-[24rpx]">
						<!-- 订单头部 -->
						<view class="flex justify-between items-center border-b border-gray-100">
							<view class="flex items-center text-sm text-[#111] flex-1">
								<text>{{ item.order_no }}</text>
								<text class="iconfuzhi iconfont ml-[15rpx]" @click="copyOrderId(item.order_no)"></text>
							</view>
							<view class="text-[26rpx]" :class="getOrderStatusClass(item)">
								{{ getOrderStatusText(item) }}
							</view>
						</view>

						<!-- 订单内容 -->
						<view class="flex mt-[24rpx]">

							<!-- 服务信息 -->
							<view class="flex-1">
								<view class="flex">
									<!-- 服务图片 -->
									<view class="w-20 h-20 bg-gray-100 rounded overflow-hidden flex-shrink-0">
										<image
											:src="item.item && item.item[0] && item.item[0].item_image_thumb_small ? img(item.item[0].item_image_thumb_small) : img('static/resource/images/diy/shop_default.jpg')"
											mode="aspectFill" class="w-full h-full"></image>
									</view>

									<!-- 服务详情 -->
									<view class="ml-3 flex-1 flex flex-col justify-between">
										<view class="flex justify-between  items-center mb-1">
											<view class="font-medium">
												{{ item.order_name }}
											</view>
											<view class="text-sm text-gray-500">×1</view>
										</view>
										<view class="text-sm text-gray-500 mb-1">时间：{{ formatDate(item.create_time) }}
										</view>
										<!-- 优化实付款金额显示 -->
										<view class=" text-[28rpx] font-bold flex items-end">
											<text class="text-[24rpx] price-font mr-[10rpx]">实付款</text>
											<text
												class="text-[24rpx] price-font text-[var(--price-text-color)]">￥</text>
											<text
												class="price-font text-[36rpx] text-[var(--price-text-color)]">{{ formatPriceBeforeDecimal(item.pay_money) }}</text>
											<text
												class="price-font text-[24rpx] text-[var(--price-text-color)]">.{{ formatPriceAfterDecimal(item.pay_money) }}</text>
										</view>
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>
				<view class="" v-else>
					<view class="bg-[#fff] m-[24rpx] p-[24rpx] rounded-lg">
						<view class="flex justify-between items-center border-b border-gray-100">
							<view class="flex items-center text-sm text-[#111] flex-1 text-[26rpx]">
								<text>{{ item.type_name }}</text>
								<text class="text-[26rpx] text-[#999999] ml-[15rpx]">{{ item.create_time }}</text>
							</view>
							<view class="text-[26rpx]" :class="getOrderStatusClass(item)">
								{{ getOrderStatusText(item) }}
							</view>
						</view>
						<view class="text-[28rpx] my-[25rpx] ">
							{{item.header_name}}
						</view>
						<view class="flex justify-between items-center">
							<view>
								<view class="text-[#999999] text-[26rpx]">
									发票代码
								</view>
								<view class="text-[28rpx] mt-[15rpx]">
									{{currentState != 'processing' ? item.invoice_number :item.tax_number}}
								</view>
							</view>
							<view v-if=" currentState == 'processing'">
								<view class="price-font text-[36rpx] text-[var(--price-text-color)]">
									￥{{item.order_money}}
								</view>
							</view>
							<view v-else>
								<view class="price-font text-[36rpx] text-[var(--price-text-color)]">
									￥{{item.money}}
								</view>
							</view>
						</view>
						<view class="flex mt-[15rpx] justify-end" v-if=" currentState != 'processing'">
							<u-button size="small" @click="uploadInvoice(item.invoice_voucher)"
								class="!mx-0 !ml-[15rpx] !w-auto !text-[var(--primary-color)] !px-[55rpx] !border-[var(--primary-color)] !rounded-[10rpx]">
								下载发票
							</u-button>
						</view>
					</view>
				</view>
			</template>

			<!-- 空状态 -->
			<mescroll-empty :option="{ 
          'icon': img('static/resource/images/empty.png'), 
          'tip': t('nothingMore') 
        }" v-if="!invoiceList.length && !loading"></mescroll-empty>
		</mescroll-body>
		<!-- 底部操作栏 -->
		<view v-if="currentState == 'pending'"
			class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-3 flex items-center justify-between body-bottom z-index-998">
			<view class="flex flex-col items-start w-[300rpx]">
				<view class="flex items-center">
					<view class="text-sm">合计：</view>
					<view class="text-red-500 font-bold">
						<text class="text-xs leading-[20rpx]">¥</text>
						<text class="text-lg">{{ formatPriceBeforeDecimal(totalPrice) }}</text>
						<text class="text-xs">.</text>
						<text class="text-xs">{{ formatPriceAfterDecimal(totalPrice) }}</text>
					</view>
				</view>
				<view class="text-sm text-gray-500">(共{{ selectedCount }}个订单)</view>
			</view>
			<u-button type="primary" class="w-[100%] !mx-0 flex-1 !ml-[25rpx] !rounded-lg" @click="confirmInvoice"
				:disabled="selectedCount === 0">
				开票
			</u-button>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, computed } from 'vue'
	import { onLoad, onPageScroll, onReachBottom, onShow } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { img } from '@/utils/common'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import useConfigStore from '@/stores/config'
	import { getInvoiceOrderList, getInvoiceList } from '@/addon/home_service/user/api/invoice'
	import { topTabar } from '@/utils/topTabbar';
	// 系统状态管理
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '发票列表', topStatusBar: { textColor: '#333' } })
	// 配置状态管理
	const configStore = useConfigStore()

	// mescroll 相关
	const { mescrollInit, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	const mescrollRef = ref(null)

	// 数据状态
	const invoiceList = ref<Array<any>>([])
	const loading = ref<boolean>(true)
	const currentState = ref('pending') // pending: 待申请, processing: 待开票, success: 开票成功
	const invoiceStateList = ref([
		{ name: '待申请', status: 'pending' },
		{ name: '待开票', status: 'processing' },
		{ name: '开票成功', status: 'success' }
	])

	// 计算属性
	const selectedCount = computed(() => {
		return invoiceList.value.filter(item => item.checked).length
	})



	const totalPrice = computed(() => {
		const sum = invoiceList.value
			.filter(item => item.checked)
			.reduce((acc, item) => acc + Number(item.pay_money), 0);
		console.log(sum)
		return sum.toFixed(2);
	});

	const uploadInvoice = (url : any) => {
		url = img(url)
		// #ifdef MP-WEIXIN
		uni.downloadFile({
			url: url, // 替换为实际的PDF文件地址
			success: (res) => {
				if (res.statusCode === 200) {
					// 下载成功，res.tempFilePath为临时文件路径
				} else {
					console.error('文件下载失败，状态码:', res.statusCode);
				}
			},
			fail: (err) => {
				console.error('文件下载失败:', err);
			}
		});
		// #endif
		// #ifdef H5
		const fileUrl = url; // 替换为实际的PDF文件链接
		const link = document.createElement('a');
		link.href = fileUrl;
		link.download = 'invoice.pdf'; // 可选，设置下载后的文件名
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		// #endif
	}
	// 主题色
	// 页面加载
	onLoad(() => {
		// 初始化时加载第一页数据
		getInvoiceListFn({ num: 1, size: 10 })
	})
	onShow(() => {
		if (getMescroll()) {
			getMescroll().resetUpScroll()
		}
	})
	// 复制订单编号
	const copyOrderId = (orderNo : string) => {
		uni.setClipboardData({
			data: orderNo,
			success: () => {
				uni.showToast({
					title: '订单编号已复制',
					icon: 'none'
				})
			}
		})
	}

	// 切换状态
	const invoiceStateFn = (e : any) => {
		currentState.value = e.status
		invoiceList.value = []
		getMescroll().resetUpScroll()
	}

	// 获取订单状态文本
	const getOrderStatusText = (item : any) => {
		if (item.is_issue_invoice === 1) {
			return '已开票'
		}
		if (currentState.value === 'pending') {
			return '待申请'
		} else if (currentState.value === 'processing') {
			return '待开票'
		} else {
			return '开票成功'
		}
	}

	// 获取订单状态样式类
	const getOrderStatusClass = (item : any) => {
		if (item.is_issue_invoice === 1) {
			return 'text-[#4CAF50]'
		}
		return currentState.value === 'pending' ? 'text-[#FF0000]' : 'text-[#ff6b35]'
	}

	// 格式化日期
	const formatDate = (dateStr : string) => {
		if (!dateStr) return ''
		// 将 2025-09-24 09:10:32 格式化为 09月24日 09:10
		const date = new Date(dateStr)
		const month = String(date.getMonth() + 1).padStart(2, '0')
		const day = String(date.getDate()).padStart(2, '0')
		const hours = String(date.getHours()).padStart(2, '0')
		const minutes = String(date.getMinutes()).padStart(2, '0')
		return `${month}月${day}日 ${hours}:${minutes}`
	}

	// 获取发票订单列表
	const getInvoiceListFn = (mescroll : any) => {
		loading.value = true

		// 构建API请求参数
		const params = {
			page: mescroll.num,
			limit: mescroll.size,
			status: currentState.value == 'processing' ? 0 : 1
		}
		const requestFn = currentState.value == 'pending' ? getInvoiceOrderList : getInvoiceList;
		requestFn(params).then((res : any) => {
			// 后续处理逻辑保持不变
			if (res.code === 1 && res.data && res.data.data) {
				const newData = res.data.data.map((item : any) => ({
					...item,
					checked: false,
					pay_money: Number(item.pay_money) // 强制转为数字
				}));
				if (mescroll.num === 1) {
					invoiceList.value = [];
				}

				invoiceList.value = invoiceList.value.concat(newData);
				mescroll.endSuccess(newData.length);
			} else {
				mescroll.endSuccess(0);
			}
			loading.value = false;
		}).catch(() => {
			loading.value = false;
		});
	}

	// 选择/取消选择订单
	const handleSelect = (orderId : number) => {
		const item = invoiceList.value.find(item => item.order_id === orderId)
		if (item) {
			item.checked = !item.checked
		}
	}

	// 确定开票
	const confirmInvoice = () => {
		if (selectedCount.value === 0) {
			uni.showToast({
				title: '请至少选择一个订单',
				icon: 'none'
			})
			return
		}

		// 获取选中的订单ID列表
		const selectedOrderIds = invoiceList.value
			.filter(item => item.checked)
			.map(item => item.order_id)

		// 跳转到开票申请页面，并传递订单ID和合计金额
		uni.navigateTo({
			url: `/addon/home_service/user/pages/member/invoice/apply?orderIds=${JSON.stringify(selectedOrderIds)}&amount=${totalPrice.value}`
		})
	}




	// 价格格式化函数
	// 价格格式化函数（保留原始兼容性，修复边界问题）
	const formatPriceBeforeDecimal = (price : string | number) => {
		// 处理空值或非有效值
		if (price === null || price === undefined || price === '') {
			return '0';
		}
		// 统一转为字符串（处理数字和字符串入参）
		const priceStr = typeof price === 'number'
			? price.toFixed(2)
			: (price.includes('.') ? price : `${price}.00`); // 确保有小数位
		const [before] = priceStr.split('.');
		return before || '0'; // 兜底防空
	};

	const formatPriceAfterDecimal = (price : string | number) => {
		if (price === null || price === undefined || price === '') {
			return '00';
		}
		const priceStr = typeof price === 'number'
			? price.toFixed(2)
			: (price.includes('.') ? price : `${price}.00`);
		const [, after] = priceStr.split('.');
		// 确保小数位是2位（补零）
		return after ? after.padEnd(2, '0').slice(0, 2) : '00';
	};
</script>

<style lang="scss" scoped>
	.body-bottom {
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	.mescroll-body {
		padding-bottom: 120rpx !important;
	}

	/* 圆形选择框样式 */
	.select-checkbox-wrapper {
		width: 38rpx;
		height: 38rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 10;
	}

	.select-checkbox {
		width: 36rpx;
		height: 36rpx;
		border: 2rpx solid #dcdcdc;
		border-radius: 50%;
		background-color: rgba(255, 255, 255, 0.95);
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
		border-color: #ff3b30;
		background-color: #ff3b30;
		box-shadow: 0 4rpx 16rpx rgba(255, 59, 48, 0.4);
	}

	.check-icon {
		width: 100%;
		height: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		animation: checkScale 0.3s ease-out;
	}
</style>