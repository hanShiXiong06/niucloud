<template>
  <view class="order-list-filters">
    <view class="status-filter">
      <scroll-view scroll-x class="status-filter__scroll" show-scrollbar="false">
        <view class="status-filter__track">
          <view
            v-for="item in statusFilterOptions"
            :key="item.value"
            class="status-filter__item"
            :class="{ active: currentStatus === item.value }"
            @tap="handleStatusTap(item.value)"
          >
            <text>{{ item.label }}</text>
            <text v-if="item.count !== undefined" class="status-filter__count">{{ item.count }}</text>
          </view>
        </view>
      </scroll-view>
    </view>

    <view class="filter-toolbar">
      <view class="delivery-filter">
        <view
          v-for="item in deliveryOptions"
          :key="item.value"
          class="filter-item"
          :class="{ active: deliveryType === item.value }"
          @tap="$emit('update:deliveryType', item.value)"
        >
          <text>{{ item.label }}</text>
        </view>
      </view>
      <view class="filter-search">
        <u-input
          :modelValue="searchKeyword"
          @update:modelValue="$emit('update:searchKeyword', $event)"
          placeholder="搜索订单号、快递单号、设备串号"
          border="surround"
          clearable
          @confirm="$emit('search')"
        >
          <template #suffix>
            <up-icon name="search" size="18" color="#94a3b8" @tap="$emit('search')" />
          </template>
        </u-input>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface StatusOption {
  label?: string
  value?: string
  text?: string
  key?: string
  count?: number
  actions?: number[]
}

interface Props {
  currentStatus: string
  deliveryType: number
  searchKeyword: string
  statusOptions: Array<StatusOption>
  deliveryOptions: Array<{ label: string; value: number }>
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:currentStatus': [value: string]
  'update:deliveryType': [value: number]
  'update:searchKeyword': [value: string]
  'search': []
}>()

const defaultStatusOptions: StatusOption[] = [
  { label: '全部', value: 'all' },
  { label: '待签收', value: '1' },
  { label: '已签收', value: '2' },
  { label: '质检中', value: '3' },
  { label: '已质检', value: '4' },
  { label: '待确认', value: '5' },
  { label: '待打款', value: '6' },
  { label: '已完成', value: '7' },
  { label: '已关闭', value: '8' },
  { label: '已取消', value: '9' }
]

const statusFilterOptions = computed(() => {
  const source = props.statusOptions.length ? props.statusOptions : defaultStatusOptions
  return source.map(item => ({
    ...item,
    label: item.label ?? item.text ?? '',
    value: item.value ?? item.key ?? ''
  })).filter(item => item.label && item.value)
})

const handleStatusTap = (value: string) => {
  emit('update:currentStatus', value)
}
</script>

<style scoped lang="scss">
.order-list-filters {
  overflow: hidden;
  border-radius: 16rpx;
  background: var(--recycle-bg-card);
  border: 1rpx solid var(--recycle-line);
  box-shadow: 0 8rpx 20rpx rgba(31, 41, 55, 0.06);
}

.status-filter {
  padding: 16rpx 0 12rpx;
}

.status-filter__scroll {
  width: 100%;
  white-space: nowrap;
}

.status-filter__track {
  display: inline-flex;
  align-items: center;
  gap: 12rpx;
  padding: 0 18rpx;
}

.status-filter__item {
  height: 60rpx;
  padding: 0 20rpx;
  display: inline-flex;
  align-items: center;
  gap: 8rpx;
  border-radius: 999rpx;
  background: var(--recycle-bg-soft);
  color: var(--recycle-text-sub);
  font-size: 24rpx;
  line-height: 60rpx;
  white-space: nowrap;

  &.active {
    color: var(--recycle-button-text);
    background: var(--recycle-button-bg);
    font-weight: 700;
    box-shadow: 0 6rpx 14rpx rgba(31, 41, 55, 0.12);
  }
}

.status-filter__count {
  min-width: 28rpx;
  height: 28rpx;
  padding: 0 8rpx;
  border-radius: 999rpx;
  background: rgba(255, 255, 255, 0.24);
  font-size: 20rpx;
  line-height: 28rpx;
  text-align: center;
}

.filter-toolbar {
  display: flex;
  align-items: center;
  gap: 14rpx;
  padding: 10rpx 18rpx 18rpx;
  border-top: 1rpx solid var(--recycle-line);
}

.delivery-filter {
  min-width: 0;
  display: flex;
  background: var(--recycle-bg-soft);
  border-radius: 6px;
  padding: 2px;
  gap: 2px;

  .filter-item {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    color: var(--recycle-text-sub);
    white-space: nowrap;

    &.active {
      background: var(--recycle-bg-card);
      color: var(--recycle-brand);
      font-weight: 600;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }
  }
}

.filter-search {
  min-width: 0;
  flex: 1;
}
</style>
