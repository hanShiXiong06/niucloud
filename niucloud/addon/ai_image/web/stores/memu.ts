import { defineStore } from 'pinia'
import storage from '@/utils/storage'
import { getPcMenu } from '@/addon/ai_image/api/aiimage'

const useMenuStore = defineStore('menu', {
    state: () => {
        return {
            menu: [] as any[]
        }
    },
    actions: {
        async getPcMenuFn() {
            try {
                const res: any = await getPcMenu()
                this.menu = res.data || []
            } catch (error) {
                console.error('Failed to load menu:', error)
                this.menu = []
            }
        }
    }
})

export default useMenuStore
