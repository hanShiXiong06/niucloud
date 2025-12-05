<template>
	<view class="bg-gray-100 min-h-[100vh]" :style="themeColor()">
		<!-- 顶部搜索栏 -->
		<view class="fixed left-0 right-0 top-0 bg-[#fff] px-[30rpx] z-50">
			<view class="mp-search-wrapper" :style="{ padding: '50px 0 40px' }">
                <view class="mp-search-content" >
                    <view class="mp-back-btn" @click="redirect({ mode: 'back' })">
                        <text class="nc-iconfont nc-icon-fanhui4 text-[#333] text-[36rpx]"></text>
                    </view>
                    <view class="mp-search-input">
                        <text @click.stop="searchTypeFn('all')" class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn"></text>
                        <input class="input" maxlength="50" type="text" v-model="goods_name"
                            placeholder="请搜索您想要的商品"
                            placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search"
                            @confirm="searchTypeFn('all')">
                        <text v-if="goods_name" class="nc-iconfont nc-icon-cuohaoV6xx1 clear" @click="goods_name=''"></text>
                    </view>
                    <view :class="['iconfont text-[32rpx] text-[#333] mp-list-type-btn', listType ? 'icona-yingyongzhongxinV6xx-32' : 'icona-yingyongliebiaoV6xx-32']"
                        @click="listIconBtn"></view>
                </view>
            </view>

			<!-- 筛选标签 -->
			<scroll-view scroll-x class="filter-scroll" show-scrollbar="false">
				<view class="filter-scroll-content">
					<!-- 分类筛选 -->
					<view class="filter-tag"
						:class="{ 'active': activeFilters.category || selectedCategoryNames.length }"
						@click="showPopup('category')">
						<text>分类</text>
						<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
					</view>
					<!-- 成色筛选 -->
					<view class="filter-tag" :class="{ 'active': activeFilters.brand }" @click="showPopup('brand')">
						<text>成色</text>
						<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
					</view>
					<!-- 价格筛选 -->
					<view class="filter-tag" :class="{ 'active': activeFilters.price }" @click="showPopup('price')">
						<text>价格</text>
						<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
					</view>
					<!-- 内存筛选 -->
					<view class="filter-tag" :class="{ 'active': activeFilters.memory }" @click="showPopup('memory')"
						v-if="selectedCategories.length === 1 && memoryList.length">
						<text>内存</text>
						<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
					</view>
					<!-- 排序方式 -->
					<view class="filter-tag" :class="{ 'active': activeFilters.sort }" @click="showPopup('sort')">
						<text>{{ sortText }}</text>
						<text class="nc-iconfont nc-icon-a-xiangxiaV6xx1 text-[20rpx] ml-[4rpx]"></text>
					</view>
					<!-- 重置按钮 -->
					<view class="filter-tag reset" @click="resetFilters" v-if="hasActiveFilters">
						<text class="nc-iconfont nc-icon-zhongzhiV6xx text-[24rpx] mr-[4rpx]"></text>
						<text>重置</text>
					</view>
				</view>
			</scroll-view>
		</view>

		<!-- 分类弹窗 -->
		<u-popup :show="popups.category" mode="top" @close="closePopup('category')" :round="10">
			<view class="popup-content category-popup justify-between">
				<!-- 一级分类 -->
				<view class='flex items-center justify-between h-full'>
					<scroll-view scroll-y class="category-left">
						<view class="category-item" v-for="item in categoryList" :key="item.category_id"
							:class="{ 'active': currentCategory.category_id === item.category_id }"
							@click="selectParentCategory(item)">
							<text>{{ item.category_name }}</text>
							<text class="count">({{ item.goods_count || 0 }})</text>
						</view>
					</scroll-view>

					<!-- 二级分类 -->
					<scroll-view scroll-y class="category-right" v-if="currentCategory.child_list?.length">
						<view class="sub-category-list">
							<view class="sub-category-item" v-for="item in currentCategory.child_list"
								:key="item.category_id"
								:class="{ 'active': selectedCategories.includes(item.category_id) }"
								@click="selectSubCategory(item)">
								<view class="content">
									<text>{{ item.category_name }}</text>
									<text class="count">({{ item.goods_count || 0 }})</text>
								</view>
								<text class="nc-iconfont nc-icon-duigouV6xx check-icon"
									v-if="selectedCategories.includes(item.category_id)"></text>
							</view>
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

		<!-- 成色弹窗 -->
		<u-popup :show="popups.brand" mode="top" @close="closePopup('brand')" :round="10">
			<view class="popup-content">
				<view class="brand-list">
					<view class="brand-item" v-for="(item, index) in brandList" :key="index"
						:class="{ 'active': filters.brand_id === item.value }" @click="selectbrand(item)">
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
		<u-popup :show="popups.price" mode="top" @close="closePopup('price')" :round="10">
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
						<u-icon name='trash' :plain="true" @click="resetPrice"> </u-icon>

						<u-button type="primary" text="确定" @click="confirmPrice"></u-button>
					</view>
				</view>
			</view>
		</u-popup>

		<!-- 排序弹窗 -->
		<u-popup :show="popups.sort" mode="top" @close="closePopup('sort')" :round="10">
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
						:class="{ 'active': filters.memory_id === item.spec_id, 'inactive': filters.memory_id !== item.spec_id }"
						@click="selectMemory(item)">
						<text>{{ item.spec_name }}</text>
					</view>
				</view>
			</view>
		</u-popup>

		<!-- 商品列表 -->
		<mescroll-body ref="mescrollRef" top="220rpx" bottom="50px" @init="mescrollInit" :down="{ use: false }"
			@up="getGoodsList">
			<view v-if="goodsList.length" :class="[!listType ? 'grid-list' : '']">
				<template v-for="(item, index) in goodsList" :key="item.goods_id">
					<!-- 列表样式 -->
					<template v-if="listType">
						<view class="goods-item-list" @click="toDetail(item.goods_id)">
							<u--image class="goods-image" width="190rpx" height="190rpx"
								:src="img(item.goods_cover_thumb_mid)" radius="10">
								<template #error>
									<image class="goods-image"
										:src="img('static/resource/images/diy/shop_default.jpg')">
									</image>
								</template>
							</u--image>
							<view class="goods-info truncate w-[450rpx]">
								<view class="goods-name ">
									<text v-if="item.brand_name" class="brands-name  w-[50rpx]">{{ item.brand_name }}
									</text>
									<view class="truncate w-[400rpx]">{{ item.goods_name }}</view>
								</view>
								<view class="goods-subtitle truncate  w-[430rpx]" v-if="item.sub_title">{{
									item.sub_title }}</view>
								<view class="goods-sku truncate  w-[400rpx]" v-if="item.goodsSku.sku_no">
									<text class="label">串号:</text>
									<text class="value">{{ item.goodsSku.sku_no }}</text>
								</view>
								<view class="goods-brand" v-if="item.brand">
									<text class="label">成色:</text>
									<text class="value">{{ getbrandLabel(item.brand) }}</text>
								</view>
								<view class="goods-bottom">
									<view class="price-wrap">
										<text class="symbol">￥</text>
										<text class="price">{{ goodsPrice(item).toFixed(2).split('.')[0] }}</text>
										<text class="decimal">.{{ goodsPrice(item).toFixed(2).split('.')[1] }}</text>
										<image class="price-tag" v-if="priceType(item) == 'member_price'"
											:src="img('addon/phone_shop/VIP.png')" mode="heightFix" />
										<image class="price-tag" v-if="priceType(item) == 'discount_price'"
											:src="img('addon/phone_shop/discount.png')" mode="heightFix" />
									</view>
									<view class="action-btns">
										<view class="stock-info">
											<text>库存:{{ item.goodsSku.stock }}{{ item.unit }}</text>

										</view>
										<view class="download-btn" @click.stop="downloadGoods(item)">
											<text class="nc-iconfont nc-icon-fenxiangV6xx"></text>
										</view>
									</view>
								</view>
							</view>
						</view>
					</template>
					<!-- 网格样式 -->
					<template v-else>
						<view class="goods-item-grid" @click="toDetail(item.goods_id)">
							<!-- <u--image class="goods-image" width="100%" height="200rpx"
								:src="img(item.goods_cover_thumb_mid)" radius="10">
								<template #error>
									<image class="goods-image"
										:src="img('static/resource/images/diy/shop_default.jpg')">
									</image>
								</template>
