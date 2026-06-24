<template>
    <el-dialog
        v-model="dialogVisible"
        title="代客户下单"
        :width="isMobile ? '95vw' : '1120px'"
        top="4vh"
        class="add-order-dialog hsx-premium-overlay"
        :destroy-on-close="true"
        @closed="handleClosed"
    >
        <el-form
            ref="formRef"
            :model="form"
            :label-width="isMobile ? '80px' : '100px'"
            :label-position="isMobile ? 'top' : 'right'"
        >
            <el-form-item label="选择会员" required>
                <div class="member-select">
                    <el-input v-model="memberSearch" placeholder="输入会员手机号/昵称/用户名搜索" clearable :disabled="!!draftOrder.id"
                        @input="handleSearchInput">
                        <template #append>
                            <el-button @click="searchMember" :disabled="!!draftOrder.id">
                                <el-icon><search /></el-icon>
                            </el-button>
                        </template>
                    </el-input>

                    <div v-if="memberSearchResults.length > 0" class="search-results">
                        <div v-for="(user, index) in memberSearchResults" :key="index" class="search-result-item"
                            @click="handleMemberSelect(user)">
                            <div class="flex items-center">
                                <el-avatar :size="30" :src="user.headimg || ''" />
                                <div class="member-info">
                                    <div class="member-name">{{ user.nickname || user.username || '未设置昵称' }}</div>
                                    <div class="member-mobile">{{ user.mobile || '未绑定手机' }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-if="hasMoreMembers" class="search-result-more" @click="loadMoreMembers">
                            加载更多...
                        </div>
                    </div>

                    <div v-if="form.member_id && selectedMember" class="selected-member transition-all duration-300">
                        <div class="flex items-center justify-between transition-all duration-300">
                            <div class="flex items-center">
                                <el-avatar :size="40" :src="selectedMember.headimg || ''" />
                                <div class="member-info">
                                    <div class="member-name">
                                        {{ selectedMember.nickname || selectedMember.username || '未设置昵称' }}
                                    </div>
                                    <div class="member-mobile">{{ selectedMember.mobile || '未绑定手机' }}</div>
                                </div>
                            </div>
                            <el-button link type="danger" @click="clearSelectedMember" :disabled="!!draftOrder.id">
                                <el-icon><delete /></el-icon>
                            </el-button>
                        </div>
                    </div>
                </div>
            </el-form-item>

            <el-form-item label="下单方式">
                <el-radio-group v-model="form.delivery_type" :disabled="!!draftOrder.id">
                    <el-radio label="1">快递</el-radio>
                    <el-radio label="2">门店</el-radio>
                </el-radio-group>
            </el-form-item>

            <el-form-item label="快递单号" v-if="form.delivery_type === '1'">
                <div class="express-input-wrapper">
                    <el-input ref="expressInput" v-model="form.express_no" placeholder="请输入快递单号或使用扫码枪"
                        @keydown.enter="handleScannerInput" @focus="handleInputFocus" @blur="handleInputBlur" :disabled="!!draftOrder.id" />
                    <el-button type="primary" @click="activateScanMode" :disabled="!!draftOrder.id">
                        <el-icon><ZoomOut /></el-icon>
                        扫码
                    </el-button>
                </div>
                <div class="scan-tip" v-if="isScanMode">
                    <el-icon><Loading class="is-loading" /></el-icon>
                    <span>准备扫码中，请对准条码...</span>
                </div>
            </el-form-item>

            <el-form-item label="" class="device-form-item">
                <DeviceEntryList
                    :devices="form.devices"
                    :order-id="draftOrder.id || ''"
                    :ensure-order="ensureDraftOrder"
                >
                    <template #head-tip>
                        <el-tag v-if="draftOrder.id" type="info" size="small" effect="plain">草稿：{{ draftOrder.order_no }}</el-tag>
                    </template>
                </DeviceEntryList>
            </el-form-item>
        </el-form>

        <template #footer>
            <div :class="isMobile ? 'dialog-footer mobile-footer' : 'dialog-footer'">
                <el-button :class="isMobile ? '!ml-0 w-full' : ''" @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :class="isMobile ? '!ml-0 w-full' : ''" @click="handleConfirm" :loading="loading" :disabled="savedDeviceCount === 0">
                    完成签收
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Delete, Loading, ZoomOut } from '@element-plus/icons-vue'
import { createRecycleOrder, getUserByMobile, updateRecycleOrder } from '@/addon/hsx_recycle/api/recycle_order'
import DeviceEntryList from '@/addon/hsx_recycle/components/device-entry/DeviceEntryList.vue'
import { normalizeDevice } from '@/addon/hsx_recycle/components/device-entry/deviceUtil'
import type { DeviceEntryRow } from '@/addon/hsx_recycle/components/device-entry/types'

