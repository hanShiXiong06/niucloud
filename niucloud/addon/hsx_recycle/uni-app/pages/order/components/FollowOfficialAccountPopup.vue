<template>
  <OrderTaskPopup :show="visible" :title="title || '关注公众号'" height="70vh" @close="handleClose">
    <view class="contact-content">
      <text class="contact-description">{{ content || '关注公众号，接收订单进度通知' }}</text>
      <text v-if="wechatName" class="contact-name">{{ wechatName }}</text>
      <image v-if="qrCode" :src="img(qrCode)" mode="aspectFit" class="contact-qr" show-menu-by-longpress />
      <text class="contact-hint">{{ qrCode ? '长按二维码关注公众号' : '暂未配置二维码，请通过门店现有联系方式咨询' }}</text>
    </view>
    <template #footer><OrderUiButton block variant="primary" @click="handleClose">我知道了</OrderUiButton></template>
  </OrderTaskPopup>
</template>
<script setup lang="ts">
import { img } from '@/utils/common'
import OrderTaskPopup from './OrderTaskPopup.vue'
import OrderUiButton from './OrderUiButton.vue'

defineProps<{
  visible: boolean
  wechatName: string
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
.contact-content { padding: 24px 20px; display: flex; flex-direction: column; align-items: center; gap: 16px; }
.contact-description { font-size: 14px; line-height: 22px; color: var(--recycle-text-sub); text-align: center; }
.contact-name { font-size: 16px; line-height: 24px; font-weight: 600; }
.contact-qr { width: 200px; height: 200px; max-width: 100%; background: #fff; }
.contact-hint { font-size: 12px; line-height: 20px; text-align: center; color: var(--recycle-text-sub); }
</style>