</u--image> -->
							<view class="goods-info">
								<view class="goods-name text-sm truncate w-[100%]">{{ item.goods_name }}</view>
								<view class="goods-subtitle truncate w-[100%]" v-if="item.sub_title">{{ item.sub_title
									}}</view>
								<view class="price-wrap">
									<view class="left">
										<text class="symbol">￥</text>
										<text class="price">{{ goodsPrice(item).toFixed(2).split('.')[0] }}</text>
										<text class="decimal">.{{ goodsPrice(item).toFixed(2).split('.')[1] }}</text>
										<image class="price-tag" v-if="priceType(item) == 'member_price'"
											:src="img('addon/phone_shop/VIP.png')" mode="heightFix" />
										<image class="price-tag" v-if="priceType(item) == 'discount_price'"
											:src="img('addon/phone_shop/discount.png')" mode="heightFix" />
									</view>
									<view class="right">

										<view class="download-btn" @click.stop="downloadGoods(item)">
											<text class="nc-iconfont nc-icon-fenxiangV6xx"></text>
										</view>
									</view>
								</view>

							</view>
						</view>
					</template>
				</template>
			</view>
			<view class="empty-state" v-if="!goodsList.length && loading">
				<u-empty mode="data" text="暂无相关商品"></u-empty>
			</view>
		</mescroll-body>

		
	</view>
