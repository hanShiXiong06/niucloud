<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh]" :style="themeColor()">
        <view class="fixed left-0 right-0 top-0 bg-[#fff] z-10">
            <view class="py-[14rpx] flex items-center justify-between px-[20rpx]">
				<view class="flex-1 search-input mr-[20rpx]">
					<input class="input" maxlength="50" type="text" v-model="searchParam.goods_name"  placeholder="请输入商品名称" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="getGoodsList()">
					<text v-if="searchParam.goods_name" class="nc-iconfont nc-icon-cuohaoV6xx1 clear mr-[10rpx]" @click="searchParam.goods_name=''"></text>
                    <text @click.stop="getGoodsList()" class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn !mr-0 "></text>
				</view>
                <view class="flex items-center">
                    <view class="text-[#666]" @click="selectData.isShow = !selectData.isShow">
                        <text class="nc-iconfont nc-icon-fuzhiV6xx1 text-[24rpx]" v-if="!selectData.isShow"></text>
                        <text class="text-[24rpx]" :class="{'text-primary': selectData.isShow}">{{ selectData.isShow ? '取消批量' : '批量' }}</text>
                    </view>
                    <view class="ml-[20rpx] text-[#666]" @click="handleScreen">
                        <text class="nc-iconfont nc-icon-shaixuanV6xx1 text-[24rpx]"></text>
                        <text class="text-[24rpx]">筛选</text>
                    </view>
                </view>
			</view>
            <view>
                <scroll-view :scroll-x="true" class="tab-style-2">
                    <view class="tab-content">
                        <view class="tab-items mr-[40rpx]" :class="{ 'class-select': searchParam.status === item.value }" @click="handleStatus(item.value)" v-for="(item, key) in goodsStatus" :key="key">{{ item.label }}</view>
                    </view>
                </scroll-view>
            </view>
        </view>
        <uni-drawer :visible="showScreen" mode="right" @close="showScreen = false" class="screen-wrap">
            <view @touchmove.prevent.stop class="drawer-common">
                <view class="title">筛选</view>
                <scroll-view scroll-y="true" class="h-[75%]">
                    <view class="px-[30rpx]">
                        <view class="text-[28rpx] mb-[30rpx]">销量</view>
                        <view class="flex items-center justify-between mb-[40rpx]">
                            <view class="w-[200rpx] h-[66rpx] box-border rounded-[8rpx] bg-[var(--temp-bg)] text-[26rpx]">
                                <input class="text-[24rpx] h-full text-center" placeholder="最低销量" v-model="searchParam.start_sale_num" />
                            </view>
                            <view class="nc-iconfont nc-icon-jianV6xx"></view>
                            <view class="w-[200rpx] h-[66rpx] box-border rounded-[8rpx] bg-[var(--temp-bg)] text-[26rpx]">
                                <input class="text-[24rpx] h-full text-center" placeholder="最高销量" v-model="searchParam.end_sale_num" />
                            </view>
                        </view>
                        <view class="text-[28rpx] mb-[30rpx]">价格</view>
                        <view class="flex items-center justify-between mb-[40rpx]">
                            <view class="w-[200rpx] h-[66rpx] box-border rounded-[8rpx] bg-[var(--temp-bg)] text-[26rpx]">
                                <input class="text-[24rpx] h-full text-center" placeholder="最低价格" v-model="searchParam.start_price" />
                            </view>
                            <view class="nc-iconfont nc-icon-jianV6xx"></view>
                            <view class="w-[200rpx] h-[66rpx] box-border rounded-[8rpx] bg-[var(--temp-bg)] text-[26rpx]">
                                <input class="text-[24rpx] h-full text-center" placeholder="最高价格" v-model="searchParam.end_price" />
                            </view>
                        </view>
                        <view class="text-[28rpx] mb-[30rpx]">商品类型</view>
                        <view class="flex flex-wrap">
                            <text @click="changeGoodsType(item.type)" v-for="(item, index) in goodsType" :key="index" :class="{ 'label-select': searchParam.goods_type == item.type}" class="truncate text-[#333] px-[10rpx] border-[2rpx] border-solid border-transparent w-[120rpx] h-[56rpx] flex items-center justify-center mr-[30rpx] mb-[30rpx] box-border bg-[var(--temp-bg)] rounded-[8rpx] text-[22rpx]">
                                {{ item.name }}
                            </text>
                        </view>
                    </view>
                </scroll-view>
                <view class="flex items-center justify-between px-[30rpx] py-[30rpx]">
                    <text class="w-[220rpx] h-[68rpx] rounded-[16rpx] text-center leading-[68rpx] text-[26rpx] mr-[20rpx] text-primary border-primary border-solid border-[2rpx]" @click="reset">重置</text>
                    <text class="w-[220rpx] h-[68rpx] bg-primary rounded-[16rpx] text-center leading-[68rpx] text-[26rpx] text-[#fff]" @click="confirm">确认</text>
                </view>
            </view>
        </uni-drawer>
        <mescroll-body ref="mescrollRef" top="176rpx" bottom="50px" @init="mescrollInit" :down="{ use: false }"  @up="getAllAppListFn">
            <view class="sidebar-margin pt-[var(--top-m)]" v-if="goodsList.length">
                <view v-for="(item, index) in goodsList" :key="index" class="flex">
                    <text v-if="selectData.isShow" class="self-center iconfont text-color text-[34rpx] mr-[32rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0 box-border" :class="{ 'iconxuanze1 text-primary': item.checked,'bg-[#fff] border-solid border-[2rpx] border-[#ccc]':!item.checked}" @click.stop="handleSelectItem(item)"></text>
                    <view class="mb-[var(--top-m)] card-template flex-1">
                        <view class="flex box-border">
                            <up-image width="120rpx" height="120rpx" :radius="'var(--goods-rounded-big)'" :src="img(item.goods_cover_thumb_small ? item.goods_cover_thumb_small : '')" mode="aspectFill">
                                <template #error>
                                    <image class="w-[120rpx] h-[120rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                                </template>
                            </up-image>
                            <view class="ml-[20rpx] flex flex-1 flex-col justify-between box-border">
                                <view class="using-hidden text-[28rpx] leading-[40rpx] text-[#303133]">{{ item.goods_name }}</view>
                                <view class="flex items-center">
                                    <view class="text-[24rpx] leading-[34rpx] text-[var(--text-color-light6)] flex items-baseline mr-[30rpx]">
                                        <text class="whitespace-nowrap mr-[4rpx]">销量：</text>
                                        <text class="mx-[2rpx]">{{ item.sale_num }}</text>
                                    </view>
                                    <view class="text-[24rpx] leading-[34rpx] text-[var(--text-color-light6)] flex items-baseline">
                                        <text class="whitespace-nowrap mr-[4rpx]">库存：</text>
                                        <text class="mx-[2rpx]">{{ item.stock }}</text>
                                    </view>
                                </view>
                                <view class="text-[28rpx] price-font text-[var(--price-text-color)]">￥{{ item.goodsSku.price }}</view>
                            </view>
                        </view>
                        <view class="flex justify-between pt-[26rpx]" v-if="!selectData.isShow">
                            <view>
                                <view class="list-grey-solid-btn mr-[14rpx]" @click.stop="handleMore(item)">更多</view>
                            </view>
                            <view class="flex">
                                <view v-if="!item.supply_id"  class="list-grey-solid-btn mr-[14rpx]" @click.stop="editPriceEvent(item)">改价</view>
                                <view v-if="!item.supply_id" class="list-grey-solid-btn mr-[14rpx]" @click.stop="editStockEvent(item)">改库存</view>
                                <view class="list-grey-solid-btn mr-[14rpx]" @click.stop="statusChange(item, 0)" v-if="item.status == 1">下架</view>
                                <view class="list-grey-solid-btn mr-[14rpx]" @click.stop="statusChange(item, 1)" v-else-if="item.status == 0">上架</view>
                                <view v-if="!item.supply_id" class="list-grey-solid-btn bg-[var(--primary-color-light)] text-primary" @click.stop="editEvent(item)">编辑</view>
                            </view>
                        </view>
                    </view>
                </view>
            </view>
            <mescroll-empty v-if="!goodsList.length && loading" :option="{tip : '暂无商品'}"></mescroll-empty>
        </mescroll-body>
         <view class="w-full footer bg-[#fff]">
            <view v-if="selectData.isShow" class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border bg-[#fff]">
                <view class="flex items-center justify-between">
                    <view class="flex flex-col justify-between flex-shrink-0">
                        <view class="flex items-center mb-[10rpx]">
                            <text class="box-border iconfont text-color text-[34rpx] mr-[20rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0" :class="{ 'iconxuanze1 text-primary': selectData.checkAll,'bg-[#fff] border-solid border-[2rpx] border-[#ccc]': !selectData.checkAll }" @click="handleSelectAll()"></text>
                            <text class="text-[24rpx]">全选</text>
                        </view>
                        <view class="text-[22rpx] text-[#999]">已选<text class="text-primary">{{ selectData.checkList.length }}</text>个商品</view>
                    </view>
                    <view class="flex items-center">
                        <button hover-class="none" class="bg-[var(--page-bg-color)] h-[60rpx] leading-[60rpx] rounded-[16rpx] text-[26rpx] font-500 mr-[30rpx]" @click="batchMore">更多批量</button>
                        <button hover-class="none" class="primary-btn-bg text-[#fff] h-[60rpx] leading-[60rpx] rounded-[16rpx] text-[26rpx] font-500 !m-0" @click="batchDeleteGoods">批量删除</button>
                    </view>
                </view>
                
            </view>
            <view v-else class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border bg-[#fff]">
                <button hover-class="none" class="primary-btn-bg text-[#fff] h-[80rpx] leading-[80rpx] rounded-[16rpx] text-[26rpx] font-500" @click="addEvent">发布商品</button>
            </view>
        </view>
        <!-- 改价 -->
        <goods-price-edit-popup ref="goodsPriceEditPopupRef" @load="loadGoodsList"></goods-price-edit-popup>
        <!-- 改库存 -->
        <goods-stock-edit-popup ref="goodsStockEditPopupRef" @load="loadGoodsList"></goods-stock-edit-popup>
        <!-- 每个更多 -->
        <u-popup :show="showMoreDialog" @close="showMoreDialog=false" mode="bottom" zIndex="99">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title">更多操作</view>
                <view class="p-[30rpx] ">
                    <view class="flex">
                        <up-image class="rounded-[10rpx] overflow-hidden" width="100rpx" height="100rpx" :src="img(curGoodsData.goods_cover_thumb_small)" mode="aspectFill">
                            <template #error>
                                <image class="w-[100rpx] h-[100rpx] rounded-[10rpx] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                            </template>
                        </up-image>
                        <view class="ml-[16rpx] flex-1 flex flex-col justify-between">
                            <view class="text-[28rpx]">{{ curGoodsData.goods_name  }}</view>
                            <view class="text-[#999]" @click="copyFn">
                                <text class="nc-iconfont nc-icon-fuzhiV6xx1 text-[24rpx]"></text>
                                <text class="text-[26rpx]">复制链接</text>
                            </view>
                        </view>
                    </view>
                    <view class="h-[2rpx] bg-[#ddd] my-[20rpx]"></view>
                    <view class="grid grid-cols-3 gap-[15rpx] py-[20rpx]">
                        <!-- <view class="bg-[var(--page-bg-color)] rounded-[10rpx] h-[60rpx] text-[26rpx] text-center leading-[60rpx]">改标题</view> -->
                        <view class="bg-[var(--page-bg-color)] rounded-[10rpx] h-[60rpx] text-[26rpx] text-center leading-[60rpx]" @click="deleteEvent">删除</view>
                        <!-- <view class="bg-[var(--page-bg-color)] rounded-[10rpx] h-[60rpx] text-[26rpx] text-center leading-[60rpx]" @click="openShareFn">分享</view> -->
                    </view>
                </view>
            </view>
        </u-popup>
        <!-- 更多批量 -->
         <u-popup :show="showbatchMoreDialog" @close="showbatchMoreDialog=false" mode="bottom" zIndex="99">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title">更多批量操作</view>
                <view class="px-[30rpx]">
                    <view class="mb-[40rpx] text-[28rpx]" v-if="searchParam.status != '1'" @click="batchGoodsStatus(1)">批量上架</view>
                    <view class="mb-[40rpx] text-[28rpx]" v-if="searchParam.status != '0'" @click="batchGoodsStatus(0)">批量下架</view>
                </view>
            </view>
         </u-popup>
        <!-- 分享 -->
        <share-popup ref="shareRef" :copyUrl="copyUrl"  :copyUrlParam="copyUrlParam" />
        <!-- 提示 -->
        <tips-popup ref="tipsRef" />
    </view>
</template>
<script setup lang="ts">
import { ref,reactive } from 'vue';
import { redirect, img, copy } from '@/utils/common';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import { getGoodsPageList, getGoodsStatus, getGoodsType, editGoodsStatus, deleteGoods } from '@/addon/mall/api/goods'
import goodsPriceEditPopup from './components/goods-price-edit-popup.vue';
import goodsStockEditPopup from './components/goods-stock-edit-popup.vue';
import uniDrawer from '@/components/uni-drawer/uni-drawer.vue';
import sharePopup from '@/components/share-popup/share-popup.vue'
import { useShare }from '@/hooks/useShare'

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

// 分享
const{setShare} = useShare()

const searchParam = reactive<any>({
    goods_name: '', // 商品名称
    status: '', // 商品状态
    start_sale_num: '', // 最低销量
    end_sale_num: '', // 最高销量
    start_price: '', // 最低价格
    end_price: '', // 最高价格
    goods_type: '', // 商品类型
});

let loading = ref<boolean>(false);
const goodsList = ref<any>([]);
const goodsStatus = ref<any>([]) // 商品状态
const selectData = reactive({
    isShow: false, // 是否显示
    checkAll: false, // 全选
    checkList: [] // 选中的
})

const getGoodsStatusFn = () => {
    goodsStatus.value = []
    getGoodsStatus().then((res: any) => {
        goodsStatus.value.push({ label: '全部', value: '' })
        Object.values(res.data).forEach((item, index) => {
			goodsStatus.value.push(item);
		});
    })
}
getGoodsStatusFn()

const goodsType = reactive<any>([])
const getGoodsTypeFn = () => {
    // 商品类型
    getGoodsType().then((res: any) => {
        goodsType.push({name: '全部', type: ''})
        Object.values(res.data).map((item: any) => {
            goodsType.push({
                name: item.name,
                type: item.type
            })
        })

    })
}
getGoodsTypeFn()

const getAllAppListFn = (mescroll: any) => {
	loading.value = false;
	let data = {
        page: mescroll.num,
		limit: mescroll.size,
        ...searchParam
    }
	getGoodsPageList(data).then((res: any) => {
		let newArr = (res.data.data as Array<Object>);
		//设置列表数据
		if (Number(mescroll.num) === 1) {
			goodsList.value = []; //如果是第一页需手动制空列表
		}
		goodsList.value = goodsList.value.concat(newArr);
		mescroll.endSuccess(newArr.length);
		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

const  getGoodsList = () => {
    getMescroll().resetUpScroll();
}

const loadGoodsList = () => {
    getMescroll().scrollTo(0,50);
    getMescroll().resetUpScroll();
}

// 筛选
const showScreen = ref(false)
const handleScreen = () => {
    if(selectData.isShow) return
    showScreen.value = true
}

const changeGoodsType = (type: string) => {
    searchParam.goods_type = type
}

// 正则表达式
const regExp = {
    number: /^\d{0,10}$/,
    digit: /^\d{0,10}(.?\d{0,2})$/
}

const confirm = () => {
    if (searchParam.start_sale_num && !regExp.digit.test(searchParam.start_sale_num)) {
        uni.showToast({
            title: '最低销量输入错误',
            icon: 'none'
        })
        return
    }
    if (searchParam.end_sale_num && !regExp.digit.test(searchParam.end_sale_num)) {
        uni.showToast({
            title: '最高销量输入错误',
            icon: 'none'
        })
        return
    }
    if (Number(searchParam.start_sale_num) > Number(searchParam.end_sale_num)) {
        uni.showToast({
            title: '最低销量不能大于最高销量',
            icon: 'none'
        })
        return
    }
    if (searchParam.start_price && !regExp.digit.test(searchParam.start_price)) {
        uni.showToast({
            title: '最低价格输入错误',
            icon: 'none'
        })
        return
    }
    if (searchParam.end_price && !regExp.digit.test(searchParam.end_price)) {
        uni.showToast({
            title: '最高价格输入错误',
            icon: 'none'
        })
        return
    }
    if (Number(searchParam.start_price) > Number(searchParam.end_price)) {
        uni.showToast({
            title: '最低价格不能大于最高价格',
            icon: 'none'
        })
        return
    }
    showScreen.value = false
    getMescroll().resetUpScroll()
}

const  reset = () => {
    searchParam.goods_name = '' // 商品名称
    searchParam.status = '' // 商品状态
    searchParam.start_sale_num = '' // 最低销量
    searchParam.end_sale_num = '' // 最高销量
    searchParam.start_price = '' // 最低价格
    searchParam.end_price = '' // 最高价格
    searchParam.goods_type = '' // 商品类型
    showScreen.value = false
    getMescroll().resetUpScroll()
}

// 切换状态
const  handleStatus = (value: string) => {
    searchParam.status = value
    getMescroll().resetUpScroll();
    selectData.isShow = false
    selectData.checkAll = false
    selectData.checkList = []
}

// 添加商品
const addEvent = () => { 
    redirect({ url: '/addon/mall/pages/goods/edit' })
}
// 编辑商品
const editEvent = (item: any) => { 
    redirect({ url: '/addon/mall/pages/goods/edit', param: {goods_id: item.goods_id, goods_type: item.goods_type }})
}
const tipsRef = ref()
// 上架/下架
let statusRepeat = false
const statusChange = (row: any, value: any) => {
    if (value) {
        if (statusRepeat) return
        statusRepeat = true
        editGoodsStatus({
            goods_ids: row.goods_id,
            status: value
        }).then((res) => {
            statusRepeat = false
            getGoodsStatusFn()
            getMescroll().resetUpScroll();
        }).catch(() => {
            statusRepeat = false
        })
    } else {
        tipsRef.value.open('您确定要下架该商品吗？', () => {
            editGoodsStatus({
                goods_ids: row.goods_id,
                status: value
            }).then((res) => {
                getMescroll().resetUpScroll();
            })
        })
    }
}
// 改价
const goodsPriceEditPopupRef = ref()
const editPriceEvent = (data: any) => {
    goodsPriceEditPopupRef.value.open(data)
}
// 改库存
const goodsStockEditPopupRef = ref()
const editStockEvent = (data: any) => {
    goodsStockEditPopupRef.value.open(data)
}
const showMoreDialog = ref(false)
const curGoodsData = ref<any>(null)
const handleMore = (data: any) => {
    curGoodsData.value = data
    showMoreDialog.value = true
}
// 删除
const deleteEvent = () => {
    showMoreDialog.value = false
    tipsRef.value.open('您确定要删除该商品吗？', () => {
        deleteGoods({
            goods_ids: curGoodsData.value.goods_id
        }).then(() => {
            getMescroll().resetUpScroll();
        }).catch(() => {
        })
    })
}
// 复制
const copyFn = () => {
    let url = location.origin + '/wap' + '/addon/mall/pages/goods/detail' + '?goods_id=' + curGoodsData.value.goods_id;
    copy(url)
    showMoreDialog.value = false
}
// 批量单个选中
const handleSelectItem = (data: any) => {
    data.checked = !data.checked;
    selectData.checkList = goodsList.value.filter((item: any) => item.checked);
    if (selectData.checkList.length == goodsList.value.length) {
        selectData.checkAll = true;
    } else {
        selectData.checkAll = false;
    }
}
const handleSelectAll = () => {
    selectData.checkAll = !selectData.checkAll;
    goodsList.value.forEach((item: any) => {
        item.checked = selectData.checkAll;
    });
    selectData.checkList = goodsList.value.filter((item: any) => item.checked);
}

// 批量删除
const batchDeleteGoods = () => {
    if (selectData.checkList.length == 0) {
        uni.showToast({ title: '请选择要删除的商品', icon: 'none' });
        return;
    }
    let ids = []
    ids = selectData.checkList.map((item: any) => item.goods_id)
    tipsRef.value.open('您确定要批量删除吗？', () => {
        deleteGoods({
            is_all: 0,
            goods_ids: ids
        }).then(() => {
            getMescroll().resetUpScroll();
            selectData.checkList = []
            selectData.isShow = false
        }).catch(() => {
        })
    })
}
// 批量上下架
const showbatchMoreDialog = ref(false)
const batchMore = () => {
    showbatchMoreDialog.value = true
}

const repeat = ref(false)
const batchGoodsStatus = (status: any) => {
    if (selectData.checkList.length == 0) {
        uni.showToast({ title: '请选择要操作的商品', icon: 'none' });
        showbatchMoreDialog.value = false
        return;
    }
    if (repeat.value) return
    repeat.value = true
    let ids = []
    ids = selectData.checkList.map((item: any) => item.goods_id)
    editGoodsStatus({
        goods_ids: ids,
        status
    }).then((res) => {
        getMescroll().resetUpScroll();
        repeat.value = false
        selectData.checkList = []
        selectData.isShow = false
        showbatchMoreDialog.value = false
    }).catch(() => {
        repeat.value = false
    })
}

/************* 分享弹框-start **************/
const shareRef: any = ref(null);
const copyUrl = ref('')
const copyUrlParam = ref('');
let posterParam: any = {};
// 分享海报链接
const copyUrlFn = ()=>{
    copyUrl.value = '/addon/mall/pages/goods/detail'
	copyUrlParam.value = '?goods_id='+ curGoodsData.value.goods_id;
}

const openShareFn = () => {
    posterParam.sku_id = curGoodsData.value.goodsSku.sku_id;
    copyUrlFn();
    showMoreDialog.value = false
    shareRef.value.openShare()
}
/************* 分享弹框-end **************/
</script>
<style lang="scss" scoped>
.footer {
    height: calc(80rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(80rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
:deep(.uni-drawer__content){
    width: 70% !important;
}
.drawer-common {
    height: 100%;
    .title{
		font-weight: 500;
		text-align: center;
		font-size: 32rpx;
		padding-top: 36rpx;
		line-height: 1;
		padding-bottom: 56rpx;
	}
}

.label-select {
	color: var(--primary-color);
	border-color: var(--primary-color);
	background-color: var(--primary-color-light);
}

</style>