<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] overflow-hidden" :style="themeColor()" v-if="siteInfo">

        <!-- 站点头部卡片 -->
        <view class="site-header px-[32rpx] pt-[48rpx] pb-[72rpx]">
            <view class="flex items-center" @click="redirect({ url: '/app/pages/site/store'})">
                <up-image width="120rpx" height="120rpx" radius="24rpx" :src="img(siteInfo.front_end_logo)" model="aspectFill">
                    <template #error>
                        <image class="w-[120rpx] h-[120rpx] rounded-[24rpx]" :src="img('addon/wuxinggou/default_shop.png')" mode="aspectFill" />
                    </template>
                </up-image>
                <view class="flex-1 ml-[24rpx] overflow-hidden">
                    <text class="block text-[36rpx] font-600 text-[#fff] truncate">{{ siteInfo.site_name }}</text>
                </view>
                <view class="flex items-center flex-shrink-0">
                    <text class="text-[24rpx] text-[#fff] opacity-90 mr-[6rpx]">切换站点</text>
                    <text class="nc-iconfont nc-icon-youV6xx text-[28rpx] text-[#fff] opacity-90"></text>
                </view>
            </view>
        </view>

        <!-- 内容区（上移与头部叠压） -->
        <view class="content-pull px-[24rpx]">

            <!-- 账号信息 -->
            <view class="card">
                <u-cell
                    title="登录账号"
                    :value="userInfo ? userInfo.username : ''"
                    :isLink="true" :border="false"
                    @click="redirect({url: '/app/pages/site/account'})">
                    <template #icon>
                        <view class="cell-icon bg-style"><text class="nc-iconfont nc-icon-yonghuV6xx text-primary text-[32rpx]"></text></view>
                    </template>
                </u-cell>
            </view>

            <!-- 功能列表 -->
            <view class="card mt-[24rpx]" v-if="centerList.length">
                <u-cell-group :border="false">
                    <u-cell
                        v-for="(item,index) in centerList" :key="index"
                        :title="item.name"
                        :isLink="true"
                        :border="index !== 0"
                        @click="redirect({url: item.page})"></u-cell>
                </u-cell-group>
            </view>

            <!-- 关于 -->
            <view class="card mt-[24rpx]">
                <u-cell
                    title="关于"
                    :isLink="true" :border="false"
                    @click="redirect({url: '/app/pages/site/about'})">
                    <template #icon>
                        <view class="cell-icon bg-style"><text class="nc-iconfont nc-icon-xinxiV6xx text-primary text-[32rpx]"></text></view>
                    </template>
                </u-cell>
            </view>

            <!-- 退出登录 -->
            <view class="mt-[48rpx]">
                <u-button
                    text="退出登录"
                    color="#fff"
                    :customStyle="{ height: '92rpx', color: '#FF4D4F', fontSize: '30rpx', fontWeight: 500, borderRadius: '24rpx' }"
                    @click="popupShow = true"></u-button>
            </view>
        </view>

        <tabbar />

        <!-- 退出登录确认 -->
        <u-modal
            :show="popupShow"
            title="提示"
            content="是否退出登录?"
            :showCancelButton="true"
            confirmText="确认退出"
            cancelText="取消"
            @confirm="confirmLogout"
            @cancel="popupShow = false"></u-modal>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { redirect, img } from '@/utils/common';
import { getSiteCenter } from '@/app/api/site'
import useUserStore from '@/stores/user'

const userStore = useUserStore()
const siteInfo = computed(() => userStore.siteInfo)
const userInfo = computed(() => userStore.userInfo)

const centerList = ref([])
const getSiteCenterFn = () => {
    getSiteCenter().then((res:any) => {
        centerList.value = res.data
    })

}
// getSiteCenterFn()

const popupShow = ref(false)
const logout = ()=>{
    userStore.logout()
}
const confirmLogout = () => {
    popupShow.value = false
    logout()
}
</script>
<style lang="scss" scoped>
.site-header {
    background: var(--primary-color);
}
.content-pull {
    margin-top: -48rpx;
}
.card {
    background: #fff;
    border-radius: 24rpx;
    overflow: hidden;
}
.cell-icon {
    width: 60rpx;
    height: 60rpx;
    border-radius: 14rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20rpx;
}
.bg-style {
    background: linear-gradient(90deg, #E6FFF5 0%, #CEFFEA 100%);
}
</style>
