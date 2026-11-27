import { computed, ref } from 'vue'
import { getSiteId, getToken } from '@/utils/common'
import { coopError, type CoopRow } from '../api/cooperation'
import * as api from '../api/promotion'

/** All relationships, qualifications and money are server-owned; no local reward ledger. */
export function usePromotion() {
    const overview = ref<CoopRow>({}), ready = ref(false), loading = ref(false), error = ref(''), saving = ref(false)
    const team = ref<CoopRow[]>([]), teamTotal = ref(0), teamPage = ref(0), teamLoading = ref(false), teamError = ref(''), teamKeyword = ref('')
    const teamLevel = ref<1 | 2>(1)
    const rewards = ref<CoopRow[]>([]), rewardTotal = ref(0), rewardPage = ref(0), rewardLoading = ref(false), rewardError = ref(''), rewardStatus = ref('')
    const invitation = ref<CoopRow>({}), invitationLoading = ref(false), invitationError = ref('')
    const application = ref<CoopRow>({}), applicationReady = ref(false), applicationLoading = ref(false), applicationError = ref(''), applicationUncertain = ref(false)
    const confirmedUpstream = ref<CoopRow | null>(null)
    let active = false, identity = '', overviewSeq = 0, teamSeq = 0, rewardSeq = 0, invitationSeq = 0, applicationSeq = 0, teamScope = ''
    const identityNow = () => getToken() ? `${getSiteId(uni.getStorageSync('wap_site_id') || '')}:${getToken()}` : ''
    const current = (who: string) => active && !!who && who === identity && who === identityNow()
    const teamMore = computed(() => team.value.length < teamTotal.value), rewardMore = computed(() => rewards.value.length < rewardTotal.value)
    function resume() {
        active = true
        const who = identityNow()
        if (who !== identity) {
            stop(); active = true; identity = who
            overview.value = {}; ready.value = false; team.value = []; rewards.value = []; invitation.value = {}
            teamTotal.value = 0; rewardTotal.value = 0; teamPage.value = 0; rewardPage.value = 0
            teamLevel.value = 1; teamKeyword.value = ''; rewardStatus.value = ''
            teamScope = ''
            error.value = ''; teamError.value = ''; rewardError.value = ''; invitationError.value = ''
            confirmedUpstream.value = null
            application.value = {}; applicationReady.value = false; applicationError.value = ''; applicationUncertain.value = false
        }
        if (!who) error.value = '请先使用本站会员身份登录'
        return !!who
    }
    function stop() { active = false; overviewSeq++; teamSeq++; rewardSeq++; invitationSeq++; applicationSeq++; loading.value = false; teamLoading.value = false; rewardLoading.value = false; invitationLoading.value = false; applicationLoading.value = false }
    async function reload() {
        if (!resume()) return false
        const who = identity, token = ++overviewSeq; loading.value = true; error.value = ''
        try {
            const response: any = await api.promotionOverview()
            if (!current(who) || token !== overviewSeq) return false
            if (!response?.data?.member?.member_id || !response.data.rules || !response.data.account) throw new Error('推广资料未完整返回，请核对配套后端')
            overview.value = response.data; ready.value = true
            if (validApplication(response.data.application)) { application.value = response.data.application; applicationReady.value = true }
            return true
        } catch (cause) { if (current(who) && token === overviewSeq) error.value = coopError(cause, '推广中心读取失败，请重试'); return false }
        finally { if (token === overviewSeq) loading.value = false }
    }
    async function loadTeam(append = false) {
        if (!current(identity) || append && teamLoading.value) return false
        const who = identity, token = ++teamSeq, keyword = teamKeyword.value, level = teamLevel.value, page = append ? teamPage.value + 1 : 1
        const scope = `${level}:${keyword}`
        if (!append && scope !== teamScope) { team.value = []; teamTotal.value = 0; teamPage.value = 0 }
        teamScope = scope
        teamLoading.value = true; teamError.value = ''
        try {
            const response: any = await api.promotionTeam({ keyword, commission_level: level, page, limit: 20 }), data = response?.data
            if (!current(who) || token !== teamSeq || keyword !== teamKeyword.value || level !== teamLevel.value) return false
            if (!Array.isArray(data?.data)) throw new Error('邀请记录格式异常，请重试')
            team.value = append ? merge(team.value, data.data, 'member_id') : data.data; teamTotal.value = Number(data.total || 0); teamPage.value = page; return true
        } catch (cause) { if (current(who) && token === teamSeq) teamError.value = coopError(cause, '邀请记录读取失败'); return false }
        finally { if (token === teamSeq) teamLoading.value = false }
    }
    async function loadRewards(append = false) {
        if (!current(identity) || rewardLoading.value) return false
        const who = identity, token = ++rewardSeq, status = rewardStatus.value, page = append ? rewardPage.value + 1 : 1
        rewardLoading.value = true; rewardError.value = ''
        try {
            const response: any = await api.promotionRewards({ status, page, limit: 20 }), data = response?.data
            if (!current(who) || token !== rewardSeq || status !== rewardStatus.value) return false
            if (!Array.isArray(data?.data)) throw new Error('推广奖励列表格式异常，请重试')
            rewards.value = append ? merge(rewards.value, data.data, 'id') : data.data; rewardTotal.value = Number(data.total || 0); rewardPage.value = page; return true
        } catch (cause) { if (current(who) && token === rewardSeq) rewardError.value = coopError(cause, '推广奖励读取失败'); return false }
        finally { if (token === rewardSeq) rewardLoading.value = false }
    }
    async function inspectInvitation(mid: number) {
        if (!resume()) return false
        const who = identity, token = ++invitationSeq; invitation.value = {}; invitationLoading.value = true; invitationError.value = ''
        if (!Number.isSafeInteger(mid) || mid <= 0) { invitationLoading.value = false; invitationError.value = '邀请链接无效，请向邀请人重新获取'; return false }
        try {
            const response: any = await api.promotionInvitation(mid)
            if (!current(who) || token !== invitationSeq) return false
            if (typeof response?.data?.can_bind !== 'boolean') throw new Error('邀请关系未完整返回，请刷新核对')
            invitation.value = response.data; return true
        } catch (cause) { if (current(who) && token === invitationSeq) invitationError.value = coopError(cause, '邀请信息读取失败'); return false }
        finally { if (token === invitationSeq) invitationLoading.value = false }
    }
    async function bind(mid: number) {
        if (saving.value || !current(identity) || !invitation.value.can_bind || confirmedUpstream.value) return false
        const who = identity; saving.value = true; invitationError.value = ''
        try {
            const response: any = await api.bindPromotionInviter(mid)
            if (!current(who)) return false
            if (Number(response?.data?.upstream?.member_id) !== mid) throw new Error('绑定响应未返回匹配的上级，请重新读取原关系核对，不要重复提交')
            // The write is confirmed separately from later read refreshes. Never invent success from a GET failure.
            confirmedUpstream.value = { ...response.data.upstream }
            const invitationRead = await inspectInvitation(mid), overviewRead = await reload()
            if (!current(who)) return false
            if (!invitationRead || !overviewRead) {
                invitationError.value = '邀请关系已保存，但资料刷新未完成，请重新读取关系核对，不要重复绑定'
                return false
            }
            if (Number(invitation.value.current_upstream?.member_id) !== mid || Number(overview.value.upstream?.member_id) !== mid) {
                invitationError.value = '绑定返回与最新邀请关系不一致，请联系平台核对，不会自动覆盖或再次绑定'
                return false
            }
            return true
        } catch (cause) { if (current(who)) invitationError.value = coopError(cause, '绑定结果尚未确认，请重新读取关系，勿重复提交'); return false }
        finally { saving.value = false }
    }
    async function readApplication() {
        if (!resume()) return false
        const who = identity, token = ++applicationSeq; applicationLoading.value = true; applicationError.value = ''
        try {
            const response: any = await api.promotionApplication()
            if (!current(who) || token !== applicationSeq) return false
            if (!validApplication(response?.data?.application)) throw new Error('申请状态未完整返回，请重新核对')
            application.value = response.data.application; applicationReady.value = true; applicationUncertain.value = false
            return true
        } catch (cause) { if (current(who) && token === applicationSeq) applicationError.value = coopError(cause, '申请状态读取失败，请重试'); return false }
        finally { if (token === applicationSeq) applicationLoading.value = false }
    }
    async function applyProvider() {
        if (saving.value || applicationLoading.value || !current(identity)) return false
        // A lost write response must be checked with a GET before another POST.
        if (applicationUncertain.value) { await readApplication(); return false }
        if (!applicationReady.value || application.value.can_apply !== true || ['pending', 'approved', 'disabled'].includes(application.value.status)) return false
        const who = identity, token = ++applicationSeq; saving.value = true; applicationError.value = ''
        try {
            const response: any = await api.submitPromotionApplication()
            if (!current(who) || token !== applicationSeq) return false
            if (!validApplication(response?.data?.application) || !['pending', 'approved'].includes(response.data.application.status)) throw new Error('提交结果尚未确认')
            application.value = response.data.application; applicationReady.value = true; applicationUncertain.value = false
            await reload()
            return current(who)
        } catch (cause) {
            if (!current(who) || token !== applicationSeq) return false
            applicationUncertain.value = true
            const checked = await readApplication()
            if (!current(who)) return false
            if (checked && ['pending', 'approved'].includes(application.value.status)) { await reload(); return current(who) }
            applicationError.value = checked ? coopError(cause, '申请未提交，请核对后重试') : '提交结果尚未确认，请先核对申请状态，不要重复提交'
            return false
        } finally { saving.value = false }
    }
    return { overview, ready, loading, error, saving, team, teamTotal, teamLoading, teamError, teamKeyword, teamLevel, teamMore, rewards, rewardTotal, rewardLoading, rewardError, rewardStatus, rewardMore, invitation, invitationLoading, invitationError, confirmedUpstream, application, applicationReady, applicationLoading, applicationError, applicationUncertain, resume, stop, reload, loadTeam, loadRewards, inspectInvitation, bind, readApplication, applyProvider }
}
function merge(previous: CoopRow[], incoming: CoopRow[], key: string) { return [...previous, ...incoming.filter(row => !previous.some(item => String(item[key]) === String(row[key])))] }
function validApplication(value: any) { return value && ['none', 'pending', 'approved', 'rejected', 'disabled'].includes(value.status) && typeof value.can_apply === 'boolean' && Number.isSafeInteger(Number(value.version)) && Number(value.version) >= 0 }