</template>

<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { redirect, img, getToken } from '@/utils/common'
import { getGoodsPages, getGoodsCategoryTree, getBrandList, getGoodsDetail, getMemoryList } from '@/addon/phone_shop/api/goods'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onPageScroll, onReachBottom, onLoad } from '@dcloudio/uni-app'

const { mescrollInit, getMescroll } = useMescroll(onPageScroll, onReachBottom)

// 列表数据
const goodsList = ref<Array<any>>([])
const mescrollRef = ref(null)
const loading = ref<boolean>(false)
const listType = ref(true)

// 搜索和筛选
const goods_name = ref("")
const categoryList = ref<Array<any>>([])
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

// 成色选项
const brandList = ref([
	// { label: '全新', value: 'new' },
	// { label: '99新', value: '99' },
	// { label: '95新', value: '95' },
	// { label: '9成新', value: '90' },
	// { label: '8成新', value: '80' },
	// { label: '7成新', value: '70' }
])

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
	// { label: '销量从高到低', value: 'sales_desc' },
	{ label: '最新上架', value: 'createTime_desc' }
]

// 内存选项
const memoryList = ref<Array<any>>([])


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
// getBrandList
const _getBrandList = async () => {
	const res = await getBrandList()
	if (res.code !== 1) return
	// brandList = res.data
	// 格式化 数据 brand_name 为 label value 
	console.log(res.data)
	const result = res.data.map((item: any) => ({
		label: item.brand_name,
		value: item.brand_id
	}))
	brandList.value = result
	console.log(result)
}
// resetBrand 	
const resetBrand = () => {
	filters.brand_id = ''
	closePopup('brand')
	searchGoods()
}

// 页面加载
onLoad(async (option) => {
	if (option.category_id) {
		filters.category_id = option.category_id
	}
	if (option.goods_name) {
		goods_name.value = option.goods_name
	}
	await loadCategories()
	await _getBrandList()
})

// 分类相关状态
const currentCategory = ref<any>({})
const selectedCategories = ref<number[]>([])
const selectedCategoryNames = ref<string[]>([])

// 选择一级分类
const selectParentCategory = (item: any) => {
	currentCategory.value = item
	// 将内存清空
	filters.memory_id = ''
	// 如果当前一级分类已经被选中，则取消选中
	const index = selectedCategories.value.indexOf(item.category_id)
	if (index > -1) {
		selectedCategories.value.splice(index, 1)
		selectedCategoryNames.value.splice(index, 1)
	} else if (!item.child_list?.length) {
		// 如果没有子分类，直接选中一级分类
		selectedCategories.value.push(item.category_id)
		selectedCategoryNames.value.push(item.category_name)
	}
}

// 选择二级分类
const selectSubCategory = (item: any) => {
	// 如果已经选中了父分类，先移除父分类
	const parentIndex = selectedCategories.value.indexOf(currentCategory.value.category_id)
	filters.memory_id = ''
	if (parentIndex > -1) {
		selectedCategories.value.splice(parentIndex, 1)
		selectedCategoryNames.value.splice(parentIndex, 1)
	}

	const index = selectedCategories.value.indexOf(item.category_id)
	if (index > -1) {
		selectedCategories.value.splice(index, 1)
		selectedCategoryNames.value.splice(index, 1)
	} else {
		selectedCategories.value.push(item.category_id)
		selectedCategoryNames.value.push(item.category_full_name)
	}
}

