<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ pageName }}</span>
            </div>
            <el-alert
                v-if="isOfflineView"
                class="mt-[12px]"
                title="线下自提订单工作台"
                description="新订单先联系客户并确认到店安排；客户到店后上传收款凭证，确认收款并交付设备。无法联系的订单可填写原因后关闭。"
                type="info"
                :closable="false"
                show-icon
            />

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="orderTable.searchParam" ref="searchFormRef">

                    <el-form-item :label="t('orderInfo')" prop='search_name'>
                        <el-select v-model="orderTable.searchParam.search_type" clearable class="input-item">
                            <el-option :label="t('orderNo')" value="order_no"></el-option>
                            <el-option :label="t('outTradeNo')" value="out_trade_no"></el-option>
                            <el-option :label="t('goodsName')" value="goods_name"></el-option>
                        </el-select>
                        <el-input class="input-item ml-3" v-model.trim="orderTable.searchParam.search_name" />
                    </el-form-item>
                    <el-form-item :label="t('memberInfo')" prop='keyword'>
                        <el-input class="!w-[260px]" v-model.trim="orderTable.searchParam.keyword" :placeholder="t('memberInfoPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('payType')" prop='pay_type'>
                        <el-select v-model="orderTable.searchParam.pay_type" clearable class="input-item">
                            <el-option v-for="(item, index) in payTypeData" :key="index" :label="item.name" :value="item.key"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item label="成交方式" prop="payment_mode" v-if="!isOfflineView">
                        <el-select v-model="orderTable.searchParam.payment_mode" clearable class="input-item">
                            <el-option label="在线支付" value="online" />
                            <el-option label="线下待处理" value="offline_pending" />
                            <el-option label="线下已收款" value="offline_cash" />
                            <el-option label="线下挂账" value="offline_credit" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('fromType')" prop='order_from'>
                        <el-select v-model="orderTable.searchParam.order_from" clearable class="input-item">
                            <el-option v-for="(item, index) in orderFromData" :key="index" :label="item" :value="index"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('createTime')" prop="create_time">
                        <el-date-picker v-model="orderTable.searchParam.create_time" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss" :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
                    </el-form-item>
                    <el-form-item :label="t('payTime')" prop="pay_time">
                        <el-date-picker v-model="orderTable.searchParam.pay_time" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss" :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadOrderList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                        <el-button type="primary" @click="exportSelectEvent">{{ t('export') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>
            <el-tabs v-model="activeName" class="demo-tabs" @tab-change="handleClick">
                <el-tab-pane :label="t('all')" name=""></el-tab-pane>
                <el-tab-pane :label="t('toBePaid')" name="1"></el-tab-pane>
                <el-tab-pane :label="t('toBeShipped')" name="2"></el-tab-pane>
                <el-tab-pane :label="t('shipped')" name="3"></el-tab-pane>
                <el-tab-pane :label="t('completed')" name="5"></el-tab-pane>
                <el-tab-pane :label="t('closed')" name="-1"></el-tab-pane>
            </el-tabs>
            <div>
                <!-- todo 后续完善，增加批量发货，再修改判断逻辑 -->
                <div class="mb-[10px] flex items-center">
                    <el-button @click="batchPrintElectronicSheet" size="small" v-if="activeName == 3">
                        {{ t('batchPrintElectronicSheet') }}
                    </el-button>
                    <el-button @click="batchDeleteFn" size="small" v-if="activeName == -1">
                        {{ t('批量删除') }}
                    </el-button>
                </div>
                <el-table :data="orderTable.data" size="large" class="table-top" @select-all="selectAllCheck">
                    <el-table-column type="selection" width="40" />
                    <el-table-column :label="t('orderGoods')" min-width="200" />
                    <el-table-column :label="t('goodsPriceNumber')" min-width="120" />
                    <el-table-column :label="t('rightsProtection')" min-width="120" />
                    <el-table-column :label="t('orderMoney')" min-width="120" />
                    <el-table-column :label="t('buyInfo')" min-width="120" />
                    <el-table-column :label="t('deliveryType')" min-width="100" />
                    <el-table-column :label="t('orderStatus')" min-width="100" />
                    <el-table-column :label="t('operation')" fixed="right" align="right" min-width="120" />
                </el-table>
                <div class="table-body min-h-[150px]" v-loading="orderTable.loading">
                    <div v-if="!orderTable.loading">
                        <template v-if="orderTable.data.length">
                            <div v-for="(item, index) in orderTable.data" :key="index">
                                <div class="flex items-center justify-between bg-[#f7f8fa] mt-[10px] border-[#e4e7ed] border-solid border-b-[1px] px-3 h-[35px] text-[12px] text-[#666]">
                                    <div>
                                        <span>{{ t('orderNo') }}：{{ (item as any).order_no }}</span>
                                        <span class="ml-5">{{ t('createTime') }}：{{ (item as any).create_time }}</span>
                                        <!-- <span class="ml-5">{{ t('orderFrom') }}：{{ (item as any).order_form_name }}</span> -->
                                        <span class="ml-5" v-if="item.pay">{{ t('payType') }}：{{ (item as any).pay.type_name }}</span>
                                        <el-tag v-if="item.payment_mode === 'offline_pending'" class="ml-5" type="warning" effect="dark" size="small">线下待处理</el-tag>
                                        <el-tag v-else-if="item.payment_mode === 'offline_cash'" class="ml-5" type="success" size="small">线下已收款</el-tag>
                                        <el-tag v-else-if="item.payment_mode === 'offline_credit'" class="ml-5" type="info" size="small">线下挂账</el-tag>
                                        <span class="ml-5" v-if="item.activity_type_name">{{ t('营销') }}：{{ (item as any).activity_type_name }}</span>
                                        <template v-if="item.offline_record">
                                            <el-tag class="ml-5" :type="offlineRecordTag(item.offline_record.status).type" size="small">
                                                {{ offlineRecordTag(item.offline_record.status).label }}
                                            </el-tag>
                                            <span class="ml-5">负责人：{{ item.offline_record.handler_name || '待认领' }}</span>
                                            <span v-if="item.offline_record.contact_at" class="ml-5">联系时间：{{ formatTimestamp(item.offline_record.contact_at) }}</span>
                                        </template>
                                        <span class="ml-5" v-if="item.delivery_type =='store' && item.buyer_ask_delivery_time">{{ t('buyerAskDeliveryTime') }}：：{{ (item as any).buyer_ask_delivery_time }}</span>
                                    </div>
                                    <div>
                                        <!-- <el-button type="primary" link>{{ t('offlinePayment') }}</el-button> -->
                                        <el-button type="primary" link @click="printTicketEvent(item)" v-if="item.isSupportPrintTicket">{{ t('printTicket') }}</el-button>
                                        <el-button type="primary" link @click="openElectronicSheetPrintDialog(item)" v-if="item.isSupportElectronicSheet">{{ t('electronicSheetPrintTitle') }}</el-button>
                                        <el-button type="primary" link @click="detailEvent(item)">{{ t('info') }}</el-button>
                                        <el-button type="primary" link @click="setNotes(item)">{{ t('notes') }}</el-button>
                                    </div>
                                </div>

                                <el-table :data="item.order_goods" size="large" :show-header="false" :span-method="arraySpanMethod" :ref="(el: any) => { setTableRef(el, index) }" @select="handleSelectChange">
                                    <el-table-column type="selection" width="40" />
                                    <el-table-column align="left" min-width="200">
                                        <template #default="{ row }">
                                            <div class="flex cursor-pointer" @click="previewEvent(row)">
                                                <div class="flex items-center min-w-[50px] mr-[10px]">
                                                    <img class="w-[50px] h-[50px]" v-if="row.goods_image" :src="img(row.goods_image)" alt="" />
                                                    <img class="w-[50px] h-[50px]" v-else src="" alt="" />
                                                </div>
                                                <div class="flex flex-col items-start">
                                                    <el-tooltip class="box-item" effect="light" placement="top">
                                                        <template #content>
                                                            <div class="max-w-[250px]">{{ row.goods_name }}</div>
                                                        </template>
                                                        <p class="multi-hidden text-[14px]">{{ row.goods_name }}</p>
                                                    </el-tooltip>
                                                    <span class="text-[12px] text-[#999] truncate">{{ row.sku_name }}</span>
                                                    <span class="px-[4px]  text-[12px] text-[#fff] rounded-[4px] bg-primary leading-[18px]" v-if="row.is_gift == 1">赠品</span>
                                                </div>
                                            </div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column min-width="120">
                                        <template #default="{ row }">
                                            <div class="flex flex-col">
                                                <span v-if="item.activity_type == 'exchange'">{{ row.extend.point }}{{ t('point') }}<span v-if="parseFloat(row.price)">+￥{{ row.price }}</span></span>
                                                <span v-else-if="row.impulse_buy_info && row.impulse_buy_info.is_impulse_buy">￥{{ parseFloat(row.impulse_buy_info.impulse_buy_price).toFixed(2) }}</span>
                                                <span v-else class="text-[13px]">￥{{ row.price }}</span>
                                                <span class="text-[13px] mt-[5px]">{{ row.num }}{{ row.unit }}</span>
                                            </div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column min-width="120">
                                        <template #default="{ row }">
                                            <div class="flex flex-col cursor-pointer">
                                                <span>{{ row.status_name }}</span>
                                            </div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column min-width="120" class-name="border-0 border-l-[1px] border-solid border-[var(--el-table-border-color)]">
                                        <template #default>
                                            <div v-if="item.activity_type == 'exchange'" class="text-[14px]">
                                                {{ item.point }}{{ t('point') }}
                                                <span v-if="parseFloat(item.order_money)">+￥{{ item.order_money }}</span>
                                            </div>
                                            <span v-else class="text-[14px]">￥{{ item.order_money }}</span>
                                            <div v-if="item.pay">{{ item.member_id !== item.pay.main_id && item.pay.status == 2 ? item.pay.pay_type_name : '' }}</div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column min-width="120">
                                        <template #default>
                                            <div class="flex flex-col">
                                                <span class="text-[12px] text-primary cursor-pointer" @click="memberEvent(item.member?.member_id)">{{ item.member?.nickname || '—' }}</span>
                                                <span class="text-[12px] mt-[5px]">{{ item.taker_name }} {{ item.taker_mobile }}</span>
                                                <span class="text-[12px] mt-[5px]">{{ item.taker_full_address }}</span>
                                            </div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column min-width="100">
                                        <template #default="{ row }">
                                             <div>
                                                <div class="text-[14px]">{{ item.delivery_type_name }}</div>
                                                <div class="text-[14px] cursor-pointer text-primary" @click="toDelivery(item)">{{ row.delivery_info?.local_delivery_status_name }}</div>
                                            </div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column min-width="100">
                                        <template #default>
                                            <span class="text-[14px]">{{ item.status_name.name }}</span>
                                        </template>
                                    </el-table-column>
                                    <el-table-column align="right" min-width="120">
                                        <template #default>
                                            <template v-if="item.status == 1">
                                                <template v-if="item.payment_mode === 'offline_pending'">
                                                    <el-button v-if="item.offline_record?.status !== 'contacted'" type="primary" link @click="markOfflineContacted(item)">确认已联系</el-button>
                                                    <el-button type="success" link @click="openOfflineProcess(item, 'confirm_paid')">确认收款</el-button>
                                                    <el-button type="warning" link @click="openOfflineProcess(item, 'confirm_credit')">确认挂账</el-button>
                                                    <el-button type="danger" link @click="closeOfflineUnreachable(item)">无法联系并关闭</el-button>
                                                </template>
                                                <el-button v-else type="primary" link @click="close(item)">{{ t('orderClose') }}</el-button>
                                                <el-button v-if="item.payment_mode !== 'offline_pending'" type="primary" link @click="orderAdjustMoney(item)">{{ t('editPrice') }}</el-button>
                                            </template>
                                            <el-button type="primary" v-if="(item.status == 2 || item.status == 1) && item.delivery_type != 'virtual' && item.delivery_type!='store' && item.activity_type != 'giftcard'" link @click="orderEditAddressFn(item)">{{ t('editAddress') }}</el-button>
                                            <el-button type="primary" link @click="delivery(item,'add')" v-if="item.status == 2 && item.delivery_type!='store'">{{ t('sendOutGoods') }}</el-button>
                                            <el-button
                                                v-if="item.status == 2 && item.delivery_type === 'store' && ['offline_cash', 'offline_credit'].includes(item.payment_mode)"
                                                type="success"
                                                link
                                                @click="confirmOfflineDelivery(item)"
                                            >确认交付</el-button>
                                            <el-button type="primary" link @click="delivery(item,'edit')" v-if="item.status == 3 && item.delivery_type!='store' && item.delivery_type != 'virtual'">{{ t('修改发货') }}</el-button>
                                            <el-button type="primary" link @click="finish(item)" v-if="item.status == 3">{{ t('confirmTakeDelivery') }}</el-button>
                                            <el-button type="primary" v-if="item.is_refund_show && item.status != 1 && item.status != -1" link @click="refundEvent(item)">{{ t('voluntaryRefund') }}</el-button>
                                            <el-button type="primary" v-if="item.status == -1" link @click="deleteEvent(item)">{{ t('delete') }}</el-button>

                                        </template>
                                    </el-table-column>
                                </el-table>
                                <div v-if="item.shop_remark" class="text-[14px] min-h-[30px] leading-[30px] px-3 bg-[#fff0e5] text-[#ff7f5b]">
                                    <span class="mr-[5px]">{{ t('notes') }}：</span>
                                    <span>{{ item.shop_remark }}</span>
                                </div>
                            </div>
                        </template>
                        <el-empty v-else :image-size="1" :description="t('emptyData')" />
                    </div>
                </div>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="orderTable.page" v-model:page-size="orderTable.limit"
                                   layout="total, sizes, prev, pager, next, jumper" :total="orderTable.total"
                                   @size-change="loadOrderList()" @current-change="loadOrderList" />
                </div>
            </div>
        </el-card>

        <adjust-money ref="orderAdjustMoneyActionDialog" @complete="loadOrderList(getTablePageStorage(orderTable.searchParam).page)" />
        <delivery-action ref="deliveryActionDialog" @complete="loadOrderList(getTablePageStorage(orderTable.searchParam).page)" />
        <order-notes ref="orderNotesDialog" @complete="loadOrderList(getTablePageStorage(orderTable.searchParam).page)" />
        <order-export-select ref="selectExportDialog" @complete="exportEvent" />
        <export-sure ref="exportSureDialog" :show="flag" :type="exportType" :searchParam="orderTable.searchParam" @close="handleClose" />
        <order-edit-address ref="orderEditAddressDialog" @complete="loadOrderList(getTablePageStorage(orderTable.searchParam).page)" />
        <electronic-sheet-print ref="electronicSheetPrintDialog" @complete="electronicSheetPrintComplete" />
        <shop-active-refund ref="shopActiveRefundDialog" @complete="loadOrderList(getTablePageStorage(orderTable.searchParam).page)" />
        <el-dialog
            v-model="offlineDialog.visible"
            :title="offlineDialog.action === 'confirm_paid' ? '确认线下收款' : '确认挂账'"
            width="500px"
            destroy-on-close
        >
            <div v-if="offlineDialog.order" class="offline-order-summary">
                <div>
                    <span class="label">客户</span>
                    <strong>{{ offlineDialog.order.member?.nickname || offlineDialog.order.taker_name || '—' }}</strong>
                    <span class="ml-[8px] text-[13px] text-[#909399]">{{ offlineDialog.order.taker_mobile }}</span>
                </div>
                <div><span class="label">订单</span>{{ offlineDialog.order.order_no }}</div>
                <div>
                    <span class="label">原订单金额</span>
                    <strong class="text-[18px] text-[var(--el-color-primary)]">￥{{ Number(offlineDialog.order.order_money || 0).toFixed(2) }}</strong>
                </div>
            </div>
            <el-alert
                class="mb-[18px]"
                :title="offlineDialog.action === 'confirm_paid' ? '确认后将记录真实到账并进入待交付。' : '确认后将生成 ERP 应收并进入待交付。'"
                type="info"
                :closable="false"
                show-icon
            />
            <el-form label-position="top">
                <el-form-item :label="offlineOrderGoodsCount > 1 ? '一口打包成交价' : '本次实际成交价'" required>
                    <el-input-number
                        v-model="offlineDialog.deal_total"
                        class="!w-full"
                        :min="0.01"
                        :max="99999999.99"
                        :precision="2"
                        :step="10"
                        controls-position="right"
                    />
                    <div class="mt-[6px] text-[12px] text-[#909399]">
                        <template v-if="offlineOrderGoodsCount > 1">
                            共 {{ offlineOrderGoodsCount }} 台设备，系统会按原成交金额比例分摊；每台分摊价将作为后续退款上限。
                        </template>
                        <template v-else>
                            可在收款或挂账前完成议价，修改后的成交价将作为退款上限。
                        </template>
                    </div>
                    <el-tag v-if="offlinePriceChange !== 0" class="mt-[8px]" :type="offlinePriceChange < 0 ? 'success' : 'warning'">
                        {{ offlinePriceChange < 0 ? '优惠' : '加价' }} ￥{{ Math.abs(offlinePriceChange).toFixed(2) }}
                    </el-tag>
                </el-form-item>
                <el-form-item v-if="offlineDialog.action === 'confirm_paid'" label="实际到账账户" required>
                    <el-select v-model="offlineDialog.capital_account_id" class="w-full" placeholder="选择 ERP 资金账户" filterable>
                        <el-option
                            v-for="account in capitalAccounts"
                            :key="account.id"
                            :label="`${account.name} · ${account.type_name}`"
                            :value="account.id"
                        />
                    </el-select>
                    <div v-if="!capitalAccounts.length" class="mt-[6px] text-[12px] text-warning">
                        暂无可用账户，请先在 ERP 资金账户中启用账户。
                    </div>
                </el-form-item>
                <el-form-item v-if="offlineDialog.action === 'confirm_paid'" label="收款凭证" required>
                    <upload-image v-model="offlineDialog.voucher_urls" :limit="6" width="80px" height="80px" image-text="上传凭证" />
                    <div class="mt-[6px] text-[12px] text-[#909399]">支持拍照或上传转账截图，作为本次线下收款的责任留痕。</div>
                </el-form-item>
                <el-form-item label="处理备注">
                    <el-input v-model="offlineDialog.remark" type="textarea" :rows="3" maxlength="255" show-word-limit placeholder="可填写收款方式或沟通结果" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="offlineDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="offlineSubmitting" @click="submitOfflineProcess">
                    {{ offlineDialog.action === 'confirm_paid' ? '确认收款' : '确认挂账' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { computed, reactive, ref } from 'vue'
import { t } from '@/lang'
import {
    getOrderList,
    getOrderStatus,
    orderClose,
    orderFinish,
    getOrderPayType,
    getOrderFrom,
    orderDelete,
    getOfflineCapitalAccounts,
    processOfflineOrder
} from '@/addon/phone_shop/api/order'
import { printTicket } from '@/app/api/printer'
import DeliveryAction from '@/addon/phone_shop/views/order/components/delivery-action.vue'
import OrderNotes from '@/addon/phone_shop/views/order/components/order-notes.vue'
import OrderExportSelect from '@/addon/phone_shop/views/order/components/order-export-select.vue'
import orderEditAddress from '@/addon/phone_shop/views/order/components/order-edit-address.vue'
import AdjustMoney from '@/addon/phone_shop/views/order/components/adjust-money.vue'
import ShopActiveRefund from '@/addon/phone_shop/views/order/components/shop-active-refund.vue'
import electronicSheetPrint from '@/addon/phone_shop/views/order/components/electronic-sheet-print.vue'
import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { useRouter, useRoute } from 'vue-router'
import { cloneDeep } from 'lodash-es'

const route = useRoute()
const router = useRouter()
const pageName = route.meta.title
const isOfflineView = computed(() => route.path.includes('/order/offline'))
const activeName: any = ref(route.query.status || '')

const statusData = ref([])
const payTypeData = ref<any[]>([])
const orderFromData = ref([])

const setFormData = async () => {
    statusData.value = await (await getOrderStatus()).data
    payTypeData.value = await (await getOrderPayType()).data
    orderFromData.value = await (await getOrderFrom()).data
}
setFormData()

const multipleSelection: any = reactive({}) // 选中数据
const multipleTable = reactive<Record<number, any>>({}) // 使用object而不是array儲存表格引用
const isSelectAll = ref(false)

// 保存表格引用
const setTableRef = (el: any, index: number) => {
  if (el) {
    multipleTable[index] = el
  }
}

const selectAllCheck = () => {
    if (!isSelectAll.value) {
        isSelectAll.value = true
        for (let i = 0; i < orderTable.data.length; i++) {
            let isAdd = false
            // 確保multipleTable中有對應索引的元素
            if (multipleTable[i]) {
                for (let j = 0; j < orderTable.data[i].order_goods.length; j++) {
                    multipleTable[i].toggleRowSelection(orderTable.data[i].order_goods[j], true)
                    isAdd = true
                }
                if (isAdd) {
                    multipleSelection['order_' + orderTable.data[i].order_id] = cloneDeep(orderTable.data[i])
                }
            }
        }
    } else {
        isSelectAll.value = false
        for (let v = 0; v < orderTable.data.length; v++) {
            if (multipleTable[v]) {
                multipleTable[v].clearSelection()
                delete multipleSelection['order_' + orderTable.data[v].order_id]
            }
        }
    }
}

// 监听表格复选框
const handleSelectChange = (selection: any, row: any) => {
    // 是否选中
    let isSelected = false
    let item: any = null

    for (let i = 0; i < orderTable.data.length; i++) {
        if (orderTable.data[i].order_id == row.order_id) {
            item = orderTable.data[i]
            break
        }
    }

    for (let i = 0; i < selection.length; i++) {
        if (selection[i].order_id == row.order_id) {
            isSelected = true
            break
        }
    }

    if (isSelected) {
        multipleSelection['order_' + row.order_id] = item
    } else {
        // 未选中，删除当前商品
        delete multipleSelection['order_' + row.order_id]
    }
}

const orderTable: any = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        search_type: 'order_no',
        search_name: '',
        keyword: '',
        pay_type: '',
        order_from: '',
        status: isOfflineView.value ? '' : (route.query.status || ''),
        payment_mode: '',
        offline_workflow: isOfflineView.value ? 1 : 0,
        order_id: route.query.order_id || '',
        create_time: [],
        pay_time: []
    }
})

