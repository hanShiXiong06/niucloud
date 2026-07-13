<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">库存中心</div>
                    <div class="mt-1 text-sm text-gray-500">以设备为主线同时查看入库来源、当前库存和销售出库，未出库设备保留明确缺省状态。</div>
                </div>
                <div class="flex gap-2">
                    <el-button v-if="selectedPendingIds.length" type="warning" @click="openSendRefurbish()">批量开始整备（{{ selectedPendingIds.length }}）</el-button>
                    <el-button type="primary" plain @click="openSerialTrace">串号追踪</el-button>
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                </div>
            </div>

            <ErpRoleFocus :items="stockRoleFocus" />

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-6">
                <div class="summary-tile">
                    <div class="summary-label">有效库存</div>
                    <div class="summary-value">{{ turnoverSummary.total_count || 0 }}</div>
                    <div class="mt-1 text-xs text-gray-400">平均库龄 {{ turnoverSummary.average_age_days || 0 }} 天</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">库存成本</div>
                    <div class="summary-value">{{ money(turnoverSummary.total_cost) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">周转正常</div>
                    <div class="summary-value text-green-600">{{ turnoverSummary.healthy_count || 0 }}</div>
                    <div class="mt-1 text-xs text-gray-400">≤ {{ turnoverSummary.thresholds?.attention_days || 7 }} 天</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">需要关注</div>
                    <div class="summary-value text-blue-600">{{ turnoverSummary.attention_count || 0 }}</div>
                    <div class="mt-1 text-xs text-gray-400">{{ (turnoverSummary.thresholds?.attention_days || 7) + 1 }}～{{ turnoverSummary.thresholds?.warning_days || 15 }} 天</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">周转预警</div>
                    <div class="summary-value text-orange-600">{{ turnoverSummary.warning_count || 0 }}</div>
                    <div class="mt-1 text-xs text-gray-400">占用 {{ money(turnoverSummary.warning_cost) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">严重滞销</div>
                    <div class="summary-value text-red-600">{{ turnoverSummary.critical_count || 0 }}</div>
                    <div class="mt-1 text-xs text-gray-400">占用 {{ money(turnoverSummary.critical_cost) }}</div>
                </div>
            </div>

            <!-- 状态快筛 Tab -->
            <el-tabs v-model="activeTab" class="mt-4 erp-status-tabs" @tab-change="onTabChange">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="库存中" name="in_stock" />
                <el-tab-pane label="已售" name="sold" />
                <el-tab-pane label="已退" name="returned" />
                <el-tab-pane label="作废" name="void" />
            </el-tabs>

            <el-form :inline="true" class="mt-2" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="型号 / IMEI / 资产号 / 来源 / 仓库" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="仓库">
                    <el-select v-model="search.warehouse_id" clearable class="!w-[160px]" placeholder="全部仓库" @change="onSearchWarehouseChange">
                        <el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="库位">
                    <el-select v-model="search.location_id" clearable class="!w-[160px]" placeholder="全部库位" :disabled="!search.warehouse_id">
                        <el-option v-for="item in searchLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="分类">
                    <el-tree-select
                        v-model="search.category_id"
                        :data="categoryTree"
                        :props="{ label: 'category_name', value: 'category_id', children: 'child_list' }"
                        check-strictly
                        clearable
                        class="!w-[220px]"
                        node-key="category_id"
                        placeholder="全部分类"
                    />
                </el-form-item>
                <el-form-item label="整备">
                    <el-select v-model="search.refurbish_status" clearable class="!w-[140px]" placeholder="全部">
                        <el-option label="无需整备" value="none" />
                        <el-option label="待整备" value="pending" />
                        <el-option label="整备中" value="processing" />
                        <el-option label="已完成" value="done" />
                        <el-option label="整备异常" value="failed" />
                    </el-select>
                </el-form-item>
                <el-form-item label="去向">
                    <el-select v-model="search.sale_target" clearable class="!w-[140px]" placeholder="全部">
                        <el-option label="未定" value="unset" />
                        <el-option label="卖同行" value="peer" />
                        <el-option label="上商城" value="mall" />
                    </el-select>
                </el-form-item>
                <el-form-item label="上架">
                    <el-select v-model="search.listing_status" clearable class="!w-[140px]" placeholder="全部">
                        <el-option label="不需要" value="none" />
                        <el-option label="待拍照" value="need_photo" />
                        <el-option label="待定价" value="need_price" />
                        <el-option label="可上架" value="ready" />
                        <el-option label="已上架" value="listed" />
                    </el-select>
                </el-form-item>
                <el-form-item label="入库时间">
                    <el-date-picker v-model="search.dateRange" type="daterange" value-format="X" start-placeholder="开始" end-placeholder="结束" class="!w-[260px]" />
                </el-form-item>
                <el-form-item label="库龄">
                    <el-input-number v-model="search.stock_age_min" :min="0" :precision="0" :controls="false" placeholder="最少天" class="!w-[100px]" />
                    <span class="mx-1 text-gray-400">-</span>
                    <el-input-number v-model="search.stock_age_max" :min="0" :precision="0" :controls="false" placeholder="最多天" class="!w-[100px]" />
                </el-form-item>
                <el-form-item label="周转">
                    <el-select v-model="search.turnover_level" clearable class="!w-[140px]" placeholder="全部等级">
                        <el-option label="全部预警" value="risk" />
                        <el-option label="周转正常" value="healthy" />
                        <el-option label="需要关注" value="attention" />
                        <el-option label="周转预警" value="warning" />
                        <el-option label="严重滞销" value="critical" />
                    </el-select>
                </el-form-item>
                <el-form-item label="成本">
                    <el-input-number v-model="search.min_cost" :min="0" :precision="2" :controls="false" placeholder="最低" class="!w-[110px]" />
                    <span class="mx-1 text-gray-400">-</span>
                    <el-input-number v-model="search.max_cost" :min="0" :precision="2" :controls="false" placeholder="最高" class="!w-[110px]" />
                </el-form-item>
                <el-form-item label="预计售价">
                    <el-input-number v-model="search.min_price" :min="0" :precision="2" :controls="false" placeholder="最低" class="!w-[110px]" />
                    <span class="mx-1 text-gray-400">-</span>
                    <el-input-number v-model="search.max_price" :min="0" :precision="2" :controls="false" placeholder="最高" class="!w-[110px]" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large" :row-class-name="stockRowClassName" @selection-change="onSelectionChange">
                <el-table-column type="selection" width="48" :selectable="row => row.status === 'in_stock' && row.refurbish_status === 'pending'" />
                <el-table-column label="设备" min-width="260">
                    <template #default="{ row }">
                        <ErpDeviceIdentity :model="row.model" :spec="row.spec" :imei="row.imei" :sn="row.sn" :asset-no="row.asset_no" />
                    </template>
                </el-table-column>
                <el-table-column label="入库" min-width="230">
                    <template #default="{ row }">
                        <div class="lifecycle-cell lifecycle-cell--inbound">
                            <div class="lifecycle-cell__title">{{ row.inbound_party_name || row.party_name || '来源未记录' }}</div>
                            <div class="lifecycle-cell__line">{{ row.inbound_purchase_no || '手工入库' }}</div>
                            <div class="lifecycle-cell__line">来源 {{ row.inbound_origin_name || 'ERP采购' }}<span v-if="row.inbound_origin_plugin_name">· {{ row.inbound_origin_plugin_name }}</span></div>
                            <div class="lifecycle-cell__line">{{ [row.inbound_warehouse_name, row.inbound_location_name].filter(Boolean).join(' / ') || '位置未记录' }}</div>
                            <div class="lifecycle-cell__time">{{ formatTime(row.inbound_at || row.stock_in_at) }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="库龄 / 周转" width="170" align="center">
                    <template #default="{ row }">
                        <span v-if="row.status === 'sold' && row.outbound_at" class="age-pill age-pill--success">
                            {{ turnoverDays(row) }}天售出
                        </span>
                        <span v-else-if="row.status === 'in_stock'" class="age-pill" :class="`age-pill--${row.turnover_type || 'neutral'}`" :title="row.turnover_label">
                            在库{{ row.stock_age_days || 0 }}天 · {{ row.turnover_label }}
                        </span>
                        <span v-else class="age-pill age-pill--muted">已退出</span>
                        <div v-if="row.status === 'in_stock' && row.turnover_level !== 'healthy'" class="mt-1 text-xs text-gray-400">{{ row.turnover_action }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="成本 / 价值" min-width="175" align="right">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900">{{ money(row.total_cost) }}</div>
                        <div v-if="row.status === 'in_stock'" class="mt-1 text-xs text-gray-500">预计卖价 {{ Number(row.estimate_sale_price || 0) ? money(row.estimate_sale_price) : '-' }}</div>
                        <div v-else-if="hasEffectiveOutbound(row)" class="mt-1 text-xs" :class="Number(row.outbound_profit || 0) >= 0 ? 'text-green-600' : 'text-red-600'">实际毛利 {{ money(row.outbound_profit) }}</div>
                        <div v-else class="mt-1 text-xs text-gray-400">历史成本</div>
                    </template>
                </el-table-column>
                <el-table-column label="出库" min-width="240">
                    <template #default="{ row }">
                        <div v-if="hasEffectiveOutbound(row)" class="lifecycle-cell lifecycle-cell--outbound" :class="outboundToneClass(row.outbound_status)">
                            <div class="flex items-center gap-2">
                                <el-tag :type="outboundStatusMeta(row.outbound_status).type" size="small" effect="plain">{{ outboundStatusMeta(row.outbound_status).label }}</el-tag>
                                <span class="lifecycle-cell__title">{{ row.outbound_party_name || '客户未记录' }}</span>
                            </div>
                            <div class="lifecycle-cell__line">{{ row.outbound_sale_no || '销售单未记录' }}</div>
                            <div class="lifecycle-cell__line">来源 {{ row.outbound_origin_name || 'ERP销售' }}· 渠道 {{ row.outbound_channel || '-' }}</div>
                            <div class="lifecycle-cell__line">实际收入 {{ money(row.outbound_net_sale_amount) }} · 毛利 {{ money(row.outbound_profit) }}</div>
                            <div v-if="Number(row.outbound_compensation_amount || 0)" class="lifecycle-cell__time">原成交 {{ money(row.outbound_sale_price) }} · 售后补差 -{{ money(row.outbound_compensation_amount) }}</div>
                            <div class="lifecycle-cell__time">{{ formatTime(row.outbound_at) }}</div>
                        </div>
                        <div v-else class="lifecycle-empty">
                            <span class="lifecycle-empty__dot"></span>
                            <div>
                                <div class="lifecycle-empty__title">{{ row.status === 'returned' ? '采购退货完成' : row.status === 'void' ? '设备已作废' : '尚未销售出库' }}</div>
                                <div class="lifecycle-empty__desc">{{ ['returned', 'void'].includes(row.status) ? '设备已退出有效库存，不再进入销售流程' : '完成销售后自动补充客户、单号和成交信息' }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="当前库存 / 流转" min-width="240">
                    <template #default="{ row }">
                        <template v-if="row.status === 'in_stock'">
                            <div class="mb-2 text-xs text-gray-500">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '当前无库存位置' }}</div>
                            <div class="flex flex-wrap gap-1">
                                <el-tag type="success">库存中</el-tag>
                                <el-tag :type="refurbishMeta(row.refurbish_status).type" effect="plain">{{ refurbishMeta(row.refurbish_status).label }}</el-tag>
                                <el-tag :type="targetMeta(row.sale_target).type" effect="plain">{{ targetMeta(row.sale_target).label }}</el-tag>
                                <el-tag v-if="row.sale_target === 'mall'" :type="listingMeta(row.listing_status).type" effect="plain">{{ listingMeta(row.listing_status).label }}</el-tag>
                                <el-tag v-if="row.sale_target === 'mall'" :type="listingSyncMeta(row.listing_sync?.status).type" effect="plain">{{ row.listing_sync?.status_label || '尚未同步' }}</el-tag>
                            </div>
                            <div v-if="row.listing_sync?.status === 'failed'" class="mt-2 text-xs text-red-500 line-clamp-2" :title="row.listing_sync.last_error">{{ row.listing_sync.last_error || '同步失败，请重试' }}</div>
                            <div v-if="row.quality_remark" class="mt-2 text-xs text-gray-500 line-clamp-1">{{ row.quality_remark }}</div>
                        </template>
                        <div v-else class="stock-exit-state" :class="stockExitToneClass(row.status)">
                            <span class="stock-exit-state__dot"></span>
                            <div>
                                <div class="stock-exit-state__title">{{ assetStatusMeta(row.status).label }}</div>
                                <div class="stock-exit-state__desc">{{ stockExitDescription(row) }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="订单结算" min-width="210">
                    <template #default="{ row }">
                        <div class="settlement-line">
                            <span class="settlement-line__label">采购款</span>
                            <el-tag :type="financeStatusMeta(row.inbound_finance_status).type" size="small" effect="plain">{{ financeStatusMeta(row.inbound_finance_status).label }}</el-tag>
                            <span class="settlement-line__amount">已付 {{ money(row.inbound_settled_amount) }} / {{ money(row.inbound_settlement_amount) }}</span>
                        </div>
                        <div class="settlement-line">
                            <span class="settlement-line__label">销售款</span>
                            <template v-if="hasEffectiveOutbound(row)">
                                <el-tag :type="financeStatusMeta(row.outbound_finance_status).type" size="small" effect="plain">{{ financeStatusMeta(row.outbound_finance_status).label }}</el-tag>
                                <span class="settlement-line__amount">已收 {{ money(row.outbound_settled_amount) }} / {{ money(row.outbound_settlement_amount) }}</span>
                            </template>
                            <template v-else>
                                <el-tag type="info" size="small" effect="plain">未发生</el-tag>
                                <span class="settlement-line__amount">当前没有有效销售</span>
                            </template>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="330" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">档案</el-button>
                        <el-button v-if="row.status === 'in_stock'" type="primary" link @click="openFlow(row)">流转</el-button>
                        <el-button v-if="row.status === 'in_stock'" type="warning" link @click="openExpense(row)">成本调整</el-button>
                        <el-button v-if="row.status === 'in_stock' && row.refurbish_status === 'pending'" type="warning" link @click="openSendRefurbish(row)">开始整备</el-button>
                        <el-button v-if="row.status === 'in_stock' && ['pending','processing'].includes(row.refurbish_status)" type="success" link @click="openCompleteRefurbish(row)">登记完工</el-button>
                        <el-button v-if="row.status === 'in_stock' && row.sale_target === 'mall' && !['pending','processing','failed'].includes(row.refurbish_status)" type="success" link @click="syncListing(row)">{{ row.listing_sync?.status === 'failed' ? '重试同步' : '同步拍照定价' }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination
                    v-model:current-page="table.page"
                    v-model:page-size="table.limit"
                    layout="total, sizes, prev, pager, next, jumper"
                    :total="table.total"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <el-dialog v-model="flow.visible" title="设备流转设置" width="620px" destroy-on-close>
            <el-form label-width="96px">
                <el-alert title="待整备或整备中的设备不会出现在销售出库的待售库存中。" type="warning" :closable="false" show-icon />
                <div class="mt-4 rounded border border-gray-100 bg-gray-50 px-4 py-3">
                    <div class="font-medium">{{ flow.row?.model || '-' }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ flow.row?.spec || '未填写规格' }} · IMEI {{ flow.row?.imei || '-' }}</div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="整备状态">
                        <el-select v-model="flow.form.refurbish_status" class="w-full" :disabled="!['none','pending'].includes(flow.row?.refurbish_status)">
                            <el-option label="无需整备" value="none" />
                            <el-option label="待整备" value="pending" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="销售去向">
                        <el-select v-model="flow.form.sale_target" class="w-full" @change="onTargetChange">
                            <el-option label="暂未决定" value="unset" />
                            <el-option label="卖同行" value="peer" />
                            <el-option label="上商城" value="mall" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="上架状态">
                        <el-select v-model="flow.form.listing_status" class="w-full" :disabled="flow.form.sale_target !== 'mall'">
                            <el-option label="不需要" value="none" />
                            <el-option label="待拍照" value="need_photo" />
                            <el-option label="待定价" value="need_price" />
                            <el-option label="可上架" value="ready" />
                            <el-option label="已上架" value="listed" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="预计卖价">
                        <el-input-number v-model="flow.form.estimate_sale_price" :min="0" :precision="2" :controls="false" class="!w-full" />
                    </el-form-item>
                    <el-form-item label="零售价">
                        <el-input-number v-model="flow.form.retail_price" :min="0" :precision="2" :controls="false" class="!w-full" placeholder="上架商城定价" />
                    </el-form-item>
                </div>
                <el-form-item label="设备图片">
                    <upload-image v-model="flow.form.image_urls" :limit="9" width="72px" height="72px" image-text="上传/选择" />
                </el-form-item>
                <el-form-item label="质检备注">
                    <el-input v-model.trim="flow.form.quality_remark" type="textarea" :rows="2" placeholder="质检、外观说明（内部使用）" />
                </el-form-item>
                <el-form-item label="对外说明">
                    <el-input v-model.trim="flow.form.remark_public" type="textarea" :rows="2" placeholder="展示给客户/商城的描述" />
                </el-form-item>
                <el-form-item label="对内备注">
                    <el-input v-model.trim="flow.form.remark_internal" placeholder="员工内部备注，不对外展示" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="flow.visible = false">取消</el-button>
                <el-button type="primary" :loading="flow.saving" @click="submitFlow">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="serialTrace.visible" title="串号追踪" width="920px" destroy-on-close>
            <div class="mb-4 flex gap-2"><el-input v-model.trim="serialTrace.keyword" clearable placeholder="输入 IMEI / SN / 型号 / 供货商" @keyup.enter="loadSerialTrace" /><el-button type="primary" @click="loadSerialTrace">查询</el-button></div>
            <el-alert class="mb-4" title="同一串号允许多次入库；每次作为独立记录，最新入库排在最上面。" type="info" :closable="false" show-icon />
            <div v-loading="serialTrace.loading" class="min-h-[120px]">
                <div v-if="serialTrace.data.length" class="overflow-hidden rounded-lg border border-slate-200">
                    <div class="grid grid-cols-[minmax(220px,2fr)_minmax(140px,1fr)_170px_90px_100px_90px] gap-3 bg-slate-50 px-4 py-3 text-xs font-medium text-slate-500">
                        <span>设备</span><span>供货商</span><span>入库时间</span><span>次数</span><span>状态</span><span>操作</span>
                    </div>
                    <div v-for="row in serialTrace.data" :key="row.id" class="grid grid-cols-[minmax(220px,2fr)_minmax(140px,1fr)_170px_90px_100px_90px] items-center gap-3 border-t border-slate-100 px-4 py-3 text-sm">
                        <div class="min-w-0"><div class="truncate font-medium" :title="row.model">{{ row.model || '-' }}</div><div class="mt-1 text-xs text-blue-600">{{ row.serial_no || '-' }}</div><div class="mt-1 truncate text-xs text-gray-400" :title="row.spec">{{ row.spec || '-' }}</div></div>
                        <div class="truncate" :title="row.party_name || '未记录'">{{ row.party_name || '未记录' }}</div>
                        <div>{{ formatTime(row.stock_in_at || row.create_at) }}</div>
                        <div><el-tag v-if="row.inbound_count > 1" type="warning">{{ row.inbound_count }} 次</el-tag><span v-else>首次</span></div>
                        <div><el-tag :type="assetStatusMeta(row.status).type">{{ assetStatusMeta(row.status).label }}</el-tag></div>
                        <div><el-button type="primary" link @click="openTraceDetail(row)">查看流转</el-button></div>
                    </div>
                </div>
                <el-empty v-else-if="!serialTrace.loading" description="暂无串号记录" />
            </div>
            <div class="mt-4 flex justify-end"><el-pagination v-model:current-page="serialTrace.page" :page-size="serialTrace.limit" layout="total,prev,pager,next" :total="serialTrace.total" @current-change="loadSerialTrace" /></div>
        </el-dialog>

        <el-drawer v-model="serialTraceDetail.visible" title="串号生命周期" size="760px" destroy-on-close>
            <div v-loading="serialTraceDetail.loading" class="min-h-[320px]">
                <template v-if="serialTraceDetail.data">
                    <div class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="truncate text-lg font-semibold text-slate-900" :title="serialTraceDetail.data.model">{{ serialTraceDetail.data.model || '未填写设备名称' }}</div>
                                <div class="mt-1 truncate text-sm text-slate-500" :title="serialTraceDetail.data.spec">{{ serialTraceDetail.data.spec || '未填写规格' }}</div>
                            </div>
                            <el-tag :type="assetStatusMeta(serialTraceDetail.data.current_status).type">{{ assetStatusMeta(serialTraceDetail.data.current_status).label }}</el-tag>
                        </div>
                        <div class="mt-4 flex items-center justify-between rounded-lg bg-white/80 px-4 py-3">
                            <span class="text-sm text-slate-500">IMEI / SN</span>
                            <span class="select-all font-semibold text-blue-700">{{ serialTraceDetail.data.serial_no || '-' }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-4 gap-3 text-center">
                            <div><div class="text-xl font-bold text-slate-900">{{ serialTraceDetail.data.inbound_count || 0 }}</div><div class="mt-1 text-xs text-slate-500">入库次数</div></div>
                            <div><div class="text-xl font-bold text-slate-900">{{ serialTraceDetail.data.sale_count || 0 }}</div><div class="mt-1 text-xs text-slate-500">销售次数</div></div>
                            <div><div class="text-xl font-bold text-slate-900">{{ serialTraceDetail.data.after_sale_count || 0 }}</div><div class="mt-1 text-xs text-slate-500">售后退回</div></div>
                            <div><div class="text-xl font-bold text-slate-900">{{ serialTraceDetail.data.purchase_return_count || 0 }}</div><div class="mt-1 text-xs text-slate-500">采购退货</div></div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-end justify-between">
                        <div><div class="font-semibold text-slate-900">入库周期</div><div class="mt-1 text-xs text-slate-400">同一串号每次重新入库均为一段独立业务</div></div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <button v-for="cycle in serialTraceDetail.data.cycles || []" :key="cycle.id" type="button" class="rounded-lg border p-4 text-left transition hover:border-blue-400 hover:bg-blue-50" :class="cycle.is_current ? 'border-blue-400 bg-blue-50' : 'border-slate-200 bg-white'" @click="openTraceCycle(cycle)">
                            <div class="flex items-center justify-between gap-2"><span class="font-semibold text-blue-700">第 {{ cycle.cycle_no }} 次入库</span><el-tag v-if="cycle.is_current" size="small" type="primary">当前周期</el-tag></div>
                            <div class="mt-2 truncate text-sm text-slate-700" :title="cycle.party_name || '未记录供应商'">{{ cycle.party_name || '未记录供应商' }}</div>
                            <div class="mt-1 text-xs text-slate-400">{{ formatTime(cycle.stock_in_at || cycle.create_at) }}</div>
                            <div class="mt-2 truncate text-xs text-slate-500" :title="`${cycle.warehouse_name || '未记录仓库'} / ${cycle.location_name || '未记录库位'}`">{{ cycle.warehouse_name || '未记录仓库' }} / {{ cycle.location_name || '未记录库位' }}</div>
                        </button>
                    </div>

                    <div class="mb-4 mt-7"><div class="font-semibold text-slate-900">完整流转时间轴</div><div class="mt-1 text-xs text-slate-400">由早到晚展示采购、销售、售后及采退，每一步均保留经办人</div></div>
                    <el-empty v-if="!(serialTraceDetail.data.timeline || []).length" description="暂无流转记录" />
                    <el-timeline v-else class="pr-3">
                        <el-timeline-item v-for="(node, index) in serialTraceDetail.data.timeline || []" :key="`${node.id || index}-${node.cycle_no || 1}`" :timestamp="formatTime(node.occurred_at || node.create_at)" placement="top" :type="traceActionMeta(node.action).type">
                            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div><span class="font-semibold text-slate-900">{{ node.action_text || assetActionLabel(node.action) }}</span><span class="ml-2 text-xs text-slate-400">第 {{ node.cycle_no || 1 }} 次入库周期</span></div>
                                    <el-tag size="small" effect="plain" :type="traceActionMeta(node.action).type">{{ traceActionMeta(node.action).label }}</el-tag>
                                </div>
                                <div v-if="node.before_status || node.after_status" class="mt-3 flex items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                    <span>{{ node.before_status_text || assetStatusMeta(node.before_status).label }}</span><span class="text-slate-300">→</span><strong class="text-slate-700">{{ node.after_status_text || assetStatusMeta(node.after_status).label }}</strong>
                                </div>
                                <div v-if="isTraceCostAdjust(node)" class="mt-3 rounded-md border border-amber-100 bg-amber-50 px-3 py-3">
                                    <div class="flex items-center justify-between gap-3"><span class="text-sm font-medium text-amber-800">{{ node.cost_type_text || '成本调整' }}</span><strong :class="Number(node.cost_delta || 0) >= 0 ? 'text-red-600' : 'text-green-600'">{{ signedMoney(node.cost_delta) }}</strong></div>
                                    <div class="mt-1 text-xs text-amber-700/80">设备成本：{{ money(node.before_total_cost) }} → {{ money(node.after_total_cost) }}</div>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs text-slate-500">
                                    <div>操作人：<span class="text-slate-700">{{ node.operator_display || node.operator_name || '系统自动' }}</span></div>
                                    <div v-if="node.party_name">往来方：<span class="text-slate-700">{{ node.party_name }}</span></div>
                                    <div v-if="node.source_no" class="col-span-2 truncate" :title="node.source_no">关联单号：<span class="select-all text-slate-700">{{ node.source_no }}</span></div>
                                </div>
                                <div v-if="node.remark" class="mt-3 border-t border-dashed border-slate-200 pt-3 text-sm leading-6 text-slate-600">{{ node.remark }}</div>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </template>
            </div>
        </el-drawer>

        <el-dialog v-model="expense.visible" title="设备成本调整" width="680px" destroy-on-close>
            <el-alert :title="costTypeTip" type="warning" :closable="false" show-icon />
            <div class="mt-4 rounded border border-gray-100 bg-gray-50 px-4 py-3">
                <div class="font-medium">{{ expense.row?.model || '-' }}</div>
                <div class="mt-1 text-xs text-gray-500">IMEI {{ expense.row?.imei || '-' }} · 当前成本 {{ money(expense.row?.total_cost) }}</div>
            </div>
            <el-form class="mt-4" label-width="110px">
                <el-form-item label="成本类型" required>
                    <el-radio-group v-model="expense.form.cost_type">
                        <el-radio-button label="purchase_adjust">供应商调价</el-radio-button>
                        <el-radio-button label="internal_adjust">内部修正</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="调整后成本" required><el-input-number v-model="expense.form.after_cost" :min="0.01" :precision="2" :controls="false" class="!w-full" /></el-form-item>
                <el-form-item label="调整原因" required><el-input v-model.trim="expense.form.reason" type="textarea" :rows="3" placeholder="说明供应商调价或账面修正原因；整备费用请走整备完工" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="expense.visible = false">取消</el-button>
                <el-button type="primary" :loading="expense.saving" @click="submitExpense">确认调整</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="sendRefurbish.visible" title="开始整备" width="600px" destroy-on-close>
            <el-alert :title="sendRefurbish.form.tracking_mode === 'external' ? '外送追踪会记录这些设备当前在哪家整备商手中。' : '简易登记不追踪在谁手中，完工时再填写每项服务商和费用。'" type="info" :closable="false" show-icon />
            <div class="my-4 rounded-lg bg-slate-50 p-4"><strong>本次 {{ sendRefurbish.assetIds.length }} 台设备</strong><div class="mt-1 text-xs text-gray-500">一次确认即可完成整筐设备交接，设备仍归属原库存位置。</div></div>
            <el-form label-width="100px">
                <el-form-item label="跟踪方式"><el-radio-group v-model="sendRefurbish.form.tracking_mode"><el-radio-button label="simple">简易登记</el-radio-button><el-radio-button label="external">外送追踪</el-radio-button></el-radio-group></el-form-item>
                <el-form-item v-if="sendRefurbish.form.tracking_mode === 'external'" label="整备商" required><counterparty-select v-model="sendRefurbish.form.provider_party_id" role-type="supplier" placeholder="选择当前接收设备的整备商" /></el-form-item>
                <el-form-item label="交接说明"><el-input v-model.trim="sendRefurbish.form.remark" type="textarea" :rows="3" placeholder="选填，例如整筐送修、预计返回时间" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="sendRefurbish.visible=false">取消</el-button><el-button type="warning" :loading="sendRefurbish.saving" @click="submitSendRefurbish">确认开始</el-button></template>
        </el-dialog>

        <el-dialog v-model="completeRefurbish.visible" title="登记整备结果" width="820px" destroy-on-close>
            <div class="rounded-lg bg-slate-50 p-4"><div class="font-medium">{{ completeRefurbish.row?.model || '-' }}</div><div class="mt-1 text-xs text-gray-500">IMEI {{ completeRefurbish.row?.imei || '-' }} · 当前成本 {{ money(completeRefurbish.row?.total_cost) }}</div></div>
            <el-form class="mt-4" label-width="100px">
                <el-form-item label="整备结果" required><el-radio-group v-model="completeRefurbish.form.result"><el-radio-button label="success">修复成功</el-radio-button><el-radio-button label="partial">部分修复</el-radio-button><el-radio-button label="failed">修复失败</el-radio-button></el-radio-group></el-form-item>
                <el-form-item label="实际项目"><div class="w-full space-y-2"><div v-for="(item,index) in completeRefurbish.form.refurbish_items" :key="index" class="grid grid-cols-12 gap-2"><el-input v-model.trim="item.name" class="col-span-4" placeholder="例如换屏、换电池、人工" /><el-input-number v-model="item.amount" class="!w-full col-span-3" :min="0" :precision="2" :controls="false" placeholder="金额" /><div class="col-span-4"><counterparty-select v-model="item.party_id" role-type="supplier" placeholder="服务商" /></div><el-button class="col-span-1" text type="danger" @click="removeRefurbishItem(index)">删除</el-button></div><el-button plain type="primary" @click="addRefurbishItem">+ 添加实际整备项目</el-button><div class="text-xs text-gray-400">没有产生费用可以不添加；每项可选择不同整备商，系统按设备、按服务商分别生成应付。</div></div></el-form-item>
                <el-form-item label="完成后仓库"><el-select v-model="completeRefurbish.form.warehouse_id" class="w-full" placeholder="默认保留原仓库" clearable @change="completeRefurbish.form.location_id=0"><el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" /></el-select></el-form-item>
                <el-form-item label="完成后库位"><el-select v-model="completeRefurbish.form.location_id" class="w-full" placeholder="默认保留原库位" clearable><el-option v-for="item in completeLocations" :key="item.id" :label="item.location_name" :value="item.id" /></el-select></el-form-item>
                <el-form-item label="结果说明"><el-input v-model.trim="completeRefurbish.form.remark" type="textarea" :rows="3" placeholder="记录实际维修结果、未修好原因或异常去向" /></el-form-item>
                <el-form-item label="整备凭证"><ErpFinanceVoucherUpload v-model="completeRefurbish.form.voucher_urls" /><div class="ml-3 text-xs text-gray-400">可上传维修清单、服务商账单或设备返回照片；实际付款凭证由财务付款时上传。</div></el-form-item>
            </el-form>
            <template #footer><el-button @click="completeRefurbish.visible=false">取消</el-button><el-button type="primary" :loading="completeRefurbish.saving" @click="submitCompleteRefurbish">确认完工</el-button></template>
        </el-dialog>

        <el-drawer v-model="detail.visible" title="设备档案" size="72%" destroy-on-close>
            <div v-loading="detail.loading">
                <template v-if="detail.data">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold">{{ detail.data.model || '-' }}</div>
                            <div class="mt-1 text-sm text-gray-500">{{ assetSubTitle(detail.data) }}</div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <el-tag :type="assetStatusMeta(detail.data.status).type">{{ assetStatusMeta(detail.data.status).label }}</el-tag>
                            <template v-if="detail.data.status === 'in_stock'">
                                <el-tag :type="refurbishMeta(detail.data.refurbish_status).type" effect="plain">{{ refurbishMeta(detail.data.refurbish_status).label }}</el-tag>
                                <el-tag :type="targetMeta(detail.data.sale_target).type" effect="plain">{{ targetMeta(detail.data.sale_target).label }}</el-tag>
                                <el-tag v-if="detail.data.sale_target === 'mall'" :type="listingMeta(detail.data.listing_status).type" effect="plain">{{ listingMeta(detail.data.listing_status).label }}</el-tag>
                            </template>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-5">
                        <div v-for="item in detailMetrics(detail.data)" :key="item.label" class="summary-tile">
                            <div class="summary-label">{{ item.label }}</div>
                            <div class="summary-value" :class="item.className">{{ item.value }}</div>
                        </div>
                    </div>

                    <el-descriptions class="mt-5" :column="3" border>
                        <el-descriptions-item label="采购来源">{{ detail.data.party_name || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="detail.data.status === 'in_stock' ? '当前仓库' : '出库仓库'">{{ [detail.data.warehouse_name, detail.data.location_name].filter(Boolean).join(' / ') || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="质检员">{{ detail.data.inspector_name || '-' }}</el-descriptions-item>
                        <el-descriptions-item v-if="detail.data.status === 'in_stock'" label="预计卖价">{{ Number(detail.data.estimate_sale_price || 0) ? money(detail.data.estimate_sale_price) : '-' }}</el-descriptions-item>
                        <el-descriptions-item v-if="detail.data.status === 'in_stock'" label="入库库龄">{{ detail.data.stock_in_at ? `${stockAgeDays(detail.data.stock_in_at)} 天` : '-' }}</el-descriptions-item>
                        <el-descriptions-item v-if="detail.data.status === 'in_stock'" label="上架状态">{{ listingMeta(detail.data.listing_status).label }}</el-descriptions-item>
                        <el-descriptions-item v-if="detail.data.status !== 'in_stock'" label="原成交价">{{ Number(detail.data.sale_price || detail.data.last_sale_item?.sale_price || 0) ? money(detail.data.sale_price || detail.data.last_sale_item?.sale_price) : '-' }}</el-descriptions-item>
                        <el-descriptions-item v-if="detail.data.status !== 'in_stock'" label="售后补差">{{ Number(detail.data.sale_compensation_amount || 0) ? `-${money(detail.data.sale_compensation_amount)}` : money(0) }}</el-descriptions-item>
                        <el-descriptions-item v-if="detail.data.status !== 'in_stock'" label="实际销售收入">{{ money(detail.data.net_sale_amount) }}</el-descriptions-item>
                        <el-descriptions-item v-if="detail.data.status !== 'in_stock'" label="最近毛利">{{ Number(detail.data.profit || detail.data.last_sale_item?.profit || 0) ? money(detail.data.profit || detail.data.last_sale_item?.profit) : '-' }}</el-descriptions-item>
                        <el-descriptions-item v-if="detail.data.status !== 'in_stock'" label="销售单">{{ detail.data.sale_order?.sale_no || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="入库图片" :span="3"><ErpImageGallery :value="detail.data.image_urls" :size="72" :limit="9" /></el-descriptions-item>
                        <el-descriptions-item label="备注" :span="3" width="100">
                            {{ detail.data.quality_remark || detail.data.remark || '-' }}
                        </el-descriptions-item>
                    </el-descriptions>

                    <el-collapse v-model="detailActivePanels" class="mt-6">
                        <el-collapse-item name="purchase" title="采购批次">
                            <div class="section-title">采购批次</div>
                            <el-descriptions :column="1" border>
                                <el-descriptions-item label="采购单">{{ detail.data.purchase_order?.purchase_no || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="采购用户">{{ detail.data.purchase_order?.party_name || detail.data.party_name || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="付款状态">{{ financeStatusLabel(detail.data.purchase_order?.finance_status) }}</el-descriptions-item>
                            </el-descriptions>
                        </el-collapse-item>
                        <el-collapse-item name="sale" title="销售批次">
                            <div class="section-title">销售批次</div>
                            <el-descriptions :column="1" border>
                                <el-descriptions-item label="销售单">{{ detail.data.sale_order?.sale_no || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="销售客户">{{ detail.data.sale_order?.party_name || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="收款状态">{{ financeStatusLabel(detail.data.sale_order?.finance_status) }}</el-descriptions-item>
                            </el-descriptions>
                        </el-collapse-item>
                    </el-collapse>

                    <div class="mt-6">
                        <div class="section-title">设备流水</div>
                        <el-alert class="mb-3" title="库存流水记录设备入库、销售出库、退货、成本调整、流转设置等库存动作；老数据或未触发库存动作时可能为空。" type="info" :closable="false" show-icon />
                        <el-empty v-if="!(detail.data.asset_ledgers || []).length" description="暂无库存流水" />
                        <el-table v-else :data="detail.data.asset_ledgers || []" size="small">
                            <el-table-column label="流水号" min-width="180">
                                <template #default="{ row }">{{ row.ledger_no || '-' }}</template>
                            </el-table-column>
                            <el-table-column label="动作" width="120">
                                <template #default="{ row }">
                                    <el-tag effect="plain">{{ row.action_text || assetActionLabel(row.action) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="变化" min-width="220">
                                <template #default="{ row }">{{ row.before_status_text || assetStatusMeta(row.before_status).label }} → {{ row.after_status_text || assetStatusMeta(row.after_status).label }}</template>
                            </el-table-column>
                            <el-table-column label="仓库库位" min-width="220">
                                <template #default="{ row }">
                                    <div>{{ [row.after_warehouse_name, row.after_location_name].filter(Boolean).join(' / ') || '-' }}</div>
                                    <div v-if="row.before_warehouse_name && row.before_warehouse_name !== row.after_warehouse_name" class="text-xs text-gray-400">
                                        原：{{ [row.before_warehouse_name, row.before_location_name].filter(Boolean).join(' / ') }}
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="成本变化" width="190" align="right">
                                <template #default="{ row }">
                                    <div>{{ money(row.before_total_cost) }} → {{ money(row.after_total_cost) }}</div>
                                    <div v-if="Number(row.cost_delta || 0)" class="text-xs" :class="Number(row.cost_delta || 0) > 0 ? 'text-red-500' : 'text-green-600'">
                                        {{ Number(row.cost_delta || 0) > 0 ? '+' : '' }}{{ money(row.cost_delta) }}
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="来源" min-width="160">
                                <template #default="{ row }"><div>{{ row.source_no || '-' }}</div><div v-if="row.source_type" class="mt-1 text-xs text-gray-400">{{ row.source_type_text || sourceTypeLabel(row.source_type) }}</div></template>
                            </el-table-column>
                            <el-table-column label="操作人" min-width="110">
                                <template #default="{ row }">{{ row.operator_display || row.operator_name || (Number(row.operator_id || row.operator_uid || 0) > 0 ? `员工 #${Number(row.operator_id || row.operator_uid)}` : '系统自动') }}</template>
                            </el-table-column>
                            <el-table-column label="说明" min-width="260"><template #default="{ row }">{{ accountLedgerRemark(row) }}</template></el-table-column>
                            <el-table-column label="时间" width="180"><template #default="{ row }">{{ formatTime(row.occurred_at || row.create_at) }}</template></el-table-column>
                        </el-table>
                    </div>

                    <div class="mt-6">
                        <div class="section-title">设备账务轨迹</div>
                        <el-alert class="mb-3" title="展示这台设备从采购应付、销售应收到付款、收款、折账及冲销的完整轨迹；已撤销销售会明确标记‘已冲销’，同一笔售后补差的应付与付款会合并展示。" type="info" :closable="false" show-icon />
                        <el-empty v-if="!accountTimelineRows(detail.data.account_ledgers).length" description="暂无设备账务记录" />
                        <el-table v-else :data="accountTimelineRows(detail.data.account_ledgers)" size="small">
                            <el-table-column label="业务事件" width="140"><template #default="{ row }">{{ timelineTypeLabel(row) }}</template></el-table-column>
                            <el-table-column label="账务影响" width="150">
                                <template #default="{ row }">
                                    <el-tag :type="timelineDirectionMeta(row).type" effect="plain">{{ timelineDirectionMeta(row).label }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="金额" width="130" align="right"><template #default="{ row }">{{ money(row.amount) }}</template></el-table-column>
                            <el-table-column label="结算结果" min-width="150"><template #default="{ row }"><span :class="timelineSettlementText(row).includes('待') ? 'text-orange-500' : 'text-green-600'">{{ timelineSettlementText(row) }}</span></template></el-table-column>
                            <el-table-column label="来源" min-width="180"><template #default="{ row }"><div>{{ row._display_source_no || row.source_no || '-' }}</div><div v-if="row.source_type" class="mt-1 text-xs text-gray-400">{{ row._merged_compensation ? '售后补差' : (row.source_type_text || sourceTypeLabel(row.source_type)) }}</div></template></el-table-column>
                            <el-table-column label="说明" min-width="240"><template #default="{ row }">{{ accountLedgerRemark(row) }}</template></el-table-column>
                            <el-table-column label="时间" width="180"><template #default="{ row }">{{ formatTime(row.create_at || row.occurred_at) }}</template></el-table-column>
                        </el-table>
                    </div>
                </template>
            </div>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onActivated, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import { adjustErpStockCost, completeErpStockRefurbish, getErpGoodsCategoryTree, getErpSerialTraceDetail, getErpSerialTraceList, getErpStockInfo, getErpStockList, getErpStockTurnoverSummary, sendErpStockRefurbish, syncErpStockListing, updateErpStockFlow } from '@/addon/hsx_erp/api/erp'
import { getErpFinanceCategories } from '@/addon/hsx_erp/api/config'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import ErpDeviceIdentity from '@/addon/hsx_erp/components/ErpDeviceIdentity.vue'
import ErpRoleFocus from '@/addon/hsx_erp/components/ErpRoleFocus.vue'
import ErpImageGallery from '@/addon/hsx_erp/components/ErpImageGallery.vue'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'

const search = reactive<any>({ keyword: '', status: '', refurbish_status: '', sale_target: '', listing_status: '', turnover_level: '', warehouse_id: '', location_id: '', category_id: '', dateRange: [], stock_age_min: undefined, stock_age_max: undefined, min_cost: undefined, max_cost: undefined, min_price: undefined, max_price: undefined })
const route = useRoute()
const activeTab = ref('')

function onTabChange(tab: string) {
    search.status = tab
    table.page = 1
    loadList()
}
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const turnoverSummary = ref<any>({ thresholds: {} })
const detail = reactive({ visible: false, loading: false, data: null as any })
const detailActivePanels = ref<string[]>([])
const flow = reactive({ visible: false, saving: false, row: null as any, form: defaultFlowForm() })
const expense = reactive({ visible: false, saving: false, row: null as any, form: { cost_type: 'refurbish', expense_type_key: '', party_id: 0, party_name: '', amount: 0, after_cost: 0, reason: '' } })
const serialTrace = reactive({ visible: false, loading: false, keyword: '', data: [] as any[], page: 1, limit: 10, total: 0 })
const serialTraceDetail = reactive({ visible: false, loading: false, data: null as any })
const selectedRows = ref<any[]>([])
const sendRefurbish = reactive({ visible: false, saving: false, assetIds: [] as number[], form: { tracking_mode: 'simple', provider_party_id: 0, remark: '' } })
const completeRefurbish = reactive({ visible: false, saving: false, row: null as any, form: { result: 'success', refurbish_items: [] as any[], warehouse_id: 0, location_id: 0, voucher_urls: '', remark: '' } })
const financeCategories = ref<any[]>([])
const warehouses = ref<any[]>([])
const categoryTree = ref<any[]>([])
const activatedOnce = ref(false)
const searchWarehouse = computed(() => warehouses.value.find(row => Number(row.id) === Number(search.warehouse_id)) || null)
const searchLocations = computed(() => searchWarehouse.value?.locations || [])
const refurbishExpenseTypes = computed(() => financeCategories.value.filter((row: any) => row.direction === 'expense' && row.scope === 'refurbish' && Number(row.enabled ?? 1) === 1))
const selectedPendingIds = computed(() => selectedRows.value.filter(row => row.status === 'in_stock' && row.refurbish_status === 'pending').map(row => Number(row.id)))
const completeWarehouse = computed(() => warehouses.value.find(row => Number(row.id) === Number(completeRefurbish.form.warehouse_id)) || null)
const completeLocations = computed(() => completeWarehouse.value?.locations || [])
const costTypeTip = computed(() => ({
    purchase_adjust: '供应商调价会同步采购本金和供应商应付；已形成付款事实时后端会阻止直接修改。',
    internal_adjust: '内部修正只订正账面成本，不改变供应商往来。',
}[expense.form.cost_type] || '请核对成本调整类型和金额'))
const stockRoleFocus = [
    { role: '仓管', focus: '入库位置、当前库存、库龄与出库结果' },
    { role: '销售', focus: '可售状态、预计售价、客户与最近成交' },
    { role: '财务', focus: '采购成本、销售毛利与完整资产追溯' },
]

const summary = computed(() => table.data.reduce((acc, row: any) => {
    acc.count += 1
    if (row.status === 'in_stock') acc.cost += Number(row.total_cost || 0)
    if (row.status === 'in_stock') acc.inStockCount += 1
    if (['pending', 'processing', 'failed'].includes(row.refurbish_status || '')) acc.needRefurbish += 1
    if (row.status === 'in_stock' && !['pending', 'processing', 'failed'].includes(row.refurbish_status || '')) acc.saleable += 1
    if (row.status === 'sold') acc.sold += 1
    return acc
}, { count: 0, cost: 0, inStockCount: 0, needRefurbish: 0, saleable: 0, sold: 0 }))

onMounted(() => {
    if (route.query.refurbish_status) search.refurbish_status = String(route.query.refurbish_status)
    if (route.query.turnover_level) search.turnover_level = String(route.query.turnover_level)
    loadList()
    loadWarehouses()
    loadCategories()
    loadFinanceCategories()
})

onActivated(() => {
    if (!activatedOnce.value) {
        activatedOnce.value = true
        return
    }
    loadList()
})

function stockAgeDays(stockInAt: number): number {
    if (!stockInAt) return 0
    return Math.floor((Date.now() / 1000 - stockInAt) / 86400)
}

function turnoverDays(row: any): number {
    const inboundAt = Number(row.inbound_at || row.stock_in_at || 0)
    const outboundAt = Number(row.outbound_at || 0)
    if (!inboundAt || !outboundAt || outboundAt <= inboundAt) return 0
    return Math.floor((outboundAt - inboundAt) / 86400)
}

function defaultFlowForm() {
    return {
        refurbish_status: 'none',
        sale_target: 'unset',
        listing_status: 'none',
        estimate_sale_price: 0,
        retail_price: 0,
        image_urls: '',
        quality_remark: '',
        remark_public: '',
        remark_internal: '',
    }
}

async function loadList() {
    table.loading = true
    try {
        const [res, turnoverRes]: any[] = await Promise.all([
            getErpStockList({ ...buildSearchParams(), page: table.page, limit: table.limit }),
            getErpStockTurnoverSummary()
        ])
        table.data = res?.data?.data || []
        table.total = res?.data?.total || 0
        turnoverSummary.value = turnoverRes?.data || { thresholds: {} }
    } finally {
        table.loading = false
    }
}

async function openSerialTrace() {
    serialTrace.visible = true
    serialTrace.page = 1
    // 等弹窗和 el-table 完成挂载后再写入异步数据，避免只更新分页总数、表体不重绘。
    await nextTick()
    await loadSerialTrace()
}
async function loadSerialTrace() {
    serialTrace.loading = true
    try {
        const res: any = await getErpSerialTraceList({ keyword: serialTrace.keyword, page: serialTrace.page, limit: serialTrace.limit })
        const rows = Array.isArray(res?.data?.data) ? res.data.data : []
        // 保留同一响应式数组引用，兼容 destroy-on-close 弹窗的重新挂载。
        serialTrace.data.splice(0, serialTrace.data.length, ...rows)
        serialTrace.total = Number(res?.data?.total || 0)
    } finally { serialTrace.loading = false }
}
async function openTraceDetail(row: any) {
    if (!row?.id) return
    serialTraceDetail.visible = true
    serialTraceDetail.loading = true
    serialTraceDetail.data = null
    try {
        const res: any = await getErpSerialTraceDetail(Number(row.id))
        serialTraceDetail.data = res?.data || null
    } catch (error: any) {
        serialTraceDetail.visible = false
        ElMessage.error(error?.message || '串号生命周期加载失败')
    } finally {
        serialTraceDetail.loading = false
    }
}

function openTraceCycle(cycle: any) {
    if (!cycle?.id) return
    serialTraceDetail.visible = false
    serialTrace.visible = false
    openDetail(cycle)
}

function traceActionMeta(action: string) {
    const value = String(action || '').toLowerCase()
    if (['inbound'].includes(value)) return { label: '采购', type: 'primary' as const }
    if (['sold'].includes(value)) return { label: '销售', type: 'success' as const }
    if (['sale_return', 'sale_return_cancel', 'sale_cancel', 'sale_item_cancel'].includes(value)) return { label: '售后', type: 'warning' as const }
    if (['purchase_return', 'purchase_cancel'].includes(value)) return { label: '采退', type: 'danger' as const }
    if (['cost_adjust', 'refurbish'].includes(value)) return { label: '成本', type: 'warning' as const }
    return { label: '设备记录', type: 'info' as const }
}

function isTraceCostAdjust(node: any): boolean {
    return ['cost_adjust', 'refurbish'].includes(String(node?.action || '').toLowerCase())
}

function signedMoney(value: any): string {
    const amount = Number(value || 0)
    return `${amount > 0 ? '+' : ''}${money(amount)}`
}

async function loadWarehouses() {
    const res: any = await getErpWarehouseOptions()
    warehouses.value = Array.isArray(res?.data) ? res.data : []
}

async function loadCategories() {
    const res: any = await getErpGoodsCategoryTree()
    categoryTree.value = Array.isArray(res?.data) ? res.data : []
}

async function loadFinanceCategories() {
    const res: any = await getErpFinanceCategories()
    financeCategories.value = Array.isArray(res?.data) ? res.data : []
}

function openExpense(row: any) {
    expense.row = row
    expense.form = { cost_type: 'purchase_adjust', expense_type_key: '', party_id: 0, party_name: '', amount: 0, after_cost: Number(row.total_cost || 0), reason: '' }
    expense.visible = true
}

function onExpensePartyResolved(row: any) {
    expense.form.party_id = Number(row?.party_id || row?.id || 0)
    expense.form.party_name = row?.party_name || row?.name || ''
}

async function submitExpense() {
    if (!expense.row?.id) return
    const costType = expense.form.cost_type
    if (!expense.form.reason) return ElMessage.warning('请填写调整原因')
    if (Number(expense.form.after_cost) <= 0) return ElMessage.warning('调整后成本必须大于 0')
    const afterCost = Number(expense.form.after_cost || 0)
    const typeNames: Record<string, string> = { purchase_adjust: '供应商调价', internal_adjust: '内部成本修正' }
    const typeName = typeNames[costType] || '成本调整'
    const confirmed = await ElMessageBox.confirm(
        `确认执行「${typeName}」？设备成本将由 ${money(expense.row.total_cost)} 调整为 ${money(afterCost)}。整备费用必须在整备完工时按实际项目登记。`,
        '确认成本调整',
        { type: 'warning', confirmButtonText: '确认调整', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    expense.saving = true
    try {
        await adjustErpStockCost(expense.row.id, {
            cost: afterCost, cost_type: costType, expense_type_key: '',
            party_id: expense.form.party_id, party_name: expense.form.party_name, reason: expense.form.reason
        })
        ElMessage.success('设备成本已调整')
        expense.visible = false
        await loadList()
    } finally {
        expense.saving = false
    }
}

function onSelectionChange(rows: any[]) { selectedRows.value = rows }

function openSendRefurbish(row?: any) {
    const ids = row?.id ? [Number(row.id)] : selectedPendingIds.value
    if (!ids.length) return ElMessage.warning('请先选择待整备设备')
    sendRefurbish.assetIds = ids
    sendRefurbish.form = { tracking_mode: 'simple', provider_party_id: 0, remark: '' }
    sendRefurbish.visible = true
}

async function submitSendRefurbish() {
    if (sendRefurbish.form.tracking_mode === 'external' && !sendRefurbish.form.provider_party_id) return ElMessage.warning('请选择整备商')
    const confirmed = await ElMessageBox.confirm(`确认开始处理这 ${sendRefurbish.assetIds.length} 台设备？确认后设备进入“整备中”，暂不可销售。`, '确认开始整备', { type: 'warning' }).then(() => true).catch(() => false)
    if (!confirmed) return
    sendRefurbish.saving = true
    try {
        await sendErpStockRefurbish({ asset_ids: sendRefurbish.assetIds, ...sendRefurbish.form })
        ElMessage.success('设备已进入整备中')
        sendRefurbish.visible = false
        await loadList()
    } finally { sendRefurbish.saving = false }
}

function openCompleteRefurbish(row: any) {
    completeRefurbish.row = row
    completeRefurbish.form = { result: 'success', refurbish_items: [], warehouse_id: Number(row.warehouse_id || 0), location_id: Number(row.location_id || 0), voucher_urls: '', remark: '' }
    completeRefurbish.visible = true
}
function addRefurbishItem() { completeRefurbish.form.refurbish_items.push({ name: '', amount: 0, party_id: 0 }) }
function removeRefurbishItem(index: number) { completeRefurbish.form.refurbish_items.splice(index, 1) }
async function submitCompleteRefurbish() {
    const invalid = completeRefurbish.form.refurbish_items.some(item => !String(item.name || '').trim() || Number(item.amount || 0) <= 0 || !Number(item.party_id || 0))
    if (invalid) return ElMessage.warning('请完整填写每一项名称、金额和服务商')
    if (completeRefurbish.form.result !== 'success' && !completeRefurbish.form.remark) return ElMessage.warning('部分修复或失败时请填写原因')
    const amount = completeRefurbish.form.refurbish_items.reduce((sum, item) => sum + Number(item.amount || 0), 0)
    const confirmed = await ElMessageBox.confirm(`确认登记整备结果？本次新增设备成本 ${money(amount)}，有费用的项目会分别生成服务商应付；确认后不能普通撤销。`, '确认整备完工', { type: 'warning', confirmButtonText: '确认完工' }).then(() => true).catch(() => false)
    if (!confirmed) return
    completeRefurbish.saving = true
    try {
        await completeErpStockRefurbish(completeRefurbish.row.id, completeRefurbish.form)
        ElMessage.success('整备结果已登记，成本与应付已同步')
        completeRefurbish.visible = false
        await loadList()
    } finally { completeRefurbish.saving = false }
}

function buildSearchParams() {
    const [start_at, end_at] = Array.isArray(search.dateRange) ? search.dateRange : []
    return {
        ...search,
        start_at: start_at || '',
        end_at: end_at || '',
        dateRange: undefined
    }
}

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    Object.assign(search, { keyword: '', status: '', refurbish_status: '', sale_target: '', listing_status: '', turnover_level: '', warehouse_id: '', location_id: '', category_id: '', dateRange: [], stock_age_min: undefined, stock_age_max: undefined, min_cost: undefined, max_cost: undefined, min_price: undefined, max_price: undefined })
    activeTab.value = ''
    handleSearch()
}

function onSearchWarehouseChange() {
    search.location_id = ''
}

async function openDetail(row: any) {
    detail.visible = true
    detail.loading = true
    try {
        const res: any = await getErpStockInfo(row.id)
        detail.data = res?.data || null
        detailActivePanels.value = detail.data?.status === 'in_stock' ? ['purchase'] : ['sale']
    } finally {
        detail.loading = false
    }
}

function openFlow(row: any) {
    flow.row = row
    flow.form = {
        refurbish_status: row.refurbish_status || 'none',
        sale_target: row.sale_target || 'unset',
        listing_status: row.listing_status || 'none',
        estimate_sale_price: Number(row.estimate_sale_price || 0),
        retail_price: Number(row.retail_price || 0),
        image_urls: row.image_urls || '',
        quality_remark: row.quality_remark || '',
        remark_public: row.remark_public || '',
        remark_internal: row.remark_internal || '',
    }
    flow.visible = true
}

function onTargetChange(value: string) {
    if (value === 'peer') flow.form.listing_status = 'none'
    if (value === 'mall' && flow.form.listing_status === 'none') {
        flow.form.listing_status = flow.form.image_urls ? (Number(flow.form.estimate_sale_price || 0) > 0 ? 'ready' : 'need_price') : 'need_photo'
    }
}

async function submitFlow() {
    if (!flow.row?.id) return
    const confirmed = await ElMessageBox.confirm(
        `确认更新设备「${flow.row.model || flow.row.imei || flow.row.asset_no || '-'}」的业务流转：${refurbishMeta(flow.form.refurbish_status).label}、${targetMeta(flow.form.sale_target).label}、${listingMeta(flow.form.listing_status).label}。该变更会影响仓管和销售后续操作，并保留设备流水。`,
        '确认更新设备流转',
        { type: 'warning', confirmButtonText: '确认更新', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    flow.saving = true
    try {
        await updateErpStockFlow(flow.row.id, { ...flow.form, refurbish_status: ['none', 'pending'].includes(flow.row?.refurbish_status) ? flow.form.refurbish_status : '' })
        ElMessage.success('设备流转已更新')
        flow.visible = false
        await loadList()
        if (detail.visible && detail.data?.id === flow.row.id) await openDetail(flow.row)
    } finally {
        flow.saving = false
    }
}

async function syncListing(row: any) {
    const confirmed = await ElMessageBox.confirm(
        `确认将设备「${row.model || row.imei || row.asset_no || '-'}」同步到拍照定价？重复同步只更新关联信息，不会重复建档。`,
        '同步拍照定价',
        { type: 'warning', confirmButtonText: '确认同步', cancelButtonText: '取消' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    const res: any = await syncErpStockListing(row.id)
    const syncResult = res?.data || {}
    if (syncResult.ok === false) return ElMessage.error(syncResult.message || '同步失败，请稍后重试')
    ElMessage.success('已提交拍照定价同步')
    await loadList()
}

function assetStatusMeta(status: string) {
    const map: any = {
        in_stock: { label: '库存中', type: 'success' },
        sold: { label: '已销售出库', type: 'success' },
        returned: { label: '已采购退货', type: 'warning' },
        void: { label: '作废', type: 'danger' }
    }
    return map[String(status || '').toLowerCase()] || { label: status ? '其他状态' : '-', type: 'info' }
}

function outboundStatusMeta(status: string) {
    const map: any = {
        sold: { label: '已销售出库', type: 'success' },
        returned: { label: '已销售退货', type: 'warning' },
        void: { label: '已取消销售', type: 'info' },
    }
    return map[status] || { label: status || '历史出库', type: 'info' }
}

function hasEffectiveOutbound(row: any) {
    return Number(row?.outbound_sale_item_id || 0) > 0 && row?.outbound_status === 'sold'
}

function outboundToneClass(status: string) {
    if (status === 'sold') return 'lifecycle-cell--success'
    if (status === 'returned') return 'lifecycle-cell--warning'
    return 'lifecycle-cell--muted'
}

function stockExitToneClass(status: string) {
    if (status === 'sold') return 'stock-exit-state--success'
    if (status === 'returned') return 'stock-exit-state--warning'
    return 'stock-exit-state--muted'
}

function stockExitDescription(row: any) {
    const location = [row.warehouse_name, row.location_name].filter(Boolean).join(' / ')
    if (row.status === 'sold') return `库存已扣减${location ? ` · 原位置 ${location}` : ''}`
    if (row.status === 'returned') return '设备已退还原供应商，不再计入库存'
    return '该设备已退出有效库存'
}

function stockRowClassName({ row }: { row: any }) {
    return `stock-row--${row?.status || 'unknown'}`
}

function assetSubTitle(row: any) {
    return [
        row.spec || '',
        row.imei ? `IMEI ${row.imei}` : '',
        row.sn ? `SN ${row.sn}` : '',
        row.asset_no ? `资产号 ${row.asset_no}` : ''
    ].filter(Boolean).join(' · ') || '-'
}

function detailMetrics(row: any) {
    if ((row?.status || '') === 'sold') {
        return [
            { label: '原成交价', value: Number(row.sale_price || 0) ? money(row.sale_price) : '-' },
            { label: '售后补差', value: Number(row.sale_compensation_amount || 0) ? `-${money(row.sale_compensation_amount)}` : money(0), className: Number(row.sale_compensation_amount || 0) ? 'text-orange-500' : '' },
            { label: '实际销售收入', value: money(row.net_sale_amount), className: 'text-blue-600' },
            { label: '总成本', value: money(row.total_cost) },
            { label: '实际毛利', value: money(row.profit), className: Number(row.profit || 0) >= 0 ? 'text-green-600' : 'text-red-600' }
        ]
    }
    if ((row?.status || '') === 'in_stock') {
        return [
            { label: '当前总成本', value: money(row.total_cost) },
            { label: '预计卖价', value: Number(row.estimate_sale_price || 0) ? money(row.estimate_sale_price) : '-' },
            { label: '库龄', value: row.stock_in_at ? `${stockAgeDays(row.stock_in_at)} 天` : '-' },
            { label: '销售去向', value: targetMeta(row.sale_target).label }
        ]
    }
    return [
        { label: '采购成本', value: money(row.purchase_cost) },
        { label: '调整成本', value: money(row.adjust_cost) },
        { label: '整备成本', value: money(row.refurbish_cost) },
        { label: '当前总成本', value: money(row.total_cost) }
    ]
}

function refurbishMeta(status: string) {
    const map: any = {
        none: { label: '无需整备', type: 'info' },
        pending: { label: '待整备', type: 'warning' },
        processing: { label: '整备中', type: 'danger' },
        done: { label: '整备完成', type: 'success' },
        failed: { label: '整备异常', type: 'danger' }
    }
    return map[status || 'none'] || { label: status || '-', type: 'info' }
}

function targetMeta(status: string) {
    const map: any = {
        unset: { label: '去向未定', type: 'info' },
        peer: { label: '卖同行', type: 'success' },
        mall: { label: '上商城', type: 'primary' }
    }
    return map[status || 'unset'] || { label: status || '-', type: 'info' }
}

function listingMeta(status: string) {
    const map: any = {
        none: { label: '不上架', type: 'info' },
        need_photo: { label: '待拍照', type: 'warning' },
        need_price: { label: '待定价', type: 'warning' },
        ready: { label: '可上架', type: 'success' },
        listed: { label: '已上架', type: 'primary' }
    }
    return map[status || 'none'] || { label: status || '-', type: 'info' }
}

function listingSyncMeta(status: string) {
    const map: any = {
        done: { type: 'success' }, processed: { type: 'success' },
        failed: { type: 'danger' }, processing: { type: 'warning' },
        pending: { type: 'warning' }, not_synced: { type: 'info' }
    }
    return map[status || 'not_synced'] || { type: 'info' }
}

function financeStatusLabel(status: string) {
    const map: any = { pending: '待处理', partial: '部分结清', settled: '已结清' }
    return map[status] || status || '-'
}

function financeStatusMeta(status: string) {
    const map: any = {
        pending: { label: '未结清', type: 'warning' },
        partial: { label: '部分结清', type: 'primary' },
        settled: { label: '已结清', type: 'success' },
        void: { label: '已取消', type: 'info' },
    }
    return map[status] || { label: '未发生', type: 'info' }
}

function bizTypeLabel(type: string) {
    const map: any = {
        purchase: '采购应付', purchase_cancel: '采购撤销冲回', purchase_return: '采购退货冲回', purchase_return_loss: '采购退货损失',
        sale: '销售应收', sale_cancel: '整单销售撤销', sale_item_cancel: '单台销售撤销', sale_return: '销售退货冲回',
        sale_compensation: '售后补差应付', adjust: '成本调整', refurbish: '整备成本',
        payment: '实际付款', receipt: '实际收款', offset: '往来折账'
    }
    return map[String(type || '').toLowerCase()] || '账务调整'
}

function sourceTypeLabel(type: string) {
    const map: any = {
        purchase: '采购单', purchase_asset: '采购设备', sale: '销售单', payable: '应付款', receivable: '应收款',
        purchase_cancel: '采购撤销', purchase_return: '采购退货', sale_cancel: '整单销售撤销', sale_item_cancel: '单台销售撤销',
        sale_return: '销售退货', sale_compensation: '售后补差', offset: '往来折账', asset: '设备档案', refurbish: '设备整备'
    }
    return map[String(type || '').toLowerCase()] || '关联业务单据'
}

function assetActionLabel(action: string) {
    const map: any = {
        inbound: '采购入库',
        sold: '销售出库',
        sale_cancel: '整单销售撤销',
        sale_item_cancel: '单台销售撤销',
        sale_return: '销售退货回库',
        purchase_cancel: '采购撤销',
        purchase_return: '采购退货出库',
        cost_adjust: '成本调整',
        flow: '流转设置',
        return: '退货',
        transfer: '调拨',
        refurbish: '整备',
        void: '作废'
    }
    return map[String(action || '').toLowerCase()] || '库存调整'
}

function accountDirectionMeta(row: any) {
    const direction = String(row?.direction || '').toLowerCase()
    const type = String(row?.biz_type || '').toLowerCase()
    if (type === 'purchase') {
        return direction === 'increase' ? { label: '应付增加', type: 'danger' } : { label: '应付减少', type: 'success' }
    }
    if (type === 'sale') {
        return direction === 'increase' ? { label: '应收增加', type: 'success' } : { label: '应收减少', type: 'danger' }
    }
    if (['sale_cancel', 'sale_item_cancel', 'sale_return'].includes(type)) return { label: '销售应收已冲回', type: 'success' }
    if (['purchase_cancel', 'purchase_return'].includes(type)) return { label: '采购应付已冲回', type: 'success' }
    if (type === 'purchase_return_loss') return { label: '退货损失增加', type: 'danger' }
    if (type === 'adjust') {
        return direction === 'increase' ? { label: '成本增加', type: 'danger' } : { label: '成本减少', type: 'success' }
    }
    if (type === 'refurbish') return { label: '整备成本增加', type: 'danger' }
    if (type === 'sale_compensation') return direction === 'increase' ? { label: '公司待付客户增加', type: 'danger' } : { label: '公司待付客户减少', type: 'success' }
    if (type === 'payment') return { label: '公司已付款', type: 'danger' }
    if (type === 'receipt') return { label: '公司已收款', type: 'success' }
    if (type === 'offset') return { label: '应收应付已互抵', type: 'warning' }
    return direction === 'increase' ? { label: '增加', type: 'primary' } : { label: '减少', type: 'info' }
}

function accountLedgerRemark(row: any) {
    const type = String(row?.biz_type || '').toLowerCase()
    const remark = String(row?.remark || '').trim()
    if (type === 'sale_compensation' && (!remark || remark === '售后补差')) return '公司应向客户支付售后补差款；设备继续由客户持有，销售毛利已同步减少。'
    if (row?._merged_compensation) return '售后补差应付与实际付款已合并展示；公司已经向客户支付补差款，本次只计一笔。'
    if (type === 'sale_cancel' && !remark) return '整张销售单已撤销，该设备回到库存，原销售应收已经冲回。'
    if (type === 'sale_item_cancel' && !remark) return '该设备销售已撤销并回到库存，设备对应的销售应收已经冲回。'
    if (type === 'sale_return' && !remark) return '客户退回设备，原销售应收已经冲回；已收款部分按退款应付另行处理。'
    if (type === 'offset' && !remark) return '应收与应付已完成折账核销，本次不产生真实资金收付。'
    if (type === 'payment' && !remark) return '公司已完成付款；付款账户和资金凭证请在财务结算明细中查看。'
    if (type === 'receipt' && !remark) return '公司已确认收款；收款账户和资金凭证请在财务结算明细中查看。'
    return remark || '-'
}

function accountTimelineRows(rows: any[] = []) {
    const source = (rows || []).map((row: any) => ({ ...row }))
    const compensations = source.filter((row: any) => String(row.biz_type || '').toLowerCase() === 'sale_compensation')
    const mergedCompensationIds = new Set<number>()
    source.forEach((row: any) => {
        if (String(row.biz_type || '').toLowerCase() !== 'payment') return
        const paymentAt = Number(row.occurred_at || row.create_at || 0)
        const match = compensations
            .filter((comp: any) => {
                const hasLifecycle = Boolean(row.lifecycle_key || comp.lifecycle_key)
                const sameLifecycle = hasLifecycle ? Boolean(row.lifecycle_key && comp.lifecycle_key && row.lifecycle_key === comp.lifecycle_key) : true
                return !mergedCompensationIds.has(Number(comp.id)) && sameLifecycle && Number(comp.asset_id || 0) === Number(row.asset_id || 0) && Math.abs(Number(comp.amount || 0) - Number(row.amount || 0)) < 0.001 && Number(comp.occurred_at || comp.create_at || 0) <= paymentAt
            })
            .sort((a: any, b: any) => Number(b.occurred_at || b.create_at || 0) - Number(a.occurred_at || a.create_at || 0))[0]
        if (!match) return
        mergedCompensationIds.add(Number(match.id))
        row._merged_compensation = true
        row._display_source_no = match.source_no || row.source_no
        row.settlement_methods = Array.from(new Set([...(row.settlement_methods || []), ...(match.settlement_methods || []), '实际付款']))
    })
    return source.filter((row: any) => !mergedCompensationIds.has(Number(row.id)))
}

function timelineTypeLabel(row: any) {
    if (row?._merged_compensation) return '售后补差付款'
    const type = String(row?.biz_type || '').toLowerCase()
    if (type === 'sale_compensation' && (row.settlement_methods || []).includes('折账结清')) return '售后补差折账'
    return row?.biz_type_text || bizTypeLabel(type)
}

function timelineDirectionMeta(row: any) {
    if (row?._merged_compensation) return { label: '公司已支付补差', type: 'danger' }
    if (String(row?.biz_type || '').toLowerCase() === 'offset') return { label: '应收应付互抵', type: 'warning' }
    return accountDirectionMeta(row)
}

function timelineSettlementText(row: any) {
    const methods = Array.from(new Set((row?.settlement_methods || []).filter(Boolean)))
    if (methods.length) return methods.join('、')
    if (row?.business_state_text) return row.business_state_text
    const type = String(row?.biz_type || '').toLowerCase()
    if (['payment', 'receipt', 'offset'].includes(type)) return type === 'offset' ? '折账结清' : type === 'payment' ? '实际付款' : '实际收款'
    if (type === 'sale') return '待客户付款'
    if (['purchase', 'refurbish', 'sale_compensation'].includes(type)) return '待财务付款'
    return '账务已记录'
}

function money(value: any) {
    return `¥${Number(value || 0).toFixed(2)}`
}

function formatTime(value: any) {
    const time = Number(value || 0)
    if (!time) return '-'
    return new Date(time * 1000).toLocaleString()
}
</script>

<style scoped>
.summary-tile {
    border-radius: 8px;
    background: #f8fafc;
    padding: 14px 16px;
}
.summary-label {
    color: #64748b;
    font-size: 13px;
}
.summary-value {
    margin-top: 6px;
    color: #111827;
    font-size: 22px;
    font-weight: 650;
}
.lifecycle-cell { display: flex; align-items: flex-start; flex-direction: column; gap: 4px; border-radius: 8px; padding: 9px 11px; }
.lifecycle-cell--inbound { border-left: 3px solid #60a5fa; background: #f4f8ff; }
.lifecycle-cell--outbound { border-left: 3px solid #cbd5e1; }
.lifecycle-cell--success { border-left-color: #22c55e; background: #f2fbf5; }
.lifecycle-cell--warning { border-left-color: #f59e0b; background: #fffbeb; }
.lifecycle-cell--muted { border-left-color: #cbd5e1; background: #f8fafc; }
.lifecycle-cell__title { color: #1f2937; font-weight: 600; }
.lifecycle-cell__line { color: #64748b; font-size: 12px; line-height: 18px; }
.lifecycle-cell__time { color: #94a3b8; font-size: 11px; }
.lifecycle-empty { display: flex; align-items: flex-start; gap: 9px; border: 1px dashed #dbe3ee; border-radius: 7px; background: #f8fafc; padding: 10px 11px; }
.lifecycle-empty__dot { width: 7px; height: 7px; flex: 0 0 auto; margin-top: 5px; border-radius: 50%; background: #cbd5e1; }
.lifecycle-empty__title { color: #64748b; font-size: 13px; font-weight: 600; }
.lifecycle-empty__desc { margin-top: 3px; color: #94a3b8; font-size: 11px; line-height: 17px; }
.age-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 62px; border-radius: 999px; padding: 4px 8px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.age-pill--success { color: #15803d; background: #dcfce7; }
.age-pill--neutral { color: #475569; background: #f1f5f9; }
.age-pill--warning { color: #b45309; background: #fef3c7; }
.age-pill--danger { color: #b91c1c; background: #fee2e2; }
.age-pill--primary { color: #1d4ed8; background: #dbeafe; }
.age-pill--muted { color: #94a3b8; background: #f1f5f9; }
.stock-exit-state { display: flex; align-items: flex-start; gap: 9px; border-radius: 8px; padding: 10px 12px; }
.stock-exit-state__dot { width: 8px; height: 8px; flex: 0 0 auto; margin-top: 5px; border-radius: 50%; background: currentColor; }
.stock-exit-state__title { font-size: 13px; font-weight: 650; }
.stock-exit-state__desc { margin-top: 3px; font-size: 11px; line-height: 17px; opacity: .78; }
.stock-exit-state--success { color: #15803d; background: #f0fdf4; }
.stock-exit-state--warning { color: #b45309; background: #fffbeb; }
.stock-exit-state--muted { color: #64748b; background: #f8fafc; }
.settlement-line { display: grid; grid-template-columns: 52px max-content; align-items: center; gap: 5px 7px; border-radius: 7px; background: #f8fafc; padding: 8px 9px; }
.settlement-line + .settlement-line { margin-top: 7px; }
.settlement-line__label { color: #475569; font-size: 12px; font-weight: 650; }
.settlement-line__amount { grid-column: 1 / -1; color: #94a3b8; font-size: 11px; white-space: nowrap; }
:deep(.el-table__body tr.stock-row--sold > td.el-table__cell) { background: #fbfefc; }
:deep(.el-table__body tr.stock-row--returned > td.el-table__cell) { background: #fffdf7; }
:deep(.el-table__body tr.stock-row--void > td.el-table__cell) { color: #94a3b8; background: #fafafa; }
:deep(.el-table__body tr:hover > td.el-table__cell) { background: #f3f7ff !important; }
.section-title {
    margin-bottom: 12px;
    border-left: 3px solid var(--el-color-primary);
    padding-left: 10px;
    color: #111827;
    font-size: 15px;
    font-weight: 650;
}
</style>
