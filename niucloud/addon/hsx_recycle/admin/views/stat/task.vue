<template>
    <PremiumTheme class="recycle-my-task">
        <el-card shadow="never">
            <div class="flex justify-between items-center mb-[12px]">
                <span class="text-page-title">{{ t('我的任务') }}</span>
                <div class="flex gap-2">
                    <el-button :icon="Setting" @click="openAssignmentSettings">{{ t('默认负责人') }}</el-button>
                    <el-button :icon="Refresh" @click="loadAll">{{ t('刷新') }}</el-button>
                </div>
            </div>

            <!-- 无负责环节 -->
            <EmptyState
                v-if="!loading && stages.length === 0"
                icon="folder"
                title="你当前没有负责的环节"
                description="任务按角色权限自动分发。请联系管理员在「角色管理」为你的角色勾选对应动作权限（物流车取货 / 签收 / 质检 / 定价 / 确认 / 打款）。"
            />

            <template v-else>
                <!-- 环节切换 -->
                <el-tabs v-model="activeStage" @tab-change="onStageChange">
                    <el-tab-pane :label="t('全部')" name="" />
                    <el-tab-pane
                        v-for="s in stages"
                        :key="s.stage_key"
                        :label="s.name"
                        :name="s.stage_key"
                    />
                </el-tabs>

                <!-- 搜索 -->
                <div class="flex items-center mb-[12px]">
                    <el-input
                        v-model="keyword"
                        :placeholder="searchPlaceholder"
                        clearable
                        style="width: 260px"
                        @keyup.enter="reload"
                        @clear="reload"
                    >
                        <template #prefix><el-icon><Search /></el-icon></template>
                    </el-input>
                    <el-button type="primary" class="ml-2" @click="reload">{{ t('搜索') }}</el-button>
                </div>

                <!-- 工单表（FIFO：越靠上越早进入该环节，先处理） -->
                <el-table :data="tableData" v-loading="loading" size="large" row-key="device_id">
                    <template #empty>
                        <EmptyState v-if="!loading" icon="inbox" title="暂无待处理工单" description="该环节当前没有排队的设备。" />
                    </template>

                    <el-table-column type="index" :label="t('队列')" width="64" align="center" />

                    <el-table-column :label="t('设备 / 订单')" min-width="220">
                        <template #default="{ row }">
                            <template v-if="row.is_order">
                                <div class="font-medium">{{ t('订单') }} {{ row.order_no || row.order_id }}</div>
                                <div class="text-gray-400 text-xs">
                                    {{ row.customer_name || '—' }}
                                    <span v-if="row.express_no"> · {{ row.express_company }} {{ row.express_no }}</span>
                                </div>
                                <div v-if="row.stage_key === 'pickup'" class="pickup-summary">
                                    <span>{{ row.logistics_pickup_address || '未填写取货地点' }}</span>
                                    <span>{{ [row.logistics_name, row.logistics_vehicle_no].filter(Boolean).join(' · ') }}</span>
                                    <span>{{ [row.logistics_contact_name, row.logistics_contact_mobile].filter(Boolean).join(' · ') }}</span>
                                    <span v-if="row.logistics_eta_at">预计 {{ formatDateTime(row.logistics_eta_at) }} 可取</span>
                                </div>
                            </template>
                            <template v-else>
                                <div class="font-medium">{{ row.model || '—' }}</div>
                                <div class="text-gray-400 text-xs">{{ row.imei || row.sn || '—' }}</div>
                            </template>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('环节')" width="110" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" :type="stageTagType(row.stage_key)">{{ stageName(row.stage_key) }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('价格 / 台数')" width="120" align="right">
                        <template #default="{ row }">
                            <span v-if="row.is_order">{{ row.device_count || 0 }} {{ t('台') }}</span>
                            <span v-else>¥{{ money(row.final_price || row.initial_price) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('责任人')" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.assignee_uid" size="small" :type="row.is_mine ? 'success' : 'info'" effect="plain">
                                {{ row.is_mine ? t('我') : row.assignee_name }}
                            </el-tag>
                            <span v-else class="text-gray-400 text-xs">{{ t('待分配') }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('operation')" fixed="right" align="right" width="240">
                        <template #default="{ row }">
                            <el-button v-if="row.assignee_uid" link @click="openAssign(row)">{{ t('转交') }}</el-button>
                            <el-button v-else type="warning" link @click="openAssignmentSettings">{{ t('检查分配规则') }}</el-button>
                            <el-button v-if="!row.assignee_uid" link @click="onClaim(row)">{{ t('我来处理') }}</el-button>
                            <el-button type="primary" link @click="onProcess(row)">{{ row.stage_key === 'pickup' ? t('查看订单') : t('去处理') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="flex justify-end mt-[16px]">
                    <el-pagination
                        v-model:current-page="pagination.page"
                        v-model:page-size="pagination.limit"
                        :page-sizes="[15, 30, 50]"
                        :total="pagination.total"
                        layout="total, sizes, prev, pager, next"
                        @size-change="reload"
                        @current-change="loadList"
                    />
                </div>
            </template>
        </el-card>

        <el-dialog v-model="assignDialog.visible" :title="assignDialog.row?.assignee_uid ? '转交任务' : '分配任务'" width="480px" destroy-on-close>
            <div class="assign-summary">
                <div class="font-medium">{{ assignDialog.row?.is_order ? `订单 ${assignDialog.row?.order_no || ''}` : (assignDialog.row?.model || '待处理设备') }}</div>
                <div class="text-gray-400 text-xs mt-1">{{ stageName(assignDialog.row?.stage_key || '') }}<span v-if="assignDialog.row?.imei"> · {{ assignDialog.row.imei }}</span></div>
            </div>
            <div v-loading="assignDialog.loading" class="assignee-list">
                <el-radio-group v-model="assignDialog.assigneeUid">
                    <el-radio v-for="user in assignDialog.users" :key="user.uid" :value="user.uid" class="assignee-option">
                        <el-avatar :size="32" :src="user.head_img || ''">{{ user.name?.slice(0, 1) }}</el-avatar>
                        <span class="assignee-name">{{ user.name }}</span>
                        <span v-if="user.username && user.username !== user.name" class="assignee-account">{{ user.username }}</span>
                    </el-radio>
                </el-radio-group>
                <EmptyState v-if="!assignDialog.loading && !assignDialog.users.length" icon="user" title="没有可分配员工" description="请先为员工角色开通当前环节的处理权限。" />
            </div>
            <template #footer>
                <el-button @click="assignDialog.visible = false">取消</el-button>
                <el-button type="primary" :disabled="!assignDialog.assigneeUid" :loading="assignDialog.saving" @click="confirmAssign">确认分配</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="settingsDialog.visible" title="默认负责人" width="620px" destroy-on-close>
            <el-alert type="info" :closable="false" show-icon title="只需设置一次。订单进入新环节后，系统会自动落库责任人并通知本人；转交只用于请假、调岗等特殊情况。" />
            <div v-loading="settingsDialog.loading" class="default-assignee-list">
                <div v-for="stage in settingsDialog.stages" :key="stage.stage_key" class="default-assignee-row">
                    <div class="default-assignee-stage">
                        <div class="font-medium">{{ stage.name }}</div>
                        <div class="text-xs text-gray-400">按角色动作权限匹配</div>
                    </div>
                    <el-select v-model="stage.default_uid" class="w-[280px]" placeholder="自动选择首位岗位员工" clearable>
                        <el-option v-for="user in stage.users" :key="user.uid" :label="user.name" :value="user.uid">
                            <span>{{ user.name }}</span><span class="float-right text-xs text-gray-400">{{ user.username }}</span>
                        </el-option>
                    </el-select>
                    <el-tag v-if="!stage.users?.length" type="danger" effect="plain">未配置岗位权限</el-tag>
                </div>
            </div>
            <template #footer>
                <el-button @click="settingsDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="settingsDialog.saving" @click="saveAssignmentDefaults">保存</el-button>
            </template>
        </el-dialog>
    </PremiumTheme>
</template>

<script setup lang="ts">
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import EmptyState from '@/addon/hsx_recycle/components/empty-state/index.vue'
import { t } from '@/lang'
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Search, Refresh, Setting } from '@element-plus/icons-vue'
import { getMyStages, getTaskList, getAssignableUsers, assignTask, claimTask, getTaskAssignmentSettings, saveTaskAssignmentSettings } from '../../api/task'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const stages = ref<any[]>([])
const activeStage = ref<string>(typeof route.query.stage === 'string' ? route.query.stage : '')
const keyword = ref(typeof route.query.keyword === 'string' ? route.query.keyword : '')
const tableData = ref<any[]>([])
const pagination = reactive({ page: 1, limit: 15, total: 0 })
const assignDialog = reactive<any>({ visible: false, loading: false, saving: false, row: null, users: [], assigneeUid: 0 })
const settingsDialog = reactive<any>({ visible: false, loading: false, saving: false, stages: [] })

const STAGE_NAME: Record<string, string> = {
    pickup: '待取货', sign: '待签收', check: '质检', price: '定价', confirm: '报价确认', pay: '打款', abnormal: '异常处理'
}
const STAGE_TAG: Record<string, string> = {
    pickup: 'warning', sign: 'info', check: 'primary', price: 'success', confirm: 'warning', pay: '', abnormal: 'danger'
}
const STAGE_ORDER = ['pickup', 'sign', 'check', 'price', 'confirm', 'pay', 'abnormal']
const stageName = (k: string) => STAGE_NAME[k] || k
const stageTagType = (k: string) => STAGE_TAG[k] ?? ''
const money = (v: any) => Number(v || 0).toFixed(2)
const searchPlaceholder = computed(() => activeStage.value === 'pickup'
    ? t('订单号 / 客户 / 车牌号 / 物流名称')
    : (activeStage.value === 'sign' ? t('订单号 / 客户 / 快递单号') : t('IMEI / SN / 型号')))
const formatDateTime = (value: any) => {
    const timestamp = Number(value || 0)
    if (!timestamp) return '—'
    const date = new Date(timestamp * 1000)
    const pad = (number: number) => String(number).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const loadMyStages = async () => {
    try {
        const res: any = await getMyStages()
        const keys: string[] = res.data || []
        // 仅保留我有权限的环节，按预设顺序展示
        stages.value = STAGE_ORDER
            .filter((k) => keys.includes(k))
            .map((k) => ({ stage_key: k, name: STAGE_NAME[k] }))
        // activeStage 若不在我的环节里则回退到全部
        if (activeStage.value && !keys.includes(activeStage.value)) activeStage.value = ''
    } catch (e) {
        stages.value = []
    }
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getTaskList({
            stage: activeStage.value,
            keyword: keyword.value,
            page: pagination.page,
            limit: pagination.limit
        })
        tableData.value = res.data?.list || []
        pagination.total = res.data?.count || 0
    } catch (e) {
        tableData.value = []
        pagination.total = 0
    } finally {
        loading.value = false
    }
}

const reload = () => {
    pagination.page = 1
    loadList()
}

const onStageChange = () => {
    pagination.page = 1
    loadList()
}

const loadAll = async () => {
    await loadMyStages()
    if (stages.value.length) await loadList()
}

const onClaim = async (row: any) => {
    await claimTask({ device_id: row.device_id, stage_key: row.stage_key })
    loadList()
}

const openAssign = async (row: any) => {
    assignDialog.visible = true
    assignDialog.loading = true
    assignDialog.row = row
    assignDialog.users = []
    assignDialog.assigneeUid = 0
    try {
        const res: any = await getAssignableUsers(row.stage_key)
        assignDialog.users = res.data || []
        const cacheKey = `hsx_recycle_last_assignee_${row.stage_key}`
        const remembered = Number(localStorage.getItem(cacheKey) || 0)
        const preferred = Number(row.assignee_uid || remembered || assignDialog.users[0]?.uid || 0)
        assignDialog.assigneeUid = assignDialog.users.some((item: any) => Number(item.uid) === preferred) ? preferred : Number(assignDialog.users[0]?.uid || 0)
    } finally {
        assignDialog.loading = false
    }
}

const confirmAssign = async () => {
    if (!assignDialog.row || !assignDialog.assigneeUid) return
    assignDialog.saving = true
    try {
        await assignTask({ device_id: assignDialog.row.device_id, stage_key: assignDialog.row.stage_key, assignee_uid: assignDialog.assigneeUid })
        localStorage.setItem(`hsx_recycle_last_assignee_${assignDialog.row.stage_key}`, String(assignDialog.assigneeUid))
        assignDialog.visible = false
        loadList()
    } finally {
        assignDialog.saving = false
    }
}

const openAssignmentSettings = async () => {
    settingsDialog.visible = true
    settingsDialog.loading = true
    try {
        const res: any = await getTaskAssignmentSettings()
        settingsDialog.stages = (res.data || []).map((item: any) => ({ ...item, default_uid: Number(item.default_uid || 0) || undefined }))
    } finally {
        settingsDialog.loading = false
    }
}

const saveAssignmentDefaults = async () => {
    settingsDialog.saving = true
    try {
        const defaults = Object.fromEntries(settingsDialog.stages.map((item: any) => [item.stage_key, Number(item.default_uid || 0)]))
        await saveTaskAssignmentSettings(defaults)
        settingsDialog.visible = false
        ElMessage.success('默认负责人已保存，后续任务将自动分配')
    } finally {
        settingsDialog.saving = false
    }
}

// 去回收订单管理页定位订单/设备，执行真正的签收/质检/定价/确认/打款动作
const onProcess = (row: any) => {
    const query: Record<string, any> = { order_id: row.order_id || '', t: Date.now() }
    if (!row.is_order && row.imei) query.imei = row.imei   // 设备级带 IMEI 精确定位；签收只带订单
    router.push({ path: '/site/recycle_order/list', query })
}

onMounted(() => {
    loadAll()
})
</script>

<style lang="scss" scoped>
.recycle-my-task {
    :deep(.el-tabs__header) { margin-bottom: 12px; }
}
.assign-summary { padding: 12px 14px; background: var(--el-fill-color-light); border-radius: 6px; margin-bottom: 14px; }
.assignee-list { min-height: 120px; max-height: 360px; overflow-y: auto; }
.assignee-list :deep(.el-radio-group) { width: 100%; display: block; }
.assignee-option { width: 100%; height: 54px; margin: 0; padding: 0 10px; border-bottom: 1px solid var(--el-border-color-lighter); }
.assignee-option :deep(.el-radio__label) { display: inline-flex; align-items: center; width: calc(100% - 24px); }
.assignee-name { margin-left: 10px; color: var(--el-text-color-primary); }.assignee-account { margin-left: auto; color: var(--el-text-color-secondary); font-size: 12px; }
.default-assignee-list { min-height: 180px; margin-top: 16px; }
.default-assignee-row { min-height: 68px; display: flex; align-items: center; gap: 16px; border-bottom: 1px solid var(--el-border-color-lighter); }
.default-assignee-stage { width: 170px; flex: none; }
.pickup-summary { display: grid; gap: 3px; margin-top: 8px; color: var(--el-text-color-secondary); line-height: 1.45; }
</style>
