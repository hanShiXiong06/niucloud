import { ref, onMounted, computed } from 'vue'
import { getConfig } from '../api/xiaoyuan'

/**
 * 功能开关检查组合式函数
 * @param featureKey 功能键名，如 'enable_secondhand'
 * @returns { config, isFeatureEnabled, loadConfig }
 */
export function useFeatureCheck(featureKey?: string) {
    const config = ref<any>(null)
    
    const isFeatureEnabled = computed(() => {
        if (!config.value || !featureKey) return true
        return config.value[featureKey] !== 0
    })
    
    const loadConfig = async () => {
        // 清除缓存，确保每次都获取最新配置
        uni.removeStorageSync('xiaoyuan_config')
        
        // 异步请求最新配置
        try {
            const res: any = await getConfig()
            console.log('获取配置成功:', res)
            if (res.code === 1 && res.data) {
                config.value = res.data
                uni.setStorageSync('xiaoyuan_config', res.data)
                console.log('配置数据:', config.value)
            }
        } catch (e) {
            console.error('获取配置失败:', e)
            // 如果请求失败，使用默认配置（全部开启）
            config.value = {
                enable_buy: 1,
                enable_send: 1,
                enable_express: 1,
                enable_print: 1,
                enable_trash: 1,
                enable_carry: 1,
                enable_clean: 1,
                enable_help: 1,
                enable_game: 1,
                enable_parttime: 1,
                enable_companion: 1,
                enable_house: 1,
                enable_schedule: 1,
                enable_group: 1,
                enable_secondhand: 1,
                enable_lost_found: 1,
                enable_community: 1,
                enable_confession: 1,
                enable_sign: 1,
                enable_points_mall: 1,
                require_auth_publish: 1,
                close_text: '功能已下架',
                secondhand_name: '闲置市场',
                community_name: '校园树洞'
            }
            console.log('使用默认配置:', config.value)
        }
    }
    
    return {
        config,
        isFeatureEnabled,
        loadConfig
    }
}
