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
            title="请设置完善支付后跳转信息，微信小程序支持跳转到视频号，其他渠道只支持跳转选择或者自定义链接，这里是集成到框架里面的商户生效"
            :closable="false"
            show-icon
          />
        </div>
        <el-form-item label="商户图标" prop="business_logo">
          <upload-image v-model="formData.business_logo" />
        </el-form-item>
        <el-form-item label="商户名称" prop="business_name">
          <el-input
            v-model="formData.business_name"
            style="width: 200px"
            placeholder="请输入收款商户名称"
          />
        </el-form-item>

        <el-form-item label="支付跳转" prop="type">
          <el-radio-group v-model="formData.type">
            <el-radio :label="'0'">视频号主页</el-radio>
            <el-radio :label="'1'">视频号视频</el-radio>
            <el-radio :label="'2'">系统链接</el-radio>
          </el-radio-group>
          <p class="ml-2">支付成功后执行动作，仅小程序支持视频号相关跳转</p>
        </el-form-item>
        <el-form-item
          v-if="formData.type == 0 || formData.type == 1"
          label="视频号ID"
          prop="finderUserName"
        >
          <el-input
            v-model="formData.finderUserName"
            style="width: 200px"
            placeholder="请输入视频号ID"
          />
        </el-form-item>
        <el-form-item v-if="formData.type == 1" label="视频ID" prop="feedId">
          <el-input
            type="textarea"
            v-model="formData.feedId"
            style="width: 200px"
            placeholder="请输入视频ID"
          />
          <!-- <p class="ml-2">支持多个视频请用‘,’分开，会随机分配一个视频</p> -->
        </el-form-item>

        <el-form-item v-if="formData.type == 2" label="选择链接" prop="page">
          <diy-link v-model="formData.page" />
        </el-form-item>

        <el-form-item>
          <div class="mt-2">
            <el-button
              style="width: 120px"
              type="primary"
              plain
              @click="getPosterEvent('wechat')"
            >
              公众号收款码
            </el-button>
            <el-button
              class="ml-2"
              style="width: 120px"
              type="primary"
              plain
              @click="getPosterEvent('weapp')"
            >
              小程序收款码
            </el-button>
          </div>
        </el-form-item>
      </el-card>
    </el-form>

    <div class="fixed-footer-wrap">
      <div class="fixed-footer">
        <el-button type="primary" @click="onSave()">{{ t("save") }}</el-button>
      </div>
    </div>
  </div>
  <el-dialog v-model="posterShow" title="商家收款码" align-center width="420px">
    <div class="">
      <el-image :src="posterlink" alt="" />
    </div>
  </el-dialog>
</template>

<script lang="ts" setup>
import { reactive, ref, computed } from "vue";
import { t } from "@/lang";
import { img } from "@/utils/common";
import { getConfig, setConfig, poster } from "@/addon/fast_pay/api/config";
import { FormInstance, ElMessage } from "element-plus";
const posterlink = ref();
const posterShow = ref(false);
const saveImage = () => {
  const a = document.createElement("a");
  a.href = posterlink.value;
  a.download = "收款码.png";
  a.click();
};

const getPosterEvent = async (channel) => {
  const data = await poster({ channel: channel });
  posterlink.value = img(data.data);
  posterShow.value = true;
};
const loading = ref(true);
const ruleFormRef = ref<FormInstance>();
const formData = reactive({
  business_name: "",
  business_logo: "",
  banener: "",
  desc: "",
  type: "0",
  finderUserName: "",
  feedId: "",
  page: "",
});
// 表单验证规则
const formRules = computed(() => {
  return {
    business_name: [
      { required: true, message: "请输入商户名称", trigger: "blur" },
    ],
    business_logo: [
      { required: true, message: "请上传商户LOGO", trigger: "blur" },
    ],
    type: [
      { required: true, message: "请选择支付后跳转类型", trigger: "blur" },
    ],
    finderUserName: [
      { required: true, message: "请填写视频号ID", trigger: "blur" },
    ],
    feedId: [{ required: true, message: "请填写视频ID", trigger: "blur" }],
    page: [{ required: true, message: "请选择跳转链接", trigger: "blur" }],
  };
});

const getData = async () => {
  const data = await getConfig();
  loading.value = false;
  for (const key in formData) {
    formData[key] = data.data[key];
  }
};
getData();
const onSave = async () => {
  await setConfig(formData);
  getData();
};
</script>

<style lang="scss" scoped></style>
