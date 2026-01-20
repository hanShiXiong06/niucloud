<template>
  <el-dialog
    v-model="showDialog"
    title="聚合发货"
    width="700px"
    class="diy-dialog-wrap"
    :destroy-on-close="true"
    @close="handleCloseEvent"
  >
    <div v-loading="loading">
      <el-form
        :model="formData"
        label-width="100px"
        ref="formRef"
        :rules="formRules"
        class="page-form mb-[30px]"
      >
        <div
          v-if="selectPreData && formData.delivery_type == 'express'"
          class="flex mb-2 items-center"
        >
          <el-avatar :src="selectPreData.logo" />
          <div class="ml-2 mr-4">
            <div class="font-bold truncate">
              {{ selectPreData.channelName }}
            </div>
            <div class="font-bold">
              预计运费:￥{{ selectPreData.preDeliveryFee }}
            </div>
          </div>
          <el-button
            type="primary"
            size="small"
            round
            @click="changePreOrderEvent()"
            >重新选择</el-button
          >
        </div>
        <div
          v-if="!selectPreData && formData.delivery_type == 'express'"
          class="flex justify-end"
        >
          <el-button type="primary" @click="changePreOrderEvent()"
            >选择快递</el-button
          >
        </div>
        <el-form-item :label="t('deliveryType')" prop="delivery_type">
          <el-radio-group
            v-model="formData.delivery_type"
            @change="deliveryChange"
          >
            <el-radio
              :label="index"
              v-for="(item, index) in deliveryType"
              :key="index"
              >{{ item }}</el-radio
            >
          </el-radio-group>
        </el-form-item>
      </el-form>
      <el-table
        :data="goodsDataArr"
        size="large"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" :selectable="selectable" />
        <el-table-column
          prop="goods_name"
          :label="t('goodsName')"
          min-width="200"
        />
        <el-table-column prop="num" :label="t('num')" min-width="80" />
        <el-table-column
          prop="status_name"
          :label="t('refundStatusName')"
          min-width="120"
        />
        <el-table-column
          prop="delivery_status_name"
          :label="t('deliveryStatusName')"
          min-width="120"
          align="right"
        />
      </el-table>
    </div>
    <el-dialog
      v-model="showPreorderDialog"
      width="65%"
      :close-on-click-modal="false"
    >
      <el-alert
        type="warning"
        title="默认会提前1kg,不保价获取快递运费，如您的重量不一致或者需要保价，您可以修改信息重新获取报价"
        description="请提前打包好物品，根据包裹实际重量下单，如长时间未揽收，请到已发货/发单记录里面取消订单重新选择其他渠道下单"
        :closable="false"
        show-icon
      />
      <el-form-item label="发件地址" class="mt-4">
        <div
          class="pr-4 pl-4 pt-1 pb-1 rounded-md statistic-card bg-gradient-to-r from-indigo-50 from-10% via-sky-50 via-10% to-emerald-50 to-10%"
        >
          <div class="font-bold">{{ mr_address.full_address }}</div>
          <div class="flex items-center">
            <div class="mr-2 font-bold">{{ mr_address.contact_name }}</div>
            <div class="mr-4">{{ mr_address.mobile }}</div>
            <el-button
              type="primary"
              size="small"
              round
              @click="showChangeAddress"
              >切换地址</el-button
            >
          </div>
        </div>
      </el-form-item>
      <el-form-item label="包裹重量">
        <el-input
          class="input-width"
          type="number"
          v-model="formData.weight"
          placeholder="请输入重量"
          style="width: 88px"
        ></el-input
        ><span class="ml-2">单位：kg</span>
      </el-form-item>
      <el-form-item label="保价金额">
        <el-input
          class="input-width"
          type="number"
          v-model="formData.bj_price"
          placeholder=""
          style="width: 88px"
        ></el-input>
        <span class="ml-2">单位：元</span>
        <span class="ml-2 text-slate-400"> 如不需保价可以留空</span>
      </el-form-item>
      <div class="flex flex-wrap">
        <block v-for="(item, index) in preData" :key="index">
          <div
            v-if="item.onlinePay == 'Y'"
            class="flex items-center w-[260px] p-2 pt-3 m-2 rounded-md statistic-card bg-gradient-to-r from-indigo-50 from-10% via-sky-50 via-10% to-emerald-50 to-10%"
            @click="selectPreDataEvent(item)"
          >
            <div>
              <el-avatar :src="item.logo" />
            </div>
            <div class="ml-2" @click="selectPreDataEvent(item)">
              <div class="font-bold truncate">{{ item.channelName }}</div>
              <div class="text-xs">限重:{{ item.limitWeight }}kg</div>
              <div class="flex items-center">
                <div class="text-xs pt-1">￥</div>
                <div class="font-bold text-xl text-red-500">
                  {{ item.preDeliveryFee }}
                </div>
              </div>
            </div>
          </div>
        </block>
      </div>
      <template #footer>
        <span class="dialog-footer">
          <el-button type="primary" @click="preOrderEvent()"
            >重新报价</el-button
          >
        </span>
      </template>
    </el-dialog>
    <el-dialog
      v-model="showChangeAddressDialog"
      title="选择地址"
      width="800px"
      class="diy-dialog-wrap"
      :destroy-on-close="true"
    >
      <el-card
        class="box-card !border-none my-[10px] table-search-wrap"
        shadow="never"
      >
        <el-form
          :inline="true"
          :model="shopAddressTable.searchParam"
          ref="searchFormRef"
        >
          <el-form-item :label="t('mobile')" prop="mobile">
            <el-input
              v-model="shopAddressTable.searchParam.mobile"
              :placeholder="t('mobilePlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('fullAddress')" prop="full_address">
            <el-input
              v-model="shopAddressTable.searchParam.full_address"
              :placeholder="t('fullAddressPlaceholder')"
            />
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="loadShopAddressList()">{{
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
          :data="shopAddressTable.data"
          size="large"
          v-loading="shopAddressTable.loading"
        >
          <template #empty>
            <span>{{ !shopAddressTable.loading ? t("emptyData") : "" }}</span>
          </template>
          <el-table-column
            prop="contact_name"
            :label="t('contactName')"
            min-width="120"
          />
          <el-table-column prop="mobile" :label="t('mobile')" min-width="120" />
          <el-table-column
            prop="full_address"
            :label="t('fullAddress')"
            min-width="120"
            :show-overflow-tooltip="true"
          />
          <el-table-column
            prop="is_delivery_address"
            :label="t('addressType')"
            min-width="120"
            align="left"
          >
            <template #default="{ row }">
              <div v-if="row.is_delivery_address">
                {{ t("deliveryAddress") }}
                <el-tag size="small" v-if="row.is_default_delivery">{{
                  t("default")
                }}</el-tag>
              </div>
              <div v-if="row.is_refund_address">
                {{ t("refundAddress") }}
                <el-tag size="small" v-if="row.is_default_refund">{{
                  t("default")
                }}</el-tag>
              </div>
            </template>
          </el-table-column>
          <el-table-column min-width="100">
            <template #default="{ row }">
              <el-button type="primary" size="small" @click="selectAddress(row)"
                >选择</el-button
              >
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-[16px] flex justify-end">
          <el-pagination
            v-model:current-page="shopAddressTable.page"
            v-model:page-size="shopAddressTable.limit"
            layout="total, sizes, prev, pager, next, jumper"
            :total="shopAddressTable.total"
            @size-change="loadShopAddressList()"
            @current-change="loadShopAddressList"
          />
        </div>
      </div>
    </el-dialog>
    <template #footer>
      <span class="dialog-footer">
        <el-button @click="showDialog = false">{{ t("cancel") }}</el-button>
        <el-button
          type="primary"
          :loading="loading"
          @click="confirm(formRef)"
          >{{ t("confirm") }}</el-button
        >
      </span>
    </template>
  </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from "vue";
import { t } from "@/lang";
import { FormInstance, ElMessage } from "element-plus";
import {
  getOrderDeliveryType,
  orderDelivery,
  getSendAddress,
  preOrder,
} from "@/addon/tk_jhkd/api/shop";
import { cloneDeep } from "lodash-es";
const mr_address = ref(null);

try {
  const storedAddress = localStorage.getItem("mr_address");
  if (storedAddress) {
    mr_address.value = JSON.parse(storedAddress);
  } else {
    // 处理地址为空的情况
    console.log("Address is empty.");
  }
} catch (error) {
  console.error("Error parsing address:", error);
}

const preData = ref();
const selectPreData = ref();
const selectAddress = async (row) => {
  showChangeAddressDialog.value = false;
  mr_address.value = row;
  formData.send_id = row.id;
  preOrderEvent();
};
const changePreOrderEvent = () => {
  selectPreData.value = null;
  showPreorderDialog.value = true;
  preOrderEvent();
};
const selectPreDataEvent = (e) => {
  selectPreData.value = e;
  formData.predata = e;
  showPreorderDialog.value = false;
};
const handleCloseEvent = () => {
  selectPreData.value = null;
};
const showPreorderDialog = ref(false);
const showChangeAddressDialog = ref(false);
const showChangeAddress = async () => {
  showChangeAddressDialog.value = true;
  const res = await getSendAddress({});
};
const shopAddressTable = reactive({
  page: 1,
  limit: 10,
  total: 0,
  loading: true,
  data: [],
  searchParam: {
    mobile: "",
    full_address: "",
  },
});

const searchFormRef = ref<FormInstance>();

/**
 * 获取商家地址库列表
 */
const loadShopAddressList = (page: number = 1) => {
  shopAddressTable.loading = true;
  shopAddressTable.page = page;
  getSendAddress({
    page: shopAddressTable.page,
    limit: shopAddressTable.limit,
    ...shopAddressTable.searchParam,
  })
    .then((res) => {
      shopAddressTable.loading = false;
      shopAddressTable.data = res.data.data;
      shopAddressTable.total = res.data.total;
    })
    .catch(() => {
      shopAddressTable.loading = false;
    });
};
loadShopAddressList();
const resetForm = (formEl: FormInstance | undefined) => {
  if (!formEl) return;
  formEl.resetFields();
  loadShopAddressList();
};
const showDialog = ref(false);
const loading = ref(false);
interface companyDataType {
  company_id: number;
  company_name: string;
  index: number;
}
const companyData = ref<companyDataType[]>([]);
const isHasVirtual = ref(false);
const deliveryType = ref([]);

/**
 * 表单数据
 */
const goodsData = ref([]);
const initialFormData = {
  order_id: 0,
  delivery_type: "",
  express_company_id: "",
  express_number: "",
  order_goods_ids: [],
  weight: "1",
  bj_price: "",
  send_id: "",
  predata: "",
};
const formData: Record<string, any> = reactive({ ...initialFormData });

const formRef = ref<FormInstance>();

// 表单验证规则
const formRules = computed(() => {
  return {
    delivery_type: [
      {
        required: true,
        message: t("deliveryTypePlaceholder"),
        trigger: "blur",
      },
    ],
    express_company_id: [
      { required: true, validator: companyPass, trigger: "blur" },
    ],
    express_number: [
      { required: true, validator: expressNumberPass, trigger: "blur" },
    ],
  };
});

const companyPass = (rule: any, value: any, callback: any) => {
  if (formData.delivery_type == "express" && value === "") {
    callback(new Error(t("companyPlaceholder")));
  } else {
    callback();
  }
};

const expressNumberPass = (rule: any, value: any, callback: any) => {
  if (formData.delivery_type == "express" && value === "") {
    callback(new Error(t("expressNumberPlaceholder")));
  } else {
    callback();
  }
};

const selectable = (row: any, index: number) => {
  if (row.status == 2 || row.delivery_status == "delivery_finish") {
    return false;
  }
  return true;
};
interface goodsDataType {
  goods_type: string;
}
const goodsDataArr = ref<goodsDataType[]>([]);
const deliveryChange = () => {
  goodsDataArr.value = cloneDeep(goodsData.value);
  if (formData.delivery_type && formData.delivery_type == "virtual") {
    for (const k in goodsDataArr.value) {
      if (goodsDataArr.value[k].goods_type != "virtual") {
        goodsDataArr.value.splice(k, 1);
      }
    }
  } else if (formData.delivery_type && formData.delivery_type != "virtual") {
    for (const k in goodsDataArr.value) {
      if (goodsDataArr.value[k].goods_type == "virtual") {
        goodsDataArr.value.splice(k, 1);
      }
    }
  }
};

const handleSelectionChange = (val: any) => {
  formData.order_goods_ids = cloneDeep([]);
  for (const v in val) {
    formData.order_goods_ids.push(val[v].order_goods_id);
  }
};

const emit = defineEmits(["complete"]);

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
  formData.send_id = mr_address.value.id;
  if (loading.value || !formEl) return;
  if (formData.order_goods_ids.length <= 0) {
    ElMessage({
      message: t("orderGoodsPlaceholder"),
      type: "warning",
    });
    return;
  }

  await formEl.validate(async (valid) => {
    if (valid) {
      loading.value = true;
      const data = formData;
      orderDelivery(data)
        .then((res) => {
          loading.value = false;
          showDialog.value = false;
          emit("complete");
          initFormData();
        })
        .catch((err) => {
          loading.value = false;
          initFormData();
        });
    }
  });
};

const setFormData = async (row: any = null) => {
  loading.value = true;
  if (row) {
    formData.order_id = row.order_id;
    formData.delivery_type = "";
    goodsData.value = row.order_goods;
    goodsDataArr.value = row.order_goods;
    await getOrderDeliveryType({
      delivery_type: row.delivery_type,
    }).then((data) => {
      deliveryType.value = data.data;
      // eslint-disable-next-line no-unreachable-loop
      for (const v in data.data) {
        formData.delivery_type = v;
        break;
      }
      deliveryChange();
    });

    for (let i = 0; i < row.order_goods.length; i++) {
      if (row.order_goods[i].goods_type == "virtual") {
        isHasVirtual.value = true;
        break;
      }
    }
    if (isHasVirtual.value == true) {
      Object.assign(deliveryType.value, { virtual: t("virtualDelivery") });
    }
    showPreorderDialog.value = true;
    preOrderEvent();
  }
  loading.value = false;
};

const initFormData = () => {
  formData.delivery_type = "";
  formData.express_company_id = "";
  formData.express_number = "";
  formData.order_goods_ids = [];
  formData.send_id = mr_address.value.id;
};
/**
 * 预下单
 */
const preOrderEvent = async () => {
  let data = {
    send_id: mr_address.value.id,
    order_id: formData.order_id,
    weight: formData.weight,
    bj_price: formData.bj_price,
  };
  if (mr_address.value.id) {
    const res = await preOrder(data);
    preData.value = res.data;
  }
};
defineExpose({
  showDialog,
  setFormData,
});
</script>

<style lang="scss" scoped></style>
<style lang="scss">
.diy-dialog-wrap .el-form-item__label {
  height: auto !important;
}
.card {
  background: #e2e2e2;
  border-radius: 8px;
}
</style>
