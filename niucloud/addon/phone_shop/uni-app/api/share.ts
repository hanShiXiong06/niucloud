import request from "@/utils/request";

/**
 * 生成商品分享短链接
 * @param params 包含 goods_id 和可选的 share_user_id
 */
export function generateShareLink(params: {
    goods_id: string | number
    share_user_id?: string | number
}) {
    return request.post(`phone_shop/share/generate_link`, params);
}

