<template>
    <section class="promotion-relations" aria-label="邀请关系与付费情况">
        <div class="relations-view"
            ><div><strong>邀请关系</strong><span>按关系展开，或按付费情况检索</span></div
            ><el-radio-group v-model="view" aria-label="邀请关系展示方式"
                ><el-radio-button label="tree">关系树</el-radio-button
                ><el-radio-button label="list">明细列表</el-radio-button></el-radio-group
            ></div
        >
        <PromotionRelationTree v-if="view==='tree'" ref="treeRef" :initial-inviter="props.initialInviter" />
        <template v-else>
            <div class="relations-summary" aria-live="polite" :aria-busy="loading">
                <div
                    ><span>邀请关系</span><strong>{{ metric('relation_count') }}<small>人</small></strong></div
                >
                <div
                    ><span>已付费会员</span><strong>{{ metric('paid_count') }}<small>人</small></strong></div
                >
                <div
                    ><span>会员服务费净收</span><strong>{{ amount('service_net') }}</strong></div
                >
                <div
                    ><span>有效推广佣金</span><strong>{{ amount('promotion_total') }}</strong></div
                >
            </div>
            <div class="relations-filter coop-surface">
                <form class="relations-search" role="search" aria-label="搜索邀请关系" @submit.prevent="search">
                    <el-input
                        v-model="query.keyword"
                        clearable
                        :prefix-icon="Search"
                        placeholder="被邀请人：编号、姓名、手机或门店"
                        aria-label="被邀请人搜索"
                        @clear="search"
                    />
                    <el-input
                        v-model="query.inviter_keyword"
                        clearable
                        :prefix-icon="Search"
                        placeholder="邀请人：编号、姓名、手机或门店"
                        aria-label="邀请人搜索"
                        @clear="search"
                    />
                    <el-select
                        v-model="query.payment_state"
                        clearable
                        placeholder="全部付费情况"
                        aria-label="付费情况"
                        @change="search"
                        ><el-option
                            v-for="item in filters.payment_states"
                            :key="item.value"
                            :value="item.value"
                            :label="item.label"
                    /></el-select>
                    <el-select
                        v-model="query.membership_state"
                        clearable
                        placeholder="全部会员状态"
                        aria-label="会员状态"
                        @change="search"
                        ><el-option
                            v-for="item in filters.membership_states"
                            :key="item.value"
                            :value="item.value"
                            :label="item.label"
                    /></el-select>
                    <div class="relations-search-actions"
                        ><el-button type="primary" native-type="submit" :loading="loading">查询</el-button
                        ><el-button :disabled="loading" @click="reset">重置</el-button></div
                    >
                </form>
                <div class="relations-scope">
                    <span>{{ loading ? '正在查询…' : `共 ${total} 条关系` }}</span>
                    <el-tag v-if="query.inviter_member_id" closable effect="plain" @close="clearInviter"
                        >{{ selectedInviter }}的直属邀请</el-tag
                    >
                    <span class="relations-scope-note">统计按当前筛选；会员权益与本人付费分别展示</span>
                </div>
            </div>
            <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error"
                ><el-button link type="primary" @click="load">重新加载</el-button></el-alert
            >
            <div class="coop-surface relations-table">
                <HsxTable
                    :columns="columns"
                    :data="rows"
                    :loading="loading"
                    row-key="member.member_id"
                    :empty-text="error ? '邀请关系未加载，请重新读取' : hasFilters ? '没有符合条件的邀请关系，可重置筛选后查看' : '暂未建立邀请关系'"
                >
                    <template #relation="{row}">
                        <div class="relations-chain">
                            <div class="coop-cell"
                                ><el-button
                                    link
                                    type="primary"
                                    class="relations-person"
                                    @click="showInviter(row.inviter)"
                                    >{{ row.inviter?.name || '原邀请人不可用' }}</el-button
                                ><small
                                    >{{ row.inviter?.member_no || (row.inviter?.member_id ? `UID ${row.inviter.member_id}` : '—') }}</small
                                ><small>直属邀请 {{ row.inviter?.direct_invite_count ?? '—' }} 人</small></div
                            >
                            <el-icon class="relations-arrow" aria-label="邀请了"><Right /></el-icon>
                            <div class="coop-cell"
                                ><strong>{{ row.merchant?.name || row.member?.name || '本站会员' }}</strong
                                ><small v-if="row.merchant?.name">{{ row.member?.name }}</small
                                ><small
                                    >{{ row.member?.member_no || `UID ${row.member?.member_id || '—'}` }} ·
                                    {{ row.member?.mobile_masked || '未留电话' }}</small
                                ></div
                            >
                        </div>
                    </template>
                    <template #membership="{row}"
                        ><div class="coop-cell relations-status"
                            ><el-tag
                                size="small"
                                :type="tone(row.membership?.status)"
                                effect="plain"
                                >{{ row.membership?.status_label || '待核对' }}</el-tag
                            ><strong v-if="row.membership?.level_name">{{ row.membership.level_name }}</strong
                            ><small v-if="row.merchant?.shared">共享所属门店权益</small
                            ><small v-if="row.membership?.expires_at"
                                >到期 {{ date(row.membership.expires_at) }}</small
                            ></div
                        ></template
                    >
                    <template #payment="{row}"
                        ><div class="coop-cell relations-status"
                            ><el-tag
                                size="small"
                                :type="tone(row.payment?.status)"
                                effect="plain"
                                >{{ row.payment?.status_label || '待核对' }}</el-tag
                            ><strong>实付净额 {{ fee(row.payment?.paid_net) }}</strong
                            ><small>其中服务费 {{ fee(row.payment?.service_net) }}</small
                            ><small v-if="Number(row.payment?.refunded_total)>0"
                                >已退款 {{ coopMoney(row.payment.refunded_total) }}</small
                            ><small v-if="row.payment?.refund_pending">退款处理中，金额待核对</small
                            ><small v-if="row.payment?.unpaid_order_count"
                                >待支付 {{ row.payment.unpaid_order_count }} 笔</small
                            ></div
                        ></template
                    >
                    <template #promotion="{row}"
                        ><div class="coop-cell"
                            ><strong class="relations-money">{{ coopMoney(row.promotion?.total) }}</strong
                            ><small>已入账 {{ coopMoney(row.promotion?.settled) }}</small
                            ><small v-if="Number(row.promotion?.pending)>0"
                                >待结算 {{ coopMoney(row.promotion.pending) }}</small
                            ><small v-if="Number(row.promotion?.held)>0"
                                >待核对 {{ coopMoney(row.promotion.held) }}</small
                            ><small v-if="Number(row.promotion?.cancelled)>0"
                                >已取消 {{ coopMoney(row.promotion.cancelled) }}</small
                            ><small v-if="row.promotion?.review_required">佣金记录待核对</small></div
                        ></template
                    >
                    <template #time="{row}"
                        ><div class="coop-cell"
                            ><small>注册 {{ date(row.create_time) }}</small
                            ><small v-if="row.bound_at">关联 {{ date(row.bound_at) }}</small
                            ><small v-if="row.payment?.last_paid_at"
                                >付款 {{ date(row.payment.last_paid_at) }}</small
                            ></div
                        ></template
                    >
                    <template #action="{row}"
                        ><el-button link type="primary" @click="showInviter(row.member)"
                            >查看 TA 邀请的</el-button
                        ></template
                    >
                </HsxTable>
                <div class="coop-pagination"
                    ><HsxPagination
                        :current-page="query.page"
                        :page-size="query.limit"
                        :total="total"
                        :disabled="loading"
                        @change="changePage"
                /></div>
            </div>
            <el-collapse class="relations-help"
                ><el-collapse-item title="付费与佣金如何核对？" name="definition"
                    ><p
                        >明细列表按直属邀请关系展示；多级关系请切换关系树。店员加入共享主体权益，不作为推广入驻；共享会员不代表该账号本人已付款。</p
                    ><p
                        >会员服务费净收扣除已退款部分，不含押金、经验费。推广佣金分别展示待结算、已入账和待核对；已取消金额不计入有效佣金，已入账不代表已提现。</p
                    ></el-collapse-item
                ></el-collapse
            >
        </template>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import PromotionRelationTree from './PromotionRelationTree.vue'
