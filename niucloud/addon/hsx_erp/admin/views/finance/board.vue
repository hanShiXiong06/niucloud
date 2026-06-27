<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">财务中心</div>
                    <div class="mt-1 text-sm text-gray-500">往来对账、应收应付明细、结算记录与经营支出，一处看清账目往来。</div>
                </div>
                <div class="flex items-center gap-2">
                    <el-button v-permission="'hsx_erp_capital_account_entry'" type="primary" plain @click="openPrepay">采购预付</el-button>
                    <el-button v-permission="'hsx_erp_capital_account_entry'" type="warning" plain @click="openExpense">记一笔支出</el-button>
                    <ai-assistant
                        scene="finance"
                        permission="hsx_erp_ai_finance"
                        title="AI 财务分析"
                        button-text="AI 财务分析"
                        button-type="success"
                        :question="aiFinanceQuestion"
                        :get-payload="buildAiFinancePayload"
                    />
                    <el-button @click="refreshAll" :loading="loading">刷新</el-button>
                    <el-button text @click="showSummary = !showSummary">
                        {{ showSummary ? '收起看板' : '展开看板' }}
                        <el-icon class="ml-1"><ArrowUp v-if="showSummary" /><ArrowDown v-else /></el-icon>
                    </el-button>
                </div>
            </div>

            <!-- 汇总卡片(可折叠,腾出屏幕空间) -->
            <div v-show="showSummary" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="stat-card" style="--c:#2ba471">
                    <el-icon class="stat-ic"><Coin /></el-icon>
                    <div class="stat-label">应收未结(欠我)</div>
                    <div class="stat-value">{{ money(summary.receivable_total) }}</div>
                </div>
                <div class="stat-card" style="--c:#e6792b">
                    <el-icon class="stat-ic"><Money /></el-icon>
                    <div class="stat-label">应付未结(我欠)</div>
                    <div class="stat-value">{{ money(summary.payable_total) }}</div>
                </div>
                <div class="stat-card" :style="{ '--c': Number(summary.net) >= 0 ? '#e6792b' : '#2ba471' }">
                    <el-icon class="stat-ic"><Sort /></el-icon>
                    <div class="stat-label">净额(应付-应收)</div>
                    <div class="stat-value">
                        {{ money(Math.abs(Number(summary.net || 0))) }}
                        <span class="stat-tag">{{ Number(summary.net) > 0 ? '净付' : (Number(summary.net) < 0 ? '净收' : '已平') }}</span>
                    </div>
                </div>
                <div class="stat-card" style="--c:#5b8ff9">
                    <el-icon class="stat-ic"><Wallet /></el-icon>
                    <div class="stat-label">资金账户总余额</div>
                    <div class="stat-value">{{ money(summary.balance_total) }}</div>
                    <div v-if="summary.accounts && summary.accounts.length" class="acct-tags mt-2">
                        <el-tag v-for="a in summary.accounts.slice(0, 3)" :key="a.id" size="small" effect="plain">
                            {{ a.account_name }}：{{ money(a.balance) }}
                        </el-tag>
                        <el-popover v-if="summary.accounts.length > 3" placement="bottom" trigger="hover" width="240">
                            <template #reference>
                                <el-tag size="small" type="info" effect="plain" class="cursor-pointer">+{{ summary.accounts.length - 3 }} 个</el-tag>
                            </template>
                            <div class="flex flex-col gap-1">
                                <div v-for="a in summary.accounts" :key="a.id" class="flex justify-between text-xs">
                                    <span class="text-gray-600">{{ a.account_name }}</span>
                                    <span class="font-medium text-blue-600">{{ money(a.balance) }}</span>
                                </div>
                            </div>
                        </el-popover>
                    </div>
                </div>
            </div>

            <el-tabs v-model="activeTab" class="mt-4" @tab-change="onTabChange">
                <!-- 往来汇总 -->
                <el-tab-pane label="往来汇总" name="board">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <el-radio-group v-model="boardFilter">
                            <el-radio-button label="">全部</el-radio-button>
                            <el-radio-button label="offsetable">可折账</el-radio-button>
                            <el-radio-button label="pay">我应付</el-radio-button>
                            <el-radio-button label="collect">我应收</el-radio-button>
                        </el-radio-group>
                        <ErpFilterBar v-model="boardSearch" :fields="boardFilterFields" :show-actions="false" />
                    </div>
                    <!-- 往来汇总：卡片 + 折叠（账目明细默认折叠） -->
                    <div v-loading="loading" class="board-cards">
                        <el-empty v-if="!filteredBoard.length" description="暂无未结往来" :image-size="70" />

                        <div v-for="g in filteredBoard" :key="g.row_key" class="board-card">
                            <!-- ====== 主体卡 ====== -->
                            <template v-if="g.is_entity">
                                <div class="bc-head" @click="toggleEntity(g.row_key)">
                                    <div class="bc-main">
                                        <el-icon class="bc-caret" :class="{ open: !closedEntities.has(g.row_key) }"><CaretRight /></el-icon>
                                        <span class="bc-name" @click.stop="openEntity(g.entity_id)">{{ g.entity_name }}</span>
                                        <el-tag size="small" effect="plain" round>主体 · {{ g.member_count }}人</el-tag>
                                    </div>
                                    <div class="bc-amounts">
                                        <span v-if="g.payable > 0" class="amt pay">应付 ¥{{ money(g.payable) }}</span>
                                        <span v-if="g.receivable > 0" class="amt rec">应收 ¥{{ money(g.receivable) }}</span>
                                        <el-tag v-if="g.offsetable > 0" size="small" type="primary" effect="light">可折 ¥{{ money(g.offsetable) }}</el-tag>
                                        <span class="net" :class="netClass(g.net)">净 ¥{{ money(Math.abs(g.net)) }} <i>{{ netLabel(g.net_direction) }}</i></span>
                                    </div>
                                    <div class="bc-ops" @click.stop>
                                        <el-tooltip v-if="g.offsetable > 0" content="折账（跨人冲抵）" placement="top">
                                            <el-button v-permission="'hsx_erp_finance_settlement_settle'" type="primary" text :icon="Switch" @click="openSettle(g)" />
                                        </el-tooltip>
                                    </div>
                                </div>

                                <div v-show="!closedEntities.has(g.row_key)" class="bc-members">
                                    <div v-for="m in g.children" :key="m.row_key" class="member-row">
                                        <div class="mr-head">
                                            <div class="mr-main" @click="m.children && m.children.length && toggleMember(m.row_key)">
                                                <el-icon v-if="m.children && m.children.length" class="mr-caret" :class="{ open: openMembers.has(m.row_key) }"><CaretRight /></el-icon>
                                                <span v-else class="mr-dot"></span>
                                                <span class="mr-name">{{ m.counterparty_name }}</span>
                                                <span v-if="m.counterparty_mobile" class="mr-mobile">{{ m.counterparty_mobile }}</span>
                                                <span v-if="m.children && m.children.length" class="mr-count">{{ m.children.length }} 笔</span>
                                            </div>
                                            <div class="mr-amounts">
                                                <span v-if="m.payable > 0" class="amt pay">应付 ¥{{ money(m.payable) }}</span>
                                                <span v-if="m.receivable > 0" class="amt rec">应收 ¥{{ money(m.receivable) }}</span>
                                            </div>
                                            <div class="mr-ops" @click.stop>
                                                <el-tooltip v-if="m.payable > 0" content="付款" placement="top">
                                                    <el-button v-permission="'hsx_erp_finance_settlement_settle'" type="warning" text :icon="Wallet" @click="openPay(m)" />
                                                </el-tooltip>
                                                <el-tooltip v-if="m.receivable > 0" content="收款" placement="top">
                                                    <el-button v-permission="'hsx_erp_finance_settlement_settle'" type="success" text :icon="Coin" @click="openCollect(m)" />
                                                </el-tooltip>
                                            </div>
                                        </div>
                                        <div v-show="openMembers.has(m.row_key)" class="mr-items">
                                            <div v-for="it in (m.children || [])" :key="it.row_key" class="item-row">
                                                <el-tag size="small" :type="it.direction === '应付' ? 'warning' : 'success'" effect="plain">{{ it.direction }}</el-tag>
                                                <span class="it-title">{{ it.title }}</span>
                                                <span v-if="it.imei" class="it-sub">· {{ it.imei }}</span>
                                                <span v-if="it.operator" class="it-op">经手 {{ it.operator }}</span>
                                                <span class="it-amt"><template v-if="it.settled > 0">欠 </template>¥{{ money(it.payable || it.receivable) }}</span>
                                                <span class="it-meta"><template v-if="it.settled > 0"><b class="it-total">总额 ¥{{ money(it.amount) }} · 已付 ¥{{ money(it.settled) }}</b> · </template>{{ it.source_type_text || '账目' }}<template v-if="it.source_no"> · {{ it.source_no }}</template><template v-if="it.occurred_at"> · {{ formatTime(it.occurred_at) }}</template></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- ====== 未归属主体的独立对接人卡 ====== -->
                            <template v-else>
                                <div class="bc-head" @click="g.children && g.children.length && toggleMember(g.row_key)">
                                    <div class="bc-main">
                                        <el-icon v-if="g.children && g.children.length" class="bc-caret" :class="{ open: openMembers.has(g.row_key) }"><CaretRight /></el-icon>
                                        <span v-else class="mr-dot"></span>
                                        <span class="bc-name">{{ g.counterparty_name || ('#' + g.counterparty_id) }}</span>
                                        <span v-if="g.counterparty_mobile" class="mr-mobile">{{ g.counterparty_mobile }}</span>
                                        <el-tag size="small" effect="plain" round type="info">未归属主体</el-tag>
                                    </div>
                                    <div class="bc-amounts">
                                        <span v-if="g.payable > 0" class="amt pay">应付 ¥{{ money(g.payable) }}</span>
                                        <span v-if="g.receivable > 0" class="amt rec">应收 ¥{{ money(g.receivable) }}</span>
                                        <span class="net" :class="netClass(g.net)">净 ¥{{ money(Math.abs(g.net)) }} <i>{{ netLabel(g.net_direction) }}</i></span>
                                    </div>
                                    <div class="bc-ops" @click.stop>
                                        <el-tooltip v-if="g.payable > 0" content="付款" placement="top">
                                            <el-button v-permission="'hsx_erp_finance_settlement_settle'" type="warning" text :icon="Wallet" @click="openPay(g)" />
                                        </el-tooltip>
                                        <el-tooltip v-if="g.receivable > 0" content="收款" placement="top">
                                            <el-button v-permission="'hsx_erp_finance_settlement_settle'" type="success" text :icon="Coin" @click="openCollect(g)" />
                                        </el-tooltip>
                                    </div>
                                </div>
                                <div v-show="openMembers.has(g.row_key)" class="mr-items mr-items-flat">
                                    <div v-for="it in (g.children || [])" :key="it.row_key" class="item-row">
                                        <el-tag size="small" :type="it.direction === '应付' ? 'warning' : 'success'" effect="plain">{{ it.direction }}</el-tag>
                                        <span class="it-title">{{ it.title }}</span>
                                        <span v-if="it.imei" class="it-sub">· {{ it.imei }}</span>
                                        <span class="it-amt">¥{{ money(it.payable || it.receivable) }}</span>
                                        <span class="it-meta">{{ it.source_type_text || '账目' }}<template v-if="it.source_no"> · {{ it.source_no }}</template><template v-if="it.occurred_at"> · {{ formatTime(it.occurred_at) }}</template></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </el-tab-pane>

                <!-- 应收明细 / 应付明细 -->
                <el-tab-pane v-for="t in detailTabs" :key="t.name" :label="t.label" :name="t.name">
                    <div class="mb-3 flex flex-wrap items-end gap-x-4 gap-y-2">
                        <div class="flt">
                            <span class="flt__l">结算状态</span>
                            <el-radio-group v-model="detail.settle_state" @change="onDetailFilter">
                                <el-radio-button label="">全部</el-radio-button>
                                <el-radio-button label="open">未结清</el-radio-button>
                                <el-radio-button label="settled">已结清</el-radio-button>
                            </el-radio-group>
                        </div>
                        <div class="flt">
                            <span class="flt__l">业务类型</span>
                            <el-select v-model="detail.source_type" :placeholder="activeTab === 'payable' ? '全部(回收/入库/整备…)' : '全部(商城/同行/出库…)'" clearable class="!w-[180px]" @change="onDetailFilter">
                                <el-option v-for="t in filterOpts.source_types" :key="t.value" :label="t.text" :value="t.value" />
                            </el-select>
                        </div>
                        <div class="flt">
                            <span class="flt__l">主体 / 对接人</span>
                            <counterparty-select v-model="detail.counterparty_id" value-field="counterparty_id"
                                :role-type="activeTab === 'payable' ? 'supplier' : 'customer'"
                                placeholder="搜索姓名 / 手机号" class="!w-[200px]" @update:modelValue="onDetailFilter" />
                        </div>
                        <div class="flt">
                            <span class="flt__l">经手人</span>
                            <el-select v-model="detail.operator" filterable clearable placeholder="全部经手人" class="!w-[140px]" @change="onDetailFilter">
                                <el-option v-for="op in filterOpts.operators" :key="op" :label="op" :value="op" />
                            </el-select>
                        </div>
                        <div class="flt">
                            <span class="flt__l">发生时间</span>
                            <el-date-picker v-model="detail.dateRange" type="daterange" value-format="X" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" class="!w-[260px]" @change="onDetailFilter" />
                        </div>
                        <div class="flt">
                            <span class="flt__l">金额区间(元)</span>
                            <div class="flex items-center gap-1">
                                <el-input v-model="detail.amount_min" placeholder="最低" clearable class="!w-[90px]" @keyup.enter="onDetailFilter" />
                                <span class="text-gray-400">~</span>
                                <el-input v-model="detail.amount_max" placeholder="最高" clearable class="!w-[90px]" @keyup.enter="onDetailFilter" />
                            </div>
                        </div>
                        <div class="flt">
                            <span class="flt__l">单号 / 关键字</span>
                            <el-input v-model="detail.keyword" placeholder="来源单号 / 手机号" clearable class="!w-[180px]" @keyup.enter="onDetailFilter" @clear="onDetailFilter" />
                        </div>
                        <div class="flt">
                            <span class="flt__l">IMEI</span>
                            <el-input v-model.trim="detail.imei" placeholder="设备串号(支持模糊)" clearable class="!w-[180px]" @keyup.enter="onDetailFilter" @clear="onDetailFilter" />
                        </div>

                        <div class="flt">
                            <span class="flt__l">&nbsp;</span>
                            <div class="flex items-center gap-2">
                                <el-button type="primary" @click="onDetailFilter">查询</el-button>
                                <el-button @click="resetDetailFilter">重置</el-button>
                            </div>
                        </div>
                    </div>
                    <el-table :data="detail.list" v-loading="detail.loading" size="large" empty-text="暂无数据" @sort-change="onSortChange"
                        :default-sort="{ prop: detail.sort_field, order: detail.sort_order === 'asc' ? 'ascending' : 'descending' }">
                        <el-table-column label="主体 / 对接人" min-width="180" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div v-if="row.entity_name" class="cursor-pointer font-medium text-[var(--el-color-primary)]" @click="openEntity(row.entity_id)">{{ row.entity_name }}</div>
                                <div v-else class="text-xs text-gray-400">未归属主体</div>
                                <div class="text-xs text-gray-500">{{ row.counterparty_name }}<span v-if="row.counterparty_mobile"> · {{ row.counterparty_mobile }}</span></div>
                            </template>
                        </el-table-column>
                        <el-table-column label="业务类型" width="100" align="center">
                            <template #default="{ row }"><el-tag size="small" effect="plain">{{ row.source_type_text }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="设备" min-width="170">
                            <template #default="{ row }">
                                <device-identity-cell :row="row" />
                            </template>
                        </el-table-column>
                        <el-table-column label="来源单号" min-width="150" show-overflow-tooltip>
                            <template #default="{ row }">
                                <span v-if="row.source_device_id > 0" class="cursor-pointer text-[var(--el-color-primary)]" @click="openTraceFromRow(row)">{{ row.source_no || '溯源' }}</span>
                                <span v-else>{{ row.source_no || '-' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="金额" width="120" align="right" prop="amount" sortable="custom">
                            <template #default="{ row }">{{ money(row.amount) }}</template>
                        </el-table-column>
                        <el-table-column label="已结" width="120" align="right" prop="settled_amount" sortable="custom">
                            <template #default="{ row }">{{ money(row.settled_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="未结" width="120" align="right" prop="outstanding" sortable="custom">
                            <template #default="{ row }"><span class="font-medium">{{ money(row.outstanding) }}</span></template>
                        </el-table-column>
                        <el-table-column label="状态" width="100" align="center">
                            <template #default="{ row }">
                                <el-tag :type="statusTagType(row.status)" effect="light" size="small">{{ row.status_text }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="经手人" width="100" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.operator" size="small" effect="plain" type="info">{{ row.operator }}</el-tag>
                                <span v-else class="text-gray-300">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="时间" width="160" prop="occurred_at" sortable="custom">
                            <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                        </el-table-column>
                        <el-table-column prop="remark" label="备注" min-width="140" show-overflow-tooltip />
                    </el-table>
                    <div class="mt-3 flex justify-end">
                        <el-pagination layout="total, prev, pager, next" :total="detail.total" :page-size="detail.limit" :current-page="detail.page" @current-change="onDetailPage" />
                    </div>
                </el-tab-pane>

                <!-- 结算记录 -->
                <el-tab-pane label="结算记录" name="settlement">
                    <ErpFilterBar v-model="settle" :fields="settleFilterFields" class="mb-3"
                        @search="onSettleSearch" @change="onSettleSearch" @reset="resetSettleFilter" />
                    <el-table :data="settle.list" v-loading="settle.loading" size="large" empty-text="暂无结算记录"
                        row-key="row_key" :tree-props="{ children: 'children' }">
                        <el-table-column label="结算单号" min-width="180" show-overflow-tooltip>
                            <template #default="{ row }">
                                <span v-if="row._group" class="font-medium text-[var(--el-color-primary)]">{{ row.settlement_no }}</span>
                                <span v-else>{{ row.settlement_no }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="主体 / 对接人" min-width="180" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div v-if="row.entity_name" class="cursor-pointer font-medium text-[var(--el-color-primary)]" @click.stop="openEntity(row.entity_id)">{{ row.entity_name }}</div>
                                <div v-else class="text-xs text-gray-400">未归属主体</div>
                                <div class="text-xs text-gray-500">
                                    <span v-if="row.is_entity">多人折账</span>
                                    <template v-else>{{ row.counterparty_name }}<span v-if="row.counterparty_mobile"> · {{ row.counterparty_mobile }}</span></template>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="结算对象（设备/单据）" min-width="180" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div v-if="row.targets && row.targets.length">
                                    <div v-for="(t, ti) in row.targets" :key="ti" class="text-xs">
                                        <span class="text-gray-700">{{ t.model || '账目' }}</span>
                                        <span v-if="t.source_no" class="text-gray-400"> · {{ t.source_no }}</span>
                                        <span class="text-gray-400"> · ¥{{ money(t.amount) }}</span>
                                    </div>
                                </div>
                                <span v-else class="text-gray-300">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="本次结算·应付" width="120" align="right"><template #default="{ row }">{{ money(row.payable_total) }}</template></el-table-column>
                        <el-table-column label="本次结算·应收" width="120" align="right"><template #default="{ row }">{{ money(row.receivable_total) }}</template></el-table-column>
                        <el-table-column label="折账" width="100" align="right"><template #default="{ row }">{{ money(row.offset_amount) }}</template></el-table-column>
                        <el-table-column label="现金" width="130" align="right">
                            <template #default="{ row }">
                                {{ money(row.cash_amount) }}
                                <span class="text-xs text-gray-400">{{ cashDirLabel(row.cash_direction) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="方式 / 户头" width="150" align="center">
                            <template #default="{ row }">
                                <span v-if="row._group" class="text-xs text-gray-400">多笔（展开看）</span>
                                <template v-else>
                                    <el-tag size="small" :type="methodTagType(row.method)" effect="light">{{ methodText(row.method) }}</el-tag>
                                    <div v-if="row.method !== 'offset' && row.account_name" class="mt-0.5 text-xs text-gray-500">{{ row.account_name }}</div>
                                    <div v-else-if="row.method !== 'offset'" class="mt-0.5 text-xs text-gray-300">未记户头</div>
                                </template>
                            </template>
                        </el-table-column>
                        <el-table-column label="经手人" width="100" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.operator_name" size="small" effect="plain" type="info">{{ row.operator_name }}</el-tag>
                                <span v-else class="text-gray-300">{{ row._group ? '多人' : '-' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="结算状态" width="110" align="center">
                            <template #default>
                                <el-tooltip placement="top" content="仅表示这笔结算动作已完成；该客户/设备账目是否全部结清，请看「往来汇总」或「应收/应付明细」的未结金额。">
                                    <el-tag type="success" size="small" effect="light">本次完成</el-tag>
                                </el-tooltip>
                            </template>
                        </el-table-column>
                        <el-table-column label="时间" width="160"><template #default="{ row }">{{ formatTime(row.occurred_at) }}</template></el-table-column>
                        <el-table-column prop="operator_name" label="操作人" width="90" show-overflow-tooltip />
                        <el-table-column prop="remark" label="备注 / 原由" min-width="180" show-overflow-tooltip>
                            <template #default="{ row }"><span :class="row.remark ? '' : 'text-gray-300'">{{ row.remark || '—' }}</span></template>
                        </el-table-column>
                        <el-table-column label="操作" width="120" align="center" fixed="right">
                            <template #default="{ row }">
                                <span v-if="row._group" class="text-xs text-gray-400">展开看每笔</span>
                                <el-button v-else type="primary" link @click="openSettleDetail(row)">核销明细</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="mt-3 flex justify-end">
                        <el-pagination layout="total, prev, pager, next" :total="settle.total" :page-size="settle.limit" :current-page="settle.page" @current-change="onSettlePage" />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 折账结算弹框 -->
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="880px" @closed="resetDialog">
            <div v-loading="dialogLoading">
                <el-alert type="info" :closable="false" class="mb-4"
                    title="勾选要一起结算的应付与应收。系统自动按 折账=min(应付,应收) 冲抵，余下一侧走现金。被勾选项均视为本次全额结清。" />
                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <div class="mb-2 font-medium text-orange-600">应付(我欠对方)</div>
                        <el-table :data="payables" size="small" @selection-change="onPayableSelect" max-height="280" empty-text="无待结应付">
                            <el-table-column type="selection" width="40" />
                            <el-table-column v-if="current?.is_entity" prop="counterparty_name" label="对接人" width="90" show-overflow-tooltip />
                            <el-table-column label="设备" min-width="130" show-overflow-tooltip>
                                <template #default="{ row }"><device-identity-cell :row="row" /></template>
                            </el-table-column>
                            <el-table-column label="待结" width="100" align="right"><template #default="{ row }">{{ money(row.outstanding) }}</template></el-table-column>
                        </el-table>
                    </div>
                    <div>
                        <div class="mb-2 font-medium text-green-600">应收(对方欠我)</div>
                        <el-table :data="receivables" size="small" @selection-change="onReceivableSelect" max-height="280" empty-text="无待结应收">
                            <el-table-column type="selection" width="40" />
                            <el-table-column v-if="current?.is_entity" prop="counterparty_name" label="对接人" width="90" show-overflow-tooltip />
                            <el-table-column label="设备" min-width="130" show-overflow-tooltip>
                                <template #default="{ row }"><device-identity-cell :row="row" /></template>
                            </el-table-column>
                            <el-table-column label="待结" width="100" align="right"><template #default="{ row }">{{ money(row.outstanding) }}</template></el-table-column>
                        </el-table>
                    </div>
                </div>
                <div class="mt-5 rounded-lg bg-gray-50 px-5 py-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">结算预演</div>
                        <el-button size="small" @click="doPreview" :loading="previewing" :disabled="!canPreview">重新计算</el-button>
                    </div>
                    <div v-if="preview" class="mt-3 grid grid-cols-4 gap-4 text-center">
                        <div><div class="text-xs text-gray-500">应付合计</div><div class="mt-1 font-semibold text-orange-600">{{ money(preview.payable_total) }}</div></div>
                        <div><div class="text-xs text-gray-500">应收合计</div><div class="mt-1 font-semibold text-green-600">{{ money(preview.receivable_total) }}</div></div>
                        <div><div class="text-xs text-gray-500">折账冲抵</div><div class="mt-1 font-semibold text-blue-600">{{ money(preview.offset_amount) }}</div></div>
                        <div><div class="text-xs text-gray-500">现金{{ cashDirLabel(preview.cash_direction) }}</div><div class="mt-1 font-semibold">{{ money(preview.cash_amount) }}</div></div>
                    </div>
                    <div v-else class="mt-3 text-sm text-gray-400">勾选应付/应收后将自动计算折账与现金净额。</div>
                    <div v-if="preview" class="mt-3 text-center"><el-tag :type="methodTagType(preview.method)" effect="light">结算方式：{{ preview.method_text }}</el-tag></div>
                </div>
                <div v-if="preview && Number(preview.cash_amount) > 0" class="mt-4">
                    <div class="mb-1 text-sm text-gray-500">现金{{ cashDirLabel(preview.cash_direction) }}账户(必选)：现金将从该资金账户{{ preview.cash_direction === 'pay' ? '出账' : '入账' }}</div>
                    <el-select v-model="settleAccountId" filterable class="w-full" placeholder="选择资金账户">
                        <el-option v-for="a in summary.accounts" :key="a.id" :label="`${a.account_name}（余额 ${money(a.balance)}）`" :value="a.id" />
                    </el-select>
                </div>
                <el-input v-model="remark" class="mt-4" type="textarea" :rows="2" placeholder="结算备注(可选)" maxlength="200" show-word-limit />
            </div>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" :disabled="!canSettle" @click="doSettle">确认结算</el-button>
            </template>
        </el-dialog>

        <!-- 付款 / 收款 弹框（纯现金，关联单据 + 资金账户） -->
        <el-dialog v-model="payColl.visible" :title="payCollTitle" width="640px">
            <div v-loading="payColl.loading">
                <el-alert type="info" :closable="false" class="mb-3"
                    :title="payColl.mode === 'pay' ? '勾选要付款的应付单据（来自回收），从所选资金账户出账并标记结清。' : '勾选要收款的应收单据（销售/同行出库挂账），收入所选资金账户并标记结清。'" />
                <el-table :data="payColl.rows" size="small" @selection-change="onPayCollSelect" max-height="300"
                    :empty-text="payColl.mode === 'pay' ? '无待付应付' : '无待收应收'">
                    <el-table-column type="selection" width="40" />
                    <el-table-column label="设备" min-width="160">
                        <template #default="{ row }">
                            <device-identity-cell :row="row" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="source_no" label="来源单" min-width="110" show-overflow-tooltip />
                    <el-table-column label="总额 / 已付" width="130" align="right">
                        <template #default="{ row }">
                            <div class="text-gray-700">{{ money(row.amount) }}</div>
                            <div v-if="Number(row.settled_amount) > 0" class="text-xs text-green-600">已付 {{ money(row.settled_amount) }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="待结" width="110" align="right">
                        <template #default="{ row }"><span class="font-medium text-orange-600">{{ money(row.outstanding) }}</span></template>
                    </el-table-column>
                </el-table>
                <div class="mt-3 text-right text-sm text-gray-500">已选 {{ payColl.selected.length }} 笔，合计
                    <b :class="payColl.mode === 'pay' ? 'text-orange-600' : 'text-green-600'">{{ money(payCollTotal) }}</b>
                </div>
                <div class="mt-3">
                    <div class="mb-1 text-sm text-gray-500">{{ payColl.mode === 'pay' ? '出账账户(必选)：现金从该账户付出' : '入账账户(必选)：现金收入该账户' }}</div>
                    <el-select v-model="payColl.accountId" filterable class="w-full" placeholder="选择资金账户">
                        <el-option v-for="a in summary.accounts" :key="a.id" :label="`${a.account_name}（余额 ${money(a.balance)}）`" :value="a.id" />
                    </el-select>
                </div>
                <el-input v-model="payColl.remark" class="mt-3" type="textarea" :rows="2" placeholder="备注(可选)" maxlength="200" show-word-limit />
            </div>
            <template #footer>
                <el-button @click="payColl.visible = false">取消</el-button>
                <el-button type="primary" :loading="payColl.submitting" :disabled="!payColl.selected.length || !payColl.accountId" @click="submitPayColl">
                    确认{{ payColl.mode === 'pay' ? '付款' : '收款' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 主体抽屉(信息/对接人/财务对账) -->
        <entity-drawer v-model="entityDrawer.visible" :entity-id="entityDrawer.id" @changed="onEntityChanged" />

        <!-- 账目溯源到设备: 点应收/应付来源单号打开设备全链路 -->
        <trace-detail v-model="trace.visible" :device-id="trace.deviceId" />

        <!-- 结算核销明细抽屉: 哪笔应付折哪笔应收 -->
        <el-drawer v-model="sdetail.visible" title="结算核销明细" size="720px" @closed="sdetail.data = null">
            <div v-loading="sdetail.loading">
                <div v-if="sdetail.data" class="mb-4 rounded-lg bg-gray-50 px-4 py-3 text-sm">
                    <div class="flex flex-wrap gap-x-6 gap-y-1">
                        <span>结算单：<b>{{ sdetail.data.settlement.settlement_no }}</b></span>
                        <span>主体/往来：<b>{{ sdetail.data.settlement.entity_name || sdetail.data.settlement.counterparty_name }}</b></span>
                        <span>方式：<el-tag size="small" :type="methodTagType(sdetail.data.settlement.method)" effect="light">{{ methodText(sdetail.data.settlement.method) }}</el-tag></span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-gray-600">
                        <span>折账冲抵：<b class="text-blue-600">{{ money(sdetail.data.settlement.offset_amount) }}</b></span>
                        <span>现金{{ cashDirLabel(sdetail.data.settlement.cash_direction) }}：<b>{{ money(sdetail.data.settlement.cash_amount) }}</b></span>
                        <span v-if="sdetail.data.settlement.account_name">户头：<b>{{ sdetail.data.settlement.account_name }}</b></span>
                        <span>时间：{{ formatTime(sdetail.data.settlement.occurred_at) }}</span>
                    </div>
                </div>

                <div class="mb-2 font-medium text-orange-600">应付侧(我欠对方,核销 {{ (sdetail.data?.payables || []).length }} 笔)</div>
                <el-table :data="sdetail.data?.payables || []" size="small" empty-text="无" class="mb-4">
                    <el-table-column label="对接人" min-width="90" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.counterparty_name }}<div v-if="row.counterparty_mobile" class="text-xs text-gray-400">{{ row.counterparty_mobile }}</div></template>
                    </el-table-column>
                    <el-table-column label="业务/设备" min-width="150" show-overflow-tooltip>
                        <template #default="{ row }">
                            <div class="text-xs text-gray-500 mb-0.5">{{ row.source_type_text }}</div>
                            <device-identity-cell :row="row" />
                        </template>
                    </el-table-column>
                    <el-table-column label="核销" width="90" align="right"><template #default="{ row }">{{ money(row.applied_amount) }}</template></el-table-column>
                    <el-table-column label="其中折账" width="90" align="right"><template #default="{ row }"><span class="text-blue-600">{{ money(row.offset_part) }}</span></template></el-table-column>
                    <el-table-column label="其中现金" width="90" align="right"><template #default="{ row }">{{ money(row.cash_part) }}</template></el-table-column>
                </el-table>

                <div class="mb-2 font-medium text-green-600">应收侧(对方欠我,核销 {{ (sdetail.data?.receivables || []).length }} 笔)</div>
                <el-table :data="sdetail.data?.receivables || []" size="small" empty-text="无">
                    <el-table-column label="对接人" min-width="90" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.counterparty_name }}<div v-if="row.counterparty_mobile" class="text-xs text-gray-400">{{ row.counterparty_mobile }}</div></template>
                    </el-table-column>
                    <el-table-column label="业务/设备" min-width="150" show-overflow-tooltip>
                        <template #default="{ row }">
                            <div class="text-xs text-gray-500 mb-0.5">{{ row.source_type_text }}</div>
                            <device-identity-cell :row="row" />
                        </template>
                    </el-table-column>
                    <el-table-column label="核销" width="90" align="right"><template #default="{ row }">{{ money(row.applied_amount) }}</template></el-table-column>
                    <el-table-column label="其中折账" width="90" align="right"><template #default="{ row }"><span class="text-blue-600">{{ money(row.offset_part) }}</span></template></el-table-column>
                    <el-table-column label="其中现金" width="90" align="right"><template #default="{ row }">{{ money(row.cash_part) }}</template></el-table-column>
                </el-table>
            </div>
        </el-drawer>

        <!-- 经营支出弹框 -->
        <el-dialog v-model="expense.visible" title="记一笔经营支出" width="460px">
            <el-form label-width="90px">
                <el-form-item label="出账账户" required>
                    <el-select v-model="expense.account_id" filterable class="w-full" placeholder="从哪个资金账户出">
                        <el-option v-for="a in summary.accounts" :key="a.id" :label="`${a.account_name}（余额 ${money(a.balance)}）`" :value="a.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="费用类型">
                    <el-select v-model="expense.category" filterable allow-create default-first-option class="w-full" placeholder="水电/房租/快递…">
                        <el-option v-for="c in expenseCategories" :key="c" :label="c" :value="c" />
                    </el-select>
                </el-form-item>
                <el-form-item label="金额" required>
                    <el-input-number v-model="expense.amount" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="对手方">
                    <el-input v-model.trim="expense.counterparty_name" placeholder="付给谁(可选)：如 国家电网 / 房东 / 顺丰" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="expense.remark" type="textarea" :rows="2" placeholder="如：6月房租 / 顺丰快递费" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="expense.visible = false">取消</el-button>
                <el-button type="primary" :loading="expense.submitting" @click="submitExpense">确认出账</el-button>
            </template>
        </el-dialog>

        <!-- 采购预付弹框:钱付了货没到 -->
        <el-dialog v-model="prepay.visible" title="采购预付（钱付了，货还没到）" width="480px">
            <el-form label-width="92px">
                <el-form-item label="往来单位" required>
                    <counterparty-select v-model="prepay.counterparty_id" value-field="member_id" role-type="supplier"
                        placeholder="搜索姓名 / 手机号选择供应商" @resolved="onPrepayResolved" />
                </el-form-item>
                <el-form-item label="付款账户" required>
                    <el-select v-model="prepay.account_id" filterable class="!w-full" placeholder="从哪个资金账户付">
                        <el-option v-for="a in summary.accounts" :key="a.id" :label="`${a.account_name}（余额 ${money(a.balance)}）`" :value="a.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="预付金额" required>
                    <el-input-number v-model="prepay.amount" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="prepay.remark" type="textarea" :rows="2" placeholder="如：采购10台 iPhone 定金 / 全款" />
                </el-form-item>
            </el-form>
            <div class="-mt-2 text-xs text-gray-400">现金即时出账，并生成一笔「采购预付」挂在该往来单位名下；货到手工建档生成应付后，到「折账」一键相抵即可，无需重复付款。</div>
            <template #footer>
                <el-button @click="prepay.visible = false">取消</el-button>
                <el-button type="primary" :loading="prepay.submitting" @click="submitPrepay">确认预付</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { CaretRight, Switch, Wallet, Coin, Money, Sort, ArrowUp, ArrowDown } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import EntityDrawer from './entity-drawer.vue'
import TraceDetail from '@/addon/hsx_erp/views/device_trace/trace-detail.vue'
import {
    getFinanceBalanceBoard,
    getFinancePayableOutstanding,
    getFinanceReceivableOutstanding,
    getFinanceGroupOutstanding,
    previewFinanceSettlement,
    settleFinance,
    getFinanceSummary,
    getFinancePayableList,
    getFinanceReceivableList,
    getFinanceSettlementList,
    getFinanceSettlementDetail,
    recordFinanceExpense,
    prepayFinance,
    getFinanceDetailFilterOptions,
} from '@/addon/hsx_erp/api/finance'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import AiAssistant from '@/addon/hsx_erp/components/ai-assistant/index.vue'
import DeviceIdentityCell from '@/addon/hsx_erp/components/device-identity-cell/index.vue'
import ErpFilterBar from '@/addon/hsx_erp/components/erp-filter-bar/index.vue'

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const netLabel = (d: string) => (d === 'pay' ? '我付' : d === 'collect' ? '我收' : '已平')
const cashDirLabel = (d: string) => (d === 'pay' ? '付出' : d === 'collect' ? '收取' : '')
const methodTagType = (m: string) => (m === 'offset' ? 'primary' : m === 'mixed' ? 'warning' : 'success')
const methodText = (m: string) => (m === 'offset' ? '折账' : m === 'mixed' ? '混合' : '现金')
const statusTagType = (s: string) => (s === 'settled' ? 'success' : s === 'partial' ? 'warning' : s === 'void' ? 'info' : 'danger')
const formatTime = (t: any) => {
    const n = Number(t || 0)
    if (!n) return '-'
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}
const statusOptions = [
    { value: 'pending', label: '待结算' },
    { value: 'partial', label: '部分结算' },
    { value: 'settled', label: '已结清' },
    { value: 'void', label: '已作废' },
]
const sortOptions = [
    { value: 'occurred_at:desc', label: '时间 最新优先' },
    { value: 'occurred_at:asc', label: '时间 最早优先' },
    { value: 'amount:desc', label: '金额 从高到低' },
    { value: 'amount:asc', label: '金额 从低到高' },
    { value: 'outstanding:desc', label: '未结额 从高到低' },
    { value: 'outstanding:asc', label: '未结额 从低到高' },
]
const expenseCategories = ['水电', '房租', '快递/物流', '办公', '工资', '其它']
const detailTabs = [
    { name: 'receivable', label: '应收明细' },
    { name: 'payable', label: '应付明细' },
]

const loading = ref(false)
const activeTab = ref('board')
const showSummary = ref(true) // 顶部汇总看板,可折叠以腾出列表空间

// 汇总
const summary = reactive<any>({ payable_total: 0, receivable_total: 0, net: 0, balance_total: 0, accounts: [] })
async function loadSummary() {
    try {
        const res: any = await getFinanceSummary()
        Object.assign(summary, res.data || {})
    } catch (e) { /* ignore */ }
}

// —— AI 财务分析：把本页财务数据作为上下文交给通用 AI 组件 ——
const aiFinanceQuestion = '请基于以上财务汇总与往来数据，分析当前应收应付与资金状况，指出欠款/回款风险，并按优先级给出处理建议。'
const buildAiFinancePayload = () => {
    return {
        context: {
            汇总: {
                应收未结_欠我: summary.receivable_total,
                应付未结_我欠: summary.payable_total,
                净额_应付减应收: summary.net,
                资金账户总余额: summary.balance_total,
                资金账户: (summary.accounts || []).map((a: any) => ({ 账户: a.account_name, 余额: a.balance })),
            },
            往来未结_前20: (board.value || []).slice(0, 20),
        },
    }
}

// 往来汇总(数据全量在前端，筛选+排序均在本地)
const board = ref<any[]>([])
const boardFilter = ref('')        // '' | offsetable | pay | collect
const boardSort = ref('offsetable:desc')
const boardSortOptions = [
    { value: 'offsetable:desc', label: '可折账 高→低' },
    { value: 'net:desc', label: '净额 高→低' },
    { value: 'net:asc', label: '净额 低→高' },
    { value: 'payable:desc', label: '应付 高→低' },
    { value: 'receivable:desc', label: '应收 高→低' },
]
// 往来汇总检索栏(本地聚合视图:主体/对接人 异步选择,选中后本地筛该人所属卡片)
const boardSearch = reactive<any>({ counterparty_id: undefined })
const boardFilterFields = [
    { key: 'counterparty_id', label: '主体/对接人', type: 'entity', valueField: 'counterparty_id', placeholder: '搜索姓名 / 手机号', width: 220 },
]
const filteredBoard = computed(() => {
    const cp = Number(boardSearch.counterparty_id || 0)
    let rows = board.value.slice()
    if (cp > 0) {
        // 命中：主体行本身 或 其下任意对接人 的 counterparty_id/entity_id
        const hit = (r: any): boolean =>
            Number(r.counterparty_id || 0) === cp || Number(r.entity_id || 0) === cp
        const hitDeep = (r: any): boolean =>
            hit(r) || (Array.isArray(r.children) && r.children.some((c: any) => hit(c) || hitDeep(c)))
        rows = rows.filter(hitDeep)
    }
    if (boardFilter.value === 'offsetable') rows = rows.filter((r: any) => Number(r.offsetable) > 0)
    else if (boardFilter.value === 'pay') rows = rows.filter((r: any) => Number(r.net) > 0)
    else if (boardFilter.value === 'collect') rows = rows.filter((r: any) => Number(r.net) < 0)
    const [f, o] = String(boardSort.value || 'offsetable:desc').split(':')
    const sign = o === 'asc' ? 1 : -1
    rows.sort((a: any, b: any) => (Number(a[f] || 0) - Number(b[f] || 0)) * sign)
    return rows
})

// 往来汇总卡片折叠：主体默认展开（看到对接人）；账目明细(第三级)默认折叠
const closedEntities = reactive(new Set<string>())
const openMembers = reactive(new Set<string>())
const toggleEntity = (k: string) => { closedEntities.has(k) ? closedEntities.delete(k) : closedEntities.add(k) }
const toggleMember = (k: string) => { openMembers.has(k) ? openMembers.delete(k) : openMembers.add(k) }
const netClass = (net: number) => (net > 0 ? 'is-pay' : (net < 0 ? 'is-collect' : 'is-zero'))
function onBoardSortChange({ prop, order }: any) {
    if (!order) { boardSort.value = 'offsetable:desc'; return }
    boardSort.value = `${prop}:${order === 'ascending' ? 'asc' : 'desc'}`
}
async function loadBoard() {
    loading.value = true
    try {
        const res: any = await getFinanceBalanceBoard()
        board.value = res.data || []
    } finally {
        loading.value = false
    }
}

// 应收/应付明细
const detail = reactive<any>({ list: [], loading: false, page: 1, limit: 15, total: 0, keyword: '', imei: '', operator: '', source_type: '', counterparty_id: undefined, settle_state: '', dateRange: [], amount_min: '', amount_max: '', sort_field: 'occurred_at', sort_order: 'desc', quickSort: 'occurred_at:desc' })
// 业务类型 / 经手人 下拉选项(随 tab 取实际值)
const filterOpts = reactive<{ source_types: any[]; operators: string[] }>({ source_types: [], operators: [] })
async function loadFilterOpts() {
    try {
        const res: any = await getFinanceDetailFilterOptions({ target: activeTab.value })
        filterOpts.source_types = res.data?.source_types || []
        filterOpts.operators = res.data?.operators || []
    } catch (e) { filterOpts.source_types = []; filterOpts.operators = [] }
}
function detailParams() {
    const [start, end] = Array.isArray(detail.dateRange) ? detail.dateRange : []
    return {
        keyword: detail.keyword, imei: detail.imei, operator: detail.operator, settle_state: detail.settle_state,
        source_type: detail.source_type, counterparty_id: detail.counterparty_id || 0,
        start_time: start ? Number(start) : 0,
        end_time: end ? Number(end) + 86399 : 0, // 含当日
        amount_min: detail.amount_min, amount_max: detail.amount_max,
        sort_field: detail.sort_field, sort_order: detail.sort_order,
        page: detail.page, limit: detail.limit,
    }
}
// 改筛选/排序回到第1页
function onDetailFilter() { detail.page = 1; loadDetail() }
function onQuickSort(v: string) {
    const [f, o] = String(v || 'occurred_at:desc').split(':')
    detail.sort_field = f; detail.sort_order = o === 'asc' ? 'asc' : 'desc'
    detail.page = 1; loadDetail()
}
// 点列头排序: el-table 给 {prop, order: 'ascending'|'descending'|null}
function onSortChange({ prop, order }: any) {
    if (!order) { detail.sort_field = 'occurred_at'; detail.sort_order = 'desc' }
    else { detail.sort_field = prop; detail.sort_order = order === 'ascending' ? 'asc' : 'desc' }
    detail.quickSort = `${detail.sort_field}:${detail.sort_order}`
    detail.page = 1; loadDetail()
}
async function loadDetail() {
    detail.loading = true
    try {
        const fn = activeTab.value === 'payable' ? getFinancePayableList : getFinanceReceivableList
        const res: any = await fn(detailParams())
        detail.list = res.data?.data || []
        detail.total = res.data?.total || 0
    } finally {
        detail.loading = false
    }
}
function onDetailPage(p: number) { detail.page = p; loadDetail() }
function resetDetailFilter() {
    Object.assign(detail, { keyword: '', imei: '', operator: '', source_type: '', counterparty_id: undefined, settle_state: '', dateRange: [], amount_min: '', amount_max: '', sort_field: 'occurred_at', sort_order: 'desc', quickSort: 'occurred_at:desc', page: 1 })
    loadDetail()
}

// 结算记录
const settle = reactive<any>({ list: [], loading: false, page: 1, limit: 15, total: 0, keyword: '', imei: '', operator: '', counterparty_id: undefined, dateRange: [] })
// 结算记录检索栏(标准化:实体异步 + 经手人 select + IMEI 精确 + 单号模糊 + 时间段)
const settleFilterFields = computed(() => [
    { key: 'counterparty_id', label: '主体/对接人', type: 'entity', valueField: 'counterparty_id', placeholder: '搜索姓名 / 手机号', width: 200 },
    { key: 'operator', label: '经手人', type: 'select', placeholder: '全部经手人', width: 150, options: (filterOpts.operators || []).map((o: string) => ({ label: o, value: o })) },
    { key: 'keyword', label: '结算单号', type: 'text', placeholder: '结算单号', width: 160 },
    { key: 'imei', label: 'IMEI', type: 'imei', match: 'exact', width: 180 },
    { key: 'dateRange', label: '时间', type: 'daterange', width: 260 },
])
// 结算记录的"按设备分组"已改由后端完成（跨页也不拆），前端直接渲染 settle.list（含父行 _group + children）
async function loadSettlement() {
    settle.loading = true
    try {
        const [start, end] = Array.isArray(settle.dateRange) ? settle.dateRange : []
        const res: any = await getFinanceSettlementList({
            keyword: settle.keyword,
            imei: settle.imei,
            operator: settle.operator,
            counterparty_id: settle.counterparty_id || 0,
            start_time: start ? Number(start) : 0,
            end_time: end ? Number(end) + 86399 : 0,
            page: settle.page, limit: settle.limit,
        })
        settle.list = res.data?.data || []
        settle.total = res.data?.total || 0
    } finally {
        settle.loading = false
    }
}
function onSettlePage(p: number) { settle.page = p; loadSettlement() }
function onSettleSearch() { settle.page = 1; loadSettlement() }
function resetSettleFilter() {
    Object.assign(settle, { keyword: '', imei: '', operator: '', counterparty_id: undefined, dateRange: [], page: 1 })
    loadSettlement()
}

// 结算核销明细抽屉
const sdetail = reactive<any>({ visible: false, loading: false, data: null })
async function openSettleDetail(row: any) {
    const sid = Number(row.id)
    if (row._group || !sid) return  // 合并父行无单条id，应展开看每笔
    sdetail.visible = true
    sdetail.loading = true
    sdetail.data = null
    try {
        const res: any = await getFinanceSettlementDetail(sid)
        sdetail.data = res.data || null
    } finally {
        sdetail.loading = false
    }
}

function onTabChange(name: string) {
    if (name === 'receivable' || name === 'payable') {
        // 切换应收/应付时,业务类型语义不同,清掉已选并重新拉取选项
        Object.assign(detail, { page: 1, source_type: '', operator: '', counterparty_id: undefined })
        loadFilterOpts()
        loadDetail()
    } else if (name === 'settlement') {
        settle.page = 1
        loadSettlement()
    } else if (name === 'board') {
        loadBoard()
    }
}

function refreshAll() {
    loadSummary()
    if (activeTab.value === 'board') loadBoard()
    else if (activeTab.value === 'settlement') loadSettlement()
    else loadDetail()
}

// 折账结算弹框
const dialogVisible = ref(false)
const dialogLoading = ref(false)
const current = ref<any>(null)
const payables = ref<any[]>([])
const receivables = ref<any[]>([])
const selectedPayables = ref<any[]>([])
const selectedReceivables = ref<any[]>([])
const preview = ref<any>(null)
const previewing = ref(false)
const submitting = ref(false)
const remark = ref('')
const settleAccountId = ref<number | undefined>(undefined)
const dialogTitle = computed(() => '结算 · ' + (current.value?.counterparty_name || ''))
const canPreview = computed(() => selectedPayables.value.length > 0 || selectedReceivables.value.length > 0)
const canSettle = computed(() => {
    const p = preview.value
    if (!p) return false
    if (!(p.payable_total > 0 || p.receivable_total > 0)) return false
    if (Number(p.cash_amount) > 0 && !settleAccountId.value) return false // 有现金必须选户头
    return true
})

// 结算作用域: 主体级传 member_ids+entity, 单人传 counterparty_id
function scopeParams() {
    const c = current.value
    if (c?.is_entity) return { member_ids: c.member_ids || [], entity_id: c.entity_id, entity_name: c.entity_name }
    return { counterparty_id: c?.counterparty_id }
}
async function openSettle(row: any) {
    current.value = row
    dialogVisible.value = true
    dialogLoading.value = true
    try {
        if (row.is_entity) {
            const res: any = await getFinanceGroupOutstanding(row.member_ids || [])
            payables.value = res.data?.payables || []
            receivables.value = res.data?.receivables || []
        } else {
            const [p, r]: any = await Promise.all([
                getFinancePayableOutstanding(row.counterparty_id),
                getFinanceReceivableOutstanding(row.counterparty_id),
            ])
            payables.value = p.data || []
            receivables.value = r.data || []
        }
    } finally {
        dialogLoading.value = false
    }
}
function onPayableSelect(rows: any[]) { selectedPayables.value = rows; autoPreview() }
function onReceivableSelect(rows: any[]) { selectedReceivables.value = rows; autoPreview() }
let previewTimer: any = null
function autoPreview() {
    preview.value = null
    if (previewTimer) clearTimeout(previewTimer)
    if (!canPreview.value) return
    previewTimer = setTimeout(doPreview, 250)
}
async function doPreview() {
    if (!canPreview.value || !current.value) return
    previewing.value = true
    try {
        const res: any = await previewFinanceSettlement({
            ...scopeParams(),
            payable_ids: selectedPayables.value.map((x) => x.id),
            receivable_ids: selectedReceivables.value.map((x) => x.id),
        })
        preview.value = res.data
    } finally {
        previewing.value = false
    }
}
async function doSettle() {
    if (!canSettle.value || !current.value) return
    const p = preview.value
    const tip = p.method === 'offset'
        ? `折账冲抵 ${money(p.offset_amount)}，无现金往来。`
        : `折账 ${money(p.offset_amount)} + 现金${cashDirLabel(p.cash_direction)} ${money(p.cash_amount)}。`
    try { await ElMessageBox.confirm(tip + ' 确认结算？', '确认结算', { type: 'warning' }) } catch { return }
    submitting.value = true
    try {
        await settleFinance({
            ...scopeParams(),
            payable_ids: selectedPayables.value.map((x) => x.id),
            receivable_ids: selectedReceivables.value.map((x) => x.id),
            remark: remark.value,
            capital_account_id: settleAccountId.value || 0,
        })
        ElMessage.success('结算完成')
        dialogVisible.value = false
        refreshAll()
    } finally {
        submitting.value = false
    }
}
function resetDialog() {
    current.value = null; payables.value = []; receivables.value = []
    selectedPayables.value = []; selectedReceivables.value = []; preview.value = null; remark.value = ''; settleAccountId.value = undefined
}

// 付款 / 收款（纯现金结算，复用 settle：只传一侧 ids + 资金账户）
const payColl = reactive<any>({ visible: false, mode: 'pay', loading: false, submitting: false, current: null, rows: [], selected: [], accountId: undefined, remark: '' })
const payCollTitle = computed(() => {
    const t = payColl.mode === 'pay' ? '付款（给客户）' : '收款（向客户/同行）'
    const c = payColl.current
    return c ? `${t} · ${c.counterparty_name || ('#' + c.counterparty_id)}` : t
})
const payCollTotal = computed(() => payColl.selected.reduce((s: number, r: any) => s + Number(r.outstanding || 0), 0))
function openPay(row: any) { openPayColl(row, 'pay') }
function openCollect(row: any) { openPayColl(row, 'collect') }
async function openPayColl(row: any, mode: 'pay' | 'collect') {
    Object.assign(payColl, { visible: true, mode, loading: true, current: row, rows: [], selected: [], accountId: undefined, remark: '' })
    if (!summary.accounts || !summary.accounts.length) loadSummary()
    try {
        const res: any = mode === 'pay'
            ? await getFinancePayableOutstanding(row.counterparty_id)
            : await getFinanceReceivableOutstanding(row.counterparty_id)
        payColl.rows = res.data || []
    } finally {
        payColl.loading = false
    }
}
function onPayCollSelect(rows: any[]) { payColl.selected = rows }
async function submitPayColl() {
    if (!payColl.selected.length || !payColl.accountId || !payColl.current) return
    const ids = payColl.selected.map((x: any) => x.id)
    const dirText = payColl.mode === 'pay' ? '付款' : '收款'
    const flow = payColl.mode === 'pay' ? '出账' : '入账'
    try { await ElMessageBox.confirm(`${dirText} ${money(payCollTotal.value)}，从所选账户${flow}。确认？`, dirText, { type: 'warning' }) } catch { return }
    payColl.submitting = true
    try {
        await settleFinance({
            counterparty_id: payColl.current.counterparty_id,
            payable_ids: payColl.mode === 'pay' ? ids : [],
            receivable_ids: payColl.mode === 'collect' ? ids : [],
            remark: payColl.remark,
            capital_account_id: payColl.accountId,
        })
        ElMessage.success(dirText + '完成')
        payColl.visible = false
        refreshAll()
    } finally {
        payColl.submitting = false
    }
}

// 经营支出
const expense = reactive<any>({ visible: false, submitting: false, account_id: undefined, category: '', amount: 0, counterparty_name: '', remark: '' })
function openExpense() {
    Object.assign(expense, { account_id: undefined, category: '', amount: 0, counterparty_name: '', remark: '' })
    if (!summary.accounts || !summary.accounts.length) loadSummary()
    expense.visible = true
}
async function submitExpense() {
    if (!expense.account_id) return ElMessage.warning('请选择出账账户')
    if (!Number(expense.amount) || Number(expense.amount) <= 0) return ElMessage.warning('请填写金额')
    expense.submitting = true
    try {
        await recordFinanceExpense({
            account_id: expense.account_id, amount: Number(expense.amount),
            category: expense.category, counterparty_name: expense.counterparty_name, remark: expense.remark,
        })
        ElMessage.success('已记一笔支出')
        expense.visible = false
        refreshAll()
    } catch (e: any) {
        ElMessage.error(e?.message || '记账失败')
    } finally {
        expense.submitting = false
    }
}

// 采购预付:钱付了货没到
const prepay = reactive<any>({ visible: false, submitting: false, counterparty_id: undefined, counterparty_name: '', account_id: undefined, amount: 0, remark: '' })
function openPrepay() {
    Object.assign(prepay, { counterparty_id: undefined, counterparty_name: '', account_id: undefined, amount: 0, remark: '' })
    if (!summary.accounts || !summary.accounts.length) loadSummary()
    prepay.visible = true
}
function onPrepayResolved(d: any) {
    prepay.counterparty_name = d ? (d.member_name || '') : ''
}
async function submitPrepay() {
    if (!prepay.counterparty_id) return ElMessage.warning('请选择往来单位')
    if (!prepay.account_id) return ElMessage.warning('请选择付款账户')
    if (!Number(prepay.amount) || Number(prepay.amount) <= 0) return ElMessage.warning('请填写预付金额')
    prepay.submitting = true
    try {
        await prepayFinance({
            counterparty_id: prepay.counterparty_id, counterparty_name: prepay.counterparty_name,
            account_id: prepay.account_id, amount: Number(prepay.amount), remark: prepay.remark,
        })
        ElMessage.success('已记一笔采购预付')
        prepay.visible = false
        refreshAll()
    } catch (e: any) {
        ElMessage.error(e?.message || '预付失败')
    } finally {
        prepay.submitting = false
    }
}

// 账目溯源到设备
const trace = reactive<any>({ visible: false, deviceId: 0 })
function openTraceFromRow(row: any) {
    if (!row.source_device_id) return
    trace.deviceId = row.source_device_id
    trace.visible = true
}

// 主体抽屉
const entityDrawer = reactive<any>({ visible: false, id: 0 })
function openEntity(id: number) {
    if (!id) return ElMessage.info('该往来未归属主体，请在出库/录入时归属或在此添加')
    entityDrawer.id = id
    entityDrawer.visible = true
}
function onEntityChanged() {
    refreshAll()
}

loadSummary()
loadBoard()
</script>

<style lang="scss" scoped>
/* 财务明细筛选:每项"标签在左、控件在右",清楚每个框是什么且省竖向空间 */
.flt { display: flex; flex-direction: row; align-items: center; gap: 6px; }
.flt__l { font-size: 12px; color: var(--el-text-color-secondary); line-height: 1; white-space: nowrap; flex-shrink: 0; }
.board-cards {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 60px;
}

.board-card {
    border: 1px solid #eef0f4;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    overflow: hidden;
    transition: box-shadow 0.18s;
}
.board-card:hover { box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06); }

/* 主体/独立人 头部 */
.bc-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    cursor: pointer;
    background: linear-gradient(180deg, #fafbfc, #fff);
}
.bc-main {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}
.bc-caret, .mr-caret {
    color: #b0b3bb;
    transition: transform 0.2s;
}
.bc-caret.open, .mr-caret.open { transform: rotate(90deg); color: var(--el-color-primary); }
.bc-name {
    font-size: 15px;
    font-weight: 600;
    color: #1f2733;
    cursor: pointer;
}
.bc-name:hover { color: var(--el-color-primary); }

.bc-amounts, .mr-amounts {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}
.amt { font-size: 13px; font-weight: 600; white-space: nowrap; }
.amt.pay { color: #e6792b; }
.amt.rec { color: #2ba471; }
.net { font-size: 13px; font-weight: 700; white-space: nowrap; }
.net i { font-size: 11px; font-weight: 400; font-style: normal; color: #b0b3bb; }
.net.is-pay { color: #e6792b; }
.net.is-collect { color: #2ba471; }
.net.is-zero { color: #b0b3bb; }
.bc-ops, .mr-ops { display: flex; align-items: center; gap: 2px; flex-shrink: 0; width: 70px; justify-content: flex-end; }

/* 对接人行 */
.bc-members { border-top: 1px solid #f2f4f7; }
.member-row { border-bottom: 1px solid #f6f7f9; }
.member-row:last-child { border-bottom: none; }
.mr-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px 10px 28px;
}
.mr-main {
    display: flex;
    align-items: center;
    gap: 7px;
    flex: 1;
    min-width: 0;
    cursor: pointer;
}
.mr-dot { width: 6px; height: 6px; border-radius: 50%; background: #dcdfe6; flex-shrink: 0; margin-left: 3px; }
.mr-name { font-size: 14px; color: #303133; }
.mr-mobile { font-size: 12px; color: #b0b3bb; }
.mr-count {
    font-size: 11px;
    color: #909399;
    background: #f0f2f5;
    border-radius: 8px;
    padding: 1px 7px;
}

/* 账目明细（第三级，默认折叠） */
.mr-items { background: #fafbfc; padding: 4px 0; }
.mr-items-flat { padding-left: 0; }
.item-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 16px 8px 50px;
    font-size: 13px;
    border-top: 1px dashed #eef0f4;
}
.mr-items-flat .item-row { padding-left: 28px; }
.it-title { color: #303133; font-weight: 500; }
.it-sub { color: #b0b3bb; font-size: 12px; }
.it-amt { color: #e6792b; font-weight: 600; margin-left: auto; }
.it-op {
    font-size: 12px;
    color: #5b8ff9;
    background: rgba(91, 143, 249, 0.1);
    border-radius: 8px;
    padding: 1px 8px;
}
.it-meta { width: 100%; color: #b0b3bb; font-size: 12px; padding-left: 2px; }
.it-total { color: #909399; font-weight: 600; }

/* 顶部汇总卡：accent 色条 + 图标水印 */
.stat-card {
    position: relative;
    border-radius: 12px;
    padding: 16px 18px;
    background: #fff;
    border: 1px solid #f0f2f5;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: var(--c);
}
.stat-ic {
    position: absolute;
    right: 10px;
    bottom: 6px;
    font-size: 64px;
    color: var(--c);
    opacity: 0.1;
    pointer-events: none;
}
.stat-label { font-size: 13px; color: #909399; position: relative; z-index: 1; }
.stat-value {
    margin-top: 6px;
    font-size: 24px;
    font-weight: 700;
    color: var(--c);
    line-height: 1.2;
    position: relative;
    z-index: 1;
}
.stat-tag { font-size: 12px; font-weight: 400; color: #b0b3bb; }
.acct-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
    position: relative;
    z-index: 1;
    max-height: 52px;
    overflow: hidden;
}
</style>
