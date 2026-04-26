<template>
  <div class="retrieve-apply-container">
    <el-card class="box-card !border-none" shadow="never">
      <template #header>
        <div class="card-header">
          <span class="font-medium">取回申请列表</span>
        </div>
      </template>
      
      <!-- 搜索表单 -->
      <el-form
        ref="searchFormRef"
        :model="searchData"
        label-width="100"
        class="mb-[10px] search-form"
        :inline="true"
      >
        <el-form-item label="订单号:">
          <el-input
            v-model="searchData.order_no"
            placeholder="请输入订单号"
            clearable
            @keyup.enter="resetPage"
          />
        </el-form-item>
        
        <el-form-item label="申请状态:">
          <el-select v-model="searchData.status" placeholder="请选择申请状态" clearable>
            <el-option label="全部" value="" />
            <el-option label="申请中" :value="0" />
            <el-option label="已发货" :value="1" />
            <el-option label="已完成" :value="2" />
            <el-option label="已取消" :value="3" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="申请时间:">
          <el-date-picker
            v-model="searchData.create_time"
            type="daterange"
            range-separator="-"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            :shortcuts="dateShortcuts"
            clearable
          />
        </el-form-item>
        
        <el-form-item>
          <el-button type="primary" @click="resetPage">搜索</el-button>
          <el-button @click="resetSearchForm">重置</el-button>
        </el-form-item>
      </el-form>
      
      <!-- 操作工具栏 -->
      <div class="mb-[10px] flex justify-between">
        <div>
          <el-button 
            type="danger" 
            :disabled="multipleSelection.length === 0" 
            @click="handleBatchDelete"
          >
            批量删除
          </el-button>
        </div>
      </div>
      
      <!-- 数据表格 -->
      <el-table
        v-loading="tableData.loading"
        :data="tableData.data"
        style="width: 100%"
        :header-cell-style="{ background: '#fbfbfb' }"
        @selection-change="handleSelectionChange"
      >
        <template #empty>
          <span>{{ !tableData.loading ? '暂无数据' : '' }}</span>
        </template>
        
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="申请ID" min-width="80" />
        <el-table-column prop="order_id" label="订单ID" min-width="80" />
        <el-table-column prop="order_no" label="订单号" min-width="160" />
        
        <el-table-column label="申请信息" min-width="180">
          <template #default="{ row }">
            <div>
              <p>联系人：{{ row.member_name || '--' }}</p>
              <p>手机：{{ row.member_mobile || '--' }}</p>
              <p>数量：{{ row.rejected_book_count || '--' }} 本</p>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column label="退回地址" min-width="240">
          <template #default="{ row }">
            <div v-if="row.address_info">
              <p>{{ row.address_info?.name || '--' }} {{ row.address_info?.mobile || '--' }}</p>
              <p>{{ row.address_info?.full_address || '--' }}</p>
            </div>
            <span v-else>--</span>
          </template>
        </el-table-column>
        
        <el-table-column label="物流信息" min-width="160">
          <template #default="{ row }">
            <div v-if="row.express_waybill">
              <p>{{ row.express_company }}</p>
              <p>{{ row.express_waybill }}</p>
              <el-button type="primary" link @click="copyText(row.express_waybill)">复制</el-button>
            </div>
            <span v-else>--</span>
          </template>
        </el-table-column>
        
        <el-table-column prop="status_text" label="状态" min-width="100">
          <template #default="{ row }">
            <div>
            <el-tag :type="getStatusTagType(row.status)" effect="light">
              {{ getStatusText(row.status) }}
            </el-tag>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column prop="create_time" label="申请时间" min-width="160" />
        
        <el-table-column label="操作" fixed="right" min-width="180">
          <template #default="{ row }">
            <el-button type="primary" link @click="goDetail(row)">查看</el-button>
            
            <!-- 不同状态显示不同操作按钮 -->
            <template v-if="row.status === 0">
              <el-button type="primary" link @click="handleShip(row)">发货</el-button>
              <el-button type="danger" link @click="handleCancel(row)">取消</el-button>
            </template>
            
            <template v-if="row.status === 1">
              <el-button type="success" link @click="handleComplete(row)">完成</el-button>
            </template>
            
            <template v-if="row.status === 2 || row.status === 3">
              <el-popconfirm title="确认要删除吗？" @confirm="handleDelete(row)">
                <template #reference>
                  <el-button type="danger" link>删除</el-button>
                </template>
              </el-popconfirm>
            </template>
          </template>
        </el-table-column>
      </el-table>
      
      <!-- 分页 -->
      <div v-show="tableData.total > 0" class="mt-[16px] flex justify-end">
        <el-pagination
          v-model:current-page="tableData.page"
          v-model:page-size="tableData.limit"
          :page-sizes="[10, 20, 50, 100]"
          background
          layout="total, sizes, prev, pager, next"
          :total="tableData.total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>
    
    <!-- 发货弹窗 -->
    <el-dialog v-model="shipDialogVisible" title="填写物流信息" width="500px" :destroy-on-close="true">
      <el-form :model="shipForm" label-width="120px" :rules="shipRules" ref="shipFormRef">
        <el-form-item label="物流公司" prop="express_company">
          <el-input v-model="shipForm.express_company" placeholder="请输入物流公司名称"></el-input>
        </el-form-item>
        <el-form-item label="物流单号" prop="express_waybill">
          <el-input v-model="shipForm.express_waybill" placeholder="请输入物流单号"></el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="shipDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="submitShip">确定</el-button>
        </span>
      </template>
    </el-dialog>
    
    <!-- 取消弹窗 -->
    <el-dialog v-model="cancelDialogVisible" title="取消申请" width="500px" :destroy-on-close="true">
      <p>确认要取消此申请吗？</p>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="cancelDialogVisible = false">关闭</el-button>
          <el-button type="primary" @click="submitCancel">确定取消</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { 
  getRetrieveApplyList, 
  shipRetrieveApply, 
  completeRetrieveApply, 
  cancelRetrieveApply, 
  deleteRetrieveApply,
  RetrieveApply
} from '@/addon/wj_books/api/wj_books_retrieve_apply'

