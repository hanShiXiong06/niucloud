<template>
    <div class="main-container">
        <el-card class="card !border-none mb-[15px]" shadow="never">
            <el-page-header :content="formData.id ? '编辑电子面单' : '新增电子面单'" :icon="ArrowLeft" @back="back" />
        </el-card>

        <el-form class="page-form" :model="formData" :rules="formRules" label-width="140px" ref="formRef" v-loading="loading">
            <el-card class="box-card !border-none" shadow="never">
                <h3 class="panel-title !text-sm mb-[16px]">基本设置</h3>

                <el-form-item label="模板名称" prop="template_name">
                    <el-input v-model.trim="formData.template_name" placeholder="如 顺丰-默认" maxlength="30" class="!w-[360px]" clearable />
                </el-form-item>
                <el-form-item label="执行服务商" prop="provider">
                    <el-select v-model="formData.provider" placeholder="选择下单服务商" class="!w-[360px]" @change="handleProviderChange">
                        <el-option label="易速快递" value="yisu" />
                        <el-option label="快递100" value="kuaidi100" />
                    </el-select>
                    <div class="text-[12px] text-gray-400 mt-[2px]">下单时由该服务商出面单（需先在「接口配置」里填好账号）。切换服务商后，下面只列出该服务商已绑定且出面单的公司。</div>
                </el-form-item>
                <el-form-item label="快递公司" prop="express_company_id">
                    <el-select v-model="formData.express_company_id" placeholder="选择快递公司" class="!w-[360px]" @change="handleCompanyChange">
                        <el-option v-for="item in companyList" :key="item.company_id" :label="item.company_name" :value="item.company_id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="业务类型" v-if="expTypeList.length">
                    <el-radio-group v-model="formData.exp_type" @change="handleExpTypeChange">
                        <el-radio v-for="(item, i) in expTypeList" :key="i" :value="String(item.value)">{{ item.text }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="打印样式" v-if="printStyleList.length">
                    <el-select v-model="formData.print_style" placeholder="选择打印样式" class="!w-[360px]" clearable>
                        <el-option v-for="(item, i) in printStyleList" :key="i" :label="item.template_name" :value="String(item.template_size)" />
                    </el-select>
                </el-form-item>
            </el-card>

            <el-card class="box-card !border-none" shadow="never">
                <h3 class="panel-title !text-sm mb-[16px]">出单与打印</h3>
                <el-form-item label="面单形式">
                    <el-select v-model="formData.output_type" class="!w-[220px]">
                        <el-option v-for="o in outputOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                    <div class="text-[12px] text-gray-400 mt-[2px]">易速出 PDF 面单；快递100 可选图片/HTML/云打印</div>
                </el-form-item>
                <el-form-item label="打印方式">
                    <el-select v-model="formData.print_channel" class="!w-[220px]">
                        <el-option label="网页打印（Mac可用）" value="browser" />
                        <el-option label="云打印" value="cloud" />
                        <el-option label="本地Lodop（Windows）" value="lodop" />
                    </el-select>
                    <div class="text-[12px] text-gray-400 mt-[2px]">Mac 建议「网页打印」：下单后拿到面单(PDF/图片)用系统打印对话框打印</div>
                </el-form-item>

                <template v-if="formData.provider === 'yisu'">
                    <el-form-item label="面单尺寸" v-if="yisuTempOptions.length">
                        <el-select v-model="formData.temp_id" class="!w-[220px]" clearable placeholder="顺丰/京东可选">
                            <el-option v-for="t in yisuTempOptions" :key="t.value" :label="t.label" :value="t.value" />
                        </el-select>
                        <div class="text-[12px] text-gray-400 mt-[2px]">易速面单 temCode，仅顺丰/京东支持</div>
                    </el-form-item>
                </template>
                <template v-else-if="formData.provider === 'kuaidi100'">
                    <el-form-item label="面单模板ID">
                        <el-input v-model.trim="formData.temp_id" placeholder="快递100 tempId（在快递100后台获取）" class="!w-[360px]" clearable />
                    </el-form-item>
                    <el-form-item label="云打印机码" v-if="formData.output_type === 'CLOUD' || formData.print_channel === 'cloud'">
                        <el-input v-model.trim="formData.siid" placeholder="云打印时必填" class="!w-[360px]" clearable />
                    </el-form-item>
                </template>
            </el-card>

            <el-card class="box-card !border-none" shadow="never">
                <h3 class="panel-title !text-sm mb-[16px]">其它设置</h3>
                <el-form-item label="付款方式">
                    <el-radio-group v-model="formData.pay_type">
                        <el-radio v-for="(label, key) in payTypeMap" :key="key" :value="Number(key)">{{ label }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="上门揽件">
                    <el-radio-group v-model="formData.is_notice">
                        <el-radio :value="1">是</el-radio>
                        <el-radio :value="0">否</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="formData.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item label="设为默认">
                    <el-switch v-model="formData.is_default" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <div class="text-[12px] text-gray-400 mt-[4px]">说明：易速/快递100 为聚合平台，账号在「接口配置」统一配置，无需在模板里重复填写月结/网点等。</div>
            </el-card>
        </el-form>

        <div class="fixed-footer-wrap">
            <div class="fixed-footer">
                <el-button type="primary" :loading="repeat" @click="confirm(formRef)">保存</el-button>
                <el-button @click="back()">取消</el-button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { ArrowLeft } from '@element-plus/icons-vue'
import type { FormInstance } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import {
    addExpressSheet, editExpressSheet, getExpressSheetInfo, getExpressSheetPayType,
    getDeliveryProviderCompanies
} from '@/addon/hsx_recycle/api/delivery'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const repeat = ref(false)
const formRef = ref<FormInstance>()

const formData = reactive<Record<string, any>>({
    id: route.query.id ? Number(route.query.id) : 0,
    template_name: '',
    provider: 'yisu',
    express_company_id: '',
    exp_type: '',
    exp_type_name: '',
    print_style: '',
    customer_name: '',
    customer_pwd: '',
    send_site: '',
    send_staff: '',
    month_code: '',
    pay_type: 1,
    output_type: 'IMAGE',
    print_channel: 'browser',
    temp_id: '',
    child_temp_id: '',
    back_temp_id: '',
    siid: '',
    is_notice: 0,
    status: 1,
    is_default: 0
})

const formRules = {
    template_name: [{ required: true, message: '请输入模板名称', trigger: 'blur' }],
    provider: [{ required: true, message: '请选择执行服务商', trigger: 'change' }],
    express_company_id: [{ required: true, message: '请选择快递公司', trigger: 'change' }]
}

const companyList = ref<any[]>([])
const expTypeList = ref<any[]>([])
const printStyleList = ref<any[]>([])
const payTypeMap = ref<Record<string, string>>({})

// 面单形式按服务商：易速只出 PDF；快递100 出 IMAGE/HTML/CLOUD
const outputOptions = computed(() => formData.provider === 'yisu'
    ? [{ label: 'PDF 面单', value: 'PDF' }]
    : [{ label: '图片 IMAGE', value: 'IMAGE' }, { label: 'HTML', value: 'HTML' }, { label: '云打印 CLOUD', value: 'CLOUD' }])

// 易速面单尺寸(temCode)：仅顺丰(5)/京东(13)，按所选公司的易速 productCode 给选项
const yisuTempOptions = computed(() => {
    const c = companyList.value.find((x) => x.company_id == formData.express_company_id)
    const code = c ? String(c.provider_code) : ''
    const map: Record<string, any[]> = {
        '5': [{ label: '100×150（默认）', value: '150' }, { label: '100×180', value: '180' }, { label: '100×210', value: '210' }],
        '13': [{ label: '100×113（默认）', value: '113' }, { label: '76×105', value: '105' }, { label: '76×130', value: '130' }, { label: '100×150', value: '150' }]
    }
    return map[code] || []
})

const defaultOutputFor = (provider: string) => provider === 'yisu' ? 'PDF' : 'IMAGE'

const fillCompanyOptions = (companyId: any, keepValue: boolean = false) => {
    const company = companyList.value.find((c) => c.company_id == companyId)
    expTypeList.value = (company && Array.isArray(company.exp_type)) ? company.exp_type : []
    printStyleList.value = (company && Array.isArray(company.print_style)) ? company.print_style : []
    if (!keepValue) {
        formData.exp_type = expTypeList.value.length ? String(expTypeList.value[0].value) : ''
        formData.exp_type_name = expTypeList.value.length ? String(expTypeList.value[0].text) : ''
        formData.print_style = printStyleList.value.length ? String(printStyleList.value[0].template_size) : ''
    }
}

const loadProviderCompanies = async (provider: string) => {
    if (!provider) { companyList.value = []; return }
    const res: any = await getDeliveryProviderCompanies(provider)
    companyList.value = res.data || []
}

const handleProviderChange = async (provider: any) => {
    formData.express_company_id = ''
    expTypeList.value = []
    printStyleList.value = []
    formData.exp_type = ''
    formData.exp_type_name = ''
    formData.print_style = ''
    formData.temp_id = ''
    formData.output_type = defaultOutputFor(provider)
    await loadProviderCompanies(provider)
}

const handleCompanyChange = (value: any) => fillCompanyOptions(value, false)

const handleExpTypeChange = (value: any) => {
    const opt = expTypeList.value.find((o) => String(o.value) === String(value))
    formData.exp_type_name = opt ? String(opt.text) : ''
}

const init = async () => {
    const payRes: any = await getExpressSheetPayType()
    payTypeMap.value = payRes.data || {}

    if (formData.id) {
        loading.value = true
        try {
            const res: any = await getExpressSheetInfo(formData.id)
            const data = res.data || {}
            Object.keys(formData).forEach((key) => {
                if (data[key] !== undefined && data[key] !== null) formData[key] = data[key]
            })
            await loadProviderCompanies(formData.provider)
            fillCompanyOptions(formData.express_company_id, true)
        } finally {
            loading.value = false
        }
    } else {
        await loadProviderCompanies(formData.provider)
    }
}
init()

const confirm = async (formEl: FormInstance | undefined) => {
    if (!formEl || repeat.value) return
    await formEl.validate((valid) => {
        if (!valid) return
        repeat.value = true
        const save = formData.id ? editExpressSheet : addExpressSheet
        save({ ...formData }).then(() => {
            repeat.value = false
            router.push('/delivery/electronic_sheet')
        }).catch(() => { repeat.value = false })
    })
}

const back = () => router.push('/delivery/electronic_sheet')
</script>

<style lang="scss" scoped></style>
