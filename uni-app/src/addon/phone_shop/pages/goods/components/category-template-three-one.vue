<template>
	<view class="category-three min-h-screen bg-[#f6f7f8] overflow-hidden" :style="categoryThemeStyle">
		<view v-if="tabsData.length" class="category-layout">
			<view class="category-header fixed top-0 left-0 right-0 z-20 bg-[#f6f7f8]" :style="headerStyle">
				<view class="header-search" v-if="config.search.control" :style="headerSearchStyle">
					<text class="page-title">分类</text>
					<view class="search-input">
						<text class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn" @click.stop="searchNameFn"></text>
						<input class="input" type="text" v-model="searchName" :placeholder="config.search.title || '搜索商品'" placeholderClass="text-[var(--text-color-light9)]" @confirm="searchNameFn" />
						<text v-if="searchName" class="nc-iconfont nc-icon-cuohaoV6xx1 clear" @click="searchName=''"></text>
					</view>
				</view>

				<view class="first-category-row">
					<scroll-view :scroll-x="true" :show-scrollbar="false" class="first-scroll" :class="{ 'is-text': !firstCategoryConfig.show_icon }">
						<view class="first-list">
							<view v-for="(item, index) in tabsData" :key="item.category_id" class="first-item" :class="{ active: index == firstActive, 'no-icon': !firstCategoryConfig.show_icon }" @click="firstLevelClick(index)">
								<view class="first-img" v-if="firstCategoryConfig.show_icon">
									<u--image v-if="item.image" width="52rpx" height="52rpx" radius="14rpx" :src="img(item.image)" model="aspectFill">
										<template #error>
											<view class="first-img-letter">{{ categoryInitial(item.category_name) }}</view>
										</template>
									</u--image>
									<view v-else class="first-img-letter">{{ categoryInitial(item.category_name) }}</view>
								</view>
								<text class="first-text truncate">{{ item.category_name }}</text>
							</view>
						</view>
					</scroll-view>
					<view class="more-category-btn" @click="showCategoryPopup = true">
						<text class="nc-iconfont nc-icon-fenleiV6mm more-category-icon"></text>
						<text class="more-category-text">全部</text>
					</view>
				</view>
			</view>

			<view class="content fixed left-0 right-0 bottom-0" :style="contentStyle" :class="{ 'has-cart': config.cart.control && config.cart.event === 'cart' }">
				<view class="second-panel">
					<scroll-view :scroll-y="true" class="h-full">
						<view v-for="(item, index) in secondLevelList" :key="item.category_id" class="second-item" :class="{ active: index == secondActive }" @click="secondLevelClick(index)">
							<text class="second-text">{{ item.category_name }}</text>
						</view>
					</scroll-view>
				</view>

				<view class="goods-panel">
					<scroll-view
						class="goods-scroll"
						:scroll-y="true"
						:show-scrollbar="false"
						:lower-threshold="120"
						:refresher-enabled="true"
						:refresher-triggered="refreshing"
						@refresherrefresh="refreshGoods"
						@refresherrestore="refreshing = false"
						@scrolltolower="loadMoreGoods"
					>
						<!-- 仓库切换:本地仓/代理仓(后台分类配置开启 + 本站为有代理货的子站才显示) -->
						<view class="warehouse-bar" v-if="showWarehouseSwitch">
							<view class="wh-tab" :class="{ on: warehouse === '' }" @click="switchWarehouse('')">全部</view>
							<view class="wh-tab" :class="{ on: warehouse === 'local' }" @click="switchWarehouse('local')">本地仓</view>
							<view class="wh-tab" :class="{ on: warehouse === 'agent' }" @click="switchWarehouse('agent')">{{ warehouseAgentName }}</view>
						</view>
						<view class="third-row">
								<scroll-view :scroll-x="true" :show-scrollbar="false" class="third-scroll" v-if="thirdLevelList.length">
									<view class="third-list">
										<view class="third-item" :class="{ active: thirdActive === -1 }" @click="thirdLevelClick(-1, selectedSecond)">全部</view>
										<view v-for="(item, index) in thirdLevelList" :key="item.category_id" class="third-item" :class="{ active: index == thirdActive }" @click="thirdLevelClick(index, item)">{{ item.category_name }}</view>
									</view>
								</scroll-view>
								<view v-else class="third-scroll-fill"></view>
								<view class="third-filter-btn" :class="{ active: activeFilterCount }" @click="openFilter">
									<text class="nc-iconfont nc-icon-shaixuanV6xx third-filter-icon"></text>
									<text>筛选</text>
									<text v-if="activeFilterCount" class="third-filter-count">{{ activeFilterCount }}</text>
								</view>
							</view>

						<view v-if="goodsLoading && !list.length" class="goods-skeleton">
							<view v-for="item in 5" :key="item" class="skeleton-item">
								<view class="skeleton-img"></view>
								<view class="skeleton-info">
									<view class="skeleton-line wide"></view>
									<view class="skeleton-line"></view>
									<view class="skeleton-line short"></view>
								</view>
							</view>
						</view>

						<view v-else class="goods-list">
							<view v-for="(item, index) in list" :key="item.goods_id" class="goods-item" @click.stop="toGoodsDetail(item.goods_id)">
								<view class="goods-img">
									<view v-if="config.show_quality && item.condition_grade" class="quality-badge quality-badge--grade">
										<text>{{ item.condition_grade }}</text>
									</view>
									<view v-if="config.show_quality && qcAbnormal(item)" class="quality-badge quality-badge--warning">
										<text>异常 {{ qcAbnormal(item) }}</text>
									</view>
									<u--image width="180rpx" height="180rpx" radius="10rpx" :src="img(item.goods_cover_thumb_mid || '')" model="aspectFill">
										<template #error>
											<image class="w-[180rpx] h-[180rpx] rounded-[10rpx]" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
										</template>
									</u--image>
								</view>
								<view class="goods-info">
									<view class="goods-name multi-hidden">{{ item.goods_name }}</view>
									<PhoneGoodsMeta :subtitle="item.sub_title" :imei="item.goodsSku?.sku_no" compact />
										<!-- <view class="device-row" v-if="item.memory_group || (config.show_quality && (item.condition_grade || qcAbnormal(item)))">
											<text v-if="item.memory_group" class="device-chip">{{ item.memory_group }}</text>
											<text v-if="config.show_quality && item.condition_grade" class="device-chip">{{ item.condition_grade }}</text>
											<text v-if="config.show_quality && qcAbnormal(item)" class="device-chip warn">异常{{ qcAbnormal(item) }}</text>
										</view> -->
									<!-- <view class="goods-date">预计 {{ deliveryDate }} 送达</view> -->
									<view class="goods-bottom">
										<view class="price-font goods-price">
											<text class="unit">￥</text>
											<text>{{ parseFloat(goodsPrice(item)).toFixed(2) }}</text>
											<image v-if="priceType(item) === 'member_price'" class="price-badge" :src="img('addon/phone_shop/VIP.png')" mode="heightFix" />
											<image v-else-if="priceType(item) === 'newcomer_price'" class="price-badge" :src="img('addon/phone_shop/newcomer.png')" mode="heightFix" />
											<image v-else-if="priceType(item) === 'discount_price'" class="price-badge" :src="img('addon/phone_shop/discount.png')" mode="heightFix" />
										</view>
										<view v-if="goodsAction" class="cart-action">
											<view
												v-if="item.goodsSku && item.goodsSku.sku_spec_format === '' && cartList['goods_' + item.goods_id] && cartList['goods_' + item.goods_id]['sku_' + item.goodsSku.sku_id] && config.cart.event === 'cart'"
												class="stepper">
												<text class="nc-iconfont nc-icon-jianshaoV6xx step-icon" @click.stop="reduceCart(cartList['goods_' + item.goods_id]['sku_' + item.goodsSku.sku_id])"></text>
												<text class="step-num">{{ cartList['goods_' + item.goods_id]['sku_' + item.goodsSku.sku_id].num }}</text>
												<text class="iconfont iconjiahao2fill step-icon add" :id="'itemCart' + index" @click.stop="addCartBtn(item, cartList['goods_' + item.goods_id]['sku_' + item.goodsSku.sku_id], 'itemCart' + index)"></text>
											</view>
											<PhoneGoodsActionButton v-else :id="'itemCart' + index" :action="goodsAction" @action="itemCart(item, 'itemCart' + index)" />
										</view>
									</view>
								</view>
							</view>
						</view>
						<view v-if="!list.length && !goodsLoading && listLoading" class="empty-goods">
							<image class="empty-goods-img" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
							<text class="empty-goods-title">暂无商品</text>
							<text class="empty-goods-desc">换个分类或排序看看</text>
						</view>
						<view v-if="list.length" class="load-more">
							<view v-if="goodsLoading" class="load-more-inner">
								<view class="loading-dot"></view>
								<text>正在加载更多</text>
							</view>
							<text v-else-if="finished">没有更多了</text>
							<text v-else>上拉加载更多</text>
						</view>
						<add-cart-popup ref="cartRef" />
						<download-config-dialog :show="showDownloadConfig" @close="showDownloadConfig = false" @confirm="onDownloadConfigConfirm" />
					</scroll-view>
				</view>
			</view>

			<view v-if="showCategoryPopup" class="category-mask" @click="showCategoryPopup = false">
				<view class="category-popup" @click.stop>
					<view class="category-popup-head">
						<view>
							<view class="category-popup-title">全部分类</view>
							<view class="category-popup-desc">快速切换一级分类</view>
						</view>
						<text class="nc-iconfont nc-icon-cuohaoV6xx1 category-popup-close" @click="showCategoryPopup = false"></text>
					</view>
					<scroll-view :scroll-y="true" class="category-popup-scroll">
						<view class="category-grid">
							<view
								v-for="(item, index) in tabsData"
								:key="item.category_id"
								class="category-grid-item"
								:class="{ active: index === firstActive }"
								@click="selectFirstFromPopup(index)"
							>
								<image v-if="firstCategoryConfig.show_icon" class="category-grid-img" :src="img(item.image || 'static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
								<text class="category-grid-text">{{ item.category_name }}</text>
							</view>
						</view>
					</scroll-view>
				</view>
			</view>

			<!-- 分类上下文筛选弹窗 -->
			<u-popup :show="showFilter" mode="bottom" :round="28" :safeAreaInsetBottom="true" :closeable="true" @close="closeFilter">
				<view class="ftr">
					<view class="ftr-head">
						<view>
							<text class="ftr-title">筛选商品</text>
							<text class="ftr-subtitle">{{ filterContextText }}</text>
						</view>
						<view v-if="activeFilterCount" class="ftr-selected-count">已选 {{ activeFilterCount }} 项</view>
					</view>
					<view class="ftr-body">
						<scroll-view :scroll-y="true" class="ftr-dims">
							<view v-for="d in dims" :key="d.key" class="ftr-dim" :class="{ on: activeDim === d.key }" @click="activeDim = d.key">
								<text class="ftr-dim-text">{{ d.label }}</text>
								<view v-if="dimHasSelected(d.key)" class="ftr-dim-dot"></view>
							</view>
						</scroll-view>
						<scroll-view :scroll-y="true" class="ftr-panel">
							<template v-if="activeDim === 'category'">
								<view class="ftr-chips" v-if="thirdOptions.length">
									<view class="ftr-chip" :class="{ on: tmpThird === -1 }" @click="chooseTmpThird(-1)"><text>全部</text></view>
									<view v-for="c in thirdOptions" :key="c.category_id" class="ftr-chip" :class="{ on: tmpThird === c.category_id }" @click="chooseTmpThird(c.category_id)"><text>{{ c.category_name }}</text></view>
								</view>
								<view v-else class="ftr-empty">该分类下暂无三级分类</view>
							</template>
							<template v-else-if="activeDim === 'memory'">
								<view v-for="grp in memoryGroups" :key="grp.name" class="ftr-group">
									<text class="ftr-group-name">{{ grp.name }}</text>
									<view class="ftr-chips">
										<view v-for="m in (expand['mem_' + grp.name] ? grp.items : grp.items.slice(0, 6))" :key="m" class="ftr-chip" :class="{ on: tmpMemory.includes(m) }" @click="toggleChip(tmpMemory, m)"><text>{{ m }}</text></view>
										<view v-if="grp.items.length > 6" class="ftr-chip more" @click="toggleExpand('mem_' + grp.name)"><text>{{ expand['mem_' + grp.name] ? '收起' : '查看更多' }}</text></view>
									</view>
								</view>
								<view v-if="!memoryGroups.length" class="ftr-empty">暂无内存可筛选</view>
							</template>
							<template v-else-if="activeDim === 'grade'">
								<view class="ftr-chips" v-if="gradeOptions.length">
									<view v-for="g in gradeOptions" :key="g" class="ftr-chip" :class="{ on: tmpGrade.includes(g) }" @click="toggleChip(tmpGrade, g)"><text>{{ g }}</text></view>
								</view>
								<view v-else class="ftr-empty">暂无成色可筛选</view>
							</template>
							<template v-else-if="activeDim === 'color'">
								<view class="ftr-section-head">
									<text class="ftr-section-title">机身颜色</text>
									<text class="ftr-section-tip">只显示当前分类有货商品的颜色</text>
								</view>
								<view class="ftr-chips" v-if="colorOptions.length">
									<view v-for="c in colorOptions" :key="c" class="ftr-chip" :class="{ on: tmpColor.includes(c) }" @click="toggleChip(tmpColor, c)"><text>{{ c }}</text></view>
								</view>
								<view v-else class="ftr-empty">当前分类暂无结构化颜色</view>
							</template>
							<template v-else-if="activeDim === 'device'">
								<view class="ftr-group" v-if="batteryOptions.length">
									<view class="ftr-section-head">
										<text class="ftr-section-title">电池健康</text>
										<text class="ftr-section-tip">按录入的电池健康度筛选</text>
									</view>
									<view class="ftr-chips">
										<view v-for="item in batteryOptions" :key="item.value" class="ftr-chip" :class="{ on: tmpBattery.includes(String(item.value)) }" @click="toggleChip(tmpBattery, String(item.value))"><text>{{ item.label }}</text></view>
									</view>
								</view>
								<view class="ftr-group" v-if="warrantyOptions.length">
									<view class="ftr-section-head">
										<text class="ftr-section-title">保修状态</text>
										<text class="ftr-section-tip">每天按保修截止日实时计算</text>
									</view>
									<view class="ftr-chips">
										<view v-for="item in warrantyOptions" :key="item.value" class="ftr-chip" :class="{ on: tmpWarranty.includes(String(item.value)) }" @click="toggleChip(tmpWarranty, String(item.value))"><text>{{ item.label }}</text></view>
									</view>
								</view>
							</template>
							<template v-else-if="activeDim === 'price'">
								<view class="ftr-section-head">
									<text class="ftr-section-title">价格区间</text>
									<text class="ftr-section-tip">可选常用区间，也可以自定义</text>
								</view>
								<GoodsPriceRangePicker v-model:start-value="tmpStartPrice" v-model:end-value="tmpEndPrice" :ranges="priceRangeOptions" />
							</template>
							<template v-else-if="activeDim === 'sort'">
								<view class="ftr-chips">
									<view v-for="opt in enabledSortOptions" :key="opt.key" class="ftr-chip" :class="{ on: opt.field ? tmpSortField === opt.field : !tmpSortField }" @click="pickSort(opt)">
										<text>{{ opt.label }}</text>
										<text v-if="opt.field && tmpSortField === opt.field" class="nc-iconfont ftr-chip-arrow" :class="tmpSortType === 'asc' ? 'nc-icon-a-xiangshangV6xx1' : 'nc-icon-a-xiangxiaV6xx1'"></text>
									</view>
								</view>
							</template>
						</scroll-view>
					</view>
					<view class="ftr-foot">
						<view class="ftr-foot-action ftr-foot-action--reset"><u-button shape="circle" :customStyle="{ height: '84rpx', background: '#f2f3f5', color: '#4e5969', border: 'none', fontWeight: '600' }" text="重置" @click="resetFilter"></u-button></view>
						<view class="ftr-foot-action ftr-foot-action--confirm"><u-button shape="circle" type="primary" :customStyle="{ height: '84rpx', background: 'var(--primary-color)', border: 'none', fontWeight: '600' }" text="查看商品" @click="applyFilter"></u-button></view>
					</view>
				</view>
			</u-popup>

			<view v-if="config.cart.control && config.cart.event === 'cart'" class="cart-bar fixed left-0 right-0 z-20 bg-[#fff]">
				<view class="cart-summary" @click.stop="toCart">
					<view id="animation-end" class="cart-icon">
						<text class="nc-iconfont nc-icon-gouwucheV6mm1 text-[#fff] text-[32rpx]"></text>
					</view>
					<view v-if="totalNum" class="cart-num">{{ totalNum > 99 ? '99+' : totalNum }}</view>
					<text class="cart-label">总计：</text>
					<text class="price-font cart-price">￥{{ parseFloat(totalMoney || 0).toFixed(2) }}</text>
				</view>
				<button class="settle-btn remove-border" :class="{ disabled: parseFloat(totalMoney || 0) <= 0 }" @click="settlement">去结算</button>
			</view>

			<view v-show="animationElStatus" :style="animationElStatus" class="fixed z-999 flex items-center justify-center text-[#fff] bg-color h-[44rpx] w-[44rpx] rounded-[22rpx] text-center">
				<text class="nc-iconfont nc-icon-gouwucheV6xx-2 !text-[30rpx]"></text>
			</view>
		</view>

		<tabbar />
		<bind-mobile ref="bindMobileRef" />
		<mescroll-empty v-if="!tabsData.length && !loading" :option="{ tip: '暂无商品分类' }"></mescroll-empty>
		<loading-page :loading="loading"></loading-page>
	</view>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, onMounted, ref } from 'vue';
