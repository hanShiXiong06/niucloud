import{x as e}from"./index-d5447f06.js";function t(o){return e.get("shop/marketing",{params:o})}function n(o){return e.get("shop/goods/coupon/init",o)}function u(o){return e.post("shop/goods/coupon",o,{showErrorMessage:!0,showSuccessMessage:!0})}function r(o){return e.get("shop/goods/coupon",{params:o})}function g(o){return e.get("shop/goods/coupon/records",{params:o})}function c(o){return e.get(`shop/goods/coupon/detail/${o}`)}function p(o){return e.put(`shop/goods/coupon/edit/${o.id}`,o,{showErrorMessage:!0,showSuccessMessage:!0})}function d(o){return e.delete(`shop/goods/coupon/${o}`,{showSuccessMessage:!0})}export{u as a,g as b,c,r as d,p as e,d as f,n as g,t as h};
