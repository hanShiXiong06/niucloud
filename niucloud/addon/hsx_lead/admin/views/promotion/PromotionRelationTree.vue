<template>
    <section class="promotion-tree" aria-label="推广关系树">
        <form class="tree-toolbar" role="search" @submit.prevent="search">
            <el-input
                v-model="keyword"
                clearable
                :prefix-icon="Search"
                placeholder="搜索会员编号、姓名、手机号或门店"
                aria-label="搜索推广关系树"
                @clear="search"
            />
            <el-select v-model="activityDays" aria-label="业务活跃统计周期" @change="search"
                ><el-option :value="7" label="近7天业务" /><el-option :value="30" label="近30天业务"
            /></el-select>
            <el-button type="primary" native-type="submit" :loading="root.loading">查询</el-button>
            <el-button :disabled="root.loading" @click="reset">重置</el-button>
        </form>
        <div class="tree-caption"
            ><span>{{ keyword.trim() ? '全站搜索结果 · 可继续展开下级' : '按邀请关系逐级展开' }}</span
            ><el-tag v-if="rootMember && !keyword.trim()" closable effect="plain" @close="resetRoot"
                >{{ rootMember.name || rootMember.member_no || '指定会员' }}的下级</el-tag
            ><el-tooltip
                content="直属和二级人数来自当前邀请关系；佣金按原受益人统计。最近访问不代表当前在线，已入账不代表已提现。"
                ><el-button link type="info">统计口径</el-button></el-tooltip
            ></div
        >
        <el-alert v-if="root.error" :title="root.error" type="error" :closable="false" show-icon
            ><el-button link @click="readRoot()">重新加载</el-button></el-alert
        >
        <el-skeleton v-if="root.loading && !root.data.length" :rows="4" animated />
        <el-empty
            v-else-if="!root.error && !root.data.length"
            :description="keyword.trim() ? '未找到匹配会员' : '暂无可展示的邀请关系'"
            :image-size="72"
        />
        <div v-if="root.data.length" class="tree-columns" aria-hidden="true"
            ><span>会员 / 邀请关系</span><span>推广佣金</span><span>业务活跃</span></div
        >
        <ul class="tree-rows" aria-label="会员邀请层级">
            <li
                v-for="entry in visibleRows"
                :key="entry.key"
                :style="{ '--tree-indent': Math.min(entry.depth, 5) * 20 + 'px' }"
                :class="['tree-entry', { 'is-child': entry.depth > 0 }]"
            >
                <template v-if="entry.node">
                    <div class="tree-identity">
                        <el-button
                            class="tree-toggle"
                            text
                            :disabled="!entry.node.has_children"
                            :aria-label="`${expanded.has(entry.node.member_id) ? '收起' : '展开'}${entry.node.name || '会员'}的下级`"
                            :aria-expanded="entry.node.has_children ? expanded.has(entry.node.member_id) : undefined"
                            @click="toggle(entry.node)"
                            ><el-icon v-if="entry.node.has_children"
                                ><ArrowDown v-if="expanded.has(entry.node.member_id)" /><ArrowRight v-else /></el-icon
                            ><span v-else class="tree-leaf"
                        /></el-button>
                        <div class="tree-person"
                            ><div class="tree-name"
                                ><strong>{{ entry.node.merchant?.name || entry.node.name || '本站会员' }}</strong
                                ><el-tag
                                    v-if="entry.node.membership?.level_name"
                                    size="small"
                                    effect="plain"
                                    >{{ entry.node.membership.level_name }}</el-tag
                                ><el-tag
                                    v-if="entry.node.payment?.status_label"
                                    size="small"
                                    :type="entry.node.payment.status==='paid'?'success':'info'"
                                    effect="plain"
                                    >本人{{ entry.node.payment.status_label }}</el-tag
                                ></div
                            ><span
                                >{{ entry.node.member_no || `UID ${entry.node.member_id}`
                                }}<template v-if="entry.node.mobile_masked">
                                    · {{ entry.node.mobile_masked }}</template
                                ></span
                            ><span class="tree-counts"
                                >直属 {{ count(entry.node.direct_invite_count) }} · 二级
                                {{ count(entry.node.indirect_invite_count) }} · 本人服务费净付
                                {{ money(entry.node.payment?.service_net) }}</span
                            ><span
                                v-if="keyword.trim() && entry.node.path?.length"
                                class="tree-path"
                                >{{ pathLabel(entry.node.path) }}</span
                            ></div
                        >
                    </div>
                    <div class="tree-money"
                        ><strong>{{ money(entry.node.rewards?.settled) }}</strong
                        ><span>已入账 · 待结算 {{ money(entry.node.rewards?.pending) }}</span
                        ><span v-if="Number(entry.node.rewards?.held) > 0"
                            >待核对 {{ money(entry.node.rewards.held) }}</span
                        ></div
                    >
                    <div class="tree-activity"
                        ><span>{{ visit(entry.node.activity?.last_visit_time) }}</span
                        ><span
                            >近{{ activityDays }}天 · 发单 {{ count(entry.node.activity?.published_count) }} · 接单
                            {{ count(entry.node.activity?.accepted_count) }} · 成交
                            {{ count(entry.node.activity?.deal_count) }}</span
                        ></div
                    >
                </template>
                <div v-else class="tree-branch-state" role="status"
                    ><span v-if="entry.state?.loading">正在读取下级…</span
                    ><template v-else-if="entry.state?.error"
                        ><span>{{ entry.state.error }}</span
                        ><el-button link type="primary" @click="readBranch(entry.parent, !entry.state.data.length)"
                            >重试读取</el-button
                        ></template
                    ><el-button
                        v-else-if="entry.state && entry.state.data.length < entry.state.total"
                        link
                        type="primary"
                        @click="readBranch(entry.parent, false)"
                        >加载更多下级（{{ entry.state.data.length }}/{{ entry.state.total }}）</el-button
                    ><span v-else>暂无下级</span></div
                >
            </li>
        </ul>
        <div v-if="root.data.length < root.total && !root.error" class="tree-more"
            ><el-button :loading="root.loading" @click="readRoot(false)"
                >加载更多会员（{{ root.data.length }}/{{ root.total }}）</el-button
            ></div
        >
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { ArrowDown, ArrowRight, Search } from '@element-plus/icons-vue'
import { getPromotionTree } from '../../api/promotion'
import { coopError, coopMoney, type CoopRow } from '../../composables/cooperation'
import { timestampParts } from '../../composables/useLeadUi'
const props = defineProps<{ initialInviter?: CoopRow | null }>()
type Branch = { data: CoopRow[]; page: number; total: number; loading: boolean; error: string }
const empty = (): Branch => ({ data: [], page: 0, total: 0, loading: false, error: '' })
const root = reactive<Branch>(empty()), branches = reactive<Record<number, Branch>>({}), expanded = reactive(new Set<number>()), keyword = ref(''), activityDays = ref(7), rootMember = ref<CoopRow | null>(props.initialInviter || null)
let epoch = 0, disposed = false
const rootLoading = computed(() => root.loading)
function pageData(value: any): { data: CoopRow[]; total: number } {
    if (!value || !Array.isArray(value.data) || !Number.isSafeInteger(Number(value.total)) || Number(value.total) < 0 || value.data.some((row: CoopRow) => !Number.isSafeInteger(Number(row.member_id)) || Number(row.member_id) <= 0)) throw Error('关系树资料未完整返回，请核对配套后端')
    return { data: value.data.map((row: CoopRow) => ({ ...row, member_id: Number(row.member_id), has_children: row.has_children === true })), total: Number(value.total) }
}
async function readRoot(reset = true) {
    if (root.loading && !reset) return
    const turn = reset ? ++epoch : epoch, page = reset ? 1 : root.page + 1, criteria = keyword.value.trim()
    if (reset) { Object.assign(root, empty()); Object.keys(branches).forEach(key => delete branches[Number(key)]); expanded.clear() }
    root.loading = true; root.error = ''
    try {
        const response = await getPromotionTree({ parent_member_id: Number(rootMember.value?.member_id || 0), keyword: criteria, activity_days: activityDays.value, page, limit: 30 })
        if (disposed || turn !== epoch) return
        const result = pageData(response.data); root.data = reset ? result.data : [...root.data, ...result.data.filter(row => !root.data.some(old => old.member_id === row.member_id))]; root.total = result.total; root.page = page
    } catch (cause) { if (!disposed && turn === epoch) root.error = coopError(cause, '邀请关系树读取失败，请重试') }
    finally { if (!disposed && turn === epoch) root.loading = false }
}
async function readBranch(parent: number, reset = true) {
    if (!branches[parent]) branches[parent] = empty()
    const state = branches[parent]; if (state.loading) return
    const turn = epoch, page = reset ? 1 : state.page + 1; state.loading = true; state.error = ''
    try {
        const response = await getPromotionTree({ parent_member_id: parent, page, limit: 30, activity_days: activityDays.value })
        if (disposed || turn !== epoch || branches[parent] !== state) return
        const result = pageData(response.data); state.data = reset ? result.data : [...state.data, ...result.data.filter(row => !state.data.some(old => old.member_id === row.member_id))]; state.page = page; state.total = result.total
    } catch (cause) { if (!disposed && turn === epoch && branches[parent] === state) state.error = coopError(cause, '下级读取失败，不代表没有下级') }
    finally { if (!disposed && turn === epoch && branches[parent] === state) state.loading = false }
}
function toggle(node: CoopRow) { if (!node.has_children) return; const id = Number(node.member_id); if (expanded.has(id)) expanded.delete(id); else { expanded.add(id); if (!branches[id]) void readBranch(id) } }
type DisplayRow = { key: string; depth: number; parent: number; node?: CoopRow; state?: Branch }
const visibleRows = computed(() => {
    const out: DisplayRow[] = []
    function append(rows: CoopRow[], depth: number, path: Set<number>) {
        for (const node of rows) {
            const id = Number(node.member_id); if (path.has(id)) continue
            const rowKey = [...path, id].join('-')
            out.push({ key: `member-${rowKey}`, depth, parent: Number(node.parent_member_id || 0), node })
            if (!expanded.has(id)) continue
            const state = branches[id]; if (!state) continue
            append(state.data, depth + 1, new Set([...path, id]))
            if (state.loading || state.error || !state.data.length || state.data.length < state.total) out.push({ key: `branch-${rowKey}`, depth: depth + 1, parent: id, state })
        }
    }
    append(root.data, 0, new Set()); return out
})
function search() { if (keyword.value.trim()) rootMember.value = null; return readRoot() }
function reset() { keyword.value = ''; activityDays.value = 7; rootMember.value = null; return readRoot() }
function resetRoot() { rootMember.value = null; return readRoot() }
function count(value: unknown) { return Number.isSafeInteger(Number(value)) && value !== undefined && value !== null && Number(value) >= 0 ? String(value) : '—' }
function money(value: unknown) { return typeof value === 'string' && /^\d+(\.\d{1,2})?$/.test(value) ? coopMoney(value) : '待核对' }
function visit(value: unknown) { const timestamp = timestampParts(value); return timestamp ? `最近访问 ${timestamp.full}` : '暂无访问记录' }
function pathLabel(path: CoopRow[]) { return path.map(item => item.name || item.member_no || `#${item.member_id}`).join(' › ') }
watch(() => props.initialInviter, value => { rootMember.value = value || null; keyword.value = ''; void readRoot() })
onMounted(() => void readRoot()); onBeforeUnmount(() => { disposed = true; epoch++ })
defineExpose({ refresh: readRoot, loading: rootLoading })
</script>
<style scoped>
.promotion-tree{--cds-text-primary:var(--el-text-color-primary);--cds-text-secondary:var(--el-text-color-secondary);--cds-surface-panel:var(--el-bg-color);--cds-border:var(--el-border-color-lighter);--cds-fill-accent:var(--el-color-primary);color:var(--cds-text-primary);background:var(--cds-surface-panel);padding:16px;border:1px solid var(--cds-border);border-radius:8px}.tree-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.tree-toolbar>.el-input{flex:1;min-width:220px;max-width:440px}.tree-toolbar>.el-select{width:150px}.tree-caption{display:flex;gap:12px;align-items:center;color:var(--cds-text-secondary);font-size:12px;min-height:48px}.tree-caption>.el-button{margin-left:auto}.tree-columns,.tree-entry{display:grid;grid-template-columns:minmax(300px,1.4fr) minmax(160px,.7fr) minmax(230px,1fr);gap:16px;align-items:center}.tree-columns{padding:12px 8px;background:var(--el-fill-color-light);color:var(--cds-text-secondary);font-size:12px}.tree-rows{list-style:none;padding:0;margin:0}.tree-entry{position:relative;min-height:102px;padding:12px 8px 12px calc(8px + var(--tree-indent));border-bottom:1px solid var(--cds-border);box-sizing:border-box}.tree-entry.is-child::before{content:'';position:absolute;left:calc(var(--tree-indent) - 8px);top:0;height:50%;width:12px;border-left:1px solid var(--el-border-color);border-bottom:1px solid var(--el-border-color)}.tree-identity{display:flex;gap:8px;align-items:center;min-width:0}.tree-toggle{flex:none;width:32px;min-height:44px;padding:0!important}.tree-leaf{height:5px;width:5px;border-radius:50%;background:var(--el-text-color-placeholder)}.tree-person,.tree-money,.tree-activity{display:flex;flex-direction:column;gap:6px;min-width:0;font-size:12px;color:var(--cds-text-secondary);overflow-wrap:anywhere}.tree-name{display:flex;gap:8px;align-items:center;flex-wrap:wrap;color:var(--cds-text-primary)}.tree-name strong{font-size:14px;font-weight:600}.tree-name .el-tag{width:auto;flex:none;max-width:100%}.tree-money strong{font-size:18px;color:var(--cds-text-primary);font-variant-numeric:tabular-nums}.tree-path{font-size:11px}.tree-branch-state{grid-column:1/-1;display:flex;gap:12px;align-items:center;font-size:12px;color:var(--cds-text-secondary);min-height:44px}.tree-more{text-align:center;padding-top:16px}.tree-more .el-button{min-height:36px}.tree-toolbar .el-button+.el-button{margin-left:0}@media(max-width:1100px){.tree-columns{display:none}.tree-entry{grid-template-columns:minmax(240px,1fr) minmax(180px,.8fr)}.tree-activity{grid-column:1/-1;padding-left:40px;flex-direction:row;flex-wrap:wrap}}@media(max-width:650px){.tree-entry{grid-template-columns:1fr}.tree-money,.tree-activity{grid-column:auto;padding-left:40px}.tree-caption{flex-wrap:wrap}.tree-caption>.el-button{margin-left:0}.tree-toolbar>.el-input{max-width:none}}@media(prefers-reduced-motion:reduce){.promotion-tree *{transition:none!important;animation:none!important}}
</style>
