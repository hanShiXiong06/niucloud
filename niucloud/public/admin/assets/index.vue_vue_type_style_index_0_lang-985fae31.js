import{g as c,j as _,m as f,D as g,E as l,t as x,F as V,u as p,c as y,K as v,L as E,M as S}from"./base-d2ce4248.js";/* empty css                    *//* empty css                 */import{t as r}from"./index-578c83eb.js";import{g as h,a as k}from"./storage-e62e496d.js";import{a as w}from"./index-3118dac6.js";import{E as F}from"./index-9997ff5d.js";import{a as $}from"./index-5e746953.js";const T=c({__name:"index",props:{modelValue:{type:String,default:""},api:{type:String,default:"sys/document/document"}},emits:["update:modelValue"],setup(u,{emit:d}){const s=u,t=_({get(){return s.modelValue},set(e){d("update:modelValue",e)}}),a={action:`/adminapi//${s.api}`,showFileList:!1,headers:{},accept:".doc,.docx,.xml,.txt,.pem,.zip,.rar,.7z,.crt",onSuccess:(e,o)=>{t.value=e.data.url,w({message:r("upload.success"),type:"success"})}};return a.headers.token=h(),a.headers["site-id"]=k.get("siteId")||0,(e,o)=>{const n=F,m=$;return f(),g(m,S(a,{class:"w-full upload-file"}),{default:l(()=>[x(e.$slots,"default",{},()=>[V(n,{modelValue:p(t),"onUpdate:modelValue":o[0]||(o[0]=i=>y(t)?t.value=i:null),class:"w-full",readonly:!0},{append:l(()=>[v(E(p(r)("upload.root")),1)]),_:1},8,["modelValue"])])]),_:3},16)}}});export{T as _};
