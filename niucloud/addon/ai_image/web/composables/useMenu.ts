import { ref, onMounted } from 'vue'

export interface MenuItem {
    name: string
    path: string
    icon: string
    external: boolean
    badge: string | null
    children?: MenuItem[]
}

export const useMenu = () => {
    const route = useRoute()
    const menuItems = ref<MenuItem[]>([
        {
            name: '首页',
            path: '/ai_image/index',
            icon: 'document',
            external: false,
            badge: null
        },
        // {
        //     name: '图生图',
        //     path: '/ai_image/image/image',
        //     icon: 'video',
        //     external: false,
        //     badge: 'NEW'
        // },

        {
            name: '创作历史',
            path: '/ai_image/history/image',
            icon: 'history',
            external: false,
            badge: null
        },
        {
            name: '卡密列表',
            path: '/ai_image/card/card',
            icon: 'history',
            external: false,
            badge: null
        },
        {
            name: '卡密兑换',
            path: '/ai_image/card/verify',
            icon: 'history',
            external: false,
            badge: null
        },
        {
            name: '套餐中心',
            path: '/ai_image/package/index',
            icon: 'history',
            external: false,
            badge: null
        },
        {
            name: '套餐订单',
            path: '/ai_image/package/order',
            icon: 'history',
            external: false,
            badge: null
        },
        {
            name: '日志记录',
            path: '/ai_image/member/point',
            icon: 'document',
            external: false,
            badge: null
        },
        {
            name: '帮助中心',
            path: '/ai_image/help/index',
            icon: 'help-circle',
            external: false,
            badge: null
        }
    ])

    // 检查菜单项是否为当前页面
    const isActive = (item: MenuItem) => {
        if (item.external) return false
        return route.path === item.path
    }

    // 在客户端异步加载菜单配置
    const loadMenu = async () => {
        try {
            const menuData = await $fetch('/addon/ai_image/menu/menu.json')
            if (menuData && Array.isArray(menuData)) {
                menuItems.value = menuData
            }
        } catch (error) {
            console.warn('Failed to load menu configuration, using default menu')
        }
    }

    // 在组件挂载时加载菜单
    onMounted(() => {
        loadMenu()
    })

    return {
        menuItems,
        isActive
    }
}
