<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="wecom-page" v-loading="configLoading">
                <div class="page-header">
                    <div>
                        <div class="page-eyebrow">{{ isPlatform ? '平台通道' : '消息协同' }}</div>
                        <h1 class="text-page-title">{{ isPlatform ? '企业微信服务商接入' : '企业微信通知' }}</h1>
                        <p class="page-subtitle">{{ isPlatform ? '同域名下的客户共用一个平台通道，各站点分别授权自己的企业。' : '通知的是本站员工：先授权企业、再绑定员工，最后验证收信与业务跳转。' }}</p>
                    </div>
                    <el-tag :type="pageStatus.type" effect="plain" size="large">{{ pageStatus.label }}</el-tag>
                </div>

                <template v-if="!configLoading">
                    <template v-if="isPlatform">
                        <ConnectionGuide :platform="true" :provider="true" />
                        <section class="channel-summary">
                            <div class="channel-mark"><el-icon><Connection /></el-icon></div>
                            <div class="channel-main">
                                <div class="channel-title-row"><h2>{{ providerConfig.admin_miniapp_name || '后台管理小程序' }}</h2><el-tag :type="pageStatus.type" effect="light">{{ pageStatus.label }}</el-tag></div>
                                <p>{{ providerPreparing ? '回调准备已保存，可去企业微信创建应用；尚未启用通道，也不代表官方回调验证已通过。' : !canPrepareProvider ? '此通道已有服务商应用；修改后请正式保存，再核对凭据、客户授权与员工实测结果。' : '首次创建应用还没有 SuiteID / SuiteSecret？先完成第一步，创建后再回来补齐第二步。' }}</p>
                                <div class="summary-meta"><span>SuiteID：{{ maskValue(providerConfig.suite_id) }}</span><span>小程序：{{ maskValue(providerConfig.admin_miniapp_appid) }}</span><span>最近 Ticket：{{ formatTime(providerState.suite_ticket_at) }}</span></div>
                            </div>
                        </section>
                        <el-alert v-if="providerState.last_error" class="mb-[18px]" type="error" :closable="false" show-icon :title="providerState.last_error" />
                        <el-alert v-if="providerLoadError" class="mb-[18px]" type="error" :closable="false" show-icon :title="providerLoadError"><el-button link type="primary" :loading="providerLoading" @click="loadProviderConfig">重试读取已保存配置</el-button></el-alert>

                        <el-form ref="providerFormRef" :model="providerConfig" :rules="providerRules" :disabled="providerLoading || providerSaving" label-width="165px" class="provider-form">
                            <div class="form-section">
                                <div class="section-heading"><div><h3>第一步：先准备回调，再创建应用</h3><p>这一步不需要 SuiteID、SuiteSecret 或小程序 AppID。参数仅由平台管理员维护。</p></div></div>
                                <el-form-item label="通道标识" prop="channel_code"><el-input v-model.trim="providerConfig.channel_code" placeholder="例如 saas_a" clearable /><div class="field-help">用于区分不同服务器和后台管理小程序，保存后不建议随意修改。</div></el-form-item>
                                <el-form-item label="服务商企业 ID" prop="provider_corp_id"><el-input v-model.trim="providerConfig.provider_corp_id" placeholder="企业微信服务商 CorpID" clearable /></el-form-item>
                                <el-form-item label="回调 Token" prop="callback_token"><el-input v-model.trim="providerConfig.callback_token" type="password" show-password autocomplete="new-password" placeholder="填写与企业微信创建页一致的 Token" /><div class="field-help">在企业微信创建页与本页填入同一组 Token / EncodingAESKey，先保存本页，再提交官方校验。已保存密钥显示为掩码，请勿把 ****** 填入企业微信。</div></el-form-item>
                                <el-form-item label="EncodingAESKey" prop="encoding_aes_key"><el-input v-model.trim="providerConfig.encoding_aes_key" type="password" show-password autocomplete="new-password" placeholder="企业微信创建页生成的 43 位 EncodingAESKey" /></el-form-item>
                                <el-form-item label="网页管理端地址" prop="web_base_url"><el-input v-model.trim="providerConfig.web_base_url" placeholder="https://example.com" clearable /><div class="field-help">填写本套 SaaS 对外可访问的 HTTPS 根地址，用于生成官方回调和管理入口。</div></el-form-item>
                                <el-form-item v-if="canPrepareProvider"><el-button type="primary" :loading="providerSaving" :disabled="!providerLoaded || providerLoading || providerTesting" @click="saveProviderPreparation">{{ providerPreparing ? '更新回调准备' : '保存回调准备' }}</el-button><div class="field-help">只保存回调基础参数，通道保持未启用，不校验或保存下方 Suite 与小程序凭据。</div></el-form-item>
                                <el-form-item v-else><div class="field-help">此通道已有 Suite 凭据或已启用，不能退回“回调准备”。修改以上参数后，请用第二步的保存按钮提交。</div></el-form-item>
                            </div>
                            <div class="form-section callback-section">
                                <div class="section-heading"><div><h3>复制到企业微信创建页</h3><p>以下内容来自服务器已保存配置。字段用途不同，请按名称逐项填写。</p></div></div>
                                <el-alert v-if="!providerCallbackReady" type="warning" :closable="false" show-icon class="mb-[18px]" title="尚未保存可用的回调准备，请先完成第一步。仅填写表单不会让服务器通过回调校验。" />
                                <el-alert v-else-if="providerBaseChanged" type="warning" :closable="false" show-icon class="mb-[18px]" title="回调基础参数有未保存修改。下方仍是上次保存的地址，请保存成功后再复制和校验。" />
                                <el-alert v-else type="info" :closable="false" show-icon class="mb-[18px]" title="本系统已保存回调准备；是否通过官方校验，请以企业微信创建页的结果为准。" />
                                <el-form-item v-for="field in providerCallbackFields" :key="field.key" :label="field.label"><el-input :model-value="savedProviderConfig[field.key] || ''" readonly placeholder="保存回调准备后由系统生成"><template #append><el-button :icon="CopyDocument" :aria-label="`复制${field.label}`" :disabled="!providerLoaded || !providerCallbackReady || providerBaseChanged || !savedProviderConfig[field.key]" @click="copyText(savedProviderConfig[field.key])" /></template></el-input><div class="field-help">{{ field.help }}</div></el-form-item>
                            </div>
                            <div class="form-section">
                                <div class="section-heading"><div><h3>第二步：应用创建后，补齐凭据并启用</h3><p>从刚创建的同一个应用获取 SuiteID / SuiteSecret，再关联本套 SaaS 的后台管理小程序。</p></div></div>
                                <el-form-item v-if="!canPrepareProvider" label="启用服务商通道"><el-switch v-model="providerConfig.enabled" :active-value="1" :inactive-value="0" /></el-form-item>
                                <el-form-item label="SuiteID" prop="suite_id"><el-input v-model.trim="providerConfig.suite_id" placeholder="应用创建成功后取得的 SuiteID" clearable /></el-form-item>
                                <el-form-item label="SuiteSecret" prop="suite_secret"><el-input v-model.trim="providerConfig.suite_secret" type="password" show-password autocomplete="new-password" placeholder="应用创建成功后取得；已配置时显示掩码" /></el-form-item>
                                <el-form-item label="管理小程序 AppID" prop="admin_miniapp_appid"><el-input v-model.trim="providerConfig.admin_miniapp_appid" placeholder="例如 wxe00f1f93e4d87ac7" clearable /></el-form-item>
                                <el-form-item label="管理小程序名称" prop="admin_miniapp_name"><el-input v-model.trim="providerConfig.admin_miniapp_name" placeholder="客户在企业微信中看到的名称" clearable /></el-form-item>
                            </div>
                            <div class="form-actions"><el-button type="primary" :loading="providerSaving" :disabled="!providerLoaded || providerLoading || providerTesting" @click="saveProviderConfig">{{ canPrepareProvider ? '保存并启用服务商通道' : '保存服务商通道' }}</el-button><el-button type="success" plain :loading="providerTesting" :disabled="!providerLoaded || !providerConfigured || providerSaving || providerLoading" @click="testProviderConnection">验证服务商通道</el-button><el-button :icon="Refresh" :loading="providerLoading" :disabled="providerSaving || providerTesting" @click="loadProviderConfig">刷新状态</el-button><div class="field-help">回调准备不等于正式启用。补齐凭据、保存启用并收到 SuiteTicket 后才能验证；验证使用已保存配置，通过后仍需客户授权与员工实测。</div></div>
                            <el-alert v-if="providerTestResult" class="provider-test-result" :type="providerTestResult.connected ? 'success' : 'error'" :closable="false" show-icon>
                                <template #title>{{ providerTestResult.message }}</template>
                                <div class="provider-test-meta"><span>通道：{{ providerTestResult.channel_code || providerConfig.channel_code || '—' }}</span><span>SuiteID：{{ maskValue(providerTestResult.suite_id || providerConfig.suite_id) }}</span><span>SuiteTicket：{{ providerTestResult.suite_ticket_at ? `最近接收于 ${formatTime(providerTestResult.suite_ticket_at)}` : '尚未接收' }}</span><span>验证时间：{{ formatTime(providerTestResult.tested_at) }}</span></div>
                            </el-alert>
                        </el-form>
                    </template>

                    <template v-else>
                        <ConnectionGuide :platform="false" :provider="isProviderMode" @navigate="navigateTab" />
                        <el-alert v-if="callbackFeedback" class="mb-[14px]" :type="callbackFeedback.type" :title="callbackFeedback.title" :description="callbackFeedback.description" show-icon @close="callbackFeedback = null" />
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
                                    <div class="onboarding-summary"><span>已绑定并启用通知：{{ boundCount }} 人</span><span>{{ testSucceeded ? '本次测试：接口已受理，仍需员工确认收信与跳转' : '尚未完成本次测试与员工验收' }}</span></div>
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
                                    <div class="section-heading compact"><div><h3>通知规则</h3><p>仅已对接的业务事件会通知所分配的员工。开启经营报告不会自动创建报表任务。</p></div></div>
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
                                <div class="test-toolbar"><div class="test-copy"><el-icon><Bell /></el-icon><div><strong>真实测试通知</strong><span>会立即发给所选员工，点击进入管理端工作台；真实工单跳转需要再分配业务待办验证。</span></div></div><div class="test-actions"><el-select v-model="testReceiverUid" filterable clearable placeholder="选择已绑定员工"><el-option v-for="item in boundStaff" :key="item.uid" :label="item.name" :value="item.uid" /></el-select><el-button type="primary" :loading="testingMessage" :disabled="!testReceiverUid || !config.enabled || (isProviderMode && !isAuthorized)" @click="sendTestMessage">发送测试通知</el-button></div></div>
                                <p class="staff-help">这里是本站管理员账号，不是企业微信的全部通讯录。缺少员工请先到 <router-link to="/site/auth/user">权限管理 → 管理员</router-link> 添加；绑定只决定通知发给谁，不增加业务权限。</p>
                                <div class="toolbar-row"><el-input v-model="staffKeyword" clearable placeholder="搜索员工或企业微信 UserID" :prefix-icon="Search" /><el-button :icon="Refresh" @click="loadStaff">刷新</el-button></div>
                                <el-table :data="filteredStaff" v-loading="staffLoading" row-key="uid">
                                    <el-table-column label="员工" min-width="190"><template #default="{ row }"><div class="staff-name">{{ row.name }}</div><div class="secondary-text">{{ row.username }}<span v-if="row.mobile"> · {{ row.mobile }}</span></div></template></el-table-column>
                                    <el-table-column label="企业微信 UserID" min-width="260"><template #default="{ row }"><el-input v-model.trim="row.wecom_userid" :readonly="isProviderMode" :clearable="!isProviderMode" :placeholder="isProviderMode ? '由员工授权后自动回填' : '填写企业微信 UserID'" /></template></el-table-column>
                                    <el-table-column label="通知" width="90" align="center"><template #default="{ row }"><el-switch v-model="row.status" :active-value="1" :inactive-value="0" /></template></el-table-column>
                                    <el-table-column label="状态" width="105" align="center"><template #default="{ row }"><el-tag :type="row.wecom_userid ? 'success' : 'warning'" effect="plain">{{ row.wecom_userid ? '已绑定' : '待绑定' }}</el-tag></template></el-table-column>
                                    <el-table-column label="操作" width="210" align="right" fixed="right"><template #default="{ row }"><el-button v-if="isProviderMode" type="primary" link :disabled="!isAuthorized" @click="bindStaff(row)">{{ row.wecom_userid ? '重新扫码绑定' : '扫码绑定' }}</el-button><el-button v-if="!isProviderMode || row.wecom_userid" type="primary" link :loading="row.saving" @click="saveStaff(row)">保存</el-button></template></el-table-column>
                                </el-table>
                            </el-tab-pane>

                            <el-tab-pane label="消息日志" name="messages">
                                <p class="staff-help">“发送成功”表示企业微信接口已受理，不代表员工已读或打开任务。重试会重新发送真实通知，请先确认失败原因。</p>
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
            <StaffBindingDialog v-if="!isPlatform" ref="staffBindingRef" :corp-name="providerState.corp_name" @bound="loadStaff" @refresh="loadStaff" />
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, markRaw, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Bell, CircleCheckFilled, Connection, CopyDocument, Refresh, Search, WarningFilled } from '@element-plus/icons-vue'
import { checkWecomAuthorization, getWecomConfig, getWecomMessages, getWecomProviderConfig, getWecomStaff, retryWecomMessage, saveWecomConfig, saveWecomProviderConfig, saveWecomStaff, startWecomAuthorization, testWecomConnection, testWecomMessage, testWecomProviderConnection } from '../../api'
import ConnectionGuide from './components/ConnectionGuide.vue'
import StaffBindingDialog from './components/StaffBindingDialog.vue'
import { canPrepareProviderConfiguration, hasSavedProviderCallbackPreparation, hasSavedProviderConfiguration, readAuthorizationCallback } from '../../utils/binding-session'

