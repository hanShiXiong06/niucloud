import { computed, onUnmounted, ref, watch } from 'vue'
import { purchaseRouteId, purchaseMoney } from './purchase-form'

export const PAYMENT_EXPERIMENT_WARNING = '我确认：仅在测试账套同步；博远应付与核销尚未核实。'
export function paymentSelection(value: any = {}) { return { creator_id: String(value.creator_id || ''), account_id: String(value.account_id || '') } }
export function paymentAccountInScope(item: any, source: any): boolean {
    const owner = String(item?.extra?.company_id || ''), companyCode = String(source?.company_code || ''), accountCode = String(item?.extra?.gsdm || '')
    if (owner) return owner === String(source?.company_id || '') && (!companyCode || !accountCode || accountCode === companyCode)
    return !!companyCode && accountCode === companyCode
}
export function paymentApLabel(evidence: any): string {
    if (evidence?.verified === true) return '应付形成已有独立证据'
    if (evidence?.status === 'rule_confirmed' || evidence?.formation_expected === true) return '形成规则已确认，实单余额和指定核销未核验（不代表应付为0或不存在）'
    return '未核验（不代表应付为0或不存在）'
}
export function paymentRegistrationDone(view: any): boolean {
    return view?.task?.status === 'registered_review' && view.operator_complete !== false && view?.result?.payment_registered === true && !!view.task.external_no
}
export function paymentResultMessage(view: any): string {
    if (paymentRegistrationDone(view)) return '已同步到博远，本笔无需再操作'
    return ({ awaiting_confirmation: 'ERP 已记账，待同步到博远', pending: '已排队，正在等博远回执', processing: '正在同步，请勿重复提交', attention: '尚未收到可靠回执，请到博远核对原付款单，不要重新同步', registered_review: '回执信息不完整，请核对原付款单，不要重新同步' } as Record<string, string>)[view?.task?.status] || '未取得付款状态，请刷新查看'
}
export function paymentConfirmation(view: any, selected: any, labels: any, experiment = false): { valid: boolean, text: string } {
    const source = view?.source || {}, amount = String(source.amount || '')
    const positive = /^[0-9]+(?:\.[0-9]{1,2})?$/.test(amount) && /[1-9]/.test(amount)
    const valid = source.scope === 'single_purchase_batch' && positive && source.currency === 'CNY' && !!source.company_id && !!source.supplier_id && !!source.external_purchase_no && !!source.settlement_no
        && !!labels?.company?.name && String(labels.company.id) === String(source.company_id)
        && !!labels?.supplier?.name && String(labels.supplier.id) === String(source.supplier_id)
        && !!labels?.creator?.name && String(labels.creator.id) === String(selected.creator_id)
        && !!labels?.account?.name && String(labels.account.id) === String(selected.account_id)
    return { valid, text: ['同步金额：' + purchaseMoney(source.amount) + '（不是银行转账）', '博远公司：' + (labels?.company?.name || '未取得') + '（仅此公司）', '供应商：' + (labels?.supplier?.name || '未取得'), '记账账户：' + (labels?.account?.name || '未选择') + '　制单人：' + (labels?.creator?.name || '未选择'), '博远采购单：' + (source.external_purchase_no || '未取得'), 'ERP 付款号：' + (source.settlement_no || '未取得'), ...(experiment ? ['仅测试登记，应付与核销尚未核实。'] : [])].join('\n') }
}