const searchFormRef = ref<FormInstance>()

// 重置选择状态
const resetSelection = () => {
    isSelectAll.value = false
    for (const key in multipleSelection) {
        delete multipleSelection[key]
    }
    
    // 清除表格的选中状态
    for (let v = 0; v < orderTable.data.length; v++) {
        if (multipleTable[v]) {
            multipleTable[v].clearSelection()
        }
    }
}

/**
 * 获取订单列表
 */
const loadOrderList = (page: number = 1) => {
    orderTable.loading = true
    orderTable.page = page
    
    resetSelection() // 使用重置方法

    getOrderList({
        page: orderTable.page,
        limit: orderTable.limit,
        ...orderTable.searchParam
    }).then(res => {
        orderTable.loading = false
        orderTable.data = res.data.data.map((el: any) => {
            el.isSupportElectronicSheet = false // 是否支持打印电子面单
            el.isSupportPrintTicket = false // 是否支持打印小票

            // 只有待发货、待收货，物流配送的情况下才能打印电子面单
            if (el.delivery_type == 'express' && el.status == 3) {
                el.isSupportElectronicSheet = true
            }

            //  待发货、待收货、已完成状态下可以打印小票
            if (el.delivery_type != 'virtual' && (el.status == 2 || el.status == 3 || el.status == 5)) {
                el.isSupportPrintTicket = true
            }
            el.order_goods.forEach((v: any) => {
                v.rowNum = el.order_goods.length
            })
            return el
        })

        // 处理主力退款按钮是否出现
        orderTable.data.forEach((item: any, index: number, arr: any) => {
            let refundOrderNum = 0
            item.order_goods.forEach((orderItem: any, orderIndex: number) => {
                if (orderItem.is_enable_refund == 1) {
                    refundOrderNum++
                }
            })
            arr[index].is_refund_show = refundOrderNum > 0
        })
        orderTable.total = res.data.total
        setTablePageStorage(orderTable.page, orderTable.limit, orderTable.searchParam)
    }).catch(() => {
        orderTable.loading = false
    })
}

