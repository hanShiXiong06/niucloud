<template>

   
      <up-tabs
        :list="tabsList"
        :current="currentTabIndex"
        @change="handleTabChange"
        lineColor="linear-gradient(to right, #4c8df8, #4c8df8)"
        :lineWidth="30"
        :lineHeight="3"
        :activeStyle="{
          color: '#4c8df8',
          fontWeight: 'bold',
          fontSize: '15px'
        }"
        :inactiveStyle="{
          color: '#606266',
          fontSize: '14px'
        }"
      ></up-tabs>
   

    <!-- 筛选和搜索栏 -->
    <view class="flex items-center gap-2 px-3 py-2 border-t border-gray-100">
      <!-- 配送方式筛选 -->
      <view class="delivery-filter">
        <view
          v-for="item in deliveryOptions"
          :key="item.value"
          class="filter-item"
          :class="{ active: deliveryType === item.value }"
          @click="$emit('update:deliveryType', item.value)"
        >
          <text>{{ item.label }}</text>
        </view>
      </view>

      <!-- 搜索框 -->
      <view class="flex-1">
        <u-input
          :modelValue="searchKeyword"
          @update:modelValue="$emit('update:searchKeyword', $event)"
          placeholder="搜索订单号/快递单号"
          border="surround"
          clearable
          @confirm="$emit('search')"
        >
          <template #suffix>
            <up-icon name="search" size="18" color="#94a3b8" @click="$emit('search')"></up-icon>
          </template>
        </u-input>
      </view>
    </view>


</template>

<script setup lang="ts">
import { computed } from 'vue'

interface StatusOption {
  label: string
  value: string  // 支持字符串类型: 'all', '1', '2', '3', '4', '5', '6', '7', '8', '9'
  count?: number
  actions?: number[]
}

interface Props {
  currentStatus: string  // 改为字符串类型
  deliveryType: number
  searchKeyword: string
  statusOptions: Array<StatusOption>
  deliveryOptions: Array<{ label: string; value: number }>
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:currentStatus': [value: string]  // 改为字符串类型
  'update:deliveryType': [value: number]
  'update:searchKeyword': [value: string]
  'search': []
}>()

// 转换为 up-tabs 需要的格式
const tabsList = computed(() => {
  return props.statusOptions.map(item => ({
    name: item.count !== undefined ? `${item.label}(${item.count})` : item.label,
    value: item.value
  }))
})

// 当前选中的 tab 索引
const currentTabIndex = computed(() => {
  return props.statusOptions.findIndex(item => item.value === props.currentStatus)
})

// 处理 tab 切换
const handleTabChange = (e: any) => {
  const selectedValue = props.statusOptions[e.index]?.value
  if (selectedValue !== undefined) {
    emit('update:currentStatus', selectedValue)
  }
}
</script>

<style scoped lang="scss">
.delivery-filter {
  display: flex;
  background: #f1f5f9;
  border-radius: 6px;
  padding: 2px;
  gap: 2px;

  .filter-item {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;

    &.active {
      background: #fff;
      color: #3b82f6;
      font-weight: 500;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }
  }
}
</style>
