<template>
    <el-dialog v-model="dialogVisible" title="代客户下单" width="600px" :destroy-on-close="true" @closed="handleClosed">
        <el-form ref="formRef" :model="form" label-width="100px">
            <el-form-item label="选择会员" required>
                <div class="member-select">
                    <el-input v-model="memberSearch" placeholder="输入会员手机号/昵称/用户名搜索" clearable
                        @input="handleSearchInput">
                        <template #append>
                            <el-button @click="searchMember">
                                <el-icon>
                                    <search />
                                </el-icon>
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
                            <el-button link type="danger" @click="clearSelectedMember">
                                <el-icon>
                                    <delete />
                                </el-icon>
                            </el-button>
                        </div>
                    </div>
                </div>
            </el-form-item>

            <el-form-item label="下单方式">
                <el-radio-group v-model="form.delivery_type">
                    <el-radio label="1">快递</el-radio>
                    <el-radio label="2">门店</el-radio>
                </el-radio-group>
            </el-form-item>

            <el-form-item label="快递单号" v-if="form.delivery_type === '1'">
                <div class="express-input-wrapper">
                    <el-input ref="expressInput" v-model="form.express_no" placeholder="请输入快递单号或使用扫码枪"
                        @keydown.enter="handleScannerInput" @focus="handleInputFocus" @blur="handleInputBlur" />
                    <el-button type="primary" @click="activateScanMode">
                        <el-icon>
                            <ZoomOut />
                        </el-icon>
                        扫码
                    </el-button>
                </div>
                <div class="scan-tip" v-if="isScanMode">
                    <el-icon>
                        <Loading class="is-loading" />
                    </el-icon>
                    <span>准备扫码中，请对准条码...</span>
                </div>
            </el-form-item>
            <!-- 数量 -->
            <el-form-item label="数量">
                <el-input-number v-model="form.count" :min="1" :max="100" />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleConfirm" :loading="loading">确定</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Delete, Loading, Aim } from '@element-plus/icons-vue'
import { createRecycleOrder, getUserByMobile } from '@/addon/recycle/api/recycle_order'
// import { searchMembers } from '@/api/member'

interface Member {
    member_id: string | number;
    nickname?: string;
    username?: string;
    mobile?: string;
    headimg?: string;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:visible', 'success', 'closed'])

// 内部状态
const dialogVisible = ref(props.visible)
const loading = ref(false)
const form = ref({
    member_id: '',
    delivery_type: '1',
    express_no: '',
    count : 1 as number
})

// 会员搜索相关
const memberSearch = ref('')
const memberSearchResults = ref<Member[]>([])
const memberPage = ref(1)
const memberPageSize = ref(10)
const hasMoreMembers = ref(false)
const selectedMember = ref<Member | null>(null)
const searchTimer = ref<number | null>(null)

// 扫码相关
const expressInput = ref(null)
const isScanMode = ref(false)
const scanBuffer = ref('')
const scanTimer = ref(null)

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
    dialogVisible.value = newVal
})

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
    emit('update:visible', newVal)
})

// 处理会员搜索输入
const handleSearchInput = () => {
    // 防抖处理
    if (searchTimer.value) {
        clearTimeout(searchTimer.value)
    }
    searchTimer.value = window.setTimeout(() => {
        if (memberSearch.value) {
            searchMember()
        } else {
            memberSearchResults.value = []
        }
    }, 300) as unknown as null
}

