/* empty css             */import{E as V}from"./el-overlay-380df695.js";/* empty css                  */import{t as i}from"./index-ebefdd26.js";import{a as C}from"./attachment-2dcaf405.js";import{E}from"./index-6f670765.js";import{d as b,r as c,b as B,e as D,f as p,g as N,q as n,p as s,v as f,x as d,u as r,F as h}from"./runtime-core.esm-bundler-c17ab5bc.js";const w={class:"dialog-footer"},U=b({__name:"index",props:{limit:{type:Number,default:1},type:{type:String,default:"image"}},emits:["confirm"],setup(t,{expose:_,emit:y}){const g=t,e=c(!1),o=c(null),v=()=>{e.value=!0},x=()=>{e.value=!1;const l=Object.values(o==null?void 0:o.value.selectedFile);y("confirm",g.limit==1?l[0]??null:l)};return _({showDialog:e}),(l,a)=>{const m=E,k=V;return B(),D(h,null,[p("span",{onClick:v,class:"cursor-pointer"},[N(l.$slots,"default")]),n(k,{modelValue:e.value,"onUpdate:modelValue":a[1]||(a[1]=u=>e.value=u),title:r(i)("upload.select"+t.type),width:"60%",class:"attachment-dialog","destroy-on-close":!0},{footer:s(()=>[p("span",w,[n(m,{onClick:a[0]||(a[0]=u=>e.value=!1)},{default:s(()=>[f(d(r(i)("cancel")),1)]),_:1}),n(m,{type:"primary",onClick:x},{default:s(()=>[f(d(r(i)("confirm")),1)]),_:1})])]),default:s(()=>[n(C,{limit:t.limit,type:t.type,ref_key:"attachmentRef",ref:o},null,8,["limit","type"])]),_:1},8,["modelValue","title"])],64)}}});export{U as _};
