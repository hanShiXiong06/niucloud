<template>
    <PremiumTheme class="return-order-list">
        <el-card class="box-card" shadow="never">
            <template #header>
                <div class="card-header">
                    <span>退回订单列表</span>
                    <div class="header-actions">
                        <!-- <el-button type="primary" size="small" @click="exportReturnOrders" :loading="exportLoading">
                            <el-icon>
                                <Download />
                            </el-icon> 导出数据
                        </el-button> -->
                    </div>
                </div>
            </template>

            <!-- 搜索区域 -->
            <el-form :model="searchParams" ref="searchForm" label-width="100px" inline>
                <el-form-item label="订单号" prop="order_id">
                    <el-input v-model="searchParams.order_no" placeholder="请输入订单号" clearable />
                </el-form-item>
                <el-form-item label="快递单号" prop="express_no">
                    <el-input v-model="searchParams.express_no" placeholder="请输入快递单号" clearable />
                </el-form-item>
                

                <el-form-item label="创建时间" prop="create_at">
                    <el-date-picker v-model="searchParams.create_at" type="daterange" range-separator="至"
                        start-placeholder="开始日期" end-placeholder="结束日期" value-format="yyyy-MM-dd" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <el-icon>
                            <Search />
                        </el-icon> 搜索
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon>
                            <Refresh />
                        </el-icon> 重置
                    </el-button>
                </el-form-item>
            </el-form>

            <!-- 状态统计卡片 -->
            <div class="status-cards">
              

                <el-tabs v-model="activeOrderStatus" @tab-click="filterByStatus">

                    <el-tab-pane v-for='(item, key) in statusCards' :label="item.label"
                        :name="item.status"></el-tab-pane>
                </el-tabs>
            </div>


            <!-- 表格区域 -->
            <el-table v-loading="tableLoading" :data="formattedTableData" style="width: 100%; margin-top: 20px" border
                @selection-change="handleSelectionChange">
                <template #empty>
                    <EmptyState
                        v-if="!tableLoading"
                        icon="search"
                        title="暂无退货订单"
                        description="拒绝回收的设备会在这里生成退货订单，可调整筛选条件再试试。"
                    />
                </template>
                <el-table-column type="selection" width="55" />
                <el-table-column prop="id" label="ID" width="80" sortable />
                <el-table-column prop="order_id" label="退回订单编号" min-width="150" sortable show-overflow-tooltip />
                <el-table-column label="快递单号" min-width="170" show-overflow-tooltip>
                    <template #default="scope">
                        <span v-if="scope.row.express_no" class="clickable-text" @click="openReturnExpressTrack(scope.row)">
                            {{ scope.row.express_no }}
                        </span>
                        <span v-else>-</span>
                    </template>
                </el-table-column>

                <el-table-column label="会员信息" min-width="150">
                    <template #default="scope">
                        <div v-if="scope.row.memberInfo">
                            <el-tooltip :content="scope.row.memberInfo.name">
                                <span>{{ scope.row.memberInfo.name }}</span>
                            </el-tooltip>
                        </div>
                        <div v-if="scope.row.memberInfo && scope.row.memberInfo.mobile">
                            <el-tooltip content="点击复制手机号">
                                <span @click="copyToClipboard(scope.row.memberInfo.mobile)" class="clickable-text">
                                    {{ scope.row.memberInfo.mobile }}
                                </span>
                            </el-tooltip>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="退回设备" min-width="140">
                    <template #default="scope">
                        <el-popover placement="top" width="300" trigger="hover" :content="getDeviceList(scope.row)">
                            <template #reference>
                                <el-tag type="info" effect="plain">{{ scope.row.deviceCount }} 台设备</el-tag>
                            </template>
                        </el-popover>
                    </template>
                </el-table-column>

                <el-table-column label="状态" width="100">
                    <template #default="scope">
                        <el-tag :type="scope.row.statusType">
                            {{ scope.row.status_name }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="create_at" label="创建时间" min-width="180" sortable />
                <el-table-column label="操作" width="340" fixed="right">
                    <template #default="scope">
                        <el-button type="primary" size="small" @click="handleDetail(scope.row)">
                            <el-icon>
                                <View />
                            </el-icon> 详情
                        </el-button>
                        <el-button v-if="scope.row.express_no" type="primary" plain size="small"
                            :loading="expressTrackLoading && activeTrackId === scope.row.id"
                            @click="openReturnExpressTrack(scope.row)">查状态</el-button>
                        <el-button v-if="canPerformAction('CONFIRM', scope.row.status)" type="success" size="small"
                            :loading="operationLoading && activeOperationId === scope.row.id"
                            @click="handleConfirm(scope.row.id)">确认退货</el-button>
                        <el-button v-if="canPerformAction('COMPLETE', scope.row.status)" type="success" size="small"
                            :loading="operationLoading && activeOperationId === scope.row.id"
                            @click="handleComplete(scope.row.id)">完成退货</el-button>
                        <!-- <el-button v-if="canPerformAction('CANCEL', scope.row.status)" type="danger" size="small"
                            :loading="operationLoading && activeOperationId === scope.row.id"
                            @click="handleCancel(scope.row.id)">取消</el-button> -->
                        <el-button v-if="canPerformAction('DELETE', scope.row.status)" type="danger" size="small"
                            :loading="operationLoading && activeOperationId === scope.row.id"
                            @click="handleDelete(scope.row.id)">删除</el-button>
                        <el-dropdown
                            v-if="getVisibleReturnPrintActions(scope.row).length"
                            trigger="click"
                            @command="(action) => printReturnByScene(scope.row, action)"
                        >
                            <el-button size="small" plain>
                                打印<el-icon class="el-icon--right"><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-for="action in getVisibleReturnPrintActions(scope.row)"
                                        :key="action.scene_key"
                                        :command="action"
                                    >
                                        {{ action.button_text || action.scene_name }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 批量操作 -->
            <div class="batch-actions" v-if="selectedRows.length > 0">
                <el-button type="primary" size="small" @click="batchExport">批量导出</el-button>
                <el-button v-if="canBatchDelete" type="danger" size="small" @click="batchDelete">批量删除</el-button>
                <span class="selected-info">已选择 {{ selectedRows.length }} 项</span>
            </div>

            <!-- 分页 -->
            <div class="pagination-container">
                <el-pagination v-model:current-page="pagination.page" v-model:page-size="pagination.limit"
                    :page-sizes="[10, 20, 50, 100]" layout="total, sizes, prev, pager, next, jumper"
                    :total="pagination.total" @size-change="handleSizeChange" @current-change="handleCurrentChange" />
            </div>
        </el-card>

        <!-- 取消订单对话框 -->
        <el-dialog v-model="cancelDialogVisible" title="取消退回订单" width="500px" :close-on-click-modal="false">
            <el-form :model="cancelForm" label-width="100px" ref="cancelFormRef">
                <el-form-item label="取消原因" prop="comment"
                    :rules="[{ required: true, message: '请输入取消原因', trigger: 'blur' }]">
                    <el-input v-model="cancelForm.comment" type="textarea" :rows="3" placeholder="请输入取消原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="cancelDialogVisible = false">取消</el-button>
                    <el-button type="primary" :loading="operationLoading" @click="confirmCancel">确认</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- 完成订单对话框 -->
        <el-dialog v-model="completeDialogVisible" title="完成退回订单" width="500px" :close-on-click-modal="false">
            <el-form :model="completeForm" label-width="100px" ref="completeFormRef">
                <el-form-item label="备注">
                    <el-input v-model="completeForm.comment" type="textarea" :rows="3" placeholder="请输入备注信息" />
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="completeDialogVisible = false">取消</el-button>
                    <el-button type="primary" :loading="operationLoading" @click="confirmComplete">确认</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- 确认退货工作台 -->
        <el-dialog v-model="confirmDialogVisible" title="退货发货工作台" width="min(1180px, calc(100vw - 32px))" class="return-shipment-dialog" :close-on-click-modal="false">
            <div class="return-workbench">
                <section class="return-panel">
                    <div class="return-panel-head">
                        <div>
                            <strong>寄件地址</strong>
                            <span>默认使用商家发货地址，可切换或临时解析。</span>
                        </div>
                        <el-button link type="danger" @click="clearReturnSender">清空</el-button>
                    </div>
                    <el-select v-model="selectedShopSenderId" filterable clearable placeholder="选择商家寄件地址" @change="applyShopSenderAddress">
                        <el-option v-for="item in senderShopAddressList" :key="item.id" :label="shopAddressLabel(item)" :value="item.id" />
                    </el-select>
                    <div class="parse-inline">
                        <el-input v-model="senderRawAddress" type="textarea" :rows="2" placeholder="临时寄件地址：姓名 手机号 省市区详细地址" />
                        <el-button :loading="addressParseLoading.sender" @click="parseReturnAddress('sender')">解析</el-button>
                    </div>
                    <div class="return-address-grid">
                        <el-input v-model="returnShipmentForm.senderName" placeholder="寄件人" />
                        <el-input v-model="returnShipmentForm.senderMobile" placeholder="寄件手机号" />
                        <el-input v-model="returnShipmentForm.senderProvince" placeholder="省" />
                        <el-input v-model="returnShipmentForm.senderCity" placeholder="市" />
                        <el-input v-model="returnShipmentForm.senderDistrict" placeholder="区县" />
                        <el-input v-model="returnShipmentForm.senderAddress" placeholder="详细地址" class="span-2" />
                    </div>
                </section>

                <section class="return-panel">
                    <div class="return-panel-head">
                        <div>
                            <strong>退货地址</strong>
                            <span>默认使用用户提交的退货地址，本次可直接修改。</span>
                        </div>
                        <el-button link type="primary" @click="parseReturnAddress('receiver')">重新解析</el-button>
                    </div>
                    <div class="parse-inline">
                        <el-input v-model="receiverRawAddress" type="textarea" :rows="2" placeholder="退货地址：姓名 手机号 省市区详细地址" />
                        <el-button :loading="addressParseLoading.receiver" @click="parseReturnAddress('receiver')">解析</el-button>
                    </div>
                    <div class="return-address-grid">
                        <el-input v-model="returnShipmentForm.receiveName" placeholder="收件人" />
                        <el-input v-model="returnShipmentForm.receiveMobile" placeholder="收件手机号" />
                        <el-input v-model="returnShipmentForm.receiveProvince" placeholder="省" />
                        <el-input v-model="returnShipmentForm.receiveCity" placeholder="市" />
                        <el-input v-model="returnShipmentForm.receiveDistrict" placeholder="区县" />
                        <el-input v-model="returnShipmentForm.receiveAddress" placeholder="详细地址" class="span-2" />
                    </div>
                </section>

                <section class="return-panel shipment-control-panel">
                    <div class="return-panel-head">
                        <div>
                            <strong>快递处理</strong>
                            <span>系统快递会先报价，选中报价后下单并反显单号。</span>
                        </div>
                    </div>
                    <el-radio-group v-model="confirmForm.shipment_mode" class="shipment-mode">
                        <el-radio-button label="system">系统快递</el-radio-button>
                        <el-radio-button label="manual">手动录入</el-radio-button>
                        <el-radio-button label="self">物流车/自取</el-radio-button>
                    </el-radio-group>

                    <div v-if="confirmForm.shipment_mode === 'system'" class="system-shipment">
                        <div class="package-inline">
                            <el-input v-model="returnShipmentForm.goods" placeholder="物品名称" />
                            <el-input-number v-model="returnShipmentForm.weight" :min="0.1" :precision="2" :step="0.1" />
                            <el-input-number v-model="returnShipmentForm.packageCount" :min="1" :max="99" />
                            <el-input-number v-model="returnShipmentForm.guaranteeValueAmount" :min="0" :precision="2" />
                        </div>
                        <el-button type="primary" :loading="quoteLoading" @click="runReturnQuote">获取报价</el-button>
                        <div v-if="returnQuoteList.length" class="quote-result-list">
                            <div v-for="(item, index) in returnQuoteList" :key="returnQuoteKey(item)" class="quote-result-item" :class="{ active: isReturnQuoteSelected(item) }" @click="selectReturnQuote(item)">
                                <div>
                                    <strong><span v-if="index === 0">最低</span>{{ item.productName || item.product_name || '快递产品' }}</strong>
                                    <em>{{ item.channelName || item.channel_name || '易速渠道' }}</em>
                                </div>
                                <b>¥{{ getReturnQuotePrice(item) }}</b>
                            </div>
                        </div>
                        <el-alert v-else title="填写寄件和退货地址后获取报价，系统会返回已启用快递公司的报价。" type="info" :closable="false" show-icon />
                        <div v-if="confirmForm.express_no" class="generated-waybill">
                            <span>已生成运单</span>
                            <strong>{{ confirmForm.express_company }} {{ confirmForm.express_no }}</strong>
                        </div>
                    </div>

                    <div v-else-if="confirmForm.shipment_mode === 'manual'" class="manual-shipment">
                        <el-select v-model="confirmForm.express_company" placeholder="请选择快递公司" clearable>
                            <el-option v-for="item in expressCompanyOptions" :key="item.value" :label="item.label" :value="item.value" />
                        </el-select>
                        <el-input v-model="confirmForm.express_no" placeholder="请输入或扫描快递单号" clearable ref="expressNoInput" @focus="focusInput" />
                        <el-alert title="手动录入时快递单号必填。系统快递下单成功后会自动反显单号。" type="info" :closable="false" show-icon />
                    </div>

                    <el-input v-model="confirmForm.remark" type="textarea" :rows="3" placeholder="退货备注" />
                </section>
            </div>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="confirmDialogVisible = false">取消</el-button>
                    <el-button type="primary" :loading="operationLoading" @click="submitConfirm">确认退货</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- 修改退货地址对话框 -->
        <el-dialog v-model="editAddressDialogVisible" title="修改本次退货地址" width="500px" :close-on-click-modal="false">
            <el-form ref="editAddressFormRef" :model="editAddressForm" :rules="editAddressRules" label-width="80px">
                <el-form-item label="联系人" prop="name">
                    <el-input v-model="editAddressForm.name" placeholder="请输入联系人姓名" />
                </el-form-item>
                <el-form-item label="手机号" prop="mobile">
                    <el-input v-model="editAddressForm.mobile" placeholder="请输入手机号" />
                </el-form-item>
                <el-form-item label="详细地址" prop="address">
                    <el-input v-model="editAddressForm.address" type="textarea" :rows="3" placeholder="请输入详细地址" />
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="editAddressDialogVisible = false">取消</el-button>
                    <el-button type="primary" @click="confirmEditAddress">确认修改</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- 订单详情对话框 -->
        <el-dialog v-model="detailDialogVisible" title="退回订单详情" width="800px" :close-on-click-modal="false"
            destroy-on-close>
            <el-descriptions :column="2" border>
                <el-descriptions-item label="订单编号" >{{ currentDetail.order_id }}</el-descriptions-item>
                <el-descriptions-item label="创建时间">{{ currentDetail.create_at }}</el-descriptions-item>
               
                <el-descriptions-item label="订单状态">
                    <el-tag :type="getStatusType(currentDetail.status)">{{ currentDetail.status_name }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="操作人" >{{ currentDetail.operator_name }}</el-descriptions-item>
                <el-descriptions-item label="快递公司">{{ currentDetail.express_company }}</el-descriptions-item>
                <el-descriptions-item label="快递单号">{{ currentDetail.express_no || '无' }}</el-descriptions-item>
                <el-descriptions-item label="备注">{{ currentDetail.comment}}</el-descriptions-item>
                <el-descriptions-item label="会员信息" :span="2">
                    <div v-if="currentDetail.member">
                        <p>姓名：{{ currentDetail.member.nickname || currentDetail.member.username }}</p>
                        <p>手机：{{ currentDetail.member.mobile }}</p>
                    </div>
                </el-descriptions-item>
                <el-descriptions-item v-if="currentDetail.return_address" label="退货地址" :span="2">
                    <div>
                        <p>联系人:{{ currentDetail.member_name }}</p>
                        <p>手机号:{{ currentDetail.member_mobile }}</p>
                        <p>地址:{{ currentDetail.return_address }}</p>
                    </div>
                </el-descriptions-item>
               
                <el-descriptions-item v-if="currentDetail.remark" label="备注">{{ currentDetail.remark || '无' }}</el-descriptions-item>
            </el-descriptions>

            <div class="device-list-section">
                <h3>退回设备列表</h3>
                <el-table :data="currentDetail.returnDevices || []" stripe border style="width: 100%; margin-top: 10px">
                    <el-table-column prop="device.model" label="设备名称" min-width="150" />
                    <el-table-column prop="device.imei" label="IMEI" min-width="150" />
                    <el-table-column prop="remark" label="退回原因" min-width="150" />
                    <el-table-column prop="price" label="设备最终定价" width="200">
                        <template #default="scope">
                            <span class="amount">{{ formatPrice(scope.row.final_price) }}</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <div class="timeline-section" v-if="currentDetail.logs && currentDetail.logs.length">
                <h3>订单状态记录</h3>
                <el-timeline>
                    <el-timeline-item v-for="(log, index) in currentDetail.logs" :key="index" :timestamp="log.create_at"
                        :type="getTimelineItemType(log.status)">
                        <div>{{ log.comment || getStatusTextByValue(log.status) }}</div>
                        <div class="timeline-operator" v-if="log.operator">操作人：{{ log.operator }}</div>
                    </el-timeline-item>
                </el-timeline>
            </div>

            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="detailDialogVisible = false">关闭</el-button>
                    <el-button type="primary" @click="printOrderDetail">打印订单</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- 快递物流信息 -->
        <el-dialog v-model="expressTrackDialogVisible" title="快递物流信息" width="600px" :close-on-click-modal="false" destroy-on-close>
            <div v-if="expressInfo" class="express-info-container">
                <div class="express-header">
                    <div class="express-header-main">
                        <div>
                            <h3>{{ expressInfo.logisticsCompanyName || currentTrackOrder?.express_company || '快递公司' }}</h3>
                            <p>运单号：{{ expressInfo.mailNo || currentTrackOrder?.express_no }}</p>
                        </div>
                        <el-tag :type="getExpressStatusType(expressInfo.logisticsStatus)" size="large">
                            {{ expressInfo.logisticsStatusDesc || '未知状态' }}
                        </el-tag>
                    </div>
                    <div class="express-latest">
                        <p>最新状态：{{ expressInfo.theLastMessage || '-' }}</p>
                        <p>更新时间：{{ expressInfo.theLastTime || '-' }}</p>
                    </div>
                </div>

                <div class="express-trace">
                    <h4>物流轨迹</h4>
                    <el-timeline>
                        <el-timeline-item
                            v-for="(item, index) in expressInfo.logisticsTraceDetailList"
                            :key="index"
                            :timestamp="item.timeDesc"
                            :type="index === 0 ? 'primary' : 'info'"
                            :size="index === 0 ? 'large' : 'normal'"
                        >
                            <div class="trace-item">
                                <div class="trace-location">{{ item.areaName }}</div>
                                <div class="trace-desc">{{ item.desc }}</div>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </div>
            </div>
            <el-empty v-else description="暂无快递信息" :image-size="90" />
            <template #footer>
                <el-button @click="expressTrackDialogVisible = false">关闭</el-button>
                <el-button type="primary" :loading="expressTrackLoading" @click="refreshCurrentExpressTrack">刷新状态</el-button>
            </template>
        </el-dialog>
    </PremiumTheme>
</template>

<script setup lang="ts">
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { Download, Search, Refresh, View, ArrowDown } from '@element-plus/icons-vue'
import EmptyState from '@/addon/hsx_recycle/components/empty-state/index.vue'

import {
    getReturnOrderList,
    getReturnOrderStatusCount,
    confirmReturnOrder,
    updateReturnOrderStatus,
    deleteReturnOrder,
    cancelReturnOrder,
    getReturnOrderDetail,
    getDeviceInfo
} from '../../api/recycle_return_order'
import { getShopAddressList } from '../../api/shop_address'
import { getExpressQuote, createExpressOrderDirect } from '../../api/express'
import { parseThirdPartyAddress } from '../../api/third_party'
import { getPrintSceneManualActions, getPrintScenePlan, printByScene } from '../../api/printer'
import { IReturnOrderListParams, IReturnOrder, IStatusCount } from '../../interface/recycle_return_order'
import {
    RETURN_ORDER_STATUS,
    RETURN_ORDER_STATUS_TEXT,
    RETURN_ORDER_STATUS_TYPE,
    STATUS_ACTION_PERMISSIONS
} from '../../constants/recycle_return_order'
import { getExpress } from '../../api/device_query_api'


const router = useRouter()
const route = useRoute()

// 搜索表单数据
const searchParams = reactive<IReturnOrderListParams>({
    order_id: '',
    express_no: '',
    status: route.query.status !== undefined && route.query.status !== ''
        ? Number(route.query.status)
        : undefined,
    create_at: route.query.start_time && route.query.end_time
        ? [String(route.query.start_time), String(route.query.end_time)]
        : []
})

// 状态选项
const statusOptions = ref([
    { label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.PENDING], value: RETURN_ORDER_STATUS.PENDING },
    { label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.RETURNING], value: RETURN_ORDER_STATUS.RETURNING },
    { label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.COMPLETED], value: RETURN_ORDER_STATUS.COMPLETED },
    { label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.CANCELLED], value: RETURN_ORDER_STATUS.CANCELLED }
])

