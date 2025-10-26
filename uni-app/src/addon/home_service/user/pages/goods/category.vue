<template>
	<view class="min-h-screen category" :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="false" />
		<!-- #endif -->
		<view class="mescroll-box bg-[#f6f6f6]" v-if="tabsData.length">
			<mescroll-body ref="mescrollRef" :top="mescrollBoxCss" 
				:down="{ use: false }" @init="mescrollInit" @up="getListFn">
				<!-- 头部搜索 -->
				<view class="search-box z-10 bg-[#fff] fixed top-0 left-0 right-0 box-border h-[110rpx]"
					:style="{'top': systemStore.topTabbarInfo.fullHeight || 0}">
					<input class="search-ipt text-[26rpx] !pl-[60rpx]" type="text" v-model="searchName" @blur="searchNameFn"
						:placeholder="t('searchKeywordPlaceholder')" />
					<view class="flex items-center z-2 h-[66rpx] absolute left-[48rpx] top-[18rpx]">
						<text class="nc-iconfont nc-icon-sousuoV6xx text-[30rpx]" @click="searchNameFn"></text>
					</view>
				</view>
				<view class="fixed bg-[#fff] z-index-99 w-[100vw] h-[130rpx]" :style="addresstabBoxCss">
					<view class="px-[20rpx] pb-[15rpx] pt-[10rpx]  box-border flex items-center" @click.stop="locationVal.reposition()">
						<view class="nc-iconfont nc-icon-dingweiV6xx-1 text-[28rpx]">
						</view>
						<text class="pl-[10rpx] text-[28rpx]">{{systemStore.diyAddressInfo?.community || '去选择定位'}}</text>
					</view>
					<scroll-view scroll-x="true" class="many-goods-list-head flex "  :scroll-into-view="'a' + cateIndex" >
						<view v-for="(item, index) in tabsData" class="scroll-item"
							  :class="[{ active: index == cateIndex }]" :id="'a' + index" :key="index"
							  :style="{paddingLeft: index == 0 ? '0' : ''}"
							  @click="firstLevelClick(item, index)">
							<view class=" flex justify-center flex-col items-center relative px-[15rpx] text-[28rpx] leading-[1.8] " :style="{paddingLeft: index == 0 ? '0' : ''}">
								<view class="name truncate max-w-[180rpx]">{{ item.category_name }}</view>
								<image v-if="index == cateIndex" class="block w-[40rpx] h-[40rpx] absolute z-index-99 top-[20rpx]" :src="img('/addon/home_service/diy/index/manygoodslist_active.png')" mode=""></image>
							</view>
						</view>
					</scroll-view>
				</view>
				<!-- 左侧切换 -->
				<view class="tabs-box z-10 fixed left-0 bg-[#fff] bottom-[150rpx] top-[155rpx] pb-ios "
					:style="tabsBoxCss">
					<scroll-view :scroll-y="true" class="h-[100%]">
						<view class="tab-item" :class="{ 'tab-item-active': index == subActive }"
							v-for="(item, index) in tabsData[tabActive]?.children" :key="tabsData[tabActive].category_id" :id="'id' + index"  @click="subMenuClick(index, item)" >
							<view class="text-box truncate max-w-[140rpx] text-[26rpx]">{{ item.category_name }}</view>
						</view>
					</scroll-view>
				</view>
				<view class="pl-[182rpx] pt-[20rpx]" style="width: calc(100% - 182rpx)">
					<view class="mr-[16rpx] !ml-[5rpx] mb-[20rpx]">
						<up-image width="100%" height="200rpx" radius="10rpx" :src="img(tabsData[tabActive].adv_image)"
							mode="aspectFill">
							<template #error>
								<u-icon name="photo" color="#999" size="80"></u-icon>
							</template>
						</up-image>
					</view>
					<view
						class="bg-white flex px-[20rpx] py-[20rpx] mr-[16rpx] !ml-[5rpx] border-0 border-solid border-[#F0F0F0] rounded-[12rpx] box-border"
						:class="{ 'mt-[16rpx]': index }" v-for="(item, index) in list" :key="item.goods_id"
						@click.stop="toLink(item)">
						<up-image width="200rpx" height="200rpx" radius="10rpx" :src="img(item.goods_cover_thumb_small)"
							mode="aspectFill">
							<template #error>
								<u-icon name="photo" color="#999" size="80"></u-icon>
							</template>
						</up-image>
						<view class="flex flex-col flex-1 justify-between ml-[14rpx]">
							<view class="w-[278rpx] mb-[10rpx] leading-[40rpx] multi-hidden text-[#303133] text-[30rpx]">
								{{ item.goods_name }}</view>
							<view class="flex items-center">
								<view class="text-[#999] text-[24rpx] multi-hidden">
									{{item.goods_subtitle}}
								</view>
							</view>
							<view class="flex items-center mt-auto justify-between p-[15rpx] items-center rounded-[10rpx] bg-style" :style="{background:'url(' + img('addon/home_service/user/goods/category-bg.png') + ')'}">
								<!-- <text class="text-[22rpx] text-[#888]">{{ t('soldOut') }} {{ item.sale_num }}</text> -->
								<view class="text-[var(--price-text-color)] text-[28rpx] font-bold">
									<text class="text-[20rpx] price-font">￥</text>
									<text class="text-[40rpx] price-font">{{ Number(goodsPrice(item)).toString().split('.')[0] }}</text>
									<text
										class="price-font text-[22rpx]">.{{ Number(goodsPrice(item)).toFixed(2).split('.')[1] }}</text>
									<text class="text-[#999]" v-if="item.unit">/{{ item.unit }}</text>
								</view>
								<text class="text-[#fff] text-[24rpx]">
									去下单
								</text>
							</view>
						</view>
					</view>
					<mescroll-empty :option="{ icon: img('static/resource/images/empty.png'), tip: t('nothingMore') }" 
						v-if="!list.length && !loading && listLoading" class="part !ml-[5rpx]"></mescroll-empty>
				</view>
			</mescroll-body>
		</view>
		<mescroll-empty v-if="!tabsData.length && !loading"
			:option="{ icon: img('static/resource/images/empty.png'), tip: t('nothingMore') }"></mescroll-empty>
		<loading-page :loading="loading"></loading-page>
		<tabbar />
	</view>
