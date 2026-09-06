<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="wecom-page" v-loading="configLoading">
                <div class="page-header">
                    <div>
                        <div class="page-eyebrow">{{ isPlatform ? '平台通道' : '消息协同' }}</div>
                        <h1 class="text-page-title">{{ isPlatform ? '企业微信服务商接入' : '企业微信通知' }}</h1>
                        <p class="page-subtitle">{{ isPlatform ? '每套 SaaS 只配置一次服务商通道和后台管理小程序。' : '授权后，业务待办会直接通知责任人并打开后台管理小程序。' }}</p>
                    </div>
                    <el-tag :type="pageStatus.type" effect="plain" size="large">{{ pageStatus.label }}</el-tag>
                </div>

                <template v-if="!configLoading">
                    <template v-if="isPlatform">
                        <section class="channel-summary">
                            <div class="channel-mark"><el-icon><Connection /></el-icon></div>
                            <div class="channel-main">
                                <div class="channel-title-row"><h2>{{ providerConfig.admin_miniapp_name || '后台管理小程序' }}</h2><el-tag :type="providerConfigured ? 'success' : 'warning'" effect="light">{{ providerConfigured ? '通道已配置' : '等待配置' }}</el-tag></div>
                                <p>{{ providerConfig.channel_code ? `通道 ${providerConfig.channel_code}` : '保存后，本系统下的客户即可一键授权企业微信。' }}</p>
                                <div class="summary-meta"><span>SuiteID：{{ maskValue(providerConfig.suite_id) }}</span><span>小程序：{{ maskValue(providerConfig.admin_miniapp_appid) }}</span><span>最近 Ticket：{{ formatTime(providerState.suite_ticket_at) }}</span></div>
                            </div>
                        </section>
                        <el-alert v-if="providerState.last_error" class="mb-[18px]" type="error" :closable="false" show-icon :title="providerState.last_error" />

                        <el-form ref="providerFormRef" :model="providerConfig" :rules="providerRules" label-width="165px" class="provider-form">
                            <div class="form-section">
                                <div class="section-heading"><div><h3>服务商通道</h3><p>这些参数只由平台管理员维护，客户站点不会看到密钥。</p></div></div>
                                <el-form-item label="启用服务商通道"><el-switch v-model="providerConfig.enabled" :active-value="1" :inactive-value="0" /></el-form-item>
                                <el-form-item label="通道标识" prop="channel_code"><el-input v-model.trim="providerConfig.channel_code" placeholder="例如 saas_a" clearable /><div class="field-help">用于区分不同服务器和后台管理小程序，保存后不建议随意修改。</div></el-form-item>
                                <el-form-item label="服务商企业 ID" prop="provider_corp_id"><el-input v-model.trim="providerConfig.provider_corp_id" placeholder="企业微信服务商 CorpID" clearable /></el-form-item>
                                <el-form-item label="SuiteID" prop="suite_id"><el-input v-model.trim="providerConfig.suite_id" placeholder="服务商应用 SuiteID" clearable /></el-form-item>
                                <el-form-item label="SuiteSecret" prop="suite_secret"><el-input v-model.trim="providerConfig.suite_secret" type="password" show-password autocomplete="new-password" placeholder="已配置时会以掩码显示" /></el-form-item>
                                <el-form-item label="回调 Token" prop="callback_token"><el-input v-model.trim="providerConfig.callback_token" type="password" show-password autocomplete="new-password" placeholder="企业微信回调 Token" /></el-form-item>
                                <el-form-item label="EncodingAESKey" prop="encoding_aes_key"><el-input v-model.trim="providerConfig.encoding_aes_key" type="password" show-password autocomplete="new-password" placeholder="企业微信回调 EncodingAESKey" /></el-form-item>
                            </div>
                            <div class="form-section">
                                <div class="section-heading"><div><h3>后台管理入口</h3><p>企业微信通知统一跳转到本套 SaaS 的后台管理小程序。</p></div></div>
                                <el-form-item label="管理小程序 AppID" prop="admin_miniapp_appid"><el-input v-model.trim="providerConfig.admin_miniapp_appid" placeholder="例如 wxe00f1f93e4d87ac7" clearable /></el-form-item>
                                <el-form-item label="管理小程序名称" prop="admin_miniapp_name"><el-input v-model.trim="providerConfig.admin_miniapp_name" placeholder="客户在企业微信中看到的名称" clearable /></el-form-item>
                                <el-form-item label="网页管理端地址" prop="web_base_url"><el-input v-model.trim="providerConfig.web_base_url" placeholder="https://example.com" clearable /><div class="field-help">用于电脑端打开业务页面，也作为授权完成后的安全回跳域名。</div></el-form-item>
                            </div>
                            <div class="form-section callback-section">
                                <div class="section-heading"><div><h3>企业微信回调地址</h3><p>复制到企业微信服务商后台，地址由系统生成，请勿手工拼接。</p></div></div>
                                <el-form-item label="事件回调 URL"><el-input :model-value="providerConfig.event_callback_url" readonly placeholder="保存通道后由系统生成"><template #append><el-button :icon="CopyDocument" @click="copyText(providerConfig.event_callback_url)" /></template></el-input></el-form-item>
                                <el-form-item label="授权回调 URL"><el-input :model-value="providerConfig.auth_callback_url" readonly placeholder="保存通道后由系统生成"><template #append><el-button :icon="CopyDocument" @click="copyText(providerConfig.auth_callback_url)" /></template></el-input></el-form-item>
                            </div>
                            <div class="form-actions"><el-button type="primary" :loading="providerSaving" @click="saveProviderConfig">保存服务商通道</el-button><el-button type="success" plain :loading="providerTesting" :disabled="!providerConfig.enabled" @click="testProviderConnection">验证服务商通道</el-button><el-button :icon="Refresh" :loading="providerLoading" @click="loadProviderConfig">刷新状态</el-button></div>
                            <el-alert v-if="providerTestResult" class="provider-test-result" :type="providerTestResult.connected ? 'success' : 'error'" :closable="false" show-icon>
                                <template #title>{{ providerTestResult.message }}</template>
                                <div class="provider-test-meta"><span>通道：{{ providerTestResult.channel_code || providerConfig.channel_code || '—' }}</span><span>SuiteID：{{ maskValue(providerTestResult.suite_id || providerConfig.suite_id) }}</span><span>SuiteTicket：{{ providerTestResult.suite_ticket_at ? `最近接收于 ${formatTime(providerTestResult.suite_ticket_at)}` : '尚未接收' }}</span><span>验证时间：{{ formatTime(providerTestResult.tested_at) }}</span></div>
                            </el-alert>
                        </el-form>
                    </template>

                    <template v-else>
                        <el-tabs v-model="activeTab" class="site-tabs" @tab-change="onTabChange">
                            <el-tab-pane label="接入与通知" name="config">
                                <div class="mode-picker">
                                    <div><strong>接入方式</strong><span>推荐使用服务商授权，不需要向平台提交企业 Secret。</span></div>
                                    <el-radio-group v-model="config.connection_mode"><el-radio-button value="provider">服务商一键授权</el-radio-button><el-radio-button value="self_built">自建应用（高级）</el-radio-button></el-radio-group>
                                </div>

                                <template v-if="isProviderMode">
                                    <section class="authorization-card" :class="`is-${authorizationStatus.key}`">
                                        <div class="authorization-icon"><el-icon><component :is="authorizationStatus.icon" /></el-icon></div>
                                        <div class="authorization-content">
                                            <div class="authorization-title-row"><div><h2>{{ authorizationStatus.title }}</h2><p>{{ authorizationStatus.description }}</p></div><el-tag :type="authorizationStatus.type" effect="light">{{ authorizationStatus.label }}</el-tag></div>
                                            <el-alert v-if="providerState.last_error" class="mt-[14px]" type="error" :closable="false" show-icon :title="providerState.last_error" />
                                            <div class="authorization-actions"><el-button type="primary" size="large" :loading="authorizing" :disabled="!providerState.configured" @click="startAuthorization">{{ isAuthorized ? '重新授权企业微信' : '一键授权企业微信' }}</el-button><el-button :icon="Refresh" :loading="checkingAuthorization" @click="checkAuthorization">检查授权状态</el-button></div>
                                        </div>
                                    </section>
                                    <div class="onboarding-grid">
                                        <article :class="{ done: isAuthorized }"><span>1</span><div><strong>授权企业微信</strong><p>{{ isAuthorized ? '企业授权已生效' : '管理员确认一次即可' }}</p></div></article>
                                        <article :class="{ done: isAuthorized && boundCount > 0 }"><span>2</span><div><strong>绑定接收员工</strong><p>{{ boundCount ? `已绑定 ${boundCount} 人` : '授权后绑定责任人' }}</p></div></article>
                                        <article :class="{ done: testSucceeded }"><span>3</span><div><strong>发送测试通知</strong><p>{{ testSucceeded ? '测试消息发送成功' : '确认消息和跳转可用' }}</p></div></article>
                                    </div>
                                    <section class="detail-panel">
                                        <div class="section-heading compact"><div><h3>当前授权</h3><p>授权信息由企业微信返回，无需手工填写。</p></div></div>
                                        <div class="detail-grid"><div><span>授权企业</span><strong>{{ providerState.corp_name || '—' }}</strong></div><div><span>应用 AgentId</span><strong>{{ providerState.agent_id || '—' }}</strong></div><div><span>授权时间</span><strong>{{ formatTime(providerState.authorized_at) }}</strong></div><div><span>所属通道</span><strong>{{ providerState.channel_code || '—' }}</strong></div><div class="wide"><span>后台管理小程序</span><strong>{{ providerState.admin_miniapp_name || '待平台配置' }} <em>{{ maskValue(providerState.admin_miniapp_appid) }}</em></strong></div></div>
                                    </section>
                                </template>

                                <template v-else>
                                    <el-alert type="warning" :closable="false" show-icon class="mb-[18px]"><template #title>高级兼容模式需要当前企业自行创建企业微信应用并维护 Secret，仅建议已有旧配置的客户继续使用。</template></el-alert>
                                    <el-form ref="selfBuiltFormRef" :model="config" :rules="selfBuiltRules" label-width="155px" class="self-built-form">
                                        <el-form-item label="企业 ID" prop="corp_id"><el-input v-model.trim="config.corp_id" placeholder="企业微信管理后台的企业 ID" clearable /></el-form-item>
                                        <el-form-item label="应用 AgentId" prop="agent_id"><el-input-number v-model="config.agent_id" :min="1" :controls="false" class="number-input" /></el-form-item>
                                        <el-form-item label="应用 Secret" prop="secret"><el-input v-model.trim="config.secret" type="password" show-password autocomplete="new-password" placeholder="自建应用 Secret" /></el-form-item>
                                        <el-form-item v-if="config.jump_mode !== 'miniapp'" label="网页管理端地址" prop="web_base_url"><el-input v-model.trim="config.web_base_url" placeholder="https://example.com" clearable /></el-form-item>
                                        <el-form-item v-if="config.jump_mode !== 'web'" label="管理小程序 AppID" prop="miniapp_appid"><el-input v-model.trim="config.miniapp_appid" placeholder="例如 wxe00f1f93e4d87ac7" clearable /></el-form-item>
                                        <el-form-item><el-button :loading="testingConnection" @click="testConnection">测试自建应用连接</el-button></el-form-item>
                                    </el-form>
                                </template>

                                <section class="notification-settings">
                                    <div class="section-heading compact"><div><h3>通知规则</h3><p>决定哪些业务通知发送，以及员工点击后进入哪个后台页面。</p></div></div>
                                    <el-form label-width="155px" class="settings-form">
                                        <el-form-item label="启用企业微信通知"><el-switch v-model="config.enabled" :active-value="1" :inactive-value="0" /></el-form-item>
                                        <el-form-item label="任务打开方式">
                                            <div v-if="isProviderMode"><el-tag type="success" effect="plain">后台管理小程序</el-tag><div class="field-help">后台管理小程序由平台统一配置，当前站点无需填写 AppID。</div></div>
                                            <el-radio-group v-else v-model="config.jump_mode"><el-radio-button value="miniapp">后台小程序</el-radio-button><el-radio-button value="dual">小程序 + 网页</el-radio-button><el-radio-button value="web">仅网页</el-radio-button></el-radio-group>
                                        </el-form-item>
                                        <el-form-item label="任务分配通知"><el-switch v-model="config.task_notice_enabled" :active-value="1" :inactive-value="0" /></el-form-item>
                                        <el-form-item label="经营报告通知"><el-switch v-model="config.report_notice_enabled" :active-value="1" :inactive-value="0" /></el-form-item>
                                        <el-form-item label="回收任务入口"><el-radio-group v-model="config.recycle_task_target"><el-radio-button value="list">我的待办</el-radio-button><el-radio-button value="detail">订单详情</el-radio-button></el-radio-group></el-form-item>
                                        <el-form-item><el-button type="primary" :loading="saving" @click="saveSiteConfig">保存通知设置</el-button></el-form-item>
                                    </el-form>
                                </section>
                            </el-tab-pane>

                            <el-tab-pane name="staff">
                                <template #label><span>接收员工</span><el-badge v-if="unboundCount" :value="unboundCount" class="tab-badge" /></template>
                                <el-alert v-if="isProviderMode && !isAuthorized" type="warning" :closable="false" show-icon class="mb-[14px]" title="请先完成企业微信授权，再绑定员工并发送测试通知。" />
                                <div class="test-toolbar"><div class="test-copy"><el-icon><Bell /></el-icon><div><strong>真实测试通知</strong><span>选择员工后，会立即发送一张可点击的企业微信卡片。</span></div></div><div class="test-actions"><el-select v-model="testReceiverUid" filterable clearable placeholder="选择已绑定员工"><el-option v-for="item in boundStaff" :key="item.uid" :label="item.name" :value="item.uid" /></el-select><el-button type="primary" :loading="testingMessage" :disabled="!testReceiverUid" @click="sendTestMessage">发送测试通知</el-button></div></div>
                                <div class="toolbar-row"><el-input v-model="staffKeyword" clearable placeholder="搜索员工或企业微信 UserID" :prefix-icon="Search" /><el-button :icon="Refresh" @click="loadStaff">刷新</el-button></div>
                                <el-table :data="filteredStaff" v-loading="staffLoading" row-key="uid">
                                    <el-table-column label="员工" min-width="190"><template #default="{ row }"><div class="staff-name">{{ row.name }}</div><div class="secondary-text">{{ row.username }}<span v-if="row.mobile"> · {{ row.mobile }}</span></div></template></el-table-column>
                                    <el-table-column label="企业微信 UserID" min-width="260"><template #default="{ row }"><el-input v-model.trim="row.wecom_userid" :readonly="isProviderMode" :clearable="!isProviderMode" :placeholder="isProviderMode ? '由员工授权后自动回填' : '填写企业微信 UserID'" /></template></el-table-column>
                                    <el-table-column label="通知" width="90" align="center"><template #default="{ row }"><el-switch v-model="row.status" :active-value="1" :inactive-value="0" /></template></el-table-column>
                                    <el-table-column label="状态" width="105" align="center"><template #default="{ row }"><el-tag :type="row.wecom_userid ? 'success' : 'warning'" effect="plain">{{ row.wecom_userid ? '已绑定' : '待绑定' }}</el-tag></template></el-table-column>
                                    <el-table-column label="操作" width="235" align="right" fixed="right"><template #default="{ row }"><el-button v-if="isProviderMode" type="primary" link :loading="row.binding" :disabled="!isAuthorized" @click="bindStaff(row)">{{ row.is_current_user ? '绑定我自己' : '复制绑定链接' }}</el-button><el-button v-if="!isProviderMode || row.wecom_userid" type="primary" link :loading="row.saving" @click="saveStaff(row)">保存</el-button></template></el-table-column>
                                </el-table>
                            </el-tab-pane>

                            <el-tab-pane label="消息日志" name="messages">
                                <div class="toolbar-row"><el-input v-model="messageQuery.keyword" clearable placeholder="搜索标题、员工或错误原因" :prefix-icon="Search" @keyup.enter="loadMessages" /><el-select v-model="messageQuery.status" clearable placeholder="全部状态" @change="loadMessages"><el-option label="待发送" value="pending" /><el-option label="发送成功" value="success" /><el-option label="发送失败" value="failed" /><el-option label="已跳过" value="skipped" /></el-select><el-button type="primary" @click="loadMessages">查询</el-button></div>
                                <el-table :data="messages" v-loading="messageLoading" row-key="id">
                                    <el-table-column label="消息" min-width="250"><template #default="{ row }"><div class="staff-name">{{ row.title }}</div><div class="secondary-text">{{ row.receiver_name || row.wecom_userid || '未绑定员工' }}</div></template></el-table-column>
                                    <el-table-column label="状态" width="105" align="center"><template #default="{ row }"><el-tag :type="statusType(row.status)" effect="plain">{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
                                    <el-table-column label="打开入口" width="170"><template #default="{ row }"><div class="target-tags"><el-tag size="small" effect="plain">{{ row.target_label }}</el-tag><el-tag v-if="row.miniapp_target_configured" size="small" type="success" effect="plain">后台小程序</el-tag><el-tag v-if="row.web_target_configured" size="small" type="info" effect="plain">网页</el-tag></div></template></el-table-column>
                                    <el-table-column label="发送结果" min-width="260"><template #default="{ row }"><span :class="row.status === 'failed' ? 'error-text' : 'secondary-text'">{{ row.error_message || (row.status === 'success' ? '企业微信接口已受理' : '—') }}</span></template></el-table-column>
                                    <el-table-column label="时间" width="175"><template #default="{ row }">{{ formatTime(row.sent_at || row.create_at) }}</template></el-table-column>
                                    <el-table-column label="操作" width="80" align="right"><template #default="{ row }"><el-button v-if="row.status === 'failed'" type="primary" link @click="retryMessage(row)">重试</el-button></template></el-table-column>
                                </el-table>
                                <div class="pagination-row"><el-pagination v-model:current-page="messageQuery.page" v-model:page-size="messageQuery.limit" layout="total, prev, pager, next" :total="messageTotal" @current-change="loadMessages" /></div>
                            </el-tab-pane>
                        </el-tabs>
                    </template>
                </template>
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, markRaw, onMounted, reactive, ref } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Bell, CircleCheckFilled, Connection, CopyDocument, Refresh, Search, WarningFilled } from '@element-plus/icons-vue'
import { checkWecomAuthorization, getWecomConfig, getWecomMessages, getWecomProviderConfig, getWecomStaff, getWecomStaffBindUrl, retryWecomMessage, saveWecomConfig, saveWecomProviderConfig, saveWecomStaff, startWecomAuthorization, testWecomConnection, testWecomMessage, testWecomProviderConnection } from '../../api'

