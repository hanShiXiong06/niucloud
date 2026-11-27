<template>
    <section class="entity-management">
        <header class="coop-head"><div><h2>主体与成员</h2><p>查看门店共享的会员权益、账号名额和加入申请。</p></div></header>
        <CooperationFilter :query="query" :loading="loading" :with-date="false" placeholder="搜索门店、主体码、负责人、手机号或会员编号" @search="search" @reload="load" @reset="resetLevel"><el-select v-if="levels.length" v-model="levelFilter" filterable clearable placeholder="全部会员等级" aria-label="主体会员等级" @change="search()"><el-option v-for="level in levels" :key="level.code" :label="level.name" :value="level.code" /></el-select><el-input v-else v-model="levelFilter" clearable placeholder="等级编码，如 ML2" aria-label="主体会员等级编码" @keyup.enter="search()" /></CooperationFilter>
        <el-alert v-if="error" class="coop-error" :title="error" type="error" :closable="false" show-icon><template #default><el-button link type="primary" @click="load">重新读取</el-button></template></el-alert>
        <HsxTable :columns="columns" :data="rows" :loading="loading" row-key="id" empty-text="暂无匹配的门店主体">
            <template #entity="{row}"><div class="coop-cell"><strong>{{ row.name }}</strong><small>{{ row.region_text || '未填写地区' }}</small></div></template>
            <template #code="{row}"><div class="entity-code-cell"><code>{{ row.subject_code || '尚未生成' }}</code><el-button v-if="row.subject_code" link :icon="CopyDocument" aria-label="复制主体码" @click="copyCode(row.subject_code)" /></div></template>
            <template #membership="{row}"><MembershipSummary :membership="row.membership" /></template>
            <template #accounts="{row}"><div class="coop-cell"><strong>{{ entitySeatText(row.membership) }}</strong><small v-if="Number(row.pending_count)>0" class="entity-pending">{{ row.pending_count }} 人待审核</small></div></template>
            <template #owner="{row}"><div class="coop-cell"><strong>{{ row.owner_name || '未返回负责人' }}</strong><small>{{ row.owner_mobile || '未填写手机' }}</small></div></template>
            <template #actions="{row}"><el-button v-if="canDetail" link type="primary" @click="open(row)">查看成员<template v-if="Number(row.pending_count)>0">（{{ row.pending_count }}）</template></el-button><span v-else class="coop-muted">仅列表权限</span></template>
        </HsxTable>
        <HsxPagination :total="total" :current-page="query.page" :page-size="query.limit" class="coop-pagination" @change="changePage" />

        <HsxDrawer v-model="detailVisible" :title="detail.name ? `${detail.name} · 主体与成员` : '主体与成员'" size="lg" :confirm-loading="actionBusy" :body-loading="detailLoading" @closed="clearDetail">
            <el-alert v-if="detailError" :title="detailError" type="error" :closable="false" show-icon class="coop-error"><template #default><el-button link type="primary" :disabled="actionBusy||detailLoading" @click="loadDetail">重新读取</el-button></template></el-alert>
            <template v-if="detail.id">
                <div class="entity-summary">
                    <div><span class="entity-summary-label">主体码</span><div class="entity-code-cell"><strong>{{ detail.subject_code || '尚未生成' }}</strong><el-button v-if="detail.subject_code" link :icon="CopyDocument" aria-label="复制主体码" @click="copyCode(detail.subject_code)" /></div></div>
                    <div><span class="entity-summary-label">共享等级</span><div class="coop-tags"><el-tag effect="plain">{{ detail.membership?.level_name || detail.membership?.level_code || '普通会员' }}</el-tag><el-tag :type="entityMembershipState(detail.membership).tone" effect="plain">{{ entityMembershipState(detail.membership).label }}</el-tag></div></div>
                    <div><span class="entity-summary-label">账号名额</span><strong>{{ entitySeatText(detail.membership) }}</strong></div>
                    <div><span class="entity-summary-label">有效期</span><span>{{ expiry(detail.membership) }}</span></div>
                </div>
                <el-collapse class="entity-help"><el-collapse-item title="主体码、共享权益和成员管理说明" name="entity-help"><p class="coop-muted">负责人可在手机端“我的 → 门店与成员”找到主体码，复制或邀请员工加入。主体码只是查找门店的入口，提交申请后仍须负责人或有权限的管理员批准。账号名额包含负责人，待审核申请不占正式名额；批准时服务端重新核对剩余名额。移除成员只解除门店访问，不删除个人会员、历史订单或账款，负责人不能在这里移除。</p></el-collapse-item></el-collapse>
                <div class="entity-member-toolbar"><el-input v-model="memberKeyword" :prefix-icon="Search" clearable placeholder="搜索成员姓名、手机或会员编号" aria-label="搜索主体成员" /><el-radio-group v-model="memberStatus" aria-label="成员状态"><el-radio-button value="">全部</el-radio-button><el-radio-button value="pending">待审核<template v-if="pendingCount">（{{ pendingCount }}）</template></el-radio-button><el-radio-button value="active">已加入</el-radio-button></el-radio-group></div>
                <el-alert v-if="actionError" :title="actionError" :type="readRequired?'warning':'error'" :closable="false" show-icon class="coop-error"><template #default><el-button v-if="readRequired" link type="primary" :disabled="actionBusy||detailLoading" @click="loadDetail">核对最新成员状态</el-button></template></el-alert>
                <HsxTable :columns="memberColumns" :data="visibleMembers" row-key="member_id" empty-text="暂无符合条件的成员或申请">
                    <template #member="{row}"><div class="coop-cell"><strong>{{ row.name || row.nickname || '未填写姓名' }}</strong><small>{{ row.mobile || '未填写手机' }}</small></div></template>
                    <template #role="{row}"><el-tag :type="row.role==='owner'?'primary':'info'" effect="plain">{{ row.role==='owner'?'负责人':'成员' }}</el-tag></template>
                    <template #status="{row}"><el-tag :type="row.status==='active'?'success':row.status==='pending'?'warning':'info'" effect="plain">{{ memberStatusLabel(row.status) }}</el-tag></template>
                    <template #time="{row}"><div class="coop-cell"><span>{{ coopTime(row.create_time) }}</span><small v-if="row.reviewed_at">处理 {{ coopTime(row.reviewed_at) }}</small></div></template>
                    <template #actions="{row}"><div class="coop-actions"><template v-if="row.status==='pending'&&canReview"><el-button link type="primary" :disabled="actionBusy||detailLoading||readRequired" @click="review(row,true)">批准加入</el-button><el-button link :disabled="actionBusy||detailLoading||readRequired" @click="review(row,false)">拒绝</el-button></template><el-button v-else-if="row.status==='active'&&row.role!=='owner'&&canRemove" link type="danger" :disabled="actionBusy||detailLoading||readRequired" @click="remove(row)">移除成员</el-button><span v-else class="coop-muted">{{ row.role==='owner'?'负责人保留':'—' }}</span></div></template>
                </HsxTable>
            </template>
            <template #footer><el-button :disabled="actionBusy" @click="detailVisible=false">关闭</el-button></template>
        </HsxDrawer>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CopyDocument, Search } from '@element-plus/icons-vue'
