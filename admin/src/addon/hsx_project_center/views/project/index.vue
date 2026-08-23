<template>
    <div class="main-page">
        <el-card shadow="never" class="!border-none">
            <template #header>
                <div class="flex items-start justify-between gap-[20px]">
                    <div>
                        <div class="text-[18px] font-semibold text-[#1d2939]">项目配置</div>
                        <div class="mt-[6px] text-[13px] leading-[20px] text-[#667085]">统一配置项目微页面、资料表单和审核负责人，客户扫码后可在同一页了解项目并提交资料。</div>
                    </div>
                    <el-button type="primary" @click="openEditor()">新建项目</el-button>
                </div>
            </template>

            <div class="mb-[18px] flex flex-wrap items-center gap-[10px] rounded-[10px] bg-[#f8fafc] p-[14px]">
                <el-input v-model="query.keyword" clearable class="!w-[280px]" placeholder="项目名称或项目编号" @keyup.enter="loadPage(1)" />
                <el-select v-model="query.status" clearable class="!w-[150px]" placeholder="全部状态">
                    <el-option label="草稿" :value="0" /><el-option label="启用" :value="1" /><el-option label="停用" :value="2" />
                </el-select>
                <el-button type="primary" @click="loadPage(1)">查询</el-button><el-button @click="resetQuery">重置</el-button>
            </div>

            <el-table v-loading="loading" :data="rows" size="large">
                <el-table-column label="项目" min-width="250">
                    <template #default="{ row }">
                        <div class="font-medium text-[#1d2939]">{{ row.title }}</div>
                        <div class="mt-[5px] text-[12px] text-[#98a2b3]">{{ row.project_no }}<span v-if="row.subtitle"> · {{ row.subtitle }}</span></div>
                    </template>
                </el-table-column>
                <el-table-column label="业务入口" min-width="210">
                    <template #default="{ row }">
                        <div>资料表单 #{{ row.form_id || '-' }}</div>
                        <div class="mt-[4px] text-[12px] text-[#98a2b3]">审核员 {{ (row.reviewer_uids || []).length }} 人 · 工单 {{ row.application_count || 0 }} 笔</div>
                    </template>
                </el-table-column>
                <el-table-column label="项目能力" min-width="210">
                    <template #default="{ row }">
                        <el-tag effect="plain" :type="row.ai_enabled ? 'success' : 'info'">AI {{ row.ai_enabled ? '已开启' : '未开启' }}</el-tag>
                        <el-tag class="ml-[6px]" effect="plain" :type="row.distribution_enabled ? 'warning' : 'info'">分销 {{ row.distribution_enabled ? '已开启' : '未开启' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="参考金额" width="130"><template #default="{ row }">{{ row.payment_amount > 0 ? `¥${row.payment_amount}` : '-' }}</template></el-table-column>
                <el-table-column label="状态" width="100"><template #default="{ row }"><el-tag :type="Number(row.status) === 1 ? 'success' : 'info'">{{ row.status_name }}</el-tag></template></el-table-column>
                <el-table-column label="操作" fixed="right" width="230">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="showEntry(row)">参与入口</el-button>
                        <el-button link type="primary" @click="openEditor(row.id)">编辑</el-button>
                        <el-button link type="danger" @click="remove(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-[18px] flex justify-end"><el-pagination v-model:current-page="page.page" v-model:page-size="page.limit" layout="total, prev, pager, next" :total="page.total" @current-change="loadPage" /></div>
        </el-card>

        <el-drawer v-model="editor.visible" :title="editor.id ? '编辑项目' : '新建项目'" size="680px" destroy-on-close>
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top" class="project-form">
                <section class="form-section">
                    <div class="section-title">基础信息</div>
                    <div class="grid grid-cols-2 gap-x-[16px]">
                        <el-form-item label="项目名称" prop="title"><el-input v-model="form.title" maxlength="120" show-word-limit placeholder="如：美团闪购入驻" /></el-form-item>
                        <el-form-item label="展示顺序"><el-input-number v-model="form.sort" :min="0" :max="9999" class="!w-full" /></el-form-item>
                    </div>
                    <el-form-item label="一句话说明"><el-input v-model="form.subtitle" maxlength="255" placeholder="让客户快速理解项目价值" /></el-form-item>
                    <el-form-item label="项目封面"><upload-image v-model="form.cover" :limit="1" width="150px" height="90px" /></el-form-item>
                </section>

                <section class="form-section">
                    <div class="section-title">资料与审核</div>
                    <el-alert class="mb-[16px]" type="info" :closable="false" title="客户扫码进入后先完成付款；点击下一步时必须核对客户群编号，核对通过后才能填写并提交资料。" />
                    <el-form-item label="万能表单" prop="form_id">
                        <el-select v-model="form.form_id" filterable class="!w-full" placeholder="请选择已经配置好的资料表单">
                            <el-option v-for="item in metadata.forms" :key="item.form_id" :label="item.form_name" :value="item.form_id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="资料审核员" prop="reviewer_uids">
                        <el-select v-model="form.reviewer_uids" multiple filterable collapse-tags class="!w-full" placeholder="审核工单将分配给这些员工">
                            <el-option v-for="item in metadata.reviewers" :key="item.uid" :label="item.name" :value="item.uid" />
                        </el-select>
                    </el-form-item>
                </section>

                <section class="form-section">
                    <div class="section-title">客户付款引导</div>
                    <div class="grid grid-cols-2 gap-x-[16px]">
                        <el-form-item label="付款金额"><el-input-number v-model="form.payment_amount" :min="0" :precision="2" class="!w-full" /></el-form-item>
                        <el-form-item label="客户付款码" required><upload-image v-model="form.payment_qrcode" :limit="1" width="90px" height="90px" /><div class="mt-[7px] text-[12px] leading-[20px] text-[#98a2b3]">客户进入项目页立即看到缩略图；点击放大后可长按识别。</div></el-form-item>
                    </div>
                    <el-form-item label="付款提示"><el-input v-model="form.payment_tips" type="textarea" :rows="3" maxlength="1000" placeholder="示例：长按识别付款码，付款后截图并发送到客户群，再点击下一步填写资料。" /></el-form-item>
                </section>

                <section class="form-section">
                    <div class="section-title">项目介绍</div>
                    <el-form-item label="DIY 项目微页面">
                        <el-select v-model="form.intro_page_id" clearable filterable class="!w-full" placeholder="不选则使用下方的简易介绍">
                            <el-option v-for="item in metadata.diy_pages" :key="item.id" :label="item.page_name" :value="item.id" />
                        </el-select>
                        <div class="mt-[7px] text-[12px] leading-[20px] text-[#98a2b3]">选中后，客户端优先展示已装修的微页面；下方内容作为未选微页面或页面失效时的兜底。</div>
                    </el-form-item>
                    <el-form-item label="项目介绍正文"><el-input v-model="form.intro" type="textarea" :rows="5" placeholder="说明项目是什么、适合谁、投入和退出规则。" /></el-form-item>
                    <el-form-item label="办理步骤（每行一步）"><el-input v-model="form.steps_text" type="textarea" :rows="4" placeholder="了解项目\n项目页长按付款码完成付款\n将付款截图发送到客户群\n核对群号并提交资料\n等待审核" /></el-form-item>
                    <el-form-item label="常见问题（每行“问题|答案”）"><el-input v-model="form.faqs_text" type="textarea" :rows="5" placeholder="付款后多久上线？|20日前通常一周左右，具体以资料审核进度为准。" /></el-form-item>
                </section>

                <section class="form-section">
                    <div class="section-title">可选能力</div>
                    <div class="setting-row"><div><div class="setting-name">AI 项目顾问</div><div class="setting-desc">默认关闭；同时开启 hsx_ai 总开关和“项目中心”业务接入后，客户可在项目页弹窗咨询。</div></div><el-switch v-model="form.ai_enabled" /></div>
                    <template v-if="form.ai_enabled">
                        <el-alert class="my-[14px]" type="info" :closable="false" title="知识按当前站点和当前项目隔离；AI 无法确认时会引导客户返回群内联系工作人员。" />
                        <div class="grid grid-cols-2 gap-x-[16px]">
                            <el-form-item label="助手名称"><el-input v-model="form.ai_title" maxlength="30" show-word-limit placeholder="AI 项目顾问" /></el-form-item>
                            <el-form-item label="语音朗读"><div class="flex h-[32px] items-center gap-[16px]"><el-switch v-model="form.ai_voice_enabled" /><el-checkbox v-if="form.ai_voice_enabled" v-model="form.ai_auto_read">默认自动朗读</el-checkbox></div></el-form-item>
                        </div>
                        <el-form-item label="欢迎语"><el-input v-model="form.ai_welcome" type="textarea" :rows="2" maxlength="300" show-word-limit placeholder="告诉客户可以咨询哪些项目问题" /></el-form-item>
                        <el-form-item label="核心关注问题（每行一个）"><el-input v-model="form.ai_suggestions_text" type="textarea" :rows="4" placeholder="这个项目适合我吗？\n需要准备哪些资料？\n付款后多久可以上线？\n不做了如何退款？" /><div class="form-tip">弹窗首屏直接展示，建议配置 4～6 个最常问的问题。</div></el-form-item>
                        <el-form-item label="项目专属知识库"><el-input v-model="form.ai_knowledge" type="textarea" :rows="8" maxlength="30000" show-word-limit placeholder="补充项目费用、适用门店、资料要求、审核规则、上线周期、退出与退款规则等。项目介绍、办理步骤和常见问题会自动进入知识库，无需重复填写。" /><div class="form-tip">只填写允许客户公开了解的内容；到账状态、审核结果等实时事实仍由群内工作人员确认。</div></el-form-item>
                    </template>
                    <div class="setting-row"><div><div class="setting-name">邀请分佣</div><div class="setting-desc">默认关闭；首版仅预留能力，未配置完整规则前不会自动发佣。</div></div><el-switch v-model="form.distribution_enabled" /></div>
                    <div class="setting-row"><div><div class="setting-name">项目状态</div><div class="setting-desc">草稿可保存不完整配置；启用前必须配置付款码、万能表单和资料审核员。</div></div><el-select v-model="form.status" class="!w-[140px]"><el-option label="草稿" :value="0" /><el-option label="启用" :value="1" /><el-option label="停用" :value="2" /></el-select></div>
                </section>
            </el-form>
            <template #footer><div class="flex justify-end gap-[10px]"><el-button @click="editor.visible = false">取消</el-button><el-button type="primary" :loading="editor.saving" @click="save">保存项目</el-button></div></template>
        </el-drawer>
        <spread-popup ref="spreadPopupRef" />
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus'
import { addProjectCenterProject, deleteProjectCenterProject, editProjectCenterProject, getProjectCenterMetadata, getProjectCenterProject, getProjectCenterProjects } from '@/addon/hsx_project_center/api'
import spreadPopup from '@/components/spread-popup/index.vue'

const loading = ref(false), rows = ref<any[]>([]), formRef = ref<FormInstance>()
const query = reactive<any>({ keyword: '', status: '' }), page = reactive({ page: 1, limit: 15, total: 0 })
const metadata = reactive<any>({ forms: [], reviewers: [], diy_pages: [] })
const editor = reactive({ visible: false, id: 0, saving: false })
const spreadPopupRef = ref<any>()
const emptyForm = () => ({ title: '', subtitle: '', cover: '', status: 0, sort: 0, intro_page_id: 0, form_id: undefined as any, payment_qrcode: '', payment_amount: 5999, payment_tips: '请长按识别付款码完成付款，保存完整截图并发送到客户群，再点击下一步填写资料。', reviewer_uids: [] as number[], ai_enabled: false, ai_scene: 'hsx_project_center.customer_assistant', ai_title: 'AI 项目顾问', ai_welcome: '你可以直接问项目费用、准备资料、办理流程、审核和退款规则。', ai_suggestions_text: '这个项目适合我吗？\n需要准备哪些资料？\n付款后多久可以上线？\n审核不通过怎么办？\n不做了如何处理退款？', ai_knowledge: '', ai_voice_enabled: true, ai_auto_read: false, distribution_enabled: false, intro: '', steps_text: '', faqs_text: '' })
const form = reactive<any>(emptyForm())
const rules = { title: [{ required: true, message: '请输入项目名称', trigger: 'blur' }] }

function pageRows(data:any) { return data?.data || data?.list || [] }
async function loadPage(toPage?:number) { if (toPage) page.page = toPage; loading.value = true; try { const res:any = await getProjectCenterProjects({ ...query, page: page.page, limit: page.limit }); rows.value = pageRows(res.data); page.total = Number(res.data?.total || 0) } finally { loading.value = false } }
function resetQuery() { query.keyword = ''; query.status = ''; loadPage(1) }
function resetForm() { Object.assign(form, emptyForm()) }
function splitFaqs(text:string) { return text.split('\n').map(line => line.trim()).filter(Boolean).map(line => { const [question, ...answer] = line.split('|'); return { question: question.trim(), answer: answer.join('|').trim() } }).filter(item => item.question && item.answer) }
async function openEditor(id = 0) { resetForm(); editor.id = id; if (id) { const res:any = await getProjectCenterProject(id); const data = res.data || {}, config = data.config_json || {}; Object.assign(form, data, { ai_enabled: !!data.ai_enabled, distribution_enabled: !!data.distribution_enabled, intro: config.intro || '', steps_text: (config.steps || []).join('\n'), faqs_text: (config.faqs || []).map((item:any) => `${item.question}|${item.answer}`).join('\n'), ai_title: config.ai_title || 'AI 项目顾问', ai_welcome: config.ai_welcome || '你可以直接问项目费用、准备资料、办理流程、审核和退款规则。', ai_suggestions_text: (config.ai_suggestions || []).join('\n') || emptyForm().ai_suggestions_text, ai_knowledge: config.ai_knowledge || '', ai_voice_enabled: config.ai_voice_enabled !== 0, ai_auto_read: !!config.ai_auto_read }) } editor.visible = true }
async function save() { await formRef.value?.validate(); if (Number(form.status) === 1 && !String(form.payment_qrcode || '').trim()) return ElMessage.warning('启用项目前请先上传客户付款码'); if (Number(form.status) === 1 && !Number(form.form_id || 0)) return ElMessage.warning('启用项目前请先选择万能表单'); if (Number(form.status) === 1 && !(form.reviewer_uids || []).length) return ElMessage.warning('启用项目前请至少选择一名资料审核员'); editor.saving = true; try { const payload = { ...form, ai_enabled: Number(form.ai_enabled), ai_scene: 'hsx_project_center.customer_assistant', distribution_enabled: Number(form.distribution_enabled), config_json: { intro: form.intro.trim(), steps: form.steps_text.split('\n').map((item:string) => item.trim()).filter(Boolean), faqs: splitFaqs(form.faqs_text), ai_title: form.ai_title.trim(), ai_welcome: form.ai_welcome.trim(), ai_suggestions: form.ai_suggestions_text.split('\n').map((item:string) => item.trim()).filter(Boolean).slice(0, 8), ai_knowledge: form.ai_knowledge.trim(), ai_voice_enabled: Number(form.ai_voice_enabled), ai_auto_read: Number(form.ai_auto_read) } }; editor.id ? await editProjectCenterProject(editor.id, payload) : await addProjectCenterProject(payload); ElMessage.success('项目已保存'); editor.visible = false; await loadPage() } finally { editor.saving = false } }
async function remove(row:any) { await ElMessageBox.confirm(`确认删除“${row.title}”吗？已有客户资料的项目将不允许删除。`, '删除项目', { type: 'warning' }); await deleteProjectCenterProject(row.id); ElMessage.success('已删除'); await loadPage(1) }
function showEntry(row:any) { spreadPopupRef.value?.show('/addon/hsx_project_center/pages/project/detail', [{ name: 'id', value: row.id }], `${row.title}参与入口`, 'hsx_project_center') }
onMounted(async () => { const res:any = await getProjectCenterMetadata(); Object.assign(metadata, res.data || {}); await loadPage() })
</script>

<style scoped>
.form-section { margin-bottom: 18px; padding: 18px; border: 1px solid #e7ecf3; border-radius: 12px; background: #fff; }
.section-title { margin-bottom: 16px; padding-left: 10px; border-left: 3px solid var(--el-color-primary); color: #1d2939; font-size: 16px; font-weight: 600; }
.setting-row { display: flex; min-height: 62px; align-items: center; justify-content: space-between; gap: 20px; border-bottom: 1px solid #f0f2f5; }
.setting-row:last-child { border-bottom: 0; }
.setting-name { color: #344054; font-weight: 500; }.setting-desc { margin-top: 4px; color: #98a2b3; font-size: 12px; }
</style>
