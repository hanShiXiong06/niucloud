<template>
  <el-form ref="formRef" :model="formData" :rules="formRules">
    <el-form-item label="" prop="discount" class="!mb-[10px]">
      <div>
        <div class="flex items-center">
          <el-checkbox v-model="formData.is_use" :true-label="1" :false-label="0" label="" size="large" />
          <span class="ml-[10px] el-form-item__label">聚合快递返佣</span>
          <div class="w-[680px]" v-show="formData.is_use">
            <el-form-item label="" class="" prop="rate">
                <el-input type="number" min="0" max="100" style="width: 160px" v-model="formData.rate" clearable placeholder="请输入分佣比"
                  class="w-[120px]" />
                  <div class="text-slate-400 ml-2">10代表10%；订单实际支付的百分比作为利润；如支付金额10元，店主应得利润=10*10%*店主等级分佣比</div>
              </el-form-item>
            </div>
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
  rate: 50,
});
const formRef = ref(null);

const formRules = reactive<FormRules>({
  rate: [
    {
      validator: (rule: any, value: any, callback: Function) => {
        if (formData.value.is_use) {
          if (Test.empty(formData.value.rate)) {
            callback("请输入分佣比例");
          }
          if (
            parseFloat(formData.value.rate) > 100
          ) {
            callback("分佣比例只能输入0~100之间的值");
          }
          if (formData.value.rate < 0) {
            callback("分佣比例不能小于0");
          }
          callback();
        } else {
          callback();
        }
      },
    },],
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

<style lang="scss" scoped></style>
