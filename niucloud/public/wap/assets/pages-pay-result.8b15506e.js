import{d as t,r as a,o as e,f as s,J as l,h as o,M as u,i as r,w as i,j as n,F as c,D as p,E as m,l as d,m as f,O as x,R as y,b5 as _,H as v,t as j,p as h,q as b,aq as g}from"./index-15eacd31.js";import{_ as w}from"./u-button.5c85e6cc.js";import{_ as T}from"./u-loading-icon.ea8c9ece.js";import{_ as C}from"./u-modal.755a4b61.js";import{g as F}from"./pay.422381f0.js";import"./u-icon.f5369555.js";import"./_plugin-vue_export-helper.1b428a4d.js";import"./u-line.bb8d1497.js";import"./u-popup.8a491625.js";import"./u-transition.a952a64b.js";import"./u-safe-bottom.593b5cec.js";const S=t({__name:"result",setup(t){const S=a(null),k=a(!1);let q="",z=0,E=0;e((t=>{q=t.trade_type,z=t.trade_id,P()}));const P=()=>{F(q,z).then((t=>{if(!uni.$u.test.isEmpty(t.data)){if(1==t.data.status&&E<5)return k.value=!0,E++,void setTimeout((()=>{P()}),1e3);S.value=t.data,k.value=!1,s({title:2==S.value.status?l("pay.paySuccess"):l("pay.payFail")})}})).catch((()=>{}))},B=()=>{var t;y({url:_(),param:{code:null==(t=S.value)?void 0:t.out_trade_no},mode:"redirectTo"})};return(t,a)=>{const e=v,s=j,y=h(b("u-button"),w),_=h(b("u-loading-icon"),T),F=h(b("u-modal"),C);return o(),u(x,null,[S.value?(o(),r(s,{key:0,class:"w-screen h-screen flex flex-col items-center"},{default:i((()=>[n(s,{class:"flex-1 flex flex-col items-center w-full pt-[100rpx]"},{default:i((()=>[n(e,{class:c(["iconfont text-2xl",2==S.value.status?"text-primary iconduigou":"iconzhifushibai text-red"])},null,8,["class"]),n(s,{class:"text-sm"},{default:i((()=>[p(m(2==S.value.status?d(l)("pay.paySuccess"):d(l)("pay.payFail")),1)])),_:1}),n(s,{class:"text-xl font-bold pt-[30rpx]"},{default:i((()=>[n(e,{class:"text-base"},{default:i((()=>[p(m(d(l)("currency")),1)])),_:1}),n(e,null,{default:i((()=>[p(m(d(g)(S.value.money)),1)])),_:1})])),_:1})])),_:1}),n(s,{class:"pb-[200rpx] w-[240rpx]"},{default:i((()=>[n(y,{type:"primary",text:2==S.value.status?d(l)("complete"):d(l)("close"),plain:!0,onClick:B},null,8,["text"])])),_:1})])),_:1})):f("",!0),n(F,{show:k.value,showCancelButton:!0,confirmText:d(l)("pay.completePay"),cancelText:d(l)("pay.incompletePay"),onCancel:B},{default:i((()=>[n(s,{class:"py-[20rpx]"},{default:i((()=>[n(_,{text:d(l)("pay.getting"),textSize:"16",mode:"circle",vertical:!0},null,8,["text"])])),_:1})])),_:1},8,["show","confirmText","cancelText"])],64)}}});export{S as default};
