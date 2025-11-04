<template>
	<view class="show-price-page">
		<!-- 顶部导航栏 -->
		<u-navbar
			:title="pageTitle"
			:safeAreaInsetTop="true"
			:placeholder="true"
			:autoBack="true"
		></u-navbar>

		<!-- 加载状态 -->
		<view v-if="loading" class="loading-container">
			<u-loading mode="circle" size="50"></u-loading>
			<text class="loading-text">加载中...</text>
		</view>

		<!-- 空状态 -->
		<view v-else-if="!loading && groupedTables.length === 0" class="empty-container">
			<u-empty mode="data" text="暂无报价数据"></u-empty>
		</view>

		<!-- 报价数据 -->
		<view v-else class="price-content">
			<!-- 报价信息头部 -->
			<view class="price-header">
				<view class="header-item">
					<text class="label">报价类型：</text>
					<text class="value">{{ priceTypeName }}</text>
				</view>
				<view class="header-item">
					<text class="label">更新时间：</text>
					<text class="value">{{ updateTime }}</text>
				</view>
			</view>

			<!-- 多表格展示 -->
			<view class="tables-wrapper">
				<view
					v-for="(table, tableIdx) in groupedTables"
					:key="table.id"
					class="table-container"
				>
					<!-- 表格标题 -->
					<view class="table-title">
						<text class="title-text">配置组 {{ tableIdx + 1 }}</text>
						<text class="title-meta">{{ table.modelCount }}个型号 · {{ table.configColumns.length }}项配置</text>
					</view>

					<!-- 配置标签 -->
					<view class="config-tags">
						<view
							v-for="config in table.configColumns"
							:key="config"
							class="config-tag"
						>
							{{ config }}
						</view>
					</view>

					<!-- Excel样式表格 -->
					<scroll-view scroll-x="false" class="table-scroll">
						<view class="excel-table">
							<!-- 表头 -->
							<view class="table-header">
								<view class="header-cell col-model">型号</view>
								<view class="header-cell col-capacity">容量</view>
								<view
									v-for="config in table.configColumns"
									:key="config"
									class="header-cell col-price"
								>
									{{ config }}
								</view>
								<view class="header-cell col-remark">备注</view>
							</view>

							<!-- 表体 -->
							<view class="table-body">
								<view
									v-for="(row, rowIdx) in table.rows"
									:key="rowIdx"
									class="table-row"
								>
									<!-- 型号列（支持跨行） -->
									<view
										v-if="row.showModel"
										class="body-cell col-model model-merged"
										:style="{ height: getCellHeight(row.modelRowspan) }"
									>
										<text class="cell-text">{{ row.goods_name }}</text>
									</view>

									<!-- 容量列 -->
									<view class="body-cell col-capacity">
										<text class="cell-text">{{ row.capacity }}</text>
									</view>

									<!-- 价格列 -->
									<view
										v-for="config in table.configColumns"
										:key="config"
										class="body-cell col-price"
									>
										<view v-if="row.prices[config]" class="price-box">
											<view class="price-item original">
												<text class="price-label">原价：</text>
												<text class="price-value">¥{{ row.prices[config].original || '-' }}</text>
											</view>
											<view class="price-item final">
												<text class="price-label">现价：</text>
												<text class="price-value">¥{{ row.prices[config].final || '-' }}</text>
											</view>
										</view>
										<text v-else class="empty-cell">-</text>
									</view>

									<!-- 备注列（支持跨行） -->
									<view
										v-if="row.showRemark"
										class="body-cell col-remark remark-merged"
										:style="{ height: getCellHeight(row.remarkRowspan) }"
									>
										<text class="remark-text">{{ row.value_info || '-' }}</text>
									</view>
								</view>
							</view>
						</view>
					</scroll-view>
				</view>
			</view>
		</view>

		<!-- 底部提示 -->
		<view v-if="!loading && groupedTables.length > 0" class="footer-tip">
			<text class="tip-text">数据仅供参考，实际价格以最终评估为准</text>
		</view>
	</view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getQuotationPriceList, type QuotationPriceData } from '@/addon/recycle/api/quotation'

// 页面参数
const priceTypeId = ref<string>('')
const pageTitle = ref('报价查询')

// 数据状态
const loading = ref(false)
const tableData = ref<QuotationPriceData[]>([])
const priceTypeName = ref('')
const updateTime = ref('')