loadOrderList(getTablePageStorage(orderTable.searchParam).page)

const handleClick = (event: any) => {
    orderTable.searchParam.status = event
    isSelectAll.value = false
    for (const key in multipleSelection) {
        delete multipleSelection[key]
    }
    loadOrderList()
}

// 合并表格行
const arraySpanMethod = ({ row, column, rowIndex, columnIndex }: any) => {
    if (rowIndex === 0) {
        if (columnIndex === 0) {
            return [row.rowNum, 1]
        } else if (columnIndex > 3) {
            return [row.rowNum, 1]
        } else {
            return [1, 1]
        }
    } else {
        if (columnIndex === 0) {
            return [0, 0]
        } else if (columnIndex > 3) {
            return [0, 0]
        } else {
            return [1, 1]
        }
    }
}

/**
 * 订单导出
 */
const exportSureDialog = ref(null)
const exportType = ref('')
const flag = ref(false)
const handleClose = (val: any) => {
    flag.value = val
}
const exportEvent = (data: any) => {
    exportType.value = data
    flag.value = true
}

const selectExportDialog: Record<string, any> | null = ref(null)

/**
 * 订单导出类型选择
 */
const exportSelectEvent = () => {
    selectExportDialog.value.showDialog = true
}

// 订单详情
const detailEvent = (data: any) => {
    router.push('/phone_shop/order/detail?order_id=' + data.order_id)
}

