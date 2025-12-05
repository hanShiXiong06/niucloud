// https://nuxt.com/docs/api/configuration/nuxt-config
import { loadEnv } from 'vite'
import topLevelAwait from 'vite-plugin-top-level-await'

const envName = (process.env as any).npm_lifecycle_event == 'dev' ? 'dev' : 'product'
const envData = loadEnv(envName, 'env')

export default defineNuxtConfig({
    app: {
        baseURL: '/web/',
        port: 3000,
        head: {
            link: [
                // 使用空的data URI阻止浏览器请求默认的/favicon.ico
                { rel: 'icon', href: 'data:,' }
            ]
        }
    },
    modules: [
        '@element-plus/nuxt',
        'nuxt-windicss'
    ],
    runtimeConfig: {
        public: envData
    },
    vite: {
        envDir: '~/env',
        plugins: [
            topLevelAwait({
                // The export name of top-level await promise for each chunk module
                promiseExportName: '__tla',
                // The function to generate import names of top-level await promise in each chunk module
                promiseImportName: i => `__tla_${i}`
            })
        ]
    },
    ssr: false
})
