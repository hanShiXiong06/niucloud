<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never" v-loading="loading">
        <div class="page">
        <div class="page-head"><div><div class="text-page-title">会员服务卡</div><p>管理开卡、收款、核销和退款；安装 ERP 时可接入资金账户与耗材库存。</p></div><div class="head-actions"><el-button @click="paymentVisible = true"><el-icon><Setting /></el-icon>收款与耗材</el-button><el-button @click="load"><el-icon><Refresh /></el-icon>刷新</el-button></div></div>
        <div class="summary-banner">
            <div class="summary-copy"><span class="summary-kicker">MEMBER SERVICE</span><h2>让会员服务更简单</h2><p>从选择客户到完成收款，一次操作即可完成开卡。</p></div>
            <div class="summary-count"><strong>{{ data.active_card_count || 0 }}</strong><span>张有效会员卡</span></div>
            <div class="summary-actions"><el-button type="primary" size="large" @click="issueVisible = true"><el-icon><Plus /></el-icon>快速开卡</el-button><el-button size="large" @click="go('hsx_member_card/card')"><el-icon><Iphone /></el-icon>手机号核销</el-button></div>
        </div>
        <div class="section-title"><div><b>今日经营</b><span>实时业务概览</span></div></div>
        <div class="metrics"><div v-for="item in metrics" :key="item.label" class="metric"><span>{{ item.label }}</span><strong :class="item.className">{{ item.prefix }}{{ item.value }}{{ item.unit }}</strong><small>{{ item.help }}</small></div></div>
        <div class="section-title"><div><b>常用功能</b><span>高频业务入口</span></div></div>
        <div class="action-strip"><button @click="issueVisible = true"><span class="action-icon blue"><el-icon><CreditCard /></el-icon></span><span><b>给客户开卡</b><small>选择客户、卡种与收款</small></span><el-icon class="arrow"><ArrowRight /></el-icon></button><button @click="go('hsx_member_card/card')"><span class="action-icon green"><el-icon><Iphone /></el-icon></span><span><b>手机号核销</b><small>手机号与姓名双重核验</small></span><el-icon class="arrow"><ArrowRight /></el-icon></button><button @click="go('hsx_member_card/order')"><span class="action-icon violet"><el-icon><Document /></el-icon></span><span><b>开卡订单</b><small>查看收款与异常订单</small></span><el-icon class="arrow"><ArrowRight /></el-icon></button></div>
        <div class="business-note"><div><b>核销规则</b><span>客户报完整手机号或后四位，店员必须再核对购卡姓名；每次固定核销 1 次，敏感操作保留审计。</span></div><el-button link type="primary" @click="go('hsx_member_card/card')">马上核销</el-button></div>
        <MemberCardIssueDialog v-model="issueVisible" @success="load" />
        <MemberCardPaymentSettings v-model="paymentVisible" @change="load" />
        </div>
        </el-card>
    </div>