const capitalAccounts = ref<any[]>([])
const offlineSubmitting = ref(false)
const offlineDialog = reactive<any>({
    visible: false,
    action: 'confirm_paid',
    capital_account_id: 0,
    deal_total: 0,
    remark: '',
    voucher_urls: [],
    order: null
})
const offlineOrderGoodsCount = computed(() => (offlineDialog.order?.order_goods || [])
    .filter((item: any) => Number(item.is_gift || 0) !== 1)
    .reduce((total: number, item: any) => total + Number(item.num || 1), 0))
const offlinePriceChange = computed(() => Number(offlineDialog.deal_total || 0) - Number(offlineDialog.order?.order_money || 0))

const openOfflineProcess = async(order: any, action: 'confirm_paid' | 'confirm_credit') => {
    offlineDialog.order = order
    offlineDialog.action = action
    offlineDialog.capital_account_id = 0
    offlineDialog.deal_total = Number(order.order_money || 0)
    offlineDialog.remark = ''
    offlineDialog.voucher_urls = []
    if (action === 'confirm_paid') {
        const { data } = await getOfflineCapitalAccounts()
        capitalAccounts.value = Array.isArray(data) ? data : []
        const defaultAccount = capitalAccounts.value.find((item: any) => Number(item.is_default) === 1)
        offlineDialog.capital_account_id = Number(defaultAccount?.id || capitalAccounts.value[0]?.id || 0)
    }
    offlineDialog.visible = true
}

