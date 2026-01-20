<template>
  <el-form ref="formRef" :model="formData" :rules="formRules">
    <el-form-item label="" prop="expand" class="!mb-[10px]">
      <div>
        <div class="flex items-center">
          <el-checkbox
            v-model="formData.is_use"
            :true-label="1"
            :false-label="0"
            label=""
            size="large"
          />
          <span class="ml-[10px] el-form-item__label">聚合快递单单返</span>
          <div class="w-[120px]" v-show="formData.is_use">
            <el-input v-model.trim="formData.expand" clearable>
              <template #append>元</template>
            </el-input>
          </div>
        </div>
        <div class="text-sm text-gray-400 mb-[5px]">
          此等级聚合快递会员专享，每单将会额外多返元到可提现余额
        </div>
      </div>
    </el-form-item>
  </el-form>
</template>

<script lang="ts" setup>
import { computed, reactive, ref, watch } from "vue";
import { FormRules } from "element-plus";
import Test from "@/utils/test";

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => {
      return {};
    },
  },
});
const emits = defineEmits(["update:modelValue"]);

const formData = ref({
  is_use: 0,
  expand: "",
});
const formRef = ref(null);

const formRules = reactive<FormRules>({
  expand: [
    {
      validator: (rule: any, value: any, callback: any) => {
        if (formData.value.is_use) {
          if (Test.empty(formData.value.expand)) {
            callback("请输入金额");
          }
          if (formData.value.expand <= 0) {
            callback("金额小于等于0");
          }
          callback();
        } else {
          callback();
        }
      },
    },
  ],
});

const value = computed({
  get() {
    return props.modelValue;
  },
  set(value) {
    emits("update:modelValue", value);
  },
});

watch(
  () => value.value,
  (nval, oval) => {
    if ((!oval || !Object.keys(oval).length) && Object.keys(nval).length) {
      formData.value = value.value;
    }
  },
  { immediate: true }
);

watch(
  () => formData.value,
  () => {
    value.value = formData.value;
  },
  { deep: true }
);

const verify = async () => {
  let verify = true;
  await formRef.value?.validate((valid) => {
    verify = valid;
  });
  return verify;
};

defineExpose({
  verify,
});
</script>

<style lang="scss" scoped>
</style>
