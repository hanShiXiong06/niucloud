<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">应收款</div>
                    <div class="mt-1 text-sm text-gray-500">统一管理销售收入、采购退货款及维修等业务收入；财务按收入类型、业务来源和到账账户核对。</div>
                </div>
                <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
            </div>

            <ErpRoleFocus :items="receivableRoleFocus" />

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile"><div class="summary-label">应收批次</div><div class="summary-value">{{ summary.count }}</div></div>
                <div class="summary-tile"><div class="summary-label">应收总额</div><div class="summary-value">{{ money(summary.amount) }}</div></div>
                <div class="summary-tile"><div class="summary-label">已结算</div><div class="summary-value text-green-600">{{ money(summary.settled) }}</div></div>
                <div class="summary-tile"><div class="summary-label">剩余应收</div><div class="summary-value text-orange-600">{{ money(summary.remain) }}</div></div>
            </div>

            <el-tabs v-model="activeStatus" class="mt-5" @tab-change="handleSearch">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="待结算" name="pending" />
                <el-tab-pane label="部分结算" name="partial" />
                <el-tab-pane label="已结清" name="settled" />
                <el-tab-pane label="已作废" name="void" />
            </el-tabs>

            <el-form :inline="true" class="mt-1" @submit.prevent>
                <el-form-item label="IMEI">
                    <el-input v-model.trim="search.imei" clearable class="!w-[180px]" placeholder="输入设备 IMEI" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="往来主体">
                    <ErpPartySelect
                        v-model="search.party_id"
                        v-model:party-name="searchPartyName"
                        party-type="all"
                        :allow-create="false"
                        class="!w-[220px]"
                        placeholder="选择客户或供货商"
                    />
                </el-form-item>
                <el-form-item label="业务场景">
                    <el-select v-model="search.source_type" clearable class="!w-[160px]" placeholder="全部来源">
                        <el-option label="销售应收" value="sale" />
                        <el-option label="采购退货退款" value="purchase_return" />
                    </el-select>
                </el-form-item>
                <el-form-item label="时间">
                    <el-date-picker v-model="search.dateRange" type="daterange" value-format="X" start-placeholder="开始时间" end-placeholder="结束时间" class="!w-[300px]" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button :icon="Filter" @click="advancedVisible = !advancedVisible">{{ advancedVisible ? '收起条件' : '更多条件' }}</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
            <el-form v-show="advancedVisible" :inline="true" class="rounded bg-gray-50 px-3 pt-3" @submit.prevent>
                <el-form-item label="来源单">
                    <el-input v-model.trim="search.source_no" clearable class="!w-[190px]" placeholder="销售单 / 退货单" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="收入类型">
                    <el-select v-model="search.finance_type_key" clearable filterable class="!w-[170px]" placeholder="全部收入类型">
                        <el-option v-for="item in financeTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="业务来源">
                    <el-select v-model="search.business_source_key" clearable filterable class="!w-[170px]" placeholder="全部业务来源">
                        <el-option v-for="item in businessSourceOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="业务渠道">
                    <el-select v-model="search.channel_code" clearable filterable class="!w-[170px]" placeholder="全部渠道">
                        <el-option v-for="item in channelOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="业务操作人">
                    <el-select v-model="search.operator_uid" clearable filterable class="!w-[180px]" placeholder="选择操作人">
                        <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="search.can_offset" true-label="1" false-label="">只看可折账</el-checkbox>
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="search.only_effective" true-label="1" false-label="">只看真实成交</el-checkbox>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large" table-layout="fixed" :row-class-name="receivableRowClass">
                <el-table-column label="往来主体" min-width="190">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900"><ErpOverflowText :text="row.party_name" max-width="165px" /></div>
                        <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                            <el-tag size="small" effect="plain" type="info">{{ partyRoleLabel(row, '往来主体') }}</el-tag>
                            <ErpOverflowText :text="contactText(row)" max-width="120px" />
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="款项来源" min-width="310">
                    <template #default="{ row }">
                        <ErpFinanceSourceMeta :row="row" compact default-direction="income" />
                        <div v-if="isVoid(row)" class="mt-2 text-xs font-medium text-gray-500">{{ row.void_reason || '来源交易已撤回，不计入真实成交' }}</div>
                        <div class="mt-1 text-xs text-gray-400">{{ row.item_count || 0 }} 台 · 业务时间 {{ formatTime(row.sale_at || row.occurred_at) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="账目" min-width="240">
                    <template #default="{ row }">
                        <div>应收 <span class="font-medium">{{ money(row.amount) }}</span></div>
                        <div class="mt-1 text-xs text-gray-500">已结算 {{ money(row.settled_amount) }} · 剩余 {{ money(row.remain_amount ?? remain(row)) }}</div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <el-tooltip :content="`结算约定：${row.opening_settle_method || row.settle_method || '-'}`" placement="top" :show-after="250">
                                <el-tag class="max-w-[190px] !inline-flex" size="small" effect="plain" type="info">
                                    <span class="truncate">结算约定：{{ row.opening_settle_method || row.settle_method || '-' }}</span>
                                </el-tag>
                            </el-tooltip>
                            <el-tag v-if="!(row.settle_summary_items || []).length" size="small" effect="plain" :type="remain(row) > 0 ? 'warning' : 'success'">{{ remain(row) > 0 ? '待清算' : '已结清' }}</el-tag>
                            <el-tag v-for="item in row.settle_summary_items" :key="item.label" size="small" effect="plain" :type="settleSummaryTagType(item.label)">
                                {{ item.label }} {{ money(item.amount) }}
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="业务 / 财务负责人" min-width="160">
                    <template #default="{ row }">
                        <div class="responsible-line">
                            <span>开单</span><ErpOverflowText :text="row.business_operator_name || row.salesman_name" max-width="105px" />
                        </div>
                        <div v-if="row.settlement_operator_name" class="responsible-line mt-1 text-xs text-green-700">
                            <span>收款</span><ErpOverflowText :text="row.settlement_operator_names?.join('、') || row.settlement_operator_name" max-width="105px" />
                        </div>
                        <div v-if="row.task_assignee_name && row.task_assignee_name !== row.settlement_operator_name" class="responsible-line mt-1 text-xs text-gray-500">
                            <span>待办</span><ErpOverflowText :text="row.task_assignee_name" max-width="105px" />
                        </div>
                        <div class="mt-1 text-xs text-gray-500"><ErpCopyText :value="row.receivable_no" title="应收单号" max-width="135px" /></div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <div v-if="isVoid(row)" class="void-stamp">已作废</div>
                        <el-tag v-else :type="statusMeta(row.status).type">{{ statusMeta(row.status).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="220" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">查看明细</el-button>
                        <el-button v-if="canConfirmReceipt(row)" type="primary" link @click="openReceipt(row)">收款</el-button>
                        <el-button v-if="canOffset(row)" type="warning" link @click="openOffset(row)">折账</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit" layout="total, sizes, prev, pager, next, jumper" :total="table.total" @size-change="loadList" @current-change="loadList" />
            </div>
        </el-card>

        <el-drawer v-model="detail.visible" title="应收详情" size="920px">
            <div v-if="detail.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div class="flex flex-wrap items-center gap-2">
                    <span>{{ partyRoleLabel(detail.row, '往来主体') }}：<span class="font-medium text-gray-900">{{ detail.row.party_name || '-' }}</span></span>
                    <el-tag :type="statusMeta(detail.row.status).type" size="small">{{ statusMeta(detail.row.status).label }}</el-tag>
                </div>
                <ErpFinanceSourceMeta :row="detail.row" class="mt-3" default-direction="income" />
                <div class="mt-3 border-t border-gray-200 pt-2">应收 {{ money(detail.row.amount) }} · 已结算 {{ money(detail.row.settled_amount) }} · 剩余 {{ money(remain(detail.row)) }}</div>
                <div v-if="detail.row.source_order" class="mt-1 text-xs text-gray-500">
                    {{ sourceOrderSummary(detail.row) }}
                </div>
            </div>
            <div v-if="detail.row?.is_mall_source" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded border border-amber-200 bg-amber-50 px-4 py-3">
                <div>
                    <div class="flex items-center gap-2 text-sm font-medium text-amber-900">
                        <span>商城来源成交</span>
                        <el-tag size="small" :type="detail.row.detail_status === 'complete' ? 'success' : 'warning'">{{ detail.row.detail_status_name }}</el-tag>
                        <el-tag v-if="detail.row.supplement_badge" size="small" type="primary">{{ detail.row.supplement_badge }}</el-tag>
                    </div>
                    <div class="mt-1 text-xs text-amber-700">这笔账来自商城；补录会建立 ERP 已售资产、成本和出入库台账，不会重复生成应收或采购应付。</div>
                </div>
                <el-button v-if="detail.row.can_supplement_sale_detail" type="primary" @click="openSupplement">补录成交资料</el-button>
            </div>
            <div class="mb-2 font-medium text-gray-900">设备明细</div>
            <el-table :data="detail.items" v-loading="detail.loading" size="small" empty-text="暂无设备明细">
                <el-table-column label="设备" min-width="230">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ erpSerialText(row) }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="仓库" min-width="140">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column label="成本" width="120">
                    <template #default="{ row }">{{ money(row.cost) }}</template>
                </el-table-column>
                <el-table-column label="售价" width="120">
                    <template #default="{ row }">{{ money(row.sale_price) }}</template>
                </el-table-column>
                <el-table-column label="毛利" width="120">
                    <template #default="{ row }"><span :class="Number(row.profit || 0) >= 0 ? 'text-green-600' : 'text-red-600'">{{ money(row.profit) }}</span></template>
                </el-table-column>
            </el-table>

            <div class="mb-2 mt-6 font-medium text-gray-900">结算记录</div>
            <ErpSettlementCards :rows="detail.settlements" :loading="detail.loading" />
        </el-drawer>

        <ErpMallReceivableSupplementDialog
            v-model="supplement.visible"
            :receivable="detail.row"
            :items="detail.items"
            :staff-options="staffOptions"
            @saved="onSupplementSaved"
        />

        <el-dialog v-model="offset.visible" title="应收应付折账" width="920px">
            <div v-if="offset.row" class="mb-4 rounded bg-amber-50 px-4 py-3 text-sm text-gray-600">
                <div>往来主体：<span class="font-medium text-gray-900">{{ offset.row.party_name }}</span></div>
                <div class="mt-1 text-xs text-gray-500">折账就是把“我要付给他的钱”和“他要付给我的钱”互相抵扣，不产生真实收付款。</div>
            </div>
            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">我要付给他</div>
                    <div class="mt-1 font-medium text-red-600">{{ money(offsetPayableChecked) }}</div>
                </div>
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">他要付给我</div>
                    <div class="mt-1 font-medium text-green-600">{{ money(offsetReceivableChecked) }}</div>
                </div>
                <div class="rounded bg-amber-50 px-3 py-2">
                    <div class="text-xs text-gray-500">本次互相抵扣</div>
                    <div class="mt-1 font-medium text-amber-700">{{ money(offsetAmount) }}</div>
                </div>
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">抵扣后</div>
                    <div class="mt-1 font-medium text-gray-900">{{ offsetResultText }}</div>
                </div>
            </div>
            <div v-loading="offset.loading" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="rounded border border-gray-200 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="font-medium text-gray-900">我要付给他（应付）</span>
                        <span class="text-xs text-gray-500">勾选合计 {{ money(offsetPayableChecked) }}</span>
                    </div>
                    <el-table :data="offset.payables" size="small" max-height="300" empty-text="无待折应付">
                        <el-table-column width="46">
                            <template #default="{ row }"><el-checkbox v-model="row.checked" @change="syncOffsetAmount" /></template>
                        </el-table-column>
                        <el-table-column label="设备 / 来源" min-width="180">
                            <template #default="{ row }">
                                <div class="text-sm">{{ row.model || row.source_no || '应付' }}</div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ row.imei ? `IMEI ${row.imei}` : row.payable_no }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="剩余应付" width="110" align="right">
                            <template #default="{ row }">{{ money(row.remain) }}</template>
                        </el-table-column>
                    </el-table>
                </div>
                <div class="rounded border border-gray-200 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="font-medium text-gray-900">他要付给我（应收）</span>
                        <span class="text-xs text-gray-500">勾选合计 {{ money(offsetReceivableChecked) }}</span>
                    </div>
                    <el-table :data="offset.receivables" size="small" max-height="300" empty-text="无待折应收">
                        <el-table-column width="46">
                            <template #default="{ row }"><el-checkbox v-model="row.checked" @change="syncOffsetAmount" /></template>
                        </el-table-column>
                        <el-table-column label="业务来源" min-width="180">
                            <template #default="{ row }">
                                <div class="text-sm">{{ row.source_no || row.batch_no || '应收' }}</div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ row.receivable_no }}</div>
                                <div v-if="row.device_summary" class="mt-0.5 text-xs text-gray-500">{{ row.device_summary }}<span v-if="row.device_more"> 等 {{ row.device_more + 2 }} 台</span></div>
                            </template>
                        </el-table-column>
                        <el-table-column label="剩余应收" width="110" align="right">
                            <template #default="{ row }">{{ money(row.remain) }}</template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>
            <el-form class="mt-4" label-width="100px">
                <el-form-item label="可折金额">
                    <span class="font-medium text-gray-900">{{ money(offsetMax) }}</span>
                    <span class="ml-2 text-xs text-gray-500">= 两侧勾选金额的较小值；可改小，剩余部分后续单独结算</span>
                </el-form-item>
                <el-form-item label="本次抵扣" required>
                    <el-input-number v-model="offset.form.amount" :min="0" :max="offsetMax" :precision="2" :controls="false" class="!w-[220px]" />
                    <span class="ml-2 text-xs text-gray-500">这一步只做账目抵扣，不会产生资金流水</span>
                </el-form-item>
                <el-form-item v-if="offsetDiffAmount > 0" label="差额处理">
                    <div class="w-full">
                        <el-checkbox v-model="offset.form.settle_diff">本次结清差额：{{ offsetDiffText }}</el-checkbox>
                        <el-select v-if="offset.form.settle_diff" v-model="offset.form.capital_account_id" clearable class="mt-2 w-full" :placeholder="offsetDiffDirection === 'payable' ? '选择付款账户' : '选择收款账户'">
                            <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                        </el-select>
                    </div>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="offset.form.remark" type="textarea" :rows="2" placeholder="如：同一客户往来抵扣" />
                </el-form-item>
                <el-form-item v-if="offset.form.settle_diff" label="差额凭证"><ErpFinanceVoucherUpload v-model="offset.form.voucher_urls" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="offset.visible = false">取消</el-button>
                <el-button type="warning" :loading="offset.saving" :disabled="offsetMax <= 0" @click="submitOffset">确认抵扣折账</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="receipt.visible" :title="receiptDialogTitle" width="980px">
            <div v-if="receipt.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>{{ receiptPartyLabel }}：<span class="font-medium text-gray-900">{{ receipt.row.party_name }}</span></div>
                <ErpFinanceSourceMeta :row="receiptSourceRow" class="mt-3" default-direction="income" />
                <div v-if="isPurchaseReturnReceipt && receipt.row.purchase_no" class="mt-1">原采购单：{{ receipt.row.purchase_no }}</div>
                <div class="mt-1">剩余应收：<span class="font-medium text-orange-600">{{ money(remain(receipt.row)) }}</span></div>
            </div>
            <div class="mb-2 flex items-center justify-between">
                <span class="font-medium text-gray-900">{{ receiptItemTitle }}</span>
                <span class="text-sm text-gray-500">本次到账合计 <span class="font-medium text-green-600">{{ money(receiptTotal) }}</span></span>
            </div>
            <el-table :data="receipt.items" v-loading="receipt.loading" size="small" max-height="360" empty-text="暂无设备明细">
                <el-table-column width="46">
                    <template #default="{ row }">
                        <el-checkbox v-model="row.checked" :disabled="itemRemain(row) <= 0" @change="syncReceiptAmount(row)" />
                    </template>
                </el-table-column>
                <el-table-column label="设备" min-width="220">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ erpSerialText(row) }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }}</div>
                        <div v-if="isPurchaseReturnReceipt" class="mt-2 text-xs leading-5" :class="Number(row.refund_amount || 0) > 0 ? 'text-orange-600' : 'text-green-600'">
                            {{ row.settlement_explanation || returnReceiptItemReason(row) }}
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="isPurchaseReturnReceipt ? '应退金额' : '实际销售价'" width="150" align="right">
                    <template #default="{ row }">
                        <span v-if="isPurchaseReturnReceipt" class="font-medium" :class="Number(row.refund_amount || 0) > 0 ? 'text-orange-600' : 'text-gray-400'">{{ money(row.refund_amount || row.sale_price) }}</span>
                        <el-input-number v-else v-model="row.sale_price" :min="0" :precision="2" :controls="false" class="!w-[120px]" @change="syncReceiptAmount(row)" />
                    </template>
                </el-table-column>
                <el-table-column :label="isPurchaseReturnReceipt ? '已到账' : '已结算'" width="120" align="right">
                    <template #default="{ row }">{{ money(row.allocated_settled) }}</template>
                </el-table-column>
                <el-table-column :label="isPurchaseReturnReceipt ? '本次到账' : '本次收款'" width="150" align="right">
                    <template #default="{ row }">
                        <el-input-number v-model="row.receipt_amount" :min="0" :max="itemRemain(row)" :precision="2" :controls="false" class="!w-[120px]" :disabled="!row.checked" />
                    </template>
                </el-table-column>
                <el-table-column :label="isPurchaseReturnReceipt ? '到账后剩余' : '收后剩余'" width="120" align="right">
                    <template #default="{ row }">{{ money(Math.max(0, itemRemain(row) - Number(row.receipt_amount || 0))) }}</template>
                </el-table-column>
            </el-table>
            <el-form label-width="100px">
                <el-form-item :label="isPurchaseReturnReceipt ? '到账金额' : '收款金额'" required>
                    <span class="font-medium text-green-600">{{ money(receiptTotal) }}</span>
                    <span class="ml-2 text-xs text-gray-500">由上方设备本次到账自动汇总</span>
                </el-form-item>
                <el-form-item label="收款账户">
                    <el-select v-model="receipt.form.capital_account_id" clearable class="w-full" placeholder="选择银行卡/微信/支付宝">
                        <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="receipt.form.remark" type="textarea" :rows="2" :placeholder="isPurchaseReturnReceipt ? '如：已核对供货商退款到账记录' : '如：已核对到账记录'" />
                </el-form-item>
                <el-form-item label="收款凭证"><ErpFinanceVoucherUpload v-model="receipt.form.voucher_urls" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="receipt.visible = false">取消</el-button>
                <el-button type="primary" :loading="receipt.saving" :disabled="!canSubmitReceipt" @click="submitReceipt">{{ isPurchaseReturnReceipt ? '确认退款到账' : '确认收款' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { erpEnumLabel, erpNamedLabel, erpSerialText } from '@/addon/hsx_erp/utils/display'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Filter, Refresh, Search } from '@element-plus/icons-vue'
import { useRoute } from 'vue-router'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpBusinessSourceOptions, getErpFinanceCategories, getErpSaleChannelOptions } from '@/addon/hsx_erp/api/config'
import { confirmErpOffset, confirmErpReceipt, getErpPayablePartyItems, getErpReceivableInfo, getErpReceivableItems, getErpReceivableList, getErpStaffOptions } from '@/addon/hsx_erp/api/erp'
import ErpRoleFocus from '@/addon/hsx_erp/components/ErpRoleFocus.vue'
import ErpPartySelect from '@/addon/hsx_erp/components/ErpPartySelect.vue'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
import ErpSettlementCards from '@/addon/hsx_erp/components/ErpSettlementCards.vue'
import ErpFinanceSourceMeta from '@/addon/hsx_erp/components/ErpFinanceSourceMeta.vue'
import ErpCopyText from '@/addon/hsx_erp/components/ErpCopyText.vue'
import ErpOverflowText from '@/addon/hsx_erp/components/ErpOverflowText.vue'
import ErpMallReceivableSupplementDialog from '@/addon/hsx_erp/components/ErpMallReceivableSupplementDialog.vue'

