<template>
    <div class="main-page">
        <el-card shadow="never" class="!border-none">
            <template #header>
                <div class="flex items-start justify-between gap-[20px]">
                    <div>
                        <div class="text-[18px] font-semibold text-[#1d2939]">客户群台账</div>
                        <div class="mt-[6px] text-[13px] text-[#667085]">先登记系统业务编号，再按需选择普通微信群或企业微信建群；群编号用于关联客户资料与审核工单。</div>
                    </div>
                    <div class="flex shrink-0 items-center gap-[10px]">
                        <el-button :icon="DocumentAdd" @click="openManualReserve">登记客户群</el-button>
                        <el-button type="primary" :icon="ChatDotRound" @click="openWecomReserve">企业微信一键建群</el-button>
                    </div>
                </div>
            </template>

            <el-alert v-if="wecom.loaded && !wecom.ready" type="warning" :closable="false" show-icon class="mb-[16px]">
                <template #title>企业微信自动建群尚未就绪：{{ wecom.reason }}</template>
                <div class="mt-[4px] text-[12px]">仍可使用“登记客户群”生成系统业务编号，再在微信中手工建群；企微配置不会阻塞资料办理。</div>
            </el-alert>
            <el-alert v-else-if="wecom.loaded && !wecom.current_bound" type="warning" :closable="false" show-icon class="mb-[16px]">
                <template #title>当前系统账号尚未绑定企业微信身份</template>
                <div class="mt-[5px] flex flex-wrap items-center gap-[10px] text-[12px]">
                    <span>请在企业微信工作台内完成一次静默绑定，之后系统会自动使用你自己的客户联系人和负责数据。</span>
                    <el-button type="primary" link :loading="bindingIdentity" @click="startSelfBind(false)">一键绑定本人</el-button>
                </div>
            </el-alert>
            <el-alert v-else-if="wecom.loaded && wecom.current_bound" type="success" :closable="false" show-icon class="mb-[16px]">
                <template #title>当前账号已绑定企业微信，可从你自己的客户联系人中选择客户并自动建群</template>
            </el-alert>

            <div class="mb-[18px] flex flex-wrap items-center gap-[10px] rounded-[10px] bg-[#f8fafc] p-[14px]">
                <el-input v-model="query.keyword" clearable class="!w-[300px]" placeholder="群编号、门店、客户或手机号" @keyup.enter="loadPage(1)" />
                <el-select v-model="query.project_id" clearable class="!w-[190px]" placeholder="全部项目"><el-option v-for="item in projects" :key="item.id" :label="item.title" :value="item.id" /></el-select>
                <el-select v-model="query.status" clearable class="!w-[210px]" placeholder="全部状态"><el-option label="编号已登记 / 待企微建群" value="reserved" /><el-option label="企微未完成（业务可继续）" value="create_failed" /><el-option label="已建群" value="created" /><el-option label="办理中" value="active" /><el-option label="待退款" value="refund_pending" /><el-option label="已退款关闭" value="refunded" /><el-option label="已完成" value="completed" /><el-option label="已放弃" value="abandoned" /><el-option label="已解散" value="dissolved" /></el-select>
                <el-button type="primary" @click="loadPage(1)">查询</el-button><el-button @click="reset">重置</el-button>
            </div>

            <el-table v-loading="loading" :data="rows" size="large">
                <el-table-column label="系统业务编号" width="145"><template #default="{ row }"><div class="text-[16px] font-semibold text-[#155eef]">{{ row.group_no }}</div><div class="mt-[4px] text-[11px] text-[#98a2b3]">{{ row.group_no_full }}</div></template></el-table-column>
                <el-table-column label="项目 / 门店" min-width="220"><template #default="{ row }"><div class="font-medium">{{ row.project_title || '-' }}</div><div class="mt-[4px] text-[13px] text-[#667085]">{{ row.store_name || '未填写门店' }}</div></template></el-table-column>
                <el-table-column label="客户" min-width="180"><template #default="{ row }"><div>{{ row.member_name || '-' }}</div><div class="mt-[4px] font-mono text-[13px] text-[#667085]">{{ row.member_mobile || '-' }}</div></template></el-table-column>
                <el-table-column label="企微关联（可选）" min-width="210"><template #default="{ row }"><div v-if="row.wecom_chat_id"><el-tag type="success" effect="plain">已绑定企微群</el-tag><div class="mt-[5px] truncate font-mono text-[11px] text-[#667085]">{{ row.wecom_chat_id }}</div></div><div v-else><span class="text-[#667085]">{{ row.create_mode === 'manual' ? '普通微信群 · 无需 Chat ID' : '暂未绑定企微群' }}</span><div v-if="row.error_message" class="mt-[4px] line-clamp-2 text-[12px] text-[#d92d20]">{{ row.error_message }}</div></div></template></el-table-column>
                <el-table-column label="业务状态" width="120"><template #default="{ row }"><el-tag :type="statusType(row.status)">{{ groupStatusName(row) }}</el-tag></template></el-table-column>
                <el-table-column label="操作" fixed="right" width="350"><template #default="{ row }">
                    <el-button v-if="!['abandoned','dissolved','completed','refund_pending','refunded'].includes(row.status)" link type="primary" :loading="creatingId === row.id" @click="startWecomGroup(row)">{{ row.wecom_chat_id ? '打开群聊' : '一键建群' }}</el-button>
                    <el-button link type="primary" @click="copyText(row.group_no, '群编号已复制')">复制编号</el-button>
                    <el-button v-permission="'hsx_project_center_group_correct_no'" link type="primary" @click="correctGroupNoRef?.open(row)">更正编号</el-button>
                    <el-button v-if="['completed','abandoned'].includes(row.status)" v-permission="'hsx_project_center_refund_info'" link type="danger" @click="refundDialogRef?.open(row)">退款处理</el-button>
                    <el-button v-if="['refund_pending','refunded'].includes(row.status)" v-permission="'hsx_project_center_refund_info'" link :type="row.status === 'refunded' ? 'success' : 'danger'" @click="refundDialogRef?.open(row)">{{ row.status === 'refunded' ? '查看退款' : '处理退款' }}</el-button>
                    <el-dropdown v-if="!['completed','abandoned','dissolved','refund_pending','refunded'].includes(row.status)" trigger="click" @command="(command) => handleCommand(row, command)"><el-button link>更多<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button><template #dropdown><el-dropdown-menu><el-dropdown-item command="manual">手工绑定 Chat ID（兜底）</el-dropdown-item><el-dropdown-item v-permission="'hsx_project_center_refund_info'" command="refund" divided>客户已付款 · 终止并退款</el-dropdown-item><el-dropdown-item command="abandoned">客户未付款 · 放弃办理</el-dropdown-item><el-dropdown-item command="dissolved">群已解散</el-dropdown-item></el-dropdown-menu></template></el-dropdown>
                </template></el-table-column>
            </el-table>
            <div class="mt-[18px] flex justify-end"><el-pagination v-model:current-page="page.page" v-model:page-size="page.limit" layout="total, prev, pager, next" :total="page.total" @current-change="loadPage" /></div>
        </el-card>

        <el-dialog v-model="reserveDialog.visible" :title="reserveDialog.mode === 'wecom' ? '企业微信一键建群' : '登记客户群'" width="580px" destroy-on-close>
            <el-alert v-if="reserveDialog.mode === 'wecom'" type="info" :closable="false" show-icon class="mb-[16px]" title="系统将生成业务编号并预填群名、员工和客户；你只需在企业微信中确认一次。" />
            <el-alert v-else type="info" :closable="false" show-icon class="mb-[16px]" title="先建立系统台账，再按建议群名手工创建微信群。系统业务编号不是微信 Chat ID。" />
            <el-form label-position="top">
                <el-form-item label="所属项目" required><el-select v-model="reserveForm.project_id" class="!w-full" placeholder="选择项目"><el-option v-for="item in enabledProjects" :key="item.id" :label="item.title" :value="item.id" /></el-select></el-form-item>
                <el-form-item v-if="reserveDialog.mode === 'manual'" label="已有群编号（选填）"><el-input v-model="reserveForm.group_no" maxlength="40" clearable placeholder="例如 0819-1；留空由系统自动生成" @blur="normalizeExistingGroupNo" /><div class="mt-[5px] text-[12px] leading-[18px] text-[#98a2b3]">仅用于补录已经建立的客户群；新建客户群建议留空，避免重复编号。</div></el-form-item>
                <div class="grid grid-cols-2 gap-x-[14px]"><el-form-item label="客户姓名"><el-input v-model="reserveForm.member_name" placeholder="用于建群前核对" /></el-form-item><el-form-item label="客户手机号" required><el-input v-model="reserveForm.member_mobile" maxlength="20" :placeholder="reserveDialog.mode === 'wecom' ? '必须与选中的企微客户一致' : '用于客户身份核验'" /></el-form-item></div>
                <el-form-item label="门店名称"><el-input v-model="reserveForm.store_name" placeholder="将与群编号组成群名" /></el-form-item>
                <el-form-item v-if="reserveDialog.mode === 'wecom'" label="群负责人" required><el-select v-model="reserveForm.owner_uid" class="!w-full" filterable :disabled="!wecom.can_view_all" placeholder="选择已绑定企业微信的负责人"><el-option v-for="item in ownerStaff" :key="item.uid" :label="staffLabel(item)" :value="item.uid" /></el-select><div v-if="!wecom.can_view_all" class="mt-[5px] text-[12px] text-[#98a2b3]">普通员工只能以本人身份创建并负责客户群</div></el-form-item>
                <el-form-item v-if="reserveDialog.mode === 'wecom'" label="群协作人员（选填）"><el-select v-model="reserveForm.collaborator_uids" class="!w-full" multiple filterable collapse-tags placeholder="可同时邀请资料审核、运营人员"><el-option v-for="item in boundStaff" :key="item.uid" :label="staffLabel(item)" :value="item.uid" /></el-select></el-form-item>
                <el-form-item label="系统会员 ID（选填）"><el-input-number v-model="reserveForm.member_id" :min="0" class="!w-full" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="reserveDialog.visible = false">取消</el-button><el-button type="primary" :loading="reserveDialog.saving" @click="reserveAndCreate">{{ reserveDialog.mode === 'wecom' ? '生成编号并建群' : '登记并生成编号' }}</el-button></template>
        </el-dialog>

        <el-dialog v-model="bindDialog.visible" title="手工绑定企业微信群（异常兜底）" width="500px" destroy-on-close>
            <el-alert type="warning" :closable="false" title="仅在自动建群无法使用时回填 Chat ID；日常操作请使用“一键建群”。" class="mb-[16px]" />
            <el-form label-position="top"><el-form-item label="群编号"><b class="text-[#155eef]">{{ bindDialog.row?.group_no }}</b></el-form-item><el-form-item label="企业微信群 Chat ID"><el-input v-model="bindDialog.chatId" clearable /></el-form-item></el-form>
            <template #footer><el-button @click="bindDialog.visible = false">取消</el-button><el-button type="primary" :loading="bindDialog.saving" @click="saveBind">保存绑定</el-button></template>
        </el-dialog>

        <CorrectGroupNoDialog ref="correctGroupNoRef" @success="loadPage()" />
        <ProjectRefundDialog ref="refundDialogRef" @success="loadPage()" />

        <el-dialog v-model="materialDialog.visible" :title="materialDialog.mode === 'manual' ? '客户群台账已登记' : '客户群已创建'" width="560px" destroy-on-close>
            <el-result icon="success" :title="materialDialog.mode === 'manual' ? '系统业务编号已生成' : '企业微信群创建并绑定成功'" :sub-title="`群编号：${materialDialog.data.group_no || ''}`" />
            <template v-if="materialDialog.mode === 'manual'">
                <div class="rounded-[10px] border border-[#d1e0ff] bg-[#f5f8ff] p-[14px]">
                    <div class="text-[12px] text-[#667085]">建议微信群名</div>
                    <div class="mt-[6px] break-all text-[16px] font-semibold text-[#1849a9]">{{ materialDialog.data.suggested_name }}</div>
                </div>
                <div class="mt-[12px] text-[13px] leading-[22px] text-[#667085]">请按建议名称创建微信群，并把该编号告知客户。客户填写资料时只需输入群编号，系统会自动关联本次项目台账。</div>
            </template>
            <template v-else>
                <div class="rounded-[10px] bg-[#f8fafc] p-[14px] text-[13px] leading-[22px] text-[#475467] whitespace-pre-wrap">{{ materialDialog.data.text }}</div>
                <div class="mt-[12px] rounded-[8px] border border-[#e4e7ec] p-[12px] font-mono text-[12px] text-[#667085]">{{ materialDialog.data.path }}</div>
            </template>
            <template #footer>
                <el-button v-if="materialDialog.mode === 'manual'" @click="copyText(materialDialog.data.group_no, '群编号已复制')">复制编号</el-button>
                <el-button v-if="materialDialog.mode === 'manual'" type="primary" @click="copyText(materialDialog.data.suggested_name, '建议群名已复制')">复制建议群名</el-button>
                <el-button v-else @click="copyText(materialDialog.data.text, '入群说明已复制')">复制入群说明</el-button>
                <el-button :type="materialDialog.mode === 'manual' ? undefined : 'primary'" @click="materialDialog.visible = false">完成</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowDown, ChatDotRound, DocumentAdd } from '@element-plus/icons-vue'