// 移除已选分类
const removeCategory = (index: number) => {
	selectedCategories.value.splice(index, 1)
	selectedCategoryNames.value.splice(index, 1)
}

// 重置分类选择
const resetCategories = () => {
	selectedCategories.value = []
	selectedCategoryNames.value = []
	currentCategory.value = {}
	filters.memory_id = ''
}

// 修改加载分类方法
const loadCategories = async () => {
	try {
		const res = await getGoodsCategoryTree()
		categoryList.value = res.data
		// 如果有预选分类，需要置选中状态
		if (filters.category_id) {
			const ids = filters.category_id.split(',').map(Number)
			selectedCategories.value = ids
			// 查找并设置分类名称
			const names: string[] = []
			categoryList.value.forEach(parent => {
				parent.child_list?.forEach(child => {
					if (ids.includes(child.category_id)) {
						names.push(child.category_full_name)
					}
				})
			})
			selectedCategoryNames.value = names
		}
		// 获取内存列表
		if (selectedCategories.value.length === 1) {
			await loadMemoryList(selectedCategories.value[0])
		} else {
			memoryList.value = []
		}
	} catch (error) {
		console.error('加载分类失败:', error)
	}
}

// 加载内存列表
const loadMemoryList = async (categoryId: number) => {
	try {
		const res = await getMemoryList({ id: categoryId })
		memoryList.value = res.data
	} catch (error) {
		console.error('加载内存列表失败:', error)
	}
}

// 确认分类选择
const confirmCategories = () => {
	// 如果没有选择任何分类，但有当前选中的一级分类，则选中一级分类
	// if (selectedCategories.value.length === 0 && currentCategory.value.category_id) {
	// 	selectedCategories.value.push(currentCategory.value.category_id)
	// 	selectedCategoryNames.value.push(currentCategory.value.category_name)
	// }
	console.log(selectedCategoryNames.value);

	if (selectedCategories.value.length > 1) {
		uni.showToast({ title: '只能选择一个分类才能筛选内存', icon: 'none' })
		memoryList.value = []
		filters.memory_id = ''
		filters.category_id = selectedCategories.value.join(',')
	} else {
		filters.category_id = selectedCategories.value.join(',')
		loadMemoryList(selectedCategories.value[0])
	}
	closePopup('category')
	searchGoods()
}

// 获取商品列表
const getGoodsList = (mescroll: any) => {
	loading.value = false
	const params = {
		page: mescroll.num,
		limit: mescroll.size,
		keyword: goods_name.value,
		goods_category: filters.category_id,
		brand_id: filters.brand_id,
		start_price: filters.price_min || undefined,
		end_price: filters.price_max || undefined,
		memory_id: filters.memory_id || undefined
	}

	// 添加排序参数
	if (filters.sort !== 'default') {
		const [field, order] = filters.sort.split('_')
		// 如果排序字段为createTime_desc，则改为create_time
		params.order = field == 'createTime' ? 'create_time' : field
		params.sort = order
	}

	getGoodsPages(params).then((res: any) => {
		const newArr = res.data.data
		if (mescroll.num === 1) goodsList.value = []
		goodsList.value = goodsList.value.concat(newArr)
		mescroll.endSuccess(newArr.length)
		loading.value = true
	}).catch(() => {
		loading.value = true
		mescroll.endErr()
	})
}

// 搜索商品
const searchGoods = () => {
	goodsList.value = []
	getMescroll().resetUpScroll()
}

// 显示弹窗
const showPopup = (type: string) => {
	Object.keys(popups).forEach(key => {
		popups[key] = key === type
	})
}

// 关闭弹窗
const closePopup = (type: string) => {
	popups[type] = false
}

// 选择分类
const selectCategory = (item: any) => {
	filters.category_id = item.category_id
	closePopup('category')
	searchGoods()
}

// 选择成色
const selectbrand = (item: any) => {
	filters.brand_id = filters.brand_id === item.value ? '' : item.value
	closePopup('brand')
	searchGoods()
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
	searchGoods()
}

