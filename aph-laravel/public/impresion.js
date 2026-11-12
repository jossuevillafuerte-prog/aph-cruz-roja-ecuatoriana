(() => {
const button=document.getElementById('imprimir');if(!button)return;
const status=document.getElementById('print-status');
const retry=document.getElementById('retry-print-maps');
async function snapshot(box) {
 const image=box.querySelector('img'),message=box.querySelector('p');image.hidden=true;message.hidden=false;message.textContent='Preparando mapa…';
 const zoom=14,size=256,world=size*2**zoom,lat=Math.max(-85.05112878,Math.min(85.05112878,Number(box.dataset.lat))),lon=Number(box.dataset.lon);
 const sin=Math.sin(lat*Math.PI/180),cx=(lon+180)/360*world,cy=(.5-Math.log((1+sin)/(1-sin))/(4*Math.PI))*world;
 const width=900,height=360,left=cx-width/2,top=cy-height/2;
 const canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;const ctx=canvas.getContext('2d');
 let failed=0;const jobs=[];
 for(let x=Math.floor(left/size);x<=Math.floor((left+width)/size);x++)for(let y=Math.floor(top/size);y<=Math.floor((top+height)/size);y++) {
  const url=box.dataset.tiles.replace('{z}',zoom).replace('{x}',((x%2**zoom)+2**zoom)%2**zoom).replace('{y}',Math.max(0,Math.min(2**zoom-1,y)));
  jobs.push(new Promise(resolve=>{const tile=new Image();tile.crossOrigin='anonymous';let done=false;
   const finish=ok=>{if(done)return;done=true;clearTimeout(timer);if(ok)ctx.drawImage(tile,x*size-left,y*size-top,size,size);else failed++;resolve();};
   const timer=setTimeout(()=>finish(false),12000);tile.onload=()=>finish(true);tile.onerror=()=>finish(false);tile.src=url;
  }));
 }
 await Promise.all(jobs);
 if(failed){message.textContent='Mapa no disponible. Consulta las coordenadas impresas debajo o recarga los mapas antes de imprimir.';return false;}
 ctx.beginPath();ctx.arc(width/2,height/2,11,0,Math.PI*2);ctx.fillStyle='#d12e2a';ctx.fill();ctx.strokeStyle='white';ctx.lineWidth=4;ctx.stroke();
 ctx.fillStyle='white';ctx.fillRect(width/2+16,height/2-15,138,27);ctx.font='bold 13px Arial';ctx.fillStyle='#18253d';ctx.fillText('Ubicación registrada',width/2+22,height/2+3);
 ctx.fillStyle='rgba(255,255,255,.9)';ctx.fillRect(0,height-24,width,24);ctx.fillStyle='#333';ctx.font='12px Arial';ctx.fillText('© OpenStreetMap contributors | Referencia aproximada',10,height-8);
 try {image.src=canvas.toDataURL('image/png');await image.decode();image.hidden=false;message.hidden=true;return true;}catch{message.textContent='No se pudo preparar la imagen del mapa. Consulta las coordenadas.';return false;}
}
async function prepare(){button.disabled=true;retry.disabled=true;button.textContent='Preparando mapas…';const results=await Promise.all([...document.querySelectorAll('.report-static-map')].map(box=>snapshot(box).catch(()=>{box.querySelector('p').textContent='Mapa no disponible. Consulta las coordenadas.';return false;})));await document.fonts.ready;button.disabled=false;retry.disabled=false;button.textContent='Imprimir o guardar como PDF';status.textContent=results.includes(false)?'Hay mapas sin cargar. Puedes recargarlos o imprimir con las coordenadas y el aviso de mapa no disponible.':'Documento listo para imprimir. Los mapas se incluyen como imágenes.';}
button.addEventListener('click',()=>window.print());retry.addEventListener('click',prepare);prepare();
})();