// 表格数据
const tableData = ref<IReturnOrder[]>([])
const tableLoading = ref(false)
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

// 导出相关
const exportLoading = ref(false)

// 批量操作相关
const selectedRows = ref<IReturnOrder[]>([])
const canBatchDelete = computed(() => {
    return selectedRows.value.every(row =>
        STATUS_ACTION_PERMISSIONS['DELETE'].includes(row.status))
})

// 操作加载状态
const operationLoading = ref(false)
const activeOperationId = ref<number | null>(null)
const expressTrackLoading = ref(false)
const activeTrackId = ref<number | null>(null)
const expressTrackDialogVisible = ref(false)
const expressInfo = ref<any>(null)
const currentTrackOrder = ref<any>(null)
const returnPrintActions = ref<any[]>([])

// 状态统计数据
const statusCounts = ref<{
    all: number;
    [key: string]: number;
}>({
    all: 0,
    0: 0,
    1: 0,
    2: 0,
    3: 0
})

// 计算状态卡片数据
const statusCards = computed(() => [
    { status: 'all', label: '全部', count: statusCounts.value.all },
    { status: String(RETURN_ORDER_STATUS.PENDING), label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.PENDING], count: statusCounts.value[RETURN_ORDER_STATUS.PENDING] },
    { status: String(RETURN_ORDER_STATUS.RETURNING), label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.RETURNING], count: statusCounts.value[RETURN_ORDER_STATUS.RETURNING] },
    { status: String(RETURN_ORDER_STATUS.COMPLETED), label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.COMPLETED], count: statusCounts.value[RETURN_ORDER_STATUS.COMPLETED] },
    { status: String(RETURN_ORDER_STATUS.CANCELLED), label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.CANCELLED], count: statusCounts.value[RETURN_ORDER_STATUS.CANCELLED] }
])



