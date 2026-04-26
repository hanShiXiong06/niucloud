<template>
  <el-dialog
    v-model="showDialog"
    :title="t('orderDetail')"
    width="80%"
    destroy-on-close
    @closed="handleDialogClosed"
  >
    <div v-loading="loading">
      <!-- 订单基本信息 -->
      <el-card class="mb-4" shadow="never">
        <template #header>
          <div class="flex justify-between items-center">
            <span class="font-bold">{{ t('basicInfo') }}</span>
            <div>
              <el-tag :type="getStatusTagType(orderInfo.status)" v-if="orderInfo.status">
                {{ formatStatus(orderInfo.status) }}
              </el-tag>
            </div>
          </div>
        </template>
        
        <el-descriptions :column="3" border>
          <el-descriptions-item :label="t('orderNo')">{{ orderInfo.order_no }}</el-descriptions-item>
          <el-descriptions-item :label="t('createTime')">{{ orderInfo.create_time }}</el-descriptions-item>
          <el-descriptions-item :label="t('pickupTime')">{{ orderInfo.pickup_time }}</el-descriptions-item>
          <el-descriptions-item :label="t('bookCount')">{{ orderInfo.book_count }}</el-descriptions-item>
          <el-descriptions-item :label="t('totalAmount')">¥{{ orderInfo.total_amount }}</el-descriptions-item>
          <el-descriptions-item :label="t('remark')">{{ orderInfo.remark || t('noRemark') }}</el-descriptions-item>
          
          <template v-if="orderInfo.status >= 2">
            <el-descriptions-item :label="t('pickupActualTime')" v-if="orderInfo.pickup_actual_time">
              {{ orderInfo.pickup_actual_time }}
            </el-descriptions-item>
          </template>
          
          <template v-if="orderInfo.status >= 3">
            <el-descriptions-item :label="t('auditProgress')">
              <el-progress 
                :percentage="orderInfo.audit_progress || 0" 
                :format="(p) => `${p}%`"
                :stroke-width="15"
              ></el-progress>
            </el-descriptions-item>
          </template>
          
          <template v-if="orderInfo.status >= 4">
            <el-descriptions-item :label="t('finalBookCount')" v-if="orderInfo.final_book_count">
              {{ orderInfo.final_book_count }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('finalAmount')" v-if="orderInfo.final_amount">
              ¥{{ orderInfo.final_amount }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('completeTime')" v-if="orderInfo.complete_time">
              {{ orderInfo.complete_time }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('completionMessage')" v-if="orderInfo.completion_message">
              {{ orderInfo.completion_message }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('retrieveDeadline')" v-if="orderInfo.retrieve_deadline">
              {{ orderInfo.retrieve_deadline }}
            </el-descriptions-item>
          </template>
          
          <template v-if="orderInfo.status === 5">
            <el-descriptions-item :label="t('cancelTime')" v-if="orderInfo.cancel_time">
              {{ orderInfo.cancel_time }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('cancelReason')" v-if="orderInfo.cancel_reason">
              {{ orderInfo.cancel_reason }}
            </el-descriptions-item>
          </template>
        </el-descriptions>
      </el-card>

      <!-- 会员信息 -->
      <el-card class="mb-4" shadow="never" v-if="orderInfo.member_info">
        <template #header>
          <div class="flex justify-between items-center">
            <span class="font-bold">会员信息</span>
          </div>
        </template>
        
        <el-descriptions :column="3" border>
          <el-descriptions-item label="会员姓名">
            <div class="flex items-center">
              <el-avatar :size="32" :src="orderInfo.member_info.headimg" v-if="orderInfo.member_info.headimg">
                {{ orderInfo.member_info.nickname?.substring(0, 1) || 'U' }}
              </el-avatar>
              <el-avatar :size="32" v-else>{{ orderInfo.member_info.nickname?.substring(0, 1) || 'U' }}</el-avatar>
              <span class="ml-2">{{ orderInfo.member_info.nickname || '未知' }}</span>
            </div>
          </el-descriptions-item>
          <el-descriptions-item label="手机号码">
            {{ orderInfo.member_info.mobile || '未提供' }}
          </el-descriptions-item>
          <el-descriptions-item label="会员ID">
            {{ orderInfo.member_info.member_id || '未知' }}
          </el-descriptions-item>
        </el-descriptions>
      </el-card>

      <!-- 地址信息 -->
      <el-card class="mb-4" shadow="never" v-if="orderInfo.address_info">
        <template #header>
          <div class="flex justify-between items-center">
            <span class="font-bold">地址信息</span>
          </div>
        </template>
        
        <el-descriptions :column="2" border>
          <el-descriptions-item label="联系人">
            {{ orderInfo.address_info.name || '未知' }}
          </el-descriptions-item>
          <el-descriptions-item label="联系电话">
            {{ orderInfo.address_info.mobile || '未提供' }}
          </el-descriptions-item>
          <el-descriptions-item label="详细地址" :span="2">
            {{ orderInfo.address_info.full_address || '未提供' }}
          </el-descriptions-item>
        </el-descriptions>
      </el-card>

      <!-- 订单状态操作区 -->
      <el-card class="mb-4" shadow="never" v-if="orderInfo.status">
        <template #header>
          <div class="flex justify-between items-center">
            <span class="font-bold">{{ t('statusOperation') }}</span>
          </div>
        </template>

        <div class="flex justify-center items-center mb-4">
          <el-steps :active="getStatusStep(orderInfo.status)" finish-status="success" align-center>
            <el-step :title="t('statusWaitingPickup')" :status="getStepStatus(orderInfo.status, 1)"></el-step>
            <el-step :title="t('statusPickedUp')" :status="getStepStatus(orderInfo.status, 2)"></el-step>
            <el-step :title="t('statusAuditing')" :status="getStepStatus(orderInfo.status, 3)"></el-step>
            <el-step :title="t('statusCompleted')" :status="getStepStatus(orderInfo.status, 4)"></el-step>
            <el-step :title="t('statusCancelled')" :status="getStepStatus(orderInfo.status, 5)" v-if="orderInfo.status === 5"></el-step>
          </el-steps>
        </div>

        <div class="flex justify-center gap-4">
          <el-button v-if="orderInfo.status === 1" type="primary" @click="updateStatus(2)">
            {{ t('confirmPickup') }}
          </el-button>
          <el-button v-if="orderInfo.status === 2" type="primary" @click="updateStatus(3)">
            {{ t('startAudit') }}
          </el-button>
          <el-button v-if="orderInfo.status === 3" type="success" @click="showCompleteDialog">
            {{ t('completeOrder') }}
          </el-button>
          <el-button v-if="orderInfo.status === 1" type="danger" @click="showCancelDialog">
            {{ t('cancelOrder') }}
          </el-button>
        </div>
      </el-card>

      <!-- 审核汇总 -->
      <audit-summary
        v-if="orderInfo.status >= 3"
        :order-id="orderInfo.id"
        :order-status="orderInfo.status"
        :books="orderInfo.books || []"
        :rejected-books-list="orderInfo.rejected_books || []"
        :audit-progress="orderInfo.audit_progress || 0"
        @complete="handleAuditComplete"
      />

      <!-- 订单书籍列表 -->
      <order-books 
        ref="orderBooksRef"
        :order-id="orderInfo.id" 
        :order-status="orderInfo.status"
        :books="orderInfo.books || []"
        @update="handleOrderBooksUpdate"
        @syncRejectedBooks="syncRejectedBooks"
      />

      <!-- 拒收书籍列表 -->
      <rejected-books 
        ref="rejectedBooksRef"
        :order-id="orderInfo.id" 
        :order-status="orderInfo.status"
        :rejected-books="orderInfo.rejected_books || []"
        @update="handleRejectedBooksUpdate"
        @updateOrderBookQuantity="updateOrderBookQuantity"
      />

      <!-- 物流信息 -->
      <el-card class="mb-4" shadow="never" v-if="orderInfo.status >= 2">
        <template #header>
          <div class="flex justify-between items-center">
            <span class="font-bold">{{ t('expressInfo') }}</span>
            <div>
              <el-button type="primary" size="small" @click="showExpressDialog" v-if="orderInfo.status >= 2 && orderInfo.status <= 3">
                {{ orderInfo.express_waybill ? t('updateExpress') : t('addExpress') }}
              </el-button>
            </div>
          </div>
        </template>
        
        <div v-if="orderInfo.express_waybill">
          <el-descriptions :column="3" border>
            <el-descriptions-item :label="t('expressChannel')">{{ orderInfo.express_channel }}</el-descriptions-item>
            <el-descriptions-item :label="t('expressWaybill')">
              <div class="flex items-center">
                {{ orderInfo.express_waybill }}
                <el-button v-if="orderInfo.express_waybill" type="primary" link size="small" @click="copyToClipboard(orderInfo.express_waybill)">
                  <el-icon><CopyDocument /></el-icon>
                </el-button>
              </div>
            </el-descriptions-item>
            <el-descriptions-item :label="t('expressStatus')">
              <el-tag :type="getExpressStatusTagType(orderInfo.express_status)">
                {{ formatExpressStatus(orderInfo.express_status) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t('expressWeight')" v-if="orderInfo.express_weight">
              {{ orderInfo.express_weight }}kg
            </el-descriptions-item>
            <el-descriptions-item :label="t('expressFreight')" v-if="orderInfo.express_freight">
              ¥{{ orderInfo.express_freight }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('expressShopbill')" v-if="orderInfo.express_shopbill">
              {{ orderInfo.express_shopbill }}
            </el-descriptions-item>
          </el-descriptions>
          
          <div v-if="orderInfo.express_courier_name || orderInfo.express_courier_phone || orderInfo.express_pickup_code" class="mt-4 p-4 bg-gray-50 rounded">
            <div class="font-bold mb-2">{{ t('courierInfo') }}</div>
            <el-descriptions :column="2" border>
              <el-descriptions-item :label="t('courierName')" v-if="orderInfo.express_courier_name">
                {{ orderInfo.express_courier_name }}
              </el-descriptions-item>
              <el-descriptions-item :label="t('courierPhone')" v-if="orderInfo.express_courier_phone">
                <div class="flex items-center">
                  {{ orderInfo.express_courier_phone }}
                  <el-button v-if="orderInfo.express_courier_phone" type="primary" link size="small" @click="copyToClipboard(orderInfo.express_courier_phone)">
                    <el-icon><CopyDocument /></el-icon>
                  </el-button>
                </div>
              </el-descriptions-item>
              <el-descriptions-item :label="t('pickupCode')" v-if="orderInfo.express_pickup_code">
                <div class="flex items-center">
                  {{ orderInfo.express_pickup_code }}
                  <el-button v-if="orderInfo.express_pickup_code" type="primary" link size="small" @click="copyToClipboard(orderInfo.express_pickup_code)">
                    <el-icon><CopyDocument /></el-icon>
                  </el-button>
                </div>
              </el-descriptions-item>
            </el-descriptions>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-400">
          {{ t('noExpressInfo') }}
        </div>
      </el-card>

      <!-- 取回申请列表 -->
      <el-card class="mb-4" shadow="never" v-if="orderInfo.status === 4 && retrieveApplyList.length > 0">
        <template #header>
          <div class="flex justify-between items-center">
            <span class="font-bold">{{ t('retrieveApply') }}</span>
          </div>
        </template>
        
        <el-table :data="retrieveApplyList" border stripe>
          <el-table-column prop="id" :label="t('applyId')" width="80" align="center" />
          <el-table-column prop="rejected_book_ids" :label="t('rejectedBooks')" min-width="150">
            <template #default="{ row }">
              <div>{{ formatRejectedBookIds(row.rejected_book_ids) }}</div>
            </template>
          </el-table-column>
          <el-table-column prop="status" :label="t('status')" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="getRetrieveApplyStatusTagType(row.status)">
                {{ formatRetrieveApplyStatus(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="create_time" :label="t('applyTime')" width="160" />
          <el-table-column :label="t('operation')" width="200" align="center">
            <template #default="{ row }">
              <el-button 
                type="primary" 
                link 
                @click="updateRetrieveApplyStatus(row.id, 1)" 
                v-if="row.status === 0"
              >
                {{ t('approve') }}
              </el-button>
              <el-button 
                type="danger" 
                link 
                @click="updateRetrieveApplyStatus(row.id, 3)" 
                v-if="row.status === 0"
              >
                {{ t('reject') }}
              </el-button>
              <el-button 
                type="success" 
                link 
                @click="updateRetrieveApplyStatus(row.id, 2)" 
                v-if="row.status === 1"
              >
                {{ t('markAsCompleted') }}
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-card>

    </div>

    <!-- 完成订单对话框 -->
    <el-dialog v-model="completeDialog.visible" :title="t('completeOrder')" width="600px" append-to-body>
      <div class="mb-4 p-4 bg-gray-50 rounded">
        <div class="flex justify-between mb-2">
          <span class="font-bold">审核统计信息</span>
        </div>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="原始书籍数量">{{ orderInfo.book_count || 0 }}</el-descriptions-item>
          <el-descriptions-item label="通过审核数量">{{ getAuditedBookCount() }}</el-descriptions-item>
          <el-descriptions-item label="拒收书籍数量">{{ getRejectedBookCount() }}</el-descriptions-item>
          <el-descriptions-item label="预估总价值">¥{{ orderInfo.total_amount || 0 }}</el-descriptions-item>
        </el-descriptions>
      </div>
      
      <el-form :model="completeDialog.form" label-width="120px">
        <el-form-item :label="t('finalAmount')" required>
          <div class="flex items-center">
            <el-input-number 
              v-model="completeDialog.form.final_amount" 
              :min="0" 
              :precision="2" 
              :step="0.01"
              controls-position="right"
            ></el-input-number>
            <el-button type="primary" size="small" class="ml-2" @click="calculateFinalAmount">
              自动计算
            </el-button>
          </div>
          <div class="text-gray-400 text-xs mt-1">
            系统计算价格: ¥{{ getCalculatedFinalAmount() }}
          </div>
        </el-form-item>
        <el-form-item :label="t('completionMessage')">
          <el-input 
            v-model="completeDialog.form.completion_message" 
            type="textarea" 
            :rows="3"
            :placeholder="t('completionMessagePlaceholder')"
          ></el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="completeDialog.visible = false">{{ t('cancel') }}</el-button>
          <el-button type="primary" @click="confirmComplete" :loading="completeDialog.loading">
            {{ t('confirm') }}
          </el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 取消订单对话框 -->
    <el-dialog v-model="cancelDialog.visible" :title="t('cancelOrder')" width="500px" append-to-body>
      <el-form :model="cancelDialog.form" label-width="100px">
        <el-form-item :label="t('cancelReason')" required>
          <el-input 
            v-model="cancelDialog.form.reason" 
            type="textarea" 
            :rows="3"
            :placeholder="t('cancelReasonPlaceholder')"
          ></el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="cancelDialog.visible = false">{{ t('cancel') }}</el-button>
          <el-button type="primary" @click="confirmCancel" :loading="cancelDialog.loading">
            {{ t('confirm') }}
          </el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 物流信息对话框 -->
    <el-dialog v-model="expressDialog.visible" :title="t('updateExpress')" width="600px" append-to-body>
      <el-form :model="expressDialog.form" label-width="120px">
        <el-form-item :label="t('expressChannel')">
          <el-input v-model="expressDialog.form.express_channel"></el-input>
        </el-form-item>
        <el-form-item :label="t('expressWaybill')">
          <el-input v-model="expressDialog.form.express_waybill"></el-input>
        </el-form-item>
        <el-form-item :label="t('expressShopbill')">
          <el-input v-model="expressDialog.form.express_shopbill"></el-input>
        </el-form-item>
        <el-form-item :label="t('expressWeight')">
          <el-input-number 
            v-model="expressDialog.form.express_weight" 
            :min="0" 
            :precision="2" 
            :step="0.1"
            controls-position="right"
            style="width: 100%"
          ></el-input-number>
        </el-form-item>
        <el-form-item :label="t('expressFreight')">
          <el-input-number 
            v-model="expressDialog.form.express_freight" 
            :min="0" 
            :precision="2" 
            :step="0.1"
            controls-position="right"
            style="width: 100%"
          ></el-input-number>
        </el-form-item>
        <el-form-item :label="t('expressStatus')">
          <el-select v-model="expressDialog.form.express_status" style="width: 100%">
            <el-option :label="t('expressStatusWaiting')" :value="1" />
            <el-option :label="t('expressStatusInTransit')" :value="2" />
            <el-option :label="t('expressStatusDelivered')" :value="3" />
            <el-option :label="t('expressStatusReturned')" :value="4" />
            <el-option :label="t('expressStatusCancelled')" :value="99" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('expressCourierName')">
          <el-input v-model="expressDialog.form.express_courier_name"></el-input>
        </el-form-item>
        <el-form-item :label="t('expressCourierPhone')">
          <el-input v-model="expressDialog.form.express_courier_phone"></el-input>
        </el-form-item>
        <el-form-item :label="t('expressPickupCode')">
          <el-input v-model="expressDialog.form.express_pickup_code"></el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="expressDialog.visible = false">{{ t('cancel') }}</el-button>
          <el-button type="primary" @click="confirmUpdateExpress" :loading="expressDialog.loading">
            {{ t('confirm') }}
          </el-button>
        </span>
      </template>
    </el-dialog>
  </el-dialog>
</template>

<script lang="ts" setup>
import { reactive, ref, defineEmits, onMounted } from 'vue'
import { t } from '@/lang'
import { ElMessageBox, ElMessage } from 'element-plus'
import { 
  getWjBooksOrderInfo, 
  updateWjBooksOrderStatus, 
  completeWjBooksOrder, 
  cancelWjBooksOrder,
  getWjBooksRetrieveApplyList,
  updateWjBooksRetrieveApplyStatus,
  updateWjBooksOrderExpress,
  updateWjBooksOrderAuditProgress
} from '@/addon/wj_books/api/wj_books_order'
import { addMemberBalanceForBooks, addMemberMoneyForBooks } from '@/addon/wj_books/api/wj_books_member'
import OrderBooks from './wj-books-order-books.vue'
import RejectedBooks from './wj-books-rejected-books.vue'
import AuditSummary from './wj-books-audit-summary.vue'
import { CopyDocument } from '@element-plus/icons-vue'

const emit = defineEmits(['refresh'])

// 对话框显示状态
const showDialog = ref(false)
const loading = ref(false)

// 组件引用
const orderBooksRef = ref(null)
const rejectedBooksRef = ref(null)

// 订单信息
const orderInfo = reactive({
  id: 0,
  order_no: '',
  book_count: 0,
  total_amount: 0,
  final_amount: null,
  final_book_count: null,
  status: 0,
  remark: '',
  create_time: '',
  pickup_time: '',
  pickup_actual_time: null,
  complete_time: null,
  cancel_time: null,
  cancel_reason: null,
  completion_message: null,
  retrieve_deadline: null,
  express_channel_id: null,
  express_channel: null,
  express_waybill: null,
  express_shopbill: null,
  express_status: null,
  express_weight: null,
  express_freight: null,
  express_courier_name: null,
  express_courier_phone: null,
  express_pickup_code: null,
  audit_progress: 0
})

// 取回申请列表
const retrieveApplyList = ref<any[]>([])

// 完成订单对话框
const completeDialog = reactive({
  visible: false,
  loading: false,
  form: {
    final_amount: 0,
    completion_message: ''
  }
})

// 取消订单对话框
const cancelDialog = reactive({
  visible: false,
  loading: false,
  form: {
    reason: ''
  }
})

// 物流信息对话框
const expressDialog = reactive({
  visible: false,
  loading: false,
  form: {
    express_channel_id: '',
    express_channel: '',
    express_waybill: '',
    express_shopbill: '',
    express_status: 1,
    express_weight: 0,
    express_freight: 0,
    express_courier_name: '',
    express_courier_phone: '',
    express_pickup_code: ''
  }
})

/**
 * 显示订单详情
 */
const showDetail = (id: number) => {
  orderInfo.id = id
  showDialog.value = true
  loading.value = true
  
  getWjBooksOrderInfo(id).then(res => {
    Object.assign(orderInfo, res.data)
    loading.value = false
    
    // 加载取回申请列表
    if (orderInfo.status === 4) {
      loadRetrieveApplyList()
    }
  }).catch(() => {
    loading.value = false
  })
}

/**
 * 加载取回申请列表
 */
const loadRetrieveApplyList = () => {
  if (!orderInfo.id) return
  
  getWjBooksRetrieveApplyList({ order_id: orderInfo.id }).then(res => {
    retrieveApplyList.value = res.data || []
  })
}

/**
 * 关闭对话框时重置数据
 */
const handleDialogClosed = () => {
  Object.keys(orderInfo).forEach(key => {
    if (key !== 'id') {
      // @ts-ignore
      orderInfo[key] = typeof orderInfo[key] === 'number' ? 0 : null
    }
  })
  retrieveApplyList.value = []
}

/**
 * 格式化订单状态
 */
const formatStatus = (status: number) => {
  const statusMap: Record<number, string> = {
    1: t('statusWaitingPickup'),
    2: t('statusPickedUp'),
    3: t('statusAuditing'),
    4: t('statusCompleted'),
    5: t('statusCancelled')
  }
  return statusMap[status] || status
}

/**
 * 获取状态标签类型
 */
const getStatusTagType = (status: number) => {
  const typeMap: Record<number, string> = {
    1: 'warning',
    2: 'info',
    3: 'primary',
    4: 'success',
    5: 'danger'
  }
  return typeMap[status] || ''
}

/**
 * 格式化物流状态
 */
const formatExpressStatus = (status: number) => {
  const statusMap: Record<number, string> = {
    1: t('expressStatusWaiting'),
    2: t('expressStatusInTransit'),
    3: t('expressStatusDelivered'),
    4: t('expressStatusReturned'),
    99: t('expressStatusCancelled')
  }
  return statusMap[status] || t('expressStatusUnknown')
}

/**
 * 获取物流状态标签类型
 */
const getExpressStatusTagType = (status: number) => {
  const typeMap: Record<number, string> = {
    1: 'warning',
    2: 'primary',
    3: 'success',
    4: 'danger',
    99: 'info'
  }
  return typeMap[status] || 'info'
}

/**
 * 格式化取回申请状态
 */
const formatRetrieveApplyStatus = (status: number) => {
  const statusMap: Record<number, string> = {
    0: t('retrieveStatusPending'),
    1: t('retrieveStatusShipped'),
    2: t('retrieveStatusCompleted'),
    3: t('retrieveStatusCancelled')
  }
  return statusMap[status] || status
}

/**
 * 获取取回申请状态标签类型
 */
const getRetrieveApplyStatusTagType = (status: number) => {
  const typeMap: Record<number, string> = {
    0: 'warning',
    1: 'primary',
    2: 'success',
    3: 'danger'
  }
  return typeMap[status] || 'info'
}

/**
 * 格式化拒收书籍ID列表
 */
const formatRejectedBookIds = (ids: string) => {
  if (!ids) return ''
  
  const idArray = ids.split(',')
  return idArray.length + t('itemCount')
}

/**
 * 获取步骤条激活步骤
 */
const getStatusStep = (status: number) => {
  const stepMap: Record<number, number> = {
    1: 0,
    2: 1,
    3: 2,
    4: 3,
    5: 4 // 取消状态显示最后一步
  }
  return stepMap[status] || 0
}

/**
 * 获取步骤状态
 */
const getStepStatus = (currentStatus: number, step: number) => {
  // 如果是已取消状态
  if (currentStatus === 5) {
    return step === 5 ? 'error' : 'wait'
  }
  
  // 当前激活的步骤
  const activeStep = getStatusStep(currentStatus)
  
  // 已完成的步骤
  if (step - 1 < activeStep) {
    return 'success'
  }
  
  // 当前步骤
  if (step - 1 === activeStep) {
    return 'process' // Element Plus支持的状态: wait, process, finish, error, success
  }
  
  // 未开始的步骤
  return 'wait'
}

/**
 * 更新订单状态
 */
const updateStatus = (status: number) => {
  let confirmMessage = ''
  if (status === 2) {
    confirmMessage = t('confirmPickupMessage')
  } else if (status === 3) {
    confirmMessage = t('startAuditMessage')
  }

  ElMessageBox.confirm(confirmMessage, t('warning'), {
    confirmButtonText: t('confirm'),
    cancelButtonText: t('cancel'),
    type: 'warning',
  }).then(() => {
    loading.value = true
    updateWjBooksOrderStatus(orderInfo.id, status).then(() => {
      loading.value = false
      getWjBooksOrderInfo(orderInfo.id).then(res => {
        Object.assign(orderInfo, res.data)
        emit('refresh')
      })
    }).catch(() => {
      loading.value = false
    })
  })
}

/**
 * 获取已审核通过的书籍数量
 */
const getAuditedBookCount = () => {
  if (!orderInfo.books || orderInfo.books.length === 0) return 0
  
  return orderInfo.books.reduce((total, book) => {
    return total + (book.accepted_quantity || 0)
  }, 0)
}

/**
 * 获取拒收的书籍数量
 */
const getRejectedBookCount = () => {
  if (!orderInfo.rejected_books || orderInfo.rejected_books.length === 0) return 0
  
  return orderInfo.rejected_books.reduce((total, book) => {
    return total + (book.rejected_quantity || 0)
  }, 0)
}

/**
 * 获取系统计算的最终价格
 */
const getCalculatedFinalAmount = () => {
  if (!orderInfo.books || orderInfo.books.length === 0) return 0
  
  // 计算所有已审核书籍的最终价格总和
  const totalFinalPrice = orderInfo.books.reduce((total, book) => {
    if (book.final_price !== null && book.accepted_quantity !== null) {
      return total + (book.final_price * book.accepted_quantity)
    }
    return total
  }, 0)
  
  return totalFinalPrice.toFixed(2)
}

/**
 * 自动计算最终价格
 */
const calculateFinalAmount = () => {
  completeDialog.form.final_amount = parseFloat(getCalculatedFinalAmount())
}

/**
 * 显示完成订单对话框
 */
const showCompleteDialog = () => {
  completeDialog.form.final_amount = parseFloat(getCalculatedFinalAmount()) || orderInfo.total_amount
  completeDialog.form.completion_message = ''
  completeDialog.visible = true
}

/**
 * 确认完成订单
 */
const confirmComplete = () => {
  if (completeDialog.form.final_amount <= 0) {
    ElMessageBox.alert(t('finalAmountRequired'), t('warning'))
    return
  }

  completeDialog.loading = true
  completeWjBooksOrder(orderInfo.id, completeDialog.form).then(() => {
    // 订单完成后，向用户账户增加相应余额（可提现余额）
    if (orderInfo.member_id && completeDialog.form.final_amount > 0) {
      // 验证数据有效性
      if (!orderInfo.member_id || orderInfo.member_id <= 0) {
        ElMessage.warning('会员ID无效，无法发放余额')
        return
      }
      
      // 构建增加可提现余额的参数 - 适配新接口
      const moneyParams = {
        member_id: orderInfo.member_id,
        account_data: completeDialog.form.final_amount, // 账户数据金额（正数表示增加，负数表示减少）
        memo: `二手书回收订单${orderInfo.order_no}完成结算`, // 添加备注说明
        from_type: 'wj_books_order', // 来源类型
        related_id: orderInfo.id.toString() // 关联ID（订单ID）
      }
      
      // 显示正在处理的提示
      ElMessage.info('正在处理余额发放，请稍候...')
      console.log('发放余额参数:', moneyParams)
      
      // 调用增加会员可提现余额的接口
      addMemberMoneyForBooks(moneyParams).then(res => {
        if (res.data && res.code === 1) {
          ElMessage.success('订单完成并成功发放可提现余额')
        } else {
          ElMessage.warning(`余额发放失败: ${res.msg || '未知错误'}`)
          console.error('可提现余额发放失败', res)
        }
      }).catch(error => {
        console.error('可提现余额发放失败', error)
        ElMessage.warning(`余额发放失败: ${error.message || '网络错误'}`)
      })
    }
    
    completeDialog.loading = false
    completeDialog.visible = false
    getWjBooksOrderInfo(orderInfo.id).then(res => {
      Object.assign(orderInfo, res.data)
      emit('refresh')
    })
  }).catch(() => {
    completeDialog.loading = false
  })
}

/**
 * 显示取消订单对话框
 */
const showCancelDialog = () => {
  cancelDialog.form.reason = ''
  cancelDialog.visible = true
}

/**
 * 确认取消订单
 */
const confirmCancel = () => {
  if (!cancelDialog.form.reason) {
    ElMessageBox.alert(t('cancelReasonRequired'), t('warning'))
    return
  }

  cancelDialog.loading = true
  cancelWjBooksOrder(orderInfo.id, cancelDialog.form.reason).then(() => {
    cancelDialog.loading = false
    cancelDialog.visible = false
    getWjBooksOrderInfo(orderInfo.id).then(res => {
      Object.assign(orderInfo, res.data)
      emit('refresh')
    })
  }).catch(() => {
    cancelDialog.loading = false
  })
}

/**
 * 显示物流信息对话框
 */
const showExpressDialog = () => {
  expressDialog.form.express_channel_id = orderInfo.express_channel_id || ''
  expressDialog.form.express_channel = orderInfo.express_channel || ''
  expressDialog.form.express_waybill = orderInfo.express_waybill || ''
  expressDialog.form.express_shopbill = orderInfo.express_shopbill || ''
  expressDialog.form.express_status = orderInfo.express_status || 1
  expressDialog.form.express_weight = orderInfo.express_weight || 0
  expressDialog.form.express_freight = orderInfo.express_freight || 0
  expressDialog.form.express_courier_name = orderInfo.express_courier_name || ''
  expressDialog.form.express_courier_phone = orderInfo.express_courier_phone || ''
  expressDialog.form.express_pickup_code = orderInfo.express_pickup_code || ''
  expressDialog.visible = true
}

/**
 * 确认更新物流信息
 */
const confirmUpdateExpress = () => {
  if (!expressDialog.form.express_channel || !expressDialog.form.express_waybill) {
    ElMessageBox.alert(t('expressInfoRequired'), t('warning'))
    return
  }

  expressDialog.loading = true
  updateWjBooksOrderExpress(orderInfo.id, expressDialog.form).then(() => {
    expressDialog.loading = false
    expressDialog.visible = false
    getWjBooksOrderInfo(orderInfo.id).then(res => {
      Object.assign(orderInfo, res.data)
    })
  }).catch(() => {
    expressDialog.loading = false
  })
}

/**
 * 更新取回申请状态
 */
const updateRetrieveApplyStatus = (id: number, status: number) => {
  let confirmMessage = ''
  if (status === 1) {
    confirmMessage = t('approveRetrieveApplyMessage')
  } else if (status === 2) {
    confirmMessage = t('completeRetrieveApplyMessage')
  } else if (status === 3) {
    confirmMessage = t('rejectRetrieveApplyMessage')
  }

  ElMessageBox.confirm(confirmMessage, t('warning'), {
    confirmButtonText: t('confirm'),
    cancelButtonText: t('cancel'),
    type: 'warning',
  }).then(() => {
    updateWjBooksRetrieveApplyStatus(id, status).then(() => {
      loadRetrieveApplyList()
      ElMessage.success(t('operationSuccess'))
    })
  })
}

/**
 * 处理订单书籍更新
 */
const handleOrderBooksUpdate = () => {
  getWjBooksOrderInfo(orderInfo.id).then(res => {
    Object.assign(orderInfo, res.data)
  })
}

/**
 * 处理拒收书籍更新
 */
const handleRejectedBooksUpdate = () => {
  getWjBooksOrderInfo(orderInfo.id).then(res => {
    Object.assign(orderInfo, res.data)
  })
}

/**
 * 处理审核完成
 */
const handleAuditComplete = () => {
  emit('refresh')
  showDialog.value = false
}

/**
 * 更新订单审核进度
 */
const updateAuditProgress = (progress: number) => {
  if (!orderInfo.id) return
  
  updateWjBooksOrderAuditProgress(orderInfo.id, progress).then(() => {
    orderInfo.audit_progress = progress
  })
}

/**
 * 复制到剪贴板
 */
const copyToClipboard = (text: string) => {
  const input = document.createElement('input')
  input.value = text
  document.body.appendChild(input)
  input.select()
  document.execCommand('copy')
  document.body.removeChild(input)
  ElMessage.success(t('copySuccess'))
}

/**
 * 同步拒收书籍
 * 当订单书籍组件发生审核操作后，需要刷新拒收书籍列表
 */
const syncRejectedBooks = () => {
  if (rejectedBooksRef.value) {
    rejectedBooksRef.value.refreshRejectedBooks()
  }
}

/**
 * 更新订单书籍数量
 * 当拒收书籍组件添加或修改拒收书籍后，需要更新订单书籍的接收数量
 * @param data 包含orderBookId和rejectedQuantity的对象
 */
const updateOrderBookQuantity = (data: { orderBookId: number, rejectedQuantity: number }) => {
  if (orderBooksRef.value) {
    orderBooksRef.value.updateFromRejectedBooks(data)
  }
}

// 向父组件暴露方法
defineExpose({
  showDetail
})
</script>

<style lang="scss" scoped>
.mb-4 {
  margin-bottom: 16px;
}

// 自定义步骤条样式
:deep(.el-step__head.is-process) {
  color: #409EFF;
  border-color: #409EFF;
}

:deep(.el-step__title.is-process) {
  color: #409EFF;
  font-weight: bold;
}

:deep(.el-step__description.is-process) {
  color: #409EFF;
}
</style>