const submitOfflineProcess = async() => {
    if (!offlineDialog.order) return
    if (Number(offlineDialog.deal_total) <= 0) {
        ElMessage.warning('请输入有效的实际成交价')
        return
    }
    if (offlineDialog.action === 'confirm_paid' && !offlineDialog.capital_account_id) {
        ElMessage.warning('请选择实际到账的 ERP 资金账户')
        return
    }
    if (offlineDialog.action === 'confirm_paid' && !offlineDialog.voucher_urls.length) {
        ElMessage.warning('请上传至少一张收款凭证')
        return
    }
    offlineSubmitting.value = true
    try {
        await processOfflineOrder({
            order_id: offlineDialog.order.order_id,
            action: offlineDialog.action,
            capital_account_id: offlineDialog.capital_account_id,
            deal_total: offlineDialog.deal_total,
            remark: offlineDialog.remark,
            voucher_urls: offlineDialog.voucher_urls
        })
        offlineDialog.visible = false
        loadOrderList(orderTable.page)
    } finally {
        offlineSubmitting.value = false
    }
}

const offlineRecordTag = (status: string) => {
    const map: Record<string, { label: string, type: '' | 'success' | 'warning' | 'info' | 'danger' }> = {
        pending: { label: '待联系', type: 'warning' },
        contacted: { label: '已联系待到店', type: 'info' },
        paid: { label: '已收款', type: 'success' },
        credit: { label: '已挂账', type: 'warning' },
        delivered: { label: '已交付', type: 'success' },
        closed: { label: '已关闭', type: 'info' }
    }
    return map[status] || { label: '待处理', type: 'warning' as const }
}

