<template>

    <view :style="themeColor()">

        <view class="w-[100vw] min-h-[100vh] bg-[#f8f8f8]" v-if="!loading">
            <view class="w-full bg-[#fff] verify-box h-[760rpx]">
                <view class="text-[var(--primary-color)] fixed top-[40rpx] right-[30rpx] flex items-center"
                    @click="redirect({ url: '/app/pages/verify/record' })">
                    <!-- <image class="w-[26rpx] h-[28rpx]" :src="img('static/resource/images/verify/history.png')" /> -->
                    <!-- <text class="text-[26rpx] ml-[10rpx]">核销记录</text> -->
                </view>
                <view v-show="operationType == 'sweepCode'" class="flex flex-col items-center justify-center">
                    <view class="sweep-code flex items-center justify-center" @click="scanCode">
                        <image class="w-[354rpx] h-[354rpx]" :src="img('static/resource/images/verify/saoma.png')" />
                    </view>
                    <view class="mt-[40rpx] text-[32rpx]">点击扫描二维码</view>
                    <view class="mt-[20rpx] text-[#8288A2] text-[26rpx] font-400 pb-[142rpx]">扫描二维码进行查找设备</view>
                </view>

                <view v-show="operationType == 'manualInput'">
                    <view class="flex pt-[126rpx] items-center justify-center">
                        <view class="flex justify-center items-center flex-col pr-[30rpx] w-[130rpx]">
                            <image class="w-[100rpx] h-[100rpx]"
                                :src="img('static/resource/images/verify/shuruhexiaoma.png')" />
                            <view class="text-[26rpx] h-[36rpx] leading-[36rpx] mt-[12rpx]">操作设备</view>
                        </view>
                        <image class="w-[74rpx] h-[12rpx] mb-[50rpx]"
                            :src="img('static/resource/images/verify/youjiantou.png')" />
                        <view class="flex justify-center items-center flex-col pl-[30rpx] w-[130rpx]">
                            <image class="w-[100rpx] h-[100rpx]"
                                :src="img('static/resource/images/verify/hexiao1.png')" />
                            <view class="text-[26rpx] h-[36rpx] leading-[36rpx] mt-[12rpx]">上/下架</view>
                        </view>
                    </view>
                    <view class="mt-[50rpx]">
                        <view
                            class="h-[90rpx] border-[2rpx] border-solid border-[#DCE0EF] rounded-[16rpx] box-border p-[20rpx] mx-[60rpx] flex items-center">
                            <text class="nc-iconfont nc-icon-saotiaoxingmaV6xx text-[44rpx] text-[#EF000C]"></text>
                            <input type="text" placeholder="请输入设备sn号"
                                class="h-[90rpx] border-none text-start ml-[30rpx] text-[28rpx] flex-1"
                                placeholder-class="_placeholder" v-model="verify_code" :focus="isFocus" ref="input" />
                        </view>
                        <view
                            class="h-[88rpx] min-w-[630rpx] text-[#fff] flex items-center justify-center !text-[32rpx] save-btn rounded-[50rpx] h-[88rpx] mx-[60rpx] mt-[146rpx] relative z-1"
                            @click="confirm">确认</view>
                    </view>
                </view>
            </view>

            <view
                class="w-[630rpx] h-[100rpx] bg-[#fff] mx-[auto] mt-[220rpx] rounded-[90rpx] flex relative action-type-wrap">
                <view
                    class="relative w-[51%] pr-[50rpx] box-border rounded-[50rpx] z-0 flex flex-col items-center justify-center"
                    @click="changeOperationType('sweepCode')" :class="{ 'xuanZhong1': operationType == 'sweepCode' }">
                    <text class="nc-iconfont nc-icon-saoyisaoV6xx !text-[40rpx]"></text>
                    <view class="text-[24rpx] leading-[1] mt-[10rpx]">扫码操作</view>
                </view>
                <view
                    class="flex flex-col items-center flex-col w-[120rpx] h-[120rpx] bg-[#FF7354] rounded-[50%] absolute top-[-10rpx] left-[255rpx] heXiao text-white z-10 shrink-0">
                    <view
                        class="nc-iconfont nc-icon-saotiaoxingmaV6xx ns-gradient-otherpages-member-balance-balance-rechange !text-[44rpx] mt-[19rpx]">
                    </view>
                    <view class="text-[24rpx] mt-[8rpx] leading-[34rpx] h-[34rpx]">操作台</view>
                </view>
                <view
                    class="relative w-[51%] pl-[50rpx] box-border rounded-[50rpx] z-0 flex flex-col items-center justify-center"
                    :class="{ 'xuanZhong': operationType == 'manualInput' }"
                    @click="changeOperationType('manualInput')">
                    <text class="iconfont iconVector-77 !text-[40rpx]"></text>
                    <view class="ml-[20rpx] text-[24rpx] leading-[1] mt-[10rpx]" @click="focus">手动输入</view>
                </view>
            </view>

            <!-- #ifdef MP-WEIXIN -->
            <!-- 小程序隐私协议 -->
            <privacy-popup ref="privacyPopup"></privacy-popup>
            <!-- #endif -->
        </view>
        <u-loading-page :loading="loading" loading-text="" loadingColor="var(--primary-color)"
            iconSize="35"></u-loading-page>
        <up-popup customStyle="width: 630rpx;" :round="10" closeable v-model:show="operationShow" mode="right"
            @close="close" @open="open">

            <up-list @scrolltolower="scrolltolower">
                <up-list-item v-for="(item, index) in goodsList" :key="index">
                    <up-cell>
                        <!-- <template #icon>
                            <up-avatar shape="square" size="35" :src="item.url"
                                customStyle="margin: -3px 5px -3px 0"></up-avatar>
                        </template>
