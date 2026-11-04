<template><CoopTaskSheet :show="show" title="发布线索" :busy="busy || uploading" :inactive="picker" full @close="$emit('close')"><view class="coop-form-section"><view class="coop-section-title">客户资料</view><view class="coop-form-row"><text>客户称呼</text><input v-model="form.customer_name" maxlength="80" placeholder="例如：刘先生" :disabled="busy" /></view><view class="coop-form-row"><text>联系电话</text><input v-model="form.mobile" type="number" maxlength="20" placeholder="客户手机号" :disabled="busy" /></view><view class="coop-form-column"><text>客户所在地区</text><CoopRegionFields v-model="region" :disabled="busy" /></view></view><view class="coop-form-section"><view class="coop-section-title">客户想找什么</view><view class="coop-type-switch"><view v-for="item in types" :key="item.value" :class="{ active: form.business_type === item.value }" @tap="!busy && (form.business_type = item.value)">{{ item.label }}<text v-if="form.business_type === item.value"> ✓</text></view></view><view class="coop-search-trigger" @tap="!busy && (picker = true)"><u-icon name="search" color="#9ba3ad" size="17" /><text>{{ demandLimit ? `选择意向型号，最多 ${demandLimit} 个` : '选择客户意向型号' }}</text></view><DemandModels :items="models" removable :disabled="busy" @remove="removeModel" /><view v-if="demandModelLimitError(models, demandLimit)" class="coop-inline-error">{{ demandModelLimitError(models, demandLimit) }}</view></view><view class="coop-form-section"><view class="coop-section-title">补充沟通截图<text>选填</text></view><CoopAttachmentUpload v-model="form.attachments" :disabled="busy" @pending="uploading = $event" /><view class="coop-fold-title" @tap="remarkOpen = !remarkOpen">补充备注<u-icon :name="remarkOpen ? 'arrow-up' : 'arrow-down'" size="12" color="#929ba6" /></view><u-textarea v-if="remarkOpen" v-model="form.remark" :disabled="busy" :maxlength="2000" placeholder="预算、成色或到店时间等，选填" /></view><view class="coop-consent" @tap="!busy && (form.consent = !form.consent)"><CoopSelectionMark :selected="form.consent" /><text>已向客户说明，会将必要联系方式与需求交给所选门店跟进。</text></view><view v-if="localError || error" class="coop-inline-error coop-form-error">{{ localError || error }}</view><template #footer><u-button type="primary" shape="circle" text="下一步，选交付门店" :loading="busy || uploading" loading-text="正在保存" :disabled="busy || uploading" @click="submit" /></template></CoopTaskSheet><CoopModelSearchPicker :show="picker" v-model="models" :max="demandLimit" @close="picker = false" /></template>
<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import DemandModels from './DemandModels.vue'
import { normalizeDemandModelLimit, demandModelLimitError } from '../../utils/demand-models'
import { coopRequestId, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopSelectionMark from './CoopSelectionMark.vue'
import CoopRegionFields from './CoopRegionFields.vue'
import CoopAttachmentUpload from './CoopAttachmentUpload.vue'
import CoopModelSearchPicker from './CoopModelSearchPicker.vue'
const props = defineProps<{ show: boolean; busy?: boolean; error?: string; config?: CoopRow }>()
const demandLimit = computed(() => normalizeDemandModelLimit(props.config?.demand_model_limit))
const emit = defineEmits<{ (event: 'close'): void; (event: 'submit', data: CoopRow): void }>()
const initial = () => ({ request_id: coopRequestId(), customer_name: '', mobile: '', province_id: 0, city_id: 0, district_id: 0, region_text: '', business_type: 'purchase', attachments: [] as string[], remark: '', consent: false })
const form = reactive(initial()), models = ref<CoopRow[]>([]), picker = ref(false), uploading = ref(false), remarkOpen = ref(false), localError = ref('')
const region = computed({ get: () => form, set: value => Object.assign(form, value) })
const types = [{ value: 'purchase', label: '购买' }, { value: 'rent', label: '租机' }, { value: 'exchange', label: '置换' }]
function removeModel(id: string) { models.value = models.value.filter(item => String(item.id) !== String(id)) }
function submit() { localError.value = ''; if (!form.customer_name.trim()) { localError.value = '请填写客户称呼'; return }; if (!/^1\d{10}$/.test(form.mobile.trim())) { localError.value = '请填写有效的客户手机号'; return }; if (!form.district_id) { localError.value = '请选择客户所在省、市、区县'; return }; if (!models.value.length) { localError.value = '请至少选择一个意向型号'; return }; const limitError = demandModelLimitError(models.value, demandLimit.value); if (limitError) { localError.value = limitError; return }; if (!form.consent) { localError.value = '请先向客户说明联系方式用途并确认'; return }; emit('submit', { ...form, mobile: form.mobile.trim(), customer_name: form.customer_name.trim(), model_ids: models.value.map(item => String(item.id)) }) }
function clear() { Object.assign(form, initial()); models.value = []; localError.value = ''; picker.value = false; remarkOpen.value = false }
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
@include hsx-coop-form-error;
.coop-type-switch { display: flex; gap: 18rpx; margin: 0 0 22rpx; }
.coop-type-switch>view { background: #f4f6f8; border: 1rpx solid transparent; border-radius: 8rpx; color: #818b97; font-size: 26rpx; line-height: 72rpx; width: 165rpx; text-align: center; }
.coop-type-switch>view.active { color: var(--primary-color, #d93833); background: var(--primary-color-light, #fff0ee); border-color: var(--primary-color, #d93833); }
</style>
