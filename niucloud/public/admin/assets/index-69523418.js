import{b as n,d as p,u as c,_ as i,w as u}from"./base-962c0c23.js";import{d as l,b as o,e as t,n as r,u as s,g as d,v as m,x as y,C as f,f as h,h as v}from"./runtime-core.esm-bundler-dc7a07d7.js";const b=n({header:{type:String,default:""},bodyStyle:{type:p([String,Object,Array]),default:""},shadow:{type:String,values:["always","hover","never"],default:"always"}}),w=l({name:"ElCard"}),S=l({...w,props:b,setup(C){const a=c("card");return(e,g)=>(o(),t("div",{class:r([s(a).b(),s(a).is(`${e.shadow}-shadow`)])},[e.$slots.header||e.header?(o(),t("div",{key:0,class:r(s(a).e("header"))},[d(e.$slots,"header",{},()=>[m(y(e.header),1)])],2)):f("v-if",!0),h("div",{class:r(s(a).e("body")),style:v(e.bodyStyle)},[d(e.$slots,"default")],6)],2))}});var _=i(S,[["__file","/home/runner/work/element-plus/element-plus/packages/components/card/src/card.vue"]]);const N=u(_);export{N as E};