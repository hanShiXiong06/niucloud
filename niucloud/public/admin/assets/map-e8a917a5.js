import{g as h,r as c,a4 as w,m as d,n as x,a1 as b,D,E as r,F as s,q as i,L as _,u as m,K as V}from"./base-45eb5090.js";/* empty css                   *//* empty css                  *//* empty css                     *//* empty css                *//* empty css                 */import{t as n}from"./index-3694a2b2.js";import{ai as B,aj as F}from"./index-ad71a852.js";import{E as C}from"./index-4ce9333e.js";import{a as N,E as I}from"./index-2bfbe5a7.js";import{E as K}from"./index-fc3020f4.js";import{E as L}from"./index-25c37860.js";import{v as M}from"./directive-9f485fe5.js";import"./common-af78c857.js";import"./common-2cf17469.js";import"./vue-router-fcbaaf02.js";import"./el-overlay-616d6124.js";import"./event-4977bef7.js";import"./index-cd1661d3.js";import"./focus-trap-318ae2e0.js";import"./el-radio-2719e44c.js";import"./storage-4159d1ed.js";import"./index-9670e877.js";import"./index-3be486d3.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-tooltip-58212670.js";import"./el-avatar-bc58ad46.js";import"./index-aef37b98.js";import"./_Uint8Array-e584e472.js";import"./_initCloneObject-983ff8c8.js";const R={class:"main-container"},S={class:"panel-title"},j={class:"fixed-footer-wrap"},q={class:"fixed-footer"},yt=h({__name:"map",setup(T){const t=c(!1),l=c(),o=w({key:""});(async()=>{const e=await(await B()).data;o.key=e.key})();const f=async e=>{t.value||!e||F(o).then(()=>{t.value=!1}).catch(()=>{t.value=!1})};return(e,a)=>{const u=C,v=N,y=K,E=I,g=L,k=M;return d(),x("div",R,[b((d(),D(E,{model:o,"label-width":"150px",ref_key:"formRef",ref:l,class:"page-form"},{default:r(()=>[s(y,{class:"box-card !border-none",shadow:"never"},{default:r(()=>[i("h3",S,_(m(n)("mapSetting")),1),s(v,{label:m(n)("mapKey"),prop:"site_name"},{default:r(()=>[s(u,{modelValue:o.key,"onUpdate:modelValue":a[0]||(a[0]=p=>o.key=p),class:"input-width",clearable:""},null,8,["modelValue"])]),_:1},8,["label"])]),_:1})]),_:1},8,["model"])),[[k,t.value]]),i("div",j,[i("div",q,[s(g,{type:"primary",loading:t.value,onClick:a[1]||(a[1]=p=>f(l.value))},{default:r(()=>[V(_(m(n)("save")),1)]),_:1},8,["loading"])])])])}}});export{yt as default};