const formatTimestamp = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(Number(timestamp) * 1000)
    const pad = (value: number) => String(value).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const markOfflineContacted = async(order: any) => {
    const confirmed = await ElMessageBox.confirm(
        `确认已联系 ${order.taker_name || order.member?.nickname || '该客户'}（${order.taker_mobile || '未留手机号'}）？`,
        '确认联系结果',
        { type: 'info', confirmButtonText: '已联系', cancelButtonText: '取消' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    await processOfflineOrder({ order_id: order.order_id, action: 'contacted', remark: '已主动联系客户并确认到店安排' })
    loadOrderList(orderTable.page)
}

const closeOfflineUnreachable = async(order: any) => {
    const result = await ElMessageBox.prompt(
        '请填写无法联系、客户取消或其他关闭原因。关闭后设备会解除锁定。',
        '关闭线下订单',
        {
            type: 'warning',
            inputType: 'textarea',
            inputPlaceholder: '例如：连续联系三次无人接听',
            inputValidator: (text: string) => text.trim().length > 0 || '请填写关闭原因',
            confirmButtonText: '确认关闭',
            cancelButtonText: '取消'
        }
    ).catch(() => null)
    if (!result) return
    await processOfflineOrder({ order_id: order.order_id, action: 'close_unreachable', close_reason: result.value })
    loadOrderList(orderTable.page)
}

const confirmOfflineDelivery = async(order: any) => {
    const confirmed = await ElMessageBox.confirm(
        `确认已核对客户，并将订单「${order.order_no}」中的设备当面交付？确认后订单将完成。`,
        '确认到店交付',
        { type: 'warning', confirmButtonText: '确认已交付', cancelButtonText: '取消' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    await processOfflineOrder({
        order_id: order.order_id,
        action: 'confirm_delivery',
        remark: '已当面核对客户并完成设备交付'
    })
    loadOrderList(orderTable.page)
}

const memberEvent = (id: number) => {
    const routeUrl = router.resolve({
        path: '/member/detail',
        query: { id }
    })
    window.open(routeUrl.href, '_blank')
}

const close = (data: any) => {
    ElMessageBox.confirm(t('orderCloseTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        orderClose(data.order_id).then(() => {
            loadOrderList(getTablePageStorage(orderTable.searchParam).page)
        })
    })
}

// 订单调整价格
const orderAdjustMoneyActionDialog: Record<string, any> | null = ref(null)
const orderAdjustMoney = (data: any) => {
    orderAdjustMoneyActionDialog.value.setFormData(data)
    orderAdjustMoneyActionDialog.value.showDialog = true
}

const deliveryActionDialog: Record<string, any> | null = ref(null)
/**
 * 发货
 */
const delivery = (data: any, type: string) => {
    deliveryActionDialog.value.setFormData(data, type)
    deliveryActionDialog.value.showDialog = true
}

const orderNotesDialog: Record<string, any> | null = ref(null)
/**
 * 设置备注
 */
const setNotes = (data: any) => {
    orderNotesDialog.value.setFormData(data)
    orderNotesDialog.value.showDialog = true
}

// 订单完成
const finish = (data: any) => {
    ElMessageBox.confirm(t('orderFinishTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        orderFinish(data.order_id).then(() => {
            loadOrderList(getTablePageStorage(orderTable.searchParam).page)
        })
    })
}

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadOrderList()
}

