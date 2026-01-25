<template>
	<view class="goods-filter-wrapper">
		<!-- 筛选标签栏 -->
		<scroll-view scroll-x class="filter-scroll" show-scrollbar="false">
			<view class="filter-scroll-content">
				<!-- 分类筛选 -->
				<view v-if="showCategory" class="filter-tag"
					:class="{ 'active': activeFilters.category || selectedCategoryNames.length }"
					@click="showPopup('category')">
					<text>{{ categoryText }}</text>
					<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
				</view>

				<!-- 品牌/成色筛选 -->
				<view v-if="showBrand" class="filter-tag" :class="{ 'active': activeFilters.brand }"
					@click="showPopup('brand')">
					<text>{{ brandText }}</text>
					<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
				</view>

				<!-- 价格筛选 -->
				<view v-if="showPrice" class="filter-tag" :class="{ 'active': activeFilters.price }"
					@click="showPopup('price')">
					<text>价格</text>
					<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
				</view>

				<!-- 内存筛选 -->
				<view v-if="showMemory && selectedCategories.length === 1 && memoryList.length" class="filter-tag"
					:class="{ 'active': activeFilters.memory }" @click="showPopup('memory')">
					<text>内存</text>
					<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
				</view>

				<!-- 排序方式 -->
				<view v-if="showSort" class="filter-tag" :class="{ 'active': activeFilters.sort }"
					@click="showPopup('sort')">
					<text>{{ sortText }}</text>
					<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
				</view>

				<!-- 重置按钮 -->
				<view v-if="hasActiveFilters" class="filter-tag reset" @click="resetFilters">
					<text class="nc-iconfont nc-icon-zhongzhiV6xx text-[24rpx] mr-[4rpx]"></text>
					<text>重置</text>
				</view>
			</view>
		</scroll-view>

		<!-- 分类弹窗 -->
		<u-popup :show="popups.category" mode="bottom" @close="closePopup('category')" :round="10">
			<view class="popup-content category-popup">
				<view class='flex h-full'>
					<!-- 一级分类 -->
					<scroll-view scroll-y class="category-left">
						<!-- 分类列表 -->
						<view class="category-item" v-for="item in categoryList" :key="item.category_id"
							:class="{ 'active': currentCategory.category_id === item.category_id || selectedCategories.includes(item.category_id) }"
							@click="selectParentCategory(item)">
							<view class="flex flex-row items-center gap-[12rpx]">
							<image v-if="item.image" :src="item.image" class="w-[32rpx] h-[32rpx] rounded-[6rpx] flex-shrink-0" mode="aspectFill" />
							<view v-else class="w-[32rpx] h-[32rpx] rounded-[6rpx] bg-[#e8e8e8] flex items-center justify-center flex-shrink-0">
								<text class="nc-iconfont nc-icon-fenleiV6mm text-[16rpx] text-[#999]"></text>
							</view>
							<text class="category-name">{{ item.category_name }}</text>
						</view>
						</view>
					</scroll-view>

					<!-- 右侧内容区 -->
					<scroll-view scroll-y class="category-right">
						<view class="sub-category-list grid  grid-cols-2 gap-2">
							<!-- 当选中一级分类且有子分类时显示 -->
							<template v-if="currentCategory.category_id && currentCategory.child_list?.length">
								<!-- 子分类列表 -->
								<view class="sub-category-item " v-for="item in currentCategory.child_list"
									:key="item.category_id"
									:class="{ 'active': selectedCategories.includes(item.category_id) }"
									@click="selectSubCategory(item)">
									<image v-if="item.image" :src="item.image" class="w-[48rpx] h-[48rpx] rounded-[6rpx] flex-shrink-0" mode="aspectFill" />
									<view v-else class="w-[48rpx] h-[48rpx] rounded-[6rpx] bg-[#e8e8e8] flex items-center justify-center flex-shrink-0">
										<text class="nc-iconfont nc-icon-fenleiV6mm text-[16rpx] text-[#999]"></text>
									</view>
									<text class="truncate text-[24rpx] ml-1">{{ item.category_name }}</text>
									<text class="nc-iconfont nc-icon-duigouV6xx check-icon"
										v-if="selectedCategories.includes(item.category_id)"></text>
								</view>
							</template>

							<!-- 当选中一级分类但没有子分类时 -->
							<template v-else-if="currentCategory.category_id && !currentCategory.child_list?.length">
								<view class="empty-tip">
									<text>该分类暂无子分类</text>
								</view>
							</template>

							<!-- 默认显示全部 -->
							<template v-else>
								<view class="empty-tip">
									<text>请选择分类</text>
								</view>
							</template>
						</view>
					</scroll-view>
				</view>
				<!-- 底部按钮 -->
				<view class="category-footer">
					<view class="btn-group">
						<u-button type="info" :plain="true" text="重置" @click="resetCategories"></u-button>
						<u-button type="primary" text="确定" @click="confirmCategories"></u-button>
					</view>
				</view>
			</view>
		</u-popup>

		<!-- 品牌/成色弹窗 -->
		<u-popup :show="popups.brand" mode="bottom" @close="closePopup('brand')" :round="10">
			<view class="popup-content">
				<view class="brand-list">
					<view class="brand-item" v-for="(item, index) in brandList" :key="index"
						:class="{ 'active': filters.brand_id === item.value }" @click="selectBrand(item)">
						<text>{{ item.label }}</text>
					</view>
					<!-- 清除 -->
					<view class="brand-item" @click="resetBrand">
						<text>清除</text>
					</view>
				</view>
			</view>
		</u-popup>

		<!-- 价格弹窗 -->
		<u-popup :show="popups.price" mode="bottom" @close="closePopup('price')" :round="10">
			<view class="popup-content">
				<view class="price-range">
					<view class="input-group">
						<u-input v-model="filters.price_min" type="number" placeholder="最低价" :border="true"></u-input>
						<text class="separator">-</text>
						<u-input v-model="filters.price_max" type="number" placeholder="最高价" :border="true"></u-input>
					</view>
					<view class="price-tags">
						<view class="price-tag" v-for="(item, index) in priceRanges" :key="index"
							:class="{ 'active': filters.price_range === item.value }" @click="selectPriceRange(item)">
							{{ item.label }}
						</view>
					</view>
					<view class="btn-group">
						<u-button type="info" :plain="true" text="重置" @click="resetPrice"></u-button>
						<u-button type="primary" text="确定" @click="confirmPrice"></u-button>
					</view>
				</view>
			</view>
		</u-popup>

		<!-- 排序弹窗 -->
		<u-popup :show="popups.sort" mode="bottom" @close="closePopup('sort')" :round="10">
			<view class="popup-content">
				<view class="sort-list">
					<view class="sort-item" v-for="(item, index) in sortOptions" :key="index"
						:class="{ 'active': filters.sort === item.value }" @click="selectSort(item)">
						<text>{{ item.label }}</text>
						<text class="nc-iconfont nc-icon-duigouV6xx" v-if="filters.sort === item.value"></text>
					</view>
				</view>
			</view>
		</u-popup>

		<!-- 内存弹窗 -->
		<u-popup :show="popups.memory && selectedCategories.length === 1" mode="top" @close="closePopup('memory')"
			:round="10">
			<view class="popup-content">
				<view class="memory-list">
					<view class="memory-item" v-for="(item, index) in memoryList" :key="item.spec_id"
						:class="{ 'active': filters.memory_id === item.spec_id }" @click="selectMemory(item)">
						<text>{{ item.spec_name }}</text>
					</view>
				</view>
			</view>
		</u-popup>
	</view>
