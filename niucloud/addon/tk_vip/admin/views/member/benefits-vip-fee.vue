<template>
  <el-form ref="formRef" :model="formData" :rules="formRules" class="benefits-form">
    <el-form-item label="" prop="discount" class="mb-3">
      <div class="benefits-container">
        <div class="flex items-center mb-4">
          <el-checkbox v-model="formData.is_use" :true-label="1" :false-label="0" label="" size="large" />
          <span class="ml-3 text-base font-medium">付费升级</span>
        </div>

        <div v-show="formData.is_use" class="pl-4">
          <!-- 各模块卡片 -->
          <el-card v-for="(item, index) in cardItems" :key="index" class="benefit-card mb-4">
            <!-- 卡片头部 -->
            <template #header>
              <div class="flex items-center justify-between">
                <span class="font-medium">{{ item.title }}</span>
                <!-- 开关选项 -->
                <el-radio-group v-if="item.switchKey" v-model="formData[item.switchKey]" class="ml-4">
                  <el-radio :label="item.offValue || 0">{{ item.offLabel || '关闭' }}</el-radio>
                  <el-radio :label="item.onValue || 1">{{ item.onLabel || '开启' }}</el-radio>
                </el-radio-group>
                <!-- 添加规格按钮 -->
                <el-button v-if="item.key === 'spec'" @click="addSpec()" type="primary" size="small">添加规格</el-button>
              </div>
            </template>

            <!-- 卡片内容 -->
            <div class="card-content">
              <!-- 实名认证模块 -->
              <template v-if="item.key === 'real'">
                <div class="px-1">
                  <span class="text-gray-400 text-sm" v-if="formData.is_real == 1">需在插件实名认证配置里面开启才会生效</span>
                </div>
              </template>

              <!-- 自购返佣模块 -->
              <template v-if="item.key === 'self'">
                <div v-if="formData.is_self == 1" class="pl-1">
                  <div class="text-sm text-gray-400 mb-4">开启自购返佣将会给拥有权限的分销自购返佣一级佣金，上级二级佣金</div>

                  <el-form-item label="自购返佣" class="mb-4" prop="self_commission_rate">
                    <div class="flex items-center">
                      <el-input v-model="formData.self_commission_rate" clearable placeholder="请输入自购返佣金额"
                        class="form-input" />
                      <span class="ml-3 text-gray-400 text-sm">将返佣{{ formData.self_commission_rate }}%佣金给自购用户</span>
                    </div>
                  </el-form-item>

                  <el-form-item label="自购返积分" class="mb-4" prop="self_point_rate">
                    <div class="flex items-center">
                      <el-input v-model="formData.self_point_rate" clearable placeholder="请输入自购返积分"
                        class="form-input" />
                      <span class="ml-3 text-gray-400 text-sm">将返佣约{{ formData.self_point_rate }}%积分给自购用户</span>
                    </div>
                  </el-form-item>
                </div>
                <div v-else class="text-sm text-gray-400 px-1">
                  开启后将允许自购用户获得返佣和积分奖励
                </div>
              </template>

              <!-- 佣金分销模块 -->
              <template v-if="item.key === 'commission'">
                <div v-if="formData.is_fenxiao_commission == 1" class="pl-1">
                  <el-form-item label="一级分销" class="mb-4" prop="first_commission_rate">
                    <div class="flex items-center">
                      <el-input v-model="formData.first_commission_rate" clearable placeholder="请输入一级分销金额"
                        class="form-input" />
                      <span class="ml-3 text-gray-400 text-sm">将分佣{{ formData.first_commission_rate }}%佣金给一级分销</span>
                    </div>
                  </el-form-item>

                  <el-form-item label="二级分销" class="mb-4" prop="second_commission_rate">
                    <div class="flex items-center">
                      <el-input v-model="formData.second_commission_rate" clearable placeholder="请输入二级分销金额"
                        class="form-input" />
                      <span class="ml-3 text-gray-400 text-sm">将分佣{{ formData.second_commission_rate }}%佣金给二级分销</span>
                    </div>
                  </el-form-item>
                </div>
                <div v-else class="text-sm text-gray-400 px-1">
                  开启后将允许分销商获得佣金奖励
                </div>
              </template>

              <!-- 积分分销模块 -->
              <template v-if="item.key === 'point'">
                <div v-if="formData.is_fenxiao_point == 1" class="pl-1">
                  <el-form-item label="一级分销" class="mb-4" prop="first_point_rate">
                    <div class="flex items-center">
                      <el-input v-model="formData.first_point_rate" clearable placeholder="请输入一级分销积分"
                        class="form-input" />
                      <span class="ml-3 text-gray-400 text-sm">将分佣约{{ formData.first_point_rate }}%积分给一级分销，四舍五入取整</span>
                    </div>
                  </el-form-item>

                  <el-form-item label="二级分销" class="mb-4" prop="second_point_rate">
                    <div class="flex items-center">
                      <el-input v-model="formData.second_point_rate" clearable placeholder="请输入二级分销积分"
                        class="form-input" />
                      <span class="ml-3 text-gray-400 text-sm">将分佣约{{ formData.second_point_rate
                      }}%积分给二级分销，四舍五入取整</span>
                    </div>
                  </el-form-item>
                </div>
                <div v-else class="text-sm text-gray-400 px-1">
                  开启后将允许分销商获得积分奖励
                </div>
              </template>

              <!-- 商城关联分销设置模块 -->
              <template v-if="item.key === 'shop_fenxiao'">
                <div class="px-1">
                  <div class="text-sm text-gray-400 mb-4">
                    开启后当会员等级到期时，将自动取消该会员在商城的分销资格
                  </div>

                  <el-form-item label="关联分销等级" prop="fenxiao_level_id" class="mb-4">
                    <div class="flex items-center">
                      <el-select v-model="formData.fenxiao_level_id" placeholder="选择分销等级" class="form-input">
                        <el-option v-for="item in shopFenxiaoLevel" :key="item.level_id" :label="item.level_name"
                          :value="item.level_id" />
                      </el-select>
                      <span class="ml-3 text-gray-400 text-sm">会员升级后可关联对应的分销等级</span>
                    </div>
                  </el-form-item>
                </div>
              </template>

              <!-- 规格设置模块 -->
              <template v-if="item.key === 'spec'">
                <div class="text-sm text-gray-400 mb-4 px-1">
                  名称可填写如日卡，季度卡等，等级有效期单位天，0为永久不限制；限制购买数量0将不会限制；会员等级到期后将会回退到默认等级
                </div>

                <div v-if="formData.fee_info && formData.fee_info.length > 0" class="spec-list">
                  <div v-for="(specItem, specIndex) in formData.fee_info" :key="specIndex"
                    class="spec-item mb-4 p-4 rounded">
                    <div class="flex items-center justify-between mb-3">
                      <span class="font-medium">规格 #{{ specIndex + 1 }}</span>
                      <div class="flex items-center">
                        <el-tooltip :content="specItem.is_use == 0 ? '下架中' : '使用中'" placement="top">
                          <el-switch v-model="specItem.is_use" class="mr-4" active-value="1" inactive-value="0" />
                        </el-tooltip>
                        <el-button @click="delSpec(specIndex)" type="danger" size="small" plain>删除</el-button>
                      </div>
                    </div>

                    <el-row :gutter="16">
                      <el-col :span="12" class="mb-3">
                        <div class="form-group">
                          <span class="form-label">名称<span class="text-red-500">*</span></span>
                          <el-input v-model="specItem.name" placeholder="如日卡" />
                        </div>
                      </el-col>

                      <el-col :span="12" class="mb-3">
                        <div class="form-group">
                          <span class="form-label">付费金额<span class="text-red-500">*</span></span>
                          <el-input v-model="specItem.price" placeholder="售卖价格" type="number" min="0" />
                        </div>
                      </el-col>

                      <el-col :span="12" class="mb-3">
                        <div class="form-group">
                          <span class="form-label">到期类型</span>
                          <el-select v-model="specItem.over_type" placeholder="选择到期类型" class="w-full">
                            <el-option key="common" label="天数" value="common" />
                            <el-option key="fixed" label="固定到期" value="fixed" />
                          </el-select>
                        </div>
                      </el-col>

                      <el-col :span="12" class="mb-3">
                        <div v-if="specItem.over_type == 'common'" class="form-group">
                          <span class="form-label">有效期<span class="text-red-500">*</span></span>
                          <div class="flex items-center">
                            <el-input v-model="specItem.day" placeholder="请输入" type="number" min="0" />
                            <span class="ml-2">天</span>
                          </div>
                        </div>
                        <div v-else-if="specItem.over_type == 'fixed'" class="form-group">
                          <span class="form-label">到期时间<span class="text-red-500">*</span></span>
                          <el-date-picker v-model="specItem.over_time" type="datetime" placeholder="请选择时间"
                            format="YYYY-MM-DD HH:mm:ss" class="w-full" />
                        </div>
                      </el-col>
                    </el-row>
                  </div>
                </div>

                <div v-else class="text-center py-6 bg-gray-50 rounded text-gray-400">
                  暂无规格，请点击"添加规格"按钮添加
                </div>
              </template>
            </div>
          </el-card>
        </div>

        <div class="text-sm text-gray-400 mt-2 px-1">
          开启后当前等级将等级权益将可以通过用户自主付费购买升级
        </div>
      </div>
    </el-form-item>
  </el-form>
