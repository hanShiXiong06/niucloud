<template>
	<view class="sheet-list">
		<view
			v-for="table in displayTables"
			:key="table.id"
			:id="`table-${table.id}`"
			:data-series-key="table.seriesKey"
			class="sheet-section"
		>
			<view v-if="displayTables.length > 1" class="section-title">
				<text>{{ table.title }}</text>
				<text class="section-sub">{{ table.configColumns.join(' / ') }}</text>
			</view>

			<view
				v-for="(modelGroup, modelIdx) in table.modelGroups"
				:key="`${table.id}-${modelIdx}`"
				class="model-card"
			>
				<view class="model-head">
					<view class="model-name-block">
						<text class="model-brand">{{ getModelBrand(modelGroup.modelName) }}</text>
						<text class="model-name">{{ modelGroup.modelName }}</text>
					</view>
					<text class="capacity-count">{{ modelGroup.displayRows.length }} 个容量</text>
				</view>

				<view class="price-table-wrap">
					<view class="mobile-price-table">
						<view class="price-row table-head">
							<view class="price-cell capacity-cell">机身内存</view>
							<view
								v-for="column in table.priceColumns"
								:key="column.key"
								class="price-cell price-head-cell"
								:style="getPriceColumnStyle(column)"
							>
								<text>{{ column.name }}</text>
							</view>
							<view
								v-for="column in table.adjustmentColumns"
								:key="column.key"
								class="price-cell adjustment-head-cell"
								:style="getAdjustmentColumnStyle(column)"
							>
								{{ column.name }}
							</view>
						</view>

						<view class="price-body-wrap">
							<view class="price-main-columns">
								<view
									v-for="(row, rowIdx) in modelGroup.displayRows"
									:key="rowIdx"
									class="price-row"
									:style="rowHeightStyle(row)"
								>
									<view class="price-cell capacity-cell capacity-body">{{ row.capacity || '--' }}</view>
									<view
										v-for="column in table.priceColumns"
										:key="column.key"
										class="price-cell price-body-cell"
										:style="getPriceColumnStyle(column)"
										@click="emit('trend', row, column.name)"
									>
										<text
											v-if="hasPriceValue(row.prices?.[column.name])"
											class="price-value"
											:class="priceTrendClass(row, column.name)"
										>{{ formatPrice(row.prices?.[column.name]) }}<text v-if="spiderPriceTrend(row, column.name) === 'up'" class="trend-arrow">▲</text><text v-else-if="spiderPriceTrend(row, column.name) === 'down'" class="trend-arrow">▼</text></text>
										<text v-else class="empty-cell">--</text>
									</view>
								</view>
							</view>

							<view
								v-for="column in table.adjustmentColumns"
								:key="column.key"
								class="adjustment-column"
								:style="getAdjustmentColumnStyle(column)"
							>
								<view
									v-for="(row, rowIdx) in modelGroup.displayRows"
									:key="`${column.key}-${rowIdx}`"
									class="adjustment-cell-wrapper"
									:style="rowHeightStyle(row)"
								>
									<view
										v-if="getAdjustmentCell(row, column.key).show"
										class="price-cell adjustment-body-cell"
										:style="adjustmentCellStyle(row, column)"
									>
										<view v-if="getAdjustmentCell(row, column.key).parts.length" class="adjustment-item-list">
											<text
												v-for="item in getAdjustmentCell(row, column.key).parts"
												:key="item"
												class="adjustment-item-text"
											>{{ item }}</text>
										</view>
										<text v-else class="adjustment-cell-text">/</text>
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
	ADJUSTMENT_ROW_HEIGHT_RPX,
	formatPrice,
	getAdjustmentCell,
	getAdjustmentColumnStyle,
	getDisplayRows,
	getModelBrand,
	getModelGroups,
	getPriceColumnStyle,
	hasPriceValue,
	spiderPriceTrend,
	type AdjustmentColumn,
	type EnhancedPriceRow,
	type GroupedTable
} from '../utils/priceTable'

const props = defineProps<{
	tables: GroupedTable[]
}>()

const emit = defineEmits<{
	(event: 'trend', row: EnhancedPriceRow, columnName: string): void
}>()

const displayTables = computed(() => props.tables.map(table => ({
	...table,
	modelGroups: getModelGroups(table.rows).map(group => ({
		...group,
		displayRows: getDisplayRows(group.rows, table.adjustmentColumns)
	}))
})))

function rowHeightStyle(row: EnhancedPriceRow): Record<string, string> {
	const height = row.displayRowHeight || ADJUSTMENT_ROW_HEIGHT_RPX
	return { height: `${height}rpx`, minHeight: `${height}rpx` }
}

function adjustmentCellStyle(row: EnhancedPriceRow, column: AdjustmentColumn): Record<string, string> {
	const cell = getAdjustmentCell(row, column.key)
	return {
		...getAdjustmentColumnStyle(column),
		height: `${cell.height || cell.rowspan * ADJUSTMENT_ROW_HEIGHT_RPX}rpx`
	}
}

function priceTrendClass(row: EnhancedPriceRow, columnName: string): string {
	const trend = spiderPriceTrend(row, columnName)
	return trend === 'up' ? 'price-up' : trend === 'down' ? 'price-down' : ''
}
</script>

<style lang="scss" scoped>
.sheet-list,
.sheet-section {
	display: flex;
	flex-direction: column;
	gap: 10rpx;
}

