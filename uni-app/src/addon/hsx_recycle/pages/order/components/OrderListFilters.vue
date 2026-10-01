<template>
  <view class="order-list-filters">
    <view v-if="showSearch || searchKeyword" class="filter-search">
      <up-search
        :modelValue="searchKeyword"
        placeholder="订单号 / 快递单号 / 设备串号"
        :showAction="false"
        clearable
        bgColor="var(--recycle-bg-soft)" color="var(--recycle-text-main)"
        searchIconColor="var(--recycle-text-sub)"
        placeholderColor="var(--recycle-text-sub)"
        @update:modelValue="$emit('update:searchKeyword', $event)"
        @search="$emit('search')"
        @clear="clearSearch"
      />
    </view>

    <view class="status-filter">
      <scroll-view
        scroll-x
        scroll-with-animation
        :scroll-into-view="activeStatusId"
        class="status-filter__scroll"
        :show-scrollbar="false"
      >
        <view class="status-filter__track">
          <view
            v-for="(item, index) in statusFilterOptions"
            :key="item.value"
            :id="'order-status-' + index"
            class="status-filter__item"
            :class="{ active: currentStatus === item.value }"
            @tap="handleStatusTap(item.value)"
          >
            <text class="status-filter__label">{{ item.label }}</text>
            <text v-if="Number(item.count) > 0" class="status-filter__count">{{ item.count }}</text>
          </view>
        </view>
      </scroll-view>
      <button class="search-toggle" aria-label="搜索订单" @tap="showSearch = !showSearch">
        <up-icon name="search" size="20" color="var(--recycle-text-main)" />
      </button>
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
import { computed, ref } from 'vue'

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
const showSearch = ref(false)

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

const activeStatusId = computed(() => {
  const index = statusFilterOptions.value.findIndex(item => item.value === props.currentStatus)
  return index < 0 ? '' : `order-status-${index}`
})

const clearSearch = () => {
  emit('update:searchKeyword', '')
  emit('search')
}

const handleStatusTap = (value: string) => {
  emit('update:currentStatus', value)
}
</script>

<style scoped lang="scss">
.order-list-filters { background: var(--recycle-bg-card); border-bottom: 1rpx solid var(--recycle-line); }
.filter-search { padding: 20rpx var(--recycle-order-gutter, 24px) 8rpx; }
.status-filter { display: flex; align-items: center; padding-right: 8px; }
.status-filter__scroll { flex: 1; min-width: 0; width: 0; height: 48px; white-space: nowrap; }
.search-toggle { display: flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; height: 44px; margin: 0; padding: 0; border: 0; border-left: 1rpx solid var(--recycle-line); border-radius: 0; background: var(--recycle-bg-card); }
.search-toggle::after { border: 0; }
.status-filter__track { display: inline-flex; flex-wrap: nowrap; align-items: center; height: 48px; padding: 0 var(--recycle-order-gutter, 24px); gap: 24px; box-sizing: border-box; }
.status-filter__item { position: relative; display: inline-flex; flex: 0 0 auto; align-items: baseline; justify-content: center; gap: 4px; height: 48px; line-height: 48px; font-size: 14px; color: var(--recycle-text-sub); white-space: nowrap; }
// H5's uni-text sets pre-line, so the labels must override it directly.
.status-filter__label, .status-filter__count { flex: 0 0 auto; white-space: nowrap; }
.status-filter__item.active { color: var(--recycle-brand); font-weight: 600; }
.status-filter__item.active::after { content: ''; position: absolute; bottom: 5px; height: 3px; width: 20px; left: 50%; transform: translateX(-50%); border-radius: 2px; background: var(--recycle-brand); }
.status-filter__count { font-size: 11px; font-weight: 400; }
.filter-toolbar { display: flex; align-items: center; gap: 12px; padding: 8px var(--recycle-order-gutter, 24px) 12px; }
.filter-toolbar__label { flex-shrink: 0; font-size: 12px; color: var(--recycle-text-sub); white-space: nowrap; }
.delivery-filter { display: flex; flex: 1; min-width: 0; padding: 4rpx; border-radius: 10rpx; background: var(--recycle-bg-soft); }
.filter-item { flex: 1; min-width: 0; min-height: 30px; display: flex; align-items: center; justify-content: center; padding: 2px; font-size: 12px; color: var(--recycle-text-sub); border-radius: 8rpx; text-align: center; }
.filter-item text { white-space: nowrap; }
.filter-item.active { color: var(--recycle-brand); background: var(--recycle-bg-card); font-weight: 500; }
</style>