// 重置价格筛选
const resetPrice = () => {
	filters.price_min = ''
	filters.price_max = ''
	filters.price_range = ''
}

// 选择排序方式
const selectSort = (item: any) => {
	filters.sort = item.value
	closePopup('sort')
	searchGoods()
}

// 重置所有筛选
const resetFilters = () => {
	Object.keys(filters).forEach(key => {
		filters[key] = key === 'sort' ? 'default' : ''
	})
	searchGoods()
}

// 切换列表样式
const listIconBtn = () => {
	listType.value = !listType.value
}

// 获取成色标签
const getbrandLabel = (value: string) => {
	const item = brandList.find(item => item.value === value)
	return item ? item.label : value
}

// 跳转详情
const toDetail = (id: string | number) => {
	redirect({ url: '/addon/phone_shop/pages/goods/detail', param: { goods_id: id }, mode: 'navigateTo' })
}

// 价格相关方法
const priceType = (data: any) => {
	if (data.is_discount && data.goodsSku.sale_price != data.goodsSku.price) {
		return 'discount_price'
	} else if (data.member_discount && getToken() && data.goodsSku.member_price != data.goodsSku.price) {
		return 'member_price'
	}
	return ""
}

const goodsPrice = (data: any) => {
	let price = "0.00"
	if (data.is_discount && data.goodsSku.sale_price != data.goodsSku.price) {
		price = data.goodsSku.sale_price || data.goodsSku.price
	} else if (data.member_discount && getToken() && data.goodsSku.member_price != data.goodsSku.price) {
		price = data.goodsSku.member_price || data.goodsSku.price
	} else {
		price = data.goodsSku.price
	}
	return parseFloat(price)
}

// 下载单个商品
const downloadGoods = async (item: any) => {
	const res = await getGoodsDetail({ goods_id: item.goods_id })
	if (!res.data.goods) {
		uni.showToast({ title: '商品信息获取失败', icon: 'none' })
		return
	}

	const images = res.data.goods.goods_image.split(',')
	downloadImages(images, item)
}

// 下载图片并复制文案
const downloadImages = (images: string[], item: any, showToast: boolean = true) => {
	return new Promise((resolve) => {
		const tasks = images.map((url: string) => {
			return new Promise((resolve, reject) => {
				uni.downloadFile({
					url,
					success: (res) => {
						if (res.statusCode === 200) {
							uni.saveImageToPhotosAlbum({
								filePath: res.tempFilePath,
								success: resolve,
								fail: reject
							})
						} else {
							reject()
						}
					},
					fail: reject
				})
			})
		})

		Promise.all(tasks).then(() => {
			const sku_no = item.goodsSku.sku_no ? '#' + item.goodsSku.sku_no + ' ' : ''
			const text = `${item.goods_name} ${item.sub_title} ${sku_no}${goodsPrice(item)}`
			uni.setClipboardData({
				data: text,
				success: () => {
					if (showToast) {
						uni.showToast({ title: '图片下载及文案复制成功', icon: 'none' })
					}
					resolve(true)
				}
			})
		}).catch(() => {
			if (showToast) {
				uni.showToast({ title: '下载失败', icon: 'none' })
			}
			resolve(false)
		})
	})
}

// 选择内存
const selectMemory = (item: any) => {
	filters.memory_id = filters.memory_id === item.spec_id ? '' : item.spec_id;
	// 关闭 
	closePopup('memory')
	searchGoods()
}
</script>

<style lang="scss" scoped>
.filter-scroll {
	width: 100%;
	white-space: nowrap;
	padding: 16rpx 0;
	position: relative;
	z-index: 99999;
	top: 134rpx;

	&::-webkit-scrollbar {
		display: none;
	}

	.filter-scroll-content {
		display: inline-flex;
		padding: 0 30rpx;
	}
}

.filter-tag {
	display: inline-flex;
	align-items: center;
	height: 56rpx;
	padding: 0 24rpx;
	margin-right: 20rpx;
	font-size: 26rpx;
	color: #666;
	background: #f6f8f8;
	border-radius: 28rpx;
	transition: all 0.3s;
	flex-shrink: 0;

	&:last-child {
		margin-right: 0;
	}

	&.active {
		color: var(--primary-color);
		background: var(--primary-color-light);
	}

	&.reset {
		color: #999;
	}
}

