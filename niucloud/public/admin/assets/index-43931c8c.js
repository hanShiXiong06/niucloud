import{b as M,a as F,m as O,E as b,a6 as Q,a1 as R}from"./index-9ef6974e.js";import{C as W}from"./event-3e082a4a.js";import{u as j,_ as q,w as X,a as Y}from"./plugin-vue_export-helper-c018272e.js";import{d as C,r as E,w as P,E as Z,b as o,e as m,g as k,n,u as e,D as ee,o as se,J as te,c as l,M as ae,C as _,f as g,h as z,m as w,p as B,P as ne,q as x,x as I,v as T,K as ie}from"./runtime-core.esm-bundler-c17ab5bc.js";const re=M({space:{type:[Number,String],default:""},active:{type:Number,default:0},direction:{type:String,default:"horizontal",values:["horizontal","vertical"]},alignCenter:{type:Boolean},simple:{type:Boolean},finishStatus:{type:String,values:["wait","process","finish","error","success"],default:"finish"},processStatus:{type:String,values:["wait","process","finish","error","success"],default:"process"}}),le={[W]:(f,S)=>[f,S].every(F)},oe=C({name:"ElSteps"}),ce=C({...oe,props:re,emits:le,setup(f,{emit:S}){const t=f,r=j("steps"),p=E([]);return P(p,()=>{p.value.forEach((i,a)=>{i.setIndex(a)})}),Z("ElSteps",{props:t,steps:p}),P(()=>t.active,(i,a)=>{S(W,i,a)}),(i,a)=>(o(),m("div",{class:n([e(r).b(),e(r).m(i.simple?"simple":i.direction)])},[k(i.$slots,"default")],2))}});var ue=q(ce,[["__file","/home/runner/work/element-plus/element-plus/packages/components/steps/src/steps.vue"]]);const pe=M({title:{type:String,default:""},icon:{type:O},description:{type:String,default:""},status:{type:String,values:["","wait","process","finish","error","success"],default:""}}),ve=C({name:"ElStep"}),de=C({...ve,props:pe,setup(f){const S=f,t=j("step"),r=E(-1),p=E({}),i=E(""),a=ee("ElSteps"),v=ie();se(()=>{P([()=>a.props.active,()=>a.props.processStatus,()=>a.props.finishStatus],([s])=>{L(s)},{immediate:!0})}),te(()=>{a.steps.value=a.steps.value.filter(s=>s.uid!==(v==null?void 0:v.uid))});const d=l(()=>S.status||i.value),G=l(()=>{const s=a.steps.value[r.value-1];return s?s.currentStatus:"wait"}),$=l(()=>a.props.alignCenter),V=l(()=>a.props.direction==="vertical"),u=l(()=>a.props.simple),N=l(()=>a.steps.value.length),D=l(()=>{var s;return((s=a.steps.value[N.value-1])==null?void 0:s.uid)===(v==null?void 0:v.uid)}),y=l(()=>u.value?"":a.props.space),H=l(()=>{const s={flexBasis:typeof y.value=="number"?`${y.value}px`:y.value?y.value:`${100/(N.value-($.value?0:1))}%`};return V.value||D.value&&(s.maxWidth=`${100/N.value}%`),s}),J=s=>{r.value=s},K=s=>{let c=100;const h={};h.transitionDelay=`${150*r.value}ms`,s===a.props.processStatus?c=0:s==="wait"&&(c=0,h.transitionDelay=`${-150*r.value}ms`),h.borderWidth=c&&!u.value?"1px":0,h[a.props.direction==="vertical"?"height":"width"]=`${c}%`,p.value=h},L=s=>{s>r.value?i.value=a.props.finishStatus:s===r.value&&G.value!=="error"?i.value=a.props.processStatus:i.value="wait";const c=a.steps.value[r.value-1];c&&c.calcProgress(i.value)},U=ae({uid:l(()=>v==null?void 0:v.uid),currentStatus:d,setIndex:J,calcProgress:K});return a.steps.value=[...a.steps.value,U],(s,c)=>(o(),m("div",{style:z(e(H)),class:n([e(t).b(),e(t).is(e(u)?"simple":e(a).props.direction),e(t).is("flex",e(D)&&!e(y)&&!e($)),e(t).is("center",e($)&&!e(V)&&!e(u))])},[_(" icon & line "),g("div",{class:n([e(t).e("head"),e(t).is(e(d))])},[e(u)?_("v-if",!0):(o(),m("div",{key:0,class:n(e(t).e("line"))},[g("i",{class:n(e(t).e("line-inner")),style:z(p.value)},null,6)],2)),g("div",{class:n([e(t).e("icon"),e(t).is(s.icon||s.$slots.icon?"icon":"text")])},[k(s.$slots,"icon",{},()=>[s.icon?(o(),w(e(b),{key:0,class:n(e(t).e("icon-inner"))},{default:B(()=>[(o(),w(ne(s.icon)))]),_:1},8,["class"])):e(d)==="success"?(o(),w(e(b),{key:1,class:n([e(t).e("icon-inner"),e(t).is("status")])},{default:B(()=>[x(e(Q))]),_:1},8,["class"])):e(d)==="error"?(o(),w(e(b),{key:2,class:n([e(t).e("icon-inner"),e(t).is("status")])},{default:B(()=>[x(e(R))]),_:1},8,["class"])):e(u)?_("v-if",!0):(o(),m("div",{key:3,class:n(e(t).e("icon-inner"))},I(r.value+1),3))])],2)],2),_(" title & description "),g("div",{class:n(e(t).e("main"))},[g("div",{class:n([e(t).e("title"),e(t).is(e(d))])},[k(s.$slots,"title",{},()=>[T(I(s.title),1)])],2),e(u)?(o(),m("div",{key:0,class:n(e(t).e("arrow"))},null,2)):(o(),m("div",{key:1,class:n([e(t).e("description"),e(t).is(e(d))])},[k(s.$slots,"description",{},()=>[T(I(s.description),1)])],2))],2)],6))}});var A=q(de,[["__file","/home/runner/work/element-plus/element-plus/packages/components/steps/src/item.vue"]]);const he=X(ue,{Step:A}),ge=Y(A);export{he as E,ge as a};
