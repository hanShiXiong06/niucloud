import{d as u,t as r}from"./index-2d1c7ba3.js";import{c as o}from"./cloneDeep-195867dd.js";import{E as a}from"./index-a31d0a55.js";import{a as i}from"./index-e9d9b1a1.js";import{af as s}from"./runtime-core.esm-bundler-67034826.js";const h=u("diy",{state:()=>({id:0,load:!1,currentIndex:-99,currentComponent:"edit-page",pageMode:"diy",editTab:"content",name:"",type:"",typeName:"",templateName:"",isDefault:0,predefineColors:["#F4391c","#ff4500","#ff8c00","#FFD009","#ffd700","#19C650","#90ee90","#00ced1","#1e90ff","#c71585","#FF407E","#CFAF70","#A253FF","rgba(255, 69, 0, 0.68)","rgb(255, 120, 0)","hsl(181, 100%, 37%)","hsla(209, 100%, 56%, 0.73)","#c7158577"],components:[],global:{title:"页面",pageBgColor:"",bgUrl:"",imgWidth:"",imgHeight:"",topStatusBar:{bgColor:"#ffffff",isTransparent:!1,isShow:!0,style:"style-1",textColor:"#333333",textAlign:"center"},bottomTabBarSwitch:!0,popWindow:{imgUrl:"",imgWidth:"",imgHeight:"",count:-1,show:0,link:{name:""}},template:{textColor:"#303133",pageBgColor:"",componentBgColor:"",topRounded:0,bottomRounded:0,elementBgColor:"",topElementRounded:0,bottomElementRounded:0,margin:{top:0,bottom:0,both:0}}},value:[]}),getters:{editComponent:e=>e.currentIndex==-99?e.global:e.value[e.currentIndex]},actions:{init(){this.global={title:"页面",pageBgColor:"",bgUrl:"",imgWidth:"",imgHeight:"",topStatusBar:{bgColor:"#ffffff",isTransparent:!1,isShow:!0,style:"style-1",textColor:"#333333",textAlign:"center"},bottomTabBarSwitch:!0,popWindow:{imgUrl:"",imgWidth:"",imgHeight:"",count:-1,show:0,link:{name:""}},template:{textColor:"#303133",pageBgColor:"",componentBgColor:"",topRounded:0,bottomRounded:0,elementBgColor:"",topElementRounded:0,bottomElementRounded:0,margin:{top:0,bottom:0,both:0}}},this.value=[]},addComponent(e,n){if(!this.load)return;let t=o(n);t.id=this.generateRandom(),t.componentName=e,t.componentTitle=t.title,t.ignore=[],Object.assign(t,t.value),delete t.title,delete t.value,delete t.type,delete t.icon;let l=o(this.global.template);Object.assign(t,l),this.checkComponentIsAdd(t)&&(this.currentIndex===-99?(this.value.push(t),this.currentIndex=this.value.length-1):this.value.splice(++this.currentIndex,0,t),this.currentComponent=t.path)},generateRandom(e=5){return Number(Math.random().toString().substr(3,e)+Date.now()).toString(36)},postMessage(){var e=JSON.stringify({pageMode:this.pageMode,currentIndex:this.currentIndex,global:s(this.global),value:s(this.value)});window.previewIframe.contentWindow.postMessage(e,"*")},changeCurrentIndex(e,n=null){this.currentIndex=e,this.currentIndex==-99?this.currentComponent="edit-page":n&&(this.currentComponent=n.path)},delComponent(){this.currentIndex!=-99&&a.confirm(r("delComponentTips"),r("warning"),{confirmButtonText:r("confirm"),cancelButtonText:r("cancel"),type:"warning",autofocus:!1}).then(()=>{this.value.splice(this.currentIndex,1),this.value.length===0&&(this.currentIndex=-99),this.currentIndex===this.value.length&&this.currentIndex--;let e=o(this.value[this.currentIndex]);this.changeCurrentIndex(this.currentIndex,e)}).catch(()=>{})},moveUpComponent(){if(this.currentIndex-1<0)return;var e=o(this.value[this.currentIndex]);e.id=this.generateRandom();let n=this.currentIndex-1;var t=o(this.value[n]);t.id=this.generateRandom(),this.value[this.currentIndex]=t,this.value[n]=e,this.changeCurrentIndex(n,e)},moveDownComponent(){if(!(this.currentIndex+1>=this.value.length)){var e=this.currentIndex+1,n=o(this.value[this.currentIndex]);n.id=this.generateRandom();var t=o(this.value[e]);t.id=this.generateRandom(),this.value[this.currentIndex]=t,this.value[e]=n,this.changeCurrentIndex(e,n)}},copyComponent(){if(this.currentIndex<0)return;let e=o(this.value[this.currentIndex]);if(e.id=this.generateRandom(),!this.checkComponentIsAdd(e)){i({type:"warning",message:`${r("notCopy")}，${e.componentTitle}${r("componentCanOnlyAdd")}${e.uses}${r("piece")}`});return}var n=this.currentIndex+1;this.value.splice(n,0,e),this.changeCurrentIndex(n,e)},checkComponentIsAdd(e){if(e.uses===0)return!0;var n=0;for(var t in this.value)this.value[t].componentName===e.componentName&&n++;return!(n>=e.uses)},resetComponent(){this.currentIndex<0||a.confirm(r("resetComponentTips"),r("warning"),{confirmButtonText:r("confirm"),cancelButtonText:r("cancel"),type:"warning",autofocus:!1}).then(()=>{for(let e=0;e<this.components.length;e++)if(this.components[e].componentName==this.editComponent.componentName){Object.assign(this.editComponent,this.components[e]);break}}).catch(()=>{})},verify(){if(this.global.title==="")return i({message:r("pageNamePlaceholder"),type:"warning"}),this.changeCurrentIndex(-99),!1;for(var e=0;e<this.value.length;e++)try{if(this.value[e].verify){var n=this.value[e].verify(e);if(!n.code)return this.changeCurrentIndex(e,this.value[e]),i({message:n.message,type:"warning"}),!1}}catch(t){console.log("verify Error:",t,e,this.value[e])}return!0}}}),f=h;export{f as u};