</template>

<script setup lang="ts">
import { reactive, ref, computed, watch } from 'vue'
import { getGoodsCategoryTree, getBrandList, getMemoryList } from '@/addon/phone_shop/api/goods'

// Props
interface Props {
	showCategory?: boolean
	showBrand?: boolean
	showPrice?: boolean
	showMemory?: boolean
	showSort?: boolean
	brandLabel?: string
	modelValue?: any
}

// 允许通过 initialFilters 传入初始筛选
interface Props {
	showCategory?: boolean
	showBrand?: boolean
	showPrice?: boolean
	showMemory?: boolean
	showSort?: boolean
	brandLabel?: string
	modelValue?: any
	initialFilters?: any
}

const props = withDefaults(defineProps<Props>(), {
	showCategory: true,
	showBrand: true,
	showPrice: true,
	showMemory: true,
	showSort: true,
	brandLabel: '成色'
})

// Emits
const emit = defineEmits(['update:modelValue', 'change', 'search'])

// 筛选数据
const filters = reactive({
	category_id: '',
	brand_id: '',
	price_min: '',
	price_max: '',
	price_range: '',
	sort: 'default',
	memory_id: ''
})

// 弹窗控制
const popups = reactive({
	category: false,
	brand: false,
	price: false,
	sort: false,
	memory: false
})

