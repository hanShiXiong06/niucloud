<template>
  <div class="main-container">
    <el-card class="box-card !border-none" shadow="never">
      <el-button type="primary" @click="addOrder()">新增订单</el-button>
      <el-button type="info" :loading="uploadLoading" @click="uploadWeapEvent()">上传发货订单</el-button>
      <span class="text-gray-500 text-sm ml-2">此处是防止部分场景自动上传失败补充操作,只能操作15天内的订单</span>
      <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
        <el-form :inline="true" :model="orderTable.searchParam" ref="searchFormRef">
          <el-form-item label="会员" prop="member_id">
            <el-select class="input-width" v-model="orderTable.searchParam.member_id" clearable
              :placeholder="t('memberIdPlaceholder')">
              <div class="mt-2 mb-2 ml-4">
                <el-input @change="change" v-model="keyword" style="width: 200px" placeholder="搜索会员支持昵称/会员名">
                  <template #append>搜索 </template></el-input>
              </div>
              <el-option label="请选择" value=""></el-option>
              <el-option v-for="(item, index) in memberIdList" :key="index" :label="item['nickname']"
                :value="item['member_id']" />
            </el-select>
          </el-form-item>

          <el-form-item :label="t('orderFrom')" prop="order_from">
            <el-select v-model="orderTable.searchParam.order_from" clearable class="input-item">
              <el-option v-for="(item, index) in orderFromData" :key="index" :label="item" :value="index"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="支付单号" prop="out_trade_no">
            <el-input v-model="orderTable.searchParam.out_trade_no" placeholder="请输入支付单号" />
          </el-form-item>

          <el-form-item label="发单状态" prop="is_send">
            <el-select class="w-[280px]" v-model="orderTable.searchParam.is_send" clearable
              :placeholder="t('isSendPlaceholder')">
              <el-option label="全部" value=""></el-option>
              <el-option v-for="(item, index) in is_sendList" :key="index" :label="item.name" :value="item.value" />
            </el-select>
          </el-form-item>

          <el-form-item :label="t('orderStatus')" prop="order_status">
            <el-select class="w-[280px]" v-model="orderTable.searchParam.order_status" clearable
              :placeholder="t('orderStatusPlaceholder')">
              <el-option label="全部" value=""></el-option>
              <el-option v-for="(item, index) in order_statusList" :key="index" :label="item.name"
                :value="item.value" />
            </el-select>
          </el-form-item>

          <el-form-item :label="t('refundStatus')" prop="refund_status">
            <el-select class="w-[280px]" v-model="orderTable.searchParam.refund_status" clearable
              :placeholder="t('refundStatusPlaceholder')">
              <el-option label="全部" value=""></el-option>
              <el-option v-for="(item, index) in refund_statusList" :key="index" :label="item.name"
                :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item :label="t('remark')" prop="remark">
            <el-input v-model="orderTable.searchParam.remark" :placeholder="t('remarkPlaceholder')" />
          </el-form-item>
          <el-form-item label="关键字搜索" prop="remark">
            <el-input style="width: 280px" v-model="orderTable.searchParam.keyword" placeholder="订单号/快递号/手机号/姓名"
              clearable />
          </el-form-item>
          <el-form-item :label="t('createTime')" prop="create_time">
            <el-date-picker v-model="orderTable.searchParam.create_time" type="datetimerange"
              format="YYYY-MM-DD hh:mm:ss" :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="loadOrderList()">{{
              t("search")
            }}</el-button>
            <el-button @click="resetForm(searchFormRef)">{{
              t("reset")
            }}</el-button>
            <el-button type="primary" @click="exportEvent">{{ t('export') }}</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <div class="mt-[10px]">
        <el-table :data="orderTable.data" size="large" v-loading="orderTable.loading">
          <template #empty>
            <span>{{ !orderTable.loading ? t("emptyData") : "" }}</span>
          </template>
          <el-table-column prop="order_from" label="渠道" min-width="80" :show-overflow-tooltip="true">
            <template #default="{ row }">
              <div class="flex">
                <el-tag type="info">{{ row.order_from }}</el-tag>
              </div>
              <div v-if="!row.platform_name == ''" class="flex mt-1">
                <el-tag>{{ row.platform_name }}</el-tag>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="order_id" label="基本信息" min-width="200" :show-overflow-tooltip="true">
            <template #default="{ row }">
              <div class="p-1">下单人:{{ row.member_id_name }}</div>
              <div class="p-1">订单号:{{ row.order_id }}</div>
              <div class="p-1">支付单号:{{ row.out_trade_no }}</div>

            </template>
          </el-table-column>
          <el-table-column label="路线" min-width="120" align="center" :show-overflow-tooltip="true">
            <template #default="{ row }">
              <div>{{ row.start_address.address }}</div>
              <div>
                <el-icon>
                  <Bottom />
                </el-icon>
              </div>
              <div class="font-bold">{{ row.end_address.address }}</div>
            </template>
          </el-table-column>

          <el-table-column prop="order_money" label="常规信息" min-width="120" :show-overflow-tooltip="true">
            <template #default="{ row }">
              <el-tag class="p-1" v-if="row.delivery_name">{{ row.delivery_name }}</el-tag>
              <div class="p-1">
                <view class="font-bold">重量:{{ row.orderInfo.weight }}kg</view>
                <view class="font-bold ml-2">实付:{{ (row.order_money - row.order_discount_money).toFixed(2) }}</view>
              </div>

              <div class="p-1">
                <view class="font-bold">总金额:{{ row.order_money }}</view>
                <view v-if="row.order_discount_money > 0" class="ml-2 text-[#f51a00]">优惠:{{ row.order_discount_money }}
                </view>
              </div>
              <div class="p-1" v-if="row.total_fee > 0">
                <view class="text-[#f51a00]">三方扣费:{{ row.total_fee }}
                </view>
              </div>
            </template>
          </el-table-column>
          <el-table-column v-if="fenxiao && fenxiao.status == false" prop="order_money" label="订单佣金" min-width="120"
            :show-overflow-tooltip="true">
            <template #default="{ row }">
              <template v-if="row.fenxiao_order != []">
                <div v-if="row.fenxiao_order.first_commission > 0" class="">
                  一级佣金: {{ row.fenxiao_order.first_commission }}
                </div>
                <div v-if="row.fenxiao_order.two_commission > 0" class="">
                  二级佣金: {{ row.fenxiao_order.two_commission }}
                </div>
                <div v-if="row.fenxiao_order.status" class="flex items-center">
                  佣金状态
                  <el-tag :type="row.fenxiao_order.status.type ? row.fenxiao_order.status.type : 'info'" class="ml-1">
                    {{ row.fenxiao_order.status.name ? row.fenxiao_order.status.name : '未知' }}
                  </el-tag>
                </div>
              </template>
            </template>
          </el-table-column>
          <!-- <el-table-column
            prop="order_discount_money"
            label="优惠金额"
            min-width="120"
            :show-overflow-tooltip="true"
          /> -->

          <el-table-column label="订单状态" min-width="120" align="center" :show-overflow-tooltip="true">
            <template #default="{ row }">
              <div v-for="(item, index) in is_sendList">
                <div v-if="item.value == row.is_send">
                  发单状态
                  <el-tag class="ml-1">{{
                    item.name
                  }}</el-tag>
                </div>
              </div>
              <div v-for="(item, index) in order_statusList" class="mt-1">
                <div>
                  <div v-if="
                    item.value == row.order_status && row.order_status != -1
                  ">订单状态
                    <el-tag class="ml-1">{{ item.name }}</el-tag>
                  </div>
                  <div v-if="
                    item.value == row.order_status && row.order_status == -1
                  ">
                    订单状态
                    <el-tag type="warning" class="ml-1">{{ item.name }}</el-tag>
                  </div>

                </div>
              </div>
              <div v-if="row.addorderInfo && row.addorderInfo.order_status == 0">
                补差状态
                <el-tag class="mt-1 ml-1" type="error">
                  需补差</el-tag>
              </div>
              <div v-for="(item, index) in refund_statusList" class="mt-1">
                <div v-if="item.value == row.refund_status">
                  退款状态
                  <el-tag class="ml-1">{{
                    item.name
                  }}</el-tag>
                </div>

              </div>
            </template>
          </el-table-column>

          <el-table-column label="关闭原因" prop="close_reason" min-width="120" align="center"
            :show-overflow-tooltip="true">
            <template #default="{ row }">
              <div class="text-red">{{ row.close_reason }}</div>
            </template>
          </el-table-column>
          <el-table-column prop="remark" :label="t('remark')" min-width="120" :show-overflow-tooltip="true">
            <template #default="{ row }">
              <div>{{ row.remark }}</div>
              <el-icon @click="showRemarkEvent(row)">
                <EditPen />
              </el-icon>
            </template>
          </el-table-column>
          <el-table-column prop="create_time" label="下单时间" min-width="120" :show-overflow-tooltip="true">

          </el-table-column>
          <el-table-column :label="t('operation')" fixed="right" min-width="120">
            <template #default="{ row }">
              <el-button type="primary" link @click="editEvent(row)">详情</el-button>
              <el-button type="primary" v-if="row.order_status == -1 || row.order_status == 0" link
                @click="deleteEvent(row.id)">{{ t("delete") }}</el-button>
              <el-button type="primary" v-if="row.order_status == 1 && row.is_send == 0" link
                @click="sendEvent(row.order_id)">发单</el-button>
              <el-button type="primary" v-if="row.order_status == 1" link @click="cancelEvent(row.id)">取消</el-button>
              <el-button type="primary"
                v-if="row.fenxiao_order != [] && row.order_status == 10 && row.fenxiao_order.status && row.fenxiao_order.status.status == 0 && fenxiao && fenxiao.status == false"
                link @click="commissionEvent(row.id)">佣金结算</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-[16px] flex justify-end">
          <el-pagination v-model:current-page="orderTable.page" v-model:page-size="orderTable.limit"
            layout="total, sizes, prev, pager, next, jumper" :total="orderTable.total" @size-change="loadOrderList()"
            @current-change="loadOrderList" />
        </div>
      </div>

      <edit ref="editOrderDialog" @complete="loadOrderList" />
    </el-card>
  </div>
  <el-dialog v-model="showWap" title="使用移动端账号登录" width="420" height="740" align-center>
    <div class="mt-[-24px]">
      <button @click="goBack" class="mr-2">返回</button>
      <button @click="refreshPage">刷新</button>
    </div>

    <div class="mt-2">
      <iframe ref="myIframeRef" :src="link" width="375" height="667"></iframe>
    </div>
  </el-dialog>
  <el-dialog v-model="showRemark" title="订单备注" align-center width="300px">
    <div class="">
      <el-input style="width: 200px" v-model="remark" />
    </div>
    <div class="mt-[24px]">
      <el-button type="primary" @click="remarkOrderEvent()" class="mr-2">确认</el-button>
    </div>
  </el-dialog>
    <export-sure ref="exportSureDialog" :show="flag" type="tk_jhkd_order"
        :searchParam="{ ...orderTable, data: undefined }" @close="handleClose" />
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from "vue";
import { t } from "@/lang";
import { useDictionary } from "@/app/api/dict";
import {
  getOrderList,
  deleteOrder,
  getWithMemberList,
  getOrderPayType,
  getOrderFrom,
  sendOrder,
  cancelOrder,
  getLink,
  remarkOrder, commissionOrder, uploadWeapOrder
} from "@/addon/tk_jhkd/api/order";
import { img } from "@/utils/common";
import { ElMessageBox, ElTag, FormInstance, ElMessage } from "element-plus";
import Edit from "@/addon/tk_jhkd/views/order/components/order-edit.vue";
import { useRoute } from "vue-router";
import { checkFenxiao } from "@/addon/tk_jhkd/api/tkjhkd";
/**
 * 导出
 */
