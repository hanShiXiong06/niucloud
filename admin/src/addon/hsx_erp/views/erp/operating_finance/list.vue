<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div><div class="text-page-title">经营收支</div><div class="mt-1 text-sm text-gray-500">记录房租、水电、办公、工资及服务收入；待结算进入应收应付，现结进入资金流水。</div></div>
                <div class="flex gap-2"><el-button :icon="Refresh" :loading="loading" @click="loadData">刷新</el-button><el-button type="primary" :icon="Plus" @click="openCreate">记一笔经营收支</el-button></div>
            </div>
            <div class="summary-grid mt-5">
                <div class="summary-card income"><span>经营收入</span><strong>¥{{ money(summary.income) }}</strong></div>
                <div class="summary-card expense"><span>经营支出</span><strong>¥{{ money(summary.expense) }}</strong></div>
                <div class="summary-card pending"><span>待收待付</span><strong>¥{{ money(summary.unsettled) }}</strong></div>
                <div class="summary-card profit"><span>经营净额</span><strong>¥{{ money(Number(summary.income)-Number(summary.expense)) }}</strong></div>
            </div>
            <el-tabs v-model="query.direction" class="mt-5" @tab-change="resetLoad">
                <el-tab-pane label="全部" name="" /><el-tab-pane label="收入" name="income" /><el-tab-pane label="支出" name="expense" />
            </el-tabs>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <el-input v-model.trim="query.keyword" placeholder="往来主体/类型/单号/说明" clearable class="!w-[250px]" @keyup.enter="resetLoad" />
                <el-select v-model="query.category_key" placeholder="收支类型" clearable class="!w-[160px]"><el-option v-for="item in operatingCategories" :key="item.key" :label="item.name" :value="item.key" /></el-select>
                <el-select v-model="query.status" placeholder="结算状态" clearable class="!w-[130px]"><el-option label="待结算" value="pending" /><el-option label="部分结算" value="partial" /><el-option label="已结清" value="settled" /></el-select>
                <el-button type="primary" @click="resetLoad">查询</el-button><el-button @click="resetFilter">重置</el-button>
            </div>
            <el-table :data="list" v-loading="loading" size="large" empty-text="暂无经营收支">
                <el-table-column label="收支事项" min-width="220"><template #default="{row}"><div class="font-semibold text-gray-800">{{ row.category_name || '-' }}</div><div class="mt-1 text-xs text-gray-400">{{ row.source_no || row.finance_no }}</div></template></el-table-column>
                <el-table-column prop="party_name" label="往来主体" min-width="160" show-overflow-tooltip />
                <el-table-column label="方向" width="90" align="center"><template #default="{row}"><el-tag :type="row.direction==='income'?'success':'warning'" effect="light">{{ row.direction==='income'?'收入':'支出' }}</el-tag></template></el-table-column>
                <el-table-column label="金额" width="140" align="right"><template #default="{row}"><strong :class="row.direction==='income'?'text-green-600':'text-orange-600'">{{ row.direction==='income'?'+':'-' }}¥{{ money(row.amount) }}</strong></template></el-table-column>
                <el-table-column label="结算" width="170"><template #default="{row}"><div>{{ statusLabel(row.status) }}</div><div class="mt-1 text-xs text-gray-400">已结 ¥{{ money(row.settled_amount) }} · 剩余 ¥{{ money(row.remain_amount) }}</div></template></el-table-column>
                <el-table-column label="经营说明" min-width="260" show-overflow-tooltip><template #default="{row}">{{ row.business_reason || row.remark || '-' }}</template></el-table-column>
                <el-table-column label="发生时间" width="170"><template #default="{row}">{{ formatTime(row.occurred_at) }}</template></el-table-column>
                <el-table-column label="操作" width="100" fixed="right"><template #default="{row}"><el-button v-if="Number(row.remain_amount)>0" link type="primary" @click="goFinance(row)">去{{ row.direction==='income'?'收款':'付款' }}</el-button><span v-else class="text-gray-400">已闭环</span></template></el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end"><el-pagination v-model:current-page="query.page" :page-size="query.limit" :total="total" layout="total, prev, pager, next" @current-change="loadData" /></div>
        </el-card>

        <el-dialog v-model="visible" title="记一笔经营收支" width="620px" destroy-on-close>
            <el-alert title="经营收支不计入具体设备成本；整备、维修某台设备请从库存中心登记。" type="info" :closable="false" class="mb-4" />
            <el-form :model="form" label-width="100px">
                <el-form-item label="收支方向" required><el-radio-group v-model="form.direction" @change="onDirectionChange"><el-radio-button value="expense">经营支出</el-radio-button><el-radio-button value="income">经营收入</el-radio-button></el-radio-group></el-form-item>
                <el-form-item label="收支类型" required><el-select v-model="form.category_key" class="w-full" placeholder="请选择"><el-option v-for="item in formCategories" :key="item.key" :label="item.name" :value="item.key" /></el-select></el-form-item>
                <el-form-item label="往来主体" required><ErpPartySelect v-model="form.party_id" v-model:party-name="form.party_name" party-type="all" placeholder="选择收款方或付款方" /></el-form-item>
                <el-form-item label="金额" required><el-input-number v-model="form.amount" :min="0" :precision="2" :controls="false" class="!w-[220px]" /></el-form-item>
                <el-form-item label="结算方式" required><el-radio-group v-model="form.settlement_mode"><el-radio value="pending">转财务结算</el-radio><el-radio value="immediate">已经现场收付</el-radio></el-radio-group><div class="w-full text-xs text-gray-400">{{ form.settlement_mode==='pending'?'保存后进入应收/应付，由财务付款、收款或折账。':'必须选择账户，立即形成实际资金流水。' }}</div></el-form-item>
                <el-form-item v-if="form.settlement_mode==='immediate'" label="资金账户" required><el-select v-model="form.capital_account_id" class="w-full" placeholder="请选择"><el-option v-for="item in enabledAccounts" :key="item.id" :label="`${item.account_name}（余额 ¥${money(item.balance)}）`" :value="item.id" /></el-select></el-form-item>
                <el-form-item label="发生时间"><el-date-picker v-model="form.occurred_at" type="datetime" value-format="X" class="w-full" /></el-form-item>
                <el-form-item label="说明" required><el-input v-model.trim="form.remark" type="textarea" :rows="3" placeholder="例如：7月份门店房租，合同号……" /></el-form-item>
                <el-form-item label="资金凭证"><ErpFinanceVoucherUpload v-model="form.voucher_urls" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="visible=false">取消</el-button><el-button type="primary" :loading="saving" @click="submit">{{ form.settlement_mode==='pending'?'确认并生成应收应付':'确认现结并记账' }}</el-button></template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh } from '@element-plus/icons-vue'