// 分类数据
const categoryList = ref<Array<any>>([])
const currentCategory = ref<any>({})
const selectedCategories = ref<number[]>([])
const selectedCategoryNames = ref<string[]>([])

// 品牌数据
const brandList = ref<Array<any>>([])

// 内存数据
const memoryList = ref<Array<any>>([])

// 价格区间
const priceRanges = [
	{ label: '1000以下', value: '0-1000' },
	{ label: '1000-3000', value: '1000-3000' },
	{ label: '3000-5000', value: '3000-5000' },
	{ label: '5000-8000', value: '5000-8000' },
	{ label: '8000以上', value: '8000-999999' }
]

// 排序选项
const sortOptions = [
	{ label: '默认排序', value: 'default' },
	{ label: '价格从低到高', value: 'price_asc' },
	{ label: '价格从高到低', value: 'price_desc' },
	{ label: '销量从高到低', value: 'sale_num_desc' },
	{ label: '最新上架', value: 'create_time_desc' }
]

// 计算属性
const activeFilters = computed(() => ({
	category: !!filters.category_id,
	brand: !!filters.brand_id,
	price: !!(filters.price_min || filters.price_max || filters.price_range),
	sort: filters.sort !== 'default',
	memory: !!filters.memory_id
}))

const hasActiveFilters = computed(() =>
	Object.values(activeFilters.value).some(v => v)
)

const sortText = computed(() => {
	const option = sortOptions.find(item => item.value === filters.sort)
	return option ? option.label : '排序'
})

const brandText = computed(() => {
	if (!filters.brand_id) return props.brandLabel
	const brand = brandList.value.find(item => item.value === filters.brand_id)
	return brand ? brand.label : props.brandLabel
})

// 分类显示文本
const categoryText = computed(() => {
	if (selectedCategoryNames.value.length === 0) {
		return '分类'
	} else if (selectedCategoryNames.value.length === 1) {
		return selectedCategoryNames.value[0]
	} else {
		return `已选${selectedCategoryNames.value.length}个分类`
	}
})

// 初始化方法
const init = async () => {
	await loadCategories()
	await loadBrandList()
}

// 加载分类
const loadCategories = async () => {
	try {
		const res: any = await getGoodsCategoryTree()
		categoryList.value = res.data
		// 如果父组件传入了初始筛选，立即应用
		if (props.initialFilters && props.initialFilters.category_id) {
			// 延迟一下确保数据完全加载
			setTimeout(() => {
				applyInitialFilters(props.initialFilters)
			}, 50)
		}
	} catch (error: any) {
		console.error('加载分类失败:', error)
	}
}

// 加���品牌列表
const loadBrandList = async () => {
	try {
		const res: any = await getBrandList()
		if (res.code === 1) {
			brandList.value = res.data.map((item: any) => ({
				label: item.brand_name,
				value: item.brand_id
			}))
		}
	} catch (error: any) {
		console.error('加载品牌失败:', error)
	}
}

// 加载内存列表
const loadMemoryList = async (categoryId: number) => {
	try {
		const res: any = await getMemoryList({ id: categoryId })
		memoryList.value = res.data
	} catch (error: any) {
		console.error('加载内存列表失败:', error)
		memoryList.value = []
	}
}

