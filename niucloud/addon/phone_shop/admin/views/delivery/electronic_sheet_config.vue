<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center mb-[5px] h-[32px]">
                <span class="text-lg">{{ pageName }}</span>
            </div>

            <el-tabs model-value="/phone_shop/delivery/electronic_sheet/config" @tab-change="handleClick">
                <el-tab-pane :label="t('tabESTemplate')" name="/phone_shop/delivery/electronic_sheet" />
                <el-tab-pane :label="t('tabESConfig')" name="/phone_shop/delivery/electronic_sheet/config" />
            </el-tabs>

            <el-alert v-if="loadError" title="电子面单配置读取失败" type="error" :closable="false" class="!mb-[16px]">
                <p>{{ loadError }}。为避免覆盖原配置，读取成功前不能保存。</p>
                <el-button type="primary" link :loading="loading" @click="setFormData">重新读取配置</el-button>
            </el-alert>
            <el-alert v-else-if="!loading && !registryAvailable" title="商城接口尚未完成升级" type="warning" :closable="false" class="!mb-[16px]">
                <p>当前商城接口没有返回电子面单服务商列表，物流插件配置成功并不代表商城已接入。</p>
                <p>请同步更新 phone_shop 配套后端与管理端；不需要重新填写快递100账号，也不要因此改用快递鸟。</p>
                <el-button type="primary" link @click="setFormData">重新检查服务商</el-button>
            </el-alert>
            <el-alert v-else-if="!loading && !hasExternal" title="暂未发现可用的物流插件" type="info" :closable="false" class="!mb-[16px]">
                <p>商城接口已支持扩展。若已配置物流插件，请检查平台是否已安装插件、当前站点是否获授权，以及插件事件缓存是否已刷新；未使用扩展时可继续原有快递鸟。</p>
                <el-button type="primary" link @click="setFormData">重新检查服务商</el-button>
            </el-alert>

            <el-form class="page-form" :model="formData" :rules="formRules" :disabled="loading || !!loadError" label-width="150px" ref="formRef" v-loading="loading">
                <el-card class="box-card !border-none" shadow="never">
                    <h3 class="panel-title !text-sm">{{ t('apiSet') }}</h3>

                    <el-form-item :label="t('interfaceType')" prop="interface_type">
                        <div>
                            <el-radio-group v-model="formData.interface_type">
                                <el-radio v-for="provider in providers" :key="provider.key" :label="provider.key" size="large">{{ provider.label }}</el-radio>
                            </el-radio-group>
                            <template v-if="formData.interface_type == 'kdbird'">
                                <p class="text-[12px] text-[#b2b2b2]">
                                    {{ t('promptTips1-1') }}
                                    <el-button class="button-size" type="primary" link @click="kdnEvent('https://www.kdniao.com/reg?from=niucloud')">https://www.kdniao.com</el-button>
                                </p>
                            </template>
                        </div>
                    </el-form-item>
                    <el-alert v-if="selectedProvider?.external" type="info" :closable="false" class="!mb-[16px]">
                        <p>{{ selectedProvider.description }}</p>
                        <p>无需再创建快递鸟模板或填写快递鸟账号。配置好后，在订单「发货」中选择本服务商，先申请面单，实际交件后再确认发货。</p>
                        <el-button type="primary" link @click="openProviderConfig">打开物流服务配置</el-button>
                    </el-alert>
                    <el-alert v-if="!selectedProvider" title="当前服务商不可用，请恢复对应插件；原运单不会自动切换到其他服务商。" type="warning" :closable="false" />
                    <div v-if="formData.interface_type == 'kdbird'">

                        <el-form-item :label="t('kdnEBusinessIDLabel')" class="input-item">
                            <div>
                                <el-input v-model.trim="formData.kdniao_id" :placeholder="t('kdnEBusinessIDPlaceholder')" class="input-width" clearable />
                                <p class="text-[12px] text-[#b2b2b2]">{{ t('kdnEBusinessIDTips') }}</p>
                            </div>
                        </el-form-item>

                        <el-form-item label="API key" class="input-item">
                            <div>
                                <el-input v-model.trim="formData.kdniao_api_key" clearable :placeholder="t('kdnAppKeyPlaceholder')" class="input-width" />
                                <p class="text-[12px] text-[#b2b2b2]">{{ t('kdnAppKeyTips') }}</p>
                            </div>
                        </el-form-item>

                    </div>

                </el-card>

                <el-card v-if="formData.interface_type === 'kdbird'" class="box-card !border-none" shadow="never">
                    <h3 class="panel-title !text-sm">{{ t('printerSet') }}</h3>

                    <el-alert type="warning" :closable="false" class="!mb-[10px]">
                        <template #default>
                            <p>用双端口加载主JS文件Lodop.js（或CLodopfuncs.js兼容老版本）以防其中某端口被占</p>
                            <p>HTTP推荐端口：8000/18000，HTTPS推荐端口：8443</p>
                            <p>1. 请将打印机连接至本机。 </p>
                            <p>2. 在本机上安装打印控件。下载链接：<a href="http://www.lodop.net/download.html" target="_blank" class="text-primary">http://www.lodop.net/download.html</a></p>
                            <p>3. 将打印控件中的打印端口下面的打印端口设为相同。</p>
                        </template>
                    </el-alert>

                    <el-form-item :label="t('serverPort1')" class="input-item-required" prop="server_port1">
                        <div>
                            <el-input v-model.trim="formData.server_port1" :placeholder="t('serverPort1Placeholder')" class="input-width" clearable />
                        </div>
                    </el-form-item>

                    <el-form-item :label="t('serverPort2')" class="input-item-required" prop="server_port2">
                        <div>
                            <el-input v-model.trim="formData.server_port2" :placeholder="t('serverPort2Placeholder')" class="input-width" clearable />
                        </div>
                    </el-form-item>

                    <el-form-item :label="t('httpsPort')" class="input-item-required" prop="https_port">
                        <div>
                            <el-input v-model.trim="formData.https_port" :placeholder="t('httpsPortPlaceholder')" class="input-width" clearable />
                        </div>
                    </el-form-item>

                </el-card>
            </el-form>

            <div class="fixed-footer-wrap">
                <div class="fixed-footer">
                    <el-button type="primary" :loading="loading" :disabled="!!loadError" @click="save(formRef)">{{ t('save') }}</el-button>
                </div>
            </div>
        </el-card>

    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, computed } from 'vue'
