<template>
    <x-skeleton :type="skeleton.type" :loading="skeleton.loading" :config="skeleton.config">
        <view :style="warpCss" class="overflow-hidden">
            <view :style="maskLayer"></view>
            <view :class="{'diy-shop-goods-list relative flex flex-wrap justify-between': diyComponent.style != 'style-2', 'biserial-goods-list': diyComponent.style == 'style-2'}">
                <template v-if="diyComponent.style == 'style-2'">
                    <view>
						<view class="mb-[24rpx]" :style="{height:style2WidthImage}">
							<u-swiper :height="style2WidthImage" indicator autoplay :list="props.swiperList" @change="change" @click="click" imgMode="aspectFill"></u-swiper>
						</view>
                        <template v-for="(item,index) in goodsList" v-if="diyStore.mode != 'decorate'">
                            <view v-if="(index%2) == 1" class="flex flex-col bg-[#fff] box-border rounded-lg overflow-hidden"
                                  :class="{'mt-[24rpx]': index > 1}" :style="itemCss" @click="toLink(item)">
                                <up-image radius="10" :width="style2Width" :height="style2Width" :src="img(item.goods_cover_thumb_mid || '')" model="aspectFill">
                                    <template #error>
                                        <image :style="{'width': style2Width,'height': style2Width, 'border-radius': imageRounded.val}" :src="img('static/resource/images/diy/figure.png')" mode="aspectFill" />
                                    </template>
                                </up-image>
<!--                                <easy-image :image-src="item.goods_cover_thumb_small" :imageStyle="imageStyle2" />-->

                                <view class="relative min-h-[44rpx] px-[16rpx] flex-1 pt-[16rpx] pb-[20rpx] flex flex-col justify-between !h-[150rpx]">
                                    <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden"
                                          :style="{ color : diyComponent.goodsNameStyle.color, fontWeight : diyComponent.goodsNameStyle.fontWeight }"
                                          v-if="diyComponent.goodsNameStyle.control">
                                        <view class="brand-tag" v-if="item.goods_brand" :style="diyGoods.baseTagStyle(item.goods_brand)">{{ item.goods_brand.brand_name }}</view>
                                        {{ item.goods_name }}
                                    </view>
									<view class="text-[24rpx] text-[#999] leading-[30rpx] using-hidden my-[5rpx]" v-if="item.sub_title">
									    {{ item.sub_title }}
									</view>
                                    <view class="flex justify-between flex-wrap items-center mt-[20rpx]">
                                        <view class="flex flex-col">
                                            <view class="flex items-baseline leading-[1]" v-if="diyComponent.priceStyle.control">
                                                <view class="text-[var(--price-text-color)] price-font block truncate max-w-[270rpx]" v-if="diyStore.mode == 'decorate'"
                                                    :style="{ color : diyComponent.priceStyle.color }">
                                                    <text class="text-[24rpx] font-400">￥</text>
                                                    <text class="text-[40rpx] font-500">100</text>
                                                    <text class="text-[24rpx] font-500">.00</text>
                                                </view>
												<view class="text-[var(--price-text-color)] price-font block truncate max-w-[270rpx]" v-else
												    :style="{ color : diyComponent.priceStyle.color }">
												    <text class="text-[24rpx] font-400">￥</text>
												    <text class="text-[40rpx] font-500">{{ parseFloat(item.member_price|| item.price).toFixed(2).split('.')[0] }}</text>
												    <text class="text-[24rpx] font-500">.{{ parseFloat(item.member_price || item.price).toFixed(2).split('.')[1] }}</text>
													<text class="price-font text-[24rpx] text-[#999] line-through font-400 ml-[10rpx]"
														v-if="item.goods_original_price && item.goods_original_price != item.member_price"><text
															class="text-[24rpx] price-font">￥</text>{{ Number(item.goods_original_price).toFixed(2) }}</text>
												</view>
                                                <image v-if="diyGoods.priceType(item) == 'member_price'" class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/home_service/VIP.png')" mode="heightFix" />
												<image v-else-if="diyGoods.priceType(item) == 'newcomer_price'"  class="max-w-[60rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/home_service/newcomer.png')" mode="heightFix" />
												<image v-else-if="diyGoods.priceType(item) == 'discount_price'" class="max-w-[80rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/home_service/discount.png')" mode="heightFix" />
                                            </view>
                                        </view>
                                    </view>
                                </view>
                            </view>
                        </template>
                    </view>
                    <view >
                        <template v-for="(item,index) in goodsList">
                            <view v-if="(index%2) == 0" class="flex flex-col bg-[#fff] rounded-lg box-border overflow-hidden" :class="{'mt-[24rpx]': index > 1}" :style="itemCss" @click="toLink(item)">
                                <up-image :width="style2Width" :height="style2Width" radius="10" :src="img(item.goods_cover_thumb_mid || '')" model="aspectFill">
                                    <template #error>
                                        <image :style="{'width': style2Width,'height': style2Width, 'border-radius': imageRounded.val}" :src="img('static/resource/images/diy/figure.png')" mode="aspectFill" />
                                    </template>
                                </up-image>
