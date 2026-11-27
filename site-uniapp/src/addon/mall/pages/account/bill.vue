<template>
    <view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()">
        <view class="fixed left-0 top-0 right-0 z-10 bg-[var(--page-bg-color)]">
            <scroll-view :scroll-x="true" class="tab-style-2">
				<view class="tab-content">
					<view class="tab-items text-[#a1a1a1] mr-[40rpx]" :class="{ 'class-select':  dateType == item.type }" @click="handleDate(item)" v-for="(item, index) in shortcuts" :key="index" >{{ item.text }}</view>
                    <view class="tab-items text-[#a1a1a1]"  :class="{ 'class-select':  dateType == 'custom' }" @click="handleSelect">自定义</view>
				</view>
			</scroll-view>
            <view class="px-[30rpx] h-[80rpx] leading-[80rpx] bg-[#fff] text-[26rpx] ">起止时间：{{ date[0].split(' ')[0] }}～{{ date[1].split(' ')[0] }}</view>
        </view>
        <mescroll-body top="168rpx" ref="mescrollRef" height="auto" @init="mescrollInit" :down="{ use: false }" @up="getListFn">
            <view class="sidebar-margin card-template my-[var(--top-m)]">
                <view class="flex flex-col items-center pb-[40rpx]">
                    <text class="text-[26rpx] text-[#666] mb-[20rpx]">营业收入</text>
                    <view class="price-font text-[52rpx]">{{ parseFloat(totalStat.order_actual_money).toFixed(2) }}</view>
                </view>
                <view class="grid grid-cols-2">
                    <view class="box-border border-top border-right px-[30rpx] py-[30rpx] flex items-center">
                        <image :src="img('static/resource/images/site_uniapp/account/icon-01.png')" class="w-[70rpx] h-[70rpx] mr-[20rpx]" />
                        <view class="flex flex-col justify-between">
                            <text class="text-[24rpx] text-[#666] mb-[10rpx]">商户余额</text>
                            <view class="price-font text-[32rpx]">{{ parseFloat(totalStat.money_get).toFixed(2) }}</view>
                        </view>
                    </view>
                    <view class="box-border border-top px-[30rpx] py-[30rpx] flex items-center">
                        <image :src="img('static/resource/images/site_uniapp/account/icon-02.png')" class="w-[70rpx] h-[70rpx] mr-[20rpx]" />
                        <view class="flex flex-col justify-between">
                            <text class="text-[24rpx] text-[#666] mb-[10rpx]">退款支出</text>
                            <view class="price-font text-[32rpx]">{{ parseFloat(totalStat.order_refund_money).toFixed(2) }}</view>
                        </view>
                    </view>
                    <view class="box-border border-top border-right px-[30rpx] py-[30rpx] flex items-center">
                        <image :src="img('static/resource/images/site_uniapp/account/icon-03.png')" class="w-[70rpx] h-[70rpx] mr-[20rpx]" />
                        <view class="flex flex-col justify-between">
                            <text class="text-[24rpx] text-[#666] mb-[10rpx]">佣金支出</text>
                            <view class="price-font text-[32rpx]">{{ parseFloat(totalStat.shop_refund_commission).toFixed(2) }}</view>
                        </view>
                    </view>
                    <view class="box-border border-top px-[30rpx] py-[30rpx] flex items-center">
                        <image :src="img('static/resource/images/site_uniapp/account/icon-04.png')" class="w-[70rpx] h-[70rpx] mr-[20rpx]" />
                        <view class="flex flex-col justify-between">
                            <text class="text-[24rpx] text-[#666] mb-[10rpx]">平台手续费</text>
                            <view class="price-font text-[32rpx]">{{ parseFloat(totalStat.mall_service_money).toFixed(2) }}</view>
                        </view>
                    </view>
                    <view class="box-border border-top border-right  px-[30rpx] py-[30rpx] flex items-center">
                        <image :src="img('static/resource/images/site_uniapp/account/icon-05.png')" class="w-[70rpx] h-[70rpx] mr-[20rpx]" />
                        <view class="flex flex-col justify-between">
                            <text class="text-[24rpx] text-[#666] mb-[10rpx]">平台优惠券补贴</text>
                            <view class="price-font text-[32rpx]">{{ parseFloat(totalStat.mall_coupon_money).toFixed(2) }}</view>
                        </view>
                    </view>
                    <view class="box-border border-top px-[30rpx] py-[30rpx] flex items-center">
                        <image :src="img('static/resource/images/site_uniapp/account/icon-06.png')" class="w-[70rpx] h-[70rpx] mr-[20rpx]" />
                        <view class="flex flex-col justify-between">
                            <text class="text-[26rpx] text-[#666] mb-[10rpx]">供货商货款</text>
                            <view class="price-font text-[32rpx]">{{ parseFloat(totalStat.supply_goods_money).toFixed(2) }}</view>
                        </view>
                    </view>
                </view>
            </view>
            <view class="sidebar-margin card-template mb-[20rpx]">
                <view class="title pb-[20rpx] border-bottom">商城账单</view>
                <view class="box-border flex items-center justify-between mb-[30rpx]">
                    <view class="flex whitespace-nowrap">
                        <view class="mr-[40rpx] text-[26rpx] leading-[40rpx] " :class="{ 'select-wrap': type == 'day'}" @click="handleBill('day')">日账单</view>
                        <view class="tab-left-item leading-[40rpx] " :class="{ 'select-wrap': type == 'month'}" @click="handleBill('month')">月账单</view>
                    </view>
                </view>
                <view class="flex items-center leading-[80rpx]" v-if="list.length">
                    <view class="w-[30%] text-center">日期</view>
                    <view class="w-[30%] text-center">收入</view>
                    <view class="w-[30%] text-center">支出</view>
                    <view class="w-[10%]"></view>
                </view>
                <view v-if="list.length">
                    <view v-for="(item, index) in list" :key="index" class="flex leading-[80rpx]" :class="{'bg-[var(--page-bg-color)] rounded-[8rpx]':index % 2 == 0}" @click="toLink(item)">
                        <view class="w-[30%] text-center">{{ item.time }}</view>
                        <view class="w-[30%] text-center">{{ item.income_money }}</view>
                        <view class="w-[30%] text-center">{{ item.outlay_money }}</view>
                        <view class="w-[10%] nc-iconfont nc-icon-youV6xx text-[28rpx] flex-center"></view>
                    </view>
                </view>
                <mescroll-empty v-if="!list.length && !loading"></mescroll-empty>
            </view>
        </mescroll-body>
        
        
        <!-- 时间选择 -->
		<select-date ref="selectDateRef" :custom-date="false" @confirm="confirmFn" />
    </view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { img, redirect } from '@/utils/common'
