<template>
    <div class="h-[60px] flex items-center justify-center bg-black border-t border-gray-800">
        <div class="text-gray-500 text-sm">
            <!-- 加载中状态 -->
            <span v-if="loading" class="animate-pulse">加载中...</span>

            <!-- 有版权数据时显示 -->
            <span v-else-if="copyright && (copyright.company_name || copyright.copyright_desc || copyright.icp)">
                <NuxtLink v-if="copyright.company_name || copyright.copyright_desc"
                    :to="copyright.copyright_link || '#'" class="hover:text-gray-300 transition-colors">
                    <span v-if="copyright.company_name">{{ copyright.company_name }}</span>
                    <span v-if="copyright.copyright_desc" class="ml-2">© {{ copyright.copyright_desc }}</span>
                </NuxtLink>
                <span v-if="(copyright.company_name || copyright.copyright_desc) && copyright.icp" class="mx-4">|</span>
                <NuxtLink v-if="copyright.icp" to="https://beian.miit.gov.cn/" target="_blank"
                    class="hover:text-gray-300 transition-colors">
                    备案号:{{ copyright.icp }}
                </NuxtLink>
            </span>

            <!-- 默认显示（无数据时） -->
            <span v-else class="text-gray-600">
                © 2025 AI设计 All Rights Reserved
            </span>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { getCopyRight } from '@/app/api/system';
import { ref, onMounted } from 'vue'

interface Copyright {
    icp?: string;
    company_name?: string;
    copyright_desc?: string;
    copyright_link?: string;
    gov_record?: string;
    gov_url?: string;
}

const copyright = ref<Copyright | null>(null);
const loading = ref(true);

const getCopy = async () => {
    try {
        loading.value = true
        const { data } = await getCopyRight()
        copyright.value = data as Copyright
        console.log('Copyright data loaded:', copyright.value)
    } catch (error) {
        console.error('Failed to load copyright data:', error)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    getCopy()
})
</script>

<style lang="scss" scoped></style>