--> <template #title>
                            <view class="u-slot-title">
                                <text class="u-cell-text text-[26rpx] font-500">{{ item.goods_name }}</text>
                            </view>
                            <!--  不换行  超出隐藏  -->
                            <view class=" text-xs text-gray-500 w-[400rpx] truncate overflow-hidden whitespace-nowrap ">
                                {{ item.sub_title }}
                            </view>
                            <!-- sn -->
                            <view class="text-[26rpx] font-500">sn:{{ item.goodsSku.sku_no }}</view>
                            <view class="flex justify-between items-center">
                                <view class="text-[26rpx] font-500">零售价:{{ item.goodsSku.price }}
                                </view>
                                <view class="text-[26rpx] font-500">
                                    同行价:{{ item.goodsSku.market_price }}
                                </view>
                            </view>
                        </template>
                        <template #right-icon>
                            <view>
                                <up-switch v-model="item.status" size="20" @change="_operationGoods(item.goods_id)"
                                    :activeValue="1" :inactiveValue="0"></up-switch>

                            </view>
                            <up-icon name="edit-pen" size="20" @click="editGoods(item)"></up-icon>
                        </template>


                    </up-cell>
                </up-list-item>
            </up-list>

        </up-popup>

    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { img, redirect, isWeixinBrowser, getToken } from '@/utils/common';
import { onShow } from '@dcloudio/uni-app'
import { getVerifierInfo, getCheckVerifier } from '@/app/api/verify'
import { getGoodsPages, oparationGoods } from '@/addon/phone_shop/api/goods'
import { t } from '@/locale'
import wechat from '@/utils/wechat'

const operationType = ref('manualInput'); //类型
const operationShow = ref(false);
const goodsList = ref([])
const page = ref(1)
const isFinished = ref(false)
const isLoading = ref(false)
const isFocus = ref(false)
const verify_code = ref('');
const loading = ref(true)
onShow(() => {
    if (getToken()) checkIsVerifier();
})

// 检测是否是核销员
const checkIsVerifier = () => {
    getCheckVerifier().then((res: any) => {
        if (!res.data) {
            uni.showToast({
                title: '非操作员无此权限',
                icon: 'none'
            });
            setTimeout(() => {
                if (getCurrentPages().length > 1) {
                    uni.navigateBack({
                        delta: 1
                    });
                } else {
                    redirect({
                        url: '/app/pages/member/index',
                        mode: 'reLaunch'
                    });
                }
            }, 1000);
        } else {
            loading.value = false;
        }
    })
}


const scanCode = () => {
    // #ifdef MP
    uni.scanCode({
        onlyFromCamera: true,
        success: res => {
            if (res.errMsg == 'scanCode:ok') {
                let code = res.result;
                console.log(code);
                page.value = 1
                goodsList.value = []
                isFinished.value = false
                getGoodsPages({ sku_no: code, status: 'all', page: page.value }).then(res => {
                    goodsList.value = res.data.data
                    if (page.value >= res.data.last_page) {
                        isFinished.value = true
                    }
                    operationShow.value = true
                    isLoading.value = false;
                })
            } else {
                uni.showToast({
                    title: res.errorMsg,
                    icon: 'none'
                });
            }
        }
    });
    // #endif

    // #ifdef H5
    if (isWeixinBrowser()) {
        wechat.init();
        wechat.scanQRCode(res => {
            if (res.resultStr) {
                let code = res.resultStr;
                getGoodsPages({ sku_no: code, status: 'all', page: page.value }).then(res => {
                    goodsList.value = res.data.data
                    operationShow.value = true
                    isLoading.value = false;
                })
            }
        });
    }
    // #endif
}

