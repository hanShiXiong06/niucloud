<template>
    <div class="flex w-full items-center gap-2">
        <el-select
            v-model="picked"
            filterable
            remote
            :remote-method="onSearch"
            :loading="loading || resolving"
            clearable
            class="flex-1"
            :placeholder="placeholder"
            :disabled="disabled"
            @change="onPick"
            @clear="onClear"
            @visible-change="onVisible">
            <el-option v-if="picked && initialLabel && !options.length" :value="picked" :label="initialLabel" />
            <el-option v-for="p in options" :key="p.member_id" :value="p.member_id" :label="labelOf(p)">
                <span class="font-medium">{{ nameOf(p) }}</span>
                <span class="ml-1 text-xs text-gray-400">{{ p.mobile || '无手机' }}</span>
                <el-tag v-if="p.counterparty_name" size="small" effect="plain" class="ml-2">{{ p.counterparty_name }}</el-tag>
                <span v-else class="ml-2 text-xs text-orange-500">无主体 · 选后自动建</span>
            </el-option>
        </el-select>
        <el-button :disabled="disabled" @click="createVisible = true">新建</el-button>

        <el-dialog v-model="createVisible" title="新建对接人" width="420px" append-to-body @closed="resetCreate">
            <el-form label-width="80px">
                <el-form-item label="姓名" required>
                    <el-input v-model.trim="createForm.name" placeholder="对接人姓名" />
                </el-form-item>
                <el-form-item label="手机号" required>
                    <el-input v-model.trim="createForm.mobile" placeholder="用于建档 / 对账锚点" />
                </el-form-item>
            </el-form>
            <div class="-mt-2 mb-2 text-xs text-gray-400">将自动建会员并关联一个个人往来主体,选用后即可记账。</div>
            <template #footer>
                <el-button @click="createVisible = false">取消</el-button>
                <el-button type="primary" :loading="creating" @click="doCreate">建并选用</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getErpMemberOptions, resolveErpContact } from '@/addon/hsx_erp/api/counterparty'

const props = withDefaults(defineProps<{
    modelValue?: number
    roleType?: string
    placeholder?: string
    initialLabel?: string
    disabled?: boolean
    // v-model 绑定哪个 id:counterparty_id(主体, 默认, 入库/资产用) 或 member_id(人, 财务/出库用)
    valueField?: 'counterparty_id' | 'member_id'
}>(), {
    modelValue: 0,
    roleType: 'customer',
    placeholder: '搜索姓名 / 手机号选择对接人',
    initialLabel: '',
    disabled: false,
    valueField: 'counterparty_id',
})

const emit = defineEmits(['update:modelValue', 'resolved'])

const picked = ref<number>(0) // 选择项以 member_id(人)为展示锚
const options = ref<any[]>([])
const loading = ref(false)
const resolving = ref(false)

const nameOf = (p: any) => p.nickname || p.username || ('会员#' + p.member_id)
const labelOf = (p: any) =>
    `${nameOf(p)}${p.mobile ? ' · ' + p.mobile : ''}${p.counterparty_name ? ' · ' + p.counterparty_name : ''}`

const onSearch = async (kw: string) => {
    loading.value = true
    try {
        const res: any = await getErpMemberOptions({ keyword: kw || '' })
        options.value = res.data || []
    } finally {
        loading.value = false
    }
}
const onVisible = (v: boolean) => { if (v && !options.value.length) onSearch('') }

// 选人即解析往来单位:无主体则后端自动建个人主体
const onPick = async (memberId: number) => {
    if (!memberId) { onClear(); return }
    resolving.value = true
    try {
        const res: any = await resolveErpContact({ member_id: memberId, role_type: props.roleType })
        const d = res.data || {}
        emit('update:modelValue', Number(d[props.valueField] || 0))
        emit('resolved', d)
        if (d.auto_created) ElMessage.success(`已为「${d.member_name || ''}」自动创建往来主体`)
    } finally {
        resolving.value = false
    }
}
const onClear = () => { picked.value = 0; emit('update:modelValue', 0); emit('resolved', null) }

// 新建对接人
const createVisible = ref(false)
const creating = ref(false)
const createForm = ref({ name: '', mobile: '' })
const resetCreate = () => { createForm.value = { name: '', mobile: '' } }
const doCreate = async () => {
    if (!createForm.value.name) { ElMessage.warning('请填写姓名'); return }
    if (!createForm.value.mobile) { ElMessage.warning('请填写手机号'); return }
    creating.value = true
    try {
        const res: any = await resolveErpContact({
            name: createForm.value.name,
            mobile: createForm.value.mobile,
            role_type: props.roleType,
        })
        const d = res.data || {}
        options.value = [
            { member_id: d.member_id, nickname: d.member_name, mobile: d.mobile, counterparty_name: d.counterparty_name },
            ...options.value,
        ]
        picked.value = Number(d.member_id || 0)
        emit('update:modelValue', Number(d[props.valueField] || 0))
        emit('resolved', d)
        createVisible.value = false
        ElMessage.success('对接人已创建并选用')
    } finally {
        creating.value = false
    }
}

watch(() => props.modelValue, (v) => { if (!v) picked.value = 0 })
</script>