import { cloneDeep } from 'lodash-es';
import { img, redirect, getToken } from '@/utils/common';
import { getGoodsCategoryTree, getGoodsPages, getGoodsDetail, getGoodsWarehouses, getGoodsFilterOptions } from '@/addon/phone_shop/api/goods';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import addCartPopup from './add-cart-popup.vue';
import bindMobile from '@/components/bind-mobile/bind-mobile.vue';
import { useLogin } from '@/hooks/useLogin';
import useMemberStore from '@/stores/member';
import useCartStore from '@/addon/phone_shop/stores/cart';
import { useGoodsDownload } from '@/addon/phone_shop/hooks/useGoodsDownload';
import { useGoodsForwardAccess } from '@/addon/phone_shop/hooks/useGoodsForwardAccess';
import DownloadConfigDialog from '@/addon/phone_shop/components/download-config-dialog/download-config-dialog.vue';
import PhoneGoodsMeta from '@/addon/phone_shop/components/PhoneGoodsMeta.vue'
import PhoneGoodsActionButton from '@/addon/phone_shop/components/PhoneGoodsActionButton.vue'
import { resolveGoodsCardAction, goodsPriceBadgeType } from '@/addon/phone_shop/utils/goods-card'
import { useGoodsDetailNavigation } from '@/addon/phone_shop/hooks/useGoodsDetailNavigation'
import GoodsPriceRangePicker from '@/addon/phone_shop/components/goods-filter/GoodsPriceRangePicker.vue'