import { createErpOperatingFinance, getErpOperatingFinanceList } from '@/addon/hsx_erp/api/erp'
import { getErpFinanceCategories } from '@/addon/hsx_erp/api/config'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import ErpPartySelect from '@/addon/hsx_erp/components/ErpPartySelect.vue'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
const router=useRouter(),loading=ref(false),saving=ref(false),visible=ref(false),list=ref<any[]>([]),total=ref(0),categories=ref<any[]>([]),accounts=ref<any[]>([]),summary=reactive({income:0,expense:0,unsettled:0})
const query=reactive({direction:'',status:'',category_key:'',keyword:'',page:1,limit:15})
const form=reactive<any>({direction:'expense',category_key:'',party_id:null,party_name:'',amount:0,settlement_mode:'pending',capital_account_id:null,occurred_at:'',remark:'',voucher_urls:''})
const operatingCategories=computed(()=>categories.value.filter(row=>row.scope==='operating'&&Number(row.enabled??1)===1)),formCategories=computed(()=>operatingCategories.value.filter(row=>row.direction===form.direction)),enabledAccounts=computed(()=>accounts.value.filter(row=>Number(row.status)===1))
const money=(v:any)=>Number(v||0).toFixed(2),formatTime=(v:any)=>v?new Date(Number(v)*1000).toLocaleString():'-',statusLabel=(s:string)=>({pending:'待结算',partial:'部分结算',settled:'已结清',void:'已作废'}[s]||s||'-')
async function loadData(){loading.value=true;try{const res:any=await getErpOperatingFinanceList(query);list.value=res?.data?.data||[];total.value=Number(res?.data?.total||0);Object.assign(summary,res?.data?.summary||{})}finally{loading.value=false}}
function resetLoad(){query.page=1;loadData()} function resetFilter(){Object.assign(query,{direction:'',status:'',category_key:'',keyword:'',page:1});loadData()}
async function loadOptions(){const [c,a]:any[]=await Promise.all([getErpFinanceCategories(),getCapitalAccounts()]);categories.value=c?.data||[];accounts.value=a?.data?.list||a?.data||[]}
function openCreate(){Object.assign(form,{direction:'expense',category_key:'',party_id:null,party_name:'',amount:0,settlement_mode:'pending',capital_account_id:enabledAccounts.value[0]?.id||null,occurred_at:String(Math.floor(Date.now()/1000)),remark:'',voucher_urls:''});onDirectionChange();visible.value=true}
function onDirectionChange(){if(!formCategories.value.some(row=>row.key===form.category_key))form.category_key=formCategories.value[0]?.key||''}
async function submit(){if(!form.category_key)return ElMessage.warning('请选择收支类型');if(!form.party_id)return ElMessage.warning('请选择往来主体');if(Number(form.amount)<=0)return ElMessage.warning('请输入金额');if(!form.remark)return ElMessage.warning('请填写经营事项说明');if(form.settlement_mode==='immediate'&&!form.capital_account_id)return ElMessage.warning('请选择资金账户');const category=formCategories.value.find(row=>row.key===form.category_key);await ElMessageBox.confirm(`确认记录「${category?.name||'经营收支'}」¥${money(form.amount)}？${form.settlement_mode==='pending'?'将生成待结算应收/应付。':'将立即改变所选账户余额。'}`,'敏感操作确认',{type:'warning',confirmButtonText:'确认记账'});saving.value=true;try{await createErpOperatingFinance({...form,occurred_at:Number(form.occurred_at||0),request_id:`operating:${Date.now()}:${Math.random().toString(36).slice(2)}`});ElMessage.success(form.settlement_mode==='pending'?'已生成应收应付':'经营收支已现结');visible.value=false;await loadData()}finally{saving.value=false}}
function goFinance(row:any){router.push({path:row.direction==='income'?'/hsx_erp/receivable':'/hsx_erp/payable',query:{source_no:row.source_no}})}
onMounted(async()=>{await loadOptions();await loadData()})
</script>

<style scoped>.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.summary-card{padding:18px;border-radius:12px;background:#f8fafc}.summary-card span,.summary-card strong{display:block}.summary-card span{font-size:13px;color:#64748b}.summary-card strong{margin-top:7px;font-size:24px}.summary-card.income strong{color:#16a34a}.summary-card.expense strong{color:#ea580c}.summary-card.pending strong{color:#2563eb}.summary-card.profit strong{color:#0f172a}@media(max-width:1100px){.summary-grid{grid-template-columns:repeat(2,1fr)}}</style>
