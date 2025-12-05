<template>
  <div class="main-container">
    <el-form :model="formData" label-width="150px" ref="ruleFormRef" :rules="rules" class="page-form"
      v-loading="loading">
      <!-- 随行付配置 -->
      <el-card class="mb-4 !border-none config-card" shadow="hover">
        <template #header>
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <el-icon class="mr-2 text-orange-500" :size="20">
                <wallet />
              </el-icon>
              <span class="text-base font-bold text-gray-800">随行付配置</span>
            </div>
            <el-tag type="warning" size="small">支付配置</el-tag>
          </div>
        </template>

        <el-alert title="请妥善保管您的公钥和私钥，不要泄露给他人" type="warning" :closable="false" show-icon class="mb-4" />

        <el-form-item label="公钥" prop="public_key">
          <el-input v-model="formData.public_key" type="textarea" :rows="4" class="textarea-custom"
            placeholder="请输入随行付公钥">
          </el-input>
        </el-form-item>

        <el-form-item label="私钥" prop="private_key">
          <el-input v-model="formData.private_key" type="textarea" :rows="4" class="textarea-custom"
            placeholder="请输入随行付私钥" show-password>
          </el-input>
        </el-form-item>

        <el-form-item label="机构号" prop="org_id">
          <div class="flex items-start gap-2 flex-wrap">
            <el-input clearable v-model="formData.org_id" class="input-custom" placeholder="请输入随行付机构号">
              <template #prefix>
                <el-icon class="text-orange-500"><office-building /></el-icon>
              </template>
            </el-input>
            <el-tooltip content="随行付平台分配的机构号" placement="top">
              <el-icon class="text-gray-400 mt-2"><question-filled /></el-icon>
            </el-tooltip>
          </div>
        </el-form-item>
      </el-card>
    </el-form>
    <div class="fixed-footer-wrap">
      <div class="fixed-footer">
        <el-button type="primary" @click="onSave()" :loading="saveLoading">
          <el-icon class="mr-1">
            <check />
          </el-icon>{{ t("save") }}
        </el-button>
        <el-button @click="getData()">
          <el-icon class="mr-1">
            <refresh />
          </el-icon>重置
        </el-button>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, computed } from "vue";
import { t } from "@/lang";
import { getConfig, setConfig } from "@/addon/ai_image/api/sxf";
import { FormInstance, ElMessage } from "element-plus";
import {
  Setting, Message, MessageBox, Connection, Cpu, Key,
  User, Search, Bell, Link, Lock, Edit, Promotion,
  Refresh, Check, VideoCamera, VideoPlay, Picture, FullScreen, ChatDotRound
} from '@element-plus/icons-vue';
// 表单引用和加载状态
const loading = ref(true);
const saveLoading = ref(false);
const ruleFormRef = ref<FormInstance>();

// 表单数据
const formData = reactive({
  public_key: '',
  private_key: '',
  org_id: '',
});

// 表单验证规则
const rules = reactive({
  public_key: [{ required: true, message: '请输入随行付公钥', trigger: 'blur' }],
  private_key: [{ required: true, message: '请输入随行付私钥', trigger: 'blur' }],
  org_id: [{ required: true, message: '请输入随行付机构号', trigger: 'blur' }],
});

// 获取配置数据
const getData = async () => {
  loading.value = true;
  try {
    const { data } = await getConfig();

    // 更新表单数据
    for (const key in formData) {
      if (key in data) {
        formData[key] = data[key];
      }
    }
  } catch (error) {
  } finally {
    loading.value = false;
  }
};

// 保存配置
const onSave = async () => {
  saveLoading.value = true;
  try {
    await setConfig(formData);

    await getData();
  } catch (error) {
    console.error('保存配置失败:', error);
  } finally {
    saveLoading.value = false;
  }
};

// 初始化
getData();
</script>

<style lang="scss" scoped>
.fixed-footer {
  @apply flex items-center justify-center py-4 bg-white shadow-md;
  width: 100%;
  z-index: 100;
}

:deep(.el-card__header) {
  @apply py-2 px-4 bg-gray-50;
}

:deep(.el-form-item) {
  @apply mb-4;
}
</style>