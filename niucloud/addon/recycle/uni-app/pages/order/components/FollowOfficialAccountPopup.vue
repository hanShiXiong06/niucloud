<template>
  <up-popup
    :show="visible"
    mode="center"
    round="16"
    :close-on-click-overlay="true"
    @close="handleClose"
  >
    <view class="follow-popup">
      <!-- 关闭按钮 -->
      <view class="popup-close" @tap="handleClose">
        <up-icon name="close" size="20" color="#999"></up-icon>
      </view>

      <!-- 标题 -->
      <view class="popup-title">
        <up-icon name="bell" size="24" color="#8C7575"></up-icon>
        <text class="title-text">关注公众号</text>
      </view>

      <!-- 提示文字 -->
      <view class="popup-desc">
        <text>关注公众号，及时接收订单状态通知</text>
      </view>

      <!-- 公众号名称 -->
      <view v-if="wechatName" class="wechat-name">
        <text class="name-text">{{ wechatName }}</text>
      </view>

      <!-- 二维码图片 -->
      <view class="qr-code-wrapper">
        <image
          :src="qrCode"
          mode="aspectFit"
          class="qr-code-image"
          show-menu-by-longpress
        />
        <view class="qr-tip">
          <text class="tip-text">长按识别二维码关注</text>
        </view>
      </view>

      <!-- 底部按钮 -->
      <view class="popup-footer">
        <up-button
          type="primary"
          text="我知道了"
          shape="circle"
          @click="handleClose"
          custom-style="background: linear-gradient(to right, #8C7575, #5F758A); border: none;"
        ></up-button>
      </view>
    </view>
  </up-popup>
</template>

<script setup lang="ts">
defineProps<{
  visible: boolean
  wechatName: string
  qrCode: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const handleClose = () => {
  emit('close')
}
</script>

<style scoped lang="scss">
.follow-popup {
  width: 580rpx;
  padding: 40rpx 30rpx 30rpx;
  position: relative;
  background: #fff;
  border-radius: 16rpx;
}

.popup-close {
  position: absolute;
  top: 20rpx;
  right: 20rpx;
  padding: 10rpx;
  z-index: 1;
}

.popup-title {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12rpx;
  margin-bottom: 16rpx;

  .title-text {
    font-size: 36rpx;
    font-weight: 600;
    color: #333;
  }
}

.popup-desc {
  text-align: center;
  margin-bottom: 30rpx;

  text {
    font-size: 28rpx;
    color: #666;
  }
}

.wechat-name {
  text-align: center;
  margin-bottom: 20rpx;

  .name-text {
    font-size: 30rpx;
    font-weight: 500;
    color: #8C7575;
  }
}

.qr-code-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 30rpx;

  .qr-code-image {
    width: 360rpx;
    height: 360rpx;
    border: 2rpx solid #eee;
    border-radius: 12rpx;
  }

  .qr-tip {
    margin-top: 16rpx;

    .tip-text {
      font-size: 24rpx;
      color: #999;
    }
  }
}

.popup-footer {
  padding: 0 20rpx;
}
</style>
