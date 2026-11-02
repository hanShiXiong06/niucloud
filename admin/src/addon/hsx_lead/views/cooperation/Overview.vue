<template>
    <section>
        <header class="coop-head"><div><h2>协作概况</h2><p>先看待处理的交付、复核与提现；统计不包含客户向门店支付的货款。</p></div><el-button :icon="Refresh" :loading="loading" @click="load">刷新</el-button></header>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <el-alert v-if="setupMessage" :title="setupMessage" type="warning" :closable="false" show-icon class="coop-error"><template #default><el-button v-if="canConfig" link type="primary" @click="emit('navigate','settings')">去配置并确认规则</el-button></template></el-alert>
        <div class="coop-facts"><HsxStatCard title="协作线索" :value="value('leads_total','total')" :loading="loading" description="当前可见业务范围" /><HsxStatCard title="等待接单" :value="value('assigned')" :loading="loading" description="已选定门店，尚未接受" /><HsxStatCard title="跟进中" :value="value('following')" :loading="loading" description="待门店反馈成交结果" /><HsxStatCard title="成交待付佣" :value="value('unpaid')" :loading="loading" description="尚不计入发布方收益" /></div>
        <div class="coop-surface"><div class="coop-section-caption"><h3>待办处理</h3><span class="coop-muted">点击进入对应列表，核对后再操作</span></div><div class="coop-overview-todos"><button type="button" @click="emit('navigate','leads')"><b>跟进交付</b><span>{{ value('assigned') }} 条待接单</span></button><button v-if="canReview" type="button" @click="emit('navigate','reviews')"><b>客服抽检与复核</b><span>{{ value('pending_reviews') }} 条待处理</span></button><button v-if="canFinance" type="button" @click="emit('navigate','finance')"><b>提现审核与打款</b><span>{{ value('pending_withdrawals') }} 笔待处理</span></button></div></div>
        <div class="coop-surface" style="margin-top:14px"><div class="coop-section-caption"><h3>规则与流程</h3><el-button v-if="canConfig" link type="primary" @click="emit('navigate','settings')">配置业务规则</el-button></div><ol class="coop-overview-flow"><li>商户发布客户需求，按地区选择一家交付门店。</li><li>门店接单联系客户，反馈“成交”或“未成交”。</li><li>成交后支付协作佣金；真实确认到账才计入发布方收益。</li><li>发布方申请提现，平台核对后实际转账并记录凭证。</li></ol><el-alert title="靠谱认证和商户等级是不同维度；连续未成交只作关注线索，违规必须由客服核实，不能自动停用商户。" type="info" show-icon closable /></div>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import HsxStatCard from '@/addon/hsx_components/components/HsxStatCard/index.vue'
import { getCooperationConfig, getCooperationDashboard } from '../../api/cooperation'
import { coopError, type CoopRow } from '../../composables/cooperation'
const props=defineProps<{ canFinance: boolean; canReview: boolean; canConfig: boolean }>()
const emit = defineEmits<{ (event: 'navigate', value: string): void; (event: 'scope', value: CoopRow): void }>()
const dashboard=ref<CoopRow>({}),setup=ref<CoopRow|null>(null),setupError=ref(''),loading=ref(false),error=ref('');let sequence=0,disposed=false
const setupMessage=computed(()=>{if(!props.canConfig)return'';if(setupError.value)return'业务配置状态暂未读取，请进入配置页核对；不影响查看已存在的协作记录。';if(!setup.value)return'';if(!setup.value.enabled)return'商户协作尚未启用：请先设置发布权益、收费规则和等级，再开启业务。';if(!setup.value.fee_confirmed)return'平台费规则尚未确认：商户暂不能创建佣金支付单，请先核对并确认。';return''})
async function loadSetup(token:number){if(!props.canConfig)return;setupError.value='';try{const result=await getCooperationConfig();if(token===sequence&&!disposed)setup.value=result.data||{}}catch(cause){if(token===sequence&&!disposed)setupError.value=coopError(cause)}}
function value(key:string,alternate=''){const data=dashboard.value;const raw=data[key]??data.counts?.[key]??(alternate?data[alternate]??data.counts?.[alternate]:undefined);return raw===undefined||raw===null?'—':Number(raw)}
async function load(){const token=++sequence;loading.value=true;error.value='';void loadSetup(token);try{const result=await getCooperationDashboard();if(token===sequence&&!disposed){dashboard.value=result.data||{};if(typeof dashboard.value.viewer_scope?.all_data==='boolean')emit('scope',dashboard.value.viewer_scope)}}catch(cause){if(token===sequence&&!disposed){dashboard.value={};error.value=coopError(cause,'概况加载失败，请核对权限并重试。')}}finally{if(token===sequence&&!disposed)loading.value=false}}
onMounted(load);onBeforeUnmount(()=>{disposed=true;sequence++})
</script>
<style scoped>.coop-overview-todos{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.coop-overview-todos button{border:1px solid var(--el-border-color-lighter);background:var(--hsx-bg-surface,var(--el-bg-color));padding:15px;border-radius:6px;text-align:left;cursor:pointer;color:var(--el-text-color-primary)}.coop-overview-todos b{display:block;font-size:14px;margin-bottom:7px}.coop-overview-todos span{color:var(--el-text-color-secondary);font-size:12px}.coop-overview-todos button:hover{border-color:var(--el-color-primary-light-5)}.coop-overview-todos button:focus-visible{outline:2px solid var(--el-color-primary);outline-offset:2px}.coop-overview-flow{padding-left:20px;margin:0 0 16px;font-size:13px;line-height:2.2;color:var(--el-text-color-regular)}@media(max-width:1000px){.coop-overview-todos{grid-template-columns:1fr}}</style>
