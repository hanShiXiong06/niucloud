<template>
    <view class="bg-gray-100 min-h-[100vh]" :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<scroll-view :scroll-x="true" class="scroll-Y box-border px-[var(--sidebar-m)] bg-white" v-if="statusList.length">
			<view class="flex whitespace-nowrap justify-around items-center h-[88rpx]">
				<view
					:class="['text-[28rpx] text-[#333] h-[88rpx] leading-[88rpx] font-400', { 'class-select !text-[var(--primary-color)]': statusType === item.status }]"
					@click="statusClickFn(item.status)" v-for="(item, index) in statusList">
					{{ item.name }}</view>
			</view>
		</scroll-view>
        <mescroll-body ref="mescrollRef" bottom="50px" @init="mescrollInit" :down="{ use: false }" @up="getAllAppListFn">
            <view class="p-[24rpx] !pb-0 flex justify-between flex-wrap">
                <template v-for="(item, index) in cardList">
					<view class="w-[342rpx] bg-[#fff] box-border rounded-[10rpx] overflow-hidden mb-[20rpx]" @click="toDetail(item.card_id)">
					    <up-image width="342rpx" height="342rpx" :src="img(item.card_image ? item.card_image : '')" mode="aspectFill">
					        <template #error>
					            <u-icon name="photo" color="#999" size="50"></u-icon>
					        </template>
					    </up-image>
					    <view class="pl-[22rpx] pr-[30rpx] mt-[18rpx] h-[80rpx] leading-[40rpx] text-[26rpx] font-500 multi-hidden">{{ item.card_name }}</view>
						<view class="text-[24rpx] font-500 text-[#999999] multi-hidden mb-[10rpx] ml-[20rpx]">使用期限：{{ item.valid_type_name }}</view>
					    <view class="pl-[22rpx] pb-[20rpx] pr-[30rpx] flex justify-between items-end mt-[12rpx]">
					        <view class="flex items-end">
								<view class="text-[var(--price-text-color)] text-[28rpx] font-bold flex items-end">
									<text class="text-[22rpx] price-font ">￥</text>
									<text
										class="price-font text-[36rpx] leading-4">{{ Number( moneyFormat(item.price)).toFixed(0) }}</text>
									<text
										class="price-font text-[20rpx]">.{{ Number( moneyFormat(item.price)).toFixed(2).split('.')[1] }}</text>
									<text v-if="item.sku_unit" class="!text-[26rpx] ml-[5rpx]">/{{ item.sku_unit }}</text>
								</view>
					        	<text class="text-[22rpx] text-[#999999] price-font line-through ml-[10rpx]">
					        		￥{{item.original_price}}
					        	</text>
					        </view>
					        <!-- <text class="text--[24rpx] text-[var(--text-color-light6)] leading-[31rpx]">{{ t('soldOut') }}{{ item.sale_num }}</text> -->
					    </view>
					</view>
                </template>
            </view>
            <mescroll-empty :option="{'icon': img('static/resource/images/empty.png'),'tip': t('nothingMore') }" v-if="!cardList.length && loading"></mescroll-empty>
        </mescroll-body>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, computed } from 'vue'
import { t } from '@/locale'
import { redirect, img, getToken,moneyFormat } from '@/utils/common';
import { getCardList,gettabStatus } from '@/addon/home_service/user/api/card';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import useSystemStore from '@/stores/system';
const statusType = ref('')
const systemStore = useSystemStore()
const statusList = ref([])
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
import { topTabar } from '@/utils/topTabbar';
const topTabarObj = topTabar()
let topTabbarData = topTabarObj.setTopTabbarParam({ title: '次卡列表', topStatusBar: { textColor: '#333' }})
/********* 自定义头部 - end ***********/
const categoryList = ref<Array<Object>>([]);
const cardList = ref<Array<any>>([]);
const coupon_id = ref<number | string>('');
const currGoodsCategory = ref<number | string>('');
const mescrollRef = ref(null);
const loading = ref<boolean>(false);
//列表类型
const listType = ref(true)
onLoad(async(option) => {
	gettabStatusFn()
})
const gettabStatusFn  = () =>{
	gettabStatus().then((res)=>{
		console.log(res)	
		Object.keys(res.data).forEach((item,index)=>{
			let obj ={
				name:res.data[item],
				status:item
			}
			statusList.value.push(obj)
		})
		statusList.value.unshift({name:'全部',status:''})
	})
}
const statusClickFn = (status : any) => {
	statusType.value = status;
	cardList.value = []; //如果是第一页需手动制空列表
	getMescroll().resetUpScroll();
};
interface mescrollStructure {
    num: number,
    size: number,
    endSuccess: Function,
    [propName: string]: any
}

const getAllAppListFn = (mescroll: mescrollStructure) => {
    loading.value = false;
    let data: object = {
        page: mescroll.num,
        limit: mescroll.size,
		valid_type:statusType.value,
		city_id:systemStore.diyAddressInfo?.city_id
    };
    getCardList(data).then((res: any) => {
        let newArr = (res.data.data as Array<Object>);
        //设置列表数据
        if (Number(mescroll.num) === 1) {
            cardList.value = []; //如果是第一页需手动制空列表
        }
        cardList.value = cardList.value.concat(newArr);
        mescroll.endSuccess(newArr.length);
        loading.value = true;
    }).catch(() => {
        loading.value = true;
        mescroll.endErr(); // 请求失败, 结束加载
    })
}

const toDetail = (id: string | number) => {
    redirect({ url: '/addon/home_service/user/pages/card/detail', param: { card_id: id }, mode: 'navigateTo' })
}

onMounted(() => {
    setTimeout(() => {
        getMescroll().optUp.textNoMore = t("end");
    }, 500)
});
</script>

<style lang="scss" scoped>
.nav-item.active {
    color: $u-primary;
}

.scroll-view-wrap {
    word-break: keep-all;
}

.label-chunk {
    color: var(--primary-color);
    background-color: var(--primary-color-light);
}

.text-color {
    color: var(--primary-color);
}

.label-select {
    color: var(--primary-color);
    border-color: var(--primary-color);
    background-color: var(--primary-color-light);
}

:deep(.u-popup .u-transition) {
    top: 174rpx !important;
}

.product-warp {
    z-index: 99999;
}

:deep(.tab-bar-placeholder) {
    display: none !important;
}

:deep(.u-tabbar__placeholder) {
    display: none !important;
}
.class-select {
		position: relative;
		font-weight: 500 !important;

		&::before {
			content: "";
			position: absolute;
			bottom: 10rpx;
			height: 6rpx;
			background-color: $u-primary;
			width: 40rpx;
			left: 50%;
			border-radius: 100rpx;
			transform: translateX(-50%);
		}
	}
</style>