// 优化表格数据格式
const formattedTableData = computed(() => {
    return tableData.value.map(item => ({
        ...item,
        deviceCount: item.returnDevices?.length || 0,
        memberInfo: item.member
            ? {
                name: item.member.nickname || item.member.username,
                mobile: item.member.mobile || ''
            }
            : null,
        statusType: getStatusType(item.status)
    }))
})

// 判断状态卡片是否激活
const getActiveStatus = (status: string) => {
    if (status === 'all') {
        return searchParams.status === undefined
    }

    return searchParams.status === Number(status)
}

// 详情对话框相关
const detailDialogVisible = ref(false)
const currentDetail = ref<any>({})

// 取消订单相关
const cancelDialogVisible = ref(false)
const cancelForm = reactive({
    id: 0,
    comment: ''
})
const cancelFormRef = ref<FormInstance>()

// 完成订单相关
const completeDialogVisible = ref(false)
const completeForm = reactive({
    id: 0,
    comment: ''
})
const completeFormRef = ref<FormInstance>()

// 确认退货对话框相关
const confirmDialogVisible = ref(false)
const confirmForm = reactive({
    id: 0,
    order_id: 0,
    express_no: '',
    express_company: '',
    remark: '',
    is_append: false,
    return_order_id: 0,
    shipment_mode: 'system',
    selected_quote_key: ''
})
const expressNoInput = ref<HTMLInputElement>()

