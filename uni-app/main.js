import App from './App'

import { createSSRApp } from 'vue'
import * as Pinia from 'pinia'
import locale from './locale'
import uviewPlus from 'uview-plus'
import '@/styles/index.scss'
import 'virtual:windi.css'

export function createApp() {
    const app = createSSRApp(App)
    app.use(Pinia.createPinia())
    app.use(locale)
    app.use(uviewPlus)
    return {
        app,
        Pinia
    }
}