const receivableRoleFocus = [
    { role: '财务', focus: '收入类型、业务来源、往来余额、到账账户与核销事实' },
    { role: '业务', focus: '销售、采购退货或维修服务等原始单据和设备明细' },
    { role: '负责人', focus: '现金回笼、逾期风险与折账事实' },
]

const route = useRoute()
const routeStatus = String(route.query.status || '')
const activeStatus = ref(['', 'pending', 'partial', 'settled', 'void'].includes(routeStatus) ? routeStatus : '')
const search = reactive({
    imei: '',
    party_id: null as number | null,
    source_type: '',
    dateRange: [] as any[],
    source_no: String(route.query.source_no || ''),
    finance_type_key: '',
    business_source_key: '',
    channel_code: '',
    operator_uid: null as number | null,
    can_offset: '',
    only_effective: ''
})
const searchPartyName = ref('')
const staffOptions = ref<any[]>([])
const advancedVisible = ref(false)
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const accounts = ref<any[]>([])
const registeredFinanceCategories = ref<any[]>([])
const registeredBusinessSources = ref<any[]>([])
const registeredChannels = ref<any[]>([])
const detail = reactive({ visible: false, loading: false, row: null as any, items: [] as any[], settlements: [] as any[] })
const supplement = reactive({ visible: false })
const offset = reactive({ visible: false, saving: false, loading: false, row: null as any, payables: [] as any[], receivables: [] as any[], form: { amount: 0, settle_diff: false, capital_account_id: 0, remark: '', voucher_urls: '' } })
const receipt = reactive({ visible: false, saving: false, loading: false, row: null as any, items: [] as any[], form: { amount: 0, capital_account_id: 0, remark: '', voucher_urls: '' } })
const summary = computed(() => table.data.filter((row: any) => !isVoid(row)).reduce((acc, row: any) => {
    acc.count += 1
    acc.amount += Number(row.amount || 0)
    acc.settled += Number(row.settled_amount || 0)
    acc.remain += remain(row)
    return acc
}, { count: 0, amount: 0, settled: 0, remain: 0 }))
const financeTypeOptions = computed(() => sourceOptions('finance_type_key', 'finance_type_name', registeredFinanceCategories.value
    .filter((item: any) => Number(item.enabled ?? 1) === 1 && item.direction === 'income')
    .map((item: any) => ({ value: String(item.key), label: String(item.name) }))))
