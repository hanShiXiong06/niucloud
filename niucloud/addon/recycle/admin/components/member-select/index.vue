<template>
    <div class="w-full">
        <el-select
            v-model="selectedMemberId"
            filterable
            remote
            reserve-keyword
            placeholder="请输入用户信息"
            :remote-method="handleSearch"
            :loading="loading"
            clearable
            class="w-full !min-h-[40px]"
            @change="handleSelectChange"
            @clear="handleClear"
        >
            <el-option
                v-for="member in memberList"
                :key="member.member_id"
                :label="formatMemberLabel(member)"
                :value="member.member_id"
                class="!h-auto !p-0"
            >
                <div class="flex items-center py-1 px-1.5 hover:bg-gray-50 rounded transition-colors">
                    <!-- 头像区域 -->
                    <div class="relative flex-shrink-0 mr-2">
                        <div class="w-7 h-7 rounded-full overflow-hidden ring-1 ring-gray-200">
                            <img 
                                v-if="member.headimg" 
                                :src="img(member.headimg)" 
                                :alt="member.nickname"
                                class="w-full h-full object-cover"
                            />
                            <div 
                                v-else 
                                class="w-full h-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-medium text-xs"
                            >
                                {{ member.nickname?.charAt(0)?.toUpperCase() || '用' }}
                            </div>
                        </div>
                        <!-- 在线状态指示器 -->
                        <div class="absolute -bottom-0.5 -right-0.5 w-2 h-2 bg-green-400 rounded-full border border-white"></div>
                    </div>
                    
                    <!-- 用户信息 -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1 leading-tight">
                            <span class="text-sm font-medium text-gray-900 truncate">
                                <span class="text-xs text-gray-500 font-mono bg-gray-100 px-1 py-0.5 rounded mr-1">{{ member.member_no }}</span>
                                {{ member.nickname || '未设置昵称' }}
                            </span>
                            <el-tag 
                                v-if="member.member_level_name" 
                                type="info" 
                                size="small"
                                class="!text-xs !px-1 !py-0 !bg-blue-50 !text-blue-600 !border-blue-200"
                            >
                                {{ member.member_level_name }}
                            </el-tag>
                        </div>
                        <div v-if="member.mobile" class="text-xs text-blue-600 font-medium -mt-0.5">
                             {{ member.mobile }}
                        </div>
                    </div>
                </div>
            </el-option>
            
            <!-- 无数据状态 -->
            <template #empty>
                <div class="flex flex-col items-center justify-center py-8 px-4">
                    <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                        <el-icon size="24" class="text-gray-400">
                            <User />
                        </el-icon>
                    </div>
                    <p class="text-sm text-gray-500 mb-2" v-if="!hasSearched">
                        请输入关键词搜索用户
                    </p>
                    <p class="text-sm text-gray-500 mb-2" v-else>
                        未找到相关用户
                    </p>
                    <p class="text-xs text-gray-400">
                        支持昵称、手机号、用户编号搜索
                    </p>
                </div>
            </template>
        </el-select>
        
        <!-- 分页控制 -->
        <div v-if="showPagination && memberList.length > 0" class="flex justify-center mt-3">
            <el-pagination
                v-model:current-page="searchParams.page"
                :page-size="searchParams.limit"
                layout="prev, pager, next"
                :total="total"
                :small="true"
                @current-change="handlePageChange"
                class="!bg-transparent"
            />
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, watch } from 'vue'
import { getMemberList } from '@/app/api/member'
import { img } from '@/utils/common'
import { User } from '@element-plus/icons-vue'
import { debounce } from 'lodash-es'

interface Member {
    member_id: number
    member_no: string
    nickname: string
    mobile: string
    headimg: string
    balance: string
    member_level_name: string
    status: number
}

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null
    },
    placeholder: {
        type: String,
        default: '请输入用户昵称、手机号或用户编号搜索'
    },
    showPagination: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'change'])

const selectedMemberId = ref<number | string | null>(props.modelValue)
const memberList = ref<Member[]>([])
const loading = ref(false)
const hasSearched = ref(false)
const total = ref(0)

const searchParams = reactive({
    page: 1,
    limit: 10,
    keyword: '',
    register_type: '',
    member_label: '',
    register_channel: '',
    member_level: ''
})

/**
 * 格式化用户显示标签
 */
const formatMemberLabel = (member: Member) => {
    const parts = []
    if (member.nickname) parts.push(member.nickname)
    if (member.mobile) parts.push(`(${member.mobile})`)
    if (member.member_no) parts.push(`[${member.member_no}]`)
    return parts.join(' ')
}

/**
 * 防抖搜索处理
 */
const handleSearch = debounce(async (query: string) => {
    if (!query || query.length < 2) {
        memberList.value = []
        hasSearched.value = false
        return
    }
    
    loading.value = true
    hasSearched.value = true
    searchParams.keyword = query
    searchParams.page = 1
    
    try {
        await loadMembers()
    } finally {
        loading.value = false
    }
}, 300)

/**
 * 加载用户列表
 */
const loadMembers = async () => {
    try {
        const { data, code } = await getMemberList({
            ...searchParams,
            status: 1 // 只显示正常状态的用户
        })
        
        if (code === 1) {
            memberList.value = data?.data || []
            total.value = data?.total || 0
        } else {
            memberList.value = []
            total.value = 0
        }
    } catch (error) {
        console.error('加载用户列表失败:', error)
        memberList.value = []
        total.value = 0
    }
}

/**
 * 分页变化处理
 */
const handlePageChange = async (page: number) => {
    searchParams.page = page
    loading.value = true
    try {
        await loadMembers()
    } finally {
        loading.value = false
    }
}

/**
 * 选择变化处理
 */
const handleSelectChange = (value: number | string | null) => {
    selectedMemberId.value = value
    emit('update:modelValue', value || null)
    emit('change', value || null, memberList.value.find(m => m.member_id === value))
}

/**
 * 清除选择
 */
const handleClear = () => {
    selectedMemberId.value = null
    memberList.value = []
    hasSearched.value = false
    emit('update:modelValue', null)
    emit('change', null, null)
}

/**
 * 监听外部值变化
 */
watch(() => props.modelValue, (newValue) => {
    selectedMemberId.value = newValue
})
</script>

<style lang="scss" scoped>
// 全局样式覆盖
:deep(.el-select-dropdown__item) {
    height: auto !important;
    padding: 2px 4px !important;
    line-height: 1.2;
    
    &:hover {
        background-color: #f8f9fa !important;
    }
}

:deep(.el-select-dropdown__item.is-selected) {
    background-color: #f0f9ff !important;
    border-left: 3px solid #3b82f6;
}

:deep(.el-select-dropdown__item:hover) {
    background-color: #f8fafc !important;
}
</style>