interface Member {
    member_id: string | number;
    nickname?: string;
    username?: string;
    mobile?: string;
    headimg?: string;
}

const props = defineProps({
    visible: { type: Boolean, default: false }
})
const emit = defineEmits(['update:visible', 'success', 'closed'])

const dialogVisible = ref(props.visible)
const loading = ref(false)
const formRef = ref()
const form = ref({
    member_id: '' as string | number,
    delivery_type: '1',
    express_no: '',
    devices: [
        { imei: '', model: '', initial_price: 0, summary_fields: [], summary_values: {} }
    ] as DeviceEntryRow[]
})
const draftOrder = ref<{ id: number | string; order_no: string }>({ id: '', order_no: '' })

const savedDeviceCount = computed(() => form.value.devices.filter(d => d.saved && d.id).length)

// 会员搜索
const memberSearch = ref('')
const memberSearchResults = ref<Member[]>([])
const memberPage = ref(1)
const memberPageSize = ref(10)
const hasMoreMembers = ref(false)
const selectedMember = ref<Member | null>(null)
const searchTimer = ref<number | null>(null)
const isMobile = ref(false)

// 扫码
const expressInput = ref<any>(null)
const isScanMode = ref(false)
const scanBuffer = ref('')
const scanTimer = ref<any>(null)

const updateResponsiveState = () => { isMobile.value = window.innerWidth <= 768 }

watch(() => props.visible, (v) => { dialogVisible.value = v })
watch(dialogVisible, (v) => { emit('update:visible', v) })

const handleSearchInput = () => {
    if (searchTimer.value) clearTimeout(searchTimer.value)
    searchTimer.value = window.setTimeout(() => {
        if (memberSearch.value) searchMember()
        else memberSearchResults.value = []
    }, 300) as unknown as number
}

const searchMember = async () => {
    if (!memberSearch.value) { memberSearchResults.value = []; return }
    try {
        const res = await getUserByMobile(memberSearch.value)
        if (res.code === 1) {
            memberSearchResults.value = res.data.data || []
            hasMoreMembers.value = res.data.count > memberPage.value * memberPageSize.value
        } else {
            ElMessage.error(res.message || '搜索会员失败')
        }
    } catch (error) {
        console.error('搜索会员失败:', error)
        ElMessage.error('搜索会员失败')
    }
}

const loadMoreMembers = async () => { memberPage.value++; await searchMember() }

const handleMemberSelect = (user: Member) => {
    selectedMember.value = user
    form.value.member_id = user.member_id
    memberSearchResults.value = []
}

const clearSelectedMember = () => {
    selectedMember.value = null
    form.value.member_id = ''
}

// 扫码
const activateScanMode = () => {
    if (expressInput.value) {
        expressInput.value.$el.querySelector('input').focus()
        isScanMode.value = true
        ElMessage.info('请将扫码枪对准条码进行扫描')
    }
}
const handleInputFocus = () => { isScanMode.value = true }
const handleInputBlur = () => { window.setTimeout(() => { isScanMode.value = false }, 100) }
const handleScannerInput = (event: KeyboardEvent) => {
    if (isScanMode.value && event.key === 'Enter' && form.value.express_no) {
        ElMessage.success('扫码成功：' + form.value.express_no)
        isScanMode.value = false
    }
}
const handleKeyDown = () => {
    if (!isScanMode.value) return
    if (scanTimer.value) clearTimeout(scanTimer.value)
    scanTimer.value = window.setTimeout(() => {
        if (scanBuffer.value) {
            form.value.express_no = scanBuffer.value
            scanBuffer.value = ''
            ElMessage.success('扫码成功：' + form.value.express_no)
            isScanMode.value = false
        }
    }, 100)
}

onMounted(() => {
    updateResponsiveState()
    window.addEventListener('resize', updateResponsiveState)
    window.addEventListener('keydown', handleKeyDown)
})
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateResponsiveState)
    window.removeEventListener('keydown', handleKeyDown)
    if (scanTimer.value) clearTimeout(scanTimer.value)
    if (searchTimer.value) clearTimeout(searchTimer.value)
})

