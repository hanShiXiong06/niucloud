<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never" v-loading="loading">
        <div class="page">
        <div class="page-head"><div><div class="text-page-title">会员服务卡</div><p>贴膜次卡的开卡、收款、核销与退款统一进入 ERP 账务。</p></div><div class="head-actions"><el-button @click="load"><el-icon><Refresh /></el-icon>刷新</el-button><el-button type="primary" @click="issueVisible = true"><el-icon><Plus /></el-icon>快速开卡</el-button></div></div>
        <div class="action-strip"><button @click="issueVisible = true"><el-icon><CreditCard /></el-icon><span><b>给客户开卡</b><small>一次完成客户、卡种和收款</small></span></button><button @click="go('hsx_member_card/card')"><el-icon><Iphone /></el-icon><span><b>手机号核销</b><small>报后四位并核对姓名</small></span></button><button @click="go('hsx_member_card/order')"><el-icon><Document /></el-icon><span><b>查看开卡订单</b><small>处理挂账和异常单</small></span></button></div>
        <div class="metrics"><div v-for="item in metrics" :key="item.label" class="metric"><span>{{ item.label }}</span><strong :class="item.className">{{ item.prefix }}{{ item.value }}{{ item.unit }}</strong><small>{{ item.help }}</small></div></div>
        <div class="business-note"><div><b>核销规则</b><span>客户报完整手机号或后四位，店员必须再核对购卡姓名；每次固定核销 1 次，敏感操作保留审计。</span></div><el-button link type="primary" @click="go('hsx_member_card/card')">马上核销</el-button></div>
        <MemberCardIssueDialog v-model="issueVisible" @success="load" />
        </div>
        </el-card>
    </div>
</template>
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CreditCard, Document, Iphone, Plus, Refresh } from '@element-plus/icons-vue'
import { getMemberCardDashboard } from '../../api'
import MemberCardIssueDialog from '../../components/MemberCardIssueDialog.vue'
const router = useRouter(), loading = ref(false), issueVisible = ref(false), data = ref<any>({})
const money = (v: any) => Number(v || 0).toFixed(2)
const metrics = computed(() => [
    { label: '今日开卡', value: data.value.issued_count || 0, prefix: '', unit: ' 张', help: '已形成有效开卡订单' },
    { label: '卡销售额', value: money(data.value.card_sale_amount), prefix: '¥', unit: '', help: '按开卡订单口径' },
    { label: '实际收款', value: money(data.value.actual_received_amount), prefix: '¥', unit: '', help: 'ERP 已确认收款', className: 'green' },
    { label: '待收款', value: money(data.value.pending_receivable_amount), prefix: '¥', unit: '', help: '需要财务跟进', className: 'orange' },
    { label: '有效会员卡', value: data.value.active_card_count || 0, prefix: '', unit: ' 张', help: '当前可使用卡' },
    { label: '今日核销', value: data.value.redemption_count || 0, prefix: '', unit: ' 次', help: `确认收入 ¥${money(data.value.recognized_amount)}` },
])
const load = async () => { loading.value = true; try { data.value = ((await getMemberCardDashboard()) as any).data || {} } finally { loading.value = false } }
const go = (path: string) => router.push({ path: `/${path}` })
onMounted(load)
</script>
<style scoped>
.page{min-height:100%}.page-head,.head-actions,.action-strip,.business-note{display:flex;align-items:center;justify-content:space-between;gap:12px}.page-head{align-items:flex-start;margin-bottom:20px}.page-head p{margin:6px 0 0;color:var(--el-text-color-secondary)}.action-strip{margin-bottom:16px}.action-strip button{flex:1;display:flex;align-items:center;gap:13px;padding:18px;border:1px solid var(--el-border-color-lighter);border-radius:4px;background:var(--el-bg-color);text-align:left;cursor:pointer}.action-strip button:hover{border-color:var(--el-color-primary);background:var(--el-color-primary-light-9)}.action-strip .el-icon{font-size:26px;color:var(--el-color-primary)}.action-strip span{display:flex;flex-direction:column;gap:5px}.action-strip b{font-size:15px}.action-strip small,.metric small{color:var(--el-text-color-secondary)}.metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.metric{display:flex;min-height:110px;padding:17px;border:1px solid var(--el-border-color-lighter);border-radius:4px;background:var(--el-bg-color);flex-direction:column;justify-content:space-between}.metric span{color:var(--el-text-color-secondary);font-size:13px}.metric strong{font-size:25px}.metric strong.green{color:var(--el-color-success)}.metric strong.orange{color:var(--el-color-warning)}.business-note{margin-top:16px;padding:16px 18px;border-radius:4px;background:var(--el-fill-color-light)}.business-note div{display:flex;flex-direction:column;gap:5px}.business-note span{color:var(--el-text-color-secondary)}@media(max-width:900px){.metrics{grid-template-columns:repeat(2,1fr)}.action-strip{align-items:stretch;flex-direction:column}}
</style>
