<template>
  <view class="recycle-member-page" :style="themeVars">
    <RecyclePageHeader title="个人中心" subtitle="管理订单、资料和回收进度" :show-back="false" />

    <u-loading-page :loading="diy.getLoading()" loadingText="" bg-color="#f7f7f7" />

    <view v-show="!diy.getLoading()">
      <view class="member-fallback">
        <view class="member-hero">
          <view class="member-avatar">
            <up-icon name="account" size="34" color="var(--recycle-button-text)"></up-icon>
          </view>
          <view class="member-profile">
            <text class="member-name">{{ displayName }}</text>
            <text class="member-sub">回收订单、收款资料、退货进度统一管理</text>
          </view>
        </view>

        <view class="quick-grid">
          <view class="quick-item primary" @click="goTo('/addon/recycle/pages/order/order')">
            <up-icon name="plus-circle" size="24" color="var(--recycle-button-text)"></up-icon>
            <text>立即下单</text>
          </view>
          <view class="quick-item" @click="goTo('/addon/recycle/pages/order/list')">
            <up-icon name="order" size="24" color="var(--recycle-brand)"></up-icon>
            <text>我的订单</text>
          </view>
          <view class="quick-item" @click="goTo('/addon/recycle/pages/price')">
            <up-icon name="rmb-circle" size="24" color="var(--recycle-brand)"></up-icon>
            <text>查看报价</text>
          </view>
          <view class="quick-item" @click="goTo('/addon/recycle/pages/payment/index')">
            <up-icon name="red-packet" size="24" color="var(--recycle-brand)"></up-icon>
            <text>收款资料</text>
          </view>
        </view>

        <view class="member-menu">
          <view class="menu-row" @click="goTo('/addon/recycle/pages/return_order/list')">
            <view class="menu-left">
              <up-icon name="file-text" size="20" color="var(--recycle-brand)"></up-icon>
              <text>退货订单</text>
            </view>
            <up-icon name="arrow-right" size="16" color="var(--recycle-text-sub)"></up-icon>
          </view>
          <view class="menu-row" @click="goTo('/addon/recycle/pages/order/list')">
            <view class="menu-left">
              <up-icon name="clock" size="20" color="var(--recycle-brand)"></up-icon>
              <text>处理进度</text>
            </view>
            <up-icon name="arrow-right" size="16" color="var(--recycle-text-sub)"></up-icon>
          </view>
        </view>
      </view>

      <!-- 自定义模板渲染 -->
      <view class="diy-template-wrap bg-index" v-if="diy.data.pageMode != 'fixed'" :style="diy.pageStyle()">

        <diy-group ref="diyGroupRef" :data="diy.data" :pullDownRefreshCount="diy.pullDownRefreshCount" />

      </view>

      <!-- 固定模板渲染 -->
      <!--<view class="fixed-template-wrap" v-if="diy.data.pageMode == 'fixed'">-->

      <!--  <fixed-group :data="diy.data" :pullDownRefreshCount="diy.pullDownRefreshCount" />-->

      <!--</view>-->

    </view>

    <!-- #ifdef MP-WEIXIN -->
    <!-- 小程序隐私协议 -->
    <wx-privacy-popup ref="wxPrivacyPopupRef"></wx-privacy-popup>
    <!-- #endif -->

  </view>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import { useDiy } from '@/hooks/useDiy'
import diyGroup from '@/addon/components/diy/group/index.vue'
// import fixedGroup from '@/addon/components/fixed/group/index.vue'
import useMemberStore from '@/stores/member'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'
import { useRecyclePageTheme } from '../../hooks/useRecyclePageTheme'

// 会员信息
const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info)
const displayName = computed(() => userInfo.value?.nickname || userInfo.value?.username || userInfo.value?.mobile || '回收用户')
const { themeVars, loadTheme } = useRecyclePageTheme()

const diy = useDiy({
  name: 'DIY_RECYCLE_MEMBER_INDEX'
})

const diyGroupRef = ref(null)
const wxPrivacyPopupRef: any = ref(null)

const goTo = (url: string) => {
  uni.navigateTo({ url })
}

// 监听页面加载
diy.onLoad();

// 监听页面显示
diy.onShow((data: any) => {
  loadTheme()
  diyGroupRef.value?.refresh();
  if (userInfo.value) {
    useMemberStore().getMemberInfo()
  }
  // #ifdef MP
  nextTick(() => {
    if (wxPrivacyPopupRef.value) wxPrivacyPopupRef.value.proactive();
  })
  // #endif
});

// 监听页面卸载
diy.onUnload();
// 监听页面隐藏
diy.onHide();
// 监听下拉刷新事件
// diy.onPullDownRefresh()

// 监听滚动事件
diy.onPageScroll()
</script>
<style lang="scss" scoped>
@import '@/styles/diy.scss';

.recycle-member-page {
  min-height: 100vh;
  background: var(--recycle-bg-main);
  color: var(--recycle-text-main);
}

.member-fallback {
  padding: 20rpx 20rpx 0;
}

.member-hero {
  display: flex;
  align-items: center;
  gap: 20rpx;
  padding: 30rpx 26rpx;
  border-radius: 18rpx;
  background: linear-gradient(100deg, var(--recycle-button-bg) 0%, var(--recycle-brand-deep) 58%, var(--recycle-brand) 100%);
  color: var(--recycle-button-text);
  box-shadow: 0 10rpx 24rpx rgba(31, 41, 55, 0.12);
}

.member-avatar {
  width: 86rpx;
  height: 86rpx;
  border-radius: 43rpx;
  background: rgba(255, 255, 255, 0.14);
  display: flex;
  align-items: center;
  justify-content: center;
}

.member-profile {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.member-name {
  font-size: 34rpx;
  line-height: 46rpx;
  font-weight: 800;
}

.member-sub {
  margin-top: 6rpx;
  font-size: 23rpx;
  line-height: 34rpx;
  color: rgba(255, 255, 255, 0.78);
}

.quick-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16rpx;
  margin-top: 18rpx;
}

.quick-item {
  min-height: 120rpx;
  padding: 20rpx;
  border-radius: 16rpx;
  background: var(--recycle-bg-card);
  border: 1rpx solid var(--recycle-line);
  box-shadow: 0 8rpx 20rpx rgba(31, 41, 55, 0.06);
  display: flex;
  align-items: center;
  gap: 14rpx;
  color: var(--recycle-text-main);
  font-size: 28rpx;
  font-weight: 800;
}

.quick-item.primary {
  background: var(--recycle-button-bg);
  color: var(--recycle-button-text);
}

.member-menu {
  margin-top: 18rpx;
  border-radius: 16rpx;
  background: var(--recycle-bg-card);
  border: 1rpx solid var(--recycle-line);
  overflow: hidden;
}

.menu-row {
  min-height: 96rpx;
  padding: 0 22rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1rpx solid var(--recycle-line);
}

.menu-row:last-child {
  border-bottom: none;
}

.menu-left {
  display: flex;
  align-items: center;
  gap: 14rpx;
  color: var(--recycle-text-main);
  font-size: 27rpx;
  font-weight: 700;
}
</style>
<style lang="scss">
.diy-template-wrap {

  /* #ifdef MP */
  .child-diy-template-wrap {
    ::v-deep .diy-group {
      >.draggable-element.top-fixed-diy {
        display: block !important;
      }
    }
  }

  /* #endif */
}
</style>
