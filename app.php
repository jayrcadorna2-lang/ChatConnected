<?php
require_once __DIR__ . '/config/bootstrap.php';
$user = require_login();
db()->prepare("UPDATE users SET status='online',last_seen=CURRENT_TIMESTAMP WHERE id=?")->execute([$user['id']]);
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ChatConnect</title><link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">
<div class="app-shell" id="app" data-user-id="<?= (int)$user['id'] ?>" data-csrf="<?=e($csrf)?>">
<aside class="sidebar" id="sidebar">
  <div class="sidebar-top"><a class="brand" href="app.php"><span class="brand-mark">C</span><span>ChatConnect</span></a><button class="icon-btn mobile-close" id="closeSidebar" aria-label="Close sidebar">×</button></div>
  <button class="compose-btn" id="newChatBtn"><span>＋</span> New conversation</button>
  <div class="side-label">WORKSPACE</div>
  <button class="nav-item active" data-panel="chats"><span>▣</span> Messages <span class="nav-count" id="unreadCount"></span></button>
  <button class="nav-item" data-panel="people"><span>♙</span> People</button>
  <button class="nav-item" data-panel="groups"><span>◉</span> Groups</button>
  <div class="side-label side-label-row"><span>RECENT CONVERSATIONS</span><span class="muted" id="chatCount">0</span></div>
  <div id="conversationList" class="conversation-list"><div class="empty-side">Your conversations will appear here.</div></div>
  <div class="sidebar-bottom">
    <div class="user-mini"><div class="avatar"><?=e(strtoupper(substr($user['fullname'],0,1)))?></div><div class="user-mini-text"><strong><?=e($user['fullname'])?></strong><small>@<?=e($user['username'])?></small></div><a class="logout-link" href="logout.php" title="Sign out">↗</a></div>
  </div>
</aside>
<main class="main-area">
  <header class="topbar"><button class="icon-btn menu-toggle" id="openSidebar" aria-label="Open menu">☰</button><div class="topbar-title"><span class="eyebrow">YOUR SPACE</span><strong id="topbarHeading">Messages</strong></div><div class="topbar-actions"><span class="secure-pill"><i></i> Connected</span><button class="icon-btn" id="profileBtn" title="My profile">⚙</button></div></header>
  <section class="content-area">
    <div class="welcome-panel" id="welcomePanel"><div class="welcome-copy"><span class="eyebrow">A LITTLE CLOSER, EVERY DAY</span><h1>Good conversations<br><em>start here.</em></h1><p>Bring your people together. Catch up one-on-one, gather a group, or start a video call.</p><button class="btn btn-primary" id="welcomeNewChat">Start a conversation <span>→</span></button></div><div class="welcome-art"><div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div><div class="art-bubble bubble-one">Hey! 👋</div><div class="art-bubble bubble-two">You free to talk?</div><div class="art-person">C</div><div class="art-dot dot-one"></div><div class="art-dot dot-two"></div></div></div>
    <div class="workspace hidden" id="workspace">
      <section class="chat-panel">
        <header class="chat-header"><button class="icon-btn back-to-list" id="backToList" aria-label="Back">←</button><div class="avatar avatar-lg" id="activeAvatar">C</div><div class="chat-heading"><strong id="activeTitle">Conversation</strong><small id="activeSubtitle">ChatConnect</small></div><div class="chat-tools"><button class="icon-btn" id="callBtn" title="Start video call">▣</button><button class="icon-btn" id="chatInfoBtn" title="Conversation details">ⓘ</button></div></header>
        <div class="messages" id="messages"><div class="message-placeholder">Choose a conversation to get started.</div></div>
        <div class="typing-line" id="typingLine"></div>
        <form class="composer" id="messageForm"><button class="icon-btn attach-btn" type="button" id="attachBtn" title="Attach a file">＋</button><input type="file" id="fileInput" hidden><textarea id="messageInput" rows="1" placeholder="Write a message…" maxlength="5000" disabled></textarea><button class="send-btn" id="sendBtn" disabled aria-label="Send message">↑</button></form>
        <div class="composer-note">Enter to send · Shift + Enter for a new line</div>
      </section>
      <aside class="details-panel" id="detailsPanel"><div class="details-heading">Conversation details</div><div class="details-person"><div class="avatar avatar-xl" id="detailAvatar">C</div><strong id="detailTitle">Choose a chat</strong><small id="detailMeta">Your conversation space</small></div><div class="details-section"><span class="side-label">SHARED SPACE</span><p class="muted tiny">Files and links shared in this conversation will be available here.</p></div></aside>
    </div>
    <section class="directory hidden" id="directory"><div class="directory-head"><div><span class="eyebrow">YOUR COMMUNITY</span><h2 id="directoryTitle">People</h2><p class="muted">Find someone and start a conversation.</p></div><input id="peopleSearch" class="search-input" placeholder="Search people…"></div><div id="peopleGrid" class="people-grid"></div></section>
  </section>
</main>
</div>
<div class="modal-backdrop hidden" id="modalBackdrop"><section class="modal" role="dialog" aria-modal="true"><button class="modal-close" id="modalClose">×</button><div id="modalContent"></div></section></div>
<div class="toast" id="toast"></div>
<div class="call-overlay hidden" id="callOverlay"><div class="call-top"><span class="call-brand">ChatConnect call</span><span id="callStatus">Connecting…</span></div><div class="video-stage"><video id="remoteVideo" autoplay playsinline></video><video id="localVideo" autoplay muted playsinline></video><div class="call-empty" id="callEmpty">Waiting for the other person to join…</div></div><div class="call-controls"><button id="micToggle" class="call-control">Mic</button><button id="cameraToggle" class="call-control">Camera</button><button id="endCall" class="call-control end-call">End call</button></div></div>
<script src="assets/js/app.js" defer></script>
</body></html>
