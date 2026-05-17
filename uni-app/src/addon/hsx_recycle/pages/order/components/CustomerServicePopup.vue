<template>
  <up-popup
    :show="visible"
    mode="center"
    round="16"
    :close-on-click-overlay="true"
    @close="handleClose"
  >
    <view class="service-popup">
      <view class="popup-close" @tap="handleClose">
        <up-icon name="close" size="20" color="#999"></up-icon>
      </view>

      <view class="popup-title">
        <up-icon name="server-man" size="24" color="var(--recycle-brand)"></up-icon>
        <text class="title-text">{{ title || '联系客服' }}</text>
      </view>

      <view class="popup-desc">
        <text>{{ content || '如需议价或咨询订单进度，请联系客服处理' }}</text>
      </view>

      <view class="qr-code-wrapper">
        <image
          :src="img(qrCode)"
          mode="aspectFit"
          class="qr-code-image"
          show-menu-by-longpress
        />
        <view class="qr-tip">
          <text class="tip-text">长按识别二维码添加工作人员</text>
        </view>
      </view>

      <view class="popup-footer">
        <up-button
          type="primary"
          text="我知道了"
          shape="circle"
          @click="handleClose"
          custom-style="background: var(--recycle-button-bg); border: none; color: var(--recycle-button-text);"
        ></up-button>
      </view>
    </view>
  </up-popup>
</template>

<script setup lang="ts">
import { img } from '@/utils/common'

defineProps<{
  visible: boolean
  qrCode: string
  title?: string
  content?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const handleClose = () => {
  emit('close')
}
</script>

<style scoped lang="scss">
.service-popup {
  width: 580rpx;
  padding: 40rpx 30rpx 30rpx;
  position: relative;
  background: var(--recycle-bg-card);
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
    color: var(--recycle-text-main);
  }
}

.popup-desc {
  text-align: center;
  margin-bottom: 30rpx;

  text {
    font-size: 28rpx;
    color: var(--recycle-text-sub);
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
