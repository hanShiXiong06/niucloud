import{b as n,d as p}from"./index-7e933ae4.js";import{d,b as c,e as o,f as t,n as r,u as s,h as l,A as i,B as u,H as m,g as y,j as f,_ as h,l as v}from"./base-04829be5.js";const b=n({header:{type:String,default:""},bodyStyle:{type:p([String,Object,Array]),default:""},shadow:{type:String,values:["always","hover","never"],default:"always"}}),S=d({name:"ElCard"}),_=d({...S,props:b,setup(g){const a=c("card");return(e,C)=>(o(),t("div",{class:r([s(a).b(),s(a).is(`${e.shadow}-shadow`)])},[e.$slots.header||e.header?(o(),t("div",{key:0,class:r(s(a).e("header"))},[l(e.$slots,"header",{},()=>[i(u(e.header),1)])],2)):m("v-if",!0),y("div",{class:r(s(a).e("body")),style:f(e.bodyStyle)},[l(e.$slots,"default")],6)],2))}});var w=h(_,[["__file","/home/runner/work/element-plus/element-plus/packages/components/card/src/card.vue"]]);const E=v(w);export{E};