.popup-content {
	background: #fff;
	padding: 30rpx;
	max-height: 60vh;
}

.category-scroll {
	max-height: 50vh;
}

.category-item {
	padding: 24rpx 0;
	font-size: 28rpx;
	color: #333;
	border-bottom: 1px solid #f5f5f5;

	&.active {
		color: var(--primary-color);
	}

	.count {
		font-size: 24rpx;
		color: #999;
		margin-left: 10rpx;
	}
}

.brand-list,
.memory-list {
	display: flex;
	flex-wrap: wrap;
	margin: -10rpx;
}

.brand-item,
.memory-item {
	width: calc(33.33% - 20rpx);
	height: 70rpx;
	margin: 10rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 26rpx;
	color: #666;
	background: #f6f8f8;
	border-radius: 8rpx;

	&.active {
		color: var(--primary-color);
		background: var(--primary-color-light);
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
		margin: -10rpx;
		margin-bottom: 30rpx;
	}

	.price-tag {
		width: calc(33.33% - 20rpx);
		height: 70rpx;
		margin: 10rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 26rpx;
		color: #666;
		background: #f6f8f8;
		border-radius: 8rpx;

		&.active {
			color: var(--primary-color);
			background: var(--primary-color-light);
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
		padding: 24rpx 0;
		font-size: 28rpx;
		color: #333;
		border-bottom: 1px solid #f5f5f5;

		&.active {
			color: var(--primary-color);
		}
	}
}

.goods-item-list {
	display: flex;
	padding: 20rpx;
	margin: 20rpx;
	background: #fff;
	border-radius: 16rpx;

	.brands-name {
		font-size: 24rpx;
		color: #ffffff;
		background-color: #19a9a9;
		border-radius: 8rpx;
		padding: 5rpx;

	}

	.goods-image {
		width: 190rpx;
		height: 190rpx;
		border-radius: 10rpx;
		overflow: hidden;
	}

	.goods-info {
		flex: 1;
		margin-left: 20rpx;
		display: flex;
		flex-direction: column;
	}

	.goods-name {
		font-size: 28rpx;
		color: #333;
		line-height: 1.4;
		margin-bottom: 2rpx;
		display: flex;
		align-items: center;
	}

	.goods-subtitle {
		font-size: 24rpx;
		color: #666;
		margin-bottom: 2rpx;
	}

	.goods-sku,
	.goods-brand {
		font-size: 24rpx;
		margin-bottom: 1rpx;

		.label {
			color: #999;
		}

		.value {
			color: #666;
			margin-left: 10rpx;
		}
	}

	.goods-bottom {
		margin-top: auto;
		display: flex;
		justify-content: space-between;
		align-items: flex-end;

		.action-btns {
			display: flex;
			align-items: center;
			gap: 20rpx;
		}

		.download-btn {
			width: 48rpx;
			height: 48rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			background: var(--primary-color);
			border-radius: 24rpx;
			color: #fff;

			.nc-iconfont {
				font-size: 24rpx;
			}
		}
	}
}

.goods-item-grid {
	background: #fff;
	border-radius: 16rpx;
	overflow: hidden;
	margin: 10rpx;
	width: calc(50% - 20rpx);

	.goods-image {
		width: 100%;
		height: 210rpx;
	}

	.goods-info {
		padding: 10rpx;
	}

	.goods-name {
		font-size: 28rpx;
		color: #333;
		line-height: 1.4;
		margin-bottom: 10rpx;
	}

	.goods-subtitle {
		font-size: 24rpx;
		color: #666;
		margin-bottom: 8rpx;
	}

	.goods-bottom {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-top: 10rpx;

		.left {
			flex: 1;
			display: flex;
			align-items: center;
			gap: 12rpx;
			font-size: 24rpx;

			.stock {
				color: #999;
			}

			.brand {
				color: #666;
				background: #f6f8f8;
				padding: 4rpx 12rpx;
				border-radius: 4rpx;
			}
		}

		.download-btn {
			width: 48rpx;
			height: 48rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			background: var(--primary-color);
			border-radius: 24rpx;
			color: #fff;

			.nc-iconfont {
				font-size: 24rpx;
			}
		}
	}
}

.price-wrap {
	display: flex;
	align-items: baseline;
	justify-content: space-between;

	.symbol {
		font-size: 24rpx;
		color: var(--price-text-color);
	}

	.price {
		font-size: 36rpx;
		font-weight: bold;
		color: var(--price-text-color);
		font-family: 'DIN';
	}

	.decimal {
		font-size: 24rpx;
		color: var(--price-text-color);
		font-family: 'DIN';
	}

	.price-tag {
		height: 24rpx;
		margin-left: 6rpx;
	}
}

.stock-info {
	font-size: 22rpx;
	color: #999;

	.separator {
		margin: 0 10rpx;
	}
}

.grid-list {
	display: flex;
	flex-wrap: wrap;
	padding: 10rpx;
}

.empty-state {
	padding: 40rpx;
	background: #fff;
	border-radius: 16rpx;
	margin: 20rpx;
}

.category-popup {
	display: flex;
	flex-direction: column;
	height: 70vh;
	padding: 0;

	.category-left {
		width: 200rpx;
		height: calc(100% - 120rpx);
		background: #f8f8f8;

		.category-item {
			padding: 30rpx 20rpx;
			font-size: 28rpx;
			color: #333;
			border-bottom: 1px solid #eee;
			position: relative;
			transition: all 0.3s;

			&.active {
				color: var(--primary-color);
				background: #fff;
				font-weight: 500;

				&::before {
					content: '';
					position: absolute;
					left: 0;
					top: 50%;
					transform: translateY(-50%);
					width: 6rpx;
					height: 36rpx;
					background: var(--primary-color);
					border-radius: 0 4rpx 4rpx 0;
				}
			}

			.count {
				font-size: 24rpx;
				color: #999;
				margin-left: 8rpx;
			}
		}
	}

	.category-right {
		flex: 1;
		height: calc(100% - 120rpx);
		background: #fff;

		.sub-category-list {
			padding: 20rpx;
		}

		.sub-category-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 24rpx 20rpx;
			margin-bottom: 16rpx;
			border-radius: 8rpx;
			background: #f8f8f8;
			transition: all 0.3s;

			&.active {
				color: var(--primary-color);
				background: var(--primary-color-light);
			}

			.content {
				font-size: 28rpx;

				.count {
					font-size: 24rpx;
					color: #999;
					margin-left: 8rpx;
				}
			}

			.check-icon {
				font-size: 32rpx;
				color: var(--primary-color);
			}
		}
	}

	.category-footer {
		height: 150rpx;
		padding: 20rpx;
		border-top: 1px solid #eee;
		background: #fff;

		.selected-tags {
			display: flex;
			flex-wrap: wrap;
			gap: 12rpx;
			margin-bottom: 16rpx;
			max-height: 72rpx;
			overflow-y: auto;

			&::-webkit-scrollbar {
				display: none;
			}

			.tag {
				display: inline-flex;
				align-items: center;
				height: 44rpx;
				padding: 0 16rpx;
				font-size: 24rpx;
				color: var(--primary-color);
				background: var(--primary-color-light);
				border-radius: 22rpx;

				.nc-icon-guanbiV6xx {
					margin-left: 8rpx;
					font-size: 24rpx;
				}
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
}

/* 小程序环境样式 */
/* #ifdef MP-WEIXIN */
.mp-search-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 99999;
    background-color: #fff;
   

}

.mp-search-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20rpx;
    box-sizing: border-box;
    width: 600rpx;
}

.mp-back-btn {
    width: 60rpx;
    height: 60rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20rpx;
}

.mp-search-input {
    flex: 1;
    display: flex;
    align-items: center;
    height: 60rpx;
    background-color: #f6f6f6;
    padding: 0 30rpx;
    border-radius: 100rpx;
    margin-right: 20rpx;

    .input {
        flex: 1;
        font-size: 24rpx;
        padding-right: 32rpx;
        height: 100%;
    }

    .clear {
        font-size: 24rpx;
        color: #999;
        line-height: 1;
    }

    .btn {
        margin-bottom: -2rpx;
        margin-right: 18rpx;
        font-size: 24rpx;
        color: #333;
    }
}

.mp-list-type-btn {
    width: 60rpx;
    height: 60rpx;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mp-filter-bar {
    position: fixed;
    left: 0;
    right: 0;
    z-index: 99998;
    margin-top: 158rpx; /* 导航栏高度 */
}
/* #endif */
</style>