const router = useRouter()

// 页面标题
const pageName = ref('取回申请管理')

// 查询参数
const searchData = reactive({
  order_no: '',
  status: '',
  create_time: []
})

// 表格数据
const tableData = reactive({
  loading: false,
  data: [] as RetrieveApply[],
  page: 1,
  limit: 10,
  total: 0
})

// 日期快捷选项
const dateShortcuts = [
  {
    text: '最近一周',
    value: () => {
      const end = new Date()
      const start = new Date()
      start.setTime(start.getTime() - 3600 * 1000 * 24 * 7)
      return [start, end]
    }
  },
  {
    text: '最近一个月',
    value: () => {
      const end = new Date()
      const start = new Date()
      start.setTime(start.getTime() - 3600 * 1000 * 24 * 30)
      return [start, end]
    }
  }
]

// 发货相关
const shipDialogVisible = ref(false)
const shipForm = reactive({
  id: '',
  express_company: '',
  express_waybill: ''
})
const shipFormRef = ref<FormInstance>()
const shipRules = {
  express_company: [{ required: true, message: '请输入物流公司', trigger: 'blur' }],
  express_waybill: [{ required: true, message: '请输入物流单号', trigger: 'blur' }]
}

// 取消相关
const cancelDialogVisible = ref(false)
const cancelForm = reactive({
  id: ''
})

// 搜索表单引用
const searchFormRef = ref()

// 状态文字映射
const getStatusText = (status) => {
  const statusMap = {
    0: '申请中',
    1: '已发货',
    2: '已完成',
    3: '已取消'
  }
  return statusMap[status] !== undefined ? statusMap[status] : '未知'
}

// 状态标签类型映射
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'success',
    3: 'info'
  }
  return typeMap[status] !== undefined ? typeMap[status] : ''
}

// 获取取回申请列表
const getList = async () => {
  tableData.loading = true
  try {
    const params = {
      page: tableData.page,
      limit: tableData.limit,
      ...searchData
    }
    const res = await getRetrieveApplyList(params)
    if (res.code === 0 || res.code === 1) {
      // 处理异常情况：有时数据在msg字段而不是data字段
      if (!res.data || (typeof res.data !== 'object')) {
        try {
          // 尝试解析msg字段是否为JSON字符串
          const msgData = typeof res.msg === 'string' ? JSON.parse(res.msg) : res.msg
          if (msgData && typeof msgData === 'object') {
            tableData.data = msgData.list || []
            tableData.total = msgData.count || 0
            return
          }
        } catch (e) {
          console.error('尝试解析msg字段失败', e)
          tableData.data = []
          tableData.total = 0
          return
        }
      }
      
      // 正常处理
      tableData.data = res.data.list || []
      tableData.total = res.data.count || 0
    } else {
      ElMessage.error(res.msg || '获取取回申请列表失败')
      tableData.data = []
      tableData.total = 0
    }
  } catch (error) {
    console.error('获取取回申请列表错误', error)
    ElMessage.error('获取取回申请列表失败')
    tableData.data = []
    tableData.total = 0
  } finally {
    tableData.loading = false
  }
}

// 复制文本
const copyText = (text: string) => {
  navigator.clipboard.writeText(text).then(() => {
    ElMessage.success('复制成功')
  }).catch(() => {
    ElMessage.error('复制失败')
  })
}

// 重置页码并搜索
const resetPage = () => {
  tableData.page = 1
  getList()
}

