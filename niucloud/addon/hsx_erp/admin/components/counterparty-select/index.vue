<template>
    <div class="counterparty-select">
        <el-select
            v-model="picked"
            filterable
            remote
            clearable
            class="flex-1"
            :remote-method="onSearch"
            :loading="loading || resolving"
            :placeholder="placeholder"
            @change="onPick"
            @clear="onClear"
            @visible-change="visible => visible && !options.length && onSearch('')"
        >
            <el-option v-for="item in options" :key="item.member_id" :value="item.member_id" :label="labelOf(item)">
                <span class="font-medium">{{ nameOf(item) }}</span>
                <span class="ml-1 text-xs text-gray-400">{{ item.mobile || '无手机' }}</span>
                <el-tag v-if="item.party_name || item.counterparty_name" size="small" effect="plain" class="ml-2">{{ item.party_name || item.counterparty_name }}</el-tag>
                <span v-else class="ml-2 text-xs text-orange-500">无主体 · 选后自动建</span>
            </el-option>
        </el-select>
        <el-button @click="createVisible = true">新建</el-button>

        <el-dialog v-model="createVisible" title="新建对接人" width="460px" append-to-body @closed="resetCreate">
            <el-form label-width="84px">
                <el-form-item label="姓名" required>
                    <el-input v-model.trim="createForm.name" placeholder="对接人姓名" />
                </el-form-item>
                <el-form-item label="手机号" required>
                    <el-input v-model.trim="createForm.mobile" placeholder="用于建档和对账锚点" />
                </el-form-item>
                <el-form-item label="M号">
                    <el-input v-model.trim="createForm.m_no" placeholder="可选，客户M号/业务编号" />
                </el-form-item>
            </el-form>
            <div class="-mt-2 mb-2 text-xs text-gray-400">将自动创建会员并绑定往来主体，后续挂账、折账都按这个主体归集。</div>
            <template #footer>
                <el-button @click="createVisible = false">取消</el-button>
                <el-button type="primary" :loading="creating" @click="doCreate">建并选用</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getErpMemberOptions, resolveErpContact } from '@/addon/hsx_erp/api/counterparty'

const props = withDefaults(defineProps<{
    modelValue?: number | string
    roleType?: string
    placeholder?: string
    valueField?: 'party_id' | 'counterparty_id' | 'member_id' | 'id'
}>(), {
    modelValue: 0,
    roleType: 'customer',
    placeholder: '搜索姓名 / 手机号选择对接人',
    valueField: 'party_id'
})

const emit = defineEmits(['update:modelValue', 'resolved'])

const picked = ref<number | undefined>(undefined)
const options = ref<any[]>([])
const loading = ref(false)
const resolving = ref(false)

const nameOf = (item: any) => item.nickname || item.username || item.member_name || `会员#${item.member_id}`
const labelOf = (item: any) => {
    const partyName = item.party_name || item.counterparty_name
    return [nameOf(item), item.mobile, partyName].filter(Boolean).join(' / ')
}

async function onSearch(keyword: string) {
    loading.value = true
    try {
        const res: any = await getErpMemberOptions({ keyword: keyword || '' })
        options.value = res?.data || []
    } catch (error: any) {
        options.value = []
        ElMessage.error(error?.msg || error?.message || '对接人列表加载失败')
    } finally {
        loading.value = false
    }
}

async function onPick(memberId: number) {
    if (!memberId) return onClear()
    resolving.value = true
    try {
        const res: any = await resolveErpContact({ member_id: memberId, role_type: props.roleType })
        const data = normalize(res?.data || {})
        emit('update:modelValue', data[props.valueField] || data.party_id || 0)
        emit('resolved', data)
        if (data.auto_created) ElMessage.success(`已为「${data.member_name || ''}」自动创建往来主体`)
    } catch (error: any) {
        ElMessage.error(error?.msg || error?.message || '对接人解析失败')
        onClear()
    } finally {
        resolving.value = false
    }
}

function onClear() {
    picked.value = undefined
    emit('update:modelValue', 0)
    emit('resolved', null)
}

const createVisible = ref(false)
const creating = ref(false)
const createForm = ref({ name: '', mobile: '', m_no: '' })

function resetCreate() {
    createForm.value = { name: '', mobile: '', m_no: '' }
}

async function doCreate() {
    if (!createForm.value.name) return ElMessage.warning('请填写姓名')
    if (!createForm.value.mobile) return ElMessage.warning('请填写手机号')
    creating.value = true
    try {
        const res: any = await resolveErpContact({ ...createForm.value, role_type: props.roleType })
        const data = normalize(res?.data || {})
        options.value = [{ member_id: data.member_id, nickname: data.member_name, mobile: data.mobile, party_name: data.party_name, m_no: data.m_no }, ...options.value]
        picked.value = Number(data.member_id || 0)
        emit('update:modelValue', data[props.valueField] || data.party_id || 0)
        emit('resolved', data)
        createVisible.value = false
        ElMessage.success('对接人已创建并选用')
    } catch (error: any) {
        ElMessage.error(error?.msg || error?.message || '对接人创建失败')
    } finally {
        creating.value = false
    }
}

function normalize(data: any) {
    return {
        ...data,
        id: data.party_id || data.counterparty_id || data.id || 0,
        party_id: data.party_id || data.counterparty_id || data.id || 0,
        party_name: data.party_name || data.counterparty_name || data.name || '',
        counterparty_id: data.counterparty_id || data.party_id || data.id || 0,
        counterparty_name: data.counterparty_name || data.party_name || data.name || ''
    }
}

watch(() => props.modelValue, value => {
    if (!value) picked.value = undefined
})
</script>

<style scoped>
.counterparty-select {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 8px;
}
</style>
