<template>
    <view class="overflow-hidden relative" v-if="diyComponent && diyComponent.goods && Object.keys(diyComponent.goods).length">
        <view v-if="diyStore.mode != 'decorate'" class="tab-bar-placeholder"></view>
        <view :style="warpCss" class="border-[0] border-t-[2rpx] border-solid border-[#f5f5f5] w-[100%] flex justify-between pl-[32rpx] pr-[4rpx] box-border tab-bar z-10 items-center" :class="[{ 'fixed left-0 bottom-0': diyStore.mode != 'decorate' }, 'bottom-layout-' + layoutStyle]">
            <view class="flex items-center">
                <view v-if="menuContent.includes('index')" class="flex flex-col justify-center items-center mr-[38rpx]" @click="redirect({ url: '/addon/phone_shop/pages/index', mode: 'reLaunch' })">
                    <view class="nc-iconfont nc-icon-shouyeV6xx11 text-[36rpx]"></view>
                    <text class="text-[18rpx] mt-[6rpx]">首页</text>
                </view>
                <template v-if="menuContent.includes('service')">
                    <!--  #ifdef H5 -->
                    <view class="flex flex-col justify-center items-center mr-[38rpx]"
                        @click="redirect({ url: '/app/pages/member/contact', param: {send_title: sendMessageTitle, send_path: encodeURIComponent(sendMessagePath), send_img: encodeURIComponent(sendMessageImg)} })">
                        <text class="nc-iconfont text-[36rpx] nc-icon-kefuV6xx-1 text-[#303133]"></text>
                        <text class="text-[18rpx] mt-[6rpx]">客服</text>
                    </view>
                    <!-- #endif -->
                    <!-- #ifdef MP-WEIXIN -->
                    <view>
                        <nc-contact
                            :send-message-title="sendMessageTitle"
                            :send-message-path="sendMessagePath"
                            :send-message-img="sendMessageImg">
                            <view class="flex flex-col justify-center items-center mr-[38rpx]">
                                <text class="nc-iconfont nc-icon-kefuV6xx-1 text-[36rpx]"></text>
                                <text class="text-[18rpx] mt-[6rpx]">客服</text>
                            </view>
                        </nc-contact>
                    </view> 
                    <!-- #endif -->
                </template>
               
                <view v-if="menuContent.includes('cart')" class="flex flex-col justify-center items-center mr-[38rpx]" @click="redirect({ url: '/addon/phone_shop/pages/goods/cart'})">
                    <view class="iconfont icongouwuche2 text-[38rpx]"></view>
                    <text class="text-[18rpx] mt-[6rpx]">购物车</text>
                </view>
                <view v-if="menuContent.includes('collect')" class="flex flex-col justify-center items-center mr-[38rpx]" @click="collectFn">
                    <text class="nc-iconfont text-[36rpx]" :class="{'text-[#ff0000] nc-icon-xihuanV6mm': isCollect, 'text-[#303133] nc-icon-guanzhuV6xx' : !isCollect}"></text>
                    <text class="text-[18rpx] mt-[6rpx]">收藏</text>
                </view>
                <view v-if="menuContent.includes('share')" class="flex flex-col justify-center items-center mr-[38rpx]"  @click="openShareFn">
                    <text class="nc-iconfont text-[36rpx] nc-icon-fenxiangV6xx text-[#303133]"></text>
                    <text class="text-[18rpx] mt-[6rpx]">分享</text>
                </view>
            </view>
            <view class="flex flex-1" v-if="isGoodsSellable">
                <button v-if="diyComponent.goods.is_gift"
                        class="!w-[420rpx] flex-1 !h-[70rpx] font-500 text-[26rpx] !text-[#fff] !bg-[#ccc] !m-0 leading-[70rpx] rounded-full remove-border"
                >商品为赠品不可购买</button>
                <template v-else-if="maxBuy > 0 || maxBuy == -1">
                    <button
                        v-if="diyGlobal?.goodsParameter?.type != 'newcomer_discount' && (diyComponent.goods.goods_type == 'real' || (diyComponent.goods.goods_type == 'virtual' && diyComponent.goods.virtual_receive_type != 'verify')) && diyComponent.cartIsShow"
                        class="flex-1 !h-[70rpx] font-500 text-[26rpx] !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border" @click="buyFn('join_cart')" :style="cartBtnStyle">
                        {{ diyComponent.cartName }}
                    </button>
                    <button
                        v-if="isShowSingleSku && diyComponent.buyIsShow !== false"
                        class="flex-1 !h-[70rpx] font-500 text-[26rpx] !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border" :style="buyBtnStyle"
                        @click="buyFn('buy_now')">{{ diyComponent.buyName }}
                    </button>
                    <!-- 一键转发朋友圈:与立即购买同款按钮(同 buyBtnStyle),文字可配置;可隐藏立即购买实现替换 -->
                    <button v-if="diyComponent.forwardIsShow"
                        class="flex-1 !h-[70rpx] font-500 text-[26rpx] !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border" :style="buyBtnStyle"
                        @click="forwardFn">{{ diyComponent.forwardName || '一键转发' }}</button>
                    <button v-if="!isShowSingleSku"
                            :style="{ width : (diyComponent.goods.goods_type == 'real' || (diyComponent.goods.goods_type == 'virtual' && diyComponent.goods.virtual_receive_type != 'verify')) ?  '200rpx' : '400rpx' + '!important'  }"
                            class="flex-1 !h-[70rpx] font-500 text-[26rpx] !text-[#fff] !bg-[#ccc] !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border"
                    >已售罄</button>
                </template>
                <button v-else-if="maxBuy == 0"
                        :style="{ width : '420rpx' + '!important'  }"
                        class="flex-1 !h-[70rpx] font-500 text-[26rpx] !text-[#fff] !bg-[#ccc] !m-0 leading-[70rpx] rounded-full remove-border"
                >已达限购数量</button>
            </view>
            <template v-else-if="diyStore.mode == 'decorate'">
                <button v-if="diyComponent.cartIsShow"
                    class="flex-1 !h-[70rpx] font-500 text-[26rpx] !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border" :style="cartBtnStyle">
                    {{ diyComponent.cartName }}
                </button>
                <button v-if="diyComponent.buyIsShow !== false"
                    class="flex-1 !h-[70rpx] font-500 text-[26rpx] !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border" :style="buyBtnStyle"
                    @click="buyFn('buy_now')">{{ diyComponent.buyName }}
                </button>
                <button v-if="diyComponent.forwardIsShow"
                    class="flex-1 !h-[70rpx] font-500 text-[26rpx] !m-0 !mr-[16rpx] leading-[70rpx] rounded-full remove-border" :style="buyBtnStyle">{{ diyComponent.forwardName || '一键转发' }}</button>
            </template>
            <view class="flex flex-1" v-else>
                <button class="w-[100%] !h-[70rpx] font-500 text-[26rpx] !text-[#fff] !bg-[#b8bec8] !m-0 leading-[70rpx] rounded-full remove-border">{{ unavailableText }}</button>
            </view>
        </view>
        <!-- 装修时，防止点击 -->
        <view v-if="diyStore.mode == 'decorate'" class="absolute z-10 top-0 right-0 bottom-0 left-0"></view>
    </view>
    <!-- 一键转发朋友圈:下载配置弹窗 -->
    <download-config-dialog :show="showConfigDialog" @close="showConfigDialog = false" @confirm="onForwardConfigConfirm" />
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { redirect, img } from '@/utils/common';
import useDiyStore from '@/app/stores/diy';
import useMemberStore from '@/stores/member'
import { collect, cancelCollect } from '@/addon/phone_shop/api/goods';
import useGoodsDetailStore from '@/addon/phone_shop/stores/goodsDetail'
import { useGoodsDownload } from '@/addon/phone_shop/hooks/useGoodsDownload'
import { useGoodsForwardAccess } from '@/addon/phone_shop/hooks/useGoodsForwardAccess'
import DownloadConfigDialog from '@/addon/phone_shop/components/download-config-dialog/download-config-dialog.vue'
import { useLogin } from '@/hooks/useLogin'

