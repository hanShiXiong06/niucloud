<template>
    <div class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 border-t border-gray-700/50">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div v-if="info" class="space-y-2">
                <!-- 第一行：头像、用户名和退出按钮 -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- 头像 -->
                        <NuxtLink to="/ai_image/member/center" class="relative group">
                            <img v-if="info.headimg" :src="img(info.headimg)" alt="头像"
                                class="w-10 h-10 rounded-full ring-2 ring-purple-500/30 transition-all duration-200 group-hover:ring-purple-500/50 object-cover" />
                            <img v-else src="@/assets/images/default_headimg.png" alt="头像"
                                class="w-10 h-10 rounded-full ring-2 ring-purple-500/30 transition-all duration-200 group-hover:ring-purple-500/50 object-cover" />
                            <div
                                class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-500 rounded-full border-2 border-gray-900">
                            </div>
                        </NuxtLink>

                        <!-- 用户名 -->
                        <NuxtLink to="/ai_image/member/center"
                            class="text-white font-medium text-sm hover:text-purple-400 transition-colors max-w-[80px] truncate block">
                            {{ (info.nickname && info.nickname.length > 4 ? info.nickname.substring(0, 4) + '...' :
                            info.nickname) || '未设置昵称' }}
                        </NuxtLink>
                    </div>

                    <!-- 退出按钮 -->
                    <button @click="logoutFn"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-gray-400 hover:text-red-400 transition-colors text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span>退出</span>
                    </button>
                </div>

                <!-- 第二行：积分 -->
                <div v-if="config" class="flex items-center ml-[52px]">
                    <div
                        class="flex items-center gap-1.5 px-2.5 py-1 bg-purple-500/10 rounded-lg border border-purple-500/20">
                        <span class="text-purple-400 text-sm font-semibold">{{ info.point || 0 }}</span>
                        <span class="text-gray-400 text-xs">{{ config.alias_name || '积分' }}</span>
                    </div>
                </div>
            </div>

            <!-- 未登录状态 -->
            <NuxtLink to="/ai_image/auth/login" v-else
                class="flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-medium transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl hover:shadow-purple-500/25">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                        clip-rule="evenodd"></path>
                </svg>
                <span>登录 / 注册</span>
            </NuxtLink>
        </div>
    </div>
</template>

<script lang="ts" setup>
import useMemberStore from '@/stores/member'
import useAppStore from '@/stores/app'
import { img } from '@/utils/common'
const memberStore = useMemberStore()
const info = computed(() => memberStore.info)
import { getConfig } from '@/addon/ai_image/api/aiimage'
const config = ref()
const getConfigFn = () => {
    getConfig().then(res => {
        config.value = res.data
    })
}
getConfigFn()
const logoutFn = () => {
    memberStore.logout()
    navigateTo(`/ai_image/auth/login`)
}

const appStore = useAppStore()
</script>

<style lang="scss" scoped>
/* 头像悬停效果 */
.group:hover img {
    filter: brightness(1.1);
}

/* 在线状态指示器动画 */
.bg-green-500 {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.7;
    }
}

/* 按钮交互优化 */
button {
    transition: all 0.3s ease;
}

button:active {
    transform: scale(0.98);
}

/* 响应式优化 */
@media (max-width: 640px) {
    .h-\[70px\] {
        height: auto;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    .flex-col {
        align-items: flex-start;
    }
}
</style>
