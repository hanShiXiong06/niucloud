<template>
  <div class="retrieve-detail-container">
    <el-card class="card !border-none" shadow="never">
      <template #header>
        <div class="card-header">
          <el-page-header :content="pageName" :icon="ArrowLeft" @back="goBack" />
        </div>
      </template>
      
      <div v-loading="loading">
        <!-- 基本信息 -->
        <el-descriptions
          class="margin-top"
          title="申请信息"
          :column="2"
          border
        >
          <el-descriptions-item label="申请ID" min-width="100px">
            {{ detail.id }}
          </el-descriptions-item>
          <el-descriptions-item label="申请状态" min-width="100px">
            <el-tag :type="getStatusTagType(detail.status)">
              {{ getStatusText(detail.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="申请时间">
            {{ detail.create_time }}
          </el-descriptions-item>
          <el-descriptions-item label="订单信息">
            <div>
              <p>订单ID：{{ detail.order_id }}</p>
              <p>订单号：{{ detail.order_no || '--' }}</p>
            </div>
          </el-descriptions-item>
          <el-descriptions-item label="发货时间" span="1">
            {{ detail.ship_time || '--' }}
          </el-descriptions-item>
          <el-descriptions-item label="完成时间" span="1">
            {{ detail.complete_time || '--' }}
          </el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">
            {{ detail.remark || '--' }}
          </el-descriptions-item>
        </el-descriptions>

        <!-- 用户信息 -->
        <el-descriptions
          class="margin-top"
          title="会员信息"
          :column="2"
          border
        >
          <el-descriptions-item label="会员ID" min-width="100px">
            {{ detail.member_id }}
          </el-descriptions-item>
          <el-descriptions-item label="联系信息" min-width="100px">
            <div>
              <p>{{ detail.member_name || '--' }}</p>
              <p>{{ detail.member_mobile || '--' }}</p>
            </div>
          </el-descriptions-item>
        </el-descriptions>
        
        <!-- 退回地址 -->
        <el-descriptions
          class="margin-top"
          title="退回地址"
          :column="1"
          border
        >
          <el-descriptions-item label="收件人信息" min-width="100px">
            {{ detail.address_info?.name || '--' }} {{ detail.address_info?.mobile || '--' }}
          </el-descriptions-item>
          <el-descriptions-item label="地址">
            {{ detail.address_info?.full_address || '--' }}
          </el-descriptions-item>
        </el-descriptions>
        
        <!-- 物流信息 -->
        <el-descriptions
          class="margin-top"
          title="物流信息"
          :column="2"
          border
          v-if="detail.express_waybill"
        >
          <el-descriptions-item label="物流公司" min-width="100px">
            {{ detail.express_company }}
          </el-descriptions-item>
          <el-descriptions-item label="物流单号" min-width="100px">
            {{ detail.express_waybill }}
            <el-button type="primary" link @click="copyText(detail.express_waybill)">复制</el-button>
          </el-descriptions-item>
        </el-descriptions>
        
        <!-- 拒收书籍列表 -->
        <div class="rejected-books margin-top">
          <h3 class="mb-[16px]">拒收书籍列表</h3>
          <el-table
            :data="rejectedBooks"
            style="width: 100%"
            :header-cell-style="{ background: '#fbfbfb' }"
          >
            <template #empty>
              <span>暂无拒收书籍数据</span>
            </template>
            <el-table-column prop="book_id" label="图书ID" width="100" />
            <el-table-column label="图书信息">
              <template #default="{ row }">
                <div class="flex items-center">
                  <div class="book-cover mr-[16px]" v-if="row.img">
                    <el-image
                      :src="img(row.img)"
                      fit="cover"
                      :preview-src-list="[img(row.img)]"
                      preview-teleported
                      style="width: 60px; height: 80px"
                      :initial-index="0"
                      :z-index="9999"
                      append-to-body
                    />
                  </div>
                  <div>
                    <p class="book-title">{{ row.title || '--' }}</p>
                    <p class="book-author" v-if="row.author">{{ row.author }}</p>
                    <p class="book-isbn" v-if="row.isbn">ISBN: {{ row.isbn }}</p>
                  </div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="rejected_quantity" label="数量" width="100" />
            <el-table-column prop="reject_reason" label="拒收原因" width="150">
              <template #default="{ row }">
                <el-tag :type="getRejectReasonTagType(row.reject_reason)">
                {{ formatRejectReason(row.reject_reason) }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </div>
        
        <!-- 操作按钮 -->
        <div class="actions mt-[30px]">
          <el-button @click="goBack">返回列表</el-button>
          
          <template v-if="detail.status === 0">
            <el-button type="primary" @click="handleShip">发货</el-button>
            <el-button type="danger" @click="handleCancel">取消申请</el-button>
          </template>
          
          <template v-if="detail.status === 1">
            <el-button type="success" @click="handleComplete">完成</el-button>
          </template>
          
          <template v-if="detail.status === 2 || detail.status === 3">
            <el-popconfirm title="确认要删除该申请记录吗？" @confirm="handleDelete">
              <template #reference>
                <el-button type="danger">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </div>
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
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { 
  getRetrieveApplyDetail, 
  shipRetrieveApply, 
  completeRetrieveApply, 
  cancelRetrieveApply, 
  deleteRetrieveApply,
  RetrieveApply
} from '@/addon/wj_books/api/wj_books_retrieve_apply'
import { ArrowLeft } from '@element-plus/icons-vue'
import { img } from '@/utils/common'

const route = useRoute()
const router = useRouter()
const id = ref(route.query.id as string)

// 页面标题
const pageName = ref('取回申请详情')

// 加载状态
const loading = ref(false)
// 详情数据
const detail = ref<RetrieveApply>({} as RetrieveApply)
// 拒收书籍列表
const rejectedBooks = ref<any[]>([])

// 发货相关
const shipDialogVisible = ref(false)
const shipForm = reactive({
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

// 格式化拒收原因
const formatRejectReason = (reason) => {
  const reasonMap = {
    'damaged': '破损',
    'not_match': '信息不符',
    'too_old': '过旧',
    'other': '其他'
  }
  return reasonMap[reason] || reason
}

// 获取拒收原因标签类型
const getRejectReasonTagType = (reason) => {
  const typeMap = {
    'damaged': 'danger',
    'not_match': 'warning',
    'too_old': 'info',
    'other': ''
  }
  return typeMap[reason] || ''
}

// 获取详情数据
const getDetail = async () => {
  if (!id.value) {
    ElMessage.error('未找到取回申请ID')
    return
  }
  
  loading.value = true
  try {
    const res = await getRetrieveApplyDetail(id.value)
    if (res.code === 0 || res.code === 1) {
      // 处理异常情况：有时数据在msg字段而不是data字段
      if (!res.data || (Array.isArray(res.data) && res.data.length === 0)) {
        try {
          // 尝试解析msg字段是否为JSON字符串
          const msgData = typeof res.msg === 'string' ? JSON.parse(res.msg) : res.msg
          if (msgData && typeof msgData === 'object') {
            detail.value = msgData
            if (msgData.rejected_books) {
              rejectedBooks.value = msgData.rejected_books
            }
            return
          }
        } catch (e) {
          console.error('尝试解析msg字段失败', e)
        }
      }
      
      // 正常处理
      detail.value = res.data
      if (res.data.rejected_books) {
        rejectedBooks.value = res.data.rejected_books
      }
    } else {
      ElMessage.error(res.msg || '获取取回申请详情失败')
    }
  } catch (error) {
    console.error('获取取回申请详情错误', error)
    ElMessage.error('获取取回申请详情失败')
  } finally {
    loading.value = false
  }
}

// 返回列表页
const goBack = () => {
  router.push('/wj_books_retrieve_apply/wj_books_retrieve_apply')
}

// 复制文本
const copyText = (text: string) => {
  navigator.clipboard.writeText(text).then(() => {
    ElMessage.success('复制成功')
  }).catch(() => {
    ElMessage.error('复制失败')
  })
}

// 处理发货
const handleShip = () => {
  shipForm.express_company = detail.value.express_company || ''
  shipForm.express_waybill = detail.value.express_waybill || ''
  shipDialogVisible.value = true
}

// 提交发货信息
const submitShip = async () => {
  if (!shipFormRef.value) return
  
  await shipFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        const res = await shipRetrieveApply(id.value, {
          express_company: shipForm.express_company,
          express_waybill: shipForm.express_waybill
        })
        
        if (res.code === 0 || res.code === 1) {
          ElMessage.success('发货操作成功')
          shipDialogVisible.value = false
          getDetail()
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
const handleComplete = () => {
  ElMessageBox.confirm('确认已收到退回的书籍，将此申请标记为完成？', '完成确认', {
    confirmButtonText: '确认',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      const res = await completeRetrieveApply(id.value)
      if (res.code === 0 || res.code === 1) {
        ElMessage.success('操作成功')
        getDetail()
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
const handleCancel = () => {
  cancelDialogVisible.value = true
}

// 提交取消
const submitCancel = async () => {
      try {
    const res = await cancelRetrieveApply(id.value, {})
        
        if (res.code === 0 || res.code === 1) {
          ElMessage.success('取消操作成功')
          cancelDialogVisible.value = false
          getDetail()
        } else {
          ElMessage.error(res.msg || '取消操作失败')
        }
      } catch (error) {
        console.error('取消操作错误', error)
        ElMessage.error('取消操作失败')
      }
}

// 处理删除
const handleDelete = async () => {
  try {
    const res = await deleteRetrieveApply(id.value)
    if (res.code === 0 || res.code === 1) {
      ElMessage.success('删除成功')
      goBack()
    } else {
      ElMessage.error(res.msg || '删除失败')
    }
  } catch (error) {
    console.error('删除操作错误', error)
    ElMessage.error('删除失败')
  }
}

// 页面挂载时获取详情
onMounted(() => {
  getDetail()
})
</script>

<style lang="scss" scoped>
.retrieve-detail-container {
  padding: 16px;
}

.margin-top {
  margin-top: 20px;
}

.book-title {
  font-weight: 500;
  margin-bottom: 5px;
}
.book-author, .book-isbn {
  color: #666;
  font-size: 13px;
}

:deep(.el-descriptions__body) {
  background-color: #fff;
}

.actions {
  margin-top: 30px;
  display: flex;
  gap: 10px;
}
</style> 