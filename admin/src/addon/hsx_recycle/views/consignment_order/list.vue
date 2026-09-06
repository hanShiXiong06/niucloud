<template>
  <PremiumTheme class="consignment-page">
    <el-card class="!border-none" shadow="never">
      <template #header>
        <HsxTitle size="page" collapsible-subtitle>
            <template #default>代卖订单</template>
            <template #subtitle>跟踪从回收订单转入代卖的设备，支持查看原订单、登记售出、结算和日志追溯。</template>
            <template #extra>
                <el-button type="primary" plain @click="goConsignmentExport">导出代卖入库</el-button>
                <el-button @click="fetchList">刷新</el-button>
            </template>
        </HsxTitle>
      </template>

      <HsxSearchPanel>
          <QueryForm v-model="queryModel" :schema="querySchema" :columns="2" :collapse-count="2" label-position="top" label-width="auto" :loading="loading" @search="searchConsignments" @reset="clearQueryScope" />
      </HsxSearchPanel>

      <el-table v-loading="loading" :data="tableData" size="large">
        <template #empty>
          <EmptyState
            v-if="!loading"
            icon="search"
            title="暂无代卖订单"
            description="在回收订单里把设备「转代卖」后，会出现在这里。可调整关键词或状态筛选。"
          />
        </template>
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
              <span>客户结算</span><strong class="text-[var(--el-color-success)]">¥{{ money(row.settlement_amount) }}</strong>
              <span>服务收益</span><strong class="text-[var(--el-color-primary)]">¥{{ money(row.service_fee) }}</strong>
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
                  <el-dropdown-item command="sold" :disabled="![0,1,2].includes(Number(row.status))">{{ erpManaged ? '前往ERP销售' : '登记售出' }}</el-dropdown-item>
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

    <HsxDrawer v-model="detailVisible" title="代卖订单详情" size="720px" :destroy-on-close="false">
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
    </HsxDrawer>

    <HsxDialog :confirm-loading="saving" v-model="actionDialog.visible" :title="actionDialog.title" width="420px" destroy-on-close>
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
        <template v-if="actionDialog.type === 'settle' && erpManaged">
          <el-form-item label="付款账户" required>
            <el-select v-model="actionForm.capital_account_id" class="!w-full" placeholder="选择ERP实际出款账户">
              <el-option v-for="account in capitalAccounts" :key="account.id" :value="account.id" :label="capitalAccountLabel(account)" />
            </el-select>
          </el-form-item>
          <el-form-item label="付款凭证">
            <upload-image v-model="actionForm.payment_images" :limit="9" />
          </el-form-item>
          <HsxNotice default-expanded type="info" :closable="false" title="ERP将统一生成应付结算与账户出账，成功后自动回写本代卖单。" />
        </template>
        <el-form-item label="备注">
          <el-input v-model="actionForm.remark" type="textarea" rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button :disabled="saving" @click="actionDialog.visible = false">取消</el-button>
        <el-button :disabled="saving" type="primary" :loading="saving" @click="submitAction">确认</el-button>
      </template>
    </HsxDialog>
  </PremiumTheme>
</template>

<script setup lang="ts">
import { QueryForm } from '@/addon/hsx_components/forms'
import type { ProFormField } from '@/addon/hsx_components/types'
import { HsxSearchPanel, HsxDialog, HsxDrawer, HsxNotice, useFeedback, HsxTitle } from '@/addon/hsx_components/core'
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
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
import EmptyState from '@/addon/hsx_recycle/components/empty-state/index.vue'
import { getCapitalAccountOptions } from '@/addon/hsx_recycle/api/recycle_order'
const hsxFeedback = useFeedback()


const route = useRoute()
const router = useRouter()
const loading = ref(false)
const saving = ref(false)
const tableData = ref<any[]>([])
const statusOptions = ref<any[]>([])
const manualPrintActions = ref<any[]>([])
const detailVisible = ref(false)
const detail = ref<any>(null)
const erpManaged = ref(false)
const capitalAccounts = ref<any[]>([])

