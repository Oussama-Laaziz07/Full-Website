let ads = [], categories = [], cities = [], currentUser = null;

function showSection(id) {
  document.querySelectorAll('.page-section').forEach(x => x.classList.add('hidden'));
  document.getElementById('section-' + id)?.classList.remove('hidden');
}
function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
async function api(path, options = {}) {
  const isFormData = options.body instanceof FormData;
  const res = await fetch(path, {...options, headers:{...(options.headers||{}), ...(options.body && !isFormData ? {'Content-Type':'application/json'} : {})}});
  let data;
  try { data = await res.json(); } catch { throw new Error('API returned a non-JSON response (' + res.status + ').'); }
  if (!res.ok) throw new Error(data.error || 'Request failed (' + res.status + ')');
  return data;
}
async function loadDatabaseData() {
  const grid = document.getElementById('ads-grid');
  if (grid) grid.textContent = 'Chargement des annonces...';
  try {
    [ads,categories,cities] = await Promise.all([api('/api/ads.php'),api('/api/categories.php'),api('/api/cities.php')]);
    populateDropdowns(); renderAds();
  } catch(e) {
    console.error(e);
    if(grid) grid.textContent = 'Impossible de charger les annonces. Vérifiez les API et les journaux Vercel.';
  }
}
async function syncSession() {
  try {
    const result = await api('/api/session.php');
    currentUser = result.user;
    document.getElementById('admin-nav')?.classList.toggle('hidden', currentUser?.role !== 'admin');
    document.getElementById('my-ads-nav')?.classList.toggle('hidden', !currentUser);
    document.getElementById('logout-nav')?.classList.toggle('hidden', !currentUser);
    renderMyAds(result.removed_ads || []);
  } catch (e) { console.error(e); }
}
function renderMyAds(items) {
  const root=document.getElementById('my-ads-list'); if(!root)return;
  if(!currentUser){root.innerHTML='<p class="text-gray-600">Connectez-vous pour consulter vos annonces.</p>';return;}
  if(!items.length){root.innerHTML='<p class="text-gray-600">Aucune annonce retirée.</p>';return;}
  root.innerHTML=items.map(a=>`<article class="border rounded-lg p-4"><h3 class="font-semibold">${esc(a.title)}</h3><p class="text-sm text-gray-600 mt-1">${a.appeal_status==='pending'?'Appel en attente de décision.':a.appeal_status==='rejected'?'Appel précédent refusé.':'Annonce retirée.'}</p>${a.appeal_status==='pending'?'':`<form onsubmit="submitAppeal(event,${Number(a.id)})" class="mt-3 space-y-2"><label class="block text-sm font-medium">Motif de l’appel</label><textarea name="reason" required maxlength="2000" class="w-full border rounded p-2" rows="3"></textarea><button class="bg-orange-main text-white px-4 py-2 rounded">Envoyer l’appel</button></form>`}</article>`).join('');
}
async function submitAppeal(event,id){event.preventDefault();const reason=new FormData(event.target).get('reason');try{await api('/api/appeals.php',{method:'POST',body:JSON.stringify({ad_id:id,reason})});await syncSession();alert('Votre appel a été envoyé à l’administrateur.');}catch(e){alert(e.message);}}
async function logout(){try{await api('/api/session.php',{method:'DELETE'});currentUser=null;await syncSession();showSection('home');}catch(e){alert(e.message);}}
async function openAdmin(){await syncSession();if(currentUser?.role==='admin'){showSection('admin');await loadAdminDashboard();}else{alert('Accès réservé à l’administrateur.');}}
async function adminAction(action,id=0,extra={}){try{await api('/api/admin.php',{method:'POST',body:JSON.stringify({action,id,...extra})});await loadAdminDashboard();await loadDatabaseData();await syncSession();return true;}catch(e){alert(e.message);return false;}}
function emptyAdmin(id,text){const el=document.getElementById(id);if(el)el.innerHTML=`<p class="text-sm text-gray-500">${text}</p>`;}
async function loadAdminDashboard(){
  if(currentUser?.role!=='admin'){showSection('home');return;}
  try{
    const d=await api('/api/admin.php');
    const card=a=>`<article class="border rounded-lg p-4 flex flex-col md:flex-row gap-4"><div class="flex-1"><h3 class="font-semibold">${esc(a.title)}</h3><p class="text-sm text-gray-600">${esc(a.category)} · ${esc(a.city)} · ${esc(a.owner_name||'Compte supprimé')}</p><p class="text-sm mt-1">${esc(a.description)}</p><p class="text-xs text-gray-500 mt-1">${esc(a.owner_email||'')}</p></div><div class="flex gap-2 items-start">${a.status==='pending_review'?`<button onclick="adminAction('approve-ad',${Number(a.id)})" class="bg-green-700 text-white px-3 py-2 rounded">Valider</button><button onclick="adminAction('remove-ad',${Number(a.id)})" class="bg-red-600 text-white px-3 py-2 rounded">Refuser</button>`:''}</div></article>`;
    const pending=d.ads.filter(a=>a.status==='pending_review'), removed=d.ads.filter(a=>a.status==='removed');
    document.getElementById('admin-pending-ads').innerHTML=pending.map(card).join('')||'<p class="text-sm text-gray-500">Aucune annonce à valider.</p>';
    document.getElementById('admin-removed-ads').innerHTML=removed.map(card).join('')||'<p class="text-sm text-gray-500">La liste est vide.</p>';
    document.getElementById('admin-appeals').innerHTML=d.appeals.map(a=>`<article class="border rounded-lg p-4"><h3 class="font-semibold">${esc(a.title)} — ${esc(a.owner_name)}</h3><p class="text-sm text-gray-600">${esc(a.owner_email)}</p><p class="mt-2 whitespace-pre-wrap">${esc(a.reason)}</p><div class="flex gap-2 mt-3"><button onclick="adminAction('resolve-appeal',${Number(a.id)},{decision:'approve'})" class="bg-green-700 text-white px-3 py-2 rounded">Rétablir l’annonce</button><button onclick="adminAction('resolve-appeal',${Number(a.id)},{decision:'reject'})" class="bg-red-600 text-white px-3 py-2 rounded">Refuser l’appel</button></div></article>`).join('')||'<p class="text-sm text-gray-500">Aucun appel à traiter.</p>';
    const listItems=(items,type)=>items.map(x=>`<li class="py-2 flex justify-between items-center gap-3"><span>${esc(x.name)}</span><button onclick="adminAction('delete-${type}',${Number(x.id)})" class="text-red-600 text-sm">Supprimer</button></li>`).join('')||'<li class="py-2 text-sm text-gray-500">Aucun élément.</li>';
    document.getElementById('admin-cities-list').innerHTML=listItems(d.cities,'city');document.getElementById('admin-categories-list').innerHTML=listItems(d.categories,'category');
    document.getElementById('admin-users-list').innerHTML=d.users.map(u=>`<li class="py-2 flex justify-between items-center gap-3"><span>${esc(u.full_name)} (${esc(u.email)}) · ${esc(u.status)}</span>${u.status==='active'?`<button onclick="adminAction('delete-user',${Number(u.id)})" class="text-red-600 text-sm">Désactiver le compte</button>`:'<span class="text-sm text-gray-500">Désactivé</span>'}</li>`).join('')||'<li class="py-2 text-sm text-gray-500">Aucun membre.</li>';
  }catch(e){['admin-pending-ads','admin-removed-ads','admin-appeals','admin-cities-list','admin-categories-list','admin-users-list'].forEach(id=>emptyAdmin(id,e.message));}
}
async function addAdminItem(event,type){event.preventDefault();const input=document.getElementById(type==='city'?'new-city-input':'new-cat-input');if(await adminAction(`add-${type}`,0,{name:input.value.trim()}))input.value='';}
function populateDropdowns() {
  const cf=document.getElementById('category-filter'), yf=document.getElementById('city-filter');
  const pc=document.getElementById('post-category'), py=document.getElementById('post-city');
  if(!cf||!yf||!pc||!py)return;
  cf.innerHTML='<option value="">Toutes les catégories</option>'; yf.innerHTML='<option value="">Toutes les villes</option>';
  pc.replaceChildren(); py.replaceChildren();
  categories.forEach(x=>{cf.add(new Option(x.name,x.name));pc.add(new Option(x.name,String(x.id)));});
  cities.forEach(x=>{yf.add(new Option(x.name,x.name));py.add(new Option(x.name,String(x.id)));});
}
function renderAds(items=ads) {
  const grid=document.getElementById('ads-grid'); if(!grid)return;
  if(!items.length){grid.innerHTML='<p class="text-gray-500 col-span-full text-center py-8">Aucune annonce trouvée.</p>';return;}
  grid.innerHTML=items.map(a=>`<article class="bg-white border rounded-xl overflow-hidden shadow-sm hover:shadow-md transition flex flex-col">
    <img src="${esc(a.image||'images/Offria.jpg')}" class="h-48 w-full object-cover" alt="${esc(a.title)}" onerror="this.src='images/Offria.jpg'">
    <div class="p-4 flex-grow flex flex-col justify-between"><div>
    <span class="text-xs font-semibold uppercase text-orange-600 bg-orange-50 px-2 py-1 rounded">${esc(a.category)}</span>
    <h3 class="font-bold text-lg text-gray-900 mt-2 line-clamp-1">${esc(a.title)}</h3>
    <p class="text-xl font-extrabold text-gray-900 mt-1">${esc(a.price)} <span class="text-sm text-gray-500 font-normal">DH</span></p>
    <p class="text-xs text-gray-400 mt-1"><i class="fa-solid fa-location-dot mr-1"></i>${esc(a.city)}</p></div>
    <div class="mt-4 pt-3 border-t"><button type="button" onclick="viewAdDetails(${Number(a.id)})" class="text-orange-main font-semibold text-sm hover:underline">Voir détails &rarr;</button></div></div></article>`).join('');
}
function filterAds() {
  const q=(document.getElementById('search-input')?.value||'').toLowerCase();
  const c=document.getElementById('category-filter')?.value||'', y=document.getElementById('city-filter')?.value||'';
  renderAds(ads.filter(a=>String(a.title).toLowerCase().includes(q)&&(!c||a.category===c)&&(!y||a.city===y)));
}
function viewAdDetails(id) {
  const a=ads.find(x=>Number(x.id)===Number(id)); if(!a)return;
  const title=document.getElementById('detail-title'); title.dataset.adId=String(a.id);
  document.getElementById('detail-img').src=a.image||'images/Offria.jpg';
  document.getElementById('detail-category').textContent=a.category;
  title.textContent=a.title; document.getElementById('detail-price').textContent=a.price+' DH';
  document.getElementById('detail-city').textContent='Ville: '+a.city;
  document.getElementById('detail-desc').textContent=a.description; showSection('details');
}
async function handlePostAd(e) {
  e.preventDefault();
  if(!currentUser){alert('Connectez-vous avant de déposer une annonce.');showSection('login');return;}
  const image=document.getElementById('post-img').files[0];
  const status=document.getElementById('post-status');
  const button=e.submitter||e.target.querySelector('button[type="submit"]');
  if(image&&image.size>5*1024*1024){status.textContent='La photo doit faire 5 Mo maximum.';status.className='text-sm mt-2 text-red-700';return;}
  const ad=new FormData();
  ad.append('title',document.getElementById('post-title').value.trim());
  ad.append('price',document.getElementById('post-price').value);
  ad.append('category_id',document.getElementById('post-category').value);
  ad.append('city_id',document.getElementById('post-city').value);
  ad.append('description',document.getElementById('post-desc').value.trim());
  if(image) ad.append('image',image);
  button.disabled=true;status.textContent=image?'Envoi de l’annonce et de la photo...':'Envoi de l’annonce...';status.className='text-sm mt-2 text-gray-600';
  try { await api('/api/ads.php',{method:'POST',body:ad}); e.target.reset(); document.getElementById('post-img-preview').classList.add('hidden'); status.textContent=''; showSection('home'); alert('Votre annonce a été envoyée à l’administrateur pour validation.'); }
  catch(err){status.textContent=err.message;status.className='text-sm mt-2 text-red-700';}
  finally{button.disabled=false;}
}
function previewAdImage(event){
  const file=event.target.files[0],preview=document.getElementById('post-img-preview'),status=document.getElementById('post-status');
  if(!file){preview.classList.add('hidden');preview.removeAttribute('src');status.textContent='';return;}
  if(file.size>5*1024*1024){event.target.value='';preview.classList.add('hidden');status.textContent='La photo doit faire 5 Mo maximum.';status.className='text-sm mt-2 text-red-700';return;}
  preview.src=URL.createObjectURL(file);preview.classList.remove('hidden');status.textContent='';
}
async function sendMessage(e) {
  e.preventDefault();
  try {
    await api('/api/messages.php',{method:'POST',body:JSON.stringify({ad_id:Number(document.getElementById('detail-title').dataset.adId),
      name:document.getElementById('msg-name').value.trim(),email:document.getElementById('msg-email').value.trim(),
      message:document.getElementById('msg-text').value.trim()})});
    e.target.reset(); alert('Message envoyé !');
  } catch(err){alert('Erreur : '+err.message);}
}
document.addEventListener('DOMContentLoaded',()=>{loadDatabaseData();syncSession();});
