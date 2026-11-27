<template>
    <section class="promotion-inviters" aria-label="推广人汇总">
        <div class="inviters-filter coop-surface">
            <form class="inviters-search" role="search" aria-label="搜索推广人" @submit.prevent="search">
                <el-input
                    v-model="query.keyword"
                    clearable
                    :prefix-icon="Search"
                    placeholder="搜索推广人编号、姓名、手机号或 ID"
                    aria-label="推广人搜索"
                    @clear="search"
                />
                <el-select
                    v-model="query.role"
                    clearable
                    placeholder="全部推广身份"
                    aria-label="推广人身份"
                    @change="search"
                    ><el-option label="普通会员" value="normal" /><el-option label="服务商" value="provider"
                /></el-select>
                <div class="inviters-search-actions"
                    ><el-button type="primary" native-type="submit" :loading="loading">查询</el-button
                    ><el-button :disabled="loading" @click="reset">重置</el-button></div
                >
            </form>
            <div class="inviters-scope"
                ><span>{{ loading ? '正在查询…' : error ? '读取失败，未返回统计' : `共 ${total} 位会员` }}</span
                ><el-tooltip
                    placement="top"
                    effect="light"
                    content="邀请和付费人数按当前直属关系统计；收益按原推广人累计，不会因更换邀请人转移。店员共享权益不算本人付费，已入账不代表已提现。"
                    ><el-button link type="info">统计说明</el-button></el-tooltip
                ></div
            >
        </div>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error"
            ><el-button link type="primary" @click="load">重新读取</el-button></el-alert
        >
        <div class="coop-surface">
            <HsxTable
                :columns="columns"
                :data="rows"
                :loading="loading"
                row-key="member_id"
                :empty-text="error ? '推广人汇总未加载，请重试' : hasFilters ? '没有符合条件的会员，可重置筛选后查看' : '暂无会员'"
            >
                <template #member="{row}"
                    ><div class="coop-cell inviters-person"
                        ><strong>{{ row.name || '本站会员' }}</strong
                        ><small>编号 {{ row.member_no || '尚未分配' }}</small
                        ><small v-if="row.mobile_masked">{{ row.mobile_masked }}</small></div
                    ></template
                >
                <template #role="{row}"
                    ><el-tag
                        size="small"
                        :type="row.role==='provider'?'warning':'info'"
                        effect="plain"
                        >{{ row.role==='provider'?'服务商':'普通会员' }}</el-tag
                    ></template
                >
                <template #invites="{row}"
                    ><el-button link type="primary" class="inviters-number" @click="showRelations(row)"
                        >{{ count(row.direct_invite_count) }}<span>人</span></el-button
                    ></template
                >
                <template #paid="{row}"
                    ><div class="coop-cell"
                        ><el-button link type="primary" class="inviters-number" @click="showRelations(row,true)"
                            >{{ count(row.paid_invitee_count) }}<span>人</span></el-button
                        ><small v-if="Number(row.review_invitee_count)>0"
                            >{{ row.review_invitee_count }} 人付费待核对</small
                        ></div
                    ></template
                >
                <template #pending="{row}"
                    ><span class="inviters-money">{{ money(row.promotion?.pending) }}</span></template
                >
                <template #settled="{row}"
                    ><div class="coop-cell"
                        ><strong class="inviters-money is-settled">{{ money(row.promotion?.settled) }}</strong
                        ><small v-if="row.promotion?.review_required">部分奖励记录待核对</small></div
                    ></template
                >
                <template #held="{row}"
                    ><span class="inviters-money">{{ money(row.promotion?.held) }}</span></template
                >
                <template #cancelled="{row}"
                    ><span class="inviters-money is-muted">{{ money(row.promotion?.cancelled) }}</span></template
                >
                <template #actions="{row}"
                    ><el-button v-if="canViewRewards" link type="primary" @click="showRewards(row)">查看台账</el-button
                    ><span v-else class="coop-muted">仅关系查看权限</span></template
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
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Search } from '@element-plus/icons-vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import { getPromotionInviters } from '../../api/promotion'
import { coopError, coopMoney, type CoopRow } from '../../composables/cooperation'