import {
    bindProjectCenterWecom, bindProjectCenterWecomIdentity, closeProjectCenterGroup, completeProjectCenterWecom, failProjectCenterWecom,
    getProjectCenterGroups, getProjectCenterProjects, getProjectCenterWecomReadiness,
    getProjectCenterWecomOauthUrl, prepareProjectCenterWecom, reserveProjectCenterGroup
} from '@/addon/hsx_project_center/api'
import CorrectGroupNoDialog from '@/addon/hsx_project_center/components/CorrectGroupNoDialog.vue'
import ProjectRefundDialog from '@/addon/hsx_project_center/components/ProjectRefundDialog.vue'

declare global { interface Window { wx: any } }

// 企业微信（尤其 iOS）会按页面首次进入时的完整 URL 校验 JS-SDK 签名。
// OAuth 绑定完成后虽然会清理地址栏中的 code/state，但签名仍必须使用首次入口 URL。
const wecomEntryUrl = typeof window === 'undefined' ? '' : window.location.href.split('#')[0]
const loading = ref(false), rows = ref<any[]>([]), projects = ref<any[]>([]), creatingId = ref(0), bindingIdentity = ref(false)
const query = reactive<any>({ keyword: '', project_id: '', status: '' }), page = reactive({ page: 1, limit: 15, total: 0 })
const wecom = reactive<any>({ loaded: false, ready: false, reason: '', staff: [] })
const reserveDialog = reactive<{ visible:boolean; saving:boolean; mode:'manual'|'wecom' }>({ visible: false, saving: false, mode: 'manual' })
const reserveForm = reactive<any>({ group_no: '', project_id: undefined, member_id: 0, member_name: '', member_mobile: '', store_name: '', owner_uid: undefined, collaborator_uids: [] })
const bindDialog = reactive<any>({ visible: false, saving: false, row: null, chatId: '' })
const correctGroupNoRef = ref<any>(null)
const refundDialogRef = ref<any>(null)
const materialDialog = reactive<any>({ visible: false, mode: 'manual', data: {} })
const enabledProjects = computed(() => projects.value.filter(item => Number(item.status) === 1))
const boundStaff = computed(() => (wecom.staff || []).filter((item:any) => item.bound && Number(item.status) === 1))
const ownerStaff = computed(() => Number(wecom.can_view_all) === 1
    ? boundStaff.value
    : boundStaff.value.filter((item:any) => Number(item.uid) === Number(wecom.current_uid)))
