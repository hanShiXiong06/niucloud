import request from "@/utils/request";

/***************************************************** hello world ****************************************************/
export function getCategoryTree() {
  return request.get(`recycle/recycle_category_tree`);
}
// 获取 商家的收货地址
export function getShopAddressList() {

  return request.get(`recycle/address_list`);
}



export function getTree() {
  return request.get("recycle/recycle_category/tree");
}

// 获取热门分类
export function getHot() {
  return request.get("recycle/recycle_category/hot");
}

export function getModelList(data: any) {
  // recycle_model/list
  return request.get("recycle/recycle_model/list", {
    brandId: data.id,
    typeId: data.typeId,
    limit: data.limit,
    page: data.page,
  });
}



export function getPrice(data: any) {
  return request.post("recycle/calculate/price", data);
}

// 发送当前订单的回收信息
export function sendOrderInfo(data: any) {
  return request.post("recycle/recycle_order", data);
}
export function getMemberLevel() {
  return request.get(`tk_vip/member/level`);
}

// 获取Banner列表
export function getBannerList() {
  return request.get("recycle/recycle_banner");
}

// 获取会员等级信息
export function getLevelInfo() {
  return request.get("tk_vip/vip/levelinfo");
}

// 获取收款信息
export function getPaymentInfo() {
  return request.get("recycle/payment/info");
}

// 获取设备状态数量统计
export function getDeviceStatusCount() {
  return request.get("recycle/recycle_device/count");
}