// 配置项排序规则
const CONFIG_SORT_ORDER = [
	'全套充新    橙色', '全套充新    白色', '全套充新    蓝色',
	'靓机-单机100🔋在保100+', '高保靓充50次内在保280+', '靓机-单机 95电池＋在保60+', '小花电池95+保修无要求',
	'高保靓充100次内在保250+', '中保靓充100🔋在保100+', '靓机', '小花',
	'花机', '内爆可测'
]

// 配置项排序函数
function sortConfigItems(configItems: string[]): string[] {
	return configItems.sort((a, b) => {
		const indexA = CONFIG_SORT_ORDER.indexOf(a)
		const indexB = CONFIG_SORT_ORDER.indexOf(b)

		if (indexA !== -1 && indexB !== -1) {
			return indexA - indexB
		}
		if (indexA !== -1) return -1
		if (indexB !== -1) return 1
		return a.localeCompare(b, 'zh-CN')
	})
}

// 按配置项分组并处理跨行逻辑
const groupedTables = computed(() => {
	const data = tableData.value
	if (data.length === 0) return []

	const configGroupMap = new Map<string, any[]>()

	// 按配置项组合分组
	data.forEach(row => {
		if (row.prices) {
			const configKeys = sortConfigItems(Object.keys(row.prices))
			const configKey = configKeys.join('|||')

			if (!configGroupMap.has(configKey)) {
				configGroupMap.set(configKey, [])
			}
			configGroupMap.get(configKey)!.push(row)
		}
	})

	const tables: any[] = []
	let tableIndex = 0

	configGroupMap.forEach((rows, configKey) => {
		tableIndex++
		const configColumns = configKey.split('|||')

		const processedRows: any[] = []
		let currentModel = ''
		let modelStartIndex = 0
		let currentRemark = ''
		let remarkStartIndex = 0

		rows.forEach((row, index) => {
			const processedRow = { ...row }

			// 处理型号跨行
			if (row.goods_name !== currentModel) {
				if (modelStartIndex < index) {
					for (let i = modelStartIndex; i < index; i++) {
						processedRows[i].modelRowspan = index - modelStartIndex
					}
				}
				currentModel = row.goods_name
				modelStartIndex = index
				processedRow.showModel = true
				processedRow.modelRowspan = 1
			} else {
				processedRow.showModel = false
				processedRow.modelRowspan = 0
			}

			// 处理备注跨行
			const remark = row.value_info || ''
			if (remark !== currentRemark) {
				if (remarkStartIndex < index) {
					for (let i = remarkStartIndex; i < index; i++) {
						processedRows[i].remarkRowspan = index - remarkStartIndex
					}
				}
				currentRemark = remark
				remarkStartIndex = index
				processedRow.showRemark = true
				processedRow.remarkRowspan = 1
			} else {
				processedRow.showRemark = false
				processedRow.remarkRowspan = 0
			}

			processedRows.push(processedRow)
		})

		// 处理最后一组跨行
		if (modelStartIndex < processedRows.length) {
			for (let i = modelStartIndex; i < processedRows.length; i++) {
				processedRows[i].modelRowspan = processedRows.length - modelStartIndex
			}
		}
		if (remarkStartIndex < processedRows.length) {
			for (let i = remarkStartIndex; i < processedRows.length; i++) {
				processedRows[i].remarkRowspan = processedRows.length - remarkStartIndex
			}
		}

		tables.push({
			id: tableIndex,
			configColumns,
			rows: processedRows,
			modelCount: new Set(rows.map(r => r.goods_name)).size
		})
	})

	return tables
})

// 计算跨行单元格高度
function getCellHeight(rowspan: number): string {
	const baseHeight = 80 // 基础行高 rpx
	return `${baseHeight * rowspan}rpx`
}

// 加载报价数据
async function loadPriceData() {
	if (!priceTypeId.value) {
		uni.showToast({
			title: '参数错误',
			icon: 'none'
		})
		return
	}

	loading.value = true

	try {
		const res = await getQuotationPriceList({
			quotation_id: priceTypeId.value,
			is_current: 1,
			price_date: new Date().toISOString().split('T')[0]
		})

		if (res.code === 1 && res.data) {
			tableData.value = res.data || []
			
			// 设置报价类型名称和更新时间
			if (tableData.value.length > 0) {
				priceTypeName.value = tableData.value[0].price_name || ''
				const createTime = tableData.value[0].create_at
				if (createTime) {
					updateTime.value = formatTime(createTime)
				}
			}
		} else {
			uni.showToast({
				title: res.msg || '加载失败',
				icon: 'none'
			})
		}
	} catch (error: any) {
		console.error('加载报价数据失败:', error)
		uni.showToast({
			title: error.msg || '加载失败',
			icon: 'none'
		})
	} finally {
		loading.value = false
	}
}

