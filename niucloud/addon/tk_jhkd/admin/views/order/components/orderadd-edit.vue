<template>
  <el-dialog v-model="showDialog" title="订单详情" width="50%" class="diy-dialog-wrap" :destroy-on-close="true">
    <el-card v-if="detailData">
      <div>
        <div class="flex items-center mt-4">
          <div
            class="mr-[28px] p-4 pl-12 pr-12 rounded-md statistic-card bg-gradient-to-r from-indigo-50 from-10% via-sky-50 via-10% to-emerald-50 to-10%"
            v-if="detailData.orderInfo">
            <div class="font-bold text-slate-400">订单金额</div>
            <div class="text-2xl">{{ detailData.orderInfo.order_money }}</div>
          </div>
          <div
            class="mr-[28px] p-4 pl-12 pr-12 rounded-md statistic-card bg-gradient-to-r from-indigo-50 from-10% via-sky-50 via-10% to-emerald-50 to-10%"
            v-if="detailData.orderInfo">
            <div class="font-bold text-slate-400">优惠金额</div>
            <div class="text-2xl">
              {{ detailData.orderInfo.order_discount_money }}
            </div>
          </div>
          <div
            class="mr-[28px] p-4 pl-12 pr-12 rounded-md statistic-card bg-gradient-to-r from-indigo-50 from-10% via-sky-50 via-10% to-emerald-50 to-10%"
            v-if="startaddress">
            <div class="font-bold text-slate-400">实付金额</div>
            <div class="text-2xl">{{ (detailData.order_money - detailData.order_discount_money).toFixed(2) }}</div>
          </div>
        </div>
        <div class="flex mt-4">
          <div class="mr-4">
            <span>订单号：</span>
            <span class="font-bold"> {{ detailData.orderInfo.order_id }} </span>
          </div>
          <div class="mr-4">
            <span>运单号：</span>
            <span class="font-bold">
              {{ detailData.deliveryInfo.delivery_id }}
            </span>
            <el-icon class="ml-2" @click="copyEvent(detailData.deliveryInfo.delivery_id)">
              <DocumentCopy />
            </el-icon>
          </div>
          <div class="mr-2">
            <span>创建时间：</span>
            <span class=""> {{ detailData.orderInfo.create_time }} </span>
          </div>
        </div>
        <div v-if="detailData.memberInfo" class="flex mt-6">
          <span>用户：</span>
          <span class="font-bold">{{ detailData.member_id_name }} </span>
          <el-tag class="ml-2 mr-12">{{
            detailData.orderInfo.order_from
          }}</el-tag>
          <span class="bg-cyan-50 rounded-md pl-2 pr-2 mr-4">
            {{ detailData.deliveryInfo.goods }}-{{
              detailData.deliveryInfo.weight
            }}kg-{{ detailData.deliveryInfo.long }}x{{
              detailData.deliveryInfo.width
            }}x{{ detailData.deliveryInfo.height }}cm
          </span>
          <div v-if="detailData.deliveryInfo.bj_price">
            保价：{{ detailData.deliveryInfo.bj_price }}
          </div>
        </div>
      </div>

      <div v-if="detailData.deliveryInfo.price_rule" class="mt-4 flex items-center">
        <div class="mr-4">计价规则</div>
        <el-tag class="mr-2">
          首重：
          {{ detailData.deliveryInfo.price_rule.start }}kg;价格:{{
            detailData.deliveryInfo.price_rule.first
          }}元
        </el-tag>
        <el-tag class="mr-2">
          续重：
          {{ detailData.deliveryInfo.price_rule.add }}元/kg
        </el-tag>
      </div>
      <div v-if="detailData.deliveryInfo" class="mt-4 flex items-center">
        <div class="mr-4">补差详情</div>
        <el-tag class="mr-2">
          通知次数:
          {{ detailData.deliveryInfo.notice_num }}次
        </el-tag>
        <el-tag class="mr-2">
          下单重量:
          {{ detailData.deliveryInfo.weight }}kg
        </el-tag>

        <div class="flex mr-2">
          <div>
            超重:
            {{
              Math.ceil(
                detailData.deliveryRealInfo.fee_weight -
                detailData.deliveryInfo.weight
              )
            }}kg;￥{{
              Math.ceil(
                detailData.deliveryRealInfo.fee_weight -
                detailData.deliveryInfo.weight
              ) * detailData.deliveryInfo.price_rule.add ?? 3
            }};
          </div>
          <block v-for="(item, index) in detailData.deliveryRealInfo.fee_blockList">
            <div>{{ item.name }}:{{ item.fee }}元;</div>
          </block>
        </div>
        <el-tag class="mr-2" type="error">
          需补金额：
          {{ detailData.order_money }}元
        </el-tag>
      </div>

      <div class="mt-4 flex items-center">
        <div class="mr-4">订单状态</div>
        <el-tag class="mr-2">
          {{ detailData.orderInfo.order_status_arr.name }}
        </el-tag>
        <el-tag v-if="detailData.orderInfo.is_send == 1"> 已发单 </el-tag>
        <el-tag type="info" v-if="detailData.orderInfo.is_send == 0">
          未发单
        </el-tag>
        <span class="ml-4 font-bold">{{
          detailData.orderInfo.close_reason
        }}</span>
      </div>

      <div class="mt-4 flex items-center" v-if="detailData.remark">
        <div class="mr-4">订单备注</div>
        <span class="ml-4 font-bold">{{ detailData.remark }}</span>
      </div>

      <div class="flex items-center mt-4">
        <div
          class="mr-[28px] p-4 rounded-md statistic-card bg-gradient-to-r from-indigo-50 from-10% via-sky-50 via-10% to-emerald-50 to-10%"
          v-if="detailData.deliveryInfo">
          <div class="font-bold">
            {{ detailData.deliveryInfo.start_address.address }}
          </div>
          <div>{{ detailData.deliveryInfo.start_address.full_address }}</div>
          <div class="flex text-xs text-current">
            <div class="mr-2">
              {{ detailData.deliveryInfo.start_address.name }}
            </div>
            <div class="text-current font-bold">
              {{ detailData.deliveryInfo.start_address.mobile }}
            </div>
          </div>
        </div>
        <el-icon class="mr-[28px]" size="28">
          <Right />
        </el-icon>
        <div
          class="p-4 rounded-md statistic-card bg-gradient-to-r from-indigo-50 from-10% via-sky-50 via-10% to-emerald-50 to-10%"
          v-if="detailData.deliveryInfo.end_address">
          <div class="font-bold">
            {{ detailData.deliveryInfo.end_address.address }}
          </div>
          <div>{{ detailData.deliveryInfo.end_address.full_address }}</div>
          <div class="flex text-xs text-current">
            <div class="mr-2">
              {{ detailData.deliveryInfo.end_address.name }}
            </div>
            <div class="text-current font-bold">
              {{ detailData.deliveryInfo.end_address.mobile }}
            </div>
          </div>
        </div>
      </div>
      <el-divider v-if="detailData.deliveryInfo" class="mt-4" content-position="left">轨迹跟踪</el-divider>
      <div class="mt-2">
        <div class="flex items-center mb-4">
          <el-avatar v-if="detailData && detailData.deliveryInfo.delivery_arry.logo"
            :src="img(detailData.deliveryInfo.delivery_arry.logo)" />
          <div class="ml-1 p-2">
            <div>{{ detailData.deliveryInfo.delivery_arry.name }}</div>
            <div class="font-bold">
              {{ detailData.deliveryInfo.delivery_id }}
            </div>
          </div>
          <div class="ml-8" v-if="detailData.deliveryInfo.courier_context">
            <div class="flex">
              <div class="mr-2">
                揽件员:{{ detailData.deliveryInfo.courier_context.courierName }}
              </div>
              <div class="">
                联系电话:{{
                  detailData.deliveryInfo.courier_context.courierPhone
                }}
              </div>
            </div>

            <div v-if="detailData.deliveryInfo.courier_context.pickUpCode">
              取件码:{{ detailData.deliveryInfo.courier_context.pickUpCode }}
            </div>
          </div>
        </div>

        <el-timeline>
          <el-timeline-item v-for="(activity, index) in deliveryInfo" :key="index" :timestamp="activity.time">
            {{ activity.desc }}
          </el-timeline-item>
        </el-timeline>
      </div>
    </el-card>
  </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch } from "vue";