import { Right, Search } from '@element-plus/icons-vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import { getPromotionRelations } from '../../api/promotion'
import { coopError, coopMoney, type CoopRow } from '../../composables/cooperation'
import { timestampParts } from '../../composables/useLeadUi'
const rows=ref<CoopRow[]>([]),total=ref(0),loading=ref(false),error=ref(''),summary=ref<CoopRow|null>(null)
const props=defineProps<{initialInviter?:CoopRow|null}>()
const view=ref<'tree'|'list'>('tree'),treeRef=ref<InstanceType<typeof PromotionRelationTree>>()
const query=reactive<CoopRow>({keyword:'',inviter_keyword:'',inviter_member_id:'',payment_state:'',membership_state:'',page:1,limit:20})
const filters=reactive<CoopRow>({payment_states:[],membership_states:[]}),selectedInviter=ref('')
const hasFilters=computed(()=>!!(query.keyword||query.inviter_keyword||query.inviter_member_id||query.payment_state||query.membership_state))
let sequence=0,disposed=false
async function load(){
    const token=++sequence;loading.value=true;error.value=''
    try{
        const result=await getPromotionRelations({...query}),page=result.data
        if(disposed||token!==sequence)return
        if(!Array.isArray(page?.data)||!page.summary||!Array.isArray(page.filters?.payment_states)||!Array.isArray(page.filters?.membership_states))throw new Error('邀请关系资料未完整返回，请核对配套后端')
        rows.value=page.data;total.value=Number(page.total||0);summary.value=page.summary;filters.payment_states=page.filters.payment_states;filters.membership_states=page.filters.membership_states
    }catch(cause){if(!disposed&&token===sequence){rows.value=[];total.value=0;summary.value=null;error.value=coopError(cause,'邀请关系读取失败，请重试')}}finally{if(!disposed&&token===sequence)loading.value=false}
}
function search(){query.page=1;return load()}
function changePage(value:{page:number;limit:number}){Object.assign(query,value);return load()}
function reset(){Object.assign(query,{keyword:'',inviter_keyword:'',inviter_member_id:'',payment_state:'',membership_state:'',page:1});selectedInviter.value='';return load()}
function showInviter(member:CoopRow){if(!Number.isSafeInteger(Number(member?.member_id))||Number(member.member_id)<=0)return;query.inviter_member_id=Number(member.member_id);query.inviter_keyword='';query.keyword='';query.payment_state=member.payment_state==='paid'?'paid':'';selectedInviter.value=String(member.name||member.member_no||`会员 #${member.member_id}`);return search()}
function clearInviter(){query.inviter_member_id='';selectedInviter.value='';return search()}
function metric(key:string){return summary.value?String(summary.value[key]??'—'):'—'}
function amount(key:string){return summary.value?summary.value[key]===null?'待核对':coopMoney(summary.value[key]):'—'}
function fee(value:unknown){return value===null?'待核对':coopMoney(value)}
function date(value:unknown){return timestampParts(value)?.full||'未记录'}
function tone(value:unknown):'success'|'warning'|'info'|'danger'{return ['paid','active'].includes(String(value))?'success':String(value)==='review'?'warning':['refunded','expired'].includes(String(value))?'info':'info'}
const columns=[{prop:'member',label:'邀请人 → 被邀请人 / 门店',slot:'relation',minWidth:350},{prop:'membership',label:'当前会员权益',slot:'membership',minWidth:175},{prop:'payment',label:'本人实际付费',slot:'payment',minWidth:200},{prop:'promotion',label:'给邀请人的佣金',slot:'promotion',minWidth:155},{prop:'create_time',label:'时间',slot:'time',minWidth:190},{prop:'action',label:'关系查询',slot:'action',width:145,fixed:'right' as const}]
function refresh(){return view.value==='tree'?treeRef.value?.refresh():load()}
const busy=computed(()=>view.value==='tree'?!!treeRef.value?.loading:loading.value)
watch(view,value=>{sequence++;loading.value=false;if(value==='list'){if(props.initialInviter?.member_id)void showInviter(props.initialInviter);else void load()}})
watch(()=>props.initialInviter,value=>{if(view.value==='list'){if(value?.member_id)void showInviter(value);else void reset()}})
defineExpose({refresh,loading:busy})
onBeforeUnmount(()=>{disposed=true;sequence++})
</script>
<style scoped>
.relations-view{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:4px 0 16px}.relations-view>div:first-child{display:flex;flex-direction:column;gap:5px}.relations-view strong{font-size:16px;font-weight:600}.relations-view span{font-size:12px;color:var(--el-text-color-secondary)}
.promotion-relations{min-width:0}.relations-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));background:var(--el-bg-color);border:1px solid var(--el-border-color-lighter);border-radius:8px;margin:8px 0 16px;padding:16px 0}.relations-summary>div{padding:0 20px;border-right:1px solid var(--el-border-color-lighter);min-width:0}.relations-summary>div:last-child{border:0}.relations-summary span{display:block;font-size:12px;color:var(--el-text-color-secondary);margin-bottom:8px}.relations-summary strong{font-size:23px;font-weight:650;line-height:1.2;color:var(--el-text-color-primary);overflow-wrap:anywhere;font-variant-numeric:tabular-nums}.relations-summary strong small{font-size:12px;font-weight:400;color:var(--el-text-color-secondary);padding-left:6px}.relations-filter{margin-bottom:14px}.relations-search{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.relations-search>.el-input{flex:1 1 245px;max-width:340px;min-width:210px}.relations-search>.el-select{width:155px}.relations-search-actions{display:flex;gap:8px;margin-left:auto}.relations-search-actions .el-button+.el-button{margin-left:0}.relations-scope{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-top:14px;font-size:12px;color:var(--el-text-color-regular);min-height:24px}.relations-scope-note{margin-left:auto;color:var(--el-text-color-secondary)}.relations-chain{display:grid;grid-template-columns:minmax(0,.9fr) 20px minmax(0,1.15fr);align-items:center;gap:12px;padding:4px 0}.relations-person.el-button{font-size:13px;font-weight:600;height:auto;align-self:flex-start;padding:0;white-space:normal;text-align:left;line-height:1.55;overflow-wrap:anywhere}.relations-person :deep(span){white-space:normal;overflow-wrap:anywhere;text-align:left}.relations-arrow{color:var(--el-text-color-placeholder)}.relations-status{align-items:flex-start}.relations-status .el-tag{align-self:flex-start;max-width:100%;white-space:normal;height:auto;min-height:22px;line-height:1.5;padding:2px 7px}.relations-money{font-variant-numeric:tabular-nums;color:var(--el-color-primary)}.relations-help{margin-top:14px;padding:0 16px;border:1px solid var(--el-border-color-lighter);border-radius:8px;background:var(--el-bg-color)}.relations-help p{font-size:12px;line-height:1.8;color:var(--el-text-color-secondary);margin:0 0 8px}.relations-help :deep(.el-collapse-item__header){font-size:13px;color:var(--el-text-color-secondary)}
@media(max-width:1100px){.relations-summary>div{padding:0 14px}.relations-summary strong{font-size:21px}.relations-search>.el-input{max-width:none;flex:1 1 280px}.relations-search-actions{margin-left:0}.relations-scope-note{margin-left:0}}
@media(max-width:650px){.relations-summary{grid-template-columns:repeat(2,minmax(0,1fr));row-gap:20px}.relations-summary>div:nth-child(2){border:0}.relations-search>.el-input{flex-basis:100%}.relations-search>.el-select{flex:1;min-width:140px}}
</style>
