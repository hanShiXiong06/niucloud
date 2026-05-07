<template>
  <div class="main-container device-query-result-page">
    <el-card class="box-card !border-none" shadow="never">
      <div class="page-head">
        <div>
          <span class="text-page-title">设备查询结果</span>
          <div class="page-desc">查看每一次设备查询的渠道、费用、余额、响应耗时和第三方返回内容。</div>
        </div>
        <el-button :loading="loading" @click="loadList">刷新</el-button>
      </div>

      <el-row :gutter="14" class="stat-row">
        <el-col :span="6">
          <div class="stat-cell">
            <span>总查询数</span>
            <strong>{{ overview.total_queries || 0 }}</strong>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-cell">
            <span>成功率</span>
            <strong>{{ overview.success_rate || 0 }}%</strong>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-cell">
            <span>总花费</span>
            <strong>¥{{ formatMoney(overview.total_cost) }}</strong>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-cell">
            <span>失败数</span>
            <strong>{{ overview.failed_queries || 0 }}</strong>
          </div>
        </el-col>
      </el-row>

      <el-form :inline="true" :model="queryParams" class="search-form">
        <el-form-item label="查询码">
          <el-input v-model.trim="queryParams.query_code" clearable placeholder="输入 IMEI 或序列号" />
        </el-form-item>
        <el-form-item label="接口">
          <el-input v-model.trim="queryParams.api_endpoint" clearable placeholder="/honor/coverage" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" clearable class="w-[140px]">
            <el-option label="查询成功" :value="1" />
            <el-option label="查询失败" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="时间">
          <el-date-picker
            v-model="queryParams.create_at"
            type="daterange"
            value-format="YYYY-MM-DD"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadList">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" v-loading="loading" size="large" border>
        <el-table-column prop="query_code" label="查询码" min-width="170" />
        <el-table-column prop="service_name" label="查询项" min-width="150" />
        <el-table-column prop="channel_name" label="渠道" min-width="140" />
        <el-table-column prop="api_endpoint" label="接口" min-width="150" />
        <el-table-column prop="query_type_name" label="类型" width="110" />
        <el-table-column prop="cost_amount" label="费用" width="100">
          <template #default="{ row }">¥{{ formatMoney(row.cost_amount) }}</template>
        </el-table-column>
        <el-table-column prop="balance" label="余额" width="110">
          <template #default="{ row }">¥{{ formatMoney(row.balance) }}</template>
        </el-table-column>
        <el-table-column prop="response_time" label="耗时" width="110">
          <template #default="{ row }">{{ row.response_time || 0 }}ms</template>
        </el-table-column>
        <el-table-column prop="status_name" label="状态" width="110">
          <template #default="{ row }">
            <el-tag :type="Number(row.status) === 1 ? 'success' : 'danger'">{{ row.status_name }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="create_at_text" label="时间" min-width="170">
          <template #default="{ row }">{{ row.create_at_text || row.create_at }}</template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="detailVisible" title="查询详情" width="980px" destroy-on-close>
      <div v-loading="detailLoading" class="detail-wrap">
        <template v-if="detailInfo.id">
          <div class="detail-title">
            <div>
              <strong>{{ detailInfo.service_name || detailInfo.api_name }}</strong>
              <span>{{ detailInfo.query_code }}</span>
            </div>
            <el-tag :type="Number(detailInfo.status) === 1 ? 'success' : 'danger'">{{ detailInfo.status_name }}</el-tag>
          </div>

          <el-descriptions title="基础信息" :column="2" border>
            <el-descriptions-item label="记录ID">{{ detailInfo.id }}</el-descriptions-item>
            <el-descriptions-item label="站点ID">{{ detailInfo.site_id }}</el-descriptions-item>
            <el-descriptions-item label="查询码">{{ detailInfo.query_code }}</el-descriptions-item>
            <el-descriptions-item label="查询类型">{{ detailInfo.query_type_name || detailInfo.query_type }}</el-descriptions-item>
            <el-descriptions-item label="查询项">{{ detailInfo.service_name || detailInfo.api_name }}</el-descriptions-item>
            <el-descriptions-item label="查询项编码">{{ detailInfo.service_code || '-' }}</el-descriptions-item>
            <el-descriptions-item label="接口名称">{{ detailInfo.api_name }}</el-descriptions-item>
            <el-descriptions-item label="接口路径">{{ detailInfo.api_endpoint }}</el-descriptions-item>
            <el-descriptions-item label="渠道">{{ detailInfo.channel_name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="渠道键">{{ detailInfo.channel_key || '-' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ detailInfo.create_at_text || detailInfo.create_at }}</el-descriptions-item>
            <el-descriptions-item label="更新时间">{{ detailInfo.update_at_text || detailInfo.update_at || '-' }}</el-descriptions-item>
          </el-descriptions>

          <el-descriptions title="费用与状态" :column="2" border class="mt-[16px]">
            <el-descriptions-item label="状态">{{ detailInfo.status_name }}</el-descriptions-item>
            <el-descriptions-item label="错误码">{{ detailInfo.error_code || '-' }}</el-descriptions-item>
            <el-descriptions-item label="错误信息">{{ detailInfo.error_message || '-' }}</el-descriptions-item>
            <el-descriptions-item label="响应耗时">{{ detailInfo.response_time || 0 }}ms</el-descriptions-item>
            <el-descriptions-item label="系统记录费用">¥{{ formatMoney(detailInfo.cost_amount) }}</el-descriptions-item>
            <el-descriptions-item label="第三方扣费">¥{{ formatMoney(detailInfo.third_cost) }}</el-descriptions-item>
            <el-descriptions-item label="账户余额">¥{{ formatMoney(detailInfo.balance) }}</el-descriptions-item>
            <el-descriptions-item label="操作人">{{ detailInfo.operator_name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="备注" :span="2">{{ detailInfo.remark || '-' }}</el-descriptions-item>
          </el-descriptions>

          <el-tabs class="detail-tabs">
            <el-tab-pane label="业务结果">
              <JsonViewer :data="detailInfo.query_result || {}" />
            </el-tab-pane>
            <el-tab-pane label="第三方响应">
              <JsonViewer :data="detailInfo.raw_response || {}" />
            </el-tab-pane>
            <el-tab-pane label="完整记录">
              <JsonViewer :data="detailInfo" />
            </el-tab-pane>
          </el-tabs>
        </template>
      </div>
    </el-dialog>
  </div>
</template>

<script lang="ts" setup>
import { defineComponent, h, onMounted, reactive, ref } from 'vue'
import { getDeviceQueryResultInfo, getDeviceQueryResultList, getDeviceQueryResultStats } from '@/addon/recycle/api/device_query_result'

const JsonViewer = defineComponent({
  name: 'JsonViewer',
  props: {
    data: {
      type: [Object, Array, String, Number, Boolean],
      default: () => ({})
    }
  },
  setup(props) {
    return () => h('pre', { class: 'json-viewer' }, JSON.stringify(props.data ?? {}, null, 2))
  }
})

const loading = ref(false)
const detailLoading = ref(false)
const detailVisible = ref(false)
const list = ref<any[]>([])
const overview = ref<any>({})
const detailInfo = ref<any>({})
const queryParams = reactive({
  query_code: '',
  api_endpoint: '',
  status: '',
  create_at: []
})

const unwrapData = (res: any) => {
  if (res?.data?.data !== undefined) return res.data.data
  if (res?.data !== undefined) return res.data
  return res || {}
}

const formatMoney = (value: any) => {
  const numberValue = Number(value || 0)
  return Number.isFinite(numberValue) ? numberValue.toFixed(3) : '0.000'
}

const loadList = async () => {
  loading.value = true
  try {
    const statRes = await getDeviceQueryResultStats(queryParams as any)
    overview.value = unwrapData(statRes) || {}
    const res = await getDeviceQueryResultList(queryParams as any)
    const payload = unwrapData(res)
    list.value = payload.list || payload.data || payload || []
  } finally {
    loading.value = false
  }
}

const openDetail = async (row: any) => {
  detailVisible.value = true
  detailInfo.value = row
  detailLoading.value = true
  try {
    const res = await getDeviceQueryResultInfo(row.id)
    detailInfo.value = unwrapData(res) || row
  } finally {
    detailLoading.value = false
  }
}

const resetSearch = () => {
  queryParams.query_code = ''
  queryParams.api_endpoint = ''
  queryParams.status = ''
  queryParams.create_at = []
  loadList()
}

onMounted(loadList)
</script>

<style lang="scss" scoped>
.device-query-result-page {
  .page-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 16px;
  }

  .page-desc {
    margin-top: 6px;
    color: #667085;
    font-size: 13px;
    line-height: 1.5;
  }

  .stat-row {
    margin-bottom: 16px;
  }

  .stat-cell {
    padding: 14px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #fafafa;

    span {
      display: block;
      color: #667085;
      font-size: 12px;
      line-height: 1.5;
    }

    strong {
      display: block;
      margin-top: 5px;
      color: #111827;
      font-size: 22px;
      line-height: 1.2;
    }
  }

  .search-form {
    margin-bottom: 14px;
  }

  .detail-title {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;

    strong,
    span {
      display: block;
    }

    strong {
      color: #111827;
      font-size: 16px;
      line-height: 1.4;
    }

    span {
      margin-top: 4px;
      color: #667085;
      font-size: 13px;
    }
  }

  .detail-tabs {
    margin-top: 16px;
  }

  :deep(.json-viewer) {
    max-height: 420px;
    margin: 0;
    padding: 12px;
    overflow: auto;
    color: #111827;
    font-size: 12px;
    line-height: 1.6;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    white-space: pre-wrap;
    word-break: break-word;
  }
}

@media (max-width: 900px) {
  .device-query-result-page {
    .page-head {
      flex-direction: column;
    }
  }
}
</style>