const pageRows = (data:any) => data?.data || data?.list || []

async function loadProjects() { const res:any = await getProjectCenterProjects({ page: 1, limit: 100 }); projects.value = pageRows(res.data) }
async function loadWecom() { try { const res:any = await getProjectCenterWecomReadiness(); Object.assign(wecom, res.data || {}, { loaded: true, ready: Number(res.data?.ready) === 1 }) } catch (e:any) { Object.assign(wecom, { loaded: true, ready: false, reason: e?.message || '企业微信配置检查失败', staff: [] }) } }
async function loadPage(toPage?:number) { if (toPage) page.page = toPage; loading.value = true; try { const res:any = await getProjectCenterGroups({ ...query, page: page.page, limit: page.limit }); rows.value = pageRows(res.data); page.total = Number(res.data?.total || 0) } finally { loading.value = false } }
function reset() { Object.assign(query, { keyword: '', project_id: '', status: '' }); loadPage(1) }
function statusType(status:string) { return ['completed','refunded'].includes(status) ? 'success' : ['abandoned','dissolved'].includes(status) ? 'danger' : ['reserved','create_failed','refund_pending'].includes(status) ? 'warning' : 'primary' }
function groupStatusName(row:any) {
    if (row.status === 'reserved' && row.create_mode === 'manual') return '编号已登记'
    if (row.status === 'reserved') return '待企微建群'
    if (row.status === 'create_failed') return '可继续办理'
    return row.status_name || row.status
}
function staffLabel(item:any) { return `${item.name}${item.mobile ? ` · ${item.mobile}` : ''}` }