const shopAddressList = ref<any[]>([])
const selectedShopSenderId = ref('')
const senderRawAddress = ref('')
const receiverRawAddress = ref('')
const returnQuoteList = ref<any[]>([])
const quoteLoading = ref(false)
const addressParseLoading = reactive({
    sender: false,
    receiver: false
})
const returnShipmentForm = reactive<any>({
    deliveryType: '',
    senderName: '',
    senderMobile: '',
    senderProvince: '',
    senderCity: '',
    senderDistrict: '',
    senderAddress: '',
    receiveName: '',
    receiveMobile: '',
    receiveProvince: '',
    receiveCity: '',
    receiveDistrict: '',
    receiveAddress: '',
    goods: '退回设备',
    weight: 1,
    packageCount: 1,
    guaranteeValueAmount: 0,
    estimated_cost: 0,
    remark: '',
    thirdOrderNo: ''
})

const senderShopAddressList = computed(() => {
    const list = shopAddressList.value.filter(item => Number(item.is_delivery_address) === 1)
    return list.length ? list : shopAddressList.value
})

const returnQuoteSignature = () => JSON.stringify({
    senderProvince: returnShipmentForm.senderProvince,
    senderCity: returnShipmentForm.senderCity,
    senderDistrict: returnShipmentForm.senderDistrict,
    senderAddress: returnShipmentForm.senderAddress,
    receiveProvince: returnShipmentForm.receiveProvince,
    receiveCity: returnShipmentForm.receiveCity,
    receiveDistrict: returnShipmentForm.receiveDistrict,
    receiveAddress: returnShipmentForm.receiveAddress,
    goods: returnShipmentForm.goods,
    weight: returnShipmentForm.weight,
    packageCount: returnShipmentForm.packageCount,
    guaranteeValueAmount: returnShipmentForm.guaranteeValueAmount
})
const lastReturnQuoteSignature = ref('')

watch(
    () => confirmForm.shipment_mode,
    (mode) => {
        if (mode !== 'system') {
            returnQuoteList.value = []
            confirmForm.selected_quote_key = ''
            returnShipmentForm.deliveryType = ''
            returnShipmentForm.estimated_cost = 0
        }
        if (mode !== 'manual') {
            confirmForm.express_no = ''
            if (mode === 'self') confirmForm.express_company = '物流车/自取'
        }
    }
)

watch(
    () => returnQuoteSignature(),
    (signature) => {
        if (lastReturnQuoteSignature.value && signature !== lastReturnQuoteSignature.value) {
            returnQuoteList.value = []
            confirmForm.selected_quote_key = ''
            returnShipmentForm.deliveryType = ''
            returnShipmentForm.estimated_cost = 0
            confirmForm.express_no = ''
        }
    }
)

// 快递公司选项
const expressCompanyOptions = ref([
    { label: '顺丰速运', value: 'sf' },
    { label: '京东速递', value: 'jd' },
    { label: '物流车/自取', value: '物流车/自取' },
   
])

// 聚焦输入框，便于扫码枪使用
const focusInput = () => {
    // 使用setTimeout确保DOM已更新
    setTimeout(() => {
        expressNoInput.value?.focus()
    }, 100)
}

// 统一API响应处理
const handleApiResponse = (res: any, successMsg?: string, errorMsg = '操作失败') => {
    // 统一检查响应结构
    if (res && ((res.code === 1) || (res.data && res.data.code === 1))) {
        successMsg && ElMessage.success(successMsg)
        return true
    } else {
        const msg = res?.data?.msg || res?.msg || errorMsg
        ElMessage.error(msg)
        return false
    }
}

// 通用业务操作处理函数
const performOperation = async (
    id: number,
    operation: () => Promise<any>,
    successMsg: string,
    errorMsg: string
) => {
    activeOperationId.value = id
    operationLoading.value = true

    try {
        const res = await operation()
        if (handleApiResponse(res, successMsg, errorMsg)) {
            getList()
            getStatusCount()
            return true
        }
    } catch (error) {
        console.error(`${errorMsg}:`, error)
        ElMessage.error(errorMsg)
    } finally {
        operationLoading.value = false
        activeOperationId.value = null
    }

    return false
}

// 格式化价格
const formatPrice = (price: number | string | undefined) => {
    if (price === undefined || price === null) return '¥0.00'

    const priceNum = typeof price === 'string' ? parseFloat(price) : price
    return `¥${priceNum.toFixed(2)}`
}

const shopAddressLabel = (item: any) => `${item.contact_name || ''} ${item.mobile || ''} ${item.full_address || item.address || ''}`.trim()

const parseAddressText = (raw: string) => {
    let text = raw.trim().replace(/\s+/g, ' ')
    const mobileMatch = text.match(/1[3-9]\d{9}/)
    const mobile = mobileMatch ? mobileMatch[0] : ''
    if (mobile) text = text.replace(mobile, ' ').replace(/\s+/g, ' ').trim()
    const parts = text.split(' ').filter(Boolean)
    let name = ''
    if (parts.length > 1 && !/(省|市|区|县|州|盟|旗|镇|路|街|号|室)/.test(parts[0])) {
        name = parts.shift() || ''
        text = parts.join('')
    } else {
        text = text.replace(/\s/g, '')
    }
    const areaMatch = text.match(/^(.+?(?:省|自治区|市))(.+?(?:市|自治州|地区|盟))?(.+?(?:区|县|市|旗))?(.+)$/)
    return {
        name,
        mobile,
        province: areaMatch?.[1] || '',
        city: areaMatch?.[2] || '',
        district: areaMatch?.[3] || '',
        address: areaMatch?.[4] || text
    }
}

const fillReturnAddress = (type: 'sender' | 'receiver', parsed: Record<string, any>) => {
    if (type === 'sender') {
        returnShipmentForm.senderName = parsed.name || returnShipmentForm.senderName
        returnShipmentForm.senderMobile = parsed.mobile || returnShipmentForm.senderMobile
        returnShipmentForm.senderProvince = parsed.province || returnShipmentForm.senderProvince
        returnShipmentForm.senderCity = parsed.city || returnShipmentForm.senderCity
        returnShipmentForm.senderDistrict = parsed.district || returnShipmentForm.senderDistrict
        returnShipmentForm.senderAddress = parsed.address || parsed.info || returnShipmentForm.senderAddress
    } else {
        returnShipmentForm.receiveName = parsed.name || returnShipmentForm.receiveName
        returnShipmentForm.receiveMobile = parsed.mobile || returnShipmentForm.receiveMobile
        returnShipmentForm.receiveProvince = parsed.province || returnShipmentForm.receiveProvince
        returnShipmentForm.receiveCity = parsed.city || returnShipmentForm.receiveCity
        returnShipmentForm.receiveDistrict = parsed.district || returnShipmentForm.receiveDistrict
        returnShipmentForm.receiveAddress = parsed.address || parsed.info || returnShipmentForm.receiveAddress
        return_user_address.value = {
            ...return_user_address.value,
            name: returnShipmentForm.receiveName,
            mobile: returnShipmentForm.receiveMobile,
            address: `${returnShipmentForm.receiveProvince}${returnShipmentForm.receiveCity}${returnShipmentForm.receiveDistrict}${returnShipmentForm.receiveAddress}`
        }
    }
}

