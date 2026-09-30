<template>
    <view :style="themeColor()">
        <PhoneGoodsAccessState v-if="!accessReady" :status="accessStatus" :message="accessMessage" @retry="loadCategoryPage" />
        <view v-if="hasContent" v-show="accessReady">
        <!-- #ifdef MP-WEIXIN || APP-PLUS -->
        <top-tabbar :data="topTabbarData" scrollBool="1" :isBack="false" v-if="!config.search?.control" />
        <!-- #endif -->
        <view class="relative">
            <category-template-one-one class="category" v-if="config.level===1 && config.template === 'style-1'" :categoryId="categoryId" :config="config" />
            <category-template-two-one v-if="config.level===2 && config.template === 'style-1'" :categoryId="categoryId" :config="config" />
            <category-template-two-two class="category" v-if="config.level===2 && config.template === 'style-2'" :categoryId="categoryId" :config="config" />
            <category-template-three-one v-if="config.level===3 && config.template === 'style-1'" :categoryId="categoryId" :config="config" />
        </view>
        <tabbar addon="phone_shop"/>
        <GoodsArrivalSubscription v-if="accessReady" back-url="/addon/phone_shop/pages/goods/category" :back-params="entryParams" />
        </view>
    </view>
</template>
<script setup lang="ts">
import { onLoad, onShow } from '@dcloudio/uni-app'
import { ref } from 'vue';
import categoryTemplateTwoOne from '@/addon/phone_shop/pages/goods/components/category-template-two-one.vue';
import categoryTemplateOneOne from '@/addon/phone_shop/pages/goods/components/category-template-one-one.vue';
import categoryTemplateTwoTwo from '@/addon/phone_shop/pages/goods/components/category-template-two-two.vue';
import categoryTemplateThreeOne from '@/addon/phone_shop/pages/goods/components/category-template-three-one.vue';
import PhoneGoodsAccessState from '@/addon/phone_shop/components/PhoneGoodsAccessState.vue';
import GoodsArrivalSubscription from '@/addon/phone_shop/components/GoodsArrivalSubscription.vue';
import { useGoodsPageAccess } from '@/addon/phone_shop/hooks/useGoodsPageAccess';
import { handleOnloadParams } from '@/utils/common';
import { topTabar } from '@/utils/topTabbar';

const config: any = ref({
})
const categoryId = ref(0)
const entryParams = ref<Record<string, any>>({})
const access = useGoodsPageAccess(() => ({url: '/addon/phone_shop/pages/goods/category', param: entryParams.value}))
const {ready: accessReady, status: accessStatus, message: accessMessage, hasContent} = access


/********* 自定义头部 - start ***********/
const topTabarObj = topTabar()
let topTabbarData = ref(topTabarObj.setTopTabbarParam({ title: '商品分类', topStatusBar: { textColor: '#333' }}))
/********* 自定义头部 - end ***********/

const loadCategoryPage = async() => {
    if (!await access.check()) return
    // 保持同一响应式对象，避免子组件持有旧配置或返回后丢失滚动位置。
    Object.assign(config.value, access.config.value)
    topTabbarData.value.title = config.value.page_title || '商品分类'
    uni.setNavigationBarTitle({title: topTabbarData.value.title})
}

onLoad((options: any) => {
    // #ifdef MP-WEIXIN
    options = handleOnloadParams(options);
    // #endif
    entryParams.value = {...options}
    categoryId.value = options.category_id || 0;
});

onShow(() => { void loadCategoryPage() })

</script>
<style>

/*  #ifdef  H5  */
:deep(.category .detail .mescroll-body) {
    padding-bottom: calc(50px + constant(safe-area-inset-bottom)) !important;
    padding-bottom: calc(50px + env(safe-area-inset-bottom)) !important;
}

:deep(.category .cart .mescroll-body) {
    padding-bottom: calc(100rpx + 50px + constant(safe-area-inset-bottom)) !important;
    padding-bottom: calc(100rpx + 50px + env(safe-area-inset-bottom)) !important;
}

/*  #endif  */
/*  #ifndef  H5  */
.category .detail .mescroll-body {
    padding-bottom: calc(100rpx + constant(safe-area-inset-bottom)) !important;
    padding-bottom: calc(100rpx + env(safe-area-inset-bottom)) !important;
}

.category .cart .mescroll-body {
    padding-bottom: calc(200rpx + constant(safe-area-inset-bottom)) !important;
    padding-bottom: calc(200rpx + env(safe-area-inset-bottom)) !important;
}

/*
.category .labelPopup :deep(.u-fade-enter-active) {
	top: 92rpx !important;
	left: 166rpx !important;
	z-index: 8 !important;
}
.category .labelPopup :deep(.u-slide-down-enter-active){
	top: 92rpx !important;
	left: 165rpx !important;
	z-index: 8 !important;
}
.category .labelPopup.active :deep(.u-fade-enter-active) {
	top: 190rpx !important;

}
.category .labelPopup.active :deep(.u-slide-down-enter-active){
	top: 190rpx !important;
}
.category .labelPopup :deep(.u-fade-enter-to){
	top: 92rpx !important;
	left: 166rpx !important;
	z-index: 8 !important;
}
.category .labelPopup :deep(.u-slide-down-leave-to){
	top: 92rpx !important;
	left: 165rpx !important;
	z-index: 8 !important;
} */
.category .labelPopup :deep(.u-transition) {
    top: 96rpx !important;
    left: 170rpx !important;
    z-index: 8 !important;

    &:nth-child(2) {
        left: 168rpx !important;
    }
}

.category .labelPopup.active :deep(.u-transition) {
    top: 190rpx !important;
}

/*  #endif  */

</style>
<style lang="scss" scoped>
:deep(.mescroll-upwarp) {
    box-sizing: border-box;
    padding-left: 182rpx;
}

:deep(.tab-bar-placeholder) {
    display: none !important;
}

:deep(.u-tabbar__placeholder) {
    display: none !important;
}

button::after {
    border: 0 !important;
}

:deep(.part .mescroll-empty) {
    width: 542rpx;
    height: 542rpx;
    margin-top: 0;
    margin-left: 0;
    margin-right: 0;
    padding-top: 50rpx;
}
</style>