const businessSourceOptions = computed(() => sourceOptions('business_source_key', 'business_source_name', registeredBusinessSources.value
    .filter((item: any) => Number(item.enabled ?? 1) === 1 && item.direction === 'income')
    .map((item: any) => ({ value: String(item.key), label: String(item.name) }))))
const channelOptions = computed(() => sourceOptions('channel_code', 'channel_name', registeredChannels.value
    .filter((item: any) => Number(item.enabled ?? 1) === 1)
    .map((item: any) => ({ value: String(item.key), label: String(item.name) }))))
const offsetPayableChecked = computed(() => offset.payables.reduce((sum: number, row: any) => row.checked ? sum + Number(row.remain || 0) : sum, 0))
const offsetReceivableChecked = computed(() => offset.receivables.reduce((sum: number, row: any) => row.checked ? sum + Number(row.remain || 0) : sum, 0))
const offsetMax = computed(() => Math.max(0, Math.min(offsetPayableChecked.value, offsetReceivableChecked.value)))
const offsetAmount = computed(() => Math.min(Number(offset.form.amount || 0), offsetMax.value))
const offsetPayableAfter = computed(() => Math.max(0, offsetPayableChecked.value - offsetAmount.value))
const offsetReceivableAfter = computed(() => Math.max(0, offsetReceivableChecked.value - offsetAmount.value))
const offsetDiffAmount = computed(() => {
    if (offsetPayableAfter.value > 0 && offsetReceivableAfter.value > 0) return 0
    return Math.max(offsetPayableAfter.value, offsetReceivableAfter.value)
})
const offsetDiffDirection = computed(() => offsetPayableAfter.value > 0 ? 'payable' : (offsetReceivableAfter.value > 0 ? 'receivable' : ''))
const offsetDiffText = computed(() => {
    if (offsetDiffDirection.value === 'payable') return `我还需付他 ${money(offsetDiffAmount.value)}`
    if (offsetDiffDirection.value === 'receivable') return `他还需付我 ${money(offsetDiffAmount.value)}`
    return '无差额'
})
const offsetResultText = computed(() => {
    const payableAfter = offsetPayableAfter.value
    const receivableAfter = offsetReceivableAfter.value
    if (payableAfter > 0 && receivableAfter > 0) return '双方仍有余额'
    if (payableAfter > 0) return `我还需付他 ${money(payableAfter)}`
    if (receivableAfter > 0) return `他还需付我 ${money(receivableAfter)}`
    return '两边互相结清'
})
const canSubmitReceipt = computed(() => {
    if (!receipt.row) return false
    const amount = Number(receiptTotal.value || 0)
    return amount > 0 && amount <= receiptRemainTotal.value && Number(receipt.form.capital_account_id || 0) > 0
})
const receiptTotal = computed(() => receipt.items.reduce((sum: number, row: any) => {
    if (!row.checked) return sum
    return sum + Number(row.receipt_amount || 0)
}, 0))
const receiptRemainTotal = computed(() => receipt.items.reduce((sum: number, row: any) => sum + itemRemain(row), 0))
const isPurchaseReturnReceipt = computed(() => receipt.row?.source_type === 'purchase_return')
const receiptDialogTitle = computed(() => isPurchaseReturnReceipt.value ? '财务确认供货商退款' : '财务确认收款')
const receiptPartyLabel = computed(() => partyRoleLabel(receipt.row, isPurchaseReturnReceipt.value ? '退款供货商' : '收款客户'))
const receiptItemTitle = computed(() => isPurchaseReturnReceipt.value ? '按退货设备确认退款到账' : '按设备确认收款')
const receiptBusinessReason = computed(() => receipt.row?.source_meta?.business_reason || receipt.row?.business_reason || (isPurchaseReturnReceipt.value
    ? '采购退货已完成，已付款部分需要由供货商退回，财务在此确认实际到账。'
    : '销售出库形成应收，财务核对客户实际到账后完成核销。'))
