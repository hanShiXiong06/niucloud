<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
        <div class="wecom-config-page">
        <div class="page-header">
            <div>
                <h1 class="text-page-title">企业微信协同</h1>
                <p class="page-subtitle">连接企业微信自建应用，将业务任务精准通知到责任人。</p>
            </div>
            <el-tag :type="config.enabled ? 'success' : 'info'" effect="plain">
                {{ config.enabled ? '已启用' : '未启用' }}
            </el-tag>
        </div>

        <el-alert type="info" :closable="false" show-icon class="mb-[16px]">
            <template #title>需要企业微信自建应用，并将接收通知的员工加入应用可见范围。</template>
        </el-alert>

        <el-tabs v-model="activeTab" @tab-change="onTabChange">
            <el-tab-pane label="应用连接" name="config">
                <el-form ref="formRef" :model="config" :rules="rules" label-width="150px" class="config-form" v-loading="configLoading">
                    <el-form-item label="启用应用通知">
                        <el-switch v-model="config.enabled" :active-value="1" :inactive-value="0" />
                    </el-form-item>
                    <el-form-item label="企业 ID" prop="corp_id">
                        <el-input v-model.trim="config.corp_id" placeholder="企业微信管理后台的企业 ID" clearable />
                    </el-form-item>
                    <el-form-item label="应用 AgentId" prop="agent_id">
                        <el-input-number v-model="config.agent_id" :min="1" :controls="false" class="number-input" />
                    </el-form-item>
                    <el-form-item label="应用 Secret" prop="secret">
                        <el-input v-model.trim="config.secret" type="password" show-password placeholder="自建应用 Secret" autocomplete="new-password" />
                    </el-form-item>
                    <el-form-item label="任务打开方式" prop="jump_mode">
                        <el-radio-group v-model="config.jump_mode">
                            <el-radio-button value="dual">网页 + 小程序</el-radio-button>
                            <el-radio-button value="miniapp">仅小程序</el-radio-button>
                            <el-radio-button value="web">仅网页</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item v-if="config.jump_mode !== 'miniapp'" label="网页管理端地址" prop="web_base_url">
                        <div class="field-stack">
                            <el-input v-model.trim="config.web_base_url" placeholder="https://example.com" clearable />
                            <span class="field-help">填写 PC 管理后台域名，不要追加 /site 或 /adminapp。</span>
                        </div>
                    </el-form-item>
                    <el-form-item v-if="config.jump_mode !== 'web'" label="管理小程序 AppID" prop="miniapp_appid">
                        <div class="field-stack">
                            <el-input v-model.trim="config.miniapp_appid" placeholder="例如 wxe00f1f93e4d87ac7" clearable />
                            <span class="field-help">只需要 AppID；该小程序必须先与当前企业微信应用完成关联。</span>
                        </div>
                    </el-form-item>
                    <el-form-item label="任务分配通知">
                        <el-switch v-model="config.task_notice_enabled" :active-value="1" :inactive-value="0" />
                    </el-form-item>
                    <el-form-item label="经营报告通知">
                        <div class="field-stack">
                            <el-switch v-model="config.report_notice_enabled" :active-value="1" :inactive-value="0" />
                            <span class="field-help">日报、周报和月报接收人在“经营分析 → 经营报告”中选择。</span>
                        </div>
                    </el-form-item>
                    <el-form-item label="回收任务入口">
                        <div class="field-stack">
                            <el-radio-group v-model="config.recycle_task_target">
                                <el-radio-button value="detail">订单详情（兼容）</el-radio-button>
                                <el-radio-button value="list">我的待办列表</el-radio-button>
                            </el-radio-group>
                            <span class="field-help">
                                {{ config.recycle_task_target === 'list'
                                    ? '适合连续处理；请确认线上管理小程序已发布 addon/hsx_recycle/pages/task/index。'
                                    : '直接打开订单详情，兼容尚未包含任务列表页面的旧版管理小程序。' }}
                            </span>
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" :loading="saving" @click="saveConfig()">保存配置</el-button>
                        <el-button :loading="testing" @click="testConnection">测试连接</el-button>
                    </el-form-item>
                </el-form>
            </el-tab-pane>

            <el-tab-pane name="staff">
                <template #label>
                    <span>员工绑定</span>
                    <el-badge v-if="unboundCount" :value="unboundCount" class="tab-badge" />
                </template>
                <div class="toolbar-row">
                    <el-input v-model="staffKeyword" clearable placeholder="搜索员工或企业微信 UserID" :prefix-icon="Search" />
                    <el-button :icon="Refresh" @click="loadStaff">刷新</el-button>
                </div>
                <el-table :data="filteredStaff" v-loading="staffLoading" row-key="uid">
                    <el-table-column label="员工" min-width="180">
                        <template #default="{ row }">
                            <div class="staff-name">{{ row.name }}</div>
                            <div class="secondary-text">{{ row.username }}<span v-if="row.mobile"> · {{ row.mobile }}</span></div>
                        </template>
                    </el-table-column>
                    <el-table-column label="企业微信成员账号 UserID" min-width="300">
                        <template #default="{ row }">
                            <el-input v-model.trim="row.wecom_userid" placeholder="例如 zhangsan" clearable />
                        </template>
                    </el-table-column>
                    <el-table-column label="通知" width="100" align="center">
                        <template #default="{ row }"><el-switch v-model="row.status" :active-value="1" :inactive-value="0" /></template>
                    </el-table-column>
                    <el-table-column label="状态" width="110" align="center">
                        <template #default="{ row }"><el-tag :type="row.wecom_userid ? 'success' : 'warning'" effect="plain">{{ row.wecom_userid ? '已绑定' : '待绑定' }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="操作" width="100" align="right">
                        <template #default="{ row }"><el-button type="primary" link :loading="row.saving" @click="saveStaff(row)">保存</el-button></template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <el-tab-pane label="消息日志" name="messages">
                <div class="toolbar-row">
                    <el-input v-model="messageQuery.keyword" clearable placeholder="搜索标题、员工或错误原因" :prefix-icon="Search" @keyup.enter="loadMessages" />
                    <el-select v-model="messageQuery.status" clearable placeholder="全部状态" @change="loadMessages">
                        <el-option label="待发送" value="pending" /><el-option label="发送成功" value="success" />
                        <el-option label="发送失败" value="failed" /><el-option label="已跳过" value="skipped" />
                    </el-select>
                    <el-button type="primary" @click="loadMessages">查询</el-button>
                </div>
                <el-table :data="messages" v-loading="messageLoading" row-key="id">
                    <el-table-column label="消息" min-width="260">
                        <template #default="{ row }"><div class="staff-name">{{ row.title }}</div><div class="secondary-text">{{ row.receiver_name || row.wecom_userid || '未绑定员工' }}</div></template>
                    </el-table-column>
                    <el-table-column label="状态" width="110" align="center">
                        <template #default="{ row }"><el-tag :type="statusType(row.status)" effect="plain">{{ statusLabel(row.status) }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="入口" width="150">
                        <template #default="{ row }">
                            <div class="target-tags">
                                <el-tag size="small" :type="row.target_plugin === 'hsx_erp' ? 'success' : 'primary'" effect="plain">{{ row.target_label }}</el-tag>
                                <el-tag v-if="row.miniapp_target_configured" size="small" effect="plain">小程序</el-tag>
                                <el-tag v-if="row.web_target_configured" size="small" type="info" effect="plain">网页</el-tag>
                                <span v-if="!row.miniapp_target_configured && !row.web_target_configured" class="secondary-text">无跳转</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="结果" min-width="240"><template #default="{ row }"><span :class="row.status === 'failed' ? 'error-text' : 'secondary-text'">{{ row.error_message || (row.status === 'success' ? '已送达企业微信' : '—') }}</span></template></el-table-column>
                    <el-table-column label="时间" width="170"><template #default="{ row }">{{ formatTime(row.sent_at || row.create_at) }}</template></el-table-column>
                    <el-table-column label="操作" width="90" align="right"><template #default="{ row }"><el-button v-if="row.status === 'failed'" type="primary" link @click="retryMessage(row)">重试</el-button></template></el-table-column>
                </el-table>
                <div class="pagination-row"><el-pagination v-model:current-page="messageQuery.page" v-model:page-size="messageQuery.limit" layout="total, prev, pager, next" :total="messageTotal" @current-change="loadMessages" /></div>
            </el-tab-pane>
        </el-tabs>
        </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import { getWecomConfig, getWecomMessages, getWecomStaff, retryWecomMessage, saveWecomConfig, saveWecomStaff, testWecomConnection } from '../../api'

