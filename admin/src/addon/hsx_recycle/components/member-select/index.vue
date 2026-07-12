<template>
    <div class="member-select" @click="openSelector">
        <el-input
            :model-value="selectedMemberLabel"
            :placeholder="placeholder"
            readonly
            :disabled="disabled"
            clearable
            @clear.stop="clearSelection"
        >
            <template #prefix><el-icon><User /></el-icon></template>
            <template #suffix><el-icon v-if="!modelValue"><Search /></el-icon></template>
        </el-input>
    </div>

    <el-dialog
        v-model="selectorVisible"
        title="选择客户"
        width="720px"
        append-to-body
        destroy-on-close
        class="member-selector-dialog"
    >
        <div class="member-selector__toolbar">
            <el-input
                v-model.trim="searchParams.keyword"
                clearable
                placeholder="搜索昵称、手机号或会员编号"
                @keyup.enter="searchMembers"
                @clear="searchMembers"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button type="primary" :icon="Search" @click="searchMembers">搜索</el-button>
            <el-button :icon="Plus" @click="openCreateDialog">新增客户</el-button>
        </div>

        <el-table
            v-loading="loading"
            :data="memberList"
            height="360"
            highlight-current-row
            empty-text="暂无客户，可点击右上角新增"
            @row-dblclick="selectMember"
        >
            <el-table-column label="客户" min-width="210">
                <template #default="{ row }">
                    <div class="member-cell">
                        <el-avatar :size="34" :src="row.headimg ? img(row.headimg) : ''">
                            {{ memberInitial(row) }}
                        </el-avatar>
                        <div class="member-cell__body">
                            <div class="member-cell__name">{{ row.nickname || row.username || '未设置昵称' }}</div>
                            <div class="member-cell__no">{{ row.member_no || '暂无会员编号' }}</div>
                        </div>
                    </div>
                </template>
            </el-table-column>
            <el-table-column prop="mobile" label="手机号" width="140">
                <template #default="{ row }">{{ row.mobile || '-' }}</template>
            </el-table-column>
            <el-table-column prop="member_level_name" label="会员等级" min-width="110">
                <template #default="{ row }">
                    <el-tag v-if="row.member_level_name" size="small" type="info" effect="plain">
                        {{ row.member_level_name }}
                    </el-tag>
                    <span v-else>-</span>
                </template>
            </el-table-column>
            <el-table-column label="操作" width="80" align="right">
                <template #default="{ row }">
                    <el-button type="primary" link @click="selectMember(row)">选择</el-button>
                </template>
            </el-table-column>
        </el-table>

        <div class="member-selector__footer">
            <span class="member-selector__tip">双击客户也可以快速选择</span>
            <el-pagination
                v-model:current-page="searchParams.page"
                :page-size="searchParams.limit"
                :total="total"
                layout="total, prev, pager, next"
                small
                background
                @current-change="loadMembers"
            />
        </div>
    </el-dialog>

    <el-dialog
        v-model="createVisible"
        title="新增客户"
        width="520px"
        append-to-body
        destroy-on-close
        :close-on-click-modal="false"
    >
        <el-alert
            title="新建后会自动选中该客户，不需要返回列表再次搜索。"
            type="info"
            show-icon
            :closable="false"
            class="create-member__alert"
        />
        <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="88px">
            <el-form-item label="会员编号" prop="member_no">
                <el-input v-model.trim="createForm.member_no" maxlength="20" clearable />
            </el-form-item>
            <el-form-item label="手机号" prop="mobile">
                <el-input v-model.trim="createForm.mobile" maxlength="11" clearable placeholder="用于登录和识别客户" />
            </el-form-item>
            <el-form-item label="客户称呼">
                <el-input v-model.trim="createForm.nickname" maxlength="20" clearable placeholder="例如：张先生" />
            </el-form-item>
            <el-form-item label="登录密码" prop="password">
                <el-input v-model="createForm.password" type="password" show-password clearable autocomplete="new-password" />
            </el-form-item>
            <el-form-item label="确认密码" prop="password_copy">
                <el-input v-model="createForm.password_copy" type="password" show-password clearable autocomplete="new-password" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="createVisible = false">取消</el-button>
            <el-button type="primary" :loading="creating" @click="createMember">新增并选择</el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, reactive, ref, watch } from 'vue'
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { Plus, Search, User } from '@element-plus/icons-vue'
import { addMember, getMemberInfo, getMemberList, getMemberNo } from '@/app/api/member'
import { img } from '@/utils/common'

interface Member {
    member_id: number | string
    member_no?: string
    nickname?: string
    username?: string
    mobile?: string
    headimg?: string
    member_level_name?: string
}

const props = withDefaults(defineProps<{
    modelValue?: number | string | null
    placeholder?: string
    showPagination?: boolean
    disabled?: boolean
}>(), {
    modelValue: null,
    placeholder: '点击选择客户',
    showPagination: false,
    disabled: false
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: number | string | null): void
    (event: 'change', value: number | string | null, member: Member | null): void
}>()

const selectorVisible = ref(false)
const createVisible = ref(false)
const loading = ref(false)
const creating = ref(false)
const memberList = ref<Member[]>([])
const selectedMember = ref<Member | null>(null)
const total = ref(0)
const createFormRef = ref<FormInstance>()

