<template>
    <HsxPage title="客资协同" subtitle="连接门店库存，让每一条客资有去向、有跟进、有收益。" eyebrow="业务协同工作台" content-width="wide" padding="compact" class="lead-workbench">
        <template #extra><el-button v-if="tab!=='cooperation'" :icon="Refresh" :loading="loading" @click="loadDashboard">刷新概况</el-button></template>
        <el-alert v-if="error&&tab!=='cooperation'" :title="error" type="error" show-icon :closable="false" class="lead-error-banner" />
        <div v-if="ready" class="lead-scope-note"><el-tag size="small" effect="plain">{{ viewerScope.all_data ? '全部业务数据' : '仅本人业务数据' }}</el-tag><span>{{ viewerScope.label }}；商户及库存快照共用。转交归属不会重新派给门店。</span></div>
        <div v-if="loading&&!ready&&tab!=='cooperation'" class="lead-card"><el-skeleton :rows="6" animated /></div>
        <el-tabs v-model="tab" @tab-change="changedTab">
            <el-tab-pane label="商户协作" name="cooperation"><CooperationPanel v-if="tab==='cooperation'" :viewer-scope="viewerScope" /></el-tab-pane>
            <el-tab-pane label="平台派单概况" name="overview"><OverviewPanel v-if="tab === 'overview'" :data="dashboard" :viewer-scope="viewerScope" @navigate="navigate" /></el-tab-pane>
            <el-tab-pane label="合作商户" name="merchants"><MerchantPanel v-if="tab === 'merchants'" :initial-filter="filters.merchants || {}" /></el-tab-pane>
            <el-tab-pane label="库存快照" name="inventory"><InventoryPanel v-if="tab === 'inventory'" /></el-tab-pane>
            <el-tab-pane label="平台派单记录" name="leads"><LeadPanel v-if="tab === 'leads'" :initial-filter="filters.leads || {}" :viewer-scope="viewerScope" @changed="loadDashboard" /></el-tab-pane>
            <el-tab-pane label="原分享台账" name="commissions"><CommissionPanel v-if="tab === 'commissions'" :summary="dashboard" :initial-filter="filters.commissions || {}" :viewer-scope="viewerScope" @changed="loadDashboard" /></el-tab-pane>
            <el-tab-pane label="商户端入口" name="portal"><MerchantPortalEntry v-if="tab === 'portal'" @manage-merchants="navigate('merchants')" /></el-tab-pane>
        </el-tabs>
    </HsxPage>
</template>
<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import HsxPage from '@/addon/hsx_components/components/HsxPage/index.vue'
import '@/addon/hsx_components/styles/theme.scss'
import OverviewPanel from '../components/OverviewPanel.vue'
import MerchantPanel from '../components/MerchantPanel.vue'
import InventoryPanel from '../components/InventoryPanel.vue'
import LeadPanel from '../components/LeadPanel.vue'
import CommissionPanel from '../components/CommissionPanel.vue'
import MerchantPortalEntry from '../components/MerchantPortalEntry.vue'
import CooperationPanel from '../cooperation/CooperationPanel.vue'
import { getDashboard, getViewerScope } from '../../api'
import { errorText, type Row } from '../../composables/useLeadUi'
import '../components/lead.css'
const tab = ref('cooperation'), dashboard = ref<Row>({}), loading = ref(false), error = ref(''), ready = ref(false)
const viewerScope = ref<Row>({ all_data: false, can_transfer: false, label: '正在核对可见范围' })
let sequence = 0
async function loadDashboard() { const token = ++sequence; loading.value = true; error.value = ''; try { const [result, scope] = await Promise.all([getDashboard(), getViewerScope()]); if (token !== sequence) return; if (typeof scope.data?.all_data !== 'boolean' || typeof scope.data?.can_transfer !== 'boolean') throw new Error('未返回有效的数据范围，请核对配套后端是否已更新'); dashboard.value = result.data; viewerScope.value = scope.data; ready.value = true } catch (cause) { if (token === sequence) { ready.value = false; dashboard.value = {}; error.value = errorText(cause, '工作台加载失败，请确认插件已安装且当前账号有管理权限。') } } finally { if (token === sequence) loading.value = false } }
const filters = ref<Record<string, Row>>({})
function navigate(value: string, filter: Row = {}) { filters.value[value] = filter; tab.value = value }
function changedTab() { if (['overview', 'commissions'].includes(tab.value)) void loadDashboard() }
onMounted(loadDashboard)
onBeforeUnmount(() => { sequence++ })
</script>
<style scoped>
.lead-scope-note { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; margin: 0 0 14px; color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.7; }
</style>