import { useDictionary } from "@/app/api/dict";
import { t } from "@/lang";
import type { FormInstance } from "element-plus";
import { img } from "@/utils/common";
import {
  addOrderAdd,
  editOrderAdd,
  getOrderAddInfo,
  getWithMemberList,
  getDeliveryInfo,
} from "@/addon/tk_jhkd/api/order";

let showDialog = ref(false);
const loading = ref(false);
const detailData = ref();
/**
 * 表单数据
 */
const initialFormData = {
  id: "",
  member_id: "",
  order_id: "",
  remark: "",
};
const formData: Record<string, any> = reactive({ ...initialFormData });

const formRef = ref<FormInstance>();

// 表单验证规则
const formRules = computed(() => {
  return {
    member_id: [
      { required: true, message: t("memberIdPlaceholder"), trigger: "blur" },
    ],
    order_id: [
      { required: true, message: t("orderIdPlaceholder"), trigger: "blur" },
    ],
  };
});

const emit = defineEmits(["complete"]);
/**
 * 复制
 */
import { useClipboard } from "@vueuse/core";
const { copy, isSupported, copied } = useClipboard();
const copyEvent = (text: string) => {
  console.log("ddddddd");
  if (!isSupported.value) {
    ElMessage({
      message: "当前浏览器不支持一键复制，请手动复制",
      type: "warning",
    });
    return;
  }
  copy(text);
  ElMessage({
    message: "复制成功",
    type: "success",
  });
};
/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
  if (loading.value || !formEl) return;
  let save = formData.id ? editOrderAdd : addOrderAdd;

  await formEl.validate(async (valid) => {
    if (valid) {
      loading.value = true;

      let data = formData;

      save(data)
        .then((res) => {
          loading.value = false;
          showDialog.value = false;
          emit("complete");
        })
        .catch((err) => {
          loading.value = false;
        });
    }
  });
};

