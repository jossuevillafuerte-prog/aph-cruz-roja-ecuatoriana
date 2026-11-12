(() => {
 const token=document.querySelector('meta[name="csrf-token"]')?.content;if(!token)return;
 let idle=Date.now()+600000,absolute=Date.now()+28800000,lastSent=0,busy=false;
 const channel='BroadcastChannel' in window?new BroadcastChannel('aph-session'):null;
 const dialog=document.createElement('dialog');dialog.innerHTML='<h2>Tu sesión está por vencer</h2><p>Continúa si todavía estás trabajando. Al vencer el tiempo, se ocultarán los datos. Los cambios sin guardar podrían perderse.</p><p class="countdown"></p><button type="button">Continuar trabajando</button>';document.body.append(dialog);
 function lock(){document.body.replaceChildren();location.replace('/login');}
 channel?.addEventListener('message',e=>{if(e.data==='logout')lock();else if(e.data?.idle)idle=e.data.idle;});
 async function sync(activity=false){
  if(busy)return;busy=true;
  try {const response=await fetch(activity?'/session/activity':'/session/status',{method:activity?'POST':'GET',headers:{Accept:'application/json','X-CSRF-TOKEN':token},cache:'no-store'});
   if(response.status===401||response.status===419){channel?.postMessage('logout');lock();return;}
   if(!response.ok)return;
   document.documentElement.style.visibility='';
   const data=await response.json();idle=Date.now()+(data.idle_until-data.server_time)*1000;absolute=Date.now()+(data.absolute_until-data.server_time)*1000;
   if(activity){lastSent=Date.now();channel?.postMessage({idle});if(dialog.open)dialog.close();}
  }catch{}finally{busy=false;}
 }
 function activity(e){if(!e.isTrusted||document.hidden||dialog.open)return;if(Date.now()-lastSent>30000)sync(true);}
 ['keydown','pointerdown','input','wheel'].forEach(event=>document.addEventListener(event,activity,{passive:true}));
 dialog.querySelector('button').onclick=()=>sync(true);
 document.addEventListener('submit',e=>{if(e.target.action?.endsWith('/logout')||e.target.action?.endsWith('/security/revoke'))channel?.postMessage('logout');});
 window.addEventListener('pagehide',()=>{document.documentElement.style.visibility='hidden';});
 window.addEventListener('pageshow',()=>sync());document.addEventListener('visibilitychange',()=>{if(!document.hidden)sync();});
 setInterval(()=>{const remaining=Math.min(idle,absolute)-Date.now();if(remaining<=0){channel?.postMessage('logout');lock();return;}if(remaining<=120000){if(!dialog.open)dialog.showModal();dialog.querySelector('.countdown').textContent=`Tiempo restante: ${Math.ceil(remaining/1000)} segundos.`;}},1000);
 setInterval(()=>sync(),30000);sync();
})();