const exportSureDialog = ref(null)
const flag = ref(false)
const handleClose = (val) => {
    flag.value = val
}
const exportEvent = () => {
    flag.value = true
}
const uploadLoading = ref(false);
const uploadWeapEvent = () => {
  uploadLoading.value = true;
  uploadWeapOrder().then((res) => {
    ElMessage.success('操作成功');
    uploadLoading.value = false;
  }).catch(() => {
    uploadLoading.value = false;
    ElMessage.error('上传失败');
  });
}
const fenxiao = ref()
checkFenxiao().then((res) => {
  fenxiao.value = res.data
})
const myIframeRef = ref(null);
const rowData = ref();
const showRemarkEvent = (row) => {
  rowData.value = row;
  remark.value = row.remark;
  showRemark.value = true;
};

const showRemark = ref(false);
const remark = ref("");
const remarkOrderEvent = async () => {
  await remarkOrder({
    id: rowData.value.id,
    remark: remark.value,
  });
  showRemark.value = false;
  loadOrderList();
};
const goBack = () => {
  if (myIframeRef.value.contentWindow) {
    myIframeRef.value.contentWindow.history.back();
  }
};

const refreshPage = () => {
  if (myIframeRef.value.contentWindow) {
    myIframeRef.value.contentWindow.location.reload();
  }
};
const showWap = ref(false);
const addOrder = () => {
  showWap.value = true;
};
const link = ref();
const getLinkData = async () => {
  link.value = await (await getLink()).msg;
};
getLinkData();
const route = useRoute();
const pageName = route.meta.title;
const payTypeData = ref<any[]>([]);
const orderFromData = ref([]);
const sendEvent = async (order_id) => {
  await sendOrder(order_id);
  loadOrderList();
};
const commissionEvent = async (id) => {
  await commissionOrder(id);
  loadOrderList();
};
const cancelEvent = async (id) => {
  await cancelOrder({ id: id });
  loadOrderList();
};
const setFormData = async () => {
  // statusData.value = await (await getOrderStatus()).data
  payTypeData.value = await (await getOrderPayType()).data;
  orderFromData.value = await (await getOrderFrom()).data;
};
setFormData();
let orderTable = reactive({
  page: 1,
  limit: 10,
  total: 0,
  loading: true,
  data: [],
  searchParam: {
    member_id: "",
    order_from: "",
    order_id: "",
    out_trade_no: "",
    is_send: "",
    order_status: "",
    refund_status: "",
    remark: "",
    create_time: [],
    keyword: '',
  },
});

