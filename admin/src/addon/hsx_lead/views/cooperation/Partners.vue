<template>
    <section>
        <header class="coop-head"><h2>商户监管</h2></header>
        <CooperationFilter :query="query" :loading="loading" :statuses="statuses" placeholder="搜索门店、联系人或电话" :with-date="false" @search="search" @reload="load" @reset="resetExtra">
            <el-button :type="query.province_id?'primary':'default'" plain @click="openRegion">{{ regionLabel||'选择地区' }}</el-button>
            <el-select v-model="levelFilter" filterable clearable placeholder="全部主体会员等级" aria-label="主体会员等级"><el-option v-for="level in levels" :key="level.code" :label="level.name" :value="level.code" /></el-select>
        </CooperationFilter>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <HsxTable :columns="columns" :data="rows" :loading="loading" empty-text="没有符合条件的合作商户">
            <template #merchant="{row}"><div class="coop-cell"><strong>{{ row.name }}</strong><small>{{ row.contact_name }} · {{ row.mobile }}</small><small>{{ row.region_text||'地区未填写' }}</small><div class="coop-tags"><el-tag v-for="business in (row.business_capabilities||[]).slice(0,3)" :key="business.business_type" size="small" type="info" effect="plain">{{ business.business_name||coopBusiness(business.business_type) }}</el-tag></div></div></template>
            <template #membership="{row}"><div class="coop-cell"><strong>{{ row.membership?.level_name||'普通会员' }}</strong><el-tag :type="entityMembershipState(row.membership).tone" effect="plain">{{ entityMembershipState(row.membership).label }}</el-tag><small>{{ expiry(row.membership) }}</small></div></template>
            <template #inventory="{row}"><div class="coop-cell"><strong>{{ Number(row.inventory_count||0)>0?row.inventory_count+' 条可售快照':Number(row.manual_model_count||0)>0?'已登记交付能力':'待登记交付能力' }}</strong><small v-if="Number(row.manual_model_count||0)>0">{{ row.manual_model_count }} 个自主登记型号</small><small v-else>接单前仍需向门店核对现货</small></div></template>
            <template #state="{row}"><el-tag :type="row.blocked_at?'danger':'success'" effect="plain">{{ row.blocked_at?'已停止合作':'合作中' }}</el-tag></template>
            <template #actions="{row}"><div class="coop-actions"><el-button link type="primary" @click="open(row)">查看资料</el-button><el-button v-if="canBlock" link type="danger" :disabled="!!row.blocked_at||actionBusy" @click="block(row)">停止合作</el-button></div></template>
        </HsxTable>
        <HsxPagination :page="query.page" :limit="query.limit" :total="total" class="coop-pagination" @pagination="changePage" />
        <HsxDrawer v-model="detailVisible" title="商户资料" :subtitle="selected.name||''" size="sm">
            <section class="coop-partner-section"><h3>主体会员权益</h3><div v-if="selected.membership" class="coop-kv"><span>当前等级</span><span>{{ selected.membership.level_name||'普通会员' }}</span><span>权益状态</span><span>{{ entityMembershipState(selected.membership).label }}</span><span>有效期</span><span>{{ expiry(selected.membership) }}</span><span>账号名额</span><span>{{ entitySeatText(selected.membership) }}</span><span>协作权限</span><span>{{ capabilityText(selected.membership) }}</span></div><el-empty v-else description="尚未返回主体权益信息" :image-size="56" /></section>
            <section class="coop-partner-section"><h3>交付资料</h3><div class="coop-kv"><span>联系人</span><span>{{ selected.contact_name||'未填写' }} · {{ selected.mobile||'未填写电话' }}</span><span>门店地区</span><span>{{ selected.region_text||'未填写地区' }}</span><span>登记业务</span><span>{{ (selected.business_capabilities||[]).map(item=>item.business_name||coopBusiness(item.business_type)).join('、')||'未登记' }}</span><span>库存参考</span><span>{{ selected.inventory_count??'未返回' }} 条可售快照</span><span>自主能力</span><span>{{ selected.manual_model_count??'未返回' }} 个型号</span></div></section>
            <section v-if="canReadLicense" class="coop-partner-section"><div class="coop-license-review__heading"><h3>营业执照</h3><el-button text type="primary" :loading="licenseLoading" @click="readLicense">{{ licenseImage?'重新读取':'私密查看' }}</el-button></div><el-alert v-if="licenseError" :title="licenseError" type="error" :closable="false" show-icon /><el-image v-if="licenseImage" :src="licenseImage" :preview-src-list="[licenseImage]" fit="contain" class="coop-license-review__image" /><p v-else-if="licenseEmpty" class="coop-form-note">商户尚未上传营业执照</p></section>
            <el-collapse><el-collapse-item title="会员配置与监管说明" name="partner-help"><p class="coop-muted">主体会员统一在“会员 → 会员等级”配置；商户自行购买、续费或升级。本页不授予另一套等级或期限。停止合作不会删除个人会员、历史记录或既有账款。</p></el-collapse-item></el-collapse>
            <template #footer><el-button @click="detailVisible=false">关闭</el-button></template>
        </HsxDrawer>
        <HsxDrawer v-model="regionVisible" title="按门店地区筛选" size="sm" show-footer confirm-text="应用地区" @confirm="applyRegion"><RegionSelect v-model="regionDraft" /></HsxDrawer>
    </section>
