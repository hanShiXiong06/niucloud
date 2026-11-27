<template>
    <view :style="warpCss" class="overflow-hidden" v-if="diyComponent && diyComponent.goods && Object.keys(diyComponent.goods).length">
        <view v-if="diyComponent.evaluate_is_show" class="card-template evaluate-card" :class="'evaluate-layout-' + layoutStyle">
            <view class="evaluate-head" :class="{'mb-[24rpx]': evaluate && evaluate.list && evaluate.list.length}">
                <view class="flex items-baseline min-w-0">
                    <text class="evaluate-title">{{ evaluateTitle }}</text>
                    <text class="evaluate-count">{{ evaluate.count || 0 }} 条</text>
                </view>
                <view v-if="evaluate.count" class="evaluate-more" @click="toLink(diyComponent.goods_id)">
                    <text class="text-[24rpx] text-[var(--text-color-light9)]">查看全部</text>
                    <text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light9)]"></text>
                </view>
            </view>
            <view v-if="evaluate.count && showSource && sourceText" class="evaluate-source">
                <u-icon name="checkmark-circle-fill" color="var(--primary-color)" size="16"></u-icon>
                <text class="evaluate-source-text">{{ sourceText }}</text>
            </view>
            <view v-if="evaluate.count">
                <view :class="{'pb-[34rpx]': index != (evaluate.list.length-1)}" v-for="(item, index) in evaluate.list" :key="index">
                    <view class="flex items-center w-full">
                        <u-avatar :default-url="img('static/resource/images/default_headimg.png')" :src="img(item.member_head)" :size="'50rpx'" leftIcon="none" />
                        <text class="ml-[10rpx] text-[28rpx] text-[#333]">{{ item.member_name }}</text>
                        <view v-if="item.is_verified_purchase" class="ml-[12rpx] px-[10rpx] h-[34rpx] flex-center rounded-[6rpx] bg-[var(--primary-color-light)]">
                            <text class="text-[20rpx] text-[var(--primary-color)]">真实购买</text>
                        </view>
                    </view>
                    <view v-if="item.category_name || item.goods_name"
                          class="mt-[12rpx] text-[22rpx] leading-[32rpx] text-[var(--text-color-light9)] truncate">
                        {{ item.category_name || item.goods_name }}{{ item.sku_name ? ` · ${item.sku_name}` : '' }}
                    </view>
                    <view class="flex justify-between w-full mt-[16rpx]">
                        <view class="flex-1 w-[540rpx] text-[26rpx] text-[#333] max-h-[72rpx] leading-[36rpx] multi-hidden mr-[50rpx]">{{ item.content }}</view>
                        <view class="w-[80rpx] shrink-0">
                            <up-image v-if="item.image_mid && item.image_mid.length" width="80rpx"
                                        height="80rpx" radius="var(--goods-rounded-mid)"
                                        :src="img(item.image_mid[0])" model="aspectFill"
                                        @click="imgListPreview(item.images[0])">
                                <template #error>
                                    <u-icon name="photo" color="#999" size="50"></u-icon>
                                </template>
                            </up-image>
                        </view>
                    </view>
                </view>
            </view>
            <view v-else class="evaluate-empty">
                <view class="evaluate-empty-icon">
                    <u-icon name="chat" color="var(--text-color-light9)" size="20"></u-icon>
                </view>
                <view class="min-w-0 flex-1">
                    <text class="evaluate-empty-title">{{ diyComponent.emptyText || '暂无真实成交评价' }}</text>
                    <text v-if="showSource && sourceText" class="evaluate-empty-desc">{{ sourceText }}</text>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { redirect, img } from '@/utils/common';
import useDiyStore from '@/app/stores/diy';
import { getEvaluateList } from '@/addon/phone_shop/api/goods';
import useGoodsDetailStore from '@/addon/phone_shop/stores/goodsDetail'

