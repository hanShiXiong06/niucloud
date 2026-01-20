<template>
  <div class="main-container">
    <el-card class="box-card !border-none" shadow="never">
      <div class="flex justify-between items-center">
        <span class="text-[20px]">{{ pageName }}</span>
      </div>

      <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
        <el-form :inline="true" :model="orderAddTable.searchParam" ref="searchFormRef">
          <el-form-item label="会员" prop="member_id">
            <el-select class="input-width" v-model="orderAddTable.searchParam.member_id" clearable
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

          <el-form-item label="订单号" prop="order_id">
            <el-input v-model="orderAddTable.searchParam.order_id" :placeholder="t('orderIdPlaceholder')" />
          </el-form-item>

          <el-form-item :label="t('orderStatus')" prop="order_status">
            <el-select class="w-[280px]" v-model="orderAddTable.searchParam.order_status" clearable
              :placeholder="t('orderStatusPlaceholder')">
              <el-option label="未支付" value="0"></el-option>
              <el-option label="已支付" value="1"></el-option>
            </el-select>
          </el-form-item>

          <el-form-item :label="t('createTime')" prop="create_time">
            <el-date-picker v-model="orderAddTable.searchParam.create_time" type="datetimerange"
              format="YYYY-MM-DD hh:mm:ss" :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="loadOrderAddList()">{{
              t("search")
            }}</el-button>
            <el-button @click="resetForm(searchFormRef)">{{
              t("reset")
            }}</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <div class="mt-[10px]">
        <el-table :data="orderAddTable.data" size="large" v-loading="orderAddTable.loading">
          <template #empty>
            <span>{{ !orderAddTable.loading ? t("emptyData") : "" }}</span>
          </template>
          <el-table-column prop="member_id_name" label="会员昵称" min-width="120" :show-overflow-tooltip="true" />

          <el-table-column prop="order_id" label="订单号" min-width="120" :show-overflow-tooltip="true" />
          <el-table-column prop="order_money" label="订单金额" min-width="120" :show-overflow-tooltip="true" />

          <el-table-column :label="t('orderStatus')" min-width="180" align="center" :show-overflow-tooltip="true">
            <template #default="{ row }">
              <el-tag v-if="row.order_status == 0">未支付</el-tag>
              <el-tag v-if="row.order_status == 1">已支付</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="notice_num" label="通知次数" min-width="120" :show-overflow-tooltip="true" />
          <el-table-column prop="remark" label="备注" min-width="120" :show-overflow-tooltip="true">
            <template #default="{ row }">
              <div>{{ row.remark }}</div>
              <el-icon @click="showRemarkEvent(row)">
                <EditPen />
              </el-icon>
            </template>
          </el-table-column>
          <el-table-column :label="t('createTime')" min-width="180" align="center" :show-overflow-tooltip="true">
            <template #default="{ row }">
              {{ row.create_time || "" }}
            </template>
          </el-table-column>
          <el-table-column :label="t('operation')" fixed="right" min-width="220">
            <template #default="{ row }">
              <el-button type="primary" link @click="openEditOrderMoney(row)">修改金额</el-button>
              <el-button type="primary" link @click="sendNotice(row.order_id)">发送通知</el-button>
              <el-button type="primary" link @click="editEvent(row)">订单信息</el-button>
              <el-button type="primary" link @click="deleteEvent(row.id)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-[16px] flex justify-end">
          <el-pagination v-model:current-page="orderAddTable.page" v-model:page-size="orderAddTable.limit"
            layout="total, sizes, prev, pager, next, jumper" :total="orderAddTable.total"
            @size-change="loadOrderAddList()" @current-change="loadOrderAddList" />
        </div>
      </div>

      <edit ref="editOrderAddDialog" @complete="loadOrderAddList" />
    </el-card>
  </div>
  <el-dialog v-model="showRemark" title="补差订单备注" align-center width="300px">
    <div class="">
      <el-input style="width: 200px" v-model="remark" />
    </div>
    <div class="mt-[24px]">
      <el-button type="primary" @click="remarkOrderEvent()" class="mr-2">确认</el-button>
    </div>
  </el-dialog>
  <el-dialog v-model="showmoney" title="修改金额" align-center width="300px">
    <div class="">
      <el-input style="width: 200px" v-model="order_money" />
    </div>
    <div class="mt-[24px]">
      <el-button type="primary" @click="editOrderMoneyFn()" class="mr-2">确认</el-button>
    </div>
  </el-dialog>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from "vue";
