<template>
    <template v-if="meta.show">
        <el-sub-menu v-if="hasVisibleChild" :index="String(routes.name)">
            <template #title>
                <span class="menu-title">
                    <template v-if="level === 1 && meta.icon">
                        <el-image v-if="isUrl(meta.icon)" class="menu-icon" :src="meta.icon" fit="contain" />
                        <icon v-else :name="meta.icon" />
                    </template>
                    <span>{{ meta.short_title || meta.title }}</span>
                </span>
            </template>
            <horizontal-menu-item
                v-for="child in routes.children"
                :key="String(child.name)"
                :routes="child"
                :level="level + 1"
            />
        </el-sub-menu>

        <el-menu-item v-else :index="String(routes.name)" @click="handleJump(routes.name)">
            <span class="menu-title">
                <template v-if="level === 1 && meta.icon">
                    <el-image v-if="isUrl(meta.icon)" class="menu-icon" :src="meta.icon" fit="contain" />
                    <icon v-else :name="meta.icon" />
                </template>
                <span>{{ meta.short_title || meta.title }}</span>
            </span>
        </el-menu-item>
    </template>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { isUrl } from '@/utils/common'
import storage from '@/utils/storage'
import horizontalMenuItem from './horizontal-menu-item.vue'

const props = defineProps({
    routes: {
        type: Object,
        required: true
    },
    level: {
        type: Number,
        default: 1
    }
})

const route = useRoute()
const router = useRouter()
const meta = computed(() => props.routes.meta || {})

const hasVisibleChild = computed(() => {
    return Array.isArray(props.routes.children)
        && props.routes.children.some((child: Record<string, any>) => child?.meta?.show)
})

const handleJump = (routeName: string) => {
    const specialMenuNames = storage.get('specialMenuNames') || []
    const specialMenuNamesLevel1 = storage.get('specialMenuNamesLevel1') || []

    if (!specialMenuNames.includes(routeName)) storage.remove('activeAppKey')
    if (specialMenuNamesLevel1.includes(routeName)) routeName = 'addon_list'

    const query = route.name === routeName ? { refresh: Date.now() } : {}
    router.push({ name: routeName, query })
}
</script>

<style lang="scss" scoped>
.menu-title {
    display: inline-flex;
    align-items: center;
    min-width: 0;
    gap: 6px;
    font-size: 14px;
}

.menu-icon {
    width: 16px;
    height: 16px;
    flex: none;
}
</style>
