<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="page-head">
                <div>
                    <div class="text-page-title">爬虫配置</div>
                    <div class="page-desc">配置DH速收报价同步需要的公共请求参数。报价源、价格策略和同步操作在报价工作台维护。</div>
                </div>
                <div class="head-actions">
                    <el-button :loading="loading" @click="loadConfig">刷新</el-button>
                    <el-button @click="loadDefault">恢复默认</el-button>
                    <el-button type="primary" :loading="saving" @click="saveConfig">保存配置</el-button>
                </div>
            </div>

            <el-alert
                class="mt-[18px]"
                type="info"
                :closable="false"
                title="Token、OpenId 和请求头属于渠道凭证，请只给可信管理员开放该页面。"
            />

            <el-form :model="form" label-width="150px" class="config-form" v-loading="loading">
                <el-form-item label="启用">
                    <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item label="服务商">
                    <el-select v-model="form.provider" disabled>
                        <el-option label="超牛报价" value="chaoniu" />
                    </el-select>
                </el-form-item>
                <el-form-item label="接口域名">
                    <el-input v-model="form.providers.chaoniu.base_url" placeholder="https://daheng.chaoniu.top" />
                </el-form-item>
                <el-form-item label="报价详情路径">
                    <el-input v-model="form.providers.chaoniu.detail_path" placeholder="/api/v1/quotation/detail" />
                </el-form-item>
                <el-form-item label="版本号">
                    <el-input v-model="form.providers.chaoniu.version" placeholder="2.4.1" />
                </el-form-item>
                <el-form-item label="AppId">
                    <el-input v-model="form.providers.chaoniu.app_id" placeholder="请输入 AppId" />
                </el-form-item>
                <el-form-item label="Platform">
                    <el-input v-model="form.providers.chaoniu.platform" placeholder="2" />
                </el-form-item>
                <el-form-item label="Authorization Token">
                    <el-input
                        v-model="form.providers.chaoniu.authorization_token"
                        type="textarea"
                        :rows="3"
                        placeholder="请输入 Authorization Token"
                    />
                </el-form-item>
                <el-form-item label="OpenId">
                    <el-input v-model="form.providers.chaoniu.open_id" placeholder="请输入 OpenId" />
                </el-form-item>
                <el-form-item label="Referer">
                    <el-input v-model="form.providers.chaoniu.referer" />
                </el-form-item>
                <el-form-item label="User-Agent">
                    <el-input v-model="form.providers.chaoniu.user_agent" type="textarea" :rows="3" />
                </el-form-item>
                <el-form-item label="Accept-Encoding">
                    <el-input v-model="form.providers.chaoniu.accept_encoding" placeholder="gzip,compress,br,deflate" />
                </el-form-item>
                <el-form-item label="超时时间">
                    <el-input-number v-model="form.providers.chaoniu.timeout" :min="1" :max="120" />
                    <span class="form-tip">秒</span>
                </el-form-item>
            </el-form>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getQuotationCrawlerConfig, getQuotationCrawlerDefaultConfig, saveQuotationCrawlerConfig } from '@/addon/recycle_daheng_quote/api/quotation'

const defaultForm = {
    enabled: 1,
    provider: 'chaoniu',
    providers: {
        chaoniu: {
            name: '超牛报价',
            base_url: 'https://daheng.chaoniu.top',
            detail_path: '/api/v1/quotation/detail',
            version: '2.4.1',
            app_id: '',
            platform: '2',
            authorization_token: '',
            open_id: '',
            referer: 'https://servicewechat.com/wx7c82fedb54be53fc/41/page-frame.html',
            user_agent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.71(0x18004730) NetType/WIFI Language/zh_CN',
            accept_encoding: 'gzip,compress,br,deflate',
            timeout: 30
        }
    }
}

const loading = ref(false)
const saving = ref(false)
const form = reactive<any>(clone(defaultForm))

function clone(data: Record<string, any>) {
    return JSON.parse(JSON.stringify(data))
}

function unwrapResponseData(res: any) {
    if (res?.data?.data !== undefined) return res.data.data
    if (res?.data !== undefined) return res.data
    return res || {}
}

function mergeDeep(target: Record<string, any>, source: Record<string, any>) {
    const result = clone(target)
    Object.keys(source || {}).forEach((key) => {
        if (
            source[key]
            && typeof source[key] === 'object'
            && !Array.isArray(source[key])
            && result[key]
            && typeof result[key] === 'object'
            && !Array.isArray(result[key])
        ) {
            result[key] = mergeDeep(result[key], source[key])
        } else {
            result[key] = source[key]
        }
    })
    return result
}

function assignForm(data: Record<string, any>) {
    Object.assign(form, mergeDeep(defaultForm, data || {}))
}

async function loadConfig() {
    loading.value = true
    try {
        const res = await getQuotationCrawlerConfig()
        assignForm(unwrapResponseData(res))
    } finally {
        loading.value = false
    }
}

async function loadDefault() {
    try {
        await ElMessageBox.confirm('恢复默认只会重置页面表单，保存后才会生效。确定恢复默认配置吗？', '提示', {
            type: 'warning'
        })
        const res = await getQuotationCrawlerDefaultConfig()
        assignForm(unwrapResponseData(res))
    } catch (error) {}
}

async function saveConfig() {
    saving.value = true
    try {
        const res = await saveQuotationCrawlerConfig(form)
        assignForm(unwrapResponseData(res))
        ElMessage.success('保存成功')
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    loadConfig()
})
</script>

<style lang="scss" scoped>
.page-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.page-desc {
    margin-top: 6px;
    color: #606266;
    font-size: 13px;
}

.head-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.config-form {
    max-width: 900px;
    margin-top: 22px;
}

.form-tip {
    margin-left: 10px;
    color: #909399;
    font-size: 13px;
}

@media (max-width: 768px) {
    .page-head {
        display: block;
    }

    .head-actions {
        margin-top: 14px;
        flex-wrap: wrap;
    }
}
</style>
