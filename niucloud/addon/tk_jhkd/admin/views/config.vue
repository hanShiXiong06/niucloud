<template>
  <div class="main-container">
    <el-form :model="formData" label-width="150px" ref="ruleFormRef" :rules="rules" class="page-form"
      v-loading="loading">
      <el-card class="box-card !border-none" shadow="never">
        <el-button @click="addPluginEvent" :loading="pluginLoading" type="primary" class="mb-4">申请小程序插件</el-button>
        <div style="width: 840px" class="mb-2">
          <el-alert type="info" title="提示:1、请逐项完善基础配置；2、请配置好阿里物流查询APPCODE;3、请在各平台配置回调地址" :closable="false" show-icon />
        </div>

        <el-form-item label="地址解析" prop="autosend">
          <el-radio-group v-model="formData.address_use">
            <el-radio :label="'0'">关闭</el-radio>
            <el-radio :label="'1'">开启</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="formData.address_use == 1">
          <el-button>
            <a href="https://market.cloud.tencent.com/products/37790?keyword=%E5%BF%AB%E9%80%92%E5%9C%B0%E5%9D%80#spec=0.00%E5%85%83%2F10%E6%AC%A1"
              target="_blank">购买地址</a>
          </el-button>
          <div class="ml-4">
            <span class="mr-2">腾讯云市场secretId</span>
            <el-input clearable v-model="formData.tx_id" style="width: 200px" placeholder="腾讯云市场购买后分配" />
          </div>
          <div class="ml-4">
            <span class="mr-2">腾讯云市场secretKey</span>
            <el-input clearable v-model="formData.tx_secret" style="width: 200px" placeholder="腾讯云市场购买后分配" />
          </div>
        </el-form-item>
        <el-form-item label="阿里云物流解析">
          <el-button>
            <a href="https://market.aliyun.com/apimarket/detail/cmapi00066593#sku=yuncode6059300002"
              target="_blank">购买地址</a>
          </el-button>
          <div class="ml-4">
            <span class="mr-2">AppCode</span>
            <el-input clearable v-model="formData.appcode" style="width: 200px" placeholder="阿里云市场购买后分配" />
          </div>

        </el-form-item>
        <el-form-item label="发单方式" prop="autosend">
          <el-radio-group v-model="formData.autosend">
            <el-radio :label="'0'">手动</el-radio>
            <el-radio :label="'1'">自动</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item :label="t('floatWay')" prop="floatWay">
          <el-radio-group v-model="formData.floatWay">
            <el-radio :label="'floatWayFixed'">{{
              t("floatWayFixed")
            }}</el-radio>
            <el-radio :label="'floatWayRate'">{{ t("floatWayRate") }}</el-radio>
            <el-radio :label="'floatWayBetwn'">{{
              t("floatWayBetwn")
            }}</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item :label="t('floatAmount')" prop="floatAmount" v-if="formData.floatWay == 'floatWayFixed'">
          <el-input v-model="formData.floatAmount" style="width: 200px" :placeholder="t('floatAmountPlaceholder')" />
          <span class="ml-[4px]">元 首重和续重均会加价</span>
        </el-form-item>

        <el-form-item :label="t('floatRate')" prop="floatRate" v-if="formData.floatWay == 'floatWayRate'">
          <el-input v-model="formData.floatRate" style="width: 200px" :placeholder="t('floatRatePlaceholder')" />
          <span class="ml-[4px]">%，首重续重就会按照此比进行计算</span>
        </el-form-item>
        <el-form-item v-if="formData.floatWay == 'floatWayBetwn'">
          <span class="ml-[4px] mr-2">首重加价</span>
          <el-input v-model="formData.firstAmount" style="width: 80px" :placeholder="t('floatRatePlaceholder')" />
          <span class="ml-[4px] mr-2 ml-6">续重加价</span>
          <el-input v-model="formData.secondAmount" style="width: 80px" :placeholder="t('floatRatePlaceholder')" />
        </el-form-item>
        <el-form-item label="取消订单">
          <el-input v-model="formData.cancelmin" style="width: 80px" placeholder="单位分" />
          <span class="ml-2">单位:分</span>
        </el-form-item>
        <el-form-item label="发货管理" prop="autosend">
          <el-radio-group v-model="formData.is_shipping">
            <el-radio :label="'0'">关闭</el-radio>
            <el-radio :label="'1'">开启</el-radio>
          </el-radio-group>
          <span class="ml-2 text-gray-500">仅小程序开通了发货管理需要开启,会自动进行虚拟下单发货</span>
        </el-form-item>

        <el-card class="!border-none" shadow="never" style="width: 640px">
          <el-alert type="info" title="开启配置通知，订单新增，催收等会同步到webhook群" :closable="false" show-icon />
        </el-card>
        <el-form-item label="">
          <el-radio-group v-model="formData.is_webhook">
            <el-radio :label="'0'">否</el-radio>
            <el-radio :label="'1'">是</el-radio>
          </el-radio-group>
          <span class="ml-4">是否开启群webhook通知</span>
        </el-form-item>

        <el-form-item class="font-bold text-xs" label="通知类型" prop="checktype">
          <el-select v-model="formData.webhook_type" clearable placeholder="请选择">
            <el-option key="0" label="企业微信" value="0" />
            <el-option key="1" label="飞书" value="1" />
            <el-option key="2" label="钉钉" value="2" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="formData.webhook_type == 0" class="font-bold text-xs" label="Webhook 地址" prop="action">
          <el-input clearable v-model="formData.qwurl" class="w-60" placeholder="请输入webhook地址" />
        </el-form-item>
        <el-form-item v-if="formData.webhook_type == 1" class="font-bold text-xs" label="Webhook 地址" prop="msg">
          <el-input type="textarea" clearable v-model="formData.fsurl" style="width: 300px"
            placeholder="请输入webhook地址" />
        </el-form-item>
        <el-form-item v-if="formData.webhook_type == 2" class="font-bold text-xs" label="Webhook 地址" prop="msg">
          <el-input clearable v-model="formData.ddurl" class="w-60" placeholder="请输入webhook地址" />
        </el-form-item>
        <el-form-item class="font-bold text-xs" label="通知频率" prop="msg">
          <el-input clearable v-model="formData.min" style="width: 200px" placeholder="以分钟计算，默认30分钟" />
          <span class="ml-4"> 单位:分钟，默认30分内只通知一次,0不限制</span>
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
import { reactive, ref } from "vue";
import { t } from "@/lang";
import {
  getJhkdConfig,
  setJhkdConfig, addPlugin
} from "@/addon/tk_jhkd/api/tkjhkd";
import { FormInstance, ElMessage } from "element-plus";
const pluginLoading = ref(false);
const addPluginEvent = async () => {
  try {
    pluginLoading.value = true;
    const res = await addPlugin();
    ElMessage.success("操作成功");
  } catch (error) {
    ElMessage.error("申请失败");
  } finally {
    pluginLoading.value = false;
  }
};
const loading = ref(true);
const ruleFormRef = ref<FormInstance>();
const balance = ref();
const formData = reactive({
  username: "",
  privateKey: "",
  floatWay: "floatWayFixed",
  floatAmount: "2",
  firstAmount: "2",
  secondAmount: "2",
  floatRate: "10",
  bindMobile: "",
  autosend: "1",
  cancelmin: "120",
  address_use: "0",
  tx_id: "",
  tx_secret: "",
  is_webhook: "0",
  webhook_type: "0",
  qwurl: "",
  fsurl: "",
  ddurl: "",
  min: "30",
  appcode: "",
  is_shipping: "0",
});
const getData = async () => {
  const data = await getJhkdConfig({});
  loading.value = false;
  for (const key in formData) {
    formData[key] = data.data.value[key];
  }

};
getData();
const onSave = async () => {
  await setJhkdConfig(formData);
  getData();
};
</script>

<style lang="scss" scoped></style>
