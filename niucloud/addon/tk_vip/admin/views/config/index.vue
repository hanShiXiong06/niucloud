<template>
  <div class="main-container">
    <el-form :model="formData" label-width="150px" ref="ruleFormRef" :rules="rules" class="page-form"
      v-loading="loading">

      <!-- 新用户赠送配置 -->
      <el-card class="mb-4 !border-none config-card" shadow="hover">
        <template #header>
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <el-icon class="mr-2 text-blue-500" :size="20">
                <user />
              </el-icon>
              <span class="text-base font-bold text-gray-800">新用户注册赠送</span>
            </div>
            <el-tag type="primary" size="small">会员等级配置</el-tag>
          </div>
        </template>

        <el-alert type="info" :closable="false" show-icon class="mb-4">
          <template #title>
            <span class="text-sm">新用户注册时自动赠送会员等级权益，选择"0"代表最初始的默认等级</span>
          </template>
        </el-alert>

        <el-form-item label="赠送会员等级" prop="level_id" class="mb-6">
          <div class="flex items-center gap-3 flex-wrap">
            <el-select v-model="formData.level_id" clearable placeholder="请选择会员等级" class="select-custom" size="large">
              <template #prefix>
                <el-icon class="text-blue-500">
                  <medal />
                </el-icon>
              </template>
              <el-option label="默认等级(0)" value="" />
              <el-option v-for="(item, index) in levelIdList" :key="index" :label="item['level_name']"
                :value="item['level_id']" />
            </el-select>
          </div>
        </el-form-item>

        <el-form-item label="有效期设置" prop="over_type" class="mb-6">
          <div class="flex items-center gap-3 flex-wrap">
            <el-select v-model="formData.over_type" placeholder="选择到期类型" class="select-custom" size="large">
              <template #prefix>
                <el-icon class="text-purple-500">
                  <timer />
                </el-icon>
              </template>
              <el-option key="common" label="天数" value="common">
                <el-icon class="mr-2">
                  <calendar />
                </el-icon>
                天数
              </el-option>
              <el-option key="fixed" label="固定到期" value="fixed">
                <el-icon class="mr-2">
                  <clock />
                </el-icon>
                固定到期
              </el-option>
            </el-select>

            <!-- 固定到期时间 -->
            <transition name="el-fade-in">
              <el-date-picker v-if="formData.over_type == 'fixed'" v-model="formData.over_time" type="datetime"
                placeholder="请选择到期时间" format="YYYY-MM-DD HH:mm:ss" size="large" class="datetime-custom" />
            </transition>

            <!-- 天数设置 -->
            <transition name="el-fade-in">
              <div v-if="formData.over_type == 'common'" class="flex items-center gap-2">
                <el-input-number v-model="formData.day" :min="0" :max="9999" controls-position="right" placeholder="天数"
                  size="large" class="input-number-custom" />
                <el-tag type="success" size="large">天</el-tag>
              </div>
            </transition>
          </div>
        </el-form-item>
      </el-card>

      <!-- iOS配置 -->
      <el-card class="mb-4 !border-none config-card" shadow="hover">
        <template #header>
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <el-icon class="mr-2 text-gray-600" :size="20">
                <iphone />
              </el-icon>
              <span class="text-base font-bold text-gray-800">iOS平台配置</span>
            </div>
            <el-tag type="info" size="small">微信小程序有效</el-tag>
          </div>
        </template>

        <el-form-item label="iOS支付" prop="ios_pay">
          <div class="flex flex-col gap-2">
            <el-radio-group v-model="formData.ios_pay" class="radio-group-custom">
              <el-radio-button :label="0">
                <el-icon class="mr-1">
                  <close />
                </el-icon>
                关闭
              </el-radio-button>
              <el-radio-button :label="1">
                <el-icon class="mr-1">
                  <check />
                </el-icon>
                开启
              </el-radio-button>
            </el-radio-group>
            <div class="flex items-center gap-2 text-xs text-gray-500 mt-2">
              <el-icon>
                <warning />
              </el-icon>
              <span>iOS支付开关，关闭后iOS端将隐藏支付功能</span>
            </div>
          </div>
        </el-form-item>

        <el-divider class="my-4" />

        <el-form-item label="iOS支付提示" prop="ios_notice">
          <div class="flex flex-col gap-2 w-full">
            <el-input v-model="formData.ios_notice" type="textarea" :rows="3" clearable
              placeholder="请输入iOS支付关闭时的提示文字，例如：暂不支持iOS支付，请使用其他方式" class="textarea-custom" maxlength="200"
              show-word-limit />
            <div class="flex items-center gap-2 text-xs text-gray-500">
              <el-icon><info-filled /></el-icon>
              <span>iOS不允许使用时的弹窗提醒文字，用于应对微信小程序苹果端关闭提醒</span>
            </div>
          </div>
        </el-form-item>
      </el-card>

    </el-form>

    <div class="fixed-footer-wrap">
      <div class="fixed-footer">
        <el-button type="primary" @click="onSave()" :loading="saveLoading" size="large">
          <el-icon class="mr-1">
            <check />
          </el-icon>{{ t("save") }}
        </el-button>
        <el-button @click="getData()" size="large">
          <el-icon class="mr-1">
            <refresh />
          </el-icon>重置
        </el-button>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from "vue";
