<template>
  <div class="main-container">
    <el-card class="box-card !border-none" shadow="never">
      <el-card
        class="box-card !border-none my-[10px] table-search-wrap"
        shadow="never"
      >
        <el-form
          :inline="true"
          :model="shopOrderTable.searchParam"
          ref="searchFormRef"
        >
          <el-form-item :label="t('orderNo')" prop="order_no">
            <el-input
              v-model="shopOrderTable.searchParam.order_no"
              :placeholder="t('orderNoPlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('deliveryId')" prop="delivery_id">
            <el-input
              v-model="shopOrderTable.searchParam.delivery_id"
              :placeholder="t('deliveryIdPlaceholder')"
            />
          </el-form-item>
          <!-- <el-form-item :label="t('orderStatusName')" prop="order_status_name">
            <el-input
              v-model="shopOrderTable.searchParam.order_status_name"
              :placeholder="t('orderStatusNamePlaceholder')"
            />
          </el-form-item> -->
          <el-form-item :label="t('createTime')" prop="create_time">
            <el-date-picker
              v-model="shopOrderTable.searchParam.create_time"
              type="datetimerange"
              format="YYYY-MM-DD hh:mm:ss"
              :start-placeholder="t('startDate')"
              :end-placeholder="t('endDate')"
            />
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="loadShopOrderList()">{{
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
          :data="shopOrderTable.data"
          size="large"
          v-loading="shopOrderTable.loading"
        >
          <template #empty>
            <span>{{ !shopOrderTable.loading ? t("emptyData") : "" }}</span>
          </template>
          <el-table-column
            prop="order_no"
            :label="t('orderNo')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="delivery_id"
            :label="t('deliveryId')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="yida_order_no"
            :label="t('yidaOrderNo')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="order_status_name"
            :label="t('orderStatusName')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="is_pick"
            label="揽收状态"
            min-width="120"
            :show-overflow-tooltip="true"
          >
            <template #default="{ row }">
              <el-tag v-if="row.is_pick == 1">已揽收</el-tag>
              <el-tag type="warning" v-if="row.is_pick == 0">未揽收</el-tag>
            </template>
          </el-table-column>

          <el-table-column
            prop="is_send"
            label="发单状态"
            min-width="120"
            :show-overflow-tooltip="true"
          >
            <template #default="{ row }">
              <el-tag v-if="row.is_send == 1">已发单</el-tag>
              <el-tag type="warning" v-if="row.is_send == 0">未发单</el-tag>
            </template>
          </el-table-column>

          <!-- <el-table-column
            prop="close_time"
            :label="t('closeTime')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="delete_time"
            :label="t('deleteTime')"
            min-width="120"
            :show-overflow-tooltip="true"
          /> -->

          <el-table-column
            :label="t('operation')"
            fixed="right"
            min-width="120"
          >
            <template #default="{ row }">
              <el-button
                type="primary"
                v-if="row.is_send == 1 && row.is_pick == 0"
                link
                @click="cancelSendEvent(row)"
                >取消发货</el-button
              >
              <el-button
                v-if="row.is_send == 0 && row.is_pick == 0"
                type="primary"
                link
                @click="deleteEvent(row.id)"
                >{{ t("delete") }}</el-button
              >
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-[16px] flex justify-end">
          <el-pagination
            v-model:current-page="shopOrderTable.page"
            v-model:page-size="shopOrderTable.limit"
            layout="total, sizes, prev, pager, next, jumper"
            :total="shopOrderTable.total"
            @size-change="loadShopOrderList()"
            @current-change="loadShopOrderList"
          />
        </div>
      </div>

      <edit ref="editShopOrderDialog" @complete="loadShopOrderList" />
    </el-card>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from "vue";
import { t } from "@/lang";
import { useDictionary } from "@/app/api/dict";
import {
  getShopOrderList,
  deleteShopOrder,
} from "@/addon/tk_jhkd/api/shop_order";
import { cancelOrder } from "@/addon/tk_jhkd/api/shop";
import { img } from "@/utils/common";
import { ElMessageBox, FormInstance } from "element-plus";
import Edit from "@/addon/tk_jhkd/views/shop_order/components/shop-order-edit.vue";
import { useRoute } from "vue-router";
const route = useRoute();
const pageName = route.meta.title;
const cancelSendEvent = async (e) => {
  try {
    await ElMessageBox.confirm("本订单所有发货将会取消，是否确定？", "确认", {
      confirmButtonText: "确定",
      cancelButtonText: "取消",
      type: "warning",
    });
    await cancelOrder(e);
    loadShopOrderList();
  } catch (error) {}
};
let shopOrderTable = reactive({
  page: 1,
  limit: 10,
  total: 0,
  loading: true,
  data: [],
  searchParam: {
    order_no: "",
    delivery_id: "",
    order_status_name: "",
    create_time: [],
  },
});

const searchFormRef = ref<FormInstance>();

// 选中数据
const selectData = ref<any[]>([]);

// 字典数据

/**
 * 获取商城发单列表
 */
const loadShopOrderList = (page: number = 1) => {
  shopOrderTable.loading = true;
  shopOrderTable.page = page;

  getShopOrderList({
    page: shopOrderTable.page,
    limit: shopOrderTable.limit,
    ...shopOrderTable.searchParam,
  })
    .then((res) => {
      shopOrderTable.loading = false;
      shopOrderTable.data = res.data.data;
      shopOrderTable.total = res.data.total;
    })
    .catch(() => {
      shopOrderTable.loading = false;
    });
};
loadShopOrderList();

const editShopOrderDialog: Record<string, any> | null = ref(null);

/**
 * 添加商城发单
 */
const addEvent = () => {
  editShopOrderDialog.value.setFormData();
  editShopOrderDialog.value.showDialog = true;
};

/**
 * 编辑商城发单
 * @param data
 */
const editEvent = (data: any) => {
  editShopOrderDialog.value.setFormData(data);
  editShopOrderDialog.value.showDialog = true;
};

/**
 * 删除商城发单
 */
const deleteEvent = (id: number) => {
  ElMessageBox.confirm(t("shopOrderDeleteTips"), t("warning"), {
    confirmButtonText: t("confirm"),
    cancelButtonText: t("cancel"),
    type: "warning",
  }).then(() => {
    deleteShopOrder(id)
      .then(() => {
        loadShopOrderList();
      })
      .catch(() => {});
  });
};

const resetForm = (formEl: FormInstance | undefined) => {
  if (!formEl) return;
  formEl.resetFields();
  loadShopOrderList();
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