// API is injected so the same guarded workflow runs in PC and mobile components.
export function usePurchasePayment(props: any, api: any, notify: (message: string) => void, changed: () => void) {
    const visible = ref(false), taskView = ref<any>(null), candidateMode = ref(false), busy = ref(false), confirming = ref(false), loading = ref(false), error = ref('')
    const selection = ref(paymentSelection()), labels = ref<any>({}), experimentAck = ref(false), uncertain = ref(false)
    const candidates = ref<any[]>([]), candidateMore = ref(false), candidatePage = ref(1), candidateTotal = ref(0)
    const lookup = ref<'creator_id' | 'account_id' | ''>(''), keyword = ref(''), choices = ref<any[]>([]), cursor = ref(''), choiceLoading = ref(false), lookupError = ref('')
    const summary = computed(() => props.purchase?.payment || null)
    const scopedDetail = computed(() => props.purchase?.active_connection === true && props.purchase?.task?.mode === 'test' && summary.value?.uat_context === true)
    const currentKey = computed(() => scopedDetail.value ? props.purchase.task.connection_key : props.config?.profiles?.test?.connection_key)
    const uatContext = computed(() => scopedDetail.value || props.config?.mode === 'test' && props.purchase?.task?.mode === 'test' && summary.value?.uat_only === true && props.config?.profiles?.test?.protocol === 'openapi_v153' && props.config.profiles.test.endpoint === 'https://open-api-uat.139erp.com/api' && props.config.profiles.test.gateway_path === '/gateway.do')
    const detailsOpen = ref(false), taskReadFailed = ref(false)
    const hasPayment = computed(() => (summary.value?.candidates?.items || []).some((row: any) => row.eligible === true))
    const enabled = computed(() => scopedDetail.value ? summary.value?.enabled === true && props.purchase?.write_enabled === true : uatContext.value && props.config?.uat_payment_enabled === true && summary.value?.enabled === true && props.config.enabled === true && props.config.purchase_enabled === true && props.config.paused === false && !!props.config.profiles.test.probe_at && props.config.profiles.test.contract_confirmed === true)
    const locked = computed(() => busy.value || confirming.value)
    const connectionMatches = computed(() => { const parentId = purchaseRouteId(taskView.value?.parent_purchase_task_id || taskView.value?.task?.parent_purchase_task_id || taskView.value?.source?.source_task_id); return parentId > 0 && parentId === purchaseRouteId(props.purchase?.task?.id) && taskView.value?.task?.mode === 'test' && taskView.value.task.connection_key === currentKey.value })
    const registrationDone = computed(() => paymentRegistrationDone(taskView.value))
    const editable = computed(() => !taskReadFailed.value && taskView.value?.task?.status === 'awaiting_confirmation' && enabled.value && connectionMatches.value && props.purchase?.can_operate !== false && taskView.value?.editable === true && taskView.value?.can_operate !== false && !uncertain.value)
    const isUnknown = computed(() => uncertain.value || taskView.value?.task?.status === 'attention' || taskView.value?.task?.status === 'registered_review' && !registrationDone.value)
    const resultMessage = computed(() => uncertain.value ? '未收到回执，请先到博远核对原付款单，不要重新同步' : paymentResultMessage(taskView.value))
    const canCapture = computed(() => enabled.value && summary.value?.can_capture === true && props.purchase?.task?.status === 'succeeded' && summary.value?.status !== 'permission_denied' && props.purchase?.task?.connection_key === currentKey.value && props.purchase?.can_operate !== false && !isUnknown.value)
    const entryIsNew = computed(() => canCapture.value && (!summary.value?.task_id || summary.value?.status === 'registered_review' && summary.value?.operator_complete === true))
    const entryLabel = computed(() => entryIsNew.value ? '同步付款记录' : summary.value?.status === 'registered_review' && summary.value?.operator_complete === true ? '查看回执' : ['pending','processing'].includes(summary.value?.status) ? '查看进度' : summary.value?.status === 'awaiting_confirmation' && canCapture.value ? '继续核对' : '查看原记录')
    const summaryMessage = computed(() => summary.value?.status === 'attention' || summary.value?.status === 'registered_review' && summary.value?.operator_complete !== true ? '回执待核对，不要重复同步。' : '')
    const canChooseAnother = computed(() => canCapture.value && (!taskView.value || editable.value || registrationDone.value))
    const facts = computed(() => paymentConfirmation(taskView.value, selection.value, labels.value))
    const needsExperiment = computed(() => taskView.value?.can_submit !== true && taskView.value?.can_submit_experiment === true)
    const saved = ref('')
    const selectionChanged = computed(() => JSON.stringify(selection.value) !== saved.value)
    const canSubmit = computed(() => editable.value && !loading.value && !selectionChanged.value && facts.value.valid && (taskView.value?.can_submit === true || needsExperiment.value && experimentAck.value))
    const saveFirst = computed(() => editable.value && (selectionChanged.value || (!taskView.value?.can_submit && !needsExperiment.value)))
    const apEvidence = computed(() => taskView.value?.ap_evidence || summary.value?.ap_evidence || { verified: false, status: 'unverified', reason: '应付形成尚未核验，不代表应付为0或不存在。' })
    const issues = computed<string[]>(() => (taskView.value?.issues || []).filter((issue: string) => issue && issue !== apEvidence.value.reason))
    let request = 0, referenceRequest = 0, lastRequestedId = 0
    const dirty = computed(() => editable.value && (selectionChanged.value || experimentAck.value))
    const message = (value: any) => String(value?.msg || value?.message || '读取或保存未完成，请核对当前任务状态').slice(0, 400)
    function apply(value: any) {
        const keepUncertain = uncertain.value && taskView.value?.task?.id === value.task?.id && value.task?.status === 'awaiting_confirmation'
        const previousSelection = selection.value, previousLabels = labels.value
        taskReadFailed.value = false
        if (value.task?.id !== taskView.value?.task?.id || value.task?.status !== taskView.value?.task?.status) detailsOpen.value = false
        taskView.value = value; selection.value = keepUncertain ? previousSelection : paymentSelection(value.selection); labels.value = keepUncertain ? { ...(value.labels || {}), creator: previousLabels.creator, account: previousLabels.account } : value.labels || {}; experimentAck.value = false; saved.value = JSON.stringify(selection.value)
        if (value.task?.status !== 'awaiting_confirmation') uncertain.value = false
    }
    function version() { return { revision: taskView.value.task.revision, mode: taskView.value.task.mode, connection_key: taskView.value.task.connection_key } }
    async function loadTask(id: number, silent = false) {
        if (locked.value || (silent && (dirty.value || lookup.value || candidateMode.value))) return
        lastRequestedId = id
        const token = ++request
        if (!silent) { loading.value = true; if (taskView.value && purchaseRouteId(taskView.value.task?.id) !== id) { taskView.value = null; uncertain.value = false } }
        try {
            const result = await api.getPaymentTask(id)
            if (token !== request || !visible.value) return
            const previous = taskView.value?.task
            const parent = purchaseRouteId(result.data?.parent_purchase_task_id || result.data?.source?.source_task_id)
            if (purchaseRouteId(result.data?.task?.id) !== id || !parent || parent !== purchaseRouteId(props.purchase?.task?.id)) throw new Error('付款回执与当前采购不匹配，未打开记录')
            apply(result.data); error.value = ''
            if (previous?.id === id && ['pending', 'processing'].includes(previous.status) && !['pending', 'processing'].includes(result.data.task.status)) changed()
        }
        catch (err) { if (token === request) { taskReadFailed.value = true; error.value = message(err) } }
        finally { if (token === request) loading.value = false }
    }
    async function loadCandidates(append = false) {
        if (!canCapture.value || locked.value || (append && !candidateMore.value)) return
        const token = ++request, next = append ? candidatePage.value + 1 : 1
        loading.value = true; error.value = ''
        if (!append) { candidates.value = []; candidateMore.value = false }
        try {
            const result = await api.getPurchasePaymentCandidates(props.purchase.task.id, { page: next, limit: 20 })
            if (token !== request || !visible.value) return
            const data = result.data, previous = append ? candidates.value : []
            candidates.value = [...previous, ...(data.items || []).filter((item: any) => !previous.some(old => old.settlement_id === item.settlement_id))]
            candidatePage.value = next; candidateTotal.value = Number(data.total || 0); candidateMore.value = data.has_more === true; candidateMode.value = true
        } catch (err) { if (token === request) error.value = message(err) }
        finally { if (token === request) loading.value = false }
    }
    async function open(originalTaskId?: unknown) {
        visible.value = true; error.value = ''; lookup.value = ''; candidateMode.value = false; detailsOpen.value = false
        const id = purchaseRouteId(originalTaskId) || purchaseRouteId(summary.value?.task_id)
        if (!originalTaskId && entryIsNew.value) { candidateMode.value = true; await loadCandidates() }
        else if (id) await loadTask(id)
        else if (canCapture.value) await loadCandidates()
    }
    async function capture(item: any) {
        if (!visible.value || !canCapture.value || locked.value || loading.value || item.task_id || item.eligible !== true || !purchaseRouteId(item.settlement_id)) return
        busy.value = true; error.value = ''
        try {
            const parent = props.purchase.task
            const result = await api.capturePurchasePayment(parent.id, { revision: parent.revision, mode: parent.mode, connection_key: parent.connection_key, settlement_id: purchaseRouteId(item.settlement_id) })
            apply(result.data); candidateMode.value = false; changed()
        } catch (err) { error.value = message(err) }
        finally { busy.value = false }
    }
    async function openExisting(item: any) { const id = purchaseRouteId(item.task_id); if (!id || locked.value) return; candidateMode.value = false; await loadTask(id) }
    async function save() {
        if (!editable.value || locked.value || loading.value) return
        busy.value = true; error.value = ''
        try { const result = await api.savePaymentDraft(taskView.value.task.id, { ...version(), selection: paymentSelection(selection.value) }); apply(result.data); changed(); notify('草稿已保存，尚未同步') }
        catch (err) { error.value = message(err) }
        finally { busy.value = false }
    }
    async function submit(confirm: (text: string) => Promise<boolean>) {
        if (!canSubmit.value || locked.value) return
        confirming.value = true
        try {
            const experiment = needsExperiment.value
            const confirmation = paymentConfirmation(taskView.value, selection.value, labels.value, experiment)
            if (!confirmation.valid || !await confirm(confirmation.text)) return
            if (!canSubmit.value || busy.value) return
            busy.value = true; error.value = ''
            try {
                const result = await api.submitPaymentTask(taskView.value.task.id, { ...version(), selection: paymentSelection(selection.value), confirm_registration: true, ...(experiment ? { confirm_unverified_ap: true } : {}) })
                apply(result.data); changed()
            } catch (err) { uncertain.value = true; error.value = message(err) }
            finally { busy.value = false }
        } finally { confirming.value = false }
    }
    async function refresh() {
        if (dirty.value || locked.value) return
        if (candidateMode.value) await loadCandidates()
        else if (taskView.value) await loadTask(taskView.value.task.id)
        else if (lastRequestedId) await loadTask(lastRequestedId)
        else if (canCapture.value) await loadCandidates()
    }
    function openLookup(key: 'creator_id' | 'account_id') { if (!editable.value || locked.value) return; lookup.value = key; keyword.value = ''; choices.value = []; cursor.value = ''; loadChoices() }
    function exitLookup() { lookup.value = ''; referenceRequest++; choiceLoading.value = false }
    async function loadChoices(append = false) {
        if (!lookup.value || !editable.value || locked.value || (append && (choiceLoading.value || !cursor.value))) return
        const field = lookup.value, token = ++referenceRequest, currentCursor = append ? cursor.value : '1'
        choiceLoading.value = true; lookupError.value = ''
        if (!append) { choices.value = []; cursor.value = '' }
        try {
            const result = await api.getReferences({ resource: field === 'creator_id' ? 'staff' : 'capital_accounts', enabled_only: true, keyword: keyword.value.trim(), cursor: currentCursor, limit: 50 })
            if (token !== referenceRequest || lookup.value !== field || !visible.value || !connectionMatches.value) return
            if (result.data?.code && result.data.code !== 'OK') throw new Error('档案读取未完成')
            const data = result.data?.data || {}, previous = append ? choices.value : []
            const rows = (data.items || []).filter((item: any) => item.enabled && !item.stale && (field !== 'account_id' || paymentAccountInScope(item, taskView.value.source)))
            choices.value = [...previous, ...rows.filter((item: any) => !previous.some(old => String(old.id) === String(item.id)))]
            cursor.value = data.next_cursor && String(data.next_cursor) !== currentCursor ? String(data.next_cursor) : ''
        } catch (err) { if (token === referenceRequest) lookupError.value = message(err) }
        finally { if (token === referenceRequest) choiceLoading.value = false }
    }
    function choose(item: any) {
        if (!lookup.value || !editable.value || locked.value || !item.enabled || item.stale) return
        if (lookup.value === 'account_id' && !paymentAccountInScope(item, taskView.value.source)) { lookupError.value = '账户不属于原采购公司，或缺少明确公司归属，不能选择'; return }
        selection.value[lookup.value] = String(item.id); labels.value[lookup.value === 'creator_id' ? 'creator' : 'account'] = { id: String(item.id), name: item.name, code: item.code }; experimentAck.value = false; exitLookup()
    }
    async function close(confirm: () => Promise<boolean>) {
        if (locked.value) return
        if (lookup.value) return exitLookup()
        if (dirty.value && !await confirm()) return
        visible.value = false; request++; referenceRequest++
    }
    watch(() => props.purchase?.task?.id, () => { visible.value = false; taskView.value = null; uncertain.value = false; detailsOpen.value = false; taskReadFailed.value = false; lastRequestedId = 0; candidates.value = []; request++; referenceRequest++ })
    const timer = setInterval(() => { if (visible.value && !loading.value && ['pending', 'processing'].includes(taskView.value?.task?.status)) loadTask(taskView.value.task.id, true) }, 6000)
    onUnmounted(() => { clearInterval(timer); request++; referenceRequest++ })
    return { visible, taskView, candidateMode, busy, confirming, loading, error, selection, labels, experimentAck, uncertain, candidates, candidateMore, candidateTotal, lookup, keyword, choices, cursor, choiceLoading, lookupError, summary, summaryMessage, hasPayment, entryIsNew, entryLabel, detailsOpen, uatContext, enabled, locked, editable, isUnknown, registrationDone, resultMessage, canCapture, canChooseAnother, facts, needsExperiment, canSubmit, saveFirst, selectionChanged, issues, apEvidence, dirty, open, loadCandidates, capture, openExisting, save, submit, refresh, openLookup, exitLookup, loadChoices, choose, close, apply }
}