import { t } from '@/lang'
import { FormInstance, FormRules } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { setElectronicSheetConfig, getElectronicSheetConfig } from '@/addon/phone_shop/api/electronic_sheet'
import { electronicSheetProviderState } from '@/addon/phone_shop/utils/electronic-sheet-provider'

const route = useRoute()
const router = useRouter()
const pageName = route.meta.title;
const loading = ref(true)
const loadError = ref('')
const registryAvailable = ref(false), hasExternal = ref(false)
const providers = ref<any[]>([{ key: 'kdbird', label: '快递鸟（原有方式）', external: false }])
const selectedProvider = computed(() => providers.value.find(item => item.key === formData.interface_type))
const openProviderConfig = () => {
    if (selectedProvider.value?.config_url) window.open(router.resolve({ path: selectedProvider.value.config_url }).href, '_blank', 'noopener')
}

const handleClick = (path: string) => {
    router.push({ path })
}

const formData: any = reactive({
    interface_type: 'kdbird',
    kdniao_id: '',
    kdniao_api_key: '',
    server_port1: '8000',
    server_port2: '18000',
    https_port: '8443'
})

const setFormData = async() => {
    loading.value = true
    loadError.value = ''
    try {
        const data = await (await getElectronicSheetConfig()).data
        if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('接口没有返回有效配置，请刷新后重试')
        const state = electronicSheetProviderState(data)
        providers.value = state.providers
        registryAvailable.value = state.registryAvailable
        hasExternal.value = state.hasExternal
        Object.keys(formData).forEach((key: string) => {
            if (data[key] != undefined) formData[key] = data[key]
        })
    } catch (error: any) {
        loadError.value = error?.msg || error?.message || '网络或服务异常，请稍后重试'
    } finally {
        loading.value = false
    }
}
setFormData()

const kdnEvent = (url: any) => {
    window.open(url, '_blank')
}

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = reactive<FormRules>({
    server_port1: [
        { required: true, message: t('serverPort1Placeholder'), trigger: 'blur' },
    ],
    server_port2: [
        { required: true, message: t('serverPort2Placeholder'), trigger: 'blur' },
    ],
    https_port: [
        { required: true, message: t('httpsPortPlaceholder'), trigger: 'blur' },
    ]
})

/**
 * 保存
 */
const save = async(formEl: FormInstance | undefined) => {
    if (loading.value || loadError.value || !formEl) return

    await formEl.validate(async(valid) => {
        if (valid) {
            loading.value = true
            setElectronicSheetConfig(formData).then(() => {
                loading.value = false
            }).catch(() => {
                loading.value = false
            })
        }
    })
}

</script>

<style lang="scss" scoped>
.input-item {
    margin-bottom: 10px !important
}

.input-item-required {
    margin-bottom: 20px !important
}

.button-size {
    font-size: 12px !important;
}

.el-radio.el-radio--large {
    height: auto !important
}
</style>