// 显示弹窗
const showPopup = (type: string) => {
	Object.keys(popups).forEach(key => {
		(popups as any)[key] = key === type
	})
}

// 关闭弹窗
const closePopup = (type: string) => {
	(popups as any)[type] = false
}

// 选择一级分类
const selectParentCategory = (item: any) => {
	// 如果切换到不同的一级分类，清除之前的所有选择
	if (currentCategory.value.category_id && currentCategory.value.category_id !== item.category_id) {
		selectedCategories.value = []
		selectedCategoryNames.value = []
		filters.memory_id = ''
	}

	// 设置当前分类（显示子分类）
	currentCategory.value = item

	// 如果该分类没有子分类，直接切换选中状态
	if (!item.child_list?.length) {
		const index = selectedCategories.value.indexOf(item.category_id)
		if (index > -1) {
			// 取消选中
			selectedCategories.value.splice(index, 1)
			selectedCategoryNames.value.splice(index, 1)
		} else {
			// 选中该一级分类
			selectedCategories.value = [item.category_id]
			selectedCategoryNames.value = [item.category_name]
		}
	} else {
		// 有子分类时，切换一级分类的选中状态
		const index = selectedCategories.value.indexOf(item.category_id)
		if (index > -1) {
			// 取消选中一级分类
			selectedCategories.value.splice(index, 1)
			selectedCategoryNames.value.splice(index, 1)
		} else {
			// 检查是否有子分类被选中
			const hasChildSelected = item.child_list?.some((child: any) =>
				selectedCategories.value.includes(child.category_id)
			)

			if (!hasChildSelected) {
				// 没有子分类被选中，则选中一级分类
				selectedCategories.value = [item.category_id]
				selectedCategoryNames.value = [item.category_name]
			}
		}
	}
}

// 选择二级分类
const selectSubCategory = (item: any) => {
	// 如果选择了子分类，取消父分类的选择
	const parentIndex = selectedCategories.value.indexOf(currentCategory.value.category_id)
	if (parentIndex > -1) {
		selectedCategories.value.splice(parentIndex, 1)
		selectedCategoryNames.value.splice(parentIndex, 1)
	}

	// 切换子分类的选中状态（支持多选）
	const index = selectedCategories.value.indexOf(item.category_id)
	if (index > -1) {
		// 取消选中
		selectedCategories.value.splice(index, 1)
		selectedCategoryNames.value.splice(index, 1)
	} else {
		// 选中该子分类（添加到数组，支持多选）
		selectedCategories.value.push(item.category_id)
		selectedCategoryNames.value.push(item.category_full_name || item.category_name)
	}

	filters.memory_id = ''
}

// 重置分类
const resetCategories = () => {
	selectedCategories.value = []
	selectedCategoryNames.value = []
	currentCategory.value = {}
	filters.memory_id = ''
}

// 确认分类选择
const confirmCategories = () => {
	// 只有选择了一个分类时才能筛选内存
	if (selectedCategories.value.length === 1) {
		loadMemoryList(selectedCategories.value[0])
	} else if (selectedCategories.value.length > 1) {
		// 选择了多个分类，清空内存筛选
		memoryList.value = []
		filters.memory_id = ''
	}

	filters.category_id = selectedCategories.value.join(',')
	closePopup('category')
	emitChange()
}

// 选择品牌
const selectBrand = (item: any) => {
	filters.brand_id = filters.brand_id === item.value ? '' : item.value
	closePopup('brand')
	emitChange()
}

// 重置品牌
const resetBrand = () => {
	filters.brand_id = ''
	closePopup('brand')
	emitChange()
}

// 选择价格区间
const selectPriceRange = (item: any) => {
	if (filters.price_range === item.value) {
		filters.price_range = ''
		filters.price_min = ''
		filters.price_max = ''
	} else {
		filters.price_range = item.value
		const [min, max] = item.value.split('-')
		filters.price_min = min
		filters.price_max = max
	}
}