import { getFinanceAccountsTotal, getFinanceAccountsList } from '@/app/api/stat'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app';

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const totalStat = ref<any>({
    order_actual_money: 0,
    money_get: 0,
    money: 0,
    order_refund_money: 0,
    shop_refund_commission: 0,
    mall_service_money: 0,
    mall_coupon_money: 0,
    supply_goods_money: 0
})
const dateType = ref<any>('seven')
const date = ref<any>([])
const type = ref<any>('day')
const list = ref<Array<any>>([])
const loading = ref<boolean>(true)
// 添加日期格式化函数
const formatDate = (date: any) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};
const shortcuts = [
    {
        text: '最近7天',
        type: 'seven',
        value: () => {
            const end = new Date()
            const start = new Date()
            start.setTime(start.getTime() - 3600 * 1000 * 24 * 7)
            return [formatDate(start), formatDate(end)]
        }
    }, 
    {
        text: '最近30天',
        type:'thirty',
        value: () => {
            const end = new Date()
            const start = new Date()
            start.setTime(start.getTime() - 3600 * 1000 * 24 * 29)
            return [formatDate(start), formatDate(end)]
        }
    }, 
    {
        text: '上月',
        type:'lastMonth',
        value: () => {
            const date = new Date()
            const year = date.getFullYear()
            const month = date.getMonth()
            const start = new Date(year, month - 1, 1)
            const end = new Date(year, month, 0)
            return [formatDate(start), formatDate(end)]
        }
    }, 
    {
        text: '本月',
        type:'thisMonth',
        value: () => {
            const date = new Date()
            const year = date.getFullYear()
            const month = date.getMonth()
            const start = new Date(year, month, 1)
            const end = new Date()
            return [formatDate(start), formatDate(end)]
        }
    }
];
date.value = shortcuts[0].value()
// 收款统计
const getFinanceAccountsTotalFn = () => {
    getFinanceAccountsTotal({ date: date.value }).then((res: any) => {
        totalStat.value = res.data
    })
}
getFinanceAccountsTotalFn()

const getListFn = (mescroll : any) => {
	loading.value = true;
	let data: Object = {
		page: mescroll.num,
		limit: mescroll.size,
		type: type.value,
		date: date.value
	};

	getFinanceAccountsList(data).then((res: any) => {
		let newArr = res.data.data;
		mescroll.endSuccess(newArr.length);
		//设置列表数据
		if (mescroll.num == 1) {
			list.value = []; //如果是第一页需手动制空列表
		}
		list.value = list.value.concat(newArr);
		loading.value = false;
	}).catch(() => {
		loading.value = false;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}
// 切换日期
const handleDate = (item: any) => {
    dateType.value = item.type
    date.value = item.value()
    getFinanceAccountsTotalFn()
	getMescroll().resetUpScroll();

}

const  handleBill = (data: any) => {
    type.value = data
    getMescroll().resetUpScroll();
}

const toLink = (data: any) => {
    redirect({ url: '/addon/mall/pages/account/bill_detail', param: { date: data.time, type: data.type } })
}


//日期筛选
const selectDateRef = ref()
const handleSelect = () =>{
	selectDateRef.value.show = true
}
// 确定时间筛选
const confirmFn = (data: any) =>{
	date.value = data;
    dateType.value = 'custom'
	getFinanceAccountsTotalFn()
	getMescroll().resetUpScroll();
}


</script>

<style lang="scss" scoped>
.border-bottom{
    border-bottom: 1rpx solid #f8f8f8;
}
.border-top {
    border-top: 1rpx solid #e5e5e5;
}
.border-right {
	border-right: 1rpx solid #e5e5e5;
}
.select-wrap{
    position: relative;
    font-weight: 500;
    color: var(--primary-color);
    &::before {
        content: "";
        position: absolute;
        bottom: -14rpx;
        height: 6rpx;
        border-radius: 100rpx;
        background-color: var(--primary-color);
        width: 50rpx;
        left: 50%;
        transform: translateX(-50%);
    }
}
:deep(.empty-page){
    margin-top:  0 !important;
    padding-top: 40rpx !important;
    width: 100% !important;
    height: 100% !important;
}

</style>