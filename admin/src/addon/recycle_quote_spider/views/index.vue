<template>
    <div class="main-container quote-spider-page">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-lg">回收报价数据源</span>
                <div class="flex gap-2">
                    <el-button @click="createDefaultSource">创建默认源</el-button>
                    <el-button type="primary" @click="openSourceDialog()">新增报价源</el-button>
                </div>
            </div>

            <el-tabs v-model="activeTab" class="mt-[16px]" @tab-change="handleTabChange">
                <el-tab-pane label="报价源" name="source">
                    <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                        <el-form :inline="true" :model="sourceQuery">
                            <el-form-item label="报价源">
                                <el-input v-model="sourceQuery.keyword" clearable placeholder="名称、标识、服务商" @keyup.enter="loadSources(1)" />
                            </el-form-item>
                            <el-form-item label="状态">
                                <el-select v-model="sourceQuery.status" clearable placeholder="全部" class="w-[120px]">
                                    <el-option label="启用" :value="1" />
                                    <el-option label="停用" :value="0" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="loadSources(1)">查询</el-button>
                                <el-button @click="resetSourceSearch">重置</el-button>
                            </el-form-item>
                        </el-form>
                    </el-card>
                    <el-table
                        v-loading="sourceLoading"
                        :data="sourceTable.data"
                        border
                        size="large"
                        row-key="id"
                        highlight-current-row
                        class="mt-[10px]"
                    >
                        <template #empty>
                            <span>{{ !sourceLoading ? '暂无数据' : '' }}</span>
                        </template>
                        <el-table-column prop="source_name" label="报价源" min-width="180">
                            <template #default="{ row }">
                                <div class="strong-text">{{ row.source_name }}</div>
                                <div class="muted">{{ row.source_key || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="provider" label="服务商" min-width="120">
                            <template #default="{ row }">{{ row.provider || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="base_url" label="基础地址" min-width="260" show-overflow-tooltip />
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="自动同步" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.sync_enabled === 1 ? 'success' : 'info'">{{ row.sync_enabled === 1 ? '开启' : '关闭' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="最近同步" min-width="180">
                            <template #default="{ row }">{{ formatTime(row.last_sync_at) }}</template>
                        </el-table-column>
                        <el-table-column label="操作" fixed="right" width="260">
                            <template #default="{ row }">
                                <el-button link type="primary" @click="openSourceData(row)">管理数据</el-button>
                                <el-button link type="primary" @click="openSourceDialog(row)">编辑</el-button>
                                <el-button link type="warning" :loading="syncingId === row.id" @click="syncSource(row)">同步</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="sourceTable.page"
                            v-model:page-size="sourceTable.limit"
                            :total="sourceTable.total"
                            :page-sizes="[10, 20, 50]"
                            layout="total, sizes, prev, pager, next, jumper"
                            @size-change="handleSourceSizeChange"
                            @current-change="loadSources"
                        />
                    </div>
                </el-tab-pane>

                <el-tab-pane label="数据管理" name="manage">
                    <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                        <el-form :inline="true" :model="itemQuery" class="filter-form">
                            <el-form-item label="报价源">
                                <el-select v-model="selectedSourceId" clearable filterable placeholder="全部报价源" class="w-[220px]" @change="handleSourceFilterChange">
                                    <el-option v-for="item in sourceOptions" :key="item.id" :label="item.source_name" :value="item.id" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="分类">
                                <el-tree-select
                                    v-model="selectedCategoryId"
                                    clearable
                                    filterable
                                    check-strictly
                                    node-key="id"
                                    :data="categoryOptions"
                                    :props="{ label: 'name', value: 'id', children: 'children' }"
                                    placeholder="全部分类"
                                    class="w-[220px]"
                                    @change="handleCategoryFilterChange"
                                />
                            </el-form-item>
                            <el-form-item label="报价项">
                                <el-input v-model="itemQuery.keyword" clearable placeholder="型号、报价项、品牌、关键词" class="w-[260px]" @keyup.enter="searchManage" />
                            </el-form-item>
                            <el-form-item label="品牌">
                                <el-select v-model="itemQuery.brand" clearable filterable placeholder="全部" class="w-[140px]" @change="handleItemSearch">
                                    <el-option v-for="brand in filterOptions.brands" :key="brand" :label="brand" :value="brand" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="分组">
                                <el-select v-model="itemQuery.tab" clearable filterable placeholder="全部" class="w-[140px]" @change="handleItemSearch">
                                    <el-option v-for="tab in filterOptions.tabs" :key="tab" :label="tab" :value="tab" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="报价类型">
                                <el-select v-model="itemQuery.quote_type" clearable filterable placeholder="全部" class="w-[140px]" @change="handleItemSearch">
                                    <el-option v-for="type in filterOptions.quote_types" :key="type" :label="type" :value="type" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="显示">
                                <el-select v-model="itemQuery.is_show" clearable placeholder="全部" class="w-[110px]" @change="handleItemSearch">
                                    <el-option label="显示" :value="1" />
                                    <el-option label="隐藏" :value="0" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="热门">
                                <el-select v-model="itemQuery.is_hot" clearable placeholder="全部" class="w-[110px]" @change="handleItemSearch">
                                    <el-option label="热门" :value="1" />
                                    <el-option label="非热门" :value="0" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="跟随">
                                <el-select v-model="itemQuery.follow_source" clearable placeholder="全部" class="w-[110px]" @change="handleItemSearch">
                                    <el-option label="跟随" :value="1" />
                                    <el-option label="不跟随" :value="0" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="类型">
                                <el-select v-model="itemQuery.is_image_quote" clearable placeholder="全部" class="w-[120px]" @change="handleItemSearch">
                                    <el-option label="结构化" :value="0" />
                                    <el-option label="图片报价" :value="1" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="更新">
                                <el-select v-model="itemQuery.has_update" clearable placeholder="全部" class="w-[110px]" @change="handleItemSearch">
                                    <el-option label="有更新" :value="1" />
                                    <el-option label="无更新" :value="0" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="searchManage">查询</el-button>
                                <el-button @click="resetFilters">重置</el-button>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <div class="manage-layout">
                        <aside class="category-nav-panel panel">
                    
                            
                            <div class="panel-actions">
                                <el-button type="primary" @click="openCreateDialog('category')">新增分类</el-button>
                                <el-dropdown :disabled="!categorySelection.length" @command="batchCategoryCommand">
                                    <el-button>批量操作</el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="show">显示</el-dropdown-item>
                                            <el-dropdown-item command="hide">隐藏</el-dropdown-item>
                                            <el-dropdown-item command="hot">设为热门</el-dropdown-item>
                                            <el-dropdown-item command="unhot">取消热门</el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>
                            <el-table
                                v-loading="categoryLoading"
                                :data="categoryTable.data"
                                border
                                size="large"
                                height="686"
                                row-key="id"
                                default-expand-all
                                :tree-props="{ children: 'children' }"
                                highlight-current-row
                                @selection-change="categorySelection = $event"
                                @row-click="selectCategory"
                            >
                                <el-table-column type="selection" width="36" />
                                <el-table-column prop="name" label="分类" min-width="140" show-overflow-tooltip class-name="category-name-column">
                                    <template #default="{ row }">
                                        <div class="category-name-text">
                                            <div class="name-main">{{ row.name }}</div>
                                            <div class="name-sub">层级 {{ row.level }} · 排序 {{ row.sort }}</div>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="排序" width="106">
                                    <template #default="{ row }">
                                        <el-input-number v-model="row.sort" :min="0" :controls="false" class="sort-input" @change="saveCategory(row, true)" />
                                    </template>
                                </el-table-column>
                                <el-table-column label="控制" width="116">
                                    <template #default="{ row }">
                                        <div class="switch-line">
                                            <span>显示</span>
                                            <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0" @change="saveCategory(row, true)" />
                                        </div>
                                        <div class="switch-line">
                                            <span>热门</span>
                                            <el-switch v-model="row.is_hot" :active-value="1" :inactive-value="0" @change="saveCategory(row, true)" />
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="操作" width="70" fixed="right">
                                    <template #default="{ row }">
                                        <el-button link type="primary" @click.stop="openEditDialog('category', row)">编辑</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </aside>

                        <section class="item-main-panel panel">
                            <div class="panel-head">
                                <div>
                                    <div class="panel-title">报价项</div>
                                    <div class="panel-subtitle">共 {{ itemTable.total }} 个{{ selectedCategoryName ? ' · ' + selectedCategoryName : '' }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <el-button :disabled="!selectedCategoryId" @click="openCreateDialog('item')">新增报价项</el-button>
                                    <el-dropdown :disabled="!itemSelection.length" @command="batchItemCommand">
                                        <el-button>批量操作</el-button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item command="show">显示</el-dropdown-item>
                                                <el-dropdown-item command="hide">隐藏</el-dropdown-item>
                                                <el-dropdown-item command="hot">设为热门</el-dropdown-item>
                                                <el-dropdown-item command="unhot">取消热门</el-dropdown-item>
                                                <el-dropdown-item command="follow">跟随爬虫</el-dropdown-item>
                                                <el-dropdown-item command="unfollow">取消跟随</el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </div>
                            </div>
                            <el-table
                                v-loading="itemLoading"
                                :data="itemTable.data"
                                border
                                size="large"
                                height="620"
                                row-key="id"
                                highlight-current-row
                                @selection-change="itemSelection = $event"
                                @row-click="selectItem"
                            >
                                <el-table-column type="selection" width="36" />
                                <el-table-column prop="name" label="报价项" min-width="220" show-overflow-tooltip>
                                    <template #default="{ row }">
                                        <div class="quote-item-cell">
                                            <el-image v-if="resolveQuoteItemIcon(row)" class="quote-item-icon" :src="imageUrl(resolveQuoteItemIcon(row))" fit="cover" />
                                            <div class="quote-item-copy">
                                                <div class="name-main">{{ row.name }}</div>
                                                <div class="name-sub">{{ row.brand || '-' }} · {{ row.tab || '-' }} · {{ row.parent_name || '-' }}</div>
                                            </div>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="排序" width="96">
                                    <template #default="{ row }">
                                        <el-input-number v-model="row.sort" :min="0" :controls="false" class="sort-input" @change="saveItem(row, true)" />
                                    </template>
                                </el-table-column>
                                <el-table-column label="控制" width="150">
                                    <template #default="{ row }">
                                        <div class="switch-line">
                                            <span>显示</span>
                                            <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0" @change="saveItem(row, true)" />
                                        </div>
                                        <div class="switch-line">
                                            <span>热门</span>
                                            <el-switch v-model="row.is_hot" :active-value="1" :inactive-value="0" @change="saveItem(row, true)" />
                                        </div>
                                        <div class="switch-line">
                                            <span>跟随</span>
                                            <el-switch v-model="row.follow_source" :active-value="1" :inactive-value="0" @change="saveItem(row, true)" />
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="类型" width="80">
                                    <template #default="{ row }">
                                        <el-tag :type="row.is_image_quote ? 'info' : 'success'">{{ row.is_image_quote ? '图片' : '结构' }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="操作" width="210" fixed="right">
                                    <template #default="{ row }">
                                        <el-button link type="primary" @click.stop="openRowDrawer(row)">价格管理</el-button>
                                        <el-button link type="primary" @click.stop="openEditDialog('item', row)">编辑</el-button>
                                        <el-button link type="warning" @click.stop="prepareExcelImport(row)">Excel覆盖</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <el-pagination
                                class="pager"
                                v-model:current-page="itemTable.page"
                                v-model:page-size="itemTable.limit"
                                :total="itemTable.total"
                                :page-sizes="[20, 50, 100]"
                                layout="total, sizes, prev, pager, next"
                                @size-change="handleItemSizeChange"
                                @current-change="loadItem"
                            />
                        </section>
                    </div>
                </el-tab-pane>

                <el-tab-pane label="Excel预览" name="excel">
                    <el-alert
                        class="mb-[12px]"
                        type="info"
                        :closable="false"
                        title="Excel 每一行是一条行价格。必填“型号”，至少填写一个价格列；选择覆盖报价项会先清空原行价格再写入 Excel，不选覆盖报价项则会在所选分类下新建报价项。"
                    />
                    <div class="excel-pane">
                        <el-upload :auto-upload="false" :show-file-list="false" accept=".xls,.xlsx" :on-change="handleExcelChange">
                            <el-button type="primary">选择Excel</el-button>
                        </el-upload>
                        <el-button @click="downloadExcelTemplate">下载模板</el-button>
                        <el-select v-model="excelSourceId" clearable placeholder="归属报价源" class="w-[220px]">
                            <el-option v-for="item in sourceOptions" :key="item.id" :label="item.source_name" :value="item.id" />
                        </el-select>
                        <span class="text-sm text-gray-500">{{ excelFileName || '上传后会先返回预览，不直接入库' }}</span>
                    </div>
                    <el-card v-if="excelPreview.rows?.length" class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
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
                                    placeholder="默认使用系统提示；Excel 中“报价提示”列有内容时会自动读取，换行会保存为 \\n"
                                />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" :loading="excelImporting" @click="confirmExcelImport">导入并覆盖</el-button>
                            </el-form-item>
                        </el-form>
                        <div class="form-tip">导入并覆盖会清空所选报价项下原有行价格，再用当前 Excel 重新生成。适合每天上传新报价单覆盖旧报价单。</div>
                    </el-card>
                    <el-table
                        v-if="excelPreviewTable.rows.length"
                        :data="excelPreviewTable.rows"
                        border
                        size="large"
                        max-height="520"
                        class="excel-matrix-table"
                    >
                        <el-table-column prop="row_number" label="行号" width="76" fixed />
                        <el-table-column
                            v-for="column in excelPreviewTable.columns"
                            :key="column.key"
                            :prop="column.key"
                            :label="column.label"
                            :min-width="column.minWidth"
                            show-overflow-tooltip
                        >
                            <template #default="{ row }">
                                <span :class="column.isPrice ? 'excel-price-cell' : 'excel-plain-cell'">
                                    {{ row[column.key] || '-' }}
                                </span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane label="同步日志" name="log">
                    <el-table v-loading="logLoading" :data="logTable.data">
                        <el-table-column prop="id" label="ID" width="80" />
                        <el-table-column prop="sync_type" label="类型" width="100" />
                        <el-table-column prop="status" label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 1 ? 'success' : row.status === 2 ? 'danger' : row.status === 3 ? 'warning' : 'info'">
                                    {{ row.status === 1 ? '成功' : row.status === 2 ? '失败' : row.status === 3 ? '部分成功' : '同步中' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="message" label="说明" min-width="180" />
                        <el-table-column prop="error_detail" label="失败原因" min-width="260" show-overflow-tooltip>
                            <template #default="{ row }">{{ row.error_detail || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="total_count" label="报价项" width="90" />
                        <el-table-column prop="success_count" label="详情成功" width="100" />
                        <el-table-column prop="failed_count" label="详情失败" width="100" />
                        <el-table-column prop="started_at" label="开始时间" width="180">
                            <template #default="{ row }">{{ formatTime(row.started_at) }}</template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <el-drawer v-model="rowDrawer.visible" size="78%" :with-header="false" destroy-on-close>
            <div class="drawer-shell">
                <div class="drawer-head">
                    <div>
                        <div class="drawer-title">{{ selectedItemName || '价格管理' }}</div>
                        <div class="panel-subtitle">
                            {{ rowDrawer.item.brand || '-' }} · {{ rowDrawer.item.tab || '-' }} · {{ rowDrawer.item.parent_name || selectedCategoryName || '-' }}
                        </div>
                    </div>
                    <div class="drawer-actions">
                        <el-button @click="prepareExcelImport(rowDrawer.item)">Excel覆盖</el-button>
                        <el-button type="primary" @click="openEditDialog('item', rowDrawer.item)">编辑报价项</el-button>
                    </div>
                </div>

                <el-tabs v-model="rowDrawer.activeTab" class="drawer-tabs">
                    <el-tab-pane label="行价格" name="rows">
                        <div class="row-toolbar">
                            <el-input v-model="rowQuery.keyword" clearable placeholder="搜索型号、备注" class="w-[260px]" @keyup.enter="handleRowSearch" />
                            <el-button @click="handleRowSearch">查询</el-button>
                            <el-button type="primary" :disabled="!selectedItemId" @click="openCreateDialog('row')">新增行价格</el-button>
                            <el-dropdown :disabled="!rowSelection.length" @command="batchRowCommand">
                                <el-button>批量操作</el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="show">显示</el-dropdown-item>
                                        <el-dropdown-item command="hide">隐藏</el-dropdown-item>
                                        <el-dropdown-item command="follow">跟随爬虫</el-dropdown-item>
                                        <el-dropdown-item command="unfollow">取消跟随</el-dropdown-item>
                                        <el-dropdown-item command="fixed">固定调整</el-dropdown-item>
                                        <el-dropdown-item command="ratio">比例调整</el-dropdown-item>
                                        <el-dropdown-item command="clear">清除调价</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <span class="panel-subtitle">已选 {{ rowSelection.length }} 行</span>
                        </div>

                        <el-table
                            v-loading="rowLoading"
                            :data="rowMatrixRows"
                            border
                            size="large"
                            height="620"
                            row-key="id"
                            :span-method="rowMatrixSpanMethod"
                            class="excel-matrix-table row-price-matrix"
                            @selection-change="handleRowSelectionChange"
                        >
                            <el-table-column type="selection" width="44" />
                            <el-table-column prop="group_name" label="分组/系列" width="136" show-overflow-tooltip>
                                <template #default="{ row }">
                                    <div class="matrix-group-cell">{{ row.group_name || '-' }}</div>
                                </template>
                            </el-table-column>
                            <el-table-column prop="model_name" label="型号" min-width="220" show-overflow-tooltip>
                                <template #default="{ row }">
                                    <div class="name-main">{{ row.model_name || '-' }}</div>
                                    <div v-if="getDisplayBrand(row)" class="name-sub">{{ getDisplayBrand(row) }}</div>
                                </template>
                            </el-table-column>
                            <el-table-column prop="capacity_name" label="内存/规格" width="120" show-overflow-tooltip>
                                <template #default="{ row }">{{ row.capacity_name || '-' }}</template>
                            </el-table-column>
                            <el-table-column
                                v-for="column in rowMatrixPriceColumns"
                                :key="column.key"
                                :label="column.label"
                                :min-width="column.minWidth"
                                :align="column.align"
                                show-overflow-tooltip
                            >
                                <template #default="{ row }">
                                    <span :class="column.isRemark ? 'excel-remark-cell' : 'excel-price-cell'">
                                        {{ getRowMatrixCell(row, column) }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column label="排序" width="100">
                                <template #default="{ row }">
                                    <el-input-number v-model="row.source.sort" :min="0" :controls="false" class="sort-input" @change="saveRow(row.source, true)" />
                                </template>
                            </el-table-column>
                            <el-table-column label="跟随" width="86">
                                <template #default="{ row }">
                                    <el-switch v-model="row.source.follow_source" :active-value="1" :inactive-value="0" @change="saveRow(row.source, true)" />
                                </template>
                            </el-table-column>
                            <el-table-column label="显示" width="86">
                                <template #default="{ row }">
                                    <el-switch v-model="row.source.is_show" :active-value="1" :inactive-value="0" @change="saveRow(row.source, true)" />
                                </template>
                            </el-table-column>
                            <el-table-column label="操作" width="82" fixed="right">
                                <template #default="{ row }">
                                    <el-button link type="primary" @click.stop="openEditDialog('row', row.source)">编辑</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-pagination
                            class="pager"
                            v-model:current-page="rowTable.page"
                            v-model:page-size="rowTable.limit"
                            :total="rowTable.total"
                            :page-sizes="[20, 50, 100]"
                            layout="total, sizes, prev, pager, next, jumper"
                            @size-change="handleRowSizeChange"
                            @current-change="loadRows"
                        />
                    </el-tab-pane>

                    <el-tab-pane label="导入覆盖" name="import">
                        <div class="drawer-import">
                            <el-alert
                                type="warning"
                                :closable="false"
                                title="当前是覆盖已有报价项：确认后会清空这个报价项下已有行价格，再按 Excel 重新生成。Excel 里新增的型号会作为新行价格写入；Excel 里没有的旧型号会被移除。"
                            />
                            <div class="excel-pane mt-[14px]">
                                <el-upload :auto-upload="false" :show-file-list="false" accept=".xls,.xlsx" :on-change="handleExcelChange">
                                    <el-button type="primary">选择Excel</el-button>
                                </el-upload>
                                <el-button @click="downloadExcelTemplate">下载模板</el-button>
                                <span class="text-sm text-gray-500">{{ excelFileName || '选择后会先预览，不会立即入库' }}</span>
                            </div>
                            <el-form :inline="true" :model="excelImportForm" class="mt-[14px]">
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
                                    <el-select v-model="excelImportForm.item_id" filterable placeholder="当前报价项" class="w-[240px]">
                                        <el-option v-for="item in itemTable.data" :key="item.id" :label="item.name" :value="item.id" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="报价提示">
                                    <el-input
                                        v-model="excelImportForm.notice_text"
                                        type="textarea"
                                        :rows="2"
                                        class="excel-notice-input"
                                        placeholder="默认使用系统提示；Excel 中“报价提示”列有内容时会自动读取，换行会保存为 \\n"
                                    />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" :loading="excelImporting" :disabled="!excelTaskId" @click="confirmExcelImport">确认覆盖</el-button>
                                </el-form-item>
                            </el-form>
                            <el-table
                                v-if="excelPreviewTable.rows.length"
                                :data="excelPreviewTable.rows"
                                border
                                size="large"
                                max-height="360"
                                class="excel-matrix-table"
                            >
                                <el-table-column prop="row_number" label="行号" width="76" fixed />
                                <el-table-column
                                    v-for="column in excelPreviewTable.columns"
                                    :key="column.key"
                                    :prop="column.key"
                                    :label="column.label"
                                    :min-width="column.minWidth"
                                    show-overflow-tooltip
                                >
                                    <template #default="{ row }">
                                        <span :class="column.isPrice ? 'excel-price-cell' : 'excel-plain-cell'">
                                            {{ row[column.key] || '-' }}
                                        </span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <el-tab-pane label="基础信息" name="info">
                        <div class="item-info-layout">
                            <section class="row-edit-section">
                                <div class="section-title">报价项信息</div>
                                <el-descriptions :column="2" border>
                                    <el-descriptions-item label="名称">{{ rowDrawer.item.name || '-' }}</el-descriptions-item>
                                    <el-descriptions-item label="品牌">{{ rowDrawer.item.brand || '-' }}</el-descriptions-item>
                                    <el-descriptions-item label="分组">{{ rowDrawer.item.tab || '-' }}</el-descriptions-item>
                                    <el-descriptions-item label="类型">{{ rowDrawer.item.is_image_quote ? '图片报价' : '结构化报价' }}</el-descriptions-item>
                                    <el-descriptions-item label="排序">{{ rowDrawer.item.sort ?? 0 }}</el-descriptions-item>
                                    <el-descriptions-item label="关键词">{{ rowDrawer.item.keywords || '-' }}</el-descriptions-item>
                                </el-descriptions>
                                <div class="mt-[14px]">
                                    <div class="image-setting-title mb-[8px]">报价详情提示</div>
                                    <el-input
                                        v-model="rowDrawer.item.notice_text"
                                        type="textarea"
                                        :rows="4"
                                        placeholder="温馨提示：报价仅供参考，最终价格以质检结果为准"
                                    />
                                    <div class="form-tip">移动端报价详情 notice-card 会展示这段文案；多行文案按换行展示。</div>
                                </div>
                            </section>
                            <section class="row-edit-section">
                                <div class="section-title">展示图片</div>
                                <div class="image-setting-grid">
                                    <div class="image-setting-card">
                                        <div class="image-setting-title">导航图标</div>
                                        <upload-image v-model="rowDrawer.item.icon" :limit="1" />
                                        <div class="form-tip">用于低代码图文导航、报价项列表缩略图。你手动替换后会优先展示这个图标。</div>
                                    </div>
                                    <div class="image-setting-card">
                                        <div class="image-setting-title">报价图片</div>
                                        <upload-image v-model="rowDrawer.item.image" :limit="1" />
                                        <div class="form-tip">图片报价会使用这张图；结构化报价也可以保留为辅助资料。</div>
                                    </div>
                                </div>
                                <div class="mt-[12px]">
                                    <el-button type="primary" @click="saveDrawerItem">保存基础信息</el-button>
                                </div>
                            </section>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </el-drawer>

        <el-dialog v-model="sourceDialog.visible" :title="sourceDialog.form.id ? '编辑报价源' : '新增报价源'" width="780px">
            <el-alert
                class="mb-[14px]"
                type="info"
                :closable="false"
                title="报价源只负责连接第三方报价接口。保存后点“同步”，系统会把分类、报价项和行价格写入本地数据表。"
            />
            <el-form :model="sourceDialog.form" label-width="116px">
                <el-form-item label="curl解析">
                    <el-input
                        v-model="sourceDialog.curlText"
                        type="textarea"
                        :rows="5"
                        placeholder="把浏览器复制出来的 curl 粘贴到这里，点击一键解析"
                    />
                    <div class="source-parse-actions">
                        <el-button type="primary" @click="parseSourceCurl">一键解析</el-button>
                        <el-button @click="sourceDialog.curlText = ''">清空</el-button>
                    </div>
                    <div class="form-tip">只解析请求地址、URL 参数、Header 和 Cookie。解析后仍需确认名称、源标识和列表/详情接口是否符合当前 SaaS 站点。</div>
                </el-form-item>
                <el-form-item label="源标识">
                    <el-input v-model="sourceDialog.form.source_key" placeholder="例如 dongxu，建议使用英文、数字或下划线" />
                    <div class="form-tip">同一个站点内不能重复。后续同步日志、数据归属都会使用这个标识。</div>
                </el-form-item>
                <el-form-item label="名称">
                    <el-input v-model="sourceDialog.form.source_name" placeholder="例如 东旭报价爬虫" />
                    <div class="form-tip">显示给后台用户看的名称。</div>
                </el-form-item>
                <el-form-item label="服务商">
                    <el-input v-model="sourceDialog.form.provider" placeholder="例如 dongxu / chaoniu" />
                    <div class="form-tip">用于区分不同供应商，建议和真实来源保持一致。</div>
                </el-form-item>
                <el-form-item label="基础地址">
                    <el-input v-model="sourceDialog.form.base_url" placeholder="例如 https://example.com" />
                    <div class="form-tip">只填域名和协议，不要把接口路径一起填进来。</div>
                </el-form-item>
                <el-form-item label="列表接口">
                    <el-input v-model="sourceDialog.form.list_path" placeholder="例如 /index.php/Api/index/newimage" />
                    <div class="form-tip">用于抓取分类和报价项列表，通常是相对路径。</div>
                </el-form-item>
                <el-form-item label="详情接口">
                    <el-input v-model="sourceDialog.form.detail_path" placeholder="例如 /index.php/Api/index/bj" />
                    <div class="form-tip">用于抓取某个报价项下的型号、等级列和价格。</div>
                </el-form-item>
                <el-form-item label="自动同步">
                    <el-switch v-model="sourceDialog.form.sync_enabled" :active-value="1" :inactive-value="0" />
                    <div class="form-tip">开启后需要队列/定时任务正常运行。手动同步不受这个开关影响。</div>
                </el-form-item>
                <el-form-item label="同步间隔秒">
                    <el-input-number v-model="sourceDialog.form.sync_interval" :min="60" />
                    <div class="form-tip">自动同步的最小间隔，建议不要低于 3600 秒，避免第三方接口压力过大。</div>
                </el-form-item>
                <el-form-item label="请求配置JSON">
                    <el-input
                        v-model="sourceDialog.requestConfigText"
                        type="textarea"
                        :rows="9"
                        placeholder='{"query":{},"headers":{},"cookies":{}}'
                    />
                    <div class="form-tip">需要 token、cookie、固定参数时填这里。必须是合法 JSON；不需要时保留空对象即可。</div>
                    <pre class="json-example">{
  "query": { "openid": "xxx" },
  "headers": { "Authorization": "Bearer xxx" },
  "cookies": {}
}</pre>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="sourceDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="saveSource">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="editDialog.visible" :title="editDialogTitle" :width="editDialog.type === 'row' ? '920px' : '680px'">
            <el-alert
                v-if="editDialog.type === 'item'"
                class="mb-[14px]"
                type="info"
                :closable="false"
                title="分类是有父子节点的层级关系；分组是第三方报价里同步过来的平铺标签，更适合作为筛选属性。"
            />
            <el-alert
                v-if="editDialog.type === 'row'"
                class="mb-[14px]"
                type="warning"
                :closable="false"
                title="跟随爬虫开启时，最终价会按源价格和调价规则计算；关闭后可保留人工价格，后续同步不主动覆盖。"
            />
            <el-form :model="editDialog.form" label-width="106px">
                <template v-if="editDialog.type === 'category'">
                    <el-form-item v-if="editDialog.mode === 'create'" label="报价源">
                        <el-select v-model="editDialog.form.source_id" class="w-full" placeholder="请选择报价源">
                            <el-option v-for="item in sourceOptions" :key="item.id" :label="item.source_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="editDialog.mode === 'create'" label="父级分类">
                        <el-tree-select
                            v-model="editDialog.form.parent_id"
                            clearable
                            check-strictly
                            node-key="id"
                            :data="categoryOptions"
                            :props="{ label: 'name', value: 'id', children: 'children' }"
                            placeholder="不选则创建一级分类"
                            class="w-full"
                        />
                    </el-form-item>
                    <el-form-item label="分类名称">
                        <el-input v-model="editDialog.form.name" placeholder="例如 苹果 / 华为 / 平板" />
                        <div class="form-tip">分类有父子节点关系，用来承载第三方来源里的目录层级。</div>
                    </el-form-item>
                    <el-form-item label="排序">
                        <el-input-number v-model="editDialog.form.sort" :min="0" />
                        <div class="form-tip">数字越小越靠前，只影响本地后台展示。</div>
                    </el-form-item>
                    <el-form-item label="显示">
                        <el-switch v-model="editDialog.form.is_show" :active-value="1" :inactive-value="0" />
                        <div class="form-tip">关闭后前台或接口可隐藏该分类，本地数据仍保留。</div>
                    </el-form-item>
                    <el-form-item label="热门">
                        <el-switch v-model="editDialog.form.is_hot" :active-value="1" :inactive-value="0" />
                        <div class="form-tip">用于标记常用分类，具体展示取决于调用方。</div>
                    </el-form-item>
                </template>
                <template v-if="editDialog.type === 'item'">
                    <el-form-item label="报价模式">
                        <el-segmented v-model="editDialog.form.is_image_quote" :options="quoteModeOptions" />
                        <div class="form-tip">结构化报价维护行价格；图片报价直接上传一张报价图。</div>
                    </el-form-item>
                    <el-form-item label="所属分类">
                        <el-tree-select
                            v-model="editDialog.form.category_id"
                            :disabled="editDialog.mode !== 'create'"
                            check-strictly
                            node-key="id"
                            :data="categoryOptions"
                            :props="{ label: 'name', value: 'id', children: 'children' }"
                            placeholder="来源分类"
                            class="w-full"
                        />
                        <div class="form-tip">这里展示的是有层级的分类节点。为避免同步关系混乱，当前暂不在弹窗内移动分类。</div>
                    </el-form-item>
                    <el-form-item label="报价项">
                        <el-input v-model="editDialog.form.name" placeholder="例如 iPhone 15 Pro Max" />
                        <div class="form-tip">报价项名称用于列表检索和展示。</div>
                    </el-form-item>
                    <el-form-item label="品牌">
                        <el-input v-model="editDialog.form.brand" placeholder="例如 苹果 / 华为 / 荣耀" />
                        <div class="form-tip">品牌是筛选属性，建议保持统一写法。</div>
                    </el-form-item>
                    <el-form-item label="分组">
                        <el-input v-model="editDialog.form.tab" placeholder="例如 国行 / 外版 / 靓机 / 花机" />
                        <div class="form-tip">分组不是层级节点，是第三方报价里的标签属性，可用于筛选和归类。</div>
                    </el-form-item>
                    <el-form-item label="报价类型">
                        <el-input v-model="editDialog.form.quote_type" :disabled="editDialog.mode !== 'create'" placeholder="例如 manual / manual_excel" />
                        <div class="form-tip">手工维护建议使用 manual；Excel 导入会写为 manual_excel。</div>
                    </el-form-item>
                    <el-form-item v-if="editDialog.mode !== 'create'" label="上级名称">
                        <el-input v-model="editDialog.form.parent_name" disabled placeholder="来源上级名称" />
                        <div class="form-tip">第三方来源返回的上级名称，用于辅助识别，不等同于本地分类节点。</div>
                    </el-form-item>
                    <el-form-item label="导航图标">
                        <upload-image v-model="editDialog.form.icon" :limit="1" />
                        <div class="form-tip">用于低代码图文导航和后台报价项缩略图。手动上传后优先展示，不填则回退到来源图片。</div>
                    </el-form-item>
                    <el-form-item v-if="editDialog.form.is_image_quote === 1" label="报价图片">
                        <upload-image v-model="editDialog.form.image" :limit="1" />
                        <div class="form-tip">图片报价不会生成行价格，适合第三方只给报价图或运营直接维护图片。</div>
                    </el-form-item>
                    <el-form-item label="关键词">
                        <el-input v-model="editDialog.form.keywords" placeholder="多个关键词可用逗号分隔" />
                        <div class="form-tip">用于搜索命中，不会改变第三方原始数据。</div>
                    </el-form-item>
                    <el-form-item label="报价提示">
                        <el-input
                            v-model="editDialog.form.notice_text"
                            type="textarea"
                            :rows="4"
                            placeholder="温馨提示：报价仅供参考，最终价格以质检结果为准"
                        />
                        <div class="form-tip">展示在移动端报价详情顶部的提示卡片里。Excel 导入时也可以通过“报价提示”列写入，换行会按多行展示。</div>
                    </el-form-item>
                    <el-form-item label="显示">
                        <el-switch v-model="editDialog.form.is_show" :active-value="1" :inactive-value="0" />
                        <div class="form-tip">关闭后该报价项可在前台隐藏。</div>
                    </el-form-item>
                    <el-form-item label="热门">
                        <el-switch v-model="editDialog.form.is_hot" :active-value="1" :inactive-value="0" />
                        <div class="form-tip">用于运营侧标记常用报价项。</div>
                    </el-form-item>
                    <el-form-item label="跟随爬虫">
                        <el-switch v-model="editDialog.form.follow_source" :active-value="1" :inactive-value="0" />
                        <div class="form-tip">开启时后续同步会继续使用第三方源价格；关闭后适合做本地人工维护。</div>
                    </el-form-item>
                </template>
                <template v-if="editDialog.type === 'row'">
                    <div class="row-edit-layout">
                        <section class="row-edit-section">
                            <div class="section-title">基础信息</div>
                            <div class="row-edit-grid">
                                <el-form-item label="型号">
                                    <el-input v-model="editDialog.form.model_name" placeholder="例如 iPhone 15 Pro Max 256G" />
                                    <div class="form-tip">行价格的主名称，通常对应具体型号、容量或报价行。</div>
                                </el-form-item>
                                <el-form-item label="品牌">
                                    <el-input v-model="editDialog.form.brand" placeholder="例如 苹果" />
                                    <div class="form-tip">用于筛选和检索，建议和报价项品牌保持一致。</div>
                                </el-form-item>
                                <el-form-item label="分组">
                                    <el-input v-model="editDialog.form.tab" placeholder="例如 国行 / 外版 / 靓机 / 花机" />
                                    <div class="form-tip">行级分组是平铺属性，不是树形节点。</div>
                                </el-form-item>
                                <el-form-item label="维护方式">
                                    <el-segmented v-model="editDialog.form.follow_source" :options="followSourceOptions" @change="handleRowModeChange" />
                                    <div class="form-tip">{{ rowEditModeTip }}</div>
                                </el-form-item>
                                <el-form-item label="排序">
                                    <el-input-number v-model="editDialog.form.sort" :min="0" />
                                    <div class="form-tip">数字越小越靠前。</div>
                                </el-form-item>
                                <el-form-item label="显示">
                                    <el-switch v-model="editDialog.form.is_show" :active-value="1" :inactive-value="0" />
                                    <div class="form-tip">关闭后前台接口不会返回这一行价格。</div>
                                </el-form-item>
                            </div>
                            <el-form-item label="备注">
                                <el-input v-model="editDialog.form.remark" type="textarea" :rows="3" placeholder="例如 有锁、仅参考、特殊报价说明" />
                                <div class="form-tip">备注会跟随行价格一起展示，适合放运营说明。</div>
                            </el-form-item>
                        </section>

                        <section class="row-edit-section">
                            <div class="section-title">
                                价格明细
                                <el-tag size="small" :type="editDialog.form.follow_source === 1 ? 'success' : 'warning'">
                                    {{ editDialog.form.follow_source === 1 ? '按源价计算' : '人工维护' }}
                                </el-tag>
                            </div>
                            <div v-if="editDialog.mode === 'create'" class="create-price-columns">
                                <el-input v-model="editDialog.columnsText" type="textarea" :rows="2" placeholder="价格列，用逗号分隔，例如 靓机,小花,内爆" />
                                <div class="form-tip">新增行时先定义价格列，再在下方填写对应人工价格。</div>
                            </div>
                            <el-table :data="rowPriceTable" border size="large" class="row-price-table">
                                <el-table-column prop="column" label="价格列" min-width="150" />
                                <el-table-column label="源价格" min-width="120">
                                    <template #default="{ row }">{{ formatMoney(row.source_price) }}</template>
                                </el-table-column>
                                <el-table-column label="当前最终价" min-width="120">
                                    <template #default="{ row }">
                                        <span class="final-price">{{ formatMoney(row.final_price) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="人工价格" min-width="170">
                                    <template #default="{ row, $index }">
                                        <el-input-number
                                            v-model="editDialog.form.manual_prices[$index]"
                                            :controls="false"
                                            :disabled="editDialog.form.follow_source === 1"
                                            placeholder="留空则不覆盖"
                                            class="manual-price-input"
                                        />
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div class="form-tip">
                                人工维护时可直接填写每一列价格；跟随爬虫时人工价格输入会锁定，最终价由源价格和调价规则计算。
                            </div>
                        </section>

                        <section class="row-edit-section">
                            <div class="section-title">调价规则</div>
                            <div class="row-edit-grid">
                                <el-form-item label="调价方式">
                                    <el-select v-model="editDialog.form.adjust_type" class="w-full" :disabled="editDialog.form.follow_source === 0">
                                        <el-option label="无" :value="0" />
                                        <el-option label="固定调整" :value="1" />
                                        <el-option label="比例调整" :value="2" />
                                    </el-select>
                                    <div class="form-tip">固定调整适合统一加减金额；比例调整适合按倍率处理价格。</div>
                                </el-form-item>
                                <el-form-item label="调价值">
                                    <el-input-number v-model="editDialog.form.adjust_value" :controls="false" :disabled="editDialog.form.follow_source === 0 || editDialog.form.adjust_type !== 1" />
                                    <div class="form-tip">固定调整时生效。正数加价，负数扣价。</div>
                                </el-form-item>
                                <el-form-item label="调价比例">
                                    <el-input-number v-model="editDialog.form.adjust_ratio" :controls="false" :step="0.01" :disabled="editDialog.form.follow_source === 0 || editDialog.form.adjust_type !== 2" />
                                    <div class="form-tip">比例调整时生效。1 不变，0.95 打 95 折，1.05 上浮 5%。</div>
                                </el-form-item>
                            </div>
                        </section>
                    </div>
                </template>
            </el-form>
            <template #footer>
                <el-button @click="editDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="submitEdit">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="adjustDialog.visible" title="批量调价" width="820px">
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
                <el-button type="primary" :disabled="!rowSelection.length" @click="submitBatchAdjust">
                    确认应用到 {{ rowSelection.length }} 行
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import type { UploadFile } from 'element-plus'
import * as XLSX from 'xlsx'
import {
    addQuoteCategory,
    addQuoteItem,
    addQuoteRow,
    addQuoteSource,
    confirmQuoteExcel,
    createDefaultQuoteSource,
    editQuoteCategory,
    editQuoteItem,
    editQuoteRow,
    editQuoteSource,
    getQuoteCategoryTree,
    getQuoteItemFilterOptions,
    getQuoteItemList,
    getQuoteRowList,
    getQuoteSourceAll,
    getQuoteSourceList,
    getQuoteSyncLogList,
    previewQuoteExcel,
    syncQuoteSource,
    uploadQuoteExcel
} from '@/addon/recycle_quote_spider/api/quote'

type EditType = 'category' | 'item' | 'row'

const activeTab = ref('source')
const sourceLoading = ref(false)
const categoryLoading = ref(false)
const itemLoading = ref(false)
const rowLoading = ref(false)
const logLoading = ref(false)
const syncingId = ref(0)
const selectedSourceId = ref<number | ''>('')
const selectedCategoryId = ref<number | ''>('')
const selectedCategoryName = ref('')
const selectedItemId = ref(0)
const selectedItemName = ref('')
const sourceOptions = ref<any[]>([])
const categoryOptions = ref<any[]>([])
const categorySelection = ref<any[]>([])
const itemSelection = ref<any[]>([])
const rowSelection = ref<any[]>([])
const excelSourceId = ref('')
const excelFileName = ref('')
const excelPreview = ref<any>({})
const excelTaskId = ref(0)
const excelImporting = ref(false)
const logRefreshTimer = ref<number | null>(null)

const sourceTable = reactive({ data: [] as any[], page: 1, limit: 10, total: 0 })
const categoryTable = reactive({ data: [] as any[], total: 0 })
const itemTable = reactive({ data: [] as any[], page: 1, limit: 20, total: 0 })
const rowTable = reactive({ data: [] as any[], page: 1, limit: 20, total: 0 })
const logTable = reactive({ data: [] as any[] })
const sourceQuery = reactive({ keyword: '', status: '' })
const categoryQuery = reactive({ keyword: '' })
const itemQuery = reactive({
    keyword: '',
    is_show: '',
    is_hot: '',
    follow_source: '',
    has_update: '',
    is_image_quote: '',
    brand: '',
    tab: '',
    quote_type: ''
})
const rowQuery = reactive({ keyword: '', item_id: '', page: 1, limit: 20 })
const filterOptions = reactive({
    brands: [] as string[],
    tabs: [] as string[],
    quote_types: [] as string[]
})
const excelImportForm = reactive({
    source_id: '' as number | '',
    category_id: '' as number | '',
    item_id: '' as number | '',
    item_name: '',
    notice_text: '',
    brand: '',
    tab: ''
})

const sourceDialog = reactive({
    visible: false,
    curlText: '',
    requestConfigText: '',
    form: {
        id: 0,
        source_key: '',
        source_name: '',
        provider: '',
        base_url: '',
        list_path: '',
        detail_path: '',
        request_config: {},
        sync_enabled: 0,
        sync_interval: 86400,
        timeout: 15,
        rate_limit: 300,
        retry_times: 1,
        status: 1
    } as Record<string, any>
})

const editDialog = reactive({
    visible: false,
    mode: 'edit' as 'create' | 'edit',
    type: 'category' as EditType,
    form: {} as Record<string, any>,
    columnsText: ''
})

const rowDrawer = reactive({
    visible: false,
    activeTab: 'rows',
    item: {} as Record<string, any>
})

const adjustDialog = reactive({
    visible: false,
    form: {
        adjust_type: 1,
        adjust_value: 0,
        adjust_ratio: 1
    }
})

const followSourceOptions = [
    { label: '跟随爬虫', value: 1 },
    { label: '人工维护', value: 0 }
]

const quoteModeOptions = [
    { label: '结构化报价', value: 0 },
    { label: '图片报价', value: 1 }
]

const adjustTypeOptions = [
    { label: '固定调整', value: 1 },
    { label: '比例调整', value: 2 }
]

const editDialogTitle = computed(() => {
    const titleMap: Record<EditType, string> = {
        category: editDialog.mode === 'create' ? '新增分类' : '编辑分类',
        item: editDialog.mode === 'create' ? '新增报价项' : '编辑报价项',
        row: editDialog.mode === 'create' ? '新增行价格' : '编辑行价格'
    }
    return titleMap[editDialog.type]
})

const rowEditModeTip = computed(() => {
    return editDialog.form.follow_source === 1
        ? '同步后继续使用第三方源价格，可叠加下方调价规则。'
        : '后续同步不主动覆盖本行最终价，可在价格明细里直接维护每一列人工价格。'
})

const rowPriceTable = computed(() => {
    const columns = editDialog.mode === 'create' && editDialog.type === 'row'
        ? parseColumnsText(editDialog.columnsText)
        : normalizePriceArray(editDialog.form.columns)
    const sourcePrices = normalizePriceArray(editDialog.form.source_prices)
    const finalPrices = normalizePriceArray(editDialog.form.final_prices)
    const manualPrices = normalizePriceArray(editDialog.form.manual_prices)
    const length = Math.max(columns.length, sourcePrices.length, finalPrices.length, manualPrices.length)
    return Array.from({ length }, (_, index) => ({
        column: columns[index] || `价格${index + 1}`,
        source_price: sourcePrices[index] ?? '',
        final_price: finalPrices[index] ?? '',
        manual_price: manualPrices[index] ?? ''
    }))
})

const getCapacityName = (row: any) => {
    const raw = row?.raw_data && typeof row.raw_data === 'object' ? row.raw_data : {}
    const candidates = [
        raw['内存'],
        raw['容量'],
        raw['规格'],
        raw['存储'],
        row.capacity_name,
        row.capacity,
        raw.capacity_name,
        raw.capacity,
        raw.memory,
        raw.storage,
        raw.rom
    ]
    const groupName = String(row.group_name || row.tab || '').trim()
    const value = candidates.find(item => isValidCapacityValue(item, groupName))
    return value === undefined ? '' : String(value).trim()
}

const isValidCapacityValue = (value: any, groupName = '') => {
    const text = String(value ?? '').trim()
    if (!text) return false
    if (groupName && text === groupName) return false
    if (/分组|系列/.test(text)) return false
    return true
}

const rowMatrixPriceColumns = computed(() => {
    const labels: string[] = []
    rowTable.data.forEach(row => {
        normalizePriceArray(row.columns).forEach((column: any, index: number) => {
            const label = String(column || `价格${index + 1}`).trim()
            if (label && !labels.includes(label)) {
                labels.push(label)
            }
        })
    })
    return labels.map((label, index) => ({
        key: `${index}-${label}`,
        label,
        index,
        isRemark: isRemarkColumn(label),
        align: isRemarkColumn(label) ? 'left' : 'right',
        minWidth: isRemarkColumn(label) ? 180 : Math.max(110, Math.min(180, label.length * 16 + 44))
    }))
})

const rowMatrixRows = computed(() => {
    return rowTable.data.map((row, index) => ({
        ...row,
        id: row.id,
        source: row,
        rowIndex: index,
        group_name: row.tab || row.parent_name || '未分组',
        model_name: row.model_name || row.name || '-',
        capacity_name: getCapacityName(row),
        modelSpanKey: `${row.tab || ''}__${row.model_name || ''}`,
        groupSpanKey: row.tab || '未分组'
    }))
})

const getDisplayBrand = (row: any) => {
    const brand = String(row.brand || '').trim()
    if (!brand || brand === row.group_name || brand === row.tab || /分组|系列/.test(brand)) {
        return ''
    }
    return brand
}

const buildSpanMap = (rows: any[], keyGetter: (row: any) => string) => {
    const spans: Record<number, number> = {}
    let start = 0
    while (start < rows.length) {
        const key = keyGetter(rows[start])
        let end = start + 1
        while (end < rows.length && keyGetter(rows[end]) === key) {
            end++
        }
        spans[start] = end - start
        for (let index = start + 1; index < end; index++) {
            spans[index] = 0
        }
        start = end
    }
    return spans
}

const rowMatrixSpanMaps = computed(() => {
    const rows = rowMatrixRows.value
    return {
        group: buildSpanMap(rows, row => row.groupSpanKey),
        model: buildSpanMap(rows, row => row.modelSpanKey)
    }
})

const rowMatrixSpanMethod = ({ rowIndex, columnIndex }: { rowIndex: number; columnIndex: number }) => {
    if (columnIndex === 1) {
        const rowspan = rowMatrixSpanMaps.value.group[rowIndex] ?? 1
        return { rowspan, colspan: rowspan === 0 ? 0 : 1 }
    }
    if (columnIndex === 2) {
        const rowspan = rowMatrixSpanMaps.value.model[rowIndex] ?? 1
        return { rowspan, colspan: rowspan === 0 ? 0 : 1 }
    }
    return { rowspan: 1, colspan: 1 }
}

const handleRowSelectionChange = (rows: any[]) => {
    rowSelection.value = rows.map(row => row.source || row)
}

const getRowMatrixCell = (row: any, column: any) => {
    const source = row.source || row
    const columns = normalizePriceArray(source.columns).map((item: any, index: number) => String(item || `价格${index + 1}`).trim())
    const prices = normalizePriceArray(source.final_prices)
    const matchedIndex = columns.findIndex((label: string) => label === column.label)
    const value = matchedIndex > -1 ? prices[matchedIndex] : ''
    if ((value === '' || value === null || value === undefined) && column.isRemark) {
        return source.remark || '-'
    }
    return formatMoney(value)
}

const excelPreviewTable = computed(() => {
    const headers = Array.isArray(excelPreview.value.headers) ? excelPreview.value.headers : []
    const rows = Array.isArray(excelPreview.value.rows) ? excelPreview.value.rows : []
    const columns = headers.map((label: string, index: number) => {
        const key = `col_${index}`
        const isPrice = isPriceColumn(label)
        return {
            key,
            label,
            isPrice,
            minWidth: isPrice ? Math.max(110, Math.min(180, String(label).length * 16 + 44)) : Math.max(120, Math.min(220, String(label).length * 16 + 56))
        }
    })
    return {
        columns,
        rows: rows.map((row: any) => {
            const data = row.data || {}
            const tableRow: Record<string, any> = { row_number: row.row_number }
            headers.forEach((header: string, index: number) => {
                tableRow[`col_${index}`] = data[header]
            })
            return tableRow
        })
    }
})

const batchAdjustRowsPreview = computed(() => rowSelection.value.slice(0, 6))

const selectedCategoryScopeIds = computed(() => {
    const current = findCategoryById(categoryOptions.value, selectedCategoryId.value)
    return current ? collectCategoryIds(current) : []
})

const batchAdjustTip = computed(() => {
    return adjustDialog.form.adjust_type === 1
        ? '适合给选中的型号统一加钱或扣钱。'
        : '适合按百分比统一上浮或下调。'
})

const batchAdjustPreviewText = computed(() => {
    if (adjustDialog.form.adjust_type === 1) {
        const value = Number(adjustDialog.form.adjust_value || 0)
        if (value === 0) return '当前固定调整为 0，保存后价格不发生变化。'
        return value > 0 ? `所有选中行在源价基础上加 ${value}` : `所有选中行在源价基础上扣 ${Math.abs(value)}`
    }
    const ratio = Number(adjustDialog.form.adjust_ratio || 1)
    if (ratio === 1) return '当前比例为 1，保存后价格不发生变化。'
    if (ratio > 1) return `所有选中行按源价上浮 ${Math.round((ratio - 1) * 10000) / 100}%`
    return `所有选中行按源价下调 ${Math.round((1 - ratio) * 10000) / 100}%`
})

const loadSources = async (page = sourceTable.page) => {
    sourceLoading.value = true
    sourceTable.page = page
    try {
        const res: any = await getQuoteSourceList({
            page: sourceTable.page,
            limit: sourceTable.limit,
            ...sourceQuery
        })
        sourceTable.data = res.data.data || []
        sourceTable.total = res.data.total || 0
        const all: any = await getQuoteSourceAll()
        sourceOptions.value = all.data || []
    } finally {
        sourceLoading.value = false
    }
}

const resetSourceSearch = () => {
    sourceQuery.keyword = ''
    sourceQuery.status = ''
    loadSources(1)
}

const loadCategory = async () => {
    categoryLoading.value = true
    try {
        const res: any = await getQuoteCategoryTree({
            source_id: selectedSourceId.value,
            keyword: categoryQuery.keyword
        })
        categoryTable.data = res.data || []
        categoryTable.total = flattenCategories(categoryTable.data).length
    } finally {
        categoryLoading.value = false
    }
}

const loadCategoryOptions = async () => {
    const res: any = await getQuoteCategoryTree({
        source_id: selectedSourceId.value
    })
    categoryOptions.value = res.data || []
}

const handleExcelSourceChange = async () => {
    selectedSourceId.value = excelImportForm.source_id
    excelImportForm.category_id = ''
    excelImportForm.item_id = ''
    await loadCategoryOptions()
    await loadItem()
}

const loadFilterOptions = async () => {
    const res: any = await getQuoteItemFilterOptions({
        source_id: selectedSourceId.value,
        category_id: selectedCategoryId.value,
        category_ids: selectedCategoryScopeIds.value.join(',')
    })
    filterOptions.brands = res.data.brands || []
    filterOptions.tabs = res.data.tabs || []
    filterOptions.quote_types = res.data.quote_types || []
}

const loadItem = async () => {
    itemLoading.value = true
    try {
        const res: any = await getQuoteItemList({
            source_id: selectedSourceId.value,
            category_id: selectedCategoryId.value,
            category_ids: selectedCategoryScopeIds.value.join(','),
            ...itemQuery,
            page: itemTable.page,
            limit: itemTable.limit
        })
        itemTable.data = res.data.data || []
        itemTable.total = res.data.total || 0
    } finally {
        itemLoading.value = false
    }
}

const loadRows = async () => {
    if (!selectedItemId.value) {
        rowTable.data = []
        rowTable.total = 0
        return
    }
    rowLoading.value = true
    try {
        rowQuery.item_id = String(selectedItemId.value)
        rowQuery.page = rowTable.page
        rowQuery.limit = rowTable.limit
        const res: any = await getQuoteRowList({
            ...rowQuery,
            source_id: selectedSourceId.value,
            brand: itemQuery.brand,
            tab: itemQuery.tab,
            follow_source: itemQuery.follow_source,
            has_update: itemQuery.has_update
        })
        rowTable.data = res.data.data || []
        rowTable.total = res.data.total || 0
    } finally {
        rowLoading.value = false
    }
}

const loadLogs = async () => {
    logLoading.value = true
    try {
        const res: any = await getQuoteSyncLogList({ source_id: selectedSourceId.value, page: 1, limit: 20 })
        logTable.data = res.data.data || []
    } finally {
        logLoading.value = false
    }
}

const refreshManage = () => {
    itemTable.page = 1
    rowTable.page = 1
    loadCategory()
    loadCategoryOptions()
    loadFilterOptions()
    loadItem()
    loadRows()
}

const searchManage = () => {
    selectedItemId.value = 0
    selectedItemName.value = ''
    itemTable.page = 1
    rowTable.page = 1
    refreshManage()
}

const resetFilters = () => {
    categoryQuery.keyword = ''
    selectedCategoryId.value = ''
    selectedCategoryName.value = ''
    selectedItemId.value = 0
    selectedItemName.value = ''
    Object.assign(itemQuery, {
        keyword: '',
        is_show: '',
        is_hot: '',
        follow_source: '',
        has_update: '',
        is_image_quote: '',
        brand: '',
        tab: '',
        quote_type: ''
    })
    rowQuery.keyword = ''
    refreshManage()
}

const handleSourceFilterChange = () => {
    if (!selectedSourceId.value) selectedSourceId.value = ''
    selectedCategoryId.value = ''
    selectedCategoryName.value = ''
    selectedItemId.value = 0
    selectedItemName.value = ''
    refreshManage()
}

const handleSourceSizeChange = () => {
    sourceTable.page = 1
    loadSources()
}

const handleCategoryFilterChange = () => {
    if (!selectedCategoryId.value) selectedCategoryId.value = ''
    const current = findCategoryById(categoryOptions.value, selectedCategoryId.value)
    selectedCategoryName.value = current?.name || ''
    selectedItemId.value = 0
    selectedItemName.value = ''
    itemTable.page = 1
    rowTable.page = 1
    loadCategory()
    loadFilterOptions()
    loadItem()
    loadRows()
}

const openSourceData = (source: any) => {
    selectedSourceId.value = source.id
    activeTab.value = 'manage'
    handleSourceFilterChange()
}

const selectCategory = (row: any) => {
    selectedCategoryId.value = row.id
    selectedCategoryName.value = row.name
    selectedItemId.value = 0
    selectedItemName.value = ''
    itemTable.page = 1
    rowTable.page = 1
    loadFilterOptions()
    loadItem()
    loadRows()
}

const selectItem = (row: any) => {
    selectedItemId.value = row.id
    selectedItemName.value = row.name
    rowTable.page = 1
    loadRows()
}

const openRowDrawer = (row: any) => {
    selectedItemId.value = row.id
    selectedItemName.value = row.name
    rowDrawer.item = { ...row }
    rowDrawer.activeTab = 'rows'
    rowDrawer.visible = true
    rowSelection.value = []
    rowTable.page = 1
    loadRows()
}

const prepareExcelImport = (row: any) => {
    if (row?.id) {
        selectedItemId.value = row.id
        selectedItemName.value = row.name
        rowDrawer.item = { ...row }
    }
    excelImportForm.source_id = Number(row?.source_id || selectedSourceId.value || '')
    excelImportForm.category_id = Number(row?.category_id || selectedCategoryId.value || '')
    excelImportForm.item_id = Number(row?.id || selectedItemId.value || '')
    excelImportForm.item_name = row?.name || selectedItemName.value || ''
    excelImportForm.notice_text = row?.notice_text || ''
    excelImportForm.brand = row?.brand || ''
    excelImportForm.tab = row?.tab || ''
    if (row?.id) {
        rowDrawer.visible = true
        rowDrawer.activeTab = 'import'
    } else {
        activeTab.value = 'excel'
    }
}

const handleCategorySearch = () => {
    loadCategory()
}

const handleItemSearch = () => {
    selectedItemId.value = 0
    selectedItemName.value = ''
    itemTable.page = 1
    rowTable.page = 1
    loadItem()
    loadRows()
}

const handleItemSizeChange = () => {
    itemTable.page = 1
    loadItem()
}

const handleRowSearch = () => {
    rowTable.page = 1
    loadRows()
}

const handleRowSizeChange = () => {
    rowTable.page = 1
    loadRows()
}

const handleTabChange = () => {
    if (activeTab.value === 'manage') refreshManage()
    if (activeTab.value === 'log') loadLogs()
}

const openSourceDialog = (row: any = null) => {
    Object.assign(sourceDialog.form, row || {
        id: 0,
        source_key: '',
        source_name: '',
        provider: '',
        base_url: '',
        list_path: '',
        detail_path: '',
        request_config: {},
        sync_enabled: 0,
        sync_interval: 86400,
        timeout: 15,
        rate_limit: 300,
        retry_times: 1,
        status: 1
    })
    sourceDialog.curlText = ''
    sourceDialog.requestConfigText = JSON.stringify(sourceDialog.form.request_config || {}, null, 2)
    sourceDialog.visible = true
}

const parseSourceCurl = () => {
    try {
        const parsed = parseCurlCommand(sourceDialog.curlText)
        if (!parsed.url) {
            ElMessage.error('没有识别到 curl 里的请求地址')
            return
        }
        const url = new URL(parsed.url)
        const inferredPaths = inferQuoteSourcePaths(url.pathname)
        sourceDialog.form.base_url = `${url.protocol}//${url.host}`
        sourceDialog.form.list_path = inferredPaths.list_path
        if (!sourceDialog.form.detail_path) {
            sourceDialog.form.detail_path = inferredPaths.detail_path
        }

        const config = parseJsonObject(sourceDialog.requestConfigText)
        const query = { ...(config.query || {}) }
        if (inferredPaths.is_detail) {
            delete query.id
        }
        url.searchParams.forEach((value, key) => {
            if (inferredPaths.is_detail && key === 'id') return
            query[key] = value
        })
        const headers = { ...(config.headers || {}), ...parsed.headers }
        const cookies = { ...(config.cookies || {}), ...parsed.cookies }
        delete headers.cookie
        delete headers.Cookie

        const requestConfig = {
            ...config,
            query,
            headers,
            cookies
        }
        if (parsed.user_agent) {
            requestConfig.user_agent = parsed.user_agent
        }
        sourceDialog.form.request_config = requestConfig
        sourceDialog.requestConfigText = JSON.stringify(requestConfig, null, 2)

        if (!sourceDialog.form.provider) sourceDialog.form.provider = url.hostname.split('.')[0] || 'saas'
        if (!sourceDialog.form.source_key) sourceDialog.form.source_key = normalizeSourceKey(sourceDialog.form.provider || url.hostname)
        if (!sourceDialog.form.source_name) sourceDialog.form.source_name = `${url.hostname} 报价源`
        ElMessage.success(inferredPaths.is_detail ? '已识别为详情接口，并自动推断列表接口，请确认后保存' : '已解析 curl，请确认接口和权限信息后保存')
    } catch (error: any) {
        ElMessage.error(error?.message || 'curl 解析失败')
    }
}

const saveSource = async () => {
    try {
        sourceDialog.form.request_config = sourceDialog.requestConfigText ? JSON.parse(sourceDialog.requestConfigText) : {}
    } catch (e) {
        ElMessage.error('请求配置不是有效JSON')
        return
    }
    if (sourceDialog.form.id) {
        await editQuoteSource(sourceDialog.form.id, sourceDialog.form)
    } else {
        await addQuoteSource(sourceDialog.form)
    }
    sourceDialog.visible = false
    ElMessage.success('保存成功')
    loadSources()
}

const createDefaultSource = async () => {
    await createDefaultQuoteSource()
    ElMessage.success('默认报价源已创建')
    loadSources()
}

const syncSource = async (row: any) => {
    syncingId.value = row.id
    try {
        const res: any = await syncQuoteSource(row.id)
        const data = res.data || {}
        selectedSourceId.value = row.id
        activeTab.value = 'log'
        ElMessage.info(data.message || '同步任务已创建，请在同步日志中查看进度')
        startLogRefresh()
        loadSources()
    } finally {
        syncingId.value = 0
    }
}

const startLogRefresh = () => {
    stopLogRefresh()
    loadLogs()
    logRefreshTimer.value = window.setInterval(() => {
        loadLogs()
        loadSources()
    }, 3000)
    window.setTimeout(stopLogRefresh, 60000)
}

const stopLogRefresh = () => {
    if (logRefreshTimer.value !== null) {
        window.clearInterval(logRefreshTimer.value)
        logRefreshTimer.value = null
    }
}

const saveCategory = (row: any, notify = false) => editQuoteCategory(row.id, {
    name: row.name,
    is_show: row.is_show,
    is_hot: row.is_hot,
    sort: row.sort
}).then(() => {
    if (notify) ElMessage.success('分类已保存')
})

const saveItem = (row: any, notify = false) => editQuoteItem(row.id, {
    name: row.name,
    brand: row.brand,
    tab: row.tab,
    keywords: row.keywords,
    quote_type: row.quote_type,
    is_image_quote: row.is_image_quote,
    image: row.image,
    timage: row.timage,
    bimage: row.bimage,
    icon: row.icon,
    notice_text: row.notice_text,
    sort: row.sort,
    is_show: row.is_show,
    is_hot: row.is_hot,
    follow_source: row.follow_source
}).then(() => {
    if (notify) ElMessage.success('报价项已保存')
})

const saveDrawerItem = async () => {
    await saveItem(rowDrawer.item)
    const current = itemTable.data.find(item => item.id === rowDrawer.item.id)
    if (current) Object.assign(current, rowDrawer.item)
    ElMessage.success('报价项已保存')
}

const saveRow = (row: any, notify = false) => editQuoteRow(row.id, {
    model_name: row.model_name,
    brand: row.brand,
    tab: row.tab,
    remark: row.remark,
    manual_prices: normalizeManualPrices(row.manual_prices),
    is_show: row.is_show,
    sort: row.sort,
    follow_source: row.follow_source,
    adjust_type: row.adjust_type,
    adjust_value: row.adjust_value,
    adjust_ratio: row.adjust_ratio,
    round_mode: row.round_mode
}).then(() => {
    if (notify) ElMessage.success('行价格已保存')
    loadRows()
})

const openEditDialog = (type: EditType, row: any) => {
    editDialog.mode = 'edit'
    editDialog.type = type
    editDialog.form = { ...row }
    editDialog.columnsText = ''
    if (type === 'row') {
        editDialog.form.columns = normalizePriceArray(row.columns)
        editDialog.form.source_prices = normalizePriceArray(row.source_prices)
        editDialog.form.final_prices = normalizePriceArray(row.final_prices)
        editDialog.form.manual_prices = normalizeManualPrices(row.manual_prices, editDialog.form.columns.length || editDialog.form.source_prices.length || editDialog.form.final_prices.length)
        editDialog.form.follow_source = Number(row.follow_source ?? 1)
        editDialog.form.adjust_type = Number(row.adjust_type ?? 0)
        editDialog.form.adjust_value = Number(row.adjust_value ?? 0)
        editDialog.form.adjust_ratio = Number(row.adjust_ratio ?? 1)
    }
    editDialog.visible = true
}

const openCreateDialog = (type: EditType) => {
    editDialog.mode = 'create'
    editDialog.type = type
    editDialog.columnsText = ''
    if (type === 'category') {
        editDialog.form = {
            source_id: selectedSourceId.value || '',
            parent_id: selectedCategoryId.value || 0,
            name: '',
            sort: 0,
            is_show: 1,
            is_hot: 0
        }
    } else if (type === 'item') {
        editDialog.form = {
            source_id: selectedSourceId.value || '',
            category_id: selectedCategoryId.value || '',
            name: '',
            brand: '',
            tab: '',
            keywords: '',
            quote_type: 'manual',
            is_image_quote: 0,
            image: '',
            timage: '',
            bimage: '',
            icon: '',
            notice_text: '',
            is_show: 1,
            is_hot: 0,
            follow_source: 0,
            columns: []
        }
    } else {
        const currentItem = itemTable.data.find(item => item.id === selectedItemId.value) || {}
        editDialog.form = {
            source_id: selectedSourceId.value || currentItem.source_id || '',
            item_id: selectedItemId.value,
            model_name: '',
            brand: currentItem.brand || '',
            tab: currentItem.tab || '',
            remark: '',
            follow_source: 0,
            columns: normalizePriceArray(currentItem.columns),
            source_prices: [],
            manual_prices: normalizeManualPrices([], normalizePriceArray(currentItem.columns).length),
            final_prices: [],
            is_show: 1,
            sort: 0,
            adjust_type: 0,
            adjust_value: 0,
            adjust_ratio: 1
        }
        editDialog.columnsText = normalizePriceArray(currentItem.columns).join(',')
    }
    editDialog.visible = true
}

const handleRowModeChange = (value: number) => {
    if (value !== 0) return
    const manualPrices = normalizeManualPrices(editDialog.form.manual_prices, rowPriceTable.value.length)
    const finalPrices = normalizePriceArray(editDialog.form.final_prices)
    const sourcePrices = normalizePriceArray(editDialog.form.source_prices)
    editDialog.form.manual_prices = manualPrices.map((price, index) => {
        if (price !== '') return price
        return finalPrices[index] ?? sourcePrices[index] ?? ''
    })
}

const submitEdit = async () => {
    if (editDialog.mode === 'create') {
        if (editDialog.type === 'category') {
            await addQuoteCategory(editDialog.form)
            loadCategory()
            loadCategoryOptions()
        }
        if (editDialog.type === 'item') {
            await addQuoteItem(editDialog.form)
            loadItem()
            loadFilterOptions()
        }
        if (editDialog.type === 'row') {
            const columns = parseColumnsText(editDialog.columnsText)
            await addQuoteRow({
                ...editDialog.form,
                columns,
                manual_prices: normalizeManualPrices(editDialog.form.manual_prices, columns.length)
            })
            loadRows()
        }
        editDialog.visible = false
        ElMessage.success('新增成功')
        return
    }

    if (editDialog.type === 'category') {
        await saveCategory(editDialog.form)
        loadCategory()
    }
    if (editDialog.type === 'item') {
        await saveItem(editDialog.form)
        loadItem()
    }
    if (editDialog.type === 'row') {
        await saveRow(editDialog.form)
    }
    editDialog.visible = false
    ElMessage.success('保存成功')
}

const batchCategoryCommand = async (command: string) => {
    const payloadMap: Record<string, Record<string, any>> = {
        show: { is_show: 1 },
        hide: { is_show: 0 },
        hot: { is_hot: 1 },
        unhot: { is_hot: 0 }
    }
    await Promise.all(categorySelection.value.map(row => editQuoteCategory(row.id, payloadMap[command])))
    ElMessage.success('批量操作完成')
    loadCategory()
}

const batchItemCommand = async (command: string) => {
    const payloadMap: Record<string, Record<string, any>> = {
        show: { is_show: 1 },
        hide: { is_show: 0 },
        hot: { is_hot: 1 },
        unhot: { is_hot: 0 },
        follow: { follow_source: 1 },
        unfollow: { follow_source: 0 }
    }
    await Promise.all(itemSelection.value.map(row => editQuoteItem(row.id, payloadMap[command])))
    ElMessage.success('批量操作完成')
    loadItem()
}

const batchRowCommand = async (command: string) => {
    if (command === 'fixed' || command === 'ratio') {
        adjustDialog.form.adjust_type = command === 'fixed' ? 1 : 2
        adjustDialog.form.adjust_value = 0
        adjustDialog.form.adjust_ratio = 1
        adjustDialog.visible = true
        return
    }
    const payloadMap: Record<string, Record<string, any>> = {
        show: { is_show: 1 },
        hide: { is_show: 0 },
        follow: { follow_source: 1 },
        unfollow: { follow_source: 0 },
        clear: { adjust_type: 0, adjust_value: 0, adjust_ratio: 1 }
    }
    await Promise.all(rowSelection.value.map(row => editQuoteRow(row.id, payloadMap[command])))
    ElMessage.success('批量操作完成')
    loadRows()
}

const handleBatchAdjustTypeChange = (value: number) => {
    if (value === 1) {
        adjustDialog.form.adjust_ratio = 1
    } else {
        adjustDialog.form.adjust_value = 0
    }
}

const submitBatchAdjust = async () => {
    const payload = {
        adjust_type: adjustDialog.form.adjust_type,
        adjust_value: adjustDialog.form.adjust_value,
        adjust_ratio: adjustDialog.form.adjust_ratio
    }
    await Promise.all(rowSelection.value.map(row => editQuoteRow(row.id, payload)))
    adjustDialog.visible = false
    ElMessage.success('批量调价完成')
    loadRows()
}

const handleExcelChange = async (file: UploadFile) => {
    if (!file.raw) return
    excelFileName.value = file.name
    const formData = new FormData()
    formData.append('file', file.raw)
    if (excelSourceId.value) formData.append('source_id', String(excelSourceId.value))
    const uploadRes: any = await uploadQuoteExcel(formData)
    excelTaskId.value = Number(uploadRes.data.task_id || 0)
    const previewRes: any = await previewQuoteExcel({ task_id: uploadRes.data.task_id, preview_count: 20 })
    excelPreview.value = previewRes.data
    const drawerItem = rowDrawer.visible ? rowDrawer.item : {}
    excelImportForm.source_id = Number(excelImportForm.source_id || excelSourceId.value || selectedSourceId.value || drawerItem.source_id || '')
    excelImportForm.category_id = Number(excelImportForm.category_id || selectedCategoryId.value || drawerItem.category_id || '')
    excelImportForm.item_id = excelImportForm.item_id || selectedItemId.value || drawerItem.id || ''
    excelImportForm.item_name = excelImportForm.item_name || selectedItemName.value || drawerItem.name || file.name.replace(/\.(xls|xlsx)$/i, '')
    excelImportForm.notice_text = excelImportForm.notice_text || previewRes.data?.notice_text || ''
    if (rowDrawer.visible) {
        rowDrawer.activeTab = 'import'
    } else {
        activeTab.value = 'excel'
    }
}

const downloadExcelTemplate = () => {
    const rows = [
        ['报价提示', '温馨提示：报价仅供参考，最终价格以质检结果为准\n请确认设备型号、容量、成色与功能状态后再下单'],
        ['分组', '型号', '品牌', '内存', '靓机', '小花', '内爆', '备注'],
        ['17系列', 'iPhone 17', '苹果', '128GB', 5200, 5000, 4300, '正常回收报价'],
        ['17系列', 'iPhone 17 Pro', '苹果', '256GB', 6500, 6200, 5400, ''],
        ['17系列', 'iPhone 17 Pro Max', '苹果', '256GB', 7200, 6900, 6100, ''],
        ['Mate系列', 'Mate 60 Pro', '华为', '12+256GB', 4100, 3900, 3300, '']
    ]
    const tips = [
        ['字段', '是否必填', '说明'],
        ['报价提示', '选填', '固定写在第一行：第一列写“报价提示”，第二列写提示内容；单元格内换行会保存为 \\n。'],
        ['分组/系列', '选填', '用于把 17、17 Pro、17 Pro Max 等型号归到同一个系列，后台表格会按它跨行展示。'],
        ['型号', '必填', '每一行会导入为一个行价格；没有型号的行会被跳过。'],
        ['品牌', '选填', '为空时使用导入表单里填写的品牌。'],
        ['内存', '选填', '会写入行价格原始数据，用于后台 Excel 表格展示。'],
        ['价格列', '至少一列', '列名可以是靓机、小花、内爆、外爆、开机、不开机等；系统会识别为价格列。'],
        ['备注', '选填', '导入到行价格备注。'],
        ['覆盖报价项', '-', '会清空所选报价项原有行价格，再用 Excel 重新生成。Excel 里有的新型号会新增，Excel 里没有的旧型号会被删除。'],
        ['新建报价项', '-', '不选择覆盖报价项时，会在所选分类下新建一个报价项，并把 Excel 行写入这个报价项。']
    ]
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet(rows), '报价导入模板')
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet(tips), '填写说明')
    XLSX.writeFile(workbook, `回收报价导入模板_${new Date().toISOString().slice(0, 10)}.xlsx`)
}

const confirmExcelImport = async () => {
    if (!excelTaskId.value) {
        ElMessage.error('请先上传Excel')
        return
    }
    excelImporting.value = true
    try {
        const res: any = await confirmQuoteExcel({
            task_id: excelTaskId.value,
            source_id: excelImportForm.source_id,
            category_id: excelImportForm.category_id,
            item_id: excelImportForm.item_id,
            item_name: excelImportForm.item_name,
            notice_text: excelImportForm.notice_text,
            brand: excelImportForm.brand,
            tab: excelImportForm.tab,
            mapping: excelPreview.value.suggested_mapping || [],
            mode: 'replace'
        })
        ElMessage.success(`导入完成，已写入 ${res.data.rows || 0} 行`)
        activeTab.value = 'manage'
        selectedSourceId.value = excelImportForm.source_id
        selectedCategoryId.value = excelImportForm.category_id
        selectedItemId.value = Number(res.data.item_id || 0)
        selectedItemName.value = excelImportForm.item_name
        refreshManage()
        if (rowDrawer.visible) {
            rowDrawer.activeTab = 'rows'
            loadRows()
        }
    } finally {
        excelImporting.value = false
    }
}

const formatTime = (value: number) => {
    if (!value) return '-'
    return new Date(value * 1000).toLocaleString()
}

const formatPrices = (columns: any[] = [], prices: any[] = []) => {
    if (!Array.isArray(prices)) return ''
    return prices.map((price, index) => columns?.[index] ? `${columns[index]}:${price}` : price).join(' / ')
}

const isRemarkColumn = (label: string) => /备注|说明|描述|note|remark/i.test(label)

const isPriceColumn = (label: string) => /价|靓机|小花|内爆|外爆|开机|不开机|废板|屏好|屏坏|成色|回收|报价/u.test(String(label))

const formatMoney = (value: any) => {
    if (value === '' || value === null || value === undefined) return '-'
    return value
}

const normalizePriceArray = (value: any) => {
    return Array.isArray(value) ? [...value] : []
}

const normalizeManualPrices = (value: any, minLength = 0) => {
    const list = normalizePriceArray(value)
    while (list.length < minLength) list.push('')
    return list.map(item => item === null || item === undefined ? '' : item)
}

const parseColumnsText = (value: string) => {
    return value.split(/[,，\n]/).map(item => item.trim()).filter(Boolean)
}

const parseJsonObject = (value: string) => {
    if (!value.trim()) return {}
    const parsed = JSON.parse(value)
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
}

const parseCurlCommand = (value: string) => {
    const tokens = tokenizeCurl(value.replace(/\\\r?\n/g, ' '))
    const result = {
        url: '',
        headers: {} as Record<string, string>,
        cookies: {} as Record<string, string>,
        user_agent: ''
    }
    for (let index = 0; index < tokens.length; index++) {
        const token = tokens[index]
        if (token === 'curl') continue
        if ((token === '-H' || token === '--header') && tokens[index + 1]) {
            applyCurlHeader(tokens[++index], result)
            continue
        }
        if ((token === '-b' || token === '--cookie' || token === '--cookie-jar') && tokens[index + 1]) {
            Object.assign(result.cookies, parseCookieHeader(tokens[++index]))
            continue
        }
        if ((token === '-A' || token === '--user-agent') && tokens[index + 1]) {
            result.user_agent = tokens[++index]
            continue
        }
        if (token.startsWith('http://') || token.startsWith('https://')) {
            result.url = token
        }
    }
    return result
}

const tokenizeCurl = (value: string) => {
    const tokens: string[] = []
    let current = ''
    let quote = ''
    let escaped = false
    for (const char of value) {
        if (escaped) {
            current += char
            escaped = false
            continue
        }
        if (char === '\\') {
            escaped = true
            continue
        }
        if (quote) {
            if (char === quote) {
                quote = ''
            } else {
                current += char
            }
            continue
        }
        if (char === '\'' || char === '"') {
            quote = char
            continue
        }
        if (/\s/.test(char)) {
            if (current) {
                tokens.push(current)
                current = ''
            }
            continue
        }
        current += char
    }
    if (current) tokens.push(current)
    return tokens
}

const applyCurlHeader = (header: string, result: { headers: Record<string, string>, cookies: Record<string, string>, user_agent: string }) => {
    const splitIndex = header.indexOf(':')
    if (splitIndex <= 0) return
    const name = header.slice(0, splitIndex).trim()
    const value = header.slice(splitIndex + 1).trim()
    if (!name) return
    const lowerName = name.toLowerCase()
    if (ignoredCurlHeaders.includes(lowerName)) return
    if (lowerName === 'cookie') {
        Object.assign(result.cookies, parseCookieHeader(value))
        return
    }
    if (lowerName === 'user-agent') {
        result.user_agent = value
        return
    }
    result.headers[name] = value
}

const ignoredCurlHeaders = [
    'host',
    'connection',
    'content-length',
    'accept-encoding',
    'sec-fetch-site',
    'sec-fetch-mode',
    'sec-fetch-dest',
    'priority'
]

const parseCookieHeader = (value: string) => {
    const cookies: Record<string, string> = {}
    value.split(';').forEach(item => {
        const splitIndex = item.indexOf('=')
        if (splitIndex <= 0) return
        const key = item.slice(0, splitIndex).trim()
        const cookieValue = item.slice(splitIndex + 1).trim()
        if (key) cookies[key] = cookieValue
    })
    return cookies
}

const inferQuoteSourcePaths = (path: string) => {
    const result = {
        list_path: path,
        detail_path: inferDetailPath(path),
        is_detail: false
    }
    if (/\/getstores\d*$/i.test(path)) {
        result.detail_path = path.replace(/\/getstores\d*$/i, '/bj4')
        return result
    }
    if (/\/bj\d*$/i.test(path)) {
        result.list_path = path.replace(/\/bj\d*$/i, '/getstores1')
        result.detail_path = path
        result.is_detail = true
    }
    return result
}

const inferDetailPath = (listPath: string) => {
    if (listPath.includes('/newimage')) return listPath.replace('/newimage', '/bj')
    if (listPath.includes('/list')) return listPath.replace('/list', '/detail')
    return listPath
}

const normalizeSourceKey = (value: string) => {
    const key = value.toLowerCase().replace(/^https?:\/\//, '').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '')
    return key || `source_${Date.now()}`
}

const findCategoryById = (list: any[], id: number | string) => {
    if (id === '') return null
    for (const item of list) {
        if (item.id === id) return item
        const child = findCategoryById(item.children || [], id)
        if (child) return child
    }
    return null
}

const collectCategoryIds = (category: any): number[] => {
    const ids = [Number(category.id)]
    ;(category.children || []).forEach((child: any) => {
        ids.push(...collectCategoryIds(child))
    })
    return ids.filter(Boolean)
}

const flattenCategories = (list: any[]): any[] => {
    const result: any[] = []
    list.forEach(item => {
        result.push(item)
        result.push(...flattenCategories(item.children || []))
    })
    return result
}

const resolveQuoteItemIcon = (item: any) => {
    return item.icon || item.image || item.timage || item.bimage || ''
}

const imageUrl = (value: string) => {
    const url = String(value || '').trim()
    if (!url) return ''
    if (/^(https?:)?\/\//.test(url) || url.startsWith('data:')) return url
    return url.startsWith('/') ? url : `/${url}`
}

onMounted(() => {
    loadSources()
    refreshManage()
})

onBeforeUnmount(() => {
    stopLogRefresh()
})
</script>

<style scoped>
.quote-spider-page {
    --panel-border: #ebeef5;
    --muted-text: #909399;
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 40px;
    margin-bottom: 10px;
}

.panel-subtitle,
.muted,
.name-sub {
    color: var(--muted-text);
    font-size: 12px;
    line-height: 1.5;
}

.excel-pane {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.excel-notice-input {
    width: 360px;
}

.manage-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}

.panel {
    border: 1px solid var(--panel-border);
    border-radius: 4px;
    padding: 12px;
    min-width: 0;
    background: #fff;
}

.category-nav-panel {
    position: sticky;
    top: 12px;
}

.item-main-panel {
    min-width: 0;
}

.panel-tools,
.panel-actions,
.row-toolbar,
.drawer-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.panel-tools {
    margin-bottom: 10px;
}

.panel-actions {
    justify-content: space-between;
    margin-bottom: 10px;
}

.category-tree-wrap {
    max-height: 240px;
    margin-bottom: 10px;
    padding: 10px;
    border: 1px solid var(--panel-border);
    border-radius: 4px;
    overflow: auto;
    background: #fafafa;
}

.root-category-select {
    width: 100%;
    margin-bottom: 8px;
}

.category-tree-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
}

.panel-title,
.name-main,
.strong-text {
    font-weight: 600;
}

.category-nav-panel :deep(.category-name-column .cell) {
    display: flex;
    align-items: center;
    gap: 6px;
}

.category-nav-panel :deep(.category-name-column .el-table__placeholder) {
    flex: 0 0 auto;
}

.category-nav-panel :deep(.category-name-column .el-table__expand-icon) {
    flex: 0 0 auto;
    margin-top: 0;
}

.category-name-text {
    min-width: 0;
    line-height: 1.45;
}

.quote-item-cell {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.quote-item-icon {
    flex: 0 0 auto;
    width: 40px;
    height: 40px;
    border: 1px solid var(--panel-border);
    border-radius: 6px;
    background: #f7f8fa;
}

.quote-item-copy {
    min-width: 0;
}

.switch-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
    line-height: 24px;
}

.sort-input {
    width: 72px;
}

.price-line {
    white-space: normal;
    line-height: 1.6;
    font-size: 12px;
}

.drawer-shell {
    height: 100%;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.drawer-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--panel-border);
}

.drawer-title {
    color: #303133;
    font-size: 18px;
    font-weight: 600;
    line-height: 1.4;
}

.drawer-tabs {
    flex: 1;
    min-height: 0;
}

.row-toolbar {
    margin-bottom: 12px;
}

.drawer-import,
.item-info-layout {
    max-width: 980px;
}

.excel-price-cell {
    display: block;
    min-height: 24px;
    color: #1f2d3d;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

.excel-plain-cell {
    display: block;
    min-height: 24px;
    color: #303133;
}

.excel-remark-cell {
    display: block;
    min-height: 24px;
    color: #606266;
}

.excel-matrix-table {
    --el-table-border-color: #dcdfe6;
}

.excel-matrix-table :deep(.el-table__header th) {
    background: #f7f8fa;
    color: #303133;
    font-weight: 600;
}

.excel-matrix-table :deep(.el-table__cell) {
    padding: 8px 0;
}

.row-price-matrix :deep(.el-table__body td:nth-child(2)),
.row-price-matrix :deep(.el-table__body td:nth-child(3)) {
    background: #fbfcfe;
}

.matrix-group-cell {
    color: #303133;
    font-weight: 600;
    line-height: 1.45;
}

.item-info-layout {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.image-setting-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.image-setting-card {
    min-width: 0;
    padding: 12px;
    border: 1px solid var(--panel-border);
    border-radius: 4px;
    background: #fafafa;
}

.image-setting-title {
    margin-bottom: 10px;
    color: #303133;
    font-size: 13px;
    font-weight: 600;
}

.pager {
    justify-content: flex-end;
    margin-top: 12px;
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.json-preview {
    margin: 0;
    white-space: pre-wrap;
    font-size: 12px;
}

.form-tip {
    width: 100%;
    margin-top: 6px;
    color: var(--muted-text);
    font-size: 12px;
    line-height: 1.5;
}

.source-parse-actions {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}

.json-example {
    width: 100%;
    margin: 8px 0 0;
    padding: 10px 12px;
    border: 1px solid var(--panel-border);
    border-radius: 4px;
    background: #f7f8fa;
    color: #606266;
    font-size: 12px;
    line-height: 1.5;
    white-space: pre-wrap;
}

.row-edit-layout {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.row-edit-section {
    border: 1px solid var(--panel-border);
    border-radius: 4px;
    padding: 12px;
}

.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
    font-weight: 600;
}

.row-edit-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 14px;
}

.row-price-table {
    width: 100%;
}

.manual-price-input {
    width: 100%;
}

.final-price {
    font-weight: 600;
    color: var(--el-color-primary);
}

.batch-adjust-layout {
    display: grid;
    grid-template-columns: minmax(280px, 0.9fr) minmax(360px, 1.1fr);
    gap: 14px;
}

.adjust-preview-card {
    margin-top: 10px;
    padding: 12px;
    border: 1px solid var(--panel-border);
    border-radius: 4px;
    background: #f7f8fa;
}

.adjust-preview-main {
    margin-top: 6px;
    font-weight: 600;
    color: #303133;
}

@media (max-width: 1400px) {
    .manage-layout {
        grid-template-columns: 1fr;
    }

    .category-nav-panel {
        position: static;
    }
}

@media (max-width: 900px) {
    .image-setting-grid,
    .row-edit-grid,
    .batch-adjust-layout {
        grid-template-columns: 1fr;
    }

    .drawer-head {
        flex-direction: column;
    }
}
</style>