const confirm = () => {
    if (!verify_code.value) {
        uni.showToast({
            title: '请输入设备sn号',
            icon: 'none'
        });
        return;
    }
    page.value = 1
    goodsList.value = []
    isFinished.value = false
    getGoodsPages({ sku_no: verify_code.value, status: 'all', page: page.value }).then(res => {
        goodsList.value = res.data.data
        if (page.value >= res.data.last_page) {
            isFinished.value = true
        }
        operationShow.value = true
        isLoading.value = false;
    })
}
// #ifdef H5
operationType.value = 'manualInput';
// #endif
// #ifndef H5
operationType.value = 'sweepCode';
// #endif

const focus = () => {
    isFocus.value = !isFocus.value;
}

const close = () => {
    operationShow.value = false
}

const open = () => {
    operationShow.value = true
}
/**
 * 操作商品的上下架
 * operationGoods
 * */
const _operationGoods = (goods_id: string) => {
    oparationGoods(goods_id).then(res => {
        console.log(res);
        if (res.code == 1) {
            uni.showToast({
                title: '操作成功',
                icon: 'none'
            });
        }
        // operationShow.value = false

    })
}

const changeOperationType = (type: string) => {
    // #ifdef H5
    if (type == 'sweepCode' && !isWeixinBrowser()) {
        uni.showToast({
            title: 'H5端不支持扫码核销',
            icon: 'none'
        });
        return;
    }
    // #endif
    operationType.value = type;
}

const editGoods = (item: any) => {
    // 携带id 跳转到 add_goods
    // redirect({ url: '/app/pages/goods/add_goods', param: { id: item.goods_id }, mode: 'navigateTo' })

    redirect({
        url: '/addon/phone_shop/pages/manager/add_goods',
        param: { id: item.goods_id },
        mode: 'navigateTo'
    });
}

const goodsPrice = (data: any) => {
    let price = "0.00";
    if (data.is_discount && data.goodsSku.sale_price != data.goodsSku.price) {
        price = data.goodsSku.sale_price ? data.goodsSku.sale_price : data.goodsSku.price // 折扣价
    } else if (data.member_discount && getToken() && data.goodsSku.member_price != data.goodsSku.price) {
        price = data.goodsSku.member_price ? data.goodsSku.member_price : data.goodsSku.price // 会员价
    } else {
        price = data.goodsSku.price
    }
    return parseFloat(price);
}

const scrolltolower = () => {
    if (isFinished.value || isLoading.value) return
    isLoading.value = true
    page.value++
    getGoodsPages({ sku_no: verify_code.value, status: 'all', page: page.value }).then(res => {
        goodsList.value = [...goodsList.value, ...res.data.data]
        if (page.value >= res.data.last_page) {
            isFinished.value = true
            uni.showToast({
                title: '没有更多数据了',
                icon: 'none'
            })
        }
        isLoading.value = false
    })
}
</script>
<style lang="scss" scoped>
.action-type-wrap {
    box-shadow: 0 6px 6px 0 rgba(0, 0, 0, 0.03), 0 4px 2px 0 rgba(0, 0, 0, 0.04);
    ;
}

.heXiao {
    background: linear-gradient(180deg, #FF7354 0%, #FF020F 100%), #EF000C;
}

.xuanZhong {
    background: linear-gradient(270deg, #FFD1D1 0%, rgba(255, 209, 209, 0.2) 100%), #FFFFFF;
    color: #EF000C;
}

.xuanZhong1 {
    background: linear-gradient(90deg, #FFD1D1 0%, rgba(255, 209, 209, 0.2) 100%), #FFFFFF;
    color: #EF000C;
}

.sweep-code {
    width: 354rpx;
    height: 354rpx;
    box-shadow: 0 8px 8px 0 rgba(0, 0, 0, 0.03), 0 6px 3px 0 rgba(0, 0, 0, 0.02);
    border-radius: 50%;
    margin-top: 146rpx;
}

.verify-box {
    border-bottom-left-radius: 400rpx 60rpx;
    border-bottom-right-radius: 400rpx 60rpx;
}

.save-btn {
    background: linear-gradient(94deg, #FB7939 0%, #FE120E 99%), #EF000C;
}

._placeholder {
    color: #8288A2;
    font-size: 26rpx;
}

.up-popup-wrap {
    width: 600rpx;

}

.up-popup-wrap>view {
    border-bottom: 1px solid #eee;
}

.up-popup-wrap>view:last-child {
    border-bottom: none;
}
</style>
