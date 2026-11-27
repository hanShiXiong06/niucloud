<template>
    <div class="main-page">
        <el-card shadow="never" class="!border-none">
            <template #header>
                <div><div class="text-[18px] font-semibold text-[#1d2939]">资料审核</div><div class="mt-[6px] text-[13px] text-[#667085]">逐项核验客户资料；退回时标记具体字段和修改示例，客户重新扫码即可看到问题并再次提交。</div></div>
            </template>
            <el-tabs v-model="query.status" @tab-change="loadPage(1)">
                <el-tab-pane label="全部" name=""/><el-tab-pane label="待审核" name="submitted"/><el-tab-pane label="审核中" name="reviewing"/><el-tab-pane label="待修改" name="rejected"/><el-tab-pane label="已通过" name="approved"/><el-tab-pane label="待退款" name="refund_pending"/><el-tab-pane label="已退款关闭" name="refunded"/><el-tab-pane label="已放弃" name="abandoned"/>
            </el-tabs>
            <div class="mb-[18px] flex flex-wrap items-center gap-[10px] rounded-[10px] bg-[#f8fafc] p-[14px]">
                <el-input v-model="query.keyword" clearable class="!w-[310px]" placeholder="工单号、群编号、客户、手机号或门店" @keyup.enter="loadPage(1)" />
                <el-select v-model="query.project_id" clearable class="!w-[190px]" placeholder="全部项目"><el-option v-for="item in projects" :key="item.id" :label="item.title" :value="item.id" /></el-select>
                <el-button type="primary" @click="loadPage(1)">查询</el-button><el-button @click="reset">重置</el-button>
            </div>
            <el-table v-loading="loading" :data="rows" size="large">
                <el-table-column label="工单 / 参与入口" min-width="190"><template #default="{ row }"><div class="font-medium">{{ row.application_no }}</div><div class="mt-[4px] text-[13px] text-[#155eef]">{{ row.group_no ? `群 ${row.group_no}` : '项目二维码直达' }}</div></template></el-table-column>
                <el-table-column label="项目 / 门店" min-width="210"><template #default="{ row }"><div>{{ row.project_title || '-' }}</div><div class="mt-[4px] text-[13px] text-[#667085]">{{ row.store_name || '未填写门店' }}</div></template></el-table-column>
                <el-table-column label="客户" min-width="180"><template #default="{ row }"><div>{{ row.member_name || '-' }}</div><div class="mt-[4px] font-mono text-[13px] text-[#667085]">{{ row.member_mobile || '-' }}</div></template></el-table-column>
                <el-table-column label="流程信息" min-width="190"><template #default="{ row }"><div>第 {{ row.submit_version || 1 }} 次提交</div><div class="mt-[4px] text-[12px] text-[#98a2b3]">客户声明付款：{{ timeText(row.payment_declared_at) }}</div></template></el-table-column>
                <el-table-column label="状态" width="105"><template #default="{ row }"><el-tag :type="statusType(row.status)">{{ row.status_name }}</el-tag></template></el-table-column>
                <el-table-column label="操作" fixed="right" width="230"><template #default="{ row }"><el-button link type="primary" @click="openDetail(row.id)">{{ canReview(row.status) ? '审核资料' : '查看详情' }}</el-button><el-button v-if="row.status === 'approved'" link type="success" :loading="archiveLoadingId === row.id" @click="downloadArchive(row)">下载资料包</el-button></template></el-table-column>
            </el-table>
            <div class="mt-[18px] flex justify-end"><el-pagination v-model:current-page="page.page" v-model:page-size="page.limit" layout="total, prev, pager, next" :total="page.total" @current-change="loadPage" /></div>
        </el-card>

        <el-drawer v-model="drawer.visible" title="客户资料审核" size="720px" destroy-on-close>
            <div v-loading="drawer.loading">
                <div v-if="detail.id" class="detail-head">
                    <div><div class="text-[18px] font-semibold text-[#1d2939]">{{ detail.project_title }}</div><div class="mt-[5px] text-[13px] text-[#667085]">{{ detail.group_no ? `群 ${detail.group_no} · ` : '' }}{{ detail.store_name || detail.member_name || '-' }} · {{ detail.member_mobile || '-' }}</div></div>
                    <div class="flex items-center gap-[10px]"><el-button v-if="detail.group_id && ['submitted','reviewing','rejected'].includes(detail.status)" v-permission="'hsx_project_center_application_correct_group_no'" size="small" plain @click="correctGroupNoRef?.open(detail, 'application')">更正群编号</el-button><el-button v-if="detail.status === 'approved'" size="small" type="success" plain :loading="archiveLoadingId === detail.id" @click="downloadArchive(detail)">下载交付资料包</el-button><el-tag :type="statusType(detail.status)">{{ detail.status_name }}</el-tag></div>
                </div>
                <el-alert v-if="detail.payment_declared_at" class="mb-[16px]" type="warning" :closable="false" :title="paymentDeclarationTitle(detail)" />
                <el-alert v-if="detail.eligibility_snapshot?.enabled" class="mb-[16px]" :type="detail.eligibility_snapshot?.eligible ? 'success' : 'error'" :closable="false" :title="eligibilityTitle(detail)" />
                <el-alert v-if="detail.last_reject_summary" class="mb-[16px]" type="error" :closable="false" :title="`上次退回：${detail.last_reject_summary}`" />

                <div class="mb-[10px] flex items-center justify-between">
                    <div><div class="text-[16px] font-semibold text-[#344054]">客户提交资料</div><span class="text-[12px] text-[#98a2b3]">勾选有问题的字段后，可从常用问题中快速选择</span></div>
                    <el-button v-permission="'hsx_project_center_review_reason_edit'" size="small" plain @click="reasonManagerRef?.open(detail.project_id, detail.project_title)">问题原因设置</el-button>
                </div>
                <el-empty v-if="!fields.length" description="未读取到万能表单字段" />
                <div v-else class="field-list">
                    <div v-for="item in fields" :key="fieldKey(item)" class="field-card" :class="{ 'is-problem': issueMap[fieldKey(item)]?.checked }">
                        <div class="field-main">
                            <div class="min-w-0 flex-1">
                                <div class="field-label">{{ fieldLabel(item) }}</div>
                                <ProjectFormLocationDetail v-if="isProjectLocation(item)" class="mt-[8px]" :data="item" />
                                <div v-else-if="isImageField(item)" class="field-images">
                                    <el-image v-for="(url, imageIndex) in imageUrls(item)" :key="`${fieldKey(item)}_${imageIndex}_${url}`" class="field-image" :src="img(url)" fit="cover" :preview-src-list="imageUrls(item).map(img)" :initial-index="imageIndex" preview-teleported />
                                    <span v-if="!imageUrls(item).length" class="field-empty">未上传图片</span>
                                    <span v-else class="image-count">共 {{ imageUrls(item).length }} 张，点击可查看原图</span>
                                </div>
                                <div v-else class="field-value">{{ fieldValue(item) }}</div>
                            </div>
                            <el-checkbox v-if="canReview(detail.status)" v-model="issueMap[fieldKey(item)].checked">此项有问题</el-checkbox>
                        </div>
                        <div v-if="issueMap[fieldKey(item)]?.checked" class="issue-editor">
                            <div class="flex items-center gap-[8px]">
                                <el-select v-model="issueMap[fieldKey(item)].reason_id" clearable filterable class="flex-1" placeholder="选择常用问题（也可以直接在下方自定义）" @change="applyReason(item)">
                                    <el-option v-for="reason in reviewReasons" :key="reason.id" :label="reason.title" :value="reason.id"><div class="reason-option"><span>{{ reason.title }}</span><small>{{ reason.message }}</small></div></el-option>
                                </el-select>
                                <el-button v-if="canSaveAsReason(item)" v-permission="'hsx_project_center_review_reason_edit'" type="primary" plain @click="saveAsReason(item)">存为常用问题</el-button>
                            </div>
                            <el-input v-model="issueMap[fieldKey(item)].message" class="mt-[8px]" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="明确告诉客户哪里不符合、需要怎么修改" @input="onIssueMessageInput(item)" />
                            <el-input v-model="issueMap[fieldKey(item)].example" class="mt-[8px]" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="正确示例（选填），例如：距门店 2 米拍摄完整门头，不要裁切" />
                        </div>
                    </div>
                </div>
                <div v-if="canReview(detail.status)" class="mt-[18px] rounded-[12px] border border-solid border-[#e7ecf3] p-[16px]"><div class="mb-[8px] font-medium text-[#344054]">审核补充说明</div><el-input v-model="reviewRemark" type="textarea" :rows="3" maxlength="1000" placeholder="选填。若退回但未勾选具体字段，此处必须填写整体原因。" /></div>
                <div v-if="canReview(detail.status) && detail.payment_check_required" class="mt-[16px] rounded-[12px] border border-solid border-[#f5d08a] bg-[#fffaf0] p-[16px]">
                    <div class="flex items-center font-semibold text-[#7a4e00]">分销项目付款确认<el-tooltip placement="top" :width="410" content="客户点击“我已付款”只代表提交声明，不代表到账。只有审核员在群内核对完整付款流水并勾选本项，审核通过后系统才会按推荐关系和会员等级快照生成一级、二级佣金单。"><el-icon class="ml-[6px] cursor-help text-[#b9892f]"><QuestionFilled /></el-icon></el-tooltip></div>
                    <el-checkbox v-model="paymentChecked" class="mt-[10px]">我已按群编号核对群内完整付款流水，确认实际到账</el-checkbox>
                    <div class="mt-[7px] text-[12px] leading-[20px] text-[#986b19]">确认人和确认时间会写入工单。示例：项目基础佣金 × 推广人会员等级系数，退款时自动冻结或冲红。</div>
                </div>
                <div v-if="detail.review_logs?.length" class="mt-[22px]"><div class="mb-[10px] text-[16px] font-semibold">审核记录</div><el-timeline><el-timeline-item v-for="log in detail.review_logs" :key="log.id" :timestamp="timeText(log.create_at)" placement="top"><div>{{ reviewActionText(log) }} · {{ log.operator_name || (log.operator_type === 'member' ? '客户' : '管理员') }}</div><div v-if="log.action === 'correct_group_no' && log.field_issues_json?.[0]?.message" class="mt-[3px] text-[13px] text-[#155eef]">{{ log.field_issues_json[0].message }}</div><div v-if="log.remark" class="mt-[3px] text-[13px] text-[#667085]">{{ log.remark }}</div></el-timeline-item></el-timeline></div>
            </div>
            <template #footer><div v-if="canReview(detail.status)" class="flex justify-end gap-[10px]"><el-button type="danger" plain :loading="drawer.saving" @click="submitReview('reject')">退回修改</el-button><el-button type="success" :loading="drawer.saving" @click="submitReview('approve')">审核通过</el-button></div></template>
        </el-drawer>
        <CorrectGroupNoDialog ref="correctGroupNoRef" @success="onGroupNoCorrected" />
        <ReviewReasonManager ref="reasonManagerRef" @saved="onReasonSettingsSaved" />
    </div>
