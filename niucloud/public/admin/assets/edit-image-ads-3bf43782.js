/* empty css             */import{a as B,E as T}from"./el-form-item-144bc604.js";/* empty css                  */import{_ as A}from"./index.vue_vue_type_script_setup_true_lang-4c106e55.js";import{_ as F}from"./index-1bbcede8.js";import{_ as R}from"./index-2de2d5e5.js";/* empty css                 */import{v as c}from"./event-3e082a4a.js";import{t as n}from"./index-3862e13d.js";import{S as $}from"./sortable.esm-be94e56d.js";import{c as N}from"./common-a45d855f.js";import{u as z}from"./diy-d0990451.js";import{r as D}from"./range-2f27c02f.js";import{E as P}from"./index-5f2625ed.js";import{E as W}from"./index-6f670765.js";import{d as j,w as L,r as M,o as O,A as q,b as h,e as f,L as g,u as o,f as m,x as b,q as l,p as d,v,F as x,t as G,g as J}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as K}from"./_plugin-vue_export-helper-c27b6911.js";const Q={class:"content-wrap"},X={class:"edit-attr-item-wrap"},Y={class:"mb-[10px]"},Z={class:"text-sm text-gray-400 mb-[10px]"},ee=["onClick"],te={class:"style-wrap"},oe=j({__name:"edit-image-ads",setup(ie,{expose:w}){const t=z();t.editComponent.ignore=[],t.editComponent.verify=a=>{var e={code:!0,message:""};return t.value[a].imageHeight==0?(e.code=!1,e.message=n("imageHeightPlaceholder"),e):/^\d+.?\d{0,2}$/.test(t.value[a].imageHeight)?(t.value[a].list.forEach(s=>{if(s.imageUrl==="")return e.code=!1,e.message=n("imageUrlTip"),e}),e):(e.code=!1,e.message=n("imageHeightRegNum"),e)},t.editComponent.list.forEach(a=>{a.id||(a.id=t.generateRandom())}),L(()=>t.editComponent.list,(a,e)=>{_()},{deep:!0});const y=()=>{t.editComponent.list.push({id:t.generateRandom(),imageUrl:"",imgWidth:0,imgHeight:0,link:{name:""}})},H=a=>{_(!0)},_=(a=!1)=>{t.editComponent.list.forEach((e,s)=>{let i=new Image;i.src=N(e.imageUrl),i.onload=async()=>{if(e.imgWidth=i.width,e.imgHeight=i.height,a&&s==0){var u=e.imgHeight/e.imgWidth;e.width=375,e.height=e.width*u,t.editComponent.imageHeight=parseInt(e.height)}}})},V=()=>{t.editComponent.imageHeight=parseInt(t.editComponent.imageHeight)},C=M();return O(()=>{q(()=>{const a=$.create(C.value,{group:"item-wrap",animation:200,onEnd:e=>{const s=t.editComponent.list[e.oldIndex];t.editComponent.list.splice(e.oldIndex,1),t.editComponent.list.splice(e.newIndex,0,s),a.sort(D(t.editComponent.list.length).map(i=>i.toString())),_(!0)}})})}),w({}),(a,e)=>{const s=P,i=B,u=R,I=F,k=A,E=W,S=T;return h(),f(x,null,[g(m("div",Q,[m("div",X,[m("h3",Y,b(o(n)("imageSet")),1),l(S,{"label-width":"80px",class:"px-[10px]"},{default:d(()=>[l(i,{label:o(n)("imageHeight"),class:"display-block"},{default:d(()=>[l(s,{modelValue:o(t).editComponent.imageHeight,"onUpdate:modelValue":e[0]||(e[0]=r=>o(t).editComponent.imageHeight=r),placeholder:o(n)("imageHeightPlaceholder"),clearable:"",maxlength:"10",onBlur:V},{append:d(()=>[v("px")]),_:1},8,["modelValue","placeholder"]),m("div",Z,b(o(n)("imageAdsTips")),1)]),_:1},8,["label"]),m("div",{ref_key:"imageBoxRef",ref:C},[(h(!0),f(x,null,G(o(t).editComponent.list,(r,U)=>(h(),f("div",{key:r.id,class:"item-wrap !cursor-move p-[10px] pb-0 relative border border-dashed border-gray-300 mb-[16px]"},[l(i,{label:o(n)("image")},{default:d(()=>[l(u,{modelValue:r.imageUrl,"onUpdate:modelValue":p=>r.imageUrl=p,limit:1,onChange:H},null,8,["modelValue","onUpdate:modelValue"])]),_:2},1032,["label"]),g(m("div",{class:"del absolute cursor-pointer z-[2] top-[-8px] right-[-8px]",onClick:p=>o(t).editComponent.list.splice(U,1)},[l(I,{name:"element-CircleCloseFilled",color:"#bbb",size:"20px"})],8,ee),[[c,o(t).editComponent.list.length>1]]),l(i,{label:o(n)("link")},{default:d(()=>[l(k,{modelValue:r.link,"onUpdate:modelValue":p=>r.link=p},null,8,["modelValue","onUpdate:modelValue"])]),_:2},1032,["label"])]))),128))],512),g(l(E,{class:"w-full",onClick:y},{default:d(()=>[v(b(o(n)("addImageAd")),1)]),_:1},512),[[c,o(t).editComponent.list.length<10]])]),_:1})])],512),[[c,o(t).editTab=="content"]]),g(m("div",te,[J(a.$slots,"style",{},void 0,!0)],512),[[c,o(t).editTab=="style"]])],64)}}});const ae=K(oe,[["__scopeId","data-v-1d588f80"]]),we=Object.freeze(Object.defineProperty({__proto__:null,default:ae},Symbol.toStringTag,{value:"Module"}));export{we as _};
