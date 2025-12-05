<template>
	<view class="show-price-page">
		<!-- 顶部导航栏 -->
		<view class="custom-navbar">
			<view class="navbar-content">
				<view class="navbar-left" @click="goBack">
					<text class="iconfont icon-left"></text>
				</view>
				<view class="navbar-title">{{ pageTitle }}</view>
				<view class="navbar-right"></view>
			</view>
		</view>

		<!-- 加载状态 -->
		<view v-if="loading" class="loading-container">
			<view class="loading-spinner"></view>
			<text class="loading-text">加载中...</text>
		</view>

		<!-- 空状态 -->
		<view v-else-if="!loading && groupedTables.length === 0" class="empty-container">
			<view class="empty-icon">📋</view>
			<text class="empty-text">暂无报价数据</text>
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
					<text class="value">{{ createAt }}</text>
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
					<!-- <view class="table-title">
						<text class="title-text">配置组 {{ tableIdx + 1 }}</text>
						<text class="title-meta">{{ table.modelCount }}个型号 · {{ table.configColumns.length }}项配置</text>
					</view> -->

					<!-- 配置标签 -->
					<!-- <view class="config-tags">
						<view
							v-for="config in table.configColumns"
							:key="config"
							class="config-tag"
						>
							{{ config }}
						</view>
					</view> -->

					<!-- Excel样式表格 -->
					<scroll-view scroll-x="false" class="table-scroll">
						<view class="excel-table">
							<!-- 表头 -->
							<view class="table-header">
								<view class="header-cell col-model">型号</view>
								<view class="header-cell col-capacity">容量</view>
                                <!-- 根据表头文字的长度，设置宽度 -->
								<view
									v-for="config in table.configColumns"
									:key="config"
									class="header-cell col-price"
                                    
                                    :style="{ width: `${config.length * 20}rpx` }"
								>
									{{ config }}
								</view>
								<view class="header-cell col-remark">备注</view>
							</view>

							<!-- 表体 -->
							<view class="table-body">
								<!-- 型号分组 -->
							<view
								v-for="(modelGroup, modelIdx) in getModelGroups(table.rows)"
								:key="modelIdx"
								class="model-group"
							>
								<view class="model-group-row">
									<!-- 型号列（跨行） -->
									<view class="body-cell col-model model-merged">
										<text class="cell-text">{{ modelGroup.modelName }}</text>
									</view>

									<!-- 数据区域（容量 + 价格 + 备注） -->
									<view class="data-area">
										<!-- 容量和价格列区域 -->
										<view class="data-columns">
											<view
												v-for="(row, rowIdx) in modelGroup.rows"
												:key="rowIdx"
												class="data-row"
											>
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
														<view class="price-item final">
															<text class="price-value">{{ row.prices[config].final || '-' }}</text>
														</view>
													</view>
													<text v-else class="empty-cell">-</text>
												</view>
											</view>
										</view>

										<!-- 备注列区域（独立，使用绝对定位实现跨行） -->
										<view class="remark-column">
											<view
												v-for="(row, rowIdx) in modelGroup.rows"
												:key="rowIdx"
												class="remark-cell-wrapper"
											>
												<view
													v-if="row.showRemark"
													class="body-cell col-remark remark-merged"
													:style="{ height: `${row.remarkRowspan * 80}rpx` }"
												>
													<text class="remark-text">{{ row.displayRemark || '-' }}</text>
												</view>
											</view>
										</view>
									</view>
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

// 按型号分组数据（用于实现跨行显示）
function getModelGroups(rows: any[]) {
	const groups: any[] = []
	let currentModelName = ''
	let currentGroup: any = null

	rows.forEach(row => {
		if (row.goods_name !== currentModelName) {
			// 新的型号，创建新组
			if (currentGroup) {
				// 处理上一组的备注跨行
				processRemarkRowspan(currentGroup.rows)
				groups.push(currentGroup)
			}
			currentModelName = row.goods_name
			currentGroup = {
				modelName: row.goods_name,
				rows: [row]
			}
		} else {
			// 同一型号，添加到当前组
			currentGroup.rows.push(row)
		}
	})

	// 添加最后一组
	if (currentGroup) {
		processRemarkRowspan(currentGroup.rows)
		groups.push(currentGroup)
	}

	return groups
}

