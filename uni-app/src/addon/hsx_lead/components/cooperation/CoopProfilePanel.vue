<template>
    <view class="coop-profile-theme" :class="{ 'is-diy': displayOnly, 'is-page-panel': page===true && !displayOnly }" :style="displayStyle">
        <view v-if="!editing && profile?.id && (hasSection('identity') || hasSection('tools'))" class="coop-profile-card coop-profile-card--identity">
            <template v-if="hasSection('identity')">
                <view class="coop-profile-title">

                    <CoopMerchantAvatar :image="profile.storefront_image" :name="profile.name" :active="profile.membership?.active===true" :rank="membershipBadgeRank(profile.membership)" :appearance="profile.membership?.level_visual" :highlighted="membershipBadgeRank(profile.membership)>=3" />
                    <view class="coop-profile-identity">
                        <view class="coop-profile-name-line"><view class="coop-profile-name">{{ profile.name }}</view><view v-if="shown('showGrade')" class="coop-profile-name-badge"><CoopLevelBadge :level="membershipBadgeRank(profile.membership)" :label="profile.membership?.level_name||'普通会员'" :appearance="profile.membership?.level_visual" :state="membershipState(profile.membership||{})" variant="inline" size="small" /></view></view>
                        <view v-if="shown('showRegion') && profile.region_text" class="coop-profile-region">{{ profile.region_text }}</view>
                        <view v-if="profile.blocked_at" class="coop-profile-badges"><text class="is-blocked">已停止合作</text></view>
                    </view>
                    <button v-if="canEdit" class="coop-profile-edit" :aria-label="text('buttonText','门店资料')" @tap="open"><u-icon name="edit-pen" size="19" :color="iconColor" /></button>
                </view>
                <view v-if="profile.cooperation_stats" class="coop-profile-stats"><button @tap="$emit('outgoing')"><text class="coop-profile-stats__count">{{ statCount('outgoing') }}</text><text>{{ text('outgoingText','找门店') }}</text></button><button @tap="$emit('incoming')"><text class="coop-profile-stats__count">{{ statCount('incoming') }}</text><text>{{ text('incomingText','找客户') }}</text></button><button @tap="$emit('members')"><text class="coop-profile-stats__count">{{ profile.membership?.used_accounts ?? '—' }}</text><text>{{ text('secondaryText','门店成员') }}</text></button></view>
            </template>
            <template v-if="hasSection('tools')">
                <view v-if="hasConfiguredTools" class="coop-profile-tools" :class="{ 'is-list': appearance?.layout === 'list' }"><button v-for="tool in tools" :key="tool.key" class="coop-profile-tool" hover-class="coop-profile-pressed" @tap="toolAction(tool.key)"><image v-if="tool.image" class="coop-profile-tool__image" :src="tool.image" mode="aspectFit" /><CoopBusinessIcon v-else :name="tool.icon" size="small" :color="iconColor" /><view><text class="coop-profile-tool__label">{{ tool.label }}</text><text v-if="tool.hint" class="coop-profile-tool__hint">{{ tool.hint }}</text></view></button></view>
                <button v-if="shown('showNotice')" class="coop-profile-notice" hover-class="coop-profile-pressed" @tap="$emit('subscribe')"><image v-if="noticeImage" class="coop-profile-tool__image" :src="noticeImage" mode="aspectFit" /><view v-else class="coop-profile-notice__icon"><u-icon name="bell" size="21" :color="iconColor" /></view><text>{{ text('noticeText','工单消息提醒') }}</text><u-icon name="arrow-right" size="12" :color="subtleColor" /></button>
                <TouchFeedbackPreference v-if="shown('showFeedback')" :label="text('feedbackText','轻触反馈')" />
            </template>
        </view>
        <view v-if="!editing && profile?.id && hasSection('promotion')" class="coop-profile-card coop-profile-promotion"><button class="coop-profile-promotion__main" hover-class="coop-profile-pressed" @tap="$emit('promotion','overview')"><image v-if="toolPicture('entryImage')" class="coop-profile-tool__image" :src="toolPicture('entryImage')" mode="aspectFit" /><CoopBusinessIcon v-else name="invite" :color="iconColor" /><view class="coop-profile-promotion__copy"><text class="coop-profile-promotion__title">{{ text('buttonText','推广中心') }}</text><text v-if="appearance.subtitle" class="coop-profile-promotion__hint">{{ appearance.subtitle }}</text></view><u-icon name="arrow-right" size="12" :color="subtleColor" /></button><view class="coop-profile-promotion__links"><button v-if="shown('showInviter')" hover-class="coop-profile-pressed" @tap="$emit('promotion','upstream')"><u-icon name="account" size="17" :color="iconColor" /><text>邀请关系</text><u-icon name="arrow-right" size="11" :color="subtleColor" /></button><button hover-class="coop-profile-pressed" @tap="$emit('promotion','application')"><u-icon name="man-add" size="17" :color="iconColor" /><text>{{ text('secondaryText','服务商申请') }}</text><u-icon name="arrow-right" size="11" :color="subtleColor" /></button></view></view>
        <view v-if="!editing && profile?.blocked_at && !displayOnly" class="coop-task-rule">合作已停用：{{ profile.block_reason || '请联系平台核对' }}。历史记录和已形成收益仍保留。</view>
        <CoopMembershipEntry v-if="!editing && profile?.id && hasSection('benefits') && shown('showMembership')" :profile="profile" :appearance="appearance" :embedded="displayOnly" @open="$emit('membership')" />
        <CoopTaskSheet v-if="!displayOnly && editing && !canEdit" :page="page" :show="true" title="门店资料" full @close="close"><view class="coop-form-section"><view class="coop-section-title">资料由门店负责人管理</view><view class="coop-muted">你可以处理负责人授权的协作业务，不能修改门店资质、付款或 ERP 授权。</view></view></CoopTaskSheet>
        <CoopTaskSheet v-if="!displayOnly && canEdit" :page="page" :show="editing || visible || onboarding" :inactive="regionOpen" :title="onboarding ? '完善门店资料' : '门店资料'" :busy="locked" :scroll-into-view="scrollTarget" full @close="close">
            <view class="coop-form-section">
                <view v-if="onboarding" class="coop-onboarding-join" @tap="joinExisting"><text>已有门店？使用主体码加入</text><u-icon name="arrow-right" size="13" color="#6e89ac" /></view>
                <view class="coop-section-title">门店信息</view>
                <view id="coop-profile-name" class="coop-form-column"><text>门店名称</text><u-input v-model="form.name" :custom-style="cooperationInputStyle" maxlength="120" placeholder="门店对外名称" :disabled="locked" /><view v-if="fieldError('name')" class="coop-profile-field-error" role="alert">{{ fieldError('name') }}</view></view>
                <view class="coop-profile-contact-row"><view id="coop-profile-contact" class="coop-form-column"><text>联系人</text><u-input v-model="form.contact_name" :custom-style="cooperationInputStyle" maxlength="60" placeholder="联系人姓名" :disabled="locked" /><view v-if="fieldError('contact')" class="coop-profile-field-error" role="alert">{{ fieldError('contact') }}</view></view><view id="coop-profile-mobile" class="coop-form-column"><text>联系电话</text><u-input v-model="form.mobile" :custom-style="cooperationInputStyle" type="number" maxlength="20" placeholder="门店联系电话" :disabled="locked" /><view v-if="fieldError('mobile')" class="coop-profile-field-error" role="alert">{{ fieldError('mobile') }}</view></view></view>
                <view id="coop-profile-region" class="coop-form-column"><text>门店所在地区</text><CoopRegionFields v-model="region" :min-level="3" :disabled="locked" @overlay="regionOpen = $event" /><view v-if="fieldError('region')" class="coop-profile-field-error" role="alert">{{ fieldError('region') }}</view></view>
                <view id="coop-profile-address" class="coop-form-column"><text>详细地址</text><u-textarea v-model="form.address" :height="64" :auto-height="true" maxlength="500" placeholder="街道、门牌和楼层" :disabled="locked" /><view v-if="fieldError('address')" class="coop-profile-field-error" role="alert">{{ fieldError('address') }}</view></view>
            </view>
            <view class="coop-form-section"><view class="coop-section-title">照片与资质</view><CoopStorefrontUpload v-model="form.storefront_image" :disabled="locked" @pending="uploading = $event" /><CoopBusinessLicenseUpload v-if="editing || visible || onboarding" v-model="form.business_license_token" :disabled="locked" @pending="licenseUploading = $event" /></view>
            <view id="coop-profile-capabilities" class="coop-form-section">
                <view class="coop-form-column"><text>经营业务与交付能力（可多选）</text><view class="coop-capability-help">不同业务分别登记。未选择的业务或空能力不会视为全部能力。</view><view v-for="business in businesses" :key="business.key" class="coop-business-capability"><view class="coop-business-capability__heading" @tap="toggleBusiness(business.key)"><CoopSelectionMark :selected="selectedBusinesses.includes(business.key)" /><text>{{ business.name }}</text></view><view v-if="selectedBusinesses.includes(business.key)" class="coop-business-capability__selection" @tap="openModelCatalog(business.key)"><text>{{ (capabilityChoices[business.key] || []).length ? '已选 ' + capabilityChoices[business.key].length + ' 项' : '选择可交付' + businessDimensionLabel(business) }}</text><u-icon name="arrow-right" size="13" color="#8092aa" /></view></view><view v-if="!businesses.length" class="coop-capability-help">平台尚未开放业务能力，请联系平台配置。</view></view>
                <view v-if="disabledCapabilities.length" class="coop-form-column"><text>已停用业务（只读保留）</text><view v-for="capability in disabledCapabilities" :key="capability.business_type" class="coop-disabled-capability"><view><text>{{ capability.business_name }}</text><text>已停用 · 已登记 {{ capability.option_ids.length }} 项</text></view><u-icon name="lock" size="16" color="#8b97a8" /></view><view class="coop-capability-help">已有能力按原记录保留，不影响修改门店资料。业务停用期间不能新增或扩大能力。</view></view>
                <view v-if="fieldError('capabilities')" class="coop-profile-field-error" role="alert">{{ fieldError('capabilities') }}</view>
            </view>
            <view class="coop-form-section coop-profile-erp"><u-collapse :border="false"><u-collapse-item title="ERP 库存同步" :label="profile.connection?.connected ? (profile.connection.provider_name || profile.provider_requested_name || form.provider) + ' · ' + connectionLabel : '选填'" name="erp-settings" icon="grid" :disabled="locked"><view class="coop-form-column"><template v-if="profile.connection?.connected"><view class="coop-connected-source"><view class="coop-connection-source"><text>{{ profile.connection.provider_name || profile.provider_requested_name || form.provider }}</text><text class="coop-connection-state" :class="{ 'is-paused': profile.connection.channel_enabled === false || !profile.connection.enabled }">{{ profile.connection.channel_enabled === false ? '渠道已关闭' : profile.connection.enabled ? '自动同步开启' : '自动同步暂停' }}</text></view><text role="button" aria-label="管理ERP连接" @tap="manageConnection">管理连接 ›</text></view></template><template v-else><picker :range="providerOptions" range-key="name" :value="providerIndex" :disabled="locked" @change="selectProvider(Number($event.detail.value))"><view class="coop-picker-field">{{ providerOptions[providerIndex]?.name || '暂不连接 ERP' }} ›</view></picker><view v-if="providerOptions[providerIndex]?.disabled" class="coop-muted">原渠道已关闭，原库存和接入资料仍保留。</view></template></view><view class="coop-profile-help-body"><view>登记的是可交付能力，不代表当前有现货。</view><view>手工型号与 ERP 库存参考可以同时使用。保存门店资料不会切换现有 ERP 账号。</view><view>需要暂停自动同步或更换账号时，进入“管理连接”操作。</view></view></u-collapse-item></u-collapse></view>
            <view v-if="onboarding" class="coop-profile-promotion-entry" role="button" :aria-disabled="locked" @tap="openPromotion"><u-icon name="share" size="18" color="#587eaf" /><text>推广中心</text><u-icon name="arrow-right" size="13" color="#8b9ab0" /></view>
            <template #footer><view v-if="localError || error" class="coop-inline-error coop-submit-error" role="alert" aria-live="polite">{{ localError || error }}</view><u-button type="primary" shape="circle" :custom-style="cooperationButtonStyle" :text="licenseUploading ? '营业执照上传中' : uploading ? '门头图片上传中' : '保存门店资料'" :loading="locked" :disabled="locked" @click="submit" /></template>
        </CoopTaskSheet>
    </view>