const prop = defineProps({
	config: {
		type: Object,
		default: () => ({})
	},
	categoryId: {
		type: [String, Number],
		default: 0
	}
})

const config = prop.config;
const goodsAction = computed(() => resolveGoodsCardAction(prop.config));
const { openGoodsDetail } = useGoodsDetailNavigation();
const priceType = (item: any) => goodsPriceBadgeType(item, memberStore.token);
let categoryId: any = prop.categoryId;
const searchName = ref('');
const loading = ref(true);
const listLoading = ref(false);
const goodsLoading = ref(false);
const refreshing = ref(false);
const finished = ref(false);
const tabsData = ref<Array<any>>([]);
const list = ref<Array<any>>([]);
const page = ref(1);
const pageSize = ref(10);
const firstActive = ref(0);
const secondActive = ref(0);
const thirdActive = ref(-1);
const showCategoryPopup = ref(false);
const selectedCategoryId = ref<any>('');
const sortField = ref('');
const sortType = ref<'asc' | 'desc'>('desc');
// 仓库切换(本地仓/代理仓)：''=全部 / 'local'=本地仓 / 'agent'=代理仓
const warehouse = ref('');
const warehouseAgentName = ref('代理仓');
const showWarehouseSwitch = ref(false);
// 内存/成色 筛选
const showFilter = ref(false);
const selectedMemory = ref<string[]>([]);
const selectedGrade = ref<string[]>([]);
const selectedColor = ref<string[]>([]);
const selectedBattery = ref<string[]>([]);
const selectedWarranty = ref<string[]>([]);
const selectedStartPrice = ref<string | number>('');
const selectedEndPrice = ref<string | number>('');
const tmpMemory = ref<string[]>([]);
const tmpGrade = ref<string[]>([]);
const tmpColor = ref<string[]>([]);
const tmpBattery = ref<string[]>([]);
const tmpWarranty = ref<string[]>([]);
const tmpStartPrice = ref<string | number>('');
const tmpEndPrice = ref<string | number>('');
const tmpSortField = ref('');
const tmpSortType = ref<'asc' | 'desc'>('desc');
// 左侧维度 + 三级选择 + 分组展开
const activeDim = ref('category');
const tmpThird = ref<any>(-1);
const expand = ref<Record<string, boolean>>({});
const dims = computed(() => [
	{ key: 'category', label: '分类' },
	{ key: 'memory', label: '内存' },
	{ key: 'grade', label: '成色' },
	{ key: 'color', label: '颜色' },
	{ key: 'device', label: '设备' },
	{ key: 'price', label: '价格' },
	{ key: 'sort', label: '排序' }
]);
const thirdOptions = computed(() => thirdLevelList.value || []);
const categoryInitial = (name: string) => String(name || '类').trim().slice(0, 1);
// 不按苹果/安卓写死：直接消费在售商品的真实值，仅按数据形态分组。
const memoryGroups = computed(() => {
	const storage: string[] = [], combination: string[] = [];
	(filterOptions.value.memories || []).forEach((item: any) => {
		const m = String(item?.value ?? item ?? '').trim();
		if (m) (m.includes('+') ? combination : storage).push(m);
	});
	const g: any[] = [];
	if (storage.length) g.push({ name: '存储容量', items: storage });
	if (combination.length) g.push({ name: '运行内存 + 存储', items: combination });
	return g;
});
const toggleExpand = (k: string) => { expand.value = { ...expand.value, [k]: !expand.value[k] }; }
const dimHasSelected = (key: string) => {
	if (key === 'category') return tmpThird.value !== -1;
	if (key === 'memory') return tmpMemory.value.length > 0;
	if (key === 'grade') return tmpGrade.value.length > 0;
	if (key === 'color') return tmpColor.value.length > 0;
	if (key === 'device') return tmpBattery.value.length > 0 || tmpWarranty.value.length > 0;
	if (key === 'price') return tmpStartPrice.value !== '' || tmpEndPrice.value !== '';
	if (key === 'sort') return !!tmpSortField.value;
	return false;
}
const filterOptions = ref<any>({
	memories: [],
	grades: [],
	colors: [],
	battery_ranges: [],
	warranty_ranges: [],
	price_ranges: []
});
const colorOptions = computed(() => (filterOptions.value.colors || []).map((item: any) => String(item?.value ?? item ?? '')).filter(Boolean));
const batteryOptions = computed(() => filterOptions.value.battery_ranges || []);
const warrantyOptions = computed(() => filterOptions.value.warranty_ranges || []);
const priceRangeOptions = computed(() => filterOptions.value.price_ranges || []);
const activeFilterCount = computed(() => {
	let count = selectedMemory.value.length + selectedGrade.value.length + selectedColor.value.length;
	if (selectedBattery.value.length) count++;
	if (selectedWarranty.value.length) count++;
	if (selectedStartPrice.value !== '' || selectedEndPrice.value !== '') count++;
	if (sortField.value) count++;
	return count;
});
const filterContextText = computed(() => selectedCategoryId.value
	? `已按“${thirdActive.value >= 0 ? thirdLevelList.value[thirdActive.value]?.category_name : selectedSecond.value?.category_name || selectedFirst.value?.category_name || '当前分类'}”收敛可选项`
	: '展示当前在售商品的全部可选项');
const instance = getCurrentInstance();
const menuButtonInfo = ref<any>({});
const systemInfo = ref<any>({});

const cartStore = useCartStore();
cartStore.getList();
const cartList = computed(() => cartStore.cartList);
const totalNum = computed(() => cartStore.totalNum);
const totalMoney = computed(() => cartStore.totalMoney);
const memberStore = useMemberStore();
const userInfo = computed(() => memberStore.info);
const { downloadGoodsImagesWithConfig, needShowConfigDialog, saveConfig } = useGoodsDownload();
const showDownloadConfig = ref(false);
const pendingDownload = ref<any>(null);

const secondLevelList = computed(() => tabsData.value[firstActive.value]?.child_list || []);
const selectedFirst = computed(() => tabsData.value[firstActive.value] || {});
const selectedSecond = computed(() => secondLevelList.value[secondActive.value] || selectedFirst.value);
const thirdLevelList = computed(() => selectedSecond.value?.child_list || []);
const firstCategoryConfig = computed(() => ({
	show_icon: config.first_category?.show_icon ?? 1,
	style: config.first_category?.style || 'pill',
	icon_size: config.first_category?.icon_size || config.first_category?.image_size || 86,
	font_size: config.first_category?.font_size || 24,
	text_color: config.first_category?.text_color || '#333333',
	active_text_color: config.first_category?.active_text_color || '#ffffff',
	active_bg_color: config.first_category?.active_bg_color || config.theme?.primary_color || '',
	item_bg_color: config.first_category?.item_bg_color || '#ffffff'
}));
const secondCategoryConfig = computed(() => ({
	width: config.second_category?.width || 168,
	font_size: config.second_category?.font_size || 24,
	text_color: config.second_category?.text_color || '#30343a',
	active_text_color: config.second_category?.active_text_color || config.theme?.primary_color || '',
	bg_color: config.second_category?.bg_color || '#f4f4f4',
	active_bg_color: config.second_category?.active_bg_color || '#ffffff'
}));
const thirdCategoryConfig = computed(() => ({
	font_size: config.third_category?.font_size || 24,
	text_color: config.third_category?.text_color || '#333333',
	active_text_color: config.third_category?.active_text_color || config.theme?.primary_color || '',
	bg_color: config.third_category?.bg_color || '#f3f3f3',
	active_bg_color: config.third_category?.active_bg_color || '#dff4e5'
}));
const toRpx = (value: any, fallback: number) => {
	const numberValue = Number(value);
	return Number.isFinite(numberValue) && numberValue > 0 ? `${numberValue}rpx` : `${fallback}rpx`;
}
const firstIconSize = computed(() => toRpx(firstCategoryConfig.value.icon_size, 86));
const categoryThemeStyle = computed(() => {
	const primaryColor = config.theme?.primary_color || '#1255e7';
	const priceColor = config.theme?.price_color || config.theme?.price_text_color || '#ff3b30';
	return [
		`--primary-color: ${primaryColor}`,
		`--price-text-color: ${priceColor}`,
		`--first-icon-size: ${firstIconSize.value}`,
		`--first-font-size: ${toRpx(firstCategoryConfig.value.font_size, 24)}`,
		`--first-text-color: ${firstCategoryConfig.value.text_color}`,
		`--first-active-text-color: ${firstCategoryConfig.value.active_text_color}`,
		`--first-active-bg-color: ${firstCategoryConfig.value.active_bg_color || primaryColor}`,
		`--first-item-bg-color: ${firstCategoryConfig.value.item_bg_color}`,
		`--second-width: ${toRpx(secondCategoryConfig.value.width, 168)}`,
		`--second-font-size: ${toRpx(secondCategoryConfig.value.font_size, 24)}`,
		`--second-text-color: ${secondCategoryConfig.value.text_color}`,
		`--second-active-text-color: ${secondCategoryConfig.value.active_text_color || primaryColor}`,
		`--second-bg-color: ${secondCategoryConfig.value.bg_color}`,
		`--second-active-bg-color: ${secondCategoryConfig.value.active_bg_color}`,
		`--third-font-size: ${toRpx(thirdCategoryConfig.value.font_size, 24)}`,
		`--third-text-color: ${thirdCategoryConfig.value.text_color}`,
		`--third-active-text-color: ${thirdCategoryConfig.value.active_text_color || primaryColor}`,
		`--third-bg-color: ${thirdCategoryConfig.value.bg_color}`,
		`--third-active-bg-color: ${thirdCategoryConfig.value.active_bg_color}`
	].join(';') + ';';
});
const sortOptionMap: Record<string, { key: string, label: string, field: string }> = {
	default: { key: 'default', label: '综合', field: '' },
	price: { key: 'price', label: '价格', field: 'price' },
// 	stock: { key: 'stock', label: '库存', field: 'stock' },
	create_time: { key: 'create_time', label: '最新', field: 'create_time' },
	sale_num: { key: 'sale_num', label: '销量', field: 'sale_num' }
};
const enabledSortOptions = computed(() => {
	const options = Array.isArray(config.sort_options) && config.sort_options.length ? config.sort_options : ['default', 'price', 'stock', 'create_time'];
	return options.map((key: string) => sortOptionMap[key]).filter(Boolean);
});
const customNavbarTop = computed(() => {
	// #ifdef MP
	if (menuButtonInfo.value && menuButtonInfo.value.top) return menuButtonInfo.value.top;
	// #endif
	// #ifdef APP-PLUS
	if (systemInfo.value && systemInfo.value.statusBarHeight) return systemInfo.value.statusBarHeight;
	// #endif
	return 0;
});
const headerSearchHeight = computed(() => {
	// #ifdef MP
	if (menuButtonInfo.value && menuButtonInfo.value.height) return `${menuButtonInfo.value.height}px`;
	// #endif
	return '64rpx';
});
const headerStyle = computed(() => {
	return `padding-top: calc(${customNavbarTop.value}px + 10rpx);`;
});
const headerSearchStyle = computed(() => {
	let style = `height: ${headerSearchHeight.value};`;
	// #ifdef MP
	if (menuButtonInfo.value && menuButtonInfo.value.left && systemInfo.value && systemInfo.value.windowWidth) {
		style += `padding-right: ${systemInfo.value.windowWidth - menuButtonInfo.value.left + 8}px;`;
	}
	// #endif
	return style;
});
const contentStyle = computed(() => {
	const firstCategoryHeight = firstCategoryConfig.value.show_icon ? (config.search.control ? 118 : 98) : (config.search.control ? 92 : 72);
	return `top: calc(${customNavbarTop.value}px + ${headerSearchHeight.value} + ${firstCategoryHeight}rpx);`;
});
// const deliveryDate = computed(() => {
// 	const date = new Date();
// 	date.setDate(date.getDate() + 2);
// 	const month = `${date.getMonth() + 1}`.padStart(2, '0');
// 	const day = `${date.getDate()}`.padStart(2, '0');
// 	return `${date.getFullYear()}-${month}-${day}`;
// });

