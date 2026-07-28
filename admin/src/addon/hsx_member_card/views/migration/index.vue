<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="page-head">
                <div>
                    <div class="text-page-title">期初数据迁移</div>
                    <p>从旧会员卡平台迁移卡项、客户和剩余权益，支持以后再次同步。</p>
                </div>
                <el-button :icon="Refresh" @click="loadTasks()">刷新任务</el-button>
            </div>

            <el-alert type="warning" :closable="false" show-icon class="security-alert">
                <template #title>期初同步不会重复产生 ERP 收款、应收或员工绩效</template>
                旧平台密码只用于本次登录，不会保存。旧接口只提供累计已用次数，无法生成历史逐笔核销明细；已有本地核销或余额调整的会员卡不会被旧平台余额直接覆盖。
            </el-alert>

            <div class="migration-grid">
                <section class="connect-panel">
                    <div class="section-title">
                        <span class="section-icon"><el-icon><Connection /></el-icon></span>
                        <div>
                            <b>连接旧平台</b>
                            <p>输入旧平台主账号，系统将自动分页读取全部数据。</p>
                        </div>
                    </div>
                    <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent>
                        <el-form-item label="旧平台手机号" prop="phone">
                            <el-input v-model.trim="form.phone" maxlength="11" size="large" placeholder="请输入11位手机号" :prefix-icon="Phone" />
                        </el-form-item>
                        <el-form-item label="旧平台登录密码" prop="password">
                            <el-input
                                v-model="form.password"
                                type="password"
                                size="large"
                                placeholder="仅用于换取本次同步 Token"
                                autocomplete="new-password"
                                show-password
                                :prefix-icon="Lock"
                                @keyup.enter="startSync"
                            />
                        </el-form-item>
                        <el-button type="primary" size="large" class="sync-button" :loading="starting" @click="startSync">
                            <el-icon><Download /></el-icon>
                            {{ starting ? '正在连接旧平台' : '登录并开始同步' }}
                        </el-button>
                    </el-form>
                    <div class="sync-guide">
                        <div><span>1</span>验证账号并读取数据量</div>
                        <div><span>2</span>后台同步卡项、客户和持卡余额</div>
                        <div><span>3</span>生成完整结果和冲突明细</div>
                    </div>
                </section>

                <section class="overview-panel">
                    <div class="section-heading">最近一次同步</div>
                    <template v-if="latestTask">
                        <div class="latest-head">
                            <div>
                                <div class="store-name">{{ latestTask.summary_json?.store_name || '旧会员卡平台' }}</div>
                                <div class="muted">{{ latestTask.source_account }} · {{ timeText(latestTask.create_at) }}</div>
                            </div>
                            <el-tag :type="statusType(latestTask.status)" effect="light">{{ latestTask.status_text }}</el-tag>
                        </div>
                        <el-progress
                            v-if="isRunning(latestTask.status)"
                            :percentage="Number(latestTask.progress || 0)"
                            :stroke-width="10"
                            striped
                            striped-flow
                        />
                        <div class="summary-cards">
                            <div><span>卡项</span><b>{{ latestTask.product_total || 0 }}</b></div>
                            <div><span>客户</span><b>{{ latestTask.customer_total || 0 }}</b></div>
                            <div><span>持卡</span><b>{{ latestTask.card_total || 0 }}</b></div>
                        </div>
                        <div class="result-row">
                            <span class="success">新增 {{ latestTask.created_count || 0 }}</span>
                            <span>手机号关联 {{ latestTask.reused_count || 0 }}</span>
                            <span>更新 {{ latestTask.updated_count || 0 }}</span>
                            <span class="warning">冲突 {{ latestTask.conflict_count || 0 }}</span>
                            <span class="danger">失败 {{ latestTask.error_count || 0 }}</span>
                        </div>
                        <el-alert
                            v-if="latestTask.error_message"
                            :title="latestTask.error_message"
                            type="error"
                            :closable="false"
                            show-icon
                        />
                    </template>
                    <el-empty v-else description="尚未执行过同步" :image-size="90" />
                </section>
            </div>

            <div class="task-section">
                <div class="table-head">
                    <div>
                        <div class="section-heading">同步记录</div>
                        <p>每次同步均独立留痕，可以查看具体新增账号和异常数据。</p>
                    </div>
                    <el-select v-model="query.status" clearable placeholder="全部状态" @change="loadTasks">
                        <el-option label="队列等待中" value="queued" />
                        <el-option label="同步中" value="processing" />
                        <el-option label="同步完成" value="completed" />
                        <el-option label="部分完成" value="partial" />
                        <el-option label="同步失败" value="failed" />
                    </el-select>
                </div>
                <el-table :data="rows" v-loading="loading" row-key="id">
                    <el-table-column label="来源门店" min-width="190">
                        <template #default="{ row }">
                            <b>{{ row.summary_json?.store_name || '旧会员卡平台' }}</b>
                            <div class="muted">{{ row.source_account }} · 门店ID {{ row.source_store_id || '—' }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="数据范围" min-width="190">
                        <template #default="{ row }">
                            卡项 {{ row.product_total || 0 }} · 客户 {{ row.customer_total || 0 }} · 持卡 {{ row.card_total || 0 }}
                            <div v-if="isRunning(row.status)" class="progress-line">
                                <el-progress :percentage="Number(row.progress || 0)" :show-text="false" />
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="处理结果" min-width="250">
                        <template #default="{ row }">
                            <div class="compact-results">
                                <span class="success">新增 {{ row.created_count || 0 }}</span>
                                <span>手机号关联 {{ row.reused_count || 0 }}</span>
                                <span>更新 {{ row.updated_count || 0 }}</span>
                                <span class="warning">冲突 {{ row.conflict_count || 0 }}</span>
                                <span class="danger">失败 {{ row.error_count || 0 }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="120">
                        <template #default="{ row }">
                            <el-tag :type="statusType(row.status)" effect="light">{{ row.status_text }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="发起人 / 时间" min-width="170">
                        <template #default="{ row }">
                            {{ row.operator_name || '系统' }}
                            <div class="muted">{{ timeText(row.create_at) }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="90" align="right">
                        <template #default="{ row }">
                            <el-button link type="primary" @click="openDetail(row)">详情</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination">
                    <el-pagination
                        v-model:current-page="query.page"
                        v-model:page-size="query.limit"
                        layout="total,prev,pager,next"
                        :total="total"
                        @current-change="loadTasks"
                    />
                </div>
            </div>
        </el-card>

        <el-drawer v-model="drawerVisible" title="同步任务详情" size="760px" destroy-on-close>
            <template v-if="currentTask">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="来源门店">{{ currentTask.summary_json?.store_name || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="来源账号">{{ currentTask.source_account }}</el-descriptions-item>
                    <el-descriptions-item label="任务状态">
                        <el-tag :type="statusType(currentTask.status)">{{ currentTask.status_text }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="发起时间">{{ timeText(currentTask.create_at) }}</el-descriptions-item>
                    <el-descriptions-item label="处理说明" :span="2">{{ currentTask.message || '—' }}</el-descriptions-item>
                </el-descriptions>
                <div class="drawer-summary">
                    <div><span>新增</span><b class="success">{{ currentTask.created_count || 0 }}</b></div>
                    <div><span>手机号关联</span><b>{{ currentTask.reused_count || 0 }}</b></div>
                    <div><span>更新</span><b>{{ currentTask.updated_count || 0 }}</b></div>
                    <div><span>跳过</span><b>{{ currentTask.skipped_count || 0 }}</b></div>
                    <div><span>冲突</span><b class="warning">{{ currentTask.conflict_count || 0 }}</b></div>
                    <div><span>失败</span><b class="danger">{{ currentTask.error_count || 0 }}</b></div>
                </div>
                <div class="detail-filter">
                    <el-select v-model="itemQuery.entity_type" clearable placeholder="全部数据" @change="loadItems">
                        <el-option label="卡项" value="product" />
                        <el-option label="客户" value="customer" />
                        <el-option label="持卡关系" value="card" />
                    </el-select>
                    <el-select v-model="itemQuery.status" clearable placeholder="全部结果" @change="loadItems">
                        <el-option label="成功" value="success" />
                        <el-option label="冲突" value="conflict" />
                        <el-option label="失败" value="failed" />
                    </el-select>
                    <el-input v-model.trim="itemQuery.keyword" clearable placeholder="姓名 / 手机号 / 外部ID" @keyup.enter="loadItems" />
                    <el-button @click="loadItems">查询</el-button>
                </div>
                <el-table :data="itemRows" v-loading="itemLoading" max-height="480">
                    <el-table-column label="类型" width="90">
                        <template #default="{ row }">{{ entityText(row.entity_type) }}</template>
                    </el-table-column>
                    <el-table-column label="数据" min-width="190">
                        <template #default="{ row }">
                            <b>{{ row.display_name || '—' }}</b>
                            <div class="muted">{{ row.mobile || row.external_id }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="结果" width="100">
                        <template #default="{ row }">
                            <el-tag :type="actionType(row.action)" size="small">{{ actionText(row.action) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="message" label="说明" min-width="240" show-overflow-tooltip />
                </el-table>
                <div class="pagination">
                    <el-pagination
                        v-model:current-page="itemQuery.page"
                        v-model:page-size="itemQuery.limit"
                        layout="total,prev,pager,next"
                        :total="itemTotal"
                        @current-change="loadItems"
                    />
                </div>
            </template>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { Connection, Download, Lock, Phone, Refresh } from '@element-plus/icons-vue'
import {
    getMemberCardMigrationItems,
    getMemberCardMigrationTask,
    getMemberCardMigrationTasks,
    startMemberCardMigration
} from '../../api'

const formRef = ref<FormInstance>()
const form = reactive({ phone: '', password: '' })
const rules: FormRules = {
    phone: [
        { required: true, message: '请输入旧平台手机号', trigger: 'blur' },
        { pattern: /^1\d{10}$/, message: '请输入正确的11位手机号', trigger: 'blur' }
    ],
    password: [{ required: true, message: '请输入旧平台登录密码', trigger: 'blur' }]
}
const query = reactive({ status: '', page: 1, limit: 15 })
const rows = ref<any[]>([])
const total = ref(0)
const loading = ref(false)
const starting = ref(false)
const drawerVisible = ref(false)
const currentTask = ref<any>()
const itemRows = ref<any[]>([])
const itemTotal = ref(0)
const itemLoading = ref(false)
const itemQuery = reactive({ entity_type: '', status: '', keyword: '', page: 1, limit: 20 })
const latestTask = computed(() => rows.value[0])
let pollTimer: ReturnType<typeof setInterval> | undefined

const isRunning = (status: string) => ['pending', 'queued', 'processing'].includes(status)
const statusType = (status: string) => ({ completed: 'success', partial: 'warning', failed: 'danger', processing: 'primary', queued: 'info' } as any)[status] || 'info'
const actionType = (action: string) => ({ created: 'success', updated: 'primary', reused: 'info', skipped: 'info', conflict: 'warning', failed: 'danger' } as any)[action] || 'info'
const actionText = (action: string) => ({ created: '新增', updated: '更新', reused: '手机号关联', skipped: '跳过', conflict: '冲突', failed: '失败' } as any)[action] || action
const entityText = (type: string) => ({ product: '卡项', customer: '客户', card: '持卡' } as any)[type] || type
const timeText = (value: any) => {
    const timestamp = Number(value || 0)
    if (!timestamp) return '—'
    return new Date(timestamp * 1000).toLocaleString('zh-CN', { hour12: false })
}

const loadTasks = async (silent = false) => {
    if (!silent) loading.value = true
    try {
        const data: any = (await getMemberCardMigrationTasks(query)).data || {}
        rows.value = data.data || data.list || []
        total.value = Number(data.total || 0)
    } finally {
        if (!silent) loading.value = false
    }
}

const startSync = async () => {
    await formRef.value?.validate()
    starting.value = true
    try {
        const response: any = await startMemberCardMigration({ phone: form.phone, password: form.password })
        form.password = ''
        ElMessage.success(response?.msg || response?.data?.message || '连接成功，数据正在后台同步')
        query.page = 1
        await loadTasks()
    } finally {
        starting.value = false
    }
}

const openDetail = async (row: any) => {
    drawerVisible.value = true
    currentTask.value = (await getMemberCardMigrationTask(Number(row.id))).data || row
    Object.assign(itemQuery, { entity_type: '', status: '', keyword: '', page: 1 })
    await loadItems()
}

const loadItems = async () => {
    if (!currentTask.value?.id) return
    itemLoading.value = true
    try {
        const data: any = (await getMemberCardMigrationItems(Number(currentTask.value.id), itemQuery)).data || {}
        itemRows.value = data.data || data.list || []
        itemTotal.value = Number(data.total || 0)
    } finally {
        itemLoading.value = false
    }
}

onMounted(async () => {
    await loadTasks()
    pollTimer = setInterval(async () => {
        if (rows.value.some(row => isRunning(row.status))) {
            await loadTasks(true)
            if (drawerVisible.value && currentTask.value && isRunning(currentTask.value.status)) {
                currentTask.value = (await getMemberCardMigrationTask(Number(currentTask.value.id))).data || currentTask.value
                await loadItems()
            }
        }
    }, 3000)
})
onBeforeUnmount(() => pollTimer && clearInterval(pollTimer))
</script>

<style scoped>
.page-head, .table-head, .latest-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.page-head { margin-bottom: 18px; }
.page-head p, .table-head p, .section-title p { margin: 6px 0 0; color: var(--el-text-color-secondary); line-height: 1.6; }
.security-alert { margin-bottom: 18px; }
.migration-grid { display: grid; grid-template-columns: minmax(360px, 0.85fr) minmax(440px, 1.15fr); gap: 18px; }
.connect-panel, .overview-panel, .task-section { padding: 22px; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; background: var(--el-bg-color); }
.section-title { display: flex; gap: 12px; margin-bottom: 18px; }
.section-title b, .section-heading { color: var(--el-text-color-primary); font-size: 17px; font-weight: 600; }
.section-icon { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; flex: 0 0 42px; border-radius: 8px; color: var(--el-color-primary); background: var(--el-color-primary-light-9); font-size: 21px; }
.sync-button { width: 100%; }
.sync-guide { display: grid; gap: 10px; margin-top: 18px; padding: 14px; border-radius: 6px; background: var(--el-fill-color-lighter); color: var(--el-text-color-regular); }
.sync-guide div { display: flex; align-items: center; gap: 9px; }
.sync-guide span { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; color: var(--el-color-primary); background: var(--el-color-primary-light-8); font-size: 12px; font-weight: 600; }
.store-name { font-size: 19px; font-weight: 600; }
.muted { margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; }
.overview-panel :deep(.el-progress) { margin-top: 20px; }
.summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 22px 0 18px; }
.summary-cards div, .drawer-summary div { padding: 16px; border-radius: 6px; background: var(--el-fill-color-lighter); }
.summary-cards span, .drawer-summary span { display: block; color: var(--el-text-color-secondary); font-size: 13px; }
.summary-cards b, .drawer-summary b { display: block; margin-top: 7px; font-size: 24px; }
.result-row, .compact-results { display: flex; flex-wrap: wrap; gap: 8px 16px; color: var(--el-text-color-regular); font-size: 13px; }
.result-row { padding-top: 16px; border-top: 1px solid var(--el-border-color-lighter); }
.success { color: var(--el-color-success) !important; }
.warning { color: var(--el-color-warning) !important; }
.danger { color: var(--el-color-danger) !important; }
.task-section { margin-top: 18px; }
.table-head { margin-bottom: 16px; }
.table-head .el-select { width: 150px; }
.progress-line { width: 150px; margin-top: 8px; }
.pagination { display: flex; justify-content: flex-end; margin-top: 16px; }
.drawer-summary { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin: 18px 0; }
.drawer-summary div { padding: 12px; text-align: center; }
.drawer-summary b { font-size: 19px; }
.detail-filter { display: flex; gap: 10px; margin-bottom: 14px; }
.detail-filter .el-select { width: 130px; }
.detail-filter .el-input { flex: 1; }
@media (max-width: 1100px) {
    .migration-grid { grid-template-columns: 1fr; }
}
</style>