/**
 * 修改地址
 */
const orderEditAddressDialog: Record<string, any> | null = ref(null)
const orderEditAddressFn = async (data: any) => {
    orderEditAddressDialog.value.showDialog = true
    orderEditAddressDialog.value.setFormData(data)
}

// 打印电子面单
const electronicSheetPrintDialog: Record<string, any> | null = ref(null)

// 单个订单打印电子面单
const openElectronicSheetPrintDialog = (data: any) => {
    const formData = cloneDeep(data)
    formData.print_type = 'single'
    electronicSheetPrintDialog.value.setFormData(formData)
    electronicSheetPrintDialog.value.showDialog = true
}

// 批量打印电子面单
const batchPrintElectronicSheet = () => {
    let noSupportCount = 0
    const orderIds: number[] = []
    for (const key in multipleSelection) {
        if (multipleSelection[key].isSupportElectronicSheet) {
            orderIds.push(multipleSelection[key].order_id)
        } else {
            noSupportCount++
        }
    }

    if (noSupportCount && orderIds.length == 0) {
        ElMessage({
            type: 'warning',
            message: `${t('notSupportPrintElectronicSheetTips')}`
        })
        return
    }

    if (orderIds.length == 0) {
        ElMessage({
            type: 'warning',
            message: `${t('batchEmptySelectedOrderTips')}`
        })
        return
    }

    electronicSheetPrintDialog.value.setFormData({
        order_id: orderIds.toString(),
        print_type: 'multiple'
    })
    electronicSheetPrintDialog.value.showDialog = true
}