onMounted(() => {
	initCustomHeader();
	initDefaultSort();
	getCategoryData();
	initWarehouseSwitch();
});

// 仓库切换:仅当后台分类配置开启 warehouse_switch 且本站确为有代理货的子站时显示
const initWarehouseSwitch = () => {
	if (!config.warehouse_switch) return;
	getGoodsWarehouses().then((res: any) => {
		const d = res.data || {};
		showWarehouseSwitch.value = Number(d.show) === 1;
		if (d.agent_name) warehouseAgentName.value = d.agent_name;
	}).catch(() => {});
}

const switchWarehouse = (val: string) => {
	if (warehouse.value === val) return;
	warehouse.value = val;
	loadGoods(true);
}

const initCustomHeader = () => {
	systemInfo.value = uni.getSystemInfoSync();
	// #ifdef MP
	menuButtonInfo.value = uni.getMenuButtonBoundingClientRect();
	// #endif
}

const getCategoryData = () => {
	loading.value = true;
	getGoodsCategoryTree().then((res: any) => {
		tabsData.value = res.data || [];
		if (tabsData.value.length) {
			if (categoryId) setActiveByCategoryId(categoryId);
			resetSelectedCategory();
			loadFilterOptions();
			loadGoods(true);
		}
		loading.value = false;
	}).catch(() => {
		loading.value = false;
	});
}

const setActiveByCategoryId = (id: any) => {
	for (let i = 0; i < tabsData.value.length; i++) {
		const first = tabsData.value[i];
		if (first.category_id == id) {
			firstActive.value = i;
			secondActive.value = 0;
			thirdActive.value = -1;
			return;
		}
		const secondIndex = (first.child_list || []).findIndex((item: any) => item.category_id == id || hasCategory(item.child_list || [], id));
		if (secondIndex >= 0) {
			firstActive.value = i;
			secondActive.value = secondIndex;
			const thirdIndex = (first.child_list[secondIndex].child_list || []).findIndex((item: any) => item.category_id == id);
			thirdActive.value = thirdIndex >= 0 ? thirdIndex : -1;
			return;
		}
	}
}

const hasCategory = (data: any[], id: any) => {
	return data.some((item: any) => item.category_id == id || hasCategory(item.child_list || [], id));
}

const resetSelectedCategory = () => {
	const second = selectedSecond.value;
	if (thirdActive.value >= 0 && thirdLevelList.value[thirdActive.value]) {
		selectedCategoryId.value = thirdLevelList.value[thirdActive.value].category_id;
	} else {
		selectedCategoryId.value = second?.category_id || selectedFirst.value?.category_id || '';
		thirdActive.value = -1;
	}
	categoryId = selectedCategoryId.value;
}

const resetGoods = () => {
	resetSelectedCategory();
	resetCategoryFacets();
	loadFilterOptions();
	loadGoods(true);
}

const resetCategoryFacets = () => {
	selectedMemory.value = [];
	selectedGrade.value = [];
	selectedColor.value = [];
	selectedBattery.value = [];
	selectedWarranty.value = [];
}

const firstLevelClick = (index: number) => {
	if (firstActive.value == index) return;
	firstActive.value = index;
	secondActive.value = 0;
	thirdActive.value = -1;
	resetGoods();
}

const secondLevelClick = (index: number) => {
	if (secondActive.value == index && thirdActive.value === -1) return;
	secondActive.value = index;
	thirdActive.value = -1;
	resetGoods();
}

const thirdLevelClick = (index: number, data: any) => {
	thirdActive.value = index;
	selectedCategoryId.value = data?.category_id || selectedSecond.value?.category_id || '';
	categoryId = selectedCategoryId.value;
	resetCategoryFacets();
	loadFilterOptions();
	loadGoods(true);
}

const loadGoods = (reset = false) => {
	if (goodsLoading.value) return;
	if (!reset && finished.value) return;
	if (reset) {
		page.value = 1;
		finished.value = false;
		list.value = [];
		listLoading.value = false;
	}
	goodsLoading.value = true;
	listLoading.value = false;
	getGoodsPages({
		page: page.value,
		limit: pageSize.value,
		goods_category: selectedCategoryId.value,
		goods_name: '',
		order: sortField.value,
		sort: sortType.value,
		memory_group: selectedMemory.value.join(','),
		condition_grade: selectedGrade.value.join(','),
		device_color: selectedColor.value.join(','),
		battery_range: selectedBattery.value.join(','),
		warranty_range: selectedWarranty.value.join(','),
		start_price: selectedStartPrice.value,
		end_price: selectedEndPrice.value,
		warehouse: warehouse.value
	}).then((res: any) => {
		const newArr = res.data.data || [];
		list.value = reset ? newArr : list.value.concat(newArr);
		finished.value = newArr.length < pageSize.value;
		if (!finished.value) page.value += 1;
		loading.value = false;
		if (!list.value.length) listLoading.value = true;
	}).catch(() => {
		loading.value = false;
		listLoading.value = true;
		uni.showToast({ title: '商品加载失败', icon: 'none' });
	}).finally(() => {
		goodsLoading.value = false;
		refreshing.value = false;
	});
}

const loadMoreGoods = () => {
	loadGoods(false);
}

const refreshGoods = () => {
	refreshing.value = true;
	loadGoods(true);
}

const selectFirstFromPopup = (index: number) => {
	showCategoryPopup.value = false;
	firstLevelClick(index);
}

const searchNameFn = () => {
	if (searchName.value) redirect({ url: '/addon/phone_shop/pages/goods/list', param: { goods_name: encodeURIComponent(searchName.value) } });
}

const changeSort = (field: string) => {
	if (!field) {
		sortField.value = '';
		sortType.value = 'desc';
		loadGoods(true);
		return;
	}
	if (sortField.value === field) {
		sortType.value = sortType.value === 'asc' ? 'desc' : 'asc';
	} else {
		sortField.value = field;
		sortType.value = 'desc';
	}
	loadGoods(true);
}

const initDefaultSort = () => {
	const sortValue = config.sort || 'default';
	const sortMap: Record<string, { field: string, type: 'asc' | 'desc' }> = {
		price_asc: { field: 'price', type: 'asc' },
		price_desc: { field: 'price', type: 'desc' },
		stock_desc: { field: 'stock', type: 'desc' },
		create_time_desc: { field: 'create_time', type: 'desc' }
	};
	const sort = sortMap[sortValue];
	if (!sort) return;
	sortField.value = sort.field;
	sortType.value = sort.type;
}

