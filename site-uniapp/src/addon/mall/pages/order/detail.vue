<template>
    <view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()">
         <view  class="pb-20rpx" v-if="Object.values(detail).length">
            <view  class="sidebar-margin  card-template my-[20rpx]">
                <view class="font-500 flex items-baseline mb-[10rpx]">
                    <text class="nc-iconfont nc-icon-naozhongV6xx text-[36rpx] mr-[10rpx]"></text>
                    <text class="text-[36rpx] ">{{ detail.status_name.name  }}</text>
                </view>
            </view>
            <view class="sidebar-margin mt-[var(--top-m)] card-template">
                <view class="title">买家信息</view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">买家昵称</view>
                    <view class="flex items-center" @click.stop="redirect({url: '/app/pages/member/detail', param: {member_id: detail.member_id}})">
                        <u-avatar :default-url="img('static/resource/images/default_headimg.png')" :src="img(detail.member.headimg)" :size="'30rpx'" leftIcon="none" />
                        <text v-if="detail.member" class="text-[28rpx] ml-[10rpx]">{{ detail.member.nickname }}</text>
                        <text class="nc-iconfont nc-icon-youV6xx text-[24rpx] text-[#999] relative top-[2rpx]"></text>
                    </view>
                </view>
                <view class="justify-between card-template-item" v-if="detail.member.mobile">
                    <view class="text-[28rpx]">联系手机</view>
                    <view class="text-[28rpx]">{{ detail.member.mobile }}</view>
                </view>
            </view>
            <view class="sidebar-margin mt-[var(--top-m)] card-template">
                <view class="title">商品信息</view>
                <view class="mb-[30rpx]" v-for="(item, index) in detail.order_goods" :key="index">
                    <view class="flex box-border" >
                        <up-image width="150rpx" height="150rpx" :radius="'var(--goods-rounded-big)'" :src="img(item.goods_image ? item.goods_image : '')" mode="aspectFill">
                            <template #error>
                                <image class="w-[150rpx] h-[150rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                            </template>
                        </up-image>
                        <view class="ml-[20rpx] flex flex-1 flex-col box-border">
                            <view class="flex justify-between items-baseline">
                                <view class="max-w-[322rpx] text-[28rpx] leading-[40rpx] font-400 truncate text-[#303133]">
                                    <text v-if="detail.order_type_name" class="relative top-[-4rpx] inline-block mr-[4rpx] bg-primary text-[#fff] text-[18rpx] rounded-[4rpx] leading-[30rpx] px-[6rpx]">{{ detail.order_type_name }}</text>
                                    {{ item.goods_name }}
                                </view>
                                <view class="text-right leading-[42rpx] ml-[10rpx] price-font">
                                    <text class="text-[22rpx]">￥</text>
                                    <text class="text-[32rpx] font-500">{{parseFloat(item.price).toFixed(2).split('.')[0] }}</text>
                                    <text class="text-[22rpx] font-500">.{{parseFloat(item.price).toFixed(2).split('.')[1] }}</text>
                                </view>
                            </view>
                            <view class="flex  justify-between items-baseline text-[#303133] mt-[14rpx]">
                                <view>
                                    <view class="text-[24rpx] text-[var(--text-color-light6)] font-400 truncate leading-[34rpx] max-w-[369rpx] mb-[10rpx]" v-if="item.sku_name">{{ item.sku_name }}</view>
                                </view>
                                <text class="text-right text-[26rpx] font-400 w-[90rpx] leading-[36rpx]">x{{ item.num }}</text>
                            </view>
                        </view>
                    </view>
                    <view class="flex justify-end mt-[20rpx]" v-if="(item.status != '1')">	
                        <view class="order-grey-hollow-btn"  @click="redirect({ url: '/addon/mall/pages/refund/detail', param: { refund_id : item.refund_id } })">查看退款</view>            
                    </view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">商品总额</view>
                    <view class="text-[28rpx]">{{ detail.goods_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">优惠金额</view>
                    <view class="text-[28rpx]">{{ detail.discount_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">平台优惠金额</view>
                    <view class="text-[28rpx]">{{ detail.mall_discount_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">配送金额</view>
                    <view class="text-[28rpx]">{{ detail.delivery_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">订单金额</view>
                    <view class="text-[28rpx]">{{ detail.order_money }}</view>
                </view>
                <view class="justify-between card-template-item" v-if="Number(detail.refund_money)">
                    <view class="text-[28rpx]">退款金额</view>
                    <view class="text-[28rpx]">{{ detail.refund_money }}</view>
                </view>
            </view>
            <view class="sidebar-margin mt-[var(--top-m)] card-template">
                <view class="title">订单信息</view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">订单编号</view>
                    <view class="flex items-center text-[28rpx]">
                        <text>{{ detail.order_no }}</text>
                        <text class="w-[2rpx] h-[20rpx] bg-[#999] mx-[10rpx]"></text>
                        <text class="text-[var(--primary-color)]" @click="copy(detail.order_no)">复制</text>
                    </view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">订单来源</view>
                    <view class="text-[28rpx]">{{ detail.order_from_name }}</view>
                </view>
                <view class="justify-between card-template-item" v-if="detail.out_trade_no">
                    <view class="text-[28rpx]">交易流水号</view>
                    <view class="text-[28rpx]">{{ detail.out_trade_no }}</view>
                </view>
                <view class="justify-between card-template-item" v-if="detail.pay">
                    <view class="text-[28rpx]">支付方式</view>
                    <view class="text-[28rpx]">{{ detail.pay.type_name }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">配送方式</view>
                    <view class="text-[28rpx]">{{ detail.delivery_type_name }}</view>
                </view>
                <view class="justify-between card-template-item" v-if="detail.buyer_ask_delivery_time">
                    <view class="text-[28rpx]">预约配送时间</view>
                    <view class="text-[28rpx]">{{ detail.buyer_ask_delivery_time }}</view>
                </view>
                <template v-if="detail.order_delivery && detail.order_delivery.length && detail.delivery_type == 'local_delivery'">
                    <view class="justify-between card-template-item">
                        <view class="text-[28rpx]">配送平台</view>
                        <view class="text-[28rpx]">{{ detail.order_delivery[0].delivery_service_name }}</view>
                    </view>
                    <view class="justify-between card-template-item">
                        <view class="text-[28rpx]">配送状态</view>
                        <view class="text-[28rpx]">{{ detail.order_delivery[0].local_delivery_status_name }}</view>
                    </view>
                    <view class="justify-between card-template-item">
                        <view class="text-[28rpx]">配送员</view>
                        <view class="text-[28rpx]">{{ detail.order_delivery[0].rider_name }}</view>
                    </view>
                    <view class="justify-between card-template-item">
                        <view class="text-[28rpx]">配送员电话</view>
                        <view class="text-[28rpx]">{{ detail.order_delivery[0].rider_mobile }}</view>
                    </view>
                </template>
                <view class="justify-between card-template-item !items-start" v-if="detail.delivery_type == 'express' || detail.delivery_type == 'local_delivery'">
                    <view class="text-[28rpx] flex-shrink-0 leading-[40rpx]">收货信息</view>
                    <view class="text-[28rpx] ml-[20rpx] text-right leading-[40rpx]">{{ detail.taker_name }}，{{ detail.taker_mobile }},{{ detail.taker_full_address }}</view>
                </view>
                <view class="justify-between card-template-item" v-if="detail.delivery_type == 'virtual'">
                    <view class="text-[28rpx]">收货人手机号</view>
                    <view class="text-[28rpx]">{{ detail.taker_mobile }}</view>
                </view>
                <template v-if="detail.delivery_type == 'store'">
                    <view class="justify-between card-template-item">
                        <view class="text-[28rpx]">自提点名称</view>
                        <view class="text-[28rpx]">{{ detail.store.store_name }}</view>
                    </view>
                    <view class="justify-between card-template-item !items-start">
                        <view class="text-[28rpx] flex-shrink-0 mr-[30rpx]">自提点地址</view>
                        <view class="text-[28rpx]">{{ detail.store.full_address }}</view>
                    </view>
                    <view class="justify-between card-template-item">
                        <view class="text-[28rpx]">自提点电话</view>
                        <view class="text-[28rpx]">{{ detail.store.mobile }}</view>
                    </view>
                    <view class="justify-between card-template-item">
                        <view class="text-[28rpx]">营业时间</view>
                        <view class="text-[28rpx]">{{ detail.store.trade_time }}</view>
                    </view>
                </template>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">买家留言</view>
                    <view class="text-[28rpx]">{{ detail.member_remark ?? '--' }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">备注</view>
                    <view class="text-[28rpx]">{{ detail.shop_remark ?? '--' }}</view>
                </view>
            </view>
            <view class="sidebar-margin mt-[var(--top-m)] card-template">
                <view class="title">结算信息</view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">店铺结算金额</view>
                    <view class="text-[28rpx]">{{ detail.shop_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">平台手续费</view>
                    <view class="text-[28rpx]">{{ detail.mall_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">店铺退款结算金额</view>
                    <view class="text-[28rpx]">{{ detail.shop_refund_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">平台退还手续费</view>
                    <view class="text-[28rpx]">{{ detail.mall_refund_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">平台优惠券补贴</view>
                    <view class="text-[28rpx]">{{ detail.mall_coupon_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">返还平台优惠券补贴</view>
                    <view class="text-[28rpx]">{{ detail.mall_refund_coupon_money }}</view>
                </view>
                <view class="justify-between card-template-item" v-if="detail.is_install_fenxiao">
                    <view class="text-[28rpx]">店铺支付佣金</view>
                    <view class="text-[28rpx]">{{ detail.shop_commission }}</view>
                </view>
                <view class="justify-between card-template-item" v-if="detail.is_install_fenxiao">
                    <view class="text-[28rpx]">店铺退还佣金</view>
                    <view class="text-[28rpx]">{{ detail.shop_refund_commission }}</view>
                </view>
                <view class="justify-between card-template-item" v-if="Number(detail.supply_money)">
                    <view class="text-[28rpx]">供货商货款</view>
                    <view class="text-[28rpx]">{{ detail.supply_money }}</view>
                </view>
                <view class="justify-between card-template-item" v-if="Number(detail.supply_refund_money)">
                    <view class="text-[28rpx]">供货商货款退款</view>
                    <view class="text-[28rpx]">{{ detail.supply_refund_money }}</view>
                </view>
            </view>
        </view>
        <view class="tab-bar-placeholder"></view>
        <view class="flex z-2 justify-between items-center bg-[#fff] fixed left-0 right-0 bottom-0 min-h-[110rpx] px-[30rpx]  pb-ios">
            <view>
                <view class="text-[24rpx]" @click="showMore()" v-if="detail.leftList?.length">更多</view>
            </view>
            <view class="flex items-center">
                <view :class="{'bg-[var(--primary-color-light)] text-primary':  item.type == 'adjust' || item.type == 'edit_address' || item.type == 'delivery'  || item.type == 'finish' }" class="list-grey-solid-btn ml-[14rpx]" v-for="(item,index) in detail.rightList" :key="index" @click="orderBtnFn(item.type)">{{ item.name }}</view>
            </view>
        </view>
        
        <!-- 备注 -->
		<order-notes ref="orderNotesRef" @confirm="orderDetailFn(orderId)" />
        <!-- 查看物流 -->
        <delivery-package ref="packageRef" />
        <u-popup :show="showMoreDialog" @close="closeBtn" mode="bottom" zIndex="99">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title">更多操作</view>
                <view class="p-[30rpx]">
					<view>
						<view class="flex items-center mb-[20rpx]" @click="copy(detail.order_no)">
							<text class="text-[26rpx]">订单编号：</text>
							<text class="text-[26rpx] text-[#999]">{{ detail.order_no }}</text>
							<text class="nc-iconfont nc-icon-fuzhiV6xx1 text-[26rpx] ml-[10rpx]"></text>
						</view>
						<view class="flex items-center">
							<text class="text-[26rpx]">订单来源：</text>
							<text class="text-[26rpx] text-[#999]">{{ detail.order_from_name }}</text>
						</view>
					</view>
					<view class="h-[2rpx] bg-[#ddd] my-[20rpx]"></view>
					<view class="grid grid-cols-3 gap-[15rpx] py-[20rpx]">
						<view class="bg-[var(--page-bg-color)] rounded-[10rpx] h-[60rpx] text-[26rpx] text-center leading-[60rpx]" v-for="(item, index) in  detail.leftList" :key="index" @click="orderBtnFn(item.type)">{{ item.name }}</view>
					</view>
                </view>
            </view>
        </u-popup>
        <!-- 提示 -->
        <tips-popup ref="tipsRef" />
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app'
import { img, redirect, copy, goback } from '@/utils/common';
import { getOrderDetail, orderClose, orderFinish  } from '@/addon/mall/api/order';
import orderNotes from './components/order-notes.vue';
import deliveryPackage from './components/delivery-package.vue';

const detail = ref<any>({});
const loading = ref<boolean>(true);
const orderId = ref('')

onLoad((option:any) => {
    if (option.order_id) {
        orderId.value = option.order_id;
        orderDetailFn(orderId.value);
    } else {
        let parameter = {
            url: '/addon/mall/pages/order/list',
            title: '缺少订单id'
        };
        goback(parameter)
    }
	
});

const orderDetailFn = (id: any) => {
	loading.value = true;
	getOrderDetail(id).then((res: any) => {
		detail.value = res.data;
        let refundOrderNum = 0
        detail.value.order_goods.forEach((orderItem: any) => {
            if (orderItem.is_enable_refund == 1) {
                refundOrderNum++
            }
            orderItem.form_details = null // 每个商品的表单详情
        })
        detail.value.is_refund_show = refundOrderNum > 0
		loading.value = false;
        detail.value.btnList = []
        detail.value.btnList.push({ name: '备注', type: 'notes'})
        if(!detail.value.supply_id){
            if(detail.value.status == 2 && detail.value.delivery_type !== 'store'){
                detail.value.btnList.push({ name: '订单发货', type: 'delivery'})
            }
			if(detail.value.status == 3 && !detail.value.supply_id && detail.value.delivery_type != 'virtual' && detail.value.delivery_type == 'express'){
				detail.value.btnList.push({ name: '修改发货', type: 'edit_delivery' })
			}
			if(detail.value.status == 3 && !detail.value.supply_id && detail.value.delivery_type != 'virtual' && detail.value.delivery_type == 'local_delivery' && (detail.value.local_delivery_status == -1 || detail.value.local_delivery_status == -2 || detail.value.local_delivery_status == 8)){
				detail.value.btnList.push({ name: '修改发货', type: 'edit_delivery' })
			}
            if(detail.value.status == 1){
                detail.value.btnList.push({ name: '关闭订单', type: 'close'})
                if(detail.value.mall_discount_money == 0) { 
                    detail.value.btnList.push({ name: '调整价格', type: 'adjust' })
                }
                
            }
            if (detail.value.is_refund_show && detail.value.status != 1 && detail.value.status != -1 && !detail.value.supply_id){
				detail.value.btnList.push({ name: '主动退款', type: 'refund' })
			}
            if(detail.value.status == 3){
                detail.value.btnList.push({ name: '确认收货', type: 'finish'})
            }
            if((detail.value.status == 1 || detail.value.status == 2) && detail.value.delivery_type != 'virtual' && detail.value.delivery_type!='store'){
               detail.value.btnList.push({ name: '修改地址', type: 'edit_address' })
			}
            if(detail.value.order_delivery && detail.value.delivery_type == 'express'){
                detail.value.btnList.push({ name: '查看物流', type: 'package' })
            }
        }
        detail.value.rightList = detail.value.btnList.slice(0, 3);
		detail.value.leftList = detail.value.btnList.slice(3);
        
	}).catch(() => {
		loading.value = false;
	})
}

// 操作
const  orderBtnFn = (type: any) => {
	switch (type) {
		case 'notes':
			setNotes();
			break;
		case 'close':
			close();
			break;
		case 'adjust':
			orderAdjustMoney();
			break;
		case 'edit_address':
			orderEditAddressFn();
			break;
		case 'delivery':
			delivery('add');
			break;
        case 'edit_delivery':
			delivery('edit');
			break;
		case 'finish':
			finish();
			break;
        case 'refund':
			refundEvent();
			break;
        case 'package':
            packageEvent();
            break;
		default:
			break;
	}
}
// 查看更多
const showMoreDialog = ref(false)
const showMore = () => {
	showMoreDialog.value = true;
}
const closeBtn = () => {
	showMoreDialog.value = false;
}
// 订单备注
const orderNotesRef = ref();
const setNotes = () => {
	orderNotesRef.value.open(detail.value);
}

// 发货/修改发货
const delivery = (type: any) => {
	redirect({ url: '/addon/mall/pages/order/delivery', param: { order_id: detail.value.order_id, type: type } })
}

const tipsRef = ref();
// 关闭订单
const close = () => {
    tipsRef.value.open('您确定要关闭该订单吗？', () => {
        orderClose(detail.value.order_id).then(() => {
            orderDetailFn(orderId.value);
        })
    })
}
// 主动退款
const refundEvent = () => {
	redirect({ url: '/addon/mall/pages/order/active_refund', param: { order_id: detail.value.order_id } })
}

// 调整价格
const orderAdjustMoney = () => {
	redirect({ url: '/addon/mall/pages/order/adjust_price', param: { order_id: detail.value.order_id } })
}

// 确认收货
const finish = () => {
    tipsRef.value.open('是否确认用户已经收货？', () => {
        orderFinish(detail.value.order_id).then(() => {
            orderDetailFn(orderId.value);
        })
    })
}

// 修改地址
const orderEditAddressFn = () => {
	redirect({ url: '/addon/mall/pages/order/edit_address', param: { order_id: detail.value.order_id } })
}

// 查看物流
const packageRef = ref();
const packageEvent = () => {
    const arr: any = []
	detail.value.order_delivery.forEach((item: any, index: number) => {
        if(item.delivery_type == 'express'){
            item.name = `包裹${index + 1}`
            item.mobile = detail.value.taker_mobile
            arr.push(item)
        }
    })
    packageRef.value.open(arr)
}
</script>

<style lang="scss" scoped>
.tab-bar-placeholder {
	padding-bottom: calc(constant(safe-area-inset-bottom) + 110rpx);
	padding-bottom: calc(env(safe-area-inset-bottom) + 110rpx);
}
</style>