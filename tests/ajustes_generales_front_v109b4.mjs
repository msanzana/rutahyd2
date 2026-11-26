import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {createRequire} from 'node:module';
const require=createRequire('/tmp/v109b4-js/package.json');
const {JSDOM}=require('jsdom');
const root=process.argv[2];
const read=p=>readFileSync(root+'/'+p,'utf8');
const dom=new JSDOM('<main id="root"></main>',{url:'https://rutahd.example',runScripts:'outside-only'});
const w=dom.window;
w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
w.HTMLDialogElement.prototype.close=function(){this.open=false;this.dispatchEvent(new w.Event('close'));};
w.HTMLElement.prototype.scrollIntoView=function(){};
const tick=()=>new Promise(r=>setTimeout(r,0));
const calls=[];
w.fetch=async(url,options)=>{calls.push([url,options]);const body=url.endsWith('/respaldos/4')?new Blob(['prueba']):null;return body?new Response(body,{headers:{'Content-Type':'application/octet-stream'}}):new Response(JSON.stringify({id:9}),{headers:{'Content-Type':'application/json'}});};
w.Response=Response;
w.eval(read('assets/js/catalogos-api.js'));
const client=w.RutaHDApiCatalogos;
client.configure('https://rutahd.example');client.authenticate('abc.def.ghi');
const form=new w.FormData();form.append('cantidad','1');form.append('respaldos[]',new w.File(['test'],'acta.pdf',{type:'application/pdf'}));
await client.generalAdjustmentCreate(form);
assert.equal(calls[0][0],'https://rutahd.example/api/v1/inventario/ajustes');
assert.equal(calls[0][1].body,form);assert.equal(calls[0][1].headers.Authorization,'Bearer abc.def.ghi');assert.equal(calls[0][1].headers['Content-Type'],undefined);
await client.generalAdjustmentPreview({cantidad:'1'});assert.equal(calls[1][1].headers['Content-Type'],'application/json');
assert.equal((await client.generalAdjustmentAttachment(9,4)).size,6);
let permitted=true;
w.RutaHDAccesos={isAdmin:()=>false,has:()=>permitted};
w.RutaHDListUI={renderPager:()=>{},action:(label,fn)=>{const b=w.document.createElement('button');b.textContent=label;b.onclick=fn;return b;}};
let created=null,previewed=null;
w.RutaHDApiCatalogos={
 generalAdjustments:async()=>({data:[],last_page:1,current_page:1,total:0}),
 inventoryWarehouses:async()=>[{id:3,nombre:'Casa'}],
 generalAdjustmentPreview:async data=>{previewed=data;return {plan:{disponible_anterior:'2',disponible_actual:'3',fisico_anterior:'2',fisico_actual:'3',cpp_anterior:'500',cpp_actual:'500',valor_movimiento:'500'}};},
 generalAdjustmentCreate:async f=>{created=f;return {id:9};},
 generalAdjustment:async()=>({operacion:{numero_interno:'AJG-9',estado_nombre:'Pendiente',estado_codigo:'INV_PEND_APROBACION',motivo_nombre_snapshot:'Sobrante',justificacion:'Respaldo de prueba',revision_tributaria:'PENDIENTE',version:1},detalles:[],respaldos:[]})
};
w.RutaHDBuscadorProductos={openSingle:async()=>({id:7,sku:'CI-7',nombre:'Producto CI'})};
w.eval(read('assets/js/inventario-v104.js'));
const host=w.document.querySelector('#root');
const button=(node,label)=>Array.from(node.querySelectorAll('button')).find(b=>b.textContent===label);
const field=(node,label)=>node.querySelector('[aria-label="'+label+'"]');
w.RutaHDInventarioV104.generalAdjustments(host);await tick();
button(host,'+ Nuevo ajuste').click();await tick();
let dialog=w.document.querySelector('dialog[open]');
assert.equal(field(dialog,'Código').value,'Automático');
assert.equal(field(dialog,'Bodega').value,'3');
field(dialog,'Motivo').value='101';
field(dialog,'Cantidad').value='1';
button(dialog,'Buscar producto').click();await tick();
button(dialog,'Revisar ajuste').click();await tick();
assert.equal(previewed.producto_id,7);assert.equal(previewed.cantidad,'1');
assert.equal(button(dialog,'Guardar').disabled,false);
field(dialog,'Cantidad').value='2';field(dialog,'Cantidad').dispatchEvent(new w.Event('input'));
assert.equal(button(dialog,'Guardar').disabled,true);
field(dialog,'Cantidad').value='1';button(dialog,'Revisar ajuste').click();await tick();
field(dialog,'Justificación obligatoria').value='Respaldo de prueba CI';
Object.defineProperty(field(dialog,'Respaldos (PDF, JPG, PNG)'),'files',{value:[new w.File(['test'],'acta.pdf',{type:'application/pdf'})]});
button(dialog,'Guardar').click();await tick();await tick();
assert.equal(created.get('producto_id'),'7');assert.equal(created.get('cantidad'),'1');assert.equal(created.getAll('respaldos[]').length,1);
dialog=w.document.querySelector('dialog[open]');assert.ok(button(dialog,'Aprobar'));assert.ok(button(dialog,'Anular'));assert.equal(button(dialog,'Confirmar ajuste'),undefined);
dialog.close();permitted=false;w.RutaHDInventarioV104.generalAdjustments(host);await tick();assert.equal(button(host,'+ Nuevo ajuste'),undefined);
const nav=read('assets/js/app-produccion.js'),html=read('index.html');
assert.match(nav,/RutaHDInventarioV104\.generalAdjustments\(content\)/);
assert.match(html,/data-nav="bodega_ajustes_generales"/);
assert.match(html,/inventario-v104\.js\?v=20261126-v109b4/);
console.log('V109B4_FRONT_OK');
