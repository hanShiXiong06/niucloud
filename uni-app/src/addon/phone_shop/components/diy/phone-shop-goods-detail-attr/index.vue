<template>
    <view :style="warpCss" class="overflow-hidden">
        <view class="goods-sku card-template" :class="'attr-layout-' + layoutStyle" v-if="hasAttrs">
            <view class="attr-head">
                <view class="title">{{ diyComponent.title || '本机参数' }}</view>
                <text class="attr-count">{{ filteredAttrFormat.length }} 项</text>
            </view>
            <view class="attr-content">
                <template v-for="(item,index) in filteredAttrFormat" :key="index">
                    <view v-if="index < previewCount || isAttrFormatShow" class="card-template-item attr-item">
                        <text class="attr-name">{{ item.attr_value_name }}</text>
                        <view class="attr-value">{{ Array.isArray(item.attr_child_value_name) ? item.attr_child_value_name.join('、') : item.attr_child_value_name }}</view>
                    </view>
                </template>
                <view v-if="filteredAttrFormat.length > previewCount" class="attr-toggle flex-center" @click="isAttrFormatShow = !isAttrFormatShow">
                    <text class="text-[24rpx] mr-[10rpx]">{{ !isAttrFormatShow ? '展开' : '收起' }}</text>
                    <text class="nc-iconfont !text-[22rpx]" :class="{'nc-icon-xiaV6xx': !isAttrFormatShow, 'nc-icon-shangV6xx-1': isAttrFormatShow}"></text>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { img } from '@/utils/common';
import useDiyStore from '@/app/stores/diy';
import useGoodsDetailStore from '@/addon/phone_shop/stores/goodsDetail'

const props = defineProps(['component', 'index', 'value']);
const diyStore = useDiyStore();
const emits = defineEmits(['update:componentIsShow']);
const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        const obj = {
            goods: {
                attr_format: [
                    {
                       attr_value_name: '颜色',
                        attr_child_value_name: '红色,蓝色,灰色'
                    },{
                        attr_value_name: '尺寸',
                        attr_child_value_name: '大,中,小'
                    }
                ]
            }
        }
        return Object.assign({},obj,diyStore.value[props.index]);
    } else {
        return Object.assign({}, props.component, useGoodsDetailStore().goodsDetail);
    }
})

// 过滤后的属性数组（去掉空值项，包含重量和体积品牌）
const filteredAttrFormat = computed(() => {
    const originalAttrs = diyComponent.value.goods?.attr_format || [];
    // 第一步：过滤原始属性的空值项
    const filteredOriginalAttrs = originalAttrs.filter(item => {
        if (Array.isArray(item.attr_child_value_name)) {
            return item.attr_child_value_name.length > 0;
        } else {
            return !!item.attr_child_value_name;
        }
    });

    // 第二步：定义重量和体积属性项（保持和原有属性结构一致）
    const extraAttrs = [];
    if (diyComponent.value.goods.goods_brand) {
        extraAttrs.push({
            attr_value_name: '品牌',
            attr_child_value_name: diyComponent.value.goods.goods_brand.brand_name
        });
    }
    // 重量属性（非空时添加）
    if (diyComponent.value.weight && diyComponent.value.weight > 0) {
        extraAttrs.push({
            attr_value_name: '重量',
            attr_child_value_name: diyComponent.value.weight + 'kg'
        });
    }
    // 体积属性（非空时添加）
    if (diyComponent.value.volume && diyComponent.value.volume > 0) {
        extraAttrs.push({
            attr_value_name: '体积',
            attr_child_value_name: diyComponent.value.volume + 'm³'
        });
    }
    // 第三步：合并原始过滤后的属性 + 重量/体积属性
    return [...extraAttrs, ...filteredOriginalAttrs];
});
const hasAttrs = computed(() => filteredAttrFormat.value.length > 0);
const layoutStyle = computed(() => diyComponent.value.layoutStyle || 'list');
const previewCount = computed(() => Math.max(2, Number(diyComponent.value.previewCount || 4)));

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

const isAttrFormatShow = ref(diyComponent.value.defaultExpand === true); //控制属性是否展开

watch(() => diyComponent.value.defaultExpand, (value) => {
    isAttrFormatShow.value = value === true;
}, { immediate: true });

onMounted(() => {
    // 装修模式下刷新
    if (diyStore.mode == 'decorate') {
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {
                if (newValue && newValue.componentName == 'PhoneShopGoodsDetailAttr') {
                    nextTick(() => {})
                }
            }
        )
    } else {
        watch(
            () => diyComponent.value,
            (newValue, oldValue) => {
                if (newValue && newValue.componentName == 'PhoneShopGoodsDetailAttr') {
                    nextTick(() => {
                        if (diyComponent.value.goods && Object.keys(diyComponent.value.goods).length && (diyComponent.value.goods.attr_format && Object.keys(diyComponent.value.goods.attr_format).length || diyComponent.value.goods.goods_brand || diyComponent.value.weight >0|| diyComponent.value.volume >0)&& diyComponent.value.isShow) {
                            emits('update:componentIsShow', true)
                        }else{
                            emits('update:componentIsShow', false)
                        }
                    })
                }
            },
            { immediate: true }
        )
    }
});
</script>
<style lang="scss" scoped>
.card-template{
    background-color: transparent !important;
    border-radius: 0 !important;
}
.attr-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24rpx;
}
.attr-head .title { margin: 0; }
.attr-count { font-size: 22rpx; color: var(--text-color-light9); }
.attr-item { align-items: flex-start; }
.attr-name {
    width: 160rpx;
    flex-shrink: 0;
    font-size: 26rpx;
    line-height: 36rpx;
    color: var(--text-color-light9);
}
.attr-value {
    width: calc(100% - 160rpx);
    padding-left: 20rpx;
    box-sizing: border-box;
    font-size: 26rpx;
    line-height: 36rpx;
    color: #303133;
    text-align: right;
}
.attr-toggle { padding-top: 18rpx; color: var(--primary-color); }
.attr-layout-grid .attr-content {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12rpx;
}
.attr-layout-grid .attr-item {
    display: flex;
    flex-direction: column;
    padding: 18rpx;
    border: 0;
    border-radius: 14rpx;
    background: #f7f8fa;
}
.attr-layout-grid .attr-name,
.attr-layout-grid .attr-value {
    width: 100%;
    padding-left: 0;
    text-align: left;
}
.attr-layout-grid .attr-name { font-size: 22rpx; margin-bottom: 8rpx; }
.attr-layout-grid .attr-value { font-weight: 600; }
.attr-layout-grid .attr-toggle { grid-column: 1 / -1; }
</style>