const validateBaseOrder = (): string => {
    if (!form.value.member_id) return '请选择会员'
    if (form.value.delivery_type === '1' && !form.value.express_no) return '请输入快递单号'
    return ''
}

// 供 DeviceEntryList 懒创建草稿订单；返回订单ID
const ensureDraftOrder = async (): Promise<number | string> => {
    if (draftOrder.value.id) return draftOrder.value.id
    const err = validateBaseOrder()
    if (err) {
        ElMessage.warning(err)
        throw new Error(err)
    }
    const res = await createRecycleOrder({
        member_id: form.value.member_id,
        delivery_type: form.value.delivery_type,
        express_no: form.value.delivery_type === '1' ? form.value.express_no : '',
        count: 0,
        order_source: 'agent',
        sign_after_create: false,
        draft_device_entry: true,
        devices: []
    })
    if (res.code !== 1) throw new Error(res.message || '创建草稿订单失败')
    draftOrder.value = { id: res.data.id, order_no: res.data.order_no }
    ElMessage.success(`草稿订单已创建：${res.data.order_no}`)
    return draftOrder.value.id
}

const handleConfirm = async () => {
    if (!draftOrder.value.id) { ElMessage.warning('请先保存至少一台设备'); return }
    const savedDevices = form.value.devices.filter(d => d.saved && d.id)
    if (!savedDevices.length) { ElMessage.warning('请先保存至少一台设备'); return }

    try {
        loading.value = true
        await ElMessageBox.confirm(
            `确定签收订单 ${draftOrder.value.order_no} 下的 ${savedDevices.length} 台设备吗？`,
            '完成签收',
            { confirmButtonText: '确认签收', cancelButtonText: '取消', type: 'warning' }
        )
        const res = await updateRecycleOrder(Number(draftOrder.value.id), {
            action: 'order_sign',
            devices: savedDevices.map(d => ({ id: d.id, ...normalizeDevice(d) }))
        })
        if (res.code !== 1) throw new Error(res.message || '签收失败')
        ElMessage.success('订单签收成功')
        resetForm()
        dialogVisible.value = false
        emit('success')
    } catch (error: any) {
        if (error === 'cancel') return
        console.error('订单签收失败:', error)
        ElMessage.error(error.message || '订单签收失败')
    } finally {
        loading.value = false
    }
}

const resetForm = () => {
    form.value = {
        member_id: '',
        delivery_type: '1',
        express_no: '',
        devices: [{ imei: '', model: '', initial_price: 0, summary_fields: [], summary_values: {} }]
    }
    draftOrder.value = { id: '', order_no: '' }
    selectedMember.value = null
    memberSearch.value = ''
    memberSearchResults.value = []
    memberPage.value = 1
    isScanMode.value = false
    scanBuffer.value = ''
}

const handleClosed = () => {
    resetForm()
    emit('closed')
}
</script>

<style lang="scss" scoped>
.member-select {
    width: 100%;
    position: relative;

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        max-height: 300px;
        overflow-y: auto;
        background-color: #fff;
        border: 1px solid #e4e7ed;
        border-radius: 4px;
        box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
        z-index: 100;
        margin-top: 5px;

        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;

            &:hover { background-color: #f5f7fa; }

            .member-info {
                margin-left: 10px;
                .member-name { font-size: 14px; font-weight: 500; }
                .member-mobile { font-size: 12px; color: #909399; }
            }
        }

        .search-result-more {
            padding: 10px;
            text-align: center;
            color: #409eff;
            cursor: pointer;
            font-size: 14px;
            &:hover { background-color: #f5f7fa; }
        }
    }

    .selected-member {
        margin-top: 10px;
        padding: 10px;
        border: 1px solid #e4e7ed;
        border-radius: 4px;
        background-color: #f5f7fa;

        .member-info {
            margin-left: 10px;
            .member-name { font-size: 14px; font-weight: 500; }
            .member-mobile { font-size: 12px; color: #606266; }
        }
    }
}

.express-input-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    .el-input { flex: 1; }
}

.scan-tip {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
    color: #409eff;
    font-size: 12px;
}

.device-form-item {
    :deep(.el-form-item__content) {
        margin-left: 0 !important;
        max-width: 100%;
    }
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

@media (max-width: 768px) {
    .mobile-footer {
        width: 100%;
        flex-direction: column;
    }
    .express-input-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
}
</style>