const props = withDefaults(defineProps<{ canViewRewards?: boolean }>(), { canViewRewards: false })
const emit = defineEmits<{ (event:'relations', member:CoopRow):void; (event:'rewards', member:CoopRow):void }>()
const rows=ref<CoopRow[]>([]),total=ref(0),loading=ref(false),error=ref('')
const query=reactive<CoopRow>({keyword:'',role:'',provider_member_id:'',page:1,limit:20})
const hasFilters=computed(()=>!!(query.keyword||query.role||query.provider_member_id))
let sequence=0,disposed=false
const moneyPattern=/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/
const validCount=(value:unknown)=>typeof value==='number'&&Number.isSafeInteger(value)&&value>=0
function validRow(row:CoopRow){return row&&validCount(row.member_id)&&row.member_id>0&&['normal','provider'].includes(row.role)&&['direct_invite_count','paid_invitee_count','review_invitee_count'].every(key=>validCount(row[key]))&&row.promotion&&['pending','settled','held','cancelled','total'].every(key=>row.promotion[key]===null||moneyPattern.test(String(row.promotion[key])))&&typeof row.promotion.review_required==='boolean'}
async function load(){
    const token=++sequence;loading.value=true;error.value=''
    try{
        const result=await getPromotionInviters({...query}),page=result.data
        if(disposed||token!==sequence)return
        if(!Array.isArray(page?.data)||!validCount(Number(page.total))||!page.data.every(validRow))throw new Error('推广人汇总未完整返回，请核对配套后端')
        rows.value=page.data;total.value=Number(page.total)
    }catch(cause){if(!disposed&&token===sequence){rows.value=[];total.value=0;error.value=coopError(cause,'推广人汇总读取失败，请重试')}}finally{if(!disposed&&token===sequence)loading.value=false}
}
function search(){query.page=1;return load()}
function reset(){Object.assign(query,{keyword:'',role:'',provider_member_id:'',page:1});return load()}
function changePage(value:{page:number;limit:number}){Object.assign(query,value);return load()}
function showRelations(row:CoopRow,paidOnly=false){if(!validCount(row?.member_id)||row.member_id<=0)return;emit('relations',{member_id:row.member_id,name:row.name,member_no:row.member_no,...(paidOnly?{payment_state:'paid'}:{})})}
function showRewards(row:CoopRow){if(!props.canViewRewards||!validCount(row?.member_id)||row.member_id<=0)return;emit('rewards',{member_id:row.member_id,name:row.name,member_no:row.member_no})}
function count(value:unknown){return validCount(value)?String(value):'待核对'}
function money(value:unknown){return value===null||!moneyPattern.test(String(value))?'待核对':coopMoney(value)}
const columns=[{prop:'member_id',label:'推广会员',slot:'member',minWidth:180},{prop:'role',label:'推广身份',slot:'role',minWidth:110},{prop:'direct_invite_count',label:'直属邀请',slot:'invites',minWidth:110},{prop:'paid_invitee_count',label:'其中有效付费',slot:'paid',minWidth:155},{prop:'pending',label:'待结算',slot:'pending',minWidth:115},{prop:'settled',label:'已结算',slot:'settled',minWidth:155},{prop:'held',label:'暂缓 / 待核对',slot:'held',minWidth:135},{prop:'cancelled',label:'取消 / 已退回',slot:'cancelled',minWidth:135},{prop:'actions',label:'明细',slot:'actions',width:130,fixed:'right' as const}]
defineExpose({refresh:load,loading})
onMounted(()=>void load());onBeforeUnmount(()=>{disposed=true;sequence++})
</script>
<style scoped>
.promotion-inviters{min-width:0}.inviters-filter{margin:8px 0 14px}.inviters-search{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.inviters-search>.el-input{flex:1 1 300px;max-width:420px}.inviters-search>.el-select{width:160px}.inviters-search-actions{display:flex;gap:8px;margin-left:auto}.inviters-search-actions .el-button+.el-button{margin-left:0}.inviters-scope{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;font-size:12px;color:var(--el-text-color-secondary)}.inviters-number.el-button{height:auto;line-height:1.5;padding:0;font-size:16px;font-weight:600;align-self:flex-start;font-variant-numeric:tabular-nums}.inviters-number span{font-size:12px;font-weight:400;margin-left:4px}.inviters-money{font-variant-numeric:tabular-nums;white-space:nowrap}.inviters-money.is-settled{color:var(--el-color-success)}.inviters-money.is-muted{color:var(--el-text-color-secondary)}.promotion-inviters .el-tag{width:auto;max-width:100%;align-self:flex-start}.inviters-person strong{overflow-wrap:anywhere}
@media(max-width:900px){.inviters-search-actions{margin-left:0}.inviters-search>.el-input{max-width:none}}
</style>
