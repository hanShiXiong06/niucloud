<template>
    <view>
        <view v-if="profile?.id" class="coop-profile-card coop-profile-card--identity">
            <view class="coop-profile-title"><CoopMerchantAvatar :image="profile.storefront_image" :name="profile.name" :reliable="profile.is_reliable" size="large" /><view class="coop-profile-identity"><view class="coop-profile-name">{{ profile.name }}</view><view class="coop-muted">{{ profile.region_text }}</view><view class="coop-profile-badges"><text>{{ profile.grade_name || '等级未设置' }}</text><text v-if="reliable" class="is-reliable">靠谱认证</text><text v-if="profile.blocked_at" class="is-blocked">已停止合作</text></view></view></view>
            <view class="coop-profile-tools"><view class="coop-menu-row" @tap="open"><view class="coop-profile-menu-label"><view class="coop-profile-tool-icon is-blue"><u-icon name="home" size="20" color="#557fc2" /></view><text>门店资料</text></view><u-icon name="arrow-right" size="14" color="#9aa2ad" /></view>
            <view class="coop-menu-row" @tap="open"><view class="coop-profile-menu-label"><view class="coop-profile-tool-icon is-violet"><u-icon name="list" size="20" color="#8a73ad" /></view><text>我的交付能力</text></view><text>{{ Number(profile.manual_model_count) > 0 ? profile.manual_model_count + ' 个自主登记型号' : profile.all_models ? '全型号' : '去完善' }} ›</text></view>
            <view class="coop-menu-row" @tap="$emit('inventory')"><view class="coop-profile-menu-label"><view class="coop-profile-tool-icon is-green"><u-icon name="bag" size="20" color="#5d917e" /></view><text>我的库存快照</text></view><text>{{ Number(profile.inventory_count || 0) }} 条参考 ›</text></view>
            <view class="coop-menu-row" @tap="$emit('connection')"><view class="coop-profile-menu-label"><view class="coop-profile-tool-icon is-gold"><u-icon name="grid" size="20" color="#b68a50" /></view><text>ERP 库存同步</text></view><text>{{ connectionLabel }} ›</text></view>
            </view><TouchFeedbackPreference /><view class="coop-menu-row" @tap="$emit('subscribe')"><view class="coop-profile-menu-label"><u-icon name="bell" size="21" color="#738aa4" /><text>接收工单通知</text></view><text>去订阅 ›</text></view>
        </view>
        <CoopPromotionEntryCard v-if="profile?.id" @open="$emit('promotion', $event)" /><view v-if="profile?.blocked_at" class="coop-task-rule">合作已停用：{{ profile.block_reason || '请联系平台核对' }}。历史记录和已形成收益仍保留。</view>
        <view v-if="profile?.id" class="coop-form-section"><view class="coop-section-title"><view class="coop-profile-menu-label"><u-icon name="order" size="20" color="#738aa4" /><text>发单权益</text></view></view><view class="coop-muted">{{ Number(profile.publisher_until) * 1000 > Date.now() ? '有效期至 ' + coopDate(profile.publisher_until) : config.publishing_required === false ? '本站当前不要求购买发单权益' : '未开通或已到期，购买后有效一个月' }}</view><view v-if="config.publishing_required" class="coop-profile-buy"><text>{{ Number(config.publishing_price) > 0 ? coopMoney(config.publishing_price) + ' / 月' : '价格尚未配置' }}</text><view class="coop-profile-purchase-action"><u-button size="small" type="primary" shape="circle" text="购买 / 续期" :disabled="busy || !!profile.blocked_at || Number(config.publishing_price) <= 0" @click="$emit('purchase', 'publishing')" /></view></view></view>
        <view v-if="profile?.id" class="coop-form-section"><view class="coop-section-title"><view class="coop-profile-menu-label"><u-icon name="checkmark-circle" size="20" color="#b58b3d" /><text>靠谱商家认证</text></view></view><view class="coop-muted">{{ reliable ? Number(profile.cert_expires_at || 0) === 0 ? '永久有效' : '有效期至 ' + coopDate(profile.cert_expires_at) : profile.certification_status === 'pending' ? '已支付申请费用，等待平台核验' : '申请后由平台核验，不代表现货或赔付担保' }}</view><view class="coop-profile-buy"><text>{{ Number(config.certification_price) > 0 ? coopMoney(config.certification_price) : '价格尚未配置' }}</text><view class="coop-profile-purchase-action"><u-button size="small" shape="circle" :text="permanentCertification ? '已永久认证' : profile.certification_status === 'pending' ? '等待核验' : reliable ? '续期认证' : '申请认证'" :disabled="busy || permanentCertification || !!profile.blocked_at || profile.certification_status === 'pending' || Number(config.certification_price) <= 0" @click="$emit('purchase', 'certification')" /></view></view></view>
        <CoopTaskSheet :show="(visible || onboarding) && !modelsOpen" :title="onboarding ? '完善门店资料' : '门店资料'" :busy="locked" full @close="close">
            <view v-if="onboarding" class="coop-profile-promotion-entry" role="button" @tap="$emit('promotion', 'overview')"><u-icon name="share" size="18" color="#587eaf" /><text>服务商推广中心</text><u-icon name="arrow-right" size="13" color="#8b9ab0" /></view>
            <view class="coop-form-section">
                <CoopStorefrontUpload v-model="form.storefront_image" :disabled="locked" @pending="uploading = $event" />
                <view class="coop-form-column"><text>门店名称</text><u-input v-model="form.name" maxlength="120" placeholder="门店对外名称" :disabled="locked" /></view>
                <view class="coop-form-column"><text>联系人</text><u-input v-model="form.contact_name" maxlength="60" placeholder="实际联系人姓名" :disabled="locked" /></view>
                <view class="coop-form-column"><text>联系电话</text><u-input v-model="form.mobile" type="number" maxlength="20" placeholder="门店联系电话" :disabled="locked" /></view>
                <view class="coop-form-column"><text>门店所在地区</text><CoopRegionFields v-model="region" :disabled="locked" /></view>
                <view class="coop-form-column"><text>详细地址</text><u-textarea v-model="form.address" maxlength="500" placeholder="街道、门牌和楼层" :disabled="locked" /></view>
                <CapabilitySummary :models="form.manual_models" :disabled="locked" @manage="openModelCatalog" />
                <view class="coop-form-column"><text>ERP 库存同步（选填）</text><template v-if="profile.connection?.connected"><view class="coop-connected-source"><view class="coop-connection-source"><text>{{ profile.connection.provider_name || profile.provider_requested_name || form.provider }}</text><text class="coop-connection-state" :class="{ 'is-paused': profile.connection.channel_enabled === false || !profile.connection.enabled }">{{ profile.connection.channel_enabled === false ? '渠道已关闭' : profile.connection.enabled ? '自动同步开启' : '自动同步暂停' }}</text></view><text role="button" aria-label="管理ERP连接" @tap="manageConnection">管理连接 ›</text></view></template><template v-else><picker :range="providerOptions" range-key="name" :value="providerIndex" :disabled="locked" @change="selectProvider(Number($event.detail.value))"><view class="coop-picker-field">{{ providerOptions[providerIndex]?.name || '暂不连接 ERP' }} ›</view></picker><view v-if="providerOptions[providerIndex]?.disabled" class="coop-muted">原渠道已关闭，原库存和接入资料仍保留。</view></template></view>
            <view class="coop-profile-help"><u-collapse :border="false"><u-collapse-item title="交付能力与 ERP 说明" name="capability-help" icon="info-circle"><view class="coop-profile-help-body"><view>登记的是可交付能力，不代表当前有现货。</view><view>手工型号与 ERP 库存参考可以同时使用。保存门店资料不会切换现有 ERP 账号。</view><view>需要暂停自动同步或更换账号时，进入“管理连接”操作。</view></view></u-collapse-item></u-collapse></view></view>
            <view v-if="localError || error" class="coop-inline-error coop-form-error">{{ localError || error }}</view>
            <template #footer><u-button type="primary" shape="circle" :text="uploading ? '门头图片上传中' : '保存门店资料'" :loading="locked" :disabled="locked" @click="submit" /></template>
        </CoopTaskSheet>
        <CoopModelSearchPicker v-model="form.manual_models" :show="modelsOpen" :initial-view="modelPickerView" title="选择可交付型号" subtitle="搜索品牌型号，多选你能为客户交付的产品" :max="0" @close="modelsOpen = false" />
    </view>