const parseReturnAddress = async (type: 'sender' | 'receiver') => {
    const raw = type === 'sender' ? senderRawAddress.value : receiverRawAddress.value
    if (!raw.trim()) {
        ElMessage.warning(type === 'sender' ? '请先填写寄件地址' : '请先填写退货地址')
        return
    }
    addressParseLoading[type] = true
    try {
        const res = await parseThirdPartyAddress({ address: raw })
        fillReturnAddress(type, res.data || {})
        ElMessage.success('地址解析成功')
    } catch (error) {
        fillReturnAddress(type, parseAddressText(raw))
        ElMessage.warning('地址解析接口不可用，已使用本地基础解析')
    } finally {
        addressParseLoading[type] = false
    }
}

const applyShopSenderAddress = (id: string | number) => {
    const item = shopAddressList.value.find(address => String(address.id) === String(id))
    if (!item) return
    senderRawAddress.value = `${item.contact_name || ''} ${item.mobile || ''} ${item.full_address || item.address || ''}`
    fillReturnAddress('sender', parseAddressText(senderRawAddress.value))
}

const clearReturnSender = () => {
    selectedShopSenderId.value = ''
    senderRawAddress.value = ''
    returnShipmentForm.senderName = ''
    returnShipmentForm.senderMobile = ''
    returnShipmentForm.senderProvince = ''
    returnShipmentForm.senderCity = ''
    returnShipmentForm.senderDistrict = ''
    returnShipmentForm.senderAddress = ''
    returnQuoteList.value = []
    confirmForm.selected_quote_key = ''
    confirmForm.express_no = ''
    confirmForm.express_company = ''
}

const getReturnQuotePrice = (row: any) => {
    for (const field of ['estimatedCost', 'totalPrice', 'totalFee', 'totalAmount', 'price', 'fee', 'amount', 'prePrice', 'predictPrice', 'estimatedPrice', 'freight', 'freightFee', 'transportFee', 'channelFee']) {
        const value = Number(row[field])
        if (value > 0) return value.toFixed(2)
    }
    return ['channelFee', 'serviceCharge', 'serviceFee', 'guarantFee', 'guaranteeFee', 'incrementFee', 'otherFee']
        .reduce((total, field) => total + Number(row[field] || 0), 0)
        .toFixed(2)
}

const returnQuoteKey = (row: any) => `${row.productCode || row.product_code || ''}-${row.productName || row.product_name || ''}-${getReturnQuotePrice(row)}`
const isReturnQuoteSelected = (row: any) => returnQuoteKey(row) === confirmForm.selected_quote_key

const selectReturnQuote = (row: any) => {
    returnShipmentForm.deliveryType = String(row.productCode || row.product_code || '')
    returnShipmentForm.estimated_cost = Number(getReturnQuotePrice(row))
    confirmForm.selected_quote_key = returnQuoteKey(row)
    confirmForm.express_company = row.productName || row.product_name || '系统快递'
    confirmForm.express_no = ''
}

const validateReturnShipmentAddress = () => {
    const fields = [
        ['senderName', '请填写寄件人'],
        ['senderMobile', '请填写寄件手机号'],
        ['senderProvince', '请填写寄件省份'],
        ['senderCity', '请填写寄件城市'],
        ['senderDistrict', '请填写寄件区县'],
        ['senderAddress', '请填写寄件详细地址'],
        ['receiveName', '请填写退货联系人'],
        ['receiveMobile', '请填写退货手机号'],
        ['receiveProvince', '请填写退货省份'],
        ['receiveCity', '请填写退货城市'],
        ['receiveDistrict', '请填写退货区县'],
        ['receiveAddress', '请填写退货详细地址']
    ]
    for (const [field, message] of fields) {
        if (!returnShipmentForm[field]) {
            ElMessage.warning(message)
            return false
        }
    }
    return true
}

const runReturnQuote = async () => {
    if (!validateReturnShipmentAddress()) return
    quoteLoading.value = true
    try {
        returnShipmentForm.deliveryType = ''
        const res = await getExpressQuote({ ...returnShipmentForm, deliveryType: '', productCode: '' })
        returnQuoteList.value = (res.data || []).sort((left: any, right: any) => Number(getReturnQuotePrice(left)) - Number(getReturnQuotePrice(right)))
        lastReturnQuoteSignature.value = returnQuoteSignature()
        if (returnQuoteList.value[0]) selectReturnQuote(returnQuoteList.value[0])
        ElMessage.success(`已获取 ${returnQuoteList.value.length} 个报价`)
    } finally {
        quoteLoading.value = false
    }
}

const queryReturnExpressTrack = async (row: any) => {
    if (!row?.express_no) {
        ElMessage.warning('当前退回订单没有快递单号')
        return
    }
    const mobile = row.member?.mobile || row.member_mobile || row.memberInfo?.mobile || ''
    const mobileLast4 = String(mobile).slice(-4)
    if (!mobileLast4) {
        ElMessage.warning('无法获取用户手机号后四位，无法查询快递信息')
        return
    }
    expressTrackLoading.value = true
    activeTrackId.value = row.id
    try {
        const res = await getExpress(row.express_no, mobileLast4)
        const data = res.data?.data || res.data || null
        if (!data?.logisticsTraceDetailList?.length) {
            expressInfo.value = null
            ElMessage.info('暂无物流信息')
            return
        }
        expressInfo.value = data
    } catch (error: any) {
        expressInfo.value = null
        ElMessage.error(error.message || '查询运单状态失败')
    } finally {
        expressTrackLoading.value = false
        activeTrackId.value = null
    }
}

const openReturnExpressTrack = async (row: any) => {
    currentTrackOrder.value = row
    expressInfo.value = null
    expressTrackDialogVisible.value = true
    await queryReturnExpressTrack(row)
}

const refreshCurrentExpressTrack = async () => {
    if (!currentTrackOrder.value) return
    await queryReturnExpressTrack(currentTrackOrder.value)
}

// 获取设备列表信息
const getDeviceList = (row: any) => {
    if (!row.returnDevices || row.returnDevices.length === 0) {
        return '暂无设备信息'
    }

console.log(row);


    return row.returnDevices.map((device: any, index: number) =>
        `${index + 1}. ${device.device?.model || '未知设备'} (${device.device?.imei || 'IMEI未知'})`
    ).join('\n')
}

// 获取设备检测状态类型
const getDeviceCheckStatusType = (status: number | string) => {
    const statusMap: Record<string, string> = {
        '0': 'info',
        '1': 'success',
        '2': 'danger'
    }
    return statusMap[String(status)] || 'info'
}

// 获取设备检测状态文本
const getDeviceCheckStatusText = (status: number | string) => {
    const statusMap: Record<string, string> = {
        '0': '待检测',
        '1': '通过',
        '2': '不通过'
    }
    return statusMap[String(status)] || '未知'
}

// 获取时间线项的类型
const getTimelineItemType = (status: number) => {
    const typeMap: Record<number, string> = {
        [RETURN_ORDER_STATUS.PENDING]: 'primary',
        [RETURN_ORDER_STATUS.RETURNING]: 'warning',
        [RETURN_ORDER_STATUS.COMPLETED]: 'success',
        [RETURN_ORDER_STATUS.CANCELLED]: 'danger'
    }
    return typeMap[status] || 'info'
}

// 根据状态值获取状态文本
const getStatusTextByValue = (status: number) => {
    return RETURN_ORDER_STATUS_TEXT[status as RETURN_ORDER_STATUS] || '未知状态'
}

// 复制到剪贴板
const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success('已复制到剪贴板')
    }).catch(() => {
        ElMessage.error('复制失败')
    })
}

