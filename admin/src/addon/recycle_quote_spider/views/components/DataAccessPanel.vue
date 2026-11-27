<template>
    <div class="data-access-panel">
        <div class="section-switcher">
            <el-radio-group v-model="accessMode">
                <el-radio-button value="sync">接口同步</el-radio-button>
                <el-radio-button value="excel">Excel 导入</el-radio-button>
            </el-radio-group>
            <div class="section-switcher-copy">
                <strong>{{ accessMode === 'sync' ? '从第三方报价系统更新数据' : '把供应商表格转成结构化报价' }}</strong>
                <span>{{ accessMode === 'sync' ? '选择数据源后手动同步，自动同步可在基础设置中开启。' : '先预览、确认映射，再覆盖或新建报价单。' }}</span>
            </div>
            <span class="toolbar-spacer" />
            <template v-if="accessMode === 'sync'">
                <el-button @click="createDefaultSource">创建默认源</el-button>
                <el-button type="primary" @click="openSourceDialog()">新增数据源</el-button>
            </template>
        </div>
        <SourceListPanel v-if="accessMode === 'sync'" />
        <ExcelImportPanel v-else />
    </div>
</template>

<script lang="ts" setup>
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'
import SourceListPanel from './SourceListPanel.vue'
import ExcelImportPanel from './ExcelImportPanel.vue'

const { accessMode, createDefaultSource, openSourceDialog } = useQuoteSpider()
</script>
