import{d as V,r as c,e as B,f as C,g as f,h as E,y as n,x as s,A as d,B as p,u as i,F as h}from"./base-06478700.js";import{E as D}from"./el-overlay-42a687c6.js";/* empty css                  */import{t as r}from"./index-e5b4f072.js";import{a as N}from"./attachment-27789be1.js";import{E as b}from"./index-c2f001d3.js";const w={class:"dialog-footer"},T=V({__name:"index",props:{limit:{type:Number,default:1},type:{type:String,default:"image"}},emits:["confirm"],setup(t,{expose:y,emit:_}){const g=t,e=c(!1),o=c(null),v=()=>{e.value=!0},x=()=>{e.value=!1;const l=Object.values(o==null?void 0:o.value.selectedFile);_("confirm",g.limit==1?l[0]??null:l)};return y({showDialog:e}),(l,a)=>{const m=b,k=D;return B(),C(h,null,[f("span",{onClick:v,class:"cursor-pointer"},[E(l.$slots,"default")]),n(k,{modelValue:e.value,"onUpdate:modelValue":a[1]||(a[1]=u=>e.value=u),title:i(r)("upload.select"+t.type),width:"60%",class:"attachment-dialog","destroy-on-close":!0},{footer:s(()=>[f("span",w,[n(m,{onClick:a[0]||(a[0]=u=>e.value=!1)},{default:s(()=>[d(p(i(r)("cancel")),1)]),_:1}),n(m,{type:"primary",onClick:x},{default:s(()=>[d(p(i(r)("confirm")),1)]),_:1})])]),default:s(()=>[n(N,{limit:t.limit,type:t.type,ref_key:"attachmentRef",ref:o},null,8,["limit","type"])]),_:1},8,["modelValue","title"])],64)}}});export{T as _};