// 筛选项由后端按当前分类的真实在售商品动态返回，避免从首屏商品推断造成缺项。
const gradeOptions = computed(() => (filterOptions.value.grades || []).map((item: any) => String(item?.grade_name ?? item ?? '')).filter(Boolean));
let filterOptionRequestId = 0;
const loadFilterOptions = async(categoryValue: any = selectedCategoryId.value) => {
	const requestId = ++filterOptionRequestId;
	try {
		const res: any = await getGoodsFilterOptions({ goods_category: categoryValue || '' });
		if (requestId !== filterOptionRequestId) return;
		filterOptions.value = Object.assign({
			memories: [], grades: [], colors: [], battery_ranges: [], warranty_ranges: [], price_ranges: []
		}, res.data || {});
	} catch (e) {
		if (requestId !== filterOptionRequestId) return;
		filterOptions.value = { memories: [], grades: [], colors: [], battery_ranges: [], warranty_ranges: [], price_ranges: [] };
	}
}
const openFilter = () => {
	loadFilterOptions();
	tmpMemory.value = [...selectedMemory.value];
	tmpGrade.value = [...selectedGrade.value];
	tmpColor.value = [...selectedColor.value];
	tmpBattery.value = [...selectedBattery.value];
	tmpWarranty.value = [...selectedWarranty.value];
	tmpStartPrice.value = selectedStartPrice.value;
	tmpEndPrice.value = selectedEndPrice.value;
	tmpSortField.value = sortField.value;
	tmpSortType.value = sortType.value;
	tmpThird.value = (thirdActive.value >= 0 && thirdLevelList.value[thirdActive.value]) ? thirdLevelList.value[thirdActive.value].category_id : -1;
	activeDim.value = thirdOptions.value.length ? 'category' : 'memory';
	showFilter.value = true;
}
const closeFilter = () => {
	showFilter.value = false;
	loadFilterOptions();
}
const chooseTmpThird = (categoryValue: any) => {
	if (tmpThird.value === categoryValue) return;
	tmpThird.value = categoryValue;
	tmpMemory.value = [];
	tmpGrade.value = [];
	tmpColor.value = [];
	tmpBattery.value = [];
	tmpWarranty.value = [];
	const effectiveCategory = categoryValue === -1
		? (selectedSecond.value?.category_id || selectedFirst.value?.category_id || '')
		: categoryValue;
	loadFilterOptions(effectiveCategory);
}
// 弹窗内选排序:综合(无field)清空;同字段再点切换升降
const pickSort = (opt: any) => {
	if (!opt.field) { tmpSortField.value = ''; tmpSortType.value = 'desc'; return; }
	if (tmpSortField.value === opt.field) tmpSortType.value = tmpSortType.value === 'asc' ? 'desc' : 'asc';
	else { tmpSortField.value = opt.field; tmpSortType.value = 'desc'; }
}
const toggleChip = (arr: string[], val: string) => {
	const i = arr.indexOf(val);
	if (i >= 0) arr.splice(i, 1); else arr.push(val);
}
const applyFilter = () => {
	const previousCategory = selectedCategoryId.value;
	// 三级分类
	if (tmpThird.value === -1) {
		thirdActive.value = -1;
		selectedCategoryId.value = selectedSecond.value?.category_id || selectedFirst.value?.category_id || '';
	} else {
		const idx = thirdLevelList.value.findIndex((c: any) => c.category_id === tmpThird.value);
		thirdActive.value = idx;
		selectedCategoryId.value = tmpThird.value;
	}
	categoryId = selectedCategoryId.value;
	selectedMemory.value = [...tmpMemory.value];
	selectedGrade.value = [...tmpGrade.value];
	selectedColor.value = [...tmpColor.value];
	selectedBattery.value = [...tmpBattery.value];
	selectedWarranty.value = [...tmpWarranty.value];
	selectedStartPrice.value = tmpStartPrice.value;
	selectedEndPrice.value = tmpEndPrice.value;
	sortField.value = tmpSortField.value;
	sortType.value = tmpSortType.value;
	showFilter.value = false;
	if (previousCategory !== selectedCategoryId.value) loadFilterOptions();
	loadGoods(true);
}
const resetFilter = () => {
	tmpThird.value = -1;
	tmpMemory.value = [];
	tmpGrade.value = [];
	tmpColor.value = [];
	tmpBattery.value = [];
	tmpWarranty.value = [];
	tmpStartPrice.value = '';
	tmpEndPrice.value = '';
	tmpSortField.value = '';
	tmpSortType.value = 'desc';
}

const toGoodsDetail = (goods_id: string) => {
	return openGoodsDetail(goods_id, prop.config);
}

const cartRef = ref();
const animationElStatus = ref('');
const animationAddRepeatFlag = ref(false);
const cartRepeatFlag = ref(false);

const itemCart = (row: any, id: any) => {
	if (!goodsAction.value) return;
	if (goodsAction.value.event === 'download') return downloadCategoryGoods(row);
	if (row.goods_type == 'virtual' && row.virtual_receive_type == 'verify') return toGoodsDetail(row.goodsSku.goods_id);
	if (goodsAction.value.event !== 'cart') return toGoodsDetail(row.goods_id);
	if (!memberStore.token) {
		useLogin().setLoginBack({ url: '/addon/phone_shop/pages/goods/category' });
		return false;
	}
	if (row.goodsSku.sku_spec_format) {
		cartRef.value.open(row.goodsSku.sku_id);
	} else {
		if (parseInt(row.goodsSku.num || 0) >= parseInt(row.goodsSku.stock)) {
			uni.showToast({ title: '商品库存不足', icon: 'none' });
			return;
		}
		animationAddCart(row, id);
	}
}

const downloadCategoryGoods = async (row: any) => {
	try {
		const allowed = await useGoodsForwardAccess().ensureGoodsForwardAccess('/addon/phone_shop/pages/goods/category');
		if (!allowed) return false;
		const res: any = await getGoodsDetail({ goods_id: row.goods_id });
		if (!res.data?.goods) {
			uni.showToast({ title: '商品信息获取失败', icon: 'none' });
			return false;
		}
		const images = res.data.goods.goods_image ? String(res.data.goods.goods_image).split(',').filter(Boolean) : [];
		if (row.goods_cover_thumb_mid) images.push(row.goods_cover_thumb_mid);
		const uniqImages = [...new Set(images)];
			const item = res.data; // 用详情数据(含顶层 show_price),价格口径一致
			if (needShowConfigDialog()) {
				pendingDownload.value = { images: uniqImages, item };
				showDownloadConfig.value = true;
				return false;
			}
			return downloadGoodsImagesWithConfig(uniqImages, item, undefined, userInfo.value?.member_id);
	} catch (e) {
		uni.showToast({ title: '商品信息获取失败', icon: 'none' });
		return false;
	}
}

const onDownloadConfigConfirm = async (cfg: any) => {
	saveConfig(cfg);
	showDownloadConfig.value = false;
	if (pendingDownload.value) {
		await downloadGoodsImagesWithConfig(pendingDownload.value.images, pendingDownload.value.item, cfg, userInfo.value?.member_id);
		pendingDownload.value = null;
	}
}

const addCartBtn = (item: any, row: any, id: string) => {
	if (parseInt(row.num) >= parseInt(row.stock)) {
		uni.showToast({ title: '商品库存不足', icon: 'none' });
		return;
	}
	const obj = cloneDeep(item);
	obj.num = row.num;
	obj.id = row.id;
	animationAddCart(obj, id);
}

const reduceCart = (row: any) => {
	if (cartRepeatFlag.value) return false;
	cartRepeatFlag.value = true;
	cartStore.reduce({
		id: row.id,
		goods_id: row.goods_id,
		sku_id: row.sku_id,
		stock: row.stock,
		sale_price: row.sale_price,
		num: row.num
	}, 1, () => {
		cartRepeatFlag.value = false;
	});
}

const animationAddCart = (row: any, id: any) => {
	if (animationAddRepeatFlag.value || cartRepeatFlag.value) return false;
	animationAddRepeatFlag.value = true;
	cartRepeatFlag.value = true;

	const obj: any = {
		goods_id: row.goodsSku.goods_id,
		sku_id: row.goodsSku.sku_id,
		sale_price: goodsPrice(row),
		stock: row.goodsSku.stock
	};
	if (row.id) {
		obj.num = row.num;
		obj.id = row.id;
	}
	cartStore.increase(obj, 1, () => {
		cartRepeatFlag.value = false;
	});

	// #ifdef MP-WEIXIN
	setTimeout(() => {
		uni.createSelectorQuery().in(instance).select('#animation-end').boundingClientRect((res: any) => {
			uni.createSelectorQuery().in(instance).select('#' + id).boundingClientRect((position: any) => {
				if (!res || !position) {
					animationAddRepeatFlag.value = false;
					return;
				}
				animationElStatus.value = `top: ${position.top}px; left: ${position.left}px;`;
				setTimeout(() => {
					animationElStatus.value = `top: ${res.top + res.height / 2 - position.height / 2}px; left: ${res.left + res.width / 2 - position.width / 2}px; transition: all 0.8s; transform: rotate(-720deg);`;
				}, 20);
				setTimeout(() => {
					animationElStatus.value = '';
					animationAddRepeatFlag.value = false;
				}, 1020);
			}).exec();
		}).exec();
	}, 100);
	// #endif
	// #ifdef H5
	setTimeout(() => {
		const animationEnd: any = window.document.getElementById('animation-end');
		const itemCart: any = window.document.getElementById(id);
		if (!animationEnd || !itemCart) {
			animationAddRepeatFlag.value = false;
			return;
		}
		const animationEndLeft = animationEnd.getBoundingClientRect().left;
		const animationEndTop = animationEnd.getBoundingClientRect().top;
		const itemCartLeft = itemCart.getBoundingClientRect().left;
		const itemCartTop = itemCart.getBoundingClientRect().top;
		animationElStatus.value = `top: ${itemCartTop}px; left: ${itemCartLeft}px;`;
		setTimeout(() => {
			animationElStatus.value = `top: ${animationEndTop + animationEnd.offsetHeight / 2 - itemCart.offsetHeight / 2}px; left: ${animationEndLeft + animationEnd.offsetWidth / 2 - itemCart.offsetHeight / 2}px; transition: all 0.8s; transform: rotate(-720deg);`;
		}, 20);
		setTimeout(() => {
			animationElStatus.value = '';
			animationAddRepeatFlag.value = false;
		}, 1020);
	}, 100);
	// #endif
}

