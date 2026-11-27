<template>
    <section class="outbound">
        <header><div><h2>销售出库核对</h2><p>{{ boundSource ? '本站销售单：' + (sourceNo || '单号无效') : reviewMode === 'mock' ? '当前为本地模拟。下面的挂账批发单只能查看，不会提交博远。' : '查看本站挂账批发单在博远的出库结果。' }}</p></div><el-button v-if="!boundSource" :loading="loading" @click="loadList">刷新列表</el-button></header>
        <template v-if="boundSource">
            <el-alert v-if="sourceError" :title="sourceError" type="warning" :closable="false" />
            <template v-if="sourceValid">
                <el-button v-if="sourceTask" type="primary" plain :disabled="busy" @click="open(sourceTask)">查看本单核对记录</el-button>
                <template v-else-if="canPrepare">
                    <p>先准备这张单的客户、制单人和设备资料，核对后再决定是否提交。此步不发送博远。</p>
                    <el-button type="primary" :disabled="!!sourceError || busy" :loading="preparing" @click="prepare">准备这笔出库</el-button>
                </template>
                <el-alert v-else title="当前账号不能准备出库，请联系有销售同步权限的同事。" type="info" :closable="false" />
            </template>
        </template>
        <template v-else>
        <div class="filters">
            <el-input v-model="keyword" placeholder="搜索本站销售单号" clearable @keyup.enter="search" @clear="search" />
            <el-select v-if="reviewMode === 'test'" v-model="status" aria-label="按出库状态筛选" @change="search">
                <el-option v-for="item in filters" :key="item[0]" :value="item[0]" :label="item[1]" />
            </el-select>
            <el-button type="primary" @click="search">查询</el-button>
        </div>
        <el-alert v-if="listError" :title="listError" type="error" :closable="false" />
        <el-table v-loading="loading" :data="rows" :empty-text="listError ? '列表未能读取' : '没有匹配的挂账批发出库单'">
            <el-table-column prop="business_no" label="本站销售单" min-width="165" />
            <el-table-column label="同步状态" min-width="180"><template #default="{ row }"><el-tag v-if="mock(row)" type="info" effect="plain">{{ mockLabel }}</el-tag><el-tag v-else :type="tone(row.status)">{{ label(row.status) }}</el-tag></template></el-table-column>
            <el-table-column label="博远出库单" min-width="155"><template #default="{ row }">{{ mock(row) ? '—' : row.external_no || '—' }}</template></el-table-column>
            <el-table-column width="100" label="操作" fixed="right"><template #default="{ row }"><el-button link type="primary" @click="open(row.id)">查看核对</el-button></template></el-table-column>
        </el-table>
        <el-pagination v-if="total > 15" layout="prev, pager, next" :page-size="15" :total="total" :current-page="page" @current-change="changePage" />
        </template>

        <el-drawer v-model="visible" title="出库核对" size="min(650px, 100%)" append-to-body :close-on-click-modal="!busy" :close-on-press-escape="!busy" :show-close="!busy" @close="clearDetail">
            <div v-loading="reading" class="detail">
                <el-alert v-if="detailError" :title="detailError" type="warning" :closable="false" />
                <el-alert v-else-if="correction" :title="correction" type="warning" :closable="false" />
                <template v-if="detail">
                    <div class="summary"><div><small>本站销售单</small><h3>{{ detail.business_no }}</h3></div><el-tag v-if="mock(detail)" type="info" effect="plain">{{ mockLabel }}</el-tag><el-tag v-else :type="tone(detail.status)">{{ label(detail.status) }}</el-tag></div>
                    <p class="next">{{ detail.reason }}</p>
                    <p v-if="detail.active_connection !== true" class="next">这是原账套的记录，只能查看。</p>
                    <p v-else-if="!mock(detail) && detail.write_enabled !== true" class="next">当前账套未开放投递，只能查看原单。</p>
                    <dl><dt>客户</dt><dd>{{ detail.snapshot?.party?.name || '未取得' }}</dd><dt>博远单号</dt><dd>{{ mock(detail) ? '无（本地模拟）' : detail.external_no || '尚未取得' }}</dd></dl>
                    <el-button v-if="!mock(detail) && detail.active_connection && router.hasRoute('hsx_erp_receivable') && user.rules.includes('hsx_erp_receivable_info')" link type="primary" @click="router.push({name:'hsx_erp_receivable',query:{source_no:detail.business_no}})">查看关联应收</el-button>
                    <section v-if="trace" class="trace-box" aria-label="追查原出库单">
                        <h4>追查原出库单</h4>
                        <dl>
                            <dt>原手工单号</dt><dd class="trace-number"><template v-if="trace.manual"><input ref="manualInput" readonly aria-label="原手工单号" :value="trace.manual" /><el-button link type="primary" @click="copyTraceNumber">复制</el-button></template><span v-else>原请求未记录</span></dd>
                            <dt>业务日期</dt><dd>{{ trace.businessDate || '原请求未记录' }}</dd>
                            <dt>查询结果</dt><dd>{{ trace.label }}<span v-if="trace.found !== null"> · 候选 {{ trace.found }} 张</span></dd>
                            <template v-if="trace.date"><dt>查询时间</dt><dd>{{ trace.date }}（北京时间）</dd></template>
                        </dl>
                        <p v-if="trace.missing">有 {{ trace.missing }} 行未返回手工单号。</p>
                        <p v-if="trace.candidates.length">待核对候选：<span v-for="(c, i) in trace.candidates" :key="c.no + ':' + c.id + ':' + i">{{ i ? '、' : '' }}{{ c.no }}</span></p>
                        <p>请用原手工单号和业务日期核对博远原单（含草稿）。未查到不等于未出库，不要重新提交。</p>
                    </section>
                    <div v-if="canSubmit" class="customer">
                        <button class="matched-summary" type="button" :aria-expanded="editMatches" @click="editMatches = !editMatches">
                            <strong>{{ !customer || !creator ? '待补资料' : '资料已齐全' }}</strong>
                            <span>客户：{{ customerName || '待选择' }} · 制单人：{{ creatorName || '待选择' }}</span>
                            <span>{{ editMatches ? '收起' : '核对 / 调整' }}</span>
                        </button>
                        <template v-if="!customer || editMatches">
                        <label>博远客户</label>
                        <el-select v-if="canSearchCustomer" v-model="customer" filterable remote clearable reserve-keyword
                            :remote-method="findCustomer" :loading="searching" :disabled="busy" placeholder="搜索并选择博远客户"
                            @visible-change="customerMenu" @change="correction = ''">
                            <el-option v-for="c in customers" :key="c.id" :label="c.name + (c.code ? ' · ' + c.code : '')" :value="c.id" />
                        </el-select>
                        <span v-else>请让有权限的管理员关联这位客户。</span>
                        <small v-if="customerError">{{ customerError }}</small>
                        </template>
                        <template v-if="!creator || editMatches">
                        <label>博远制单人</label>
                        <el-select v-if="canSearchCustomer" v-model="creator" filterable remote clearable reserve-keyword
                            :remote-method="findCreator" :loading="findingCreator" :disabled="busy || !detail.creator_draft?.company_id"
                            :placeholder="detail.creator_draft?.mapped ? '沿用：' + detail.creator_draft.mapped.name : '搜索出库仓所属公司的员工'" @visible-change="v => v && findCreator('')" @change="correction = ''">
                            <el-option v-for="person in creators" :key="person.id" :label="person.name + (person.code ? ' · ' + person.code : '')" :value="person.id" />
                        </el-select>
                        <span v-else>{{ creatorName || '请让管理员先关联原销售单的制单员工' }}</span>
                        <small v-if="!detail.creator_draft?.company_id">请先同步并关联出库仓，再选择制单人。</small>
                        <small v-else-if="creatorError">{{ creatorError }}</small>
                        </template>
                    </div>
                    <p v-else-if="detail.request?.biz?.head?.creater_name">博远制单人：{{ detail.request.biz.head.creater_name }}</p>
                    <div class="devices"><h4>出库设备 · {{ detail.snapshot?.lines?.length || 0 }} 台</h4>
                        <div v-for="l in detail.snapshot?.lines || []" :key="l.source_line_id" class="device">
                            <div><strong>{{ l.product_name || '设备' }}</strong><p>串号 {{ l.imei || l.sn || '无' }}</p></div><span>¥{{ l.amount }}</span>
                        </div>
                    </div>
                    <p v-if="!mock(detail) && detail.status === 'succeeded'">已核对出库单和设备明细，不代表客户已付款。</p>
                </template>
            </div>
            <template #footer>
                <el-button :loading="reading" :disabled="busy" @click="refreshDetail">刷新结果</el-button>
                <el-button v-if="currentAction" type="primary" :loading="busy" :disabled="reading" @click="act(currentAction, true)">{{ currentAction === 'submit' ? '立即处理本单' : '继续核对原单' }}</el-button>
                <el-button v-if="canSubmit" type="primary" :loading="busy" :disabled="reading || !customer || !creator" @click="act('submit')">提交博远出库</el-button>
                <el-button v-else-if="canVerify" type="primary" :loading="busy" :disabled="reading" @click="act('verify')">核对原单</el-button>
            </template>
        </el-drawer>
    </section>