const receiptSourceRow = computed(() => ({ ...(receipt.row || {}), business_reason: receiptBusinessReason.value }))

onMounted(() => {
    loadAccounts()
    loadStaffOptions()
    loadDynamicOptions()
})
useErpPageRefresh(loadList)

async function loadList() {
    table.loading = true
    try {
        const [startAt, endAt] = search.dateRange || []
        const res: any = await getErpReceivableList({
            ...search,
            status: activeStatus.value,
            start_at: Number(startAt || 0),
            end_at: Number(endAt || 0) ? Number(endAt) + 86399 : 0,
            dateRange: undefined,
            page: table.page,
            limit: table.limit
        })
        table.data = res?.data?.data || []
        table.total = res?.data?.total || 0
    } finally {
        table.loading = false
    }
}

async function loadAccounts() {
    const res: any = await getCapitalAccounts()
    accounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
}

async function loadStaffOptions() {
    const res: any = await getErpStaffOptions()
    staffOptions.value = res?.data?.users || []
}

async function loadDynamicOptions() {
    const [categories, sources, channels] = await Promise.allSettled([
        getErpFinanceCategories(),
        getErpBusinessSourceOptions(),
        getErpSaleChannelOptions()
    ])
    if (categories.status === 'fulfilled') registeredFinanceCategories.value = responseRows(categories.value)
    if (sources.status === 'fulfilled') registeredBusinessSources.value = responseRows(sources.value)
    if (channels.status === 'fulfilled') registeredChannels.value = responseRows(channels.value)
}

