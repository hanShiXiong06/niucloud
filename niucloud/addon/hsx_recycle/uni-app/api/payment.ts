import request from "@/utils/request";

// 收款方式列表
export function getPaymentList() {
  return request.get("recycle_price/payment/list");
}

// 添加收款方式
export function addPayment(data: any) {
  return request.post("recycle_price/payment/add", data);
}

// 编辑收款方式
export function updatePayment(id: number, data: any) {
  return request.put(`recycle_price/payment/update/${id}`, data);
}

// 删除收款方式
export function deletePayment(id: number) {
  return request.delete(`recycle_price/payment/delete/${id}`);
}

// 设置默认收款方式
export function setDefaultPayment(id: number) {
  return request.put(`recycle_price/payment/set_default/${id}`);
}

// 定义接口类型
export interface PaymentInfo {
  id: number;
  member_id: number;
  pay_type: string;
  account: string;
  qrcode_image?: string;
  is_default: number;
  create_time?: number;
  update_time?: number;
}