// 获取订单列表
const getList = async () => {
    tableLoading.value = true
    try {
        const params: IReturnOrderListParams = {
            ...searchParams,
            page: pagination.page,
            limit: pagination.limit
        }

        const res = await getReturnOrderList(params)

        
        

        if (handleApiResponse(res, '', '获取列表失败')) {
            // 确保数据结构一致性
            const responseData = res.data

            // 检查不同的响应数据结构
            if (responseData.list) {
                // 如果是 {list: [...], count: number} 结构
                tableData.value = Array.isArray(responseData.list) ? responseData.list : []
                pagination.total = responseData.count || 0
            } else if (responseData.data) {
                // 如果是 {data: [...], total: number, current_page: number, per_page: number} 结构
                tableData.value = Array.isArray(responseData.data) ? responseData.data : []
                pagination.total = responseData.total || 0
                pagination.page = Number(responseData.current_page || 1)
                pagination.limit = Number(responseData.per_page || 10)
            } else {
                // 如果直接是数组
                tableData.value = Array.isArray(responseData) ? responseData : []
            }
        }
    } catch (error) {
        console.error('获取列表失败:', error)
        ElMessage.error('获取列表失败')
    } finally {
        tableLoading.value = false
    }
}

// 获取状态统计
const getStatusCount = async () => {
    try {
        const res = await getReturnOrderStatusCount()
        if (handleApiResponse(res, '', '获取状态统计失败')) {
            // 初始化计数
            statusCounts.value = { all: 0, 0: 0, 1: 0, 2: 0, 3: 0 }

            // 正确处理返回的数据
            const countData = res.data?.data || []
            countData.forEach((item: IStatusCount) => {
                if (item.status === 'all') {
                    statusCounts.value.all = item.count
                } else if (typeof item.status === 'string' && item.status in statusCounts.value) {
                    statusCounts.value[item.status] = item.count
                }
            })
        }
    } catch (error) {
        console.error('获取状态统计失败:', error)
        ElMessage.error('获取状态统计失败')
    }
}

// 搜索
const handleSearch = () => {
    pagination.page = 1
    getList()
}

// 重置搜索
const resetSearch = () => {
    searchParams.order_id = ''
    searchParams.express_no = ''
    searchParams.status = undefined
    searchParams.create_at = []
    pagination.page = 1
    getList()
}

// 状态筛选
const activeOrderStatus = ref('all')
const filterByStatus = (status: any) => {

   
    if (status === 'all') {
        searchParams.status = undefined // 全部状态即为不筛选
    } else {
        searchParams.status = activeOrderStatus.value
    }
    pagination.page = 1
    getList()
}

// 分页相关
const handleSizeChange = (val: number) => {
    pagination.limit = val
    pagination.page = 1 // 切换每页显示数量时，重置为第一页
    getList()
}

const handleCurrentChange = (val: number) => {
    pagination.page = val
    getList()
}

// 获取状态类型
const getStatusType = (status: number) => {
    return RETURN_ORDER_STATUS_TYPE[status as RETURN_ORDER_STATUS] || ''
}

const getExpressStatusType = (status: string) => {
    const statusMap: Record<string, string> = {
        ACCEPT: 'info',
        TRANSPORT: 'warning',
        DELIVER: 'primary',
        SIGN: 'success',
        REJECT: 'danger',
        EXCEPTION: 'danger'
    }
    return statusMap[status] || 'info'
}

// 查看详情
const handleDetail = async (row: IReturnOrder) => {
    try {
        tableLoading.value = true
        currentDetail.value = row
        detailDialogVisible.value = true

    } catch (error) {
        console.error('获取订单详情失败:', error)
        ElMessage.error('获取订单详情失败')
    } finally {
        tableLoading.value = false
    }
}

// 退货地址类型定义
interface ReturnUserAddress {
    id?: number
    name?: string
    mobile?: string
    address?: string
    id_card?: string
    card_pic?: string
}

const return_user_address = ref<ReturnUserAddress>({})

// 修改退货地址对话框相关
const editAddressDialogVisible = ref(false)
const editAddressForm = reactive({
    name: '',
    mobile: '',
    address: ''
})
const editAddressFormRef = ref<FormInstance>()