// 确认价格筛选
const confirmPrice = () => {
	closePopup('price')
	emitChange()
}

// 重置价格
const resetPrice = () => {
	filters.price_min = ''
	filters.price_max = ''
	filters.price_range = ''
}

// 选择排序
const selectSort = (item: any) => {
	filters.sort = item.value
	closePopup('sort')
	emitChange()
}

// 选择内存
const selectMemory = (item: any) => {
	filters.memory_id = filters.memory_id === item.spec_id ? '' : item.spec_id
	closePopup('memory')
	emitChange()
}

// 重置所有筛选
const resetFilters = () => {
	Object.keys(filters).forEach(key => {
		(filters as any)[key] = key === 'sort' ? 'default' : ''
	})
	selectedCategories.value = []
	selectedCategoryNames.value = []
	currentCategory.value = {}
	memoryList.value = []
	emitChange()
}

// 触发变更事件
const emitChange = () => {
	const filterData = {
		...filters,
		selectedCategories: selectedCategories.value,
		selectedCategoryNames: selectedCategoryNames.value
	}
	emit('update:modelValue', filterData)
	emit('change', filterData)
	emit('search')
}

// 将外部初始筛选应用到组件内部（主要处理 category_id）
const applyInitialFilters = (initial: any) => {
	if (!initial || !initial.category_id || !categoryList.value.length) return
	const ids = ('' + initial.category_id).split(',').map((v: string) => Number(v)).filter(Boolean)
	if (!ids.length) return

	// 如果已经选中了相同的分类,不需要重复应用
	if (filters.category_id === ids.join(',')) return

	selectedCategories.value = []
	selectedCategoryNames.value = []
	ids.forEach((id: number) => {
		for (const cat of categoryList.value) {
			if (cat.category_id === id) {
				// 选中一级分类
				selectedCategories.value.push(cat.category_id)
				selectedCategoryNames.value.push(cat.category_name)
				currentCategory.value = cat
				break
			} else if (cat.child_list && cat.child_list.some((c: any) => c.category_id === id)) {
				const child = cat.child_list.find((c: any) => c.category_id === id)
				selectedCategories.value.push(child.category_id)
				selectedCategoryNames.value.push(child.category_full_name || child.category_name)
				currentCategory.value = cat
				break
			}
		}
	})
	filters.category_id = selectedCategories.value.join(',')

	// 只有选中了一个分类时才加载内存列表
	if (selectedCategories.value.length === 1) {
		loadMemoryList(selectedCategories.value[0])
	}
}

// 监听外部 initialFilters 的变化（如果在父组件 later 更新）
watch(() => props.initialFilters, (v) => {
	if (v && v.category_id && categoryList.value.length) {
		applyInitialFilters(v)
	}
}, { immediate: false, deep: true })

// 获取筛选数据
const getFilters = () => {
	return {
		...filters,
		selectedCategories: selectedCategories.value,
		selectedCategoryNames: selectedCategoryNames.value
	}
}

// 暴露方法
defineExpose({
	init,
	getFilters,
	resetFilters,
	// 允许父组件主动设置初始筛选
	setFilters: applyInitialFilters
})

// 初始化
init()
</script>

<style lang="scss" scoped>
.goods-filter-wrapper {
	background: #fff;
}

.filter-scroll {
	width: 100%;
	white-space: nowrap;
	padding: 16rpx 0;

	&::-webkit-scrollbar {
		display: none;
	}

	.filter-scroll-content {
		display: inline-flex;
		padding: 0 30rpx;
		gap: 16rpx;
	}
}

.filter-tag {
	display: inline-flex;
	align-items: center;
	height: 56rpx;
	padding: 0 24rpx;
	font-size: 26rpx;
	color: #666;
	background: #f5f5f5;
	border-radius: 28rpx;
	transition: all 0.3s ease;
	flex-shrink: 0;
	border: 1rpx solid transparent;

	&.active {
		color: var(--primary-color);
		background: #e6f7ff;
		border-color: #91d5ff;
	}

	&.reset {
		color: #999;
		background: #fafafa;
	}
}

