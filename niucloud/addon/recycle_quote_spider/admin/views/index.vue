<template>
    <div class="main-container quote-spider-page qs-scope">
        <el-card class="box-card !border-none" shadow="never">
            <div class="page-header quote-page-header">
                <div>
                    <div class="page-header-title">报价中心</div>
                    <div class="page-header-desc">查询当前报价、更新供应商数据，并查看 7 天 / 30 天历史价格。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Upload" @click="openExcelImport">导入 Excel</el-button>
                    <el-button type="primary" :icon="Refresh" :disabled="!selectedSource" :loading="syncingId === selectedSourceId" @click="syncCurrentSource">
                        {{ selectedSource ? `同步${selectedSource.source_name}` : '选择数据源后同步' }}
                    </el-button>
                </div>
            </div>

            <el-tabs v-model="activeTab" class="quote-main-tabs" @tab-change="handleTabChange">
                <el-tab-pane label="报价工作台" name="workbench">
                    <QuoteWorkbenchPanel />
                </el-tab-pane>
                <el-tab-pane label="数据更新" name="access">
                    <DataAccessPanel />
                </el-tab-pane>
                <el-tab-pane label="运行记录" name="records">
                    <OperationRecordsPanel />
                </el-tab-pane>
                <el-tab-pane label="基础设置" name="settings">
                    <QuoteSettingsPanel />
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <RowPriceDrawer />
        <SourceFormDialog />
        <EntityEditDialog />
        <BatchAdjustDialog />
        <PriceHistoryDialog />
    </div>
</template>

<script lang="ts" setup>
import { onBeforeUnmount, onMounted } from 'vue'
import { Refresh, Upload } from '@element-plus/icons-vue'
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'
import QuoteWorkbenchPanel from './components/QuoteWorkbenchPanel.vue'
import DataAccessPanel from './components/DataAccessPanel.vue'
import OperationRecordsPanel from './components/OperationRecordsPanel.vue'
import QuoteSettingsPanel from './components/QuoteSettingsPanel.vue'
import RowPriceDrawer from './components/RowPriceDrawer.vue'
import SourceFormDialog from './components/SourceFormDialog.vue'
import EntityEditDialog from './components/EntityEditDialog.vue'
import BatchAdjustDialog from './components/BatchAdjustDialog.vue'
import PriceHistoryDialog from './components/PriceHistoryDialog.vue'
import './components/quote-spider.css'

const {
    activeTab,
    accessMode,
    selectedSource,
    selectedSourceId,
    syncingId,
    handleTabChange,
    loadSources,
    refreshManage,
    syncSource,
    stopLogRefresh
} = useQuoteSpider()

const openExcelImport = () => {
    accessMode.value = 'excel'
    activeTab.value = 'access'
}

const syncCurrentSource = () => {
    if (selectedSource.value) syncSource(selectedSource.value)
}

onMounted(() => {
    loadSources()
    refreshManage()
})

onBeforeUnmount(() => {
    stopLogRefresh()
})
</script>