function resetReserveForm() {
    Object.assign(reserveForm, { group_no: '', project_id: undefined, member_id: 0, member_name: '', member_mobile: '', store_name: '', collaborator_uids: [] })
}
function normalizeExistingGroupNo() {
    const value = String(reserveForm.group_no || '').trim().replace(/[－—–]/g, '-').replace(/\s+/g, '')
    reserveForm.group_no = value
}
function currentOwner() {
    return ownerStaff.value.find((item:any) => Number(item.uid) === Number(wecom.current_uid))
}
async function openManualReserve() {
    reserveDialog.mode = 'manual'
    const current = currentOwner()
    if (!reserveForm.owner_uid && current) reserveForm.owner_uid = current.uid
    reserveDialog.visible = true
}
async function openWecomReserve() {
    await loadWecom()
    if (!wecom.ready) return ElMessageBox.alert(wecom.reason || '企业微信自动建群尚未就绪', '暂时无法自动建群', { type: 'warning', confirmButtonText: '知道了' })
    if (!isWecom()) return showOpenInWecom()
    if (!Number(wecom.current_bound)) return startSelfBind(false)
    reserveDialog.mode = 'wecom'
    const current = currentOwner()
    if (!reserveForm.owner_uid && current) reserveForm.owner_uid = current.uid
    if (!reserveForm.owner_uid && ownerStaff.value.length === 1) reserveForm.owner_uid = ownerStaff.value[0].uid
    reserveDialog.visible = true
}

