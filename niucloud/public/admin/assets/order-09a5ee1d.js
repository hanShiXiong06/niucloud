import{x as r}from"./index-1bbcede8.js";function n(){return r.get("shop/order/config")}function u(e){return r.post("shop/order/config",e,{showSuccessMessage:!0})}function s(e){return r.get("shop/order/list",{params:e})}function i(e){return r.get(`shop/order/detail/${e}`)}function d(){return r.get("shop/order/status")}function f(e){return r.put(`shop/order/close/${e}`)}function p(e){return r.get("shop/order/delivery_type",{params:e})}function a(e){return r.put("shop/order/delivery",e)}function c(e){return r.put("shop/order/shop_remark",e)}function g(e){return r.put(`shop/order/finish/${e}`)}function h(e){return r.get("shop/order/delivery/package",{params:e})}function l(e){return r.get("shop/order/refund",{params:e})}function v(e){return r.get(`shop/order/refund/${e}`)}function y(e){return r.put(`shop/order/refund/audit/${e.order_refund_no}`,e)}function $(e){return r.put(`shop/order/refund/delivery/${e.order_refund_no}`,e)}function D(e){return r.get("shop/invoice",{params:e})}function O(e){return r.get(`shop/invoice/${e}`)}function _(e,t){return r.put(`shop/invoice/${e}`,t,{showSuccessMessage:!0})}function k(){return r.get("shop/order/pay/type")}function m(){return r.get("shop/order/from")}export{O as a,c as b,n as c,h as d,u as e,i as f,p as g,f as h,g as i,D as j,d as k,k as l,m,s as n,a as o,l as p,v as q,y as r,_ as s,$ as t};
