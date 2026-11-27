<template>
    <CoopTaskSheet
        :page="page"
        :show="show"
        title="发布客户需求"
        :busy="busy || uploading"
        :inactive="regionOpen"
        full
        @close="$emit('close')"
    >
        <view class="coop-form-section">
            <view class="coop-section-title">客户资料</view>
            <view class="coop-form-row"
                ><text>客户称呼</text
                ><input v-model="form.customer_name" maxlength="80" placeholder="例如：刘先生" :disabled="busy"
            /></view>
            <view class="coop-form-row"
                ><text>联系电话</text
                ><input v-model="form.mobile" type="number" maxlength="20" placeholder="客户手机号" :disabled="busy"
            /></view>
            <view class="coop-form-column"
                ><text>客户所在地区</text
                ><CoopRegionFields v-model="region" :min-level="3" :disabled="busy" @overlay="regionOpen = $event"
            /></view>
        </view>
        <view class="coop-form-section">
            <view class="coop-section-title">客户想找什么</view>
            <view v-if="businesses.length" class="coop-form-row coop-business-choice"
                ><text>业务类型</text
                ><picker
                    :range="businesses"
                    range-key="name"
                    :value="businessIndex"
                    :disabled="busy"
                    @change="changeBusiness"
                    ><view class="coop-business-choice__value"
                        ><text>{{ business?.name || '请选择业务' }}</text
                        ><u-icon name="arrow-right" size="12" color="#8996a6" /></view></picker
            ></view>
            <CoopState
                v-if="!businesses.length"
                title="暂无开放的业务"
                hint="请联系平台配置业务类型后再发布，不会清除已填内容。"
            />
            <view v-if="business" class="coop-search-trigger" @tap="chooseDemand"
                ><u-icon name="search" color="#9ba3ad" size="17" /><text
                    >{{ models.length ? '已选 ' + models.length + ' 项' : '选择意向' + dimension
                    }}<text class="coop-demand-limit"
                        >{{ demandLimit ? ' · 最多 ' + demandLimit + ' 项' : ' · 数量不限' }} ›</text
                    ></text
                ></view
            >
            <CoopBusinessField
                v-for="field in business?.fields || []"
                :key="form.business_type + ':' + field.key"
                v-model="values[field.key]"
                :field="field"
                :disabled="busy"
            />
            <view v-if="business" class="coop-publish-rule"
                >成交最低协作费 {{ coopMoney(business.commission_minimum) }}<text> · 未成交不收费</text></view
            >
            <view v-if="business" class="coop-publish-fee"
                ><u-collapse :border="false"
                    ><u-collapse-item title="费用如何分配" name="business-fee"
                        ><view class="coop-fee-description"
                            >平台维护费按本笔协作费的 {{ feeRules.percent ?? '—' }}% 计算，最低
                            {{ coopMoney(feeRules.minimum) }}，剩余归发布方。以发布时保存到本单的规则为准。</view
                        ></u-collapse-item
                    ></u-collapse
                ></view
            >
        </view>
        <view class="coop-form-section"
            ><view class="coop-section-title">补充沟通截图<text>选填</text></view
            ><CoopAttachmentUpload v-model="form.attachments" :disabled="busy" @pending="uploading = $event" /><view
                class="coop-fold-title"
                role="button"
                :aria-expanded="remarkOpen"
                @tap="remarkOpen = !remarkOpen"
                >补充备注<u-icon :name="remarkOpen ? 'arrow-up' : 'arrow-down'" size="12" color="#929ba6" /></view
            ><u-textarea
                v-if="remarkOpen"
                v-model="form.remark"
                :disabled="busy"
                :maxlength="2000"
                placeholder="预算、成色或到店时间等，选填"
        /></view>
        <view class="coop-consent" @tap="!busy && (form.consent = !form.consent)"
            ><CoopSelectionMark :selected="form.consent" /><text
                >已向客户说明，会将必要联系方式与需求交给所选门店跟进。</text
            ></view
        >
        <view class="coop-draft-note">返回后可继续编辑本机草稿</view>
        <template #footer
            ><view
                v-if="localError || error"
                class="coop-inline-error coop-submit-error"
                role="alert"
                aria-live="polite"
                >{{ localError || error }}</view
            ><u-button
                :custom-style="cooperationButtonStyle"
                type="primary"
                shape="circle"
                text="下一步，选交付门店"
                :loading="busy || uploading"
                loading-text="正在保存"
                :disabled="busy || uploading || !business"
                @click="submit"
        /></template>
    </CoopTaskSheet>
