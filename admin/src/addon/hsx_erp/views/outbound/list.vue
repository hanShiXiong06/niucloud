<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">出库管理 · 同行出货</div>
                    <div class="mt-1 text-sm text-gray-500">
                        把在库设备卖给同行并出库。可现结(出库即填价)或先出库、价格未来回填。同行销售出库会按往来单位生成应收。
                    </div>
                </div>
                <el-button v-permission="'hsx_erp_outbound_create'" type="primary" @click="openCreate">新建出库</el-button>
            </div>

            <!-- 出库类型分栏: 区分商城订单 / 同行订单, 更清晰 -->
            <el-tabs v-model="search.outbound_type" class="mt-3 erp-outbound-tabs" @tab-change="loadList">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="商城销售" name="mall_sale" />
                <el-tab-pane label="同行销售" name="peer_sale" />
                <el-tab-pane label="报废出库" name="scrap" />
                <el-tab-pane label="其他出库" name="other" />
            </el-tabs>

            <el-form :inline="true" @submit.prevent>
                <el-form-item label="单据状态">
                    <el-select v-model="search.biz_status" placeholder="全部" clearable @change="loadList" class="!w-[120px]">
                        <el-option label="待回填" value="pending" />
                        <el-option label="已定价" value="filled" />
                        <el-option label="已退回" value="void" />
                    </el-select>
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" placeholder="出库单号 / 卖给谁(往来单位)" clearable class="!w-[200px]" @keyup.enter="loadList" />
                </el-form-item>
                <el-form-item label="IMEI">
                    <el-input v-model.trim="search.imei" placeholder="串号精确查询" clearable class="!w-[180px]" @keyup.enter="loadList" @clear="loadList" />
                </el-form-item>
                <el-form-item label="出库时间">
                    <el-date-picker v-model="search.dateRange" type="daterange" value-format="X" range-separator="至"
                        start-placeholder="开始" end-placeholder="结束" style="width: 230px" @change="loadList" />
                </el-form-item>
                <el-form-item label="金额">
                    <el-input v-model="search.amount_min" placeholder="最低" clearable style="width: 90px" @keyup.enter="loadList" />
                    <span class="mx-1 text-gray-400">~</span>
                    <el-input v-model="search.amount_max" placeholder="最高" clearable style="width: 90px" @keyup.enter="loadList" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList" :loading="table.loading">查询</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table class="mt-4" :data="table.data" v-loading="table.loading" size="large" empty-text="暂无出库单" @sort-change="onSort">
                <el-table-column prop="outbound_no" label="出库单号" min-width="170" />
                <el-table-column prop="type_text" label="类型" width="100" />
                <el-table-column label="往来单位(对接人/单位)" min-width="180" show-overflow-tooltip>
                    <template #default="{ row }">
                        <div v-if="row.entity_name" class="cursor-pointer font-medium text-[var(--el-color-primary)]" @click="openEntity(row.entity_id)">{{ row.entity_name }}</div>
                        <div v-else class="text-xs text-gray-400">未归属主体</div>
                        <div class="text-xs text-gray-500">{{ row.counterparty_name || (row.counterparty_id ? '#' + row.counterparty_id : '-') }}<span v-if="row.counterparty_mobile"> · {{ row.counterparty_mobile }}</span></div>
                    </template>
                </el-table-column>
                <el-table-column prop="qty" label="台数" width="100" align="center" sortable="custom" />
                <el-table-column label="出货总额" width="120" align="right" prop="total_amount" sortable="custom">
                    <template #default="{ row }">{{ money(row.total_amount) }}</template>
                </el-table-column>
                <el-table-column label="结算/状态" width="150" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_void" type="info" effect="light">已退回</el-tag>
                        <template v-else>
                            <el-tag :type="(row.settle_mode === 'now' || row.collected) ? 'success' : 'warning'" effect="light">{{ row.settle_mode === 'now' ? '现结·已售' : (row.collected ? '挂单·已收款' : '挂单·已出货') }}</el-tag>
                            <div v-if="row.settle_mode === 'later'" class="mt-0.5 text-xs" :class="row.collected ? 'text-green-600' : (row.price_status === 'pending' ? 'text-orange-500' : 'text-gray-400')">{{ row.price_status === 'pending' ? '待回填价' : (row.collected ? '已收款·完成' : '价格已定·待收款') }}</div>
                        </template>
                    </template>
                </el-table-column>
                <el-table-column label="快递单号" min-width="140" show-overflow-tooltip>
                    <template #default="{ row }"><span :class="row.express_no ? '' : 'text-gray-300'">{{ row.express_no || '—' }}</span></template>
                </el-table-column>
                <el-table-column label="出库时间" width="170" prop="out_at" sortable="custom">
                    <template #default="{ row }">{{ row.out_at ? formatTime(row.out_at) : '-' }}</template>
                </el-table-column>
                <el-table-column label="操作" width="200" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openInfo(row)">详情</el-button>
                        <el-button v-if="canProcessPending(row)" v-permission="'hsx_erp_outbound_fill_price'" type="warning" link @click="openPendingProcess(row)">处理挂单</el-button>
                        <el-button v-if="row.can_cancel" v-permission="'hsx_erp_outbound_cancel'" type="danger" link @click="doCancel(row)">整单退回</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination layout="total, prev, pager, next" :total="table.total" :page-size="table.limit"
                    :current-page="table.page" @current-change="onPageChange" />
            </div>
        </el-card>

        <!-- 新建出库 -->
        <el-dialog v-model="createVisible" title="新建出库" width="900px" @closed="resetCreate">
            <el-form :model="form" label-width="110px">
                <el-form-item label="出库类型">
                    <el-radio-group v-model="form.outbound_type" @change="onTypeChange">
                        <el-radio label="peer_sale">同行销售</el-radio>
                        <el-radio label="scrap">报废出库</el-radio>
                        <el-radio label="other">其他出库</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="form.outbound_type === 'peer_sale'" label="对接人/交易人">
                    <counterparty-select v-model="form.counterparty_id" value-field="member_id" role-type="customer"
                        placeholder="按姓名 / 手机检索交易人" class="!w-[420px]" @resolved="onContactResolved" />
                    <div class="mt-1 text-xs text-gray-400">出库按"交易人(人)"记账(与回收同口径,可折账)。检索选人即可,没建档的人点"新建"一步搞定,无往来主体会自动补建。</div>
                </el-form-item>
                <el-form-item v-if="form.outbound_type === 'peer_sale'" label="结算方式">
                    <el-radio-group v-model="form.settle_mode">
                        <el-radio label="now">现结(出库即收款·设备已售下架)</el-radio>
                        <el-radio label="later">挂单(暂不收款·设备锁定可退回)</el-radio>
                    </el-radio-group>
                    <div class="mt-1 text-xs text-gray-400">
                        现结=当场收钱,款进所选户头、设备直接售出;挂单=先把设备锁定给买家,款未到,可回填价/退回。
                    </div>
                </el-form-item>
                <el-form-item v-if="form.outbound_type === 'peer_sale' && form.settle_mode === 'now'" label="收款户头" required>
                    <el-select v-model="form.capital_account_id" filterable placeholder="款项收入哪个账户" class="!w-[320px]">
                        <el-option v-for="a in payAccounts" :key="a.id" :label="`${a.account_name}（余额 ${money(a.balance)}）`" :value="a.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="选择设备">
                    <div class="w-full">
                        <div class="mb-2 flex items-center gap-2">
                            <el-select v-model="assetWarehouseId" placeholder="按仓库筛选" clearable class="!w-[200px]" @change="reloadAssets">
                                <el-option v-for="w in warehouseOptions" :key="w.id"
                                    :label="w.warehouse_name + '（' + businessTypeLabel(w.business_type) + '）'" :value="w.id" />
                            </el-select>
                            <el-input v-model.trim="assetKeyword" placeholder="资产号/IMEI/型号" clearable class="!w-[200px]" @keyup.enter="reloadAssets" />
                            <el-button @click="reloadAssets" :loading="assetLoading">筛选</el-button>
                            <span class="text-xs text-gray-400">先按仓库性质筛选，再勾选要出库的设备</span>
                        </div>
                        <el-table :data="availableAssets" size="small" max-height="300" row-key="id" @selection-change="onAssetSelect"
                            v-loading="assetLoading" empty-text="无可出库设备（试试切换仓库）">
                            <el-table-column type="selection" width="40" reserve-selection />
                            <el-table-column prop="asset_no" label="资产号" min-width="120" show-overflow-tooltip />
                            <el-table-column label="所在仓库" min-width="130" show-overflow-tooltip>
                                <template #default="{ row }">{{ warehouseName(row.warehouse_id) }}</template>
                            </el-table-column>
                            <el-table-column prop="model" label="型号" min-width="120" show-overflow-tooltip />
                            <el-table-column prop="imei" label="IMEI" min-width="120" show-overflow-tooltip />
                            <el-table-column v-if="showPrice" label="出货价" width="140">
                                <template #default="{ row }">
                                    <el-input-number v-model="priceInput[row.id]" :min="0" :controls="false" size="small" class="w-28" />
                                </template>
                            </el-table-column>
                            <el-table-column v-if="showConsignorCol" label="应付寄卖人" width="150">
                                <template #default="{ row }">
                                    <el-input-number v-if="isConsign(row)" v-model="consignorInput[row.id]" :min="0" :precision="2" :controls="false" size="small" class="w-28" />
                                    <span v-else class="text-xs text-gray-300">非代卖</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-xs text-gray-400">已选 {{ selectedAssets.length }} 台（勾选跨页保留）</span>
                            <el-pagination layout="total, prev, pager, next" :total="assetTotal" :page-size="assetLimit" :current-page="assetPage" @current-change="onAssetPage" />
                        </div>
                    </div>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="form.remark" type="textarea" :rows="2" maxlength="200" show-word-limit />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" :disabled="selectedAssets.length === 0" @click="doCreate">确认出库</el-button>
            </template>
        </el-dialog>

        <!-- 处理挂单：留下的填价格，退回的勾退回 -->
        <el-dialog v-model="pendingProcess.visible" title="处理挂单" width="780px" @closed="resetPendingProcess">
            <!-- 概览:客户/单号 + 留下/退回 对比 -->
            <div class="pp-summary">
                <div class="pp-meta">客户 <b>{{ pendingProcess.buyer || '-' }}</b><span class="pp-sep">·</span>单号 <b>{{ pendingProcess.outboundNo || '-' }}</b></div>
                <div class="pp-chips">
                    <span class="pp-chip keep">✓ 留下 {{ keptProcessCount }} 台 · 应收 ¥{{ keptTotal.toFixed(2) }}</span>
                    <span class="pp-chip back" :class="{ on: returnProcessCount > 0 }">↩ 退回 {{ returnProcessCount }} 台</span>
                </div>
            </div>

            <!-- 第一步:逐台决定 留下 / 退回 -->
            <div class="pp-step"><span class="pp-step-no">1</span>逐台决定:留下的填成交价,不要的点退回</div>
            <el-table :data="pendingProcess.items" size="small" :row-class-name="processRowClass" empty-text="无明细">
                <el-table-column label="设备" min-width="180" show-overflow-tooltip>
                    <template #default="{ row }">
                        <div :class="(row.is_returned || row.return_selected) ? 'line-through text-gray-400' : 'font-medium'">{{ row.model }}</div>
                        <div class="text-xs text-gray-400">IMEI {{ row.imei || '-' }}<span v-if="row.asset_no"> · {{ row.asset_no }}</span></div>
                    </template>
                </el-table-column>
                <el-table-column label="成交价" width="150" align="right">
                    <template #default="{ row }">
                        <el-input-number v-if="!row.is_returned && !row.return_selected" v-model="row.sale_price" :min="0" :precision="2" :controls="false" size="small" class="w-28" placeholder="填价" />
                        <span v-else class="text-gray-300">—</span>
                    </template>
                </el-table-column>
                <el-table-column label="处理" width="180" align="center">
                    <template #default="{ row }">
                        <el-radio-group v-if="row.can_return && !row.is_returned" v-model="row.return_selected" size="small">
                            <el-radio-button :label="false">留下</el-radio-button>
                            <el-radio-button :label="true">退回</el-radio-button>
                        </el-radio-group>
                        <el-tag v-else-if="row.is_returned" size="small" type="info" effect="plain">已退回</el-tag>
                        <el-tag v-else size="small" type="success" effect="plain">留下(不可退)</el-tag>
                    </template>
                </el-table-column>
            </el-table>
            <el-form v-if="returnProcessCount > 0" class="mt-3" label-width="84px">
                <el-form-item label="退回原因">
                    <el-input v-model="pendingProcess.reason" placeholder="如：客户只留其中1台 / 货不对板" maxlength="100" show-word-limit />
                </el-form-item>
            </el-form>

            <!-- 留下的设备:回填价后挂「待结应收」,收款一律到财务中心。出库管理不收款(职责分明)。 -->
            <div v-if="keptProcessCount > 0" class="pp-keep-note">
                留下的 <b>{{ keptProcessCount }}</b> 台将挂「待结应收 ¥{{ keptTotal.toFixed(2) }}」,收款请到 <b>财务中心</b> 操作。出库管理只负责离库/回填,不收款。
            </div>

            <template #footer>
                <div class="pp-footer">
                    <div class="pp-footer-hint">
                        <template v-if="returnProcessCount > 0">将退回 {{ returnProcessCount }} 台 · 留下 {{ keptProcessCount }} 台挂应收</template>
                        <template v-else>留下 {{ keptProcessCount }} 台挂应收</template>
                    </div>
                    <div>
                        <el-button @click="pendingProcess.visible = false">取消</el-button>
                        <el-button type="primary" :loading="pendingProcess.submitting" @click="doPendingProcess">确认处理</el-button>
                    </div>
                </div>
            </template>
        </el-dialog>

        <!-- 详情抽屉 -->
        <el-drawer v-model="infoVisible" title="出库单详情" size="820px">
            <div v-if="infoData" v-loading="infoLoading">
                <!-- 概览 -->
                <div class="rounded-lg bg-gray-50 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                        <span class="font-medium">{{ infoData.outbound_no }}</span>
                        <el-tag size="small" effect="plain">{{ infoData.type_text }}</el-tag>
                        <el-tag size="small" :type="infoData.is_void ? 'info' : (infoData.collected ? 'success' : 'warning')" effect="light">
                            {{ infoData.is_void ? '已退回' : (infoData.settle_mode === 'now' ? '现结·已售' : (infoData.collected ? '挂单·已收款' : '挂单·待收款')) }}
                        </el-tag>
                        <span class="text-sm text-gray-500">{{ formatTime(infoData.out_at) }} · {{ infoData.operator_name || '-' }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-gray-600">
                        <span>对接人：<b>{{ infoData.buyer_name || '-' }}</b><span v-if="infoData.buyer_mobile" class="text-gray-400"> · {{ infoData.buyer_mobile }}</span></span>
                        <span>对接单位：
                            <b v-if="infoData.buyer_entity" class="cursor-pointer text-[var(--el-color-primary)]" @click="openEntity(infoData.buyer_entity_id)">{{ infoData.buyer_entity }}</b>
                            <span v-else class="text-gray-400">未归属主体</span>
                        </span>
                    </div>
                    <div v-if="infoData.remark" class="mt-1 text-sm text-gray-500">备注：{{ infoData.remark }}</div>
                    <div class="mt-3 grid grid-cols-4 gap-3 text-center">
                        <div><div class="text-xs text-gray-500">台数</div><div class="mt-1 font-semibold">{{ infoData.qty }}</div></div>
                        <div><div class="text-xs text-gray-500">总售价</div><div class="mt-1 font-semibold text-blue-600">{{ money(infoData.total_amount) }}</div></div>
                        <div><div class="text-xs text-gray-500">总成本</div><div class="mt-1 font-semibold text-orange-600">{{ money(infoData.total_cost) }}</div></div>
                        <div><div class="text-xs text-gray-500">总毛利</div><div class="mt-1 font-semibold" :class="infoData.total_profit >= 0 ? 'text-green-600' : 'text-red-600'">{{ money(infoData.total_profit) }}</div></div>
                    </div>
                </div>

                <!-- 设备明细 -->
                <div class="mt-4 mb-2 flex items-center justify-between">
                    <span class="font-medium">设备明细（{{ (infoData.items || []).length }} 台）</span>
                    <el-button v-if="canProcessPending(infoData)" v-permission="'hsx_erp_outbound_fill_price'" size="small" type="warning" @click="openPendingProcessFromDrawer(infoData)">处理挂单</el-button>
                </div>
                <el-table :data="infoData.items || []" size="small" empty-text="无明细">
                    <el-table-column label="设备" min-width="150" show-overflow-tooltip>
                        <template #default="{ row }">
                            <div :class="row.is_returned ? 'line-through text-gray-400' : ''">{{ row.model }}</div>
                            <div class="text-xs text-gray-400">IMEI {{ row.imei || '-' }}<span v-if="row.asset_no"> · {{ row.asset_no }}</span></div>
                        </template>
                    </el-table-column>
                    <el-table-column label="成本" width="90" align="right"><template #default="{ row }">{{ money(row.cost) }}</template></el-table-column>
                    <el-table-column label="出货价" width="90" align="right"><template #default="{ row }">{{ money(row.sale_price) }}</template></el-table-column>
                    <el-table-column label="毛利" width="90" align="right"><template #default="{ row }"><span :class="row.profit >= 0 ? 'text-green-600' : 'text-red-600'">{{ money(row.profit) }}</span></template></el-table-column>
                    <el-table-column label="状态" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_returned" size="small" type="info" effect="light">已退回</el-tag>
                            <el-tag v-else size="small" effect="light">{{ row.inventory_status_text || '-' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="90" align="center">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="openTrace(row)">全链路</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 财务关联 -->
                <div class="mt-4 mb-2 font-medium">财务关联</div>
                <div class="rounded-lg border border-gray-100 px-4 py-3 text-sm">
                    <div class="flex flex-wrap gap-x-6 gap-y-1">
                        <span>已收：<b class="text-green-600">{{ money(infoData.received) }}</b></span>
                        <span>未收：<b :class="infoData.unreceived > 0 ? 'text-orange-600' : 'text-gray-400'">{{ money(infoData.unreceived) }}</b></span>
                    </div>
                    <div v-if="(infoData.receivables || []).length" class="mt-2">
                        <div class="text-xs text-gray-500">应收</div>
                        <div v-for="r in infoData.receivables" :key="r.id" class="mt-1 flex flex-wrap gap-x-4 text-gray-600">
                            <span>{{ r.source_no }}</span><span>¥{{ Number(r.amount).toFixed(2) }}</span>
                            <span>已结 ¥{{ Number(r.settled_amount).toFixed(2) }}</span>
                            <el-tag size="small" :type="r.status === 'settled' ? 'success' : (r.status === 'partial' ? 'warning' : 'danger')" effect="light">{{ r.status_text }}</el-tag>
                        </div>
                    </div>
                    <div v-if="(infoData.settlements || []).length" class="mt-2">
                        <div class="text-xs text-gray-500">结算/折账</div>
                        <div v-for="s in infoData.settlements" :key="s.id" class="mt-1 flex flex-wrap gap-x-4 text-gray-600">
                            <span>{{ s.settlement_no }}</span>
                            <span>折账 ¥{{ Number(s.offset_amount).toFixed(2) }}</span>
                            <span>现金 ¥{{ Number(s.cash_amount).toFixed(2) }}</span>
                            <span v-if="s.account_name">户头：{{ s.account_name }}</span>
                        </div>
                    </div>
                    <div v-if="(infoData.capital_flows || []).length" class="mt-2">
                        <div class="text-xs text-gray-500">收款流水</div>
                        <div v-for="(c, i) in infoData.capital_flows" :key="i" class="mt-1 flex flex-wrap gap-x-4 text-gray-600">
                            <span>{{ c.account_name }}</span><span>{{ c.direction === 'in' ? '收' : '付' }} ¥{{ Number(c.amount).toFixed(2) }}</span>
                            <span class="text-gray-400">{{ c.remark }}</span>
                        </div>
                    </div>
                    <div v-if="!(infoData.receivables || []).length && !(infoData.capital_flows || []).length" class="mt-1 text-gray-400">暂无财务记录</div>
                </div>
            </div>
        </el-drawer>

        <!-- 设备全链路 + 主体抽屉 -->
        <trace-detail v-model="trace.visible" :asset-id="trace.assetId" :device-id="trace.deviceId" />
        <entity-drawer v-model="entityDrawer.visible" :entity-id="entityDrawer.id" />

        <!-- 快速建档:对接人+主体一步建 -->
        <el-dialog v-model="quickContact.visible" title="快速建档(对接人)" width="420px" append-to-body>
            <el-form label-width="84px">
                <el-form-item label="对接人" required>
                    <el-input v-model.trim="quickContact.name" placeholder="交易人姓名,如 张三 / 某同行老板" />
                </el-form-item>
                <el-form-item label="手机号" required>
                    <el-input v-model.trim="quickContact.mobile" placeholder="用于建会员档,必填" />
                </el-form-item>
                <el-form-item label="所属主体">
                    <el-input v-model.trim="quickContact.entity_name" placeholder="留空则以对接人姓名作主体名" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="quickContact.visible = false">取消</el-button>
                <el-button type="primary" :loading="quickContact.submitting" @click="submitQuickContact">建档并选中</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import { getErpOutboundList, getErpOutboundInfo, createErpOutbound, fillErpOutboundPrice, cancelErpOutbound, partialReturnErpOutbound } from '@/addon/hsx_erp/api/outbound'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpAssetList } from '@/addon/hsx_erp/api/asset'
import { getErpMemberOptions, quickCreateErpContact } from '@/addon/hsx_erp/api/counterparty'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import TraceDetail from '@/addon/hsx_erp/views/device_trace/trace-detail.vue'
import EntityDrawer from '@/addon/hsx_erp/views/finance/entity-drawer.vue'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { useListQuery } from '@/addon/hsx_erp/composables/useListQuery'

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const formatTime = (t: number) => new Date(t * 1000).toLocaleString()

// 列表查询统一走 useListQuery：search/分页/排序/日期区间/loading 都收敛在里面
const { search, table, loadList, reset: resetSearch, onSort, onPage: onPageChange } = useListQuery({
    api: getErpOutboundList,
    defaults: { outbound_type: '', biz_status: '', keyword: '', imei: '', dateRange: [], amount_min: '', amount_max: '', sort_field: '', sort_order: '' },
    dateRangeField: 'dateRange',
})

async function doCancel(row: any) {
    try {
        await ElMessageBox.confirm(
            `退回出库单 ${row.outbound_no}？锁定设备将恢复在库、未收款的应收作废。`,
            '退回出库', { type: 'warning' }
        )
    } catch { return }
    try {
        const res: any = await cancelErpOutbound(row.id)
        ElMessage.success(`已退回，恢复在库 ${res.data?.restored ?? 0} 台`)
        loadList()
    } catch (e: any) {
        ElMessage.error(e?.message || '退回失败')
    }
}

// 新建出库
const createVisible = ref(false)
const submitting = ref(false)
const form = reactive<any>({ outbound_type: 'peer_sale', counterparty_id: undefined, counterparty_name: '', settle_mode: 'now', capital_account_id: undefined, remark: '' })
const contacts = ref<any[]>([])
const cpLoading = ref(false)
const payAccounts = ref<any[]>([])
const warehouseOptions = ref<any[]>([])
const assetWarehouseId = ref<number | ''>('')
const assetKeyword = ref('')
const businessTypeMap: Record<string, string> = { mall: '商城', peer: '同行', consignment: '代卖', hold: '暂存', scrap: '报废' }
const businessTypeLabel = (v: string) => businessTypeMap[v] || '商城'
const warehouseName = (id: number) => warehouseOptions.value.find((w: any) => Number(w.id) === Number(id))?.warehouse_name || '-'
const availableAssets = ref<any[]>([])
const assetLoading = ref(false)
const assetPage = ref(1)
const assetTotal = ref(0)
const assetLimit = 100
const selectedAssets = ref<any[]>([])
const priceInput = reactive<Record<number, number>>({})
const consignorInput = reactive<Record<number, number>>({})
const showPrice = computed(() => form.outbound_type === 'peer_sale' && form.settle_mode === 'now')
// 代卖设备卖出需填"应付寄卖人"金额（人手填）
const isConsign = (row: any) => String(row?.ownership_type) === 'consign'
const showConsignorCol = computed(() => form.outbound_type === 'peer_sale' && availableAssets.value.some((a) => isConsign(a)))

async function openCreate() {
    createVisible.value = true
    await Promise.all([searchContacts(''), loadWarehouses(), loadAvailableAssets(), loadPayAccounts()])
}
async function loadWarehouses() {
    try {
        const res: any = await getErpWarehouseOptions()
        warehouseOptions.value = res.data || []
    } catch { warehouseOptions.value = [] }
}
async function loadPayAccounts() {
    try {
        const res: any = await getCapitalAccounts()
        payAccounts.value = (res.data?.list || []).filter((a: any) => Number(a.status) === 1)
    } catch { payAccounts.value = [] }
}
async function searchContacts(keyword: string) {
    cpLoading.value = true
    try {
        const res: any = await getErpMemberOptions({ keyword: keyword || '' })
        contacts.value = res.data || []
    } catch { contacts.value = [] } finally {
        cpLoading.value = false
    }
}
async function loadAvailableAssets() {
    assetLoading.value = true
    try {
        const params: any = { sellable: 1, page: assetPage.value, limit: assetLimit }
        if (assetWarehouseId.value) params.warehouse_id = assetWarehouseId.value
        if (assetKeyword.value) params.keyword = assetKeyword.value
        const res: any = await getErpAssetList(params)
        availableAssets.value = res.data?.data || []
        assetTotal.value = res.data?.total || 0
    } finally {
        assetLoading.value = false
    }
}
// 改筛选条件回到第1页(勾选用 reserve-selection 跨页保留)
function reloadAssets() {
    assetPage.value = 1
    loadAvailableAssets()
}
function onAssetPage(p: number) {
    assetPage.value = p
    loadAvailableAssets()
}
function onTypeChange() {
    if (form.outbound_type !== 'peer_sale') form.settle_mode = 'none'
    else if (form.settle_mode === 'none') form.settle_mode = 'now'
}
function onContactChange(id: number) {
    const c = contacts.value.find((x) => x.member_id === id)
    form.counterparty_name = c ? (c.nickname || c.username || '') : ''
}
// counterparty-select 解析回调:以人为锚,记账名取交易人姓名
function onContactResolved(d: any) {
    form.counterparty_name = d ? (d.member_name || '') : ''
}

// 快速建档:一步建对接人+主体
const quickContact = reactive<any>({ visible: false, submitting: false, name: '', mobile: '', entity_name: '' })
function openQuickContact() {
    Object.assign(quickContact, { visible: true, submitting: false, name: '', mobile: '', entity_name: '' })
}
async function submitQuickContact() {
    if (!quickContact.name) return ElMessage.warning('请填写对接人姓名')
    if (!quickContact.mobile) return ElMessage.warning('请填写手机号(用于建档)')
    quickContact.submitting = true
    try {
        const res: any = await quickCreateErpContact({
            name: quickContact.name, mobile: quickContact.mobile, entity_name: quickContact.entity_name,
        })
        const d = res.data || {}
        // 选中刚建好的对接人(锚=member_id)
        contacts.value.unshift({ member_id: d.member_id, nickname: d.member_name, username: d.member_name, mobile: d.mobile, counterparty_name: d.counterparty_name })
        form.counterparty_id = d.member_id
        form.counterparty_name = d.member_name
        ElMessage.success('已建档并选中:' + d.member_name + '（' + d.counterparty_name + '）')
        quickContact.visible = false
    } catch (e: any) {
        ElMessage.error(e?.message || '建档失败')
    } finally {
        quickContact.submitting = false
    }
}
function onAssetSelect(rows: any[]) {
    selectedAssets.value = rows
}
async function doCreate() {
    if (selectedAssets.value.length === 0) return
    if (form.outbound_type === 'peer_sale' && !form.counterparty_id) {
        ElMessage.warning('请选择对接人/交易人(或点"快速建档")')
        return
    }
    if (form.outbound_type === 'peer_sale' && form.settle_mode === 'now' && !form.capital_account_id) {
        ElMessage.warning('现结出库请选择收款户头')
        return
    }
    const items = selectedAssets.value.map((a) => ({
        asset_id: a.id,
        sale_price: showPrice.value ? (priceInput[a.id] || 0) : 0,
        consignor_payable: isConsign(a) ? (consignorInput[a.id] || 0) : 0,
    }))
    if (showPrice.value && items.some((i) => !i.sale_price)) {
        ElMessage.warning('现结出库请为每台填写出货价')
        return
    }
    submitting.value = true
    try {
        await createErpOutbound({
            outbound_type: form.outbound_type,
            counterparty_id: form.counterparty_id,
            counterparty_name: form.counterparty_name,
            settle_mode: form.outbound_type === 'peer_sale' ? form.settle_mode : 'none',
            capital_account_id: form.settle_mode === 'now' ? form.capital_account_id : 0,
            remark: form.remark,
            items,
        })
        ElMessage.success('出库成功')
        createVisible.value = false
        loadList()
    } finally {
        submitting.value = false
    }
}
function resetCreate() {
    form.outbound_type = 'peer_sale'
    form.counterparty_id = undefined
    form.counterparty_name = ''
    form.settle_mode = 'now'
    form.capital_account_id = undefined
    form.remark = ''
    selectedAssets.value = []
    assetWarehouseId.value = ''
    assetKeyword.value = ''
    assetPage.value = 1
    Object.keys(priceInput).forEach((k) => delete priceInput[Number(k)])
    Object.keys(consignorInput).forEach((k) => delete consignorInput[Number(k)])
}

// 处理挂单：一个弹窗完成回填价格 + 部分退回
const pendingProcess = reactive<any>({
    visible: false, submitting: false,
    orderId: 0, outboundNo: '', buyer: '', priceStatus: '', items: [], reason: '',
    collect_now: false, payments: [] as Array<{ account_id: number | undefined; amount: number }>,
})
// 同行挂单(later)未收齐都可处理:回填价 / 部分退回 / 当场收款
const canProcessPending = (row: any) => !row?.is_void && row?.settle_mode === 'later' && !row?.collected
const keptProcessCount = computed(() => pendingProcess.items.filter((it: any) => !it.is_returned && !it.return_selected).length)
const returnProcessCount = computed(() => pendingProcess.items.filter((it: any) => it.return_selected).length)
// 留下设备的应收合计(当场收款的目标金额)
const keptTotal = computed(() => pendingProcess.items
    .filter((it: any) => !it.is_returned && !it.return_selected)
    .reduce((s: number, it: any) => s + Number(it.sale_price || 0), 0))
const collectPaid = computed(() => (pendingProcess.payments || []).reduce((s: number, p: any) => s + Number(p.amount || 0), 0))
const collectSumOk = computed(() => Math.abs(keptTotal.value - collectPaid.value) < 0.01)
// 收款账户(微信/支付宝…户头),首次打开收款时加载
const collectAccounts = ref<any[]>([])
async function loadCollectAccounts() {
    if (collectAccounts.value.length) return
    try { const res: any = await getCapitalAccounts(); collectAccounts.value = (res.data?.list || res.data || []).filter((a: any) => Number(a.status) === 1) } catch { collectAccounts.value = [] }
}
const addCollectPayment = () => {
    const remain = Math.max(0, Number((keptTotal.value - collectPaid.value).toFixed(2)))
    pendingProcess.payments.push({ account_id: undefined, amount: remain })
}
const onCollectToggle = (v: any) => {
    if (v) {
        loadCollectAccounts()
        if (!pendingProcess.payments.length) pendingProcess.payments = [{ account_id: undefined, amount: Number(keptTotal.value.toFixed(2)) }]
    }
}
// 行底色:已退回灰、本次退回浅红
const processRowClass = ({ row }: any) => row.is_returned ? 'pp-row-returned' : (row.return_selected ? 'pp-row-back' : '')
function setPendingProcess(data: any) {
    pendingProcess.orderId = data.id
    pendingProcess.outboundNo = data.outbound_no || ''
    pendingProcess.buyer = data.buyer_name || data.counterparty_name || ''
    pendingProcess.priceStatus = data.price_status || ''
    pendingProcess.reason = ''
    pendingProcess.collect_now = false
    pendingProcess.payments = []
    pendingProcess.items = (data.items || []).map((it: any) => ({
        ...it,
        sale_price: Number(it.sale_price || 0),
        return_selected: false,
    }))
    pendingProcess.visible = true
}
async function openPendingProcess(row: any) {
    const res: any = await getErpOutboundInfo(row.id)
    setPendingProcess(res.data || {})
}
function openPendingProcessFromDrawer(data: any) {
    setPendingProcess(data)
}
function resetPendingProcess() {
    pendingProcess.orderId = 0
    pendingProcess.outboundNo = ''
    pendingProcess.buyer = ''
    pendingProcess.priceStatus = ''
    pendingProcess.items = []
    pendingProcess.reason = ''
    pendingProcess.collect_now = false
    pendingProcess.payments = []
}
async function doPendingProcess() {
    const returnIds = pendingProcess.items.filter((it: any) => it.return_selected).map((it: any) => it.id)
    const keptItems = pendingProcess.items.filter((it: any) => !it.is_returned && !it.return_selected)
    if (pendingProcess.priceStatus === 'pending' && keptItems.some((it: any) => !(Number(it.sale_price) > 0))) {
        ElMessage.warning('客户留下的设备请填写成交价；不要的设备请勾选退回')
        return
    }
    // 出库管理只回填价/退回,不收款:留下的设备挂待结应收,收款由财务中心处理(职责分明)。
    pendingProcess.submitting = true
    try {
        if (returnIds.length) {
            await partialReturnErpOutbound(pendingProcess.orderId, returnIds, pendingProcess.reason)
        }
        if (keptItems.length) {
            const pricedItems = keptItems.map((it: any) => ({ item_id: it.id, sale_price: Number(it.sale_price) || 0 }))
            if (pricedItems.some((it: any) => it.sale_price > 0)) {
                await fillErpOutboundPrice(pendingProcess.orderId, pricedItems, {})
            }
        }
        ElMessage.success(`处理完成：留下 ${keptItems.length} 台挂待结应收，退回 ${returnIds.length} 台。收款请到财务中心`)
        pendingProcess.visible = false
        loadList()
        if (infoVisible.value && infoData.value?.id === pendingProcess.orderId) {
            const r: any = await getErpOutboundInfo(pendingProcess.orderId)
            infoData.value = r.data
        }
    } catch (e: any) {
        ElMessage.error(e?.message || '处理失败')
    } finally {
        pendingProcess.submitting = false
    }
}

// 详情
const infoVisible = ref(false)
const infoLoading = ref(false)
const infoData = ref<any>(null)
async function openInfo(row: any) {
    infoVisible.value = true
    infoLoading.value = true
    try {
        const res: any = await getErpOutboundInfo(row.id)
        infoData.value = res.data
    } finally {
        infoLoading.value = false
    }
}

// 设备全链路 + 主体抽屉
const trace = reactive<any>({ visible: false, assetId: 0, deviceId: 0 })
function openTrace(row: any) {
    trace.assetId = row.asset_id || 0
    trace.deviceId = row.source_device_id || 0
    trace.visible = true
}
const entityDrawer = reactive<any>({ visible: false, id: 0 })
function openEntity(id: number) {
    if (!id) return
    entityDrawer.id = id
    entityDrawer.visible = true
}
// 首次加载由 useListQuery(immediate) 触发，这里不再手动 loadList()
</script>

<style lang="scss" scoped>
/* 处理挂单弹窗:概览/分步/收款 */
.pp-summary {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
    padding: 10px 14px; margin-bottom: 12px;
    background: var(--el-fill-color-light); border-radius: 8px;
}
.pp-meta { font-size: 13px; color: var(--el-text-color-regular); }
.pp-meta b { color: var(--el-text-color-primary); }
.pp-sep { margin: 0 8px; color: var(--el-text-color-placeholder); }
.pp-chips { display: flex; gap: 8px; }
.pp-chip { font-size: 12px; padding: 3px 10px; border-radius: 14px; font-weight: 600; }
.pp-chip.keep { color: var(--el-color-success); background: var(--el-color-success-light-9); }
.pp-chip.back { color: var(--el-text-color-placeholder); background: var(--el-fill-color); }
.pp-chip.back.on { color: var(--el-color-danger); background: var(--el-color-danger-light-9); }
.pp-step {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600; color: var(--el-text-color-primary); margin-bottom: 8px;
}
.pp-step-no {
    display: inline-flex; align-items: center; justify-content: center;
    width: 18px; height: 18px; border-radius: 50%;
    background: var(--el-color-primary); color: #fff; font-size: 12px;
}
.pp-step-amt { margin-left: 6px; font-weight: 700; color: var(--el-color-danger); }
.pp-collect-mode { margin-bottom: 8px; }
.pp-pay { padding: 12px 14px; background: var(--el-fill-color-lighter); border-radius: 8px; }
.pp-pay-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.pp-pay-foot { display: flex; align-items: center; justify-content: space-between; }
.pp-pay-sum { font-size: 12px; color: var(--el-text-color-secondary); font-weight: 600; }
.pp-pay-sum.bad { color: var(--el-color-danger); }
.pp-pay-tip { margin-top: 8px; font-size: 12px; color: var(--el-text-color-placeholder); line-height: 1.5; }
.pp-footer { display: flex; align-items: center; justify-content: space-between; }
.pp-footer-hint { font-size: 12px; color: var(--el-text-color-secondary); }
:deep(.pp-row-returned) { background: var(--el-fill-color-light) !important; }
:deep(.pp-row-back) { background: var(--el-color-danger-light-9) !important; }
</style>
