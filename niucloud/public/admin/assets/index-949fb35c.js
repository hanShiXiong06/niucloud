import{ct as Y,co as Z,cu as x,cv as ee,b as V,g as k,h as D,j as c,m as r,n as u,q as v,t as d,u as e,D as b,v as l,am as W,T as f,L as E,_ as z,y as q,e as se,r as C,w as T,V as te,ak as ne,U as ae,o as le,$ as ie,a4 as oe,x as j,E as B,G as P,F as G,a9 as re,bt as ce,K as L,a0 as ue,a7 as pe}from"./base-d2ce4248.js";import{C as A}from"./event-f83e96f5.js";const _={success:"icon-success",warning:"icon-warning",error:"icon-error",info:"icon-info"},U={[_.success]:Y,[_.warning]:Z,[_.error]:x,[_.info]:ee},ve=V({title:{type:String,default:""},subTitle:{type:String,default:""},icon:{type:String,values:["success","warning","info","error"],default:"info"}}),de=k({name:"ElResult"}),fe=k({...de,props:ve,setup(m){const y=m,s=D("result"),o=c(()=>{const a=y.icon,i=a&&_[a]?_[a]:"icon-info",n=U[i]||U["icon-info"];return{class:i,component:n}});return(a,i)=>(r(),u("div",{class:l(e(s).b())},[v("div",{class:l(e(s).e("icon"))},[d(a.$slots,"icon",{},()=>[e(o).component?(r(),b(W(e(o).component),{key:0,class:l(e(o).class)},null,8,["class"])):f("v-if",!0)])],2),a.title||a.$slots.title?(r(),u("div",{key:0,class:l(e(s).e("title"))},[d(a.$slots,"title",{},()=>[v("p",null,E(a.title),1)])],2)):f("v-if",!0),a.subTitle||a.$slots["sub-title"]?(r(),u("div",{key:1,class:l(e(s).e("subtitle"))},[d(a.$slots,"sub-title",{},()=>[v("p",null,E(a.subTitle),1)])],2)):f("v-if",!0),a.$slots.extra?(r(),u("div",{key:2,class:l(e(s).e("extra"))},[d(a.$slots,"extra")],2)):f("v-if",!0)],2))}});var me=z(fe,[["__file","/home/runner/work/element-plus/element-plus/packages/components/result/src/result.vue"]]);const Ce=q(me),ye=V({space:{type:[Number,String],default:""},active:{type:Number,default:0},direction:{type:String,default:"horizontal",values:["horizontal","vertical"]},alignCenter:{type:Boolean},simple:{type:Boolean},finishStatus:{type:String,values:["wait","process","finish","error","success"],default:"finish"},processStatus:{type:String,values:["wait","process","finish","error","success"],default:"process"}}),Se={[A]:(m,y)=>[m,y].every(se)},he=k({name:"ElSteps"}),ge=k({...he,props:ye,emits:Se,setup(m,{emit:y}){const s=m,o=D("steps"),a=C([]);return T(a,()=>{a.value.forEach((i,n)=>{i.setIndex(n)})}),te("ElSteps",{props:s,steps:a}),T(()=>s.active,(i,n)=>{y(A,i,n)}),(i,n)=>(r(),u("div",{class:l([e(o).b(),e(o).m(i.simple?"simple":i.direction)])},[d(i.$slots,"default")],2))}});var _e=z(ge,[["__file","/home/runner/work/element-plus/element-plus/packages/components/steps/src/steps.vue"]]);const ke=V({title:{type:String,default:""},icon:{type:ne},description:{type:String,default:""},status:{type:String,values:["","wait","process","finish","error","success"],default:""}}),$e=k({name:"ElStep"}),we=k({...$e,props:ke,setup(m){const y=m,s=D("step"),o=C(-1),a=C({}),i=C(""),n=ae("ElSteps"),h=ue();le(()=>{T([()=>n.props.active,()=>n.props.processStatus,()=>n.props.finishStatus],([t])=>{Q(t)},{immediate:!0})}),ie(()=>{n.steps.value=n.steps.value.filter(t=>t.uid!==(h==null?void 0:h.uid))});const g=c(()=>y.status||i.value),H=c(()=>{const t=n.steps.value[o.value-1];return t?t.currentStatus:"wait"}),N=c(()=>n.props.alignCenter),M=c(()=>n.props.direction==="vertical"),S=c(()=>n.props.simple),I=c(()=>n.steps.value.length),R=c(()=>{var t;return((t=n.steps.value[I.value-1])==null?void 0:t.uid)===(h==null?void 0:h.uid)}),$=c(()=>S.value?"":n.props.space),K=c(()=>{const t={flexBasis:typeof $.value=="number"?`${$.value}px`:$.value?$.value:`${100/(I.value-(N.value?0:1))}%`};return M.value||R.value&&(t.maxWidth=`${100/I.value}%`),t}),J=t=>{o.value=t},O=t=>{let p=100;const w={};w.transitionDelay=`${150*o.value}ms`,t===n.props.processStatus?p=0:t==="wait"&&(p=0,w.transitionDelay=`${-150*o.value}ms`),w.borderWidth=p&&!S.value?"1px":0,w[n.props.direction==="vertical"?"height":"width"]=`${p}%`,a.value=w},Q=t=>{t>o.value?i.value=n.props.finishStatus:t===o.value&&H.value!=="error"?i.value=n.props.processStatus:i.value="wait";const p=n.steps.value[o.value-1];p&&p.calcProgress(i.value)},X=oe({uid:c(()=>h==null?void 0:h.uid),currentStatus:g,setIndex:J,calcProgress:O});return n.steps.value=[...n.steps.value,X],(t,p)=>(r(),u("div",{style:j(e(K)),class:l([e(s).b(),e(s).is(e(S)?"simple":e(n).props.direction),e(s).is("flex",e(R)&&!e($)&&!e(N)),e(s).is("center",e(N)&&!e(M)&&!e(S))])},[f(" icon & line "),v("div",{class:l([e(s).e("head"),e(s).is(e(g))])},[e(S)?f("v-if",!0):(r(),u("div",{key:0,class:l(e(s).e("line"))},[v("i",{class:l(e(s).e("line-inner")),style:j(a.value)},null,6)],2)),v("div",{class:l([e(s).e("icon"),e(s).is(t.icon||t.$slots.icon?"icon":"text")])},[d(t.$slots,"icon",{},()=>[t.icon?(r(),b(e(P),{key:0,class:l(e(s).e("icon-inner"))},{default:B(()=>[(r(),b(W(t.icon)))]),_:1},8,["class"])):e(g)==="success"?(r(),b(e(P),{key:1,class:l([e(s).e("icon-inner"),e(s).is("status")])},{default:B(()=>[G(e(re))]),_:1},8,["class"])):e(g)==="error"?(r(),b(e(P),{key:2,class:l([e(s).e("icon-inner"),e(s).is("status")])},{default:B(()=>[G(e(ce))]),_:1},8,["class"])):e(S)?f("v-if",!0):(r(),u("div",{key:3,class:l(e(s).e("icon-inner"))},E(o.value+1),3))])],2)],2),f(" title & description "),v("div",{class:l(e(s).e("main"))},[v("div",{class:l([e(s).e("title"),e(s).is(e(g))])},[d(t.$slots,"title",{},()=>[L(E(t.title),1)])],2),e(S)?(r(),u("div",{key:0,class:l(e(s).e("arrow"))},null,2)):(r(),u("div",{key:1,class:l([e(s).e("description"),e(s).is(e(g))])},[d(t.$slots,"description",{},()=>[L(E(t.description),1)])],2))],2)],6))}});var F=z(we,[["__file","/home/runner/work/element-plus/element-plus/packages/components/steps/src/item.vue"]]);const Ne=q(_e,{Step:F}),Ie=pe(F);export{Ce as E,Ne as a,Ie as b};
