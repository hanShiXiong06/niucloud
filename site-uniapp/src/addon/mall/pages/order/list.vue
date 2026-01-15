<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden order-list" :style="themeColor()">
		<view class="fixed left-0 top-0 right-0 z-10" v-if="statusLoading">
			<view class="px-[20rpx] py-[14rpx] bg-[#fff] relative z-10084">
				<view class="h-[60rpx] bg-[#f6f6f6] rounded-[30rpx] flex items-center">
					<view class="search-input !px-[20rpx]">
						<input class="input" maxlength="50" type="text" v-model="keyword" placeholder="请输入搜索关键词" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="searchTypeFn()">
						<text v-if="keyword" class="nc-iconfont nc-icon-cuohaoV6xx1 clear mt-[4rpx] mr-[10rpx]" @click="keyword=''"></text>
						<text class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn !mr-0 w-[40rpx] text-center" @click="searchTypeFn()"></text>
					</view>
				</view>
			</view>
			<scroll-view :scroll-x="true" class="tab-style-2">
				<view class="tab-content">
					<view class="tab-items mr-[40rpx]" :class="{ 'class-select': orderState === item.status.toString() }" @click="orderStateFn(item.status)" v-for="(item, index) in orderStateList">{{ item.name }}</view>
				</view>
			</scroll-view>
		</view>

		<mescroll-body ref="mescrollRef" top="176rpx" @init="mescrollInit" :down="{ use: false }" @up="getMallOrderFn">
			<view class="sidebar-margin pt-[var(--top-m)]" v-if="list.length">
				<template v-for="(item, index) in list" :key="index">
					<view class="mb-[var(--top-m)] card-template">
						<view @click.stop="toLink(item)">
							<view class="flex justify-between items-center mb-[20rpx]">
								<view class="flex items-center" v-if="item.member" @click.stop="redirect({url: '/app/pages/member/detail', param: {member_id: item.member_id}})">
									<u-avatar :default-url="img('static/resource/images/default_headimg.png')" :src="img(item.member.headimg)" :size="'30rpx'" leftIcon="none" />
									<text  class="text-[28rpx] leading-[30rpx] ml-[10rpx]">{{ item.member.nickname }}</text>
									<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[#999] relative top-[1rpx]"></text>
								</view>
								<view class="flex items-center">
									<text class="text-[26rpx] font-400 text-[#999]">{{ item.create_time  }}</text>
									<text class="bg-[#ccc] w-[2rpx] h-[20rpx] mx-[10rpx]"></text>
									<text class="text-[#303133] text-[26rpx] leading-[34rpx]" :class="{'text-primary': item.status  == 1,'!text-[var(--text-color-light9)]' :item.status  == 5 || item.status  == -1}">{{ item.status_name.name }}</text>
								</view>
							</view>
							<view class="flex box-border mb-[20rpx]" v-for="(subitem, index) in item.order_goods" :key="index">
								<up-image width="150rpx" height="150rpx" :radius="'var(--goods-rounded-big)'" :src="img(subitem.goods_image ? subitem.goods_image : '')" mode="aspectFill">
									<template #error>
										<image class="w-[150rpx] h-[150rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
									</template>
								</up-image>
								<view class="ml-[20rpx] flex flex-1 flex-col box-border">
									<view class="flex justify-between items-baseline">
										<view class="max-w-[322rpx] text-[28rpx] leading-[40rpx] font-400 truncate text-[#303133]">
											<text v-if="item.order_type_name" class="relative top-[-4rpx] inline-block mr-[4rpx] bg-primary text-[#fff] text-[18rpx] rounded-[4rpx] leading-[30rpx] px-[6rpx]">{{ item.order_type_name }}</text>
											{{ subitem.goods_name }}
										</view>
										<view class="text-right leading-[42rpx] ml-[10rpx] price-font">
											<text class="text-[22rpx]">￥</text>
											<text class="text-[32rpx] font-500">{{parseFloat(subitem.price).toFixed(2).split('.')[0] }}</text>
											<text class="text-[22rpx] font-500">.{{parseFloat(subitem.price).toFixed(2).split('.')[1] }}</text>
										</view>
									</view>
									<view class="flex  justify-between items-baseline text-[#303133] mt-[14rpx]">
										<view>
											<view class="text-[24rpx] text-[var(--text-color-light6)] font-400 truncate leading-[34rpx] max-w-[369rpx] mb-[10rpx]" v-if="subitem.sku_name">{{ subitem.sku_name }}</view>
										</view>
										<text class="text-right text-[26rpx] font-400 w-[90rpx] leading-[36rpx]">x{{ subitem.num }}</text>
									</view>
								</view>
							</view>
						</view>
						<view class="flex justify-end items-center mb-[20rpx]">
							<view class="flex items-baseline">
								<view class="text-[22rpx] font-400 leading-[30rpx] text-[#303133]">实收：</view>
								<view class="leading-[1] text-[var(--price-text-color)]">
									<text class="ext-[22rpx] leading-[26rpx] price-font">￥</text>
									<text class="text-[36rpx] font-500 leading-[40rpx] price-font">{{ parseFloat(item.order_money).toFixed(2).split('.')[0]  }}</text>
									<text class="text-[22rpx] font-500 leading-[28rpx] price-font">.{{ parseFloat(item.order_money).toFixed(2).split('.')[1]  }}</text>
								</view>
							</view>
						</view>
						<view class="bg-[var(--page-bg-color)] p-[20rpx] rounded-[10rpx]">
							<view class="flex items-center" @click="copy(item.order_no)">
								<text class="nc-iconfont nc-icon-dingdanbianhaoV6xx text-[28rpx] mr-[10rpx]"></text>
								<text class="text-[24rpx]">订单编号 </text>
								<text class="text-[24rpx] text-[#999] ml-[4rpx]">{{ item.order_no }}</text>
								<text class="nc-iconfont nc-icon-fuzhiV6xx1 text-[26rpx] ml-[10rpx]"></text>
							</view>
							<view class="text-[24rpx] text-[var(--text-color-light6)] leading-[34rpx] mt-[20rpx]" v-if="item.taker_name || item.taker_mobile || item.taker_full_address">
								<text class="nc-iconfont nc-icon-dizhiguanliV6xx text-[24rpx] text-[#303133] mr-[10rpx]"></text>
								<text v-if="item.taker_name">{{ item.taker_name }}</text>
								<text v-if="item.taker_mobile">，{{ item.taker_mobile }}</text>
								<text v-if="item.taker_full_address">，{{item.taker_full_address}}</text>
							</view>
						</view>
						<view class="flex justify-between items-center pt-[26rpx]">
							<view>
								<view class="text-[24rpx]" @click="showMore(item)" v-if="item.leftList?.length">更多</view>
							</view>
							<view class="flex items-center">
								<view :class="{'bg-[var(--primary-color-light)] text-primary':  subItem.type == 'adjust' || subItem.type == 'edit_address' || subItem.type == 'delivery' || subItem.type == 'edit_delivery' || subItem.type == 'finish' }" class="list-grey-solid-btn ml-[14rpx]" v-for="(subItem,subIndex) in item.rightList" :key="subIndex" @click="orderBtnFn(subItem.type, item)">{{ subItem.name }}</view>
							</view>
						</view>
					</view>
				</template>
			</view>
			<mescroll-empty :option="{tip : '暂无订单'}" v-if="!list.length && loading"></mescroll-empty>
		</mescroll-body>
		<!-- 备注 -->
		<order-notes ref="orderNotesRef" @confirm="searchTypeFn" />
		<u-popup :show="showMoreDialog" @close="closeBtn" mode="bottom" zIndex="99">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title">更多操作</view>
                <view class="p-[30rpx]">
					<view>
						<view class="flex items-center mb-[20rpx]" @click="copy(curOrderData.order_no)">
							<text class="text-[26rpx]">订单编号：</text>
							<text class="text-[26rpx] text-[#999]">{{ curOrderData.order_no }}</text>
							<text class="nc-iconfont nc-icon-fuzhiV6xx1 text-[26rpx] ml-[10rpx]"></text>
						</view>
						<view class="flex items-center">
							<text class="text-[26rpx]">订单来源：</text>
							<text class="text-[26rpx] text-[#999]">{{ curOrderData.order_from_name }}</text>
						</view>
					</view>
					<view class="h-[2rpx] bg-[#ddd] my-[20rpx]"></view>
					<view class="grid grid-cols-3 gap-[15rpx] py-[20rpx]">
						<view class="bg-[var(--page-bg-color)] rounded-[10rpx] h-[60rpx] text-[26rpx] text-center leading-[60rpx]" v-for="(item, index) in  curOrderData.leftList" :key="index" @click="orderBtnFn(item.type, curOrderData)">{{ item.name }}</view>
					</view>
                </view>
            </view>
        </u-popup>
        <!-- 提示框 -->
        <tips-popup ref="tipsRef" />
	</view>
</template>

<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { img, copy, redirect } from '@/utils/common';
import { getOrderList, getOrderStatus, orderClose, orderFinish, orderDelete } from '@/addon/mall/api/order';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom, onShow } from '@dcloudio/uni-app';
import orderNotes from './components/order-notes.vue';

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);


