import"./base-0e92f4db.js";/* empty css                   *//* empty css                 *//* empty css                *//* empty css                 *//* empty css                        */import{_ as E}from"./app_store_default-c4798c2d.js";import{g as C,y as A,z as S}from"./index-fac59425.js";import{c as I}from"./common-46715e7e.js";import{a as j}from"./vue-router-8b032575.js";import{t as B}from"./index-2d1c7ba3.js";import{E as V}from"./index-2b1dc445.js";import{E as D}from"./index-2668a8ea.js";import{E as F}from"./index-0caa5b89.js";import{v as M}from"./directive-c6f70b8e.js";import{d as N,M as R,r as d,L as q,u as n,b as o,e as i,m as z,p as u,f as e,F as U,t as $,q as f,x,C as _,au as G,av as H}from"./runtime-core.esm-bundler-67034826.js";import{_ as J}from"./_plugin-vue_export-helper-c27b6911.js";import"./event-a537c4cb.js";import"./index-72686045.js";import"./index-81f2aa1e.js";import"./el-main-7a89c415.js";import"./index-ebd2990f.js";import"./el-overlay-3eff2fc5.js";import"./index-defed8ff.js";import"./focus-trap-83769a43.js";import"./index-6cae7119.js";import"./index-d87ae4a2.js";/* empty css                  */import"./el-form-item-c2dd2ffe.js";/* empty css                 *//* empty css                  */import"./index-e9d9b1a1.js";import"./index-8cefa3ab.js";import"./index-e09a20f5.js";import"./index-ef31373f.js";import"./index-de22cd40.js";import"./index-c7745eb3.js";import"./debounce-f6ba9d12.js";import"./position-c2e84b2a.js";const h=p=>(G("data-v-32a262a7"),p=p(),H(),p),K={class:"main-container w-full bg-white"},O=h(()=>e("div",{class:"flex justify-between items-center"},[e("span",{class:"text-[20px]"},"应用管理")],-1)),P={class:"flex flex-wrap plug-list pb-10 plug-large"},Q={key:0,class:"relative app-item cursor-pointer px-4 mr-4 mt-[20px] bg-[#f7f7f7] border-[1px] hover:border-primary"},T=["onClick"],W={class:"flex py-5 items-center"},X={class:"flex justify-center items-center"},Y=h(()=>e("div",{class:"image-slot"},[e("img",{class:"w-[50px] h-[50px]",src:E})],-1)),Z={class:"flex flex-col justify-between text-left w-[190px]"},tt={class:"app-text w-[190px] text-[17px] text-[#222] pl-3"},et={class:"border-t-[1px] border-[#e8e9eb] py-3"},st={class:"app-text text-[14px] text-[#999] w-[200px]"},ot={key:1,class:"empty flex items-center justify-center"},rt=N({__name:"concern",setup(p){const l=C(),v=j(),a=R({list:[]});let m=d(!0);(async()=>{const t=await A({type:"tart"});a.list=t.data,m.value=!1})();const c=d({});(()=>{l.routers.forEach((t,s)=>{t.meta.app!=""&&(t.children&&t.children.length?c.value[t.meta.app]=S(t.children):c.value[t.meta.app]=t.name)})})();const g=t=>{let s=l.appMenuList;s.length&&s.includes(t)||s.push(t),l.setAppMenuList(s),v.push({name:c.value[t]})};return(t,s)=>{const y=V,k=D,w=F,b=M;return q((o(),i("div",K,[a.list.length?(o(),z(k,{key:0,class:"box-card !border-none",shadow:"never"},{default:u(()=>[O,e("div",P,[(o(!0),i(U,null,$(a.list,(r,L)=>(o(),i("div",{key:L+"b"},[c.value[r.key]?(o(),i("div",Q,[e("div",{onClick:at=>g(r.key)},[e("div",W,[e("div",X,[f(y,{class:"w-[50px] h-[50px]",src:n(I)(r.icon),fit:"contain"},{error:u(()=>[Y]),_:2},1032,["src"])]),e("div",Z,[e("p",tt,x(r.title),1)])]),e("div",et,[e("p",st,x(r.desc),1)])],8,T)])):_("",!0)]))),128))])]),_:1})):_("",!0),!n(m)&&!a.list.length?(o(),i("div",ot,[f(w,{description:n(B)("emptyData")},null,8,["description"])])):_("",!0)])),[[b,n(m)]])}}});const Pt=J(rt,[["__scopeId","data-v-32a262a7"]]);export{Pt as default};