<!--                                <easy-image :image-src="item.goods_cover_thumb_small" :imageStyle="imageStyle2" />-->
                                <view class="relative min-h-[44rpx] px-[16rpx] flex-1 pt-[16rpx] pb-[20rpx] flex flex-col justify-between !h-[150rpx]">
                                    <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden"
                                          :style="{ color : diyComponent.goodsNameStyle.color, fontWeight : diyComponent.goodsNameStyle.fontWeight }"
                                          v-if="diyComponent.goodsNameStyle.control">
                                        <view class="brand-tag" v-if="item.goods_brand" :style="diyGoods.baseTagStyle(item.goods_brand)">{{ item.goods_brand.brand_name }}</view>
                                        {{ item.goods_name }}
                                    </view>
									<view class="text-[24rpx] text-[#999] leading-[30rpx] using-hidden my-[5rpx]" v-if="item.sub_title">
									    {{ item.sub_title }}
									</view>
                                    <view v-if="item.goods_label_name && item.goods_label_name.length && diyComponent.labelStyle.control" class="flex flex-wrap">
                                        <template v-for="(tagItem, tagIndex) in item.goods_label_name">
                                            <image class="img-tag" v-if="tagItem.style_type == 'icon' && tagItem.icon" :src="img(tagItem.icon)" mode="heightFix" @error="diyGoods.error(tagItem,'icon')" />
                                            <view class="base-tag" v-else-if="tagItem.style_type == 'diy' || !tagItem.icon" :style="diyGoods.baseTagStyle(tagItem)">{{ tagItem.label_name }}</view>
                                        </template>
                                    </view>
                                    <view class="flex justify-between flex-wrap items-center mt-[20rpx]">
                                        <view class="flex flex-col">
                                            <view class="flex items-baseline leading-[1]" v-if="diyComponent.priceStyle.control">
                                                <view class="text-[var(--price-text-color)] price-font block truncate max-w-[270rpx]" v-if="diyStore.mode == 'decorate'"
                                                    :style="{ color : diyComponent.priceStyle.color }">
                                                    <text class="text-[24rpx] font-400">￥</text>
                                                    <text class="text-[40rpx] font-500">100</text>
                                                    <text class="text-[24rpx] font-500">.00</text>
                                                </view>
                                                <view class="text-[var(--price-text-color)] price-font block truncate max-w-[270rpx]" v-else
                                                    :style="{ color : diyComponent.priceStyle.color }">
                                                    <text class="text-[24rpx] font-400">￥</text>
                                                    <text class="text-[40rpx] font-500">{{ parseFloat(item.member_price || item.price).toFixed(2).split('.')[0] }}</text>
                                                    <text class="text-[24rpx] font-500">.{{ parseFloat(item.member_price ||item.price).toFixed(2).split('.')[1] }}</text>
													<text class="price-font text-[24rpx] text-[#999] line-through font-400 ml-[10rpx]"
														v-if="item.goods_original_price && item.goods_original_price != item.member_price"><text
															class="text-[24rpx] price-font">￥</text>{{ Number(item.goods_original_price).toFixed(2) }}</text>
                                                </view>
												<image v-if="diyGoods.priceType(item) == 'member_price'" class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/home_service/VIP.png')" mode="heightFix" />
												<image v-else-if="diyGoods.priceType(item) == 'newcomer_price'"  class="max-w-[60rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/home_service/newcomer.png')" mode="heightFix" />
												<image v-else-if="diyGoods.priceType(item) == 'discount_price'" class="max-w-[80rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/home_service/discount.png')" mode="heightFix" />
                                            </view>
                                        </view>
                                    </view>
                                </view>
                            </view>
                        </template>
                    </view>
                </template>
            </view>
        </view>
    </x-skeleton>
