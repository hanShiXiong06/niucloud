<template>
    <div class="text-white min-h-screen">
        <div class="px-6 py-6">
            <!-- 页面标题 -->
            <div class="mb-8 text-center">
                <h1
                    class="text-4xl font-bold mb-4 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
                    个人中心
                </h1>
                <p class="text-gray-400 text-lg">管理您的个人信息</p>
            </div>

            <div class="max-w-5xl mx-auto">
                <!-- 加载状态 -->
                <div v-if="loading" class="flex justify-center items-center py-20">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
                </div>

                <!-- 个人信息卡片 -->
                <div v-else-if="info" class="space-y-6">
                    <!-- 头像卡片 -->
                    <div
                        class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl shadow-xl border border-gray-700/50 p-8 transition-all duration-300 hover:border-purple-500/30 group">
                        <div class="flex items-center justify-between flex-wrap gap-6">
                            <div class="flex items-center gap-6">
                                <el-upload class="avatar-uploader" :show-file-list="false" v-bind="upload">
                                    <div class="relative cursor-pointer group/avatar">
                                        <img v-if="!info.headimg"
                                            class="w-12 h-12 rounded-2xl ring-4 ring-purple-500/30 transition-all duration-300 group-hover/avatar:ring-purple-500/60 group-hover/avatar:scale-105 object-cover"
                                            src="@/assets/images/default_headimg.png" alt="头像">
                                        <img v-else :src="img(info.headimg)"
                                            class="w-12 h-12 rounded-2xl ring-4 ring-purple-500/30 transition-all duration-300 group-hover/avatar:ring-purple-500/60 group-hover/avatar:scale-105 object-cover"
                                            alt="头像">
                                        <div
                                            class="absolute inset-0 rounded-2xl bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover/avatar:opacity-100 transition-all duration-300 flex flex-col items-center justify-end pb-3">
                                            <svg class="w-6 h-6 text-white mb-1" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path
                                                    d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z">
                                                </path>
                                            </svg>
                                            <span class="text-white text-xs font-medium">更换头像</span>
                                        </div>
                                    </div>
                                </el-upload>
                                <div>
                                    <div class="flex items-center gap-3 mb-3">

                                        <div>
                                            <h3 class="text-2xl font-bold text-white mb-1">个人头像</h3>
                                            <p class="text-sm text-gray-400">展示您的个性形象</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <el-upload class="avatar-uploader" :show-file-list="false" v-bind="upload">
                                <button
                                    class="px-8 py-3.5 rounded-xl text-sm font-semibold transition-all duration-300 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white shadow-lg hover:shadow-xl hover:shadow-purple-500/25 transform hover:scale-105 active:scale-95 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z">
                                        </path>
                                    </svg>
                                    <span>上传头像</span>
                                </button>
                            </el-upload>
                        </div>
                    </div>

                    <!-- 昵称卡片 -->
                    <div
                        class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl shadow-xl border border-gray-700/50 p-8 transition-all duration-300 hover:border-purple-500/30 group">
                        <div class="flex items-center justify-between flex-wrap gap-6">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-4">
                                    <div
                                        class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center shadow-lg transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-2xl font-bold text-white mb-1">{{ updateNickname.value || '未设置'
                                            }}</h3>
                                        <p class="text-sm text-gray-400">您在平台上的显示名称</p>
                                    </div>
                                </div>

                            </div>
                            <button @click="updateNickname.modal = true"
                                class="px-8 py-3.5 rounded-xl text-sm font-semibold transition-all duration-300 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white shadow-lg hover:shadow-xl hover:shadow-purple-500/25 transform hover:scale-105 active:scale-95 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z">
                                    </path>
                                </svg>
                                <span>编辑昵称</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 编辑昵称弹窗 -->
            <el-dialog v-model="updateNickname.modal" title="编辑昵称" width="500px" :close-on-click-modal="false">
                <el-form :model="info">
                    <el-form-item>
                        <el-input v-model="updateNickname.value" placeholder="请输入昵称" size="large" clearable>
                            <template #prefix>
                                <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </template>
                        </el-input>
                    </el-form-item>
                </el-form>
                <template #footer>
                    <div class="flex gap-3 justify-end">
                        <button @click="updateNickname.modal = false"
                            class="px-6 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 bg-gray-700/50 text-gray-300 hover:bg-gray-600/50 hover:text-white">
                            取消
                        </button>
                        <button @click="updateNicknameConfirm"
                            class="px-6 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white shadow-lg hover:shadow-xl transform hover:scale-105 active:scale-95">
                            确定
                        </button>
                    </div>
                </template>
            </el-dialog>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, computed, onMounted } from 'vue'