// 格式化时间
function formatTime(timestamp: number): string {
	const date = new Date(timestamp * 1000)
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	const hours = String(date.getHours()).padStart(2, '0')
	const minutes = String(date.getMinutes()).padStart(2, '0')
	return `${year}-${month}-${day} ${hours}:${minutes}`
}

// 页面加载
onLoad((options: any) => {
	if (options.id) {
		priceTypeId.value = options.id
		loadPriceData()
	} else {
		uni.showToast({
			title: '缺少参数',
			icon: 'none'
		})
	}
})
</script>

<style lang="scss" scoped>
.show-price-page {
	min-height: 100vh;
	background: #f5f5f5;
}

.loading-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 200rpx 0;

	.loading-text {
		margin-top: 20rpx;
		font-size: 28rpx;
		color: #999;
	}
}

.empty-container {
	padding: 200rpx 0;
}

.price-content {
	padding: 20rpx;
}

.price-header {
	background: #fff;
	border-radius: 16rpx;
	padding: 24rpx;
	margin-bottom: 20rpx;

	.header-item {
		display: flex;
		align-items: center;
		margin-bottom: 12rpx;

		&:last-child {
			margin-bottom: 0;
		}

		.label {
			font-size: 28rpx;
			color: #666;
		}

		.value {
			font-size: 28rpx;
			color: #333;
			font-weight: 500;
		}
	}
}

.tables-wrapper {
	.table-container {
		background: #fff;
		border-radius: 16rpx;
		margin-bottom: 20rpx;
		overflow: hidden;

		.table-title {
			padding: 24rpx;
			border-bottom: 1rpx solid #f0f0f0;

			.title-text {
				font-size: 32rpx;
				font-weight: 600;
				color: #333;
			}

			.title-meta {
				display: block;
				font-size: 24rpx;
				color: #999;
				margin-top: 8rpx;
			}
		}

		.config-tags {
			display: flex;
			flex-wrap: wrap;
			padding: 16rpx 24rpx;
			gap: 12rpx;

			.config-tag {
				background: #f0f2f5;
				color: #666;
				font-size: 22rpx;
				padding: 8rpx 16rpx;
				border-radius: 8rpx;
			}
		}
	}
}

.table-scroll {
	width: 100%;
}

.excel-table {
	min-width: 100%;

	.table-header,
	.table-row {
		display: flex;
		border-bottom: 1rpx solid #e8e8e8;
	}

	.table-header {
		background: #fafafa;
		position: sticky;
		top: 0;
		z-index: 10;
	}

	.header-cell,
	.body-cell {
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 16rpx 8rpx;
		font-size: 24rpx;
		box-sizing: border-box;
		border-right: 1rpx solid #e8e8e8;
		word-break: break-all;

		&:last-child {
			border-right: none;
		}
	}

	.header-cell {
		font-weight: 600;
		color: #333;
	}

	.body-cell {
		color: #666;
		min-height: 80rpx;
	}

	.col-model {
		width: 160rpx;
		flex-shrink: 0;
		background: #fafafa;
	}

	.col-capacity {
		width: 100rpx;
		flex-shrink: 0;
	}

	.col-price {
		flex: 1;
		min-width: 160rpx;
	}

	.col-remark {
		width: 240rpx;
		flex-shrink: 0;
		background: #fffbf0;
	}

	.model-merged,
	.remark-merged {
		align-items: flex-start;
		padding-top: 20rpx;
	}

	.cell-text {
		word-break: break-all;
		text-align: center;
	}

	.price-box {
		width: 100%;

		.price-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 6rpx 0;

			&.original {
				.price-label {
					color: #999;
				}

				.price-value {
					color: #999;
					text-decoration: line-through;
				}
			}

			&.final {
				.price-label {
					color: #333;
					font-weight: 500;
				}

				.price-value {
					color: #ff6b00;
					font-weight: 600;
					font-size: 26rpx;
				}
			}

			.price-label {
				font-size: 22rpx;
			}

			.price-value {
				font-size: 24rpx;
			}
		}
	}

	.empty-cell {
		color: #ddd;
		font-size: 32rpx;
	}

	.remark-text {
		font-size: 22rpx;
		color: #666;
		line-height: 1.6;
		word-break: break-all;
		white-space: pre-wrap;
		text-align: left;
	}
}

.footer-tip {
	padding: 40rpx 20rpx;
	text-align: center;

	.tip-text {
		font-size: 24rpx;
		color: #999;
	}
}
</style>