</template>

<script lang="ts" setup>
import { computed, reactive, ref, watch } from "vue";
import { FormRules } from "element-plus";
import { guid } from "@/utils/common";
import Test from "@/utils/test";
import { getShopFenxiaoLevel } from "@/addon/tk_vip/api/config";
const is_shop_fenxiao = ref(0);
const shopFenxiaoLevel = ref([]);
const getShopFenxiaoLevelData = async () => {
  const res = await getShopFenxiaoLevel();
  shopFenxiaoLevel.value = res.data.level;
  is_shop_fenxiao.value = res.data.is_shop_fenxiao;
};
getShopFenxiaoLevelData();
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
  fee_info: [],
  is_real: 0, //是否需要实名认证
  //进行分销相关设置
  is_fenxiao_commission: 0,
  first_commission_rate: 5,
  second_commission_rate: 2,
  is_fenxiao_point: 0,
  first_point_rate: 5,
  second_point_rate: 2,
  fenxiao_level_id: 0,//不关联
  close_shop_fenxiao: 0,//到期取消商城分销
  is_self: 0,
  self_commission_rate: 2, // 修正变量名
  self_point_rate: 10,
});

// 使用计算属性，根据是否开启商城分销动态生成卡片项
const cardItems = computed(() => {
  const items = [
    {
      key: 'real',
      title: '实名认证',
      switchKey: 'is_real',
      onValue: '1',
      offValue: '0',
      onLabel: '需要',
      offLabel: '不需要'
    },
    {
      key: 'self',
      title: '自购返佣',
      switchKey: 'is_self',
      onValue: 1,
      offValue: 0
    },
    {
      key: 'commission',
      title: '佣金分销',
      switchKey: 'is_fenxiao_commission',
      onValue: '1',
      offValue: '0',
      onLabel: '开启',
      offLabel: '不开启'
    },
    {
      key: 'point',
      title: '积分分销',
      switchKey: 'is_fenxiao_point',
      onValue: '1',
      offValue: '0',
      onLabel: '开启',
      offLabel: '不开启'
    }
  ];

  // 只有当商城分销功能开启时，才添加商城关联分销卡片
  if (is_shop_fenxiao.value === 1) {
    items.push({
      key: 'shop_fenxiao',
      title: '商城关联分销',
      switchKey: 'close_shop_fenxiao',
      onValue: 1,
      offValue: 0,
      onLabel: '开启',
      offLabel: '关闭'
    });
  }

  // 添加会员规格卡片
  items.push({
    key: 'spec',
    title: '会员规格'
  });

  return items;
});

