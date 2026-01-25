<template>
  <el-dialog v-model="showDialog" title="安果ERP" width="580px" :destroy-on-close="true">
    <el-form :model="formData" label-width="140px" ref="formRef" :rules="formRules" class="page-form"
      v-loading="loading">
      <el-form-item label="是否启用" prop="is_use">
        <el-radio-group v-model="formData.is_use">
          <el-radio :label="1">启用</el-radio>
          <el-radio :label="0">停用</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="API地址" prop="base_url">
        <el-input v-model="formData.base_url" placeholder="请输入API地址，如：http://115.190.35.168:3000" class="input-width" clearable />
        <div class="text-gray-400 text-sm mt-1">不填写则使用默认地址：http://115.190.35.168:3000</div>
      </el-form-item>

      <el-form-item label="快递公司ID" prop="express_company_id">
        <el-input v-model="formData.express_company_id" placeholder="请输入快递公司ID，默认：2（顺丰）" class="input-width" clearable />
        <div class="text-gray-400 text-sm mt-1">不填写则默认使用顺丰（ID=2）</div>
      </el-form-item>

      <el-form-item label="说明">
        <el-alert type="info" :closable="false" show-icon>
          <template #title>
            <div class="text-sm">
              <p>1. API地址和快递公司ID为可选配置，不填写将使用默认值</p>
              <p class="mt-1">2. 安果ERP无需预询价，用户填写信息后可直接下单</p>
              <p class="mt-1">3. 下单成功后会返回顺丰运单号</p>
            </div>
          </template>
        </el-alert>
      </el-form-item>

      <el-form-item label="常用导航">
        <el-button>
          <a href="http://115.190.35.168:3000" target="_blank">安果ERP后台</a>
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
  base_url: "",
  express_company_id: "",
};
const formData: Record<string, any> = reactive({ ...initialFormData });

const formRef = ref<FormInstance>();

// 表单验证规则
const formRules = computed(() => {
  return {
    is_use: [
      { required: true, message: "请选择是否启用", trigger: "blur" },
    ],
    // base_url 和 express_company_id 都是可选的，不设置为必填
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
