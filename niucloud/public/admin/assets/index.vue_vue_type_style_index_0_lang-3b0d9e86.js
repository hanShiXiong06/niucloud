import{d as i,c as f,e as _,v as g,x as l,h as x,y,u as p,i as V,A as v,B as S,C as h}from"./base-d77b0726.js";/* empty css                    *//* empty css                 */import{t as r}from"./index-ace71ef4.js";import{g as k}from"./common-56ee0a80.js";import{a as w}from"./index-9e51ba8b.js";import{E}from"./index-c1eb81db.js";import{a as B}from"./index-bbf3e154.js";const D=i({__name:"index",props:{modelValue:{type:String,default:""},api:{type:String,default:"sys/document/document"},accept:{type:String,default:".doc,.docx,.xml,.txt,.pem,.zip,.rar,.7z,.crt"}},emits:["update:modelValue"],setup(u,{emit:n}){const a=u,t=f({get(){return a.modelValue},set(e){n("update:modelValue",e)}}),s={action:`/adminapi//${a.api}`,showFileList:!1,headers:{},accept:a.accept,onSuccess:(e,o)=>{t.value=e.data.url,w({message:r("upload.success"),type:"success"})}};return s.headers.token=k(),(e,o)=>{const d=E,c=B;return _(),g(c,h(s,{class:"w-full upload-file"}),{default:l(()=>[x(e.$slots,"default",{},()=>[y(d,{modelValue:p(t),"onUpdate:modelValue":o[0]||(o[0]=m=>V(t)?t.value=m:null),class:"w-full",readonly:!0},{append:l(()=>[v(S(p(r)("upload.root")),1)]),_:1},8,["modelValue"])])]),_:3},16)}}});export{D as _};