// 电子面单完成事件
const electronicSheetPrintComplete = () => {
    isSelectAll.value = false
    for (let v = 0; v < orderTable.data.length; v++) {
        if (multipleTable[v]) {
            multipleTable[v].clearSelection()
            delete multipleSelection['order_' + orderTable.data[v].order_id]
        }
    }
}

const repeat = ref(false)

/**
 * 打印小票
 */
const printTicketEvent = (data: any) => {
    if (repeat.value) return
    repeat.value = true

    printTicket({
        type: 'shopGoodsOrder', // 小票模板类型
        trigger: 'manual', // 触发时机：手动触发
        // 业务参数，根据自身业务传值
        business: {
            order_id: data.order_id
        }
    }).then((res: any) => {
        repeat.value = false
    }).catch(() => {
        repeat.value = false
    })
}

/**
 * 商家主动退款
 */
const shopActiveRefundDialog: Record<string, any> | null = ref(null)
const refundEvent = (data: any) => {
    shopActiveRefundDialog.value.setFormData(data)
    shopActiveRefundDialog.value.showDialog = true
}

// 商品预览
const previewEvent = (data: any) => {
    const url = router.resolve({
        path: '/preview/wap',
        query: {
            page: `/addon/phone_shop/pages/goods/detail?goods_id=${data.goods_id}`
        }
    })
    window.open(url.href)
}

// 删除
const deleteEvent = (data: any) => {
    ElMessageBox.confirm(t('deleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        orderDelete({ order_ids: [data.order_id] }).then(() => {
            loadOrderList(getTablePageStorage(orderTable.searchParam).page)
        }).catch(() => {
        })
    })
}
// 批量删除
const batchDeleteFn = () => {
    const orderIds: number[] = []
    for (const key in multipleSelection) {
        orderIds.push(multipleSelection[key].order_id)
    }
    if (orderIds.length == 0) {
        ElMessage({
            type: 'warning',
            message: `${t('batchEmptySelectedOrderTips')}`
        })
        return
    }

    ElMessageBox.confirm(t('batchDeleteTips'), t('warning'), {
        confirmButtonText: t('confirm'),
        cancelButtonText: t('cancel'),
        type: 'warning'
    }).then(() => {
        orderDelete({ order_ids: orderIds }).then(() => {
            resetSelection() // 重置选择状态
            loadOrderList(getTablePageStorage(orderTable.searchParam).page)
        }).catch(() => {
        })
    })
}

const toDelivery = (data: any) => {
    const url = router.resolve({
        path: '/phone_shop/delivery/local/record',
        query: {
            trade_no: data.order_no
        }
    })
    window.open(url.href)
}
</script>

<style lang="scss" scoped>
.table-top :deep(.el-table__body-wrapper) {
    display: none;
}

.input-item {
    width: 150px !important;
}

:deep(.el-table) {
    --el-table-row-hover-bg-color: var(--el-transfer-border-color);
}

/* 多行超出隐藏 */
.multi-hidden {
    word-break: break-all;
    text-overflow: ellipsis;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.offline-order-summary {
    display: grid;
    gap: 10px;
    margin-bottom: 16px;
    padding: 14px 16px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 10px;
    background: var(--el-fill-color-light);

    .label {
        display: inline-block;
        width: 52px;
        color: var(--el-text-color-secondary);
    }
}
</style>
