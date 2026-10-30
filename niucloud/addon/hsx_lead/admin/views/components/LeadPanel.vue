<template>
    <section>
        <div class="lead-page-title"><div><h2>客资与跟进工单</h2><p>从设备清单勾选意向机型，完整记录数量、预算及补充要求，再匹配承接门店。</p></div><div class="lead-actions"><el-button @click="catalogVisible = true">设备清单</el-button><el-button @click="noticeSettingsVisible = true">通知设置</el-button><el-button type="primary" :icon="Plus" @click="openLead()">录入客资</el-button></div></div>
        <el-radio-group v-model="mode" class="lead-banner" @change="changeMode"><el-radio-button value="leads">客资池</el-radio-button><el-radio-button value="tasks">跟进工单</el-radio-button></el-radio-group>
        <div v-if="mode === 'tasks' && tasks.query.todo" class="lead-banner"><el-tag type="warning">异常待办：{{ todoLabels[tasks.query.todo] }}</el-tag><el-button link type="primary" @click="tasks.query.todo = ''; tasks.search()">查看全部工单</el-button></div>
        <el-alert v-if="list.error.value" :title="list.error.value" type="error" show-icon :closable="false" class="lead-error-banner" />
        <div class="lead-card lead-table-card">
            <HsxSearchPanel :collapsible="false" :show-layout-switch="false"><el-form inline class="lead-search" @submit.prevent="list.search">
                <el-form-item label="客户 / 单号"><el-input v-model="list.query.keyword" clearable placeholder="搜索客户、手机号或单号" @keyup.enter="list.search" @clear="list.search" /></el-form-item>
                <el-form-item label="处理状态"><el-select v-model="list.query.status" clearable placeholder="全部状态"><el-option v-for="status in mode === 'leads' ? leadStatuses : taskStatuses" :key="status" :label="label(status)" :value="status" /></el-select></el-form-item>
                <el-form-item><el-button :icon="Search" @click="list.search">查询</el-button><el-button :icon="Refresh" :loading="list.loading.value" @click="list.load">刷新</el-button></el-form-item>
            </el-form></HsxSearchPanel>
            <HsxTable :columns="columns" :data="list.rows.value" :loading="list.loading.value" :empty-text="mode === 'leads' ? '暂无客资，点击录入客资开始' : '暂无工单，客资分配给门店后生成工单'">
                <template #customer="{ row }"><div class="lead-cell"><strong>{{ row.customer_name || row.lead?.customer_name || '未填写称呼' }}</strong><small>{{ row.mobile || row.lead?.mobile || '未提供电话' }}</small><small>{{ row.region_text || row.lead?.region_text || region(row.lead || row) }}</small></div></template>
                <template #need="{ row }"><div class="lead-cell"><strong>{{ row.model || row.lead?.model || '未填写需求' }}</strong><small>{{ demandHint(row.lead || row) }}</small><small v-if="mode === 'tasks'">{{ progressLabel(row) }}</small></div></template>
                <template #source="{ row }"><div class="lead-cell"><strong>{{ mode === 'tasks' ? (row.merchant_name || row.merchant?.name || '未返回门店') : (row.source || '人工录入') }}</strong><small>{{ row.lead_no || row.task_no || `#${row.id}` }}</small></div></template>
                <template #status="{ row }"><div class="lead-cell"><el-tag :type="tone(row.status)">{{ label(row.status) }}</el-tag><el-tag v-if="mode === 'tasks' && row.status === 'assigned' && (row.is_overdue || isExpired(row))" type="danger" size="small" effect="light">已超时，需重派</el-tag><small v-else-if="mode === 'tasks' && row.status === 'assigned'">{{ deadlineLabel(row.response_deadline) }}</small></div></template>
                <template #updated="{ row }"><div class="lead-cell"><small>{{ time(row.update_time || row.create_time) }}</small></div></template>
                <template #actions="{ row }"><div class="lead-actions"><template v-if="mode === 'leads'"><el-button v-if="row.status === 'pending'" link type="primary" @click="match(row)">匹配 / 分配</el-button><el-button link type="primary" @click="openLead(row.id)">{{ row.status === 'pending' ? '编辑' : '详情' }}</el-button><el-button v-if="row.status !== 'pending'" link @click="viewTasks(row)">查看工单</el-button></template><template v-else><el-button link type="primary" @click="taskId = row.id; taskVisible = true">{{ row.status === 'deal_pending' ? '核验本批成交' : '查看 / 处理' }}</el-button><el-button v-if="['assigned', 'following'].includes(row.status)" link type="primary" :disabled="Number(row.total_confirmed_amount) > 0" @click="reassign(row.lead || row)">重新派单</el-button></template></div></template>
            </HsxTable>
            <div class="lead-pagebar"><HsxPagination v-model:current-page="list.query.page" v-model:page-size="list.query.limit" :total="list.total.value" @change="list.load" /></div>
        </div>
        <HsxDrawer v-model="leadVisible" :title="form.id ? (editable ? '编辑客资' : '客资详情') : '录入客资'" subtitle="人工选择设备清单；每项需求完整交给承接商户。" size="md" :body-loading="detailLoading" :show-footer="editable" :confirm-loading="saving" :confirm-disabled="detailLoading || !!detailError" confirm-text="保存客资" @confirm="save(false)">
            <el-alert v-if="detailError" :title="detailError" type="error" :closable="false" class="lead-error-banner" />
            <el-form v-if="!detailLoading && !detailError" ref="formRef" :model="form" :rules="rules" label-position="top" class="lead-form" :disabled="saving || !editable">
                <div class="lead-grid-2"><el-form-item label="客户称呼" prop="customer_name"><el-input v-model.trim="form.customer_name" maxlength="50" placeholder="如何称呼客户" /></el-form-item><el-form-item label="联系电话" prop="mobile"><el-input v-model.trim="form.mobile" maxlength="30" /></el-form-item></div>
                <el-form-item label="客户服务地区" prop="district_id"><RegionSelect v-model="area" /></el-form-item>
                <el-form-item label="需求方式" class="lead-demand-mode"><el-radio-group v-model="form.demand_mode"><el-radio value="any">任选一款</el-radio><el-radio value="all">分别要多台</el-radio></el-radio-group><p class="lead-muted">{{ form.demand_mode === 'all' ? '以下每项都是需要购买的设备，商户需分别核对各项数量。' : '以下是客户可接受的备选机型，只需满足其中一款，不是每款都买。' }}</p></el-form-item>
                <el-form-item v-if="editable" label="从设备清单勾选机型" prop="requirements"><el-select-v2 v-model="selectedCatalogIds" :options="catalogOptions" multiple filterable collapse-tags collapse-tags-tooltip :max-collapse-tags="3" :multiple-limit="20" :height="260" :loading="catalogLoading" placeholder="搜索品牌、型号或别名，可勾选多款" style="width: 100%" /><p class="lead-muted" style="margin-top: 8px">{{ catalogSource === 'configured' ? '当前使用自定义设备清单' : '未上传清单，先提供常用机型与已同步库存型号，仅为需求选项；可上传自定义列表' }}</p><span v-if="catalogError" class="lead-error">{{ catalogError }}</span><el-button link type="primary" :loading="catalogLoading" @click="loadCatalog">刷新选项</el-button></el-form-item>
                <RequirementsEditor v-if="editable" v-model="form.requirements" />
                <RequirementList v-else-if="form.requirements?.length" :items="form.requirements" :mode="form.demand_mode" />
                <el-form-item label="来源 / 需求备注"><el-input v-model="form.remark" type="textarea" :rows="4" maxlength="2000" show-word-limit placeholder="客资来源、成色偏好、到店时间或需要门店确认的事项" /></el-form-item>
                <el-form-item v-if="!form.consent_at" label="客户联系授权" prop="consent" :rules="[{ validator: (_rule, value, callback) => value ? callback() : callback(new Error('请先取得客户同意，再录入和派单')) }]"><el-checkbox v-model="form.consent">已确认客户同意由匹配门店联系，且仅用于本次购机需求</el-checkbox></el-form-item><el-alert v-else :title="`客户联系同意已记录 · ${time(form.consent_at)}`" type="success" :closable="false" />
            </el-form>
            <template v-if="editable" #footer><el-button :disabled="saving" @click="leadVisible = false">取消</el-button><el-button :loading="saving" @click="save(false)">保存客资</el-button><el-button type="primary" :loading="saving" @click="save(true)">保存并匹配</el-button></template>
        </HsxDrawer>
        <HsxDrawer v-model="matchVisible" title="匹配与分配门店" subtitle="系统提供地区与库存参考，派单仍由运营确认。" size="md" :body-loading="matching" show-footer :confirm-loading="assigning" :confirm-disabled="!selectedMerchant || matching || !!matchError || (manualRequired && !manualConfirmed)" confirm-text="确认派单" @confirm="assign">
            <el-alert v-if="matchError" :title="matchError" type="error" :closable="false" class="lead-error-banner" />
            <template v-if="!matching && !matchError"><div class="lead-summary"><span>客户<b>{{ matchingLead.customer_name }}</b></span><span>需求<b>{{ matchingLead.model }}</b></span><span>地区<b>{{ matchingLead.region_text || region(matchingLead) }}</b></span></div>
                <RequirementList v-if="matchingLead.requirements?.length" :items="matchingLead.requirements" :mode="matchingLead.demand_mode" />
                <el-alert title="库存快照不保证实际可售，不会锁货。门店接单时仍需确认；过期库存不能作为本次派单依据。" type="info" :closable="false" show-icon class="lead-banner" />
                <el-empty v-if="!candidates.length" description="暂无符合条件的门店，请先核对地区、需求型号或更新库存" :image-size="95" />
                <div v-for="candidate in candidates" :key="candidate.merchant_id" class="lead-candidate" :class="{ 'is-selected': selectedMerchant === Number(candidate.merchant_id) }" role="radio" :aria-checked="selectedMerchant === Number(candidate.merchant_id)" tabindex="0" @click="selectCandidate(candidate)" @keydown.enter.prevent="selectCandidate(candidate)" @keydown.space.prevent="selectCandidate(candidate)">
                    <div class="lead-page-title" style="margin-bottom: 10px"><div><h4>{{ candidate.name || candidate.merchant_name }}</h4><p>{{ candidate.match_reason || matchLevels[candidate.match_level] || '地区与库存参考匹配' }}</p></div><el-tag :type="Number(candidate.inventory_count) > 0 ? 'success' : 'warning'">{{ Number(candidate.inventory_count) > 0 ? `${candidate.inventory_count} 条匹配快照` : '需人工确认库存' }}</el-tag></div>
                    <div v-for="item in (candidate.items || []).slice(0, 3)" :key="item.id" class="lead-muted">{{ [item.model, item.memory, item.color, item.grade].filter(Boolean).join(' · ') }} · {{ item.price == null || item.price === '' ? '价格未返回' : money(item.price) }}</div>
                    <div v-if="candidate.matched_requirements?.length" class="lead-match-requirements"><div v-for="(item, index) in candidate.matched_requirements" :key="`${item.title}-${index}`" class="lead-muted">{{ item.title }} · 需求 {{ item.requested_quantity || 1 }} 台 · {{ item.reference_count || 0 }} 条库存参考 · <span class="lead-error">可售数量未确认</span></div></div>
                    <div v-if="candidate.unmatched_requirements?.length" class="lead-match-requirements"><el-tag type="warning" size="small">尚无有效库存参考</el-tag><p v-for="(item, index) in candidate.unmatched_requirements" :key="`unmatched-${index}`" class="lead-muted">{{ typeof item === 'string' ? item : item.title }}<span v-if="item.requested_quantity"> · 需求 {{ item.requested_quantity }} 台</span></p></div>
                </div>
                <div v-if="manualRequired" class="lead-banner"><el-alert title="当前门店没有符合条件的新鲜库存资料。只有已与门店人工核实库存和承接能力，才能继续派单。" type="warning" :closable="false" class="lead-banner" /><el-checkbox v-model="manualConfirmed">已人工核实该门店的库存与承接能力</el-checkbox></div>
                <el-form label-position="top" class="lead-form"><el-form-item label="商户响应窗口（分钟）"><el-input-number v-model="assignment.response_minutes" :min="5" :max="1440" :precision="0" /><span class="lead-muted" style="margin-left: 12px">派单后在工单中显示期限</span></el-form-item><el-form-item label="派单备注"><el-input v-model="assignment.remark" type="textarea" :rows="2" maxlength="1000" placeholder="交给门店的补充说明" /></el-form-item></el-form>
            </template>
        </HsxDrawer>
        <WorkorderDrawer v-model="taskVisible" :id="taskId" @changed="refreshAll" @reassign="reassign" />
        <CatalogDrawer v-model="catalogVisible" @changed="loadCatalog" /><NoticeSettings v-model="noticeSettingsVisible" />
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxSearchPanel from '@/addon/hsx_components/components/HsxSearchPanel/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import RegionSelect from './RegionSelect.vue'
import WorkorderDrawer from './WorkorderDrawer.vue'
import RequirementsEditor from './RequirementsEditor.vue'
import RequirementList from './RequirementList.vue'
import CatalogDrawer from './CatalogDrawer.vue'
import NoticeSettings from './NoticeSettings.vue'
import { deadlineLabel, isExpired, progressLabel } from '../../composables/deal'
import * as api from '../../api'
import { demandHint, errorText, label, money, region, time, tone, useLeadList, type Row } from '../../composables/useLeadUi'
const props = withDefaults(defineProps<{ initialFilter?: Row }>(), { initialFilter: () => ({}) })
const todoLabels: Record<string, string> = { overdue: '接单超时', partial: '部分成交未结束', verification: '本批成交待核验' }
const emit = defineEmits<{ (e: 'changed'): void }>()
const leads = useLeadList(api.getLeads, { status: '' }), tasks = useLeadList(api.getWorkorders, { status: '', todo: '' }), mode = ref('leads')
const list = computed(() => mode.value === 'leads' ? leads : tasks)
const leadStatuses = ['pending', 'assigned', 'following', 'deal_pending', 'completed', 'closed'], taskStatuses = ['assigned', 'following', 'deal_pending', 'completed', 'rejected', 'reassigned', 'closed', 'cancelled']
const columns = [{ prop: 'customer_name', label: '客户 / 地区', slot: 'customer', minWidth: 190 }, { prop: 'model', label: '需求 / 预算', slot: 'need', minWidth: 210 }, { prop: 'source', label: '来源 / 门店', slot: 'source', minWidth: 170 }, { prop: 'status', label: '当前状态', slot: 'status', width: 125 }, { prop: 'update_time', label: '更新时间', slot: 'updated', minWidth: 160 }, { prop: 'actions', label: '操作', slot: 'actions', minWidth: 210, fixed: 'right' as const }]
const empty = () => ({ id: 0, status: 'pending', customer_name: '', mobile: '', province_id: 0, city_id: 0, district_id: 0, province_name: '', city_name: '', district_name: '', demand_mode: 'any', requirements: [] as Row[], remark: '', consent: false, consent_at: 0 })
const form = reactive<Row>(empty()), formRef = ref<FormInstance>(), leadVisible = ref(false), detailLoading = ref(false), detailError = ref(''), saving = ref(false)
const editable = computed(() => !form.id || form.status === 'pending')
const area = computed({ get: () => ({ province_id: form.province_id, city_id: form.city_id, district_id: form.district_id }), set: value => Object.assign(form, value) })
const rules: FormRules = { customer_name: [{ required: true, message: '请填写客户称呼', trigger: 'blur' }], mobile: [{ required: true, message: '请填写联系电话', trigger: 'blur' }], requirements: [{ type: 'array', required: true, min: 1, max: 20, message: '请从设备清单选择 1–20 款意向机型', trigger: 'change' }], district_id: [{ validator: (_rule, _value, callback) => form.province_id && form.city_id && form.district_id ? callback() : callback(new Error('请选择完整省、市、区县')), trigger: 'change' }] }
const noticeSettingsVisible = ref(false)
const catalogVisible = ref(false), catalogItems = ref<Row[]>([]), catalogSource = ref('inventory'), catalogLoading = ref(false), catalogError = ref('')
const catalogOptions = computed(() => { const map = new Map(catalogItems.value.map(item => [String(item.id), { value: String(item.id), label: [item.brand, item.title, ...(item.aliases || [])].filter(Boolean).join(' · ') }])); for (const item of form.requirements) if (!map.has(String(item.catalog_id))) map.set(String(item.catalog_id), { value: String(item.catalog_id), label: `${item.title} · 已保存需求快照` }); return [...map.values()] })
const selectedCatalogIds = computed({ get: () => form.requirements.map((item: Row) => String(item.catalog_id)), set: (ids: string[]) => { form.requirements = ids.map(id => { const existing = form.requirements.find((item: Row) => String(item.catalog_id) === id); if (existing) return existing; const item = catalogItems.value.find(row => String(row.id) === id); return item ? { catalog_id: item.id, title: item.title, brand: item.brand || '', quantity: 1, budget_min: undefined, budget_max: undefined, remark: '' } : null }).filter(Boolean) } })
let catalogSequence = 0
async function loadCatalog() { const token = ++catalogSequence; catalogLoading.value = true; catalogError.value = ''; try { const result = await api.getCatalog(); if (token === catalogSequence) { catalogItems.value = result.data.items || []; catalogSource.value = result.data.source || 'inventory' } } catch (cause) { if (token === catalogSequence) catalogError.value = errorText(cause, '设备选项读取失败，请刷新或先维护设备清单。') } finally { if (token === catalogSequence) catalogLoading.value = false } }
function removeRequirement(id: any) { selectedCatalogIds.value = selectedCatalogIds.value.filter((value: string) => value !== String(id)) }
let detailSequence = 0, matchSequence = 0
async function openLead(id = 0) {
    const sequence = ++detailSequence; Object.assign(form, empty()); leadVisible.value = true; detailError.value = ''; detailLoading.value = !!id
    void loadCatalog()
    if (!id) return
    try { const result = await api.getLead(id); if (sequence === detailSequence) Object.assign(form, result.data, { demand_mode: result.data.demand_mode || 'any', requirements: (result.data.requirements || []).map((item: Row) => ({ ...item, quantity: Number(item.quantity || 1), budget_min: item.budget_min == null ? undefined : Number(item.budget_min), budget_max: item.budget_max == null ? undefined : Number(item.budget_max) })) }) }
    catch (cause) { if (sequence === detailSequence) detailError.value = errorText(cause, '客资加载失败，请关闭后重试。') }
    finally { if (sequence === detailSequence) detailLoading.value = false }
}
async function save(andMatch = false) {
    if (!await formRef.value?.validate().catch(() => false)) return
    for (const item of form.requirements) { if (!Number.isInteger(Number(item.quantity)) || Number(item.quantity) < 1 || Number(item.quantity) > 999) { ElMessage.warning(`${item.title}：请填写 1–999 台的整数数量`); return }; if (Number(item.budget_max) > 0 && Number(item.budget_min) > Number(item.budget_max)) { ElMessage.warning(`${item.title}：最高预算不能小于最低预算`); return } }
    saving.value = true
    const payload: Row = { ...form, requirements: form.requirements.map((item: Row) => ({ catalog_id: item.catalog_id, title: item.title, brand: item.brand || '', quantity: Number(item.quantity), budget_min: Number(item.budget_min) > 0 ? item.budget_min : null, budget_max: Number(item.budget_max) > 0 ? item.budget_max : null, remark: item.remark || '' })) }; delete payload.model; delete payload.budget_min; delete payload.budget_max
    try { const result = await api.saveLead(payload); leadVisible.value = false; ElMessage.success('客资已保存，多项需求将完整展示给承接商户'); await leads.load(); emit('changed'); if (andMatch) { const saved = result.data?.id ? result.data : { ...payload, id: form.id }; if (saved.id) await match(saved); else ElMessage.warning('保存已完成，未返回客资编号，请从列表进入匹配') } }
    catch (cause) { ElMessage.error(errorText(cause, '客资未保存，请检查填写内容。')) }
    finally { saving.value = false }
}
const matchVisible = ref(false), matching = ref(false), matchError = ref(''), matchingLead = ref<Row>({}), candidates = ref<Row[]>([]), selectedMerchant = ref(0), assigning = ref(false), assignment = reactive({ response_minutes: 30, remark: '' })
const manualConfirmed = ref(false), manualRequired = computed(() => { const candidate = candidates.value.find(row => Number(row.merchant_id) === selectedMerchant.value); return !!candidate && (!!candidate.needs_manual || Number(candidate.inventory_count) <= 0) })
const matchLevels: Record<string, string> = { district: '同区县 · 地区优先', city: '同城市 · 需确认服务范围', province: '同省份 · 需确认跨市服务', cross_region: '跨地区 · 需人工确认' }
async function match(row: Row) {
    const sequence = ++matchSequence; matchVisible.value = true; matching.value = true; matchError.value = ''; matchingLead.value = row; candidates.value = []; selectedMerchant.value = 0; assignment.response_minutes = 30; assignment.remark = ''
    try { const result = await api.getMatches(row.id); if (sequence === matchSequence) candidates.value = result.data.candidates || [] }
    catch (cause) { if (sequence === matchSequence) matchError.value = errorText(cause, '匹配失败，请检查库存同步后重试。') }
    finally { if (sequence === matchSequence) matching.value = false }
}
function selectCandidate(candidate: Row) { selectedMerchant.value = Number(candidate.merchant_id); manualConfirmed.value = false }
async function assign() {
    if (!selectedMerchant.value || assigning.value || (manualRequired.value && !manualConfirmed.value)) return
    assigning.value = true
    try { await api.assignLead(matchingLead.value.id, { merchant_id: selectedMerchant.value, ...assignment, manual_stock_confirmed: manualRequired.value && manualConfirmed.value }); matchVisible.value = false; ElMessage.success('已派单，请等待门店接单确认库存'); await refreshAll() }
    catch (cause) { ElMessage.error(errorText(cause, '派单未完成，客资或库存可能已变化，请重新匹配。')) }
    finally { assigning.value = false }
}
const taskVisible = ref(false), taskId = ref(0)
async function reassign(row: Row) { taskVisible.value = false; const lead = row.lead || row; if (!lead.id) { ElMessage.warning('缺少客资编号，请刷新工单'); return }; await match(lead) }
function changeMode() { return list.value.load() }
async function viewTasks(row: Row) { mode.value = 'tasks'; tasks.query.keyword = row.lead_no || String(row.id); tasks.query.status = ''; await tasks.search() }
async function refreshAll() { await list.value.load(); emit('changed') }
watch(leadVisible, value => { if (!value) detailSequence++ })
watch(matchVisible, value => { if (!value) matchSequence++ })
onBeforeUnmount(() => { detailSequence++; matchSequence++; catalogSequence++ })
onMounted(() => { if (props.initialFilter.todo) { mode.value = 'tasks'; tasks.query.todo = props.initialFilter.todo; void tasks.load() } else void leads.load() })
</script>
