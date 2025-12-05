<template>
  <div class="main-container">
    <el-form :model="formData" label-width="150px" ref="ruleFormRef" :rules="rules" class="page-form"
      v-loading="loading">
      <!-- AI配置卡片 -->
      <el-card class="mb-4 !border-none" shadow="hover">
        <template #header>
          <div class="flex items-center">
            <el-icon class="mr-2">
              <setting />
            </el-icon>
            <span class="font-bold">AI大模型配置</span>
          </div>
        </template>

        <el-form-item class="font-bold text-xs" label="请求地址" prop="ai_host">
          <el-input clearable v-model="formData.ai_host" style="width: 400px" placeholder="请输入完整openai接口请求地址">
            <template #prefix><el-icon>
                <connection />
              </el-icon></template>
          </el-input>
          <span class="ml-4 text-gray-500">完整openai接口请求地址</span>
        </el-form-item>

        <el-form-item class="font-bold text-xs" label="AI模型" prop="ai_model">
          <el-input clearable v-model="formData.ai_model" style="width: 400px" placeholder="请输入AI模型名称">
            <template #prefix><el-icon>
                <monitor />
              </el-icon></template>
          </el-input>
          <span class="ml-4 text-gray-500">指定使用的AI模型</span>
        </el-form-item>

        <el-form-item class="font-bold text-xs" label="API密钥" prop="ai_key">
          <el-input clearable v-model="formData.ai_key" style="width: 400px" placeholder="请输入API密钥" show-password>
            <template #prefix><el-icon>
                <key />
              </el-icon></template>
          </el-input>
          <span class="ml-4 text-gray-500">大模型请求key</span>
        </el-form-item>
      </el-card>
      <el-card class="mb-4 !border-none" shadow="hover">
        <template #header>
          <div class="flex items-center">
            <el-icon class="mr-2">
              <setting />
            </el-icon>
            <span class="font-bold">基础配置</span>
          </div>
        </template>
        <el-form-item class="font-bold text-xs" label="公告" prop="notice">
          <el-input clearable v-model="formData.notice" style="width: 400px" placeholder="请输入公告">
            <template #prefix><el-icon>
                <connection />
              </el-icon></template>
          </el-input>
          <span class="ml-4 text-gray-500">在创建页面头部提示，如AI处理时长大概10-30s请耐心等待</span>
        </el-form-item>

        <el-form-item class="font-bold text-xs" label="API密钥" prop="api_key">

          <el-input clearable v-model="formData.duomi_key" style="width: 400px" placeholder="请输入API密钥">
            <template #prefix><el-icon>
                <key />
              </el-icon></template>
          </el-input>

          <el-button>
            <a href="https://duomiapi.com/user/register?cps=z1ex5XqP" target="_blank">开放平台</a>
          </el-button>
          <span class="ml-2 text-red-500 text-[16rpx]">接口成本0.07元/张</span>
        </el-form-item>

      </el-card>
      <el-card class="mb-4 !border-none" shadow="hover">
        <template #header>
          <div class="flex items-center">
            <el-icon class="mr-2">
              <setting />
            </el-icon>
            <span class="font-bold">消耗配置</span>
          </div>
        </template>
        <!-- <el-form-item class="font-bold text-xs" label="别名" prop="alias_name">
          <el-input clearable v-model="formData.alias_name" style="width: 400px" placeholder="请输入别名">
          </el-input>
        </el-form-item>
        <el-form-item class="font-bold text-xs" label="创作消耗/次" prop="create_point">
          <el-input-number clearable v-model="formData.create_point" style="width: 400px" placeholder="请输入消耗">
          </el-input-number>
          <span class="ml-4 text-gray-500">AI润色消耗{{ formData.alias_name }}</span>
        </el-form-item> -->
        <el-form-item class="font-bold text-xs" label="AI润色消耗/次" prop="chat_point">
          <el-input-number clearable v-model="formData.chat_point" style="width: 400px" placeholder="请输入消耗">
          </el-input-number>
          <span class="ml-4 text-gray-500">AI润色消耗{{ formData.alias_name }}</span>
        </el-form-item>
      </el-card>
      <el-card class="mb-4 !border-none" shadow="hover">
        <template #header>
          <div class="flex items-center">
            <el-icon class="mr-2">
              <setting />
            </el-icon>
            <span class="font-bold">营销配置</span>
          </div>
        </template>
        <!-- <el-form-item class="font-bold text-xs" label="新用户赠送" prop="give_point">
          <el-input-number clearable v-model="formData.give_point" style="width: 400px" placeholder="请输入新用户赠送">
          </el-input-number>
          <span class="ml-4 text-gray-500">新注册用户赠送{{ formData.alias_name }}</span>
        </el-form-item> -->
        <el-form-item class="font-bold text-xs" label="邀请新用户赠送" prop="share_point">
          <el-input-number clearable v-model="formData.share_point" style="width: 400px" placeholder="请输入邀请新用户赠送">
          </el-input-number>
          <span class="ml-4 text-gray-500">邀请新用户赠送{{ formData.alias_name }}</span>
        </el-form-item>
      </el-card>
      <el-card class="mb-4 !border-none" shadow="hover">
        <template #header>
          <div class="flex items-center">
            <el-icon class="mr-2">
              <setting />
            </el-icon>
            <span class="font-bold">IOS支付配置</span>
          </div>
        </template>
        <el-form-item class="font-bold text-xs" label="ios支付" prop="ios_pay">
          <el-radio-group v-model="formData.ios_pay">
            <el-radio :label="0">关闭</el-radio>
            <el-radio :label="1">开启</el-radio>
          </el-radio-group>
          <span class="ml-4 text-gray-500">ios支付是否开启</span>
        </el-form-item>
        <el-form-item class="font-bold text-xs" label="ios通知" prop="ios_notice">
          <el-input v-model="formData.ios_notice" clearable placeholder="请输入ios通知" style="width: 400px" />
          <span class="ml-4 text-gray-500">ios不允许支付弹窗提醒</span>
        </el-form-item>
      </el-card>

      <el-card class="mb-4 !border-none" shadow="hover">
        <template #header>
          <div class="flex items-center">
            <el-icon class="mr-2">
              <setting />
            </el-icon>
            <span class="font-bold">PC配置</span>
          </div>
        </template>
        <el-form-item class="font-bold text-xs" label="公众号扫码" prop="is_scan">
          <el-radio-group v-model="formData.is_scan">
            <el-radio :label="0">关闭</el-radio>
            <el-radio :label="1">开启</el-radio>
          </el-radio-group>
          <span class="ml-4 text-gray-500">PC端公众号扫码登录是否开启,仅底部拦截有效</span>
        </el-form-item>
        <el-form-item class="font-bold text-xs" label="PC支付" prop="pc_pay">
          <el-radio-group v-model="formData.pc_pay">
            <el-radio :label="0">关闭</el-radio>
            <el-radio :label="1">开启</el-radio>
          </el-radio-group>
          <span class="ml-4 text-gray-500">PC端支付是否开启</span>
        </el-form-item>
        <el-form-item v-if="formData.pc_pay == 0" class="font-bold text-xs" label="PC支付通知" prop="pc_notice">
          <el-input v-model="formData.pc_notice" clearable placeholder="请输入PC通知" style="width: 400px" />
          <span class="ml-4 text-gray-500">如:需充值请联系平台客服充值</span>
        </el-form-item>
        <el-form-item v-if="formData.pc_pay == 1" class="font-bold text-xs" label="商户编号" prop="mno">
          <el-input v-model="formData.mno" clearable placeholder="请输入商户编号" style="width: 400px" />
          <span class="ml-4 text-gray-500">请联系站点管理员开通</span>
        </el-form-item>
        <el-form-item class="font-bold text-xs" label="PC地址" prop="url">
          <div class="flex items-center gap-3 flex-wrap">
            <div class="url-display-box">
              <el-icon class="text-blue-500 mr-2">
                <link />
              </el-icon>
              <span class="url-text">{{ formData.url || '暂无地址' }}</span>
            </div>
            <el-button type="primary" :icon="CopyDocument" @click="copyUrl" size="default">
              复制地址
            </el-button>
            <el-button type="success" :icon="ChromeFilled" link @click="openUrl" v-if="formData.url">
              打开访问
            </el-button>
          </div>
          <span class="mt-2 ml-4 text-gray-500 block text-xs">
            <el-icon><info-filled /></el-icon>
            此地址为您的前端访问地址，可分享给用户
          </span>
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
import { getConfig, setConfig } from "@/addon/ai_image/api/config";
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
  ai_key: '',
  ai_model: '',
  ai_host: '',
  duomi_key: '',
  alias_name: '积分',
  chat_point: '',
  is_self: 0,
  point: '',
  create_point: 10,
  give_point: '',
  share_point: '',
  platform: '多米',
  notice: "",//创建页面提示
  ios_pay: 0,
  ios_notice: '',
  mno: '',
  is_scan: 0,
  pc_pay: 0,
  pc_notice: '',
  url: '',
});