const props = defineProps(['component', 'index', 'value']);
const diyStore = useDiyStore();
const emits = defineEmits(['update:componentIsShow']); //商品数据加载完成之后触发
const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        const obj = {
            evaluate_is_show: true,
            goods: {goods_type: 'real'},
            goods_id: ''
        }
        return Object.assign({},obj,diyStore.value[props.index]);
    } else {
        return Object.assign({}, props.component, useGoodsDetailStore().goodsDetail);
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

// 获取评价
const evaluate = ref({
    count: 0,
    list: [],
    subject: {}
})
const layoutStyle = computed(() => diyComponent.value.layoutStyle || 'standard')
const showSource = computed(() => diyComponent.value.showSource !== false)
const evaluateTitle = computed(() => diyComponent.value.title || (evaluate.value.subject?.type === 'category' ? '同型号真实评价' : '宝贝评价'))
const sourceText = computed(() => {
    if (evaluate.value.subject?.type !== 'category' || !evaluate.value.subject?.category_path) return ''
    return `评价来自 ${ evaluate.value.subject.category_path } 的真实成交`
})

const getEvaluateListFn = () => {
    getEvaluateList(diyComponent.value.goods_id).then((res: any) => {
        evaluate.value = res.data
    })
}

//进入评论
const toLink = () => {
    redirect({ url: '/addon/phone_shop/pages/evaluate/list', param: { goods_id: diyComponent.value.goods_id } })
}

//预览图片
const imgListPreview = (item: any, index: any) => {
    if (Array.isArray(item)) {
        if (!item.length) return false
        var urlList = item;
        uni.previewImage({
            indicator: "number",
            current: index,
            loop: true,
            urls: urlList
        })
    } else {
        if (item === '') return false
        var urlList = []
        urlList.push(img(item))  //push中的参数为 :src="item.img_url" 中的图片地址
        uni.previewImage({
            indicator: "number",
            loop: true,
            urls: urlList
        })
    }

}

onMounted(() => {
    // 装修模式下刷新
    if (diyStore.mode == 'decorate') {
        evaluate.value = {
            list: [
                {
                    member_head: '',
                    member_name: '用户01',
                    content: '超级良心，值得信赖！',
                    image_mid: []
                },
                {
                    member_head: '',
                    member_name: '用户01',
                    content: '质量非常棒，服务也很到位！',
                    image_mid: []
                }
            ],
            count: 2
        }
    } else {
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {
                if (newValue && newValue.componentName == 'PhoneShopGoodsDetailEvaluate') {
                    if (!diyComponent.value.isShow) {
                        emits('update:componentIsShow', false)
                    }else{
                        emits('update:componentIsShow', true)
                    }
                }
            },
            { immediate: true }
        )
        // 获取评价
        getEvaluateListFn();
    }
});
</script>
<style lang="scss" scoped>
.card-template {
    background-color: transparent !important;
    border-radius: 0 !important;
}
.evaluate-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 44rpx;
}
.evaluate-title {
    overflow: hidden;
    color: #303133;
    font-size: 30rpx;
    font-weight: 600;
    line-height: 42rpx;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.evaluate-count {
    flex-shrink: 0;
    margin-left: 10rpx;
    color: var(--text-color-light9);
    font-size: 22rpx;
}
.evaluate-more { display: flex; align-items: center; flex-shrink: 0; }
.evaluate-source {
    display: flex;
    align-items: center;
    margin-bottom: 24rpx;
    padding: 12rpx 16rpx;
    border-radius: 12rpx;
    background: var(--temp-bg);
}
.evaluate-source-text {
    overflow: hidden;
    margin-left: 8rpx;
    color: var(--text-color-light6);
    font-size: 22rpx;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.evaluate-empty {
    display: flex;
    align-items: center;
    margin-top: 18rpx;
    padding: 20rpx;
    border-radius: 16rpx;
    background: #f7f8fa;
}
.evaluate-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60rpx;
    height: 60rpx;
    margin-right: 16rpx;
    border-radius: 50%;
    background: #fff;
}
.evaluate-empty-title,
.evaluate-empty-desc { display: block; }
.evaluate-empty-title { color: #606266; font-size: 25rpx; line-height: 34rpx; }
.evaluate-empty-desc {
    overflow: hidden;
    margin-top: 4rpx;
    color: var(--text-color-light9);
    font-size: 21rpx;
    line-height: 30rpx;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.evaluate-layout-compact {
    .evaluate-source { padding: 8rpx 12rpx; margin-bottom: 18rpx; }
    .evaluate-empty { margin-top: 12rpx; padding: 14rpx 16rpx; }
    .evaluate-empty-icon { width: 50rpx; height: 50rpx; }
}
</style>
