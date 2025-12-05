<template>
    <div class="flex items-center justify-center h-[80px] px-6 relative overflow-hidden">
        <!-- 简化的背景装饰 -->
        <div class="absolute inset-0">
            <!-- 主背景 - 与侧边栏融合 -->
            <div class="absolute inset-0 bg-gradient-to-b from-slate-800/50 to-transparent"></div>
            <!-- 微妙的光效 -->
            <div class="absolute top-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-blue-400/20 to-transparent"></div>
        </div>
        
        <!-- Logo -->
        <div class="flex items-center relative z-10">
            <NuxtLink to="/ai_image/index" class="flex items-center group" v-if="siteInfo">
                <div class="relative">
                    <!-- Logo容器 -->
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-purple-500/20 border border-blue-500/30 backdrop-blur-sm flex items-center justify-center transition-all duration-300 group-hover:scale-105 group-hover:shadow-lg group-hover:shadow-blue-500/20">
                        <img :src="img(siteInfo.front_end_logo || '/assets/images/index/logo.jpg')" :alt="siteInfo.front_end_name || 'SORA'" 
                             class="w-8 h-8 rounded-lg object-cover">
                    </div>
                </div>
                <div class="ml-3">
                    <div class="text-lg font-bold text-white tracking-wide transition-colors duration-300 group-hover:text-blue-300">
                        {{ siteInfo.front_end_name || 'SORA' }}
                    </div>
                    <div class="text-xs text-slate-400 mt-0.5 transition-colors duration-300 group-hover:text-slate-300">{{ siteInfo.desc || 'AI智能创作平台' }}</div>
                </div>
            </NuxtLink>
        </div>
    </div>
</template>

<script lang="ts" setup>
import useMemberStore from '@/stores/member'
import useAppStore from '@/stores/app'
import useSystemStore from '@/stores/system'
import {img} from '@/utils/common'
const systemStore = useSystemStore()
const siteInfo = computed(() => systemStore.site)
const memberStore = useMemberStore()
const info = computed(() => memberStore.info)
const appStore = useAppStore()

const logoutFn = () => {
    memberStore.logout()
    navigateTo(`/auth/login`)
}
</script>

<style lang="scss" scoped>
:deep(.el-menu--horizontal) {
    border-bottom: none;
}

.el-menu-item {
    padding-left: 0;
    border: none !important;
    color: #000 !important;

    &.is-active {
        border: none !important;
        color: #000 !important;

        span {
            &:first-of-type {
                position: relative;
                z-index: 1;
            }

            &:last-of-type {
                position: absolute;
                width: 16px;
                height: 16px;
                background-image: linear-gradient(to bottom right, #FFFFFF, var(--el-color-primary));
                border-radius: 100px;
                bottom: 15px;
                right: 27px;
                z-index: 0;
            }
        }
    }

    &:hover {
        background-color: transparent !important;
        color: var(--el-menu-hover-text-color) !important;
    }

    &:focus {
        background-color: transparent !important;
    }
}
</style>