function responseRows(response: any) {
    const data = response?.data
    if (Array.isArray(data)) return data
    if (Array.isArray(data?.list)) return data.list
    return []
}

async function openDetail(row: any) {
    detail.row = row
    detail.items = []
    detail.settlements = []
    detail.visible = true
    detail.loading = true
    try {
        const res: any = await getErpReceivableInfo(row.id)
        const data = res?.data || row
        detail.row = { ...row, ...data }
        detail.items = data?.items || []
        detail.settlements = data?.settlements || []
    } finally {
        detail.loading = false
    }
}

function openSupplement() {
    if (!detail.row?.can_supplement_sale_detail) return
    supplement.visible = true
}

async function onSupplementSaved() {
    if (!detail.row?.id) return
    await openDetail(detail.row)
    await loadList()
}

async function openReceipt(row: any) {
    if (!canConfirmReceipt(row)) return
    receipt.row = row
    receipt.items = []
    receipt.form = { amount: remain(row), capital_account_id: accounts.value[0]?.id || 0, remark: '', voucher_urls: '' }
    receipt.visible = true
    receipt.loading = true
    try {
        const res: any = await getErpReceivableInfo(row.id)
        const data = res?.data || {}
        receipt.row = { ...row, ...data }
        const list = Array.isArray(data) ? data : (data?.items || [])
        receipt.items = list.map((item: any) => ({
            ...item,
            sale_item_id: Number(item.id || 0),
            asset_id: Number(item.asset_id || item.id || 0),
            sale_price: Number(item.sale_price || 0),
            allocated_settled: Number(item.allocated_settled || 0),
            receipt_amount: Math.max(0, Number(item.allocated_remain || 0)),
            checked: Number(item.allocated_remain || 0) > 0
        }))
        if (receipt.row?.source_type === 'purchase_return') {
            receipt.form.remark = '确认供货商退货退款到账'
        }
        syncReceiptFormAmount()
    } finally {
        receipt.loading = false
    }
}