</template>
<script setup lang="ts">
import { computed,onMounted,onBeforeUnmount,ref,watch } from 'vue'
import useUserStore from '@/stores/modules/user'
import { hasLeadPagePermission } from '../../composables/navigation'
import { ElMessage,ElMessageBox } from 'element-plus'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import RegionSelect from '../components/RegionSelect.vue'
import CooperationFilter from './CooperationFilter.vue'
import { blockCooperationPartner,getCooperationPartners,getCooperationBusinessLicense } from '../../api/cooperation'
import { normalizeEntityLevels,entitySeatText,entityMembershipState,subjectCapabilityLabels,type EntityLevel } from '../../composables/entity'
import { coopBusiness,coopError,coopTime,useCooperationList,type CoopRow } from '../../composables/cooperation'
const statuses=[{value:'1',label:'合作中'},{value:'blocked',label:'已停止合作'}]
const levels=ref<EntityLevel[]>([]),levelFilter=ref('')
const {rows,total,loading,error,query,load,search:searchList,changePage}=useCooperationList(async(params)=>{const result=await getCooperationPartners(params);if(Array.isArray(result.data?.levels))levels.value=normalizeEntityLevels(result.data.levels);return result})
const regionLabel=ref(''),regionVisible=ref(false),regionDraft=ref<CoopRow>({})
function search(patch:CoopRow={}){return searchList({...patch,level_code:levelFilter.value})}
function resetExtra(){levelFilter.value='';regionLabel.value='';Object.assign(query,{province_id:0,city_id:0,district_id:0})}
function openRegion(){regionDraft.value={province_id:Number(query.province_id||0),city_id:Number(query.city_id||0),district_id:Number(query.district_id||0)};regionVisible.value=true}
function applyRegion(){Object.assign(query,regionDraft.value);regionLabel.value=[regionDraft.value.province_name,regionDraft.value.city_name,regionDraft.value.district_name].filter(Boolean).join(' / ');regionVisible.value=false;void search()}
const columns=[{prop:'name',label:'商户 / 地区',slot:'merchant',minWidth:220},{prop:'membership',label:'主体会员 / 有效期',slot:'membership',minWidth:180},{prop:'inventory_count',label:'交付依据',slot:'inventory',minWidth:200},{prop:'blocked_at',label:'合作状态',slot:'state',width:125},{prop:'actions',label:'操作',slot:'actions',width:170,fixed:'right' as const}]
const selected=ref<CoopRow>({}),detailVisible=ref(false),actionBusy=ref(false)
const user=useUserStore(),canReadLicense=computed(()=>hasLeadPagePermission(user,'hsx_lead_cooperation_license_read')),canBlock=computed(()=>hasLeadPagePermission(user,'hsx_lead_cooperation_partner_block'))
const licenseLoading=ref(false),licenseImage=ref(''),licenseError=ref(''),licenseEmpty=ref(false)
let licenseGeneration=0
function resetLicense(){licenseGeneration++;licenseImage.value='';licenseError.value='';licenseEmpty.value=false;licenseLoading.value=false}
async function readLicense(){if(licenseLoading.value||!canReadLicense.value||!detailVisible.value)return;const id=Number(selected.value.id),version=++licenseGeneration;licenseLoading.value=true;licenseError.value='';licenseImage.value='';licenseEmpty.value=false;try{const response=await getCooperationBusinessLicense(id);if(version!==licenseGeneration||!detailVisible.value||id!==Number(selected.value.id))return;if(response.data?.has_image===false){licenseEmpty.value=true;return}if(!/^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$/.test(response.data?.data_url||''))throw new Error('证照预览结果不完整，请重新读取');licenseImage.value=response.data.data_url}catch(cause){if(version===licenseGeneration)licenseError.value=coopError(cause,'营业执照未读取，请重试')}finally{if(version===licenseGeneration)licenseLoading.value=false}}
watch([detailVisible,()=>selected.value.id],resetLicense);onBeforeUnmount(resetLicense)
function open(row:CoopRow){selected.value={...row};detailVisible.value=true}
function expiry(membership:CoopRow={}){return Number(membership.expires_at)>0?coopTime(membership.expires_at)+' 到期':membership.active===true?'长期有效':'尚未开通'}
function capabilityText(membership:CoopRow){return Object.entries(subjectCapabilityLabels).filter(([key])=>membership.capabilities?.[key]===true||membership.benefits?.[key]===true).map(([,label])=>label).join('、')||'未授予协作权限'}
async function block(row:CoopRow){if(actionBusy.value||row.blocked_at||!canBlock.value)return;actionBusy.value=true;try{const result=await ElMessageBox.prompt('说明为什么永久停止与“'+row.name+'”合作。历史记录和已形成余额会保留。','记录停止合作原因',{inputType:'textarea',inputPattern:/\S.{3,}/,inputErrorMessage:'请填写至少4个字符的明确原因',confirmButtonText:'继续核对',cancelButtonText:'取消'});await ElMessageBox.confirm('确认永久停止与“'+row.name+'”合作？将不能继续发单或接收交付，不删除历史记录，也不向发布方垫付损失。','再次确认停止合作',{type:'warning',confirmButtonText:'确认永久停止合作',cancelButtonText:'返回',distinguishCancelAndClose:true});await blockCooperationPartner(Number(row.id),{reason:result.value,confirm:true});ElMessage.success('已停止合作，历史记录保留');await load()}catch(cause){if(cause!=='cancel'&&cause!=='close')ElMessage.error(coopError(cause,'停止合作未完成，请刷新核对'))}finally{actionBusy.value=false}}
onMounted(load)
</script>
<style scoped>
.coop-partner-section{padding:0 0 20px;margin-bottom:20px;border-bottom:1px solid var(--el-border-color-lighter)}.coop-partner-section h3{font-size:14px;margin:0 0 14px}.coop-license-review__heading{display:flex;align-items:center;justify-content:space-between;gap:12px}.coop-license-review__heading h3{margin-bottom:0}.coop-license-review__image{display:block;width:100%;height:210px;margin-top:12px}
</style>
