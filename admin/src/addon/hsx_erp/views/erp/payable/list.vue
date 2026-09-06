<template>
    <HsxPage padding="none" class="main-container">
        <el-card class="!border-none" shadow="never">
            <HsxTitle size="page" collapsible-subtitle class="mb-4">
                <template #default>应付款</template>
                <template #subtitle>统一处理采购、整备、销售退货与售后补差等支出；按业务来源和设备逐笔核对。</template>
                <template #extra><div class="flex gap-2 flex-wrap">
                        <el-button :icon="Tickets" @click="openSettlement">结算明细</el-button>
                        <el-button :icon="Tickets" @click="openLedger">账目流水</el-button>
                        <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                    </div></template>
            </HsxTitle>

            <ErpRoleFocus :items="payableRoleFocus" />

            <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
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

            <HsxSearchPanel>
                <el-form :inline="true" class="mt-1" @submit.prevent>
                    <el-form-item label="关键词">
                        <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="往来主体 / IMEI / 型号 / 来源单" @keyup.enter="handleSearch" />
                    </el-form-item>
                    <el-form-item label="时间">
                        <el-date-picker v-model="dateRange" type="daterange" value-format="X" start-placeholder="开始日期" end-placeholder="结束日期" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                        <el-button :icon="Filter" @click="advancedVisible = !advancedVisible">{{ advancedVisible ? '收起条件' : '更多条件' }}</el-button>
                        <el-button @click="handleReset">重置</el-button>
                    </el-form-item>
                </el-form>
            </HsxSearchPanel>
            <HsxSearchPanel v-show="advancedVisible">
                <el-form :inline="true" class="" @submit.prevent>
                    <el-form-item label="往来主体">
                        <ErpPartySelect v-model="search.party_id" v-model:party-name="search.party_name" party-type="all" :allow-create="false" clearable class="!w-[220px]" placeholder="选择供货商、客户或服务商" />
                    </el-form-item>
                    <el-form-item label="来源单">
                        <el-input v-model.trim="search.source_no" clearable class="!w-[190px]" placeholder="采购单 / 应付单" @keyup.enter="handleSearch" />
                    </el-form-item>
                    <el-form-item label="手机号">
                        <el-input v-model.trim="search.contact_mobile" clearable class="!w-[170px]" placeholder="联系人手机号" @keyup.enter="handleSearch" />
                    </el-form-item>
                    <el-form-item label="支出类型">
                        <el-select v-model="search.finance_type_key" clearable filterable class="!w-[170px]" placeholder="全部支出类型">
                            <el-option v-for="item in financeTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="业务渠道">
                        <el-select v-model="search.channel_code" clearable filterable class="!w-[170px]" placeholder="全部渠道">
                            <el-option v-for="item in channelOptions" :key="item.value" :label="item.label" :value="item.value" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-checkbox v-model="search.can_offset" true-label="1" false-label="">只看可折账</el-checkbox>
                    </el-form-item>
                </el-form>
            </HsxSearchPanel>

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column label="往来主体" min-width="210">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900"><ErpOverflowText :text="row.party_name" max-width="190px" /></div>
                        <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                            <el-tag size="small" effect="plain" type="info">{{ partyRoleLabel(row, '付款对象') }}</el-tag>
                            <ErpOverflowText :text="contactText(row)" max-width="140px" />
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="款项来源" min-width="310">
                    <template #default="{ row }">
                        <ErpFinanceSourceMeta :row="row" compact default-direction="expense" />
                        <div class="mt-1 text-xs text-gray-400">{{ row.payable_count || 0 }} 笔账 · 业务时间 {{ formatTime(row.purchase_at || row.latest_at) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="账目" min-width="240">
                    <template #default="{ row }">
                        <div>应付 <span class="font-medium">{{ money(row.amount) }}</span></div>
                        <div class="mt-1 text-xs text-gray-500">已结算 {{ money(row.settled_amount) }} · 剩余 {{ money(remain(row)) }}</div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <el-tag size="small" effect="plain" type="info">结算约定：{{ row.opening_settle_method || row.settle_method || '-' }}</el-tag>
                            <el-tag v-if="!(row.settle_summary_items || []).length" size="small" effect="plain" :type="remain(row) > 0 ? 'warning' : 'success'">{{ remain(row) > 0 ? '待清算' : '已结清' }}</el-tag>
                            <el-tag v-for="item in row.settle_summary_items" :key="item.label" size="small" effect="plain" :type="settleSummaryTagType(item.label)">
                                {{ item.label }} {{ money(item.amount) }}
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="业务 / 财务负责人" min-width="150">
                    <template #default="{ row }">
                        <div><ErpOverflowText :text="row.business_operator_name || row.purchaser_name" max-width="135px" /></div>
                        <div v-if="row.task_assignee_name" class="mt-1 text-xs text-gray-500">财务 {{ row.task_assignee_name }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="partyStatusMeta(row).type">{{ partyStatusMeta(row).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="180" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openItems(row)">查看明细</el-button>
                        <el-button v-if="canConfirmPay(row)" type="primary" link @click="openPay(row)">打款</el-button>
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

        <HsxDialog :confirm-loading="pay.saving" v-model="pay.visible" :title="isNonDevicePay ? '财务确认经营付款' : '财务确认付款'" width="1080px" :destroy-on-close="false">
            <div v-if="pay.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>{{ partyRoleLabel(pay.row, '付款对象') }}：<span class="font-medium text-gray-900">{{ pay.row.party_name }}</span></div>
                <ErpFinanceSourceMeta :row="pay.row" class="mt-3" default-direction="expense" />
            </div>
            <div v-if="payeeMethods.length" class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3">
                <div class="mb-2 text-sm font-medium text-amber-900">客户收款资料 <span class="font-normal text-amber-700">（来自回收订单，仅供付款核对）</span></div>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <div v-for="(method,index) in payeeMethods" :key="`${method.pay_type}-${method.account}-${index}`" class="flex items-center justify-between gap-3 rounded bg-white px-3 py-2">
                        <div class="min-w-0 text-sm">
                            <div class="font-medium text-gray-900">{{ method.pay_type || '其他收款方式' }} <el-tag v-if="method.is_default" size="small" type="warning" effect="plain">默认</el-tag></div>
                            <div class="mt-1 break-all text-gray-600">{{ method.account || '未填写账号' }}</div>
                        </div>
                        <ErpImageGallery v-if="method.qrcode_image" :value="img(method.qrcode_image)" :size="48" :limit="1" />
                    </div>
                </div>
            </div>
            <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded bg-gray-50 px-3 py-2"><div class="text-xs text-gray-500">应付总额（含调价）</div><div class="mt-1 font-medium">{{ money(payAmountSummary.amount) }}</div></div>
                <div class="rounded bg-green-50 px-3 py-2"><div class="text-xs text-gray-500">累计已付／折账</div><div class="mt-1 font-medium text-green-700">{{ money(payAmountSummary.paid) }}</div></div>
                <div class="rounded bg-orange-50 px-3 py-2"><div class="text-xs text-gray-500">剩余待付</div><div class="mt-1 font-medium text-orange-600">{{ money(payAmountSummary.remaining) }}</div></div>
                <div class="rounded bg-red-50 px-3 py-2">
                    <div class="text-xs text-gray-500">本次付款</div>
                    <div class="mt-1 font-medium text-red-600">{{ money(paySelectedTotal) }}</div>
                </div>
            </div>
            <div class="mb-4 text-xs text-gray-500">已选 {{ paySelectedCount }} {{ isNonDevicePay ? '笔' : '台' }}，本次付款后仍欠 {{ money(payAfterRemainTotal) }}。历史付款不重复支付；展开设备行可查看原付款记录。</div>
            <el-form label-width="100px">
                <el-form-item label="付款账户">
                    <el-select v-model="pay.form.capital_account_id" clearable class="w-full" placeholder="选择银行卡/微信/支付宝">
                        <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="isNonDevicePay ? '费用明细' : '设备明细'" required>
                    <el-table v-if="isNonDevicePay" :data="pay.items" v-loading="pay.loading" size="small" max-height="320" class="w-full">
                        <el-table-column width="54"><template #default="{ row }"><el-checkbox v-model="row.checked" :disabled="Number(row.allocated_remain || 0) <= 0" @change="syncPayAmount(row)" /></template></el-table-column>
                        <el-table-column label="支出类型" min-width="150"><template #default="{ row }"><ErpOverflowText :text="row.category_name || pay.row?.category_name || '经营支出'" max-width="180px" /></template></el-table-column>
                        <el-table-column label="费用事项" min-width="280"><template #default="{ row }"><ErpOverflowText :text="row.business_reason || row.remark || pay.row?.business_reason" :lines="2" max-width="360px" /></template></el-table-column>
                        <el-table-column label="来源单号" min-width="180" show-overflow-tooltip><template #default="{ row }">{{ row.source_no || row.payable_no || '-' }}</template></el-table-column>
                        <el-table-column label="剩余应付" width="120" align="right"><template #default="{ row }">{{ money(row.allocated_remain) }}</template></el-table-column>
                        <el-table-column label="本次付款" width="160"><template #default="{ row }"><el-input-number v-model="row.pay_amount" :disabled="!row.checked" :min="0" :max="Number(row.allocated_remain || 0)" :precision="2" :controls="false" class="!w-[130px]" /></template></el-table-column>
                    </el-table>
                    <el-table v-else :data="pay.items" v-loading="pay.loading" size="small" max-height="320" class="w-full">
                        <el-table-column type="expand">
                            <template #default="{ row }">
                                <div class="px-4 py-2 text-sm">
                                    <div v-if="row.latest_purchase_adjustment" class="mb-3 text-amber-700">最近调价 {{ money(row.latest_purchase_adjustment.cost_delta) }} · {{ row.latest_purchase_adjustment.remark }} · {{ row.latest_purchase_adjustment.operator_name }}</div>
                                    <div v-if="!row.settlements?.length" class="text-gray-400">暂无历史结算记录</div>
                                    <div v-for="record in row.settlements || []" :key="record.settlement_id" class="mb-2 rounded bg-gray-50 p-2">
                                        {{ record.settlement_type_text || '结算' }} {{ money(record.applied_amount) }} · {{ record.pay_method_text || record.capital_account_name || '未填写账户' }} · {{ formatTime(record.confirmed_at) }}
                                        <div v-if="record.remark" class="text-xs text-gray-500">{{ record.remark }}</div>
                                        <ErpImageGallery v-for="entry in record.money_ledgers || []" :key="entry.ledger_no" :value="entry.voucher_urls" :size="48" />
                                    </div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column width="54">
                            <template #default="{ row }">
                                <el-checkbox v-model="row.checked" :disabled="Number(row.allocated_remain || 0) <= 0" @change="syncPayAmount(row)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="设备" min-width="230">
                            <template #default="{ row }">
                                <div class="font-medium">{{ row.model || '-' }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ row.spec || '未填写规格' }} · IMEI {{ row.imei || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="应付金额" width="120" align="right">
                            <template #default="{ row }">
                                <div>{{ money(row.payable_amount) }}</div>
                                <div v-if="row.current_total_cost" class="mt-1 text-xs text-gray-400">当前成本 {{ money(row.current_total_cost) }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="已付／折账" width="110" align="right">
                            <template #default="{ row }">{{ money(row.allocated_paid) }}</template>
                        </el-table-column>
                        <el-table-column label="剩余应付" width="120" align="right">
                            <template #default="{ row }">{{ money(row.allocated_remain) }}</template>
                        </el-table-column>
                        <el-table-column label="本次付款" width="160">
                            <template #default="{ row }">
                                <el-tag v-if="Number(row.allocated_remain || 0) <= 0" type="info" effect="plain">已付清</el-tag>
                                <el-input-number
                                    v-else
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
                <el-form-item label="付款凭证"><ErpFinanceVoucherUpload v-model="pay.form.voucher_urls" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="pay.saving" @click="pay.visible = false">取消</el-button>
                <el-button type="primary" :loading="pay.saving" :disabled="(!canSubmitPay) || (pay.saving)" @click="submitPay">确认付款并记账</el-button>
            </template>
        </HsxDialog>

        <HsxDialog :confirm-loading="offset.saving" v-model="offset.visible" title="应收应付折账" width="920px" :destroy-on-close="false">
            <div v-if="offset.row" class="mb-4 rounded bg-amber-50 px-4 py-3 text-sm text-gray-600">
                <div>往来单位：<span class="font-medium text-gray-900">{{ offset.row.party_name }}</span></div>
                <div class="mt-1 text-xs text-gray-500">折账就是把“我要付给他的钱”和“他要付给我的钱”互相抵扣，不产生真实收付款。</div>
            </div>
            <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
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
                <el-form-item v-if="offset.form.settle_diff" label="差额凭证"><ErpFinanceVoucherUpload v-model="offset.form.voucher_urls" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="offset.saving" @click="offset.visible = false">取消</el-button>
                <el-button type="warning" :loading="offset.saving" :disabled="(offsetMax <= 0) || (offset.saving)" @click="submitOffset">确认抵扣折账</el-button>
            </template>
        </HsxDialog>

        <HsxDrawer v-model="items.visible" :title="`${financeTypeLabel(items.row, '应付款')} · 设备明细`" size="86%" :destroy-on-close="false">
            <div v-if="items.row" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>{{ partyRoleLabel(items.row, '付款对象') }}：<span class="font-medium text-gray-900">{{ items.row.party_name || '-' }}</span></div>
                <ErpFinanceSourceMeta :row="items.row" class="mt-3" default-direction="expense" />
                <div class="mt-3 border-t border-gray-200 pt-2">应付 {{ money(items.row.amount) }} · 已结算 {{ money(items.row.settled_amount) }} · 剩余 {{ money(remain(items.row)) }}</div>
            </div>
            <div class="mb-2 font-medium text-gray-900">设备明细</div>
            <el-table :data="items.data" v-loading="items.loading" size="large">
                <el-table-column label="设备" min-width="240">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '未填写规格' }} · IMEI {{ row.imei || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="位置" min-width="150">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column label="来源单号" min-width="180">
                    <template #default="{ row }"><ErpCopyText :value="row.source_meta?.source_no || row.source_no || row.purchase_no" title="来源单号" max-width="170px" /></template>
                </el-table-column>
                <el-table-column label="应付" min-width="180" align="right">
                    <template #default="{ row }">
                        <div>{{ money(row.payable_amount) }}</div>
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
                        <div class="mt-1 text-xs text-gray-400"><ErpCopyText :value="row.settlement_no" title="结算单号" max-width="170px" /></div>
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
                                <div class="mt-1 text-gray-500">{{ erpSerialText(device) }}</div>
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
                            <ErpImageGallery v-if="ledger.voucher_urls" class="mt-2" :value="ledger.voucher_urls" :size="48" :limit="3" />
                        </div>
                        <div v-if="!row.money_ledgers?.length" class="text-xs text-gray-400">折账不产生资金流水</div>
                    </template>
                </el-table-column>
            </el-table>
        </HsxDrawer>

        <HsxDrawer v-model="ledger.visible" title="账目流水" size="72%" :destroy-on-close="false">
            <div class="ledger-intro">
                <div>
                    <b>每笔业务怎样影响账面</b>
                    <span>这里记录应收、应付、成本与真实收付款变化，不等同于银行卡流水；每条记录直接说明“新增应付、冲回应收、实际支出”等业务结果。</span>
                </div>
                <span>共 {{ ledger.total }} 条</span>
            </div>
            <div class="ledger-summary">
                <div><span>本页记录</span><b>{{ ledger.data.length }}</b></div>
                <div><span>实际收付款</span><b class="text-green-600">{{ ledgerPageSummary.cash }} 笔</b></div>
                <div><span>账款 / 成本变化</span><b class="text-blue-600">{{ ledgerPageSummary.business }} 笔</b></div>
            </div>
            <div class="ledger-toolbar">
                <el-input v-model.trim="ledger.keyword" clearable class="!w-[260px]" placeholder="流水号 / 往来单位 / 单号 / 备注" @keyup.enter="loadLedger" />
                <el-button type="primary" @click="loadLedger">查询</el-button>
                <el-button @click="ledger.keyword = ''; loadLedger()">重置</el-button>
            </div>
            <div v-loading="ledger.loading" class="ledger-body">
                <el-empty v-if="!ledger.loading && !ledger.data.length" description="暂无账目流水" />
                <el-collapse v-else v-model="ledger.expanded" class="ledger-event-list">
                    <el-collapse-item v-for="row in ledger.data" :key="row.id || row.ledger_no" :name="row.id || row.ledger_no">
                        <template #title>
                            <div class="ledger-event-head">
                                <span class="ledger-event-dot" :class="row.direction === 'decrease' ? 'is-decrease' : 'is-increase'"></span>
                                <div class="ledger-event-main">
                                    <div class="ledger-event-title"><el-tag size="small" effect="plain">{{ bizText(row) }}</el-tag><b>{{ row.party_name || '未记录往来单位' }}</b></div>
                                    <span>{{ ledgerSentence(row) }}</span>
                                </div>
                                <div class="ledger-event-result">
                                    <strong :class="ledgerAmountMeta(row).className">{{ ledgerAmountMeta(row).text }}</strong>
                                    <span>{{ formatTime(row.occurred_at) }}</span>
                                </div>
                            </div>
                        </template>
                        <div class="ledger-event-detail">
                            <div><span>来源单号</span><b>{{ row.source_no || '-' }}</b></div>
                            <div><span>账目流水号</span><b>{{ row.ledger_no || '-' }}</b></div>
                            <div><span>操作人</span><b>{{ row.operator_name || '-' }}</b></div>
                            <div><span>账务影响</span><b>{{ ledgerImpactText(row) }}</b></div>
                            <div v-if="row.remark"><span>原始备注</span><b>{{ row.remark }}</b></div>
                        </div>
                    </el-collapse-item>
                </el-collapse>
            </div>
            <div class="ledger-pagination">
                <el-pagination
                    v-model:current-page="ledger.page"
                    v-model:page-size="ledger.limit"
                    layout="total, prev, pager, next"
                    :total="ledger.total"
                    @current-change="loadLedger"
                />
            </div>
        </HsxDrawer>

        <HsxDrawer v-model="settle.visible" title="结算记录" size="72%" :destroy-on-close="false">
            <div class="settlement-intro">
                <div><b>每笔钱怎么结的</b><span>先看往来单位、金额和结算方式；设备、账款单号、资金流水和凭证按需展开。</span></div>
                <span>共 {{ settle.total }} 笔</span>
            </div>
            <div class="settlement-toolbar">
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
            <ErpSettlementCards :rows="settle.data" :loading="settle.loading" />
            <div class="settlement-pagination">
                <el-pagination
                    v-model:current-page="settle.page"
                    v-model:page-size="settle.limit"
                    layout="total, prev, pager, next"
                    :total="settle.total"
                    @current-change="loadSettlement"
                />
            </div>
        </HsxDrawer>
    </HsxPage>
</template>

<script setup lang="ts">
import { HsxTitle, HsxPage, HsxSearchPanel, HsxDialog, HsxDrawer, useFeedback } from '@/addon/hsx_components/core'
import { erpNamedLabel, erpSerialText } from '@/addon/hsx_erp/utils/display'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { Filter, Refresh, Search, Tickets } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpFinanceCategories, getErpSaleChannelOptions } from '@/addon/hsx_erp/api/config'
import { confirmErpOffset, confirmErpPayableItemsPayment, getErpAccountLedger, getErpPayableList, getErpPayablePartyItems, getErpReceivableItems, getErpReceivableList, getErpSettlementList } from '@/addon/hsx_erp/api/erp'
import ErpRoleFocus from '@/addon/hsx_erp/components/ErpRoleFocus.vue'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
import ErpImageGallery from '@/addon/hsx_erp/components/ErpImageGallery.vue'
import ErpSettlementCards from '@/addon/hsx_erp/components/ErpSettlementCards.vue'
import ErpFinanceSourceMeta from '@/addon/hsx_erp/components/ErpFinanceSourceMeta.vue'
import ErpCopyText from '@/addon/hsx_erp/components/ErpCopyText.vue'
import ErpPartySelect from '@/addon/hsx_erp/components/ErpPartySelect.vue'
import ErpOverflowText from '@/addon/hsx_erp/components/ErpOverflowText.vue'
import { img } from '@/utils/common'
const hsxFeedback = useFeedback()


const route = useRoute()

const payableRoleFocus = [
    { role: '财务', focus: '采购、整备、售后等各类应付，付款账户与逐台核销明细' },
    { role: '业务', focus: '采购来源或售后退货来源、设备归属与退款原因' },
    { role: '负责人', focus: '现金支出、未付风险与折账事实' },
]

const routeStatus = String(route.query.status || '')
const activeStatus = ref(['', 'pending', 'partial', 'settled'].includes(routeStatus) ? routeStatus : '')
const search = reactive({ keyword: '', party_id: null as number | null, party_name: '', source_no: String(route.query.source_no || ''), contact_mobile: '', finance_type_key: '', business_source_key: '', channel_code: '', can_offset: '' })
const advancedVisible = ref(false)
const dateRange = ref<any[]>([])
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const accounts = ref<any[]>([])
const registeredFinanceCategories = ref<any[]>([])
const registeredChannels = ref<any[]>([])
const pay = reactive({ visible: false, saving: false, loading: false, row: null as any, items: [] as any[], form: { capital_account_id: 0, remark: '', voucher_urls: '' } })
const items = reactive({ visible: false, loading: false, row: null as any, data: [] as any[], settlements: [] as any[], page: 1, limit: 15, total: 0 })
const ledger = reactive({ visible: false, loading: false, keyword: '', data: [] as any[], expanded: [] as Array<number | string>, page: 1, limit: 15, total: 0 })
const offset = reactive({ visible: false, saving: false, loading: false, row: null as any, payables: [] as any[], receivables: [] as any[], form: { amount: 0, settle_diff: false, capital_account_id: 0, remark: '', voucher_urls: '' } })
const settle = reactive({ visible: false, loading: false, keyword: '', type: '', assetId: 0, assetLabel: '', data: [] as any[], page: 1, limit: 15, total: 0 })

const summary = computed(() => table.data.reduce((acc, row: any) => {
    acc.count += 1
    acc.amount += Number(row.amount || 0)
    acc.settled += Number(row.settled_amount || 0)
    acc.remain += remain(row)
    return acc
}, { count: 0, amount: 0, settled: 0, remain: 0 }))
const financeTypeOptions = computed(() => sourceOptions('finance_type_key', 'finance_type_name', registeredFinanceCategories.value
    .filter((item: any) => Number(item.enabled ?? 1) === 1 && item.direction === 'expense')
    .map((item: any) => ({ value: String(item.key), label: String(item.name) }))))
const channelOptions = computed(() => sourceOptions('channel_code', 'channel_name', registeredChannels.value
    .filter((item: any) => Number(item.enabled ?? 1) === 1)
    .map((item: any) => ({ value: String(item.key), label: String(item.name) }))))

const ledgerPageSummary = computed(() => ledger.data.reduce((acc, row: any) => {
    if (['payment', 'receipt'].includes(String(row.biz_type || '').toLowerCase())) acc.cash += 1
    else acc.business += 1
    return acc
}, { cash: 0, business: 0 }))

const paySelectedTotal = computed(() => pay.items.reduce((sum: number, row: any) => {
    if (!row.checked) return sum
    return sum + Number(row.pay_amount || 0)
}, 0))
const payAmountSummary = computed(() => pay.items.reduce((sum: any, row: any) => ({
    amount: sum.amount + Number(row.payable_amount || 0), paid: sum.paid + Number(row.allocated_paid || 0),
    remaining: sum.remaining + Number(row.allocated_remain || 0)
}), { amount: 0, paid: 0, remaining: 0 }))
const paySelectedCount = computed(() => pay.items.filter((row: any) => row.checked && Number(row.pay_amount || 0) > 0).length)
const isNonDevicePay = computed(() => {
    const row = pay.row || {}
    return String(row.source_type || '').includes('operating_') || String(row.biz_scene || '').includes('operating_') || String(row.category_statement_group || '').includes('operating_')
})
const payAfterRemainTotal = computed(() => pay.items.reduce((sum: number, row: any) => sum + payAfterRemain(row), 0))
const canSubmitPay = computed(() => paySelectedTotal.value > 0 && Number(pay.form.capital_account_id || 0) > 0)
const payeeMethods = computed(() => {
    const unique = new Map<string, any>()
    pay.items.flatMap((row: any) => Array.isArray(row.payee_methods) ? row.payee_methods : []).forEach((item: any) => {
        const key = `${String(item.pay_type || '').trim()}|${String(item.account || '').trim()}|${String(item.qrcode_image || '').trim()}`
        if (key !== '||' && !unique.has(key)) unique.set(key, item)
    })
    return Array.from(unique.values())
})

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
    loadAccounts()
    loadDynamicOptions()
})
useErpPageRefresh(loadList)

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

async function loadDynamicOptions() {
    const [categories, channels] = await Promise.allSettled([
        getErpFinanceCategories(),
        getErpSaleChannelOptions()
    ])
    if (categories.status === 'fulfilled') registeredFinanceCategories.value = responseRows(categories.value)
    if (channels.status === 'fulfilled') registeredChannels.value = responseRows(channels.value)
}

function responseRows(response: any) {
    const data = response?.data
    if (Array.isArray(data)) return data
    if (Array.isArray(data?.list)) return data.list
    return []
}

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    search.keyword = ''
    search.party_id = null
    search.party_name = ''
    search.source_no = ''
    search.contact_mobile = ''
    search.finance_type_key = ''
    search.business_source_key = ''
    search.channel_code = ''
    search.can_offset = ''
    activeStatus.value = ''
    dateRange.value = []
    handleSearch()
}

async function openPay(row: any) {
    if (!canConfirmPay(row)) return
    pay.row = row
    pay.items = []
    pay.form = { capital_account_id: accounts.value[0]?.id || 0, remark: row.source_meta?.business_reason || (row.source_type === 'sale_return' ? (row.sale_return_business_type === 'after_sale_compensation' ? `公司向客户【${row.party_name || '-'}】支付售后补差款` : `公司向客户【${row.party_name || '-'}】退还销售货款`) : (row.source_type === 'refurbish' ? `公司向整备服务商【${row.party_name || '-'}】支付设备整备费用` : '')), voucher_urls: '' }
    pay.visible = true
    pay.loading = true
    try {
        const res: any = await getErpPayablePartyItems(row.party_id, {
            ...search,
            status: '',
            purchase_order_id: row.purchase_order_id || 0,
            source_type: row.source_type || '',
            start_at: Number(dateRange.value?.[0] || 0),
            end_at: Number(dateRange.value?.[1] || 0) ? Number(dateRange.value[1]) + 86399 : 0,
            page: 1,
            limit: 200
        })
        pay.items = (res?.data?.data || [])
            .filter((item: any) => isNonDevicePay.value ? Number(item.payable_id || 0) > 0 : Number(item.asset_payable_id || 0) > 0)
            .map((item: any) => {
                const payableRemain = Number(item.allocated_remain || 0)
                return { ...item, checked: payableRemain > 0, pay_amount: payableRemain > 0 ? payableRemain : 0 }
            })
        if (!pay.items.length && remain(row) > 0) {
            hsxFeedback.warning(isNonDevicePay.value ? '当前经营应付明细已变化，请刷新后重试' : '当前应付缺少设备级账目，请用新采购开单数据验证')
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
    if (!selected.length) return hsxFeedback.warning(isNonDevicePay.value ? '请选择要付款的费用明细' : '请选择要付款的设备')
    if (paySelectedTotal.value <= 0) return hsxFeedback.warning('请填写付款金额')
    if (!pay.form.capital_account_id) return hsxFeedback.warning('请选择付款账户')
    if (pay.items.some((row: any) => row.checked && Number(row.pay_amount || 0) > Number(row.allocated_remain || 0))) return hsxFeedback.warning('本次付款不能大于设备剩余应付')
    const payAccount = accounts.value.find((item: any) => Number(item.id) === Number(pay.form.capital_account_id))
    const payConfirmed = await ElMessageBox.confirm(
        `确认向「${pay.row.party_name || '该付款对象'}」付款 ${money(paySelectedTotal.value)}，付款账户「${payAccount?.account_name || '所选账户'}」，核销 ${selected.length} ${isNonDevicePay.value ? '笔经营支出' : '台设备应付'}。确认后将写入资金流水，不能直接删除。`,
        isNonDevicePay.value ? '确认经营付款' : '确认设备付款',
        { type: 'warning', confirmButtonText: '确认付款并记账', cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!payConfirmed) return
    pay.saving = true
    try {
        await confirmErpPayableItemsPayment(pay.row.party_id, { ...pay.form, items: selected })
        hsxFeedback.success('付款已确认')
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
            getErpPayablePartyItems(row.party_id, { status: '', source_type: row.source_type || '', purchase_order_id: row.purchase_order_id || 0, page: 1, limit: 200 }),
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
            hsxFeedback.warning('该往来单位需同时存在剩余应付和剩余应收才能折账')
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
    if (!payableIds.length) return hsxFeedback.warning('请勾选要抵扣的应付')
    if (!receivableIds.length) return hsxFeedback.warning('请勾选要抵扣的应收')
    const amount = Number(offset.form.amount || 0)
    if (amount <= 0) return hsxFeedback.warning('请填写折账金额')
    if (amount > offsetMax.value + 0.001) return hsxFeedback.warning('折账金额不能大于可折金额')
    if (offset.form.settle_diff) {
        if (amount < offsetMax.value - 0.001) return hsxFeedback.warning('部分抵扣后双方仍有余额，不能本次结清差额')
        if (offsetDiffAmount.value <= 0) return hsxFeedback.warning('当前没有需要结清的差额')
        if (!offset.form.capital_account_id) return hsxFeedback.warning('请选择差额收付款账户')
    }
    const offsetConfirmed = await ElMessageBox.confirm(
        `确认对「${offset.row.party_name || '该往来主体'}」执行应付应收折账 ${money(amount)}。该操作会同时核销双方账目${offset.form.settle_diff ? `，并结清差额 ${money(offsetDiffAmount.value)}` : ''}；确认后不能直接删除流水。`,
        '确认应付应收折账',
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
        const recRemainAfter = Math.max(0, offsetReceivableChecked.value - amount)
        const payRemainAfter = Math.max(0, offsetPayableChecked.value - amount)
        hsxFeedback.success(`已折账 ¥${amount.toFixed(2)}；勾选应收剩余 ¥${recRemainAfter.toFixed(2)}、应付剩余 ¥${payRemainAfter.toFixed(2)}`)
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
            source_type: items.row.source_type || '',
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
    return map[status] || { label: '状态待确认', type: 'info' }
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

function partyRoleLabel(row: any, fallback: string) {
    return erpNamedLabel(row?.source_meta?.party_role_label, '', fallback)
}

function financeTypeLabel(row: any, fallback: string) {
    return erpNamedLabel(row?.source_meta?.finance_type_name || row?.source_label, row?.source_meta?.finance_type_key, fallback)
}

function sourceOptions(valueKey: string, labelKey: string, defaults: Array<{ value: string, label: string }>) {
    const map = new Map(defaults.map(item => [item.value, erpNamedLabel(item.label, item.value)]))
    table.data.forEach((row: any) => {
        const value = String(row?.source_meta?.[valueKey] || '').trim()
        if (value) map.set(value, erpNamedLabel(row?.source_meta?.[labelKey], value))
    })
    return Array.from(map, ([value, label]) => ({ value, label }))
}

function bizText(value: any) {
    const type = typeof value === 'object' ? value?.biz_type : value
    const map: any = {
        purchase: '采购应付', purchase_cancel: '采购撤销冲回', purchase_return: '采购退货冲回', purchase_return_loss: '采购退货损失',
        sale: '销售应收', sale_cancel: '整单销售撤销', sale_item_cancel: '单台销售撤销', sale_return: '销售退货冲回',
        sale_compensation: '售后补差应付', payment: '实际付款', receipt: '实际收款', offset: '往来折账',
        adjust: '成本调整', refurbish: '整备成本'
    }
    return erpNamedLabel(typeof value === 'object' ? value?.biz_type_text : '', String(type || '').toLowerCase(), '账务调整', map)
}

function ledgerSentence(row: any) {
    const map: any = {
        purchase: '采购入库形成应付款', purchase_cancel: '撤销采购并冲回原应付款', purchase_return: '采购退货冲回设备成本', purchase_return_loss: '采购退货产生不可收回损失',
        sale: '销售出库形成应收款', sale_cancel: '撤销整张销售单并冲回应收', sale_item_cancel: '撤销该设备销售并冲回应收', sale_return: '客户退货并冲回销售应收',
        sale_compensation: '公司同意补偿客户，新增一笔待付给客户的款项', payment: '公司已经完成实际付款', receipt: '公司已经确认实际收款', offset: '应收与应付完成折账核销',
        adjust: '设备成本发生调整', refurbish: '设备新增整备成本'
    }
    return map[String(row?.biz_type || '').toLowerCase()] || '账面发生业务变化'
}

function ledgerAmountMeta(row: any) {
    const amount = money(row?.amount)
    const type = String(row?.biz_type || '').toLowerCase()
    const decrease = String(row?.direction || '').toLowerCase() === 'decrease'
    const map: any = {
        purchase: { text: `新增应付 ${amount}`, className: 'text-orange-600' },
        purchase_cancel: { text: `冲回应付 ${amount}`, className: 'text-green-600' },
        purchase_return: { text: `冲回成本 ${amount}`, className: 'text-green-600' },
        purchase_return_loss: { text: `新增损失 ${amount}`, className: 'text-red-600' },
        sale: { text: `新增应收 ${amount}`, className: 'text-blue-600' },
        sale_cancel: { text: `冲回应收 ${amount}`, className: 'text-orange-600' },
        sale_item_cancel: { text: `冲回应收 ${amount}`, className: 'text-orange-600' },
        sale_return: { text: `冲回应收 ${amount}`, className: 'text-orange-600' },
        sale_compensation: { text: `新增应付 ${amount}`, className: 'text-orange-600' },
        payment: { text: `实际支出 ${amount}`, className: 'text-red-600' },
        receipt: { text: `实际收入 ${amount}`, className: 'text-green-600' },
        offset: { text: `折账核销 ${amount}`, className: 'text-purple-600' },
        refurbish: { text: `新增成本 ${amount}`, className: 'text-orange-600' }
    }
    return map[type] || { text: `${decrease ? '减少' : '增加'} ${amount}`, className: decrease ? 'text-orange-600' : 'text-blue-600' }
}

function ledgerImpactText(row: any) {
    const type = String(row?.biz_type || '').toLowerCase()
    if (type === 'sale_compensation') return `公司新增欠客户 ${money(row.amount)}，这不是资金入账；财务付款后会另有“实际支出”记录。`
    if (type === 'payment') return `公司资金账户实际支出 ${money(row.amount)}。`
    if (type === 'receipt') return `公司资金账户实际收入 ${money(row.amount)}。`
    return `${ledgerSentence(row)}，账务金额 ${money(row.amount)}。`
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
.ledger-intro { display:flex; max-width:1040px; margin:0 auto 12px; align-items:flex-start; justify-content:space-between; gap:18px; padding:14px 16px; border:1px solid #dbeafe; border-radius:8px; background:#f8fbff; }
.ledger-intro > div { display:flex; min-width:0; flex-direction:column; gap:4px; }
.ledger-intro b { color:#1e293b; font-size:15px; }
.ledger-intro div span { color:#64748b; font-size:12px; line-height:1.6; }
.ledger-intro > span { flex:none; padding:4px 9px; border-radius:999px; color:#1d4ed8; background:#dbeafe; font-size:12px; font-weight:600; }
.ledger-summary { display:grid; max-width:1040px; margin:0 auto 12px; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
.ledger-summary > div { display:flex; min-width:0; flex-direction:column; gap:5px; padding:11px 14px; border:1px solid #e5e7eb; border-radius:7px; background:#fff; }
.ledger-summary span { color:#94a3b8; font-size:12px; }
.ledger-summary b { color:#1e293b; font-size:17px; }
.ledger-toolbar { display:flex; max-width:1040px; margin:0 auto 14px; align-items:center; flex-wrap:wrap; gap:8px; padding:10px 12px; border-radius:7px; background:#f8fafc; }
.ledger-body { min-height:160px; }
.ledger-event-list { max-width:1040px; margin:0 auto; border-top:0; }
.ledger-event-list :deep(.el-collapse-item) { margin-bottom:10px; overflow:hidden; border:1px solid #e5e7eb; border-radius:8px; background:#fff; }
.ledger-event-list :deep(.el-collapse-item__header) { height:auto; min-height:76px; padding:0 16px; border-bottom:0; line-height:normal; }
.ledger-event-list :deep(.el-collapse-item__wrap) { border-top:1px solid #f1f5f9; border-bottom:0; }
.ledger-event-list :deep(.el-collapse-item__content) { padding:12px 16px 16px; }
.ledger-event-head { display:flex; width:100%; min-width:0; align-items:center; gap:12px; padding:12px 10px 12px 0; }
.ledger-event-dot { width:9px; height:9px; flex:none; border-radius:50%; }
.ledger-event-dot.is-increase { background:#3b82f6; box-shadow:0 0 0 4px #dbeafe; }
.ledger-event-dot.is-decrease { background:#f59e0b; box-shadow:0 0 0 4px #fef3c7; }
.ledger-event-main { display:flex; min-width:0; flex:1; flex-direction:column; gap:6px; }
.ledger-event-title { display:flex; min-width:0; align-items:center; gap:9px; }
.ledger-event-title b { overflow:hidden; color:#1e293b; font-size:14px; text-overflow:ellipsis; white-space:nowrap; }
.ledger-event-main > span { overflow:hidden; color:#64748b; font-size:12px; text-overflow:ellipsis; white-space:nowrap; }
.ledger-event-result { display:flex; min-width:150px; flex:none; align-items:flex-end; flex-direction:column; gap:5px; }
.ledger-event-result strong { font-size:18px; }
.ledger-event-result span { color:#94a3b8; font-size:12px; }
.ledger-event-detail { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px 18px; padding-left:21px; }
.ledger-event-detail > div { display:flex; min-width:0; flex-direction:column; gap:4px; }
.ledger-event-detail span { color:#94a3b8; font-size:12px; }
.ledger-event-detail b { overflow-wrap:anywhere; color:#475569; font-size:13px; font-weight:500; }
.ledger-pagination { display:flex; max-width:1040px; margin:16px auto 0; justify-content:flex-end; }
@media (max-width: 900px) { .ledger-summary { grid-template-columns:1fr; } .ledger-event-result { min-width:110px; } .ledger-event-detail { grid-template-columns:1fr; } }
.settlement-intro { display:flex; max-width:1040px; margin:0 auto 12px; align-items:flex-start; justify-content:space-between; gap:18px; padding:14px 16px; border:1px solid #dbeafe; border-radius:8px; background:#f8fbff; }
.settlement-intro > div { display:flex; flex-direction:column; gap:4px; }
.settlement-intro b { color:#1e293b; font-size:15px; }
.settlement-intro div span { color:#64748b; font-size:12px; }
.settlement-intro > span { flex:none; padding:4px 9px; border-radius:999px; color:#1d4ed8; background:#dbeafe; font-size:12px; font-weight:600; }
.settlement-toolbar { display:flex; max-width:1040px; margin:0 auto 14px; align-items:center; flex-wrap:wrap; gap:8px; padding:10px 12px; border-radius:7px; background:#f8fafc; }
.settlement-pagination { display:flex; max-width:1040px; margin:16px auto 0; justify-content:flex-end; }
</style>
