<template>
    <div class="main-container quotation-v2-page">
        <el-card class="box-card !border-none" shadow="never">
            <div class="page-head">
                <div>
                    <span class="text-page-title">报价 2.0 工作台</span>
                    <div class="page-desc">报价单是数据边界。先预览爬虫解析结果，再导入型号、容量、价格项和附加说明项。</div>
                </div>
                <div class="head-actions">
                    <el-button :loading="datasetTable.loading" @click="loadDatasets(datasetTable.page)">刷新</el-button>
                    <el-button @click="initDefaults">创建超牛 5 个报价单</el-button>
                    <el-button type="primary" @click="openDatasetDialog()">新增报价单</el-button>
                </div>
            </div>

            <div class="guide-grid">
                <div class="guide-item">
                    <strong>报价单</strong>
                    <span>靓机、花机、外版分别管理，同名型号不会混用。</span>
                </div>
                <div class="guide-item">
                    <strong>字段分类</strong>
                    <span>花机、内爆可测是价格项；彩点、蓝光这类内容可改为说明项。</span>
                </div>
                <div class="guide-item">
                    <strong>价格闭环</strong>
                    <span>爬虫价、价格差、最终价分开保存，锁定后不被同步覆盖。</span>
                </div>
                <div class="guide-item">
                    <strong>同步策略</strong>
                    <span>系统每小时检查一次，只同步已开启自动同步且到达间隔的报价单。</span>
                </div>
            </div>

            <el-form :inline="true" :model="datasetTable.search" class="filter-form">
                <el-form-item label="报价ID">
                    <el-input v-model="datasetTable.search.quotation_id" clearable placeholder="如 115" />
                </el-form-item>
                <el-form-item label="报价单">
                    <el-input v-model="datasetTable.search.dataset_name" clearable placeholder="名称关键词" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="datasetTable.search.status" clearable placeholder="全部" class="w-[120px]">
                        <el-option label="启用" :value="1" />
                        <el-option label="停用" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadDatasets(1)">查询</el-button>
                    <el-button @click="resetDatasetSearch">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table
                :data="datasetTable.data"
                v-loading="datasetTable.loading"
                border
                size="large"
                row-key="id"
                highlight-current-row
                class="mt-[12px]"
                @row-click="selectDataset"
            >
                <el-table-column prop="quotation_id" label="报价ID" width="100" />
                <el-table-column prop="dataset_name" label="报价单" min-width="170">
                    <template #default="{ row }">
                        <div class="dataset-name-cell">
                            <el-image v-if="row.nav_image" class="dataset-nav-image" :src="imageUrl(row.nav_image)" fit="cover" />
                            <div class="dataset-name-main">
                                <div class="strong-text">{{ row.dataset_name }}</div>
                                <div class="muted">{{ row.price_name }} · {{ row.channel_key }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="sort" label="排序" width="80" />
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status ? 'success' : 'info'">{{ row.status ? '启用' : '停用' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="最近同步" min-width="190">
                    <template #default="{ row }">
                        <div>{{ formatTime(row.last_sync_at) }}</div>
                        <div class="muted">{{ row.last_sync_message || '暂无同步' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="自动同步" min-width="190">
                    <template #default="{ row }">
                        <div class="auto-sync-cell">
                            <el-switch
                                v-model="row.sync_enabled"
                                :active-value="1"
                                :inactive-value="0"
                                :loading="autoSyncLoadingId === row.id"
                                @click.stop
                                @change="toggleDatasetAutoSync(row)"
                            />
                            <span>{{ row.sync_enabled ? '已开启' : '未开启' }}</span>
                        </div>
                        <div class="muted">{{ autoSyncText(row) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="最近结果" min-width="230">
                    <template #default="{ row }">
                        <div v-if="row.last_sync_summary">
                            型号 {{ row.last_sync_summary.models_saved || 0 }}，
                            容量 {{ row.last_sync_summary.capacities_saved || 0 }}，
                            价格 {{ row.last_sync_summary.prices_saved || 0 }}，
                            说明 {{ row.last_sync_summary.notes_saved || 0 }}
                        </div>
                        <span v-else class="muted">暂无</span>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="280">
                    <template #default="{ row }">
                        <el-button link type="primary" @click.stop="openDatasetDialog(row)">编辑</el-button>
                        <el-button link type="success" :loading="previewLoadingId === row.id" @click.stop="previewDataset(row)">预览</el-button>
                        <el-button link type="warning" :loading="syncLoadingId === row.id" @click.stop="syncNow(row)">同步</el-button>
                        <el-button link type="danger" @click.stop="deleteDataset(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="datasetTable.page"
                    v-model:page-size="datasetTable.limit"
                    layout="total, sizes, prev, pager, next, jumper"
                    :total="datasetTable.total"
                    @size-change="loadDatasets(1)"
                    @current-change="loadDatasets"
                />
            </div>
        </el-card>

        <el-card class="box-card !border-none mt-[12px]" shadow="never">
            <div class="detail-head">
                <div>
                    <div class="strong-text">{{ selectedDataset?.dataset_name || '请先选择报价单' }}</div>
                    <div class="muted">当前区域只管理选中报价单的数据，所有筛选和调整都不会影响其他报价单。</div>
                </div>
                <div class="detail-actions" v-if="selectedDataset">
                    <span class="detail-label">当前报价单</span>
                    <el-select
                        :model-value="selectedDataset.id"
                        filterable
                        placeholder="切换报价单"
                        class="w-[280px]"
                        @change="switchActiveDataset"
                    >
                        <el-option
                            v-for="item in datasetOptions"
                            :key="item.id"
                            :label="`${item.dataset_name}（${item.quotation_id}）`"
                            :value="item.id"
                        />
                    </el-select>
                    <el-tag type="success">报价ID {{ selectedDataset.quotation_id }}</el-tag>
                </div>
            </div>

            <el-empty v-if="!selectedDataset" description="请在上方选择一个报价单" />
            <template v-else>
                <div class="active-dataset-bar">
                    <div>
                        <strong>{{ selectedDataset.dataset_name }}</strong>
                        <span>{{ selectedDataset.price_name }} · {{ selectedDataset.channel_key }}</span>
                    </div>
                    <div class="active-dataset-hint">型号、容量、字段、加/扣钱项和备注内容都绑定在这张报价单下。</div>
                </div>
                <el-tabs v-model="activeTab" class="mt-[12px]" @tab-change="loadDetail(1)">
	                    <el-tab-pane label="价格管理" name="prices">
	                        <div class="section-toolbar">
	                            <el-form :inline="true" :model="detailSearch.prices">
                                <el-form-item label="报价单">
                                    <el-select
                                        v-model="detailSearch.prices.dataset_id"
                                        filterable
                                        placeholder="选择报价单"
                                        class="w-[240px]"
                                        @change="switchPriceDataset"
                                    >
                                        <el-option
                                            v-for="item in datasetOptions"
                                            :key="item.id"
                                            :label="`${item.dataset_name}（${item.quotation_id}）`"
                                            :value="item.id"
                                        />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="报价日期">
                                    <el-date-picker
                                        v-model="detailSearch.prices.price_date"
                                        type="date"
                                        value-format="YYYY-MM-DD"
                                        placeholder="默认今天"
                                        class="w-[150px]"
                                    />
                                </el-form-item>
                                <el-form-item label="型号">
                                    <el-input v-model="detailSearch.prices.model_name" clearable placeholder="型号关键词" />
                                </el-form-item>
                                <el-form-item label="容量">
                                    <el-input v-model="detailSearch.prices.capacity_name" clearable placeholder="容量" class="w-[120px]" />
                                </el-form-item>
                                <el-form-item label="价格项">
                                    <el-input v-model="detailSearch.prices.field_name" clearable placeholder="价格项" class="w-[140px]" />
                                </el-form-item>
	                                <el-form-item>
	                                    <el-button type="primary" @click="loadDetail(1)">查询</el-button>
	                                    <el-button @click="resetDetailSearch('prices')">重置</el-button>
	                                </el-form-item>
	                            </el-form>
	                            <div class="batch-mode-actions">
	                                <el-button v-if="!batchPriceMode" type="primary" @click="enterBatchPriceMode">批量调价</el-button>
	                                <template v-else>
	                                    <el-tag type="warning" size="large">已选 {{ selectedPriceCount }} 个价格</el-tag>
	                                    <el-button @click="selectCurrentPriceResult">选择当前结果</el-button>
	                                    <el-button @click="clearSelectedPrices">清空</el-button>
	                                    <el-button type="primary" :disabled="!selectedPriceCount" @click="openBatchPriceDialog">批量调整</el-button>
	                                    <el-button @click="exitBatchPriceMode">退出</el-button>
	                                </template>
	                            </div>
	                        </div>

                        <el-empty v-if="!priceMatrixGroups.length && !detailTable.prices.loading" description="暂无价格数据，请先预览并导入报价单" />
                        <div v-else v-loading="detailTable.prices.loading" class="matrix-group-list">
                            <div v-for="group in priceMatrixGroups" :key="group.group_key" class="matrix-group">
                                <div class="matrix-group-head">
                                    <div>
                                        <div class="matrix-title">{{ group.group_title }}</div>
                                        <div class="muted">
                                            {{ group.model_count }} 个型号，{{ group.capacity_count }} 个容量，{{ group.columns.length }} 个等级列。不同等级结构已自动拆表。
                                        </div>
                                    </div>
                                    <el-tag type="info">独立等级表</el-tag>
                                </div>
                                <el-table
                                    :data="group.rows"
                                    border
	                                    row-key="row_key"
	                                    :span-method="priceMatrixSpanMethodMap[group.group_key]"
	                                    class="quotation-matrix-table"
	                                >
	                                    <el-table-column prop="model_label" label="型号" min-width="210" fixed>
	                                        <template #default="{ row }">
	                                            <div class="model-cell">
	                                                <el-checkbox
	                                                    v-if="batchPriceMode"
	                                                    :model-value="isModelFullySelected(group, row.model_id)"
	                                                    :indeterminate="isModelPartiallySelected(group, row.model_id)"
	                                                    @change="toggleModelPrices(group, row.model_id, $event)"
	                                                />
	                                                <span>{{ row.model_label }}</span>
	                                            </div>
	                                        </template>
	                                    </el-table-column>
	                                    <el-table-column prop="capacity_label" label="容量" width="130" fixed>
	                                        <template #default="{ row }">
	                                            <div class="capacity-cell">
	                                                <el-checkbox
	                                                    v-if="batchPriceMode"
	                                                    :model-value="isRowFullySelected(row)"
	                                                    :indeterminate="isRowPartiallySelected(row)"
	                                                    @change="toggleRowPrices(row, $event)"
	                                                />
	                                                <span>{{ row.capacity_label }}</span>
	                                            </div>
	                                        </template>
	                                    </el-table-column>
	                                    <el-table-column
	                                        v-for="column in group.columns"
	                                        :key="column.field_id"
	                                        min-width="132"
	                                    >
	                                        <template #header>
	                                            <div class="grade-head">
	                                                <el-checkbox
	                                                    v-if="batchPriceMode"
	                                                    :model-value="isColumnFullySelected(group, column.field_id)"
	                                                    :indeterminate="isColumnPartiallySelected(group, column.field_id)"
	                                                    @change="toggleColumnPrices(group, column.field_id, $event)"
	                                                />
	                                                <span>{{ column.field_name }}</span>
	                                            </div>
	                                        </template>
	                                        <template #default="{ row }">
	                                            <div
	                                                v-if="row.prices?.[column.field_id]"
	                                                class="price-cell"
	                                                :class="{ 'is-selected': isPriceSelected(row.prices[column.field_id].id), 'is-batch-mode': batchPriceMode }"
	                                                role="button"
	                                                tabindex="0"
	                                                @click="handlePriceCellClick(row.prices[column.field_id])"
	                                                @keydown.enter.prevent="handlePriceCellClick(row.prices[column.field_id])"
	                                                @keydown.space.prevent="handlePriceCellClick(row.prices[column.field_id])"
	                                            >
	                                                <el-checkbox
	                                                    v-if="batchPriceMode"
	                                                    class="price-cell-check"
	                                                    :model-value="isPriceSelected(row.prices[column.field_id].id)"
	                                                    @click.stop
	                                                    @change="toggleSinglePrice(row.prices[column.field_id].id, $event)"
	                                                />
	                                                <strong>{{ row.prices[column.field_id].price_value_text }}</strong>
	                                                <span v-if="Number(row.prices[column.field_id].adjust_value || 0) !== 0">{{ row.prices[column.field_id].adjust_value_text }}</span>
	                                                <em v-if="row.prices[column.field_id].locked">锁定</em>
	                                            </div>
	                                            <span v-else class="muted">-</span>
	                                        </template>
	                                    </el-table-column>
                                    <el-table-column
                                        v-for="column in group.adjustment_columns"
                                        :key="`adjustment-${column.field_id}`"
                                        :label="column.field_name"
                                        :min-width="isDeductionColumn(column) ? 320 : 220"
                                    >
                                        <template #default="{ row }">
                                            <div v-if="getAdjustmentContent(row, column.field_id)" class="adjustment-content">
                                                <template v-if="isDeductionColumn(column)">
                                                    <span
                                                        v-for="rule in getAdjustmentRules(row, column.field_id)"
                                                        :key="rule"
                                                        class="adjustment-rule"
                                                        :class="getAdjustmentRuleClass(rule)"
                                                    >
                                                        {{ rule }}
                                                    </span>
                                                </template>
                                                <template v-else>{{ getAdjustmentContent(row, column.field_id) }}</template>
                                            </div>
                                            <span v-else class="muted">-</span>
                                        </template>
                                    </el-table-column>
                                    <el-table-column v-if="!group.adjustment_columns?.length" label="加/扣钱项" min-width="180">
                                        <template #default>
                                            <span class="muted">无</span>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>
                        </div>
                        <table-pagination :table="detailTable.prices" @load="loadDetail" />
                    </el-tab-pane>

                    <el-tab-pane label="型号" name="models">
                        <div class="section-toolbar">
                            <el-form :inline="true" :model="detailSearch.models">
                                <el-form-item label="型号">
                                    <el-input v-model="detailSearch.models.model_name" clearable placeholder="型号关键词" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" @click="loadDetail(1)">查询</el-button>
                                    <el-button @click="resetDetailSearch('models')">重置</el-button>
                                </el-form-item>
                            </el-form>
                            <el-button type="primary" @click="openManageDialog('model')">新增型号</el-button>
                        </div>
                        <el-table :data="detailTable.models.data" v-loading="detailTable.models.loading" border>
                            <el-table-column prop="external_goods_id" label="型号ID" width="100" />
                            <el-table-column prop="model_name" label="型号名称" min-width="180" />
                            <el-table-column prop="series_name" label="系列" min-width="120">
                                <template #default="{ row }">
                                    <el-tag v-if="row.series_name" size="small">{{ row.series_name }}</el-tag>
                                    <span v-else class="muted">未分组</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="热门" width="90">
                                <template #default="{ row }">
                                    <el-tag v-if="Number(row.is_hot || 0) === 1" type="danger" size="small">热门</el-tag>
                                    <span v-else class="muted">普通</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="price_name" label="报价名称" width="120" />
                            <el-table-column prop="sort" label="排序" width="80" />
                            <status-column />
                            <follow-column />
                            <el-table-column label="操作" width="140">
                                <template #default="{ row }">
                                    <el-button link type="primary" @click="openManageDialog('model', row)">编辑</el-button>
                                    <el-button link type="danger" @click="deleteManaged('model', row)">删除</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <table-pagination :table="detailTable.models" @load="loadDetail" />
                    </el-tab-pane>

                    <el-tab-pane label="容量" name="capacities">
                        <div class="section-toolbar">
                            <el-form :inline="true" :model="detailSearch.capacities">
                                <el-form-item label="型号">
                                    <el-select
                                        v-model="detailSearch.capacities.model_id"
                                        filterable
                                        clearable
                                        placeholder="全部型号"
                                        class="w-[220px]"
                                    >
                                        <el-option v-for="item in modelOptions" :key="item.id" :label="item.model_name" :value="item.id" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="容量">
                                    <el-input v-model="detailSearch.capacities.capacity_name" clearable placeholder="容量关键词" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" @click="loadDetail(1)">查询</el-button>
                                    <el-button @click="resetDetailSearch('capacities')">重置</el-button>
                                </el-form-item>
                            </el-form>
                            <el-button type="primary" @click="openManageDialog('capacity')">新增容量</el-button>
                        </div>
                        <el-table :data="detailTable.capacities.data" v-loading="detailTable.capacities.loading" border>
                            <el-table-column prop="external_goods_id" label="型号ID" width="100" />
                            <el-table-column prop="model_name" label="所属型号" min-width="170" />
                            <el-table-column prop="capacity_answer_id" label="容量ID" width="100" />
                            <el-table-column prop="capacity_name" label="容量名称" min-width="150" />
                            <el-table-column prop="sort" label="排序" width="80" />
                            <status-column />
                            <follow-column />
                            <el-table-column label="操作" width="140">
                                <template #default="{ row }">
                                    <el-button link type="primary" @click="openManageDialog('capacity', row)">编辑</el-button>
                                    <el-button link type="danger" @click="deleteManaged('capacity', row)">删除</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <table-pagination :table="detailTable.capacities" @load="loadDetail" />
                    </el-tab-pane>

                    <el-tab-pane label="价格调整" name="adjustments">
                        <div class="section-toolbar">
                            <el-form :inline="true" :model="detailSearch.adjustments">
                                <el-form-item label="型号">
                                    <el-select
                                        v-model="detailSearch.adjustments.model_id"
                                        filterable
                                        clearable
                                        placeholder="全部型号"
                                        class="w-[220px]"
                                    >
                                        <el-option v-for="item in modelOptions" :key="item.id" :label="item.model_name" :value="item.id" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="型号关键词">
                                    <el-input v-model="detailSearch.adjustments.model_name" clearable placeholder="可模糊搜索" />
                                </el-form-item>
                                <el-form-item label="调整项">
                                    <el-input v-model="detailSearch.adjustments.field_name" clearable placeholder="如 加/扣钱项、彩点" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" @click="loadDetail(1)">查询</el-button>
                                    <el-button @click="resetDetailSearch('adjustments')">重置</el-button>
                                </el-form-item>
                            </el-form>
                            <el-button type="primary" @click="openManageDialog('note', null, 'adjustment')">新增调整项内容</el-button>
                        </div>
                        <el-table :data="detailTable.adjustments.data" v-loading="detailTable.adjustments.loading" border>
                            <el-table-column prop="target_summary" label="覆盖范围" min-width="260" show-overflow-tooltip />
                            <el-table-column label="覆盖数量" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="row.is_shared ? 'success' : 'info'">{{ row.target_count || 1 }} 个</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="field_name" label="调整项" width="130" />
                            <el-table-column prop="content_text" label="规则内容" min-width="360" show-overflow-tooltip />
                            <status-column />
                            <follow-column />
                            <el-table-column label="操作" width="170">
                                <template #default="{ row }">
                                    <el-button link type="primary" @click="openManageDialog('note', row)">编辑规则</el-button>
                                    <el-button link type="danger" @click="deleteManaged('note', row)">删除规则</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <table-pagination :table="detailTable.adjustments" @load="loadDetail" />
                    </el-tab-pane>

                    <el-tab-pane label="字段" name="fields">
                        <div class="section-toolbar">
                            <el-form :inline="true" :model="detailSearch.fields">
                                <el-form-item label="字段">
                                    <el-input v-model="detailSearch.fields.field_name" clearable placeholder="字段关键词" />
                                </el-form-item>
                                <el-form-item label="类型">
                                    <el-select v-model="detailSearch.fields.field_type" clearable placeholder="全部" class="w-[150px]">
                                        <el-option label="价格项" value="price" />
                                        <el-option label="价格调整项" value="adjustment" />
                                        <el-option label="附加说明项" value="note" />
                                        <el-option label="报价说明" value="remark" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" @click="loadDetail(1)">查询</el-button>
                                    <el-button @click="resetDetailSearch('fields')">重置</el-button>
                                </el-form-item>
                            </el-form>
                            <el-button type="primary" @click="openManageDialog('field')">新增字段</el-button>
                        </div>
                        <el-table :data="detailTable.fields.data" v-loading="detailTable.fields.loading" border>
                            <el-table-column prop="question_id" label="字段ID" width="90" />
                            <el-table-column prop="field_name" label="字段名称" min-width="180" />
                            <el-table-column label="类型" width="120">
                                <template #default="{ row }">
                                    <el-tag :type="fieldTypeTag(row.field_type)">{{ row.field_type_name }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="sort" label="排序" width="80" />
                            <status-column />
                            <follow-column />
                            <el-table-column label="操作" width="150">
                                <template #default="{ row }">
                                    <el-button link type="primary" @click="openManageDialog('field', row)">编辑</el-button>
                                    <el-button link type="danger" @click="deleteManaged('field', row)">删除</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <table-pagination :table="detailTable.fields" @load="loadDetail" />
                    </el-tab-pane>

                    <!-- 暂时隐藏附加说明入口：价格调整页已经覆盖当前规则编辑体验。
                    <el-tab-pane label="附加说明" name="notes">
                        <div class="section-toolbar">
                            <el-form :inline="true" :model="detailSearch.notes">
                                <el-form-item label="型号">
                                    <el-select
                                        v-model="detailSearch.notes.model_id"
                                        filterable
                                        clearable
                                        placeholder="全部型号"
                                        class="w-[220px]"
                                    >
                                        <el-option v-for="item in modelOptions" :key="item.id" :label="item.model_name" :value="item.id" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="型号关键词">
                                    <el-input v-model="detailSearch.notes.model_name" clearable placeholder="可模糊搜索" />
                                </el-form-item>
                                <el-form-item label="说明项">
                                    <el-input v-model="detailSearch.notes.field_name" clearable placeholder="说明项" />
                                </el-form-item>
                                <el-form-item label="类型">
                                    <el-select v-model="detailSearch.notes.field_type" clearable placeholder="全部" class="w-[150px]">
                                        <el-option label="全部" value="" />
                                        <el-option label="价格调整项" value="adjustment" />
                                        <el-option label="附加说明项" value="note" />
                                        <el-option label="报价说明" value="remark" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" @click="loadDetail(1)">查询</el-button>
                                    <el-button @click="resetDetailSearch('notes')">重置</el-button>
                                </el-form-item>
                            </el-form>
                            <el-button type="primary" @click="openManageDialog('note', null, 'note')">新增说明内容</el-button>
                        </div>
                        <el-table :data="detailTable.notes.data" v-loading="detailTable.notes.loading" border>
                            <el-table-column prop="target_summary" label="覆盖范围" min-width="260" show-overflow-tooltip />
                            <el-table-column label="覆盖数量" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="row.is_shared ? 'success' : 'info'">{{ row.target_count || 1 }} 个</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="field_name" label="说明项" width="130" />
                            <el-table-column label="类型" width="120">
                                <template #default="{ row }">
                                    <el-tag :type="fieldTypeTag(row.field_type)">{{ row.field_type_name || row.content_type_name || row.content_type || row.field_type }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="content_text" label="内容" min-width="360" show-overflow-tooltip />
                            <status-column />
                            <follow-column />
                            <el-table-column label="操作" width="140">
                                <template #default="{ row }">
                                    <el-button link type="primary" @click="openManageDialog('note', row)">编辑规则</el-button>
                                    <el-button link type="danger" @click="deleteManaged('note', row)">删除规则</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <table-pagination :table="detailTable.notes" @load="loadDetail" />
                    </el-tab-pane>
                    -->

                    <el-tab-pane label="同步记录" name="logs">
                        <el-table :data="detailTable.logs.data" v-loading="detailTable.logs.loading" border>
                            <el-table-column prop="id" label="ID" width="80" />
                            <el-table-column label="状态" width="90">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status_name || (row.status === 1 ? '成功' : '失败') }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="触发方式" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="syncSourceTag(row.sync_source)">{{ row.sync_source_name || '未记录' }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="duration" label="耗时(ms)" width="100" />
                            <el-table-column label="导入" width="90">
                                <template #default="{ row }">
                                    <el-tag :type="row.imported ? 'success' : 'info'">{{ row.imported_name || (row.imported ? '已导入' : '仅预览') }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="channel_key" label="渠道" width="110" />
                            <el-table-column label="统计" min-width="300">
                                <template #default="{ row }">
                                    型号 {{ row.stats?.model_count || row.stats?.import?.models_saved || 0 }}，
                                    容量 {{ row.stats?.capacity_count || row.stats?.import?.capacities_saved || 0 }}，
                                    价格 {{ row.stats?.price_count || row.stats?.import?.prices_saved || 0 }}，
                                    调整 {{ row.stats?.adjustment_count || row.stats?.import?.adjustments_saved || 0 }}，
                                    说明 {{ row.stats?.note_count || row.stats?.import?.notes_saved || 0 }}
                                </template>
                            </el-table-column>
                            <el-table-column prop="error_message" label="错误信息" min-width="220" show-overflow-tooltip />
                            <el-table-column prop="create_at" label="时间" width="170">
                                <template #default="{ row }">{{ displayTime(row.create_at_text || row.create_at) }}</template>
                            </el-table-column>
                        </el-table>
                        <table-pagination :table="detailTable.logs" @load="loadDetail" />
                    </el-tab-pane>
                </el-tabs>
            </template>
        </el-card>

        <el-dialog v-model="previewDialog.show" title="同步预览" width="960px">
            <div v-if="previewDialog.data">
                <div class="preview-stats">
                    <div><span>型号</span><strong>{{ previewDialog.data.stats.model_count }}</strong></div>
                    <div><span>容量</span><strong>{{ previewDialog.data.stats.capacity_count }}</strong></div>
                    <div><span>价格项</span><strong>{{ previewDialog.data.stats.price_field_count }}</strong></div>
                    <div><span>价格</span><strong>{{ previewDialog.data.stats.price_count }}</strong></div>
                    <div><span>价格调整项</span><strong>{{ previewDialog.data.stats.adjustment_field_count || 0 }}</strong></div>
                    <div><span>调整规则</span><strong>{{ previewDialog.data.stats.adjustment_count || 0 }}</strong></div>
                    <div><span>说明项</span><strong>{{ previewDialog.data.stats.note_field_count }}</strong></div>
                    <div><span>说明</span><strong>{{ previewDialog.data.stats.note_count }}</strong></div>
                </div>
                <el-alert v-if="previewDialog.data.warnings?.length" type="warning" class="mt-[12px]" :closable="false" :title="`有 ${previewDialog.data.warnings.length} 条提醒，请确认后再导入。`" />
                <el-tabs class="mt-[12px]">
                    <el-tab-pane label="字段分类">
                        <el-table :data="previewDialog.data.fields" max-height="320" border>
                            <el-table-column prop="field_name" label="字段" />
                            <el-table-column prop="field_type" label="类型" width="120" />
                            <el-table-column prop="question_id" label="字段ID" width="100" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane label="型号样例">
                        <el-table :data="previewDialog.data.sample_models" max-height="320" border>
                            <el-table-column prop="external_goods_id" label="型号ID" width="100" />
                            <el-table-column prop="model_name" label="型号" />
                            <el-table-column label="容量数" width="90">
                                <template #default="{ row }">{{ row.capacities?.length || 0 }}</template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane label="价格样例">
                        <el-table :data="previewDialog.data.sample_prices" max-height="320" border>
                            <el-table-column prop="external_goods_id" label="型号ID" width="90" />
                            <el-table-column prop="capacity_answer_id" label="容量ID" width="90" />
                            <el-table-column prop="field_name" label="价格项" />
                            <el-table-column prop="crawler_price" label="价格" width="100" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane label="说明样例">
                        <el-table :data="previewDialog.data.sample_notes" max-height="320" border>
                            <el-table-column prop="external_goods_id" label="型号ID" width="90" />
                            <el-table-column prop="capacity_answer_id" label="容量ID" width="90" />
                            <el-table-column prop="field_name" label="说明项" width="130" />
                            <el-table-column prop="content_text" label="内容" show-overflow-tooltip />
                        </el-table>
                    </el-tab-pane>
                </el-tabs>
            </div>
            <template #footer>
                <el-button @click="previewDialog.show = false">取消</el-button>
                <el-button type="primary" :loading="importLoading" @click="importPreview">确认导入</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="datasetDialog.show" :title="datasetDialog.form.id ? '编辑报价单' : '新增报价单'" width="620px">
            <el-form :model="datasetDialog.form" label-width="120px">
                <el-form-item label="报价ID"><el-input-number v-model="datasetDialog.form.quotation_id" :min="1" /></el-form-item>
                <el-form-item label="报价名称"><el-input v-model="datasetDialog.form.price_name" placeholder="例如 花机/内爆" /></el-form-item>
                <el-form-item label="显示名称"><el-input v-model="datasetDialog.form.dataset_name" placeholder="后台展示名称" /></el-form-item>
                <el-form-item label="导航图标">
                    <upload-image v-model="datasetDialog.form.nav_image" :limit="1" />
                    <div class="form-tip">用于低代码图文导航展示；不填时前台显示默认占位图。</div>
                </el-form-item>
                <el-form-item label="渠道"><el-input v-model="datasetDialog.form.channel_key" placeholder="默认 chaoniu" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="datasetDialog.form.sort" :min="0" /></el-form-item>
                <el-form-item label="状态"><el-switch v-model="datasetDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="自动同步">
                    <el-switch v-model="datasetDialog.form.sync_enabled" :active-value="1" :inactive-value="0" />
                    <div class="form-tip">开启后系统计划任务每小时检查一次。只有报价单启用且到达同步间隔时才会真正请求第三方。</div>
                </el-form-item>
                <el-form-item label="同步间隔">
                    <el-select v-model="datasetDialog.form.sync_interval" class="w-[220px]">
                        <el-option label="每 6 小时" :value="21600" />
                        <el-option label="每 12 小时" :value="43200" />
                        <el-option label="每天一次" :value="86400" />
                        <el-option label="每 3 天" :value="259200" />
                        <el-option label="每 7 天" :value="604800" />
                    </el-select>
                    <div class="form-tip">这是同一张报价单两次自动同步之间的最短间隔。手动同步不受限制。</div>
                </el-form-item>
                <el-form-item label="备注"><el-input v-model="datasetDialog.form.remark" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="datasetDialog.show = false">取消</el-button>
                <el-button type="primary" @click="saveDataset">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="manageDialog.show" :title="manageDialogTitle" width="620px">
            <el-form :model="manageDialog.form" label-width="120px">
                <el-alert
                    v-if="selectedDataset"
                    type="info"
                    :closable="false"
                    class="mb-[12px]"
                    :title="`当前报价单：${selectedDataset.dataset_name}（报价ID ${selectedDataset.quotation_id}）`"
                />
                <template v-if="manageDialog.type === 'model'">
                    <el-alert type="success" :closable="false" class="mb-[12px]" title="新增型号会直接归属到当前报价单；不同报价单可以有同名型号，互不影响。" />
                    <el-form-item label="型号名称"><el-input v-model="manageDialog.form.model_name" /></el-form-item>
                    <el-form-item label="系列名称">
                        <el-input v-model="manageDialog.form.series_name" placeholder="例如 iPhone 17 系列、OPPO Reno 系列" clearable />
                        <span class="form-tip">前端会按系列分组展示，留空时会按分组编号兜底。</span>
                    </el-form-item>
                    <el-form-item label="系列分组">
                        <el-input-number v-model="manageDialog.form.group_key" :min="0" />
                        <span class="form-tip">保留给爬虫原始分组使用，同一编号会在报价表中优先放到一起展示。</span>
                    </el-form-item>
                    <el-form-item label="热门型号">
                        <el-switch v-model="manageDialog.form.is_hot" :active-value="1" :inactive-value="0" />
                        <span class="form-tip">开启后，移动端可通过热门筛选快速找到该型号。</span>
                    </el-form-item>
                </template>
                <template v-if="manageDialog.type === 'capacity'">
                    <el-alert type="success" :closable="false" class="mb-[12px]" title="容量必须挂在某个型号下面，例如 iPhone 16 Pro Max 下的 256G、512G、1T。" />
                    <el-form-item label="所属型号">
                        <el-select v-model="manageDialog.form.model_id" filterable placeholder="请选择型号">
                            <el-option v-for="item in modelOptions" :key="item.id" :label="item.model_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="容量名称"><el-input v-model="manageDialog.form.capacity_name" /></el-form-item>
                </template>
                <template v-if="manageDialog.type === 'field'">
                    <el-alert type="success" :closable="false" class="mb-[12px]" title="字段决定表格列和备注类型。价格项会出现在报价表，价格调整项和附加说明项会出现在规则内容里。" />
                    <el-form-item label="字段名称"><el-input v-model="manageDialog.form.field_name" /></el-form-item>
                    <el-form-item label="字段类型">
                        <el-select v-model="manageDialog.form.field_type">
                            <el-option label="价格项" value="price" />
                            <el-option label="价格调整项" value="adjustment" />
                            <el-option label="附加说明项" value="note" />
                            <el-option label="报价说明" value="remark" />
                        </el-select>
                    </el-form-item>
                    <el-alert type="info" :closable="false" title="字段类型会写入该报价单的解析规则，下次同步仍按这里的设置解析。" />
                </template>
                <template v-if="manageDialog.type === 'note'">
                    <el-alert type="success" :closable="false" class="mb-[12px]" title="同一段价格调整或说明可以同时应用到多个型号、多个容量；后续编辑共用项时可以一次同步修改同一批规则。" />
                    <el-form-item label="筛选型号">
                        <el-select
                            v-model="manageDialog.form.model_filter_ids"
                            multiple
                            collapse-tags
                            collapse-tags-tooltip
                            filterable
                            clearable
                            placeholder="可先选型号，再勾选容量"
                            class="w-full"
                            @change="onNoteTargetModelFilterChange"
                        >
                            <el-option v-for="item in modelOptions" :key="item.id" :label="item.model_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="应用范围">
                        <el-select
                            v-model="manageDialog.form.target_keys"
                            multiple
                            collapse-tags
                            collapse-tags-tooltip
                            filterable
                            placeholder="选择多个型号/容量共用同一段内容"
                            class="w-full"
                        >
                            <el-option
                                v-for="item in capacityOptions"
                                :key="item.id"
                                :label="`${item.model_name || '未命名型号'} / ${item.capacity_name}`"
                                :value="`${item.model_id}#${item.id}`"
                            />
                        </el-select>
                        <span class="form-tip">列表支持搜索型号或容量。爬虫带来的共用规则会回显全部覆盖范围，保存后按这里的选择整体更新。</span>
                    </el-form-item>
                    <el-form-item label="字段类型" v-if="!manageDialog.form.id">
                        <el-radio-group v-model="manageDialog.form.field_type" @change="onManageFieldTypeChange">
                            <el-radio label="adjustment">价格调整项</el-radio>
                            <el-radio label="note">附加说明项</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item label="字段">
                        <el-select v-model="manageDialog.form.field_id" filterable placeholder="请选择字段">
                            <el-option v-for="item in fieldOptions" :key="item.id" :label="`${item.field_name}（${item.field_type_name}）`" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-alert
                        v-if="!manageDialog.form.id && !fieldOptions.length"
                        type="warning"
                        :closable="false"
                        class="mb-[12px]"
                        title="当前报价单还没有这个类型的字段。你可以在下面输入新字段名称，保存时系统会先创建字段，再写入内容。"
                    />
                    <el-form-item label="新字段名称" v-if="!manageDialog.form.id && !manageDialog.form.field_id">
                        <el-input v-model="manageDialog.form.new_field_name" placeholder="例如：加/扣钱项、颜色加/扣钱项、备注说明" />
                    </el-form-item>
                    <el-form-item label="内容">
                        <el-input v-model="manageDialog.form.content_html" type="textarea" :rows="7" placeholder="例如：白色+100 蓝色-300，或：前彩点-300 后彩点-350" />
                    </el-form-item>
                </template>
                <template v-if="manageDialog.type !== 'note'">
                    <el-form-item label="排序"><el-input-number v-model="manageDialog.form.sort" :min="0" /></el-form-item>
                </template>
                <el-form-item label="状态"><el-switch v-model="manageDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="同步策略">
                    <el-radio-group v-model="manageDialog.form.follow_crawler">
                        <el-radio :label="1">跟随爬虫</el-radio>
                        <el-radio :label="0">自定义保留</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="manageDialog.show = false">取消</el-button>
                <el-button type="primary" @click="saveManaged">保存</el-button>
            </template>
        </el-dialog>

	        <el-dialog v-model="priceDialog.show" title="设置 SKU 价格" width="500px">
            <el-form :model="priceDialog.form" label-width="110px">
                <el-alert
                    type="info"
                    :closable="false"
                    class="mb-[12px]"
                    title="价格调整只作用于当前点击的一个 SKU：型号 + 容量 + 等级/价格项。"
                />
                <el-form-item label="调整方式">
                    <el-select v-model="priceDialog.form.adjust_type">
                        <el-option label="固定金额" :value="1" />
                        <el-option label="百分比" :value="2" />
                        <el-option label="覆盖价格" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item label="调整值"><el-input-number v-model="priceDialog.form.adjust_value" :precision="2" /></el-form-item>
                <el-form-item label="锁定价格"><el-switch v-model="priceDialog.form.locked" :active-value="1" :inactive-value="0" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="priceDialog.show = false">取消</el-button>
                <el-button type="primary" @click="savePriceAdjust">保存</el-button>
	            </template>
	        </el-dialog>

	        <el-dialog v-model="batchPriceDialog.show" title="批量调价" width="620px">
	            <div class="batch-summary">
	                <div><span>已选价格</span><strong>{{ selectedPriceCount }}</strong></div>
	                <div><span>型号</span><strong>{{ selectedPriceSummary.modelCount }}</strong></div>
	                <div><span>容量</span><strong>{{ selectedPriceSummary.capacityCount }}</strong></div>
	                <div><span>等级</span><strong>{{ selectedPriceSummary.fieldCount }}</strong></div>
	            </div>
	            <el-form :model="batchPriceDialog.form" label-width="110px" class="mt-[14px]">
	                <el-form-item label="调整方式">
	                    <el-select v-model="batchPriceDialog.form.adjust_type" class="w-full">
	                        <el-option label="固定金额" :value="1" />
	                        <el-option label="百分比" :value="2" />
	                        <el-option label="覆盖价格" :value="3" />
	                    </el-select>
	                </el-form-item>
	                <el-form-item label="调整值">
	                    <el-input-number v-model="batchPriceDialog.form.adjust_value" :precision="2" class="w-full" />
	                </el-form-item>
	                <el-form-item label="锁定策略">
	                    <el-radio-group v-model="batchPriceDialog.form.lock_mode">
	                        <el-radio label="keep">不改变</el-radio>
	                        <el-radio label="lock">调整后锁定</el-radio>
	                        <el-radio label="unlock">调整后解锁</el-radio>
	                    </el-radio-group>
	                </el-form-item>
	                <el-alert
	                    type="warning"
	                    :closable="false"
	                    title="批量调价只作用于当前选中的价格记录。提交前请确认选中范围，保存后会刷新当前报价矩阵。"
	                />
	            </el-form>
	            <div v-if="selectedPricePreview.length" class="batch-preview">
	                <div class="batch-preview-title">影响示例</div>
	                <div v-for="item in selectedPricePreview" :key="item.id" class="batch-preview-row">
	                    <span>{{ item.model_label }} / {{ item.capacity_label }} / {{ item.field_name }}</span>
	                    <strong>{{ item.price_value_text }}</strong>
	                </div>
	            </div>
	            <template #footer>
	                <el-button @click="batchPriceDialog.show = false">取消</el-button>
	                <el-button type="primary" :loading="batchPriceDialog.loading" :disabled="!selectedPriceCount" @click="saveBatchPriceAdjust">确认应用</el-button>
	            </template>
	        </el-dialog>
	    </div>
	</template>

<script setup lang="ts">
import { computed, defineComponent, h, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox, ElPagination, ElTableColumn } from 'element-plus'
import {
    addQuotationV2Capacity,
    addQuotationV2Dataset,
    addQuotationV2Field,
	    addQuotationV2Model,
	    addQuotationV2Note,
	    adjustQuotationV2Price,
	    batchAdjustQuotationV2Price,
	    deleteQuotationV2Capacity,
    deleteQuotationV2Dataset,
    deleteQuotationV2Field,
    deleteQuotationV2Model,
    deleteQuotationV2Note,
    deleteQuotationV2NoteGroup,
    editQuotationV2Capacity,
    editQuotationV2Dataset,
    editQuotationV2Field,
    editQuotationV2Model,
    editQuotationV2Note,
    editQuotationV2NoteGroup,
    getQuotationV2Capacities,
    getQuotationV2DatasetAll,
    getQuotationV2DatasetList,
    getQuotationV2Fields,
    getQuotationV2Logs,
    getQuotationV2Models,
    getQuotationV2Notes,
    getQuotationV2PriceMatrix,
    getQuotationV2Prices,
    importQuotationV2Preview,
    initQuotationV2ChaoniuDefaults,
    previewQuotationV2Dataset,
    syncQuotationV2Now
} from '@/addon/recycle_daheng_quote/api/quotation'

type TableState = {
    page: number
    limit: number
    total: number
    loading: boolean
    data: any[]
}

const createTable = (limit = 10): TableState => ({
    page: 1,
    limit,
    total: 0,
    loading: false,
    data: []
})

const datasetTable = reactive({
    ...createTable(10),
    search: {
        quotation_id: '',
        dataset_name: '',
        status: ''
    }
})
const detailTable = reactive<Record<string, TableState>>({
    prices: createTable(1000),
    adjustments: createTable(20),
    models: createTable(20),
    capacities: createTable(20),
    fields: createTable(20),
    notes: createTable(20),
    logs: createTable(10)
})
const detailSearch = reactive<Record<string, any>>({
    prices: { dataset_id: '', price_date: '', model_name: '', capacity_name: '', field_name: '' },
    adjustments: { model_id: '', model_name: '', field_name: '', field_type: 'adjustment' },
    models: { model_name: '' },
    capacities: { model_id: '', capacity_name: '' },
    fields: { field_name: '', field_type: '' },
    notes: { model_id: '', model_name: '', field_name: '', field_type: '' },
    logs: {}
})

const selectedDataset = ref<any>(null)
const activeTab = ref('prices')
const importLoading = ref(false)
const previewLoadingId = ref(0)
const syncLoadingId = ref(0)
const autoSyncLoadingId = ref(0)
const datasetOptions = ref<any[]>([])
const modelOptions = ref<any[]>([])
const capacityOptions = ref<any[]>([])
const fieldOptions = ref<any[]>([])
const OPTION_LIMIT = 120
const WORKBENCH_STATE_KEY = 'recycle_daheng_quote_v2_workbench_state'

const datasetDialog = reactive<any>({ show: false, form: {} })
const previewDialog = reactive<any>({ show: false, data: null })
const manageDialog = reactive<any>({ show: false, type: '', form: {} })
const priceDialog = reactive<any>({ show: false, form: {} })
const batchPriceDialog = reactive<any>({
    show: false,
    loading: false,
    form: {
        adjust_type: 1,
        adjust_value: 0,
        lock_mode: 'keep'
    }
})
const priceMatrix = reactive<any>({ columns: [], adjustment_columns: [], rows: [], groups: [] })
const batchPriceMode = ref(false)
const selectedPriceIds = ref<Set<number>>(new Set())

const responseRows = (res: any) => Array.isArray(res.data) ? res.data : (res.data?.data || [])
const responseListRows = (res: any) => res.data?.list || res.data?.data || []
const responseTotal = (res: any) => Number(res.data?.total || 0)
const responseMatrix = (res: any) => res.data?.matrix || { columns: [], adjustment_columns: [], rows: [], groups: [] }
const loadAllOptionRows = async (api: Function, params: Record<string, any>) => {
    const limit = OPTION_LIMIT
    let page = 1
    let total = 0
    const rows: any[] = []
    do {
        const res = await api({ ...params, page, limit })
        const pageRows = responseRows(res)
        rows.push(...pageRows)
        total = responseTotal(res)
        if (!pageRows.length || pageRows.length < limit) break
        page++
    } while (!total || rows.length < total)
    return rows
}

const readWorkbenchState = () => {
    try {
        return JSON.parse(localStorage.getItem(WORKBENCH_STATE_KEY) || '{}')
    } catch (error) {
        return {}
    }
}

const writeWorkbenchState = () => {
    localStorage.setItem(WORKBENCH_STATE_KEY, JSON.stringify({
        activeTab: activeTab.value,
        selectedDatasetId: selectedDataset.value?.id || detailSearch.prices.dataset_id || '',
        datasetPage: datasetTable.page,
        datasetLimit: datasetTable.limit,
        detailPages: Object.fromEntries(Object.entries(detailTable).map(([key, table]) => [key, table.page])),
        detailLimits: Object.fromEntries(Object.entries(detailTable).map(([key, table]) => [key, table.limit]))
    }))
}

const restoreWorkbenchState = () => {
    const state = readWorkbenchState()
    if (state.activeTab && state.activeTab !== 'notes' && detailTable[state.activeTab]) {
        activeTab.value = state.activeTab
    }
    if (Number(state.datasetPage || 0) > 0) datasetTable.page = Number(state.datasetPage)
    if (Number(state.datasetLimit || 0) > 0) datasetTable.limit = Number(state.datasetLimit)
    Object.entries(state.detailPages || {}).forEach(([key, page]) => {
        if (detailTable[key] && Number(page || 0) > 0) detailTable[key].page = Number(page)
    })
    Object.entries(state.detailLimits || {}).forEach(([key, limit]) => {
        if (detailTable[key] && Number(limit || 0) > 0) detailTable[key].limit = Number(limit)
    })
    return state
}

const buildSpanMap = (rows: any[], keyGetter: (row: any) => string) => {
    const spans: Record<number, number> = {}
    let start = 0
    while (start < rows.length) {
        const key = keyGetter(rows[start])
        let end = start + 1
        while (end < rows.length && keyGetter(rows[end]) === key) end++
        spans[start] = end - start
        for (let index = start + 1; index < end; index++) spans[index] = 0
        start = end
    }
    return spans
}

const priceModelSpan = computed(() => buildSpanMap(detailTable.prices.data, (row: any) => String(row.model_id || row.external_goods_id || '')))
const priceCapacitySpan = computed(() => buildSpanMap(detailTable.prices.data, (row: any) => `${row.model_id || row.external_goods_id || ''}-${row.capacity_id || row.capacity_answer_id || ''}`))
const priceMatrixColumns = computed(() => priceMatrix.columns || [])
const priceMatrixRows = computed(() => priceMatrix.rows || [])
const priceMatrixGroups = computed(() => priceMatrix.groups || [])
const selectedPriceCount = computed(() => selectedPriceIds.value.size)
const allMatrixPrices = computed(() => priceMatrixGroups.value.flatMap((group: any) => getGroupPrices(group)))
const selectedMatrixPrices = computed(() => allMatrixPrices.value.filter((item: any) => selectedPriceIds.value.has(Number(item.id || 0))))
const selectedPriceSummary = computed(() => {
    const modelIds = new Set(selectedMatrixPrices.value.map((item: any) => item.model_id))
    const capacityIds = new Set(selectedMatrixPrices.value.map((item: any) => item.capacity_id))
    const fieldIds = new Set(selectedMatrixPrices.value.map((item: any) => item.field_id))
    return {
        modelCount: modelIds.size,
        capacityCount: capacityIds.size,
        fieldCount: fieldIds.size
    }
})
const selectedPricePreview = computed(() => selectedMatrixPrices.value.slice(0, 5))
const priceMatrixModelSpan = computed(() => buildSpanMap(priceMatrixRows.value, (row: any) => String(row.model_id || row.external_goods_id || '')))
const priceMatrixSpanMethodMap = computed(() => {
    const map: Record<string, Function> = {}
    priceMatrixGroups.value.forEach((group: any) => {
        const rows = group.rows || []
        const modelSpans = buildSpanMap(rows, (row: any) => String(row.model_id || row.external_goods_id || ''))
        const adjustmentColumns = group.adjustment_columns || []
        const adjustmentColumnStart = 2 + (group.columns || []).length
        const adjustmentSpans: Record<number, Record<number, number>> = {}
        adjustmentColumns.forEach((column: any) => {
            const fieldId = Number(column.field_id || 0)
            adjustmentSpans[fieldId] = buildSpanMap(rows, (row: any) => {
                const modelKey = String(row.model_id || row.external_goods_id || '')
                const content = String(row.adjustment_span_key?.[fieldId] ?? getAdjustmentContent(row, fieldId) ?? '')
                return `${modelKey}#${fieldId}#${content}`
            })
        })
        map[group.group_key] = ({ rowIndex, columnIndex }: any) => {
            if (columnIndex === 0) {
                return { rowspan: modelSpans[rowIndex] ?? 1, colspan: modelSpans[rowIndex] === 0 ? 0 : 1 }
            }
            if (columnIndex >= adjustmentColumnStart && columnIndex < adjustmentColumnStart + adjustmentColumns.length) {
                const adjustmentColumn = adjustmentColumns[columnIndex - adjustmentColumnStart]
                const fieldId = Number(adjustmentColumn?.field_id || 0)
                const spans = adjustmentSpans[fieldId] || {}
                return { rowspan: spans[rowIndex] ?? 1, colspan: spans[rowIndex] === 0 ? 0 : 1 }
            }
        }
    })
    return map
})

const priceSpanMethod = ({ rowIndex, columnIndex }: any) => {
    if (columnIndex === 1) {
        return { rowspan: priceModelSpan.value[rowIndex] ?? 1, colspan: priceModelSpan.value[rowIndex] === 0 ? 0 : 1 }
    }
    if (columnIndex === 2 || columnIndex === 7) {
        return { rowspan: priceCapacitySpan.value[rowIndex] ?? 1, colspan: priceCapacitySpan.value[rowIndex] === 0 ? 0 : 1 }
    }
}

const normalizeChecked = (value: string | number | boolean) => value === true

const getRowPrices = (row: any) => Object.values(row.prices || {})
    .map((price: any) => ({
        ...price,
        model_id: row.model_id,
        model_label: row.model_label,
        capacity_id: row.capacity_id,
        capacity_label: row.capacity_label
    }))
    .filter((price: any) => Number(price.id || 0) > 0)

const getRowPriceIds = (row: any) => getRowPrices(row).map((price: any) => Number(price.id))

const getGroupPrices = (group: any) => (group.rows || []).flatMap((row: any) => getRowPrices(row))

const getModelPriceIds = (group: any, modelId: number) => (group.rows || [])
    .filter((row: any) => Number(row.model_id || 0) === Number(modelId || 0))
    .flatMap((row: any) => getRowPriceIds(row))

const getColumnPriceIds = (group: any, fieldId: number) => (group.rows || [])
    .map((row: any) => Number(row.prices?.[fieldId]?.id || 0))
    .filter(Boolean)

const areAllSelected = (ids: number[]) => ids.length > 0 && ids.every(id => selectedPriceIds.value.has(id))
const areSomeSelected = (ids: number[]) => ids.some(id => selectedPriceIds.value.has(id))
const isPriceSelected = (id: number) => selectedPriceIds.value.has(Number(id || 0))
const isRowFullySelected = (row: any) => areAllSelected(getRowPriceIds(row))
const isRowPartiallySelected = (row: any) => {
    const ids = getRowPriceIds(row)
    return !areAllSelected(ids) && areSomeSelected(ids)
}
const isModelFullySelected = (group: any, modelId: number) => areAllSelected(getModelPriceIds(group, modelId))
const isModelPartiallySelected = (group: any, modelId: number) => {
    const ids = getModelPriceIds(group, modelId)
    return !areAllSelected(ids) && areSomeSelected(ids)
}
const isColumnFullySelected = (group: any, fieldId: number) => areAllSelected(getColumnPriceIds(group, fieldId))
const isColumnPartiallySelected = (group: any, fieldId: number) => {
    const ids = getColumnPriceIds(group, fieldId)
    return !areAllSelected(ids) && areSomeSelected(ids)
}

const setSelectedPriceIds = (ids: number[], checked: boolean) => {
    const next = new Set(selectedPriceIds.value)
    ids.filter(Boolean).forEach(id => {
        if (checked) next.add(Number(id))
        else next.delete(Number(id))
    })
    selectedPriceIds.value = next
}

const toggleSinglePrice = (id: number, checked: string | number | boolean) => {
    setSelectedPriceIds([Number(id || 0)], normalizeChecked(checked))
}

const toggleRowPrices = (row: any, checked: string | number | boolean) => {
    setSelectedPriceIds(getRowPriceIds(row), normalizeChecked(checked))
}

const toggleModelPrices = (group: any, modelId: number, checked: string | number | boolean) => {
    setSelectedPriceIds(getModelPriceIds(group, modelId), normalizeChecked(checked))
}

const toggleColumnPrices = (group: any, fieldId: number, checked: string | number | boolean) => {
    setSelectedPriceIds(getColumnPriceIds(group, fieldId), normalizeChecked(checked))
}

const enterBatchPriceMode = () => {
    batchPriceMode.value = true
}

const exitBatchPriceMode = () => {
    batchPriceMode.value = false
    clearSelectedPrices()
}

const clearSelectedPrices = () => {
    selectedPriceIds.value = new Set()
}

const selectCurrentPriceResult = () => {
    setSelectedPriceIds(allMatrixPrices.value.map((item: any) => Number(item.id || 0)), true)
}

const handlePriceCellClick = (price: any) => {
    if (batchPriceMode.value) {
        toggleSinglePrice(price.id, !isPriceSelected(price.id))
        return
    }
    openPriceDialog(price)
}

const openBatchPriceDialog = () => {
    if (!selectedPriceCount.value) {
        ElMessage.warning('请先选择需要调整的价格')
        return
    }
    batchPriceDialog.form = {
        adjust_type: 1,
        adjust_value: 0,
        lock_mode: 'keep'
    }
    batchPriceDialog.show = true
}

const priceMatrixSpanMethod = ({ rowIndex, columnIndex }: any) => {
    if (columnIndex === 0) {
        return { rowspan: priceMatrixModelSpan.value[rowIndex] ?? 1, colspan: priceMatrixModelSpan.value[rowIndex] === 0 ? 0 : 1 }
    }
}

const TablePagination = defineComponent({
    name: 'TablePagination',
    props: {
        table: { type: Object, required: true }
    },
    emits: ['load'],
    setup(props, { emit }) {
        return () => h('div', { class: 'pagination-wrap' }, [
            h(ElPagination, {
                currentPage: props.table.page,
                pageSize: props.table.limit,
                total: props.table.total,
                layout: 'total, sizes, prev, pager, next, jumper',
                'onUpdate:currentPage': (page: number) => { props.table.page = page },
                'onUpdate:pageSize': (limit: number) => { props.table.limit = limit },
                onSizeChange: () => emit('load', 1),
                onCurrentChange: (page: number) => emit('load', page)
            })
        ])
    }
})

const StatusColumn = defineComponent({
    name: 'StatusColumn',
    setup() {
        return () => h(ElTableColumn, { label: '状态', width: 90 }, {
            default: ({ row }: any) => h('span', { class: row.status ? 'status-on' : 'status-off' }, row.status ? '启用' : '停用')
        })
    }
})

const FollowColumn = defineComponent({
    name: 'FollowColumn',
    setup() {
        return () => h(ElTableColumn, { label: '同步策略', width: 100 }, {
            default: ({ row }: any) => h('span', { class: row.follow_crawler ? 'status-on' : 'status-custom' }, row.follow_crawler ? '跟随' : '自定义')
        })
    }
})

const manageDialogTitle = computed(() => {
    const action = manageDialog.form?.id ? '编辑' : '新增'
    const map: Record<string, string> = {
        model: `${action}型号`,
        capacity: `${action}容量`,
        field: `${action}字段`,
        note: manageDialog.form?.field_type === 'adjustment' ? `${action}价格调整项内容` : `${action}附加说明内容`
    }
    return map[manageDialog.type] || action
})

const todayDate = () => {
    const date = new Date()
    const pad = (num: number) => String(num).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const syncIntervalOptions = [
    { label: '每 6 小时', value: 21600 },
    { label: '每 12 小时', value: 43200 },
    { label: '每天一次', value: 86400 },
    { label: '每 3 天', value: 259200 },
    { label: '每 7 天', value: 604800 }
]

const syncIntervalText = (seconds: number) => {
    const value = Number(seconds || 0)
    const option = syncIntervalOptions.find((item) => item.value === value)
    if (option) return option.label
    if (value >= 86400) return `每 ${Math.round(value / 86400)} 天`
    if (value >= 3600) return `每 ${Math.round(value / 3600)} 小时`
    return `每 ${Math.max(1, Math.round(value / 60))} 分钟`
}

const nextSyncTime = (row: any) => {
    if (!row.sync_enabled) return ''
    const last = Number(row.last_sync_at || 0)
    if (!last) return '等待首次自动同步'
    return `下次最早 ${formatTime(last + Number(row.sync_interval || 86400))}`
}

const autoSyncText = (row: any) => {
    if (!row.sync_enabled) return '开启后按所选间隔自动抓取'
    return `${syncIntervalText(Number(row.sync_interval || 86400))}，${nextSyncTime(row)}`
}

const loadDatasetOptions = async () => {
    const res = await getQuotationV2DatasetAll({ status: 1 })
    datasetOptions.value = responseRows(res)
}

const patchRowById = (rows: any[], id: number, patch: Record<string, any>) => {
    const index = rows.findIndex((item: any) => Number(item.id) === Number(id))
    if (index < 0) return false
    rows[index] = { ...rows[index], ...patch }
    return true
}

const removeRowById = (rows: any[], id: number) => {
    const index = rows.findIndex((item: any) => Number(item.id) === Number(id))
    if (index < 0) return false
    rows.splice(index, 1)
    return true
}

const patchDatasetLocal = (id: number, patch: Record<string, any>) => {
    patchRowById(datasetTable.data, id, patch)
    if (selectedDataset.value && Number(selectedDataset.value.id) === Number(id)) {
        selectedDataset.value = { ...selectedDataset.value, ...patch }
        if (!detailSearch.prices.dataset_id) {
            detailSearch.prices.dataset_id = selectedDataset.value.id
        }
    }

    const optionIndex = datasetOptions.value.findIndex((item: any) => Number(item.id) === Number(id))
    if (Number(patch.status ?? 1) === 1) {
        if (optionIndex >= 0) {
            datasetOptions.value[optionIndex] = { ...datasetOptions.value[optionIndex], ...patch }
        } else if (selectedDataset.value && Number(selectedDataset.value.id) === Number(id)) {
            datasetOptions.value.unshift({ ...selectedDataset.value })
        }
    } else if (optionIndex >= 0) {
        datasetOptions.value.splice(optionIndex, 1)
    }
}

const patchManagedLocal = (type: string, id: number, patch: Record<string, any>) => {
    const table = detailTable[activeTab.value]
    if (table?.data) {
        patchRowById(table.data, id, patch)
    }

    if (type === 'model') {
        patchRowById(modelOptions.value, id, patch)
    } else if (type === 'capacity') {
        patchRowById(capacityOptions.value, id, patch)
    } else if (type === 'field') {
        patchRowById(fieldOptions.value, id, patch)
    }
}

const loadManageOptions = async (type = '') => {
    if (!selectedDataset.value?.id) return
    const datasetId = selectedDataset.value.id
    if (type === 'capacity' || type === 'note' || type === '') {
        modelOptions.value = await loadAllOptionRows(getQuotationV2Models, { dataset_id: datasetId })
    }
    if (type === 'note' || type === '') {
        const modelFilterIds = Array.isArray(manageDialog.form?.model_filter_ids)
            ? manageDialog.form.model_filter_ids
            : []
        const capacityRows = await loadAllOptionRows(getQuotationV2Capacities, {
            dataset_id: datasetId,
            model_id: ''
        })
        capacityOptions.value = modelFilterIds.length
            ? capacityRows.filter((item: any) => modelFilterIds.includes(Number(item.model_id || 0)))
            : capacityRows
        const fieldRows = await loadAllOptionRows(getQuotationV2Fields, {
            dataset_id: datasetId,
            field_type: manageDialog.form?.field_type || ''
        })
        fieldOptions.value = fieldRows.filter((item: any) => ['adjustment', 'note'].includes(item.field_type))
    }
}

const onManageModelChange = async () => {
    manageDialog.form.capacity_id = ''
    await loadManageOptions('note')
}

const onNoteTargetModelFilterChange = async () => {
    await loadManageOptions('note')
    const availableKeys = new Set(capacityOptions.value.map((item: any) => `${item.model_id}#${item.id}`))
    manageDialog.form.target_keys = (manageDialog.form.target_keys || []).filter((key: string) => availableKeys.has(key))
}

const onManageFieldTypeChange = async () => {
    manageDialog.form.field_id = ''
    await loadManageOptions('note')
}

const resetDatasetScopedFilters = () => {
    detailSearch.capacities.model_id = ''
    detailSearch.adjustments.model_id = ''
    detailSearch.notes.model_id = ''
}

const loadDatasets = async (page = 1) => {
    datasetTable.loading = true
    datasetTable.page = page
    try {
        const res = await getQuotationV2DatasetList({
            page: datasetTable.page,
            limit: datasetTable.limit,
            ...datasetTable.search
        })
        datasetTable.data = responseRows(res)
        datasetTable.total = responseTotal(res)
        if (!selectedDataset.value && datasetTable.data.length) {
            const state = readWorkbenchState()
            selectedDataset.value = datasetTable.data.find((item: any) => Number(item.id) === Number(state.selectedDatasetId || 0)) || datasetTable.data[0]
            detailSearch.prices.dataset_id = selectedDataset.value.id
            await loadDetail(detailTable[activeTab.value]?.page || 1)
        } else if (selectedDataset.value) {
            const latest = datasetTable.data.find((item: any) => item.id === selectedDataset.value.id)
            if (latest) selectedDataset.value = latest
            if (!detailSearch.prices.dataset_id) {
                detailSearch.prices.dataset_id = selectedDataset.value.id
            }
        }
    } finally {
        datasetTable.loading = false
    }
}

const syncActiveDatasetOption = () => {
    if (!selectedDataset.value?.id) return
    const latest = datasetOptions.value.find((item: any) => Number(item.id) === Number(selectedDataset.value.id))
    if (latest) selectedDataset.value = latest
}

const loadDetail = async (page = 1) => {
    if (!selectedDataset.value?.id) return
    if (['capacities', 'adjustments', 'notes'].includes(activeTab.value)) {
        await ensureModelOptions()
    }
    const table = detailTable[activeTab.value]
    table.loading = true
    table.page = page
    try {
        if (activeTab.value === 'prices') {
            await loadPriceMatrix()
            return
        }
        const params = {
            page: table.page,
            limit: table.limit,
            dataset_id: selectedDataset.value.id,
            ...detailSearch[activeTab.value]
        }
        const apiMap: Record<string, Function> = {
            adjustments: getQuotationV2Notes,
            models: getQuotationV2Models,
            capacities: getQuotationV2Capacities,
            fields: getQuotationV2Fields,
            notes: getQuotationV2Notes,
            logs: getQuotationV2Logs
        }
        const res = await apiMap[activeTab.value](params)
        table.data = responseRows(res)
        priceMatrix.columns = []
        priceMatrix.adjustment_columns = []
        priceMatrix.rows = []
        priceMatrix.groups = []
        table.total = responseTotal(res)
    } finally {
        table.loading = false
    }
}

const loadPriceMatrix = async () => {
    const table = detailTable.prices
    const datasetId = Number(detailSearch.prices.dataset_id || selectedDataset.value?.id || 0)
    if (!datasetId) return
    const res = await getQuotationV2PriceMatrix({
        dataset_id: datasetId,
        price_date: detailSearch.prices.price_date || todayDate(),
        model_name: detailSearch.prices.model_name,
        capacity_name: detailSearch.prices.capacity_name,
        field_name: detailSearch.prices.field_name
    })
    table.data = responseListRows(res)
    table.total = responseTotal(res)
    const matrix = responseMatrix(res)
	    priceMatrix.columns = matrix.columns || []
	    priceMatrix.adjustment_columns = matrix.adjustment_columns || []
	    priceMatrix.rows = matrix.rows || []
	    priceMatrix.groups = normalizeMatrixGroups(matrix.groups || [])
	    clearSelectedPrices()
	}

const ensureModelOptions = async () => {
    if (!selectedDataset.value?.id || modelOptions.value.length) return
    modelOptions.value = await loadAllOptionRows(getQuotationV2Models, { dataset_id: selectedDataset.value.id })
}

const normalizeMatrixGroups = (groups: any[]) => {
    return (groups || []).map((group: any) => {
        const rows = (group.rows || []).map((row: any) => ({ ...row }))
        const adjustmentColumns = group.adjustment_columns || []
        const modelRows: Record<string, any[]> = {}
        const groupDisplayMap: Record<number, any> = {}
        const groupTextMap: Record<number, string> = {}

        adjustmentColumns.forEach((column: any) => {
            const fieldId = Number(column.field_id || 0)
            if (!canInheritAcrossModels(column)) return
            const nonEmptyItems = rows
                .map((row: any) => row.adjustments?.[fieldId])
                .filter((item: any) => String(item?.content_text || '').trim() !== '')
            const uniqueTexts = Array.from(new Set(nonEmptyItems.map((item: any) => String(item.content_text || '').trim())))
            if (uniqueTexts.length === 1 && nonEmptyItems.length > 0) {
                groupDisplayMap[fieldId] = nonEmptyItems[0]
                groupTextMap[fieldId] = uniqueTexts[0]
            }
        })

        rows.forEach((row: any) => {
            const modelKey = String(row.model_id || row.external_goods_id || '')
            if (!modelRows[modelKey]) modelRows[modelKey] = []
            modelRows[modelKey].push(row)
        })

        Object.values(modelRows).forEach((items: any[]) => {
            adjustmentColumns.forEach((column: any) => {
                const fieldId = Number(column.field_id || 0)
                const canInherit = canInheritWithinModel(column)
                const nonEmptyItems = items
                    .map((row: any) => row.adjustments?.[fieldId])
                    .filter((item: any) => String(item?.content_text || '').trim() !== '')
                const uniqueTexts = Array.from(new Set(nonEmptyItems.map((item: any) => String(item.content_text || '').trim())))

                if (canInherit && uniqueTexts.length === 1 && nonEmptyItems.length > 0) {
                    const displayItem = nonEmptyItems[0]
                    items.forEach((row: any) => {
                        row.adjustment_display = row.adjustment_display || {}
                        row.adjustment_span_key = row.adjustment_span_key || {}
                        row.adjustment_display[fieldId] = displayItem
                        row.adjustment_span_key[fieldId] = uniqueTexts[0]
                    })
                } else if (canInherit && uniqueTexts.length === 0 && groupDisplayMap[fieldId]) {
                    items.forEach((row: any) => {
                        row.adjustment_display = row.adjustment_display || {}
                        row.adjustment_span_key = row.adjustment_span_key || {}
                        row.adjustment_display[fieldId] = groupDisplayMap[fieldId]
                        row.adjustment_span_key[fieldId] = groupTextMap[fieldId]
                    })
                } else {
                    items.forEach((row: any) => {
                        row.adjustment_span_key = row.adjustment_span_key || {}
                        row.adjustment_span_key[fieldId] = String(row.adjustments?.[fieldId]?.content_text || '').trim()
                    })
                }
            })
        })

        return { ...group, rows }
    })
}

const getAdjustmentContent = (row: any, fieldId: number | string) => {
    const id = Number(fieldId || 0)
    return row.adjustment_display?.[id]?.content_text || row.adjustments?.[id]?.content_text || ''
}

const normalizeFieldName = (column: any) => String(column?.field_name || '').replace(/\s+/g, '')

const isDeductionColumn = (column: any) => normalizeFieldName(column).includes('加/扣钱项')

const canInheritAcrossModels = (column: any) => {
    const name = normalizeFieldName(column)
    return name === '加/扣钱项' || name === '彩点' || name === '蓝光'
}

const canInheritWithinModel = (column: any) => {
    const name = normalizeFieldName(column)
    return name !== '颜色加/扣钱项'
}

const getAdjustmentRules = (row: any, fieldId: number | string) => {
    const content = getAdjustmentContent(row, fieldId)
    return content
        .split(/\s+/)
        .map((item: string) => item.trim())
        .filter(Boolean)
}

const getAdjustmentRuleClass = (rule: string) => {
    if (rule.includes('+') || rule.includes('加')) return 'is-add'
    if (rule.includes('-') || rule.includes('扣')) return 'is-minus'
    return ''
}

const selectDataset = async (row: any) => {
	    selectedDataset.value = row
	    detailSearch.prices.dataset_id = row.id
        writeWorkbenchState()
	    clearSelectedPrices()
	    batchPriceMode.value = false
	    resetDatasetScopedFilters()
    modelOptions.value = []
    capacityOptions.value = []
    fieldOptions.value = []
    await loadDetail(1)
}

const switchActiveDataset = async (datasetId: number) => {
    const dataset = datasetOptions.value.find((item: any) => Number(item.id) === Number(datasetId))
        || datasetTable.data.find((item: any) => Number(item.id) === Number(datasetId))
    if (!dataset) return
	    selectedDataset.value = dataset
	    detailSearch.prices.dataset_id = dataset.id
        writeWorkbenchState()
	    clearSelectedPrices()
	    batchPriceMode.value = false
	    resetDatasetScopedFilters()
    modelOptions.value = []
    capacityOptions.value = []
    fieldOptions.value = []
    await loadDetail(1)
}

const switchPriceDataset = async (datasetId: number) => {
    await switchActiveDataset(datasetId)
}

const resetDatasetSearch = async () => {
    datasetTable.search.quotation_id = ''
    datasetTable.search.dataset_name = ''
    datasetTable.search.status = ''
    await loadDatasets(1)
}

const resetDetailSearch = async (tab: string) => {
    Object.keys(detailSearch[tab]).forEach((key) => {
        if (key === 'field_type') {
            detailSearch[tab][key] = tab === 'adjustments' ? 'adjustment' : ''
        } else if (tab === 'prices' && key === 'dataset_id') {
            detailSearch[tab][key] = selectedDataset.value?.id || ''
        } else if (tab === 'prices' && key === 'price_date') {
            detailSearch[tab][key] = todayDate()
        } else {
            detailSearch[tab][key] = ''
        }
    })
	    await loadDetail(1)
	}

const initDefaults = async () => {
    await initQuotationV2ChaoniuDefaults()
    await loadDatasetOptions()
    await loadDatasets(1)
}

const previewDataset = async (row: any) => {
    previewLoadingId.value = row.id
    try {
        const res = await previewQuotationV2Dataset(row.id)
        previewDialog.data = res.data
        previewDialog.show = true
    } finally {
        previewLoadingId.value = 0
    }
}

const importPreview = async () => {
    if (!previewDialog.data?.log_id) return
    importLoading.value = true
    try {
        await importQuotationV2Preview(previewDialog.data.log_id)
        ElMessage.success('导入成功')
        previewDialog.show = false
        await loadDatasets(datasetTable.page)
        await loadDetail(1)
    } finally {
        importLoading.value = false
    }
}

const syncNow = async (row: any) => {
    await ElMessageBox.confirm(`确定直接同步 ${row.dataset_name} 吗？建议首次使用先点“预览”。`, '确认同步', { type: 'warning' })
    syncLoadingId.value = row.id
    try {
        await syncQuotationV2Now(row.id)
        await loadDatasets(datasetTable.page)
        await loadDetail(1)
    } finally {
        syncLoadingId.value = 0
    }
}

const toggleDatasetAutoSync = async (row: any) => {
    autoSyncLoadingId.value = row.id
    const nextValue = Number(row.sync_enabled || 0)
    const oldValue = nextValue ? 0 : 1
    try {
        const payload = {
            ...row,
            sync_enabled: nextValue,
            sync_interval: Number(row.sync_interval || 86400)
        }
        await editQuotationV2Dataset(row.id, payload)
        patchDatasetLocal(Number(row.id), payload)
        ElMessage.success(nextValue ? '已开启自动同步' : '已关闭自动同步')
    } catch (error) {
        row.sync_enabled = oldValue
        throw error
    } finally {
        autoSyncLoadingId.value = 0
    }
}

const openDatasetDialog = (row?: any) => {
    datasetDialog.form = row ? { ...row } : {
        quotation_id: 0,
        price_name: '',
        dataset_name: '',
        nav_image: '',
        channel_key: 'chaoniu',
        sort: 0,
        status: 1,
        sync_enabled: 0,
        sync_interval: 86400,
        remark: ''
    }
    datasetDialog.show = true
}

const saveDataset = async () => {
    if (datasetDialog.form.id) {
        await editQuotationV2Dataset(datasetDialog.form.id, datasetDialog.form)
        patchDatasetLocal(Number(datasetDialog.form.id), { ...datasetDialog.form })
        datasetDialog.show = false
        ElMessage.success('保存成功')
        return
    } else {
        await addQuotationV2Dataset(datasetDialog.form)
    }
    datasetDialog.show = false
    await loadDatasetOptions()
    syncActiveDatasetOption()
    await loadDatasets(datasetTable.page)
}

const deleteDataset = async (row: any) => {
    await ElMessageBox.confirm(`确定删除 ${row.dataset_name} 吗？型号、容量、价格、说明和同步记录会一起删除。`, '删除报价单', { type: 'warning' })
    await deleteQuotationV2Dataset(row.id)
    selectedDataset.value = null
    detailSearch.prices.dataset_id = ''
    await loadDatasetOptions()
    await loadDatasets(1)
}

const openManageDialog = async (type: string, row?: any, fieldType = '') => {
    if (!selectedDataset.value?.id) {
        ElMessage.warning('请先选择报价单')
        return
    }
    manageDialog.type = type
    const base = {
        dataset_id: selectedDataset.value.id,
        quotation_id: selectedDataset.value.quotation_id,
        price_name: selectedDataset.value.price_name,
        status: 1,
        follow_crawler: 0,
        sort: 0
    }
    if (row) {
        const targets = Array.isArray(row.targets) ? row.targets : []
        const targetKeys = targets
            .map((item: any) => `${item.model_id}#${item.capacity_id}`)
            .filter((key: string) => !key.startsWith('0#') && !key.endsWith('#0'))
        const modelFilterIds = Array.from(new Set(targets.map((item: any) => Number(item.model_id || 0)).filter(Boolean)))
        manageDialog.form = {
            ...row,
            model_id: targets[0]?.model_id || row.model_id,
            capacity_id: targets[0]?.capacity_id || row.capacity_id,
            model_filter_ids: modelFilterIds,
            target_keys: targetKeys,
            update_shared: sharedTargetCount(row) > 1 ? 1 : 0
        }
    } else if (type === 'model') {
        manageDialog.form = { ...base, model_name: '', group_key: 0, series_name: '', is_hot: 0 }
    } else if (type === 'capacity') {
        manageDialog.form = { ...base, model_id: '', capacity_name: '' }
    } else if (type === 'field') {
        manageDialog.form = { ...base, field_name: '', field_type: 'adjustment' }
    } else if (type === 'note') {
        manageDialog.form = { ...base, model_id: '', capacity_id: '', capacity_ids: [], model_filter_ids: [], target_keys: [], field_id: '', field_type: fieldType || 'adjustment', new_field_name: '', content_html: '', content_text: '', update_shared: 0 }
    } else {
        manageDialog.form = { ...base }
    }
    if (manageDialog.form.follow_crawler === undefined || manageDialog.form.follow_crawler === null) {
        manageDialog.form.follow_crawler = 0
    }
    await loadManageOptions(type)
    manageDialog.show = true
}

const saveManaged = async () => {
    if (!validateManageForm()) return
    const id = manageDialog.form.id || 0
    const editApiMap: Record<string, Function> = {
        model: editQuotationV2Model,
        capacity: editQuotationV2Capacity,
        field: editQuotationV2Field,
        note: editQuotationV2Note
    }
    const addApiMap: Record<string, Function> = {
        model: addQuotationV2Model,
        capacity: addQuotationV2Capacity,
        field: addQuotationV2Field,
        note: addQuotationV2Note
    }
    if (id) {
        if (manageDialog.type === 'note') {
            manageDialog.form.targets = buildNoteTargets()
            await editQuotationV2NoteGroup(id, manageDialog.form)
        } else {
            await editApiMap[manageDialog.type](id, manageDialog.form)
        }
        patchManagedLocal(manageDialog.type, Number(id), { ...manageDialog.form })
        manageDialog.show = false
        ElMessage.success('保存成功')
        return
    } else {
        if (manageDialog.type === 'note' && !manageDialog.form.field_id && manageDialog.form.new_field_name) {
            const fieldRes = await addQuotationV2Field({
                dataset_id: selectedDataset.value.id,
                quotation_id: selectedDataset.value.quotation_id,
                price_name: selectedDataset.value.price_name,
                field_name: manageDialog.form.new_field_name,
                field_type: manageDialog.form.field_type || 'note',
                status: 1,
                follow_crawler: 0,
                sort: 0
            })
            manageDialog.form.field_id = fieldRes.data?.id || fieldRes.data?.data?.id || 0
        }
        if (manageDialog.type === 'note') {
            manageDialog.form.targets = buildNoteTargets()
        }
        await addApiMap[manageDialog.type](manageDialog.form)
    }
    manageDialog.show = false
    await loadManageOptions()
    await loadDetail(detailTable[activeTab.value].page)
}

const validateManageForm = () => {
    const form = manageDialog.form || {}
    if (manageDialog.type === 'model' && !String(form.model_name || '').trim()) {
        ElMessage.warning('请输入型号名称')
        return false
    }
    if (manageDialog.type === 'capacity') {
        if (!form.model_id) {
            ElMessage.warning('请选择所属型号')
            return false
        }
        if (!String(form.capacity_name || '').trim()) {
            ElMessage.warning('请输入容量名称')
            return false
        }
    }
    if (manageDialog.type === 'field') {
        if (!String(form.field_name || '').trim()) {
            ElMessage.warning('请输入字段名称')
            return false
        }
        if (!form.field_type) {
            ElMessage.warning('请选择字段类型')
            return false
        }
    }
    if (manageDialog.type === 'note') {
        const targets = buildNoteTargets()
        if (!targets.length) {
            ElMessage.warning('请选择至少一个型号/容量')
            return false
        }
        if (!form.field_id && !String(form.new_field_name || '').trim()) {
            ElMessage.warning('请选择字段，或输入新字段名称')
            return false
        }
        if (!String(form.content_html || '').trim()) {
            ElMessage.warning('请输入内容')
            return false
        }
    }
    return true
}

const deleteManaged = async (type: string, row: any) => {
    const nameMap: Record<string, string> = {
        model: row.model_name,
        capacity: row.capacity_name,
        field: row.field_name,
        note: `${row.field_name || '该规则'}（${row.target_count || 1} 个型号/容量）`
    }
    await ElMessageBox.confirm(`确定删除 ${nameMap[type] || '该记录'} 吗？相关明细会一并清理。`, '删除确认', { type: 'warning' })
    const apiMap: Record<string, Function> = {
        model: deleteQuotationV2Model,
        capacity: deleteQuotationV2Capacity,
        field: deleteQuotationV2Field,
        note: deleteQuotationV2NoteGroup
    }
    await apiMap[type](row.id)
    const table = detailTable[activeTab.value]
    if (table?.data && removeRowById(table.data, Number(row.id))) {
        table.total = Math.max(0, Number(table.total || 0) - 1)
    }
    if (type === 'model') {
        removeRowById(modelOptions.value, Number(row.id))
    } else if (type === 'capacity') {
        removeRowById(capacityOptions.value, Number(row.id))
    } else if (type === 'field') {
        removeRowById(fieldOptions.value, Number(row.id))
    }
    ElMessage.success('删除成功')
}

const openPriceDialog = (row: any) => {
    priceDialog.form = {
        id: row.id,
        adjust_type: row.adjust_type || 1,
        adjust_value: Number(row.adjust_value || 0),
        locked: row.locked || 0
    }
    priceDialog.show = true
}

const savePriceAdjust = async () => {
    await adjustQuotationV2Price(priceDialog.form.id, priceDialog.form)
    priceDialog.show = false
    await loadDetail(detailTable.prices.page)
}

const saveBatchPriceAdjust = async () => {
    if (!selectedPriceCount.value) {
        ElMessage.warning('请先选择需要调整的价格')
        return
    }
    const ids = Array.from(selectedPriceIds.value)
    const lockPayload: Record<string, number> = {}
    if (batchPriceDialog.form.lock_mode === 'lock') lockPayload.locked = 1
    if (batchPriceDialog.form.lock_mode === 'unlock') lockPayload.locked = 0
    await ElMessageBox.confirm(
        `将批量调整 ${ids.length} 个价格，确定继续吗？`,
        '确认批量调价',
        { type: 'warning' }
    )
    batchPriceDialog.loading = true
    try {
        await batchAdjustQuotationV2Price({
            ids,
            adjust_type: batchPriceDialog.form.adjust_type,
            adjust_value: batchPriceDialog.form.adjust_value,
            ...lockPayload
        })
        batchPriceDialog.show = false
        clearSelectedPrices()
        await loadDetail(detailTable.prices.page)
    } finally {
        batchPriceDialog.loading = false
    }
}

const fieldTypeTag = (type: string) => {
    if (type === 'price') return 'success'
    if (type === 'adjustment') return 'danger'
    if (type === 'note') return 'warning'
    return 'info'
}

const syncSourceTag = (source: string) => {
    if (source === 'auto') return 'success'
    if (source === 'manual') return 'primary'
    if (source === 'preview') return 'warning'
    return 'info'
}

const formatTime = (time: number) => {
    if (!time) return '暂无'
    const date = new Date(Number(time) * 1000)
    const pad = (num: number) => String(num).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const displayTime = (time: any) => {
    if (!time) return '暂无'
    if (typeof time === 'string' && time.includes('-')) return time
    return formatTime(Number(time || 0))
}

const imageUrl = (value: string) => {
    const url = String(value || '').trim()
    if (!url) return ''
    if (/^(https?:)?\/\//.test(url) || url.startsWith('data:')) return url
    return url.startsWith('/') ? url : `/${url}`
}

const normalizeMergeItems = (value: any) => Array.isArray(value) ? value.filter(Boolean).map(String) : []

const sharedTargetCount = (row: any) => {
    if (Number(row?.target_count || 0) > 0) return Number(row.target_count)
    const mergeItems = normalizeMergeItems(row?.merge_items)
    return mergeItems.length || 1
}

const buildNoteTargets = () => {
    const keys = Array.isArray(manageDialog.form.target_keys) ? manageDialog.form.target_keys : []
    return keys.map((key: string) => {
        const [modelId, capacityId] = String(key).split('#').map((item) => Number(item || 0))
        return { model_id: modelId, capacity_id: capacityId }
    }).filter((item: any) => item.model_id > 0 && item.capacity_id > 0)
}

onMounted(async () => {
    const state = restoreWorkbenchState()
    detailSearch.prices.price_date = todayDate()
    if (state.selectedDatasetId) {
        detailSearch.prices.dataset_id = Number(state.selectedDatasetId)
    }
    await loadDatasetOptions()
    if (!selectedDataset.value && state.selectedDatasetId) {
        selectedDataset.value = datasetOptions.value.find((item: any) => Number(item.id) === Number(state.selectedDatasetId)) || null
    }
    await loadDatasets(datasetTable.page || 1)
})

watch(
    [
        activeTab,
        () => selectedDataset.value?.id,
        () => datasetTable.page,
        () => datasetTable.limit,
        () => detailTable.prices.page,
        () => detailTable.models.page,
        () => detailTable.capacities.page,
        () => detailTable.adjustments.page,
        () => detailTable.fields.page,
        () => detailTable.notes.page,
        () => detailTable.logs.page
    ],
    writeWorkbenchState
)
</script>

<style scoped lang="scss">
.quotation-v2-page {
    .page-head,
    .detail-head,
    .section-toolbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

	    .head-actions,
	    .section-toolbar {
	        flex-wrap: wrap;
	    }

    .batch-mode-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .detail-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .detail-label {
        color: #6b7280;
        font-size: 13px;
    }

    .active-dataset-bar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-top: 14px;
        padding: 12px 14px;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        background: #f0fdf4;
    }

    .active-dataset-bar strong,
    .active-dataset-bar span {
        display: block;
    }

    .active-dataset-bar strong {
        color: #14532d;
        font-weight: 700;
    }

    .active-dataset-bar span,
    .active-dataset-hint,
    .form-tip {
        margin-top: 4px;
        color: #15803d;
        font-size: 13px;
        line-height: 1.5;
    }

    .form-tip {
        margin-left: 10px;
        color: #6b7280;
    }

    .page-desc,
    .muted {
        margin-top: 5px;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.5;
    }

    .strong-text {
        color: #111827;
        font-weight: 700;
    }

    .dataset-name-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .dataset-nav-image {
        width: 38px;
        height: 38px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        flex-shrink: 0;
        background: #f9fafb;
    }

    .dataset-name-main {
        min-width: 0;
    }

    .auto-sync-cell {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #111827;
        font-size: 13px;
        font-weight: 600;
    }

    .filter-form {
        margin-top: 16px;
        padding: 12px 12px 0;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
    }

    .guide-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
    }

    .guide-item {
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
    }

    .guide-item strong,
    .guide-item span {
        display: block;
    }

    .guide-item span {
        margin-top: 6px;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.5;
    }

    .pagination-wrap {
        display: flex;
        justify-content: flex-end;
        margin-top: 14px;
    }

    .preview-stats {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 10px;
    }

    .preview-stats > div {
        padding: 10px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
    }

    .preview-stats span,
    .preview-stats strong {
        display: block;
    }

    .preview-stats span {
        color: #6b7280;
        font-size: 12px;
    }

    .preview-stats strong {
        margin-top: 5px;
        color: #111827;
        font-size: 20px;
    }

    .adjustment-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 4px 0;
    }

    .adjustment-item {
        display: grid;
        grid-template-columns: 86px minmax(0, 1fr);
        gap: 8px;
        align-items: flex-start;
        font-size: 12px;
        line-height: 1.5;
    }

    .adjustment-name {
        color: #b42318;
        font-weight: 600;
    }

    .adjustment-content {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        color: #344054;
        word-break: break-word;
        white-space: pre-wrap;
    }

    .adjustment-rule {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 2px 8px;
        border: 1px solid #d0d5dd;
        border-radius: 4px;
        background: #f9fafb;
        color: #344054;
        font-size: 12px;
        line-height: 1.4;
    }

    .adjustment-rule.is-add {
        border-color: #a6f4c5;
        background: #ecfdf3;
        color: #067647;
    }

    .adjustment-rule.is-minus {
        border-color: #fecdc9;
        background: #fffbfa;
        color: #b42318;
    }

	    .price-cell {
        position: relative;
	        display: inline-flex;
	        flex-direction: column;
	        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 48px;
        border: 0;
        border-radius: 4px;
        background: #f0fdf4;
        color: #1f2937;
	        cursor: pointer;
	    }

	    .price-cell:hover {
	        background: #dcfce7;
	    }

    .price-cell.is-batch-mode {
        padding: 8px 8px 8px 30px;
        background: #f8fafc;
        border: 1px solid #d0d5dd;
    }

    .price-cell.is-batch-mode:hover {
        background: #eff6ff;
        border-color: #93c5fd;
    }

    .price-cell.is-selected {
        background: #dbeafe;
        border-color: #2563eb;
        box-shadow: inset 0 0 0 1px #2563eb;
    }

    .price-cell-check {
        position: absolute;
        left: 8px;
        top: 50%;
        transform: translateY(-50%);
        height: 16px;
    }

    .price-cell strong {
        font-size: 16px;
        line-height: 1.3;
    }

    .price-cell span,
    .price-cell em {
        margin-top: 2px;
        color: #b42318;
        font-size: 12px;
        font-style: normal;
    }

    .matrix-group-list {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .matrix-group {
        overflow: hidden;
        border: 1px solid #d0d5dd;
        border-radius: 8px;
        background: #fff;
    }

    .matrix-group-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 16px;
        border-bottom: 1px solid #d0d5dd;
        background: #f8fafc;
    }

	    .matrix-title {
	        color: #111827;
        font-size: 16px;
        font-weight: 700;
	        line-height: 1.4;
	    }

    .model-cell,
    .capacity-cell,
    .grade-head {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .model-cell span,
    .capacity-cell span,
    .grade-head span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .batch-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .batch-summary > div {
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #f9fafb;
    }

    .batch-summary span,
    .batch-summary strong {
        display: block;
    }

    .batch-summary span {
        color: #6b7280;
        font-size: 12px;
    }

    .batch-summary strong {
        margin-top: 4px;
        color: #111827;
        font-size: 20px;
        line-height: 1.2;
    }

    .batch-preview {
        margin-top: 14px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        overflow: hidden;
    }

    .batch-preview-title {
        padding: 9px 12px;
        color: #111827;
        font-size: 13px;
        font-weight: 700;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }

    .batch-preview-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 12px;
        border-top: 1px solid #f2f4f7;
        font-size: 13px;
    }

    .batch-preview-row:first-of-type {
        border-top: 0;
    }

    .batch-preview-row span {
        min-width: 0;
        color: #344054;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .batch-preview-row strong {
        color: #111827;
        font-weight: 700;
        flex-shrink: 0;
    }

    .quotation-matrix-table {
        :deep(.el-table__header-wrapper th) {
            background: #1f2937;
            color: #fff;
            font-weight: 700;
        }

        :deep(.el-table__fixed-header-wrapper th),
        :deep(.el-table__fixed-right .el-table__fixed-header-wrapper th) {
            background: #1f2937;
            color: #fff;
        }

        :deep(.el-table__cell) {
            vertical-align: middle;
        }
    }

    .status-on,
    .status-custom,
    .status-off {
        display: inline-flex;
        align-items: center;
        height: 24px;
        padding: 0 8px;
        border-radius: 4px;
        font-size: 12px;
    }

    .status-on {
        color: #067647;
        background: #ecfdf3;
    }

    .status-custom {
        color: #b54708;
        background: #fffaeb;
    }

    .status-off {
        color: #667085;
        background: #f2f4f7;
    }
}

@media (max-width: 1200px) {
    .quotation-v2-page {
        .guide-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .preview-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
}
</style>
