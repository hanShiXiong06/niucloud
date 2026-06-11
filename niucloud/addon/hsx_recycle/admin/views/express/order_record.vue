<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="page-head">
                <div>
                    <span class="text-page-title">快递运单记录</span>
                    <div class="page-desc">查看站点内通过回收插件创建的快递运单，按时间、状态、单号和联系人快速筛选。</div>
                </div>
                <div class="head-actions">
                    <el-button type="primary" @click="openCreateDialog">新建运单</el-button>
                    <el-button :loading="loading" @click="refreshPage">刷新</el-button>
                </div>
            </div>

            <div class="stat-grid mt-[18px]">
                <div class="stat-item">
                    <span>筛选订单</span>
                    <strong>{{ total }}</strong>
                </div>
                <div class="stat-item">
                    <span>统计订单</span>
                    <strong>{{ statistics.total_count || 0 }}</strong>
                </div>
                <div class="stat-item">
                    <span>预估费用</span>
                    <strong>¥{{ formatMoney(statistics.total_estimated_cost) }}</strong>
                </div>
                <div class="stat-item">
                    <span>实际费用</span>
                    <strong>¥{{ formatMoney(statistics.total_actual_cost) }}</strong>
                </div>
                <div class="stat-item">
                    <span>费用差异</span>
                    <strong :class="Number(statistics.total_cost_diff || 0) > 0 ? 'danger' : 'success'">¥{{ formatMoney(statistics.total_cost_diff) }}</strong>
                </div>
            </div>

            <div class="filter-panel mt-[18px]">
                <el-form :model="searchForm" label-width="84px">
                    <el-row :gutter="12">
                        <el-col :span="8">
                            <el-form-item label="关键词">
                                <el-input v-model.trim="searchForm.keyword" clearable placeholder="订单号、运单号、姓名、手机号" @keyup.enter="handleSearch" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="8">
                            <el-form-item label="创建时间">
                                <el-date-picker
                                    v-model="searchForm.create_time"
                                    type="datetimerange"
                                    value-format="YYYY-MM-DD HH:mm:ss"
                                    start-placeholder="开始时间"
                                    end-placeholder="结束时间"
                                    class="!w-full"
                                />
                            </el-form-item>
                        </el-col>
                        <el-col :span="4">
                            <el-form-item label="状态">
                                <el-select v-model="searchForm.order_status" clearable placeholder="全部状态">
                                    <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
                                </el-select>
                            </el-form-item>
                        </el-col>
                        <el-col :span="4">
                            <el-form-item label="产品代码">
                                <el-input v-model.trim="searchForm.product_code" clearable placeholder="如 59" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-row :gutter="12">
                        <el-col :span="6">
                            <el-form-item label="平台订单">
                                <el-input v-model.trim="searchForm.order_no" clearable placeholder="易速订单号" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="6">
                            <el-form-item label="运单号">
                                <el-input v-model.trim="searchForm.delivery_id" clearable placeholder="快递运单号" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="6">
                            <el-form-item label="寄件手机">
                                <el-input v-model.trim="searchForm.sender_mobile" clearable placeholder="寄件人手机号" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="6">
                            <el-form-item label="收件手机">
                                <el-input v-model.trim="searchForm.receiver_mobile" clearable placeholder="收件人手机号" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-row :gutter="12">
                        <el-col :span="6">
                            <el-form-item label="回收订单">
                                <el-input v-model.trim="searchForm.recycle_order_id" clearable placeholder="回收订单ID" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="18">
                            <el-form-item>
                                <el-button type="primary" :loading="loading" @click="handleSearch">查询</el-button>
                                <el-button @click="resetForm">重置</el-button>
                            </el-form-item>
                        </el-col>
                    </el-row>
                </el-form>
            </div>

            <el-table v-loading="loading" :data="orderList" class="mt-[16px]" stripe border>
                <template #empty>
                    <EmptyState
                        v-if="!loading"
                        icon="document"
                        title="暂无快递运单"
                        description="点右上角「新建运单」创建，或调整时间/状态/单号等筛选条件。"
                    />
                </template>
                <el-table-column label="运单信息" min-width="230">
                    <template #default="{ row }">
                        <div v-if="row.delivery_id" class="primary-text clickable-text" @click="openExpressTrack(row)">{{ row.delivery_id }}</div>
                        <div v-else class="primary-text">-</div>
                        <div class="muted-text">平台订单：{{ row.order_no || '-' }}</div>
                        <div class="muted-text">快递公司：{{ row.provider_name || row.express_company || '-' }}</div>
                        <div v-if="row.recycle_order_id" class="muted-text">回收订单：{{ row.recycle_order_id }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="产品" min-width="140">
                    <template #default="{ row }">
                        <div>{{ row.product_name || '-' }}</div>
                        <div class="muted-text">{{ row.product_code || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="寄件人" min-width="190">
                    <template #default="{ row }">
                        <div>{{ row.sender_name || '-' }} {{ row.sender_mobile || '' }}</div>
                        <div class="muted-text text-line">{{ joinAddress(row, 'sender') }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="收件人" min-width="190">
                    <template #default="{ row }">
                        <div>{{ row.receiver_name || '-' }} {{ row.receiver_mobile || '' }}</div>
                        <div class="muted-text text-line">{{ joinAddress(row, 'receiver') }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="重量/费用" width="150">
                    <template #default="{ row }">
                        <div>{{ row.estimated_weight || 0 }} kg</div>
                        <div>¥{{ formatMoney(row.estimated_cost) }}</div>
                        <div v-if="Number(row.actual_cost || 0) > 0" class="muted-text">实付 ¥{{ formatMoney(row.actual_cost) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="110">
                    <template #default="{ row }">
                        <el-tag :type="statusMeta(row.order_status).type">{{ row.status_text || statusMeta(row.order_status).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.create_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="210">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="handleViewDetail(row)">详情</el-button>
                        <el-button link type="warning" @click="handleUpdateActual(row)">更新费用</el-button>
                        <el-button v-if="row.order_no || row.delivery_id" link type="primary" :loading="operationLoading[row.id] === 'waybill'" @click="handleWaybillPdf(row)">面单</el-button>
                        <el-button v-if="row.delivery_id" link type="primary" @click="openExpressTrack(row)">查物流</el-button>
                        <el-button v-if="canCancel(row)" link type="danger" :loading="operationLoading[row.id] === 'cancel'" @click="handleCloseOrder(row)">取消</el-button>
                        <el-button v-if="canIntercept(row)" link type="danger" :loading="operationLoading[row.id] === 'intercept'" @click="handleCloseOrder(row)">拦截</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="flex justify-end mt-[16px]">
                <el-pagination
                    v-model:current-page="searchForm.page"
                    v-model:page-size="searchForm.limit"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="handlePageChange"
                    @current-change="handlePageChange"
                />
            </div>
        </el-card>

        <el-dialog v-model="detailDialogVisible" title="运单详情" width="900px">
            <template v-if="currentOrder">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="平台订单">{{ currentOrder.order_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="运单号">{{ currentOrder.delivery_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="快递公司">{{ currentOrder.provider_name || currentOrder.express_company || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="快递产品">{{ currentOrder.product_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusMeta(currentOrder.order_status).type">{{ currentOrder.status_text || statusMeta(currentOrder.order_status).label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="寄件人">{{ currentOrder.sender_name }} {{ currentOrder.sender_mobile }}</el-descriptions-item>
                    <el-descriptions-item label="收件人">{{ currentOrder.receiver_name }} {{ currentOrder.receiver_mobile }}</el-descriptions-item>
                    <el-descriptions-item label="寄件地址" :span="2">{{ joinAddress(currentOrder, 'sender') }}</el-descriptions-item>
                    <el-descriptions-item label="收件地址" :span="2">{{ joinAddress(currentOrder, 'receiver') }}</el-descriptions-item>
                    <el-descriptions-item label="物品">{{ currentOrder.goods_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="包裹数">{{ currentOrder.package_count || 1 }}</el-descriptions-item>
                    <el-descriptions-item label="预估重量">{{ currentOrder.estimated_weight || 0 }} kg</el-descriptions-item>
                    <el-descriptions-item label="预估费用">¥{{ formatMoney(currentOrder.estimated_cost) }}</el-descriptions-item>
                    <el-descriptions-item label="实际重量">{{ currentOrder.actual_weight || 0 }} kg</el-descriptions-item>
                    <el-descriptions-item label="实际费用">¥{{ formatMoney(currentOrder.actual_cost) }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ formatTime(currentOrder.create_at) }}</el-descriptions-item>
                    <el-descriptions-item label="更新时间">{{ formatTime(currentOrder.update_at) }}</el-descriptions-item>
                </el-descriptions>

                <div class="detail-section-title">系统流程</div>
                <el-timeline v-if="currentOrder.status_history?.length">
                    <el-timeline-item v-for="(item, index) in currentOrder.status_history" :key="index" :timestamp="formatTime(item.time)">
                        {{ statusMeta(item.status).label }}<span v-if="item.remark">：{{ item.remark }}</span>
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else description="暂无流程记录" :image-size="80" />
            </template>
        </el-dialog>

        <el-dialog v-model="updateDialogVisible" title="更新实际费用" width="500px">
            <el-form :model="updateForm" label-width="100px">
                <el-form-item label="实际重量">
                    <el-input-number v-model="updateForm.actual_weight" :min="0" :precision="2" :step="0.1" />
                    <span class="ml-2">kg</span>
                </el-form-item>
                <el-form-item label="实际费用">
                    <el-input-number v-model="updateForm.actual_cost" :min="0" :precision="2" :step="0.1" />
                    <span class="ml-2">元</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="updateDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleConfirmUpdate">确定</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="createDialogVisible" title="新建快递运单" width="min(1320px, calc(100vw - 32px))" class="shipment-dialog" destroy-on-close>
            <div class="shipment-layout">
                <div class="shipment-main">
                    <el-alert
                        class="quote-alert"
                        :type="quoteState.valid ? 'success' : 'warning'"
                        :closable="false"
                        show-icon
                        :title="quoteState.valid ? `已选择报价：${selectedQuoteName}，预估 ¥${Number(shipmentForm.estimated_cost || 0).toFixed(2)}` : '请先获取报价再下单。快递产品、重量、包裹数、保价、预约时间或寄收件信息变化后，需要重新获取报价。'"
                    />
                    <el-form :model="shipmentForm" label-width="88px" class="shipment-form">
                        <div class="shipment-section">
                            <div class="section-head">
                                <div>
                                    <strong>寄收件信息</strong>
                                    <span>可粘贴整段地址解析，也可从常用地址一键填入。</span>
                                </div>
                            </div>
                            <div class="address-form-grid">
                                <div>
                                    <div class="address-panel">
                                        <div class="address-title">
                                            <span>寄件人</span>
                                            <div class="address-title-actions">
                                                <el-button link type="primary" @click="saveCurrentAddress('sender')">保存当前地址</el-button>
                                                <el-button link type="danger" @click="clearShipmentAddress('sender')">清空</el-button>
                                            </div>
                                        </div>
                                        <div class="address-selector">
                                            <el-select
                                                v-model="selectedAddressBookIds.sender"
                                                filterable
                                                remote
                                                clearable
                                                reserve-keyword
                                                popper-class="express-address-select-dropdown"
                                                :remote-method="(keyword: string) => searchAddressBooks('sender', keyword)"
                                                :loading="addressBookLoading.sender"
                                                placeholder="搜索寄件地址：姓名 / 手机号 / 地址"
                                                @focus="searchAddressBooks('sender', addressBookKeywords.sender)"
                                                @change="applyAddressBookById('sender', $event)"
                                            >
                                                <el-option v-for="item in senderAddressBooks" :key="item.id" :label="addressOptionLabel(item)" :value="item.id">
                                                    <div class="address-option">
                                                        <div>
                                                            <strong>{{ item.name }} {{ item.mobile }}</strong>
                                                            <span>{{ formatAddressBook(item) }}</span>
                                                        </div>
                                                        <div class="address-option-actions">
                                                            <em v-if="item.is_default">默认</em>
                                                            <em v-else-if="item.is_top">置顶</em>
                                                            <el-button link type="primary" @click.stop.prevent="setAddressDefault(item)">默认</el-button>
                                                            <el-button link type="primary" @click.stop.prevent="toggleAddressTop(item)">{{ item.is_top ? '取消置顶' : '置顶' }}</el-button>
                                                        </div>
                                                    </div>
                                                </el-option>
                                            </el-select>
                                            <div v-if="selectedSenderAddress" class="selected-address">
                                                <strong>{{ selectedSenderAddress.name }} {{ selectedSenderAddress.mobile }}</strong>
                                                <span>{{ formatAddressBook(selectedSenderAddress) }}</span>
                                            </div>
                                        </div>
                                        <el-form-item label="整段地址">
                                            <div class="parse-row">
                                                <el-input v-model="senderRawAddress" type="textarea" :rows="2" placeholder="姓名 手机号 省市区详细地址" />
                                                <el-button :loading="addressParseLoading.sender" @click="parseRawAddress('sender')">解析</el-button>
                                            </div>
                                        </el-form-item>
                                        <div class="field-grid two">
                                            <el-form-item label="姓名"><el-input v-model="shipmentForm.senderName" /></el-form-item>
                                            <el-form-item label="手机号"><el-input v-model="shipmentForm.senderMobile" /></el-form-item>
                                        </div>
                                        <div class="field-grid">
                                            <el-form-item label="省"><el-input v-model="shipmentForm.senderProvince" /></el-form-item>
                                            <el-form-item label="市"><el-input v-model="shipmentForm.senderCity" /></el-form-item>
                                            <el-form-item label="区县"><el-input v-model="shipmentForm.senderDistrict" /></el-form-item>
                                        </div>
                                        <el-form-item label="详细地址">
                                            <el-input v-model="shipmentForm.senderAddress" />
                                        </el-form-item>
                                    </div>
                                </div>
                                <div>
                                    <div class="address-panel">
                                        <div class="address-title">
                                            <span>收件人</span>
                                            <div class="address-title-actions">
                                                <el-button link type="primary" @click="saveCurrentAddress('receiver')">保存当前地址</el-button>
                                                <el-button link type="danger" @click="clearShipmentAddress('receiver')">清空</el-button>
                                            </div>
                                        </div>
                                        <div class="address-selector">
                                            <el-select
                                                v-model="selectedAddressBookIds.receiver"
                                                filterable
                                                remote
                                                clearable
                                                reserve-keyword
                                                popper-class="express-address-select-dropdown"
                                                :remote-method="(keyword: string) => searchAddressBooks('receiver', keyword)"
                                                :loading="addressBookLoading.receiver"
                                                placeholder="搜索收件地址：姓名 / 手机号 / 地址"
                                                @focus="searchAddressBooks('receiver', addressBookKeywords.receiver)"
                                                @change="applyAddressBookById('receiver', $event)"
                                            >
                                                <el-option-group label="常用收件地址">
                                                    <el-option v-for="item in receiverAddressBooks" :key="`book-${item.id}`" :label="addressOptionLabel(item)" :value="`book:${item.id}`">
                                                        <div class="address-option">
                                                            <div>
                                                                <strong>{{ item.name }} {{ item.mobile }}</strong>
                                                                <span>{{ formatAddressBook(item) }}</span>
                                                            </div>
                                                            <div class="address-option-actions">
                                                                <em v-if="item.is_default">默认</em>
                                                                <em v-else-if="item.is_top">置顶</em>
                                                                <el-button link type="primary" @click.stop.prevent="setAddressDefault(item)">默认</el-button>
                                                                <el-button link type="primary" @click.stop.prevent="toggleAddressTop(item)">{{ item.is_top ? '取消置顶' : '置顶' }}</el-button>
                                                            </div>
                                                        </div>
                                                    </el-option>
                                                </el-option-group>
                                                <el-option-group v-if="shopAddressList.length" label="商家地址">
                                                    <el-option v-for="item in filteredShopAddressList" :key="`shop-${item.id}`" :label="shopAddressOptionLabel(item)" :value="`shop:${item.id}`">
                                                        <div class="address-option">
                                                            <div>
                                                                <strong>{{ item.contact_name }} {{ item.mobile }}</strong>
                                                                <span>{{ item.full_address || item.address || '' }}</span>
                                                            </div>
                                                            <div class="address-option-actions">
                                                                <em v-if="Number(item.is_default_refund) === 1">默认</em>
                                                            </div>
                                                        </div>
                                                    </el-option>
                                                </el-option-group>
                                            </el-select>
                                            <div v-if="selectedReceiverAddress" class="selected-address">
                                                <strong>{{ selectedReceiverAddress.name }} {{ selectedReceiverAddress.mobile }}</strong>
                                                <span>{{ formatAddressBook(selectedReceiverAddress) }}</span>
                                            </div>
                                        </div>
                                        <el-form-item label="整段地址">
                                            <div class="parse-row">
                                                <el-input v-model="receiverRawAddress" type="textarea" :rows="2" placeholder="姓名 手机号 省市区详细地址" />
                                                <el-button :loading="addressParseLoading.receiver" @click="parseRawAddress('receiver')">解析</el-button>
                                            </div>
                                        </el-form-item>
                                        <div class="field-grid two">
                                            <el-form-item label="姓名"><el-input v-model="shipmentForm.receiveName" /></el-form-item>
                                            <el-form-item label="手机号"><el-input v-model="shipmentForm.receiveMobile" /></el-form-item>
                                        </div>
                                        <div class="field-grid ">
                                            <el-form-item label="省"><el-input v-model="shipmentForm.receiveProvince" /></el-form-item>
                                            <el-form-item label="市"><el-input v-model="shipmentForm.receiveCity" /></el-form-item>
                                            <el-form-item label="区县"><el-input v-model="shipmentForm.receiveDistrict" /></el-form-item>
                                        </div>
                                        <el-form-item label="详细地址">
                                            <el-input v-model="shipmentForm.receiveAddress" />
                                        </el-form-item>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="shipment-section">
                            <div class="section-head">
                                <div>
                                    <strong>包裹信息</strong>
                                    <span>点击获取报价后，系统会查询已启用快递公司的报价并按价格排序。</span>
                                </div>
                                <el-button type="primary" :loading="shipmentLoading.quote" @click="runShipmentQuote">获取全部报价</el-button>
                            </div>
                            <div class="package-grid">
                                <el-form-item label="物品" class="goods-field"><el-input v-model="shipmentForm.goods" /></el-form-item>
                                <el-form-item label="重量kg"><el-input-number v-model="shipmentForm.weight" :min="0.1" :precision="2" :step="0.1" /></el-form-item>
                                <el-form-item label="包裹数"><el-input-number v-model="shipmentForm.packageCount" :min="1" :max="99" /></el-form-item>
                                <el-form-item label="保价"><el-input-number v-model="shipmentForm.guaranteeValueAmount" :min="0" :precision="2" /></el-form-item>
                                <el-form-item label="预约" class="time-field"><el-date-picker v-model="shipmentForm.orderSendTime" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="可选" /></el-form-item>
                                <el-form-item label="备注" class="remark-field"><el-input v-model="shipmentForm.remark" /></el-form-item>
                            </div>
                        </div>
                    </el-form>
                </div>

                <aside class="shipment-side">
                    <div class="side-title">报价结果</div>
                    <div v-if="!quoteList.length" class="quote-empty">
                        <strong>还没有报价</strong>
                        <span>填写寄收件和包裹信息后点击“获取全部报价”。</span>
                    </div>
                    <div v-else class="quote-card-list">
                        <div v-for="(row, index) in quoteList" :key="`${row.productCode || row.product_code || row.productName}-${getQuotePrice(row)}`" class="quote-card" :class="{ active: isQuoteSelected(row) }" @click="selectQuote(row)">
                            <div>
                                <strong>
                                    <span v-if="index === 0" class="quote-rank">最低</span>
                                    {{ row.productName || row.product_name || '快递产品' }}
                                </strong>
                                <span>{{ row.channelName || row.channel_name || '易速渠道' }}</span>
                            </div>
                            <em>¥{{ getQuotePrice(row) }}</em>
                        </div>
                    </div>
                    <div class="quote-tip">
                        下单会使用当前选中的快递公司。若重量、保价、预约时间或地址变动，请重新获取报价。
                    </div>
                </aside>
            </div>

            <template #footer>
                <el-button @click="createDialogVisible = false">关闭</el-button>
                <el-tooltip :disabled="canCreateShipment" :content="createShipmentDisabledTip" placement="top">
                    <span>
                        <el-button type="primary" :disabled="!canCreateShipment" :loading="shipmentLoading.create" @click="runShipmentCreate">按选中快递下单</el-button>
                    </span>
                </el-tooltip>
            </template>
        </el-dialog>

        <ExpressTrackDialog
            v-model:visible="expressTrackDialogVisible"
            :express-no="currentTrackOrder?.delivery_id || ''"
            :mobile="currentTrackMobile"
            :company-name="currentTrackOrder?.provider_name || currentTrackOrder?.express_company || '快递公司'"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import EmptyState from '@/addon/hsx_recycle/components/empty-state/index.vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    cancelOrInterceptExpressOrder,
    createExpressOrderDirect,
    getExpressQuote,
    getExpressOrderRecordList,
    getExpressOrderRecordInfo,
    getExpressWaybillPdf,
    getExpressAddressBookList,
    saveExpressAddressBook,
    setExpressAddressBookDefault,
    setExpressAddressBookTop,
    updateExpressOrderActualInfo,
    getExpressOrderStatistics
} from '@/addon/hsx_recycle/api/express'
import { parseThirdPartyAddress } from '@/addon/hsx_recycle/api/third_party'
import { getShopAddressList } from '@/addon/hsx_recycle/api/shop_address'
import ExpressTrackDialog from '@/addon/hsx_recycle/components/ExpressTrackDialog.vue'

const route = useRoute()
const loading = ref(false)
const orderList = ref<any[]>([])
const total = ref(0)
const statistics = ref<any>({})
const detailDialogVisible = ref(false)
const updateDialogVisible = ref(false)
const createDialogVisible = ref(false)
const expressTrackDialogVisible = ref(false)
const currentOrder = ref<any>(null)
const currentTrackOrder = ref<any>(null)
const operationLoading = reactive<Record<number, '' | 'cancel' | 'intercept' | 'waybill'>>({})
const shopAddressList = ref<any[]>([])
const addressBookList = ref<any[]>([])
const selectedAddressBookIds = reactive<Record<'sender' | 'receiver', string | number>>({
    sender: '',
    receiver: ''
})
const addressBookKeywords = reactive<Record<'sender' | 'receiver', string>>({
    sender: '',
    receiver: ''
})
const addressBookLoading = reactive<Record<'sender' | 'receiver', boolean>>({
    sender: false,
    receiver: false
})
const senderRawAddress = ref('')
const receiverRawAddress = ref('')
const quoteList = ref<any[]>([])
const quoteState = reactive({
    valid: false,
    signature: '',
    selectedKey: ''
})
const addressParseLoading = reactive<Record<string, boolean>>({
    sender: false,
    receiver: false
})
const shipmentLoading = reactive({
    quote: false,
    create: false
})

const defaultSearchForm = () => ({
    keyword: '',
    order_no: '',
    delivery_id: '',
    order_status: '',
    product_code: '',
    sender_mobile: '',
    receiver_mobile: '',
    recycle_order_id: '',
    create_time: [] as string[],
    page: 1,
    limit: 20
})

const searchForm = reactive(defaultSearchForm())
const updateForm = reactive({
    id: 0,
    actual_weight: 0,
    actual_cost: 0
})
const defaultShipmentForm = () => ({
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
    goods: '回收设备',
    weight: 1,
    packageCount: 1,
    guaranteeValueAmount: 0,
    estimated_cost: 0,
    orderSendTime: getDefaultOrderSendTime(),
    remark: '',
    thirdOrderNo: ''
})
const shipmentForm = reactive<any>(defaultShipmentForm())

const statusOptions = [
    { label: '待揽收', value: 'pending', type: 'info' },
    { label: '已揽收', value: 'picked', type: 'warning' },
    { label: '运输中', value: 'in_transit', type: 'primary' },
    { label: '已签收', value: 'delivered', type: 'success' },
    { label: '已关闭', value: 'cancelled', type: 'danger' }
] as const

const statusMeta = (status: string): any => {
    return statusOptions.find(item => item.value === status) || { label: status || '未知', value: status || '', type: 'info' }
}

const senderAddressBooks = computed(() => addressBookList.value.filter(item => item.address_type === 'sender'))
const receiverAddressBooks = computed(() => addressBookList.value.filter(item => item.address_type === 'receiver'))
const filteredShopAddressList = computed(() => {
    const keyword = addressBookKeywords.receiver.trim()
    if (!keyword) return shopAddressList.value
    return shopAddressList.value.filter(item => shopAddressOptionLabel(item).includes(keyword))
})
const selectedSenderAddress = computed(() => senderAddressBooks.value.find(item => String(item.id) === String(selectedAddressBookIds.sender)))
const selectedReceiverAddress = computed(() => {
    const value = String(selectedAddressBookIds.receiver || '')
    if (!value.startsWith('book:')) return null
    const id = value.replace('book:', '')
    return receiverAddressBooks.value.find(item => String(item.id) === id) || null
})
const selectedQuoteName = computed(() => {
    const quote = quoteList.value.find(item => quoteKey(item) === quoteState.selectedKey)
    return quote?.productName || quote?.product_name || shipmentForm.deliveryType || '快递产品'
})
const createShipmentDisabledTip = computed(() => {
    if (shipmentLoading.create) return ''
    if (!quoteList.value.length) return '请先获取报价'
    if (!quoteState.valid || !shipmentForm.deliveryType) return '请先选择一个报价'
    if (quoteState.signature !== quoteSignature()) return '当前报价已失效，请重新获取报价'
    return ''
})
const canCreateShipment = computed(() => !shipmentLoading.create && !createShipmentDisabledTip.value)

const buildParams = () => {
    const params: Record<string, any> = {
        page: searchForm.page,
        limit: searchForm.limit
    }
    ;['keyword', 'order_no', 'delivery_id', 'order_status', 'product_code', 'sender_mobile', 'receiver_mobile'].forEach((key) => {
        if ((searchForm as any)[key]) params[key] = (searchForm as any)[key]
    })
    if (searchForm.recycle_order_id) params.recycle_order_id = searchForm.recycle_order_id
    if (searchForm.create_time?.length === 2) params.create_time = searchForm.create_time
    return params
}

const loadOrderList = async () => {
    loading.value = true
    try {
        const res = await getExpressOrderRecordList(buildParams())
        orderList.value = res.data.data || res.data.list || []
        total.value = res.data.total || 0
    } catch (error) {
        ElMessage.error('加载运单记录失败')
    } finally {
        loading.value = false
    }
}

const loadStatistics = async () => {
    try {
        const params: Record<string, any> = {}
        if (searchForm.create_time?.length === 2) {
            params.start_time = Math.floor(new Date(searchForm.create_time[0]).getTime() / 1000)
            params.end_time = Math.floor(new Date(searchForm.create_time[1]).getTime() / 1000)
        }
        const res = await getExpressOrderStatistics(params)
        statistics.value = res.data || {}
    } catch (error) {
        statistics.value = {}
    }
}

const loadCreateOptions = async () => {
    const [addressRes, bookRes] = await Promise.allSettled([
        getShopAddressList({ page: 1, limit: 100 }),
        getExpressAddressBookList({})
    ])
    if (addressRes.status === 'fulfilled') {
        shopAddressList.value = addressRes.value.data?.list || []
    }
    if (bookRes.status === 'fulfilled') {
        addressBookList.value = bookRes.value.data || []
    }
    applyDefaultAddressBooks()
}

const handleSearch = async () => {
    searchForm.page = 1
    await refreshPage()
}

const handlePageChange = async () => {
    await loadOrderList()
}

const refreshPage = async () => {
    await Promise.all([loadOrderList(), loadStatistics()])
}

const resetForm = async () => {
    Object.assign(searchForm, defaultSearchForm())
    await refreshPage()
}

const handleViewDetail = async (row: any) => {
    try {
        const res = await getExpressOrderRecordInfo(row.id)
        currentOrder.value = res.data
        detailDialogVisible.value = true
    } catch (error) {
        ElMessage.error('获取运单详情失败')
    }
}

const currentTrackMobile = computed(() => {
    if (!currentTrackOrder.value) return ''
    return currentTrackOrder.value.sender_mobile || currentTrackOrder.value.receiver_mobile || ''
})

const openExpressTrack = (row: any) => {
    if (!row.delivery_id) {
        ElMessage.warning('当前运单没有快递单号')
        return
    }
    const mobile = row.sender_mobile || row.receiver_mobile || ''
    if (!mobile) {
        ElMessage.warning('无法获取手机号后四位，无法查询快递信息')
        return
    }
    currentTrackOrder.value = row
    expressTrackDialogVisible.value = true
}

const handleUpdateActual = (row: any) => {
    updateForm.id = row.id
    updateForm.actual_weight = Number(row.actual_weight || row.estimated_weight || 0)
    updateForm.actual_cost = Number(row.actual_cost || row.estimated_cost || 0)
    updateDialogVisible.value = true
}

const handleConfirmUpdate = async () => {
    try {
        await updateExpressOrderActualInfo(updateForm)
        ElMessage.success('更新成功')
        updateDialogVisible.value = false
        await refreshPage()
    } catch (error: any) {
        ElMessage.error(error.message || '更新失败')
    }
}

const resetShipmentForm = () => {
    Object.assign(shipmentForm, defaultShipmentForm())
    senderRawAddress.value = ''
    receiverRawAddress.value = ''
    quoteList.value = []
    resetQuoteState()
    selectedAddressBookIds.sender = ''
    selectedAddressBookIds.receiver = ''
    addressBookKeywords.sender = ''
    addressBookKeywords.receiver = ''
}

const openCreateDialog = async () => {
    resetShipmentForm()
    createDialogVisible.value = true
    await loadCreateOptions()
}

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

const fillParsedAddress = (type: 'sender' | 'receiver', parsed: Record<string, any>) => {
    if (type === 'sender') {
        shipmentForm.senderName = parsed.name || shipmentForm.senderName
        shipmentForm.senderMobile = parsed.mobile || shipmentForm.senderMobile
        shipmentForm.senderProvince = parsed.province || shipmentForm.senderProvince
        shipmentForm.senderCity = parsed.city || shipmentForm.senderCity
        shipmentForm.senderDistrict = parsed.district || shipmentForm.senderDistrict
        shipmentForm.senderAddress = parsed.address || parsed.info || shipmentForm.senderAddress
    } else {
        shipmentForm.receiveName = parsed.name || shipmentForm.receiveName
        shipmentForm.receiveMobile = parsed.mobile || shipmentForm.receiveMobile
        shipmentForm.receiveProvince = parsed.province || shipmentForm.receiveProvince
        shipmentForm.receiveCity = parsed.city || shipmentForm.receiveCity
        shipmentForm.receiveDistrict = parsed.district || shipmentForm.receiveDistrict
        shipmentForm.receiveAddress = parsed.address || parsed.info || shipmentForm.receiveAddress
    }
}

const clearShipmentAddress = (type: 'sender' | 'receiver') => {
    if (type === 'sender') {
        selectedAddressBookIds.sender = ''
        senderRawAddress.value = ''
        shipmentForm.senderName = ''
        shipmentForm.senderMobile = ''
        shipmentForm.senderProvince = ''
        shipmentForm.senderCity = ''
        shipmentForm.senderDistrict = ''
        shipmentForm.senderAddress = ''
    } else {
        selectedAddressBookIds.receiver = ''
        receiverRawAddress.value = ''
        shipmentForm.receiveName = ''
        shipmentForm.receiveMobile = ''
        shipmentForm.receiveProvince = ''
        shipmentForm.receiveCity = ''
        shipmentForm.receiveDistrict = ''
        shipmentForm.receiveAddress = ''
    }
    resetQuoteState()
}

const resetQuoteState = () => {
    quoteState.valid = false
    quoteState.signature = ''
    quoteState.selectedKey = ''
    shipmentForm.estimated_cost = 0
}

function getDefaultOrderSendTime() {
    const date = new Date()
    const addHours = date.getMinutes() >= 30 ? 2 : 1
    date.setHours(date.getHours() + addHours, 0, 0, 0)
    const pad = (num: number) => String(num).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:00:00`
}

const ensureOrderSendTime = () => {
    if (!shipmentForm.orderSendTime) {
        shipmentForm.orderSendTime = getDefaultOrderSendTime()
    }
}

const quoteSignature = () => JSON.stringify({
    deliveryType: shipmentForm.deliveryType,
    senderProvince: shipmentForm.senderProvince,
    senderCity: shipmentForm.senderCity,
    senderDistrict: shipmentForm.senderDistrict,
    senderAddress: shipmentForm.senderAddress,
    receiveProvince: shipmentForm.receiveProvince,
    receiveCity: shipmentForm.receiveCity,
    receiveDistrict: shipmentForm.receiveDistrict,
    receiveAddress: shipmentForm.receiveAddress,
    goods: shipmentForm.goods,
    weight: shipmentForm.weight,
    packageCount: shipmentForm.packageCount,
    guaranteeValueAmount: shipmentForm.guaranteeValueAmount,
    orderSendTime: shipmentForm.orderSendTime
})

watch(
    () => quoteSignature(),
    (signature) => {
        if (quoteState.valid && quoteState.signature && signature !== quoteState.signature) {
            quoteState.valid = false
            shipmentForm.estimated_cost = 0
        }
    }
)

const parseRawAddress = async (type: 'sender' | 'receiver') => {
    const raw = type === 'sender' ? senderRawAddress.value : receiverRawAddress.value
    if (!raw.trim()) {
        ElMessage.warning('请先粘贴需要解析的地址')
        return
    }

    addressParseLoading[type] = true
    try {
        const res = await parseThirdPartyAddress({ address: raw })
        fillParsedAddress(type, res.data || {})
        ElMessage.success('地址解析成功')
    } catch (error) {
        fillParsedAddress(type, parseAddressText(raw))
        ElMessage.warning('地址解析接口不可用，已使用本地基础解析')
    } finally {
        addressParseLoading[type] = false
    }
}

const applyShopAddress = (id: string | number) => {
    const item = shopAddressList.value.find(address => String(address.id) === String(id))
    if (!item) return
    receiverRawAddress.value = `${item.contact_name || ''} ${item.mobile || ''} ${item.full_address || item.address || ''}`
    fillParsedAddress('receiver', parseAddressText(receiverRawAddress.value))
}

const formatAddressBook = (item: any) => `${item.province || ''}${item.city || ''}${item.district || ''}${item.address || ''}`
const addressOptionLabel = (item: any) => `${item.name || ''} ${item.mobile || ''} ${formatAddressBook(item)} ${item.tag || ''}`.trim()
const shopAddressOptionLabel = (item: any) => `${item.contact_name || ''} ${item.mobile || ''} ${item.full_address || item.address || ''}`.trim()

const applyAddressBook = (item: any) => {
    const parsed = {
        name: item.name,
        mobile: item.mobile,
        province: item.province,
        city: item.city,
        district: item.district,
        address: item.address
    }
    if (item.address_type === 'sender') {
        selectedAddressBookIds.sender = item.id
        senderRawAddress.value = `${item.name} ${item.mobile} ${formatAddressBook(item)}`
        fillParsedAddress('sender', parsed)
    } else {
        selectedAddressBookIds.receiver = `book:${item.id}`
        receiverRawAddress.value = `${item.name} ${item.mobile} ${formatAddressBook(item)}`
        fillParsedAddress('receiver', parsed)
    }
}

const applyAddressBookById = (type: 'sender' | 'receiver', value: string | number) => {
    if (!value) return
    const rawValue = String(value)
    if (type === 'receiver' && rawValue.startsWith('shop:')) {
        applyShopAddress(rawValue.replace('shop:', ''))
        return
    }

    const id = rawValue.replace('book:', '')
    const list = type === 'sender' ? senderAddressBooks.value : receiverAddressBooks.value
    const item = list.find(address => String(address.id) === id)
    if (item) applyAddressBook(item)
}

const applyDefaultAddressBooks = () => {
    const sender = senderAddressBooks.value.find(item => Number(item.is_default) === 1) || senderAddressBooks.value[0]
    const receiver = receiverAddressBooks.value.find(item => Number(item.is_default) === 1) || receiverAddressBooks.value[0]
    const shopReceiver = shopAddressList.value.find(item => Number(item.is_default_refund) === 1) || shopAddressList.value[0]
    if (sender) applyAddressBook(sender)
    if (receiver) {
        applyAddressBook(receiver)
    } else if (shopReceiver) {
        selectedAddressBookIds.receiver = `shop:${shopReceiver.id}`
        applyShopAddress(shopReceiver.id)
    }
}

const searchAddressBooks = async (type: 'sender' | 'receiver', keyword = '') => {
    addressBookKeywords[type] = keyword
    addressBookLoading[type] = true
    try {
        const res = await getExpressAddressBookList({ address_type: type, keyword })
        const nextList = res.data || []
        const otherList = addressBookList.value.filter(item => item.address_type !== type)
        addressBookList.value = [...otherList, ...nextList]
    } finally {
        addressBookLoading[type] = false
    }
}

const buildAddressPayload = (type: 'sender' | 'receiver') => {
    if (type === 'sender') {
        return {
            address_type: 'sender',
            name: shipmentForm.senderName,
            mobile: shipmentForm.senderMobile,
            province: shipmentForm.senderProvince,
            city: shipmentForm.senderCity,
            district: shipmentForm.senderDistrict,
            address: shipmentForm.senderAddress,
            tag: '手动保存'
        }
    }

    return {
        address_type: 'receiver',
        name: shipmentForm.receiveName,
        mobile: shipmentForm.receiveMobile,
        province: shipmentForm.receiveProvince,
        city: shipmentForm.receiveCity,
        district: shipmentForm.receiveDistrict,
        address: shipmentForm.receiveAddress,
        tag: '手动保存'
    }
}

const saveCurrentAddress = async (type: 'sender' | 'receiver') => {
    const payload = buildAddressPayload(type)
    const required = ['name', 'mobile', 'province', 'city', 'district', 'address']
    if (required.some(key => !payload[key as keyof typeof payload])) {
        ElMessage.warning(type === 'sender' ? '请先补全寄件人地址' : '请先补全收件人地址')
        return
    }
    await saveExpressAddressBook(payload)
    ElMessage.success('常用地址已保存')
    await searchAddressBooks(type, addressBookKeywords[type])
    const list = type === 'sender' ? senderAddressBooks.value : receiverAddressBooks.value
    const saved = list.find(item => item.mobile === payload.mobile && item.address === payload.address)
    if (saved) applyAddressBook(saved)
}

const reloadAddressBook = async () => {
    const [senderRes, receiverRes] = await Promise.all([
        getExpressAddressBookList({ address_type: 'sender', keyword: addressBookKeywords.sender }),
        getExpressAddressBookList({ address_type: 'receiver', keyword: addressBookKeywords.receiver })
    ])
    addressBookList.value = [...(senderRes.data || []), ...(receiverRes.data || [])]
}

const setAddressDefault = async (item: any) => {
    await setExpressAddressBookDefault(item.id)
    ElMessage.success('默认地址已更新')
    await reloadAddressBook()
}

const toggleAddressTop = async (item: any) => {
    const nextTop = Number(item.is_top) === 1 ? 0 : 1
    await setExpressAddressBookTop(item.id, nextTop)
    ElMessage.success(nextTop ? '地址已置顶' : '已取消置顶')
    await reloadAddressBook()
}

const getQuotePrice = (row: any) => {
    const fields = [
        'estimatedCost',
        'totalPrice',
        'totalFee',
        'totalAmount',
        'price',
        'fee',
        'amount',
        'prePrice',
        'predictPrice',
        'estimatedPrice',
        'freight',
        'freightFee',
        'transportFee',
        'channelFee'
    ]
    for (const field of fields) {
        const value = Number(row[field])
        if (value > 0) return value.toFixed(2)
    }

    const detailFee = ['channelFee', 'serviceCharge', 'serviceFee', 'guarantFee', 'guaranteeFee', 'incrementFee', 'otherFee']
        .reduce((total, field) => total + Number(row[field] || 0), 0)
    return detailFee.toFixed(2)
}

const selectQuote = (row: any) => {
    shipmentForm.deliveryType = String(row.productCode || row.product_code || shipmentForm.deliveryType)
    shipmentForm.estimated_cost = Number(getQuotePrice(row))
    quoteState.valid = true
    quoteState.signature = quoteSignature()
    quoteState.selectedKey = quoteKey(row)
}

const quoteKey = (row: any) => `${row.productCode || row.product_code || ''}-${row.productName || row.product_name || ''}-${getQuotePrice(row)}`
const isQuoteSelected = (row: any) => quoteKey(row) === quoteState.selectedKey

const validateShipment = (requireProduct = true) => {
    const required = [
        ['senderName', '请填写寄件人姓名'],
        ['senderMobile', '请填写寄件人手机号'],
        ['senderProvince', '请填写寄件省份'],
        ['senderCity', '请填写寄件城市'],
        ['senderDistrict', '请填写寄件区县'],
        ['senderAddress', '请填写寄件详细地址'],
        ['receiveName', '请填写收件人姓名'],
        ['receiveMobile', '请填写收件人手机号'],
        ['receiveProvince', '请填写收件省份'],
        ['receiveCity', '请填写收件城市'],
        ['receiveDistrict', '请填写收件区县'],
        ['receiveAddress', '请填写收件详细地址']
    ]
    if (requireProduct) required.unshift(['deliveryType', '请先选择一个报价'])
    for (const [field, message] of required) {
        if (!shipmentForm[field]) {
            ElMessage.warning(message)
            return false
        }
    }
    return true
}

const runShipmentQuote = async () => {
    ensureOrderSendTime()
    if (!validateShipment(false)) return
    shipmentLoading.quote = true
    try {
        shipmentForm.deliveryType = ''
        const res = await getExpressQuote({ ...shipmentForm, deliveryType: '', productCode: '' })
        quoteList.value = (res.data || []).sort((left: any, right: any) => Number(getQuotePrice(left)) - Number(getQuotePrice(right)))
        if (quoteList.value[0]) selectQuote(quoteList.value[0])
        if (!quoteList.value.length) resetQuoteState()
        ElMessage.success(`已获取 ${quoteList.value.length} 个可用报价`)
    } finally {
        shipmentLoading.quote = false
    }
}

const runShipmentCreate = async () => {
    ensureOrderSendTime()
    if (!validateShipment()) return
    if (!quoteState.valid || quoteState.signature !== quoteSignature()) {
        ElMessage.warning('当前报价已失效，请重新获取报价后再下单')
        quoteState.valid = false
        return
    }
    if (!shipmentForm.thirdOrderNo) {
        shipmentForm.thirdOrderNo = `manual_${Date.now()}`
    }
    shipmentLoading.create = true
    try {
        await createExpressOrderDirect(shipmentForm)
        ElMessage.success('快递下单成功')
        createDialogVisible.value = false
        await refreshPage()
    } finally {
        shipmentLoading.create = false
    }
}

const canOperateClose = (row: any) => !['cancelled', 'delivered'].includes(row.order_status) && (row.order_no || row.delivery_id || row.recycle_order_id)

const canCancel = (row: any) => canOperateClose(row) && ['pending', ''].includes(row.order_status || '')

const canIntercept = (row: any) => canOperateClose(row) && !canCancel(row)

const buildCancelParams = (row: any, genre: 1 | 3) => {
    const params: Record<string, any> = { genre }
    if (row.order_no) params.order_no = row.order_no
    if (row.delivery_id) params.waybill_no = row.delivery_id
    if (row.site_id && row.recycle_order_id) params.third_order_no = `recycle_${row.site_id}_${row.recycle_order_id}`
    return params
}

const handleCloseOrder = async (row: any) => {
    const genre: 1 | 3 = canCancel(row) ? 1 : 3
    const actionName = genre === 3 ? '拦截' : '取消'
    try {
        await ElMessageBox.confirm(`确认${actionName}这个运单吗？操作成功后列表状态会变为已关闭。`, `${actionName}运单`, {
            type: 'warning',
            confirmButtonText: actionName,
            cancelButtonText: '返回'
        })
    } catch (error) {
        return
    }

    operationLoading[row.id] = genre === 3 ? 'intercept' : 'cancel'
    try {
        await cancelOrInterceptExpressOrder(buildCancelParams(row, genre))
        ElMessage.success(`${actionName}请求已提交`)
        await refreshPage()
    } catch (error: any) {
        ElMessage.error(error.message || `${actionName}失败`)
    } finally {
        operationLoading[row.id] = ''
    }
}

const buildWaybillParams = (row: any) => {
    const params: Record<string, any> = {}
    if (row.order_no) params.order_no = row.order_no
    if (row.delivery_id) params.waybill_no = row.delivery_id
    if (row.site_id && row.recycle_order_id) params.third_order_no = `recycle_${row.site_id}_${row.recycle_order_id}`
    params.template_code = String(row.product_code) === '59' ? '113' : ''
    return params
}

const openWaybillData = (data: any) => {
    const pdfData = data?.pdfData || data?.url || data?.pdf_url || ''
    if (!pdfData) return false
    if (Number(data?.dataFormat) === 1 || /^https?:\/\//.test(pdfData)) {
        window.open(pdfData, '_blank')
        return true
    }
    const base64 = pdfData.startsWith('data:') ? pdfData : `data:application/pdf;base64,${pdfData}`
    const win = window.open('', '_blank')
    if (win) {
        win.document.write(`<iframe src="${base64}" style="border:0;width:100%;height:100vh;"></iframe>`)
        return true
    }
    return false
}

const handleWaybillPdf = async (row: any) => {
    operationLoading[row.id] = 'waybill'
    try {
        const res = await getExpressWaybillPdf(buildWaybillParams(row))
        const opened = openWaybillData(res.data || {})
        ElMessage.success(opened ? '面单已打开' : '面单获取成功')
    } catch (error: any) {
        ElMessage.error(error.message || '获取面单失败')
    } finally {
        operationLoading[row.id] = ''
    }
}

const joinAddress = (row: any, type: 'sender' | 'receiver') => {
    const prefix = type === 'sender' ? 'sender' : 'receiver'
    return [row[`${prefix}_province`], row[`${prefix}_city`], row[`${prefix}_district`], row[`${prefix}_address`]].filter(Boolean).join('') || '-'
}

const formatMoney = (value: any) => Number(value || 0).toFixed(2)

const formatTime = (value: any) => {
    if (!value) return '-'
    let time = value
    if (typeof time === 'string' && /^\d+$/.test(time)) time = Number(time)
    const date = typeof time === 'number' ? new Date(time < 1000000000000 ? time * 1000 : time) : new Date(time)
    if (Number.isNaN(date.getTime())) return '-'
    const pad = (num: number) => String(num).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const clearQuickActionQuery = () => {
    const url = new URL(window.location.href)
    url.searchParams.delete('quick_action')
    url.searchParams.delete('t')
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
}

const handleRouteQuickAction = async () => {
    if (!['create', 'search'].includes(String(route.query.quick_action || ''))) {
        return
    }

    if (route.query.quick_action === 'create') {
        await openCreateDialog()
    }

    if (route.query.quick_action === 'search') {
        const keywordInput = document.querySelector<HTMLInputElement>('.filter-panel input')
        keywordInput?.focus()
    }

    clearQuickActionQuery()
}

onMounted(async () => {
    await refreshPage()
    await handleRouteQuickAction()
})
</script>

<style scoped lang="scss">
.main-container {
    padding: 10px;
}

.page-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.page-desc {
    margin-top: 6px;
    color: #6b7280;
    font-size: 13px;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
}

.stat-item {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px 16px;
    background: #fff;

    span {
        display: block;
        color: #6b7280;
        font-size: 13px;
    }

    strong {
        display: block;
        margin-top: 8px;
        color: #111827;
        font-size: 22px;
        line-height: 1;
    }
}

.filter-panel {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px 16px 0;
    background: #fbfdff;
}

.primary-text {
    color: #111827;
    font-weight: 600;
}

.muted-text {
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
}

.clickable-text {
    cursor: pointer;
    color: #2563eb;
}

.text-line {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.detail-section-title {
    margin: 18px 0 10px;
    color: #111827;
    font-weight: 600;
}

.success {
    color: #16a34a !important;
}

.danger {
    color: #dc2626 !important;
}

.head-actions {
    display: flex;
    gap: 8px;
}

.shipment-form {
    :deep(.el-form-item) {
        margin-bottom: 14px;
    }

    :deep(.el-form-item__content) {
        min-width: 0;
    }

    :deep(.el-input),
    :deep(.el-select) {
        width: 100%;
    }

    :deep(.el-input-number) {
        width: 100%;
    }

    :deep(.el-date-editor) {
        width: 100%;
    }
}

.shipment-dialog {
    :deep(.el-dialog__body) {
        padding-top: 12px;
        max-height: calc(100vh - 190px);
        overflow: auto;
    }

    :deep(.el-dialog__footer) {
        padding-top: 12px;
        border-top: 1px solid #edf0f5;
    }
}

.shipment-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 304px;
    gap: 16px;
    align-items: flex-start;
}

.shipment-main {
    min-width: 0;
}

.shipment-form {
    :deep(.el-form-item) {
        margin-bottom: 14px;
    }

    :deep(.el-form-item__content) {
        min-width: 0;
    }

    :deep(.el-input),
    :deep(.el-select),
    :deep(.el-input-number),
    :deep(.el-date-editor) {
        width: 100%;
    }
}

.quote-alert {
    margin-bottom: 12px;
}

.shipment-section {
    min-width: 0;
    padding: 16px 16px 4px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;

    & + .shipment-section {
        margin-top: 12px;
    }
}

.section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;

    strong {
        display: block;
        color: #111827;
        font-size: 15px;
        line-height: 1.3;
    }

    span {
        display: block;
        margin-top: 4px;
        color: #6b7280;
        font-size: 12px;
        line-height: 1.5;
    }
}

.address-panel {
    min-width: 0;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px 14px 2px;
    background: #fbfdff;
}

.address-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.field-grid {
    display: grid;
    gap: 10px;
    min-width: 0;

    &.two {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    &.three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.parse-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 72px;
    gap: 8px;
    width: 100%;

    .el-button {
        align-self: stretch;
        height: auto;
    }
}

.address-title {
    display: flex;
    min-height: 32px;
    margin-bottom: 10px;
    align-items: center;
    justify-content: space-between;
    color: #111827;
    font-weight: 600;
    gap: 10px;
}

.address-title-actions {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 8px;
}

.package-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 0 12px;

    > :deep(.el-form-item) {
        grid-column: span 3;
        min-width: 0;
    }

    .product-field,
    .goods-field,
    .time-field {
        grid-column: span 4;
    }

    .remark-field {
        grid-column: span 8;
    }
}

.address-selector {
    margin-bottom: 12px;
}

.selected-address {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-top: 8px;
    padding: 8px 10px;
    border: 1px solid #dbeafe;
    border-radius: 6px;
    background: #eff6ff;

    strong {
        color: #111827;
        font-size: 13px;
        line-height: 1.4;
    }

    span {
        color: #4b5563;
        font-size: 12px;
        line-height: 1.5;
    }
}

.address-option {
    display: flex;
    width: 100%;
    min-height: 58px;
    align-items: center;
    justify-content: space-between;
    gap: 12px;

    strong,
    span {
        display: block;
        max-width: 420px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    strong {
        color: #111827;
        font-size: 13px;
        line-height: 1.4;
    }

    span {
        margin-top: 2px;
        color: #6b7280;
        font-size: 12px;
        line-height: 1.5;
    }
}

.address-option-actions {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 8px;

    em {
        padding: 1px 5px;
        border-radius: 4px;
        color: #2563eb;
        background: #eff6ff;
        font-size: 11px;
        font-style: normal;
    }

    :deep(.el-button) {
        height: 20px;
        padding: 0;
        font-size: 12px;
    }
}

.shipment-side {
    position: sticky;
    top: 0;
    max-height: calc(100vh - 230px);
    padding: 14px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fbfdff;
    overflow: auto;
}

.side-title {
    color: #111827;
    font-size: 15px;
    font-weight: 600;
}

.quote-empty {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 12px;
    padding: 18px 12px;
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    color: #6b7280;
    text-align: center;

    strong {
        color: #111827;
        font-size: 14px;
    }

    span {
        font-size: 12px;
        line-height: 1.5;
    }
}

.quote-card-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 12px;
}

.quote-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;

    &.active {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, .12);
    }

    strong,
    span {
        display: block;
    }

    strong {
        color: #111827;
        font-size: 13px;
    }

    span {
        margin-top: 4px;
        color: #6b7280;
        font-size: 12px;
    }

    em {
        color: #dc2626;
        font-size: 16px;
        font-style: normal;
        font-weight: 700;
    }
}

.quote-tip {
    margin-top: 12px;
    padding: 10px;
    border-radius: 6px;
    color: #92400e;
    background: #fffbeb;
    font-size: 12px;
    line-height: 1.6;
}

@media (max-width: 1180px) {
    .shipment-layout {
        grid-template-columns: 1fr;
    }

    .shipment-side {
        position: static;
    }

    .address-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .shipment-side {
        max-height: none;
    }
}

@media (max-width: 920px) {
    .address-form-grid {
        grid-template-columns: 1fr;
    }

    .package-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));

        > :deep(.el-form-item),
        .product-field,
        .goods-field,
        .time-field,
        .remark-field {
            grid-column: span 6;
        }
    }
}

@media (max-width: 760px) {
    .field-grid.two,
    .field-grid.three {
        grid-template-columns: 1fr;
    }

    .address-title,
    .address-title-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .address-option {
        align-items: flex-start;
        flex-direction: column;

        strong,
        span {
            max-width: 100%;
        }
    }
}
</style>

<style lang="scss">
.express-address-select-dropdown {
    .el-select-dropdown__item {
        height: auto;
        min-height: 66px;
        padding: 8px 12px;
        line-height: normal;
    }

    .el-select-group__title {
        height: 30px;
        padding-left: 12px;
        line-height: 30px;
    }

    .el-select-dropdown__item.selected,
    .el-select-dropdown__item.hover {
        background-color: #f5f8ff;
    }
}
</style>