// 重置搜索表单
const resetSearchForm = () => {
  searchFormRef.value?.resetFields()
  resetPage()
}

// 处理每页条数变化
const handleSizeChange = (val: number) => {
  tableData.limit = val
  getList()
}

// 处理页码变化
const handleCurrentChange = (val: number) => {
  tableData.page = val
  getList()
}

// 跳转到详情页
const goDetail = (row) => {
  router.push({
    path: `/wj_books/wj_books_retrieve_apply/detail`,
    query: { id: row.id }
  })
}

// 处理发货
const handleShip = (row) => {
  shipForm.id = row.id
  shipForm.express_company = ''
  shipForm.express_waybill = ''
  shipDialogVisible.value = true
}

// 提交发货信息
const submitShip = async () => {
  if (!shipFormRef.value) return
  
  await shipFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        const res = await shipRetrieveApply(shipForm.id, {
          express_company: shipForm.express_company,
          express_waybill: shipForm.express_waybill
        })
        
        if (res.code === 0 || res.code === 1) {
          ElMessage.success('发货操作成功')
          shipDialogVisible.value = false
          getList()
        } else {
          ElMessage.error(res.msg || '发货操作失败')
        }
      } catch (error) {
        console.error('发货操作错误', error)
        ElMessage.error('发货操作失败')
      }
    }
  })
}

// 处理完成
const handleComplete = (row) => {
  ElMessageBox.confirm('确认已收到退回的书籍，将此申请标记为完成？', '完成确认', {
    confirmButtonText: '确认',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      const res = await completeRetrieveApply(row.id)
      if (res.code === 0 || res.code === 1) {
        ElMessage.success('操作成功')
        getList()
      } else {
        ElMessage.error(res.msg || '操作失败')
      }
    } catch (error) {
      console.error('完成操作错误', error)
      ElMessage.error('操作失败')
    }
  }).catch(() => {
    // 用户取消操作
  })
}

// 处理取消
const handleCancel = (row) => {
  cancelForm.id = row.id
  cancelDialogVisible.value = true
}

// 提交取消
const submitCancel = async () => {
      try {
    const res = await cancelRetrieveApply(cancelForm.id, {})
        
        if (res.code === 0 || res.code === 1) {
          ElMessage.success('取消操作成功')
          cancelDialogVisible.value = false
          getList()
        } else {
          ElMessage.error(res.msg || '取消操作失败')
        }
      } catch (error) {
        console.error('取消操作错误', error)
        ElMessage.error('取消操作失败')
      }
}

// 处理删除
const handleDelete = async (row) => {
  try {
    const res = await deleteRetrieveApply(row.id)
    if (res.code === 0 || res.code === 1) {
      ElMessage.success('删除成功')
      getList()
    } else {
      ElMessage.error(res.msg || '删除失败')
    }
  } catch (error) {
    console.error('删除操作错误', error)
    ElMessage.error('删除失败')
  }
}

// 添加script中的相关代码
// 在script setup中添加
const multipleSelection = ref<RetrieveApply[]>([])

// 多选变化
const handleSelectionChange = (val: RetrieveApply[]) => {
  multipleSelection.value = val
}

// 批量删除
const handleBatchDelete = () => {
  if (multipleSelection.value.length === 0) {
    ElMessage.warning('请选择要删除的申请')
    return
  }

  // 检查是否包含不可删除的申请(只有已完成或已取消的申请才能删除)
  const invalidApply = multipleSelection.value.find(item => item.status !== 2 && item.status !== 3)
  if (invalidApply) {
    ElMessage.error('只有已完成或已取消的申请才能删除')
    return
  }

  ElMessageBox.confirm(`确认要删除选中的${multipleSelection.value.length}条申请记录吗？`, '批量删除', {
    confirmButtonText: '确认',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      // 这里应实现批量删除接口，暂以循环单删替代
      let successCount = 0
      let failCount = 0
      
      for (const item of multipleSelection.value) {
        try {
          const res = await deleteRetrieveApply(item.id)
          if (res.code === 0 || res.code === 1) {
            successCount++
          } else {
            failCount++
          }
        } catch (error) {
          failCount++
        }
      }
      
      if (successCount > 0) {
        ElMessage.success(`成功删除${successCount}条记录`)
        if (failCount > 0) {
          ElMessage.warning(`${failCount}条记录删除失败`)
        }
        getList()
      } else {
        ElMessage.error('删除失败')
      }
    } catch (error) {
      console.error('批量删除错误', error)
      ElMessage.error('批量删除失败')
    }
  }).catch(() => {
    // 用户取消操作
  })
}

// 页面挂载时获取列表数据
onMounted(() => {
  getList()
})
</script>

<style lang="scss" scoped>
.retrieve-apply-container {
  padding: 16px;
}
.search-form {
  .el-form-item {
    margin-bottom: 16px;
  }
}
</style> 