</template>
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, CreditCard, Document, Iphone, Plus, Refresh, Setting } from '@element-plus/icons-vue'
import { getMemberCardDashboard } from '../../api'
import MemberCardIssueDialog from '../../components/MemberCardIssueDialog.vue'
import MemberCardPaymentSettings from '../../components/MemberCardPaymentSettings.vue'
const router = useRouter(), loading = ref(false), issueVisible = ref(false), paymentVisible = ref(false), data = ref<any>({})
const money = (v: any) => Number(v || 0).toFixed(2)
const metrics = computed(() => [
    { label: '今日开卡', value: data.value.issued_count || 0, prefix: '', unit: ' 张', help: '已形成有效开卡订单' },
    { label: '卡销售额', value: money(data.value.card_sale_amount), prefix: '¥', unit: '', help: '按开卡订单口径' },
    { label: '实际收款', value: money(data.value.actual_received_amount), prefix: '¥', unit: '', help: '已确认到账金额', className: 'green' },
    { label: '待收款', value: money(data.value.pending_receivable_amount), prefix: '¥', unit: '', help: '需要财务跟进', className: 'orange' },
    { label: '有效会员卡', value: data.value.active_card_count || 0, prefix: '', unit: ' 张', help: '当前可使用卡' },
    { label: '今日核销', value: data.value.redemption_count || 0, prefix: '', unit: ' 次', help: `确认收入 ¥${money(data.value.recognized_amount)}` },
])
const load = async () => { loading.value = true; try { data.value = ((await getMemberCardDashboard()) as any).data || {} } finally { loading.value = false } }
const go = (path: string) => router.push({ path: `/${path}` })
onMounted(load)
</script>
<style scoped>
.page{min-height:100%;color:#475569}.page-head,.head-actions,.business-note{display:flex;align-items:center;justify-content:space-between;gap:12px}.page-head{align-items:flex-start;margin-bottom:18px}.page-head p{margin:6px 0 0;color:#94a3b8}.summary-banner{position:relative;display:grid;grid-template-columns:minmax(260px,1fr) auto auto;align-items:center;gap:34px;min-height:132px;padding:26px 30px;overflow:hidden;border:1px solid #dbeafe;border-radius:10px;background:linear-gradient(120deg,#f8fbff 0%,#eef5ff 64%,#f5f3ff 100%)}.summary-banner:after{position:absolute;right:-55px;top:-90px;width:230px;height:230px;border-radius:50%;background:rgba(59,130,246,.06);content:""}.summary-copy,.summary-count{position:relative;z-index:1}.summary-kicker{color:#7c9ac7;font-size:11px;letter-spacing:1.6px}.summary-copy h2{margin:7px 0 5px;color:#334155;font-size:22px;font-weight:600}.summary-copy p{margin:0;color:#8190a5}.summary-count{display:flex;padding:0 28px;border-right:1px solid #dbe4f0;border-left:1px solid #dbe4f0;flex-direction:column;align-items:center}.summary-count strong{color:#2563eb;font-size:31px;font-weight:600}.summary-count span{margin-top:3px;color:#94a3b8;font-size:12px}.summary-actions{position:relative;z-index:1;display:flex}.section-title{display:flex;margin:24px 0 12px;align-items:center;justify-content:space-between}.section-title div{display:flex;align-items:baseline;gap:10px}.section-title b{color:#475569;font-size:16px;font-weight:600}.section-title span{color:#a0aec0;font-size:12px}.metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));overflow:hidden;border:1px solid #e8edf3;border-radius:8px;background:#fff}.metric{display:flex;min-height:104px;padding:18px 20px;box-sizing:border-box;border-right:1px solid #edf1f5;border-bottom:1px solid #edf1f5;flex-direction:column}.metric:nth-child(3n){border-right:0}.metric:nth-child(n+4){border-bottom:0}.metric span{color:#7f8da2;font-size:13px}.metric strong{margin-top:10px;color:#3f4d63;font-size:24px;font-weight:600}.metric strong.green{color:#16a34a}.metric strong.orange{color:#d97706}.metric small{margin-top:5px;color:#a0aec0}.action-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.action-strip button{display:grid;min-width:0;padding:16px 17px;border:1px solid #e8edf3;border-radius:8px;background:#fff;grid-template-columns:44px 1fr auto;align-items:center;gap:13px;text-align:left;cursor:pointer;transition:.2s}.action-strip button:hover{border-color:#bfdbfe;box-shadow:0 8px 22px rgba(30,64,175,.06);transform:translateY(-1px)}.action-strip button>span:not(.action-icon){display:flex;min-width:0;flex-direction:column;gap:5px}.action-strip b{color:#475569;font-size:14px;font-weight:600}.action-strip small{overflow:hidden;color:#94a3b8;text-overflow:ellipsis;white-space:nowrap}.action-icon{display:flex;width:44px;height:44px;align-items:center;justify-content:center;border-radius:12px;background:#eff6ff;color:#2563eb}.action-icon.green{background:#ecfdf3;color:#16a34a}.action-icon.violet{background:#f5f3ff;color:#7c3aed}.action-icon .el-icon{font-size:21px}.action-strip .arrow{color:#cbd5e1;font-size:15px}.business-note{margin-top:16px;padding:15px 18px;border-radius:8px;background:#f8fafc}.business-note div{display:flex;flex-direction:column;gap:5px}.business-note b{color:#526075;font-weight:600}.business-note span{color:#94a3b8}@media(max-width:1000px){.summary-banner{grid-template-columns:1fr auto}.summary-actions{grid-column:1/3}.metrics{grid-template-columns:repeat(2,1fr)}.metric:nth-child(3n){border-right:1px solid #edf1f5}.metric:nth-child(2n){border-right:0}.metric:nth-child(n+4){border-bottom:1px solid #edf1f5}.metric:nth-child(n+5){border-bottom:0}.action-strip{grid-template-columns:1fr}}
</style>
