<template>
  <view class="agreement-checkbox">
    <view class="agreement-checkbox__inner">
      <u-checkbox-group>
        <u-checkbox
          activeColor="var(--recycle-brand, var(--primary-color))"
          :checked="modelValue"
          shape="circle"
          size="20"
          @change="setAgreement"
        />
      </u-checkbox-group>
      <view class="agreement-checkbox__content">
        <text class="agreement-checkbox__text" @tap="toggleAgreement">{{ agreementText }}</text>
        <text class="agreement-checkbox__link" @tap.stop="toAgreementPage">《{{ agreementTitle }}》</text>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
interface Props {
  modelValue: boolean
  agreementText?: string
  agreementKey: string
  agreementTitle?: string
}

const props = withDefaults(defineProps<Props>(), {
  agreementText: '我已阅读并同意',
  agreementTitle: '回收服务协议'
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const toggleAgreement = () => {
  emit('update:modelValue', !props.modelValue)
}

const setAgreement = (value: boolean) => {
  if (typeof value === 'boolean') {
    emit('update:modelValue', value)
    return
  }
  toggleAgreement()
}

const toAgreementPage = () => {
  uni.navigateTo({
    url: `/app/pages/auth/agreement?key=${props.agreementKey}`
  })
}
</script>

<style scoped lang="scss">
.agreement-checkbox {
  width: 100%;
  background: var(--recycle-bg-card, #fff);
}

.agreement-checkbox__inner {
  min-height: 56rpx;
  display: flex;
  align-items: center;
}

.agreement-checkbox__content {
  min-height: 56rpx;
  margin-left: 14rpx;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  font-size: 26rpx;
  line-height: 38rpx;
}

.agreement-checkbox__text {
  color: var(--recycle-text-sub, #666);
  padding: 10rpx 0;
}

.agreement-checkbox__link {
  color: var(--recycle-brand, var(--primary-color));
  padding: 10rpx 0;
  font-weight: 600;
}
</style>
