(() => {
'use strict';
const source = document.getElementById('dashboard-data');
if (!source) return;
const records = JSON.parse(source.textContent);
const filters = new Map();
const sheetSelect = document.getElementById('sheet-select');
const sheetSearch = document.getElementById('sheet-filter');
const sheets = [...new Set(records.map(r=>String(r.K||'')).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'es',{numeric:true}));
function updateSheets() {
 const previous=sheetSelect.value, query=sheetSearch.value.trim().toLocaleLowerCase();
 sheetSelect.replaceChildren(new Option('Todas las hojas coincidentes',''));
 for(const sheet of sheets.filter(s=>s.toLocaleLowerCase().includes(query))) sheetSelect.add(new Option(sheet,sheet));
 if([...sheetSelect.options].some(o=>o.value===previous)) sheetSelect.value=previous;
}
updateSheets();
let selectedIds = null;
const colors = ['#d12e2a','#186b8c','#df9a18','#7255a5','#27836d','#b14a79','#64748b','#9c6929'];
const labels = {provincia:'Provincia',F:'Evento o causa',M:'Tipo de identificación',O:'Sexo',Q:'Nacionalidad',R:'Condición de salud',S:'Discapacidad',U:'Códigos CIE-10',J:'Despachos por recurso',tipo:'Despachos por tipo de recurso',G:'Cantón',D:'Tipo de atención',mes:'Mes',B:'Fecha',edad:'Rango etario'};
const bands = [[4,'0–4'],[14,'5–14'],[24,'15–24'],[34,'25–34'],[44,'35–44'],[54,'45–54'],[64,'55–64'],[120,'65+']];
const value = (r,key) => {
 if (key==='mes') return r.B?.slice(0,7) || 'Sin registrar';
 if (key==='edad') return /^\d+$/.test(String(r.P)) ? bands.find(([max])=>Number(r.P)<=max)?.[1] || 'Sin registrar' : 'Sin registrar';
 return String(r[key] ?? '') || 'Sin registrar';
};
const el = (tag,text,cls) => { const n=document.createElement(tag); if(text!==undefined)n.textContent=text; if(cls)n.className=cls; return n; };
const fmt = n => n.toLocaleString('es-EC',{maximumFractionDigits:1});
const svgEl = (tag,attrs={}) => {const n=document.createElementNS('http://www.w3.org/2000/svg',tag); for(const [k,v] of Object.entries(attrs))n.setAttribute(k,v); return n;};
function toggle(key,label) { filters.get(key)===label ? filters.delete(key) : filters.set(key,label); render(); }
function interactive(node, description, callback) {
 node.setAttribute('tabindex','0'); node.setAttribute('role','button'); node.setAttribute('aria-label',description);
 node.addEventListener('click',callback); node.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();callback();}});
}
function counts(rows,key) {const out=new Map(); for(const r of rows){if(['J','tipo'].includes(key)&&!r.J)continue; const v=value(r,key);out.set(v,(out.get(v)||0)+1);}return [...out].sort((a,b)=>b[1]-a[1]||a[0].localeCompare(b[0]));}
function card(title) {const c=el('section',undefined,'card interactive-chart'); c.append(el('h2',title)); document.getElementById('dashboard-charts').append(c); return c;}
function legend(c,entries,key) {
 const list=el('div',undefined,'chart-legend'); const total=entries.reduce((sum,e)=>sum+e[1],0);
 entries.forEach(([name,n],i)=>{const b=el('button',undefined,'chart-choice');b.type='button';b.setAttribute('aria-pressed',String(filters.get(key)===name));
 const sw=el('span',undefined,'chart-swatch');sw.style.background=colors[i%colors.length];
 b.append(sw,el('span',name),el('strong',`${n} · ${fmt(n/total*100)}%`)); b.onclick=()=>toggle(key,name);list.append(b);}); c.append(list);
}
function pie(rows,key,donut=false) {
 const c=card(labels[key]), entries=counts(rows,key), total=entries.reduce((s,e)=>s+e[1],0);
 if(!total){c.append(el('p','Sin datos para estos filtros.'));return;}
 const svg=svgEl('svg',{viewBox:'0 0 240 240',class:'pie-chart','aria-label':labels[key]}); let angle=-Math.PI/2;
 entries.forEach(([name,n],i)=>{const next=angle+n/total*Math.PI*2;
 const shape=n===total ? svgEl('circle',{cx:120,cy:120,r:100}) : svgEl('path',{d:`M120 120 L${120+100*Math.cos(angle)} ${120+100*Math.sin(angle)} A100 100 0 ${next-angle>Math.PI?1:0} 1 ${120+100*Math.cos(next)} ${120+100*Math.sin(next)} Z`});
 shape.setAttribute('fill',colors[i%colors.length]);shape.setAttribute('stroke','#fff');shape.setAttribute('stroke-width','2');
 shape.append(svgEl('title'));shape.firstChild.textContent=`${name}: ${n} (${fmt(n/total*100)}%)`;
 interactive(shape,`${name}: ${n}. Filtrar`,()=>toggle(key,name));svg.append(shape);angle=next;});
 if(donut) {svg.append(svgEl('circle',{cx:120,cy:120,r:58,fill:'white','pointer-events':'none'}));const t=svgEl('text',{x:120,y:127,'text-anchor':'middle',fill:'#333'});t.textContent=total;svg.append(t);}
 c.append(svg);legend(c,entries,key);
}
function bars(rows,key) {
 const c=card(labels[key]), entries=counts(rows,key), max=Math.max(1,...entries.map(e=>e[1]));
 if(!entries.length)c.append(el('p','Sin datos para estos filtros.'));
 const list=el('div',undefined,'interactive-bars');
 entries.forEach(([name,n])=>{const b=el('button',undefined,'chart-bar');b.type='button';b.setAttribute('aria-pressed',String(filters.get(key)===name));b.append(el('span',name),el('strong',n));const track=el('span',undefined,'stat-track'), fill=el('span');fill.style.width=`${100*n/max}%`;track.append(fill);b.append(track);b.onclick=()=>toggle(key,name);list.append(b);}); c.append(list);
}
function line(entries,key,title,unit='') {
 const c=card(title);entries.sort((a,b)=>a[0].localeCompare(b[0]));
 if(!entries.length){c.append(el('p','Sin datos para estos filtros.'));return;}
 const svg=svgEl('svg',{viewBox:'0 0 600 220',class:'line-chart','aria-label':title});const max=Math.max(1,...entries.map(e=>e[1]));
 const coords=entries.map((e,i)=>[40+(entries.length===1?250:i*520/(entries.length-1)),175-e[1]*140/max]);
 const axis=svgEl('path',{d:'M40 20 V175 H570',stroke:'#aaa',fill:'none'});svg.append(axis);
 svg.append(svgEl('polyline',{points:coords.map(p=>p.join(',')).join(' '),fill:'none',stroke:colors[0],'stroke-width':3}));
 entries.forEach(([date,n],i)=>{const [x,y]=coords[i];const p=svgEl('circle',{cx:x,cy:y,r:5,fill:colors[1]});const t=svgEl('title');t.textContent=`${date}: ${fmt(n)}${unit}`;p.append(t);interactive(p,t.textContent,()=>toggle(key,date));svg.append(p);});
 for(const [x,y,text] of [[4,25,fmt(max)],[12,178,'0'],[40,205,entries[0][0]],[470,205,entries.at(-1)[0]]]){const t=svgEl('text',{x,y,'font-size':13});t.textContent=text;svg.append(t);}c.append(svg);
 const details=el('details');details.append(el('summary','Ver fechas y valores'));entries.forEach(([date,n])=>{const b=el('button',`${date}: ${fmt(n)}${unit}`,'button');b.type='button';b.onclick=()=>toggle(key,date);details.append(b);});c.append(details);
}
function pyramid(rows) {
 const c=card('Pirámide por rangos etarios'); c.classList.add('full-chart');
 c.append(el('p','Hombres a la izquierda · Mujeres a la derecha. Selecciona un valor para filtrar por edad y sexo.'));
 const groups=[...bands.map(b=>b[1]),'Sin registrar'].reverse(); const sexes=['Hombre','Mujer','Sin especificar'];
 const entries=groups.map(age=>sexes.map(sex=>rows.filter(r=>value(r,'edad')===age&&value(r,'O')===sex).length));
 const peak=Math.max(1,...entries.flat());const table=el('table',undefined,'age-pyramid');const head=el('tr');['Hombre','Edad','Mujer','Sin especificar'].forEach(t=>head.append(el('th',t)));table.append(head);
 groups.forEach((age,i)=>{const tr=el('tr');const make=(sex,j)=>{const td=el('td');const b=el('button',String(entries[i][j]),'pyramid-choice'); b.type='button';b.style.setProperty('--bar',`${entries[i][j]/peak*100}%`);b.setAttribute('aria-label',`${age}, ${sex}: ${entries[i][j]}`);b.onclick=()=>{filters.set('edad',age);filters.set('O',sex);render();};td.append(b);return td;};tr.append(make('Hombre',0),el('th',age),make('Mujer',1),make('Sin especificar',2));table.append(tr);});c.append(table);
}
const maps=[];
document.querySelectorAll('.dashboard-map').forEach(node=>{
 if(!window.L){node.textContent='Mapa no disponible.';return;}
 const map=L.map(node,{scrollWheelZoom:false}).setView([-2.9,-79],9);
 L.tileLayer(node.dataset.tiles,{attribution:node.dataset.attribution,maxZoom:19}).addTo(map);
 maps.push({map,layer:L.layerGroup().addTo(map),key:node.dataset.location,fitted:false});
});
function updateMaps(rows) {
 maps.forEach(m=>{m.layer.clearLayers();const grouped=new Map();
 for(const r of rows){const lat=r[m.key+'_lat'],lon=r[m.key+'_lon'];if(lat===null||lon===null||lat===undefined||lon===undefined||!Number.isFinite(Number(lat))||!Number.isFinite(Number(lon))||Math.abs(lat)>90||Math.abs(lon)>180)continue;const key=[lat,lon].join(',');if(!grouped.has(key))grouped.set(key,{coords:[Number(lat),Number(lon)],rows:[]});grouped.get(key).rows.push(r);}
 let count=0;for(const group of grouped.values()){count+=group.rows.length;const tooltip=el('div');tooltip.append(el('strong',`${group.rows.length} atención(es)`));
 group.rows.slice(0,6).forEach(r=>tooltip.append(el('div',`Hoja ${r.K||'sin número'} · ${value(r,'F')} · ${r.B||''}`)));if(group.rows.length>6)tooltip.append(el('div',`Y ${group.rows.length-6} más`));tooltip.append(el('div','Pulsa para filtrar esta ubicación'));
 const marker=L.circleMarker(group.coords,{radius:group.rows.length>1?10:7,color:'#fff',weight:2,fillColor:colors[0],fillOpacity:1}).bindTooltip(tooltip).addTo(m.layer);
 marker.on('click',()=>{selectedIds=new Set(group.rows.map(r=>r.id));render();});}
 document.getElementById('map-count-'+m.key).textContent=`${count} fichas en ${grouped.size} ubicaciones. Pulsa un punto para filtrar.`;
 if(!m.fitted&&grouped.size){m.map.fitBounds([...grouped.values()].map(g=>g.coords),{padding:[25,25],maxZoom:13});m.fitted=true;}
 });
}
const provinceMaps=[];
let provinceFeatures=null;
const provinceColors=['#dbe1e8','#fee5d9','#fcae91','#fb6a4a','#cb181d'];
function provinceColor(count,max){return count===0?provinceColors[0]:provinceColors[Math.max(1,Math.ceil(count/Math.max(1,max)*4))];}
function updateProvinces(rows){
 if(!provinceFeatures)return;
 const totals=new Map(counts(rows,'provincia'));const max=Math.max(0,...totals.values());
 const features=provinceFeatures.features;
 const list=document.getElementById('province-counts');list.replaceChildren();
 [...features].sort((a,b)=>(totals.get(b.properties.name)||0)-(totals.get(a.properties.name)||0)||a.properties.name.localeCompare(b.properties.name,'es')).forEach(f=>{
  const name=f.properties.name,n=totals.get(name)||0;const b=el('button',`${name}: ${n}`,'button emphasis');b.type='button';b.dataset.province=f.properties.key;b.dataset.count=n;b.setAttribute('aria-pressed',String(filters.get('provincia')===name));b.onclick=()=>toggle('provincia',name);list.append(b);
 });
 provinceMaps.forEach(({layer})=>layer.eachLayer(region=>{
  const name=region.feature.properties.name,n=totals.get(name)||0;
  region.setStyle({fillColor:provinceColor(n,max),fillOpacity:.9,color:filters.get('provincia')===name?'#172b4d':'#718096',weight:filters.get('provincia')===name?3:1});
  region.unbindTooltip();region.bindTooltip(el('span',`${name}: ${n} atención(es). Pulsa para filtrar.`));
  const path=region.getElement();if(path){path.setAttribute('aria-label',`${name}: ${n} atenciones. Filtrar`);path.setAttribute('aria-pressed',String(filters.get('provincia')===name));}
 }));
 const legend=document.getElementById('province-legend');legend.replaceChildren();
 const ranges=[[0,0,provinceColors[0]]];for(let i=1;i<=4;i++){const low=Math.floor((i-1)*max/4)+1,high=Math.floor(i*max/4);if(high>=low)ranges.push([low,high,provinceColors[i]]);}
 ranges.forEach(([low,high,color])=>{const item=el('span',undefined,'province-scale');const sw=el('span',undefined,'chart-swatch');sw.style.background=color;item.append(sw,el('span',low===high?String(low):`${low}–${high}`));legend.append(item);});
 document.getElementById('province-map-status').textContent=`${rows.length} atenciones en la selección · 24 provincias · máximo: ${max}. Escala recalculada con los filtros.`;
}
if(document.getElementById('ecuador-map')){
 fetch('/ecuador-provincias.geojson').then(r=>{if(!r.ok)throw Error('Cartografía no disponible');return r.json();}).then(data=>{
  provinceFeatures=data;
  if(window.L){for(const [id,islands] of [['ecuador-map',false],['galapagos-map',true]]){
   const map=L.map(id,{scrollWheelZoom:false,attributionControl:false,zoomSnap:.25});
   const layer=L.geoJSON(data,{filter:f=>(f.properties.key==='galapagos')===islands,onEachFeature:(f,region)=>region.on('click',()=>toggle('provincia',f.properties.name))}).addTo(map);
   map.fitBounds(layer.getBounds(),{padding:[15,15]});
   layer.eachLayer(region=>{const path=region.getElement();if(path){path.setAttribute('tabindex','0');path.setAttribute('role','button');path.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle('provincia',region.feature.properties.name);}});}});
   provinceMaps.push({map,layer});
  }}
  render();
 }).catch(()=>{document.getElementById('province-map-status').textContent='No se pudo cargar la cartografía. Recarga la página para volver a intentarlo.';});
}

