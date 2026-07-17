<template>
  <view class="order-list-filters">
    <view class="filter-search">
      <up-search
        :modelValue="searchKeyword"
        placeholder="订单号 / 快递单号 / 设备串号"
        :showAction="false"
        clearable
        bgColor="#f3f5f8"
        searchIconColor="#8b96a9"
        placeholderColor="#a6afbd"
        @update:modelValue="$emit('update:searchKeyword', $event)"
        @search="$emit('search')"
        @clear="$emit('search')"
      />
    </view>

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
      <text class="filter-toolbar__label">交付方式</text>
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
  background: #fff;
  border-bottom: 1rpx solid #edf0f4;
}

.filter-search {
  padding: 18rpx 24rpx 6rpx;
}

.status-filter {
  padding: 12rpx 0 10rpx;
}

.status-filter__scroll {
  width: 100%;
  white-space: nowrap;
}

.status-filter__track {
  display: inline-flex;
  align-items: center;
  gap: 10rpx;
  padding: 0 24rpx;
}

.status-filter__item {
  height: 58rpx;
  padding: 0 22rpx;
  display: inline-flex;
  align-items: center;
  gap: 8rpx;
  border-radius: 999rpx;
  border: 1rpx solid transparent;
  background: #f4f6f8;
  color: #5f6b7d;
  font-size: 24rpx;
  line-height: 58rpx;
  white-space: nowrap;

  &.active {
    color: var(--recycle-brand);
    border-color: rgba(59, 130, 246, 0.18);
    background: rgba(59, 130, 246, 0.09);
    font-weight: 700;
  }
}

.status-filter__count {
  min-width: 28rpx;
  height: 28rpx;
  padding: 0 8rpx;
  border-radius: 999rpx;
  color: inherit;
  background: rgba(255, 255, 255, 0.75);
  font-size: 20rpx;
  line-height: 28rpx;
  text-align: center;
}

.filter-toolbar {
  display: flex;
  align-items: center;
  gap: 16rpx;
  padding: 10rpx 24rpx 20rpx;
}

.filter-toolbar__label {
  color: #8b96a9;
  font-size: 22rpx;
  line-height: 52rpx;
  white-space: nowrap;
}

.delivery-filter {
  min-width: 0;
  display: flex;
  background: #f3f5f8;
  border-radius: 14rpx;
  padding: 4rpx;
  gap: 4rpx;

  .filter-item {
    min-width: 92rpx;
    height: 48rpx;
    padding: 0 18rpx;
    border-radius: 11rpx;
    font-size: 22rpx;
    line-height: 48rpx;
    color: #667085;
    text-align: center;
    white-space: nowrap;

    &.active {
      background: #fff;
      color: var(--recycle-brand);
      font-weight: 600;
      box-shadow: 0 2rpx 8rpx rgba(31, 41, 55, 0.07);
    }
  }
}
</style>
