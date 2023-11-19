import{a as n}from"./vue-router-8b032575.js";import{d,b as i,e as c,f as s}from"./runtime-core.esm-bundler-67034826.js";const a="/admin/assets/addon_develop-a78473b3.png",_="/admin/assets/code-cea24823.png",m="/admin/assets/sys_dict_list-da03b204.png",r="/admin/assets/tools_Update_cache-f2bdda40.png",f="/admin/assets/tools_check_environment-b1730280.png",h="/admin/assets/tools_schedule-bfdc05aa.png",u="/admin/assets/auth_menu-05fb8852.png",v="/admin/assets/app_auth-d99146b6.png",w="/admin/assets/official_market-919bcfb1.png",b={class:"box-border px-[64px] pt-[64px]"},g=s("div",{class:"font-600 text-[22px] text-[#222] mb-[32px] pl-[14px]"},"工具管理",-1),k={class:"flex flex-wrap mt-[28px]"},y=s("div",{class:"flex-1 py-[19px] px-[24px] flex flex-col"},[s("span",{class:"text-[16px] text-[#222] font-bold"},"插件开发"),s("p",{class:"text-[13px] text-[#666] mt-[8px] multi-hidden"},"点击新建插件，生成插件后系统会生成对应插")],-1),C=s("img",{src:a,class:"w-[256px] h-[148px]"},null,-1),$=[y,C],B=s("div",{class:"flex-1 py-[19px] px-[24px] flex flex-col"},[s("span",{class:"text-[16px] text-[#222] font-bold"},"代码生成"),s("p",{class:"text-[13px] text-[#666] mt-[8px] multi-hidden"},"代码生成")],-1),R=s("img",{src:_,class:"w-[256px] h-[148px]"},null,-1),z=[B,R],E=s("div",{class:"flex-1 py-[19px] px-[24px] flex flex-col"},[s("span",{class:"text-[16px] text-[#222] font-bold"},"数据字典"),s("p",{class:"text-[13px] text-[#666] mt-[8px] multi-hidden"},"数据字典")],-1),L=s("img",{src:m,class:"w-[256px] h-[148px]"},null,-1),N=[E,L],U=s("div",{class:"flex-1 py-[19px] px-[24px] flex flex-col"},[s("span",{class:"text-[16px] text-[#222] font-bold"},"更新缓存"),s("p",{class:"text-[13px] text-[#666] mt-[8px] multi-hidden"},"更新缓存")],-1),V=s("img",{src:r,class:"w-[256px] h-[148px]"},null,-1),j=[U,V],q=s("div",{class:"flex-1 py-[19px] px-[24px] flex flex-col"},[s("span",{class:"text-[16px] text-[#222] font-bold"},"环境监测"),s("p",{class:"text-[13px] text-[#666] mt-[8px] multi-hidden"},"环境监测")],-1),A=s("img",{src:f,class:"w-[256px] h-[148px] cursor-pointer"},null,-1),D=[q,A],F=s("div",{class:"flex-1 py-[19px] px-[24px] flex flex-col"},[s("span",{class:"text-[16px] text-[#222] font-bold"},"计划任务"),s("p",{class:"text-[13px] text-[#666] mt-[8px] multi-hidden"},"计划任务")],-1),G=s("img",{src:h,class:"w-[256px] h-[148px]"},null,-1),H=[F,G],I=s("div",{class:"flex-1 py-[19px] px-[24px] flex flex-col"},[s("span",{class:"text-[16px] text-[#222] font-bold"},"菜单管理"),s("p",{class:"text-[13px] text-[#666] mt-[8px] multi-hidden"},"菜单管理")],-1),J=s("img",{src:u,class:"w-[256px] h-[148px]"},null,-1),K=[I,J],M=s("div",{class:"flex-1 py-[19px] px-[24px] flex flex-col"},[s("span",{class:"text-[16px] text-[#222] font-bold"},"授权信息"),s("p",{class:"text-[13px] text-[#666] mt-[8px] multi-hidden"},"查看授权信息及重新认证授权")],-1),O=s("img",{src:v,class:"w-[256px] h-[148px]"},null,-1),P=[M,O],Q=s("div",{class:"flex-1 py-[19px] px-[24px] flex flex-col"},[s("span",{class:"text-[16px] text-[#222] font-bold"},"官方市场"),s("p",{class:"text-[13px] text-[#666] mt-[8px] multi-hidden"},"官方市场")],-1),S=s("img",{src:w,class:"w-[256px] h-[148px]"},null,-1),T=[Q,S],Z=d({__name:"index",setup(W){const p=n(),o=e=>{p.push(e)},l=()=>{window.open("https://www.niucloud.com/app")};return(e,t)=>(i(),c("div",b,[g,s("div",k,[s("div",{class:"w-[256px] h-[260px] tools-item-shadow mb-[24px] mx-[14px] rounded-[8px] flex flex-col cursor-pointer",onClick:t[0]||(t[0]=x=>o("/tools/addon"))},$),s("div",{class:"w-[256px] h-[260px] tools-item-shadow mb-[24px] mx-[14px] rounded-[8px] flex flex-col cursor-pointer",onClick:t[1]||(t[1]=x=>o("/tools/code"))},z),s("div",{class:"w-[256px] h-[260px] tools-item-shadow mb-[24px] mx-[14px] rounded-[8px] flex flex-col cursor-pointer",onClick:t[2]||(t[2]=x=>o("/tools/list"))},N),s("div",{class:"w-[256px] h-[260px] tools-item-shadow mb-[24px] mx-[14px] rounded-[8px] flex flex-col cursor-pointer",onClick:t[3]||(t[3]=x=>o("/tools/update"))},j),s("div",{class:"w-[256px] h-[260px] tools-item-shadow mb-[24px] mx-[14px] rounded-[8px] flex flex-col cursor-pointer",onClick:t[4]||(t[4]=x=>o("/tools/detection"))},D),s("div",{class:"w-[256px] h-[260px] tools-item-shadow mb-[24px] mx-[14px] rounded-[8px] flex flex-col cursor-pointer",onClick:t[5]||(t[5]=x=>o("/tools/schedule"))},H),s("div",{class:"w-[256px] h-[260px] tools-item-shadow mb-[24px] mx-[14px] rounded-[8px] flex flex-col cursor-pointer",onClick:t[6]||(t[6]=x=>o("/tools/menu"))},K),s("div",{class:"w-[256px] h-[260px] tools-item-shadow mb-[24px] mx-[14px] rounded-[8px] flex flex-col cursor-pointer",onClick:t[7]||(t[7]=x=>o("/tools/authorize"))},P),s("div",{class:"w-[256px] h-[260px] tools-item-shadow mb-[24px] mx-[14px] rounded-[8px] flex flex-col cursor-pointer",onClick:l},T)])]))}});export{Z as default};
