<template>
    <el-dialog v-model="showDialog" :title="t('updateOfflinepay')" width="800px" :destroy-on-close="true">
        <el-form :model="formData" label-width="110px" ref="formRef" class="page-form" v-loading="loading">
            <!-- 账户列表 - 使用Tab切换 -->
            <el-tabs v-model="activeAccount" type="card" editable @edit="handleTabEdit">
                <el-tab-pane
                    v-for="(account, index) in formData.config.accounts"
                    :key="index"
                    :label="account.name || `账户${index + 1}`"
                    :name="String(index)"
                >
                    <el-form-item label="账户名称" :prop="`config.accounts.${index}.name`" :rules="[{ required: true, message: '请输入账户名称', trigger: 'blur' }]">
                        <el-input v-model.trim="account.name" placeholder="如:微信1、支付宝1、银行卡1" clearable />
                    </el-form-item>

                    <el-form-item label="账户类型" :prop="`config.accounts.${index}.type`" :rules="[{ required: true, message: '请选择账户类型', trigger: 'change' }]">
                        <el-select v-model="account.type" placeholder="请选择账户类型">
                            <el-option label="微信" value="wechat" />
                            <el-option label="支付宝" value="alipay" />
                            <el-option label="银行卡" value="bank" />
                            <!-- 现金 -->
                            <el-option label="现金" value="cash" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="当前余额">
                        <el-input-number v-model="account.balance" :precision="2" :min="0" :step="100" />
                        <span class="ml-2 text-gray-500">元(阶段1仅显示,不自动计算)</span>
                    </el-form-item>

                    <el-form-item label="收款二维码">
                        <upload-image v-model="account.qrcode" />
                    </el-form-item>

                    <!-- 银行卡特有字段 -->
                    <template v-if="account.type === 'bank'">
                        <el-form-item label="开户银行">
                            <el-input v-model.trim="account.bank_name" placeholder="如:中国工商银行" clearable />
                        </el-form-item>
                        <el-form-item label="银行账号">
                            <el-input v-model.trim="account.account_no" placeholder="银行卡号" clearable />
                        </el-form-item>
                        <el-form-item label="户名">
                            <el-input v-model.trim="account.account_name" placeholder="开户人姓名" clearable />
                        </el-form-item>
                    </template>

                    <!-- 微信/支付宝字段 -->
                    <template v-else>
                        <el-form-item label="账号">
                            <el-input v-model.trim="account.account_no" placeholder="微信号/支付宝账号" clearable />
                        </el-form-item>
                        <el-form-item label="姓名">
                            <el-input v-model.trim="account.account_name" placeholder="收款人姓名" clearable />
                        </el-form-item>
                    </template>

                    <el-form-item label="备注">
                        <el-input v-model.trim="account.remark" type="textarea" rows="3" placeholder="账户备注说明" clearable />
                    </el-form-item>
                </el-tab-pane>
            </el-tabs>

            <div class="mt-4">
                <el-button @click="addAccount" type="primary" size="small">+ 添加账户</el-button>
            </div>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" :loading="loading" @click="confirm(formRef)">{{ t('confirm') }}</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive } from 'vue'
import { t } from '@/lang'
import { ElMessage } from 'element-plus'
import type { FormInstance } from 'element-plus'

const showDialog = ref(false)
const loading = ref(false)
const activeAccount = ref('0')

/**
 * 表单数据
 */
const initialFormData = {
    type: 'hsx_offlinepay',
    config: {
        accounts: [
            {
                name: '微信1',
                type: 'wechat',
                balance: 0,
                qrcode: '',
                account_no: '',
                account_name: '',
                bank_name: '',
                remark: ''
            }
        ]
    },
    channel: '',
    status: 0,
    is_default: 0
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

const emit = defineEmits(['complete'])

/**
 * 添加账户
 */
const addAccount = () => {
    formData.config.accounts.push({
        name: `账户${formData.config.accounts.length + 1}`,
        type: 'wechat',
        balance: 0,
        qrcode: '',
        account_no: '',
        account_name: '',
        bank_name: '',
        remark: ''
    })
    activeAccount.value = String(formData.config.accounts.length - 1)
}

/**
 * 处理Tab编辑(删除账户)
 */
const handleTabEdit = (targetName: string | number, action: 'remove' | 'add') => {
    if (action === 'remove') {
        if (formData.config.accounts.length <= 1) {
            ElMessage.warning('至少保留一个账户')
            return
        }
        const index = Number(targetName)
        formData.config.accounts.splice(index, 1)
        activeAccount.value = '0'
    } else if (action === 'add') {
        addAccount()
    }
}

/**
 * 确认
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return

    await formEl.validate((valid) => {
        if (valid && formData.config.accounts.length > 0) {
            emit('complete', formData)
            showDialog.value = false
        }
    })
}

const setFormData = async (data: any = null) => {
    loading.value = true

    // 重置为初始数据
    Object.assign(formData, JSON.parse(JSON.stringify(initialFormData)))

    if (data) {
        // 直接赋值配置数据
        if (data.config?.accounts) {
            formData.config = data.config
        }
        formData.channel = data.redio_key ? data.redio_key.split('_')[0] : ''
        formData.status = Number(data.status)
    }

    activeAccount.value = '0'
    loading.value = false
}

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped>
.ml-2 {
    margin-left: 8px;
}
.text-gray-500 {
    color: #6b7280;
}
.mt-4 {
    margin-top: 16px;
}
</style>