import { t } from "@/lang";
import { useDictionary } from "@/app/api/dict";
import {
  getOrderAddList,
  deleteOrderAdd,
  getWithMemberList,
  sendNotice,
  remarkAddOrder, editOrderMoney
} from "@/addon/tk_jhkd/api/order";
import { img } from "@/utils/common";
import { ElMessageBox, FormInstance } from "element-plus";
import Edit from "@/addon/tk_jhkd/views/order/components/orderadd-edit.vue";
import { useRoute } from "vue-router";
const route = useRoute();
const pageName = route.meta.title;
const rowData = ref();
const showRemarkEvent = (row) => {
  rowData.value = row;
  remark.value = row.remark;
  showRemark.value = true;
};
const showmoney = ref(false)
const order_money = ref(0)
const openEditOrderMoney = (item) => {
  rowData.value = item;
  order_money.value = item.order_money
  showmoney.value = true
}
const editOrderMoneyFn = async () => {
  await editOrderMoney({
    id: rowData.value.id,
    order_money: order_money.value
  })
  showmoney.value = false
  loadOrderAddList()
}
const showRemark = ref(false);
const remark = ref("");
const remarkOrderEvent = async () => {
  await remarkAddOrder({
    id: rowData.value.id,
    remark: remark.value,
  });
  showRemark.value = false;
  loadOrderAddList();
};
let orderAddTable = reactive({
  page: 1,
  limit: 10,
  total: 0,
  loading: true,
  data: [],
  searchParam: {
    member_id: "",
    order_id: "",
    order_status: "",
    create_time: [],
  },
});

const searchFormRef = ref<FormInstance>();

// 选中数据
const selectData = ref<any[]>([]);

// 字典数据
const order_statusList = ref([] as any[]);
const order_statusDictList = async () => {
  order_statusList.value = await (
    await useDictionary("jhkd_order_status")
  ).data.dictionary;
};
order_statusDictList();

/**
 * 获取订单列列表
 */
const loadOrderAddList = (page: number = 1) => {
  orderAddTable.loading = true;
  orderAddTable.page = page;

  getOrderAddList({
    page: orderAddTable.page,
    limit: orderAddTable.limit,
    ...orderAddTable.searchParam,
  })
    .then((res) => {
      orderAddTable.loading = false;
      orderAddTable.data = res.data.data;
      orderAddTable.total = res.data.total;
    })
    .catch(() => {
      orderAddTable.loading = false;
    });
};
loadOrderAddList();

const editOrderAddDialog: Record<string, any> | null = ref(null);

/**
 * 添加订单列
 */
const addEvent = () => {
  editOrderAddDialog.value.setFormData();
  editOrderAddDialog.value.showDialog = true;
};

/**
 * 编辑订单列
 * @param data
 */
const editEvent = (data: any) => {
  editOrderAddDialog.value.setFormData(data);
  editOrderAddDialog.value.showDialog = true;
};

/**
 * 删除订单列
 */
const deleteEvent = (id: number) => {
  ElMessageBox.confirm(t("orderAddDeleteTips"), t("warning"), {
    confirmButtonText: t("confirm"),
    cancelButtonText: t("cancel"),
    type: "warning",
  }).then(() => {
    deleteOrderAdd(id)
      .then(() => {
        loadOrderAddList();
      })
      .catch(() => { });
  });
};

const change = () => {
  setMemberIdList();
  loadOrderAddList();
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
  loadOrderAddList();
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