const searchFormRef = ref<FormInstance>();

// 选中数据
const selectData = ref<any[]>([]);

// 字典数据
const is_sendList = ref([] as any[]);
const is_sendDictList = async () => {
  is_sendList.value = await (
    await useDictionary("jhkd_is_send")
  ).data.dictionary;
};
is_sendDictList();
const is_pickList = ref([] as any[]);
const is_pickDictList = async () => {
  is_pickList.value = await (
    await useDictionary("jhkd_is_pick")
  ).data.dictionary;
};
is_pickDictList();
const order_statusList = ref([] as any[]);
const order_statusDictList = async () => {
  order_statusList.value = await (
    await useDictionary("jhkd_order_status")
  ).data.dictionary;
};
order_statusDictList();
const refund_statusList = ref([] as any[]);
const refund_statusDictList = async () => {
  refund_statusList.value = await (
    await useDictionary("jhkd_refund_status")
  ).data.dictionary;
};
refund_statusDictList();

/**
 * 获取订单列列表
 */
const loadOrderList = (page: number = 1) => {
  orderTable.loading = true;
  orderTable.page = page;

  getOrderList({
    page: orderTable.page,
    limit: orderTable.limit,
    ...orderTable.searchParam,
  })
    .then((res) => {
      orderTable.loading = false;
      orderTable.data = res.data.data;
      orderTable.total = res.data.total;
    })
    .catch(() => {
      orderTable.loading = false;
    });
};
loadOrderList();