// 表单验证规则
const rules = reactive({
  ai_host: [{ required: false, message: '请输入API请求地址', trigger: 'blur' }],
  ai_key: [{ required: false, message: '请输入API密钥', trigger: 'blur' }],
  ai_model: [{ required: false, message: '请输入AI模型', trigger: 'blur' }],
  api_key: [{ required: false, message: '请输入API密钥', trigger: 'blur' }],
  alias_name: [{ required: false, message: '请输入别名', trigger: 'blur' }],
  text_point: [{ required: false, message: '请输入文本点数', trigger: 'blur' }],
  image_point: [{ required: false, message: '请输入图片点数', trigger: 'blur' }],
  chat_point: [{ required: false, message: '请输入对话点数', trigger: 'blur' }],
  give_point: [{ required: false, message: '请输入新用户赠送', trigger: 'blur' }],
  share_point: [{ required: false, message: '请输入邀请新用户赠送', trigger: 'blur' }],
  ios_pay: [
    { required: true, message: '请选择是否开启ios支付', trigger: 'blur' },
  ],
  ios_notice: [
    { required: true, message: '请输入ios不允许支付的提示内容', trigger: 'blur' },
  ],
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
// 复制URL
const copyUrl = async () => {
  if (!formData.url) {
    ElMessage.warning('暂无地址可复制');
    return;
  }

  try {
    await navigator.clipboard.writeText(formData.url);
    ElMessage.success('地址已复制到剪贴板');
  } catch (err) {
    // 降级方案：使用传统方法复制
    const textarea = document.createElement('textarea');
    textarea.value = formData.url;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      ElMessage.success('地址已复制到剪贴板');
    } catch (e) {
      ElMessage.error('复制失败，请手动复制');
    }
    document.body.removeChild(textarea);
  }
};

// 打开URL
const openUrl = () => {
  if (formData.url) {
    window.open(formData.url, '_blank');
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