// 处理备注列的跨行逻辑
function processRemarkRowspan(rows: any[]) {
	let currentRemark = ''
	let remarkStartIndex = 0

	rows.forEach((row, index) => {
		// 标准化备注：空值统一处理为空字符串（显示时会变成 '-'）
		const remark = row.value_info?.trim() || ''
		
		if (remark !== currentRemark) {
			// 备注内容变化，更新之前的跨行数
			if (remarkStartIndex < index) {
				for (let i = remarkStartIndex; i < index; i++) {
					rows[i].remarkRowspan = index - remarkStartIndex
				}
			}
			
			// 开始新的备注组
			currentRemark = remark
			remarkStartIndex = index
			row.showRemark = true
			row.remarkRowspan = 1
			// 保存处理后的备注值（用于显示）
			row.displayRemark = remark || '-'
		} else {
			// 相同备注，不显示
			row.showRemark = false
			row.remarkRowspan = 0
		}
	})

	// 处理最后一组备注
	if (remarkStartIndex < rows.length) {
		for (let i = remarkStartIndex; i < rows.length; i++) {
			rows[i].remarkRowspan = rows.length - remarkStartIndex
		}
	}
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
		const res: any = await getQuotationPriceList({
			quotation_id: priceTypeId.value,
			is_current: 1,
			// price_date: new Date().toISOString().split('T')[0]
		})

		if (res.code === 1 && res.data) {
			tableData.value = res.data || []
			
			// 设置报价类型名称和更新时间
			if (tableData.value.length > 0) {
				priceTypeName.value = tableData.value[0].price_name || ''

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

// create_at 获取第一条的 create_at
const createAt = computed(() => {
	return tableData.value[0].create_at
})

// 返回上一页
function goBack() {
	uni.navigateBack()
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
    line-height: 1.4;
}

// 自定义导航栏
.custom-navbar {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	z-index: 999;
	background: #fff;
	border-bottom: 1rpx solid #f0f0f0;

	.navbar-content {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 88rpx;
		padding: 0 32rpx;
		padding-top: env(safe-area-inset-top);

		.navbar-left {
			width: 80rpx;
			display: flex;
			align-items: center;

			.iconfont {
				font-size: 40rpx;
				color: #333;
			}
		}

		.navbar-title {
			flex: 1;
			text-align: center;
			font-size: 32rpx;
			font-weight: 600;
			color: #333;
		}

		.navbar-right {
			width: 80rpx;
		}
	}
}

.loading-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 300rpx 0;

	.loading-spinner {
		width: 80rpx;
		height: 80rpx;
		border: 6rpx solid #f3f3f3;
		border-top-color: #409eff;
		border-radius: 50%;
		animation: spin 1s linear infinite;
	}

	.loading-text {
		margin-top: 24rpx;
		font-size: 28rpx;
		color: #999;
	}
}

@keyframes spin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}

.empty-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 300rpx 0;

	.empty-icon {
		font-size: 120rpx;
		margin-bottom: 24rpx;
	}

	.empty-text {
		font-size: 28rpx;
		color: #999;
	}
}

.price-content {
	// padding: 20rpx;
	padding-top: calc(88rpx + env(safe-area-inset-top) + 20rpx);
}

.loading-container,
.empty-container {
	padding-top: calc(88rpx + env(safe-area-inset-top) + 100rpx);
}

.price-header {
	background: #fff;
	border-radius: 16rpx;
	padding: 24rpx;
	margin-bottom: 20rpx;
    width: 100%;
    position: sticky;
    top: calc(88rpx + env(safe-area-inset-top));
    z-index:99;

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
		// border-radius: 16rpx;
		// margin-bottom: 20rpx;
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

	.table-header {
		display: flex;
		background: #fafafa;
		border-bottom: 1rpx solid #e8e8e8;
		position: sticky;
		top: 0;
		z-index: 10;
	}

	.table-body {
		.model-group {
			border-bottom: 1rpx solid #e8e8e8;

			.model-group-row {
				display: flex;
			}

			.data-area {
				flex: 1;
				display: flex;
				position: relative;
			}

			.data-columns {
				flex: 1;
				display: flex;
				flex-direction: column;
			}

			.data-row {
				display: flex;
				border-bottom: 1rpx solid #f0f0f0;
				min-height: 80rpx;

				&:last-child {
					border-bottom: none;
				}
			}

			.remark-column {
				width: 200rpx;
				flex-shrink: 0;
				display: flex;
				flex-direction: column;
				position: relative;
                border-left: 1px solid #f1f2f5;
                border-bottom: 1px solid #f1f2f5;
			}

			.remark-cell-wrapper {
				min-height: 80rpx;
				position: relative;
			}
		}
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
        font-size: 24rpx;
		color: #333;

	}

	.body-cell {
		color: #666;
		min-height: 80rpx;
	}

	.col-model {
		width: 150rpx;
		flex-shrink: 0;
        padding: 10rpx;
		background: #fafafa;
	}

	.col-capacity {
		width: 100rpx;
		flex-shrink: 0;
	}

	.col-price {
		flex: 1;
		min-width: 127rpx;
	}

	.col-remark {
		width: 200rpx;
		flex-shrink: 0;
		background: #fffbf0;
	}

	.model-merged {
		align-items: center;
		padding-top: 20rpx;
	}

	.remark-merged {
		display: flex;
		align-items: center;
		justify-content: center;
		border-bottom: 1rpx solid #f0f0f0;
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		width: 100%;
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
			justify-content: center;
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
		line-height: 1;
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
