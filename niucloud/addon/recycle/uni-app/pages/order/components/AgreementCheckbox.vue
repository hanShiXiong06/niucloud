<template>
  <view class="bg-white rounded-lg  ">
    <view class="flex items-center">
      <u-checkbox-group>
        <u-checkbox
          activeColor="var(--primary-color)"
          :checked="modelValue"
          shape="circle"
          size="14"
          @change="handleChange"
        />
      </u-checkbox-group>
      <view class="flex items-center ml-2 text-sm">
        <text class="text-xs text-[#666]">{{ agreementText }}</text>
        <view @click="toAgreementPage" class="ml-1">
          <text class="text-primary">《{{ agreementTitle }}》</text>
        </view>
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

const handleChange = () => {
  emit('update:modelValue', !props.modelValue)
}

const toAgreementPage = () => {
  uni.navigateTo({
    url: `/app/pages/auth/agreement?key=${props.agreementKey}`
  })
}
</script>

<style scoped lang="scss">
.text-primary {
  color: var(--primary-color);
}
</style>