.section-title {
	margin: 6rpx 0 2rpx;
	padding: 18rpx 20rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 18rpx;
	border-radius: 12rpx;
	background: var(--bg-soft);
	border: 1rpx solid var(--line);
	font-size: 26rpx;
	line-height: 36rpx;
	font-weight: 700;
	color: var(--text-main);
}

.section-sub {
	flex-shrink: 0;
	max-width: 430rpx;
	font-size: 22rpx;
	font-weight: 400;
	color: var(--text-sub);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.model-card {
	background: var(--bg-card);
	border: none;
	border-radius: 0;
	overflow: hidden;
	box-shadow: none;
}

.model-card + .model-card {
	border-top: 8rpx solid rgba(15, 23, 42, 0.18);
}

.model-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 14rpx 10rpx 12rpx;
	background: var(--model-head-bg);
	border-bottom: 1rpx solid var(--line);
}

.model-name-block {
	min-width: 0;
	display: flex;
	align-items: center;
	gap: 10rpx;
}

.model-brand {
	flex-shrink: 0;
	height: 32rpx;
	line-height: 32rpx;
	padding: 0 9rpx;
	border-radius: 4rpx;
	background: var(--model-brand-bg);
	color: var(--model-brand-text);
	font-size: 19rpx;
	font-weight: 700;
}

.model-name {
	font-size: 27rpx;
	line-height: 36rpx;
	font-weight: 800;
	color: var(--text-main);
	text-align: left;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.capacity-count {
	flex-shrink: 0;
	font-size: 20rpx;
	line-height: 28rpx;
	color: var(--text-sub);
	margin-left: 16rpx;
}

.price-table-wrap,
.mobile-price-table {
	width: 100%;
}

.price-table-wrap {
	overflow: hidden;
}

.price-row {
	display: flex;
	height: 78rpx;
	min-height: 78rpx;
	border-bottom: 1rpx solid var(--line);
}

.price-row:last-child {
	border-bottom: none;
}

.table-head {
	height: 90rpx;
	min-height: 90rpx;
	background: var(--button-bg);
	color: var(--button-text);
}

.price-cell {
	box-sizing: border-box;
	min-width: 0;
	padding: 4rpx 2rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	border-right: 1rpx solid var(--line);
	text-align: center;
	word-break: break-word;
}

.price-cell:last-child {
	border-right: none;
}

.capacity-cell {
	flex: 0 0 82rpx;
	width: 82rpx;
	min-width: 82rpx;
	max-width: 82rpx;
	font-size: 18rpx;
	font-weight: 400;
	background: inherit;
}

.capacity-body {
	background: var(--bg-soft);
	color: var(--text-main);
	font-size: 22rpx;
	line-height: 28rpx;
	white-space: normal;
	overflow-wrap: anywhere;
}

.price-head-cell {
	flex: 1 1 0;
	font-size: 17rpx;
	line-height: 22rpx;
	font-weight: 700;
	color: var(--button-text);
	overflow: hidden;
}

.price-head-cell text {
	display: -webkit-box;
	-webkit-line-clamp: 4;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.adjustment-head-cell {
	flex-shrink: 0;
	width: 228rpx;
	min-width: 228rpx;
	max-width: 228rpx;
	font-size: 18rpx;
	line-height: 24rpx;
	font-weight: 800;
	color: var(--button-text);
	background: var(--brand-deep);
}

.price-body-wrap {
	display: flex;
	align-items: stretch;
}

.price-main-columns {
	flex: 1;
	min-width: 0;
}

.price-body-cell {
	flex: 1 1 0;
	background: var(--bg-soft);
	overflow: hidden;
}

.price-row:nth-child(2n + 1) .price-body-cell {
	background: var(--bg-card);
}

.price-value {
	font-size: 22rpx;
	line-height: 28rpx;
	font-weight: 800;
	color: var(--price);
	white-space: nowrap;
}

.price-value.price-up {
	color: #e04b4b;
}

.price-value.price-down {
	color: #1faa6b;
}

.trend-arrow {
	font-size: 18rpx;
	margin-left: 2rpx;
}

.empty-cell {
	font-size: 22rpx;
	color: var(--text-sub);
}

.adjustment-column {
	position: relative;
	flex-shrink: 0;
	width: 228rpx;
	min-width: 228rpx;
	max-width: 228rpx;
	background: var(--bg-card);
}

.adjustment-cell-wrapper {
	position: relative;
	height: 68rpx;
	min-height: 68rpx;
	border-bottom: 1rpx solid var(--line);
	box-sizing: border-box;
}

.adjustment-cell-wrapper:last-child {
	border-bottom: none;
}

.adjustment-body-cell {
	position: absolute;
	left: 0;
	right: 0;
	top: 0;
	z-index: 2;
	width: 100%;
	min-width: 228rpx;
	max-width: 228rpx;
	padding: 3rpx 5rpx;
	background: var(--bg-card);
	border-right: none;
	border-bottom: 1rpx solid var(--line);
	align-items: center;
	justify-content: center;
}

.adjustment-item-list {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: stretch;
	justify-content: center;
	gap: 2rpx;
}

.adjustment-item-text,
.adjustment-cell-text {
	display: block;
	width: 100%;
	font-size: 15rpx;
	line-height: 19rpx;
	font-weight: 600;
	color: var(--text-main);
	text-align: center;
	white-space: pre-wrap;
	word-break: break-word;
}
</style>
