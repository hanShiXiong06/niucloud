import{d as a,r as e,o as t,f as s,Z as l,i as r,F as o,j as u,w as p,k as c,n,K as i,L as m,m as x,G as d,h as f,a$ as y,M as _,x as v,N as g,q as j,t as h,y as w,aj as b}from"./index-f4365f98.js";import{_ as T}from"./u-button.86ec562d.js";import{_ as S}from"./u-loading-icon.3d6a4740.js";import{_ as k}from"./u-modal.df76d526.js";import{g as F}from"./pay.5ff55654.js";import{_ as C}from"./_plugin-vue_export-helper.1b428a4d.js";import"./u-icon.47bf0917.js";import"./u-line.33e04a4b.js";import"./u-popup.78a3211f.js";import"./u-transition.dab525a8.js";import"./u-safe-bottom.d2c92feb.js";const I=C(a({__name:"result",setup(a){const C=e(null),I=e(!1);let L="",M=0,N=0;t((a=>{L=a.trade_type,M=a.trade_id,P()}));const P=()=>{F(L,M).then((a=>{if(!uni.$u.test.isEmpty(a.data)){if(1==a.data.status&&N<5)return I.value=!0,N++,void setTimeout((()=>{P()}),1e3);C.value=a.data,I.value=!1,s({title:2==C.value.status?l("pay.paySuccess"):l("pay.payFail")})}})).catch((()=>{}))},R=()=>{var a;const e=decodeURIComponent(uni.getStorageSync("payReturn"));f(e?{url:e,mode:"redirectTo"}:{url:y(),param:{code:null==(a=C.value)?void 0:a.out_trade_no},mode:"redirectTo"})};return(a,e)=>{const t=_,s=v,f=g,y=j(h("u-button"),T),F=j(h("u-loading-icon"),S),L=j(h("u-modal"),k);return r(),o(d,null,[C.value?(r(),u(s,{key:0,class:"w-screen bg-[#fff] flex flex-col items-center"},{default:p((()=>[c(s,{class:"flex-1 flex flex-col items-center w-full pt-[100rpx]"},{default:p((()=>[2==C.value.status?(r(),u(t,{key:0,class:"max-w-[144rpx] max-h-[88rpx]",src:n(w)("static/resource/images/result/pay_succeed.png")},null,8,["src"])):(r(),u(t,{key:1,class:"max-w-[144rpx] max-h-[88rpx]",src:n(w)("static/resource/images/result/pay_error.png")},null,8,["src"])),c(s,{class:"text-[32rpx] font-bold mt-[22rpx]"},{default:p((()=>[i(m(2==C.value.status?n(l)("pay.paySuccess"):n(l)("pay.payFail")),1)])),_:1}),c(s,{class:"text-[40rpx] font-bold mt-[40rpx] text-[#FF4646]"},{default:p((()=>[c(f,{class:"text-base"},{default:p((()=>[i(m(n(l)("currency")),1)])),_:1}),c(f,null,{default:p((()=>[i(m(n(b)(C.value.money)),1)])),_:1})])),_:1})])),_:1}),c(s,{class:"pb-[80rpx] pt-[40rpx] w-[240rpx]"},{default:p((()=>[c(y,{type:"primary",text:2==C.value.status?n(l)("complete"):n(l)("close"),plain:!0,onClick:R},null,8,["text"])])),_:1})])),_:1})):x("v-if",!0),c(L,{show:I.value,showCancelButton:!0,confirmText:n(l)("pay.completePay"),cancelText:n(l)("pay.incompletePay"),onCancel:R},{default:p((()=>[c(s,{class:"py-[20rpx]"},{default:p((()=>[c(F,{text:n(l)("pay.getting"),textSize:"16",mode:"circle",vertical:!0},null,8,["text"])])),_:1})])),_:1},8,["show","confirmText","cancelText"])],64)}}}),[["__scopeId","data-v-03e1fe0c"]]);export{I as default};