</template>

<script setup lang="ts">
	import { ref, computed } from 'vue'
	import { img, redirect, getToken } from '@/utils/common'
	import { getGoodsList, getCategory } from '@/addon/home_service/user/api/goods'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { t } from '@/locale'
	import { useLocation } from '@/hooks/useLocation'
	import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	import { topTabar } from '@/utils/topTabbar';
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	const cateIndex = ref(0)
	const lineBg = img('/addon/home_service/diy/index/manygoodslist_active.png');
	/********* 自定义头部 - start ***********/
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '项目分类', topStatusBar: { textColor: '#333' } })
	/********* 自定义头部 - end ***********/

	const list = ref<Array<Object>>([])
	const searchName = ref('')
	const category_id = ref('')
	const loading = ref<boolean>(true) //页面加载动画
	const listLoading = ref<boolean>(false) //列表加载动画
	const homeCategoryList = ref([
		{
			name: "热门服务",
			value: 0,
		},
		{
			name: "推荐服务",
			value: 1,
		},
		{
			name: "洗沙发",
			value: 2,
		},
		{
			name: "修冰箱",
			value: 3,
		},
		{
			name: "热门服务",
			value: 4,
		},
		{
			name: "推荐服务",
			value: 1,
		},
		{
			name: "洗沙发",
			value: 2,
		},
		{
			name: "推荐服务",
			value: 1,
		},
		{
			name: "洗沙发",
			value: 2,
		},
	])
	interface acceptingDataStructure {
		data : acceptingDataItemStructure
		msg : string
		code : number
	}

	interface acceptingDataItemStructure {
		data : object
		[propName : string] : number | string | object
	}

	interface mescrollStructure {
		num : number
		size : number
		endSuccess : Function
		[propName : string] : any
	}
	const addresstabBoxCss = computed(() => {
		let style = ''
		style += `top: calc(${systemStore.topTabbarInfo.height || 0}px + 110rpx);`
		return style
	})
	const mescrollBoxCss = computed(() => {
		// #ifdef MP-WEIXIN
		return (systemStore.topTabbarInfo.height || 0) + 144 + 'rpx'
		// #endif
		// #ifdef H5
		return (systemStore.topTabbarInfo.height || 0) + 132 + 'px'
		// #endif
	})
	const tabsBoxCss = computed(() => {
		let style = ''
		style += `top: calc(${systemStore.topTabbarInfo.height || 0}px + 240rpx);`
		return style
	})
	// 二级菜单弹窗样式
	const twoTabCss = computed(() => {
		let style = ''
		style += `padding-top: calc(${systemStore.topTabbarInfo.height || 0}px + 174rpx);`
		return style
	})
	const locationVal = useLocation(true);
	locationVal.onLoad();
	locationVal.init();
	const clickHomeCategory = (e : any) => {
		console.log(e)
	}
	// 获取项目列表
	const getListFn = (mescroll : mescrollStructure) => {
		loading.value = true
		listLoading.value = false
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
			goods_category: category_id.value,
			goods_name: searchName.value,
			city_id:systemStore.diyAddressInfo?.city_id
		}

		getGoodsList(data).then((res : acceptingDataStructure) => {
			let newArr = res.data.data
			//设置列表数据
			if (mescroll.num == 1) {
				list.value = [] //如果是第一页需手动制空列表
			}
			list.value = list.value.concat(newArr)
			loading.value = false
			mescroll.endSuccess(newArr.length)
			if (!list.value.length) listLoading.value = true
		}).catch(() => {
			loading.value = false
			listLoading.value = true
			mescroll.endErr() // 请求失败, 结束加载
		})
	}

	const toLink = (data : any) => {
		redirect({ url: '/addon/home_service/user/pages/goods/detail', param: { sku_id: data.goods_sku.sku_id } })
	}
	const currGoodsPid = ref(0)
	onLoad((option) => {
		category_id.value = option.curr_goods_category || ''
		currGoodsPid.value = option.pid || 0
		getCategoryData()
	})

	/**
	 * @description 获取分类数据
	 * */
	const tabsData = ref<Array<Object>>([])
	const getCategoryData = () => {
		loading.value = true
		getCategory().then((res : any) => {
			tabsData.value = res.data
			// 有从onload中传入分类id
			if (category_id.value) {
				for (let i = 0; i < tabsData.value.length; i++) {
					// 表示选中的是二级分类
					if (currGoodsPid.value && currGoodsPid.value == tabsData.value[i].category_id) {
						tabActive.value = i
						if (tabsData.value[i]) {
							tabsData.value[i].children.forEach((item : any, index : number) => {
								if (item.category_id == category_id.value) {
									subMenuClick(index, item)
									return false;
								}
							})
						}
						return false;
					} else if (tabsData.value[i].category_id == category_id.value) {
						firstLevelClick(i, tabsData.value[i])
						return false;
					}
				}

			} else {
				if (res.data[0].children && res.data[0].children.length) {
					category_id.value = res.data[0].children[0].category_id
				} else {
					category_id.value = res.data[0].category_id
				}
			}
			loading.value = false
		}).catch(() => {
			loading.value = false
		})
	}

	// 一级菜单样式控制
	const tabActive = ref<number>(0)
	// 二级菜单样式控制
	const subActive = ref<number>(0)

	// 一级菜单点击事件
	const firstLevelClick = ( data : Object,index : number) => {
		tabActive.value = index
		cateIndex.value = index
		if (data.children && data.children.length) {
			subMenuClick(0, data.children[0])
		} else {
			category_id.value = data.category_id
			getMescroll().resetUpScroll()
		}
	}

	// 二级菜单点击事件
	const subMenuClick = (index : number, data : object) => {
		subActive.value = index
		category_id.value = data.category_id
		getMescroll().resetUpScroll()
	}

	// 显示所有分类
	const isShowAll = ref<boolean>(true)
	const showAllTabs = () => {
		const el = document.getElementsByClassName('tab-text')[0]
		// console.log("_____+", isShowAll.value, tabsData.value[tabActive.value]?.children.length)
		if (isShowAll.value === true && tabsData.value[tabActive.value]?.children.length > 3) {
			el.style.height = `100px`
			isShowAll.value = false
		} else if (isShowAll.value === false) {
			el.style.height = `30px`
			isShowAll.value = true
		}
	}

	// 搜索名字
	const searchNameFn = () => {
		getMescroll().resetUpScroll()
		// redirect({ url: '/addon/home_service/pages/goods/list', param: { goods_name: searchName.value } })
	}

	// 价格类型
	let priceType = (data : any) => {
		let type = ''
		if ( getToken()) {
			type = 'member_price' // 会员价
		} else {
			type = ''
		}
		return type
	}
	// 商品价格
	let goodsPrice = (data : any) => {
		let price = '0.00'
		if (getToken()) {
			price = data.goods_sku.member_price || '0.00' // 会员价
		} else {
			price = data.goods_sku.price || '0.00'
		}
		return parseFloat(price).toFixed(2)
	}