</template>

<script setup lang="ts">
// 商品列表
import { ref, reactive, computed, watch, onMounted, nextTick, getCurrentInstance } from 'vue';
import { redirect, img } from '@/utils/common';
import useDiyStore from '@/app/stores/diy';
import { getGoodsComponents } from '@/addon/home_service/api/diy';
import { useGoods } from '@/addon/home_service/hooks/useGoods'
import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'
import { cloneDeep } from 'lodash-es'
import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()

const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info)

const diyGoods = useGoods();
const props = defineProps(['component', 'index', 'value','swiperList']);
const diyStore = useDiyStore();
const emits = defineEmits(['loadingFn']); //商品数据加载完成之后触发

const skeleton = reactive({
    type: '',
    loading: diyStore.mode == 'decorate' ? false : true,
    config: {}
})

const goodsList = ref<Array<any>>([]);

const diyComponent = computed(() => {
    if (props.value) {
        return props.value;
    } else if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index];
    } else {
        return props.component;
    }
})

const warpCss = computed(() => {
    let style = '';
    style += 'position:relative;';
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

const imageRounded = computed(() => {
    const obj = {
        val: '',
        style: ''
    };
    if (diyComponent.value.imgElementRounded) {
        obj.val = diyComponent.value.imgElementRounded * 2 + 'rpx';
        obj.style += 'border-radius:' + diyComponent.value.imgElementRounded * 2 + 'rpx;';
    }
    return obj;
})

// 背景图加遮罩层
const maskLayer = computed(() => {
    let style = '';
    if (diyComponent.value.componentBgUrl) {
        style += 'position:absolute;top:0;width:100%;';
        style += `background: rgba(0,0,0,${ diyComponent.value.componentBgAlpha / 10 });`;
        style += `height:${ height.value }px;`;

        if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
        if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
        if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
        if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
    }

    return style;
});

const itemCss = computed(() => {
    let style = '';
    if (diyComponent.value.elementBgColor) style += 'background-color:' + diyComponent.value.elementBgColor + ';';
    if (diyComponent.value.topElementRounded) style += 'border-top-left-radius:' + diyComponent.value.topElementRounded * 2 + 'rpx;';
    if (diyComponent.value.topElementRounded) style += 'border-top-right-radius:' + diyComponent.value.topElementRounded * 2 + 'rpx;';
    if (diyComponent.value.bottomElementRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomElementRounded * 2 + 'rpx;';
    if (diyComponent.value.bottomElementRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomElementRounded * 2 + 'rpx;';
    if (diyComponent.value.style == 'style-2') {
        if (diyComponent.value.margin && diyComponent.value.margin.both) style += 'width: calc((100vw - ' + (diyComponent.value.margin.both * 4) + 'rpx - 20rpx) / 2);'
        else style += 'width: calc((100vw - 20rpx) / 2 );'
    }
    return style;
})

const goodsBtnCss = computed(() => {
    let style = '';
    if (diyComponent.value.btnStyle.style == 'button' && diyComponent.value.btnStyle.aroundRadius) style += 'border-radius:' + diyComponent.value.btnStyle.aroundRadius * 2 + 'rpx;';
    if (diyComponent.value.btnStyle.startBgColor && diyComponent.value.btnStyle.endBgColor) {
        style += `background:linear-gradient(${ diyComponent.value.btnStyle.startBgColor },${ diyComponent.value.btnStyle.endBgColor });`;
    } else {
        style += 'background-color:' + (diyComponent.value.btnStyle.startBgColor || diyComponent.value.btnStyle.endBgColor) + ';';
    }
    if (diyComponent.value.btnStyle.textColor) style += 'color:' + diyComponent.value.btnStyle.textColor + ';';
    if (diyComponent.value.btnStyle.style == 'button' && diyComponent.value.btnStyle.fontWeight) style += 'font-weight: bold;';
    return style;
})

const imageStyle1 = computed(() => {
    let style = 'border-radius:' + imageRounded.value.val + ';';
    return style;
})

const style2Width = computed(() => {
    let style = '';
    if (diyComponent.value.margin && diyComponent.value.margin.both) style += 'calc((100vw - ' + (diyComponent.value.margin.both * 4) + 'rpx - 20rpx) / 2)'
    else style += 'calc((100vw - 20rpx) / 2 )'
    return style;
})
const style2WidthImage =  computed(() => {
    let style = '';
    if (diyComponent.value.margin && diyComponent.value.margin.both) style += 'calc((100vw - ' + (diyComponent.value.margin.both * 4) + 'rpx - 20rpx) / 2 + 136rpx)'
    else style += 'calc((100vw - 20rpx) / 2 + 138rpx)'
    return style;
})
const imageStyle2 = computed(() => {
    let style = 'border-radius:' + imageRounded.value.val + ';';
    style += 'width:'+ style2Width.value + ';';
    style += 'height:'+ style2Width.value + ';';
    return style;
})

const style3Css = computed(() => {
    let style = '';
    style += 'padding:0 20rpx;';
    if (diyComponent.value.margin && diyComponent.value.margin.both) {
        style += 'width: calc(100vw - ' + ((diyComponent.value.margin.both * 4) + 40) + 'rpx);'
    } else {
        style += 'box-sizing: border-box; width: 100vw;';
    }
    return style;
})

const imageStyle3 = computed(() => {
    let style = 'border-radius:' + imageRounded.value.val + ';';
    return style;
})

//商品样式三
const itemStyle3 = ref('');
const setItemStyle3 = () => {
    // #ifdef MP-WEIXIN
    uni.createSelectorQuery().in(instance).select('#warpStyle3-' + diyComponent.value.id).boundingClientRect((res: any) => {
        uni.createSelectorQuery().in(instance).select('#item0' + diyComponent.value.id).boundingClientRect((data: any) => {
            itemStyle3.value = `margin-right:${ (res.width - data.width * 3) / 2 }px;`
        }).exec()
    }).exec()
    // #endif
    // #ifdef H5
    itemStyle3.value = 'margin-right:14rpx;'
    // #endif
}

const getGoodsListFn = () => {
    let data = {
		source: diyComponent.value.source,
        num: (diyComponent.value.source == 'all' || diyComponent.value.source == 'category') ? diyComponent.value.num : '',
        goods_ids:diyComponent.value.source == 'all' ? '' : diyComponent.value.goods_ids,
        goods_category: diyComponent.value.source == 'category' ? diyComponent.value.goods_category : '',
		city_id:systemStore.diyAddressInfo?.city_id
    }
    getGoodsComponents(data).then((res) => {
        goodsList.value = res.data;

        // 数据为空时隐藏整个组件
        // if(goodsList.value.length == 0 && diyComponent.value.pageStyle) {
        //     diyComponent.value.pageStyle = '';
        // }

        skeleton.loading = false;
        emits('loadingFn', res.data)
        if (diyComponent.value.componentBgUrl) {
            setTimeout(() => {
                const query = uni.createSelectorQuery().in(instance);
                query.select('.diy-shop-goods-list').boundingClientRect((data: any) => {
                    if (data) height.value = data.height;
                }).exec();
            }, 1000)
        }
        nextTick(() => {
            setTimeout(() => {
                if (diyComponent.value.style == 'style-3') setItemStyle3()
            }, 500)
        })

        goodsMaxBuy()
    });
}

/*************** 加入购物车 - start ***********************/
const goodsMaxBuy = () => {
    goodsList.value.forEach((data, index) => {
        data.isMaxBuy = false;

        let maxBuyNum = -1;
        // 限购 - 是否开启限购
        if (data.is_limit) {
            if (data.max_buy) {
                let max_buy = 0;
                if (data.limit_type == 1) { //单次限购
                    max_buy = data.max_buy;
                } else { // 单人限购
                    let buyVal = data.max_buy - (data.has_buy || 0);
                    max_buy = buyVal > 0 ? buyVal : 0;
                }

                if (max_buy > data.stock) {
                    maxBuyNum = data.stock
                } else if (max_buy <= data.stock) {
                    maxBuyNum = max_buy;
                }
            }
        }
        if (maxBuyNum == 0) {
            data.isMaxBuy = true;
        }
    })
}

// 商品价格
const goodsPrice = (data: any) => {
    let price = "0.00";
	price = data.goodsSku.show_price
    return price;
}

const animationAddCart = (row: any, id: any) => {
    if (cartRepeatFlag.value) return false
    cartRepeatFlag.value = true

    let obj: any = {
        goods_id: row.goodsSku.goods_id,
        sku_id: row.goodsSku.sku_id,
        sale_price: goodsPrice(row),
        stock: row.goodsSku.stock
    };
    if (row.id) {
        obj.num = row.num;
        obj.id = row.id;
    }

    // 起购
    let num = 1;
    if (row.min_buy > 0 && !row.num) {
        num = row.min_buy;
    } else {
        num = 1;
    }

}

//点击商品购物车按钮
const cartRef = ref()
const cartRepeatFlag = ref<Boolean>(false)
const itemCart = (row: any, id: any) => {
    if(diyStore.mode == 'decorate') return false
    // 虚拟商品，并且需要核销，禁止加入购物车
    if (row.goods_type == 'virtual' && row.virtual_receive_type == 'verify') {
        return toLink(row)
    }
    if (diyComponent.value.btnStyle.cartEvent !== 'cart') {
        return toLink(row)
    }

    if (!userInfo.value) {
        useLogin().setLoginBack({ url: '/addon/home_service/user/pages/index' })
        return false
    }

    cartRef.value.open(row.goodsSku.sku_id)

    // if (row.goodsSku.sku_spec_format) {
    //     cartRef.value.open(row.goodsSku.sku_id)
    // } else {
    //     //单规格添加购物车
    //     if (!row.goodsSku.stock || parseInt(row.goodsSku.num || 0) > parseInt(row.goodsSku.stock)) {
    //         uni.showToast({ title: '商品库存不足', icon: 'none' })
    //         return;
    //     }
    //     if (row.min_buy && row.min_buy > parseInt(row.stock)) {
    //         uni.showToast({ title: '商品库存小于起购数量', icon: 'none' })
    //         return;
    //     }
    //     animationAddCart(row, id)
    // }
}


//点击购物车加号 添加数量
const addCartBtn = (item: any, row: any, id: string) => {
    if(diyStore.mode == 'decorate') return false
    if (parseInt(row.num) >= parseInt(row.stock)) {
        uni.showToast({ title: '商品库存不足', icon: 'none' })
        return;
    }

    // 起购
    let num = row.num;
    if (item.min_buy > 0 && item.min_buy > row.num) {
        num = item.min_buy;
    }

    /************** 限购-start *****************/
    // let maxBuyNum = -1;
    // 限购 - 是否开启限购
    if (item.is_limit && item.max_buy) {
        let max_buy = 0;
        if (item.limit_type == 1) { //单次限购
            max_buy = item.max_buy;
        } else { // 单人限购
            let buyVal = item.max_buy - (item.has_buy || 0);
            max_buy = buyVal > 0 ? buyVal : 0;
        }

        // if(max_buy > item.goodsSku.stock){
        // 	maxBuyNum = item.goodsSku.stock
        // }else if(max_buy <= item.goodsSku.stock){
        // 	maxBuyNum = max_buy;
        // }
    }
    if (item.is_limit && num >= item.max_buy) {
        let tips = `该商品单次限购${ item.max_buy }件`;
        if (item.limit_type != 1) { //单次限购
            tips = `该商品每人限购${ item.max_buy }件`;
        }
        uni.showToast({ title: tips, icon: 'none' })
        return false;
    }
    /************** 限购-end *****************/

    let obj = cloneDeep(item)
    obj.num = num;
    obj.id = row.id;
    animationAddCart(obj, id)
}

//点击购物车减号
const reduceCart = (data: any, row: any) => {
    if (cartRepeatFlag.value || diyStore.mode == 'decorate') return false
    cartRepeatFlag.value = true

    let reduceNum = 1;
    if (data.min_buy > 0 && data.min_buy == row.num) {
        reduceNum = data.min_buy;
    }

}
/*************** 加入购物车 - end ***********************/

const initSkeleton = () => {
    if (diyComponent.value.style == 'style-1') {

        // 单列 风格
        skeleton.type = 'list'
        skeleton.config = {
            textRows: 2
        };
    } else if (diyComponent.value.style == 'style-2') {

        // 两列 风格
        skeleton.type = 'waterfall'
        skeleton.config = {
            headHeight: '320rpx',
            gridRows: 1,
            textRows: 2,
            textWidth: ['100%', '80%']
        };
    } else if (diyComponent.value.style == 'style-3') {

        // 横向滑动 风格
        skeleton.type = 'waterfall'
        skeleton.config = {
            gridRows: 1,
            gridColumns: 3,
            headHeight: '200rpx',
            textRows: 2,
            textWidth: ['100%', '80%']
        };
    }
}

const instance = getCurrentInstance();
const height = ref(0)

onMounted(() => {
    refresh();
    // 装修模式下刷新
    if (diyStore.mode == 'decorate') {
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {
                if (newValue && newValue.componentName == 'GoodsList') {
                    nextTick(() => {
                        const query = uni.createSelectorQuery().in(instance);
                        query.select('.diy-shop-goods-list').boundingClientRect((data: any) => {
                            if (data) height.value = data.height;
                        }).exec();
                        if (diyComponent.value.style == 'style-3') setItemStyle3()
                    })
                }
            }
        )
    } else {
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {
                refresh();
            },
            { deep: true }
        )
    }
});

const refresh = () => {
    // 装修模式下设置默认图
    if (diyStore.mode == 'decorate') {
        let obj = {
            goods_cover_thumb_small: "",
            goods_name: "商品名称",
            sale_num: "100",
            unit: "件",
            goodsSku: { show_price: 100 }
        };
        goodsList.value.push(obj);
        goodsList.value.push(obj);
        nextTick(() => {
            if (diyComponent.value.style == 'style-3') setItemStyle3()
        })
    } else {
        initSkeleton();
        getGoodsListFn();
    }

}

const toLink = (data: any) => {
    redirect({ url: '/addon/home_service/user/pages/goods/detail', param: { goods_id: data.goods_id } })
}
</script>
<style lang="scss" scoped>

.biserial-goods-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-gap: 10px;
}
.text-color {
    color: var(--primary-color);
}
</style>