const list = ref<any>([]);
const loading = ref<boolean>(false);
const statusLoading = ref<boolean>(false);
const orderState = ref('')
const orderStateList = ref<any>([]);
const keyword = ref('');
const create_time = ref([])

onLoad((option: any) => {
	orderState.value = option.status || "";
	getMallOrderStatusFn();
});

onShow(() => {
	nextTick(() => {
		getMescroll().resetUpScroll();
	});
});


const getMallOrderFn = (mescroll:any) => {
	loading.value = false;
	let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
		order_keyword: keyword.value,
		status: orderState.value,
		create_time: create_time.value
	};

	getOrderList(data).then((res: any) => {
		let newArr = (res.data.data as Array<Object>);
		newArr.forEach((item: any, index: number, arr: any) => {
            let refundOrderNum = 0
            item.order_goods.forEach((orderItem: any, orderIndex: number) => {
                if (orderItem.is_enable_refund == 1) {
                    refundOrderNum++
                }
            })
            arr[index].is_refund_show = refundOrderNum > 0 ? true : false;
        })
		//设置列表数据
		if (mescroll.num == 1) {
			list.value = []; //如果是第一页需手动制空列表
		}
		newArr = newArr.map((item: any) => {
			item.btnList = []
			item.btnList.push({ name: '备注', type: 'notes'})
			if(item.status == 1 && !item.supply_id && item.mall_discount_money == 0){
				item.btnList.push({ name: '调整价格', type: 'adjust' })
			}
			if((item.status == 1 || item.status == 2) && item.delivery_type != 'virtual' && !item.supply_id  && item.delivery_type!='store'){
				item.btnList.push({ name: '修改地址', type: 'edit_address' })
			}
			if(item.status == 2 && !item.supply_id && item.delivery_type !== 'store'){
				item.btnList.push({ name: '发货', type: 'delivery' })
			}
			if(item.status == 3 && !item.supply_id && item.delivery_type != 'virtual' && item.delivery_type == 'express'){
				item.btnList.push({ name: '修改发货', type: 'edit_delivery' })
			}
			if(item.status == 3 && !item.supply_id && item.delivery_type != 'virtual' && item.delivery_type == 'local_delivery' && (item.local_delivery_status == -1 || item.local_delivery_status == -2 || item.local_delivery_status == 8)){
				item.btnList.push({ name: '修改发货', type: 'edit_delivery' })
			}
			if(item.status == 3 && !item.supply_id){
				item.btnList.push({ name: '确认收货', type: 'finish' })
			}
			if(item.status == 1 && !item.supply_id){
				item.btnList.push({ name: '关闭订单', type: 'close' })
			}
			if (item.is_refund_show && item.status != 1 && item.status != -1 && !item.supply_id){
				item.btnList.push({ name: '主动退款', type: 'refund' })
			}
			if(item.status == -1){
				item.btnList.push({ name: '删除', type: 'delete' })
			}
			if(item.out_trade_no){
				item.btnList.push({ name: '支付信息', type: 'pay_info' })
			}
			// 根据btnList生成rightList和leftList
			item.rightList = item.btnList.slice(0, 3);
			item.leftList = item.btnList.slice(3);
			return item
		})
		list.value = list.value.concat(newArr);
		mescroll.endSuccess(newArr.length);

		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

const getMallOrderStatusFn = () => {
	statusLoading.value = false;
	orderStateList.value = [];
	let obj = { name: '全部', status: '' };
	orderStateList.value.push(obj);

	getOrderStatus().then((res: any) => {
		Object.values(res.data).forEach((item, index) => {
			orderStateList.value.push(item);
		});
		statusLoading.value = true;
	}).catch(() => {
		statusLoading.value = true;
	})
}

const orderStateFn = (status: any) => {
	orderState.value = status.toString();
	list.value = [];
	getMescroll().resetUpScroll();
};

// 操作
const  orderBtnFn = (type: any, data: any) => {
	switch (type) {
		case 'notes':
			setNotes(data);
			break;
		case 'close':
			closeEvent(data);
			break;
		case 'adjust':
			orderAdjustMoney(data);
			break;
		case 'edit_address':
			orderEditAddressFn(data);
			break;
		case 'delivery':
			delivery(data, 'add');
			break;
		case 'edit_delivery':
			delivery(data, 'edit');
			break;
		case 'finish':
			finish(data);
			break;
		case 'refund':
			refundEvent(data);
			break;
		case 'delete':
			deleteEvent(data);
			break;
		case 'pay_info':
			toPayInfo(data);
			break;
		default:
			break;
	}
}


// 查看更多
const curOrderData = ref<any>({})
const showMoreDialog = ref(false)
const showMore = (data: any) => {
	curOrderData.value = data;
	showMoreDialog.value = true;
}
const closeBtn = () => {
	showMoreDialog.value = false;
	curOrderData.value = {};
}

const searchTypeFn = () => {
	list.value = [];
	getMescroll().resetUpScroll();
	
}
// 订单备注
const orderNotesRef = ref();
const setNotes = (data: any) => {
	orderNotesRef.value.open(data);
}

const tipsRef = ref();
// 关闭订单
const closeEvent = (data: any) => {
	tipsRef.value.open('您确定要关闭该订单吗？', () => {
		orderClose(data.order_id).then(() => {
			getMescroll().resetUpScroll();
		})
	})
}
// 调整价格
const orderAdjustMoney = (data: any) => {
	redirect({ url: '/addon/mall/pages/order/adjust_price', param: { order_id: data.order_id } })
}
// 修改地址
const orderEditAddressFn = (data: any) => {
	redirect({ url: '/addon/mall/pages/order/edit_address', param: { order_id: data.order_id } })
}
// 跳转
const toLink = (data: any) => {
	redirect({ url: '/addon/mall/pages/order/detail', param: { order_id: data.order_id } })
}
// 发货/修改发货
const delivery = (data: any, type: any) => {
	redirect({ url: '/addon/mall/pages/order/delivery', param: { order_id: data.order_id, type: type } })
}
// 确认收货
const finish = (data: any) => {
	tipsRef.value.open('是否确认用户已经收货？', () => {
		orderFinish(data.order_id).then(() => {
			getMescroll().resetUpScroll();
		})
	})
}
// 主动退款
const refundEvent = (data: any) => {
	showMoreDialog.value = false;
	redirect({ url: '/addon/mall/pages/order/active_refund', param: { order_id: data.order_id } })
}
// 删除
const deleteEvent = (data: any) => {
	tipsRef.value.open('是否确认删除该订单？', () => {
		orderDelete({ order_ids: [data.order_id] }).then(() => {
			getMescroll().resetUpScroll();
		}).catch(() => {
		})
	})
}
// 支付信息
const toPayInfo = (data: any) => {
	showMoreDialog.value = false;
	redirect({ url: '/addon/mall/pages/order/pay_info', param: { order_id: data.order_id, order_type: 'mall' } })
}


</script>
<style>
.order-list .mescroll-body {
	padding-bottom: constant(safe-area-inset-bottom) !important;
	padding-bottom: env(safe-area-inset-bottom) !important;
}
</style>
<style lang="scss" scoped>

</style>
