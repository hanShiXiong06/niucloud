<template>
    <el-dialog v-model="adjustDialog.visible" title="批量调价" width="820px" class="qs-scope quote-spider-dialog" append-to-body>
        <el-alert
            class="mb-[14px]"
            type="warning"
            :closable="false"
            :title="`本次会应用到已选的 ${rowSelection.length} 行价格，请确认选中范围后再保存。`"
        />
        <div class="batch-adjust-layout">
            <section class="row-edit-section">
                <div class="section-title">调整规则</div>
                <el-form :model="adjustDialog.form" label-width="88px">
                    <el-form-item label="调价方式">
                        <el-segmented v-model="adjustDialog.form.adjust_type" :options="adjustTypeOptions" @change="handleBatchAdjustTypeChange" />
                        <div class="form-tip">{{ batchAdjustTip }}</div>
                    </el-form-item>
                    <el-form-item v-if="adjustDialog.form.adjust_type === 1" label="调价值">
                        <el-input-number
                            v-model="adjustDialog.form.adjust_value"
                            :controls="false"
                            :precision="2"
                            placeholder="例如 -50 或 100"
                            class="w-full"
                        />
                        <div class="form-tip">正数表示加价，负数表示扣价。例如填 -50，所有选中价格在源价基础上扣 50。</div>
                    </el-form-item>
                    <el-form-item v-if="adjustDialog.form.adjust_type === 2" label="比例">
                        <el-input-number
                            v-model="adjustDialog.form.adjust_ratio"
                            :controls="false"
                            :precision="4"
                            :step="0.01"
                            :min="0"
                            placeholder="例如 0.95 或 1.05"
                            class="w-full"
                        />
                        <div class="form-tip">1 表示不变，0.95 表示下调 5%，1.05 表示上调 5%。</div>
                    </el-form-item>
                </el-form>
                <div class="adjust-preview-card">
                    <div class="muted">规则预览</div>
                    <div class="adjust-preview-main">{{ batchAdjustPreviewText }}</div>
                </div>
            </section>

            <section class="row-edit-section">
                <div class="section-title">
                    已选行
                    <el-tag size="small" type="warning">{{ rowSelection.length }} 行</el-tag>
                </div>
                <el-table :data="batchAdjustRowsPreview" border size="large" max-height="260">
                    <el-table-column prop="model_name" label="型号" min-width="180" show-overflow-tooltip />
                    <el-table-column label="当前价格" min-width="240" show-overflow-tooltip>
                        <template #default="{ row }">{{ formatPrices(row.columns, row.final_prices) || '-' }}</template>
                    </el-table-column>
                    <el-table-column prop="tab" label="分组" width="110" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.tab || '-' }}</template>
                    </el-table-column>
                </el-table>
                <div v-if="rowSelection.length > batchAdjustRowsPreview.length" class="form-tip">
                    仅预览前 {{ batchAdjustRowsPreview.length }} 行，保存会应用到全部 {{ rowSelection.length }} 行。
                </div>
            </section>
        </div>
        <template #footer>
            <el-button @click="adjustDialog.visible = false">取消</el-button>
            <el-button type="primary" :loading="adjustDialog.submitting" :disabled="!rowSelection.length" @click="submitBatchAdjust">
                确认应用到 {{ rowSelection.length }} 行
            </el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const {
    adjustDialog,
    rowSelection,
    adjustTypeOptions,
    batchAdjustTip,
    batchAdjustPreviewText,
    batchAdjustRowsPreview,
    formatPrices,
    handleBatchAdjustTypeChange,
    submitBatchAdjust
} = useQuoteSpider()
</script>
