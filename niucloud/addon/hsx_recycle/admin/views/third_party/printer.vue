<template>
    <ThirdPartyServiceShell
        title="云打印服务"
        description="管理打印通道的基础连接，打印机设备与模板分别维护。配置页只负责通道，不再混入其他第三方能力。"
        provider-label="芯烨云打印"
        :capability="capabilityInfo"
        :loading="loading"
        :saving="saving"
        :testable="false"
        :features="['标签与小票打印通道', '打印机设备独立管理', '模板可预览和测试打印', '业务打印失败可追踪']"
        :steps="['启用云打印通道', '到打印机管理绑定设备', '创建并测试打印模板', '在业务场景中触发打印']"
        @refresh="load"
        @save="save"
    >
        <el-form label-position="top" class="service-form">
            <div class="section-heading">
                <div><h3>打印通道</h3><p>停用后不会自动触发云打印，已保存的打印机和模板不会删除。</p></div>
                <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" active-text="启用服务" />
            </div>
            <el-row :gutter="18">
                <el-col :span="12"><el-form-item label="服务商"><el-select v-model="form.provider" class="!w-full"><el-option label="芯烨云打印" value="xpyun" /></el-select></el-form-item></el-col>
                <el-col :span="6"><el-form-item label="请求超时（秒）"><el-input-number v-model="form.xpyun.timeout" :min="3" :max="120" class="!w-full" /></el-form-item></el-col>
                <el-col :span="6"><el-form-item label="连接超时（秒）"><el-input-number v-model="form.xpyun.connect_timeout" :min="2" :max="60" class="!w-full" /></el-form-item></el-col>
                <el-col :span="16"><el-form-item label="服务地址"><el-input v-model="form.xpyun.base_url" /></el-form-item></el-col>
                <el-col :span="8"><el-form-item label="标签打印接口"><el-input v-model="form.xpyun.print_label_path" /></el-form-item></el-col>
            </el-row>
            <el-alert type="info" :closable="false" show-icon title="打印机编号、密钥和门店绑定请在“打印机管理”中维护；纸张、字段和排版请在“打印模板”中维护。" />
        </el-form>
        <template #aside>
            <section class="aside-action-card">
                <h3>打印资源</h3>
                <p>通道保存后，请继续完成打印机和模板配置，整个打印流程才算闭环。</p>
                <el-button class="!w-full" type="primary" plain @click="router.push('/recycle/printer/list')">打印机管理</el-button>
                <el-button class="!w-full !ml-0 mt-[8px]" @click="router.push('/recycle/printer_template/list')">打印模板</el-button>
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
const defaults = { enabled: 1, provider: 'xpyun', xpyun: { base_url: 'https://open.xpyun.net/api/openapi', print_label_path: '/xprinter/printLabel', timeout: 30, connect_timeout: 10 } }
const { form, loading, saving, capabilityInfo, load, save } = useThirdPartyServiceConfig('printer', defaults)
onMounted(load)
</script>
<style scoped lang="scss">
.service-form { max-width: 920px; }.section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 18px; padding-bottom: 16px; border-bottom: 1px solid #eef2f7; }.section-heading h3,.aside-action-card h3 { margin: 0; color: #111827; font-size: 15px; }.section-heading p,.aside-action-card p { margin: 5px 0 14px; color: #94a3b8; font-size: 12px; line-height: 1.6; }.aside-action-card { padding: 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
</style>
