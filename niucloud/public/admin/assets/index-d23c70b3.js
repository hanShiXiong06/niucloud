import{b as g,d as r,a as b}from"./index-72686045.js";import{m as u}from"./index-8cefa3ab.js";import{u as _,_ as h,w}from"./base-0e92f4db.js";import{d as p,D as O,c as l,k as R,b as $,m as v,p as j,g as N,n as x,u as c,h as C,U as E,E as S}from"./runtime-core.esm-bundler-67034826.js";const k=Symbol("rowContextKey"),K=g({tag:{type:String,default:"div"},span:{type:Number,default:24},offset:{type:Number,default:0},pull:{type:Number,default:0},push:{type:Number,default:0},xs:{type:r([Number,Object]),default:()=>u({})},sm:{type:r([Number,Object]),default:()=>u({})},md:{type:r([Number,Object]),default:()=>u({})},lg:{type:r([Number,Object]),default:()=>u({})},xl:{type:r([Number,Object]),default:()=>u({})}}),P=p({name:"ElCol"}),B=p({...P,props:K,setup(f){const e=f,{gutter:o}=O(k,{gutter:l(()=>0)}),a=_("col"),i=l(()=>{const t={};return o.value&&(t.paddingLeft=t.paddingRight=`${o.value/2}px`),t}),m=l(()=>{const t=[];return["span","offset","pull","push"].forEach(s=>{const n=e[s];b(n)&&(s==="span"?t.push(a.b(`${e[s]}`)):n>0&&t.push(a.b(`${s}-${e[s]}`)))}),["xs","sm","md","lg","xl"].forEach(s=>{b(e[s])?t.push(a.b(`${s}-${e[s]}`)):R(e[s])&&Object.entries(e[s]).forEach(([n,y])=>{t.push(n!=="span"?a.b(`${s}-${n}-${y}`):a.b(`${s}-${y}`))})}),o.value&&t.push(a.is("guttered")),[a.b(),t]});return(t,d)=>($(),v(E(t.tag),{class:x(c(m)),style:C(c(i))},{default:j(()=>[N(t.$slots,"default")]),_:3},8,["class","style"]))}});var D=h(B,[["__file","/home/runner/work/element-plus/element-plus/packages/components/col/src/col.vue"]]);const Q=w(D),L=["start","center","end","space-around","space-between","space-evenly"],A=["top","middle","bottom"],I=g({tag:{type:String,default:"div"},gutter:{type:Number,default:0},justify:{type:String,values:L,default:"start"},align:{type:String,values:A,default:"top"}}),J=p({name:"ElRow"}),T=p({...J,props:I,setup(f){const e=f,o=_("row"),a=l(()=>e.gutter);S(k,{gutter:a});const i=l(()=>{const t={};return e.gutter&&(t.marginRight=t.marginLeft=`-${e.gutter/2}px`),t}),m=l(()=>[o.b(),o.is(`justify-${e.justify}`,e.justify!=="start"),o.is(`align-${e.align}`,e.align!=="top")]);return(t,d)=>($(),v(E(t.tag),{class:x(c(m)),style:C(c(i))},{default:j(()=>[N(t.$slots,"default")]),_:3},8,["class","style"]))}});var U=h(T,[["__file","/home/runner/work/element-plus/element-plus/packages/components/row/src/row.vue"]]);const V=w(U);export{Q as E,V as a};
