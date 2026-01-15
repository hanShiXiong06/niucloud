import { img } from '@/utils/common';
export function useShop(params: any = {}) {
    const siteName = (data: any) => {
        let name = data.front_end_name ? data.front_end_name : data.site_name; 
        return name;
    }
    const siteLogo = (data: any) => {
        let logo  = data.front_end_logo? data.front_end_logo : data.icon;
        return img(logo);
    }

    return {
        siteName: siteName,
        siteLogo: siteLogo,
    }
}