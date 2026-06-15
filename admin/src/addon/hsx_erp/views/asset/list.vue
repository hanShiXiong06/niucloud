<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">设备流转</div>
                    <div class="mt-1 text-sm text-gray-500">一条主线跟着设备走：待入库 → 在库/整备 → 待定价 → 可售。按阶段切换，就地处理当前该做的动作。</div>
                </div>
                <div class="flex gap-2">
                    <el-button type="primary" @click="openManualInbound">手工建档入库</el-button>
                    <el-button :icon="Refresh" @click="loadList">刷新</el-button>
                </div>
            </div>

            <el-tabs v-model="search.inventory_status" class="mt-5" @tab-change="handleSearch">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="待入库" name="pending_in" />
                <el-tab-pane label="在库待整备" name="in_stock" />
                <el-tab-pane label="整备中" name="refurbishing" />
                <el-tab-pane :label="integrated ? '已交中台' : '待销售定价'" name="pending_pricing" />
                <el-tab-pane label="可售" name="available_for_sale" />
                <el-tab-pane label="已售/下架" name="sold" />
            </el-tabs>

            <el-form :inline="true" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input
                        v-model.trim="search.keyword"
                        clearable
                        class="!w-[260px]"
                        placeholder="资产编号 / IMEI / SN / 型号"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item label="仓库">
                    <el-select v-model="search.warehouse_id" placeholder="全部仓库" clearable filterable style="width: 180px" @change="handleSearch">
                        <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.warehouse_name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                    <el-button @click="exportCsv" :disabled="!table.data.length">导出当前页</el-button>
                </el-form-item>
            </el-form>

            <div class="mb-3 flex flex-wrap gap-4 rounded-lg bg-gray-50 px-4 py-2 text-sm">
                <span>共 <b class="text-[var(--el-color-primary)]">{{ summary.count }}</b> 台</span>
                <span>成本合计 <b class="text-orange-600">{{ money(summary.total_cost) }}</b></span>
                <span>参考售价合计 <b class="text-blue-600">{{ money(summary.total_sale) }}</b></span>
            </div>

            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    已选择 {{ selectedAssets.length }} 台待入库设备
                </div>
                <el-button
                    type="primary"
                    :disabled="selectedAssets.length === 0"
                    @click="batchConfirmInbound"
                >
                    批量确认入库{{ selectedAssets.length ? ` (${selectedAssets.length})` : '' }}
                </el-button>
            </div>

            <el-table
                :data="table.data"
                v-loading="table.loading"
                size="large"
                @selection-change="handleSelectionChange"
            >
                <el-table-column type="selection" width="52" :selectable="rowSelectable" />
                <el-table-column prop="asset_no" label="资产编号" min-width="180" />
                <el-table-column label="设备" min-width="260">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">IMEI：{{ row.imei || '-' }}</div>
                        <div class="text-xs text-gray-500">SN：{{ row.sn || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="source_device_id" label="来源设备ID" width="120" />
                <el-table-column label="往来单位" min-width="150">
                    <template #default="{ row }">{{ row.counterparty?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="归属" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.ownership_type === 'consign' ? 'warning' : 'success'" effect="plain">
                            {{ row.ownership_type === 'consign' ? '代卖' : '自有' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="成本" width="130" align="right">
                    <template #default="{ row }">¥{{ money(row.current_cost) }}</template>
                </el-table-column>
                <el-table-column label="参考售价" width="150" align="right">
                    <template #default="{ row }">
                        <template v-if="Number(row.current_sale_price) > 0">
                            <div class="font-medium text-gray-800">¥{{ money(row.current_sale_price) }}</div>
                            <div class="text-xs text-gray-400">{{ priceSource() }}</div>
                        </template>
                        <span v-else class="text-gray-300">—</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tooltip
                            :content="nextStepText(row.inventory_status)"
                            :disabled="!nextStepText(row.inventory_status)"
                            placement="top"
                        >
                            <span class="inline-flex cursor-default items-center gap-1">
                                <el-tag :type="statusType(row.inventory_status)">
                                    {{ flowStatusName(row.inventory_status) }}
                                </el-tag>
                                <el-icon class="text-gray-300"><InfoFilled /></el-icon>
                            </span>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column prop="stock_in_at" label="入库时间" width="180">
                    <template #default="{ row }">{{ formatTime(row.stock_in_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="250" align="center">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.inventory_status === 'pending_in'"
                            type="primary"
                            link
                            :loading="row._confirming"
                            @click="confirmInbound(row)"
                        >
                            确认入库
                        </el-button>
                        <el-button v-if="row.inventory_status === 'in_stock'" type="primary" link @click="startRefurbishment(row)">
                            发起整备
                        </el-button>
                        <el-button v-if="row.inventory_status === 'in_stock'" type="success" link @click="skipRefurbishment(row)">
                            无需整备
                        </el-button>
                        <!-- 独立模式:ERP 自己定价/调价 -->
                        <el-button
                            v-if="!integrated && ['pending_pricing', 'available_for_sale'].includes(row.inventory_status)"
                            type="primary"
                            link
                            @click="openPricing(row)"
                        >
                            {{ row.inventory_status === 'available_for_sale' ? '调价' : '定价' }}
                        </el-button>
                        <!-- 联合模式:定价交给中台,ERP 不再定价,仅提示进度 -->
                        <el-tooltip
                            v-else-if="integrated && row.inventory_status === 'pending_pricing'"
                            content="拍照与销售定价由数据中台完成，完成后自动回写参考价并转可售"
                            placement="top"
                        >
                            <el-tag type="info" effect="plain" size="small">中台处理中</el-tag>
                        </el-tooltip>
                        <el-button v-if="canTransfer(row)" type="warning" link @click="openTransfer(row)">调拨</el-button>
                        <el-button type="primary" link @click="openDetail(row)">详情</el-button>
                    </template>
                </el-table-column>

                <template #empty>
                    <EmptyState
                        v-if="search.keyword || search.inventory_status"
                        icon="search"
                        title="没有符合条件的设备"
                        description="换个关键词或库存状态再试试。"
                    />
                    <EmptyState
                        v-else
                        icon="box"
                        title="还没有库存设备"
                        description="点击「手工建档入库」录入第一台；或在回收订单确认回收后，设备会自动同步到这里的待入库池。"
                    >
                        <template #action>
                            <el-button type="primary" @click="openManualInbound">手工建档入库</el-button>
                        </template>
                    </EmptyState>
                </template>
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

        <el-dialog v-model="manualDialog.visible" title="手工建档入库" width="680px" destroy-on-close>
            <el-alert
                class="mb-4"
                type="info"
                :closable="false"
                title="ERP 可独立使用：手工录入后同样进入待入库，后续核对、库存和成本流水与回收同步设备完全一致。"
            />
            <el-form label-width="110px">
                <div class="grid grid-cols-2 gap-x-4">
                    <el-form-item label="设备型号" required>
                        <el-input v-model.trim="manualForm.model" placeholder="例如 iPhone 15 Pro" />
                    </el-form-item>
                    <el-form-item label="入库类型" required>
                        <el-select v-model="manualForm.business_type" class="w-full" @change="handleBusinessTypeChange">
                            <el-option label="回收客户" value="recycle" />
                            <el-option label="同行/供应商采购" value="purchase" />
                            <el-option label="代卖委托" value="consignment" />
                            <el-option label="期初库存" value="opening" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="往来单位" :required="manualForm.business_type !== 'opening'">
                        <div class="flex w-full gap-2">
                            <el-select v-model="manualForm.counterparty_id" filterable class="flex-1" placeholder="请选择货物来源">
                                <el-option
                                    v-for="item in counterpartyOptions"
                                    :key="item.id"
                                    :label="`${item.name}${item.mobile ? ` (${item.mobile})` : ''}`"
                                    :value="item.id"
                                />
                            </el-select>
                            <el-button @click="openQuickCounterparty">新增</el-button>
                        </div>
                    </el-form-item>
                    <el-form-item label="IMEI">
                        <el-input v-model.trim="manualForm.imei" />
                    </el-form-item>
                    <el-form-item label="IMEI2">
                        <el-input v-model.trim="manualForm.imei2" />
                    </el-form-item>
                    <el-form-item label="SN">
                        <el-input v-model.trim="manualForm.sn" />
                    </el-form-item>
                    <el-form-item label="容量">
                        <el-input v-model.trim="manualForm.capacity" placeholder="例如 256GB" />
                    </el-form-item>
                    <el-form-item label="颜色">
                        <el-input v-model.trim="manualForm.color" />
                    </el-form-item>
                    <el-form-item :label="manualForm.business_type === 'consignment' ? '入库成本' : '应付/成本'">
                        <el-input-number
                            v-model="manualForm.purchase_cost"
                            :min="0"
                            :precision="2"
                            :disabled="manualForm.business_type === 'consignment'"
                            class="!w-full"
                        />
                    </el-form-item>
                    <el-form-item label="已付金额">
                        <el-input-number
                            v-model="manualForm.paid_amount"
                            :min="0"
                            :max="manualForm.purchase_cost"
                            :precision="2"
                            :disabled="['consignment', 'opening'].includes(manualForm.business_type)"
                            class="!w-full"
                        />
                    </el-form-item>
                    <el-form-item label="销售价">
                        <el-input-number v-model="manualForm.suggested_sale_price" :min="0" :precision="2" class="!w-full" />
                    </el-form-item>
                    <el-form-item label="入库仓库">
                        <el-select v-model="manualForm.warehouse_id" filterable clearable class="w-full" placeholder="选仓库则入库即归位（不选则留待入库）" @change="manualForm.location_id = 0">
                            <el-option v-for="item in warehouseOptions" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="入库库位" :required="Number(manualForm.warehouse_id) > 0">
                        <el-select v-model="manualForm.location_id" filterable clearable class="w-full" :disabled="!Number(manualForm.warehouse_id)" placeholder="选了仓库需选库位">
                            <el-option v-for="item in manualLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </div>
                <el-alert
                    v-if="Number(manualForm.warehouse_id) > 0"
                    class="mb-4"
                    type="info"
                    :closable="false"
                    :title="Number(manualForm.suggested_sale_price) > 0 ? '已选库位+已填销售价：建档后自动确认入库并直接转「可售」，无需再单独定价。' : '已选库位：建档后自动确认入库到该库位；未填销售价则进入「待定价」。'"
                />
                <el-alert
                    class="mb-4"
                    :type="manualUnpaidAmount > 0 ? 'warning' : 'success'"
                    :closable="false"
                    :title="manualSettlementText"
                />
                <el-form-item label="备注">
                    <el-input v-model.trim="manualForm.remark" type="textarea" :rows="3" placeholder="录入来源、采购说明等" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="manualDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="manualDialog.submitting" @click="submitManualInbound">
                    创建待入库设备
                </el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="counterpartyDialog.visible" title="快速新增往来单位" width="520px">
            <el-form label-width="100px">
                <el-form-item label="单位类型">
                    <el-select v-model="counterpartyDialog.form.counterparty_type" class="w-full">
                        <el-option label="个人" value="individual" />
                        <el-option label="企业" value="company" />
                    </el-select>
                </el-form-item>
                <el-form-item label="名称" required><el-input v-model.trim="counterpartyDialog.form.name" /></el-form-item>
                <el-form-item label="手机号"><el-input v-model.trim="counterpartyDialog.form.mobile" /></el-form-item>
                <el-form-item label="联系人"><el-input v-model.trim="counterpartyDialog.form.contact_name" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="counterpartyDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="counterpartyDialog.loading" @click="submitQuickCounterparty">保存并选择</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detailVisible" title="ERP 设备详情" size="720px">
            <el-descriptions v-if="detail.asset" :column="2" border>
                <el-descriptions-item label="资产编号">{{ detail.asset.asset_no }}</el-descriptions-item>
                <el-descriptions-item label="库存状态">{{ statusName(detail.asset.inventory_status) }}</el-descriptions-item>
                <el-descriptions-item label="IMEI">{{ detail.asset.imei || '-' }}</el-descriptions-item>
                <el-descriptions-item label="SN">{{ detail.asset.sn || '-' }}</el-descriptions-item>
                <el-descriptions-item label="型号" :span="2">{{ detail.asset.model || '-' }}</el-descriptions-item>
                <el-descriptions-item label="往来单位">
                    {{ detail.counterparty?.name || '-' }}
                </el-descriptions-item>
                <el-descriptions-item label="联系电话">
                    {{ detail.counterparty?.mobile || '-' }}
                </el-descriptions-item>
                <el-descriptions-item label="采购成本">¥{{ money(detail.asset.purchase_cost) }}</el-descriptions-item>
                <el-descriptions-item label="当前总成本">¥{{ money(detail.asset.current_cost) }}</el-descriptions-item>
            </el-descriptions>

            <el-alert
                v-if="detail.asset"
                class="mt-4"
                :type="detail.asset.inventory_status === 'pending_in' ? 'warning' : 'info'"
                :closable="false"
                :title="nextStepText(detail.asset.inventory_status)"
            />

            <div class="mt-5 font-medium">库存流水</div>
            <el-table class="mt-3" :data="detail.stock_ledger || []" size="small" empty-text="暂无库存流水">
                <el-table-column prop="action" label="动作" width="120" />
                <el-table-column label="状态变化" min-width="160">
                    <template #default="{ row }">{{ statusName(row.before_status) }} → {{ statusName(row.after_status) }}</template>
                </el-table-column>
                <el-table-column prop="operator_name" label="操作人" width="120" />
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
            </el-table>

            <div class="mt-5 font-medium">成本流水</div>
            <el-table class="mt-3" :data="detail.cost_ledger || []" size="small" empty-text="暂无成本流水">
                <el-table-column prop="cost_type" label="成本类型" width="120" />
                <el-table-column label="变动金额" width="120" align="right">
                    <template #default="{ row }">¥{{ money(row.amount_delta) }}</template>
                </el-table-column>
                <el-table-column label="变动后成本" width="130" align="right">
                    <template #default="{ row }">¥{{ money(row.after_cost) }}</template>
                </el-table-column>
                <el-table-column prop="operator_name" label="操作人" width="120" />
                <el-table-column prop="remark" label="说明" min-width="180" show-overflow-tooltip />
            </el-table>

            <div class="mt-5 font-medium">操作时间线</div>
            <el-timeline class="mt-4">
                <el-timeline-item
                    v-for="item in detail.timeline || []"
                    :key="item.id"
                    :timestamp="formatTime(item.occurred_at)"
                >
                    {{ item.action }} · {{ item.operator_name || '系统' }}
                </el-timeline-item>
            </el-timeline>
        </el-drawer>

        <el-dialog v-model="inbound.visible" title="确认入库位置" width="560px">
            <el-form label-width="100px">
                <el-form-item label="入库仓库" required>
                    <el-select v-model="inbound.warehouse_id" class="w-full" @change="inbound.location_id = 0">
                        <el-option v-for="item in warehouseOptions" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="入库库位" required>
                    <el-select v-model="inbound.location_id" class="w-full">
                        <el-option v-for="item in availableLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注"><el-input v-model.trim="inbound.remark" type="textarea" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="inbound.visible = false">取消</el-button>
                <el-button type="primary" :loading="inbound.loading" @click="submitInbound">确认入库</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="transfer.visible" title="调拨" width="560px">
            <el-form label-width="100px">
                <el-form-item label="设备">
                    <span class="text-gray-600">{{ transfer.asset?.model || '-' }}（IMEI {{ transfer.asset?.imei || '-' }}）</span>
                </el-form-item>
                <el-form-item label="当前库位">
                    <el-tag v-if="currentWarehouseName" type="info" effect="plain" size="small">
                        {{ currentWarehouseName }}<template v-if="currentLocationName"> / {{ currentLocationName }}</template>
                    </el-tag>
                    <span v-else class="text-gray-400">未归位</span>
                </el-form-item>
                <el-form-item label="目标库位" required>
                    <el-tree-select
                        v-model="transfer.target_value"
                        :data="transferTreeData"
                        node-key="value"
                        :props="{ label: 'label', children: 'children', disabled: 'disabled' }"
                        :render-after-expand="false"
                        check-strictly
                        default-expand-all
                        class="w-full"
                        placeholder="选择目标仓库 / 库位"
                        @change="onTargetChange"
                    />
                    <div class="text-xs text-gray-400 mt-1">仓库为父节点、库位为子节点；选到库位即归位，选仓库则暂不指定库位。</div>
                </el-form-item>
                <el-form-item v-if="showConsignChoice" label="代卖处理" required>
                    <el-radio-group v-model="transfer.consign_action">
                        <el-radio value="list">上架代卖（卖出时再结寄卖人）</el-radio>
                        <el-radio value="buyout">我方买断（立即应付寄卖人）</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="showConsignChoice && transfer.consign_action === 'buyout'" label="买断价" required>
                    <el-input-number v-model="transfer.buyout_price" :min="0" :precision="2" :controls="false" class="!w-[180px]" />
                    <span class="text-xs text-gray-400 ml-2">买断价计入成本，并对寄卖人生成应付</span>
                </el-form-item>
                <el-alert
                    v-if="transferBlocked"
                    class="mb-3"
                    type="error"
                    :closable="false"
                    show-icon
                    title="调拨受限"
                    :description="transferBlockedMsg"
                />
                <el-form-item label="备注"><el-input v-model.trim="transfer.remark" type="textarea" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="transfer.visible = false">取消</el-button>
                <el-button type="primary" :loading="transfer.loading" :disabled="transferBlocked" @click="submitTransfer">确认调拨</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="priceDialog.visible" :title="priceDialog.asset?.inventory_status === 'available_for_sale' ? '调价' : '销售定价'" width="480px">
            <el-form label-width="90px">
                <el-form-item label="设备">
                    <span class="text-gray-600">{{ priceDialog.asset?.model || '-' }}（IMEI {{ priceDialog.asset?.imei || '-' }}）</span>
                </el-form-item>
                <el-form-item label="采购成本">
                    <span class="text-gray-600">¥{{ money(priceDialog.asset?.purchase_cost) }}</span>
                </el-form-item>
                <el-form-item label="销售价" required>
                    <el-input-number v-model="priceDialog.sale_price" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="最低利润">
                    <el-input-number v-model="priceDialog.min_profit" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="priceDialog.remark" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="priceDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="priceDialog.submitting" @click="submitPricing">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search, InfoFilled } from '@element-plus/icons-vue'
import { useRouter } from 'vue-router'
import {
    batchConfirmErpAssetInbound,
    confirmErpAssetInbound,
    createErpManualInbound,
    getErpAssetInfo,
    getErpAssetList,
    getErpIntegrationStatus
} from '@/addon/hsx_erp/api/asset'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { getErpCounterpartyOptions, saveErpCounterparty } from '@/addon/hsx_erp/api/counterparty'
import { skipErpRefurbishment } from '@/addon/hsx_erp/api/refurbishment'
import { transferErpAsset } from '@/addon/hsx_erp/api/outbound'
import { saveErpAssetPrice } from '@/addon/hsx_erp/api/pricing'
import EmptyState from '@/addon/hsx_erp/components/empty-state/index.vue'

const router = useRouter()
const search = reactive({ keyword: '', inventory_status: '', warehouse_id: '' as any })
const summary = reactive({ count: 0, total_cost: 0, total_sale: 0 })
const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
// 是否已接入中台(数据中台)：接入后拍照/定价交给中台，ERP 不再自行定价
const integrated = ref(false)
const table = reactive({ data: [] as any[], total: 0, page: 1, limit: 20, loading: false })
const detailVisible = ref(false)
const detail = reactive<any>({ asset: null, timeline: [] })
const selectedAssets = ref<any[]>([])
const warehouseOptions = ref<any[]>([])
const counterpartyOptions = ref<any[]>([])
const inbound = reactive<any>({
    visible: false, loading: false, warehouse_id: 0, location_id: 0, remark: '', assetIds: []
})
const availableLocations = computed(() =>
    warehouseOptions.value.find((item: any) => Number(item.id) === Number(inbound.warehouse_id))?.locations || []
)

// 调拨
const transfer = reactive<any>({
    visible: false, loading: false, asset: null,
    to_warehouse_id: 0, to_location_id: 0, target_value: '', remark: '',
    consign_action: 'list', buyout_price: 0
})
const transferLocations = computed(() =>
    warehouseOptions.value.find((item: any) => Number(item.id) === Number(transfer.to_warehouse_id))?.locations || []
)
// 反显设备当前所在仓库/库位（名称从已加载的 warehouseOptions 解析，资产行只带 id）
const currentWarehouseName = computed(() => {
    const wid = Number(transfer.asset?.warehouse_id || 0)
    if (!wid) return ''
    // 优先用后端补的名称，其次从 warehouseOptions 解析，最后兜底显示 id
    if (transfer.asset?.warehouse_name) return transfer.asset.warehouse_name
    const wh = warehouseOptions.value.find((w: any) => Number(w.id) === wid)
    return wh?.warehouse_name || ('仓#' + wid)
})
const currentLocationName = computed(() => {
    const wid = Number(transfer.asset?.warehouse_id || 0)
    const lid = Number(transfer.asset?.location_id || 0)
    if (!lid) return ''
    if (transfer.asset?.location_name) return transfer.asset.location_name
    const wh = warehouseOptions.value.find((w: any) => Number(w.id) === wid)
    const loc = (wh?.locations || []).find((l: any) => Number(l.id) === lid)
    return loc?.location_name || ('库位#' + lid)
})
// 目标仓库/库位树：仓库为父、库位为子。代卖仓锁死：不接受调入；代卖仓设备只能调去二手机仓。
const warehouseTypeLabel = (t: string) =>
    (({ mall: '二手机仓', peer: '同行仓', consignment: '代卖仓', hold: '暂存仓' }) as Record<string, string>)[t] || ''
// 设备当前所在仓的业务类型
const transferFromType = computed(() => {
    const wid = Number(transfer.asset?.warehouse_id || 0)
    return String(warehouseOptions.value.find((w: any) => Number(w.id) === wid)?.business_type || '')
})
const transferTreeData = computed(() => {
    const fromConsign = transferFromType.value === 'consignment'
    return warehouseOptions.value.map((w: any) => {
        const t = String(w.business_type || '')
        // 目标仓未开启「允许调入」则禁用；代卖仓设备只能调去二手机仓
        const blocked = Number(w.allow_inbound ?? 1) !== 1 || (fromConsign && t !== 'mall')
        const tl = warehouseTypeLabel(t)
        return {
            value: 'w:' + w.id,
            label: w.warehouse_name + (tl ? `（${tl}）` : '') + (blocked ? ' · 不可调入' : ''),
            disabled: blocked,
            children: (w.locations || []).map((l: any) => ({
                value: `l:${w.id}:${l.id}`,
                label: l.location_name,
                disabled: blocked
            }))
        }
    })
})
const transferToWarehouse = computed(() =>
    warehouseOptions.value.find((w: any) => Number(w.id) === Number(transfer.to_warehouse_id))
)
const onTargetChange = (val: string) => {
    if (!val) { transfer.to_warehouse_id = 0; transfer.to_location_id = 0; return }
    if (val.startsWith('w:')) {
        transfer.to_warehouse_id = Number(val.slice(2))
        transfer.to_location_id = 0
    } else if (val.startsWith('l:')) {
        const parts = val.split(':')
        transfer.to_warehouse_id = Number(parts[1])
        transfer.to_location_id = Number(parts[2])
    }
}
const transferTargetType = computed(() =>
    String(warehouseOptions.value.find((item: any) => Number(item.id) === Number(transfer.to_warehouse_id))?.business_type || '')
)
// 代卖仓锁死规则 → 禁用确认并提示
const transferBlocked = computed(() => {
    const wh = transferToWarehouse.value
    if (!wh) return false
    if (Number(wh.allow_inbound ?? 1) !== 1) return true                                        // 目标仓未开启允许调入
    if (transferFromType.value === 'consignment' && String(wh.business_type || '') !== 'mall') return true  // 代卖仓设备只能去二手机仓
    return false
})
const transferBlockedMsg = computed(() => {
    const wh = transferToWarehouse.value
    if (!wh) return ''
    if (Number(wh.allow_inbound ?? 1) !== 1) return '目标仓库未开启「允许调入」，请在仓库管理中开启，或改选其它目标仓。'
    if (transferFromType.value === 'consignment' && String(wh.business_type || '') !== 'mall') return '代卖仓设备只能调拨到二手机仓（买断转回收），不能调往其它仓。'
    return ''
})
// 代卖设备调进二手机仓(商城)时，需要选择"上架代卖 or 我方买断"
const showConsignChoice = computed(() =>
    transfer.asset && String(transfer.asset.ownership_type) === 'consign' && transferTargetType.value === 'mall'
)
const canTransfer = (row: any) => !['pending_in', 'outbound', 'locked'].includes(String(row.inventory_status))
const openTransfer = (row: any) => {
    transfer.asset = row
    // 反显：默认选中设备当前所在的仓库/库位
    const wid = Number(row.warehouse_id || 0)
    const lid = Number(row.location_id || 0)
    transfer.to_warehouse_id = wid
    transfer.to_location_id = lid
    transfer.target_value = lid > 0 ? `l:${wid}:${lid}` : (wid > 0 ? `w:${wid}` : '')
    transfer.remark = ''
    transfer.consign_action = 'list'
    transfer.buyout_price = 0
    transfer.visible = true
}
const submitTransfer = async () => {
    if (!transfer.to_warehouse_id) { ElMessage.warning('请选择目标仓库'); return }
    if (showConsignChoice.value && transfer.consign_action === 'buyout' && Number(transfer.buyout_price) <= 0) {
        ElMessage.warning('我方买断必须填写买断价'); return
    }
    transfer.loading = true
    try {
        const payload: any = {
            asset_ids: [Number(transfer.asset.id)],
            to_warehouse_id: Number(transfer.to_warehouse_id),
            to_location_id: Number(transfer.to_location_id),
            remark: transfer.remark,
            consign_action: transfer.consign_action
        }
        if (showConsignChoice.value && transfer.consign_action === 'buyout') {
            payload.buyout_prices = [{ asset_id: Number(transfer.asset.id), amount: Number(transfer.buyout_price) }]
        }
        await transferErpAsset(payload)
        ElMessage.success('调拨成功')
        transfer.visible = false
        loadList()
    } finally {
        transfer.loading = false
    }
}
const manualDialog = reactive({ visible: false, submitting: false })
const manualForm = reactive({
    model: '',
    business_type: 'recycle',
    counterparty_id: 0,
    imei: '',
    imei2: '',
    sn: '',
    capacity: '',
    color: '',
    purchase_cost: 0,
    paid_amount: 0,
    suggested_sale_price: 0,
    warehouse_id: 0,
    location_id: 0,
    remark: ''
})
// 手工建档可选入库库位（与确认入库共用 warehouseOptions）
const manualLocations = computed(() =>
    warehouseOptions.value.find((item: any) => Number(item.id) === Number(manualForm.warehouse_id))?.locations || []
)
const counterpartyDialog = reactive<any>({
    visible: false,
    loading: false,
    form: { counterparty_type: 'individual', name: '', mobile: '', contact_name: '' }
})
const manualUnpaidAmount = computed(() =>
    Math.max(0, Number(manualForm.purchase_cost || 0) - Number(manualForm.paid_amount || 0))
)
const manualSettlementText = computed(() => {
    if (manualForm.business_type === 'consignment') return '代卖入库：暂不形成采购成本和应付，销售后按代卖结算规则处理。'
    if (manualForm.business_type === 'opening') return `期初成本 ¥${money(manualForm.purchase_cost)}，不自动形成外部应付。`
    return `应付 ¥${money(manualForm.purchase_cost)}，已付 ¥${money(manualForm.paid_amount)}，未付 ¥${money(manualUnpaidAmount.value)}`
})

const loadList = async () => {
    table.loading = true
    try {
        const res: any = await getErpAssetList({ ...search, page: table.page, limit: table.limit })
        table.data = res.data?.data || []
        table.total = Number(res.data?.total || 0)
        const s = res.data?.summary || {}
        summary.count = Number(s.count || 0)
        summary.total_cost = Number(s.total_cost || 0)
        summary.total_sale = Number(s.total_sale || 0)
    } finally {
        table.loading = false
    }
}

const handleSearch = () => {
    table.page = 1
    loadList()
}

const handleReset = () => {
    search.keyword = ''
    search.inventory_status = ''
    search.warehouse_id = ''
    handleSearch()
}

// 导出当前页为 CSV
const exportCsv = () => {
    const head = ['资产编号', 'IMEI', 'SN', '型号', '仓库', '成本', '参考售价', '状态']
    const rows = table.data.map((r: any) => [
        r.asset_no || '', r.imei || '', r.sn || '', r.model || '', r.warehouse_name || '',
        Number(r.current_cost || 0).toFixed(2), Number(r.current_sale_price || 0).toFixed(2), statusName(r.inventory_status),
    ])
    const csv = [head, ...rows].map((line) => line.map((c: any) => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n')
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = '资产清单_' + Date.now() + '.csv'
    a.click()
    URL.revokeObjectURL(a.href)
}

const resetManualForm = () => {
    Object.assign(manualForm, {
        model: '',
        business_type: 'recycle',
        counterparty_id: 0,
        imei: '',
        imei2: '',
        sn: '',
        capacity: '',
        color: '',
        purchase_cost: 0,
        paid_amount: 0,
        suggested_sale_price: 0,
        warehouse_id: 0,
        location_id: 0,
        remark: ''
    })
    // 默认带出默认仓库及其首个库位，省一步点选
    const def = warehouseOptions.value.find((item: any) => item.is_default === 1) || warehouseOptions.value[0]
    if (def) {
        manualForm.warehouse_id = Number(def.id || 0)
        manualForm.location_id = Number(def.locations?.[0]?.id || 0)
    }
}

const handleBusinessTypeChange = () => {
    if (['consignment', 'opening'].includes(manualForm.business_type)) {
        manualForm.paid_amount = 0
    }
    if (manualForm.business_type === 'consignment') manualForm.purchase_cost = 0
}

const openManualInbound = () => {
    resetManualForm()
    manualDialog.visible = true
}

const submitManualInbound = async () => {
    if (!manualForm.model) {
        ElMessage.warning('请填写设备型号')
        return
    }
    if (!manualForm.imei && !manualForm.sn) {
        ElMessage.warning('IMEI 和 SN 至少填写一个')
        return
    }
    if (manualForm.business_type !== 'opening' && !manualForm.counterparty_id) {
        ElMessage.warning('请选择往来单位')
        return
    }
    if (Number(manualForm.paid_amount) > Number(manualForm.purchase_cost)) {
        ElMessage.warning('已付金额不能大于应付金额')
        return
    }
    if (Number(manualForm.warehouse_id) > 0 && !Number(manualForm.location_id)) {
        ElMessage.warning('选择了入库仓库，请同时选择库位')
        return
    }
    manualDialog.submitting = true
    try {
        await createErpManualInbound({ ...manualForm })
        ElMessage.success(Number(manualForm.warehouse_id) > 0 ? '已建档并入库到指定库位' : '已创建待入库设备')
        manualDialog.visible = false
        search.inventory_status = 'pending_in'
        table.page = 1
        await loadList()
    } finally {
        manualDialog.submitting = false
    }
}

const loadCounterparties = async () => {
    const res: any = await getErpCounterpartyOptions()
    counterpartyOptions.value = res.data || []
}

const openQuickCounterparty = () => {
    Object.assign(counterpartyDialog.form, {
        counterparty_type: 'individual',
        name: '',
        mobile: '',
        contact_name: '',
        role_type: manualForm.business_type === 'consignment' ? 'consignor' : 'supplier'
    })
    counterpartyDialog.visible = true
}

const submitQuickCounterparty = async () => {
    if (!counterpartyDialog.form.name) return ElMessage.warning('请填写往来单位名称')
    counterpartyDialog.loading = true
    try {
        const res: any = await saveErpCounterparty(0, { ...counterpartyDialog.form, status: 1 })
        await loadCounterparties()
        manualForm.counterparty_id = Number(res.data || 0)
        counterpartyDialog.visible = false
        ElMessage.success('往来单位已新增并选中')
    } finally {
        counterpartyDialog.loading = false
    }
}

const rowSelectable = (row: any) => row.inventory_status === 'pending_in'
const handleSelectionChange = (rows: any[]) => {
    selectedAssets.value = rows.filter(rowSelectable)
}

const confirmInbound = async (row: any) => {
    inbound.assetIds = [Number(row.id)]
    inbound.remark = ''
    inbound.visible = true
}

const batchConfirmInbound = async () => {
    const assetIds = selectedAssets.value.map((item: any) => Number(item.id))
    if (assetIds.length === 0) {
        ElMessage.warning('请先勾选待入库设备')
        return
    }
    inbound.assetIds = assetIds
    inbound.remark = ''
    inbound.visible = true
}

const submitInbound = async () => {
    if (!inbound.warehouse_id || !inbound.location_id) {
        ElMessage.warning('请选择入库仓库和库位')
        return
    }
    inbound.loading = true
    try {
        const data = { warehouse_id: inbound.warehouse_id, location_id: inbound.location_id, remark: inbound.remark }
        const res: any = inbound.assetIds.length === 1
            ? await confirmErpAssetInbound(inbound.assetIds[0], data)
            : await batchConfirmErpAssetInbound(inbound.assetIds, data)
        ElMessage.success(`已确认入库 ${Number(res.data?.confirmed_count || inbound.assetIds.length)} 台`)
        inbound.visible = false
        selectedAssets.value = []
        await loadList()
    } finally {
        inbound.loading = false
    }
}

const openDetail = async (row: any) => {
    const res: any = await getErpAssetInfo(row.id)
    Object.assign(detail, res.data || {})
    detailVisible.value = true
}

const startRefurbishment = (row: any) => {
    router.push({ path: '/hsx_erp/refurbishment', query: { asset_id: String(row.id) } })
}

// 改为内联定价/调价弹框（不再跳转到独立的"销售定价"页）
const priceDialog = reactive<any>({
    visible: false, submitting: false, asset: null, sale_price: 0, min_profit: 0, remark: ''
})
const openPricing = (row: any) => {
    priceDialog.asset = row
    priceDialog.sale_price = Number(row.current_sale_price || 0)
    priceDialog.min_profit = 0
    priceDialog.remark = ''
    priceDialog.visible = true
}
const submitPricing = async () => {
    if (!priceDialog.asset?.id) return
    if (Number(priceDialog.sale_price) <= 0) {
        ElMessage.warning('请填写销售价')
        return
    }
    priceDialog.submitting = true
    try {
        await saveErpAssetPrice(Number(priceDialog.asset.id), {
            sale_price: Number(priceDialog.sale_price),
            min_profit: Number(priceDialog.min_profit || 0),
            remark: priceDialog.remark || ''
        })
        ElMessage.success('已保存销售定价')
        priceDialog.visible = false
        loadList()
    } catch (error: any) {
        ElMessage.error(error?.message || '定价失败')
    } finally {
        priceDialog.submitting = false
    }
}

const skipRefurbishment = async (row: any) => {
    await ElMessageBox.confirm(
        `确认 ${row.model || row.asset_no} 无需整备，直接进入待销售定价吗？`,
        '无需整备',
        { type: 'warning', confirmButtonText: '进入待销售定价', cancelButtonText: '取消' }
    )
    await skipErpRefurbishment(Number(row.id), { remark: '库存工作台确认无需整备' })
    ElMessage.success('设备已进入待销售定价')
    await loadList()
}

const money = (value: any) => Number(value || 0).toFixed(2)
const statusName = (status: string) => ({
    pending_in: '待入库',
    inbound_rejected: '入库驳回',
    in_stock: '在库',
    refurbishing: '整备中',
    pending_pricing: '待销售定价',
    available_for_sale: '可售',
    locked: '已完成',
    outbound: '已完成'
}[status] || status || '-')
const statusType = (status: string) => ({
    pending_in: 'warning',
    inbound_rejected: 'danger',
    in_stock: 'success',
    refurbishing: 'warning',
    pending_pricing: 'primary',
    available_for_sale: 'success',
    locked: 'success',
    outbound: 'success'
}[status] || 'info')
const nextStepText = (status: string) => ({
    pending_in: '下一步：核对串号、型号和成本后确认入库',
    inbound_rejected: '下一步：修正驳回信息后重新提交入库',
    in_stock: '下一步：发起整备，或确认无需整备后直接进入待销售定价',
    refurbishing: '下一步：完成整备验收并确认实际费用',
    pending_pricing: '下一步：创建销售定价单，确认销售价和最低利润',
    available_for_sale: '下一步：客户锁定后创建销售单并出库'
}[status] || '请根据设备当前状态继续处理')
const formatTime = (value: any) => {
    if (!value) return '-'
    const date = new Date(Number(value) * 1000)
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('zh-CN')
}

const loadWarehouses = async () => {
    const res: any = await getErpWarehouseOptions()
    warehouseOptions.value = res.data || []
    const defaultWarehouse = warehouseOptions.value.find((item: any) => item.is_default === 1) || warehouseOptions.value[0]
    inbound.warehouse_id = Number(defaultWarehouse?.id || 0)
    inbound.location_id = Number(defaultWarehouse?.locations?.[0]?.id || 0)
}

const loadIntegration = async () => {
    try {
        const res: any = await getErpIntegrationStatus()
        integrated.value = !!res.data?.device_asset_connected
    } catch (e) {
        integrated.value = false
    }
}

// 列表与状态标签的展示名：联合模式下"待销售定价"语义其实是"已交中台·处理中"
const flowStatusName = (status: string) =>
    integrated.value && status === 'pending_pricing' ? '已交中台·处理中' : statusName(status)

// 参考售价来源标注：均为"参考价"，真实成交价在销售环节产生
const priceSource = () => (integrated.value ? '中台参考价' : '门店参考价')

onMounted(() => Promise.all([loadIntegration(), loadList(), loadWarehouses(), loadCounterparties()]))
</script>