async function reserveAndCreate() {
    if (!reserveForm.project_id) return ElMessage.warning('请选择项目')
    if (!String(reserveForm.member_mobile || '').trim() && !reserveForm.member_id) return ElMessage.warning('请填写客户手机号或系统会员 ID')
    if (reserveDialog.mode === 'wecom' && !reserveForm.owner_uid) return ElMessage.warning('请选择群负责人')
    normalizeExistingGroupNo()
    reserveDialog.saving = true
    try {
        const mode = reserveDialog.mode
        const payload = { ...reserveForm, group_no: mode === 'manual' ? reserveForm.group_no : '' }
        const res:any = await reserveProjectCenterGroup(payload)
        const row = res.data || {}
        reserveDialog.visible = false
        await loadPage(1)
        if (mode === 'wecom') {
            ElMessage.success(`群编号 ${row.group_no || ''} 已生成，正在拉起企业微信`)
            await startWecomGroup(row)
        } else {
            const project = projects.value.find((item:any) => Number(item.id) === Number(row.project_id || reserveForm.project_id))
            const customer = String(row.store_name || row.member_name || reserveForm.store_name || reserveForm.member_name || '').trim()
            const suggestedName = [project?.title, row.group_no, customer].filter(Boolean).join(' ')
            materialDialog.mode = 'manual'
            materialDialog.data = { ...row, suggested_name: suggestedName || row.group_no }
            materialDialog.visible = true
        }
        resetReserveForm()
    } finally { reserveDialog.saving = false }
}

