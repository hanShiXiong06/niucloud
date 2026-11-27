<template>
    <ThirdPartyServiceShell
        title="智能地址解析"
        description="把客户粘贴的一段地址识别为姓名、手机号、省市区和详细地址，减少人工拆分。"
        provider-label="腾讯云市场地址解析"
        :capability="capabilityInfo"
        :loading="loading"
        :saving="saving"
        :testing="testing"
        :features="['姓名与手机号识别', '省市区自动拆分', '详细地址结构化', '业务页面可直接复用']"
        :steps="['填写云市场密钥并保存', '测试连接确认服务可用', '在右侧用真实地址试解析', '业务人员粘贴地址自动回填']"
        @refresh="load"
        @save="save"
        @test="test"
    >
        <el-form label-position="top" class="service-form">
            <div class="section-heading">
                <div><h3>解析通道</h3><p>停用后仍可手工填写地址，只是不再自动识别。</p></div>
                <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" active-text="启用服务" />
            </div>
            <el-row :gutter="18">
                <el-col :span="12"><el-form-item label="服务商"><el-select v-model="form.provider" class="!w-full"><el-option label="腾讯云市场地址解析" value="tencent_cloud_market_address" /></el-select></el-form-item></el-col>
                <el-col :span="12"><el-form-item label="超时时间（秒）"><el-input-number v-model="form.tencent_cloud_market_address.timeout" :min="3" :max="60" class="!w-full" /></el-form-item></el-col>
                <el-col :span="12"><el-form-item label="Secret ID"><el-input v-model="form.tencent_cloud_market_address.secret_id" show-password placeholder="留空表示不修改已有密钥" /></el-form-item></el-col>
                <el-col :span="12"><el-form-item label="Secret Key"><el-input v-model="form.tencent_cloud_market_address.secret_key" show-password placeholder="留空表示不修改已有密钥" /></el-form-item></el-col>
                <el-col :span="16"><el-form-item label="服务地址"><el-input v-model="form.tencent_cloud_market_address.base_url" /></el-form-item></el-col>
                <el-col :span="8"><el-form-item label="接口路径"><el-input v-model="form.tencent_cloud_market_address.api_path" /></el-form-item></el-col>
            </el-row>
        </el-form>
        <template #aside>
            <section class="address-tester">
                <h3>试解析</h3>
                <p>保存配置后，可用真实文本验证识别结果。</p>
                <el-input v-model="sampleText" type="textarea" :rows="4" resize="none" placeholder="例：张三 13800000000 浙江省杭州市西湖区文三路 1 号" />
                <el-button class="!w-full mt-[10px]" type="primary" :loading="parsing" @click="runParse">解析地址</el-button>
                <el-descriptions v-if="parseResult" :column="1" border size="small" class="mt-[12px]">
                    <el-descriptions-item v-for="item in resultFields" :key="item.key" :label="item.label">{{ parseResult[item.key] || '-' }}</el-descriptions-item>
                </el-descriptions>
            </section>
        </template>
    </ThirdPartyServiceShell>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import ThirdPartyServiceShell from '@/addon/hsx_recycle/components/ThirdPartyServiceShell.vue'
import { useThirdPartyServiceConfig } from '@/addon/hsx_recycle/composables/useThirdPartyServiceConfig'
import { parseThirdPartyAddress } from '@/addon/hsx_recycle/api/third_party'
const defaults = { enabled: 1, provider: 'tencent_cloud_market_address', tencent_cloud_market_address: { base_url: 'https://ap-guangzhou.cloudmarket-apigw.com', api_path: '/service-3qtg8hpi/identify_address', secret_id: '', secret_key: '', timeout: 10 } }
const { form, loading, saving, testing, capabilityInfo, load, save, test } = useThirdPartyServiceConfig('address_parse', defaults)
const sampleText = ref('')
const parsing = ref(false)
const parseResult = ref<any>(null)
const resultFields = [{ key: 'name', label: '姓名' }, { key: 'mobile', label: '手机号' }, { key: 'province', label: '省' }, { key: 'city', label: '市' }, { key: 'district', label: '区县' }, { key: 'address', label: '详细地址' }]
const runParse = async () => {
    if (!sampleText.value.trim()) return ElMessage.warning('请先输入需要解析的地址文本')
    parsing.value = true
    try { const res: any = await parseThirdPartyAddress({ address: sampleText.value.trim() }); parseResult.value = res.data || {}; ElMessage.success('地址解析完成') }
    finally { parsing.value = false }
}
onMounted(load)
</script>
<style scoped lang="scss">
.service-form { max-width: 920px; }.section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 18px; padding-bottom: 16px; border-bottom: 1px solid #eef2f7; }.section-heading h3,.address-tester h3 { margin: 0; color: #111827; font-size: 15px; }.section-heading p,.address-tester p { margin: 5px 0 14px; color: #94a3b8; font-size: 12px; line-height: 1.6; }.address-tester { padding: 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
</style>
