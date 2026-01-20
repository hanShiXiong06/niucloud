<template>
  <el-dialog v-model="showDialog" title="快递鸟" width="580px" :destroy-on-close="true">
    <el-form :model="formData" label-width="140px" ref="formRef" :rules="formRules" class="page-form"
      v-loading="loading">
      <el-form-item label="是否启用">
        <el-radio-group v-model="formData.is_use" prop="is_use">
          <el-radio :label="1">启用</el-radio>
          <el-radio :label="0">停用</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="business_id" prop="business_id">
        <el-input v-model="formData.business_id" placeholder="商户ID" class="input-width" clearable />
      </el-form-item>

      <el-form-item label="api_key" prop="api_key">
        <el-input v-model="formData.api_key" placeholder="密钥" class="input-width" clearable />
      </el-form-item>
      <el-form-item label="产品类型" prop="shipper_type">
        <el-radio-group v-model="formData.shipper_type">
          <el-radio :label="1">特快（仅支持顺丰）</el-radio>
          <el-radio :label="3">2小时收</el-radio>
          <el-radio :label="4">半日收</el-radio>
          <el-radio :label="5">当日收</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="环境类型" prop="is_test">
        <el-radio-group v-model="formData.is_test">
          <el-radio :label="1">测试环境</el-radio>
          <el-radio :label="0">正式环境</el-radio>
        </el-radio-group>
        <span class="ml-2 text-gray-400">正式使用请切换正式环境</span>
      </el-form-item>
      <el-form-item label="常用导航">
        <el-button>
          <a href="https://biz.kdniao.com/login" target="_blank">快递鸟后台</a>
        </el-button>
      </el-form-item>
    </el-form>

    <template #footer>
      <span class="dialog-footer">
        <el-button @click="showDialog = false">取消</el-button>
        <el-button type="primary" :loading="loading" @click="confirm(formRef)">确认</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from "vue";
import { t } from "@/lang";
import type { FormInstance } from "element-plus";
import { getPlatformInfo, editPlatform } from "@/addon/tk_jhkd/api/platform";

const showDialog = ref(false);
const loading = ref(true);

/**
 * 表单数据
 */
const initialFormData = {
  type: "",
  is_use: "",
  business_id: "",
  api_key: "",
  shipper_type: 5,
  is_test: 0,
};
const formData: Record<string, any> = reactive({ ...initialFormData });

const formRef = ref<FormInstance>();

// 表单验证规则
const formRules = computed(() => {
  return {
    is_use: [
      { required: true, message: "请选择是否启用", trigger: "blur" },
    ],
    business_id: [
      { required: true, message: "请输商户ID", trigger: "blur" },
    ],
    api_key: [{ required: true, message: "请输入密钥", trigger: "blur" }],
    shipper_type: [{ required: true, message: "请选择产品类型", trigger: "blur" }],
    is_test: [{ required: true, message: "请选择环境类型", trigger: "blur" }],
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
      loading.value = true;

      const data = formData;

      editPlatform(data)
        .then((res) => {
          loading.value = false;
          showDialog.value = false;
          emit("complete");
        })
        .catch(() => {
          loading.value = false;
          // showDialog.value = false
        });
    }
  });
};

const setFormData = async (row: any = null) => {
  loading.value = true;
  Object.assign(formData, initialFormData);
  if (row) {
    const data = await (await getPlatformInfo(row.type)).data;
    Object.keys(formData).forEach((key: string) => {
      if (data[key] != undefined) formData[key] = data[key];
      if (data.params[key] != undefined) formData[key] = data.params[key].value;
    });
  }
  loading.value = false;
};
defineExpose({
  showDialog,
  setFormData,
});
</script>

<style lang="scss" scoped></style>
