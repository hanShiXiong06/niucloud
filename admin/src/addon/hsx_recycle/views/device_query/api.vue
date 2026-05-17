<template>
  <div class="main-container">
    <el-card class="box-card !border-none" shadow="never">
      <div class="flex justify-between items-center mb-4">
        <div>
          <span class="text-page-title">设备查询日志与映射</span>
          <p class="text-sm text-gray-500 mt-1">查看查询记录、成本与渠道映射</p>
        </div>
      </div>

      <el-table :data="list" v-loading="loading" size="large">
        <el-table-column prop="service_code" label="查询项" min-width="160" />
        <el-table-column prop="service_name" label="名称" min-width="180" />
        <el-table-column prop="channel_name" label="渠道" min-width="160" />
        <el-table-column prop="query_code" label="查询码" min-width="180" />
        <el-table-column prop="query_type" label="类型" width="120" />
        <el-table-column prop="third_cost" label="成本" width="100">
          <template #default="{ row }">¥{{ row.third_cost }}</template>
        </el-table-column>
        <el-table-column prop="balance" label="余额" width="100">
          <template #default="{ row }">¥{{ row.balance }}</template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" min-width="180" />
      </el-table>
    </el-card>
  </div>
</template>

<script lang="ts" setup>
import { onMounted, ref } from 'vue'
import { getDeviceQueryResultList } from '@/addon/hsx_recycle/api/device_query_result'

const loading = ref(false)
const list = ref<any[]>([])

const loadList = async () => {
  loading.value = true
  try {
    const res = await getDeviceQueryResultList({ page: 1, limit: 20 })
    list.value = res.data?.data?.list || res.data?.data || []
  } finally {
    loading.value = false
  }
}

onMounted(loadList)
</script>
