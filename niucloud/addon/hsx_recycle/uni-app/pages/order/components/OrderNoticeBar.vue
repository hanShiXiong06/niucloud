<template>
  <view v-if="visible" class="order-notice">
    <view class="notice-icon">
      <up-icon name="bell" size="14" color="var(--recycle-notice-text)"></up-icon>
    </view>
    <view class="notice-content">
      <view class="notice-heading">
        <view class="notice-toggle" @tap="expanded = !expanded"><text class="notice-title">{{ title }}</text><up-icon :name="expanded ? 'arrow-up' : 'arrow-down'" size="12" color="var(--recycle-notice-text)" /></view>
        <view v-if="url" class="notice-link" role="button" @tap.stop="openLink">
          <text>{{ opening ? '正在打开…' : (linkText || '查看详情') }}</text>
          <up-icon name="arrow-right" size="12" color="var(--recycle-notice-text)" />
        </view>
      </view>
      <text class="notice-text" :class="{ 'notice-text--collapsed': !expanded }" @tap="expanded = !expanded">{{ content }}</text>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { redirect } from '@/utils/common'
import { getNeedLoginPages } from '@/utils/pages'
import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'
import { noticeTarget } from '../../../utils/notice-navigation'

const props = defineProps<{
  enabled: number
  title: string
  content: string
  url?: string
  linkText?: string
  returnUrl?: string
}>()

const visible = computed(() => !!props.enabled && !!String(props.content || '').trim())
const opening = ref(false)
const expanded = ref(false)
const openLink = () => {
  if (opening.value) return
  const target = noticeTarget(props.url || '', props.returnUrl)
  if (!target) return uni.showToast({ title: '跳转地址无效，请联系商家', icon: 'none' })
  if (!useMemberStore().token && getNeedLoginPages().includes(target.url)) {
    useLogin().setLoginBack(target)
    return
  }
  opening.value = true
  const failed = () => {
    opening.value = false
    uni.showToast({ title: '页面打开失败，请联系商家检查地址', icon: 'none' })
  }
  try {
    const external = /^https?:\/\//i.test(target.url)
    // H5 直接打开网页；小程序复用框架已有 webview，业务域名仍需管理员配置。
    // #ifdef H5
    if (external) { window.location.assign(target.url); opening.value = false; return }
    // #endif
    const destination = external
      ? { url: '/app/pages/webview/index', param: { src: encodeURIComponent(target.url) } }
      : target
    redirect({ ...destination, fail: failed, complete: () => { opening.value = false } })
  } catch { failed() }
}
</script>

<style scoped lang="scss">
.order-notice {
  display: flex;
  gap: 12rpx;
  padding: 12px var(--recycle-order-gutter, 24px);
  margin-bottom: var(--recycle-order-section-gap, 12px);
  background: var(--recycle-notice-bg);
}

.notice-icon {
  flex-shrink: 0;
  width: 28rpx;
  height: 44rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.notice-content {
  flex: 1;
  min-width: 0;
}

.notice-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0 20rpx; min-height: 44rpx; }

.notice-toggle { display: flex; align-items: center; min-height: 52rpx; gap: 10rpx; }
.notice-text--collapsed { overflow: hidden; text-overflow: ellipsis; white-space: nowrap !important; }
.notice-title {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--recycle-notice-text);
  line-height: 1.4;
}

.notice-text {
  display: block;
  margin-top: 6rpx;
  font-size: 24rpx;
  color: var(--recycle-text-sub);
  line-height: 1.55;
  white-space: pre-wrap;
}
.notice-link { display: inline-flex; align-items: center; flex-shrink: 0; gap: 6rpx; min-height: 44rpx; color: var(--recycle-notice-text); font-size: 23rpx; font-weight: 500; }
</style>
