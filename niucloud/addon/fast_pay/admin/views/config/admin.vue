<template>
  <div class="main-container">
    <el-form
      :model="formData"
      label-width="150px"
      ref="ruleFormRef"
      :rules="formRules"
      class="page-form"
      v-loading="loading"
    >
      <el-card class="box-card !border-none" shadow="never">
        <div class="mb-4" style="width: 640px">
          <el-alert
            type="info"
            title="配置微信支付服务商信息，站点可以使用服务商模式进行支付，支付通道名称“微信快捷支付”"
            :closable="false"
            show-icon
          />
        </div>
        <el-form-item label="服务商APPID" prop="app_id">
          <el-input
            v-model="formData.app_id"
            style="width: 200px"
            placeholder="请输入服务商appid"
          />
        </el-form-item>
        <el-form-item label="服务商商户号" prop="mch_id">
          <el-input
            v-model="formData.mch_id"
            style="width: 200px"
            placeholder="请输入服务商商户号"
          />
        </el-form-item>
        <el-form-item label="V3密钥" prop="mch_secret_key">
          <el-input
            v-model="formData.mch_secret_key"
            style="width: 200px"
            placeholder="请输入服务商V3密钥"
          />
        </el-form-item>
        <el-form-item label="上传私钥证书" prop="mch_secret_cert">
          <div class="input-width">
            <upload-file
              v-model="formData.mch_secret_cert"
              api="sys/document/wechat"
            />
          </div>
          <div class="form-tip">微信支付API证书（apiclient_key.pem）</div>
        </el-form-item>
        <el-form-item label="上传公钥证书" prop="mch_public_cert_path">
          <div class="input-width">
            <upload-file
              v-model="formData.mch_public_cert_path"
              api="sys/document/wechat"
            />
          </div>
          <div class="form-tip">微信支付API证书（apiclient_cert.pem）</div>
        </el-form-item>
      </el-card>
    </el-form>

    <div class="fixed-footer-wrap">
      <div class="fixed-footer">
        <el-button type="primary" @click="onSave()">{{ t("save") }}</el-button>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, computed } from "vue";
import { t } from "@/lang";
import { img } from "@/utils/common";
import { getAdminConfig, setAdminConfig } from "@/addon/fast_pay/api/config";
import { FormInstance, ElMessage } from "element-plus";

const loading = ref(true);
const ruleFormRef = ref<FormInstance>();
const formData = reactive({
  app_id: "",
  mch_id: "",
  mch_secret_key: "",
  mch_secret_cert: "",
  mch_public_cert_path: "",
});
// 表单验证规则
const formRules = computed(() => {
  return {
    app_id: [
      { required: true, message: "请输入服务商商户名称", trigger: "blur" },
    ],
    mch_id: [
      { required: true, message: "请输入服务商商户名称", trigger: "blur" },
    ],
    mch_secret_key: [
      { required: true, message: "请输入V3密钥", trigger: "blur" },
    ],
    mch_secret_cert: [
      { required: true, message: "请上传私钥", trigger: "blur" },
    ],
    mch_public_cert_path: [
      { required: true, message: "请上传公钥", trigger: "blur" },
    ],
  };
});

const getData = async () => {
  const data = await getAdminConfig();
  console.log(data);
  loading.value = false;
  for (const key in formData) {
    formData[key] = data.data[key];
  }
};
getData();
const onSave = async () => {
  await setAdminConfig(formData);
  getData();
};
</script>

<style lang="scss" scoped></style>
