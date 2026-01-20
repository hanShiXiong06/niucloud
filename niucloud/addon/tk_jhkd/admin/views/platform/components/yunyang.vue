<template>
  <el-dialog v-model="showDialog" title="云洋物流" width="580px" :destroy-on-close="true">
    <el-form :model="formData" label-width="140px" ref="formRef" :rules="formRules" class="page-form"
      v-loading="loading">
      <el-form-item label="是否启用" prop="is_use">
        <el-radio-group v-model="formData.is_use">
          <el-radio :label="1">启用</el-radio>
          <el-radio :label="0">停用</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="appid" prop="appid">
        <el-input v-model="formData.appid" placeholder="appid" class="input-width" clearable />
      </el-form-item>

      <el-form-item label="appkey" prop="appkey">
        <el-input v-model="formData.appkey" placeholder="appkey" class="input-width" clearable />
      </el-form-item>
      <el-form-item label="常用导航">
        <el-button>
          <a href="https://open.yunyangwl.com/#/personalCenter" target="_blank">云洋后台</a>
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
  appid: "",
  appkey: "",
};
const formData: Record<string, any> = reactive({ ...initialFormData });

const formRef = ref<FormInstance>();

// 表单验证规则
const formRules = computed(() => {
  return {
    is_use: [
      { required: true, message: "请选择是否启用", trigger: "blur" },
    ],
    appid: [
      { required: true, message: "请输入appid", trigger: "blur" },
    ],
    appkey: [{ required: true, message: "请输入appkey", trigger: "blur" }],

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