const searchParams = reactive({ page: 1, limit: 10, keyword: '' })
const createForm = reactive({
    member_no: '',
    init_member_no: '',
    mobile: '',
    nickname: '',
    password: '',
    password_copy: ''
})

const selectedMemberLabel = computed(() => {
    if (!selectedMember.value) return props.modelValue ? `客户 ID：${props.modelValue}` : ''
    const name = selectedMember.value.nickname || selectedMember.value.username || '未设置昵称'
    return [name, selectedMember.value.mobile].filter(Boolean).join(' · ')
})

const createRules: FormRules = {
    member_no: [
        { required: true, message: '请输入会员编号', trigger: 'blur' },
        { pattern: /^[0-9a-zA-Z]+$/, message: '会员编号只能包含数字和字母', trigger: 'blur' }
    ],
    mobile: [
        { required: true, message: '请输入手机号', trigger: 'blur' },
        { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号', trigger: 'blur' }
    ],
    password: [{ required: true, message: '请输入登录密码', trigger: 'blur' }],
    password_copy: [
        { required: true, message: '请再次输入密码', trigger: 'blur' },
        {
            validator: (_rule, value, callback) => {
                value === createForm.password ? callback() : callback(new Error('两次输入的密码不一致'))
            },
            trigger: 'blur'
        }
    ]
}

const memberInitial = (member: Member) => String(member.nickname || member.username || '客').slice(0, 1)

const loadMembers = async () => {
    loading.value = true
    try {
        const res: any = await getMemberList({
            page: searchParams.page,
            limit: searchParams.limit,
            keyword: searchParams.keyword,
            status: 1
        })
        memberList.value = res.data?.data || []
        total.value = Number(res.data?.total || memberList.value.length)
    } finally {
        loading.value = false
    }
}

const openSelector = () => {
    if (props.disabled) return
    selectorVisible.value = true
    searchParams.page = 1
    loadMembers()
}

const searchMembers = () => {
    searchParams.page = 1
    loadMembers()
}

const selectMember = (member: Member) => {
    selectedMember.value = member
    emit('update:modelValue', member.member_id)
    emit('change', member.member_id, member)
    selectorVisible.value = false
}

const clearSelection = () => {
    selectedMember.value = null
    emit('update:modelValue', null)
    emit('change', null, null)
}

const resetCreateForm = async () => {
    Object.assign(createForm, {
        member_no: '', init_member_no: '', mobile: '', nickname: '', password: '', password_copy: ''
    })
    createFormRef.value?.clearValidate()
    try {
        const res: any = await getMemberNo()
        createForm.member_no = String(res.data || '')
        createForm.init_member_no = createForm.member_no
    } catch (error) {
        console.error('获取会员编号失败:', error)
    }
}

const openCreateDialog = async () => {
    createVisible.value = true
    await resetCreateForm()
}

const createMember = async () => {
    if (!createFormRef.value || creating.value) return
    const valid = await createFormRef.value.validate().catch(() => false)
    if (!valid) return
    creating.value = true
    try {
        const res: any = await addMember({ ...createForm })
        let member: Member | null = null
        const memberId = res.data?.member_id || res.data?.id || res.data
        if (memberId && !Number.isNaN(Number(memberId))) {
            const detail: any = await getMemberInfo(Number(memberId))
            member = detail.data || null
        }
        if (!member) {
            const listRes: any = await getMemberList({ keyword: createForm.mobile, page: 1, limit: 10, status: 1 })
            member = (listRes.data?.data || []).find((item: Member) => item.mobile === createForm.mobile) || null
        }
        if (!member) throw new Error('客户已新增，但未能自动读取客户资料')
        createVisible.value = false
        ElMessage.success('客户已新增并选中')
        selectMember(member)
    } catch (error: any) {
        if (error?.message) ElMessage.error(error.message)
    } finally {
        creating.value = false
    }
}

const loadSelectedMember = async (value: number | string | null | undefined) => {
    if (!value) {
        selectedMember.value = null
        return
    }
    if (String(selectedMember.value?.member_id || '') === String(value)) return
    try {
        const res: any = await getMemberInfo(Number(value))
        selectedMember.value = res.data || null
    } catch (error) {
        selectedMember.value = null
    }
}

watch(() => props.modelValue, loadSelectedMember, { immediate: true })
</script>

<style lang="scss" scoped>
.member-select { width: 100%; min-width: 0; cursor: pointer; }
.member-select :deep(.el-input__inner) { cursor: pointer; }
.member-selector__toolbar { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 8px; margin-bottom: 12px; }
.member-selector__footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; }
.member-selector__tip { color: var(--el-text-color-secondary); font-size: 12px; }
.member-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
.member-cell__body { min-width: 0; }
.member-cell__name { overflow: hidden; color: var(--el-text-color-primary); font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.member-cell__no { margin-top: 2px; color: var(--el-text-color-secondary); font-size: 12px; }
.create-member__alert { margin-bottom: 18px; }
@media (max-width: 720px) {
    .member-selector__toolbar { grid-template-columns: minmax(0, 1fr) auto; }
    .member-selector__toolbar > :last-child { grid-column: 1 / -1; }
    .member-selector__footer { align-items: flex-end; flex-direction: column; }
}
</style>