async function openOffset(row: any) {
    if (!canOffset(row)) return
    offset.row = row
    offset.payables = []
    offset.receivables = []
    offset.form = { amount: 0, settle_diff: false, capital_account_id: 0, remark: '', voucher_urls: '' }
    offset.visible = true
    offset.loading = true
    try {
        const [payRes, recRes]: any = await Promise.all([
            getErpPayablePartyItems(row.party_id, { status: '', page: 1, limit: 200 }),
            getErpReceivableList({ party_id: row.party_id, page: 1, limit: 200 })
        ])
        offset.payables = (payRes?.data?.data || [])
            .map((it: any) => ({
                payable_id: Number(it.asset_payable_id || it.payable_id || 0),
                payable_no: it.payable_no || '',
                model: it.model || '',
                imei: it.imei || '',
                source_no: it.purchase_no || it.source_no || '',
                remain: Number(it.allocated_remain ?? (Number(it.total_cost || 0) - Number(it.allocated_paid || 0))),
                checked: true
            }))
            .filter((it: any) => it.payable_id > 0 && it.remain > 0)
        offset.receivables = (recRes?.data?.data || [])
            .map((it: any) => ({
                id: Number(it.id || 0),
                receivable_no: it.receivable_no || '',
                source_no: it.source_no || it.batch_no || '',
                remain: remain(it),
                checked: true
            }))
            .filter((it: any) => it.id > 0 && it.remain > 0)
        await fillOffsetReceivableDevices(offset.receivables)
        if (!offset.payables.length || !offset.receivables.length) {
            ElMessage.warning('该往来主体需同时存在剩余应付和剩余应收才能折账')
        }
        syncOffsetAmount()
    } finally {
        offset.loading = false
    }
}

function syncOffsetAmount() {
    const current = Number(offset.form.amount || 0)
    const max = Number(offsetMax.value || 0)
    if (current <= 0 || current > max) {
        offset.form.amount = Number(max.toFixed(2))
    }
}