const toCart = () => {
	redirect({ url: '/addon/phone_shop/pages/goods/cart' });
}

const bindMobileRef: any = ref(null);
const settlement = () => {
	// #ifdef H5
	if (uni.getStorageSync('isbindmobile')) {
		bindMobileRef.value.open();
		return false;
	}
	// #endif

	if (!totalNum.value) {
		uni.showToast({ title: '还没有选择商品', icon: 'none' });
		return;
	}
	const cart_ids: any = [];
	Object.values(cartList.value).forEach((item: any) => {
		Object.keys(item).forEach(v => {
			if (v != 'totalNum' && v != 'totalMoney') cart_ids.push(item[v].id);
		});
	});
	if (!cart_ids.length) return;
	uni.setStorage({
		key: 'orderCreateData',
		data: { cart_ids },
		success() {
			redirect({ url: '/addon/phone_shop/pages/order/payment' });
		}
	});
}

// 统一用后端算好的 show_price(含会员价/活动价/level_no 口径),多规格无 goodsSku 时兜底
const goodsPrice = (data: any) => {
	const sku = data.goodsSku || {};
	if (sku.show_price !== undefined && sku.show_price !== null && sku.show_price !== '') return sku.show_price;
	if (data.show_price !== undefined && data.show_price !== null && data.show_price !== '') return data.show_price;
	return sku.price || data.price || 0;
}

// 质检异常项数(从 qc_report.severity_summary 取),无则 0
const qcAbnormal = (data: any) => {
	try {
		const raw = data.qc_report || (data.goods && data.goods.qc_report);
		if (!raw) return 0;
		const qc = typeof raw === 'string' ? JSON.parse(raw) : raw;
		return Number(qc?.severity_summary?.abnormal || 0) || 0;
	} catch (e) { return 0; }
}
</script>

<style lang="scss" scoped>
.remove-border::after {
	border: none;
}

.bg-color {
	background-color: var(--primary-color);
}

.category-header {
	padding: 10rpx 20rpx;
	box-sizing: border-box;
	background: rgba(255, 255, 255, 0.98) !important;
	border-bottom: 2rpx solid #eef1f5;
	box-shadow: 0 6rpx 18rpx rgba(32, 41, 57, 0.035);
}

.header-search {
	display: flex;
	align-items: center;
	height: 72rpx;
	gap: 18rpx;
}

.page-title {
	flex-shrink: 0;
	font-size: 32rpx;
	line-height: 46rpx;
	font-weight: 600;
	color: #2f3238;
}

.search-input {
	min-width: 0;
	height: 64rpx;
	flex: 1;
	border: 2rpx solid #e2e7ef;
	border-radius: 34rpx;
	background-color: #f6f8fb;
	display: flex;
	align-items: center;
	padding: 0 22rpx;
	box-sizing: border-box;
}

.search-input .btn {
	color: #a9adb3;
	font-size: 34rpx;
	margin-right: 12rpx;
}

.search-input .input {
	flex: 1;
	min-width: 0;
	height: 60rpx;
	font-size: 27rpx;
	color: #333;
}

.search-input .clear {
	color: #a9adb3;
	font-size: 28rpx;
	margin-left: 10rpx;
}

.first-scroll {
	flex: 1;
	min-width: 0;
	height: 82rpx;
	margin-top: 10rpx;
	white-space: nowrap;
}

.first-scroll.is-text {
	height: 70rpx;
	margin-top: 8rpx;
}

.first-list {
	display: inline-flex;
	align-items: center;
	gap: 12rpx;
	min-width: 100%;
}

.first-category-row {
	display: flex;
	align-items: center;
	gap: 10rpx;
}

.first-item {
	width: auto;
	min-width: 106rpx;
	height: 68rpx;
	padding: 0 18rpx 0 8rpx;
	display: flex;
	flex-direction: row;
	align-items: center;
	justify-content: center;
	gap: 10rpx;
	border: 2rpx solid transparent;
	border-radius: 34rpx;
	background: #f5f7fa;
	box-sizing: border-box;
}

.first-item.no-icon {
	height: 58rpx;
	padding: 0 22rpx;
	justify-content: center;
}

.first-item.no-icon .first-text {
	max-width: 160rpx;
	height: auto;
	line-height: 36rpx;
	margin-top: 0;
	padding: 0;
	border-radius: 0;
	background-color: transparent;
}