</template>
<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import { listOutbound, getOutbound, prepareOutbound, confirmOutbound, verifyOutbound, searchCustomers, searchCreators } from '../api/wholesale'
import { checkTaskReceipt, processCurrentAction, taskReceiptNotice, type ManualAction } from '../utils/manual-task-receipt'
import { wholesaleTrace } from '../utils/wholesale-trace'

const user = useUserStore(), rows = ref<any[]>([]), keyword = ref(''), appliedKeyword = ref(''), status = ref(''), page = ref(1), total = ref(0)
const route = useRoute(), reviewMode = ref(''), mockLabel = '本地模拟 · 未提交博远'
const router = useRouter(), preparing = ref(false), sourceError = ref('')
const boundSource = computed(() => route.query.sale_id !== undefined || route.query.task_id !== undefined)
const positiveId = (v: unknown) => (typeof v === 'string' || typeof v === 'number') && /^[1-9]\d{0,15}$/.test(String(v)) && Number.isSafeInteger(Number(v)) ? Number(v) : 0
const sourceNo = computed(() => typeof route.query.sale_no === 'string' && route.query.sale_no.length <= 128 && !/[\x00-\x1f]/.test(route.query.sale_no) ? route.query.sale_no.trim() : '')
const sourceSale = computed(() => positiveId(route.query.sale_id)), sourceTask = computed(() => positiveId(route.query.task_id))
const sourceValid = computed(() => !!(boundSource.value && sourceNo.value && (route.query.sale_id === undefined || sourceSale.value) && (route.query.task_id === undefined || sourceTask.value)))
const canPrepare = computed(() => sourceValid.value && !!sourceSale.value && !sourceTask.value && user.rules.includes('hsx_boyuan_wholesale_submit'))
let scopeVersion = 0
const mock = (row: any) => row?.mode === 'mock' && row.kind === 'sale'
const supported = (row: any) => (row?.mode === 'test' && row.kind === 'wholesale_outbound') || mock(row)
const loading = ref(false), listError = ref(''), visible = ref(false), reading = ref(false), busy = ref(false), detailError = ref('')
const detail = ref<any>(null), activeId = ref(0), customer = ref(''), customers = ref<any[]>([]), searching = ref(false), customerError = ref(''), correction = ref('')
const trace = computed(() => wholesaleTrace(detail.value))
const manualInput = ref<HTMLInputElement | null>(null)
async function copyTraceNumber() {
    const no = trace.value?.manual
    if (!no) return
    try {
        if (!navigator.clipboard?.writeText) throw Error('clipboard unavailable')
        await navigator.clipboard.writeText(no); if (trace.value?.manual === no) ElMessage.success('已复制原手工单号')
    } catch {
        if (trace.value?.manual !== no) return
        manualInput.value?.focus(); manualInput.value?.select()
        ElMessage.info('浏览器未允许复制，请手动复制原手工单号。')
    }
}
const creator = ref(''), creators = ref<any[]>([]), findingCreator = ref(false), creatorError = ref(''); let creatorVersion = 0
const editMatches = ref(false)
const customerName = computed(() => customers.value.find(x => x.id === customer.value)?.name || '')
const creatorName = computed(() => creators.value.find(x => x.id === creator.value)?.name || '')
function withSelected(rows: any[], before: any[], id: string) {
    const selected = before.find(item => item.id === id)
    return selected && !rows.some(item => item.id === id) ? [selected, ...rows] : rows
}
let listVersion = 0, detailVersion = 0, customerVersion = 0, pollUntil = 0, timer: ReturnType<typeof setTimeout> | undefined
const filters = [['', '全部状态'], ['awaiting_confirmation', '待确认提交'], ['blocked','未发送 · 需处理'], ['created_review', '已取单号 · 待核对'], ['attention','结果待核实'], ['needs_review', '需人工核对'], ['succeeded', '已核实出库']]
const labels: Record<string,string> = { awaiting_confirmation: '待确认提交', blocked: '未发送 · 需处理', pending: '已排队 · 待发送', processing: '处理中', confirming: '正在核对', attention: '结果待核实', needs_review: '需人工核对', created_review: '已取单号 · 待核对', succeeded: '已核实出库' }
const label = (s: string) => labels[s] || '待核实'
const tone = (s: string) => s === 'succeeded' ? 'success' : ['blocked','attention','needs_review','created_review'].includes(s) ? 'warning' : 'info'
const msg = (e: any, fallback: string) => typeof e === 'string' ? e : e?.msg || e?.message || fallback
const actionable = computed(() => detail.value?.mode === 'test' && detail.value?.kind === 'wholesale_outbound' && detail.value.active_connection === true && detail.value.write_enabled === true && !detailError.value)
const canSubmit = computed(() => actionable.value && user.rules.includes('hsx_boyuan_wholesale_submit') && detail.value.resolution !== 'source_changed' && detail.value.side_effect === 'none' && ['awaiting_confirmation','blocked'].includes(detail.value.status))
const canVerify = computed(() => actionable.value && user.rules.includes('hsx_boyuan_wholesale_verify') && detail.value.side_effect !== 'none' && ['created_review','attention','needs_review'].includes(detail.value.status))
const canSearchCustomer = computed(() => user.rules.includes('hsx_boyuan_reference_search'))
const currentAction = computed(() => {
    const action = processCurrentAction(detail.value, actionable.value && detail.value?.can_process_current === true)
    return action && user.rules.includes('hsx_boyuan_wholesale_' + action) ? action : ''
})

