import{_ as e}from"./u-tabs.07abd6a5.js";import{d as a,r as s,K as t,o as l,aq as r,i as o,j as i,w as d,k as u,m as n,l as c,v as p,P as f,Q as m,R as x,n as b,e as _,q as v,t as y,x as k,a7 as g,G as h,H as C,ar as j,y as w,as as A,I as S}from"./index-19cdd642.js";import{_ as q,a as I}from"./uni-swipe-action.384733b7.js";import{_ as K}from"./u-empty.946c9418.js";import{_ as $}from"./u-button.16c348c3.js";import{_ as z}from"./u-tabbar.41e257d5.js";import{_ as B}from"./_plugin-vue_export-helper.1b428a4d.js";import"./u-badge.23b944d1.js";import"./u-icon.608fbfc2.js";import"./u-loading-icon.448cb1c8.js";import"./u-safe-bottom.c40e3bad.js";const D=B(a({__name:"address",setup(a){const B=s(!0),D=s(0),E=s([{name:t("address"),key:"address"},{name:t("locationAddress"),key:"location_address"}]),F=s([]),G=s([]),H=s("");l((e=>{H.value=e.type||"",e.type&&(D.value="address"==e.type?0:1)})),r({}).then((({data:e})=>{const a=[],s=[];e.forEach((e=>{"address"==e.type?a.push(e):s.push(e)})),F.value=a,G.value=s,B.value=!1})).catch((()=>{B.value=!1}));const P=e=>{D.value=e.index},Q=()=>{const e=`/app/pages/member/${E.value[D.value].key}_edit`;_({url:e,param:{type:H.value}})},R=e=>{const a=`/app/pages/member/${E.value[D.value].key}_edit`;_({url:a,param:{id:e,type:H.value}})},U=s([{text:t("delete"),style:{backgroundColor:"#F56C6C"}}]),V=e=>{const a=uni.getStorageSync("selectAddressCallback");a&&(a.address_id=e.id,uni.setStorage({key:"selectAddressCallback",data:a,success(){_({url:a.back})}}))},W=e=>{const a=D.value?G:F,s=a.value[e.index];A(s.id).then((()=>{a.value.splice(e.index,1)})).catch()};return(a,s)=>{const l=v(y("u-tabs"),e),r=k,_=S,A=v(y("uni-swipe-action-item"),q),J=v(y("u-empty"),K),L=v(y("uni-swipe-action"),I),M=v(y("u-button"),$),N=v(y("u-tabbar"),z),O=g;return B.value?n("v-if",!0):(o(),i(O,{key:0,"scroll-y":"true"},{default:d((()=>[H.value?n("v-if",!0):(o(),i(r,{key:0,class:"border-0 !border-b !border-[#eee] border-solid"},{default:d((()=>[u(l,{list:E.value,onClick:P,current:D.value,itemStyle:"width:50%;height:88rpx;box-sizing: border-box;"},null,8,["list","current"])])),_:1})),u(L,null,{default:d((()=>[c(u(r,{class:"p-[30rpx]"},{default:d((()=>[(o(!0),f(x,null,m(F.value,(e=>(o(),i(A,{"right-options":U.value,onClick:W},{default:d((()=>[u(r,{class:"border-0 !border-b !border-[#f5f5f5] border-solid pb-[30rpx] flex items-center"},{default:d((()=>[u(r,{class:"flex-1",onClick:a=>V(e)},{default:d((()=>[u(r,{class:"font-bold my-[10rpx] text-sm"},{default:d((()=>[h(C(e.full_address),1)])),_:2},1024),u(r,{class:"text-sm flex items-center"},{default:d((()=>[h(C(e.name)+" ",1),u(_,{class:"text-[26rpx] text-gray-subtitle"},{default:d((()=>[h(C(b(j)(e.mobile)),1)])),_:2},1024),1==e.is_default?(o(),i(r,{key:0,class:"bg-primary text-white text-xs px-[10rpx] leading-none flex items-center h-[32rpx] ml-[10rpx] rounded"},{default:d((()=>[h(C(b(t)("default")),1)])),_:1})):n("v-if",!0)])),_:2},1024)])),_:2},1032,["onClick"]),u(_,{class:"iconfont iconbianji",onClick:a=>R(e.id)},null,8,["onClick"])])),_:2},1024)])),_:2},1032,["right-options"])))),256)),F.value.length?n("v-if",!0):(o(),i(r,{key:0,class:"pt-[20vh]"},{default:d((()=>[u(J,{mode:"address",icon:b(w)("static/resource/images/empty.png")},null,8,["icon"])])),_:1}))])),_:1},512),[[p,0==D.value]]),c(u(r,{class:"p-[30rpx]"},{default:d((()=>[(o(!0),f(x,null,m(G.value,(e=>(o(),i(A,{"right-options":U.value,onClick:W},{default:d((()=>[u(r,{class:"border-0 !border-b !border-[#f5f5f5] border-solid pb-[30rpx] flex items-center"},{default:d((()=>[u(r,{class:"flex-1",onClick:a=>V(e)},{default:d((()=>[u(r,{class:"font-bold my-[10rpx] text-sm"},{default:d((()=>[h(C(e.full_address),1)])),_:2},1024),u(r,{class:"text-sm flex items-center"},{default:d((()=>[h(C(e.name)+" ",1),u(_,{class:"text-[26rpx] text-gray-subtitle"},{default:d((()=>[h(C(b(j)(e.mobile)),1)])),_:2},1024),1==e.is_default?(o(),i(r,{key:0,class:"bg-primary text-white text-xs px-[10rpx] leading-none flex items-center h-[32rpx] ml-[10rpx] rounded"},{default:d((()=>[h(C(b(t)("default")),1)])),_:1})):n("v-if",!0)])),_:2},1024)])),_:2},1032,["onClick"]),u(_,{class:"iconfont iconbianji",onClick:a=>R(e.id)},null,8,["onClick"])])),_:2},1024)])),_:2},1032,["right-options"])))),256)),G.value.length?n("v-if",!0):(o(),i(r,{key:0,class:"pt-[15vh]"},{default:d((()=>[u(J,{mode:"address",icon:b(w)("static/resource/images/empty.png")},null,8,["icon"])])),_:1}))])),_:1},512),[[p,1==D.value]])])),_:1}),u(N,{fixed:!0,safeAreaInsetBottom:!0,border:!1},{default:d((()=>[u(r,{class:"p-[24rpx] pt-0 w-full"},{default:d((()=>[u(M,{type:"primary",shape:"circle",text:b(t)("createAddress"),onClick:Q},null,8,["text"])])),_:1})])),_:1})])),_:1}))}}}),[["__scopeId","data-v-8c90cf16"]]);export{D as default};
