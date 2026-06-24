import { updateRunnerLocation } from '../api/xiaoyuan'

// 位置缓存时间（毫秒）
const CACHE_TIME = 5 * 60 * 1000 // 5分钟

// 缓存的位置信息
let cachedLocation: any = null
let lastUpdateTime: number = 0

/**
 * 获取当前位置
 */
export function getCurrentLocation(): Promise<any> {
    return new Promise((resolve, reject) => {
        // 5分钟内返回缓存位置
        if (cachedLocation && (Date.now() - lastUpdateTime) < CACHE_TIME) {
            resolve(cachedLocation)
            return
        }

        uni.getLocation({
            type: 'gcj02',
            success: (res) => {
                cachedLocation = {
                    latitude: res.latitude,
                    longitude: res.longitude,
                    address: ''
                }
                lastUpdateTime = Date.now()
                resolve(cachedLocation)
            },
            fail: (err) => {
                // 如果有缓存，返回缓存
                if (cachedLocation) {
                    resolve(cachedLocation)
                } else {
                    reject(err)
                }
            }
        })
    })
}

/**
 * 选择位置
 */
export function chooseLocation(): Promise<any> {
    return new Promise((resolve, reject) => {
        uni.chooseLocation({
            success: (res) => {
                const location = {
                    name: res.name,
                    address: res.address,
                    latitude: res.latitude,
                    longitude: res.longitude
                }
                // 更新缓存
                cachedLocation = location
                lastUpdateTime = Date.now()
                resolve(location)
            },
            fail: (err) => {
                reject(err)
            }
        })
    })
}

/**
 * 计算两点距离（公里）
 */
export function calculateDistance(lat1: number, lng1: number, lat2: number, lng2: number): number {
    const earthRadius = 6371

    const dlat = (lat2 - lat1) * Math.PI / 180
    const dlng = (lng2 - lng1) * Math.PI / 180

    const a = Math.sin(dlat / 2) * Math.sin(dlat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dlng / 2) * Math.sin(dlng / 2)

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
    const distance = earthRadius * c

    return Math.round(distance * 100) / 100
}

/**
 * 格式化距离显示
 */
export function formatDistance(distance: number): string {
    if (distance < 1) {
        return Math.round(distance * 1000) + 'm'
    }
    return distance.toFixed(1) + 'km'
}

/**
 * 接单员位置上报服务
 * 每5分钟上报一次位置
 */
let reportTimer: any = null

export function startLocationReport(runnerId: number) {
    if (reportTimer) {
        clearInterval(reportTimer)
    }

    // 立即上报一次
    reportLocation(runnerId)

    // 每5分钟上报一次
    reportTimer = setInterval(() => {
        reportLocation(runnerId)
    }, CACHE_TIME)
}

export function stopLocationReport() {
    if (reportTimer) {
        clearInterval(reportTimer)
        reportTimer = null
    }
}

async function reportLocation(runnerId: number) {
    try {
        const location = await getCurrentLocation()
        await updateRunnerLocation({
            runner_id: runnerId,
            latitude: location.latitude,
            longitude: location.longitude,
            address: location.address || ''
        })
    } catch (e) {
        console.error('位置上报失败', e)
    }
}

/**
 * 获取缓存的位置（不触发定位）
 */
export function getCachedLocation(): any {
    return cachedLocation
}

/**
 * 清除位置缓存
 */
export function clearLocationCache() {
    cachedLocation = null
    lastUpdateTime = 0
}