async function loadList() {
    if (boundSource.value) return
    const v = ++listVersion; loading.value = true; listError.value = ''
    try { const {data} = await listOutbound(page.value, appliedKeyword.value, status.value); if (v !== listVersion) return
        if (!Array.isArray(data?.data) || !['mock','test','production'].includes(data.review_mode) || data.data.some((r: any) => r.mode !== data.review_mode || !supported(r))) throw Error('列表与出库业务不符，请检查插件版本')
        if (data.review_mode !== 'test' && status.value) { status.value = ''; reviewMode.value = data.review_mode; await loadList(); return }
        reviewMode.value = data.review_mode
        rows.value = data.data; total.value = Number(data.total)
    } catch (e) { if (v === listVersion) { rows.value = []; total.value = 0; listError.value = msg(e, '无法读取出库单，请刷新重试') } }
    finally { if (v === listVersion) loading.value = false }
}
function search() { appliedKeyword.value = keyword.value.trim(); page.value = 1; loadList() }
function changePage(p: number) { page.value = p; loadList() }
function clearDetail() { clearTimeout(timer); ++detailVersion; ++customerVersion; ++creatorVersion; activeId.value = 0; detail.value = null; customer.value = ''; customers.value = []; customerError.value = ''; correction.value = ''; searching.value = false; creator.value = ''; creators.value = []; findingCreator.value = false; creatorError.value = '' }
function open(id: number) { if (busy.value || (boundSource.value && (!sourceValid.value || sourceTask.value !== id))) return; clearDetail(); activeId.value = Number(id); visible.value = true; pollUntil = Date.now()+90000; refreshDetail() }
async function refreshDetail() {
    clearTimeout(timer)
    const v = ++detailVersion, id = activeId.value; if (!id) return
    reading.value = true; detailError.value = ''; correction.value = ''
    let received = false, staleRead = false
    try { const {data} = await getOutbound(id); if (v !== detailVersion) return
        received = true
        const wrongSource = boundSource.value ? !sourceValid.value || sourceTask.value !== id || data?.active_connection !== true ||
            data.business_no !== sourceNo.value || data.snapshot?.source_document_no !== sourceNo.value ||
            (!!sourceSale.value && data.snapshot?.source_document_id !== 'sale:' + sourceSale.value) : data?.mode !== reviewMode.value
        if (!data || Number(data.id) !== id || !supported(data) || wrongSource ||
            (mock(data) && (data.snapshot?.sale_type !== 'wholesale' || data.snapshot?.payment_method_ref !== 'method:挂账'))) throw Error('不是当前账套的挂账批发出库任务')
        if (detail.value?.id === data.id && detail.value.connection_key === data.connection_key && Number(data.revision) < Number(detail.value.revision)) { staleRead = true; throw Error('详情读取的版本较旧，请刷新；已保留刚取得的回执。') }
        const last = detail.value?.creator_draft, next = data.creator_draft
        if (!detail.value || !customer.value || customer.value === last?.customer?.id) {
            customer.value = next?.customer?.id || ''; customers.value = next?.customer ? [next.customer] : []
        }
        if (!detail.value || !creator.value || creator.value === last?.mapped?.id || next?.company_id !== last?.company_id) {
            creator.value = next?.mapped?.id || ''; creators.value = next?.mapped ? [next.mapped] : []
        }
        if (!detail.value) editMatches.value = false
        detail.value = data
        const existing = rows.value.find(row => Number(row.id) === id)
        if (existing) Object.assign(existing, {status:data.status, reason:data.reason, external_no:data.external_no})
        if (!mock(data) && Date.now() < pollUntil && ['pending','processing','confirming'].includes(data.status)) timer = setTimeout(refreshDetail, 3500)
    } catch (e) { if (v === detailVersion) { if (!staleRead && (received || !detail.value?.external_no)) detail.value = null; detailError.value = msg(e, '未取得最新状态，请刷新结果') } }
    finally { if (v === detailVersion) reading.value = false }
}
function customerMenu(expanded: boolean) { if (expanded) findCustomer('') }
async function findCreator(keyword: string) {
    const company = detail.value?.creator_draft?.company_id
    if (!canSubmit.value || !canSearchCustomer.value || !company) return
    const v = ++creatorVersion; findingCreator.value = true; creatorError.value = ''
    try {
        const {data} = await searchCreators(detail.value.connection_key, company, keyword)
        if (v !== creatorVersion) return
        if (data?.code !== 'OK' || !Array.isArray(data?.data?.items)) throw Error('员工档案未能读取，请重试')
        creators.value = withSelected(data.data.items.filter((r: any) => r.enabled && !r.stale && r.extra?.company_id === company), creators.value, creator.value)
    } catch (e) { if (v === creatorVersion) creatorError.value = msg(e, '员工档案未能读取') }
    finally { if (v === creatorVersion) findingCreator.value = false }
}
async function findCustomer(query: string) {
    if (!canSubmit.value || !canSearchCustomer.value) return
    const v = ++customerVersion; searching.value = true; customerError.value = ''
    try { const res = await searchCustomers(detail.value.connection_key, query); if (v !== customerVersion) return
        if (res.data?.code !== 'OK' || !Array.isArray(res.data?.data?.items)) throw Error('客户档案未能读取，请重试')
        customers.value = withSelected(res.data.data.items.filter((r: any) => r.enabled && !r.stale), customers.value, customer.value)
    } catch (e) { if (v === customerVersion) customerError.value = msg(e, '客户档案未能读取') }
    finally { if (v === customerVersion) searching.value = false }
}
async function act(action: ManualAction, processCurrent = false) {
    const permitted = () => processCurrent ? currentAction.value === action : (action === 'submit' ? canSubmit.value : canVerify.value)
    if (busy.value || reading.value || !permitted()) return
    const v = scopeVersion, d = detail.value, id = activeId.value, rev = Number(d.revision), selected = customer.value
    if (!processCurrent && action === 'submit' && (!creator.value || !selected)) { correction.value = '请先选择博远' + [!selected && '客户', !creator.value && '制单人'].filter(Boolean).join('、'); return }
    if (!Number.isInteger(rev) || rev <= 0) { detailError.value = '任务版本无效，请刷新结果'; return }
    busy.value = true
    try {
        if (action === 'submit') {
            try { await ElMessageBox.confirm('将向博远提交一张挂账批发出库单，不登记收款。确认单号和设备无误？', '确认提交', {confirmButtonText:'确认提交',cancelButtonText:'再核对一下'}) } catch { return }
        }
        if (v !== scopeVersion || id !== activeId.value || d !== detail.value || !permitted()) return
        clearTimeout(timer)
        const body = {revision: rev, mode: d.mode, connection_key: d.connection_key}
        const payload = processCurrent ? {...body, intent:'process_current'} : action === 'submit' ? {...body, customer_id:selected, creator_id:creator.value} : body
        const result: any = await (action === 'submit' ? confirmOutbound(id, payload) : verifyOutbound(id, payload))
        if (v !== scopeVersion || id !== activeId.value || !visible.value) return
        const receipt = checkTaskReceipt(result?.data, id, rev)
        Object.assign(detail.value, {revision:receipt.revision, status:receipt.status, reason:receipt.reason || '', external_no:receipt.external_no || '', can_process_current:false})
        const notice = taskReceiptNotice(receipt, '出库'); ElMessage[notice.type](notice.text)
        pollUntil = Date.now()+90000; await refreshDetail(); if (!boundSource.value) loadList()
    } catch (e) {
        if (v !== scopeVersion) return
        const message = msg(e, '未取得处理结果，请先刷新原单状态')
        if (/\[SALE_(CUSTOMER|CREATOR)_(REQUIRED|INVALID)\]/.test(message)) correction.value = message.replace(/^.*?\]\s*/, '')
        else detailError.value = '请刷新原单核对，不要重复提交。' + message
        ElMessage.warning(correction.value || detailError.value)
    } finally { if (v === scopeVersion) busy.value = false }
}
async function prepare() {
    if (!canPrepare.value || preparing.value || busy.value || sourceError.value) return
    const v = scopeVersion, sale = sourceSale.value, no = sourceNo.value
    preparing.value = true
    try {
        const {data} = await prepareOutbound(sale,no)
        if (v !== scopeVersion) return
        const task = positiveId(data?.task_id)
        if (!task || data?.sale_no !== no) throw Error('未取得与原销售单一致的准备结果。')
        await router.replace({path:route.path,query:{sale_id:String(sale),sale_no:no,task_id:String(task)}})
    } catch (e) { if (v === scopeVersion) sourceError.value = msg(e,'未取得准备结果。') + ' 请返回本站销售单刷新核对；准备本身不会向博远出库。' }
    finally { if (v === scopeVersion) preparing.value = false }
}
async function enterRoute() {
    ++scopeVersion; ++listVersion; clearDetail(); visible.value = false
    sourceError.value = ''; preparing.value = false; busy.value = false; reading.value = false; loading.value = false; rows.value = []; listError.value = ''; reviewMode.value = ''
    keyword.value = appliedKeyword.value = sourceNo.value
    if (!boundSource.value) { await loadList(); return }
    if (!sourceValid.value) { sourceError.value = '原单链接缺少有效单号或编号，请从本站销售单重新进入。'; return }
    if (sourceTask.value) open(sourceTask.value)
}
onMounted(enterRoute)
watch(() => [route.query.sale_id,route.query.sale_no,route.query.task_id], enterRoute)
onBeforeUnmount(() => { ++scopeVersion; ++listVersion; clearDetail() })
</script>
<style scoped>
.trace-box{margin:16px 0;padding:12px 16px;border:1px solid var(--el-border-color-light);border-radius:6px;background:var(--el-fill-color-lighter)}.trace-box h4{margin:0}.trace-box p{margin:7px 0;font-size:13px;color:var(--el-text-color-regular);overflow-wrap:anywhere}.trace-number{display:flex;align-items:center;gap:8px;min-width:0}.trace-number input{flex:1;min-width:0;padding:4px 6px;border:1px solid var(--el-border-color);border-radius:4px;background:var(--el-bg-color);color:var(--el-text-color-primary);font:inherit;user-select:all}
.outbound { padding: 20px; background: var(--el-bg-color); }
.matched-summary {display:flex;align-items:center;gap:10px;flex-wrap:wrap;text-align:left;border:0;padding:12px;border-radius:6px;background:var(--el-fill-color-light);color:var(--el-text-color-primary);cursor:pointer}.matched-summary span:last-child {margin-left:auto;color:var(--el-color-primary)}
.outbound header,.summary,.device { display:flex; justify-content:space-between; align-items:center; gap:16px; }
h2,h3,h4,p { margin:0; } h2 { font-size:20px; } h3 { font-size:19px; overflow-wrap:anywhere; }
header p,.detail small,.detail dt,.detail p { color:var(--el-text-color-secondary); font-size:13px; }
header p,.device p { margin-top:5px; }
.filters { display:flex; gap:8px; flex-wrap:wrap; margin:22px 0 14px; }.filters .el-input {width:240px}.filters .el-select{width:175px}
.el-pagination { margin-top:16px; justify-content:flex-end; }.detail {min-height:160px}.detail .el-alert{margin-bottom:14px}
.next { margin:12px 0; }.detail dl { display:grid; grid-template-columns:auto 1fr; gap:12px 18px; }.detail dd { margin:0; overflow-wrap:anywhere; }
.customer {display:flex; flex-direction:column; gap:7px; margin:20px 0;}.customer small {color:var(--el-color-warning)}
.devices { margin:24px 0; }.device { padding:12px 0; border-bottom:1px solid var(--el-border-color-lighter); }.device p { overflow-wrap:anywhere; }
</style>
