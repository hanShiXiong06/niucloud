import request from "@/utils/request";

export function getConsignmentOrderList(params: any) {
  return request.get("recycle/consignment_order", params);
}

export function getConsignmentOrderDetail(id: number) {
  return request.get(`recycle/consignment_order/${id}`);
}

export function getConsignmentStatusCount() {
  return request.get("recycle/consignment_order/status_count");
}

export function getConsignmentStatusList() {
  return request.get("recycle/consignment_order/status");
}
