<template>
  <el-form ref="formRef" :model="formData" :rules="formRules">
    <el-form-item label="" prop="discount" class="!mb-[10px]">
      <div>
        <div class="flex items-center">
          <el-checkbox v-model="formData.is_use" :true-label="1" :false-label="0" label="" size="large" />
          <span class="ml-[10px] el-form-item__label">聚合快递分销</span>
          <div class="w-[680px]" v-show="formData.is_use">
           <el-form-item label="佣金方式" class="" prop="way">
              <el-radio-group v-model="formData.way" size="large">
                <el-radio-button label="rate">比例</el-radio-button>
                <el-radio-button label="commission">固定金额</el-radio-button>
              </el-radio-group>
            </el-form-item>
           <!--   <el-form-item label="分佣字段" class="" prop="field">
              <el-radio-group v-model="formData.field" size="large">
                <el-radio-button label="order_money">订单金额</el-radio-button>
                <el-radio-button label="pay_money">实付金额</el-radio-button>
                <el-radio-button label="commission">佣金</el-radio-button>
              </el-radio-group>
            </el-form-item> -->
            <el-form-item v-if="formData.way == 'rate'" label="一级分佣" class="" prop="child_rate">
              <el-input type="number" min="0" max="100" style="width: 160px" v-model="formData.child_rate" clearable
                placeholder="请输入一级佣金比" class="w-[120px]" />
              <span class="ml-2 text-gray-400">按照实际支付的{{ formData.child_rate }}%返佣</span>
            </el-form-item>
            <el-form-item v-if="formData.way == 'rate'" label="二级分佣" class="" prop="two_rate">
              <el-input type="number" min="0" max="100" style="width: 160px" v-model="formData.two_rate" clearable
                placeholder="请输入二级佣金比" class="w-[120px]" />
              <span class="ml-2 text-gray-400">按照实际支付的{{ formData.two_rate }}%返佣</span>
            </el-form-item>
             <el-form-item v-if="formData.way == 'commission'" label="一级分佣" class="" prop="child_commission">
              <el-input type="number" min="0" max="100" style="width: 160px" v-model="formData.child_commission" clearable
                placeholder="请输入一级佣金" class="w-[120px]" />
              <span class="ml-2 text-gray-400">按照{{ formData.child_commission }}元固定返佣</span>
            </el-form-item>
            <el-form-item v-if="formData.way == 'commission'" label="二级分佣" class="" prop="two_commission">
              <el-input type="number" min="0" max="100" style="width: 160px" v-model="formData.two_commission" clearable
                placeholder="请输入二级佣金" class="w-[120px]" />
              <span class="ml-2 text-gray-400">按照{{ formData.two_commission }}元固定返佣</span>
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
  way: "rate",//rate,commission
  field: 'pay_money',//order_money,pay_money,commission
  child_rate: 4,
  two_rate: 4,
  child_commission: 0,
  two_commission: 0,
});
const formRef = ref(null);

const formRules = reactive<FormRules>({
  child_rate: [
    {
      validator: (rule: any, value: any, callback: Function) => {
        if (formData.value.is_use) {
          if (Test.empty(formData.value.child_rate)) {
            callback("请输入一级分销人数");
          }
          if (formData.value.child_rate < 0) {
            callback("一级分销人数不能小于0");
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