async function fillOffsetReceivableDevices(rows: any[]) {
    await Promise.all((rows || []).map(async (row: any) => {
        try {
            const res: any = await getErpReceivableItems(row.id)
            const data = res?.data
            const list = Array.isArray(data) ? data : (data?.items || [])
            row.device_summary = list.slice(0, 2).map((item: any) => {
                const imei = item.imei ? `IMEI ${item.imei}` : ''
                return [item.model, imei].filter(Boolean).join(' / ')
            }).filter(Boolean).join('；')
            row.device_more = Math.max(0, list.length - 2)
        } catch (e) {
            row.device_summary = ''
            row.device_more = 0
        }
    }))
}

async function submitOffset() {
    if (!offset.row) return
    const payableIds = offset.payables.filter((r: any) => r.checked).map((r: any) => r.payable_id)
    const receivableIds = offset.receivables.filter((r: any) => r.checked).map((r: any) => r.id)
    if (!payableIds.length) return ElMessage.warning('请勾选要抵扣的应付')
    if (!receivableIds.length) return ElMessage.warning('请勾选要抵扣的应收')
    const amount = Number(offset.form.amount || 0)
    if (amount <= 0) return ElMessage.warning('请填写折账金额')
    if (amount > offsetMax.value + 0.001) return ElMessage.warning('折账金额不能大于可折金额')
    if (offset.form.settle_diff) {
        if (amount < offsetMax.value - 0.001) return ElMessage.warning('部分抵扣后双方仍有余额，不能本次结清差额')
        if (offsetDiffAmount.value <= 0) return ElMessage.warning('当前没有需要结清的差额')
        if (!offset.form.capital_account_id) return ElMessage.warning('请选择差额收付款账户')
    }
    const offsetConfirmed = await ElMessageBox.confirm(
        `确认对「${offset.row.party_name || '该往来主体'}」执行应收应付折账 ${money(amount)}。该操作不产生真实收付款，但会同时核销双方账目${offset.form.settle_diff ? `，并结清差额 ${money(offsetDiffAmount.value)}` : ''}；确认后不能直接删除流水。`,
        '确认应收应付折账',
        { type: 'warning', confirmButtonText: '确认折账并记账', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!offsetConfirmed) return
    offset.saving = true
    try {
        await confirmErpOffset({
            payable_ids: payableIds,
            receivable_ids: receivableIds,
            amount,
            settle_diff: offset.form.settle_diff,
            capital_account_id: offset.form.capital_account_id,
            remark: offset.form.remark
        })
        ElMessage.success(`已折账 ¥${amount.toFixed(2)}`)
        offset.visible = false
        await loadList()
        if (detail.visible) await openDetail(detail.row)
    } finally {
        offset.saving = false
    }
}

async function submitReceipt() {
    if (!receipt.row) return
    const amount = Number(receiptTotal.value || 0)
    if (amount <= 0) return ElMessage.warning('请选择要收款的设备并填写本次收款')
    if (amount > receiptRemainTotal.value) return ElMessage.warning('收款金额不能大于设备剩余应收')
    if (!receipt.form.capital_account_id) return ElMessage.warning('请选择收款账户')
    const account = accounts.value.find((item: any) => Number(item.id) === Number(receipt.form.capital_account_id))
    const receiptConfirmed = await ElMessageBox.confirm(
        `确认收到「${receipt.row.party_name || '该往来主体'}」${isPurchaseReturnReceipt.value ? '退货退款' : '款项'} ${money(amount)}，到账账户「${account?.account_name || '所选账户'}」，核销 ${receipt.items.filter((item: any) => item.checked).length} 台设备。确认后将写入资金流水和应收核销记录，不能直接删除。`,
        isPurchaseReturnReceipt.value ? '确认供货商退款到账' : '确认客户收款到账',
        { type: 'warning', confirmButtonText: isPurchaseReturnReceipt.value ? '确认退款到账' : '确认收款到账', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!receiptConfirmed) return
    receipt.saving = true
    try {
        await confirmErpReceipt(receipt.row.id, {
            ...receipt.form,
            amount,
            items: receipt.items.filter((item: any) => item.checked).map((item: any) => ({
                sale_item_id: item.sale_item_id,
                asset_id: item.asset_id,
                sale_price: item.sale_price,
                amount: item.receipt_amount
            }))
        })
        ElMessage.success('收款已确认')
        receipt.visible = false
        await loadList()
        await loadAccounts()
    } finally {
        receipt.saving = false
    }
}

function handleSearch() { table.page = 1; loadList() }
function handleReset() {
    search.imei = ''
    search.party_id = null
    searchPartyName.value = ''
    search.source_type = ''
    search.dateRange = []
    search.source_no = ''
    search.finance_type_key = ''
    search.business_source_key = ''
    search.channel_code = ''
    search.operator_uid = null
    search.can_offset = ''
    search.only_effective = ''
    activeStatus.value = ''
    handleSearch()
}
function remain(row: any) { return Math.max(0, Number(row.remain_amount ?? (Number(row.amount || 0) - Number(row.settled_amount || 0)))) }
function itemRemain(row: any) { return Math.max(0, Number(row.sale_price || 0) - Number(row.allocated_settled || 0)) }
function syncReceiptAmount(row: any) {
    const max = itemRemain(row)
    if (!row.checked) {
        row.receipt_amount = 0
    } else if (Number(row.receipt_amount || 0) <= 0 || Number(row.receipt_amount || 0) > max) {
        row.receipt_amount = Number(max.toFixed(2))
    }
    syncReceiptFormAmount()
}
function syncReceiptFormAmount() {
    receipt.form.amount = Number(receiptTotal.value.toFixed(2))
}
function isVoid(row: any) { return Boolean(row?.is_void) || row?.status === 'void' }
function canConfirmReceipt(row: any) { return !isVoid(row) && remain(row) > 0 && row.status !== 'settled' }
function canOffset(row: any) { return !isVoid(row) && Boolean(row.can_offset) && remain(row) > 0 }
function receivableRowClass({ row }: any) { return isVoid(row) ? 'receivable-row--void' : '' }
function contactText(row: any) { return [row.contact_name, row.contact_mobile || row.m_no].filter(Boolean).join(' / ') || '-' }
function partyRoleLabel(row: any, fallback: string) { return erpNamedLabel(row?.source_meta?.party_role_label, '', fallback) }
function sourceOptions(valueKey: string, labelKey: string, defaults: Array<{ value: string, label: string }>) {
    const map = new Map(defaults.map(item => [item.value, erpNamedLabel(item.label, item.value)]))
    table.data.forEach((row: any) => {
        const value = String(row?.source_meta?.[valueKey] || '').trim()
        if (value) map.set(value, erpNamedLabel(row?.source_meta?.[labelKey], value))
    })
    return Array.from(map, ([value, label]) => ({ value, label }))
}
function settleSummaryTagType(label: string) {
    if (label === '折账') return 'warning'
    if (label.includes('微信') || label.includes('支付宝') || label.includes('现金')) return 'success'
    if (label.includes('银行') || label.includes('银行卡')) return 'primary'
    return 'primary'
}
function deviceSummary(device: any) {
    return [
        device.spec,
        [device.warehouse_name, device.location_name].filter(Boolean).join(' / '),
        Number(device.sale_price || 0) > 0 ? `售价 ${money(device.sale_price)}` : '',
        Number(device.cost || 0) > 0 ? `成本 ${money(device.cost)}` : ''
    ].filter(Boolean).join(' · ') || '-'
}
function sourceTypeText(type: string) {
    const map: any = { sale: '销售应收', purchase_return: '采购退货应收' }
    return erpEnumLabel(type, map, '应收款')
}
function purchaseRefundModeLabel(mode: string) {
    const map: Record<string, string> = {
        none: '未付款，冲销原应付',
        cash: '当场收款已到账',
        receivable: '形成退款应收',
        offset: '往来折抵',
    }
    return map[mode] || '按退货结算规则处理'
}
function sourceOrderSummary(row: any) {
    const order = row.source_order || {}
    if (row.source_type === 'purchase_return') {
        return [
            order.purchase_no ? `原采购单 ${order.purchase_no}` : '',
            order.refund_mode ? `退款方式 ${purchaseRefundModeLabel(order.refund_mode)}` : '',
            order.remark ? `备注 ${order.remark}` : ''
        ].filter(Boolean).join(' · ')
    }
    return [
        order.sale_channel ? `渠道 ${order.sale_channel}` : '',
        order.salesman_name ? `开单人 ${order.salesman_name}` : '',
        order.settle_method ? `开单结算 ${order.settle_method}` : '',
        order.remark ? `备注 ${order.remark}` : ''
    ].filter(Boolean).join(' · ')
}
function returnReceiptItemReason(row: any) {
    const refund = Number(row?.refund_amount || row?.sale_price || 0)
    const offset = Number(row?.unpaid_offset_amount || 0)
    if (refund > 0 && offset > 0) return `未付款 ${money(offset)} 已冲销应付；已付款部分需收回 ${money(refund)}`
    if (refund > 0) return `采购款已支付，退货后需向供货商收回 ${money(refund)}`
    return `未付款部分已冲销应付 ${money(offset)}，本设备无需实际收款`
}
function statusMeta(status: string) {
    const map: any = { pending: { label: '待结算', type: 'warning' }, partial: { label: '部分结算', type: 'primary' }, settled: { label: '已结清', type: 'success' }, void: { label: '已作废', type: 'info' } }
    return map[status] || { label: '状态待确认', type: 'info' }
}
function money(value: any) { return `¥${Number(value || 0).toFixed(2)}` }
function formatTime(value: any) { return Number(value || 0) ? new Date(Number(value) * 1000).toLocaleString() : '-' }
function staffName(user: any) { return user?.name || user?.real_name || user?.username || '姓名未登记' }
</script>

<style scoped>
.summary-tile { border-radius: 8px; background: #f8fafc; padding: 14px 16px; }
.summary-label { color: #64748b; font-size: 13px; }
.summary-value { margin-top: 6px; color: #0f172a; font-size: 22px; font-weight: 700; }
.responsible-line { display:flex; min-width:0; align-items:center; gap:7px; }
.responsible-line > span:first-child { flex:none; width:28px; color:#94a3b8; font-size:12px; }
.receipt-business-reason { display:flex; gap:10px; margin-top:10px; padding:9px 11px; border:1px solid #fed7aa; border-radius:7px; background:#fff7ed; line-height:1.55; }
.receipt-business-reason span { flex:none; color:#9a3412; font-size:12px; font-weight:650; }
.receipt-business-reason b { color:#7c2d12; font-size:12px; font-weight:500; }
.void-stamp { display:inline-block; border:2px solid #94a3b8; border-radius:4px; padding:5px 8px; color:#64748b; font-size:15px; font-weight:800; letter-spacing:2px; transform:rotate(-5deg); }
:deep(.receivable-row--void td.el-table__cell) { background:#f8fafc !important; color:#94a3b8; }
:deep(.receivable-row--void .el-tag) { filter:grayscale(1); opacity:.72; }
</style>