async function startWecomGroup(row:any) {
    if (!isWecom()) return showOpenInWecom()
    if (!Number(wecom.current_bound)) return startSelfBind(false)
    creatingId.value = Number(row.id)
    try {
        const res:any = await prepareProjectCenterWecom(row.id, { url: wecomEntryUrl })
        const prepared = res.data || {}
        await setupWecomSdk(prepared.sdk)
        let externalUserId = String(prepared.external_userid || '')
        if (!prepared.chat_id && !externalUserId) {
            const contact = await selectExternalContact()
            externalUserId = String(contact.id || contact.userId || contact.external_userid || '')
            if (!externalUserId) throw new Error('企业微信未返回客户 ID')
            const expected = [row.member_name, row.member_mobile].filter(Boolean).join(' / ')
            await ElMessageBox.confirm(`已选择企微客户“${contact.name || externalUserId}”。请核对系统客户：${expected || '未填写'}，确认是同一人后再建群。`, '核对客户身份', { type: 'warning', confirmButtonText: '确认并建群', cancelButtonText: '重新选择' })
        }
        const result = await openEnterpriseChat({
            groupName: prepared.group_name,
            userIds: (prepared.user_ids || []).join(';'),
            externalUserIds: externalUserId,
            chatId: prepared.chat_id || ''
        })
        const chatId = String(result.chatId || result.chat_id || prepared.chat_id || '')
        if (!chatId) throw new Error('企业微信未返回群聊 Chat ID，请确认应用已配置 agentConfig 权限')
        const complete:any = await completeProjectCenterWecom(row.id, { wecom_chat_id: chatId, wecom_external_userid: externalUserId })
        materialDialog.mode = 'wecom'
        materialDialog.data = complete.data?.material || {}
        materialDialog.visible = true
        await loadPage()
    } catch (e:any) {
        const message = normalizeWecomError(e)
        if (!/取消|cancel/i.test(message)) {
            try { await failProjectCenterWecom(row.id, { error_message: message }) } catch (_) {}
            ElMessage.error(message)
            await loadPage()
        }
    } finally { creatingId.value = 0 }
}

