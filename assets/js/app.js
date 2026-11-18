(() => {
'use strict';
const root=document.getElementById('app'); if(!root)return;
const me=Number(root.dataset.userId), csrf=root.dataset.csrf;
const $=id=>document.getElementById(id);
let active=null,lastMessage=0,pollTimer=null,conversationItems=[],peopleItems=[],pc=null,localStream=null,signalTimer=null,callPeer=null;
let translationCache = new Map();
let autoTranslate = true;
let translationTarget = 'en';
function addTranslationToolbar() {
    if ($('translationToolbar')) return;

    $('messages').insertAdjacentHTML('beforebegin', `
        <div id="translationToolbar"
             style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;
                    padding:10px 16px;border-bottom:1px solid #e5e7eb;
                    background:#fff;font-size:13px;">

            <span style="font-weight:600;">🌐 Translate messages</span>

            <select id="translationLanguage"
                    style="padding:7px 10px;border:1px solid #d1d5db;
                           border-radius:8px;background:white;">
                <option value="en">English</option>
                <option value="tl">Filipino</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
                <option value="it">Italian</option>
                <option value="pt">Portuguese</option>
                <option value="ja">Japanese</option>
                <option value="ko">Korean</option>
                <option value="zh">Chinese</option>
                <option value="ar">Arabic</option>
                <option value="hi">Hindi</option>
                <option value="ru">Russian</option>
                <option value="nl">Dutch</option>
                <option value="pl">Polish</option>
                <option value="tr">Turkish</option>
                <option value="vi">Vietnamese</option>
                <option value="th">Thai</option>
                <option value="id">Indonesian</option>
            </select>

            <label style="display:flex;align-items:center;gap:6px;">
                <input type="checkbox" id="autoTranslateToggle" checked>
                Auto-translate incoming messages
            </label>
        </div>
    `);

    $('translationLanguage').value = translationTarget;

    $('translationLanguage').addEventListener('change', async e => {
        translationTarget = e.target.value;

        if (autoTranslate) {
            await translateVisibleMessages();
        }
    });

    $('autoTranslateToggle').addEventListener('change', async e => {
        autoTranslate = e.target.checked;

        if (autoTranslate) {
            await translateVisibleMessages();
        }
    });
}

addTranslationToolbar();
async function translateMessage(messageId) {
    const article = document.querySelector(
        `.message-row[data-message-id="${messageId}"]`
    );

    if (!article) return;

    const bubble = article.querySelector('.message-bubble');
    const output = article.querySelector('.translated-text');
    const button = article.querySelector('.translate-btn');

    if (!bubble || !output || !button) return;

    const original = bubble.dataset.original || '';

    if (!original.trim()) return;

    const cacheKey = `${messageId}_${translationTarget}`;

    try {
        button.disabled = true;
        button.textContent = 'Translating...';

        let translated = translationCache.get(cacheKey);

        if (!translated) {
            const result = await api('translate', {
                method: 'POST',
                body: {
                    text: original,
                    target: translationTarget
                }
            });

            translated = result.translatedText;
            translationCache.set(cacheKey, translated);
        }

        output.textContent = translated;
        output.style.display = 'block';
        button.textContent = 'Show original';
        button.dataset.showingTranslation = 'true';

    } catch (error) {
        output.textContent = error.message;
        output.style.display = 'block';
        output.style.color = '#dc2626';
        button.textContent = 'Try again';
        console.error('Translation error:', error);

    } finally {
        button.disabled = false;
    }
}

async function translateVisibleMessages() {
    const messages = document.querySelectorAll(
        '.message-row:not(.own)[data-message-id]'
    );

    for (const message of messages) {
        await translateMessage(message.dataset.messageId);
    }
}
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function api(action,options={}) {
  const method=options.method||'GET',url='api.php?action='+encodeURIComponent(action)+(options.query?'&'+new URLSearchParams(options.query):'');
  const headers={'X-CSRF-Token':csrf};let body=options.body;
  if(body && !(body instanceof FormData)){headers['Content-Type']='application/json';body=JSON.stringify(body);}
  const res=await fetch(url,{method,headers,body,credentials:'same-origin'});
  let data;try{data=await res.json()}catch{throw new Error('The server returned an unexpected response.');}
  if(!res.ok||!data.ok)throw new Error(data.error||'Something went wrong.');return data;
}
function toast(msg){const t=$('toast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2800);}
function avatar(name,cls=''){return `<div class="avatar ${cls}">${esc((name||'?').trim().charAt(0).toUpperCase())}</div>`;}
function showModal(html){$('modalContent').innerHTML=html;$('modalBackdrop').classList.remove('hidden');}
function closeModal(){$('modalBackdrop').classList.add('hidden');}
$('modalClose').onclick=closeModal;$('modalBackdrop').addEventListener('click',e=>{if(e.target===$('modalBackdrop'))closeModal();});
function showWorkspace(){ $('welcomePanel').classList.add('hidden');$('directory').classList.add('hidden');$('workspace').classList.remove('hidden'); }
function showWelcome(){ $('workspace').classList.add('hidden');$('directory').classList.add('hidden');$('welcomePanel').classList.remove('hidden');$('topbarHeading').textContent='Messages';}
async function loadConversations(){
 try{const d=await api('conversations');conversationItems=d.items;renderConversations();}
 catch(e){toast(e.message);}
}
function renderConversations(){
 $('chatCount').textContent=conversationItems.length;
 const unread=conversationItems.reduce((n,c)=>n+(c.unread||0),0);$('unreadCount').textContent=unread?unread:'';
 if(!conversationItems.length){$('conversationList').innerHTML='<div class="empty-side">No conversations yet.<br>Start one with someone.</div>';return;}
 $('conversationList').innerHTML=conversationItems.map(c=>`<button class="conversation-item ${active&&active.id===c.id?'selected':''}" data-id="${c.id}">${avatar(c.title)}<span class="conversation-copy"><strong>${esc(c.title)}</strong><small>${esc(c.last_message||c.subtitle||'Start the conversation')}</small></span>${c.unread?`<span class="unread-dot">${c.unread}</span>`:''}</button>`).join('');
 document.querySelectorAll('.conversation-item').forEach(b=>b.onclick=()=>openConversation(Number(b.dataset.id)));
}
async function openConversation(id){
 const c = conversationItems.find(x => Number(x.id) === Number(id));if(!c)return;
 active=c;lastMessage=0;showWorkspace();$('activeTitle').textContent=c.title;$('activeSubtitle').textContent=c.online?'● Online':(c.type==='group'?'Group conversation':c.subtitle);
 $('activeAvatar').textContent=(c.title||'?').charAt(0).toUpperCase();$('detailAvatar').textContent=(c.title||'?').charAt(0).toUpperCase();$('detailTitle').textContent=c.title;$('detailMeta').textContent=c.subtitle||'ChatConnect';
 $('messageInput').disabled=false;$('sendBtn').disabled=false;$('messageInput').focus();renderConversations();$('topbarHeading').textContent=c.title;
 $('messages').innerHTML='<div class="message-placeholder">Loading messages…</div>';
 await loadMessages(true);if(pollTimer)clearInterval(pollTimer);pollTimer=setInterval(()=>loadMessages(false),1800);
 if(window.innerWidth<900)$('sidebar').classList.remove('open');
 if(signalTimer)clearInterval(signalTimer);
 if(c.type==='private')signalTimer=setInterval(pollSignals,1200);
}
function renderMessage(m) {
    const own = Number(m.sender_id) === me;

    const attachment = m.attachment_path
        ? `<a class="attachment"
              href="${esc(m.attachment_path)}"
              target="_blank"
              rel="noopener">
              ↗ ${esc(m.attachment_name || 'Open attachment')}
           </a>`
        : '';

    const body = m.body
        ? `<div class="bubble-text">${esc(m.body).replace(/\n/g, '<br>')}</div>`
        : '';

    const image = m.attachment_mime &&
                  m.attachment_mime.startsWith('image/')
        ? `<a href="${esc(m.attachment_path)}" target="_blank">
               <img class="message-image"
                    src="${esc(m.attachment_path)}"
                    alt="${esc(m.attachment_name)}">
           </a>`
        : '';

    const translator = (!own && m.body)
        ? `
            <div class="translated-text"
                 style="display:none;margin-top:9px;padding-top:9px;
                        border-top:1px solid #e5e7eb;font-size:13px;">
            </div>

            <button type="button"
                    class="translate-btn"
                    style="margin-top:8px;border:0;background:transparent;
                           color:#218838;cursor:pointer;font-size:12px;">
                Translate
            </button>
          `
        : '';

    return `
        <article class="message-row ${own ? 'own' : ''}"
                 data-message-id="${Number(m.id)}">

            ${own ? '' : avatar(m.fullname, 'message-avatar')}

            <div class="message-content">
                ${own ? '' : `<small class="sender-name">${esc(m.fullname)}</small>`}

                <div class="message-bubble"
                     data-original="${esc(m.body || '')}">
                    ${body}
                    ${image}
                    ${attachment}
                    ${translator}
                </div>

                <time>${esc(m.created_at)}</time>
            </div>
        </article>
    `;
}
$('messages').addEventListener('click', async e => {
    const button = e.target.closest('.translate-btn');

    if (!button) return;

    const article = button.closest('.message-row');
    if (!article) return;

    const output = article.querySelector('.translated-text');

    if (button.dataset.showingTranslation === 'true') {
        output.style.display = 'none';
        button.dataset.showingTranslation = 'false';
        button.textContent = 'Translate';
        return;
    }

    await translateMessage(article.dataset.messageId);
});
async function loadMessages(initial=false){
 if(!active)return;
 try{const d=await api('messages',{query:{conversation_id:active.id,after:initial?0:lastMessage}});
 if(initial)$('messages').innerHTML='';
if (d.items.length) {
    $('messages').insertAdjacentHTML(
        'beforeend',
        d.items.map(renderMessage).join('')
    );

    lastMessage = Number(d.items[d.items.length - 1].id);

    if (autoTranslate) {
        d.items
            .filter(m => Number(m.sender_id) !== me && m.body)
            .forEach(m => translateMessage(Number(m.id)));
    }

    $('messages').scrollTop = $('messages').scrollHeight;
}
 if(initial&&!d.items.length)$('messages').innerHTML='<div class="message-placeholder">This is the beginning of your conversation. Say hello 👋</div>';
 await loadConversations();
 }catch(e){console.error(e);}
}
async function sendMessage(){
 if(!active)return;const input=$('messageInput'),body=input.value.trim();if(!body)return;
 input.value='';input.style.height='auto';
 try{await api('send',{method:'POST',body:{conversation_id:active.id,body}});await loadMessages(false);}
 catch(e){input.value=body;toast(e.message);}
}
$('messageForm').addEventListener('submit',e=>{e.preventDefault();sendMessage();});
$('messageInput').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMessage();}});
$('messageInput').addEventListener('input',e=>{e.target.style.height='auto';e.target.style.height=Math.min(e.target.scrollHeight,120)+'px';});
$('attachBtn').onclick=()=>{if(!active){toast('Open a conversation first.');return;}$('fileInput').click();};
$('fileInput').onchange=async e=>{const file=e.target.files[0];if(!file||!active)return;const form=new FormData();form.append('conversation_id',active.id);form.append('file',file);try{await api('upload',{method:'POST',body:form});await loadMessages(false);toast('File shared.');}catch(err){toast(err.message);}e.target.value='';};
async function loadPeople(q=''){
 try{const d=await api('people',{query:{q}});peopleItems=d.items;renderPeople();}
 catch(e){toast(e.message);}
}
function renderPeople(){
 $('peopleGrid').innerHTML=peopleItems.map(p=>`<article class="person-card">${avatar(p.fullname,'avatar-lg')}<div class="person-info"><strong>${esc(p.fullname)}</strong><small>@${esc(p.username)}</small><span class="presence ${p.status==='online'?'is-online':''}">${p.status==='online'?'Online':'Offline'}</span></div><button class="btn btn-light btn-small" data-person="${p.id}">Message</button></article>`).join('')||'<div class="empty-state">No people found.</div>';
 document.querySelectorAll('[data-person]').forEach(b=>b.onclick=()=>startPrivate(Number(b.dataset.person)));
}
async function startPrivate(id){try{const d=await api('start_private',{method:'POST',body:{user_id:id}});await loadConversations();const found=conversationItems.find(c=>c.id===d.conversation.id);if(!found)conversationItems.unshift(d.conversation);await openConversation(d.conversation.id);closeModal();}catch(e){toast(e.message);}}
function openDirectory(panel){
 $('welcomePanel').classList.add('hidden');$('workspace').classList.add('hidden');$('directory').classList.remove('hidden');
 $('directoryTitle').textContent=panel==='groups'?'Create a group':'People';$('topbarHeading').textContent=panel==='groups'?'Groups':'People';
 $('peopleSearch').classList.toggle('hidden',panel==='groups');
 if(panel==='groups')openGroupModal();else loadPeople();
}
document.querySelectorAll('.nav-item').forEach(b=>b.onclick=()=>{document.querySelectorAll('.nav-item').forEach(x=>x.classList.remove('active'));b.classList.add('active');if(b.dataset.panel==='chats')showWelcome();else openDirectory(b.dataset.panel);});
function openNewChat(){loadPeople();showModal(`<span class="eyebrow">START SOMETHING</span><h2>New conversation</h2><p class="muted">Choose someone to start a private chat.</p><input class="search-input modal-search" id="modalPeopleSearch" placeholder="Search by name or username"><div class="modal-list" id="modalPeopleList"></div>`);renderModalPeople();$('modalPeopleSearch').oninput=async e=>{await loadPeople(e.target.value);renderModalPeople();};}
function renderModalPeople(){const el=$('modalPeopleList');if(!el)return;el.innerHTML=peopleItems.map(p=>`<button class="modal-person" data-modal-person="${p.id}">${avatar(p.fullname)}<span><strong>${esc(p.fullname)}</strong><small>@${esc(p.username)}</small></span><b>→</b></button>`).join('')||'<p class="muted">No people found.</p>';document.querySelectorAll('[data-modal-person]').forEach(b=>b.onclick=()=>startPrivate(Number(b.dataset.modalPerson)));}
$('newChatBtn').onclick=openNewChat;$('welcomeNewChat').onclick=openNewChat;
function openGroupModal(){loadPeople();showModal(`<span class="eyebrow">BRING EVERYONE TOGETHER</span><h2>Create a group</h2><p class="muted">Give your group a name and choose its members.</p><label class="field-label">Group name<input class="search-input" id="groupName" maxlength="80" placeholder="e.g. Weekend crew"></label><input class="search-input modal-search" id="groupSearch" placeholder="Find members"><div class="modal-list" id="groupPeopleList"></div><button class="btn btn-primary btn-full modal-submit" id="createGroup">Create group</button>`);renderGroupPeople();$('groupSearch').oninput=async e=>{await loadPeople(e.target.value);renderGroupPeople();};$('createGroup').onclick=createGroup;}
function renderGroupPeople(){const el=$('groupPeopleList');if(!el)return;el.innerHTML=peopleItems.map(p=>`<label class="select-person">${avatar(p.fullname)}<span><strong>${esc(p.fullname)}</strong><small>@${esc(p.username)}</small></span><input type="checkbox" value="${p.id}"></label>`).join('')||'<p class="muted">No people found.</p>';}
async function createGroup(){const name=$('groupName').value.trim(),members=[...document.querySelectorAll('#groupPeopleList input:checked')].map(x=>Number(x.value));try{const d=await api('create_group',{method:'POST',body:{name,members}});await loadConversations();await openConversation(d.conversation.id);closeModal();}catch(e){toast(e.message);}}
$('peopleSearch').oninput=e=>loadPeople(e.target.value);
async function openProfile(){
 const d=await fetch('api.php?action=people&q='+encodeURIComponent('')).then(r=>r.json()).catch(()=>({}));
 showModal(`<span class="eyebrow">YOUR ACCOUNT</span><h2>Profile settings</h2><label class="field-label">Display name<input id="profileName" class="search-input" maxlength="100" value="${esc(document.querySelector('.user-mini-text strong')?.textContent||'')}"></label><label class="field-label">Short bio<textarea id="profileBio" class="search-input" maxlength="180" rows="3" placeholder="A little about you"></textarea></label><button class="btn btn-primary btn-full" id="saveProfile">Save profile</button>`);
 $('saveProfile').onclick=async()=>{try{await api('profile',{method:'POST',body:{fullname:$('profileName').value,bio:$('profileBio').value}});toast('Profile updated. Refresh to see your name everywhere.');closeModal();}catch(e){toast(e.message);}};
}
$('profileBtn').onclick=openProfile;
$('chatInfoBtn').onclick=()=>toast(active?'Conversation details are shown in the right panel.':'Choose a conversation first.');
$('backToList').onclick=()=>{if(window.innerWidth<900)$('sidebar').classList.add('open');};
$('openSidebar').onclick=()=>$('sidebar').classList.add('open');$('closeSidebar').onclick=()=>$('sidebar').classList.remove('open');
$('callBtn').onclick=()=>startCall();
async function startCall(){
 if(!active||active.type!=='private'||!active.other_user_id){toast('Video calling is currently available in private conversations.');return;}
 if(!navigator.mediaDevices?.getUserMedia){toast('Camera access requires HTTPS or localhost and browser permission.');return;}
 try{
  localStream=await navigator.mediaDevices.getUserMedia({video:true,audio:true});
  $('localVideo').srcObject=localStream;$('callOverlay').classList.remove('hidden');$('callStatus').textContent='Calling '+active.title+'…';
  callPeer=active.other_user_id;
  pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
  localStream.getTracks().forEach(t=>pc.addTrack(t,localStream));
  pc.ontrack=e=>{$('remoteVideo').srcObject=e.streams[0];$('callEmpty').classList.add('hidden');$('callStatus').textContent='Connected';};
  pc.onicecandidate=e=>{if(e.candidate)sendSignal('ice',e.candidate.toJSON());};
  const offer=await pc.createOffer();await pc.setLocalDescription(offer);await sendSignal('offer',offer);
  if(signalTimer)clearInterval(signalTimer);signalTimer=setInterval(pollSignals,1000);
 }catch(e){toast('Could not start call: '+e.message);endCall();}
}
async function sendSignal(type,payload){if(!active||!callPeer)return;try{await api('signal_send',{method:'POST',body:{conversation_id:active.id,recipient_id:callPeer,signal_type:type,payload}});}catch(e){console.error(e);}}
async function pollSignals(){
 if(!active)return;
 try{const d=await api('signal_poll',{query:{conversation_id:active.id}});
 for(const s of d.items){
  if(s.signal_type==='offer'){
   if(!localStream){localStream=await navigator.mediaDevices.getUserMedia({video:true,audio:true});$('localVideo').srcObject=localStream;callPeer=Number(s.sender_id);$('callOverlay').classList.remove('hidden');}
   if(!pc){pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});localStream.getTracks().forEach(t=>pc.addTrack(t,localStream));pc.ontrack=e=>{$('remoteVideo').srcObject=e.streams[0];$('callEmpty').classList.add('hidden');$('callStatus').textContent='Connected';};pc.onicecandidate=e=>{if(e.candidate)sendSignal('ice',e.candidate.toJSON());};}
   await pc.setRemoteDescription(s.payload);const answer=await pc.createAnswer();await pc.setLocalDescription(answer);await sendSignal('answer',answer);$('callStatus').textContent='Connecting…';
  }else if(s.signal_type==='answer'&&pc){await pc.setRemoteDescription(s.payload);$('callStatus').textContent='Connecting…';}
  else if(s.signal_type==='ice'&&pc){try{await pc.addIceCandidate(s.payload);}catch(err){console.warn(err);}}
  else if(s.signal_type==='hangup'){endCall(false);toast('The other person ended the call.');}
 }
 }catch(e){console.error(e);}
}
function endCall(notify=true){
 if(notify&&callPeer)sendSignal('hangup',{});
 if(signalTimer)clearInterval(signalTimer);signalTimer=null;
 if(pc){pc.close();pc=null;}if(localStream){localStream.getTracks().forEach(t=>t.stop());localStream=null;}
 $('localVideo').srcObject=null;$('remoteVideo').srcObject=null;$('callOverlay').classList.add('hidden');$('callEmpty').classList.remove('hidden');callPeer=null;
}
$('endCall').onclick=()=>endCall();$('micToggle').onclick=()=>{if(localStream){const t=localStream.getAudioTracks()[0];if(t)t.enabled=!t.enabled;$('micToggle').classList.toggle('muted-control',!t?.enabled);}};$('cameraToggle').onclick=()=>{if(localStream){const t=localStream.getVideoTracks()[0];if(t)t.enabled=!t.enabled;$('cameraToggle').classList.toggle('muted-control',!t?.enabled);}};
loadConversations();setInterval(loadConversations,7000);
})();