const search = reactive({
  keyword: '',
  status: route.query.status !== undefined ? String(route.query.status) : '',
  source_order_id: route.query.source_order_id || '',
  create_time: route.query.start_time && route.query.end_time
    ? [String(route.query.start_time), String(route.query.end_time)]
    : []
})
const queryModel = computed({
  get: () => ({ keyword: search.keyword, status: search.status }),
  set: value => Object.assign(search, { keyword: '', status: '' }, value)
})
const querySchema = computed<ProFormField[]>(() => [
  { prop: 'keyword', label: '关键词', component: 'input', placeholder: '代卖单号 / 原订单 / IMEI / 客户手机', props: { clearable: true } },
  { prop: 'status', label: '状态', component: 'select', placeholder: '全部状态', props: { clearable: true }, options: statusOptions.value }
])
function searchConsignments(values: Record<string, any>) {
  Object.assign(search, { keyword: '', status: '' }, values)
  search.keyword = String(search.keyword || '').trim()
  handleSearch()
}
function clearQueryScope() { search.source_order_id = ''; search.create_time = [] }
const page = reactive({ page: 1, limit: 10, total: 0 })

const actionDialog = reactive({ visible: false, title: '', type: '', row: null as any })
const actionForm = reactive({
  listing_price: 0,
  sold_price: 0,
  settlement_amount: 0,
  capital_account_id: undefined as number | undefined,
  payment_images: '',
  remark: ''
})
const capitalAccountLabel = (account: any) => `${account.type_name ? `[${account.type_name}] ` : ''}${account.name || account.account_name || '未命名账户'}`

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
const fetchFinanceCapability = async () => {
  try {
    const res: any = await getCapitalAccountOptions()
    erpManaged.value = !!res.data?.payment_managed_by_erp
    capitalAccounts.value = Array.isArray(res.data?.accounts) ? res.data.accounts : []
    const defaultAccount = capitalAccounts.value.find((item: any) => Number(item.is_default) === 1) || capitalAccounts.value[0]
    actionForm.capital_account_id = defaultAccount?.id
  } catch (error) {
    erpManaged.value = false
    capitalAccounts.value = []
  }
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
    hsxFeedback.success('已取消')
    fetchList()
    return
  }
  if (command === 'sold' && erpManaged.value) {
    router.push({ path: '/site/hsx_erp/sale', query: { imei: row.device_imei || '', asset_source_id: row.source_device_id || '', t: Date.now() } })
    return
  }
  actionDialog.type = command
  actionDialog.row = row
  actionDialog.title = command === 'listing' ? '设置挂牌价' : command === 'sold' ? '登记售出' : '结算客户'
  actionForm.listing_price = Number(row.listing_price || 0)
  actionForm.sold_price = Number(row.sold_price || 0)
  actionForm.settlement_amount = Number(row.settlement_amount || 0)
  const defaultAccount = capitalAccounts.value.find((item: any) => Number(item.is_default) === 1) || capitalAccounts.value[0]
  actionForm.capital_account_id = defaultAccount?.id
  actionForm.payment_images = ''
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
    hsxFeedback.error(error?.message || '打印失败')
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
      if (erpManaged.value && !actionForm.capital_account_id) {
        hsxFeedback.warning(capitalAccounts.value.length ? '请选择ERP实际出款账户' : 'ERP没有可用资金账户，请先完成资金账户配置')
        return
      }
      await settleConsignment(id, {
        settlement_amount: actionForm.settlement_amount,
        capital_account_id: actionForm.capital_account_id || 0,
        payment_images: actionForm.payment_images,
        request_id: `consignment-${id}-${Date.now()}`,
        remark: actionForm.remark
      })
    }
    hsxFeedback.success('操作成功')
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
  fetchFinanceCapability()
})
</script>

<style scoped>
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
