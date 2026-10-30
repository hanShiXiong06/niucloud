<template>
    <HsxPage title="客资协同" subtitle="商户接入、库存参考、客资派单与佣金登记，一个工作台闭环。" eyebrow="MERCHANT LEAD WORKSPACE" content-width="wide" class="lead-workbench">
        <template #extra><el-button :icon="Refresh" :loading="loading" @click="loadDashboard">刷新概况</el-button></template>
        <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="lead-error-banner" />
        <div v-if="loading && !ready" class="lead-card"><el-skeleton :rows="6" animated /></div>
        <el-tabs v-if="ready" v-model="tab" @tab-change="changedTab">
            <el-tab-pane label="工作台" name="overview"><OverviewPanel v-if="tab === 'overview'" :data="dashboard" @navigate="navigate" /></el-tab-pane>
            <el-tab-pane label="合作商户" name="merchants"><MerchantPanel v-if="tab === 'merchants'" :initial-filter="filters.merchants || {}" /></el-tab-pane>
            <el-tab-pane label="库存快照" name="inventory"><InventoryPanel v-if="tab === 'inventory'" /></el-tab-pane>
            <el-tab-pane label="客资工单" name="leads"><LeadPanel v-if="tab === 'leads'" :initial-filter="filters.leads || {}" @changed="loadDashboard" /></el-tab-pane>
            <el-tab-pane label="佣金收款" name="commissions"><CommissionPanel v-if="tab === 'commissions'" :summary="dashboard" @changed="loadDashboard" /></el-tab-pane>
            <el-tab-pane label="商户端入口" name="portal"><div class="lead-card"><h3>商户使用现有会员身份</h3><p class="lead-muted">在商户资料中绑定会员后，商户可通过原有手机号、微信等方式登录移动端；不需要注册新的后台账号或保存另一套密码。</p><el-descriptions :column="1" border style="margin-top: 20px"><el-descriptions-item label="会员工作台路径">/addon/hsx_lead/pages/workbench/index</el-descriptions-item><el-descriptions-item label="可操作业务">接单 / 拒单、记录跟进、提交成交、关闭工单</el-descriptions-item><el-descriptions-item label="数据范围">只允许当前登录会员绑定的商户，不接受传入其他商户 ID</el-descriptions-item><el-descriptions-item label="敏感信息">不向会员端下发 ERP 账号、密码或 Token</el-descriptions-item></el-descriptions><el-alert title="这里仅展示入口说明，不会用管理员身份模拟会员或切换查看其他商户的数据。" type="info" :closable="false" show-icon style="margin-top: 20px" /></div></el-tab-pane>
        </el-tabs>
    </HsxPage>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import HsxPage from '@/addon/hsx_components/components/HsxPage/index.vue'
import '@/addon/hsx_components/styles/theme.scss'
import OverviewPanel from '../components/OverviewPanel.vue'
import MerchantPanel from '../components/MerchantPanel.vue'
import InventoryPanel from '../components/InventoryPanel.vue'
import LeadPanel from '../components/LeadPanel.vue'
import CommissionPanel from '../components/CommissionPanel.vue'
import { getDashboard } from '../../api'
import { errorText, type Row } from '../../composables/useLeadUi'
import '../components/lead.css'
const tab = ref('overview'), dashboard = ref<Row>({}), loading = ref(false), error = ref(''), ready = ref(false)
async function loadDashboard() { loading.value = true; error.value = ''; try { const result = await getDashboard(); dashboard.value = result.data; ready.value = true } catch (cause) { error.value = errorText(cause, '工作台加载失败，请确认插件已安装且当前账号有管理权限。') } finally { loading.value = false } }
const filters = ref<Record<string, Row>>({})
function navigate(value: string, filter: Row = {}) { filters.value[value] = filter; tab.value = value }
function changedTab() { if (['overview', 'commissions'].includes(tab.value)) void loadDashboard() }
onMounted(loadDashboard)
</script>