function render() {
 const search=document.getElementById('sheet-filter').value.trim().toLocaleLowerCase();
 const rows=records.filter(r=>(!sheetSelect.value||String(r.K||'')===sheetSelect.value)&&(!search||String(r.K||'').toLocaleLowerCase().includes(search))&&(!selectedIds||selectedIds.has(r.id))&&[...filters].every(([key,v])=>value(r,key)===v));
 document.getElementById('dashboard-result').textContent=`${rows.length} de ${records.length} atenciones. Los filtros se combinan en todos los gráficos y mapas.`;
 const chips=document.getElementById('dashboard-filters');chips.replaceChildren();
 filters.forEach((v,key)=>{const b=el('button',`${labels[key]}: ${v} ×`,'button');b.onclick=()=>{filters.delete(key);render();};chips.append(b);});
 if(selectedIds){const b=el('button','Ubicación seleccionada ×','button');b.onclick=()=>{selectedIds=null;render();};chips.append(b);}
 const km=new Map();rows.forEach(r=>{if(r.Z!==''&&r.AA!==''&&r.Z!=null&&r.AA!=null&&Number.isFinite(Number(r.Z))&&Number.isFinite(Number(r.AA))&&Number(r.Z)>=0&&Number(r.AA)>=Number(r.Z)&&r.B)km.set(r.B,(km.get(r.B)||0)+Number(r.AA)-Number(r.Z));});
 const metrics=document.getElementById('dashboard-metrics');metrics.replaceChildren();
 for(const [title,n] of [['Atenciones',rows.length],['Pendientes',rows.filter(r=>r.estado==='Pendiente').length],['Cerradas',rows.filter(r=>r.estado==='Cerrada').length],['Despachos',rows.filter(r=>r.J).length],['Kilómetros',fmt([...km.values()].reduce((a,b)=>a+b,0))]]){const c=el('section',undefined,'card');c.append(el('h2',title),el('p',n,'metric-value'));metrics.append(c);}
 document.getElementById('dashboard-charts').replaceChildren();
 pie(rows,'O');pie(rows,'S');pie(rows,'M',true);pie(rows,'R',true);
 line(counts(rows,'mes'),'mes','Atenciones por mes');line([...km],'B','Kilómetros recorridos por fecha',' km');
 ['F','J','tipo','Q','U','G','D'].forEach(key=>bars(rows,key));pyramid(rows);updateMaps(rows);updateProvinces(rows);
}
sheetSearch.addEventListener('input',()=>{sheetSelect.value='';updateSheets();render();});
sheetSelect.addEventListener('change',render);
document.getElementById('clear-dashboard').onclick=()=>{filters.clear();selectedIds=null;sheetSearch.value='';sheetSelect.value='';updateSheets();render();};
render();
})();