// 搜索会员
const searchMember = async () => {
    if (!memberSearch.value) {
        memberSearchResults.value = []
        return
    }

    try {
        const params = {
            page: memberPage.value,
            page_size: memberPageSize.value,
            keyword: memberSearch.value
        }
        const res = await getUserByMobile(params.keyword)
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

// 加载更多会员
const loadMoreMembers = async () => {
    memberPage.value++
    await searchMember()
}

// 选择会员
const handleMemberSelect = (user: Member) => {
    selectedMember.value = user
    form.value.member_id = user.member_id
    memberSearchResults.value = [] // 清空搜索结果
}

// 清除已选会员
const clearSelectedMember = () => {
    selectedMember.value = null
    form.value.member_id = ''
}

// 激活扫码模式
const activateScanMode = () => {
    if (expressInput.value) {
        // 使用原生DOM方法获取焦点
        expressInput.value.$el.querySelector('input').focus()
        isScanMode.value = true
        ElMessage.info('请将扫码枪对准条码进行扫描')
    }
}

// 处理输入框获取焦点
const handleInputFocus = () => {
    isScanMode.value = true
}

// 处理输入框失去焦点
const handleInputBlur = () => {
    // 使用window.setTimeout而不是直接绑定到组件上
    window.setTimeout(() => {
        isScanMode.value = false
    }, 100)
}

// 处理扫码枪输入
const handleScannerInput = (event: KeyboardEvent) => {
    if (isScanMode.value) {
        // 大多数扫码枪会在扫描完成后自动发送回车，这里我们捕获回车事件
        if (event.key === 'Enter') {
            // 如果输入框有值，说明扫码成功
            if (form.value.express_no) {
                ElMessage.success('扫码成功：' + form.value.express_no)
                isScanMode.value = false
            }
        }
    }
}

// 监听键盘事件（针对更复杂的扫码器行为）
const handleKeyDown = (event: KeyboardEvent) => {
    if (isScanMode.value) {
        // 某些扫码枪会快速输入字符，我们可以通过检测输入速度来判断是否是扫码枪

        if (scanTimer.value) {
            clearTimeout(scanTimer.value)
        }

        // 设置一个超时，如果一段时间内没有新输入，就认为扫码结束
        scanTimer.value = window.setTimeout(() => {
            if (scanBuffer.value) {
                // 将缓冲区的内容设置到表单中
                form.value.express_no = scanBuffer.value
                scanBuffer.value = ''
                ElMessage.success('扫码成功：' + form.value.express_no)
                isScanMode.value = false
            }
        }, 100) as unknown as null
    }
}

// 生命周期钩子
onMounted(() => {
    // 添加全局键盘事件监听
    window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
    // 移除全局键盘事件监听
    window.removeEventListener('keydown', handleKeyDown)

    // 清理定时器
    if (scanTimer.value) {
        clearTimeout(scanTimer.value)
    }
    if (searchTimer.value) {
        clearTimeout(searchTimer.value)
    }
})

// 确认添加订单
const handleConfirm = async () => {
    if (!form.value.member_id) {
        ElMessage.warning('请选择会员')
        return
    }

    if (form.value.delivery_type === '1' && !form.value.express_no) {
        ElMessage.warning('请输入快递单号')
        return
    }

    try {
        loading.value = true

        // 二次确认
        const memberName = selectedMember.value?.nickname || selectedMember.value?.username || '所选会员'
        await ElMessageBox.confirm(
            `确定要为用户 "${memberName}" 创建回收订单吗？`,
            '创建订单确认',
            {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            }
        )

        const params = {
            member_id: form.value.member_id,
            delivery_type: form.value.delivery_type,
            express_no: form.value.delivery_type === '1' ? form.value.express_no : ''
        }

        const res = await createRecycleOrder(params)
        if (res.code !== 1) {
            throw new Error(res.message || '创建订单失败')
        }

        ElMessage.success('创建订单成功')
        resetForm()
        dialogVisible.value = false
        emit('success')
    } catch (error) {
        if (error === 'cancel') return // 用户取消操作
        console.error('创建订单失败:', error)
        ElMessage.error(error.message || '创建订单失败')
    } finally {
        loading.value = false
    }
}

// 重置表单
const resetForm = () => {
    form.value = {
        member_id: '',
        delivery_type: '1',
        express_no: ''
    }
    selectedMember.value = null
    memberSearch.value = ''
    memberSearchResults.value = []
    memberPage.value = 1
    isScanMode.value = false
    scanBuffer.value = ''
}

// 对话框关闭处理
const handleClosed = () => {
    resetForm()
    emit('closed')
}

// 初始化
if (props.visible) {
    // 可以在这里进行一些初始化操作
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

            &:hover {
                background-color: #f5f7fa;
            }

            .member-info {
                margin-left: 10px;

                .member-name {
                    font-size: 14px;
                    font-weight: 500;
                }

                .member-mobile {
                    font-size: 12px;
                    color: #909399;
                }
            }
        }

        .search-result-more {
            padding: 10px;
            text-align: center;
            color: #409eff;
            cursor: pointer;
            font-size: 14px;

            &:hover {
                background-color: #f5f7fa;
            }
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

            .member-name {
                font-size: 14px;
                font-weight: 500;
            }

            .member-mobile {
                font-size: 12px;
                color: #606266;
            }
        }
    }
}

.express-input-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;

    .el-input {
        flex: 1;
    }
}

.scan-tip {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
    color: #409eff;
    font-size: 12px;
}
</style>