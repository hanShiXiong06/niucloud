<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">应付款</div>
                    <div class="mt-1 text-sm text-gray-500">以供应商和采购批次为账单单位，财务确认付款或折账后才正式结算。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Tickets" @click="openSettlement">结算明细</el-button>
                    <el-button :icon="Tickets" @click="openLedger">账目流水</el-button>
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile">
                    <div class="summary-label">应付批次</div>
                    <div class="summary-value">{{ summary.count }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">应付总额</div>
                    <div class="summary-value">{{ money(summary.amount) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">已结算</div>
                    <div class="summary-value text-green-600">{{ money(summary.settled) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">剩余应付</div>
                    <div class="summary-value text-orange-600">{{ money(summary.remain) }}</div>
                </div>
            </div>

            <el-tabs v-model="activeStatus" class="mt-5" @tab-change="handleSearch">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="待结算" name="pending" />
                <el-tab-pane label="部分结算" name="partial" />
                <el-tab-pane label="已结清" name="settled" />
            </el-tabs>

            <el-form :inline="true" class="mt-1" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="供应商 / 手机号 / IMEI / 型号 / 采购单" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="时间">
                    <el-date-picker v-model="dateRange" type="daterange" value-format="X" start-placeholder="开始日期" end-placeholder="结束日期" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column label="供应商/往来主体" min-width="190">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900">{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ contactText(row) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="采购批次" min-width="230">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900">{{ row.batch_no || row.purchase_no || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.purchase_channel || '未填写渠道' }} · {{ row.payable_count || 0 }} 台</div>
                        <div class="mt-1 text-xs text-gray-500">采购时间 {{ formatTime(row.purchase_at || row.latest_at) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="账目" min-width="240">
                    <template #default="{ row }">
                        <div>应付 <span class="font-medium">{{ money(row.amount) }}</span></div>
                        <div class="mt-1 text-xs text-gray-500">已结算 {{ money(row.settled_amount) }} · 剩余 {{ money(remain(row)) }}</div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <el-tag size="small" effect="plain" type="info">开单：{{ row.opening_settle_method || row.settle_method || '-' }}</el-tag>
                            <el-tag v-if="!(row.settle_summary_items || []).length" size="small" effect="plain" type="warning">待清算</el-tag>
                            <el-tag v-for="item in row.settle_summary_items" :key="item.label" size="small" effect="plain" :type="settleSummaryTagType(item.label)">
                                {{ item.label }} {{ money(item.amount) }}
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="采购员" min-width="130">
                    <template #default="{ row }">{{ row.purchaser_name || '-' }}</template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="partyStatusMeta(row).type">{{ partyStatusMeta(row).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="220" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openItems(row)">查看列表</el-button>
                        <el-button v-if="canConfirmPay(row)" type="primary" link @click="openPay(row)">确认付款</el-button>
                        <el-button v-if="canConfirmPay(row)" type="success" link @click="openPartyPay(row)">整体付款</el-button>
                        <el-button v-if="canOffset(row)" type="warning" link @click="openOffset(row)">折账</el-button>
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

        <el-dialog v-model="pay.visible" title="财务确认付款" width="1080px">
            <div v-if="pay.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>付款对象：<span class="font-medium text-gray-900">{{ pay.row.party_name }}</span></div>
                <div class="mt-1">采购批次：{{ pay.row.batch_no || pay.row.purchase_no || '-' }}</div>
            </div>
            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded bg-red-50 px-3 py-2">
                    <div class="text-xs text-gray-500">本次付款</div>
                    <div class="mt-1 font-medium text-red-600">{{ money(paySelectedTotal) }}</div>
                </div>
                <div class="rounded bg-orange-50 px-3 py-2">
                    <div class="text-xs text-gray-500">付款后仍欠</div>
                    <div class="mt-1 font-medium text-orange-600">{{ money(payAfterRemainTotal) }}</div>
                </div>
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">已选设备</div>
                    <div class="mt-1 font-medium text-gray-900">{{ paySelectedCount }} 台</div>
                </div>
            </div>
            <el-form label-width="100px">
                <el-form-item label="付款账户">
                    <el-select v-model="pay.form.capital_account_id" clearable class="w-full" placeholder="选择银行卡/微信/支付宝">
                        <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="设备明细" required>
                    <el-table :data="pay.items" v-loading="pay.loading" size="small" max-height="320" class="w-full">
                        <el-table-column width="54">
                            <template #default="{ row }">
                                <el-checkbox v-model="row.checked" :disabled="Number(row.allocated_remain || 0) <= 0" @change="syncPayAmount(row)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="设备" min-width="230">
                            <template #default="{ row }">
                                <div class="font-medium">{{ row.model || '-' }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="应付金额" width="120" align="right">
                            <template #default="{ row }">{{ money(row.total_cost || row.payable_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="已付" width="110" align="right">
                            <template #default="{ row }">{{ money(row.allocated_paid) }}</template>
                        </el-table-column>
                        <el-table-column label="剩余应付" width="120" align="right">
                            <template #default="{ row }">{{ money(row.allocated_remain) }}</template>
                        </el-table-column>
                        <el-table-column label="本次付款" width="160">
                            <template #default="{ row }">
                                <el-input-number
                                    v-model="row.pay_amount"
                                    :disabled="!row.checked"
                                    :min="0"
                                    :max="Number(row.allocated_remain || 0)"
                                    :precision="2"
                                    :controls="false"
                                    class="!w-[130px]"
                                />
                            </template>
                        </el-table-column>
                        <el-table-column label="付后剩余" width="120" align="right">
                            <template #default="{ row }">{{ money(payAfterRemain(row)) }}</template>
                        </el-table-column>
                    </el-table>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="pay.form.remark" type="textarea" :rows="2" placeholder="如：已核对银行卡流水" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="pay.visible = false">取消</el-button>
                <el-button type="primary" :loading="pay.saving" :disabled="!canSubmitPay" @click="submitPay">确认付款并记账</el-button>
            </template>
        </el-dialog>

        <!-- 整体付款对话框（一次性付清该供应商全部剩余应付） -->
        <el-dialog v-model="partyPay.visible" title="整体付款" width="480px">
            <div v-if="partyPay.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>付款对象：<span class="font-medium text-gray-900">{{ partyPay.row.party_name }}</span></div>
                <div class="mt-1">剩余应付总额：<span class="font-medium text-red-600">{{ money(partyPay.row.remain_amount) }}</span></div>
            </div>
            <el-form label-width="100px">
                <el-form-item label="付款金额" required>
                    <el-input-number
                        v-model="partyPay.form.amount"
                        :min="0.01"
                        :max="Number(partyPay.row?.remain_amount || 0)"
                        :precision="2"
                        :controls="false"
                        class="!w-full"
                    />
                    <div class="mt-1 text-xs text-gray-400">默认填入全部剩余应付，可修改为部分付款</div>
                </el-form-item>
                <el-form-item label="付款账户" required>
                    <el-select v-model="partyPay.form.capital_account_id" clearable class="w-full" placeholder="选择银行卡/微信/支付宝">
                        <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="partyPay.form.remark" placeholder="如：整体结清本批欠款" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="partyPay.visible = false">取消</el-button>
                <el-button
                    type="primary"
                    :loading="partyPay.saving"
                    :disabled="!partyPay.form.amount || !partyPay.form.capital_account_id"
                    @click="submitPartyPay"
                >确认付款并记账</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="offset.visible" title="应收应付折账" width="920px">
            <div v-if="offset.row" class="mb-4 rounded bg-amber-50 px-4 py-3 text-sm text-gray-600">
                <div>往来单位：<span class="font-medium text-gray-900">{{ offset.row.party_name }}</span></div>
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
                        <el-table-column label="来源单" min-width="180">
                            <template #default="{ row }">
                                <div class="text-sm">{{ row.source_no || '应收' }}</div>
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
                    <el-input v-model.trim="offset.form.remark" type="textarea" :rows="2" placeholder="如：同行往来对冲" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="offset.visible = false">取消</el-button>
                <el-button type="warning" :loading="offset.saving" :disabled="offsetMax <= 0" @click="submitOffset">确认抵扣折账</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="items.visible" :title="`${items.row?.batch_no || '采购批次'} · 应付批次明细`" size="86%">
            <div v-if="items.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>供应商：<span class="font-medium text-gray-900">{{ items.row.party_name || '-' }}</span></div>
                <div class="mt-1">批次：{{ items.row.batch_no || '-' }} · 应付 {{ money(items.row.amount) }} · 已结算 {{ money(items.row.settled_amount) }} · 剩余 {{ money(remain(items.row)) }}</div>
            </div>
            <div class="mb-2 font-medium text-gray-900">设备明细</div>
            <el-table :data="items.data" v-loading="items.loading" size="large">
                <el-table-column label="设备" min-width="240">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-400">资产号：{{ row.asset_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="位置" min-width="150">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column prop="purchase_no" label="采购单" min-width="170" />
                <el-table-column label="应付" min-width="180" align="right">
                    <template #default="{ row }">
                        <div>{{ money(row.total_cost) }}</div>
                        <div class="mt-1 text-xs text-gray-500">已结算 {{ money(row.allocated_paid) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="剩余" width="130" align="right">
                    <template #default="{ row }">{{ money(row.allocated_remain) }}</template>
                </el-table-column>
                <el-table-column label="状态" width="110">
                    <template #default="{ row }"><el-tag :type="statusMeta(row.payable_status).type">{{ statusMeta(row.payable_status).label }}</el-tag></template>
                </el-table-column>
                <el-table-column label="采购时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.purchase_at) }}</template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination v-model:current-page="items.page" v-model:page-size="items.limit" layout="total, prev, pager, next" :total="items.total" @current-change="loadItems" />
            </div>

            <div class="mb-2 mt-6 font-medium text-gray-900">结算明细</div>
            <el-table :data="items.settlements" v-loading="items.loading" size="small" empty-text="暂无结算记录">
                <el-table-column label="结款方式" min-width="180">
                    <template #default="{ row }">
                        <div class="font-medium" :class="row.settlement_type === 'offset' ? 'text-amber-600' : 'text-gray-900'">{{ row.settlement_type_text || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.pay_method_text || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-400">{{ row.settlement_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="本次结算" width="130" align="right">
                    <template #default="{ row }">{{ money(row.applied_amount) }}</template>
                </el-table-column>
                <el-table-column label="确认信息" min-width="190">
                    <template #default="{ row }">
                        <div>{{ row.operator_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ formatTime(row.confirmed_at) }}</div>
                        <div v-if="row.remark" class="mt-1 text-xs text-gray-500">{{ row.remark }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="关联设备 / 账目" min-width="330">
                    <template #default="{ row }">
                        <div v-for="target in row.targets" :key="`${target.target_type}_${target.target_id}`" class="mb-2 rounded bg-gray-50 px-2 py-2 text-xs text-gray-600">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ target.target_type_text }} {{ target.target_no || target.source_no || '-' }}</span>
                                <span class="font-medium text-gray-900">结算 {{ money(target.applied_amount) }}</span>
                            </div>
                            <div v-for="device in target.devices" :key="`${target.target_type}_${target.target_id}_${device.asset_id}_${device.imei}`" class="mt-2 border-l-2 border-gray-200 pl-2">
                                <div class="font-medium text-gray-900">{{ device.model || '-' }}</div>
                                <div class="mt-1 text-gray-500">{{ device.imei ? `IMEI ${device.imei}` : device.asset_no || '-' }}</div>
                                <div class="mt-1 text-gray-500">{{ deviceSummary(device) }}</div>
                            </div>
                            <div v-if="!target.devices?.length" class="mt-1 text-gray-400">{{ target.source_no || '-' }}</div>
                        </div>
                        <div v-if="!row.targets?.length" class="text-xs text-gray-400">-</div>
                    </template>
                </el-table-column>
                <el-table-column label="账户收付明细" min-width="260">
                    <template #default="{ row }">
                        <div v-for="ledger in row.money_ledgers" :key="ledger.ledger_no" class="mb-2 rounded bg-gray-50 px-2 py-2 text-xs text-gray-600">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ ledger.capital_account_name || row.capital_account_name || '-' }}</span>
                                <span :class="ledger.direction === 'in' ? 'text-green-600' : 'text-red-600'">{{ ledger.direction === 'in' ? '收款' : '付款' }} {{ money(ledger.amount) }}</span>
                            </div>
                            <div class="mt-1 text-gray-400">{{ ledger.ledger_no }} · 账户余额 {{ money(ledger.balance_after) }}</div>
                        </div>
                        <div v-if="!row.money_ledgers?.length" class="text-xs text-gray-400">折账不产生资金流水</div>
                    </template>
                </el-table-column>
            </el-table>
        </el-drawer>

        <el-drawer v-model="ledger.visible" title="账目流水" size="72%">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <el-input v-model.trim="ledger.keyword" clearable class="!w-[260px]" placeholder="流水号 / 往来单位 / 单号 / 备注" @keyup.enter="loadLedger" />
                <el-button type="primary" @click="loadLedger">查询</el-button>
                <el-button @click="ledger.keyword = ''; loadLedger()">重置</el-button>
            </div>
            <el-table :data="ledger.data" v-loading="ledger.loading" size="large">
                <el-table-column prop="ledger_no" label="流水号" min-width="180" />
                <el-table-column label="业务" width="110">
                    <template #default="{ row }"><el-tag effect="plain">{{ bizText(row.biz_type) }}</el-tag></template>
                </el-table-column>
                <el-table-column label="方向" width="100">
                    <template #default="{ row }">{{ row.direction === 'increase' ? '增加' : row.direction === 'decrease' ? '减少' : '-' }}</template>
                </el-table-column>
                <el-table-column label="金额" width="130" align="right">
                    <template #default="{ row }">{{ money(row.amount) }}</template>
                </el-table-column>
                <el-table-column prop="party_name" label="往来单位" min-width="150" />
                <el-table-column prop="source_no" label="来源单号" min-width="160" />
                <el-table-column prop="remark" label="备注" min-width="180" />
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination
                    v-model:current-page="ledger.page"
                    v-model:page-size="ledger.limit"
                    layout="total, prev, pager, next"
                    :total="ledger.total"
                    @current-change="loadLedger"
                />
            </div>
        </el-drawer>

        <el-drawer v-model="settle.visible" title="结算明细 · 每笔账怎么结的" size="80%">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <el-input v-model.trim="settle.keyword" clearable class="!w-[240px]" placeholder="结算单号 / 往来单位 / 账户 / 备注" @keyup.enter="reloadSettlement" />
                <el-select v-model="settle.type" clearable class="!w-[150px]" placeholder="结算方式" @change="reloadSettlement">
                    <el-option label="付款" value="payment" />
                    <el-option label="收款" value="receipt" />
                    <el-option label="折账" value="offset" />
                </el-select>
                <el-button type="primary" @click="reloadSettlement">查询</el-button>
                <el-button @click="settle.keyword = ''; settle.type = ''; reloadSettlement()">重置</el-button>
                <span v-if="settle.assetLabel" class="text-sm text-gray-500">当前筛选设备：{{ settle.assetLabel }}
                    <el-button link type="primary" @click="settle.assetId = 0; settle.assetLabel = ''; reloadSettlement()">清除</el-button>
                </span>
            </div>
            <el-table :data="settle.data" v-loading="settle.loading" size="large">
                <el-table-column prop="settlement_no" label="结算单号" min-width="180" />
                <el-table-column label="结算方式" width="110">
                    <template #default="{ row }">
                        <el-tag :type="settleTypeTag(row.settlement_type)" effect="plain">{{ row.settlement_type_text || row.settlement_type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="收付方式 / 账户" min-width="160">
                    <template #default="{ row }">
                        <span :class="row.settlement_type === 'offset' ? 'text-amber-600' : 'text-gray-900'">{{ row.pay_method_text || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="金额" width="130" align="right">
                    <template #default="{ row }">{{ money(row.amount) }}</template>
                </el-table-column>
                <el-table-column prop="party_name" label="往来单位" min-width="140" />
                <el-table-column label="关联设备 / 抵扣明细" min-width="330">
                    <template #default="{ row }">
                        <div v-for="(l, i) in (row.links || [])" :key="i" class="mb-2 rounded bg-gray-50 px-2 py-2 text-xs text-gray-600">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ l.target_type_text }} {{ l.target_no || l.source_no || `#${l.target_id}` }}</span>
                                <span class="font-medium text-gray-900">抵 {{ money(l.applied_amount) }}</span>
                            </div>
                            <div v-for="device in l.devices" :key="`${l.target_type}_${l.target_id}_${device.asset_id}_${device.imei}`" class="mt-2 border-l-2 border-gray-200 pl-2">
                                <div class="font-medium text-gray-900">{{ device.model || '-' }}</div>
                                <div class="mt-1 text-gray-500">{{ device.imei ? `IMEI ${device.imei}` : device.asset_no || '-' }}</div>
                                <div class="mt-1 text-gray-500">{{ deviceSummary(device) }}</div>
                            </div>
                        </div>
                        <span v-if="!(row.links || []).length" class="text-xs text-gray-400">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="operator_name" label="经手人" width="110" />
                <el-table-column label="确认时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.confirmed_at) }}</template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination
                    v-model:current-page="settle.page"
                    v-model:page-size="settle.limit"
                    layout="total, prev, pager, next"
                    :total="settle.total"
                    @current-change="loadSettlement"
                />
            </div>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Search, Tickets } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { confirmErpOffset, confirmErpPartyPayment, confirmErpPayableItemsPayment, getErpAccountLedger, getErpPayableList, getErpPayablePartyItems, getErpReceivableItems, getErpReceivableList, getErpSettlementList } from '@/addon/hsx_erp/api/erp'

const activeStatus = ref('')
const search = reactive({ keyword: '' })
const dateRange = ref<any[]>([])
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const accounts = ref<any[]>([])
const pay = reactive({ visible: false, saving: false, loading: false, row: null as any, items: [] as any[], form: { capital_account_id: 0, remark: '' } })
const items = reactive({ visible: false, loading: false, row: null as any, data: [] as any[], settlements: [] as any[], page: 1, limit: 15, total: 0 })
const ledger = reactive({ visible: false, loading: false, keyword: '', data: [] as any[], page: 1, limit: 15, total: 0 })
const offset = reactive({ visible: false, saving: false, loading: false, row: null as any, payables: [] as any[], receivables: [] as any[], form: { amount: 0, settle_diff: false, capital_account_id: 0, remark: '' } })
const settle = reactive({ visible: false, loading: false, keyword: '', type: '', assetId: 0, assetLabel: '', data: [] as any[], page: 1, limit: 15, total: 0 })
const partyPay = reactive({ visible: false, saving: false, row: null as any, form: { amount: 0, capital_account_id: 0, remark: '' } })

const summary = computed(() => table.data.reduce((acc, row: any) => {
    acc.count += 1
    acc.amount += Number(row.amount || 0)
    acc.settled += Number(row.settled_amount || 0)
    acc.remain += remain(row)
    return acc
}, { count: 0, amount: 0, settled: 0, remain: 0 }))

const paySelectedTotal = computed(() => pay.items.reduce((sum: number, row: any) => {
    if (!row.checked) return sum
    return sum + Number(row.pay_amount || 0)
}, 0))
const paySelectedCount = computed(() => pay.items.filter((row: any) => row.checked && Number(row.pay_amount || 0) > 0).length)
const payAfterRemainTotal = computed(() => pay.items.reduce((sum: number, row: any) => sum + payAfterRemain(row), 0))
const canSubmitPay = computed(() => paySelectedTotal.value > 0 && Number(pay.form.capital_account_id || 0) > 0)

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

onMounted(() => {
    loadList()
    loadAccounts()
})

async function loadList() {
    table.loading = true
    try {
        const res: any = await getErpPayableList({
            ...search,
            status: activeStatus.value,
            start_at: Number(dateRange.value?.[0] || 0),
            end_at: Number(dateRange.value?.[1] || 0) ? Number(dateRange.value[1]) + 86399 : 0,
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

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    search.keyword = ''
    activeStatus.value = ''
    dateRange.value = []
    handleSearch()
}

async function openPay(row: any) {
    if (!canConfirmPay(row)) return
    pay.row = row
    pay.items = []
    pay.form = { capital_account_id: accounts.value[0]?.id || 0, remark: '' }
    pay.visible = true
    pay.loading = true
    try {
        const res: any = await getErpPayablePartyItems(row.party_id, {
            ...search,
            status: '',
            purchase_order_id: row.purchase_order_id || 0,
            start_at: Number(dateRange.value?.[0] || 0),
            end_at: Number(dateRange.value?.[1] || 0) ? Number(dateRange.value[1]) + 86399 : 0,
            page: 1,
            limit: 200
        })
        pay.items = (res?.data?.data || [])
            .filter((item: any) => Number(item.allocated_remain || 0) > 0 && Number(item.asset_payable_id || 0) > 0)
            .map((item: any) => ({ ...item, checked: true, pay_amount: Number(item.allocated_remain || 0) }))
        if (!pay.items.length && remain(row) > 0) {
            ElMessage.warning('当前应付缺少设备级账目，请用新采购开单数据验证')
        }
    } finally {
        pay.loading = false
    }
}

async function submitPay() {
    if (!pay.row) return
    const selected = pay.items
        .filter((row: any) => row.checked && Number(row.pay_amount || 0) > 0)
        .map((row: any) => ({ payable_id: Number(row.payable_id || 0), amount: Number(row.pay_amount || 0) }))
    if (!selected.length) return ElMessage.warning('请选择要付款的设备')
    if (paySelectedTotal.value <= 0) return ElMessage.warning('请填写付款金额')
    if (!pay.form.capital_account_id) return ElMessage.warning('请选择付款账户')
    if (pay.items.some((row: any) => row.checked && Number(row.pay_amount || 0) > Number(row.allocated_remain || 0))) return ElMessage.warning('本次付款不能大于设备剩余应付')
    pay.saving = true
    try {
        await confirmErpPayableItemsPayment(pay.row.party_id, { ...pay.form, items: selected })
        ElMessage.success('付款已确认')
        pay.visible = false
        await loadList()
        if (items.visible) await loadItems()
        await loadAccounts()
    } finally {
        pay.saving = false
    }
}

function payAfterRemain(row: any) {
    if (!row.checked) return Number(row.allocated_remain || 0)
    return Math.max(0, Number(row.allocated_remain || 0) - Number(row.pay_amount || 0))
}

function syncPayAmount(row: any) {
    if (!row.checked) {
        row.pay_amount = 0
        return
    }
    const remainAmount = Number(row.allocated_remain || 0)
    if (Number(row.pay_amount || 0) <= 0 || Number(row.pay_amount || 0) > remainAmount) {
        row.pay_amount = Number(remainAmount.toFixed(2))
    }
}

function openPartyPay(row: any) {
    partyPay.row = row
    partyPay.form = {
        amount: Number((row.remain_amount || 0).toFixed(2)),
        capital_account_id: 0,
        remark: '',
    }
    partyPay.visible = true
}

async function submitPartyPay() {
    if (!partyPay.row) return
    if (!partyPay.form.amount || partyPay.form.amount <= 0) return ElMessage.warning('请填写付款金额')
    if (!partyPay.form.capital_account_id) return ElMessage.warning('请选择付款账户')
    partyPay.saving = true
    try {
        await confirmErpPartyPayment(partyPay.row.party_id, partyPay.form)
        ElMessage.success('整体付款已确认')
        partyPay.visible = false
        await loadList()
        await loadAccounts()
    } finally {
        partyPay.saving = false
    }
}

async function openOffset(row: any) {
    if (!canOffset(row)) return
    offset.row = row
    offset.payables = []
    offset.receivables = []
    offset.form = { amount: 0, settle_diff: false, capital_account_id: 0, remark: '' }
    offset.visible = true
    offset.loading = true
    try {
        const [payRes, recRes]: any = await Promise.all([
            getErpPayablePartyItems(row.party_id, { status: '', purchase_order_id: row.purchase_order_id || 0, page: 1, limit: 200 }),
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
                source_no: it.source_no || '',
                remain: Math.max(0, Number(it.amount || 0) - Number(it.settled_amount || 0)),
                checked: true
            }))
            .filter((it: any) => it.id > 0 && it.remain > 0)
        await fillOffsetReceivableDevices(offset.receivables)
        if (!offset.payables.length || !offset.receivables.length) {
            ElMessage.warning('该往来单位需同时存在剩余应付和剩余应收才能折账')
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
        const recRemainAfter = Math.max(0, offsetReceivableChecked.value - amount)
        const payRemainAfter = Math.max(0, offsetPayableChecked.value - amount)
        ElMessage.success(`已折账 ¥${amount.toFixed(2)}；勾选应收剩余 ¥${recRemainAfter.toFixed(2)}、应付剩余 ¥${payRemainAfter.toFixed(2)}`)
        offset.visible = false
        await loadList()
        if (items.visible) await loadItems()
    } finally {
        offset.saving = false
    }
}

function openItems(row: any) {
    items.row = row
    items.page = 1
    items.settlements = []
    items.visible = true
    loadItems()
}

async function loadItems() {
    if (!items.row?.party_id) return
    items.loading = true
    try {
        const res: any = await getErpPayablePartyItems(items.row.party_id, {
            ...search,
            status: activeStatus.value,
            purchase_order_id: items.row.purchase_order_id || 0,
            start_at: Number(dateRange.value?.[0] || 0),
            end_at: Number(dateRange.value?.[1] || 0) ? Number(dateRange.value[1]) + 86399 : 0,
            page: items.page,
            limit: items.limit
        })
        items.data = res?.data?.data || []
        items.settlements = collectSettlements(items.data)
        items.total = res?.data?.total || 0
    } finally {
        items.loading = false
    }
}

function collectSettlements(rows: any[]) {
    const map = new Map<string, any>()
    ;(rows || []).forEach((item: any) => {
        ;(item.settlements || []).forEach((settlement: any) => {
            const key = `${settlement.settlement_id}_${settlement.applied_amount}_${item.payable_id}`
            if (!map.has(key)) map.set(key, settlement)
        })
    })
    return Array.from(map.values())
}

function openLedger() {
    ledger.visible = true
    loadLedger()
}

async function loadLedger() {
    ledger.loading = true
    try {
        const res: any = await getErpAccountLedger({ keyword: ledger.keyword, page: ledger.page, limit: ledger.limit })
        ledger.data = res?.data?.data || []
        ledger.total = res?.data?.total || 0
    } finally {
        ledger.loading = false
    }
}

function openSettlement() {
    settle.visible = true
    settle.assetId = 0
    settle.assetLabel = ''
    reloadSettlement()
}

function reloadSettlement() {
    settle.page = 1
    loadSettlement()
}

async function loadSettlement() {
    settle.loading = true
    try {
        const res: any = await getErpSettlementList({
            keyword: settle.keyword,
            settlement_type: settle.type,
            asset_id: settle.assetId || 0,
            page: settle.page,
            limit: settle.limit
        })
        settle.data = res?.data?.data || []
        settle.total = res?.data?.total || 0
    } finally {
        settle.loading = false
    }
}

function settleTypeTag(type: string) {
    const map: any = { payment: 'primary', receipt: 'success', offset: 'warning' }
    return map[type] || 'info'
}

function remain(row: any) {
    return Math.max(0, Number(row.amount || 0) - Number(row.settled_amount || 0))
}

function canConfirmPay(row: any) {
    return remain(row) > 0
}

function canOffset(row: any) {
    return Boolean(row.can_offset) && remain(row) > 0
}

function statusMeta(status: string) {
    const map: any = {
        pending: { label: '待付款', type: 'warning' },
        partial: { label: '部分付款', type: 'primary' },
        settled: { label: '已结清', type: 'success' }
    }
    return map[status] || { label: status || '-', type: 'info' }
}

function partyStatusMeta(row: any) {
    const amount = Number(row.amount || 0)
    const settled = Number(row.settled_amount || 0)
    if (amount > 0 && settled >= amount) return { label: '已结清', type: 'success' }
    if (settled > 0) return { label: '部分付款', type: 'primary' }
    return { label: '待付款', type: 'warning' }
}

function contactText(row: any) {
    return [row.contact_name, row.contact_mobile || row.m_no].filter(Boolean).join(' / ') || '-'
}

function bizText(type: string) {
    const map: any = { purchase: '采购', payment: '付款', adjust: '调整', offset: '折账', sale: '销售', receipt: '收款' }
    return map[type] || type || '-'
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
</style>
