<template>
    <ThirdPartyServiceShell
        title="快递轨迹查询"
        description="独立管理快递查询渠道。发件服务负责下单，轨迹服务只负责查询，服务异常互不影响。"
        provider-label="阿里云快递查询"
        :capability="capabilityInfo"
        :loading="loading"
        :saving="saving"
        :testing="testing"
        :features="['按运单号查询轨迹', '适配多家快递公司', '查询失败独立记录', '可替换其他查询服务商']"
        :steps="['购买并开通快递查询接口', '填写 API Key 并保存', '测试连接确认可用', '在订单中查询物流轨迹']"
        @refresh="load"
        @save="save"
        @test="test"
    >
        <el-form label-position="top" class="service-form">
            <div class="section-heading">
                <div><h3>查询通道</h3><p>该开关只影响物流轨迹查询，不影响已创建的快递运单。</p></div>
                <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" active-text="启用服务" />
            </div>
            <el-row :gutter="18">
                <el-col :span="12"><el-form-item label="服务商"><el-select v-model="form.provider" class="!w-full"><el-option label="阿里云快递查询" value="ali_express" /></el-select></el-form-item></el-col>
                <el-col :span="12"><el-form-item label="API Key"><el-input v-model="form.ali_express.api_key" show-password placeholder="留空表示不修改已有密钥" /></el-form-item></el-col>
                <el-col :span="16"><el-form-item label="服务地址"><el-input v-model="form.ali_express.base_url" /></el-form-item></el-col>
                <el-col :span="8"><el-form-item label="超时时间（秒）"><el-input-number v-model="form.ali_express.timeout" :min="3" :max="120" class="!w-full" /></el-form-item></el-col>
                <el-col :span="24"><el-form-item label="查询接口路径"><el-input v-model="form.ali_express.api_path" /></el-form-item></el-col>
            </el-row>
            <el-alert type="info" :closable="false" show-icon title="保存后请先执行“测试连接”。测试结果与实际调用都会进入第三方调用日志，方便排查失败原因。" />
        </el-form>
        <template #aside>
            <section class="aside-action-card">
                <h3>运行记录</h3>
                <p>查看轨迹查询请求、响应、耗时和失败原因。</p>
                <el-button class="!w-full" @click="router.push({ path: '/third_party/api_log', query: { service_type: 'express_query' } })">查看查询日志</el-button>
            </section>
        </template>
    </ThirdPartyServiceShell>
</template>
<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import ThirdPartyServiceShell from '@/addon/hsx_recycle/components/ThirdPartyServiceShell.vue'
import { useThirdPartyServiceConfig } from '@/addon/hsx_recycle/composables/useThirdPartyServiceConfig'
const router = useRouter()
const defaults = { enabled: 1, provider: 'ali_express', ali_express: { base_url: 'https://kzexpress.market.alicloudapi.com', api_key: '', api_path: '/api-mall/api/express/query', timeout: 30 } }
const { form, loading, saving, testing, capabilityInfo, load, save, test } = useThirdPartyServiceConfig('express_query', defaults)
onMounted(load)
</script>
<style scoped lang="scss">
.service-form { max-width: 920px; }.section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 18px; padding-bottom: 16px; border-bottom: 1px solid #eef2f7; }.section-heading h3,.aside-action-card h3 { margin: 0; color: #111827; font-size: 15px; }.section-heading p,.aside-action-card p { margin: 5px 0 14px; color: #94a3b8; font-size: 12px; line-height: 1.6; }.aside-action-card { padding: 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
</style>