import { t } from "@/lang";
import { getConfig, setConfig } from "@/addon/tk_vip/api/config";
import { FormInstance, ElMessage } from "element-plus";
import { getWithMemberLevelList } from "@/addon/tk_vip/api/vip";
import {
  User, Medal, Timer, Calendar, Clock, Iphone,
  Check, Close, Warning, InfoFilled, Refresh
} from '@element-plus/icons-vue';

const levelIdList = ref([] as any[]);
const setLevelIdList = async () => {
  levelIdList.value = await (await getWithMemberLevelList({})).data;
};
setLevelIdList();

const loading = ref(true);
const saveLoading = ref(false);
const ruleFormRef = ref<FormInstance>();

const formData = reactive({
  level_id: "",
  day: 0,
  over_type: "common",
  over_time: "",
  ios_pay: 0,
  ios_notice: "",
});

const rules = reactive({});

const getData = async () => {
  loading.value = true;
  try {
    const data = await getConfig();
    for (const key in formData) {
      if (key in data.data) {
        (formData as any)[key] = data.data[key];
      }
    }
  } catch (error) {
    console.error('获取配置失败:', error);
  } finally {
    loading.value = false;
  }
};

getData();

const onSave = async () => {
  saveLoading.value = true;
  try {
    await setConfig(formData);
    ElMessage.success('保存成功');
    await getData();
  } catch (error) {
    console.error('保存配置失败:', error);
    ElMessage.error('保存失败，请重试');
  } finally {
    saveLoading.value = false;
  }
};
</script>

<style lang="scss" scoped>
.fixed-footer-wrap {
  position: sticky;
  bottom: 0;
  z-index: 100;
  margin-top: 20px;
}

.fixed-footer {
  @apply flex items-center justify-center py-4 bg-white shadow-lg;
  border-top: 1px solid #e5e7eb;
  border-radius: 8px 8px 0 0;
}

// 配置卡片样式
.config-card {
  transition: all 0.3s ease;
  border-radius: 12px;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }
}

:deep(.el-card__header) {
  @apply py-3 px-5 bg-gradient-to-r from-gray-50 to-gray-100;
  border-radius: 12px 12px 0 0;
}

:deep(.el-form-item) {
  @apply mb-5;
}

:deep(.el-form-item__label) {
  @apply font-semibold text-gray-700;
}

// 自定义选择框样式
.select-custom {
  min-width: 240px;

  :deep(.el-input__wrapper) {
    border-radius: 8px;
    transition: all 0.3s ease;

    &:hover {
      box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
    }
  }
}

// 自定义日期选择器
.datetime-custom {
  min-width: 280px;

  :deep(.el-input__wrapper) {
    border-radius: 8px;
    transition: all 0.3s ease;

    &:hover {
      box-shadow: 0 2px 8px rgba(147, 51, 234, 0.2);
    }
  }
}

// 自定义数字输入框
.input-number-custom {
  width: 180px;

  :deep(.el-input__wrapper) {
    border-radius: 8px;
  }
}

// 自定义文本域样式
.textarea-custom {
  max-width: 600px;

  :deep(.el-textarea__inner) {
    border-radius: 8px;
    transition: all 0.3s ease;

    &:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    &:focus {
      box-shadow: 0 2px 12px rgba(59, 130, 246, 0.2);
    }
  }
}

// 自定义单选按钮组样式
.radio-group-custom {
  :deep(.el-radio-button__inner) {
    border-radius: 8px;
    margin-right: 8px;
    padding: 10px 20px;
    transition: all 0.3s ease;

    &:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
  }

  :deep(.el-radio-button:first-child .el-radio-button__inner) {
    border-radius: 8px;
  }

  :deep(.el-radio-button:last-child .el-radio-button__inner) {
    border-radius: 8px;
  }

  :deep(.el-radio-button__original-radio:checked + .el-radio-button__inner) {
    box-shadow: 0 2px 8px rgba(64, 158, 255, 0.3);
  }
}

// 分割线样式
:deep(.el-divider) {
  @apply my-5;
  border-color: #e5e7eb;
}

// Alert 样式
:deep(.el-alert) {
  border-radius: 8px;
}

// 淡入动画
.el-fade-in-enter-active,
.el-fade-in-leave-active {
  transition: all 0.3s ease;
}

.el-fade-in-enter-from {
  opacity: 0;
  transform: translateX(-10px);
}

.el-fade-in-leave-to {
  opacity: 0;
  transform: translateX(10px);
}

// 响应式适配
@media (max-width: 768px) {

  .select-custom,
  .datetime-custom,
  .input-number-custom,
  .textarea-custom {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
  }
}
</style>
