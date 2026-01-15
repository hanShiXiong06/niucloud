<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] overflow-hidden" :style="themeColor()" v-if="webSite">
        <view class="py-[100rpx] bg-[#fff] flex flex-col items-center justify-center mb-[20rpx]">
            <image class="h-[90rpx] w-[300rpx]  rounded-[8rpx]" mode="aspectFit" :src="img(webSite?.front_end_logo)" @error="webSite.front_end_logo='static/resource/images/logo.png'"></image>
            <view class="mt-[20rpx] text-[26rpx] text-[#999]">{{ webSite.site_name }}</view>
        </view>
        <view class="px-[20rpx] bg-[#fff]">
            <view class="h-[100rpx] box-border flex items-center justify-between">
                <text>版本信息</text>
                <text v-if="versionInfo">{{ versionInfo.version.version }}</text>
            </view>
            <view class="h-[100rpx] box-border flex items-center justify-between border-0 border-t-[1rpx] border-solid border-[#ebebeb]" @click="callFn">
                <text>联系我们</text>
                <text v-if="serverInfo">{{ serverInfo.tel }}</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { img } from '@/utils/common'
import useSystemStore from '@/stores/system'
import { onLoad, onShow } from '@dcloudio/uni-app'

import { getService, getVersions } from '@/app/api/system'

const systemStore = useSystemStore()

const webSite = computed(() => {
    return systemStore.website
})

const serverInfo = ref<any>(null)

const  getServiceFn = () => {
    getService().then((res: any) => {
        serverInfo.value = res.data
    })
}
getServiceFn()

const versionInfo = ref<any>(null)
const getVersionsFn = () => {
    getVersions().then((res: any) => {
        versionInfo.value = res.data
    })
}
getVersionsFn()

const callFn = () => {
    if(serverInfo.value.tel){
        uni.makePhoneCall({ phoneNumber: serverInfo.value.tel })
    }
}
</script>

<style scoped>

</style>