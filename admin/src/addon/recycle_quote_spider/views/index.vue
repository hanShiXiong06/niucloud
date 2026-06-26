<template>
    <div class="main-container quote-spider-page qs-scope">
        <el-card class="box-card !border-none" shadow="never">
            <div class="page-header">
                <div>
                    <div class="page-header-title">回收报价数据源</div>
                    <div class="page-header-desc">连接第三方报价接口，同步分类 / 报价项 / 行价格，并支持 Excel 覆盖导入与人工维护。</div>
                </div>
                <div class="flex gap-2">
                    <el-button @click="createDefaultSource">创建默认源</el-button>
                    <el-button type="primary" @click="openSourceDialog()">新增报价源</el-button>
                </div>
            </div>

            <el-tabs v-model="activeTab" class="mt-[16px]" @tab-change="handleTabChange">
                <el-tab-pane label="报价源" name="source">
                    <SourceListPanel />
                </el-tab-pane>
                <el-tab-pane label="数据管理" name="manage">
                    <DataManagePanel />
                </el-tab-pane>
                <el-tab-pane label="Excel导入" name="excel">
                    <ExcelImportPanel />
                </el-tab-pane>
                <el-tab-pane label="同步日志" name="log">
                    <SyncLogPanel />
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
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'
import SourceListPanel from './components/SourceListPanel.vue'
import DataManagePanel from './components/DataManagePanel.vue'
import ExcelImportPanel from './components/ExcelImportPanel.vue'
import SyncLogPanel from './components/SyncLogPanel.vue'
import RowPriceDrawer from './components/RowPriceDrawer.vue'
import SourceFormDialog from './components/SourceFormDialog.vue'
import EntityEditDialog from './components/EntityEditDialog.vue'
import BatchAdjustDialog from './components/BatchAdjustDialog.vue'
import PriceHistoryDialog from './components/PriceHistoryDialog.vue'
import './components/quote-spider.css'

const { activeTab, handleTabChange, createDefaultSource, openSourceDialog, loadSources, refreshManage, stopLogRefresh } =
    useQuoteSpider()

onMounted(() => {
    loadSources()
    refreshManage()
})

onBeforeUnmount(() => {
    stopLogRefresh()
})
</script>
