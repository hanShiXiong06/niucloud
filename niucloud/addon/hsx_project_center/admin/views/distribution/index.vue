<template>
    <div class="main-page">
        <el-card shadow="never" class="!border-none">
            <template #header>
                <div><div class="flex items-center text-[18px] font-semibold text-[#1d2939]">分销佣金工作台<el-tooltip placement="top" :width="430" content="这里展示当前数据库真实账目。佣金在资料审核通过且核对付款后生成；项目配置提供基础佣金，受益人的会员等级提供资格和系数。退款会冻结或冲红，不足部分进入待抵扣。"><el-icon class="ml-[7px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></div><div class="mt-[6px] text-[13px] text-[#667085]">每一笔一级、二级佣金都保留项目规则、会员等级、计算系数和退款处理记录。</div></div>
            </template>
            <div class="overview-grid">
                <div class="metric"><div class="metric-label">佣金单总数</div><div class="metric-value">{{ overview.order_count || 0 }}</div></div>
                <div class="metric"><div class="metric-label flex items-center">邀请卡 / 新绑定<el-tooltip placement="top" :width="390" content="邀请卡是已获得推广资格且生成过分享凭证的会员数；新绑定只统计通过本项目分享链接首次写入的推荐关系。会员原本已有的框架推荐关系仍会参与分佣，但不重复计入新绑定。"><el-icon class="ml-[5px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></div><div class="metric-value">{{ overview.invite_count || 0 }} / {{ overview.bound_count || 0 }}</div></div>
                <div class="metric"><div class="metric-label">待处理 / 冻结</div><div class="metric-value">{{ overview.pending_count || 0 }}</div><div class="metric-sub">待计佣 ¥{{ money(overview.pending_amount) }}</div></div>
                <div class="metric"><div class="metric-label">实际已入账</div><div class="metric-value">¥{{ money(overview.settled_amount) }}</div></div>
                <div class="metric"><div class="metric-label">已冲红佣金</div><div class="metric-value">¥{{ money(overview.reversed_amount) }}</div></div>
                <div class="metric is-warning"><div class="metric-label flex items-center">待抵扣欠款<el-tooltip placement="top" :width="370" content="退款时可用佣金不足，系统不制造负余额，而是记录待抵扣金额；该会员未来获得新佣金时会优先自动抵扣。"><el-icon class="ml-[5px] cursor-help"><QuestionFilled /></el-icon></el-tooltip></div><div class="metric-value">¥{{ money(overview.debt_amount) }}</div></div>
            </div>
            <div class="my-[18px] flex flex-wrap items-center gap-[10px] rounded-[10px] bg-[#f8fafc] p-[14px]">
                <el-input v-model="query.keyword" clearable class="!w-[280px]" placeholder="佣金单号、客户昵称或手机号" @keyup.enter="loadPage(1)" />
                <el-select v-model="query.project_id" clearable class="!w-[190px]" placeholder="全部项目"><el-option v-for="item in projects" :key="item.id" :label="item.title" :value="item.id" /></el-select>
                <el-select v-model="query.status" clearable class="!w-[150px]" placeholder="全部状态"><el-option v-for="(name,key) in statusOptions" :key="key" :label="name" :value="key" /></el-select>
                <el-button type="primary" @click="loadPage(1)">查询</el-button><el-button @click="reset">重置</el-button>
            </div>
            <el-table v-loading="loading" :data="rows" size="large" row-key="id">
                <el-table-column type="expand"><template #default="{ row }"><div class="detail-wrap">
                    <el-alert v-if="row.error_message" class="mb-[12px]" type="error" :closable="false" :title="row.error_message" />
                    <div v-if="!(row.details || []).length" class="py-[18px] text-center text-[#98a2b3]">审批时未找到符合等级权益的推荐人，因此没有生成佣金明细</div>
                    <el-table v-else :data="row.details" size="small" border>
                        <el-table-column label="关系" width="100"><template #default="{ row: item }">{{ item.relation_level_name }}</template></el-table-column>
                        <el-table-column label="受益人" min-width="160"><template #default="{ row: item }"><div>{{ item.beneficiary_name || `会员#${item.beneficiary_member_id}` }}</div><small class="text-[#98a2b3]">{{ item.beneficiary_mobile || '-' }}</small></template></el-table-column>
                        <el-table-column label="等级快照" min-width="150"><template #default="{ row: item }">{{ item.beneficiary_level_name || '-' }}（{{ item.coefficient }}%）</template></el-table-column>
                        <el-table-column label="计算规则" min-width="230"><template #default="{ row: item }"><el-tooltip placement="top" :width="390" :content="`${item.relation_level_name}：基础佣金 ¥${money(item.base_commission)} × 审批时“${item.beneficiary_level_name || '会员等级'}”系数 ${item.coefficient}% = ¥${money(item.commission_amount)}。等级以后变化不追溯本单。`"><span class="cursor-help border-b border-dashed border-[#98a2b3]">¥{{ money(item.base_commission) }} × {{ item.coefficient }}% = ¥{{ money(item.commission_amount) }}</span></el-tooltip></template></el-table-column>
                        <el-table-column label="入账 / 冲红 / 欠款" min-width="190"><template #default="{ row: item }">¥{{ money(item.settled_amount) }} / ¥{{ money(item.reversed_amount) }} / ¥{{ money(item.debt_amount) }}</template></el-table-column>
                        <el-table-column label="状态" width="110"><template #default="{ row: item }"><el-tag :type="statusType(item.status)">{{ item.status_name }}</el-tag></template></el-table-column>
                    </el-table>
                    <div class="mt-[12px] text-[12px] leading-[20px] text-[#667085]">规则快照：{{ ruleText(row) }}</div>
                </div></template></el-table-column>
                <el-table-column label="佣金单 / 项目" min-width="220"><template #default="{ row }"><div class="font-medium">{{ row.order_no }}</div><div class="mt-[4px] text-[13px] text-[#667085]">{{ row.project_title }}</div></template></el-table-column>
                <el-table-column label="参与客户" min-width="180"><template #default="{ row }"><div>{{ row.buyer_name || `会员#${row.buyer_member_id}` }}</div><div class="mt-[4px] text-[12px] text-[#98a2b3]">{{ row.buyer_mobile || '-' }}</div></template></el-table-column>
                <el-table-column label="项目金额" width="120"><template #default="{ row }">¥{{ money(row.base_amount) }}</template></el-table-column>
                <el-table-column label="结算时间" min-width="165"><template #default="{ row }">{{ timeText(row.settle_at) }}</template></el-table-column>
                <el-table-column label="状态" width="115"><template #default="{ row }"><el-tag :type="statusType(row.status)">{{ row.status_name }}</el-tag></template></el-table-column>
                <el-table-column label="操作" fixed="right" width="130"><template #default="{ row }"><el-button v-if="['pending','exception'].includes(row.status)" v-permission="'hsx_project_center_distribution_settle'" link type="primary" @click="settle(row)">立即结算</el-button><span v-else class="text-[12px] text-[#98a2b3]">展开查看明细</span></template></el-table-column>
            </el-table>
            <div class="mt-[18px] flex justify-end"><el-pagination v-model:current-page="page.page" v-model:page-size="page.limit" layout="total, prev, pager, next" :total="page.total" @current-change="loadPage" /></div>
        </el-card>
    </div>
</template>
<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { QuestionFilled } from '@element-plus/icons-vue'
import { getProjectCenterDistributionOrders, getProjectCenterDistributionOverview, getProjectCenterDistributionProjects, settleProjectCenterDistributionOrder } from '@/addon/hsx_project_center/api'
const loading = ref(false), rows = ref<any[]>([]), projects = ref<any[]>([])
const overview = reactive<any>({}), query = reactive<any>({ keyword: '', project_id: '', status: '' }), page = reactive({ page: 1, limit: 15, total: 0 })
const statusOptions:any = { pending: '待结算', frozen: '退款冻结', settled: '已结算', cancelled: '已取消', partial_reversed: '部分冲红', reversed: '已冲红', exception: '处理异常' }
const money = (value:any) => Number(value || 0).toFixed(2)
const timeText = (value:any) => !value ? '-' : new Date(Number(value) * 1000).toLocaleString()
const statusType = (status:string) => status === 'settled' ? 'success' : ['reversed','cancelled'].includes(status) ? 'info' : status === 'exception' ? 'danger' : 'warning'
const pageRows = (data:any) => data?.data || data?.list || []
function ruleText(row:any) { const rule = row.rule_snapshot?.rule || {}; const suffix = rule.commission_type === 'ratio' ? '%' : '元'; return `一级 ${rule.first_value ?? 0}${suffix}，二级 ${rule.second_value ?? 0}${suffix}，保护期 ${rule.settle_days ?? 0} 天；明细金额再乘各受益人的会员等级系数。` }
async function loadOverview() { const res:any = await getProjectCenterDistributionOverview(); Object.assign(overview, res.data || {}) }
async function loadPage(toPage?:number) { if (toPage) page.page = toPage; loading.value = true; try { const res:any = await getProjectCenterDistributionOrders({ ...query, page: page.page, limit: page.limit }); rows.value = pageRows(res.data); page.total = Number(res.data?.total || 0) } finally { loading.value = false } }
function reset() { Object.assign(query, { keyword: '', project_id: '', status: '' }); loadPage(1) }
async function settle(row:any) { await ElMessageBox.confirm('确认立即结算这笔佣金吗？系统会先抵扣该推广人的历史退款欠款，再把剩余金额计入佣金账户。', '立即结算', { type: 'warning' }); await settleProjectCenterDistributionOrder(row.id); ElMessage.success('结算已执行'); await Promise.all([loadOverview(), loadPage()]) }
onMounted(async () => { const res:any = await getProjectCenterDistributionProjects(); projects.value = res.data || []; await Promise.all([loadOverview(), loadPage()]) })
</script>
<style scoped>
.overview-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.metric{min-height:104px;padding:17px;border:1px solid #e7ecf3;border-radius:12px;background:linear-gradient(135deg,#fff,#f8faff)}.metric.is-warning{border-color:#f5d08a;background:#fffaf0}.metric-label{color:#667085;font-size:13px}.metric-value{margin-top:10px;color:#1d2939;font-size:24px;font-weight:700}.metric-sub{margin-top:5px;color:#98a2b3;font-size:12px}.detail-wrap{padding:14px 22px 18px;background:#f8fafc}@media (max-width:1450px){.overview-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
