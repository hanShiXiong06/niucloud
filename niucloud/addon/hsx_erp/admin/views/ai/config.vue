<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never" v-loading="loading">
            <div class="text-page-title">AI 配置</div>
            <div class="mt-1 text-sm text-gray-500">仅本站点生效。密钥只保存在后端，页面只显示脱敏值。</div>

            <el-form :model="form" label-width="130px" class="mt-6 max-w-3xl">
                <el-form-item label="启用 AI">
                    <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" />
                    <span class="ml-3 text-xs text-gray-400">关闭后所有 AI 入口不可用</span>
                </el-form-item>

                <el-form-item label="接口地址">
                    <el-input v-model="form.base_url" placeholder="https://yunwu.ai/v1" />
                    <div class="mt-1 text-xs text-gray-400">
                        国内访问慢可改用分站：<code>https://yunwu.zeabur.app/v1</code>
                    </div>
                </el-form-item>

                <el-form-item label="API 密钥">
                    <el-input
                        v-model="form.api_key"
                        type="password"
                        show-password
                        :placeholder="hasApiKey ? '已配置（留空则不修改）' : '请输入云雾令牌 sk-...'"
                    />
                </el-form-item>

                <el-form-item label="默认模型">
                    <div class="flex w-full gap-2">
                        <el-select v-model="form.default_model" placeholder="选择默认模型" filterable allow-create class="flex-1">
                            <el-option v-for="m in form.available_models" :key="m" :label="m" :value="m" />
                        </el-select>
                        <el-button :loading="modelLoading" @click="fetchModels">拉取模型</el-button>
                        <el-button :loading="pingLoading" @click="testPing">测试连通</el-button>
                    </div>
                </el-form-item>

                <el-form-item label="可用模型">
                    <el-select
                        v-model="form.available_models"
                        multiple
                        filterable
                        allow-create
                        default-first-option
                        placeholder="可手动输入或从「拉取模型」获取"
                        class="w-full"
                    >
                        <el-option v-for="m in modelOptions" :key="m" :label="m" :value="m" />
                    </el-select>
                </el-form-item>

                <el-form-item label="超时(秒)">
                    <el-input-number v-model="form.timeout" :min="5" :max="300" />
                </el-form-item>

                <el-form-item label="每日 token 上限">
                    <el-input-number v-model="form.daily_token_limit" :min="0" :step="10000" />
                    <span class="ml-3 text-xs text-gray-400">0 表示不限</span>
                </el-form-item>

                <el-form-item>
                    <el-button type="primary" :loading="saving" @click="save">保存</el-button>
                </el-form-item>
            </el-form>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getAiConfig, saveAiConfig, getAiModels, pingAi } from '@/addon/hsx_erp/api/ai'

const loading = ref(false)
const saving = ref(false)
const modelLoading = ref(false)
const pingLoading = ref(false)
const hasApiKey = ref(false)
const modelOptions = ref<string[]>([])

const form = reactive<Record<string, any>>({
    enabled: 0,
    base_url: 'https://yunwu.ai/v1',
    api_key: '',
    default_model: '',
    available_models: [],
    timeout: 60,
    daily_token_limit: 0,
})

const load = async () => {
    loading.value = true
    try {
        const res: any = await getAiConfig()
        const data = res.data || {}
        Object.assign(form, {
            enabled: data.enabled ?? 0,
            base_url: data.base_url || 'https://yunwu.ai/v1',
            api_key: '',
            default_model: data.default_model || '',
            available_models: data.available_models || [],
            timeout: data.timeout || 60,
            daily_token_limit: data.daily_token_limit || 0,
        })
        hasApiKey.value = !!data.has_api_key
        modelOptions.value = data.available_models || []
    } finally {
        loading.value = false
    }
}

const fetchModels = async () => {
    modelLoading.value = true
    try {
        const res: any = await getAiModels()
        const list: string[] = res.data || []
        modelOptions.value = list
        // 合并进可用模型，去重
        const merged = new Set([...(form.available_models || []), ...list])
        form.available_models = Array.from(merged)
        ElMessage.success(`拉取到 ${list.length} 个模型`)
    } finally {
        modelLoading.value = false
    }
}

const testPing = async () => {
    pingLoading.value = true
    try {
        const res: any = await pingAi(form.default_model)
        if (res.data?.ok) {
            ElMessage.success(`连通正常：${res.data.content}`)
        } else {
            ElMessage.warning('已连通，但返回为空')
        }
    } finally {
        pingLoading.value = false
    }
}

const save = async () => {
    saving.value = true
    try {
        await saveAiConfig({ ...form })
        await load()
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>