function isWecom() { return /wxwork/i.test(navigator.userAgent) }
function showOpenInWecom() { return ElMessageBox.alert('自动建群必须在企业微信客户端内打开当前管理页面。请把当前地址配置到企微应用工作台，并从企业微信进入。', '请在企业微信中操作', { type: 'info', confirmButtonText: '知道了' }) }
function cleanOauthUrl() {
    const url = new URL(window.location.href)
    url.searchParams.delete('code')
    url.searchParams.delete('state')
    return url.toString()
}
async function startSelfBind(auto = false) {
    if (!wecom.ready) return
    if (!isWecom()) {
        if (!auto) await showOpenInWecom()
        return
    }
    bindingIdentity.value = true
    try {
        const res:any = await getProjectCenterWecomOauthUrl({ redirect_uri: cleanOauthUrl() })
        const url = String(res.data?.url || '')
        if (!url) throw new Error('未获取到企业微信身份授权地址')
        window.location.replace(url)
    } catch (e:any) {
        sessionStorage.removeItem('hsx_project_center_wecom_auto_bind')
        ElMessage.error(e?.message || '企业微信身份绑定发起失败')
        bindingIdentity.value = false
    }
}
async function completeSelfBindFromUrl() {
    const url = new URL(window.location.href)
    const code = String(url.searchParams.get('code') || '')
    const state = String(url.searchParams.get('state') || '')
    if (!code || !state.startsWith('pcbind_')) return false
    bindingIdentity.value = true
    try {
        await bindProjectCenterWecomIdentity({ code, state })
        window.history.replaceState({}, document.title, cleanOauthUrl())
        sessionStorage.removeItem('hsx_project_center_wecom_auto_bind')
        await loadWecom()
        ElMessage.success('企业微信身份已自动绑定')
        return true
    } catch (e:any) {
        window.history.replaceState({}, document.title, cleanOauthUrl())
        sessionStorage.removeItem('hsx_project_center_wecom_auto_bind')
        ElMessage.error(e?.message || '企业微信身份绑定失败')
        return false
    } finally { bindingIdentity.value = false }
}
function isValidWecomSdk(wx:any) {
    return wx && ['config', 'ready', 'error', 'agentConfig'].every(name => typeof wx[name] === 'function')
}
let wecomSdkPromise: Promise<void> | null = null
function loadWecomScript(src:string, marker:string) {
    return new Promise<void>((resolve, reject) => {
        const selector = `script[${marker}]`
        const old = document.querySelector(selector) as HTMLScriptElement | null
        if (old) {
            if (old.dataset.loaded === '1') return resolve()
            old.addEventListener('load', () => resolve(), { once: true })
            old.addEventListener('error', () => reject(new Error('企业微信 JS-SDK 加载失败')), { once: true })
            return
        }
        const script = document.createElement('script')
        script.src = src
        script.setAttribute(marker, '1')
        script.onload = () => { script.dataset.loaded = '1'; resolve() }
        script.onerror = () => reject(new Error('企业微信 JS-SDK 加载失败'))
        document.head.appendChild(script)
    })
}
function loadWecomSdk() {
    if (wecomSdkPromise) return wecomSdkPromise
    wecomSdkPromise = (async () => {
        // 企业微信旧版兼容方案需要同时加载微信基础 SDK 与企微应用 SDK。
        await loadWecomScript('https://res.wx.qq.com/open/js/jweixin-1.4.0.js', 'data-wecom-jssdk')
        await loadWecomScript('https://open.work.weixin.qq.com/wwopen/js/jwxwork-1.0.0.js', 'data-wecom-work-sdk')
        if (!isValidWecomSdk(window.wx)) throw new Error('企业微信 JS-SDK 加载不完整')
    })()
    return wecomSdkPromise
}
function wecomErrorText(error:any) { return String(error?.errMsg || error?.message || error || '未知错误').replace(/^Error:\s*/, '') }
function withTimeout<T>(promise:Promise<T>, timeout:number, message:string) {
    return new Promise<T>((resolve, reject) => {
        const timer = window.setTimeout(() => reject(new Error(message)), timeout)
        promise.then(value => { window.clearTimeout(timer); resolve(value) }, error => { window.clearTimeout(timer); reject(error) })
    })
}
async function setupWecomSdk(sdk:any) {
    const required = ['corp_id', 'agent_id', 'timestamp', 'nonce_str', 'signature', 'agent_signature']
    const missing = required.filter(key => sdk?.[key] === undefined || sdk?.[key] === null || String(sdk[key]).trim() === '')
    if (missing.length) throw new Error(`企业微信 JS-SDK 参数缺失：${missing.join('、')}`)
    await withTimeout(loadWecomSdk(), 12000, '企业微信 JS-SDK 加载超时')
    const wx = window.wx
    const signUrl = String(sdk.sign_url || wecomEntryUrl)
    const configJsApiList = Array.isArray(sdk.config_js_api_list)
        ? sdk.config_js_api_list.map((item:any) => String(item)).filter(Boolean)
        : ['checkJsApi']
    const agentJsApiList = Array.isArray(sdk.agent_js_api_list)
        ? sdk.agent_js_api_list.map((item:any) => String(item)).filter(Boolean)
        : (Array.isArray(sdk.js_api_list) ? sdk.js_api_list.map((item:any) => String(item)).filter(Boolean) : [])
    const configPayload = {
        beta: true,
        debug: false,
        appId: String(sdk.corp_id || '').trim(),
        timestamp: Number(sdk.timestamp),
        nonceStr: String(sdk.nonce_str || '').trim(),
        signature: String(sdk.signature || '').trim(),
        jsApiList: configJsApiList
    }
    if (!configPayload.appId || !Number.isFinite(configPayload.timestamp) || configPayload.timestamp <= 0 || !configPayload.nonceStr || !configPayload.signature || !configPayload.jsApiList.length) {
        throw new Error('企业微信 wx.config 参数格式不正确，请重新保存企业微信配置后再试')
    }
    const sdkScript = (document.querySelector('script[data-wecom-jssdk]') as HTMLScriptElement)?.src || 'unknown'
    const diagnostic = [
        `appId长度=${configPayload.appId.length}`,
        `timestamp=${configPayload.timestamp}`,
        `nonce长度=${configPayload.nonceStr.length}`,
        `签名长度=${configPayload.signature.length}`,
        `configJSAPI=${configPayload.jsApiList.join(',') || '空'}`,
        `agentJSAPI=${agentJsApiList.join(',') || '空'}`,
        `SDK=${sdkScript}`
    ].join('；')
    try {
        await withTimeout(new Promise<void>((resolve, reject) => {
            wx.config(configPayload)
            wx.ready(resolve)
            wx.error((error:any) => reject(error))
        }), 12000, '企业微信 wx.config 校验超时')
    } catch (error:any) {
        throw new Error(`企业微信 wx.config 校验失败：${wecomErrorText(error)}；签名页面：${signUrl}；诊断：${diagnostic}`)
    }
    try {
        await withTimeout(new Promise<void>((resolve, reject) => wx.agentConfig({
            corpid: String(sdk.corp_id || '').trim(),
            agentid: Number(sdk.agent_id),
            timestamp: Number(sdk.timestamp),
            nonceStr: String(sdk.nonce_str || '').trim(),
            signature: String(sdk.agent_signature || '').trim(),
            jsApiList: agentJsApiList,
            success: resolve,
            fail: reject
        })), 12000, '企业微信 agentConfig 校验超时')
    } catch (error:any) {
        throw new Error(`企业微信 agentConfig 校验失败：${wecomErrorText(error)}`)
    }
}
function selectExternalContact() { return new Promise<any>((resolve, reject) => { const done = (res:any) => { const list = res.userList || res.userlist || (res.userIds || []).map((id:string) => ({ id, userId: id })); if (res.errMsg && !/ok$/i.test(res.errMsg)) return reject(res); if (list.length !== 1) return reject(new Error('请选择且只能选择 1 位客户')); resolve(list[0]) }; const wx = window.wx; if (typeof wx.selectExternalContact === 'function') return wx.selectExternalContact({ filterType: 0, success: done, fail: reject }); wx.invoke('selectExternalContact', { filterType: 0 }, done) }) }
function openEnterpriseChat(options:any) { return new Promise<any>((resolve, reject) => { const done = (res:any) => { if (res.errMsg && !/ok$/i.test(res.errMsg)) return reject(res); resolve(res) }; const wx = window.wx; if (typeof wx.openEnterpriseChat === 'function') return wx.openEnterpriseChat({ ...options, success: done, fail: reject }); wx.invoke('openEnterpriseChat', options, done) }) }
function normalizeWecomError(error:any) { const raw = wecomErrorText(error); if (/cancel/i.test(raw)) return '用户取消建群'; if (/wx\.config|config:fail/i.test(raw)) return `${raw}。config:fail 表示身份注册参数不完整，请把本条完整诊断反馈给技术人员`; if (/invalid signature/i.test(raw)) return `${raw}。请核对页面签名地址及服务器时间`; if (/invalid url domain/i.test(raw)) return `${raw}。请把当前域名加入企业微信应用的“网页授权及 JS-SDK”可信域名`; if (/agentConfig|permission|function not exist|no permission/i.test(raw)) return `${raw}。请检查企业微信应用可见范围和客户联系权限`; return raw }