.first-img {
	width: 52rpx;
	height: 52rpx;
	padding: 0;
	border-radius: 14rpx;
	box-sizing: border-box;
	display: flex;
	align-items: center;
	justify-content: center;
	background: linear-gradient(145deg, #eef4ff, #e6edff);
	overflow: hidden;
}

.first-img-fallback {
	width: 52rpx;
	height: 52rpx;
	border-radius: 14rpx;
}

.first-img-letter {
	width: 52rpx;
	height: 52rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 14rpx;
	color: var(--primary-color);
	background: linear-gradient(145deg, #eef4ff, #e5edff);
	font-size: 24rpx;
	font-weight: 700;
}

.first-text {
	max-width: 150rpx;
	height: 38rpx;
	line-height: 38rpx;
	margin-top: 0;
	padding: 0;
	border-radius: 0;
	font-size: var(--first-font-size);
	color: var(--first-text-color);
	text-align: center;
	box-sizing: border-box;
}

.first-item.active .first-img {
	box-shadow: 0 0 0 2rpx rgba(18, 85, 231, 0.12);
}

.first-item.active {
	border-color: var(--primary-color);
	background: #fff;
	box-shadow: 0 6rpx 16rpx rgba(18, 85, 231, 0.09);
}

.first-item.active .first-text {
	color: var(--primary-color);
	background-color: transparent;
	font-weight: 600;
}

.more-category-btn {
	flex-shrink: 0;
	width: 76rpx;
	height: 64rpx;
	padding: 0;
	margin-top: 10rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 2rpx;
	border-radius: 20rpx;
	background: #f2f5fa;
	box-shadow: none;
	color: var(--primary-color);
	font-weight: 600;
}

.first-scroll.is-text + .more-category-btn {
	margin-top: 8rpx;
	height: 58rpx;
	flex-direction: row;
	width: 92rpx;
	gap: 4rpx;
}

.more-category-icon {
	font-size: 26rpx;
	line-height: 1;
}

.more-category-text {
	font-size: 19rpx;
	line-height: 24rpx;
}

.content {
	top: 244rpx;
	display: flex;
	background-color: #f5f7fa;
}

.content.has-cart {
	bottom: 100rpx;
}

.second-panel {
	width: var(--second-width);
	height: 100%;
	background-color: #f4f6f9;
	border-right: 2rpx solid #edf0f4;
	flex-shrink: 0;
}
.second-panel scroll-view {
  padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
  box-sizing: border-box;
}

.second-item {
	min-height: 96rpx;
	padding: 0 18rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	color: var(--second-text-color);
	font-size: var(--second-font-size);
	line-height: 38rpx;
	text-align: center;
}

.second-item.active {
	background-color: var(--second-active-bg-color);
	color: var(--second-active-text-color);
	font-weight: 700;
	position: relative;
}

.second-item.active::before {
	content: '';
	position: absolute;
	left: 0;
	top: 28rpx;
	width: 7rpx;
	height: 40rpx;
	border-radius: 0 6rpx 6rpx 0;
	background-color: var(--primary-color);
}

.second-text {
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.goods-panel {
	flex: 1;
	min-width: 0;
	height: 100%;
	background-color: #fff;
	display: flex;
	flex-direction: column;
}

.goods-scroll {
	flex: 1;
	min-height: 0;
	height: 100%;
	background-color: #fff;
}

/* 三级行:横滑分类 + 右侧筛选按钮(带左阴影分隔,体面些) */
.warehouse-bar {
	display: flex;
	align-items: center;
	gap: 16rpx;
	padding: 12rpx 8rpx 0;

	.wh-tab {
		padding: 8rpx 28rpx;
		font-size: 24rpx;
		color: #5b6b7a;
		background: #f4f4f4;
		border-radius: 28rpx;
		line-height: 1.4;

		&.on {
			color: #fff;
			background: var(--primary-color, #1255e7);
		}
	}
}

.third-row {
	position: sticky;
	top: 0;
	z-index: 6;
	display: flex;
	align-items: center;
	height: 78rpx;
	margin-top: 0;
	border-bottom: 2rpx solid #f0f0f0;
	background-color: #fff;
}

.third-scroll {
	flex: 1;
	min-width: 0;
	height: 78rpx;
	white-space: nowrap;
}

.third-scroll-fill {
	flex: 1;
}

.third-filter-btn {
	flex-shrink: 0;
	display: flex;
	align-items: center;
	gap: 6rpx;
	height: 78rpx;
	padding: 0 24rpx 0 22rpx;
	color: #4e5969;
	font-size: 24rpx;
	background-color: #fff;
	box-shadow: -16rpx 0 18rpx -6rpx rgba(255, 255, 255, 0.95);
	position: relative;
}

.third-filter-btn::before {
	content: '';
	position: absolute;
	left: 0;
	top: 22rpx;
	bottom: 22rpx;
	width: 2rpx;
	background: #eee;
}

.third-filter-btn.active {
	color: var(--primary-color);
	font-weight: 600;
}

.third-filter-count {
	min-width: 28rpx;
	height: 28rpx;
	padding: 0 7rpx;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	border-radius: 14rpx;
	background: var(--primary-color);
	color: #fff;
	font-size: 18rpx;
}

.third-filter-icon {
	font-size: 26rpx;
}

.third-list {
	display: inline-flex;
	align-items: center;
	min-width: 100%;
	height: 78rpx;
	padding: 0 18rpx;
	box-sizing: border-box;
	gap: 16rpx;
}

.third-item {
	height: 48rpx;
	line-height: 48rpx;
	padding: 0 20rpx;
	border-radius: 24rpx;
	background-color: var(--third-bg-color);
	color: var(--third-text-color);
	font-size: var(--third-font-size);
	white-space: nowrap;
}

.third-item.active {
	background-color: var(--third-active-bg-color);
	color: var(--third-active-text-color);
	font-weight: 600;
}

.filter-row {
	position: sticky;
	top: 0;
	z-index: 6;
	height: 64rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 24rpx;
	border-bottom: 2rpx solid #f0f0f0;
	box-sizing: border-box;
	color: #8f9399;
	font-size: 24rpx;
	background-color: rgba(255, 255, 255, 0.96);
	backdrop-filter: blur(12rpx);
}

.filter-row.no-third {
	margin-top: 12rpx;
}

.filter-left {
	display: flex;
	align-items: center;
}

.filter-item {
	display: flex;
	align-items: center;
	color: #8f9399;
	height: 52rpx;
}

.filter-item.active {
	color: var(--primary-color);
	font-weight: 600;
}

.sort-icon {
	margin-left: 6rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 0;
	width: 20rpx;
	height: 28rpx;
	color: #b7bcc4;
}

.sort-icon .nc-iconfont {
	height: 13rpx;
	line-height: 13rpx;
	font-size: 16rpx;
	transform: scale(0.82);
}

.sort-icon .on {
	color: var(--primary-color);
	font-weight: 700;
}

.filter-mode {
	color: #333;
	font-size: 28rpx;
}

.filter-screen {
	display: flex;
	align-items: center;
	gap: 6rpx;
	color: #8f9399;
	font-size: 24rpx;
}

.filter-screen.active {
	color: var(--primary-color);
	font-weight: 600;
}

.filter-screen-icon {
	font-size: 26rpx;
}

/* 商业化筛选弹层(uView u-popup 接管容器/动画/圆角/安全区) */
.filter-sheet {
	display: flex;
	flex-direction: column;
	background: #fff;
}

.filter-sheet-head {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 12rpx;
	padding: 30rpx 30rpx 8rpx;
}

.filter-sheet-title {
	font-size: 32rpx;
	font-weight: 700;
	color: #1d2129;
}

.filter-sheet-count {
	font-size: 22rpx;
	color: var(--primary-color);
}

.filter-sheet-scroll {
	flex: 1;
	min-height: 200rpx;
	max-height: 52vh;
	padding: 8rpx 30rpx 20rpx;
	box-sizing: border-box;
}

.filter-group {
	margin-top: 24rpx;
}

.filter-group-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 18rpx;
}

.filter-group-title {
	font-size: 27rpx;
	font-weight: 600;
	color: #1d2129;
}

.filter-group-clear {
	font-size: 22rpx;
	color: #9aa0aa;
}

.filter-chip-wrap {
	display: flex;
	flex-wrap: wrap;
	gap: 18rpx;
}

.filter-chip {
	display: flex;
	align-items: center;
	gap: 6rpx;
	min-width: 150rpx;
	height: 64rpx;
	padding: 0 26rpx;
	border-radius: 12rpx;
	background: #f5f6f8;
	border: 2rpx solid transparent;
	color: #4e5969;
	font-size: 25rpx;
	box-sizing: border-box;
	justify-content: center;
}

.filter-chip.on {
	background: var(--primary-color-light, rgba(18, 85, 231, 0.08));
	border-color: var(--primary-color);
	color: var(--primary-color);
	font-weight: 600;
}

.filter-chip-check {
	font-size: 22rpx;
}

.filter-empty {
	padding: 80rpx 0;
	text-align: center;
	color: #9aa0aa;
	font-size: 24rpx;
}

.filter-sheet-foot {
	display: flex;
	gap: 20rpx;
	padding: 16rpx 30rpx 24rpx;
	border-top: 2rpx solid #f3f4f6;
	background: #fff;
}

.filter-u-btn {
	flex: 1;
}

.goods-list {
	padding: 12rpx 12rpx 24rpx;
	box-sizing: border-box;
}

.goods-item {
	display: flex;
	padding: 18rpx;
	margin-bottom: 12rpx;
	box-sizing: border-box;
	background-color: #fff;
	border: 2rpx solid #eef1f5;
	border-radius: 16rpx;
	box-shadow: 0 4rpx 14rpx rgba(31, 41, 55, 0.035);
}

.goods-img {
	position: relative;
	width: 180rpx;
	height: 180rpx;
	flex-shrink: 0;
	border-radius: 14rpx;
	overflow: hidden;
	background-color: #fafafa;
}

.quality-badge {
	position: absolute;
	z-index: 2;
	top: 10rpx;
	max-width: 126rpx;
	height: 34rpx;
	padding: 0 10rpx;
	border: 2rpx solid rgba(255, 255, 255, 0.9);
	border-radius: 8rpx;
	box-sizing: border-box;
	color: #fff;
	font-size: 20rpx;
	font-weight: 600;
	line-height: 30rpx;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	box-shadow: 0 3rpx 10rpx rgba(15, 23, 42, 0.18);
}

.quality-badge--grade {
	left: 10rpx;
	max-width: 96rpx;
	background: rgba(30, 41, 59, 0.86);
}

.quality-badge--warning {
	right: 10rpx;
	max-width: 76rpx;
	padding: 0 8rpx;
	color: #b42318;
	background: rgba(255, 242, 240, 0.96);
}

.goods-info {
	min-width: 0;
	flex: 1;
	margin-left: 20rpx;
	display: flex;
	flex-direction: column;
}

.goods-name {
  min-height: 42rpx;
  max-height: 42rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: block;
}

.goods-tags {
	margin-top: 6rpx;
	display: flex;
	align-items: center;
	color: #9aa0aa;
	font-size: 22rpx;
	line-height: 30rpx;
	overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* 用 flex order 把"内存/等级"行提到名称正下方,不改 DOM 顺序 */
.goods-name { order: -2; }
.device-row { order: -1; flex-wrap: nowrap; white-space: nowrap; }

.goods-tags .sep {
	margin: 0 10rpx;
}

.device-row {
	margin-top: 8rpx;
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8rpx;
	overflow: hidden;
}

.device-chip {
	font-size: 20rpx;
	line-height: 30rpx;
	color: #5a6573;
	background: #f2f4f7;
	border-radius: 6rpx;
	padding: 0 10rpx;
}

.device-chip.warn {
	color: #f53f3f;
	background: #fff0f0;
}

.device-imei {
	font-size: 20rpx;
	color: #9aa0aa;
	white-space: nowrap;
}

.goods-date {
	color: #9a9da3;
	font-size: 24rpx;
	line-height: 34rpx;
	margin-top: 4rpx;
}

.goods-bottom {
	margin-top: auto;
	display: flex;
	flex-wrap: wrap;
	gap: 8rpx;
	align-items: flex-end;
	justify-content: space-between;
	min-height: 46rpx;
}

.goods-price {
	display: flex;
	align-items: baseline;
	white-space: nowrap;
	color: var(--price-text-color);
	font-size: 30rpx;
	font-weight: 600;
}

.goods-price .unit {
	font-size: 22rpx;
	margin-right: 2rpx;
}

.price-badge {
	height: 24rpx;
	max-width: 68rpx;
	margin-left: 6rpx;
	flex-shrink: 0;
}

.cart-action {
	margin-left: auto;
	min-width: 44rpx;
	height: 44rpx;
	display: flex;
	align-items: center;
	justify-content: flex-end;
}

.stepper {
	display: flex;
	align-items: center;
	height: 44rpx;
}

.step-icon {
	color: var(--primary-color);
	font-size: 36rpx;
	line-height: 44rpx;
}

.step-icon.add {
	font-size: 34rpx;
}

.step-num {
	min-width: 38rpx;
	padding: 0 8rpx;
	text-align: center;
	font-size: 24rpx;
	color: #333;
}

.category-mask {
	position: fixed;
	left: 0;
	right: 0;
	top: 0;
	bottom: 0;
	z-index: 80;
	background: rgba(15, 23, 42, 0.42);
	display: flex;
	align-items: flex-end;
}

.category-popup {
	width: 100%;
	max-height: 72vh;
	display: flex;
	flex-direction: column;
	padding: 26rpx 24rpx calc(32rpx + env(safe-area-inset-bottom));
	border-radius: 28rpx 28rpx 0 0;
	background: #fff;
	box-sizing: border-box;
	box-shadow: 0 -18rpx 50rpx rgba(15, 23, 42, 0.18);
}

.category-popup-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 22rpx;
}

.category-popup-title {
	font-size: 32rpx;
	font-weight: 700;
	color: #111827;
}

.category-popup-desc {
	margin-top: 4rpx;
	font-size: 23rpx;
	color: #8f9399;
}

.category-popup-close {
	width: 56rpx;
	height: 56rpx;
	line-height: 56rpx;
	border-radius: 50%;
	text-align: center;
	color: #64748b;
	background: #f3f4f6;
	font-size: 26rpx;
}

.category-popup-scroll {
	height: 56vh;
	min-height: 360rpx;
}

.category-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 18rpx;
}

.category-grid-item {
	min-height: 118rpx;
	padding: 18rpx 8rpx;
	border: 2rpx solid #f1f5f9;
	border-radius: 18rpx;
	background: #f8fafc;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
}

.category-grid-item.active {
	border-color: var(--primary-color);
	background: rgba(31, 143, 77, 0.08);
	color: var(--primary-color);
}

.category-grid-img {
	width: 58rpx;
	height: 58rpx;
	margin-bottom: 8rpx;
	border-radius: 50%;
	background: #fff;
}

.category-grid-text {
	max-width: 100%;
	font-size: 23rpx;
	line-height: 30rpx;
	text-align: center;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.goods-skeleton {
	padding: 16rpx 24rpx 24rpx;
}

.skeleton-item {
	display: flex;
	padding: 20rpx 0;
}

.skeleton-img,
.skeleton-line {
	background: linear-gradient(90deg, #f2f4f7 25%, #e8ebef 37%, #f2f4f7 63%);
	background-size: 400% 100%;
	animation: skeleton-loading 1.3s ease infinite;
}

.skeleton-img {
	width: 180rpx;
	height: 180rpx;
	border-radius: 12rpx;
	flex-shrink: 0;
}

.skeleton-info {
	flex: 1;
	margin-left: 20rpx;
	padding-top: 8rpx;
}

.skeleton-line {
	width: 62%;
	height: 24rpx;
	margin-top: 22rpx;
	border-radius: 12rpx;
}

.skeleton-line.wide {
	width: 86%;
	margin-top: 0;
}

.skeleton-line.short {
	width: 38%;
}

@keyframes skeleton-loading {
	0% { background-position: 100% 50%; }
	100% { background-position: 0 50%; }
}

.empty-goods {
	min-height: 520rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	color: #9ca3af;
}

.empty-goods-img {
	width: 112rpx;
	height: 112rpx;
	border-radius: 24rpx;
	opacity: 0.72;
}

.empty-goods-title {
	margin-top: 18rpx;
	font-size: 28rpx;
	color: #4b5563;
	font-weight: 600;
}

.empty-goods-desc {
	margin-top: 8rpx;
	font-size: 23rpx;
	color: #9ca3af;
}

.load-more {
	height: 92rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #9ca3af;
	font-size: 23rpx;
}

.load-more-inner {
	display: flex;
	align-items: center;
	gap: 10rpx;
}

.loading-dot {
	width: 24rpx;
	height: 24rpx;
	border: 3rpx solid rgba(31, 143, 77, 0.18);
	border-top-color: var(--primary-color);
	border-radius: 50%;
	animation: spin 0.8s linear infinite;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}

.cart-bar {
	height: 100rpx;
	bottom: 50px;
	padding: 15rpx 24rpx;
	box-sizing: border-box;
	border-top: 2rpx solid #f1f1f1;
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.cart-summary {
	display: flex;
	align-items: center;
	position: relative;
	min-width: 0;
}

.cart-icon {
	width: 66rpx;
	height: 66rpx;
	border-radius: 50%;
	background-color: var(--primary-color);
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 22rpx;
}

.cart-num {
	position: absolute;
	left: 46rpx;
	top: -8rpx;
	min-width: 28rpx;
	height: 28rpx;
	padding: 0 8rpx;
	border-radius: 16rpx;
	background-color: #ff4646;
	color: #fff;
	font-size: 20rpx;
	line-height: 28rpx;
	text-align: center;
	box-sizing: border-box;
}

.cart-label {
	font-size: 26rpx;
	color: #333;
}

.cart-price {
	color: var(--price-text-color);
	font-size: 34rpx;
	font-weight: 600;
}

.settle-btn {
	width: 180rpx;
	height: 70rpx;
	line-height: 70rpx;
	border-radius: 36rpx;
	margin: 0;
	color: #fff;
	font-size: 26rpx;
	font-weight: 600;
	background-color: var(--primary-color);
}

.settle-btn.disabled {
	background-color: #f0f0f0;
	color: #a8abb0;
}

:deep(.mescroll-body) {
	min-height: 100%;
}

:deep(.mescroll-upwarp) {
	padding-left: 0 !important;
}

:deep(.u-tabbar__placeholder),
:deep(.tab-bar-placeholder) {
	display: none !important;
}

.mescroll-empty.empty-page.part {
	width: 100%;
	height: 420rpx;
	margin-top: 0;
	padding-top: 80rpx;
}

/* #ifndef H5 */
.cart-bar {
	bottom: 100rpx;
}

.content.has-cart {
	bottom: 200rpx;
}
/* #endif */

/* ===== 筛选抽屉:左维度 + 右分组标签 ===== */
.ftr {
	display: flex;
	flex-direction: column;
	height: 72vh;
	background: #fff;
	border-radius: 28rpx 28rpx 0 0;
	overflow: hidden;
}
.ftr-head {
	flex: 0 0 auto;
	height: 112rpx;
	padding: 0 86rpx 0 30rpx;
	box-sizing: border-box;
	display: flex;
	align-items: center;
	justify-content: space-between;
	border-bottom: 1rpx solid #f2f3f5;
}
.ftr-title {
	display: block;
	font-size: 32rpx;
	font-weight: 600;
	color: #1d2129;
}
.ftr-subtitle {
	display: block;
	max-width: 430rpx;
	margin-top: 6rpx;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
	font-size: 21rpx;
	color: #94a3b8;
}
.ftr-selected-count {
	padding: 8rpx 15rpx;
	border-radius: 18rpx;
	background: rgba(var(--primary-color-rgb, 18, 85, 231), 0.08);
	color: var(--primary-color);
	font-size: 20rpx;
	font-weight: 600;
}
.ftr-body {
	flex: 1;
	display: flex;
	min-height: 0;
}
.ftr-dims {
	flex: 0 0 176rpx;
	height: 100%;
	background: #f6f7f8;
}
.ftr-dim {
	position: relative;
	height: 100rpx;
	display: flex;
	align-items: center;
	padding-left: 28rpx;
	font-size: 26rpx;
	color: #4e5969;
}
.ftr-dim.on {
	background: #fff;
	color: #1d2129;
	font-weight: 600;
}
.ftr-dim.on::before {
	content: '';
	position: absolute;
	left: 0;
	top: 28rpx;
	bottom: 28rpx;
	width: 6rpx;
	border-radius: 0 6rpx 6rpx 0;
	background: var(--primary-color);
}
.ftr-dim-dot {
	width: 12rpx;
	height: 12rpx;
	border-radius: 50%;
	background: #ff4d4f;
	margin-left: 10rpx;
}
.ftr-panel {
	flex: 1;
	height: 100%;
	padding: 24rpx 28rpx 40rpx;
	box-sizing: border-box;
}
.ftr-group {
	margin-bottom: 28rpx;
}
.ftr-group-name {
	display: block;
	font-size: 24rpx;
	color: #86909c;
	margin-bottom: 18rpx;
}
.ftr-section-head {
	margin-bottom: 20rpx;
}
.ftr-section-title {
	display: block;
	color: #334155;
	font-size: 27rpx;
	font-weight: 600;
}
.ftr-section-tip {
	display: block;
	margin-top: 7rpx;
	color: #94a3b8;
	font-size: 21rpx;
}
.ftr-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 20rpx;
}
.ftr-chip {
	display: flex;
	align-items: center;
	min-width: 140rpx;
	max-width: 100%;
	height: 64rpx;
	padding: 0 24rpx;
	box-sizing: border-box;
	background: #f6f7f8;
	border: 1rpx solid #f6f7f8;
	border-radius: 12rpx;
	font-size: 26rpx;
	color: #4e5969;
	justify-content: center;
}
.ftr-chip text {
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}
.ftr-chip.on {
	background: rgba(var(--primary-color-rgb, 250, 44, 25), 0.08);
	border-color: var(--primary-color);
	color: var(--primary-color);
	font-weight: 600;
}
.ftr-chip.more {
	background: transparent;
	border: none;
	color: #86909c;
	min-width: 0;
}
.ftr-chip-arrow {
	font-size: 22rpx;
	margin-left: 6rpx;
}
.ftr-empty {
	padding: 80rpx 0;
	text-align: center;
	font-size: 26rpx;
	color: #c9cdd4;
}
.ftr-foot {
	flex: 0 0 auto;
	display: flex;
	align-items: center;
	gap: 20rpx;
	padding: 16rpx 28rpx;
	padding-bottom: calc(16rpx + constant(safe-area-inset-bottom));
	padding-bottom: calc(16rpx + env(safe-area-inset-bottom));
	border-top: 1rpx solid #f2f3f5;
}
.ftr-foot-action--reset {
	flex: 0 0 200rpx;
}
.ftr-foot-action--confirm {
	flex: 1;
	min-width: 0;
}
</style>