</template>
<script lang="ts">
// Match Vue's transparent layout in native Mini Programs: an extra custom
// component host otherwise breaks the page's definite-height flex chain.
export default { options: { virtualHost: true } }
</script>
<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { coopMoney, coopRequestId, type CoopRow } from '../../api/cooperation'
import { enabledBusinesses, businessDimensionLabel, businessSelectionLimit, businessFormError, businessFeeRules } from '../../utils/cooperation-business'
import { openCooperationCatalog } from '../../utils/cooperation-navigation'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopState from './CoopState.vue'
import CoopSelectionMark from './CoopSelectionMark.vue'
import CoopRegionFields from './CoopRegionFields.vue'
import CoopAttachmentUpload from './CoopAttachmentUpload.vue'
import CoopBusinessField from './CoopBusinessField.vue'
const props = defineProps<{ show: boolean; page?: boolean; initialBusinessType?: string; merchantId?: number; busy?: boolean; error?: string; config?: CoopRow }>()
const emit = defineEmits<{ (event: 'close'): void; (event: 'submit', data: CoopRow): void }>()
const initial = () => ({ request_id: coopRequestId(), customer_name: '', mobile: '', province_id: 0, city_id: 0, district_id: 0, region_text: '', business_type: '', attachments: [] as string[], remark: '', consent: false })
const form = reactive(initial()), selections = reactive<Record<string, CoopRow[]>>({}), fields = reactive<Record<string, CoopRow>>({}), uploading = ref(false), regionOpen = ref(false), remarkOpen = ref(false), localError = ref('')
const businesses = computed(() => enabledBusinesses(props.config || {})), business = computed(() => businesses.value.find(item => item.key === form.business_type)), dimension = computed(() => businessDimensionLabel(business.value)), demandLimit = computed(() => businessSelectionLimit(business.value))
const businessIndex = computed(() => Math.max(0, businesses.value.findIndex(item => item.key === form.business_type)))
const models = computed(() => selections[form.business_type] || [])
const feeRules = computed(() => businessFeeRules(business.value, props.config || {}))
const values = computed(() => fields[form.business_type] || (fields[form.business_type] = {}))
const region = computed({ get: () => form, set: value => Object.assign(form, value) })
const draftKey = () => `hsx_lead_publish_draft:${uni.getStorageSync('wap_site_id') || 'current'}:${uni.getStorageSync('wap_member_id') || props.merchantId || 0}`
let draftActive = false
function restore() { if (draftActive) return; const draft = uni.getStorageSync(draftKey()); if (draft && typeof draft === 'object') { Object.assign(form, draft.form || {}); Object.assign(selections, draft.selections || {}); Object.assign(fields, draft.fields || {}); remarkOpen.value = !!form.remark }; if (props.initialBusinessType) form.business_type = props.initialBusinessType; if (!form.business_type) form.business_type = businesses.value[0]?.key || ''; draftActive = true }
function changeBusiness(event: any) { if (props.busy) return; const index = Number(event.detail.value); if (!Number.isInteger(index) || index < 0) return; const selected = businesses.value[index]; if (!selected) return; form.business_type = selected.key; localError.value = '' }
function chooseDemand() { if (props.busy || !business.value) return; const key = form.business_type; openCooperationCatalog({ title: '选择意向' + dimension.value, business_type: key, max: demandLimit.value, selection_mode: business.value.selection_mode, allow_bulk: false, items: models.value, options: business.value.options }, items => { selections[key] = items; localError.value = '' }) }
function submit() {
    if (props.busy || uploading.value) return
    localError.value = ''
    if (props.config?.fee_confirmed !== true) { localError.value = '平台尚未确认收费规则，暂不能发布，请联系管理员'; return }
    if (!form.customer_name.trim()) { localError.value = '请填写客户称呼'; return }
    if (!/^1\d{10}$/.test(form.mobile.trim())) { localError.value = '请填写有效的客户手机号'; return }
    if (!form.district_id) { localError.value = '请选择客户所在省、市、区县'; return }
    const problem = businessFormError(business.value, models.value as any, values.value)
    if (problem) { localError.value = problem; return }
    if (!form.consent) { localError.value = '请先向客户说明联系方式用途并确认'; return }
    const formValues = Object.fromEntries((business.value?.fields || []).filter(field => values.value[field.key] != null && values.value[field.key] !== '').map(field => [field.key, values.value[field.key]]))
    emit('submit', { ...form, mobile: form.mobile.trim(), customer_name: form.customer_name.trim(), demand_ids: models.value.map(item => String(item.id)), form_values: formValues })
}
function clear() { draftActive = false; uni.removeStorageSync(draftKey()); Object.assign(form, initial()); Object.keys(selections).forEach(key => delete selections[key]); Object.keys(fields).forEach(key => delete fields[key]); localError.value = ''; remarkOpen.value = false }
watch(() => props.show, show => { if (show) restore() }, { immediate: true })
watch([form, selections, fields], () => { if (draftActive) uni.setStorageSync(draftKey(), { form: { ...form }, selections: { ...selections }, fields: { ...fields } }) }, { deep: true })
defineExpose({ clear })
</script>
<style scoped lang="scss">
@import './cooperation-shared';
@include hsx-coop-form-section;
@include hsx-coop-section-title;
@include hsx-coop-form-row;
@include hsx-coop-form-column;
@include hsx-coop-search-trigger;
@include hsx-coop-fold-title;
@include hsx-coop-consent;
@include hsx-coop-inline-error;
.coop-business-choice { margin-bottom: 12px; }
.coop-business-choice>picker { padding: 0; }
.coop-business-choice__value { display: flex; align-items: center; justify-content: flex-end; gap: 10px; min-height: 44px; color: #35465c; }
.coop-business-choice__value>text { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.coop-submit-error { padding: 0 0 16rpx; }
.coop-publish-fee { margin-top: 4px; }
.coop-fee-description { font-size: 12px; color: #7d8b9d; line-height: 1.8; }
</style>

<style scoped>
.coop-publish-rule,.coop-draft-note { font-size: 12px; color: #70829a; line-height: 1.8; }.coop-publish-rule { padding-top: 12px; }.coop-draft-note { padding: 0 16px 16px; }.coop-demand-limit { font-size: 12px; color: #8a98aa; }
</style>
