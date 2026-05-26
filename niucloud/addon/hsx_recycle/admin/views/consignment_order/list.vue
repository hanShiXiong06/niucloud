<template>
  <div class="consignment-page">
    <el-card class="!border-none" shadow="never">
      <template #header>
        <div class="page-header">
          <div>
            <div class="page-title">代卖订单</div>
            <div class="page-subtitle">跟踪从回收订单转入代卖的设备，支持查看原订单、登记售出、结算和日志追溯。</div>
          </div>
          <div class="page-actions">
            <el-button type="primary" plain @click="goConsignmentExport">导出代卖入库</el-button>
            <el-button @click="fetchList">刷新</el-button>
          </div>
        </div>
      </template>

      <el-form :model="search" inline class="search-form">
        <el-form-item label="关键词">
          <el-input v-model.trim="search.keyword" class="!w-[240px]" clearable placeholder="代卖单号/原订单/IMEI/客户手机" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="search.status" class="!w-[150px]" clearable placeholder="全部">
            <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="tableData" size="large">
        <el-table-column label="代卖信息" min-width="260">
          <template #default="{ row }">
            <div class="font-semibold text-gray-900">{{ row.consignment_no }}</div>
            <div class="mt-1 text-xs text-gray-500">来源订单：
              <el-button link type="primary" @click="goSourceOrder(row)">{{ row.source_order_no || '-' }}</el-button>
            </div>
            <div class="mt-1 text-xs text-gray-500">设备：{{ row.device_model || '-' }} / {{ row.device_imei || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="客户" min-width="150">
          <template #default="{ row }">
            <div>{{ row.member?.nickname || row.customer_name || '-' }}</div>
            <div class="text-xs text-gray-500">{{ row.member?.mobile || row.customer_phone || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusTag(row.status)">{{ row.status_name }}</el-tag>
            <div class="mt-1 text-xs text-gray-500">{{ row.pay_status_name }}</div>
          </template>
        </el-table-column>
        <el-table-column label="金额" min-width="260">
          <template #default="{ row }">
            <div class="money-grid">
              <span>回收报价</span><strong>¥{{ money(row.quote_price) }}</strong>
              <span>挂牌价</span><strong>¥{{ money(row.listing_price) }}</strong>
              <span>成交价</span><strong>¥{{ money(row.sold_price) }}</strong>
              <span>客户结算</span><strong class="text-green-600">¥{{ money(row.settlement_amount) }}</strong>
              <span>服务收益</span><strong class="text-blue-600">¥{{ money(row.service_fee) }}</strong>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="时间" width="180">
          <template #default="{ row }">
            <div class="text-xs leading-5 text-gray-600">
              <div>创建：{{ formatTime(row.create_time) }}</div>
              <div v-if="row.sold_time">售出：{{ formatTime(row.sold_time) }}</div>
              <div v-if="row.settle_time">结算：{{ formatTime(row.settle_time) }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right" align="center">
          <template #default="{ row }">
            <el-dropdown trigger="click" @command="(cmd) => handleCommand(cmd, row)">
              <el-button type="primary" plain size="small">操作</el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="detail">查看详情</el-dropdown-item>
                  <el-dropdown-item command="listing" :disabled="row.status >= 4">设置挂牌价</el-dropdown-item>
                  <el-dropdown-item command="sold" :disabled="![0,1,2].includes(Number(row.status))">登记售出</el-dropdown-item>
                  <el-dropdown-item command="settle" :disabled="Number(row.status) !== 3">结算客户</el-dropdown-item>
                  <el-dropdown-item command="cancel" :disabled="row.status >= 4">取消代卖</el-dropdown-item>
                  <el-dropdown-item command="push_notify" divided>推送进度通知</el-dropdown-item>
                  <el-dropdown-item
                    v-for="action in getVisiblePrintActions(row)"
                    :key="action.scene_key"
                    :command="`print:${action.scene_key}`"
                  >
                    {{ action.button_text || action.scene_name || '打印' }}
                  </el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-4 flex justify-end">
        <el-pagination
          v-model:current-page="page.page"
          v-model:page-size="page.limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="page.total"
          @size-change="fetchList"
          @current-change="fetchList"
        />
      </div>
    </el-card>

    <el-drawer v-model="detailVisible" title="代卖订单详情" size="720px">
      <div v-if="detail" class="detail-wrap">
        <section>
          <h3>基础信息</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="代卖单号">{{ detail.consignment_no }}</el-descriptions-item>
            <el-descriptions-item label="状态"><el-tag>{{ detail.status_name }}</el-tag></el-descriptions-item>
            <el-descriptions-item label="来源订单">
              <el-button link type="primary" @click="goSourceOrder(detail)">{{ detail.source_order_no }}</el-button>
            </el-descriptions-item>
            <el-descriptions-item label="设备">{{ detail.device_model }} / {{ detail.device_imei }}</el-descriptions-item>
          </el-descriptions>
        </section>

        <section>
          <h3>设备信息</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="设备型号">{{ getDeviceField('model') || detail.device_model || '-' }}</el-descriptions-item>
            <el-descriptions-item label="IMEI">{{ getDeviceField('imei') || detail.device_imei || '-' }}</el-descriptions-item>
            <el-descriptions-item label="备用串号">{{ getDeviceField('imei2') || getDeviceField('sn') || '-' }}</el-descriptions-item>
            <el-descriptions-item label="设备状态">{{ getDeviceField('status_name') || '-' }}</el-descriptions-item>
            <el-descriptions-item label="容量">{{ getDeviceField('capacity') || '-' }}</el-descriptions-item>
            <el-descriptions-item label="颜色">{{ getDeviceField('color') || '-' }}</el-descriptions-item>
            <el-descriptions-item label="回收报价">¥{{ money(getDeviceField('final_price') || detail.quote_price) }}</el-descriptions-item>
            <el-descriptions-item label="代卖售价">¥{{ money(getDeviceField('sell_price') || detail.listing_price) }}</el-descriptions-item>
          </el-descriptions>
        </section>

        <section>
          <h3>金额</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="原回收报价">¥{{ money(detail.quote_price) }}</el-descriptions-item>
            <el-descriptions-item label="客户期望价">¥{{ money(detail.expected_price) }}</el-descriptions-item>
            <el-descriptions-item label="最低结算价">¥{{ money(detail.min_settlement_price) }}</el-descriptions-item>
            <el-descriptions-item label="挂牌价">¥{{ money(detail.listing_price) }}</el-descriptions-item>
            <el-descriptions-item label="成交价">¥{{ money(detail.sold_price) }}</el-descriptions-item>
            <el-descriptions-item label="客户结算">¥{{ money(detail.settlement_amount) }}</el-descriptions-item>
            <el-descriptions-item label="服务收益">¥{{ money(detail.service_fee) }}</el-descriptions-item>
          </el-descriptions>
        </section>

        <section>
          <h3>操作日志</h3>
          <el-timeline>
            <el-timeline-item v-for="log in detail.logs || []" :key="log.id" :timestamp="formatTime(log.create_time)">
              <div class="font-medium">{{ actionName(log.action) }}：{{ log.operator_name || '-' }}</div>
              <div class="text-sm text-gray-500">{{ log.remark || '无备注' }}</div>
            </el-timeline-item>
          </el-timeline>
        </section>
      </div>
    </el-drawer>

    <el-dialog v-model="actionDialog.visible" :title="actionDialog.title" width="420px" destroy-on-close>
      <el-form :model="actionForm" label-width="110px">
        <el-form-item v-if="actionDialog.type === 'listing'" label="挂牌价">
          <el-input-number v-model="actionForm.listing_price" :min="0" :precision="2" class="!w-full" />
        </el-form-item>
        <template v-if="actionDialog.type === 'sold'">
          <el-form-item label="成交价">
            <el-input-number v-model="actionForm.sold_price" :min="0" :precision="2" class="!w-full" />
          </el-form-item>
          <el-form-item label="客户结算">
            <el-input-number v-model="actionForm.settlement_amount" :min="0" :precision="2" class="!w-full" />
          </el-form-item>
        </template>
        <el-form-item v-if="actionDialog.type === 'settle'" label="结算金额">
          <el-input-number v-model="actionForm.settlement_amount" :min="0" :precision="2" class="!w-full" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="actionForm.remark" type="textarea" rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="actionDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="submitAction">确认</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import {
  closeConsignment,
  getConsignmentOrderInfo,
  getConsignmentOrderList,
  getConsignmentStatusOptions,
  markConsignmentSold,
  pushConsignmentNotify,
  settleConsignment,
  updateConsignmentListing
} from '@/addon/hsx_recycle/api/consignment_order'
import { getPrintSceneManualActions, getPrintScenePlan, printByScene } from '@/addon/hsx_recycle/api/printer'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const saving = ref(false)
const tableData = ref<any[]>([])
const statusOptions = ref<any[]>([])
const manualPrintActions = ref<any[]>([])
const detailVisible = ref(false)
const detail = ref<any>(null)

const search = reactive({
  keyword: '',
  status: '',
  source_order_id: route.query.source_order_id || ''
})
const page = reactive({ page: 1, limit: 10, total: 0 })

const actionDialog = reactive({ visible: false, title: '', type: '', row: null as any })
const actionForm = reactive({
  listing_price: 0,
  sold_price: 0,
  settlement_amount: 0,
  remark: ''
})

const money = (value: any) => Number(value || 0).toFixed(2)
const getDeviceField = (key: string) => detail.value?.sourceDevice?.[key] ?? detail.value?.source_device?.[key] ?? ''
const formatTime = (value: any) => {
  const num = Number(value || 0)
  if (!num) return '-'
  const d = new Date(num * 1000)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
const getStatusTag = (status: any) => {
  if ([4].includes(Number(status))) return 'success'
  if ([3].includes(Number(status))) return 'warning'
  if ([5, 6].includes(Number(status))) return 'info'
  return 'primary'
}
const actionName = (action: string) => ({
  create: '创建代卖',
  listing: '设置挂牌价',
  sold: '登记售出',
  settle: '结算客户',
  cancel: '取消代卖',
  return: '退回客户'
}[action] || action)

const getVisiblePrintActions = (row: any) => {
  return manualPrintActions.value.filter((action: any) => {
    if (action.button_position && action.button_position !== 'consignment_order_actions') return false
    const statuses = Array.isArray(action.visible_device_status)
      ? action.visible_device_status.map((item: any) => Number(item))
      : []
    return !statuses.length || statuses.includes(Number(row.status))
  })
}

const fetchManualPrintActions = async () => {
  try {
    const res: any = await getPrintSceneManualActions({ biz_type: 'consignment' })
    manualPrintActions.value = res.code === 1 && Array.isArray(res.data) ? res.data : []
  } catch (error) {
    manualPrintActions.value = []
  }
}

const fetchStatus = async () => {
  const res: any = await getConsignmentStatusOptions()
  statusOptions.value = res.data || []
}
const fetchList = async () => {
  loading.value = true
  try {
    const res: any = await getConsignmentOrderList({ ...search, page: page.page, limit: page.limit })
    tableData.value = res.data?.data || []
    page.total = res.data?.total || 0
  } finally {
    loading.value = false
  }
}
const handleSearch = () => {
  page.page = 1
  fetchList()
}
const resetSearch = () => {
  search.keyword = ''
  search.status = ''
  search.source_order_id = ''
  page.page = 1
  fetchList()
}
const goSourceOrder = (row: any) => {
  router.push({ path: '/site/recycle_order/list', query: { order_id: row.source_order_id, order_no: row.source_order_no || '', t: Date.now() } })
}
const goConsignmentExport = () => {
  router.push({ path: '/site/device_export/list', query: { warehouse_type: 'consign', t: Date.now() } })
}
const openDetail = async (row: any) => {
  const res: any = await getConsignmentOrderInfo(row.id)
  detail.value = res.data
  detailVisible.value = true
}
const handleCommand = async (command: string, row: any) => {
  if (command === 'detail') {
    openDetail(row)
    return
  }
  if (command === 'push_notify') {
    await pushConsignmentNotify(row.id)
    fetchList()
    if (detailVisible.value && detail.value?.id === row.id) {
      openDetail(row)
    }
    return
  }
  if (command.startsWith('print:')) {
    await handlePrintScene(command.replace('print:', ''), row)
    return
  }
  if (command === 'cancel') {
    await ElMessageBox.confirm(`确定取消代卖订单 ${row.consignment_no} 吗？`, '取消代卖', { type: 'warning' })
    await closeConsignment(row.id, { status: 5, remark: '后台取消代卖' })
    ElMessage.success('已取消')
    fetchList()
    return
  }
  actionDialog.type = command
  actionDialog.row = row
  actionDialog.title = command === 'listing' ? '设置挂牌价' : command === 'sold' ? '登记售出' : '结算客户'
  actionForm.listing_price = Number(row.listing_price || 0)
  actionForm.sold_price = Number(row.sold_price || 0)
  actionForm.settlement_amount = Number(row.settlement_amount || 0)
  actionForm.remark = ''
  actionDialog.visible = true
}

const handlePrintScene = async (sceneKey: string, row: any) => {
  const action = manualPrintActions.value.find((item: any) => item.scene_key === sceneKey) || {}
  const params = { consignment_id: row.id, biz_id: row.id }
  try {
    const planRes: any = await getPrintScenePlan(sceneKey, params)
    if (planRes.code !== 1 || !planRes.data?.can_print) {
      throw new Error(planRes.msg || planRes.data?.message || '打印计划不可用')
    }
    const plan = planRes.data || {}
    if (Number(action.confirm_required ?? 1) === 1) {
      const sceneName = plan.scene?.scene_name || action.scene_name || '代卖打印'
      const templateName = plan.template?.template_name || '-'
      const printerName = plan.printer?.printer_name || '-'
      await ElMessageBox.confirm(
        `确认打印「${sceneName}」？\n模板：${templateName}\n打印机：${printerName}`,
        action.button_text || plan.scene?.button_text || '打印代卖凭证',
        {
          type: 'info',
          confirmButtonText: '确认打印',
          cancelButtonText: '取消'
        }
      )
    }
    const res: any = await printByScene(sceneKey, params)
    if (res.code !== 1) {
      throw new Error(res.msg || '打印失败')
    }
  } catch (error: any) {
    if (error === 'cancel' || error === 'close') return
    ElMessage.error(error?.message || '打印失败')
  }
}

const submitAction = async () => {
  saving.value = true
  try {
    const id = actionDialog.row.id
    if (actionDialog.type === 'listing') {
      await updateConsignmentListing(id, { listing_price: actionForm.listing_price, remark: actionForm.remark })
    } else if (actionDialog.type === 'sold') {
      await markConsignmentSold(id, { sold_price: actionForm.sold_price, settlement_amount: actionForm.settlement_amount, remark: actionForm.remark })
    } else if (actionDialog.type === 'settle') {
      await settleConsignment(id, { settlement_amount: actionForm.settlement_amount, remark: actionForm.remark })
    }
    ElMessage.success('操作成功')
    actionDialog.visible = false
    fetchList()
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchStatus()
  fetchList()
  fetchManualPrintActions()
})
</script>

<style scoped>
.consignment-page {
  padding: 16px;
}
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.page-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.page-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}
.page-subtitle {
  margin-top: 4px;
  color: #64748b;
  font-size: 13px;
}
.search-form {
  margin-bottom: 16px;
}
.money-grid {
  display: grid;
  grid-template-columns: 72px 1fr;
  gap: 4px 10px;
  font-size: 12px;
  color: #64748b;
}
.money-grid strong {
  color: #111827;
}
.detail-wrap {
  display: grid;
  gap: 18px;
}
.detail-wrap h3 {
  margin: 0 0 10px;
  font-size: 15px;
  font-weight: 700;
}
</style>
