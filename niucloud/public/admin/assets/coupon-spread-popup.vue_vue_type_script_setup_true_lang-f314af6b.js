/* empty css             */import{E as B}from"./el-overlay-380df695.js";/* empty css                 *//* empty css                  *//* empty css                 *//* empty css                        */import{t as o}from"./index-6b4cbbe4.js";import{b as D}from"./browser-a1ac24ac.js";import{a3 as L}from"./event-3e082a4a.js";import{S as U}from"./index-596de8a9.js";import{a as g}from"./index-a6ffcea0.js";import{E as $}from"./index-6701860e.js";import{E as I}from"./index-6f670765.js";import{E as N}from"./index-5f2625ed.js";import{d as j,M,r as t,w as Q,b as T,e as q,q as l,p as c,f as a,x as u,u as n,v as O}from"./runtime-core.esm-bundler-c17ab5bc.js";const P={class:"promote-flex flex"},R={class:"promote-img flex justify-center items-center bg-[#f8f8f8] w-[150px] h-[150px]"},z={class:"px-[20px] flex-1"},A={class:"mb-[10px]"},F=["href"],ne=j({__name:"coupon-spread-popup",setup(G,{expose:w}){const m=M({}),s=t(!1),d=t("");t("");const i=t(""),r=t(""),_=t("");U().then(e=>{d.value=e.data.wap_url});const x=()=>{r.value=`${d.value}${_.value}`,D.toDataURL(r.value,{errorCorrectionLevel:"L",margin:0,width:120}).then(e=>{i.value=e})},h=e=>{Object.assign(m,e),_.value=`/shop/pages/coupon/detail?coupon_id=${m.id}`,x(),s.value=!0},{copy:y,isSupported:E,copied:f}=L(),b=e=>{E.value||g({message:o("notSupportCopy"),type:"warning"}),y(e)};return Q(f,()=>{f.value&&g({message:o("copySuccess"),type:"success"})}),w({showDialog:s,show:h}),(e,p)=>{const C=$,S=I,V=N,k=B;return T(),q("div",null,[l(k,{modelValue:s.value,"onUpdate:modelValue":p[1]||(p[1]=v=>s.value=v),title:n(o)("couponSpreadTitle"),width:"500px","destroy-on-close":!0},{default:c(()=>[a("div",P,[a("div",R,[l(C,{src:i.value},null,8,["src"])]),a("div",z,[a("div",A,u(n(o)("spreadLink")),1),l(V,{class:"mb-[10px]",readonly:"",value:r.value},{append:c(()=>[l(S,{onClick:p[0]||(p[0]=v=>b(r.value))},{default:c(()=>[O(u(n(o)("copy")),1)]),_:1})]),_:1},8,["value"]),a("a",{class:"text-primary",href:i.value,download:""},u(n(o)("downloadQrcode")),9,F)])])]),_:1},8,["modelValue","title"])])}}});export{ne as _};
