import"./base-962c0c23.js";/* empty css                   *//* empty css                  *//* empty css                     *//* empty css                *//* empty css                 */import{t as i}from"./index-105fbad0.js";import{K as g,L as h}from"./sys-4abedf95.js";import{E as k}from"./index-93f2c618.js";import{a as b,E as w}from"./index-61c777fa.js";import{E as x}from"./index-69523418.js";import{E as V}from"./index-bba9e58c.js";import{v as B}from"./directive-c0c3e9a3.js";import{d as D,r as C,M as F,b as l,e as L,L as M,m as N,p as r,q as s,f as m,x as c,u as n,v as I}from"./runtime-core.esm-bundler-dc7a07d7.js";import"./common-080b9b9f.js";import"./common-2cf17469.js";import"./index-7525671c.js";import"./vue-router-79053937.js";import"./el-overlay-60700377.js";import"./event-ff03ec12.js";import"./index-5d86eb33.js";import"./focus-trap-b8b5a003.js";import"./el-radio-bfd4b1ad.js";import"./storage-abe718b1.js";import"./index-8bcaafa6.js";import"./index-7a123a20.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-tooltip-58212670.js";import"./el-avatar-3bb47ce2.js";import"./index-d57cc47d.js";import"./_Uint8Array-6ff3cafa.js";import"./_initCloneObject-28e6bdaa.js";const K={class:"main-container"},R={class:"panel-title"},S={class:"fixed-footer-wrap"},q={class:"fixed-footer"},Et=D({__name:"map",setup(T){const t=C(!1),o=F({key:""});(async()=>{const e=await(await g()).data;o.key=e.key})();const d=async e=>{t.value||!e||h(o).then(()=>{t.value=!1}).catch(()=>{t.value=!1})};return(e,a)=>{const f=k,_=b,u=x,v=w,y=V,E=B;return l(),L("div",K,[M((l(),N(v,{model:o,"label-width":"150px",ref:"formRef",class:"page-form"},{default:r(()=>[s(u,{class:"box-card !border-none",shadow:"never"},{default:r(()=>[m("h3",R,c(n(i)("mapSetting")),1),s(_,{label:n(i)("mapKey"),prop:"site_name"},{default:r(()=>[s(f,{modelValue:o.key,"onUpdate:modelValue":a[0]||(a[0]=p=>o.key=p),class:"input-width",clearable:""},null,8,["modelValue"])]),_:1},8,["label"])]),_:1})]),_:1},8,["model"])),[[E,t.value]]),m("div",S,[m("div",q,[s(y,{type:"primary",loading:t.value,onClick:a[1]||(a[1]=p=>d(e.formRef))},{default:r(()=>[I(c(n(i)("save")),1)]),_:1},8,["loading"])])])])}}});export{Et as default};