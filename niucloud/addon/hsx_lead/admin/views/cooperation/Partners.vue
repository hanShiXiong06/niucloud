<template>
    <section>
        <header class="coop-head"><h2>等级认证</h2></header>
        <CooperationFilter :query="query" :loading="loading" :statuses="statuses" placeholder="搜索门店、联系人或电话" :with-date="false" @search="search" @reload="load" @reset="resetExtra">
            <el-button :type="query.province_id ? 'primary' : 'default'" plain @click="openRegion">{{ regionLabel || '选择地区' }}</el-button>
            <el-select v-model="reliable" clearable placeholder="全部认证" aria-label="靠谱认证"><el-option label="有效靠谱认证" value="1" /><el-option label="未认证 / 已到期" value="0" /></el-select>
        </CooperationFilter>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <div class="coop-surface"><HsxTable :columns="columns" :data="rows" :loading="loading" empty-text="没有符合条件的合作商户">
            <template #merchant="{row}"><div class="coop-cell"><strong>{{ row.name }}</strong><small>{{ row.contact_name }} · {{ row.mobile }}</small><small>{{ row.region_text || '地区未填写' }}</small></div></template>
            <template #grade="{row}"><div class="coop-cell"><strong>{{ row.grade_name || '待核定等级' }}</strong><small>{{ row.grade_mode === 'manual' ? '人工指定' : '按有效库存自动评定' }}</small><small v-if="row.all_models">具备全型号承接授权</small></div></template>
            <template #reliable="{row}"><div class="coop-cell"><span v-if="row.is_reliable" class="coop-reliable">靠谱认证</span><span v-else>{{ row.certification_status === 'pending' ? '认证待核验' : '未认证 / 已到期' }}</span><small v-if="row.is_reliable && Number(row.cert_expires_at) === 0">永久有效</small><small v-else-if="row.cert_expires_at">有效至 {{ coopTime(row.cert_expires_at) }}</small></div></template>
            <template #inventory="{row}"><div class="coop-cell"><strong>{{ row.all_models ? '全型号承接' : Number(row.inventory_count || 0) > 0 ? `${row.inventory_count} 条可售快照` : Number(row.manual_model_count || 0) > 0 ? '手选交付型号' : '待设置' }}</strong><small v-if="Number(row.manual_model_count || 0) > 0">已选 {{ row.manual_model_count }} 个型号 · 非库存数量</small><small v-else>{{ row.all_models ? '平台按等级授予' : Number(row.inventory_count || 0) > 0 ? '接单前向门店核对现货' : '无需ERP，可由商户手选型号' }}</small></div></template>
            <template #publishing="{row}"><div class="coop-cell"><strong v-if="Number(row.publisher_until)>0">{{ Number(row.publisher_until)>now ? '权益有效' : '权益已到期' }}</strong><strong v-else>尚未开通</strong><small v-if="Number(row.publisher_until)>0">{{ coopTime(row.publisher_until) }}</small><small v-if="row.blocked_at" class="coop-danger-copy">已永久停止合作</small></div></template>
            <template #actions="{row}"><div class="coop-actions"><el-button link type="primary" @click="edit(row)">管理</el-button><el-button link type="danger" :disabled="!!row.blocked_at || actionBusy" @click="block(row)">停止合作</el-button></div></template>
        </HsxTable><div class="coop-pagination"><HsxPagination :current-page="query.page" :page-size="query.limit" :total="total" :disabled="loading" @change="changePage" /></div></div>
        <HsxDrawer v-model="editVisible" title="管理商户资格" :subtitle="selected.name || ''" size="sm" show-footer :confirm-loading="saving" confirm-text="保存资格设置" @confirm="save">
            <el-alert v-if="saveError" :title="saveError" type="error" :closable="false" show-icon class="coop-error" />
            <el-alert v-if="selected.blocked_at" title="该商户已永久停止合作；修改等级或认证不能解除停用。已形成的账目和余额仍保留。" type="warning" :closable="false" show-icon class="coop-error" />
            <el-form class="coop-form" label-position="top" :disabled="saving">
                <el-form-item label="商户等级评定"><el-radio-group v-model="form.grade_mode"><el-radio value="auto">按库存规则自动</el-radio><el-radio value="manual" :disabled="!grades.length">人工指定</el-radio></el-radio-group><p class="coop-form-note">等级反映交付能力，不是设备成色。自动等级只使用有效的在售库存快照。</p></el-form-item>
                <el-form-item v-if="form.grade_mode === 'manual'" label="指定等级"><el-select v-model="form.grade_id" placeholder="选择后台维护的等级"><el-option v-for="grade in grades" :key="grade.id" :value="String(grade.id)" :label="grade.name" /></el-select><p v-if="selectedGrade?.all_models" class="coop-form-note">该等级已配置全型号承接能力，不依赖每个型号的库存；这不代表实物现货或锁货保证。</p></el-form-item>
                <el-alert v-if="!grades.length" title="尚未读取到可用等级，请先由有业务配置权限的管理员维护等级规则，再刷新列表。" type="info" :closable="false" show-icon class="coop-error" />
                <el-form-item label="靠谱认证状态"><el-select v-model="form.certification_status"><el-option label="未认证" value="none" /><el-option label="待核验" value="pending" /><el-option label="有效认证" value="active" /><el-option label="撤销认证" value="revoked" /></el-select><p class="coop-form-note">人工授予或调整认证不自动收款，不代表赔付或无违约保证。</p></el-form-item>
                <el-form-item label="认证有效期"><el-radio-group v-model="certPermanent"><el-radio :value="true">永久有效</el-radio><el-radio :value="false">指定截止时间</el-radio></el-radio-group><el-date-picker v-if="!certPermanent" v-model="certUntil" type="datetime" placeholder="选择未来的截止时间" /><p class="coop-form-note">只有启用有效认证后才显示靠谱标识。撤销认证或停止合作后，永久认证也会失效。</p></el-form-item>
                <el-form-item label="发单权益有效期至"><el-date-picker v-model="publisherUntil" type="datetime" placeholder="未开通可留空" /><p class="coop-form-note">购买权益按购买时起算一个月；这里是有记录的人工授予或调整，不产生付款。</p></el-form-item>
                <el-form-item label="调整原因"><el-input v-model="form.remark" type="textarea" :rows="3" maxlength="1000" show-word-limit placeholder="说明本次等级、认证或权益调整原因" /></el-form-item>
            </el-form>
        </HsxDrawer>
        <HsxDrawer v-model="regionVisible" title="按门店地区筛选" subtitle="可只选省或城市，不必选到区县。" size="sm" show-footer confirm-text="应用地区" @confirm="applyRegion"><RegionSelect v-model="regionDraft" /></HsxDrawer>
    </section>
