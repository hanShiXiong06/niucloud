<template>
    <template v-if="tabbar && Object.keys(tabbar).length">
        <u-tabbar :value="value" zIndex="9999" :fixed="true" :placeholder="true" :safeAreaInsetBottom="true" :inactive-color="tabbar.textColor" :active-color="tabbar.textHoverColor" class="custom-tabbar">
            
            <block v-for="item in tabbar.list">
                <u-tabbar-item class="py-[5rpx]" :custom-style="{'background-color': tabbar.backgroundColor}" :text="item.text" :icon="img(value == item.link.url ? item.iconSelectPath : item.iconPath)" :name="item.link.url" @click="itemBtn(item.link.url)"></u-tabbar-item>
            </block>
        </u-tabbar>
        <view class="tab-bar-placeholder"></view>
    </template>
</template>

<script setup lang="ts">
import { reactive, computed, watch, nextTick, getCurrentInstance } from 'vue'
import { redirect, currRoute, currShareRoute, img } from '@/utils/common'
import useConfigStore from '@/stores/config'

const tabbar: any = reactive({
    backgroundColor: '#fff', // 背景色
    textColor: '#a7a7a7', // 文字颜色
    textHoverColor: '#1F71EF', // 文字选中颜色
    list: []
})
tabbar.list = useConfigStore().tabbarList


watch(
    () => useConfigStore().tabbarList,
    (newValue, oldValue) => {
        if (newValue && newValue.length) {
            tabbar.list = useConfigStore().tabbarList
        }
    },
    { deep: true }
)


const value = computed(() => {
    let query: any = currShareRoute().params;
    let str = [];
    for (let key in query) {
        str.push(key + '=' + query[key]);
    }
    return '/' + currRoute() + (str.length > 0 ? '?' + str.join('&') : '')
})

const itemBtn = (url: any) => {
    if (url.indexOf('http') != -1 || url.indexOf('http') != -1) {

        // #ifdef H5
        window.location.href = url;
        // #endif

        // #ifdef MP
        redirect({
            url: '/app/pages/webview/index',
            param: { src: encodeURIComponent(url) }
        });
        // #endif
    } else {
        let query: any = currShareRoute().params;
        let str = [];
        for (let key in query) {
            str.push(key + '=' + query[key]);
        }
        if (url == ('/' + currRoute()) && !str.length) return
        redirect({ url, mode: 'reLaunch' })
    }
}

const instance = getCurrentInstance();
nextTick(() => {
    const query = uni.createSelectorQuery().in(instance);
    query.select('.tab-bar-placeholder').boundingClientRect((data: any) => {
        let height = data ? data.height : 0;
        let tabbarInfo = {
            height: height
        };
        uni.setStorageSync('tabbarInfo', tabbarInfo);
    }).exec();
})
</script>

<style lang="scss" scoped>
.tab-bar-placeholder {
    padding-bottom: calc(constant(safe-area-inset-bottom) + 50px);
    padding-bottom: calc(env(safe-area-inset-bottom) + 50px);
}
</style>

<style lang="scss">
.custom-tabbar {
    .u-tabbar-item__icon .u-icon__img {
        width: 19px !important;
        height: 19px !important;
    }

    .u-tabbar-item__text {
        font-size: 20rpx !important;
    }
}

/* #ifdef MP */
.u-tabbar {
    ::v-deep .u-tabbar-item__icon .u-icon .u-icon__img {
        width: 19px !important;
        height: 19px !important;
    }

    ::v-deep .u-tabbar-item__text {
        font-size: 20rpx !important;
    }
}

/* #endif */
</style>