import useMemberStore from '@/stores/member'
import useAppStore from '@/stores/app'
import { modifyMember } from '@/app/api/member'
import { ElMessage, UploadFile, UploadFiles } from 'element-plus'
import request from '@/utils/request'
import storage from '@/utils/storage'
import { getToken } from '@/utils/common'


const memberStore = useMemberStore()
const loading = ref(true)

//会员昵称
const updateNickname = reactive({
    modal: false,
    value: ''
})

const info = computed(() => {
    updateNickname.value = memberStore.info?.nickname;
    return memberStore.info;
})

const appStore = useAppStore()
definePageMeta({ middleware: 'auth' })

// 页面加载完成后关闭 loading
onMounted(() => {
    // 等待会员信息加载
    const checkInfo = () => {
        if (memberStore.info) {
            loading.value = false
        } else {
            setTimeout(checkInfo, 100)
        }
    }
    checkInfo()
})
const upload = computed(() => {
    const headers: Record<string, any> = {}
    headers.token = getToken()
    const runtimeConfig = useRuntimeConfig()
    headers['site-id'] = useCookie('siteId').value || runtimeConfig.public.VITE_SITE_ID
    return {
        action: `${request.options.baseURL}/file/image`,
        limit: 1,
        headers,
        onSuccess: (response: any, uploadFile: UploadFile, uploadFiles: UploadFiles) => {
            let img = uploadFile?.response?.data?.url;
             modifyMember({
                    field: 'headimg',
                    value: img
                }).then(() => {
                    memberStore.info.headimg = img
                    ElMessage.success('修改成功')
                })
        }
    }
})

// 修改会员名称
const updateNicknameConfirm = () => {
    if (!updateNickname.value) { ElMessage.error('会员昵称不能为空'); return }

    modifyMember({
        field: 'nickname',
        value: updateNickname.value
    }).then(res => {
        updateNickname.modal = false
    })
}
</script>

<style lang="scss" scoped>
/* 渐变文字动画 */
.bg-clip-text {
    background-size: 200% 200%;
    animation: gradient 3s ease infinite;
}

@keyframes gradient {
    0% {
        background-position: 0% 50%;
    }

    50% {
        background-position: 100% 50%;
    }

    100% {
        background-position: 0% 50%;
    }
}

/* 卡片悬停效果 */
.bg-gradient-to-br {
    transition: all 0.3s ease;
}

.bg-gradient-to-br:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
}

/* 按钮交互效果 */
button {
    transition: all 0.3s ease-in-out;
    position: relative;
}

button:active:not(:disabled) {
    transform: scale(0.98);
}

button::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

button:hover:not(:disabled)::before {
    opacity: 1;
}

/* 弹窗样式优化 */
:deep(.el-dialog) {
    background: linear-gradient(135deg, rgba(31, 41, 55, 0.95) 0%, rgba(17, 24, 39, 0.95) 100%);
    border: 1px solid rgba(107, 114, 128, 0.3);
    border-radius: 1rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);

    .el-dialog__header {
        border-bottom: 1px solid rgba(107, 114, 128, 0.2);
        padding: 1.5rem;

        .el-dialog__title {
            color: white;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .el-dialog__headerbtn {
            top: 1.5rem;
            right: 1.5rem;

            .el-dialog__close {
                color: rgba(156, 163, 175, 0.8);
                font-size: 1.25rem;

                &:hover {
                    color: white;
                }
            }
        }
    }

    .el-dialog__body {
        padding: 1.5rem;
    }

    .el-dialog__footer {
        border-top: 1px solid rgba(107, 114, 128, 0.2);
        padding: 1.5rem;
    }
}

/* 表单样式优化 */
:deep(.el-form-item) {
    margin-bottom: 0;

    .el-input__wrapper {
        background: rgba(31, 41, 55, 0.5);
        border: 1px solid rgba(107, 114, 128, 0.3);
        border-radius: 0.75rem;
        padding: 12px 16px;
        box-shadow: none;
        transition: all 0.2s;

        &:hover {
            border-color: rgba(139, 92, 246, 0.5);
            background: rgba(31, 41, 55, 0.7);
        }

        &.is-focus {
            border-color: #8b5cf6;
            background: rgba(31, 41, 55, 0.8);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
    }

    .el-input__inner {
        color: white;

        &::placeholder {
            color: rgba(156, 163, 175, 0.6);
        }
    }

    .el-input__prefix {
        display: flex;
        align-items: center;
    }
}

/* 响应式布局优化 */
@media (max-width: 640px) {
    .px-6 {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .text-4xl {
        font-size: 2rem;
    }

    .flex-col-reverse {
        flex-direction: column-reverse;
    }

    button {
        min-height: 44px;
    }
}
</style>