function openBind(row:any) { Object.assign(bindDialog, { visible: true, row, chatId: row.wecom_chat_id || '' }) }
async function saveBind() { bindDialog.saving = true; try { await bindProjectCenterWecom(bindDialog.row.id, { wecom_chat_id: bindDialog.chatId, create_mode: 'manual' }); ElMessage.success('群绑定已保存'); bindDialog.visible = false; await loadPage() } finally { bindDialog.saving = false } }
async function copyText(value:string, message:string) { await navigator.clipboard.writeText(String(value || '')); ElMessage.success(message) }
async function handleCommand(row:any, command:string) { if (command === 'manual') return openBind(row); if (command === 'refund') return refundDialogRef.value?.open(row); return closeGroup(row, command) }
async function closeGroup(row:any, status:string) { const label = status === 'dissolved' ? '群已解散' : '客户未付款 · 放弃办理'; const tip = status === 'abandoned' ? '只有确认群内未收到客户款项时才能直接放弃；如果客户已经付款，请返回选择“终止并退款”。' : '未完成的资料工单会同步结束，但历史不会删除。'; const result:any = await ElMessageBox.prompt(`确认将群 ${row.group_no} 标记为“${label}”吗？${tip}`, label, { inputPlaceholder:'请填写具体原因，便于后续查询', inputValidator:(value) => String(value || '').trim() ? true : '请填写结束原因', confirmButtonText:status === 'abandoned' ? '确认未收款并结束' : '确认结束', cancelButtonText:'取消', type:'warning' }); await closeProjectCenterGroup(row.id, { status, reason:result.value, confirmed_unpaid:status === 'abandoned' ? 1 : 0 }); ElMessage.success('群流程已结束'); await loadPage() }

onMounted(async () => {
    await Promise.all([loadProjects(), loadWecom(), loadPage()])
    const completed = await completeSelfBindFromUrl()
    if (!completed && wecom.ready && !Number(wecom.current_bound) && isWecom() && !sessionStorage.getItem('hsx_project_center_wecom_auto_bind')) {
        sessionStorage.setItem('hsx_project_center_wecom_auto_bind', '1')
        await startSelfBind(true)
    }
})
</script>