const editOrderDialog: Record<string, any> | null = ref(null);

/**
 * 添加订单列
 */
const addEvent = () => {
  editOrderDialog.value.setFormData();
  editOrderDialog.value.showDialog = true;
};

/**
 * 编辑订单列
 * @param data
 */
const editEvent = (data: any) => {
  editOrderDialog.value.setFormData(data);
  editOrderDialog.value.showDialog = true;
};

/**
 * 删除订单列
 */
const deleteEvent = (id: number) => {
  ElMessageBox.confirm(t("orderDeleteTips"), t("warning"), {
    confirmButtonText: t("confirm"),
    cancelButtonText: t("cancel"),
    type: "warning",
  }).then(() => {
    deleteOrder(id)
      .then(() => {
        loadOrderList();
      })
      .catch(() => { });
  });
};

const change = () => {
  setMemberIdList();
};
const keyword = ref();
const memberIdList = ref([]);
const setMemberIdList = async () => {
  memberIdList.value = await (
    await getWithMemberList({ keyword: keyword.value })
  ).data.data;
};
setMemberIdList();

const resetForm = (formEl: FormInstance | undefined) => {
  if (!formEl) return;
  formEl.resetFields();
  loadOrderList();
};
</script>

<style lang="scss" scoped>
/* 多行超出隐藏 */
.multi-hidden {
  word-break: break-all;
  text-overflow: ellipsis;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
</style>
