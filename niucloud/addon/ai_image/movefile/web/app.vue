<template>
    <el-config-provider :locale="locale">
        <NuxtLayout>
            <NuxtLoadingIndicator />
            <NuxtPage />
        </NuxtLayout>
    </el-config-provider>
</template>

<script lang="ts" setup>
import useConfigStore from '@/stores/config'
import zhCn from 'element-plus/dist/locale/zh-cn.mjs'
import en from 'element-plus/dist/locale/en.mjs'
import useSystemStore from '@/stores/system'
import useAppStore from '@/stores/app'
import useMemberStore from '@/stores/member'
// 引入全局样式
import '@/assets/styles/index.scss'

if (process.client) {
    const match = location.href.match(/\/web\/(\d*)\//)
    const cookie = useCookie('siteId')
    match ? cookie.value = match[1] : cookie.value = null
}

// 初始化设置语言
const systemStore = useSystemStore()

const locale = computed(() => (systemStore.lang === 'zh-cn' ? zhCn : en))

// 初始化查询一些配置
const configStore = useConfigStore()
configStore.getLoginConfig()

// 查询站点信息
systemStore.getSiteInfoFn()

// 如果已登录
getToken() && useMemberStore().setToken(getToken())

const route = useRoute()
watch(route, (nval, oval) => {
    useAppStore().$patch(state => {
        state.route = route.path
    })
    // 设置页面title
    let path = route.path == '/' ? '/index' : route.path
    // 处理部署后不知道为什么url会自动拼接上 / 的问题
    if (path.slice(-1) == '/') path = path.slice(0, -1)
    path = !path.lastIndexOf('/') ? `${path}/index` : path
    let key = path.replace('/', '').replaceAll('/', '.')
    setTimeout(() => {
        // useHead({
        //     title: t(`pages.${key}`)
        // })
    }, !oval ? 500 : 0)
}, { immediate: true })
watch(() => systemStore.site, () => {
    const meta = systemStore.site
    const siteTitle = systemStore.site.meta_title || systemStore.site.front_end_name || systemStore.site.site_name
    const faviconUrl = meta.front_end_icon ? img(meta.front_end_icon) : ''
    
    // 根据文件扩展名确定MIME类型
    const getFaviconType = (url: string) => {
        const ext = url.split('.').pop()?.toLowerCase()
        const typeMap: Record<string, string> = {
            'png': 'image/png',
            'jpg': 'image/jpeg',
            'jpeg': 'image/jpeg',
            'gif': 'image/gif',
            'svg': 'image/svg+xml',
            'ico': 'image/x-icon'
        }
        return typeMap[ext || 'ico'] || 'image/x-icon'
    }
    
    console.log(meta)
    console.log(faviconUrl)
    console.log(getFaviconType(faviconUrl))
    
    // 直接操作DOM更新favicon
    if (process.client && faviconUrl) {
        // 移除旧的favicon
        const existingLinks = document.querySelectorAll('link[rel*="icon"]')
        existingLinks.forEach(link => link.remove())
        
        // 添加新的favicon
        const link1 = document.createElement('link')
        link1.rel = 'icon'
        link1.type = getFaviconType(faviconUrl)
        link1.href = faviconUrl
        document.head.appendChild(link1)
        
        const link2 = document.createElement('link')
        link2.rel = 'shortcut icon'
        link2.type = getFaviconType(faviconUrl)
        link2.href = faviconUrl
        document.head.appendChild(link2)
    }
    
    useHead({
        titleTemplate: (title) => {
            if (title) {
                if (siteTitle) {
                    return `${title} - ${siteTitle}`;
                } else {
                    return title;
                }
            } else {
                return siteTitle;
            }
        },
        meta: [
            {
                name: 'description',
                content: meta.meta_desc ? meta.meta_desc : ''
            },
            {
                name: 'Keywords',
                content: meta.meta_keyword ? meta.meta_keyword : ''
            }
        ]
    })
}, { deep: true, immediate: true })
// 设置title模板
// useHead({
// 	titleTemplate: (title) => {
// 		const siteTitle = systemStore.site.front_end_name || systemStore.site.site_name
// 		return title ? `${title} - ${siteTitle}` : siteTitle
// 	}
// })
</script>