import useUserStore from '@/stores/modules/user'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import CooperationFilter from './CooperationFilter.vue'
import MembershipSummary from './MembershipSummary.vue'
import { getEntities, getEntity, reviewEntityMember, removeEntityMember } from '../../api/entity'
import { normalizeEntityLevels, entitySeatText, entityMembershipState, filterEntityMembers, type EntityLevel } from '../../composables/entity'
import { hasLeadPagePermission } from '../../composables/navigation'
import { coopError, coopTime, useCooperationList, type CoopRow } from '../../composables/cooperation'

const user=useUserStore(),canDetail=computed(()=>hasLeadPagePermission(user,'hsx_lead_entity_detail')),canReview=computed(()=>canDetail.value&&hasLeadPagePermission(user,'hsx_lead_entity_review')),canRemove=computed(()=>canDetail.value&&hasLeadPagePermission(user,'hsx_lead_entity_remove'))
const levels=ref<EntityLevel[]>([]),levelFilter=ref('')
const {query,rows,total,loading,error,load,search:searchList,changePage}=useCooperationList(async(params)=>{const result=await getEntities(params);if(Array.isArray(result.data?.tiers))levels.value=normalizeEntityLevels(result.data.tiers);return result})
function search(patch:CoopRow={}){return searchList({...patch,level_code:levelFilter.value})}
function resetLevel(){levelFilter.value=''}
const columns=[{prop:'name',label:'门店主体 / 地区',slot:'entity',minWidth:210},{prop:'subject_code',label:'主体码',slot:'code',minWidth:150},{prop:'membership',label:'会员等级 / 有效期',slot:'membership',minWidth:210},{prop:'used_accounts',label:'账号名额（含负责人）',slot:'accounts',minWidth:170},{prop:'owner_name',label:'负责人',slot:'owner',minWidth:150},{prop:'actions',label:'操作',slot:'actions',width:150,fixed:'right' as const}]
const memberColumns=[{prop:'name',label:'成员',slot:'member',minWidth:180},{prop:'role',label:'身份',slot:'role',width:100},{prop:'status',label:'状态',slot:'status',width:110},{prop:'create_time',label:'申请 / 处理时间',slot:'time',minWidth:190},{prop:'actions',label:'操作',slot:'actions',width:180,fixed:'right' as const}]
const detailVisible=ref(false),detail=ref<CoopRow>({}),detailLoading=ref(false),detailError=ref(''),memberKeyword=ref(''),memberStatus=ref(''),actionBusy=ref(false),actionError=ref(''),readRequired=ref(false)
let detailGeneration=0,disposed=false,selectedId=0
const members=computed<CoopRow[]>(()=>Array.isArray(detail.value.members)?detail.value.members:[])
const visibleMembers=computed(()=>filterEntityMembers(members.value,memberKeyword.value,memberStatus.value))
const pendingCount=computed(()=>members.value.filter(row=>row.status==='pending').length)
function memberStatusLabel(status:string){return({active:'已加入',pending:'待审核',rejected:'已拒绝',removed:'已移除'} as Record<string,string>)[status]||'状态待核对'}
function expiry(membership:CoopRow={}){return Number(membership.expires_at)>0?`${coopTime(membership.expires_at)} 到期`:membership.active===true?'长期有效':'尚未开通'}
async function copyCode(value:string){try{await navigator.clipboard.writeText(value);ElMessage.success('主体码已复制')}catch{ElMessage.warning('复制未完成，请选中主体码手动复制')}}
async function open(row:CoopRow){if(!canDetail.value||actionBusy.value)return;selectedId=Number(row.id);detail.value={};detailVisible.value=true;memberKeyword.value='';memberStatus.value=Number(row.pending_count)>0?'pending':'';actionError.value='';readRequired.value=false;await loadDetail()}
async function loadDetail(){
    if(!selectedId||!detailVisible.value||!canDetail.value)return false
    const version=++detailGeneration,id=selectedId;detailLoading.value=true;detailError.value=''
    try{const result=await getEntity(id);if(disposed||version!==detailGeneration||id!==selectedId||!detailVisible.value)return false;const value=result.data;if(Number(value?.id)!==id||!Array.isArray(value.members)||!value.membership)throw new Error('主体与成员资料未完整返回，请核对配套后端');detail.value=value;readRequired.value=false;actionError.value='';return true}
    catch(cause){if(!disposed&&version===detailGeneration)detailError.value=coopError(cause,'主体成员读取失败，请重新读取');return false}
    finally{if(!disposed&&version===detailGeneration)detailLoading.value=false}
}
function clearDetail(){if(actionBusy.value)return;detailGeneration++;selectedId=0;detail.value={};detailError.value='';actionError.value='';readRequired.value=false}
async function memberAction(row:CoopRow,action:'approve'|'reject'|'remove'){
    if(actionBusy.value||detailLoading.value||readRequired.value||!detail.value.id||row.role==='owner')return
    if(action==='remove'?!canRemove.value:!canReview.value)return
    if(action==='remove'?row.status!=='active':row.status!=='pending')return
    const id=Number(detail.value.id),memberId=Number(row.member_id),name=row.name||row.nickname||row.mobile||'这位成员'
    if(!Number.isInteger(memberId)||memberId<=0)return
    actionBusy.value=true;actionError.value='';let submitted=false
    try{
        const title=action==='approve'?'批准加入门店':action==='reject'?'拒绝加入申请':'移除门店成员'
        const message=action==='approve'?`确认让“${name}”加入“${detail.value.name}”？该成员将共享门店权益并占用1个账号名额。批准时会重新核对剩余名额。`:action==='reject'?`确认拒绝“${name}”的加入申请？不会产生门店访问权限，也不会占用账号名额。`:`确认将“${name}”从“${detail.value.name}”移除？其门店访问权限将被解除，个人会员及历史订单、账款会保留。`
        await ElMessageBox.confirm(message,title,{confirmButtonText:action==='approve'?'确认批准':action==='reject'?'确认拒绝':'确认移除',cancelButtonText:'取消',type:action==='approve'?'info':'warning',distinguishCancelAndClose:true})
        submitted=true
        if(action==='remove')await removeEntityMember(id,memberId);else await reviewEntityMember(id,memberId,action==='approve')
        const refreshed=await loadDetail(),expected=action==='approve'?'active':action==='reject'?'rejected':'removed',persisted=members.value.find(item=>Number(item.member_id)===memberId)
        if(!refreshed||!persisted||persisted.status!==expected)throw new Error('操作结果尚未核实，请先重新读取成员状态，不要重复提交')
        ElMessage.success(action==='approve'?'成员已加入门店':action==='reject'?'已拒绝加入申请':'成员已移除，历史记录保留');await load()
    }catch(cause){if(cause!=='cancel'&&cause!=='close'){actionError.value=coopError(cause,'操作结果尚未核实，请重新读取成员状态');if(submitted)readRequired.value=true}}
    finally{actionBusy.value=false}
}
function review(row:CoopRow,approve:boolean){return memberAction(row,approve?'approve':'reject')}
function remove(row:CoopRow){return memberAction(row,'remove')}
onMounted(load);onBeforeUnmount(()=>{disposed=true;detailGeneration++})
</script>
<style scoped>
.entity-code-cell{display:flex;align-items:center;gap:8px;min-width:0;line-height:1.6}.entity-code-cell code,.entity-code-cell strong{overflow-wrap:anywhere;font-size:13px;font-variant-numeric:tabular-nums}.entity-pending{color:var(--el-color-warning)!important}.entity-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px 24px;padding:0 0 20px;border-bottom:1px solid var(--hsx-border-color,var(--el-border-color-lighter));font-size:13px}.entity-summary>div{display:flex;flex-direction:column;gap:7px;min-width:0}.entity-summary-label{font-size:12px;color:var(--hsx-text-secondary,var(--el-text-color-secondary))}.entity-help{margin:8px 0 20px}.entity-member-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px}.entity-member-toolbar>.el-input{flex:1 1 240px;max-width:360px}.entity-management .coop-cell{min-height:42px;justify-content:center}
@media(max-width:600px){.entity-summary{grid-template-columns:1fr}.entity-member-toolbar>.el-input{max-width:none}}
</style>
