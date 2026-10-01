<template>
  <view v-if="visible" class="order-notice">
    <view class="notice-icon">
      <up-icon name="bell" size="14" color="var(--recycle-notice-text)"></up-icon>
    </view>
    <view class="notice-content">
      <text class="notice-title">{{ title }}</text>
      <text class="notice-text">{{ content }}</text>
      <view v-if="url" class="notice-link" role="button" @tap.stop="openLink">
        <text>{{ opening ? '正在打开…' : (linkText || '查看详情') }}</text>
        <up-icon name="arrow-right" size="12" color="var(--recycle-notice-text)" />
      </view>
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
  gap: 16rpx;
  padding: 20rpx;
  margin-bottom: 20rpx;
  background: var(--recycle-notice-bg);
  border: 1px solid rgba(245, 158, 11, 0.24);
  border-radius: 16rpx;
}

.notice-icon {
  flex-shrink: 0;
  width: 40rpx;
  height: 40rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(245, 158, 11, 0.12);
  border-radius: 50%;
}

.notice-content {
  flex: 1;
  min-width: 0;
}

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
  font-size: 12px;
  color: var(--recycle-text-main);
  line-height: 1.55;
  white-space: pre-wrap;
}
.notice-link { display: inline-flex; align-items: center; gap: 8rpx; margin-top: 12rpx; padding: 8rpx 0; color: var(--recycle-notice-text); font-size: 25rpx; font-weight: 600; }
</style>
