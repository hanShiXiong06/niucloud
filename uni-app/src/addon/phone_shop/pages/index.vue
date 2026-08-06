<template>
    <view :style="themeColor()">

        <loading-page :loading="diy.getLoading()"></loading-page>

        <view v-show="!diy.getLoading()">

            <!-- 自定义模板渲染 -->
            <view class="diy-template-wrap bg-index" :style="diy.pageStyle()">

                <diy-group ref="diyGroupRef" :data="diy.data" />

            </view>

        </view>

        <!-- #ifdef MP-WEIXIN -->
        <!-- 收藏小程序提示 -->
        <collect-tip ref="collectTipRef" ></collect-tip>
        <!-- 小程序隐私协议 -->
        <wx-privacy-popup ref="wxPrivacyPopupRef"></wx-privacy-popup>
        <!-- #endif -->

        <template v-if="diyStore && diyStore.mode == '' && diyStore.global && diyStore.global.bottomTabBar && diyStore.global.bottomTabBar.isShow">
            <tabbar :addon="diyStore.global.bottomTabBar.designNav?.key" />
        </template>

        <view v-if="aiAvailable && diyStore.mode == ''" class="ai-assistant-entry" @click="openAiAssistant">
            <text class="ai-assistant-entry__mark">AI</text>
            <text class="ai-assistant-entry__text">帮我选</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue';
import { useDiy } from '@/hooks/useDiy'
import { useShare } from '@/hooks/useShare'
import diyGroup from '@/addon/components/diy/group/index.vue'
import useDiyStore from '@/app/stores/diy'
import request from '@/utils/request'
import { redirect } from '@/utils/common'

const { setShare } = useShare()
const diy = useDiy({
    name: 'DIY_PHONE_SHOP_INDEX'
})
const diyStore = useDiyStore()
const diyGroupRef = ref(null)
const aiAvailable = ref(false)
let aiCapabilityChecked = false

const detectAiAssistant = async () => {
    if (aiCapabilityChecked) return
    aiCapabilityChecked = true
    try {
        const response: any = await request.get('ai/assistant/capability', {}, { showErrorMessage: false })
        aiAvailable.value = Boolean(response?.data?.available)
    } catch (_) {
        aiAvailable.value = false
    }
}
const openAiAssistant = () => redirect({ url: '/addon/hsx_ai/pages/chat/index' })

const wxPrivacyPopupRef: any = ref(null)
const collectTipRef: any = ref(null)
// 监听页面加载
diy.onLoad();

// 监听页面显示
diy.onShow((data: any) => {
    let share = data.share ? JSON.parse(data.share) : null;
    setShare(share);
    diyGroupRef.value?.refresh();
    detectAiAssistant()

    // #ifdef MP
    nextTick(() => {
        if (wxPrivacyPopupRef.value) wxPrivacyPopupRef.value.proactive();
        if (collectTipRef.value) collectTipRef.value.show();
    })
    // #endif
});

// 监听页面隐藏
diy.onHide();

// 监听页面卸载
diy.onUnload();

// 监听滚动事件
diy.onPageScroll()
</script>
<style lang="scss" scoped>
@import '@/styles/diy.scss';
</style>
<style lang="scss">
.diy-template-wrap {
    /* #ifdef MP */
    .child-diy-template-wrap {
        ::v-deep .diy-group {
            > .draggable-element.top-fixed-diy {
                display: block !important;
            }
        }
    }

    /* #endif */
}

.ai-assistant-entry {
    position: fixed;
    right: 24rpx;
    bottom: calc(132rpx + env(safe-area-inset-bottom));
    z-index: 80;
    box-sizing: border-box;
    width: 108rpx;
    height: 108rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2rpx solid rgba(255, 255, 255, .86);
    border-radius: 50%;
    background: #172033;
    color: #fff;
    box-shadow: 0 10rpx 28rpx rgba(23, 32, 51, .22);
}
.ai-assistant-entry__mark { font-size: 27rpx; font-weight: 700; line-height: 32rpx; }
.ai-assistant-entry__text { margin-top: 2rpx; font-size: 20rpx; line-height: 26rpx; }
</style>