</script>

<style lang="scss" scoped>
	.class-select {
		position: relative;
		font-weight: bold;

		&::after {
			content: '';
			position: absolute;
			bottom: 0;
			height: 6rpx;
			background-color: $u-primary;
			width: 90%;
			left: 50%;
			transform: translateX(-50%);
		}
	}

	.list-select {
		position: relative;
		margin-right: 28rpx;

		&::after {
			content: '';
			position: absolute;
			background-color: #999;
			width: 2rpx;
			height: 70%;
			top: 50%;
			right: -14rpx;
			transform: translatey(-50%);
		}
	}

	.transform-rotate {
		transform: rotate(180deg);
	}

	.font-scale {
		transform: scale(0.75);
	}

	.text-color {
		color: $u-primary;
	}

	.bg-color {
		background-color: $u-primary;
	}

	.search-box {
		padding: 20rpx 24rpx;
	}

	.search-box .search-ipt {
		height: 66rpx;
		background-color: #f6f6f6;
		padding-left: 20rpx;
		border-radius: 33rpx;
	}

	.search-box .search-ipt .input-placeholder {
		padding-left: 10rpx;
		color: #a5a6a6;
	}

	.tabs-box {
		width: 168rpx;
		font-size: 26rpx;
	}

	.tabs-box .tab-item {
		min-height: 56rpx;
		padding: 20rpx 0;
		text-align: center;
		background-color: #fff;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.tabs-box .tab-item-active {
		position: relative;
		color: var(--primary-color);
		background-color: var(--temp-bg);

		&::before {
			display: inline-block;
			position: absolute;
			left: 0;
			top: 50%;
			transform: translateY(-50%);
			content: '';
			width: 6rpx;
			height: 48rpx;
			background-color: var(--primary-color);
		}

		&::after {
			display: inline-block;
			position: absolute;
			left: 0;
			top: 50%;
			transform: translateY(-50%);
			content: '';
			width: 6rpx;
			height: 48rpx;
			background-color: var(--primary-color);
		}
	}

	.sort-tabs .tab-text .sort-item {
		display: block;
		text-align: center;
		width: 136rpx;
		height: 48rpx;
		line-height: 48rpx;
		margin-right: 20rpx;
		margin-bottom: 10rpx;
		padding: 0 10rpx;
		border-radius: 50rpx;
		border: 1px solid #e2e2e2;
		font-size: 22rpx;
	}

	.sub-tab-active {
		color: var(--primary-color);
		border: 1px solid var(--primary-color) !important;
	}

	.sort-tabs .tab-icon {
		position: absolute;
		right: 15rpx;
		top: 15%;
		transform: rotate(180deg);
	}

	.labelPopup :deep(.u-transition) {
		top: 208rpx !important;
		left: 182rpx !important;
		z-index: 8 !important;
		border: none !important;
	}

	:deep(.tab-bar-placeholder) {
		display: none !important;
	}

	:deep(.u-tabbar__placeholder) {
		display: none !important;
	}

	.uni-button:after {
		border: none !important;
	}
	.active {
		font-weight:bold;
	    .desc {
	        color: #ffffff;
	        border-radius: 20rpx;
	    }
	}
	.many-goods-list-head {
	    left: 0;
	    right: 0;
	    z-index: 5;
	    width: 100%;
	    white-space: nowrap;
	    box-sizing: border-box;
	    padding:0 20rpx 0 20rpx;
	    margin-top:10rpx;
	    position: relative;
	    &.style-2 {
	        height: 100rpx;
	        padding: 20rpx 0 16rpx;
	    }
	
	    &.style-3 {
	        padding: 26rpx 20rpx;
	        background-color: #fff;
	        margin-bottom: 20rpx;
	        width: 100%;
	        white-space: nowrap;
	        box-sizing: border-box;
	    }
	
	    &.style-4 {
	        padding-bottom: 0;
	    }
	
	    .scroll-item {
	        display: inline-block;
	        text-align: center;
	        width: auto;
	        padding: 0 20rpx;
	
	    }
	
	}
</style>
<style>
	/*  #ifdef  H5  */
	:deep(.category .mescroll-body) {
		padding-bottom: 50px !important;
	}

	/*  #endif  */
	/*  #ifndef  H5  */
	.category .mescroll-body {
		padding-bottom: calc(100rpx + env(safe-area-inset-bottom)) !important;
		padding-bottom: calc(100rpx + constant(safe-area-inset-bottom)) !important;
	}

	/*  #endif  */

	/* // 空页面 */
	:deep(.part) {
		display: flex;
		justify-content: center;
	}

	.mescroll-empty.empty-page.part {
		width: 542rpx;
		height: 542rpx;
		margin-top: 0;
		margin-left: 0;
		padding-top: 50rpx;

		.img {
			width: 160rpx !important;
			height: 120rpx !important;
		}
	}
	.bg-style{
		background-size: 100% 100% !important;
	}
	/* #ifdef MP-WEIXIN */
	:deep(.mescroll-empty) {
		margin-top: 0rpx !important;
	}
	/* #endif */
	
</style>