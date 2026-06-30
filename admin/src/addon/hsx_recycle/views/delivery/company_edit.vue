<template>
    <div class="main-container">
        <el-card class="card !border-none mb-[15px]" shadow="never">
            <el-page-header :content="formData.company_id ? '编辑快递公司' : '新增快递公司'" :icon="ArrowLeft" @back="back" />
        </el-card>

        <el-form class="page-form" :model="formData" :rules="formRules" label-width="120px" ref="formRef" v-loading="loading">
            <el-card class="box-card !border-none" shadow="never">
                <h3 class="panel-title !text-sm mb-[16px]">基本信息</h3>
                <el-form-item label="公司名称" prop="company_name">
                    <el-input v-model.trim="formData.company_name" placeholder="如 顺丰速运" maxlength="20" class="!w-[360px]" clearable />
                </el-form-item>
                <el-form-item label="LOGO">
                    <el-input v-model.trim="formData.logo" placeholder="图片链接（可选）" class="!w-[360px]" clearable />
                </el-form-item>
                <el-form-item label="官网链接">
                    <el-input v-model.trim="formData.url" placeholder="可选" class="!w-[360px]" clearable />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="formData.sort" :min="0" :max="9999" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="formData.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-card>

            <el-card v-for="p in providers" :key="p.key" class="box-card !border-none" shadow="never">
                <div class="flex items-center justify-between mb-[12px]">
                    <h3 class="panel-title !text-sm !mb-0">{{ p.name }} 绑定</h3>
                    <div class="flex items-center text-[13px] text-gray-500">
                        出面单
                        <el-switch class="ml-[8px]" v-model="bindings[p.key].electronic_sheet_switch" :active-value="1" :inactive-value="0" />
                    </div>
                </div>
                <el-form-item :label="p.codeLabel">
                    <el-input v-model.trim="bindings[p.key].provider_code" :placeholder="p.codePlaceholder" class="!w-[360px]" clearable />
                    <div class="text-[12px] text-gray-400 mt-[2px]">{{ p.codeTip }}</div>
                </el-form-item>

                <template v-if="bindings[p.key].electronic_sheet_switch">
                    <el-form-item label="业务类型">
                        <div class="w-[520px]">
                            <el-table :data="bindings[p.key].exp_type" size="small" border>
                                <el-table-column label="显示名" min-width="150">
                                    <template #default="{ row }"><el-input v-model.trim="row.text" placeholder="如 标准快递" /></template>
                                </el-table-column>
                                <el-table-column label="值" min-width="110">
                                    <template #default="{ row }"><el-input v-model.trim="row.value" placeholder="如 1" /></template>
                                </el-table-column>
                                <el-table-column label="操作" width="70" align="center">
                                    <template #default="{ $index }"><el-button type="danger" link @click="bindings[p.key].exp_type.splice($index, 1)">删除</el-button></template>
                                </el-table-column>
                            </el-table>
                            <el-button class="mt-[8px]" @click="bindings[p.key].exp_type.push({ text: '', value: '' })">添加业务类型</el-button>
                        </div>
                    </el-form-item>
                    <el-form-item label="打印样式">
                        <div class="w-[520px]">
                            <el-table :data="bindings[p.key].print_style" size="small" border>
                                <el-table-column label="显示名" min-width="150">
                                    <template #default="{ row }"><el-input v-model.trim="row.template_name" placeholder="如 100x180" /></template>
                                </el-table-column>
                                <el-table-column label="样式标识" min-width="110">
                                    <template #default="{ row }"><el-input v-model.trim="row.template_size" placeholder="如 180" /></template>
                                </el-table-column>
                                <el-table-column label="操作" width="70" align="center">
                                    <template #default="{ $index }"><el-button type="danger" link @click="bindings[p.key].print_style.splice($index, 1)">删除</el-button></template>
                                </el-table-column>
                            </el-table>
                            <el-button class="mt-[8px]" @click="bindings[p.key].print_style.push({ template_name: '', template_size: '' })">添加打印样式</el-button>
                        </div>
                    </el-form-item>
                </template>
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
import { ref, reactive } from 'vue'
import { ArrowLeft } from '@element-plus/icons-vue'
import type { FormInstance } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { addDeliveryCompany, editDeliveryCompany, getDeliveryCompanyInfo } from '@/addon/hsx_recycle/api/delivery'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const repeat = ref(false)
const formRef = ref<FormInstance>()

const providers = [
    { key: 'kuaidi100', name: '快递100', codeLabel: '快递100编码', codePlaceholder: 'kuaidicom，如 shunfeng', codeTip: '快递100公司编码（kuaidicom）' },
    { key: 'yisu', name: '易速快递', codeLabel: '易速产品码', codePlaceholder: 'productCode，如 5', codeTip: '易速产品编号（仅顺丰5/京东13 出面单）' }
]

const emptyBinding = () => ({ provider_code: '', electronic_sheet_switch: 0, exp_type: [] as any[], print_style: [] as any[] })
const bindings = reactive<Record<string, any>>({ kuaidi100: emptyBinding(), yisu: emptyBinding() })

const formData = reactive<Record<string, any>>({
    company_id: route.query.company_id ? Number(route.query.company_id) : 0,
    company_name: '',
    logo: '',
    url: '',
    sort: 0,
    status: 1
})

const formRules = {
    company_name: [{ required: true, message: '请输入公司名称', trigger: 'blur' }]
}

const init = () => {
    if (!formData.company_id) return
    loading.value = true
    getDeliveryCompanyInfo(formData.company_id).then((res: any) => {
        const data = res.data || {}
        ;['company_name', 'logo', 'url', 'sort', 'status'].forEach((k) => {
            if (data[k] !== undefined && data[k] !== null) formData[k] = data[k]
        })
        ;(data.bindings || []).forEach((b: any) => {
            const key = b.provider
            if (!bindings[key]) return
            bindings[key].provider_code = b.provider_code || ''
            bindings[key].electronic_sheet_switch = Number(b.electronic_sheet_switch || 0)
            bindings[key].exp_type = Array.isArray(b.exp_type) ? b.exp_type : []
            bindings[key].print_style = Array.isArray(b.print_style) ? b.print_style : []
        })
        loading.value = false
    }).catch(() => { loading.value = false })
}
init()

const buildBindings = () => {
    const list: any[] = []
    providers.forEach((p) => {
        const b = bindings[p.key]
        const has = (b.provider_code && b.provider_code !== '') || b.electronic_sheet_switch === 1
            || (b.exp_type && b.exp_type.length) || (b.print_style && b.print_style.length)
        if (has) {
            list.push({
                provider: p.key,
                provider_code: b.provider_code,
                electronic_sheet_switch: b.electronic_sheet_switch,
                exp_type: b.exp_type,
                print_style: b.print_style
            })
        }
    })
    return list
}

const confirm = async (formEl: FormInstance | undefined) => {
    if (!formEl || repeat.value) return
    await formEl.validate((valid) => {
        if (!valid) return
        repeat.value = true
        const payload = { ...formData, bindings: buildBindings() }
        const save = formData.company_id ? editDeliveryCompany : addDeliveryCompany
        save(payload).then(() => {
            repeat.value = false
            router.push('/delivery/company')
        }).catch(() => { repeat.value = false })
    })
}

const back = () => router.push('/delivery/company')
</script>

<style lang="scss" scoped></style>