const props = defineProps(['component', 'index', 'value', 'global']);
const diyStore = useDiyStore();
const emits = defineEmits(['loadingFn', 'update:componentIsShow']); //商品数据加载完成之后触发
const sendMessageTitle = ref('')
const sendMessagePath = ref('')
const sendMessageImg = ref('')

// 会员信息
const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info)

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        const obj = {
            stock: 1,
            sku_id: 0,
            skuList: [{ sku_id: 0, stock: 1, sku_spec_format: '' }],
            goods: {
                sow_show_list: '',
                goods_name: '示例商品',
                goods_type: 'real',
                status: 1,
                stock: 1,
                is_gift: 0,
                is_limit: 0,
                sale_state: { can_sell: 1, name: '' }
            },
            menuContent: ['index', 'service', 'cart'],
            cartIsShow: true,
            buyIsShow: true,
            cartName: '加入购物车',
            buyName: '立即购买',
            cartStyle: {
                textColor: '#FFFFFF',
                fontSize: 16,
                gradientAngle: 'to right',
                startColor: '#FFB000',
                endColor: '#FFA029'
            },
            buyStyle: {
                textColor: '#FFFFFF',
                fontSize: 16,
                gradientAngle: 'to right',
                startColor: '#FB7939',
                endColor: '#FF4142'
            }
        }
        const configured = diyStore.value[props.index] || {}
        return {
            ...obj,
            ...configured,
            goods: { ...obj.goods, ...(configured.goods || {}) },
            cartStyle: { ...obj.cartStyle, ...(configured.cartStyle || {}) },
            buyStyle: { ...obj.buyStyle, ...(configured.buyStyle || {}) }
        };
    } else {
        return Object.assign({}, props.component, useGoodsDetailStore().goodsDetail);
    }
})