</template>
<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import RegionSelect from '../components/RegionSelect.vue'
import CooperationFilter from './CooperationFilter.vue'
import { blockCooperationPartner, getCooperationPartners, saveCooperationPartner } from '../../api/cooperation'
import { coopError, coopTime, useCooperationList, type CoopRow } from '../../composables/cooperation'
const statuses=[{value:'1',label:'合作中'},{value:'blocked',label:'已停止合作'}]
const grades=ref<CoopRow[]>([]),reliable=ref(''),now=Math.floor(Date.now()/1000)
const { rows,total,loading,error,query,load,search:searchList,changePage }=useCooperationList(async(params)=>{const result=await getCooperationPartners(params);if(Array.isArray(result.data?.grades))grades.value=result.data.grades.filter((grade:CoopRow)=>grade.status!==false&&grade.status!==0);return result})
const regionLabel=ref(''),regionVisible=ref(false),regionDraft=ref<CoopRow>({})
function search(patch:CoopRow={}){return searchList({...patch,is_reliable:reliable.value})}
function resetExtra(){reliable.value='';regionLabel.value='';Object.assign(query,{province_id:0,city_id:0,district_id:0})}
function openRegion(){regionDraft.value={province_id:Number(query.province_id||0),city_id:Number(query.city_id||0),district_id:Number(query.district_id||0)};regionVisible.value=true}
function applyRegion(){Object.assign(query,regionDraft.value);regionLabel.value=[regionDraft.value.province_name,regionDraft.value.city_name,regionDraft.value.district_name].filter(Boolean).join(' / ');regionVisible.value=false;void search()}
const columns=[{prop:'name',label:'商户 / 地区',slot:'merchant',minWidth:210},{prop:'grade_name',label:'交付等级',slot:'grade',minWidth:160},{prop:'is_reliable',label:'靠谱认证',slot:'reliable',minWidth:155},{prop:'inventory_count',label:'交付依据',slot:'inventory',minWidth:175},{prop:'publisher_until',label:'发布权益 / 合作状态',slot:'publishing',minWidth:175},{prop:'actions',label:'操作',slot:'actions',width:145,fixed:'right' as const}]
const selected=ref<CoopRow>({}),editVisible=ref(false),saving=ref(false),saveError=ref(''),form=reactive<CoopRow>({grade_mode:'auto',grade_id:'',certification_status:'none',remark:''}),certUntil=ref<Date>(),publisherUntil=ref<Date>(),actionBusy=ref(false)
const certPermanent=ref(true)
const selectedGrade=computed(()=>grades.value.find(grade=>String(grade.id)===String(form.grade_id)))
function edit(row:CoopRow){selected.value={...row};Object.assign(form,{grade_mode:row.grade_mode||'auto',grade_id:String(row.grade_id||''),certification_status:row.certification_status||'none',remark:''});certPermanent.value=Number(row.cert_expires_at||0)===0;certUntil.value=Number(row.cert_expires_at)>0?new Date(Number(row.cert_expires_at)*1000):undefined;publisherUntil.value=Number(row.publisher_until)>0?new Date(Number(row.publisher_until)*1000):undefined;saveError.value='';editVisible.value=true}
async function save(){if(saving.value)return;saveError.value='';if(!String(form.remark).trim()){saveError.value='请填写调整原因，便于核对本次人工操作';return}if(form.grade_mode==='manual'&&!selectedGrade.value){saveError.value='请选择后台已维护的等级';return}if(!certPermanent.value&&(!certUntil.value||!Number.isFinite(certUntil.value.getTime())||(form.certification_status==='active'&&certUntil.value.getTime()<=Date.now()))){saveError.value='请选择有效的认证截止时间，或改为永久有效';return}saving.value=true;try{await saveCooperationPartner(Number(selected.value.id),{...form,cert_expires_at:certPermanent.value?0:Math.floor(certUntil.value!.getTime()/1000),publisher_until:publisherUntil.value?Math.floor(publisherUntil.value.getTime()/1000):0});editVisible.value=false;ElMessage.success('商户资格设置已保存，不会自动扣款');await load()}catch(cause){saveError.value=coopError(cause,'保存未完成，已保留填写内容')}finally{saving.value=false}}
async function block(row:CoopRow){if(actionBusy.value||row.blocked_at)return;actionBusy.value=true;try{const result=await ElMessageBox.prompt(`说明为什么永久停止与“${row.name}”合作。历史记录和已形成余额会保留，不能用等级编辑解除停用。`,'记录停止合作原因',{inputType:'textarea',inputPattern:/\S.{3,}/,inputErrorMessage:'请填写至少4个字符的明确原因',confirmButtonText:'继续核对',cancelButtonText:'取消'});await ElMessageBox.confirm(`确认永久停止与“${row.name}”合作？系统会阻止其继续发单或接收交付。这个动作不会删除历史，也不会向发布方垫付损失。`,'再次确认停止合作',{type:'warning',confirmButtonText:'确认永久停止合作',cancelButtonText:'返回',distinguishCancelAndClose:true});await blockCooperationPartner(Number(row.id),{reason:result.value,confirm:true});ElMessage.success('已停止合作，历史记录保留');await load()}catch(cause){if(cause!=='cancel'&&cause!=='close')ElMessage.error(coopError(cause,'停止合作未完成，请刷新核对'))}finally{actionBusy.value=false}}
onMounted(load)
</script>
