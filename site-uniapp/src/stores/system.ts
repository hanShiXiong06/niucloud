import { defineStore } from 'pinia'
import { getSiteNavs, getSiteInfo, getWebConfig } from '@/app/api/system'
import useConfigStore from '@/stores/config'
import useUserStore from '@/stores/user'
import { isWeixinBrowser } from '@/utils/common'
import { cloneDeep } from 'lodash-es';

interface System {
    site: AnyObject | null,
    siteApps: string[],
    siteAddons: string[],
    currRoute: string,
    mapConfig: any,
    initStatus: any, // 初始化状态
    menuButtonInfo: any, // 如果是小程序，获取右上角胶囊的尺寸信息
    shareCallback: any, // 分享回调
    defaultPositionAddress: any,
    diyAddressInfo: any,  // 定位信息
    website: Object
}

const useSystemStore = defineStore('system', {
    state: (): System => {
        return {
            site: null,
            siteApps: [],
            siteAddons: [],
            currRoute: '',
            mapConfig: {
                is_open: 1,
                valid_time: 0
            },
            initStatus: 'wait',
            menuButtonInfo: {
                height: '',
                top: '',
                right: '',
                width: ''
            },
            shareCallback: null,
            defaultPositionAddress: '定位中',
            diyAddressInfo: null,
            website:{}
        }
    },
    actions: {
        // 获取初始化数据信息
        getInitFn(callback: any) {

            this.initStatus = 'finish'; // 初始化完成
            if (callback) callback()
            this.getMenuButtonInfoFn();
        },
        getMenuButtonInfoFn() {
            // 如果是小程序，获取右上角胶囊的尺寸信息，避免导航栏右侧内容与胶囊重叠(支付宝小程序非本API，尚未兼容)
            // #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
            this.menuButtonInfo = uni.getMenuButtonBoundingClientRect();
            // #endif
        },
        async getSiteInfoFn() {
            await getSiteInfo().then((res: any) => {
                this.site = res.data
                this.siteApps = res.data.app
                this.siteAddons = res.data.site_addons.map((item: AnyObject) => {
                    return item.key
                })
            }).catch((err) => {
            })
        },
        // 当前选择的收货地址信息[经纬度，当前定位地址，定位过期时间]
        setAddressInfo(data: any = {}) {
            let addressInfo = cloneDeep(data);
            // 过期时间
            var date = new Date();
            date.setSeconds(60 * this.mapConfig.valid_time);
            addressInfo.valid_time = date.getTime() / 1000; // 定位信息 5分钟内有效，过期后将重新获取定位信息

            if (this.diyAddressInfo) {
                this.diyAddressInfo = Object.assign(this.diyAddressInfo, addressInfo);
            } else {
                this.diyAddressInfo = addressInfo;
            }

            if (Object.keys(data).length) {
                uni.setStorageSync('location_address', addressInfo);
            } else {
                uni.removeStorageSync('location_address');
            }
        },
        getSiteNavsFn(){
             getSiteNavs().then((res: any) => {
                // 底部导航
                const configStore = useConfigStore()
                configStore.tabbarList = res.data;
            })
        },
        getWebsiteInfo() {
            getWebConfig().then(({ data }) => {
                this.website = data
            }).catch()
        }
                   
        
    }
})

export default useSystemStore