const SECRET_MASK = '******'
const route = useRoute()
const router = useRouter()
const staffBindingRef = ref<InstanceType<typeof StaffBindingDialog>>()
const callbackFeedback = ref<{ type: 'success' | 'warning' | 'error'; title: string; description: string } | null>(null)
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
const providerLoaded = ref(false)
const providerLoadError = ref('')
const providerSaving = ref(false)
const providerTesting = ref(false)
const providerTestResult = ref<any>(null)
const savedProviderConfigured = ref(false)
const savedProviderConfig = ref<Record<string, any>>({})
const providerPreparationFields = ['channel_code', 'provider_corp_id', 'callback_token', 'encoding_aes_key', 'web_base_url']
const providerCallbackFields = [
    { key: 'installation_callback_domain', label: '安装完成回调域名', help: '填入官方同名字段：只填域名，不含 https://、路径或端口。' },
    { key: 'data_callback_url', label: '数据回调 URL', help: '填入官方“数据回调 URL”。当前仅支持地址验证与加密数据安全接收确认，不接入聊天、会话存档或业务消息处理。' },
    { key: 'event_callback_url', label: '指令回调 URL', help: '填入官方“指令回调 URL”：接收 SuiteTicket 和授权变更指令。接口字段名为 event_callback_url。' },
    { key: 'application_settings_url', label: '应用设置 URL', help: '填入官方“应用设置 URL”：这是普通 SaaS 站点登录后的企业微信配置入口，需要本站账号登录；不是企业微信管理员免登或单点登录。' },
    { key: 'auth_callback_url', label: '安装授权返回 URL', help: '客户安装授权后的返回地址，不要误填到“安装完成回调域名”“数据回调 URL”或“应用设置 URL”。' }
]
const providerConfig = reactive<any>({ enabled: 0, channel_code: '', provider_corp_id: '', suite_id: '', suite_secret: '', callback_token: '', encoding_aes_key: '', admin_miniapp_appid: '', admin_miniapp_name: '', web_base_url: '', event_callback_url: '', auth_callback_url: '' })
const providerRules: FormRules = {
    channel_code: [{ required: true, message: '请填写通道标识', trigger: 'blur' }, { pattern: /^[a-z][a-z0-9_-]{1,39}$/, message: '使用 2—40 位小写字母、数字、短横线或下划线，且以字母开头', trigger: 'blur' }],
    provider_corp_id: [{ required: true, message: '请填写服务商企业 ID', trigger: 'blur' }],
    suite_id: [{ required: true, message: '请填写 SuiteID', trigger: 'blur' }],
    suite_secret: [{ required: true, message: '请填写 SuiteSecret', trigger: 'blur' }],
    callback_token: [{ required: true, message: '请填写回调 Token', trigger: 'blur' }],
    encoding_aes_key: [{ required: true, message: '请填写 EncodingAESKey', trigger: 'blur' }, { validator: (_rule, value, callback) => {
        const storedMask = value === SECRET_MASK && Number(savedProviderConfig.value.encoding_aes_key_configured) === 1
        callback(storedMask || /^[A-Za-z0-9+/]{43}$/.test(String(value || '')) ? undefined : new Error('EncodingAESKey 应为 43 位字符，请从企业微信创建页复制'))
    }, trigger: 'blur' }],
    admin_miniapp_appid: [{ required: true, message: '请填写管理小程序 AppID', trigger: 'blur' }, { pattern: /^wx[a-zA-Z0-9]{16}$/, message: '请输入正确的小程序 AppID', trigger: 'blur' }],
    admin_miniapp_name: [{ required: true, message: '请填写管理小程序名称', trigger: 'blur' }],
    web_base_url: [{ required: true, message: '请填写网页管理端地址', trigger: 'blur' }, { pattern: /^https?:\/\//i, message: '请输入以 https:// 开头的地址；本地调试可用 http://', trigger: 'blur' }]
}
const selfBuiltFormRef = ref<FormInstance>()
const selfBuiltRules: FormRules = { corp_id: [{ required: true, message: '请输入企业 ID', trigger: 'blur' }], agent_id: [{ required: true, message: '请输入应用 AgentId', trigger: 'change' }], secret: [{ required: true, message: '请输入应用 Secret', trigger: 'blur' }], web_base_url: [{ pattern: /^https?:\/\//i, message: '请输入以 http:// 或 https:// 开头的地址', trigger: 'blur' }], miniapp_appid: [{ pattern: /^wx[a-zA-Z0-9]{16}$/, message: '请输入正确的小程序 AppID', trigger: 'blur' }] }

const isPlatform = computed(() => Number(config.is_platform) === 1)
const isProviderMode = computed(() => config.connection_mode !== 'self_built')
const authorizedStatuses = ['authorized', 'active', 'connected', 'success']
const isAuthorized = computed(() => authorizedStatuses.includes(String(providerState.status || '').toLowerCase()))
const providerConfigured = computed(() => isPlatform.value ? savedProviderConfigured.value : Number(providerState.configured || 0) === 1)
const providerCallbackReady = computed(() => hasSavedProviderCallbackPreparation(savedProviderConfig.value))
const providerPreparing = computed(() => savedProviderConfig.value.status === 'preparing' && providerCallbackReady.value)
const canPrepareProvider = computed(() => canPrepareProviderConfiguration(savedProviderConfig.value))
const providerBaseChanged = computed(() => providerPreparationFields.some(key => String(providerConfig[key] || '') !== String(savedProviderConfig.value[key] || '')))
const providerVerified = computed(() => providerTestResult.value?.connected === true)
const authorizationStatus = computed(() => {
    const status = String(providerState.status || '').toLowerCase()
    if (isAuthorized.value) return { key: 'success', type: 'success', icon: markRaw(CircleCheckFilled), label: '已授权', title: providerState.corp_name || '企业授权已生效', description: '下一步：绑定接收员工、保存通知开关，再由员工验收真实通知与任务跳转。' }
    if (['pending', 'authorizing', 'checking'].includes(status)) return { key: 'pending', type: 'warning', icon: markRaw(Connection), label: '授权中', title: '等待企业微信确认', description: '请完成企业微信管理员授权，然后返回本页检查状态。' }
    if (!providerState.configured) return { key: 'warning', type: 'warning', icon: markRaw(WarningFilled), label: '平台待配置', title: '服务商通道尚未就绪', description: '平台管理员完成服务商通道配置后，当前站点即可一键授权。' }
    if (['cancelled', 'expired', 'failed', 'error'].includes(status)) return { key: 'danger', type: 'danger', icon: markRaw(WarningFilled), label: '需要修复', title: '企业微信授权需要检查', description: '请甲方管理员重新授权，随后核对接收员工和通知测试；不需要填写企业 Secret。' }
    return { key: 'default', type: 'info', icon: markRaw(Connection), label: '未授权', title: '连接甲方自己的企业微信', description: '由甲方企业管理员完成授权，再绑定本站员工。不需要更换域名，也不用加入服务商的企业。' }
})
const pageStatus = computed(() => {
    if (isPlatform.value) {
        if (providerVerified.value) return { type: 'success', label: '本次凭据验证通过' }
        if (providerConfigured.value) return { type: 'warning', label: '通道已启用 · 待验证' }
        if (providerPreparing.value) return { type: 'warning', label: '回调准备已保存 · 未启用' }
        if (Number(savedProviderConfig.value.id) > 0 && !canPrepareProvider.value) return { type: 'info', label: '通道已保存 · 未启用' }
        return { type: 'warning', label: '等待回调准备' }
    }
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
function applyProviderConfig(data: any) {
    const saved = data.config && typeof data.config === 'object' ? data.config : data
    Object.assign(providerConfig, saved)
    savedProviderConfig.value = { ...saved }
    savedProviderConfigured.value = hasSavedProviderConfiguration(saved)
    providerLoaded.value = true
    providerLoadError.value = ''
    providerTestResult.value = null
    if (data.provider && typeof data.provider === 'object') Object.assign(providerState, data.provider)
    else Object.assign(providerState, { configured: data.configured ?? providerState.configured, status: data.status ?? providerState.status, suite_ticket_at: data.suite_ticket_at ?? providerState.suite_ticket_at, last_error: data.last_error ?? providerState.last_error })
}
async function loadProviderConfig() {
    providerLoading.value = true
    providerLoaded.value = false
    providerLoadError.value = ''
    try {
        const res: any = await getWecomProviderConfig(); applyProviderConfig(res.data || {})
        return true
    } catch {
        providerLoadError.value = '无法读取已保存的服务商配置。请重试读取成功后再保存，避免覆盖现有通道。'
        return false
    } finally { providerLoading.value = false }
}
async function saveProviderPreparation() {
    if (!providerLoaded.value || providerLoading.value || providerSaving.value || providerTesting.value) return ElMessage.warning('请先读取已保存配置，并等待当前操作完成')
    if (!canPrepareProvider.value) return ElMessage.warning('已有 Suite 凭据或启用通道不能退回回调准备，请使用第二步保存')
    if (!(await providerFormRef.value?.validateField(providerPreparationFields).catch(() => false))) return
    providerSaving.value = true
    providerTestResult.value = null
    try {
        const payload = Object.fromEntries(providerPreparationFields.map(key => [key, providerConfig[key]]))
        const suiteDraft = Object.fromEntries(['suite_id', 'suite_secret', 'admin_miniapp_appid', 'admin_miniapp_name'].map(key => [key, providerConfig[key]]))
        const res: any = await saveWecomProviderConfig({ ...payload, id: savedProviderConfig.value.id || 0, enabled: 0, prepare_only: 1 })
        applyProviderConfig(res.data || {})
        Object.assign(providerConfig, suiteDraft)
        if (!providerPreparing.value) return ElMessage.warning('接口未确认回调准备已就绪，请刷新检查服务端版本和保存结果')
        ElMessage.success('回调准备已保存，请复制对应字段到企业微信创建页完成官方校验')
    } finally { providerSaving.value = false }
}
async function saveProviderConfig() {
    if (!providerLoaded.value || providerLoading.value || providerSaving.value || providerTesting.value) return ElMessage.warning('请先读取已保存配置，并等待当前操作完成')
    const enabled = canPrepareProvider.value ? 1 : Number(providerConfig.enabled)
    if (enabled && !(await providerFormRef.value?.validate().catch(() => false))) return
    providerSaving.value = true
    providerTestResult.value = null
    try {
        const res: any = await saveWecomProviderConfig({ ...providerConfig, enabled, prepare_only: 0 })
        applyProviderConfig(res.data || {})
        ElMessage.success(enabled ? '服务商通道已保存并启用，请等待 SuiteTicket 后验证' : '服务商通道已保存为停用')
        if (!(await loadProviderConfig())) providerLoadError.value = '配置保存已成功，但重新读取失败。请重试读取已保存配置后继续操作。'
    } finally { providerSaving.value = false }
}
async function testProviderConnection() {
    if (!providerLoaded.value || !providerConfigured.value || providerLoading.value || providerSaving.value || providerTesting.value) return ElMessage.warning('请先读取并保存完整的启用配置后再验证')
    providerTesting.value = true
    providerTestResult.value = null
    try {
        const res: any = await testWecomProviderConnection()
        const data = res.data || {}
        if (data.connected !== true) throw new Error(data.message || '接口没有返回验证通过状态，请联系平台技术人员检查。')
        providerTestResult.value = { ...data, connected: true, message: data.message || 'SuiteTicket 与服务商凭据验证成功', tested_at: Date.now() }
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
function bindStaff(row: any) { staffBindingRef.value?.open(row) }
async function saveStaff(row: any) { row.saving = true; try { await saveWecomStaff(row.uid, { wecom_userid: row.wecom_userid, status: row.status }); ElMessage.success(`${row.name}的绑定已保存`); await loadStaff() } finally { row.saving = false } }
async function sendTestMessage() {
    if (!testReceiverUid.value) return ElMessage.warning('请选择已绑定员工')
    testingMessage.value = true
    try { const res: any = await testWecomMessage({ receiver_uid: testReceiverUid.value }); const data = res.data || {}; if (data.status !== 'success') throw new Error(data.error_message || data.message || '测试通知尚未发送成功，请查看消息日志'); testSucceeded.value = true; ElMessage.success('企业微信接口已受理，请员工确认收到通知并打开管理端工作台'); if (activeTab.value === 'messages') await loadMessages() } catch (error: any) { testSucceeded.value = false; ElMessage.error(error?.msg || error?.message || '测试通知失败，请查看消息日志') } finally { testingMessage.value = false }
}
async function loadMessages() { messageLoading.value = true; try { const res: any = await getWecomMessages(messageQuery); const data = res.data || {}; messages.value = data.data || []; messageTotal.value = data.total || 0 } finally { messageLoading.value = false } }
async function retryMessage(row: any) { await retryWecomMessage(row.id); ElMessage.success('已重新发送'); await loadMessages() }
function onTabChange(name: string) { if (name === 'staff' && !staff.value.length) void loadStaff(); if (name === 'messages') void loadMessages() }
function navigateTab(name: string) { activeTab.value = name }
function statusLabel(status: string) { return ({ pending: '待发送', success: '成功', failed: '失败', skipped: '已跳过' } as Record<string, string>)[status] || status }
function statusType(status: string) { return (({ success: 'success', failed: 'danger', skipped: 'warning', pending: 'info' } as Record<string, string>)[status] || 'info') as any }
function maskValue(value: any) { const text = String(value || ''); if (!text) return '—'; if (text === SECRET_MASK || text.length <= 8) return text; return `${text.slice(0, 4)}****${text.slice(-4)}` }
function formatTime(value: any) { if (!value) return '—'; if (typeof value === 'string' && !/^\d+$/.test(value)) return value; const timestamp = Number(value); if (!timestamp) return '—'; return new Date(timestamp < 1000000000000 ? timestamp * 1000 : timestamp).toLocaleString('zh-CN', { hour12: false }) }
async function copyText(value: any) { const text = String(value || ''); if (!text) return ElMessage.warning('请先保存配置生成回调地址'); try { await navigator.clipboard.writeText(text); ElMessage.success('地址已复制') } catch { ElMessage.warning('复制失败，请手工选择地址复制') } }
onMounted(async () => {
    const callback = readAuthorizationCallback(route.query)
    activeTab.value = callback.tab
    if (callback.hasResult || route.query.tab) await router.replace({ query: callback.cleanQuery as any })
    try {
        await loadConfig()
        if (isPlatform.value) await loadProviderConfig()
        else {
            await loadStaff()
            if (activeTab.value === 'messages') await loadMessages()
            if (callback.hasResult) callbackFeedback.value = callback.successHint && isAuthorized.value
                ? { type: 'success', title: '已读取到有效企业授权', description: '请到接收员工完成本人扫码与核对，再测试通知；企业授权不代表员工已自动绑定。' }
                : { type: 'warning', title: '授权结果需要核对', description: callback.message || '请检查当前授权企业和状态；如未生效，请甲方管理员重新发起授权，仍失败联系平台人员。' }
        }
    } catch (error: any) {
        if (callback.hasResult) callbackFeedback.value = { type: 'error', title: '已返回后台，但无法核对授权状态', description: error?.msg || error?.message || '请刷新页面检查，不能仅凭返回链接判断授权成功。' }
    }
})
</script>

<style lang="scss" scoped>
.wecom-page { min-height: 520px; color: var(--el-text-color-primary); }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--el-border-color-lighter); }
.page-eyebrow { margin-bottom: 5px; color: var(--el-color-primary); font-size: 12px; font-weight: 600; letter-spacing: 1px; }
.page-subtitle { margin-top: 7px; color: var(--el-text-color-secondary); font-size: 14px; }
.onboarding-summary { display: flex; flex-wrap: wrap; gap: 8px 20px; margin: 12px 0; font-size: 12px; color: var(--el-text-color-secondary); }.staff-help { margin: 12px 0; font-size: 12px; line-height: 1.7; color: var(--el-text-color-secondary); }.staff-help a { color: var(--el-color-primary); }
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