.popup-content {
	background: #fff;
	padding: 30rpx;
	max-height: 60vh;
}

.category-popup {
	display: flex;
	flex-direction: column;
	height: 70vh;
	padding: 0 0  50px 0 ;
	background: #fff;

	.category-left {
		width: 200rpx;
		height: calc(100% - 20rpx);
		background: #f5f5f5;

		.category-item {
			padding: 28rpx 20rpx;
			border-bottom: 1rpx solid #e8e8e8;
			position: relative;
			transition: all 0.3s ease;
			text-align: center;

			&.active {
				background: #fff;

				&::before {
					content: '';
					position: absolute;
					left: 0;
					top: 50%;
					transform: translateY(-50%);
					width: 4rpx;
					height: 32rpx;
					background: var(--primary-color);
					border-radius: 0 4rpx 4rpx 0;
				}

				.category-name {
					color: var(--primary-color);
					font-weight: 500;
				}
			}

			.category-name {
				font-size: 28rpx;
				color: #333;
				line-height: 1.4;
			}
		}
	}

	.category-right {
		flex: 1;
		height: calc(100% - 50rpx);
		background: #fff;
		padding: 20rpx;

		.sub-category-list {

			.empty-tip {
				padding: 60rpx 20rpx;
				text-align: center;
				font-size: 28rpx;
				color: #999;
			}
		}

		.sub-category-item {
			display: flex;
			align-items: center;
			flex-direction: row;
			// justify-content: space-between;
			padding: 16rpx ;

			border-radius: 12rpx;
			background: #f5f5f5;
			transition: all 0.3s ease;
			font-size: 28rpx;
			color: #333;

			&.active {
				color: var(--primary-color);
				background: #e6f7ff;
				border: 1rpx solid #91d5ff;
			}

			.check-icon {
				font-size: 32rpx;
				color: var(--primary-color);
			}
		}
	}

	.category-footer {
		height: 120rpx;
		padding: 20rpx;
		border-top: 1rpx solid #e8e8e8;
		background: #fff;

		.btn-group {
			display: flex;
			justify-content: space-between;
			gap: 20rpx;

			:deep(.u-button) {
				flex: 1;
			}
		}
	}
}

.brand-list,
.memory-list {
	display: flex;
	flex-wrap: wrap;
	gap: 8rpx;
	padding: 10rpx;
}

.brand-item,
.memory-item {
	width: calc(33.33% - 12rpx);
	height: 70rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 26rpx;
	color: #666;
	background: #f5f5f5;
	border-radius: 12rpx;
	transition: all 0.3s ease;
	border: 1rpx solid transparent;

	&.active {
		color: var(--primary-color);
		background: #e6f7ff;
		border-color: #91d5ff;
	}
}

.price-range {
	.input-group {
		display: flex;
		align-items: center;
		margin-bottom: 30rpx;

		.separator {
			margin: 0 20rpx;
			color: #999;
		}
	}

	.price-tags {
		display: flex;
		flex-wrap: wrap;
		gap: 12rpx;
		margin-bottom: 30rpx;
	}

	.price-tag {
		width: calc(33.33% - 12rpx);
		height: 70rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 26rpx;
		color: #666;
		background: #f5f5f5;
		border-radius: 12rpx;
		transition: all 0.3s ease;
		border: 1rpx solid transparent;

		&.active {
			color: var(--primary-color);
			background: #e6f7ff;
			border-color: #91d5ff;
		}
	}

	.btn-group {
		display: flex;
		justify-content: space-between;
		gap: 20rpx;

		:deep(.u-button) {
			flex: 1;
		}
	}
}

.sort-list {
	.sort-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 24rpx 20rpx;
		font-size: 28rpx;
		color: #333;
		border-bottom: 1rpx solid #f0f0f0;
		transition: all 0.3s ease;

		&:last-child {
			border-bottom: none;
		}

		&.active {
			color: var(--primary-color);
			background: #e6f7ff;
			border-radius: 8rpx;
		}

		.nc-iconfont {
			color: var(--primary-color);
		}
	}
}
</style>
