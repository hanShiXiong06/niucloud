<template>
    <ThirdPartyServiceShell
        title="快递发件服务"
        description="配置快递报价、下单、取消拦截、面单及资金查询。服务配置与产品目录分开管理，避免一页堆叠。"
        provider-label="易速开放平台"
        :capability="capabilityInfo"
        :loading="loading"
        :saving="saving"
        :testing="testing"
        :features="['运费实时报价', '快递下单与取消拦截', '面单 PDF 与资金查询', '完整调用日志留痕']"
        :steps="['填写服务商凭证并测试连接', '进入快递产品目录启用常用产品', '在业务单中报价并创建运单', '到运单中心跟踪状态和费用']"
        @refresh="load"
        @save="save"
        @test="test"
    >
        <el-form label-position="top" class="service-form">
            <div class="section-heading">
                <div><h3>基础配置</h3><p>停用后，业务页面不会再调用快递发件能力。</p></div>
                <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" active-text="启用服务" />
            </div>
            <el-row :gutter="18">
                <el-col :span="12">
                    <el-form-item label="服务商">
                        <el-select v-model="form.provider" class="!w-full">
                            <el-option label="易速开放平台" value="yisu" />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item label="接口版本"><el-input v-model="form.yisu.version" /></el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item label="App ID"><el-input v-model="form.yisu.appid" placeholder="请输入服务商 App ID" /></el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item label="App Secret"><el-input v-model="form.yisu.app_secret" show-password placeholder="留空表示不修改已有密钥" /></el-form-item>
                </el-col>
                <el-col :span="16">
                    <el-form-item label="服务地址"><el-input v-model="form.yisu.base_url" /></el-form-item>
                </el-col>
                <el-col :span="8">
                    <el-form-item label="超时时间（秒）"><el-input-number v-model="form.yisu.timeout" :min="3" :max="120" class="!w-full" /></el-form-item>
                </el-col>
                <el-col :span="24">
                    <el-form-item label="回调地址">
                        <el-input v-model="form.yisu.callback_url" />
                        <div class="form-tip">请将该地址配置到服务商后台，用于接收运单状态更新。</div>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-collapse class="advanced-collapse">
                <el-collapse-item title="高级接口路径（正常情况下无需修改）" name="paths">
                    <el-row :gutter="18">
                        <el-col v-for="item in pathFields" :key="item.key" :span="12">
                            <el-form-item :label="item.label"><el-input v-model="form.yisu.api_paths[item.key]" /></el-form-item>
                        </el-col>
                    </el-row>
                </el-collapse-item>
            </el-collapse>
        </el-form>

        <template #aside>
            <section class="aside-action-card">
                <h3>快捷入口</h3>
                <el-button class="!w-full" type="primary" plain @click="router.push('/third_party/express_product')">管理快递产品</el-button>
                <el-button class="!w-full !ml-0 mt-[8px]" @click="router.push('/express/order_record')">查看快递运单</el-button>
                <el-button class="!w-full !ml-0 mt-[8px]" :loading="balanceLoading" @click="loadBalance">查询服务商余额</el-button>
                <div v-if="balanceText" class="balance-result">{{ balanceText }}</div>
            </section>
        </template>
    </ThirdPartyServiceShell>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import ThirdPartyServiceShell from '@/addon/hsx_recycle/components/ThirdPartyServiceShell.vue'
import { useThirdPartyServiceConfig } from '@/addon/hsx_recycle/composables/useThirdPartyServiceConfig'
import { getExpressFund } from '@/addon/hsx_recycle/api/express'

const router = useRouter()
const defaults = {
    enabled: 1,
    provider: 'yisu',
    yisu: {
        base_url: 'http://open.yisuopen.com', appid: '', app_secret: '', version: 'V1.0', timeout: 30,
        callback_url: 'https://gl.hsxbk.top/api/tk_jhkd/yisunotice',
        api_paths: { quote: '/openApi/getPrice', create: '/openApi/doOrder', cancel: '/openApi/doCancel', modify: '/openApi/doModify', detail: '/openApi/getOrderDetail', waybillPdf: '/openApi/getWaybillPdf', fund: '/openApi/fund' }
    }
}
const { form, loading, saving, testing, capabilityInfo, load, save, test } = useThirdPartyServiceConfig('express_order', defaults)
const pathFields = [
    { key: 'quote', label: '报价接口' }, { key: 'create', label: '下单接口' }, { key: 'cancel', label: '取消/拦截接口' },
    { key: 'modify', label: '修改运单接口' }, { key: 'detail', label: '运单详情接口' }, { key: 'waybillPdf', label: '面单接口' }, { key: 'fund', label: '资金接口' }
]
const balanceLoading = ref(false)
const balanceText = ref('')
const loadBalance = async () => {
    balanceLoading.value = true
    try {
        const res: any = await getExpressFund()
        const data = res.data || {}
        balanceText.value = data.balance !== undefined ? `当前余额：¥${data.balance}` : (data.message || '余额查询成功')
    } catch (error: any) {
        ElMessage.error(error?.message || '余额查询失败')
    } finally { balanceLoading.value = false }
}
onMounted(load)
</script>

<style scoped lang="scss">
.service-form { max-width: 920px; }
.section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 18px; padding-bottom: 16px; border-bottom: 1px solid #eef2f7; }
.section-heading h3, .aside-action-card h3 { margin: 0; color: #111827; font-size: 15px; }.section-heading p { margin: 5px 0 0; color: #94a3b8; font-size: 12px; }
.form-tip { margin-top: 6px; color: #94a3b8; font-size: 12px; }.advanced-collapse { margin-top: 8px; border-top: 1px solid #eef2f7; }
.aside-action-card { padding: 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }.aside-action-card h3 { margin-bottom: 14px; }.balance-result { margin-top: 12px; padding: 10px; border-radius: 6px; color: #166534; background: #f0fdf4; font-size: 12px; text-align: center; }
</style>
