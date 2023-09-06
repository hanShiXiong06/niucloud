import{aM as y,aG as _,f as N,i as q,x,M as I,O as M,aN as C,aO as j,aP as D,aQ as E,aR as L,e as V,aS as z,aT as H,__tla as $}from"./entry.9b92f05f.js";let P,F=Promise.all([(()=>{try{return $}catch{}})()]).then(async()=>{const m=globalThis.requestIdleCallback||(t=>{const a=Date.now(),i={didTimeout:!1,timeRemaining:()=>Math.max(0,50-(Date.now()-a))};return setTimeout(()=>{t(i)},1)}),S=globalThis.cancelIdleCallback||(t=>{clearTimeout(t)}),k=t=>{const a=y();a.isHydrating?a.hooks.hookOnce("app:suspense:resolve",()=>{m(t)}):m(t)};async function b(t,a=_()){const{path:i,matched:e}=a.resolve(t);if(!e.length||(a._routePreloaded||(a._routePreloaded=new Set),a._routePreloaded.has(i)))return;const l=a._preloadPromises=a._preloadPromises||[];if(l.length>4)return Promise.all(l).then(()=>b(t,a));a._routePreloaded.add(i);const u=e.map(r=>{var o;return(o=r.components)==null?void 0:o.default}).filter(r=>typeof r=="function");for(const r of u){const o=Promise.resolve(r()).catch(()=>{}).finally(()=>l.splice(l.indexOf(o)));l.push(o)}await Promise.all(l)}const R=(...t)=>t.find(a=>a!==void 0),w="noopener noreferrer";function A(t){const a=t.componentName||"NuxtLink",i=(e,l)=>{if(!e||t.trailingSlash!=="append"&&t.trailingSlash!=="remove")return e;const u=t.trailingSlash==="append"?z:H;if(typeof e=="string")return u(e,!0);const r="path"in e?e.path:l(e).path;return{...e,name:void 0,path:u(r,!0)}};return N({name:a,props:{to:{type:[String,Object],default:void 0,required:!1},href:{type:[String,Object],default:void 0,required:!1},target:{type:String,default:void 0,required:!1},rel:{type:String,default:void 0,required:!1},noRel:{type:Boolean,default:void 0,required:!1},prefetch:{type:Boolean,default:void 0,required:!1},noPrefetch:{type:Boolean,default:void 0,required:!1},activeClass:{type:String,default:void 0,required:!1},exactActiveClass:{type:String,default:void 0,required:!1},prefetchedClass:{type:String,default:void 0,required:!1},replace:{type:Boolean,default:void 0,required:!1},ariaCurrentValue:{type:String,default:void 0,required:!1},external:{type:Boolean,default:void 0,required:!1},custom:{type:Boolean,default:void 0,required:!1}},setup(e,{slots:l}){const u=_(),r=q(()=>{const n=e.to||e.href||"";return i(n,u.resolve)}),o=q(()=>e.external||e.target&&e.target!=="_self"?!0:typeof r.value=="object"?!1:r.value===""||L(r.value,{acceptRelative:!0})),f=x(!1),v=x(null),B=n=>{var c;v.value=e.custom?(c=n==null?void 0:n.$el)==null?void 0:c.nextElementSibling:n==null?void 0:n.$el};if(e.prefetch!==!1&&e.noPrefetch!==!0&&e.target!=="_blank"&&!T()){const n=y();let c,s=null;I(()=>{const g=O();k(()=>{c=m(()=>{var p;(p=v==null?void 0:v.value)!=null&&p.tagName&&(s=g.observe(v.value,async()=>{s==null||s(),s=null;const h=typeof r.value=="string"?r.value:u.resolve(r.value).fullPath;await Promise.all([n.hooks.callHook("link:prefetch",h).catch(()=>{}),!o.value&&b(r.value,u).catch(()=>{})]),f.value=!0}))})})}),M(()=>{c&&S(c),s==null||s(),s=null})}return()=>{var p,h;if(!o.value){const d={ref:B,to:r.value,activeClass:e.activeClass||t.activeClass,exactActiveClass:e.exactActiveClass||t.exactActiveClass,replace:e.replace,ariaCurrentValue:e.ariaCurrentValue,custom:e.custom};return e.custom||(f.value&&(d.class=e.prefetchedClass||t.prefetchedClass),d.rel=e.rel),C(j("RouterLink"),d,l.default)}const n=typeof r.value=="object"?((p=u.resolve(r.value))==null?void 0:p.href)??null:r.value||null,c=e.target||null,s=e.noRel?null:R(e.rel,t.externalRelAttribute,n?w:"")||null,g=()=>V(n,{replace:e.replace});return e.custom?l.default?l.default({href:n,navigate:g,get route(){if(!n)return;const d=D(n);return{path:d.pathname,fullPath:d.pathname,get query(){return E(d.search)},hash:d.hash,params:{},name:void 0,matched:[],redirectedFrom:void 0,meta:{},href:n}},rel:s,target:c,isExternal:o.value,isActive:!1,isExactActive:!1}):null:C("a",{ref:v,href:n,rel:s,target:c},(h=l.default)==null?void 0:h.call(l))}}})}P=A({componentName:"NuxtLink"});function O(){const t=y();if(t._observer)return t._observer;let a=null;const i=new Map,e=(l,u)=>(a||(a=new IntersectionObserver(r=>{for(const o of r){const f=i.get(o.target);(o.isIntersecting||o.intersectionRatio>0)&&f&&f()}})),i.set(l,u),a.observe(l),()=>{i.delete(l),a.unobserve(l),i.size===0&&(a.disconnect(),a=null)});return t._observer={observe:e}}function T(){const t=navigator.connection;return!!(t&&(t.saveData||/2g/.test(t.effectiveType)))}});export{P as _,F as __tla};