</template>
<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { QuestionFilled } from '@element-plus/icons-vue'
import { img } from '@/utils/common'
import { downloadProjectCenterApplicationArchive, getProjectCenterApplication, getProjectCenterApplications, getProjectCenterProjects, getProjectCenterReviewReasons, reviewProjectCenterApplication, saveProjectCenterReviewReasons } from '@/addon/hsx_project_center/api'
import CorrectGroupNoDialog from '@/addon/hsx_project_center/components/CorrectGroupNoDialog.vue'
import ReviewReasonManager from '@/addon/hsx_project_center/components/ReviewReasonManager.vue'
import ProjectFormLocationDetail from '../diy_form/components/detail-project-form-location.vue'
const loading = ref(false), rows = ref<any[]>([]), projects = ref<any[]>([]), detail = ref<any>({}), reviewRemark = ref(''), paymentChecked = ref(false)
const archiveLoadingId = ref(0)
const correctGroupNoRef = ref<any>(null)
const reasonManagerRef = ref<any>(null)
const reviewReasons = ref<any[]>([])
const route = useRoute()
const query = reactive<any>({ status: '', project_id: '', keyword: '' }), page = reactive({ page: 1, limit: 15, total: 0 }), drawer = reactive({ visible: false, loading: false, saving: false })
const issueMap = reactive<Record<string, any>>({})
const fields = computed<any[]>(() => Object.values(detail.value.form_record?.recordsFieldList || detail.value.form_record?.records_field_list || {}))
const pageRows = (data:any) => data?.data || data?.list || []
const canReview = (status:string) => ['submitted', 'reviewing'].includes(status)
function statusType(status:string) { return ['approved','refunded'].includes(status) ? 'success' : ['rejected','abandoned'].includes(status) ? 'danger' : status === 'reviewing' ? 'primary' : 'warning' }
function timeText(value:any) { if (!value) return '-'; if (typeof value === 'string' && value.includes('-')) return value; return new Date(Number(value) * 1000).toLocaleString() }
function paymentDeclarationTitle(row:any) { return row.group_no ? '客户已点击“我已付款”。这只是资料提交流程声明，不代表系统或财务已确认到账；请按群编号核对群内流水截图。' : '客户通过项目二维码直达入口声明已付款。该声明不代表系统或财务已确认到账；请按线下约定核验付款凭证。' }
function eligibilityTitle(row:any) { const snapshot = row?.eligibility_snapshot || {}; if (snapshot.status === 'historical_grandfathered') return snapshot.message || '历史客户按原规则继续办理'; return `付款前地区查询：${snapshot.full_name || '未记录地区'} · ${snapshot.eligible ? '符合参与范围' : '不符合参与范围'}` }
function reviewActionText(log:any) { return ({ approve: '审核通过', reject: '退回修改', submit: '客户提交', resubmit: '客户重新提交', abandon: '结束办理', refund_request: '发起退款', refund_complete: '退款完成', refund_cancel: '取消退款并恢复', correct_group_no: '更正群编号' } as Record<string,string>)[String(log?.action || '')] || '流程记录' }
function fieldKey(item:any) { return String(item.id ?? item.field_key ?? item.name ?? item.field_name) }
function fieldLabel(item:any) { return item.field_name || item.label || item.title || '资料项' }
function isProjectLocation(item:any) { return String(item?.field_type || '') === 'ProjectFormLocation' }
function parseFieldValue(value:any) {
    if (typeof value !== 'string') return value
    const text = value.trim()
    if (!text || !['[', '{'].includes(text.charAt(0))) return value
    try { return JSON.parse(text) } catch (_) { return value }
}
function rawFieldValue(item:any) { return parseFieldValue(item.handle_field_value ?? item.field_value ?? item.value ?? '') }
function isImageField(item:any) { return String(item?.field_type || '').toLowerCase().includes('image') }
function imageUrls(item:any) {
    if (!isImageField(item)) return []
    const result:string[] = []
    const collect = (value:any) => {
        value = parseFieldValue(value)
        if (Array.isArray(value)) return value.forEach(collect)
        if (value && typeof value === 'object') {
            const preferred = value.url ?? value.path ?? value.src
            if (preferred) collect(preferred)
            return
        }
        if (typeof value !== 'string') return
        const url = value.trim()
        if (/^(?:https?:\/\/|\/|upload\/|addon\/)/i.test(url)) result.push(url)
    }
    collect(rawFieldValue(item))
    return [...new Set(result)]
}
function optionLabels(value:any):string[] {
    value = parseFieldValue(value)
    if (Array.isArray(value)) return value.flatMap(optionLabels).filter(Boolean)
    if (value && typeof value === 'object') {
        for (const key of ['text', 'label', 'name', 'title']) {
            const label = value[key]
            if (typeof label === 'string' && label.trim()) return [label.trim()]
        }
        return Object.values(value).flatMap(optionLabels).filter(Boolean)
    }
    if (typeof value === 'string' && value.trim()) return [value.trim()]
    if (typeof value === 'number') return [String(value)]
    return []
}
function fieldValue(item:any) {
    const rendered = parseFieldValue(item.render_value)
    if (typeof rendered === 'string' && rendered.trim() && !['[', '{'].includes(rendered.trim().charAt(0))) return rendered.trim()
    const value = rawFieldValue(item)
    const labels = optionLabels(value)
    if (labels.length) return [...new Set(labels)].join('、')
    return value === false ? '否' : value === true ? '是' : '-'
}
async function loadProjects() { const res:any = await getProjectCenterProjects({ page: 1, limit: 100 }); projects.value = pageRows(res.data) }
async function loadPage(toPage?:number) { if (toPage) page.page = toPage; loading.value = true; try { const res:any = await getProjectCenterApplications({ ...query, page: page.page, limit: page.limit }); rows.value = pageRows(res.data); page.total = Number(res.data?.total || 0) } finally { loading.value = false } }
function reset() { Object.assign(query, { status: '', project_id: '', keyword: '' }); loadPage(1) }
async function loadReviewReasons(projectId:number) { try { const res:any = await getProjectCenterReviewReasons(projectId); reviewReasons.value = res.data || [] } catch (_) { reviewReasons.value = [] } }
async function openDetail(id:number) { drawer.visible = true; drawer.loading = true; reviewRemark.value = ''; paymentChecked.value = false; Object.keys(issueMap).forEach(key => delete issueMap[key]); try { const res:any = await getProjectCenterApplication(id); detail.value = res.data || {}; paymentChecked.value = Number(detail.value.payment_confirmed_at || 0) > 0; await Promise.all([nextTick(), loadReviewReasons(Number(detail.value.project_id || 0))]); fields.value.forEach(item => { issueMap[fieldKey(item)] = { checked: false, reason_id: '', message: '', example: '' } }) } finally { drawer.loading = false } }
function applyReason(item:any) { const issue = issueMap[fieldKey(item)]; const reason = reviewReasons.value.find(row => String(row.id) === String(issue?.reason_id || '')); if (!issue || !reason) return; issue.message = String(reason.message || ''); issue.example = String(reason.example || '') }
function onIssueMessageInput(item:any) { const issue = issueMap[fieldKey(item)]; if (!issue?.reason_id) return; const reason = reviewReasons.value.find(row => String(row.id) === String(issue.reason_id)); if (!reason || String(reason.message || '') !== String(issue.message || '')) issue.reason_id = '' }
function canSaveAsReason(item:any) { const message = String(issueMap[fieldKey(item)]?.message || '').trim(); return !!message && !reviewReasons.value.some(reason => String(reason.message || '').trim() === message) }
async function saveAsReason(item:any) { const issue = issueMap[fieldKey(item)]; const message = String(issue?.message || '').trim(); if (!message) return; const newReason = { id: `reason_${Date.now()}`, title: fieldLabel(item), message, example: String(issue.example || '').trim(), sort: (reviewReasons.value.length + 1) * 10 }; const res:any = await saveProjectCenterReviewReasons(Number(detail.value.project_id), [...reviewReasons.value, newReason]); reviewReasons.value = res.data || []; const saved = reviewReasons.value.find(reason => String(reason.message || '').trim() === message); if (saved) issue.reason_id = saved.id; ElMessage.success('已加入当前项目的常用问题') }
function onReasonSettingsSaved(rows:any[]) { reviewReasons.value = rows || [] }
async function onGroupNoCorrected(row:any) {
    detail.value.group_no = String(row?.group_no || detail.value.group_no || '')
    detail.value.group_no_full = String(row?.group_no_full || detail.value.group_no_full || '')
    await Promise.all([loadPage(), openDetail(Number(detail.value.id || 0))])
}
function issues() { return fields.value.filter(item => issueMap[fieldKey(item)]?.checked).map(item => ({ field_key: fieldKey(item), field_label: fieldLabel(item), message: String(issueMap[fieldKey(item)].message || '').trim(), example: String(issueMap[fieldKey(item)].example || '').trim() })) }
async function submitReview(action:'approve'|'reject') { const fieldIssues = issues(); if (action === 'reject') { const empty = fieldIssues.find(item => !item.message); if (empty) return ElMessage.warning(`请填写“${empty.field_label}”的问题说明`); if (!fieldIssues.length && !reviewRemark.value.trim()) return ElMessage.warning('请标记有问题的字段或填写整体退回原因') } if (action === 'approve' && detail.value.payment_check_required && !paymentChecked.value) return ElMessage.warning('该项目会生成推广佣金，请先核对群内付款流水并勾选确认'); await ElMessageBox.confirm(action === 'approve' ? (detail.value.payment_check_required ? '确认资料准确、款项已核对，并生成推广佣金单吗？' : '确认资料准确并审核通过吗？') : '确认退回给客户修改吗？客户将看到逐项说明。', action === 'approve' ? '审核通过' : '退回修改', { type: action === 'approve' ? 'success' : 'warning' }); drawer.saving = true; try { await reviewProjectCenterApplication(detail.value.id, { action, field_issues: fieldIssues, remark: reviewRemark.value, payment_checked: Number(paymentChecked.value) }); ElMessage.success(action === 'approve' ? '资料已通过' : '资料已退回'); drawer.visible = false; await loadPage() } finally { drawer.saving = false } }
async function archiveBlob(response:any):Promise<Blob> { const blob = response instanceof Blob ? response : new Blob([response]); if (!blob.size) throw new Error('服务器返回了空文件'); const magic = new Uint8Array(await blob.slice(0, 4).arrayBuffer()); const isZip = magic.length >= 4 && magic[0] === 0x50 && magic[1] === 0x4b && [0x03, 0x05, 0x07].includes(magic[2]) && [0x04, 0x06, 0x08].includes(magic[3]); if (isZip) return blob; let message = '服务器返回的不是有效压缩包'; try { const text = await blob.text(); const json = JSON.parse(text); message = String(json?.msg || json?.message || message) } catch (_) {} throw new Error(message) }
async function downloadArchive(row:any) { archiveLoadingId.value = Number(row.id || 0); try { const response:any = await downloadProjectCenterApplicationArchive(archiveLoadingId.value); const blob = await archiveBlob(response); const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = `${row.application_no || '客户资料'}.zip`; document.body.appendChild(link); link.click(); link.remove(); window.setTimeout(() => URL.revokeObjectURL(url), 1000); ElMessage.success('交付资料包已生成') } catch (error:any) { let message = error?.msg || error?.message || '资料包生成失败，请检查资料状态或稍后重试'; const errorBlob = error?.response?.data; if (errorBlob instanceof Blob) { try { await archiveBlob(errorBlob) } catch (parsed:any) { message = parsed?.message || message } } ElMessage.error(message) } finally { archiveLoadingId.value = 0 } }
onMounted(async () => { await Promise.all([loadProjects(), loadPage()]); const id = Number(route.query.application_id || 0); if (id > 0) await openDetail(id) })
</script>
<style scoped>
.detail-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px; padding:16px; border-radius:12px; background:linear-gradient(135deg,#f4f7ff,#f8fafc); }
.field-list { display:flex; flex-direction:column; gap:10px; }.field-card { overflow:hidden; border:1px solid #e7ecf3; border-radius:12px; transition:.18s ease; }.field-card.is-problem { border-color:#fda29b; box-shadow:0 0 0 2px rgba(240,68,56,.06); }
.field-main { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; padding:14px 16px; }.field-label { color:#667085; font-size:13px; }.field-value { margin-top:6px; color:#1d2939; font-size:14px; line-height:22px; white-space:pre-wrap; overflow-wrap:anywhere; }
.field-images { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:8px; }.field-image { width:84px; height:84px; overflow:hidden; border:1px solid #eaecf0; border-radius:10px; background:#f8fafc; }.image-count,.field-empty { color:#98a2b3; font-size:12px; }
.issue-editor { padding:12px 16px 14px; border-top:1px solid #fee4e2; background:#fff7f6; }
.reason-option { display:flex; min-width:0; align-items:center; justify-content:space-between; gap:14px; }.reason-option span { flex:none; color:#344054; font-weight:500; }.reason-option small { overflow:hidden; color:#98a2b3; text-overflow:ellipsis; white-space:nowrap; }
</style>
