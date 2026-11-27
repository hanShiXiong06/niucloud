<template>
  <view class="logistics-card">
    <view class="logistics-head">
      <view>
        <text class="logistics-title">物流车信息</text>
        <text class="logistics-subtitle">到站后由工作人员前往取货</text>
      </view>
    </view>

    <view class="field-grid">
      <view class="field-item">
        <text class="field-label">物流名称</text>
        <up-input :model-value="modelValue.logistics_name" border="none" placeholder="例如：石家庄至北京专线" @update:modelValue="updateField('logistics_name', $event)" />
      </view>
      <view class="field-item">
        <text class="field-label">车牌号</text>
        <up-input :model-value="modelValue.logistics_vehicle_no" border="none" placeholder="请输入车牌号" @update:modelValue="updateField('logistics_vehicle_no', $event)" />
      </view>
      <view class="field-item">
        <text class="field-label">联系人</text>
        <up-input :model-value="modelValue.logistics_contact_name" border="none" placeholder="司机或现场联系人" @update:modelValue="updateField('logistics_contact_name', $event)" />
      </view>
      <view class="field-item">
        <text class="field-label">联系电话</text>
        <up-input :model-value="modelValue.logistics_contact_mobile" type="number" border="none" placeholder="取货时联系" @update:modelValue="updateField('logistics_contact_mobile', $event)" />
      </view>
    </view>
    <view class="field-item field-item--address">
      <text class="field-label">取货地点</text>
      <up-textarea :model-value="modelValue.logistics_pickup_address" autoHeight border="none" maxlength="255" placeholder="写字楼、市场或物流点的具体位置" @update:modelValue="updateField('logistics_pickup_address', $event)" />
    </view>
    <view v-if="arrivalHint" class="arrival-hint">{{ arrivalHint }}</view>
  </view>
</template>

<script setup lang="ts">
const props = defineProps<{ modelValue: Record<string, string>; arrivalHint?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: Record<string, string>] }>()
const updateField = (key: string, value: string | number) => emit('update:modelValue', { ...props.modelValue, [key]: String(value ?? '') })
</script>

<style scoped lang="scss">
.logistics-card { margin-top: var(--recycle-order-section-gap, 12px); padding: 20px var(--recycle-order-gutter, 24px); background: var(--recycle-bg-card); }
.logistics-head { margin-bottom: 16rpx; }
.logistics-title, .logistics-subtitle { display: block; }.logistics-title { font-size: 30rpx; line-height: 42rpx; font-weight: 600; color: var(--recycle-text-main); }.logistics-subtitle { margin-top: 8rpx; font-size: 23rpx; line-height: 34rpx; color: var(--recycle-text-sub); }
.field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16rpx; }
.field-item { min-width: 0; padding: 18rpx 20rpx; background: var(--recycle-bg-soft); border-radius: 8rpx; }
.field-item--address { margin-top: 16rpx; }
.field-label { display: block; margin-bottom: 12rpx; font-size: 24rpx; line-height: 36rpx; color: var(--recycle-text-sub); }
.field-item :deep(.u-input), .field-item :deep(.u-textarea) { padding: 0 !important; background: transparent !important; }
.field-item :deep(.u-input__content__field-wrapper__field), .field-item :deep(.u-textarea__field) { color: var(--recycle-text-main); }
.arrival-hint { margin-top: 20rpx; padding: 16rpx 20rpx; border-radius: 8rpx; background: var(--recycle-notice-bg); color: var(--recycle-notice-text); font-size: 24rpx; line-height: 36rpx; }
</style>