// 获取字典数据
let order_statusList = ref([]);
const order_statusDictList = async () => {
  order_statusList.value = await (
    await useDictionary("jhkd_order_status")
  ).data.dictionary;
};
order_statusDictList();
watch(
  () => order_statusList.value,
  () => {
    formData.order_status = order_statusList.value[0].value;
  }
);

const memberIdList = ref([] as any[]);
const setMemberIdList = async () => {
  memberIdList.value = await (await getWithMemberList({})).data;
};
setMemberIdList();
const deliveryInfo = ref();
const setDeliveryInfo = async (delivery_id) => {
  const data = await getDeliveryInfo(delivery_id);
  deliveryInfo.value = data.data;
};
const setFormData = async (row: any = null) => {
  Object.assign(formData, initialFormData);
  loading.value = true;
  if (row) {
    const data = await (await getOrderAddInfo(row.id)).data;
    detailData.value = data;
    if (detailData.value.deliveryInfo.delivery_id) {
      setDeliveryInfo(detailData.value.deliveryInfo.delivery_id);
    }
    if (data)
      Object.keys(formData).forEach((key: string) => {
        if (data[key] != undefined) formData[key] = data[key];
      });
  }
  loading.value = false;
};

// 验证手机号格式
const mobileVerify = (rule: any, value: any, callback: any) => {
  if (value && !/^1[3-9]\d{9}$/.test(value)) {
    callback(new Error(t("generateMobile")));
  } else {
    callback();
  }
};

// 验证身份证号
const idCardVerify = (rule: any, value: any, callback: any) => {
  if (
    value &&
    !/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X)$/.test(
      value
    )
  ) {
    callback(new Error(t("generateIdCard")));
  } else {
    callback();
  }
};

// 验证邮箱号
const emailVerify = (rule: any, value: any, callback: any) => {
  if (value && !/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/.test(value)) {
    callback(new Error(t("generateEmail")));
  } else {
    callback();
  }
};

// 验证请输入整数
const numberVerify = (rule: any, value: any, callback: any) => {
  if (!Number.isInteger(value)) {
    callback(new Error(t("generateNumber")));
  } else {
    callback();
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
</style>