</template>
<script setup lang="ts">
import CoopBusinessIcon from './CoopBusinessIcon.vue'
import CoopMembershipEntry from './CoopMembershipEntry.vue'
import CoopLevelBadge from './CoopLevelBadge.vue'
import TouchFeedbackPreference from './TouchFeedbackPreference.vue'
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopRegionFields from './CoopRegionFields.vue'
import CoopMerchantAvatar from './CoopMerchantAvatar.vue'
import CoopStorefrontUpload from './CoopStorefrontUpload.vue'
import CoopBusinessLicenseUpload from './CoopBusinessLicenseUpload.vue'
import CoopSelectionMark from './CoopSelectionMark.vue'
import { enabledBusinesses, businessDimensionLabel } from '../../utils/cooperation-business'
import { openCooperationCatalog } from '../../utils/cooperation-navigation'
import { entityPageUrl, membershipBadgeRank, membershipState } from '../../utils/entity-membership'
import { cooperationButtonStyle, cooperationInputStyle } from '../../utils/mobile-ui'
import { cooperationDiyVars } from '../../utils/cooperation-diy-style'
import { img } from '@/utils/common'
const props = withDefaults(defineProps<{ profile: CoopRow; page?: boolean; editing?: boolean; config: CoopRow; providers: CoopRow[]; onboarding?: boolean; busy?: boolean; error?: string; displayOnly?: boolean; section?: string; appearance?: CoopRow }>(), { onboarding: false, busy: false, error: '', displayOnly: false, section: 'all', appearance: () => ({}) })
const emit = defineEmits<{ (event: 'submit', value: CoopRow): void; (event: 'connection' | 'subscribe' | 'inventory' | 'edit' | 'close' | 'membership' | 'members' | 'outgoing' | 'incoming'): void; (event: 'promotion', value: string): void; (event: 'overlay', value: boolean): void }>()
const hasSection = (key: string) => !props.section || props.section === 'all' || props.section === key
const canEdit = computed(() => !props.profile.id || props.profile.permissions?.can_manage_billing !== false)
const shown = (key: string) => ![false, 0, '0'].includes(props.appearance?.[key]) && (!['showProfile', 'showCapability'].includes(key) || canEdit.value) && (key !== 'showConnection' || props.profile.permissions?.can_manage_erp !== false)
// Do not silently remove ordinary entries from an already-saved layout. New
// templates use GraphicNav and have none of these old presentation switches.
const hasConfiguredTools = computed(() => tools.value.length > 0)
const displayStyle = computed(() => cooperationDiyVars(props.appearance))
const iconColor = computed(() => 'var(--lead-diy-icon, #333333)'), subtleColor = computed(() => 'var(--lead-diy-subtle, #909399)')
const text = (key:string,fallback:string) => props.appearance[key] === undefined ? fallback : String(props.appearance[key] || '')
const customTool = (key:string) => !props.displayOnly || (Object.prototype.hasOwnProperty.call(props.appearance,key) ? shown(key) : ['showProfile','showCapability','showInventory','showConnection'].includes(key))
const toolPicture = (key:string) => typeof props.appearance[key] === 'string' && props.appearance[key].trim() ? img(props.appearance[key].trim()) : ''
const noticeImage = computed(() => toolPicture('noticeImage') || toolPicture('entryImage'))
const tools = computed(() => [
    { key:'profile',flag:'showProfile',icon:'store',label:text('profileText','门店资料'),hint:'',image:toolPicture('profileImage') },
    { key:'capability',flag:'showCapability',icon:'order',label:text('capabilityText','交付能力'),hint:'',image:toolPicture('capabilityImage') },
    { key:'inventory',flag:'showInventory',icon:'stock',label:text('inventoryText','库存快照'),hint:typeof props.profile.inventory_count === 'number' ? props.profile.inventory_count+' 条参考' : '',image:toolPicture('inventoryImage') },
    { key:'connection',flag:'showConnection',icon:'connection',label:text('connectionText','ERP 同步'),hint:connectionLabel.value,image:toolPicture('connectionImage') },
    { key:'members',flag:'showMembers',icon:'team',label:text('membersText','门店成员'),hint:'',image:toolPicture('membersImage') },
].filter(tool => customTool(tool.flag) && shown(tool.flag)))
function statCount(key:string) { const raw=props.profile.cooperation_stats?.[key],count=typeof raw==='number'||typeof raw==='string'&&raw!==''?Number(raw):NaN;return Number.isSafeInteger(count) && count>=0 ? count : '—' }
function toolAction(key:string) { if (locked.value) return; if (key==='profile'||key==='capability') open();else if (key==='members') emit('members');else if (key==='inventory') emit('inventory');else if (key==='connection') emit('connection') }
const defaults = () => ({ name: '', contact_name: '', mobile: '', province_id: 0, city_id: 0, district_id: 0, address: '', provider: '', storefront_image: '', business_license_token: '', manual_models: [] as CoopRow[] })
const businesses = computed(() => enabledBusinesses(props.config)), selectedBusinesses = ref<string[]>([]), capabilityChoices = reactive<Record<string, CoopRow[]>>({}), regionOpen = ref(false)
const disabledCapabilities = computed(() => (Array.isArray(props.profile.business_capabilities) ? props.profile.business_capabilities : []).filter((entry: CoopRow) => !businesses.value.some(business => business.key === entry.business_type)).map((entry: CoopRow) => ({ business_type: String(entry.business_type), business_name: entry.business_name || props.config.businesses?.find((business: CoopRow) => business.key === entry.business_type)?.name || entry.business_type, option_ids: (Array.isArray(entry.option_ids) ? entry.option_ids : (entry.items || []).map((item: CoopRow) => item.id)).map(String) })))
const visible = ref(false), localError = ref(''), uploading = ref(false), form = reactive<CoopRow>(defaults())
const licenseUploading = ref(false)
const errorField = ref(''), scrollTarget = ref('')
const locked = computed(() => props.busy || uploading.value || licenseUploading.value)
const region = computed({ get: () => form, set: value => Object.assign(form, value) }), providerOptions = computed(() => {
    const items: CoopRow[] = [{ key: '', name: '暂不连接 ERP，手动选择交付型号' }, ...props.providers]
    const current = props.profile.provider_requested || props.profile.connection?.provider || ''
    if (props.profile.id && current && !props.providers.some(item => item.key === current)) items.push({ key: current, name: (props.profile.connection?.provider_name || props.profile.provider_name || current) + '（已关闭，保留原选择）', disabled: true })
    return items
}), providerIndex = computed(() => Math.max(0, providerOptions.value.findIndex(item => item.key === form.provider)))
const connectionLabel = computed(() => props.profile.connection?.channel_enabled === false ? '渠道关闭·同步暂停' : props.profile.connection?.connected && !props.profile.connection?.enabled ? '同步已暂停' : props.profile.connection?.last_sync_status === 'completed' ? '已同步' : props.profile.connection?.enabled ? '查看进度' : '选填，不影响协作')
function selectProvider(index: number) { const item = providerOptions.value[index]; if (!item) return; if (item.disabled && item.key !== form.provider) { localError.value = '该渠道已关闭，只能保留原选择或改用开放渠道'; return }; form.provider = item.key; localError.value = '' }