const activeTab = ref('config')
const formRef = ref<FormInstance>()
const configLoading = ref(false)
const saving = ref(false)
const testing = ref(false)
const config = reactive({ enabled: 0, corp_id: '', agent_id: 0, secret: '', web_base_url: '', miniapp_appid: '', jump_mode: 'web', recycle_task_target: 'detail', task_notice_enabled: 1, report_notice_enabled: 1, secret_configured: 0 })
const rules: FormRules = {
    corp_id: [{ required: true, message: '请输入企业 ID', trigger: 'blur' }],
    agent_id: [{ required: true, message: '请输入应用 AgentId', trigger: 'change' }],
    secret: [{ required: true, message: '请输入应用 Secret', trigger: 'blur' }],
    web_base_url: [{ pattern: /^https?:\/\//i, message: '请输入以 http:// 或 https:// 开头的访问地址', trigger: 'blur' }],
    miniapp_appid: [{ pattern: /^wx[a-zA-Z0-9]{16}$/, message: '请输入正确的小程序 AppID', trigger: 'blur' }]
}

const staffLoading = ref(false)
const staffKeyword = ref('')
const staff = ref<any[]>([])
const unboundCount = computed(() => staff.value.filter((item) => !item.wecom_userid).length)
const filteredStaff = computed(() => {
    const keyword = staffKeyword.value.trim().toLowerCase()
    if (!keyword) return staff.value
    return staff.value.filter((item) => [item.name, item.username, item.mobile, item.wecom_userid].some((value) => String(value || '').toLowerCase().includes(keyword)))
})

const messageLoading = ref(false)
const messages = ref<any[]>([])
const messageTotal = ref(0)
const messageQuery = reactive({ status: '', keyword: '', page: 1, limit: 15 })

const loadConfig = async () => { configLoading.value = true; try { Object.assign(config, (await getWecomConfig()).data || {}) } finally { configLoading.value = false } }
const validateConfig = async (required: boolean) => {
    if (!required && !config.enabled) {
        formRef.value?.clearValidate()
        return true
    }
    return Boolean(await formRef.value?.validate().catch(() => false))
}
const saveConfig = async (showMessage = true) => {
    if (!(await validateConfig(false))) return false
    saving.value = true
    try {
        Object.assign(config, (await saveWecomConfig(config)).data || {})
        if (showMessage) ElMessage.success('配置已保存')
        return true
    } finally {
        saving.value = false
    }
}
const testConnection = async () => {
    if (!(await validateConfig(true))) return
    if (!(await saveConfig(false))) return
    testing.value = true
    try {
        const res: any = await testWecomConnection()
        ElMessage.success(`连接成功${res.data?.agent_name ? `：${res.data.agent_name}` : ''}`)
    } finally {
        testing.value = false
    }
}
const loadStaff = async () => { staffLoading.value = true; try { staff.value = ((await getWecomStaff()).data || []).map((item: any) => ({ ...item, saving: false })) } finally { staffLoading.value = false } }
const saveStaff = async (row: any) => { row.saving = true; try { await saveWecomStaff(row.uid, { wecom_userid: row.wecom_userid, status: row.status }); ElMessage.success(`${row.name}的绑定已保存`) } finally { row.saving = false } }
const loadMessages = async () => { messageLoading.value = true; try { const data: any = (await getWecomMessages(messageQuery)).data || {}; messages.value = data.data || []; messageTotal.value = data.total || 0 } finally { messageLoading.value = false } }
const retryMessage = async (row: any) => { await retryWecomMessage(row.id); ElMessage.success('已重新发送'); loadMessages() }
const onTabChange = (name: string) => { if (name === 'staff' && !staff.value.length) loadStaff(); if (name === 'messages') loadMessages() }
const statusLabel = (status: string) => ({ pending: '待发送', success: '成功', failed: '失败', skipped: '已跳过' }[status] || status)
const statusType = (status: string) => ({ success: 'success', failed: 'danger', skipped: 'warning', pending: 'info' }[status] || 'info') as any
const formatTime = (value: any) => { const timestamp = Number(value || 0); if (!timestamp) return '—'; return new Date(timestamp * 1000).toLocaleString('zh-CN', { hour12: false }) }

onMounted(async () => { await Promise.all([loadConfig(), loadStaff()]) })
</script>

<style lang="scss" scoped>
.wecom-config-page { min-height: 100%; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
.page-subtitle { margin-top: 6px; color: var(--el-text-color-secondary); font-size: 14px; }
.config-form { width: min(760px, 100%); padding: 20px 0 4px; }
.config-form :deep(.el-input), .number-input { width: 100%; }
.field-stack { width: 100%; }.field-help { display: block; margin-top: 6px; color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.5; }
.toolbar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; padding: 14px 16px; border-radius: 4px; background: var(--el-fill-color-lighter); }
.toolbar-row .el-input { width: 320px; }.toolbar-row .el-select { width: 150px; }
.staff-name { color: var(--el-text-color-primary); font-weight: 500; }.secondary-text { color: var(--el-text-color-secondary); font-size: 13px; }.error-text { color: var(--el-color-danger); font-size: 13px; }
.target-tags { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.pagination-row { display: flex; justify-content: flex-end; margin-top: 16px; }.tab-badge { margin-left: 8px; }
@media (max-width: 768px) { .toolbar-row { align-items: stretch; flex-wrap: wrap; }.toolbar-row .el-input { width: 100%; } }
</style>
