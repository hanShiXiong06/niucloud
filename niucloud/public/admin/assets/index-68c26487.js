/* empty css             *//* empty css                   *//* empty css                *//* empty css                 *//* empty css                 *//* empty css                        */import{a as j,E as B}from"./el-form-item-144bc604.js";/* empty css                  *//* empty css                 */import{_ as M}from"./app_default-ef62993a.js";import{g as D,y as R,z as T}from"./index-d5447f06.js";import{a as x,c as U}from"./common-a45d855f.js";import{a as $}from"./vue-router-9f815af7.js";import{t as _}from"./index-ebefdd26.js";import{E as q}from"./index-5f2625ed.js";import{E as z}from"./index-6f670765.js";import{E as P}from"./index-6701860e.js";import{E as G}from"./index-d64b2f0e.js";import{E as H}from"./index-c5af06c2.js";import{v as J}from"./directive-d226d53f.js";import{d as K,M as O,r as v,L as Q,u as a,b as l,e as n,q as r,p as i,f as s,v as W,x as g,F as X,t as Y,C as y,at as Z,au as ee}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as te}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./event-3e082a4a.js";import"./_baseClone-37ba2e68.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./index-e42600b8.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";/* empty css                  */import"./index-a6ffcea0.js";import"./index-138bfa06.js";import"./index-72bf6cb5.js";import"./index-f6eed9e8.js";import"./debounce-196ce6a6.js";import"./position-0d02b322.js";const k=c=>(Z("data-v-ea110895"),c=c(),ee(),c),oe={class:"main-container w-full bg-white"},se={class:"flex justify-between items-center"},re=k(()=>s("span",{class:"text-[20px]"},"应用管理",-1)),ae={key:0,class:"flex flex-wrap plug-list pb-10 plug-large"},pe={class:"relative app-item cursor-pointer px-4 mr-4 mt-[20px] bg-[#f7f7f7] border-[1px] hover:border-primary"},ie=["onClick"],le={class:"flex justify-center items-center"},ne=k(()=>s("div",{class:"image-slot"},[s("img",{class:"w-[50px] h-[50px]",src:M})],-1)),ce={class:"flex flex-col justify-between text-left w-[190px]"},me={class:"app-text w-[190px] text-[17px] text-[#222] pl-3"},_e={key:1,class:"empty flex items-center justify-center"},de=K({__name:"index",setup(c){var E=x.get("menuAppStorage");const d=D(),b=$(),o=O({list:[],search:{title:"",key:E}});let u=v(!0);const f=async()=>{const e=await R({title:o.search.title,support_app:o.search.key});o.list=e.data.filter(t=>m.value[t.key]&&t.type=="addon"),u.value=!1},m=v({});(()=>{d.routers.forEach((e,t)=>{e.meta.app!=""&&(e.children&&e.children.length?m.value[e.meta.app]=T(e.children):m.value[e.meta.app]=e.name)}),f()})();const w=e=>{x.set({key:"plugMenuTypeStorage",data:e});let t=d.appMenuList;t.length&&t.includes(e)||t.push(e),d.setAppMenuList(t),b.push({name:m.value[e]})};return(e,t)=>{const L=q,h=j,C=z,S=B,V=P,A=G,I=H,F=J;return Q((l(),n("div",oe,[r(I,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[s("div",se,[re,r(S,{inline:!0,model:o.search,ref:"searchFormRef"},{default:i(()=>[r(h,{label:a(_)("appName"),prop:"title"},{default:i(()=>[r(L,{modelValue:o.search.title,"onUpdate:modelValue":t[0]||(t[0]=p=>o.search.title=p),placeholder:a(_)("appNamePlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(h,null,{default:i(()=>[r(C,{type:"primary",onClick:t[1]||(t[1]=p=>f())},{default:i(()=>[W(g(a(_)("search")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),o.list.length?(l(),n("div",ae,[(l(!0),n(X,null,Y(o.list,(p,N)=>(l(),n("div",{key:N+"b"},[s("div",pe,[s("div",{onClick:fe=>w(p.key),class:"flex py-5 items-center"},[s("div",le,[r(V,{class:"w-[40px] h-[40px]",src:a(U)(p.icon),fit:"contain"},{error:i(()=>[ne]),_:2},1032,["src"])]),s("div",ce,[s("p",me,g(p.title),1)])],8,ie)])]))),128))])):y("",!0),!a(u)&&!o.list.length?(l(),n("div",_e,[r(A,{description:a(_)("emptyAppData")},null,8,["description"])])):y("",!0)]),_:1})])),[[F,a(u)]])}}});const st=te(de,[["__scopeId","data-v-ea110895"]]);export{st as default};