const draftKey = () => `hsx_lead_profile_draft:${uni.getStorageSync('wap_site_id') || 'current'}:${uni.getStorageSync('wap_member_id') || props.profile.member_id || 0}`
let draftActive = false
function fill() {
    Object.assign(form, defaults(), props.profile, { provider: props.profile.provider_requested || props.profile.connection?.provider || '', storefront_image: typeof props.profile.storefront_image === 'string' ? props.profile.storefront_image : '' })
    const capabilities = Array.isArray(props.profile.business_capabilities) ? props.profile.business_capabilities : []
    selectedBusinesses.value = capabilities.map((item: CoopRow) => String(item.business_type)).filter((key: string) => businesses.value.some(business => business.key === key))
    for (const item of capabilities) capabilityChoices[String(item.business_type)] = (item.items || []).map((option: CoopRow) => ({ ...option }))
    const draft = uni.getStorageSync(draftKey())
    if (draft && typeof draft === 'object') { Object.assign(form, draft.form || {}); selectedBusinesses.value = Array.isArray(draft.selectedBusinesses) ? draft.selectedBusinesses.filter((key: string) => businesses.value.some(business => business.key === key)) : selectedBusinesses.value; Object.assign(capabilityChoices, draft.capabilityChoices || {}) }
    localError.value = ''; errorField.value = ''; scrollTarget.value = ''; draftActive = true
}
function toggleBusiness(key: string) { if (locked.value || !businesses.value.some(business => business.key === key)) return; selectedBusinesses.value = selectedBusinesses.value.includes(key) ? selectedBusinesses.value.filter(value => value !== key) : [...selectedBusinesses.value, key] }
function openModelCatalog(key: string) { if (locked.value) return; const business = businesses.value.find(item => item.key === key); if (!business) return; openCooperationCatalog({ title: '选择' + business.name + '交付' + businessDimensionLabel(business), business_type: key, max: 0, selection_mode: 'multiple', allow_bulk: true, items: capabilityChoices[key] || [] }, items => { capabilityChoices[key] = items; localError.value = '' }) }
function open() { if (!locked.value && canEdit.value) emit('edit') }
function close() { if (locked.value) return; if (props.editing || props.page) { emit('close'); return }; visible.value = false; emit('overlay', false) }
function manageConnection() { if (!locked.value) emit('connection') }
function openPromotion() { if (!locked.value) emit('promotion', 'overview') }
function joinExisting() { if (!locked.value) uni.navigateTo({ url: entityPageUrl + '?mode=join' }) }
function clearDraft() { draftActive = false; uni.removeStorageSync(draftKey()) }
function fieldError(key: string) { return errorField.value === key ? localError.value : '' }
function clearField(key: string) { if (errorField.value === key) { errorField.value = ''; localError.value = ''; scrollTarget.value = '' } }
function invalidField(key: string, message: string) { errorField.value = key; localError.value = message; scrollTarget.value = ''; void nextTick(() => { if (errorField.value === key) scrollTarget.value = 'coop-profile-' + key }) }
function submit() {
    if (locked.value || !canEdit.value) return
    localError.value = ''; errorField.value = ''; scrollTarget.value = ''
    if (!form.name.trim()) { invalidField('name', '请填写门店名称与联系人'); return }
    if (!form.contact_name.trim()) { invalidField('contact', '请填写门店名称与联系人'); return }
    if (!/^1\d{10}$/.test(String(form.mobile))) { invalidField('mobile', '请填写有效联系电话'); return }
    if (!form.district_id) { invalidField('region', '请选择门店省市区并填写详细地址'); return }
    if (!String(form.address || '').trim()) { invalidField('address', '请选择门店省市区并填写详细地址'); return }
    const capabilities = disabledCapabilities.value.map(entry => ({ business_type: entry.business_type, option_ids: [...entry.option_ids] }))
    for (const key of selectedBusinesses.value) {
        const business = businesses.value.find(item => item.key === key)
        if (!business) continue // Closed businesses use only the persisted read-only snapshot above.
        const items = capabilityChoices[key] || []
        if (!items.length) { invalidField('capabilities', '请选择' + business.name + '的具体交付能力，或取消该业务'); return }
        if (items.length > 5000) { invalidField('capabilities', business.name + '的交付能力超过目录技术上限，请缩小选择'); return }
        capabilities.push({ business_type: key, option_ids: items.map(item => String(item.id)) })
    }
    emit('submit', { ...Object.fromEntries(['name', 'contact_name', 'mobile', 'province_id', 'city_id', 'district_id', 'address', 'provider', 'storefront_image', 'business_license_token'].map(key => [key, form[key]])), business_capabilities: capabilities })
}
watch(() => props.editing || props.onboarding, value => { if (value && !draftActive) fill() }, { immediate: true })
watch(() => form.name, () => clearField('name'))
watch(() => form.contact_name, () => clearField('contact'))
watch(() => form.mobile, () => clearField('mobile'))
watch(() => form.address, () => clearField('address'))
watch(() => [form.province_id, form.city_id, form.district_id], () => clearField('region'))
watch([selectedBusinesses, capabilityChoices], () => clearField('capabilities'), { deep: true })
watch([form, selectedBusinesses, capabilityChoices], () => { if (draftActive) uni.setStorageSync(draftKey(), { form: { ...form }, selectedBusinesses: selectedBusinesses.value, capabilityChoices: { ...capabilityChoices } }) }, { deep: true })
defineExpose({ open, close, clearDraft })
</script>
<style scoped lang="scss">
.coop-profile-theme .coop-profile-title { display:flex;align-items:center;gap:12px;padding:0; }
.coop-profile-theme .coop-profile-identity { flex:1;min-width:0; }
.coop-profile-name-line { display:flex;align-items:center;gap:6px;min-width:0; }
.coop-profile-theme .coop-profile-name-line .coop-profile-name { flex:0 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.coop-profile-name-badge { flex:none;display:flex;align-items:center;min-width:0;max-width:50%;overflow:hidden; }
.coop-profile-theme .coop-profile-name { font-size:var(--lead-diy-title-size,18px);font-weight:650;line-height:1.5;color:var(--lead-diy-title,#202124);overflow-wrap:anywhere; }
.coop-profile-theme .coop-profile-region { margin-top:3px;font-size:12px;line-height:1.6;color:var(--lead-diy-subtle,#909399);overflow-wrap:anywhere; }
.coop-profile-theme .coop-profile-badges { display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin:8px 0 0; }
.coop-profile-theme .coop-profile-edit { display:flex;align-items:center;justify-content:center;flex:none;min-width:44px;height:44px;margin:-6px -8px 0 0;padding:0;background:transparent; }
.coop-profile-theme .coop-profile-stats { display:flex;align-items:stretch;gap:8px;margin-top:20px;padding-bottom:2px; }
.coop-profile-theme .coop-profile-stats button { flex:1;min-width:0;display:flex;align-items:center;flex-direction:column;gap:4px;margin:0;padding:4px;background:transparent;font-size:12px;line-height:20px;color:var(--lead-diy-text,#606266); }
.coop-profile-theme .coop-profile-stats .coop-profile-stats__count { font-size:21px;line-height:28px;font-weight:650;color:var(--lead-diy-title,#202124); }
.coop-profile-theme .coop-profile-tools { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--lead-diy-row-gap,12px);margin:0; }
.coop-profile-theme .coop-profile-tool { min-width:0;display:flex;align-items:center;flex-direction:column;justify-content:flex-start;gap:8px;margin:0;padding:8px 0;min-height:76px;background:transparent;line-height:1.5;text-align:center;color:var(--lead-diy-text,#606266); }
.coop-profile-theme .coop-profile-tool>view { min-width:0; }
.coop-profile-theme .coop-profile-tool__image { width:32px;height:32px;flex:none; }
.coop-profile-theme .coop-profile-tool__label { display:block;font-size:12px;line-height:20px;color:var(--lead-diy-text,#606266);overflow-wrap:anywhere; }
.coop-profile-theme .coop-profile-tool__hint { display:block;margin-top:2px;font-size:11px;line-height:16px;color:var(--lead-diy-subtle,#909399);overflow-wrap:anywhere; }
.coop-profile-theme .coop-profile-tools.is-list { grid-template-columns:minmax(0,1fr);gap:0; }
.coop-profile-theme .coop-profile-tools.is-list .coop-profile-tool { min-height:52px;flex-direction:row;justify-content:flex-start;text-align:left;padding:8px 0;gap:12px; }
.coop-profile-theme .coop-profile-tools.is-list .coop-profile-tool__label { font-size:var(--lead-diy-text-size,14px); }
.coop-profile-theme .coop-profile-notice { display:flex;align-items:center;gap:12px;min-height:48px;width:100%;margin:0;padding:2px 0;background:transparent;line-height:22px;text-align:left;color:var(--lead-diy-text,#606266); }
.coop-profile-theme .coop-profile-notice>text { flex:1;min-width:0;font-size:var(--lead-diy-text-size,14px);overflow-wrap:anywhere; }
.coop-profile-theme .coop-profile-notice__icon { width:32px;display:flex;align-items:center;justify-content:center;flex:none; }
.coop-profile-theme .coop-profile-promotion__main { display:flex;align-items:center;gap:12px;min-height:48px;width:100%;margin:0;padding:0;background:transparent;line-height:1.5;text-align:left;color:var(--lead-diy-title,#202124); }
.coop-profile-theme .coop-profile-promotion { min-width:0; }
.coop-profile-theme .coop-profile-promotion__copy { flex:1;min-width:0; }
.coop-profile-theme .coop-profile-promotion__title { display:block;font-size:var(--lead-diy-text-size,14px);font-weight:600;color:var(--lead-diy-title,#202124);overflow-wrap:anywhere; }
.coop-profile-theme .coop-profile-promotion__hint { display:block;margin-top:3px;font-size:12px;color:var(--lead-diy-subtle,#909399);overflow-wrap:anywhere; }
.coop-profile-theme .coop-profile-promotion__links { display:flex;gap:20px;margin-top:12px;padding-top:10px;border-top:1px solid #f0f1f2; }
.coop-profile-theme .coop-profile-promotion__links button { flex:1;min-width:0;display:flex;align-items:center;gap:8px;min-height:44px;margin:0;padding:0;background:transparent;text-align:left;color:var(--lead-diy-text,#606266);font-size:12px;line-height:20px; }
.coop-profile-theme .coop-profile-promotion__links text { flex:1;min-width:0;overflow-wrap:anywhere; }
.coop-profile-theme .coop-profile-card button::after { border:0; }
.coop-profile-pressed { opacity:.6; }
</style>
<style scoped lang="scss">
.coop-profile-theme { min-width:0;color:var(--lead-diy-text,#606266); }
.coop-profile-theme.is-page-panel { height:100%;max-height:100%;min-height:0;flex:1 1 0;display:flex;flex-direction:column;overflow:hidden;box-sizing:border-box; }
.coop-profile-theme .coop-profile-name,.coop-profile-theme .coop-section-title { color:var(--lead-diy-title,#202124); }
.coop-profile-theme.is-diy .coop-profile-card,.coop-profile-theme.is-diy .coop-form-section { margin:0;border:0;border-radius:0;background:var(--lead-diy-panel,transparent);padding:var(--lead-diy-content-padding,16px); }
.coop-profile-theme.is-diy .coop-form-section+.coop-form-section { border-top:1px solid #f0f1f2; }
.coop-profile-theme.is-diy .coop-muted { color:var(--lead-diy-text,#606266); }
</style>
<style scoped>.coop-disabled-capability { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid #edf1f6; }.coop-disabled-capability>view { display: flex; flex-direction: column; gap: 5px; min-width: 0; }.coop-disabled-capability text:first-child { font-size: 14px; color: #62758d; }.coop-disabled-capability text:last-child { font-size: 12px; color: #8a97a8; }</style>
<style scoped lang="scss">
@import './cooperation-shared';
@include hsx-coop-form-section;
@include hsx-coop-section-title;
@include hsx-coop-muted;
@include hsx-coop-task-rule;
@include hsx-coop-form-column;
@include hsx-coop-picker-field;
@include hsx-coop-inline-error;
.coop-profile-card { background: #fff; padding: 30rpx; margin-bottom: 18rpx; }
.coop-submit-error { padding: 0 0 16rpx; }
.coop-profile-contact-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); gap: 22rpx; }
.coop-profile-contact-row>.coop-form-column { min-width: 0; }
.coop-profile-field-error { color: #b3594d; font-size: 12px; line-height: 20px; margin-top: 7px; }
.coop-onboarding-join { display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:44px;background:#f0f6fe;color:#6683a9;border-radius:7px;padding:0 12px;margin-bottom:18px;font-size:13px; }
.coop-profile-erp { padding: 4px 12px; }
.coop-profile-promotion-entry { display: flex; align-items: center; gap: 7px; min-height: 44px; margin: 12px; padding: 0 14px; border-radius: 8px; background: #edf4ff; color: #577aa9; font-size: 13px; }.coop-profile-promotion-entry>text { flex: 1; min-width: 0; }
.coop-profile-title { display: flex; gap: 22rpx; align-items: center; }
.coop-profile-name { font-size: 33rpx; font-weight: 650; line-height: 1.7; }
.coop-profile-badges { display: flex; gap: 13rpx; flex-wrap: wrap; margin: 24rpx 0; }
.coop-profile-badges>text { color: #6d819c; background: #edf1f8; border-radius: 6rpx; padding: 6rpx 12rpx; font-size: 22rpx; }
.coop-profile-badges>text.is-reliable { color: #886c3a; background: #f8f1e0; }
.coop-profile-badges>text.is-blocked { color: #ad6c5b; background: #fff0eb; }
@media (max-width: 360px) { .coop-profile-contact-row { grid-template-columns: minmax(0,1fr); gap: 0; } }
.coop-profile-identity { flex: 1; min-width: 0; }.coop-profile-identity .coop-muted { margin-top: 7rpx; }.coop-profile-identity .coop-profile-badges { margin: 12rpx 0 0; }
.coop-connected-source { display: flex; justify-content: space-between; align-items: center; gap: 18rpx; font-size: 26rpx; color: #5d7189; background: #edf3fa; padding: 20rpx; border-radius: 8rpx; }.coop-connected-source>text:last-child { flex: none; color: var(--primary-color, #d93833); font-size: 23rpx; }
.coop-profile-card--identity { padding: 28rpx 24rpx 0; }.coop-profile-title { padding: 10rpx 6rpx 26rpx; }.coop-profile-badges>text { padding: 4rpx 11rpx; }.coop-profile-tools { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 16rpx; margin: 0 0 8rpx; }.coop-form-section { border-radius: 12rpx; margin: 20rpx 24rpx; border: 1rpx solid #e8edf4; }.coop-profile-name { color: #26364d; }.coop-profile-identity .coop-muted { color: #8492a5; }
.coop-connection-source { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 5px; }.coop-connection-source>text:first-child { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; font-weight: 550; color: #3e587b; }.coop-connection-state { font-size: 11px; color: #668678; line-height: 18px; }.coop-connection-state.is-paused { color: #a18458; }.coop-connected-source { gap: 12px; padding: 13px 14px; border-radius: 8px; background: #f1f6fd; }.coop-connected-source>text:last-child { min-height: 44px; display: flex; align-items: center; font-size: 12px; }.coop-profile-help { border-top: 1px solid #edf1f6; margin-top: 14px; }.coop-profile-help :deep(.u-cell__body) { padding: 11px 0; }.coop-profile-help :deep(.u-cell__title-text) { color: #7b8da5; font-size: 12px; }.coop-profile-help :deep(.u-collapse-item__content__text) { padding: 0; }.coop-profile-help-body { color: #7b8ba1; font-size: 12px; line-height: 1.8; padding: 0 0 10px; }.coop-profile-help-body>view+view { margin-top: 7px; }</style>

<style scoped>.coop-capability-help { font-size: 12px; line-height: 1.8; color: #7b8ba1; margin: 8px 0 12px; }.coop-business-capability { border-bottom: 1px solid #edf1f6; padding: 10px 0; }.coop-business-capability__heading { display: flex; align-items: center; gap: 10px; min-height: 44px; font-size: 14px; color: #344a65; }.coop-business-capability__selection { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 44px; margin-left: 30px; color: var(--primary-color,#3478cc); font-size: 13px; }</style>