// 修改退货地址验证规则
const editAddressRules = {
    name: [
        { required: true, message: '请输入联系人姓名', trigger: 'blur' }
    ],
    mobile: [
        { required: true, message: '请输入手机号', trigger: 'blur' },
        { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号', trigger: 'blur' }
    ],
    address: [
        { required: true, message: '请输入详细地址', trigger: 'blur' }
    ]
}

// 修改本次退货地址
const edit_return_user_address = () => {
    // 填充当前地址信息到编辑表单
    editAddressForm.name = return_user_address.value.name || ''
    editAddressForm.mobile = return_user_address.value.mobile || ''
    editAddressForm.address = return_user_address.value.address || ''
    
    editAddressDialogVisible.value = true
}

// 确认修改地址（本地修改）
const confirmEditAddress = async () => {
    if (!editAddressFormRef.value) return

    await editAddressFormRef.value.validate(async (valid) => {
        if (!valid) {
            ElMessage.warning('请填写完整的地址信息')
            return
        }

        // 直接更新本地显示的地址信息
        return_user_address.value = {
            ...return_user_address.value,
            name: editAddressForm.name,
            mobile: editAddressForm.mobile,
            address: editAddressForm.address
        }
        
        editAddressDialogVisible.value = false
        ElMessage.success('地址修改成功')
    })
}
// 确认退货
const handleConfirm = async (id: number) => {
    try {
        // 先获取设备信息，了解其所属订单
        tableLoading.value = true
        const [res, shopAddressRes] = await Promise.all([
            getDeviceInfo(id),
            getShopAddressList({ page: 1, limit: 100 })
        ])

        return_user_address.value = res.data?.memberAddress || {}
        shopAddressList.value = shopAddressRes.data?.list || shopAddressRes.data?.data || shopAddressRes.data || []

        if (handleApiResponse(res, '', '获取设备信息失败')) {
            const deviceInfo = res.data?.data || res.data || {}

            // 重置表单
            confirmForm.id = id
            confirmForm.order_id = deviceInfo.order_id || 0
            confirmForm.express_no = ''
            confirmForm.express_company = ''
            confirmForm.remark = ''
            confirmForm.is_append = false
            confirmForm.return_order_id = 0
            confirmForm.shipment_mode = 'system'
            confirmForm.selected_quote_key = ''
            returnQuoteList.value = []
            lastReturnQuoteSignature.value = ''
            Object.assign(returnShipmentForm, {
                deliveryType: '',
                senderName: '',
                senderMobile: '',
                senderProvince: '',
                senderCity: '',
                senderDistrict: '',
                senderAddress: '',
                receiveName: '',
                receiveMobile: '',
                receiveProvince: '',
                receiveCity: '',
                receiveDistrict: '',
                receiveAddress: '',
                goods: `退回设备${deviceInfo.model ? `-${deviceInfo.model}` : ''}`,
                weight: 1,
                packageCount: 1,
                guaranteeValueAmount: 0,
                estimated_cost: 0,
                remark: '',
                thirdOrderNo: `return_${id}_${Date.now()}`
            })
            const defaultSender = senderShopAddressList.value.find(item => Number(item.is_default_delivery) === 1) || senderShopAddressList.value[0]
            if (defaultSender) {
                selectedShopSenderId.value = defaultSender.id
                applyShopSenderAddress(defaultSender.id)
            }
            receiverRawAddress.value = `${return_user_address.value.name || ''} ${return_user_address.value.mobile || ''} ${return_user_address.value.address || ''}`
            if (receiverRawAddress.value.trim()) {
                fillReturnAddress('receiver', parseAddressText(receiverRawAddress.value))
            }

            // 检查是否已有该订单的退货单
            if (deviceInfo.order_id) {
                const existingReturnOrder = await checkExistingReturnOrder(deviceInfo.order_id)
                if (existingReturnOrder) {
                    confirmForm.is_append = true
                    confirmForm.return_order_id = existingReturnOrder.id
                    confirmForm.express_no = existingReturnOrder.express_no || ''
                    confirmForm.express_company = existingReturnOrder.express_company || ''
                    confirmForm.shipment_mode = existingReturnOrder.express_no ? 'manual' : 'system'

                    // 如果已有退货单，显示追加提示
                    ElMessage({
                        type: 'info',
                        message: `检测到订单 ${deviceInfo.order_no || deviceInfo.order_id} 已有退货单，设备将追加到现有退货单中`
                    })
                }
            }

            confirmDialogVisible.value = true
        }
    } catch (error) {
        console.error('获取设备信息失败:', error)
        ElMessage.error('获取设备信息失败')
    } finally {
        tableLoading.value = false
    }
}

// 检查是否已有退货单
const checkExistingReturnOrder = async (orderId: number) => {
    try {
        // 实际实现中，需要调用后端API检查是否存在退货单
        const res = await getExistingReturnOrder(orderId)
        if (res.data && res.data.code === 1 && res.data.data) {
            return res.data.data
        }
    } catch (error) {
        console.error('检查现有退货单失败:', error)
    }
    return null
}

// 提交确认退货
const submitConfirm = async () => {
    operationLoading.value = true
    activeOperationId.value = confirmForm.id

    try {
        let res
        let expressOrderNo = confirmForm.express_no
        let expressCompany = confirmForm.express_company

        if (confirmForm.shipment_mode === 'system') {
            if (!validateReturnShipmentAddress()) return
            if (!returnShipmentForm.deliveryType || !confirmForm.selected_quote_key) {
                ElMessage.warning('请先获取报价并选择快递公司')
                return
            }
            try {
                const expressRes = await createExpressOrderDirect({
                    ...returnShipmentForm,
                    remark: confirmForm.remark,
                    recycle_order_id: confirmForm.order_id
                })
                const expressData = expressRes.data || {}
                expressOrderNo = expressData.deliveryId || expressData.waybillNo || expressData.express_no || expressData.delivery_id || ''
                if (expressOrderNo) {
                    confirmForm.express_no = expressOrderNo
                    expressCompany = confirmForm.express_company || '系统快递'
                    ElMessage.success('系统快递下单成功')
                } else {
                    throw new Error('系统快递下单成功但未返回运单号')
                }
            } catch (error) {
                console.error('系统快递下单失败:', error)
                ElMessage.error('系统快递下单失败，请重试')
                return
            }
        } else if (confirmForm.shipment_mode === 'manual') {
            if (!confirmForm.express_company || !confirmForm.express_no) {
                ElMessage.warning('请填写快递公司和快递单号')
                return
            }
        } else {
            expressCompany = '物流车/自取'
            expressOrderNo = ''
        }

        if (confirmForm.is_append) {
            res = await appendToReturnOrder({
                device_id: confirmForm.id,
                return_order_id: confirmForm.return_order_id,
                remark: confirmForm.remark
            })

            if (handleApiResponse(res, '设备已成功添加到退货单', '添加设备到退货单失败')) {
                confirmDialogVisible.value = false
                getList()
                getStatusCount()
            }
        } else {
            res = await confirmReturnOrder(confirmForm.id, {
                express_no: expressOrderNo,
                express_company: expressCompany,
                remark: confirmForm.remark,
                order_id: confirmForm.order_id,
                member_mobile: returnShipmentForm.receiveMobile || return_user_address.value.mobile,
                member_name: returnShipmentForm.receiveName || return_user_address.value.name,
                return_address: `${returnShipmentForm.receiveProvince}${returnShipmentForm.receiveCity}${returnShipmentForm.receiveDistrict}${returnShipmentForm.receiveAddress}` || return_user_address.value.address,
            })

            if (handleApiResponse(res, '确认退货成功', '确认退货失败')) {
                confirmDialogVisible.value = false
                getList()
                getStatusCount()
            }
        }
    } catch (error) {
        console.error('确认退货失败:', error)
        ElMessage.error('确认退货失败')
    } finally {
        operationLoading.value = false
        activeOperationId.value = null
    }
}



// 获取现有退货单 - 假设这是一个API调用
const getExistingReturnOrder = async (orderId: number) => {
    // 实际实现中应替换为真实的API调用
    // 示例: return request.get(`/hsx_recycle/recycle_order/${orderId}/return_order`)
    return Promise.resolve({
        data: {
            code: 1,
            data: null // 示例中返回null，表示没有找到现有退货单
        }
    })
}

// 追加设备到现有退货单 - 假设这是一个API调用
const appendToReturnOrder = async (data: any) => {
    // 实际实现中应替换为真实的API调用
    // 示例: return request.post('/hsx_recycle/recycle_return_order/append_device', data)
    return Promise.resolve({
        data: {
            code: 1,
            msg: '设备已成功添加到退货单'
        }
    })
}

// 完成退货
const handleComplete = (id: number) => {
    completeForm.id = id
    completeForm.comment = ''
    completeDialogVisible.value = true
}

// 确认完成
const confirmComplete = async () => {
    operationLoading.value = true
    try {
        const res = await updateReturnOrderStatus(completeForm.id, {
            status: RETURN_ORDER_STATUS.COMPLETED,
            comment: completeForm.comment
        })
        if (handleApiResponse(res, '完成退货成功', '完成退货失败')) {
            completeDialogVisible.value = false
            getList()
            getStatusCount()
        }
    } catch (error) {
        console.error('完成退货失败:', error)
        ElMessage.error('完成退货失败')
    } finally {
        operationLoading.value = false
    }
}

// 删除订单
const handleDelete = (id: number) => {
    ElMessageBox.confirm('确定要删除该退回订单吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    })
        .then(async () => {
            await performOperation(
                id,
                () => deleteReturnOrder(id),
                '删除成功',
                '删除失败'
            )
        })
        .catch(() => { })
}

// 取消订单
const handleCancel = (id: number) => {
    cancelForm.id = id
    cancelForm.comment = ''
    cancelDialogVisible.value = true
}

// 确认取消
const confirmCancel = async () => {
    // 验证表单
    if (!cancelFormRef.value) return

    await cancelFormRef.value.validate(async (valid) => {
        if (!valid) {
            ElMessage.warning('请填写取消原因')
            return
        }

        operationLoading.value = true
        try {
            const res = await cancelReturnOrder(cancelForm.id, cancelForm.comment)
            if (handleApiResponse(res, '取消成功', '取消失败')) {
                cancelDialogVisible.value = false
                getList()
                getStatusCount()
            }
        } catch (error) {
            console.error('取消失败:', error)
            ElMessage.error('取消失败')
        } finally {
            operationLoading.value = false
        }
    })
}

// 多选处理
const handleSelectionChange = (rows: IReturnOrder[]) => {
    selectedRows.value = rows
}

// 批量导出
const batchExport = () => {
    ElMessage.success(`已导出选中的 ${selectedRows.value.length} 条记录`)
    // 实现批量导出逻辑
}

// 批量删除
const batchDelete = () => {
    const ids = selectedRows.value.map(row => row.id)
    ElMessageBox.confirm(`确定要删除选中的 ${ids.length} 条记录吗？`, '批量删除', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(() => {
        ElMessage.success('批量删除成功')
        getList()
        getStatusCount()
    }).catch(() => { })
}

// 导出数据
const exportReturnOrders = () => {
    exportLoading.value = true
    setTimeout(() => {
        ElMessage.success('数据导出成功')
        exportLoading.value = false
    }, 1500)
}

// 打印订单
const printOrderDetail = () => {
    ElMessage.success('订单打印功能已触发')
    // 实现打印功能
}

const escapePrintHtml = (value: any) => String(value ?? '').replace(/[&<>"']/g, (char) => {
    const map: Record<string, string> = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }
    return map[char] || char
})

const safePrintText = (value: any, fallback = '未填写') => escapePrintHtml(value === undefined || value === null || value === '' ? fallback : value)

const buildReturnPrintConfirmHtml = (plan: any) => `
    <div class="return-print-plan">
        <div class="return-print-plan__title">请确认本次打印内容</div>
        <div class="return-print-plan__grid">
            <span>打印场景</span><strong>${safePrintText(plan.scene?.scene_name)}</strong>
            <span>退货单号</span><strong>${safePrintText(plan.biz?.title || plan.device?.order_no)}</strong>
            <span>当前状态</span><strong>${safePrintText(plan.biz?.subtitle || plan.device?.status_name)}</strong>
            <span>打印模板</span><strong>${safePrintText(plan.template?.template_name)}</strong>
            <span>目标打印机</span><strong>${safePrintText(plan.printer?.printer_name)}</strong>
            <span>打印份数</span><strong>${safePrintText(plan.copies, '1')} 份</strong>
        </div>
        <div class="return-print-plan__hint">确认后会立即发送到打印机。若模板或打印机不对，请先到打印场景中调整绑定关系。</div>
    </div>
`

const loadReturnPrintActions = async () => {
    try {
        const res = await getPrintSceneManualActions({ biz_type: 'return' })
        returnPrintActions.value = res.code === 1 && Array.isArray(res.data) ? res.data : []
    } catch (error) {
        returnPrintActions.value = []
    }
}

const getVisibleReturnPrintActions = (row: any) => {
    return returnPrintActions.value.filter((action) => {
        if (action.button_position && action.button_position !== 'return_order_actions') return false
        const statuses = Array.isArray(action.visible_device_status) ? action.visible_device_status.map((item: any) => Number(item)) : []
        return !statuses.length || statuses.includes(Number(row.status))
    })
}

const printReturnByScene = async (row: any, action: any) => {
    const sceneKey = action?.scene_key
    if (!sceneKey) return
    const params = { return_order_id: row.id, biz_id: row.id }
    try {
        const planRes = await getPrintScenePlan(sceneKey, params)
        if (planRes.code !== 1 || !planRes.data?.can_print) {
            throw new Error(planRes.msg || planRes.data?.message || '打印计划不可用')
        }
        const plan = planRes.data
        if (Number(action?.confirm_required ?? 1) === 1) {
            await ElMessageBox.confirm(buildReturnPrintConfirmHtml(plan), action?.button_text || plan.scene?.button_text || '打印', {
                dangerouslyUseHTMLString: true,
                confirmButtonText: '确认打印',
                cancelButtonText: '取消'
            })
        }
        const res = await printByScene(sceneKey, params)
        if (res.code !== 1) {
            throw new Error(res.msg || '打印失败')
        }
    } catch (error: any) {
        if (error === 'cancel' || error === 'close') return
        ElMessage.error(error?.message || '打印失败')
    }
}

// 检查操作权限
const canPerformAction = (action: 'DELETE' | 'CANCEL' | 'CONFIRM' | 'COMPLETE', status: number) => {
    return STATUS_ACTION_PERMISSIONS[action].includes(status)
}

onMounted(() => {
    getList()
    getStatusCount()
    loadReturnPrintActions()
})
</script>

<style scoped>

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.search-form {
    margin-bottom: 20px;
}

.status-cards {

    margin-bottom: 20px;
    flex-wrap: wrap;
}

.status-card {
    margin-right: 15px;
    margin-bottom: 15px;
    width: 150px;
    cursor: pointer;
    transition: all 0.3s;
}

.status-card.active {
    border-color: #409eff;
    box-shadow: 0 0 8px rgba(64, 158, 255, 0.6);
}

.status-card-content {
    text-align: center;
}

.status-card-title {
    font-size: 14px;
    color: #606266;
    margin-bottom: 10px;
}

.status-card-count {
    font-size: 24px;
    font-weight: bold;
    color: #303133;
}

.pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

.amount {
    font-weight: bold;
    color: #f56c6c;
}

.batch-actions {
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.selected-info {
    margin-left: 10px;
    color: #606266;
    font-size: 14px;
}

.device-list-section,
.timeline-section {
    margin-top: 20px;
}

.device-list-section h3,
.timeline-section h3 {
    font-size: 16px;
    margin-bottom: 10px;
    color: #303133;
    font-weight: bold;
}

.timeline-operator {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.clickable-text {
    color: #409eff;
    cursor: pointer;
}

.clickable-text:hover {
    text-decoration: underline;
}

.express-info-container {
    max-height: 500px;
    overflow: auto;
}

.express-header {
    border-bottom: 1px solid #ebeef5;
    padding-bottom: 16px;
    margin-bottom: 16px;
}

.express-header-main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.express-header-main h3 {
    margin: 0 0 6px;
    color: #303133;
    font-size: 18px;
    font-weight: 500;
}

.express-header-main p,
.express-latest p {
    margin: 0;
    color: #606266;
    font-size: 13px;
}

.express-latest {
    margin-top: 10px;
}

.express-latest p + p {
    margin-top: 4px;
    color: #909399;
    font-size: 12px;
}

.express-trace h4 {
    margin: 0 0 12px;
    color: #303133;
    font-size: 15px;
    font-weight: 500;
}

.trace-item .trace-location {
    margin-bottom: 4px;
    color: #303133;
    font-weight: 500;
}

.trace-item .trace-desc {
    color: #606266;
    font-size: 14px;
    line-height: 1.5;
}

.scan-tip {
    margin-top: 10px;
}

.scan-instruction {
    font-size: 12px;
    color: #909399;
}

.return-shipment-dialog :deep(.el-dialog__body) {
    max-height: calc(100vh - 190px);
    overflow: auto;
}

.return-workbench {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.return-panel {
    min-width: 0;
    padding: 14px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
}

.shipment-control-panel {
    grid-column: span 2;
}

.return-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.return-panel-head strong {
    display: block;
    color: #111827;
    font-size: 15px;
    line-height: 1.3;
}

.return-panel-head span {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
}

.parse-inline {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 72px;
    gap: 8px;
    margin: 10px 0;
}

.parse-inline .el-button {
    height: auto;
}

.return-address-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.return-address-grid .span-2 {
    grid-column: span 2;
}

.shipment-mode {
    margin-bottom: 12px;
}

.system-shipment,
.manual-shipment {
    display: grid;
    gap: 12px;
}

.package-inline {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) repeat(3, minmax(120px, .7fr));
    gap: 10px;
}

.package-inline :deep(.el-input-number) {
    width: 100%;
}

.quote-result-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
}

.quote-result-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fbfdff;
    cursor: pointer;
}

.quote-result-item.active {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, .12);
}

.quote-result-item strong,
.quote-result-item em {
    display: block;
}

.quote-result-item strong {
    color: #111827;
    font-size: 13px;
}

.quote-result-item strong span {
    margin-right: 6px;
    padding: 1px 5px;
    border-radius: 4px;
    color: #dc2626;
    background: #fee2e2;
    font-size: 11px;
}

.quote-result-item em {
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
    font-style: normal;
}

.quote-result-item b {
    color: #dc2626;
    font-size: 16px;
}

.generated-waybill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: #065f46;
    background: #ecfdf5;
}

:global(.return-print-plan) {
    padding: 4px 0;
}

:global(.return-print-plan__title) {
    margin-bottom: 12px;
    color: #111827;
    font-size: 15px;
    font-weight: 700;
}

:global(.return-print-plan__grid) {
    display: grid;
    grid-template-columns: 92px 1fr;
    gap: 8px 12px;
    padding: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
}

:global(.return-print-plan__grid span) {
    color: #6b7280;
}

:global(.return-print-plan__grid strong) {
    color: #111827;
}

:global(.return-print-plan__hint) {
    margin-top: 10px;
    color: #6b7280;
    font-size: 12px;
}

@media (max-width: 900px) {
    .return-workbench,
    .package-inline {
        grid-template-columns: 1fr;
    }

    .shipment-control-panel,
    .return-address-grid .span-2 {
        grid-column: auto;
    }
}
</style>