const SECRET_MASK = '******'
const activeTab = ref('config')
const configLoading = ref(true)
const saving = ref(false)
const testingConnection = ref(false)
const authorizing = ref(false)
const checkingAuthorization = ref(false)
const testSucceeded = ref(false)
const config = reactive<any>({ is_platform: 0, connection_mode: 'provider', enabled: 0, corp_id: '', agent_id: 0, secret: '', web_base_url: '', miniapp_appid: '', jump_mode: 'miniapp', recycle_task_target: 'list', task_notice_enabled: 1, report_notice_enabled: 1, secret_configured: 0 })

const providerDefaults = { configured: 0, status: '', channel_code: '', corp_name: '', agent_id: 0, authorized_at: 0, last_error: '', admin_miniapp_appid: '', admin_miniapp_name: '', event_callback_url: '', auth_callback_url: '', suite_ticket_at: 0 }
const providerState = reactive<any>({ ...providerDefaults })
const providerFormRef = ref<FormInstance>()
const providerLoading = ref(false)
const providerSaving = ref(false)
const providerTesting = ref(false)
const providerTestResult = ref<any>(null)
const providerConfig = reactive<any>({ enabled: 0, channel_code: '', provider_corp_id: '', suite_id: '', suite_secret: '', callback_token: '', encoding_aes_key: '', admin_miniapp_appid: '', admin_miniapp_name: '', web_base_url: '', event_callback_url: '', auth_callback_url: '' })
const providerRules: FormRules = {
    channel_code: [{ required: true, message: '请填写通道标识', trigger: 'blur' }], provider_corp_id: [{ required: true, message: '请填写服务商企业 ID', trigger: 'blur' }], suite_id: [{ required: true, message: '请填写 SuiteID', trigger: 'blur' }], suite_secret: [{ required: true, message: '请填写 SuiteSecret', trigger: 'blur' }], callback_token: [{ required: true, message: '请填写回调 Token', trigger: 'blur' }], encoding_aes_key: [{ required: true, message: '请填写 EncodingAESKey', trigger: 'blur' }], admin_miniapp_appid: [{ required: true, message: '请填写管理小程序 AppID', trigger: 'blur' }, { pattern: /^wx[a-zA-Z0-9]{16}$/, message: '请输入正确的小程序 AppID', trigger: 'blur' }], admin_miniapp_name: [{ required: true, message: '请填写管理小程序名称', trigger: 'blur' }], web_base_url: [{ required: true, message: '请填写网页管理端地址', trigger: 'blur' }, { pattern: /^https?:\/\//i, message: '请输入以 http:// 或 https:// 开头的地址', trigger: 'blur' }]
}
const selfBuiltFormRef = ref<FormInstance>()
const selfBuiltRules: FormRules = { corp_id: [{ required: true, message: '请输入企业 ID', trigger: 'blur' }], agent_id: [{ required: true, message: '请输入应用 AgentId', trigger: 'change' }], secret: [{ required: true, message: '请输入应用 Secret', trigger: 'blur' }], web_base_url: [{ pattern: /^https?:\/\//i, message: '请输入以 http:// 或 https:// 开头的地址', trigger: 'blur' }], miniapp_appid: [{ pattern: /^wx[a-zA-Z0-9]{16}$/, message: '请输入正确的小程序 AppID', trigger: 'blur' }] }

const isPlatform = computed(() => Number(config.is_platform) === 1)
const isProviderMode = computed(() => config.connection_mode !== 'self_built')
const authorizedStatuses = ['authorized', 'active', 'connected', 'success']
const isAuthorized = computed(() => authorizedStatuses.includes(String(providerState.status || '').toLowerCase()))
const providerConfigured = computed(() => Number(providerState.configured || 0) === 1 || Boolean(providerConfig.suite_id && providerConfig.admin_miniapp_appid))
const authorizationStatus = computed(() => {
    const status = String(providerState.status || '').toLowerCase()
    if (isAuthorized.value) return { key: 'success', type: 'success', icon: markRaw(CircleCheckFilled), label: '已授权', title: providerState.corp_name || '企业微信已连接', description: '业务通知已经具备发送条件，可以继续绑定员工并发送测试通知。' }
    if (['pending', 'authorizing', 'checking'].includes(status)) return { key: 'pending', type: 'warning', icon: markRaw(Connection), label: '授权中', title: '等待企业微信确认', description: '请完成企业微信管理员授权，然后返回本页检查状态。' }
    if (!providerState.configured) return { key: 'warning', type: 'warning', icon: markRaw(WarningFilled), label: '平台待配置', title: '服务商通道尚未就绪', description: '平台管理员完成服务商通道配置后，当前站点即可一键授权。' }
    if (['cancelled', 'expired', 'failed', 'error'].includes(status)) return { key: 'danger', type: 'danger', icon: markRaw(WarningFilled), label: '需要修复', title: '企业微信授权已失效', description: '重新授权即可恢复通知，不需要填写 CorpID 或 Secret。' }
    return { key: 'default', type: 'info', icon: markRaw(Connection), label: '未授权', title: '连接企业微信', description: '企业管理员确认一次，业务通知即可发送到对应责任人。' }
})
const pageStatus = computed(() => {
    if (isPlatform.value) return providerConfigured.value ? { type: 'success', label: '通道已配置' } : { type: 'warning', label: '等待配置' }
    if (!isProviderMode.value) return config.enabled ? { type: 'success', label: '自建应用已启用' } : { type: 'info', label: '自建应用未启用' }
    return { type: authorizationStatus.value.type, label: authorizationStatus.value.label }
})

const staffLoading = ref(false)
const staffKeyword = ref('')
const staff = ref<any[]>([])
const unboundCount = computed(() => staff.value.filter((item) => !item.wecom_userid).length)
const boundStaff = computed(() => staff.value.filter((item) => item.wecom_userid && Number(item.status) === 1))
const boundCount = computed(() => boundStaff.value.length)
const filteredStaff = computed(() => { const keyword = staffKeyword.value.trim().toLowerCase(); if (!keyword) return staff.value; return staff.value.filter((item) => [item.name, item.username, item.mobile, item.wecom_userid].some((value) => String(value || '').toLowerCase().includes(keyword))) })
const testReceiverUid = ref<number | undefined>()
const testingMessage = ref(false)
const messageLoading = ref(false)
const messages = ref<any[]>([])
const messageTotal = ref(0)
const messageQuery = reactive({ status: '', keyword: '', page: 1, limit: 15 })

function applyConfig(data: any) {
    const payload = data && typeof data === 'object' ? data : {}
    const provider = payload.provider && typeof payload.provider === 'object' ? payload.provider : {}
    Object.assign(config, payload)
    Object.assign(providerState, providerDefaults, provider)
    config.is_platform = Number(payload.is_platform || 0)
    config.connection_mode = payload.connection_mode === 'self_built' ? 'self_built' : 'provider'
    if (!['dual', 'miniapp', 'web'].includes(config.jump_mode)) config.jump_mode = 'miniapp'
    if (config.connection_mode === 'provider') config.jump_mode = 'miniapp'
    if (!['detail', 'list'].includes(config.recycle_task_target)) config.recycle_task_target = 'list'
}
async function loadConfig() { configLoading.value = true; try { const res: any = await getWecomConfig(); applyConfig(res.data || {}) } finally { configLoading.value = false } }
async function loadProviderConfig() {
    providerLoading.value = true
    try {
        const res: any = await getWecomProviderConfig(); const data = res.data || {}; Object.assign(providerConfig, data.config && typeof data.config === 'object' ? data.config : data)
        if (data.provider && typeof data.provider === 'object') Object.assign(providerState, data.provider)
        else Object.assign(providerState, { configured: data.configured ?? providerState.configured, status: data.status ?? providerState.status, suite_ticket_at: data.suite_ticket_at ?? providerState.suite_ticket_at, last_error: data.last_error ?? providerState.last_error })
    } finally { providerLoading.value = false }
}
async function saveProviderConfig() {
    if (providerConfig.enabled && !(await providerFormRef.value?.validate().catch(() => false))) return
    providerSaving.value = true
    try { const res: any = await saveWecomProviderConfig({ ...providerConfig }); const data = res.data || {}; Object.assign(providerConfig, data.config && typeof data.config === 'object' ? data.config : data); if (data.provider && typeof data.provider === 'object') Object.assign(providerState, data.provider); ElMessage.success('服务商通道已保存'); await loadProviderConfig() } finally { providerSaving.value = false }
}
async function testProviderConnection() {
    providerTesting.value = true
    providerTestResult.value = null
    try {
        const res: any = await testWecomProviderConnection()
        const data = res.data || {}
        providerTestResult.value = { ...data, connected: data.connected !== false, message: data.message || 'SuiteTicket 与服务商凭据验证成功', tested_at: Date.now() }
        if (data.suite_ticket_at) providerState.suite_ticket_at = data.suite_ticket_at
        ElMessage.success(providerTestResult.value.message)
    } catch (error: any) {
        providerTestResult.value = { connected: false, message: error?.message || '服务商通道验证失败，请检查 SuiteTicket 与凭据配置', tested_at: Date.now() }
    } finally { providerTesting.value = false }
}
function siteConfigPayload() { const payload = { ...config }; if (payload.connection_mode === 'provider') payload.jump_mode = 'miniapp'; delete payload.is_platform; delete payload.provider; return payload }
async function saveSiteConfig(showMessage = true) {
    if (!isProviderMode.value && config.enabled) { const valid = await selfBuiltFormRef.value?.validate().catch(() => false); if (!valid) return false }
    saving.value = true
    try { const res: any = await saveWecomConfig(siteConfigPayload()); applyConfig({ ...config, ...(res.data || {}), is_platform: 0, provider: providerState }); if (showMessage) ElMessage.success('通知设置已保存'); return true } finally { saving.value = false }
}
async function startAuthorization() {
    config.connection_mode = 'provider'; config.enabled = 1
    if (!(await saveSiteConfig(false))) return
    authorizing.value = true
    try { const res: any = await startWecomAuthorization(); const url = String(res.data?.url || res.data || ''); if (!/^https?:\/\//i.test(url)) throw new Error('企业微信未返回有效的授权地址'); window.location.assign(url) } catch (error: any) { ElMessage.error(error?.message || '发起企业微信授权失败') } finally { authorizing.value = false }
}
async function checkAuthorization() { checkingAuthorization.value = true; try { await checkWecomAuthorization(); await loadConfig(); ElMessage.success(isAuthorized.value ? '企业微信授权有效' : '状态已刷新') } finally { checkingAuthorization.value = false } }
async function testConnection() { if (!(await saveSiteConfig(false))) return; testingConnection.value = true; try { const res: any = await testWecomConnection(); ElMessage.success(`连接成功${res.data?.agent_name ? `：${res.data.agent_name}` : ''}`) } finally { testingConnection.value = false } }
async function loadStaff() { staffLoading.value = true; try { const res: any = await getWecomStaff(); staff.value = (res.data || []).map((item: any) => ({ ...item, saving: false, binding: false })); if (!testReceiverUid.value || !boundStaff.value.some((item) => item.uid === testReceiverUid.value)) testReceiverUid.value = boundStaff.value[0]?.uid } finally { staffLoading.value = false } }
async function bindStaff(row: any) { row.binding = true; try { const res: any = await getWecomStaffBindUrl(row.uid); const url = String(res.data?.url || res.data || ''); if (!/^https?:\/\//i.test(url)) throw new Error('企业微信未返回有效的员工绑定地址'); if (row.is_current_user) { window.location.assign(url); return } await navigator.clipboard.writeText(url); ElMessage.success(`已复制${row.name}的专属绑定链接，请单独发给本人并在企业微信中打开`) } catch (error: any) { ElMessage.error(error?.message || '生成员工绑定链接失败') } finally { row.binding = false } }
async function saveStaff(row: any) { row.saving = true; try { await saveWecomStaff(row.uid, { wecom_userid: row.wecom_userid, status: row.status }); ElMessage.success(`${row.name}的绑定已保存`); await loadStaff() } finally { row.saving = false } }
async function sendTestMessage() {
    if (!testReceiverUid.value) return ElMessage.warning('请选择已绑定员工')
    testingMessage.value = true
    try { const res: any = await testWecomMessage({ receiver_uid: testReceiverUid.value, target_key: 'hsx_recycle.task.list' }); const data = res.data || {}; if (data.status === 'failed' || data.success === false) throw new Error(data.error_message || data.message || '测试通知发送失败'); testSucceeded.value = true; ElMessage.success('测试通知已发送，请到企业微信中点击验证'); if (activeTab.value === 'messages') await loadMessages() } finally { testingMessage.value = false }
}
async function loadMessages() { messageLoading.value = true; try { const res: any = await getWecomMessages(messageQuery); const data = res.data || {}; messages.value = data.data || []; messageTotal.value = data.total || 0 } finally { messageLoading.value = false } }
async function retryMessage(row: any) { await retryWecomMessage(row.id); ElMessage.success('已重新发送'); await loadMessages() }
function onTabChange(name: string) { if (name === 'staff' && !staff.value.length) void loadStaff(); if (name === 'messages') void loadMessages() }
function statusLabel(status: string) { return ({ pending: '待发送', success: '成功', failed: '失败', skipped: '已跳过' } as Record<string, string>)[status] || status }
function statusType(status: string) { return (({ success: 'success', failed: 'danger', skipped: 'warning', pending: 'info' } as Record<string, string>)[status] || 'info') as any }
function maskValue(value: any) { const text = String(value || ''); if (!text) return '—'; if (text === SECRET_MASK || text.length <= 8) return text; return `${text.slice(0, 4)}****${text.slice(-4)}` }
function formatTime(value: any) { if (!value) return '—'; if (typeof value === 'string' && !/^\d+$/.test(value)) return value; const timestamp = Number(value); if (!timestamp) return '—'; return new Date(timestamp < 1000000000000 ? timestamp * 1000 : timestamp).toLocaleString('zh-CN', { hour12: false }) }
async function copyText(value: any) { const text = String(value || ''); if (!text) return ElMessage.warning('请先保存配置生成回调地址'); try { await navigator.clipboard.writeText(text); ElMessage.success('地址已复制') } catch { ElMessage.warning('复制失败，请手工选择地址复制') } }
onMounted(async () => { await loadConfig(); if (isPlatform.value) await loadProviderConfig(); else await loadStaff() })
</script>

<style lang="scss" scoped>
.wecom-page { min-height: 520px; color: var(--el-text-color-primary); }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--el-border-color-lighter); }
.page-eyebrow { margin-bottom: 5px; color: var(--el-color-primary); font-size: 12px; font-weight: 600; letter-spacing: 1px; }
.page-subtitle { margin-top: 7px; color: var(--el-text-color-secondary); font-size: 14px; }
.channel-summary, .authorization-card { display: flex; gap: 18px; margin: 22px 0 18px; padding: 22px; border: 1px solid #dce7ff; border-radius: 12px; background: linear-gradient(135deg, #f5f8ff 0%, #fff 72%); }
.channel-mark, .authorization-icon { display: flex; align-items: center; justify-content: center; flex: 0 0 52px; width: 52px; height: 52px; border-radius: 12px; background: var(--el-color-primary); color: #fff; font-size: 27px; }
.channel-main, .authorization-content { min-width: 0; flex: 1; }
.channel-title-row, .authorization-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.channel-title-row h2, .authorization-title-row h2 { margin: 0; font-size: 19px; line-height: 1.4; }
.channel-main > p, .authorization-title-row p { margin-top: 6px; color: var(--el-text-color-secondary); font-size: 14px; }
.summary-meta { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-top: 15px; color: var(--el-text-color-regular); font-size: 13px; }
.provider-form { max-width: 920px; }
.form-section, .notification-settings, .detail-panel { margin-top: 18px; padding: 22px 22px 8px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; background: #fff; }
.section-heading { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 22px; padding-bottom: 15px; border-bottom: 1px solid var(--el-border-color-lighter); }.section-heading.compact { margin-bottom: 18px; }
.section-heading h3 { margin: 0; font-size: 16px; }.section-heading p { margin-top: 5px; color: var(--el-text-color-secondary); font-size: 13px; }
.provider-form :deep(.el-input), .self-built-form :deep(.el-input), .number-input { width: 100%; }.field-help { width: 100%; margin-top: 6px; color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.5; }
.form-actions { display: flex; flex-wrap: wrap; gap: 10px; padding: 22px 0 8px 165px; }.provider-test-result { margin: 12px 0 8px 165px; }.provider-test-meta { display: flex; flex-wrap: wrap; gap: 6px 22px; margin-top: 7px; font-size: 12px; line-height: 1.6; }.site-tabs { margin-top: 14px; }
.mode-picker { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin: 16px 0 18px; padding: 18px 20px; border-radius: 10px; background: var(--el-fill-color-lighter); }
.mode-picker strong { display: block; margin-bottom: 5px; font-size: 15px; }.mode-picker span { color: var(--el-text-color-secondary); font-size: 13px; }
.authorization-card { margin-top: 0; }.authorization-card.is-success { border-color: #b9e6cc; background: linear-gradient(135deg, #f0fbf5 0%, #fff 72%); }.authorization-card.is-success .authorization-icon { background: var(--el-color-success); }
.authorization-card.is-danger { border-color: #f6caca; background: linear-gradient(135deg, #fff5f5 0%, #fff 72%); }.authorization-card.is-danger .authorization-icon { background: var(--el-color-danger); }
.authorization-card.is-warning, .authorization-card.is-pending { border-color: #f3d8aa; background: linear-gradient(135deg, #fff9ee 0%, #fff 72%); }.authorization-card.is-warning .authorization-icon, .authorization-card.is-pending .authorization-icon { background: var(--el-color-warning); }
.authorization-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
.onboarding-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 16px 0 18px; }.onboarding-grid article { display: flex; align-items: center; gap: 12px; min-height: 74px; padding: 14px 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; background: #fff; }
.onboarding-grid article > span { display: flex; align-items: center; justify-content: center; flex: 0 0 28px; width: 28px; height: 28px; border-radius: 50%; background: var(--el-fill-color); color: var(--el-text-color-secondary); font-weight: 600; }.onboarding-grid article.done { border-color: #b9e6cc; background: #f5fcf8; }.onboarding-grid article.done > span { background: var(--el-color-success); color: #fff; }
.onboarding-grid strong { display: block; font-size: 14px; }.onboarding-grid p { margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; }
.detail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; padding-bottom: 14px; }.detail-grid > div { min-width: 0; padding: 13px 15px; border-radius: 8px; background: var(--el-fill-color-lighter); }.detail-grid .wide { grid-column: span 2; }
.detail-grid span { display: block; margin-bottom: 5px; color: var(--el-text-color-secondary); font-size: 12px; }.detail-grid strong { display: block; overflow: hidden; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }.detail-grid em { margin-left: 8px; color: var(--el-text-color-secondary); font-size: 12px; font-style: normal; font-weight: 400; }
.self-built-form { max-width: 780px; padding: 6px 0; }.notification-settings { max-width: 920px; }.settings-form { max-width: 760px; }
.test-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 14px; padding: 16px 18px; border: 1px solid #dce7ff; border-radius: 10px; background: #f7f9ff; }.test-copy { display: flex; align-items: center; gap: 12px; }.test-copy > .el-icon { color: var(--el-color-primary); font-size: 24px; }.test-copy strong, .test-copy span { display: block; }.test-copy span { margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; }.test-actions { display: flex; gap: 10px; }.test-actions .el-select { width: 210px; }
.toolbar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; padding: 13px 15px; border-radius: 8px; background: var(--el-fill-color-lighter); }.toolbar-row .el-input { width: 320px; }.toolbar-row .el-select { width: 150px; }
.staff-name { color: var(--el-text-color-primary); font-weight: 500; }.secondary-text { color: var(--el-text-color-secondary); font-size: 13px; }.error-text { color: var(--el-color-danger); font-size: 13px; }.target-tags { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }.pagination-row { display: flex; justify-content: flex-end; margin-top: 16px; }.tab-badge { margin-left: 8px; }
@media (max-width: 900px) { .mode-picker, .test-toolbar { align-items: stretch; flex-direction: column; }.onboarding-grid, .detail-grid { grid-template-columns: 1fr; }.detail-grid .wide { grid-column: auto; }.test-actions { width: 100%; }.test-actions .el-select { width: 100%; } }
@media (max-width: 680px) { .page-header, .channel-title-row, .authorization-title-row { flex-direction: column; }.channel-summary, .authorization-card { padding: 17px; }.channel-mark, .authorization-icon { flex-basis: 44px; width: 44px; height: 44px; }.mode-picker :deep(.el-radio-group) { display: flex; width: 100%; }.mode-picker :deep(.el-radio-button) { flex: 1; }.toolbar-row { align-items: stretch; flex-wrap: wrap; }.toolbar-row .el-input { width: 100%; }.form-actions, .provider-test-result { margin-left: 0; padding-left: 0; } }
</style>
