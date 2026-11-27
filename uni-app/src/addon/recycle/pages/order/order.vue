<template>
  <view class="redirect-page">
    <view class="loading-text">正在跳转...</view>
  </view>
</template>

<script setup lang="ts">
import { onLoad } from '@dcloudio/uni-app'

onLoad((options: any) => {
  const query = options || {}
  let queryStr = ''
  const keys = Object.keys(query)
  if (keys.length) {
    queryStr = '?' + keys.map(k => `${k}=${query[k]}`).join('&')
  }
  uni.reLaunch({
    url: `/addon/hsx_recycle/pages/order/order${queryStr}`
  })
})
</script>

<style scoped>
.redirect-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
}
.loading-text {
  color: #999;
  font-size: 28rpx;
}
</style>
