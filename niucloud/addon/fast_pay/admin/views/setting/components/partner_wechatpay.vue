<template>
  <el-dialog
    v-model="showDialog"
    :title="t('updateWechat')"
    width="500px"
    :destroy-on-close="true"
  >
    <el-form
      :model="formData"
      label-width="90px"
      ref="formRef"
      :rules="formRules"
      class="page-form"
      v-loading="loading"
    >
      <el-form-item label="子商户号" prop="config.sub_mch_id">
        <el-input
          v-model.trim="formData.config.sub_mch_id"
          placeholder="请输入子商户号"
          class="input-width"
          maxlength="32"
          show-word-limit
          clearable
        />
        <div class="form-tip">子商户/二级商户的商户号,服务商模式支付</div>
      </el-form-item>
    </el-form>

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
import type { FormInstance } from "element-plus";
import Test from "@/utils/test";

const showDialog = ref(false);
const loading = ref(true);

/**
 * 表单数据
 */
const initialFormData = {
  type: "fastpay_partner_wechatpay",
  config: {
    // sub_appid: "",
    sub_mch_id: "",
    //site_id: "",
  },
  channel: "",
  status: 0,
  is_default: 0,
};
const formData: Record<string, any> = reactive({ ...initialFormData });

const formRef = ref<FormInstance>();

// 表单验证规则
const formRules = computed(() => {
  return {
    "config.sub_mch_id": [
      { required: true, message: "请输入子商户号", trigger: "blur" },
    ],
  };
});

const emit = defineEmits(["complete"]);

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
  if (loading.value || !formEl) return;
  await formEl.validate(async (valid) => {
    if (valid) {
      // formData.config.site_id = JSON.parse(
      //   localStorage.getItem("site.siteInfo")
      // ).site_id;

      emit("complete", formData);
      showDialog.value = false;
    }
  });
};

const setFormData = async (data: any = null) => {
  loading.value = true;
  Object.assign(formData, initialFormData);
  if (data) {
    Object.keys(formData).forEach((key: string) => {
      if (data[key] != undefined) formData[key] = data[key];
    });
    formData.channel = data.redio_key.split("_")[0];
    formData.status = Number(formData.status);
    // 判断data.config是否为空，如果为空则使用本地的config
    if (!data.config || Object.keys(data.config).length === 0) {
      formData.config = { ...initialFormData.config };
    }
  }
  loading.value = false;
};

const enableVerify = () => {
  let verify = true;
  if (Test.empty(formData.config.sub_mch_id)) verify = false;
  return verify;
};

defineExpose({
  showDialog,
  setFormData,
  enableVerify,
});
</script>

<style lang="scss" scoped></style>
