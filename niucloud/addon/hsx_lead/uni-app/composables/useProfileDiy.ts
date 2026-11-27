import { computed, inject, ref, type InjectionKey } from 'vue'
import { getSiteId, getToken, redirect } from '@/utils/common'
import { getMemberInfo } from '@/app/api/member'
import { useLogin } from '@/hooks/useLogin'
import { useCooperationSubscribe } from './useCooperationSubscribe'
import useDiyStore from '@/app/stores/diy'
import { useCooperation } from './useCooperation'
import { cooperationPageUrls, type CoopRow } from '../api/cooperation'
import { openCooperationTask } from '../utils/cooperation-navigation'
import { entityPageUrl, membershipPageUrl } from '../utils/entity-membership'

/** Page-owned read context shared by all DIY sections. No private data crosses iframe boundaries. */
export function createProfileDiyContext() {
    const store = useDiyStore(), ui = useCooperation('profile'), owner = ref('')
    const member = ref<CoopRow>({}), memberLoading = ref(false), memberError = ref('')
    const preview = computed(() => store.mode === 'decorate')
    const subscribeState = useCooperationSubscribe({ enabled: () => !preview.value })
    const identity = () => `${String(getSiteId(uni.getStorageSync('wap_site_id') || '') || '')}:${String(getToken() || '')}`
    const active = ref(true)
    let disposed = false, pending: Promise<boolean> | null = null, sequence = 0
    const current = () => { const captured = owner.value; return active.value && !disposed && !!getToken() && captured === identity() }
    const ready = computed(() => current() && ui.loggedIn.value && ui.ready.value && !ui.error.value && !memberError.value && !!member.value.member_id)
    const profile = computed(() => ready.value ? ui.profile.value : {})
    const config = computed(() => ready.value ? ui.config.value : {})
    const sampleProfile = { id: -1, name: '示例门店', region_text: '示例省 / 示例市 / 示例区', storefront_image: '', business_capabilities: [{ business_type: 'purchase' }], inventory_count: 0, membership: { active:false, level_name:'普通会员', display_rank:0 } }
    const sampleConfig = { enabled: true, publishing_required: true, businesses: [] }
    const sampleMember = { member_id:-1, member_no:'NIU0000021', nickname:'示例会员', headimg:'', balance:'0.00', money:'0.00' }
    function clear() { member.value = {}; memberError.value = ''; memberLoading.value = false; ui.profile.value = {}; ui.pendingEntity.value = {}; ui.config.value = {}; ui.providers.value = []; ui.wallet.value = {}; ui.stats.value = {}; ui.ready.value = false; ui.error.value = '' }
    function login(params: CoopRow = {}) { if (preview.value) return; useLogin().setLoginBack({ url: cooperationPageUrls.profile, param: params, mode: 'redirectTo' }) }
    async function refresh() {
        if (disposed || preview.value) return false
        active.value = true
        const nextOwner = identity()
        if (owner.value !== nextOwner) { sequence++; pending = null; ui.pause(); clear(); owner.value = nextOwner }
        ui.resume()
        if (!getToken()) { clear(); return false }
        void subscribeState.prepare()
        if (pending) return pending
        const turn = ++sequence, captured = owner.value
        memberLoading.value = true; memberError.value = ''
        const memberRead = getMemberInfo().then((response: any) => {
            if (turn !== sequence || captured !== owner.value || !current()) return false
            if (!Number.isSafeInteger(Number(response?.data?.member_id)) || Number(response.data.member_id) <= 0) throw new Error('会员信息未完整返回，请重新加载')
            member.value = response.data; return true
        }).catch(() => { if (turn === sequence && captured === owner.value && current()) memberError.value = '会员信息读取失败，请重新加载'; return false }).finally(() => { if (turn === sequence) memberLoading.value = false })
        const work = Promise.all([ui.bootstrap(), memberRead]).then(results => results.every(Boolean) && turn === sequence && captured === owner.value && current()).finally(() => { if (pending === work) pending = null })
        pending = work
        return work
    }
    function action(key: string, value = '') {
        if (preview.value || disposed || !active.value) return
        if (!getToken() || owner.value !== identity()) { clear(); login(); return }
        if (!ready.value) { void refresh(); return }
        if (['inventory','connection'].includes(key) && config.value.erp_visible === false) { uni.showToast({ title:'该功能暂未开放', icon:'none' }); return }
        if (key === 'settings' || key === 'balance') { redirect({ url:key === 'settings' ? '/app/pages/setting/index' : '/app/pages/member/balance' }); return }
        if (key === 'profile') { if (profile.value.permissions?.can_manage_billing === false) { uni.showToast({ title: '门店资料由负责人管理', icon: 'none' }); return }; openCooperationTask('profile-edit'); return }
        if (key === 'promotion') { openCooperationTask('promotion', { tab: value || 'overview' }); return }
        if (key === 'outgoing' || key === 'incoming') { uni.navigateTo({ url: key === 'outgoing' ? cooperationPageUrls.stores : cooperationPageUrls.deliveries }); return }
        if (key === 'members') { uni.navigateTo({ url: entityPageUrl }); return }
        if (key === 'wallet') { uni.navigateTo({ url: cooperationPageUrls.wallet }); return }
        if (!profile.value.id) { openCooperationTask('profile-edit'); return }
        if (key === 'membership') uni.navigateTo({ url: membershipPageUrl })
        else if (key === 'inventory' || key === 'connection') { if (key === 'connection' && profile.value.permissions?.can_manage_erp === false) { uni.showToast({ title: 'ERP 授权由负责人管理', icon: 'none' }); return }; openCooperationTask(key) }
        else if (key === 'subscribe') {
            void subscribeState.request()
        }
    }
    function pause() { active.value = false; sequence++; pending = null; memberLoading.value = false; ui.pause() }
    function dispose() { pause(); disposed = true }
    return { preview, ready, profile, config, pendingEntity: ui.pendingEntity, loading: computed(() => ui.loading.value || memberLoading.value), error: computed(() => ui.error.value || memberError.value), loggedIn: ui.loggedIn, action, login, refresh, pause, dispose,
        displayMember: computed<CoopRow>(() => preview.value ? sampleMember : ready.value ? member.value : {}),
        displayProfile: computed<CoopRow>(() => preview.value ? { ...sampleProfile, cooperation_stats:{outgoing:0,incoming:0} } : ready.value ? { ...profile.value, cooperation_stats:ui.stats.value, cooperation_account:ui.wallet.value } : {}),
        displayConfig: computed<CoopRow>(() => preview.value ? sampleConfig : config.value),
        displayable: computed(() => preview.value || ready.value) }
}
export type ProfileDiyContext = ReturnType<typeof createProfileDiyContext>
export const profileDiyKey: InjectionKey<ProfileDiyContext> = Symbol('hsx-lead-profile-diy')
export function useProfileDiyContext() { return inject(profileDiyKey, null) }