const diyGlobal = computed(() => {
    return props.global;
})

const warpCss = computed(() => {
    let style = '';
    if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${ diyComponent.value.componentGradientAngle },${ diyComponent.value.componentStartBgColor },${ diyComponent.value.componentEndBgColor });`;
    else style += 'background-color:' + (diyComponent.value.componentStartBgColor || diyComponent.value.componentEndBgColor) + ';';

    if (diyComponent.value.componentBgUrl) {
        style += `background-image:url('${ img(diyComponent.value.componentBgUrl) }');`;
        style += 'background-size: cover;background-repeat: no-repeat;';
    }

    if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
    if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
    if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
    if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
    return style;
})

// 立即购买按钮
const buyBtnStyle = computed(() => {
    let style = '';
    if (diyComponent.value.buyStyle.startColor && diyComponent.value.buyStyle.endColor) style += `background:linear-gradient(${ diyComponent.value.buyStyle.gradientAngle },${ diyComponent.value.buyStyle.startColor },${ diyComponent.value.buyStyle.endColor });`;
    else style += 'background-color:' + (diyComponent.value.buyStyle.startColor || diyComponent.value.buyStyle.endColor) + ';';

    if (diyComponent.value.buyStyle.textColor) style += 'color:' + diyComponent.value.buyStyle.textColor+';';
    if (diyComponent.value.buyStyle.fontSize) style += 'font-size:' + diyComponent.value.buyStyle.fontSize * 2 + 'rpx;';

    if (diyComponent.value.goods.goods_type == 'real' || (diyComponent.value.goods.goods_type == 'virtual' && diyComponent.value.goods.virtual_receive_type != 'verify')){
        style += 'width:200rpx !important;';
    }else{
        style += 'width:400rpx !important;';
    }
    return style;
})

// 一键转发朋友圈:下载商品图到相册 + 复制商品信息(复用 useGoodsDownload hook)
const { downloadGoodsImagesWithConfig, needShowConfigDialog, saveConfig } = useGoodsDownload()
const showConfigDialog = ref(false)
const pendingDownload: any = ref(null)
const forwardFn = async () => {
    try {
        const allowed = await useGoodsForwardAccess().ensureGoodsForwardAccess('/addon/phone_shop/pages/goods/detail')
        if (!allowed) return
        const item: any = diyComponent.value || {}
        const gi = item.goods && item.goods.goods_image
        let images: string[] = []
        if (gi) images = Array.isArray(gi) ? gi : String(gi).split(',').map((u: string) => img(u.trim()))
        if (needShowConfigDialog()) {
            pendingDownload.value = { images, item }
            showConfigDialog.value = true
        } else {
            await downloadGoodsImagesWithConfig(images, item, undefined, undefined, true)
        }
    } catch (e) {
        uni.showToast({ title: '转发失败', icon: 'none' })
    }
}
const onForwardConfigConfirm = async (config: any) => {
    saveConfig(config)
    showConfigDialog.value = false
    if (pendingDownload.value) {
        await downloadGoodsImagesWithConfig(pendingDownload.value.images, pendingDownload.value.item, config, undefined, true)
        pendingDownload.value = null
    }
}

// 加入购物车按钮
const cartBtnStyle = computed(() => {
    let style = '';
    if (diyComponent.value.cartStyle.startColor && diyComponent.value.cartStyle.endColor) style += `background:linear-gradient(${ diyComponent.value.cartStyle.gradientAngle }, ${ diyComponent.value.cartStyle.startColor },${ diyComponent.value.cartStyle.endColor });`;
    else style += 'background-color:' + (diyComponent.value.cartStyle.startColor || diyComponent.value.cartStyle.endColor) + ';';

    if (diyComponent.value.cartStyle.textColor) style += 'color:' + diyComponent.value.cartStyle.textColor+';';
    if (diyComponent.value.cartStyle.fontSize) style += 'font-size:' + diyComponent.value.cartStyle.fontSize * 2 + 'rpx;';
    return style;
})

// 收藏
const isCollect: any = ref(0);
const collectFn = () => {
    // 检测是否登录
    if (!userInfo.value) {
        uni.showToast({
            title: '未登录，请先登录后再收藏商品',
            icon: 'none'
        });
        return false
    }
    let api = isCollect.value ? cancelCollect({ goods_ids: [diyComponent.value.goods_id] }) : collect(diyComponent.value.goods_id);
    api.then(res => {
        isCollect.value = !isCollect.value;
        if (isCollect.value) {
            uni.showToast({
                title: '收藏成功',
                icon: 'none'
            });
        } else {
            uni.showToast({
                title: '取消收藏',
                icon: 'none'
            });
        }
    })
}

// 商品限购
const maxBuy = ref(-1);
const goodsMaxBuy = (data: any = {}) => {
    // 限购 - 是否开启限购
    if (data.goods.is_limit && userInfo.value && data.goods.stock > 0) {
        if (data.goods.max_buy) {
            let max_buy = 0;
            if (data.goods.limit_type == 1) { //单次限购
                max_buy = data.goods.max_buy;
            } else { // 单人限购
                let buyVal = data.goods.max_buy - (data.goods.has_buy || 0);
                max_buy = buyVal > 0 ? buyVal : 0;
            }

            if (max_buy > data.goods.stock) {
                maxBuy.value = data.goods.stock
            } else if (max_buy <= data.goods.stock) {
                maxBuy.value = max_buy;
            }
        }
    }
}

// 判断单规格库存是否为0
const isShowSingleSku = computed(() => {
    // 装修预览没有真实商品 SKU；历史装修数据也可能缺少 skuList。
    // 此时按可预览处理，不能让整个装修画布因空数组报错。
    const skuList = Array.isArray(diyComponent.value?.skuList) ? diyComponent.value.skuList : []
    if (!skuList.length) return true

    const isSingleSpec = skuList.some((item: any) => Boolean(item?.sku_spec_format)) // true：多规格，false：单规格
    const stock = Number(diyComponent.value?.stock || 0)

    // 单规格，库存为0，显示已售罄
    if (!isSingleSpec && stock <= 0) {
        return false;
    } else if (!isSingleSpec && stock > 0) {
        // 单规格，库存大于0，可以购买
        return true;
    }
    return true;
})

const canDirectBuy = computed(() => {
    const data: any = diyComponent.value || {}
    const skuList = Array.isArray(data.skuList) ? data.skuList : []
    const sku = skuList[0] || {}
    const stock = Number(sku.stock ?? data.stock ?? 0)
    const minBuy = Math.max(1, Number(data.goods?.min_buy || 1))
    return skuList.length === 1
        && stock === 1
        && minBuy === 1
        && !data.goods?.form_id
        && Number(data.goods?.is_limit ? data.goods?.max_buy || 1 : 1) >= 1
})
const layoutStyle = computed(() => diyComponent.value.layoutStyle || 'standard')
const saleState = computed(() => diyComponent.value.goods?.sale_state || {})
const isGoodsSellable = computed(() => {
    if (diyStore.mode == 'decorate') return true
    return Number(diyComponent.value.goods?.status) === 1 && Number(saleState.value.can_sell ?? 1) === 1
})
const unavailableText = computed(() => saleState.value.name || (Number(diyComponent.value.goods?.status) === 1 ? '暂不可购买' : '该商品已下架'))

const directBuy = () => {
    const data: any = diyComponent.value || {}
    const sku = data.skuList[0]
    if (!userInfo.value) {
        useLogin().setLoginBack({
            url: '/addon/phone_shop/pages/goods/detail',
            param: { sku_id: sku.sku_id, type: data.type || '' }
        })
        return
    }
    const detail = data.detail || sku
    const extendData: any = {}
    if (detail.type === 'newcomer_discount' && detail.newcomer_price) {
        extendData.relate_id = ''
        extendData.activity_type = 'newcomer_discount'
    } else {
        extendData.relate_id = detail.show_type === 'discount_price' ? (data.discount_info?.discount_id || '') : ''
        extendData.activity_type = detail.show_type === 'discount_price' ? 'discount' : (detail.type || data.type || '')
    }
    uni.setStorage({
        key: 'orderCreateData',
        data: {
            sku_data: [{ sku_id: sku.sku_id, num: 1 }],
            extend_data: extendData
        },
        success: () => redirect({ url: '/addon/phone_shop/pages/order/payment' })
    })
}

const buyFn = (type: any) => {
    if (type === 'buy_now' && canDirectBuy.value) {
        directBuy()
        return
    }
    useGoodsDetailStore().setGoodsDetail({isOpenSkuBuy: true, skuBuyType: type});
}

onMounted(() => {
    refresh();
    // 装修模式下刷新
    if (diyStore.mode == 'decorate') {
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {
                if (newValue && newValue.componentName == 'PhoneShopGoodsDetailBottom') {
                    nextTick(() => {
                        menuContent.value = (typeof diyComponent.value?.menuContent == 'object') ? diyComponent.value?.menuContent : diyComponent.value?.menuContent.split(',');
                    })
                }
            }
        )
    } else {
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {

            }
        )
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {
                if (newValue && newValue.componentName == 'PhoneShopGoodsDetailBottom') {
                    refresh();
                    emits('update:componentIsShow', true)
                }
            },
            { immediate: true }
        )
        initFn()
    }
    // 商品限购
    goodsMaxBuy(diyComponent.value);
});

const menuContent = ref([])
const initFn = () => {
    isCollect.value = diyComponent.value.goods.is_collect;
    sendMessageTitle.value = diyComponent.value.goods.goods_name
    sendMessagePath.value = '/addon/phone_shop/pages/goods/detail?sku_id=' + diyComponent.value.sku_id;
    if (diyComponent.value.type) {
        sendMessagePath.value += '&type=' + diyComponent.value.type;
    }
    sendMessageImg.value = img(diyComponent.value.goods.goods_cover_thumb_mid)
    menuContent.value = (typeof diyComponent.value?.menuContent == 'object') ? diyComponent.value?.menuContent : diyComponent.value?.menuContent.split(',');
}

/************* 分享海报-start **************/
const openShareFn = () => {
    useGoodsDetailStore().setGoodsDetail({isOpenSharePoster: true});
}
/************* 分享海报-end **************/

const refresh = () => {
    const content = diyComponent.value?.menuContent
    menuContent.value = Array.isArray(content)
        ? content
        : (typeof content == 'string' && content ? content.split(',') : ['index', 'service', 'cart']);
}
</script>
<style lang="scss" scoped>
.tab-bar-placeholder {
    padding-bottom: calc(constant(safe-area-inset-bottom) + 100rpx);
    padding-bottom: calc(env(safe-area-inset-bottom) + 100rpx);
}
.tab-bar {
    padding-top: 16rpx;
    padding-bottom: calc(constant(safe-area-inset-bottom) + 16rpx);
    padding-bottom: calc(env(safe-area-inset-bottom) + 16rpx);
}
.bottom-layout-floating {
    left: 20rpx !important;
    right: 20rpx !important;
    bottom: 18rpx !important;
    width: calc(100% - 40rpx) !important;
    border: 0 !important;
    border-radius: 28rpx;
    box-shadow: 0 10rpx 38rpx rgba(31, 41, 55, .16);
}
.bottom-layout-compact {
    padding-top: 10rpx;
    padding-bottom: calc(env(safe-area-inset-bottom) + 10rpx);
    :deep(button) { height: 62rpx !important; line-height: 62rpx !important; }
}
</style>
