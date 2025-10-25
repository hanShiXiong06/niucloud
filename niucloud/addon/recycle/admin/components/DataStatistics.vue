<template>
  <div class="data-statistics mb-4">
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
      <!-- 头部标题 -->
      <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-blue-50 to-purple-50 border-b border-gray-100">
        <div class="flex items-center space-x-2">
          <div class="w-6 h-6 bg-blue-500 rounded-md flex items-center justify-center">
            <el-icon class="text-white text-sm"><DataAnalysis /></el-icon>
          </div>
          <h3 class="text-sm font-semibold text-gray-800">数据概况</h3>
        </div>
        <el-button 
          type="primary" 
          size="small" 
          @click="loadStatistics"
          :loading="loading"
          class="!h-7 !px-3 !text-xs"
        >
          <el-icon class="mr-1"><Refresh /></el-icon>
          刷新
        </el-button>
      </div>
      
      <!-- 统计数据 -->
      <div class="px-4 py-4">
        <div class="grid grid-cols-4 gap-4">
          <!-- 总消费金额 -->
          <div class="flex items-center space-x-3 p-3 bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg border border-green-100">
            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center shadow-sm">
              <el-icon class="text-white text-lg"><Money /></el-icon>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-lg font-bold text-gray-900 truncate">
                ¥{{ formatAmount(statisticsData.total_consumption) }}
              </div>
              <div class="text-xs text-gray-600">总消费金额</div>
            </div>
          </div>
          
          <!-- 查询总次数 -->
          <div class="flex items-center space-x-3 p-3 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-100">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center shadow-sm">
              <el-icon class="text-white text-lg"><Document /></el-icon>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-lg font-bold text-gray-900 truncate">
                {{ statisticsData.total_count }}
              </div>
              <div class="text-xs text-gray-600">查询总次数</div>
            </div>
          </div>
        
          <div class="">
            <div class="space-y-1">
              <label class="text-xs text-gray-600">时间范围</label>
              <el-date-picker
                v-model="filterForm.dateRange"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                @change="handleDateChange"
                size="small"
                class="!w-full"
              />
            </div>
            <div class="space-y-1">
              <label class="text-xs text-gray-600">查询类型</label>
              <el-select 
                v-model="filterForm.queryType" 
                placeholder="请选择查询类型" 
                @change="handleFilterChange"
                size="small"
                class="!w-full"
              >
                <el-option label="全部" value="" />
                <el-option label="IMEI查询" value="imei" />
                <el-option label="序列号查询" value="serial" />
                <el-option label="型号查询" value="model" />
                <el-option label="保修查询" value="coverage" />
                <el-option label="激活锁查询" value="activationlock" />
              </el-select>
            </div>
           
          </div>
          <div class="flex justify-end space-x-2 mt-3 pt-3  border-gray-200">
            <el-button size="small" @click="resetFilter" class="!h-7 !px-3 !text-xs">
              重置
            </el-button>
            <el-button type="primary" size="small" @click="applyFilter" class="!h-7 !px-3 !text-xs">
              应用筛选
            </el-button>
          </div>
        </div>
      </div>
      
    
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { DataAnalysis, Money, Document, Refresh, ArrowUp, ArrowDown } from '@element-plus/icons-vue'
import { getTotalConsumption } from '@/addon/recycle/api/device_query_result'

// 组件属性
interface Props {
  autoRefresh?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  autoRefresh: false
})

// 响应式数据
const loading = ref(false)
const showFilter = ref(false)

const statisticsData = reactive({
  total_consumption: 0,
  total_count: 0
})

const filterForm = reactive({
  dateRange: [] as string[],
  queryType: '',
  status: ''
})

// 发送事件给父组件
const emit = defineEmits(['filterChange'])

// 加载统计数据
const loadStatistics = async (filters?: any) => {
  loading.value = true
  try {
    const params = filters || {}
    
    // 添加日期范围参数 - 修复为后端期望的create_at格式
    if (filterForm.dateRange && filterForm.dateRange.length === 2) {
      params.create_at = filterForm.dateRange
    }
    
    // 添加其他筛选参数
    if (filterForm.queryType) params.query_type = filterForm.queryType
    if (filterForm.status !== '') params.status = filterForm.status
    
    const response: any = await getTotalConsumption(params)
    console.log('API响应:', response)
    
    // 修复数据层级问题
    if (response.code === 1 && response.data) {
      statisticsData.total_consumption = response.data.total_consumption || 0
      statisticsData.total_count = response.data.total_count || 0
    } else {
      ElMessage.error('获取统计数据失败')
    }
  } catch (error) {
    console.error('获取统计数据失败:', error)
    ElMessage.error('获取统计数据失败')
  } finally {
    loading.value = false
  }
}

// 格式化金额
const formatAmount = (amount: number): string => {
  return amount.toFixed(2)
}

// 处理日期变化
const handleDateChange = () => {
  if (props.autoRefresh) {
    applyFilter()
  }
}

// 处理筛选变化
const handleFilterChange = () => {
  if (props.autoRefresh) {
    applyFilter()
  }
}

// 应用筛选
const applyFilter = () => {
  const filters = {
    dateRange: filterForm.dateRange,
    queryType: filterForm.queryType,
    status: filterForm.status
  }
  
  // 刷新统计数据
  loadStatistics(filters)
  
  // 通知父组件筛选条件变化
  emit('filterChange', filters)
}

// 重置筛选
const resetFilter = () => {
  filterForm.dateRange = []
  filterForm.queryType = ''
  filterForm.status = ''
  
  loadStatistics()
  emit('filterChange', {})
}

// 暴露方法给父组件
defineExpose({
  loadStatistics,
  resetFilter
})

onMounted(() => {
  loadStatistics()
})
</script>

<style scoped>
/* 自定义样式补充 */
.data-statistics :deep(.el-button--small) {
  font-size: 12px;
}

.data-statistics :deep(.el-date-editor) {
  --el-date-editor-width: 100%;
}

.data-statistics :deep(.el-select) {
  width: 100%;
}

/* 响应式优化 */
@media (max-width: 1024px) {
  .data-statistics .grid-cols-2 {
    @apply grid-cols-1 gap-3;
  }
  
  .data-statistics .lg\\:grid-cols-3 {
    @apply grid-cols-1;
  }
}

@media (max-width: 640px) {
  .data-statistics .flex.items-center.space-x-3 {
    @apply space-x-2;
  }
  
  .data-statistics .w-10.h-10 {
    @apply w-8 h-8;
  }
  
  .data-statistics .text-lg {
    @apply text-base;
  }
}
</style> 