<template>
  <el-card class="mb-4" shadow="never" v-if="orderStatus >= 3">
    <template #header>
      <div class="flex justify-between items-center">
        <span class="font-bold">{{ t('auditSummary') }}</span>
        <div v-if="orderStatus === 3">
          <el-progress 
            :percentage="auditProgress" 
            :format="progressFormat" 
            :status="auditProgress === 100 ? 'success' : ''"
            :stroke-width="15"
            class="w-[200px]"
          ></el-progress>
        </div>
      </div>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="stats-card bg-blue-50">
        <div class="stats-title">{{ t('originalBooks') }}</div>
        <div class="stats-value">{{ totalBooks }} <span class="text-sm">本</span></div>
        <div class="stats-desc">原始预估金额: ¥{{ totalAmount.toFixed(2) }}</div>
      </div>
      
      <div class="stats-card bg-green-50">
        <div class="stats-title">{{ t('acceptedBooks') }}</div>
        <div class="stats-value">{{ acceptedBooks }} <span class="text-sm">本</span></div>
        <div class="stats-desc">接收金额: ¥{{ acceptedAmount.toFixed(2) }}</div>
      </div>
      
      <div class="stats-card bg-red-50">
        <div class="stats-title">{{ t('rejectedBooksCount') }}</div>
        <div class="stats-value">{{ rejectedBooks }} <span class="text-sm">本</span></div>
        <div class="stats-desc">拒收比例: {{ rejectedPercentage }}%</div>
      </div>
      
      <div class="stats-card bg-yellow-50">
        <div class="stats-title">{{ t('acceptanceRate') }}</div>
        <div class="stats-value">{{ acceptanceRate }}%</div>
        <div class="stats-desc">最终结算: ¥{{ finalAmount.toFixed(2) }}</div>
      </div>
    </div>
  </el-card>
</template>

<script lang="ts" setup>
import { computed, defineProps, defineEmits } from 'vue'
import { t } from '@/lang'

const props = defineProps({
  orderId: {
    type: Number,
    required: true
  },
  orderStatus: {
    type: Number,
    default: 1
  },
  books: {
    type: Array,
    default: () => []
  },
  rejectedBooksList: {
    type: Array,
    default: () => []
  },
  auditProgress: {
    type: Number,
    default: 0
  }
})

// 计算总书籍数量
const totalBooks = computed(() => {
  return props.books.reduce((sum: number, book: any) => sum + (book.quantity || 0), 0)
})

// 计算总金额
const totalAmount = computed(() => {
  return props.books.reduce((sum: number, book: any) => sum + (book.price * book.quantity || 0), 0)
})

// 计算已接收书籍数量
const acceptedBooks = computed(() => {
  return props.books.reduce((sum: number, book: any) => {
    if (book.final_price !== null && book.accepted_quantity !== null) {
      return sum + book.accepted_quantity
    }
    return sum
  }, 0)
})

// 计算已接收书籍金额
const acceptedAmount = computed(() => {
  return props.books.reduce((sum: number, book: any) => {
    if (book.final_price !== null && book.accepted_quantity !== null) {
      return sum + (book.final_price * book.accepted_quantity)
    }
    return sum
  }, 0)
})

// 计算拒收书籍数量
const rejectedBooks = computed(() => {
  return totalBooks.value - acceptedBooks.value
})

// 计算拒收百分比
const rejectedPercentage = computed(() => {
  if (totalBooks.value === 0) return 0
  return Math.round((rejectedBooks.value / totalBooks.value) * 100)
})

// 计算接收率
const acceptanceRate = computed(() => {
  if (totalBooks.value === 0) return 0
  return Math.round((acceptedBooks.value / totalBooks.value) * 100)
})

// 计算最终金额
const finalAmount = computed(() => {
  return acceptedAmount.value
})

// 格式化进度条
const progressFormat = (percentage: number) => {
  return percentage === 100 ? '审核完成' : `${percentage}%`
}
</script>

<style lang="scss" scoped>
.stats-card {
  padding: 16px;
  border-radius: 8px;
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.05);
  
  .stats-title {
    font-size: 14px;
    color: #606266;
    margin-bottom: 8px;
  }
  
  .stats-value {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 8px;
  }
  
  .stats-desc {
    font-size: 12px;
    color: #909399;
  }
}
</style> 