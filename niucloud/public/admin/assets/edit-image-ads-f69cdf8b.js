import"./base-962c0c23.js";/* empty css                     *//* empty css                  */import{_ as B}from"./index.vue_vue_type_script_setup_true_lang-12727745.js";import{B as F}from"./index-7525671c.js";import{_ as T}from"./index-f4731ae1.js";/* empty css                 */import{v as c}from"./event-ff03ec12.js";import{t as l}from"./index-105fbad0.js";import{S as A}from"./sortable.esm-be94e56d.js";import{d as R}from"./storage-abe718b1.js";import{u as $}from"./diy-f6b4263e.js";import{r as N}from"./range-5a416794.js";import{E as z}from"./index-93f2c618.js";import{a as D,E as P}from"./index-61c777fa.js";import{E as W}from"./index-bba9e58c.js";import{d as j,w as L,r as M,o as O,A as q,b as h,e as f,L as g,u as o,f as m,x as b,q as n,p as d,v as x,F as v,t as G,g as J}from"./runtime-core.esm-bundler-dc7a07d7.js";import{_ as K}from"./_plugin-vue_export-helper-c27b6911.js";const Q={class:"content-wrap"},X={class:"edit-attr-item-wrap"},Y={class:"mb-[10px]"},Z={class:"text-sm text-gray-400 mb-[10px]"},ee=["onClick"],te={class:"style-wrap"},oe=j({__name:"edit-image-ads",setup(ie,{expose:w}){const t=$();t.editComponent.ignore=[],t.editComponent.verify=a=>{var e={code:!0,message:""};return t.value[a].imageHeight==0?(e.code=!1,e.message=l("imageHeightPlaceholder"),e):/^\d+.?\d{0,2}$/.test(t.value[a].imageHeight)?(t.value[a].list.forEach(s=>{if(s.imageUrl==="")return e.code=!1,e.message=l("imageUrlTip"),e}),e):(e.code=!1,e.message=l("imageHeightRegNum"),e)},t.editComponent.list.forEach(a=>{a.id||(a.id=t.generateRandom())}),L(()=>t.editComponent.list,(a,e)=>{_()},{deep:!0});const y=()=>{t.editComponent.list.push({id:t.generateRandom(),imageUrl:"",imgWidth:0,imgHeight:0,link:{name:""}})},H=a=>{_(!0)},_=(a=!1)=>{t.editComponent.list.forEach((e,s)=>{let i=new Image;i.src=R(e.imageUrl),i.onload=async()=>{if(e.imgWidth=i.width,e.imgHeight=i.height,a&&s==0){var u=e.imgHeight/e.imgWidth;e.width=375,e.height=e.width*u,t.editComponent.imageHeight=e.height}}})},V=()=>{t.editComponent.imageHeight=parseFloat(t.editComponent.imageHeight).toFixed(2)},C=M();return O(()=>{q(()=>{const a=A.create(C.value,{group:"item-wrap",animation:200,onEnd:e=>{const s=t.editComponent.list[e.oldIndex];t.editComponent.list.splice(e.oldIndex,1),t.editComponent.list.splice(e.newIndex,0,s),a.sort(N(t.editComponent.list.length).map(i=>i.toString())),_(!0)}})})}),w({}),(a,e)=>{const s=z,i=D,u=T,k=F,I=B,E=W,S=P;return h(),f(v,null,[g(m("div",Q,[m("div",X,[m("h3",Y,b(o(l)("imageSet")),1),n(S,{"label-width":"80px",class:"px-[10px]"},{default:d(()=>[n(i,{label:o(l)("imageHeight"),class:"display-block"},{default:d(()=>[n(s,{modelValue:o(t).editComponent.imageHeight,"onUpdate:modelValue":e[0]||(e[0]=r=>o(t).editComponent.imageHeight=r),placeholder:o(l)("imageHeightPlaceholder"),clearable:"",maxlength:"10",onBlur:V},{append:d(()=>[x("px")]),_:1},8,["modelValue","placeholder"]),m("div",Z,b(o(l)("imageAdsTips")),1)]),_:1},8,["label"]),m("div",{ref_key:"imageBoxRef",ref:C},[(h(!0),f(v,null,G(o(t).editComponent.list,(r,U)=>(h(),f("div",{key:r.id,class:"item-wrap p-[10px] pb-0 relative border border-dashed border-gray-300 mb-[16px]"},[n(i,{label:o(l)("image")},{default:d(()=>[n(u,{modelValue:r.imageUrl,"onUpdate:modelValue":p=>r.imageUrl=p,limit:1,onChange:H},null,8,["modelValue","onUpdate:modelValue"])]),_:2},1032,["label"]),g(m("div",{class:"del absolute cursor-pointer z-[2] top-[-8px] right-[-8px]",onClick:p=>o(t).editComponent.list.splice(U,1)},[n(k,{name:"element-CircleCloseFilled",color:"#bbb",size:"20px"})],8,ee),[[c,o(t).editComponent.list.length>1]]),n(i,{label:o(l)("link")},{default:d(()=>[n(I,{modelValue:r.link,"onUpdate:modelValue":p=>r.link=p},null,8,["modelValue","onUpdate:modelValue"])]),_:2},1032,["label"])]))),128))],512),g(n(E,{class:"w-full",onClick:y},{default:d(()=>[x(b(o(l)("addImageAd")),1)]),_:1},512),[[c,o(t).editComponent.list.length<10]])]),_:1})])],512),[[c,o(t).editTab=="content"]]),g(m("div",te,[J(a.$slots,"style",{},void 0,!0)],512),[[c,o(t).editTab=="style"]])],64)}}});const ae=K(oe,[["__scopeId","data-v-553cf91a"]]),ye=Object.freeze(Object.defineProperty({__proto__:null,default:ae},Symbol.toStringTag,{value:"Module"}));export{ye as _};
