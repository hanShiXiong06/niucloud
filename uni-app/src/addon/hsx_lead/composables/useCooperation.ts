import { computed, reactive, ref } from 'vue'
import { getSiteId, getToken } from '@/utils/common'
import * as api from '../api/cooperation'
import type { CoopRow, CooperationTab, Direction, LeadAction } from '../api/cooperation'

/** Server-owned capabilities and money; this layer only manages requests and view state. */
export function useCooperation(initialTab: CooperationTab = 'deliveries') {
    // Session presence is known synchronously. Do not render the visitor shell
    // for one frame while an already signed-in page waits for mounted/resume.
    // Business data still requires the current identity and a successful read.
    const loggedIn = ref(!!getToken()), ready = ref(false), loading = ref(false), error = ref(''), profile = ref<CoopRow>({}), config = ref<CoopRow>({}), providers = ref<CoopRow[]>([]), stats = ref<CoopRow>({}), wallet = ref<CoopRow>({}), pendingEntity = ref<CoopRow>({}), memberId = ref(0)
    const onboardingInviter = ref<CoopRow>({})
    const tab = ref<CooperationTab>(initialTab), direction = ref<Direction>('outgoing'), query = reactive({ keyword: '', status: '', page: 1, limit: 20 }), rows = ref<CoopRow[]>([]), total = ref(0), listLoading = ref(false), listError = ref(''), walletLoading = ref(false), walletError = ref('')
    const detail = ref<CoopRow>({}), detailLoading = ref(false), detailError = ref(''), busy = ref(false), writeError = ref('')
    const canLoadMore = computed(() => rows.value.length < total.value)
    const walletPage = ref(0)
    const walletCanLoadMore = computed(() => ['entries'].some(key => (wallet.value[key]?.data || []).length < Number(wallet.value[key]?.total || 0)))
    let identity = '', generation = 0, listGeneration = 0, detailGeneration = 0, walletGeneration = 0, active = true, disposed = false
    type ListRead = { who: string; append: boolean; signature: string }
    type DetailRead = { who: string; id: number }
    let pendingListRead: ListRead | null = null, interruptedListRead: ListRead | null = null, pendingDetailRead: DetailRead | null = null, interruptedDetailRead: DetailRead | null = null
    const listSignature = () => JSON.stringify([direction.value, query.keyword, query.status, query.limit])
    const siteId = () => {
        // Match the framework request header source. Production must not borrow
        // another tenant from an old development cache; getSiteId still owns
        // the H5 route and mini-program extension configuration overrides.
        const configured = import.meta.env.VITE_SITE_ID
        const fallback = process.env.NODE_ENV === 'development' ? configured || (typeof uni !== 'undefined' && typeof uni.getStorageSync === 'function' ? uni.getStorageSync('wap_site_id') || '' : '') : configured
        return String(getSiteId(fallback) || '')
    }
    const sessionScope = () => { const token = String(getToken() || ''); return token ? `${siteId()}:${token}` : '' }
    const current = (value: string) => active && !disposed && value !== '' && value === identity && value === sessionScope()
    function identify() { const value = sessionScope(); loggedIn.value = !!getToken(); if (identity !== value) { identity = value; generation++; listGeneration++; detailGeneration++; walletGeneration++; pendingListRead = null; interruptedListRead = null; pendingDetailRead = null; interruptedDetailRead = null; profile.value = {}; memberId.value = 0; pendingEntity.value = {}; onboardingInviter.value = {}; config.value = {}; providers.value = []; stats.value = {}; rows.value = []; total.value = 0; detail.value = {}; wallet.value = {}; ready.value = false; loading.value = false; listLoading.value = false; detailLoading.value = false; walletLoading.value = false; walletPage.value = 0; query.keyword = ''; query.status = ''; query.page = 1; error.value = ''; listError.value = ''; detailError.value = ''; walletError.value = ''; writeError.value = '' }; return value }
    async function bootstrap() {
        const who = identify(), token = ++generation; if (!who) return false
        loading.value = true; error.value = ''
        try { const response: any = await api.cooperationBootstrap(); if (!current(who) || token !== generation) return false; const data = response.data; if (!data || typeof data !== 'object' || !data.config) throw new Error('协作配置未完整返回，请核对配套后端'); const viewer = Number(data.current_member_id); memberId.value = Number.isSafeInteger(viewer) && viewer > 0 ? viewer : 0; profile.value = data.profile || {}; pendingEntity.value = data.pending_entity || {}; onboardingInviter.value = data.onboarding_inviter && typeof data.onboarding_inviter === 'object' ? data.onboarding_inviter : {}; config.value = data.config; providers.value = Array.isArray(data.providers) ? data.providers : data.providers?.items || []; stats.value = data.stats || {}; wallet.value = data.wallet || {}; ready.value = true; return true }
        catch (cause) { if (current(who) && token === generation) error.value = api.coopError(cause, '协作工作台读取失败，请重试'); return false }
        finally { if (token === generation) loading.value = false }
    }
    async function loadLeads(append = false) {
        const who = identity, token = ++listGeneration, page = append ? query.page + 1 : 1, selectedDirection = direction.value, signature = listSignature()
        if (!current(who) || !profile.value.id) return false
        interruptedListRead = null; pendingListRead = { who, append, signature }
        listLoading.value = true; listError.value = ''
        try { const response: any = await api.cooperationLeads({ ...query, direction: selectedDirection, page }); if (!current(who) || token !== listGeneration || direction.value !== selectedDirection || signature !== listSignature()) return false; const data = response.data || {}; if (!Array.isArray(data.data)) throw new Error('线索列表格式异常，请重试'); rows.value = append ? [...rows.value, ...data.data.filter((row: CoopRow) => !rows.value.some(item => Number(item.id) === Number(row.id)))] : data.data; total.value = Number(data.total || 0); query.page = page; return true }
        catch (cause) { if (current(who) && token === listGeneration && signature === listSignature()) listError.value = api.coopError(cause, '线索读取失败'); return false }
        finally { if (token === listGeneration) { listLoading.value = false; pendingListRead = null } }
    }
    async function loadDetail(id: number) {
        if (!Number.isSafeInteger(id) || id <= 0) { detailError.value = '工单链接无效，请从列表打开'; return false }
        const who = identity, token = ++detailGeneration; detailLoading.value = true; detailError.value = ''; detail.value = {}
        interruptedDetailRead = null; pendingDetailRead = { who, id }
        try { const response: any = await api.cooperationLead(id); if (!current(who) || token !== detailGeneration) return false; const data = response.data || {}; if (Number(data.id) !== id) throw new Error('未返回有效工单，可能已无权访问'); detail.value = data; return true }
        catch (cause) { if (current(who) && token === detailGeneration) detailError.value = api.coopError(cause, '工单读取失败'); return false }
        finally { if (token === detailGeneration) { detailLoading.value = false; pendingDetailRead = null } }
    }
    async function loadWallet(append = false) { if (profile.value.permissions?.can_withdraw === false) { wallet.value = {}; walletError.value = ''; return false }; if (walletLoading.value) return false; const who = identity, token = ++walletGeneration, page = append ? walletPage.value + 1 : 1; if (!current(who) || !profile.value.id) return false; walletLoading.value = true; walletError.value = ''; try { const response: any = await api.cooperationWallet({ page, limit: 20 }); if (!current(who) || token !== walletGeneration) return false; const next = response.data || {}; if (append) for (const key of ['entries']) { const previous = wallet.value[key]?.data || [], incoming = next[key]?.data || []; next[key] = { ...(next[key] || {}), data: [...previous, ...incoming.filter((row: CoopRow) => !previous.some((item: CoopRow) => Number(item.id) === Number(row.id)))] } }; wallet.value = next; walletPage.value = page; return true } catch (cause) { if (current(who) && token === walletGeneration) walletError.value = api.coopError(cause, '收益读取失败'); return false } finally { if (token === walletGeneration) walletLoading.value = false } }
    async function write(work: () => Promise<any>) { if (busy.value || !current(identity)) return null; const who = identity; busy.value = true; writeError.value = ''; try { const response: any = await work(); if (!current(who)) return null; return response.data || {} } catch (cause) { if (current(who)) writeError.value = api.coopError(cause, '结果尚未确认，请保留内容并刷新核对，勿重复提交'); return null } finally { busy.value = false } }
    const saveProfile = (data: CoopRow) => write(() => api.saveCooperationProfile(data, !!profile.value.id))
    const createLead = (data: CoopRow) => write(() => api.createCooperationLead(data))
    const assign = (id: number, merchantId: number, version: number, assigneeMemberId = 0, replace = false) => write(() => api.assignCooperationLead(id, { merchant_id: merchantId, assignment_version: version, ...(replace ? { replace: true } : {}), ...(Number.isSafeInteger(assigneeMemberId) && assigneeMemberId > 0 ? { assignee_member_id: assigneeMemberId } : {}) }))
    const action = (type: LeadAction) => { const snapshot = { ...detail.value }; return write(() => api.actCooperationLead(Number(snapshot.id), type, Number(snapshot.assignment_version), '')) }
    const order = (data: CoopRow) => write(() => api.createCooperationOrder(data))
    async function changeDirection(value: Direction) { if (direction.value === value || busy.value) return; direction.value = value; query.status = ''; query.keyword = ''; rows.value = []; total.value = 0; await loadLeads() }
    async function refresh() { const success = await bootstrap(); if (success && profile.value.id) { if (tab.value === 'wallet') await loadWallet(); else if (tab.value === 'deliveries') await loadLeads() }; return success }
    async function resumeInterruptedReads() {
        const list = interruptedListRead, task = interruptedDetailRead
        interruptedListRead = null; interruptedDetailRead = null
        const reads: Promise<boolean>[] = []
        if (list && current(list.who) && list.signature === listSignature()) reads.push(loadLeads(list.append))
        if (task && current(task.who)) reads.push(loadDetail(task.id))
        await Promise.all(reads)
    }
    function resume() { active = true; disposed = false; return identify() }
    function pause() { if (listLoading.value && pendingListRead) interruptedListRead = pendingListRead; if (detailLoading.value && pendingDetailRead) interruptedDetailRead = pendingDetailRead; pendingListRead = null; pendingDetailRead = null; active = false; generation++; listGeneration++; detailGeneration++; walletGeneration++; loading.value = false; listLoading.value = false; detailLoading.value = false; walletLoading.value = false }
    function dispose() { pause(); disposed = true }
    return { loggedIn, ready, loading, error, profile, memberId, siteId, sessionScope, config, providers, stats, wallet, pendingEntity, onboardingInviter, tab, direction, query, rows, total, listLoading, listError, walletLoading, walletError, detail, detailLoading, detailError, busy, writeError, canLoadMore, walletCanLoadMore, bootstrap, loadLeads, loadDetail, loadWallet, saveProfile, createLead, assign, action, order, changeDirection, refresh, resume, pause, dispose, resumeInterruptedReads }
}