const formRef = ref(null);

const addSpec = () => {
  // 确保 feeInfo 是一个数组
  if (!Array.isArray(formData.value.fee_info)) {
    formData.value.fee_info = [];
  }
  formData.value.fee_info.push({
    id: guid(),
    name: "",
    market_price: 0,
    price: 0,
    day: 0,
    limit_num: 0,
    num: 0,
    is_use: "1",
    over_type: "common",
    over_time: "",
  });
};

const delSpec = (index) => {
  formData.value.fee_info.splice(index, 1);
};

const formRules = reactive<FormRules>({});

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
.benefits-container {
  @apply rounded-md;
}

.form-label {
  @apply text-gray-700 font-medium mb-2 block;
}

.form-group {
  @apply flex flex-col;
}

.form-input {
  @apply w-40;
}

.benefits-form :deep(.el-form-item__label) {
  @apply font-medium text-gray-700;
}

.spec-list {
  @apply mt-4;
}

.spec-item {
  @apply border border-gray-200 bg-white transition-all;

  &:hover {
    @apply border-blue-100 shadow-md;
  }
}

.card-content {
  @apply p-1;
}

/* 统一卡片样式 */
:deep(.el-card) {
  @apply mb-4 border border-gray-100 overflow-visible;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

:deep(.el-card__header) {
  @apply py-3 px-4 bg-gray-50 border-b border-gray-100;
}

:deep(.el-card__body) {
  @apply p-4;
}

/* 统一表单元素样式 */
:deep(.el-input__wrapper),
:deep(.el-select__wrapper) {
  @apply transition-all shadow-sm;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);

  &:hover {
    @apply border-blue-300;
  }

  &:focus-within {
    @apply border-blue-500 ring-1 ring-blue-200;
  }
}

:deep(.el-button--primary) {
  @apply bg-blue-500 border-blue-500;

  &:hover {
    @apply bg-blue-600 border-blue-600;
  }
}

/* 单选按钮组样式 */
:deep(.el-radio) {
  @apply mr-4;

  &.is-checked .el-radio__label {
    @apply text-blue-500;
  }
}

:deep(.el-form-item__content) {
  @apply flex-wrap;
}

:deep(.el-form-item) {
  @apply mb-4;
}
</style>
