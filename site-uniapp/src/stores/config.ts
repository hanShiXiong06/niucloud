import { defineStore } from 'pinia'
import { isWeixinBrowser } from "@/utils/common";


interface Config {
    tabbarList: any,
    themeColor: any
}

const useConfigStore = defineStore('config', {
    state: (): Config => {
        return {
            tabbarList: {},
            themeColor: ''
        }
    },
    actions: {
        // 获取主色调
        getThemeColor() {
            let themeColorStorage = uni.getStorageSync('current_theme_color');
            if (!this.themeColor && themeColorStorage) {
                this.themeColor = JSON.parse(themeColorStorage);
            }
            if (this.themeColor) {
                let style = '';
                for (let k in this.themeColor) {
                    style += `${ k }:${ this.themeColor[k] };`;
                }
                return style;
            }
            return '';
        }
    }
})

export default useConfigStore