</template>
<script setup lang="ts">
import CoopPromotionEntryCard from './CoopPromotionEntryCard.vue'
import CapabilitySummary from './CapabilitySummary.vue'
import TouchFeedbackPreference from './TouchFeedbackPreference.vue'
import { computed, reactive, ref, watch } from 'vue'
import { coopDate, coopMoney, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopRegionFields from './CoopRegionFields.vue'
import CoopMerchantAvatar from './CoopMerchantAvatar.vue'
import CoopStorefrontUpload from './CoopStorefrontUpload.vue'
import CoopModelSearchPicker from './ModelCatalogPicker.vue'
const props = withDefaults(defineProps<{ profile: CoopRow; config: CoopRow; providers: CoopRow[]; onboarding?: boolean; busy?: boolean; error?: string }>(), { onboarding: false, busy: false, error: '' })
const emit = defineEmits<{ (event: 'submit', value: CoopRow): void; (event: 'connection' | 'subscribe' | 'inventory'): void; (event: 'purchase', purpose: string): void; (event: 'promotion', value: string): void; (event: 'overlay', value: boolean): void }>()
const defaults = () => ({ name: '', contact_name: '', mobile: '', province_id: 0, city_id: 0, district_id: 0, address: '', provider: '', storefront_image: '', manual_models: [] as CoopRow[] })
const modelPickerView = ref<'browse' | 'selected'>('browse')
const visible = ref(false), modelsOpen = ref(false), localError = ref(''), uploading = ref(false), form = reactive<CoopRow>(defaults())
const locked = computed(() => props.busy || uploading.value)
const reliable = computed(() => props.profile.is_reliable === true || props.profile.is_reliable === 1 || props.profile.is_reliable === '1')
const permanentCertification = computed(() => reliable.value && Number(props.profile.cert_expires_at || 0) === 0)
const region = computed({ get: () => form, set: value => Object.assign(form, value) }), providerOptions = computed(() => {
    const items: CoopRow[] = [{ key: '', name: '暂不连接 ERP，手动选择交付型号' }, ...props.providers]
    const current = props.profile.provider_requested || props.profile.connection?.provider || ''
    if (props.profile.id && current && !props.providers.some(item => item.key === current)) items.push({ key: current, name: (props.profile.connection?.provider_name || props.profile.provider_name || current) + '（已关闭，保留原选择）', disabled: true })
    return items
}), providerIndex = computed(() => Math.max(0, providerOptions.value.findIndex(item => item.key === form.provider)))
const connectionLabel = computed(() => props.profile.connection?.channel_enabled === false ? '渠道关闭·同步暂停' : props.profile.connection?.connected && !props.profile.connection?.enabled ? '同步已暂停' : props.profile.connection?.last_sync_status === 'completed' ? '已同步' : props.profile.connection?.enabled ? '查看进度' : '选填，不影响协作')
function selectProvider(index: number) { const item = providerOptions.value[index]; if (!item) return; if (item.disabled && item.key !== form.provider) { localError.value = '该渠道已关闭，只能保留原选择或改用开放渠道'; return }; form.provider = item.key; localError.value = '' }
function fill() { Object.assign(form, defaults(), props.profile, { manual_models: (props.profile.manual_models || []).map((item: CoopRow) => ({ ...item })), provider: props.profile.provider_requested || props.profile.connection?.provider || '', storefront_image: typeof props.profile.storefront_image === 'string' ? props.profile.storefront_image : '' }); localError.value = ''; modelsOpen.value = false }
function openModelCatalog() { if (locked.value) return; modelPickerView.value = form.manual_models.length ? 'selected' : 'browse'; modelsOpen.value = true }
function open() { if (locked.value) return; fill(); visible.value = true; emit('overlay', true) }
function close() { if (locked.value || props.onboarding) return; visible.value = false; emit('overlay', false) }
function manageConnection() { if (locked.value) return; visible.value = false; modelsOpen.value = false; emit('overlay', false); emit('connection') }
function submit() { if (locked.value) return; localError.value = ''; if (!form.name.trim() || !form.contact_name.trim()) { localError.value = '请填写门店名称与联系人'; return }; if (!/^1\d{10}$/.test(String(form.mobile))) { localError.value = '请填写有效联系电话'; return }; if (!form.district_id || !String(form.address || '').trim()) { localError.value = '请选择门店省市区并填写详细地址'; return }; emit('submit', Object.fromEntries(['name', 'contact_name', 'mobile', 'province_id', 'city_id', 'district_id', 'address', 'provider', 'storefront_image', 'manual_models'].map(key => [key, form[key]]))) }
watch(() => props.onboarding, value => { if (value) fill() }, { immediate: true })
watch(() => props.busy, (value, old) => { if (!value && old && !props.error && props.profile.id) { visible.value = false; emit('overlay', false) } })
defineExpose({ open, close })
</script>
<style scoped lang="scss">
@import './cooperation-shared';
@include hsx-coop-form-section;
@include hsx-coop-section-title;
@include hsx-coop-muted;
@include hsx-coop-task-rule;
@include hsx-coop-form-column;
@include hsx-coop-picker-field;
@include hsx-coop-form-error;
@include hsx-coop-menu-row;
.coop-profile-card { background: #fff; padding: 30rpx; margin-bottom: 18rpx; }
.coop-profile-promotion-entry { display: flex; align-items: center; gap: 14rpx; min-height: 88rpx; padding: 0 30rpx; background: #edf4ff; color: #577aa9; font-size: 25rpx; }.coop-profile-promotion-entry>text { flex: 1; min-width: 0; }
.coop-profile-title { display: flex; gap: 22rpx; align-items: center; }
.coop-profile-name { font-size: 33rpx; font-weight: 650; line-height: 1.7; }
.coop-profile-badges { display: flex; gap: 13rpx; flex-wrap: wrap; margin: 24rpx 0; }
.coop-profile-badges>text { color: #6d819c; background: #edf1f8; border-radius: 6rpx; padding: 6rpx 12rpx; font-size: 22rpx; }
.coop-profile-badges>text.is-reliable { color: #886c3a; background: #f8f1e0; }
.coop-profile-badges>text.is-blocked { color: #ad6c5b; background: #fff0eb; }
.coop-profile-buy { display: flex; align-items: center; justify-content: space-between; gap: 30rpx; margin-top: 25rpx; font-size: 26rpx; color: #7f8b99; }
.coop-profile-buy>text { flex: 1; min-width: 0; white-space: nowrap; }
.coop-profile-purchase-action { flex: none; width: 210rpx; max-width: 50%; }
.coop-profile-identity { flex: 1; min-width: 0; }.coop-profile-identity .coop-muted { margin-top: 7rpx; }.coop-profile-identity .coop-profile-badges { margin: 12rpx 0 0; }.coop-profile-menu-label { display: flex; align-items: center; gap: 16rpx; min-width: 0; }.coop-profile-menu-label>text { min-width: 0; }.coop-menu-row>.coop-profile-menu-label { font-size: 26rpx; color: #5e6a79; }.coop-menu-row { gap: 16rpx; }.coop-menu-row>text:last-child { white-space: nowrap; flex: none; }
.coop-connected-source { display: flex; justify-content: space-between; align-items: center; gap: 18rpx; font-size: 26rpx; color: #5d7189; background: #edf3fa; padding: 20rpx; border-radius: 8rpx; }.coop-connected-source>text:last-child { flex: none; color: var(--primary-color, #d93833); font-size: 23rpx; }
.coop-profile-card--identity { padding: 28rpx 24rpx 0; }.coop-profile-title { padding: 10rpx 6rpx 26rpx; }.coop-profile-badges>text { padding: 4rpx 11rpx; }.coop-profile-tools { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 16rpx; margin: 0 0 8rpx; }.coop-profile-tools .coop-menu-row { min-width: 0; min-height: 142rpx; padding: 18rpx 20rpx; border: 0; border-radius: 12rpx; background: #f7f9fc; display: flex; flex-direction: column; align-items: flex-start; justify-content: center; gap: 9rpx; }.coop-profile-tools .coop-profile-menu-label { font-size: 25rpx; color: #34435a; gap: 12rpx; }.coop-profile-tools .coop-menu-row>text:last-child { white-space: normal; font-size: 21rpx; color: #8b97a8; }.coop-profile-tools .coop-menu-row>.u-icon:last-child { display: none; }.coop-profile-card--identity>.coop-menu-row { min-height: 95rpx; padding: 20rpx 6rpx; }.coop-profile-purchase-action { width: 194rpx; }.coop-form-section { border-radius: 12rpx; margin: 20rpx 24rpx; border: 1rpx solid #e8edf4; }.coop-profile-name { color: #26364d; }.coop-profile-identity .coop-muted { color: #8492a5; }
.coop-profile-tool-icon { width: 49rpx; height: 49rpx; border-radius: 12rpx; display: flex; align-items: center; justify-content: center; flex: none; background: #eaf1fc; }.coop-profile-tool-icon.is-violet { background: #f0ebf8; }.coop-profile-tool-icon.is-green { background: #e7f1ec; }.coop-profile-tool-icon.is-gold { background: #f7eddf; }.coop-connection-source { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 5px; }.coop-connection-source>text:first-child { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; font-weight: 550; color: #3e587b; }.coop-connection-state { font-size: 11px; color: #668678; line-height: 18px; }.coop-connection-state.is-paused { color: #a18458; }.coop-connected-source { gap: 12px; padding: 13px 14px; border-radius: 8px; background: #f1f6fd; }.coop-connected-source>text:last-child { min-height: 44px; display: flex; align-items: center; font-size: 12px; }.coop-profile-help { border-top: 1px solid #edf1f6; margin-top: 14px; }.coop-profile-help :deep(.u-cell__body) { padding: 11px 0; }.coop-profile-help :deep(.u-cell__title-text) { color: #7b8da5; font-size: 12px; }.coop-profile-help :deep(.u-collapse-item__content__text) { padding: 0; }.coop-profile-help-body { color: #7b8ba1; font-size: 12px; line-height: 1.8; padding: 0 0 10px; }.coop-profile-help-body>view+view { margin-top: 7px; }</style>
