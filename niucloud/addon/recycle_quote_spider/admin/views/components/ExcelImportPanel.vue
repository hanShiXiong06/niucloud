<template>
    <div>
        <el-alert
            class="mb-[12px]"
            type="info"
            :closable="false"
            title="Excel 每一行是一条行价格。必填“型号”，至少一个价格列；选择“覆盖报价项”会先清空原行价格再写入，不选则在所选分类下新建报价项。"
        />

        <div class="excel-upload-card mb-[12px]">
            <div class="excel-pane">
                <el-upload :auto-upload="false" :show-file-list="false" accept=".xls,.xlsx" :on-change="handleExcelChange">
                    <el-button type="primary">① 选择 Excel</el-button>
                </el-upload>
                <el-button @click="downloadExcelTemplate">下载模板</el-button>
                <el-select v-model="excelSourceId" clearable placeholder="归属报价源（选填）" class="w-[220px]">
                    <el-option v-for="item in sourceOptions" :key="item.id" :label="item.source_name" :value="item.id" />
                </el-select>
                <el-button v-if="excelFileName" link type="info" @click="resetExcel">清除</el-button>
                <span class="muted">{{ excelFileName || '上传后会先返回预览，不直接入库' }}</span>
            </div>
        </div>

        <div v-if="excelSheets.length > 1" class="excel-mode-switch mb-[12px]">
            <span class="mr-[10px]">该文件有 <b>{{ excelSheets.length }}</b> 个工作表，</span>
            <el-radio-group v-model="batchMode" size="small">
                <el-radio-button :value="true">按品牌批量分流（每个工作表→一个报价项）</el-radio-button>
                <el-radio-button :value="false">单表导入</el-radio-button>
            </el-radio-group>
        </div>

        <template v-if="excelPreview.rows?.length && batchMode">
            <el-card class="box-card !border-none mb-[10px] table-search-wrap" shadow="never">
                <div class="section-title">② 选择品牌分类，确认每个工作表导入到哪个报价项</div>
                <el-form :inline="true" :model="excelImportForm">
                    <el-form-item label="报价源">
                        <el-select v-model="excelImportForm.source_id" filterable placeholder="选择报价源" class="w-[200px]" @change="handleBatchCategoryChange">
                            <el-option v-for="item in sourceOptions" :key="item.id" :label="item.source_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="品牌分类">
                        <el-tree-select
                            v-model="excelImportForm.category_id"
                            check-strictly
                            node-key="id"
                            :data="categoryOptions"
                            :props="{ label: 'name', value: 'id', children: 'children' }"
                            placeholder="选择该品牌对应的分类"
                            class="w-[220px]"
                            @change="handleBatchCategoryChange"
                        />
                    </el-form-item>
                    <el-form-item label="报价提示">
                        <el-input v-model="excelImportForm.notice_text" type="textarea" :rows="1" class="excel-notice-input" placeholder="默认使用系统提示；留空则读取 Excel" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" :loading="batchImporting" :disabled="batchSelectedCount === 0" @click="batchConfirmExcelImport">
                            ③ 一键批量导入（{{ batchSelectedCount }}）
                        </el-button>
                    </el-form-item>
                </el-form>

                <el-table :data="batchTargets" border size="large" class="mt-[6px]">
                    <el-table-column label="导入" width="64" align="center">
                        <template #default="{ row }">
                            <el-checkbox v-model="row.enabled" />
                        </template>
                    </el-table-column>
                    <el-table-column label="工作表（系列）" min-width="160">
                        <template #default="{ row }">
                            <span class="font-medium">{{ row.sheet_name }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="row_count" label="数据行" width="90" align="center" />
                    <el-table-column label="导入到报价项" min-width="240">
                        <template #default="{ row }">
                            <el-select v-model="row.item_id" filterable placeholder="新建同名报价项" class="w-[100%]" :disabled="!row.enabled">
                                <el-option :value="0" label="➕ 新建同名报价项" />
                                <el-option v-for="item in batchCategoryItems" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                        </template>
                    </el-table-column>
                    <el-table-column label="新报价项名称" min-width="160">
                        <template #default="{ row }">
                            <el-input v-if="!row.item_id" v-model="row.item_name" placeholder="新建时的名称" :disabled="!row.enabled" />
                            <span v-else class="muted">覆盖已选报价项</span>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="form-tip">
                    每个勾选的工作表会按其表头自动识别价格列，<b>清空目标报价项原有行价格后重新写入</b>。
                    未匹配到的工作表会在该分类下新建同名报价项。适合每天上传整个品牌的报价表一次性覆盖。
                </div>
            </el-card>
        </template>

        <template v-if="excelPreview.rows?.length && !batchMode">
            <el-card class="box-card !border-none mb-[10px] table-search-wrap" shadow="never">
                <div class="section-title">② 确认导入目标</div>
                <el-form :inline="true" :model="excelImportForm">
                    <el-form-item label="报价源">
                        <el-select v-model="excelImportForm.source_id" filterable placeholder="选择报价源" class="w-[220px]" @change="handleExcelSourceChange">
                            <el-option v-for="item in sourceOptions" :key="item.id" :label="item.source_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="分类">
                        <el-tree-select
                            v-model="excelImportForm.category_id"
                            check-strictly
                            node-key="id"
                            :data="categoryOptions"
                            :props="{ label: 'name', value: 'id', children: 'children' }"
                            placeholder="选择分类"
                            class="w-[220px]"
                        />
                    </el-form-item>
                    <el-form-item label="覆盖报价项">
                        <el-select v-model="excelImportForm.item_id" clearable filterable placeholder="不选则新建" class="w-[220px]">
                            <el-option v-for="item in itemTable.data" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="新报价项">
                        <el-input v-model="excelImportForm.item_name" placeholder="不选覆盖项时必填" />
                    </el-form-item>
                    <el-form-item label="报价提示">
                        <el-input
                            v-model="excelImportForm.notice_text"
                            type="textarea"
                            :rows="2"
                            class="excel-notice-input"
                            placeholder="默认使用系统提示；Excel 中“报价提示”列有内容时会自动读取，换行会保存为 \n"
                        />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" :loading="excelImporting" @click="confirmExcelImport">③ 导入并覆盖</el-button>
                    </el-form-item>
                </el-form>
                <div class="form-tip">
                    本次识别到 <b>{{ excelPriceColumnCount }}</b> 个价格列、<b>{{ excelPreview.rows.length }}</b> 行预览数据。
                    导入并覆盖会清空所选报价项下原有行价格，再用当前 Excel 重新生成。适合每天上传新报价单覆盖旧报价单。
                </div>
            </el-card>

            <el-table :data="excelPreviewTable.rows" border size="large" max-height="520" class="excel-matrix-table">
                <el-table-column prop="row_number" label="行号" width="76" fixed />
                <el-table-column
                    v-for="column in excelPreviewTable.columns"
                    :key="column.key"
                    :prop="column.key"
                    :label="column.label"
                    :min-width="column.minWidth"
                    show-overflow-tooltip
                >
                    <template #header>
                        <span>{{ column.label }}</span>
                        <el-tag v-if="column.isPrice" size="small" type="success" class="ml-[6px]">价格</el-tag>
                    </template>
                    <template #default="{ row }">
                        <span :class="column.isPrice ? 'excel-price-cell' : 'excel-plain-cell'">
                            {{ row[column.key] || '-' }}
                        </span>
                    </template>
                </el-table-column>
            </el-table>
        </template>

        <el-empty v-if="!excelPreview.rows?.length" description="还没有预览数据，先选择一个 Excel 文件" />
    </div>
</template>

<script lang="ts" setup>
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const {
    excelSourceId,
    excelFileName,
    excelPreview,
    excelImportForm,
    excelImporting,
    sourceOptions,
    categoryOptions,
    itemTable,
    excelPreviewTable,
    excelPriceColumnCount,
    handleExcelChange,
    downloadExcelTemplate,
    handleExcelSourceChange,
    confirmExcelImport,
    resetExcel,
    excelSheets,
    batchMode,
    batchImporting,
    batchTargets,
    batchCategoryItems,
    batchSelectedCount,
    handleBatchCategoryChange,
    batchConfirmExcelImport
} = useQuoteSpider()
</script>
