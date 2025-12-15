import { ref, onMounted } from 'vue'
import useMenuStore from '@/addon/ai_image/stores/memu'

export interface MenuItem {
    name: string
    path: string
    icon: string
    external: boolean
    badge: string | null
    children?: MenuItem[]
}

export const useMenu = () => {
    const menuStore = useMenuStore()
    const route = useRoute()

    // 使用 computed 从 store 中获取菜单数据
    const menuItems = computed(() => menuStore.menu)

    // 检查菜单项是否为当前页面
    const isActive = (item: MenuItem) => {
        if (item.external) return false
        return route.path === item.path
    }

    // 在组件挂载时加载菜单
    onMounted(async () => {
        // 如果菜单为空，则加载菜单
        if (menuStore.menu.length === 0) {
            await menuStore.getPcMenuFn()
        }
    })

    return {
        menuItems,
        isActive
    }
}
