<template>
  <div class="main-container">
    <el-card class="box-card !border-none" shadow="never">
      <div class="flex justify-between items-center">
        <span class="text-lg">{{ pageName }}</span>
        <el-button type="primary" @click="addEvent">
          {{ t("addBusinessOrder") }}
        </el-button>
      </div>

      <el-card
        class="box-card !border-none my-[10px] table-search-wrap"
        shadow="never"
      >
        <el-form
          :inline="true"
          :model="businessOrderTable.searchParam"
          ref="searchFormRef"
        >
          <el-form-item :label="t('siteId')" prop="site_id">
            <el-input
              v-model="businessOrderTable.searchParam.site_id"
              :placeholder="t('siteIdPlaceholder')"
            />
          </el-form-item>

          <el-form-item label="会员" prop="member_id">
            <el-select
              class="input-width"
              v-model="businessOrderTable.searchParam.member_id"
              clearable
              :placeholder="t('memberIdPlaceholder')"
            >
              <div class="mt-2 mb-2 ml-4">
                <el-input
                  @change="change"
                  v-model="keyword"
                  style="width: 200px"
                  placeholder="搜索会员支持昵称/会员名"
                >
                  <template #append>搜索 </template></el-input
                >
              </div>
              <el-option label="请选择" value=""></el-option>
              <el-option
                v-for="(item, index) in memberIdList"
                :key="index"
                :label="item['nickname']"
                :value="item['member_id']"
              />
            </el-select>
          </el-form-item>

          <el-form-item :label="t('businessId')" prop="business_id">
            <el-select
              class="w-[280px]"
              v-model="businessOrderTable.searchParam.business_id"
              clearable
              :placeholder="t('businessIdPlaceholder')"
            >
              <el-option
                v-for="(item, index) in businessIdList"
                :key="index"
                :label="item['name']"
                :value="item['id']"
              />
            </el-select>
          </el-form-item>

          <el-form-item :label="t('orderFrom')" prop="order_from">
            <el-input
              v-model="businessOrderTable.searchParam.order_from"
              :placeholder="t('orderFromPlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('orderId')" prop="order_id">
            <el-input
              v-model="businessOrderTable.searchParam.order_id"
              :placeholder="t('orderIdPlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('orderStatus')" prop="order_status">
            <el-input
              v-model="businessOrderTable.searchParam.order_status"
              :placeholder="t('orderStatusPlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('outTradeNo')" prop="out_trade_no">
            <el-input
              v-model="businessOrderTable.searchParam.out_trade_no"
              :placeholder="t('outTradeNoPlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('payTime')" prop="pay_time">
            <el-date-picker
              v-model="businessOrderTable.searchParam.pay_time"
              type="datetimerange"
              format="YYYY-MM-DD hh:mm:ss"
              :start-placeholder="t('startDate')"
              :end-placeholder="t('endDate')"
            />
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="loadBusinessOrderList()">{{
              t("search")
            }}</el-button>
            <el-button @click="resetForm(searchFormRef)">{{
              t("reset")
            }}</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <div class="mt-[10px]">
        <el-table
          :data="businessOrderTable.data"
          size="large"
          v-loading="businessOrderTable.loading"
        >
          <template #empty>
            <span>{{ !businessOrderTable.loading ? t("emptyData") : "" }}</span>
          </template>
          <el-table-column
            prop="member_id_name"
            :label="t('memberId')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="business_id_name"
            :label="t('businessId')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="order_from"
            :label="t('orderFrom')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="order_id"
            :label="t('orderId')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="order_money"
            :label="t('orderMoney')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="order_discount_money"
            :label="t('orderDiscountMoney')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="order_status"
            :label="t('orderStatus')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="refund_status"
            :label="t('refundStatus')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="out_trade_no"
            :label="t('outTradeNo')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="remark"
            :label="t('remark')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            :label="t('payTime')"
            min-width="180"
            align="center"
            :show-overflow-tooltip="true"
          >
            <template #default="{ row }">
              {{ row.pay_time || "" }}
            </template>
          </el-table-column>

          <el-table-column
            prop="close_reason"
            :label="t('closeReason')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="is_enable_refund"
            :label="t('isEnableRefund')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="close_time"
            :label="t('closeTime')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="ip"
            :label="t('ip')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            :label="t('operation')"
            fixed="right"
            min-width="120"
          >
            <template #default="{ row }">
              <el-button type="primary" link @click="editEvent(row)">{{
                t("edit")
              }}</el-button>
              <el-button type="primary" link @click="deleteEvent(row.id)">{{
                t("delete")
              }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-[16px] flex justify-end">
          <el-pagination
            v-model:current-page="businessOrderTable.page"
            v-model:page-size="businessOrderTable.limit"
            layout="total, sizes, prev, pager, next, jumper"
            :total="businessOrderTable.total"
            @size-change="loadBusinessOrderList()"
            @current-change="loadBusinessOrderList"
          />
        </div>
      </div>

      <edit ref="editBusinessOrderDialog" @complete="loadBusinessOrderList" />
    </el-card>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from "vue";
import { t } from "@/lang";
import { useDictionary } from "@/app/api/dict";
import {
  getBusinessOrderList,
  deleteBusinessOrder,
  getWithMemberList,
  getWithBusinessList,
} from "@/addon/fast_pay/api/businessorder";
import { img } from "@/utils/common";
import { ElMessageBox, FormInstance } from "element-plus";
import Edit from "@/addon/fast_pay/views/businessorder/components/businessorder-edit.vue";
import { useRoute } from "vue-router";
const route = useRoute();
const pageName = route.meta.title;

let businessOrderTable = reactive({
  page: 1,
  limit: 10,
  total: 0,
  loading: true,
  data: [],
  searchParam: {
    site_id: "",
    member_id: "",
    business_id: "",
    order_from: "",
    order_id: "",
    order_status: "",
    out_trade_no: "",
    pay_time: [],
  },
});

const searchFormRef = ref<FormInstance>();

// 选中数据
const selectData = ref<any[]>([]);

// 字典数据

/**
 * 获取商户订单列列表
 */
const loadBusinessOrderList = (page: number = 1) => {
  businessOrderTable.loading = true;
  businessOrderTable.page = page;

  getBusinessOrderList({
    page: businessOrderTable.page,
    limit: businessOrderTable.limit,
    ...businessOrderTable.searchParam,
  })
    .then((res) => {
      businessOrderTable.loading = false;
      businessOrderTable.data = res.data.data;
      businessOrderTable.total = res.data.total;
    })
    .catch(() => {
      businessOrderTable.loading = false;
    });
};
loadBusinessOrderList();

const editBusinessOrderDialog: Record<string, any> | null = ref(null);

/**
 * 添加商户订单列
 */
const addEvent = () => {
  editBusinessOrderDialog.value.setFormData();
  editBusinessOrderDialog.value.showDialog = true;
};

/**
 * 编辑商户订单列
 * @param data
 */
const editEvent = (data: any) => {
  editBusinessOrderDialog.value.setFormData(data);
  editBusinessOrderDialog.value.showDialog = true;
};

/**
 * 删除商户订单列
 */
const deleteEvent = (id: number) => {
  ElMessageBox.confirm(t("businessOrderDeleteTips"), t("warning"), {
    confirmButtonText: t("confirm"),
    cancelButtonText: t("cancel"),
    type: "warning",
  }).then(() => {
    deleteBusinessOrder(id)
      .then(() => {
        loadBusinessOrderList();
      })
      .catch(() => {});
  });
};

const siteIdList = ref([]);
const setSiteIdList = async () => {
  siteIdList.value = await (await getWithMemberList({})).data;
};
setSiteIdList();
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
const businessIdList = ref([]);
const setBusinessIdList = async () => {
  businessIdList.value = await (await getWithBusinessList({})).data;
};
setBusinessIdList();

const resetForm = (formEl: FormInstance | undefined) => {
  if (!formEl) return;
  formEl.resetFields();
  loadBusinessOrderList();
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
