<template>
  <div class="express-log-container">
    <el-card class="box-card">
      <template #header>
        <div class="card-header">
          <span>物流回调日志</span>
          <div>
            <el-button type="danger" @click="handleClear" :loading="loading">清空日志</el-button>
          </div>
        </div>
      </template>
      <el-form :model="searchData" ref="searchFormRef" label-width="80px" class="search-form">
        <el-row :gutter="20">
          <el-col :span="6">
            <el-form-item label="运单号">
              <el-input v-model="searchData.waybill" placeholder="请输入运单号" clearable />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="商家单号">
              <el-input v-model="searchData.shopbill" placeholder="请输入商家单号" clearable />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="订单ID">
              <el-input v-model="searchData.order_id" placeholder="请输入订单ID" clearable />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="状态码">
              <el-select v-model="searchData.type_code" placeholder="请选择状态码" clearable>
                <el-option label="待揽收" :value="1" />
                <el-option label="运输中" :value="2" />
                <el-option label="已签收" :value="3" />
                <el-option label="拒收退回" :value="4" />
                <el-option label="已取消" :value="99" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="创建时间">
              <el-date-picker
                v-model="dateRange"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                value-format="YYYY-MM-DD HH:mm:ss"
                :default-time="['00:00:00', '23:59:59']"
                style="width: 100%"
                @change="handleDateChange"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12" class="search-btn-container">
            <el-button type="primary" @click="getList(1)" :loading="loading">查询</el-button>
            <el-button @click="resetSearch">重置</el-button>
          </el-col>
        </el-row>
      </el-form>

      <el-empty v-if="tableData.length === 0 && !loading" description="暂无数据" />

      <el-table v-else v-loading="loading" :data="tableData" style="width: 100%" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="order_id" label="订单ID" width="100">
          <template #default="scope">
            {{ scope.row.order_id || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="waybill" label="运单号" min-width="120" />
        <el-table-column prop="shopbill" label="商家单号" min-width="120">
          <template #default="scope">
            {{ scope.row.shopbill || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="type" label="运单状态" min-width="100">
          <template #default="scope">
            {{ scope.row.type || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="状态码" min-width="100">
          <template #default="scope">
            <el-tag v-if="scope.row.type_code !== null && scope.row.type_code !== undefined" :type="getStatusType(scope.row.type_code)">
              {{ getStatusText(scope.row.type_code) }}
            </el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="下单重量(kg)" min-width="120">
          <template #default="scope">
            {{ scope.row.weight || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="计费重量(kg)" min-width="120">
          <template #default="scope">
            {{ scope.row.cal_weight || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="总扣款费用" min-width="120">
          <template #default="scope">
            {{ scope.row.total_freight || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="快递费" min-width="100">
          <template #default="scope">
            {{ scope.row.freight || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="快递员" min-width="100">
          <template #default="scope">
            {{ scope.row.courier_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="联系电话" min-width="120">
          <template #default="scope">
            {{ scope.row.courier_phone || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" min-width="160" />
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="scope">
            <el-button type="primary" link @click="handleDetail(scope.row)">详情</el-button>
            <el-button type="danger" link @click="handleDelete(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div v-if="tableData.length > 0" class="pagination-container">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="limit"
          :page-sizes="[10, 20, 30, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailVisible" title="物流回调日志详情" width="800px">
      <div v-if="detailData" class="detail-container">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="ID">{{ detailData.id }}</el-descriptions-item>
          <el-descriptions-item label="订单ID">{{ detailData.order_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="运单号">{{ detailData.waybill }}</el-descriptions-item>
          <el-descriptions-item label="商家单号">{{ detailData.shopbill || '-' }}</el-descriptions-item>
          <el-descriptions-item label="运单状态">{{ detailData.type || '-' }}</el-descriptions-item>
          <el-descriptions-item label="状态码">
            <el-tag v-if="detailData.type_code !== null" :type="getStatusType(detailData.type_code)">
              {{ getStatusText(detailData.type_code) }}
            </el-tag>
            <span v-else>-</span>
          </el-descriptions-item>
          <el-descriptions-item label="下单重量">{{ detailData.weight || '-' }} kg</el-descriptions-item>
          <el-descriptions-item label="站点称重">{{ detailData.real_weight || '-' }} kg</el-descriptions-item>
          <el-descriptions-item label="分拣称重">{{ detailData.transfer_weight || '-' }} kg</el-descriptions-item>
          <el-descriptions-item label="计费重量">{{ detailData.cal_weight || '-' }} kg</el-descriptions-item>
          <el-descriptions-item label="体积">{{ detailData.volume || '-' }} cm³</el-descriptions-item>
          <el-descriptions-item label="体积换算重量">{{ detailData.parse_weight || '-' }} kg</el-descriptions-item>
          <el-descriptions-item label="总扣款费用">{{ detailData.total_freight || '-' }} 元</el-descriptions-item>
          <el-descriptions-item label="快递费">{{ detailData.freight || '-' }} 元</el-descriptions-item>
          <el-descriptions-item label="保价费">{{ detailData.freight_insured || '-' }} 元</el-descriptions-item>
          <el-descriptions-item label="增值费用">{{ detailData.freight_haocai || '-' }} 元</el-descriptions-item>
          <el-descriptions-item label="换单号">{{ detailData.change_bill || '-' }}</el-descriptions-item>
          <el-descriptions-item label="逆向费">{{ detailData.change_bill_freight || '-' }} 元</el-descriptions-item>
          <el-descriptions-item label="扣费状态">
            {{ detailData.fee_over === 1 ? '已扣费' : detailData.fee_over === 0 ? '冻结' : '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="快递员">{{ detailData.courier_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="联系电话">{{ detailData.courier_phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="取件码">{{ detailData.pickup_code || '-' }}</el-descriptions-item>
          <el-descriptions-item label="创建时间" :span="2">{{ detailData.create_time }}</el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="center">回调原始内容</el-divider>
        <pre class="json-content">{{ formatJson(detailData.content) }}</pre>
      </div>
    </el-dialog>
  </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getWjBooksExpressLogLists,
  getWjBooksExpressLogInfo,
  deleteWjBooksExpressLog,
  clearWjBooksExpressLog
} from '@/addon/wj_books/api/wj_books_express_log'

// 表格数据
const tableData = ref<any[]>([])
const loading = ref(false)
const page = ref(1)
const limit = ref(10)
const total = ref(0)

// 日期范围
const dateRange = ref<string[]>([])

// 搜索表单数据
const searchFormRef = ref()
const searchData = reactive({
  waybill: '',
  shopbill: '',
  order_id: '',
  type_code: '',
  start_time: '',
  end_time: ''
})

// 处理日期变化
const handleDateChange = (val: string[] | null) => {
  if (val) {
    searchData.start_time = val[0]
    searchData.end_time = val[1]
  } else {
    searchData.start_time = ''
    searchData.end_time = ''
  }
}

// 详情弹窗
const detailVisible = ref(false)
const detailData = ref<any>(null)

// 获取列表数据
const getList = async (p?: number) => {
  if (p) page.value = p
  loading.value = true

  try {
    const params: Record<string, any> = {
      page: page.value,
      limit: limit.value,
      waybill: searchData.waybill,
      shopbill: searchData.shopbill,
      order_id: searchData.order_id,
      type_code: searchData.type_code,
      start_time: searchData.start_time,
      end_time: searchData.end_time
    }

    console.log('查询参数：', params)
    const res = await getWjBooksExpressLogLists(params)
    console.log('查询结果：', res.data)
    
    if (res.data) {
      // 适配后端返回的数据结构
      if (res.data.data) {
        // 新的数据结构
        tableData.value = res.data.data
        total.value = res.data.total || 0
      } else if (res.data.list) {
        // 旧的数据结构
        tableData.value = res.data.list
        total.value = res.data.count || 0
      } else {
        tableData.value = []
        total.value = 0
        ElMessage.warning('获取数据失败，返回结果格式不正确')
      }
    } else {
      tableData.value = []
      total.value = 0
      ElMessage.warning('获取数据失败，返回结果格式不正确')
    }
  } catch (error) {
    console.error('获取物流回调日志列表失败', error)
    ElMessage.error('获取物流回调日志列表失败')
    tableData.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

// 重置搜索条件
const resetSearch = () => {
  searchData.waybill = ''
  searchData.shopbill = ''
  searchData.order_id = ''
  searchData.type_code = ''
  searchData.start_time = ''
  searchData.end_time = ''
  dateRange.value = []
  getList(1)
}

// 处理分页变化
const handleSizeChange = (val: number) => {
  limit.value = val
  getList(1)
}

const handleCurrentChange = (val: number) => {
  page.value = val
  getList()
}

// 查看详情
const handleDetail = async (row: any) => {
  try {
    loading.value = true
    const res = await getWjBooksExpressLogInfo(row.id)
    detailData.value = res.data
    detailVisible.value = true
  } catch (error) {
    console.error('获取物流回调日志详情失败', error)
    ElMessage.error('获取物流回调日志详情失败')
  } finally {
    loading.value = false
  }
}

// 删除日志
const handleDelete = (row: any) => {
  ElMessageBox.confirm('确认删除该物流回调日志?', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  })
    .then(async () => {
      try {
        loading.value = true
        await deleteWjBooksExpressLog(row.id)
        ElMessage.success('删除成功')
        // 如果是最后一条数据并且不是第一页，则回到上一页
        if (tableData.value.length === 1 && page.value > 1) {
          page.value--
        }
        getList(page.value)
      } catch (error) {
        console.error('删除物流回调日志失败', error)
        ElMessage.error('删除物流回调日志失败')
      } finally {
        loading.value = false
      }
    })
    .catch(() => {})
}

// 清空日志
const handleClear = () => {
  ElMessageBox.confirm('确认清空所有物流回调日志?', '警告', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  })
    .then(async () => {
      try {
        loading.value = true
        await clearWjBooksExpressLog()
        ElMessage.success('清空成功')
        getList(1)
      } catch (error) {
        console.error('清空物流回调日志失败', error)
        ElMessage.error('清空物流回调日志失败')
      } finally {
        loading.value = false
      }
    })
    .catch(() => {})
}

// 格式化JSON
const formatJson = (jsonStr: string) => {
  try {
    const obj = typeof jsonStr === 'string' ? JSON.parse(jsonStr) : jsonStr
    return JSON.stringify(obj, null, 2)
  } catch (e) {
    return jsonStr
  }
}

// 获取状态文本
const getStatusText = (typeCode: number) => {
  switch (typeCode) {
    case 1:
      return '待揽收'
    case 2:
      return '运输中'
    case 3:
      return '已签收'
    case 4:
      return '拒收退回'
    case 99:
      return '已取消'
    default:
      return '未知状态'
  }
}

// 获取状态标签类型
const getStatusType = (typeCode: number) => {
  switch (typeCode) {
    case 1:
      return 'info'
    case 2:
      return 'warning'
    case 3:
      return 'success'
    case 4:
      return 'danger'
    case 99:
      return ''
    default:
      return ''
  }
}

onMounted(() => {
  getList()
})
</script>

<style scoped>
.express-log-container {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.search-form {
  margin-bottom: 20px;
}

.search-btn-container {
  display: flex;
  justify-content: flex-start;
  align-items: flex-end;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.detail-container {
  max-height: 60vh;
  overflow-y: auto;
}

.json-content {
  background-color: #f5f7fa;
  padding: 10px;
  border-radius: 4px;
  overflow-x: auto;
  white-space: pre-wrap;
  font-family: monospace;
  font-size: 12px;
  max-height: 200px;
  overflow-y: auto;
}
</style> 