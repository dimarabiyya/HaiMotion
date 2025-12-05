<?php 
// FILE: chat.php (REVISI LENGKAP MESSENGER DENGAN GRUP CHAT & SETTINGS - CHECKBOX LIST)

// Pastikan file db_connect dan session sudah ter-include di index.php
if(!isset($_SESSION['login_id'])) {
    header("location:login.php");
    exit();
}
// Include db_connect.php harus dijamin tersedia di konteks index.php
// Jika Anda menjalankan file ini sebagai halaman terpisah, tambahkan: include 'db_connect.php'; di sini
$current_user_id = $_SESSION['login_id'];

// Ambil ID yang dienkripsi dari URL jika ada
$encoded_thread_id = $_GET['thread_id'] ?? null;
$encoded_recipient_id = $_GET['recipient_id'] ?? null;
$encoded_group_id = $_GET['group_id'] ?? null; // Tambahan untuk Group Chat

// ➡️ DECODE ID DARI URL & PASTIKAN HANYA ID NUMERIK YANG DIGUNAKAN
$initial_thread_id = null;
$initial_recipient_id = null;
$initial_group_id = null;

// Fungsi Decode ID (Asumsi tersedia di file lain atau didefinisikan)
if (!function_exists('decode_id')) {
    function decode_id($id) { return $id; } // Fallback jika tidak ada enkripsi
}

// 1. Decode Thread ID
if (!empty($encoded_thread_id)) {
    $decoded_id = decode_id($encoded_thread_id);
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_thread_id = $decoded_id;
    }
}

// 2. Decode Recipient ID
if (!empty($encoded_recipient_id)) {
    $decoded_id = decode_id($encoded_recipient_id);
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_recipient_id = $decoded_id;
    }
}

// 3. Decode Group ID
if (!empty($encoded_group_id)) {
    $decoded_id = decode_id($encoded_group_id);
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_group_id = $decoded_id;
    }
}


// LOGIKA: Jika Thread ID ada tapi Recipient/Group belum ada, cari Recipient ID
if ($initial_thread_id && !$initial_recipient_id && isset($conn)) {
    $thread_q = $conn->query("SELECT user1_id, user2_id FROM chat_threads WHERE id = '{$initial_thread_id}'");
    if ($thread_q && $thread_q->num_rows > 0) {
        $thread_data = $thread_q->fetch_assoc();
        $initial_recipient_id = ($thread_data['user1_id'] == $current_user_id) ? $thread_data['user2_id'] : $thread_data['user1_id'];
    } else {
        $initial_thread_id = '';
    }
}

// Teruskan nilai ke JavaScript, pastikan string kosong jika null/gagal
$initial_thread_id_js = $initial_thread_id === null ? '' : (string)$initial_thread_id;
$initial_recipient_id_js = $initial_recipient_id === null ? '' : (string)$initial_recipient_id;
$initial_group_id_js = $initial_group_id === null ? '' : (string)$initial_group_id;
?>

<style>
    /* ------------------------------------------- */
    /* WA / MESSENGER STYLES (UI FIXES)            */
    /* ------------------------------------------- */
    .chat-container {
        display: flex;
        height: 75vh;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        overflow: hidden;
    }
    
    /* --- SIDEBAR (USER LIST) --- */
    .user-list-sidebar {
        width: 350px;
        border-right: 1px solid #dee2e6;
        overflow-y: auto;
        padding: 0;
        background-color: #f8f9fa;
        min-height: 100%;
        flex-shrink: 0;
    }
    .user-item {
        display: flex; 
        align-items: center;
        padding: 12px 15px;
        border-bottom: 1px solid #f1f1f1;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .user-item .flex-grow-1 {
        min-width: 0; 
        margin-left: 10px; 
    }
    .user-item:hover, .user-item.active {
        background-color: #e9ecef;
    }
    
    /* --- CHAT AREA --- */
    .chat-area {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        background-color: #f0f0f0; 
    }
    .chat-header {
        background-color: #ffffff; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        z-index: 10;
        padding: 10px 15px;
        /* ➡️ FIX: Memastikan Judul dan Tombol Settings Sejajar */
        display: flex; 
        justify-content: space-between;
        align-items: center;
    }
    .chat-box {
        flex-grow: 1;
        overflow-y: auto;
        padding: 20px 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        height: 1px;
    }
    
    /* --- MESSAGE BUBBLES --- */
    .message-item {
        margin: 5px 0;
        padding: 8px 12px;
        border-radius: 18px; 
        max-width: 65%; 
        position: relative;
        word-wrap: break-word; 
        font-size: 0.95em; 
    }
    .incoming-message {
        background-color: #ffffff; 
        margin-right: auto;
        border-bottom-left-radius: 4px; 
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
    }
    .outgoing-message {
        background-color: #B75301; 
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 4px; 
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
    }
    
    .sender-name {
        font-weight: bold;
        margin-bottom: 2px;
        font-size: 0.85em; 
        opacity: 0.8;
        color: #B75301; /* Warna tema untuk nama pengirim pesan masuk */
    }
    .outgoing-message .sender-name {
        color: #ffffff; 
        opacity: 0.9;
    }
    
    .timestamp {
        font-size: 0.65em; 
        opacity: 0.6;
        display: block;
        margin-top: 3px;
        text-align: right;
    }
    .outgoing-message .timestamp {
        color: #fff;
        opacity: 0.8;
    }
    
    /* --- INPUT AREA --- */
    #chat_input_container {
        background-color: #ffffff; 
        border-top: 1px solid #dee2e6;
        padding: 10px 15px;
        flex-shrink: 0;
    }
    #message_content {
        border-radius: 20px !important; 
        padding: 10px 15px;
        min-height: 44px;
        line-height: 24px;
    }
    #chat_input_container .input-group-append button {
        border-radius: 20px !important;
        margin-left: 5px;
    }
    
    /* --- AVATAR & BADGE --- */
    .default-avatar {
        width: 40px; 
        height: 40px;
        font-size: 1.1em;
        border-radius: 50%;
        text-align: center;
        line-height: 40px;
        flex-shrink: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: #6c757d; 
        color: white;
    }
    .badge-danger {
        background-color: #dc3545; 
        color: white;
        font-size: 0.7rem;
        padding: 4px 6px;
        line-height: 1;
        margin-left: 5px;
    }
    .user-item .font-weight-bold {
        font-weight: bold !important;
    }
    
    /* ➡️ STYLE UNTUK LIST CHECKBOX DI MODAL */
    .member-list-container {
        max-height: 300px; /* Diperbesar sedikit */
        overflow-y: auto; 
        border: 1px solid #ced4da; /* Border tipis */
        border-radius: 6px;
        padding: 0;
        background-color: #ffffff; /* Background putih */
    }
    
    .member-list-item {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #eee; /* Garis pemisah tipis */
        transition: background-color 0.2s;
        user-select: none; /* Mencegah teks terseleksi saat klik */
    }
    .member-list-item:last-child {
        border-bottom: none; /* Hilangkan border di item terakhir */
    }
    .member-list-item:hover {
        background-color: #f5f5f5; /* Hover yang lebih lembut */
    }

    /* Style untuk Checkbox/Label */
    .member-list-item input[type="checkbox"] {
        min-width: 18px;
        min-height: 18px;
        margin-right: 15px;
        cursor: pointer;
    }

    /* Style untuk nama pengguna */
    .member-list-item span {
        flex-grow: 1; /* Nama mengambil sisa ruang */
        font-size: 0.95em;
        color: #333;
    }
</style>

<h3 class="m-0 mb-3">Messenger</h3>

<div class="p-0 chat-container container-fluid">
    
    <div class="user-list-sidebar">
        <div class="p-3 border-bottom bg-white sticky-top d-flex justify-content-between align-items-center" style="z-index: 5;">
            <h5 class="m-0 text-dark font-weight-bold">Chats</h5>
            <button class="btn btn-sm btn-primary" id="new_group_btn" type="button" data-toggle="modal" data-target="#groupModal" style="background-color:#B75301; border-color:#B75301;">
                <i class="fas fa-users"></i> New Group
            </button>
        </div>
        
        <div id="group_list_display" class="pt-2 border-bottom">
            <h6 class="p-2 m-0 text-secondary">Groups</h6>
            </div>
        <div id="user_list_display">
            <h6 class="p-2 m-0 text-secondary">Direct Messages</h6>
            </div>
    </div>

    <div class="chat-area">
        <div class="chat-header" id="recipient_header">
            <h5 class="m-0 text-muted">Choose contact or group</h5>
            <button class="btn btn-sm btn-outline-secondary" id="group_settings_btn" type="button" data-toggle="modal" data-target="#groupSettingsModal" style="display: none;">
                <i class="fas fa-cog"></i> Settings
            </button>
        </div>
        
        <div class="chat-box" id="chat_display">
            <div class="text-center p-5 text-muted">Choose contact or group for start conversation</div>
        </div>
        
        <div id="chat_input_container" style="display: none;">
            <form id="chat_form">
                <input type="hidden" name="thread_id" id="form_thread_id">
                <div class="input-group">
                    <input type="text" name="message_content" id="message_content" class="form-control" placeholder="Write message.." required>
                    <div class="input-group-append">
                        <button class="btn text-white" style="background-color:#B75301" type="submit">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
                <small class="form-text text-muted ml-2">Mention tag : '@user', '#project', '!task'</small>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="groupModal" tabindex="-1" role="dialog" aria-labelledby="groupModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupModalLabel">Create New Group</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="group_form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="group_name">Group Name</label>
                        <input type="text" class="form-control" id="group_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Members (Select multiple)</label>
                        <div id="new_group_member_list" class="member-list-container">
                            </div>
                        <small class="text-muted">Choose group member</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" style="background-color:#B75301; border-color:#B75301;">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="groupSettingsModal" tabindex="-1" role="dialog" aria-labelledby="groupSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupSettingsModalLabel">Group Settings: <span id="settings_group_name_title"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="group_settings_form">
                <input type="hidden" name="group_id" id="settings_group_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="settings_group_name">Group Name</label>
                        <input type="text" class="form-control" id="settings_group_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Add/Remove Members</label>
                         <div id="settings_group_member_list" class="member-list-container">
                            </div>
                        <small class="text-muted">Choose member</small>
                    </div>
                    <hr>
                    <button type="button" class="btn btn-danger btn-block mt-3" id="delete_group_btn">
                        <i class="fas fa-trash"></i> Delete Group
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" style="background-color:#B75301; border-color:#B75301;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/id.min.js"></script> 
<script>
    moment.locale('id'); 

    // --- GLOBAL STATE ---
    let lookupData = { users: {}, projects: {}, tasks: {} }; 
    let reverseLookup = { users: {}, projects: {}, tasks: {} }; 
    
    let currentThreadId = '<?php echo $initial_thread_id_js; ?>'; 
    let currentRecipientId = '<?php echo $initial_recipient_id_js; ?>';
    let currentGroupId = '<?php echo $initial_group_id_js; ?>'; // NEW: Group ID
    
    let currentRecipientName = '';
    let currentChatType = ''; // 'personal' or 'group'
    
    // Tentukan tipe chat awal
    if (currentThreadId || currentRecipientId) {
        currentChatType = 'personal';
    } else if (currentGroupId) {
        currentChatType = 'group';
    }

    const currentUserId = <?php echo $current_user_id; ?>;
    let isChatBoxScrolledToBottom = true; 
    
    // --- HELPER FUNCTIONS ---
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function formatMessage(message) {
        const links = {
            'user': 'index.php?page=view_user&id=',
            'project': 'index.php?page=view_project&id=',
            'task': 'index.php?page=view_task&id='
        };
        
        let formattedMessage = message;

        const sortedUserNames = Object.keys(reverseLookup.users).sort((a, b) => b.length - a.length);
        const sortedProjectNames = Object.keys(reverseLookup.projects).sort((a, b) => b.length - a.length);
        const sortedTaskNames = Object.keys(reverseLookup.tasks).sort((a, b) => b.length - a.length);

        sortedUserNames.forEach(name => {
            const escapedName = escapeRegExp(name);
            const userRegex = new RegExp(`@(${escapedName})(?=\\s|$)`, 'gi');
            if (userRegex.test(formattedMessage)) {
                const id = reverseLookup.users[name];
                const encoded_id = lookupData.users[id]?.encoded_id || id;
                const replacement = `<a href="${links.user}${encoded_id}" class="mention-tag" target="_blank">@${name}</a>`;
                formattedMessage = formattedMessage.replace(userRegex, replacement);
            }
        });
        
        sortedProjectNames.forEach(name => {
            const escapedName = escapeRegExp(name);
            const projectRegex = new RegExp(`#(${escapedName})(?=\\s|$)`, 'gi');
            if (projectRegex.test(formattedMessage)) {
                const id = reverseLookup.projects[name];
                const encoded_id = lookupData.projects[id]?.encoded_id || id;
                const replacement = `<a href="${links.project}${encoded_id}" class="mention-tag" target="_blank">#${name}</a>`;
                formattedMessage = formattedMessage.replace(projectRegex, replacement);
            }
        });
        sortedTaskNames.forEach(name => {
            const escapedName = escapeRegExp(name);
            const taskRegex = new RegExp(`!(${escapedName})(?=\\s|$)`, 'gi');
            if (taskRegex.test(formattedMessage)) {
                const id = reverseLookup.tasks[name];
                const encoded_id = lookupData.tasks[id]?.encoded_id || id;
                const replacement = `<a href="${links.task}${encoded_id}" class="mention-tag" target="_blank">!${name}</a>`;
                formattedMessage = formattedMessage.replace(taskRegex, replacement);
            }
        });

        return formattedMessage;
    }
    
    function buildChatHtml(messages) {
        var html = '';
        if (messages && messages.length > 0) {
            messages.forEach(function(msg) {
                var isOutgoing = msg.sender_id == currentUserId;
                var messageClass = isOutgoing ? 'outgoing-message' : 'incoming-message';
                
                var showSenderName = !isOutgoing || currentChatType === 'group';
                var senderNameHtml = showSenderName ? `<span class="sender-name">${msg.sender_name}</span>` : '';
                
                var formattedContent = formatMessage(msg.message_content);

                html += `
                    <div class="message-item ${messageClass}">
                        ${senderNameHtml}
                        <p class="m-0">${formattedContent}</p>
                        <span class="timestamp">${msg.created_at}</span>
                    </div>
                `;
            });
        } else {
            html = '<div class="text-center p-5 text-muted">Start first Conversation</div>';
        }
        return html;
    }
    
    // --- SCROLL LISTENER ---
    $('#chat_display').scroll(function() {
        const chatBox = $(this);
        isChatBoxScrolledToBottom = (chatBox.scrollTop() + chatBox.innerHeight() >= chatBox[0].scrollHeight - 50);
    });

    // --- CHAT LOGIC ---

    // 1. Memuat pesan (Bisa Personal atau Group)
    function loadChatMessages(isManualLoad = true) {
        if (!currentThreadId && !currentGroupId) {
            if(isManualLoad) $('#chat_display').html('<div class="text-center p-5 text-muted">Pilih kontak atau grup untuk memulai percakapan.</div>');
            $('#chat_input_container').hide();
            $('#group_settings_btn').hide(); 
            return;
        }

        const threadParam = currentChatType === 'personal' ? { thread_id: currentThreadId } : { group_id: currentGroupId };
        const chatAction = currentChatType === 'personal' ? 'get_personal_chat_messages' : 'get_group_chat_messages';

        var chatBox = $('#chat_display');
        if(isManualLoad) chatBox.html('<div class="text-center p-3 text-muted">Loading...</div>');
        
        // ➡️ LOGIKA TOMBOL GROUP SETTINGS
        if (currentChatType === 'group') {
            $('#recipient_header h5').html('<div class="d-flex align-items-center"><i class="fas fa-users mr-2"></i>' + currentRecipientName + '</div>');
            $('#group_settings_btn').show();
        } else {
            $('#recipient_header h5').html('<div class="d-flex align-items-center"><i class="fas fa-comment-dots mr-2"></i>' + currentRecipientName + '</div>');
            $('#group_settings_btn').hide();
        }

        $('#chat_input_container').show();
        $('#form_thread_id').val(currentThreadId); 

        $.ajax({
            url: 'ajax.php?action=' + chatAction,
            method: 'POST',
            data: threadParam,
            dataType: 'json',
            success: function(response) {
                
                // Update Lookup Maps 
                lookupData.users = response.users || {};
                lookupData.projects = response.projects || {};
                lookupData.tasks = response.tasks || {};
                
                reverseLookup.users = {};
                for (const id in lookupData.users) { reverseLookup.users[lookupData.users[id].name.trim()] = id; }
                reverseLookup.projects = {};
                for (const id in lookupData.projects) { reverseLookup.projects[lookupData.projects[id].name.trim()] = id; }
                reverseLookup.tasks = {};
                for (const id in lookupData.tasks) { reverseLookup.tasks[lookupData.tasks[id].name.trim()] = id; }
                
                
                const newContentHtml = buildChatHtml(response.messages);

                if (isManualLoad || chatBox.data('last-content') !== newContentHtml) {
                    chatBox.empty().append(newContentHtml);
                    chatBox.data('last-content', newContentHtml);
                    
                    if (isManualLoad || isChatBoxScrolledToBottom) {
                        chatBox.scrollTop(chatBox[0].scrollHeight);
                    }
                }
                loadChatSidebar(false); 
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (loadChatMessages):", status, error, xhr.responseText);
                if(isManualLoad) chatBox.html('<div class="text-center p-5 text-danger">Error loading messages. Check console for details.</div>');
            }
        });
    }
    
    // 2. Mendapatkan ID thread atau membuat yang baru (Hanya untuk Personal Chat)
    function getOrCreateThread() {
        if (!currentRecipientId || currentRecipientId == currentUserId) return;
        
        $.ajax({
            url: 'ajax.php?action=get_or_create_thread_id',
            method: 'POST',
            data: { user2_id: currentRecipientId },
            success: function(threadId) {
                if (threadId > 0) {
                    currentThreadId = threadId;
                    loadChatMessages(); 
                } else {
                    alert_toast("Gagal membuat atau mendapatkan thread chat.", "error");
                    $('#chat_input_container').hide();
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan koneksi saat membuat thread.", "error");
                $('#chat_input_container').hide();
            }
        });
    }

    // 3. Memuat Opsi Anggota Grup (BARU: Menggunakan Checkbox List)
    function loadGroupUserOptions(users, listContainerId, selectedMembers = []) {
        const listContainer = $(listContainerId);
        listContainer.empty();
        
        users.forEach(user => {
          
            if(listContainerId === '#new_group_member_list' && user.id == currentUserId) return; 
        
            const isSelected = selectedMembers.includes(user.id.toString());
            const checkedAttr = isSelected ? 'checked' : '';
            const isDisabled = (listContainerId === '#settings_group_member_list' && user.id == currentUserId) ? 'disabled' : '';
            
            // Gunakan nama input yang sama: user_ids[]
            const checkbox = `<input type="checkbox" name="user_ids[]" value="${user.id}" ${checkedAttr} ${isDisabled}>`;

            // Tambahkan label "Anda" jika itu adalah pengguna saat ini
            const userNameDisplay = user.id == currentUserId ? `${user.name} (Anda)` : user.name;

            const userHtml = `
                <label class="member-list-item">
                    ${checkbox}
                    <span>${userNameDisplay}</span>
                </label>
            `;
            listContainer.append(userHtml);
        });
    }

    // 4. Memuat Data Group untuk Modal Settings
    function loadGroupSettingsModalData(groupId) {
        if (!groupId) return;

        // Muat semua pengguna 
        $.ajax({
            url: 'ajax.php?action=get_all_chat_sidebar_data', 
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                const allUsers = data.users || [];
                
                // Muat detail grup dan anggota yang sudah ada
                $.ajax({
                    url: 'ajax.php?action=get_group_details', // 💡 Harus ada di ajax.php
                    method: 'POST',
                    data: { group_id: groupId },
                    dataType: 'json',
                    success: function(groupData) {
                        if (groupData.id) {
                            $('#settings_group_id').val(groupData.id);
                            $('#settings_group_name').val(groupData.name);
                            $('#settings_group_name_title').text(groupData.name);
                            
                            const currentMemberIds = groupData.members.map(m => m.user_id.toString());
                            
                            // Muat opsi anggota untuk modal settings
                            loadGroupUserOptions(allUsers, '#settings_group_member_list', currentMemberIds);

                        } else {
                            alert_toast("Gagal memuat detail grup.", "error");
                        }
                    },
                    error: function() {
                        alert_toast("Koneksi gagal saat memuat detail grup.", "error");
                    }
                });
            }
        });
    }


    // 5. Memuat Daftar Chat di Sidebar (Personal & Group)
    function loadChatSidebar(initialLoad = true) {
        $.ajax({
            url: 'ajax.php?action=get_all_chat_sidebar_data', 
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                
                if(data.error) {
                     console.error("Backend Error:", data.debug);
                     return;
                }

                const users = data.users || [];
                const groups = data.groups || [];

                // --- 1. PROSES PERSONAL CHAT ---
                $('#user_list_display h6').nextAll().remove(); 
                
                // Logika Pengurutan
                users.sort((a, b) => {
                    // Prioritas 1: Pesan belum dibaca
                    if (a.unread_count > 0 && b.unread_count === 0) return -1;
                    if (a.unread_count === 0 && b.unread_count > 0) return 1;
                    // Prioritas 2: Timestamp pesan terakhir
                    const timeA = a.last_message_timestamp ? new Date(a.last_message_timestamp).getTime() : 0;
                    const timeB = b.last_message_timestamp ? new Date(b.last_message_timestamp).getTime() : 0;
                    return timeB - timeA;
                });

                users.forEach(function(user) {
                    if (user.id == currentUserId) return; 

                    // Cek item aktif saat ini
                    var activeClass = (user.id == currentRecipientId && currentChatType === 'personal') ? 'active' : '';
                    
                    var initials = (user.name || 'NN').split(' ').map(n => n[0]).join('').substring(0, 2);
                    var avatarHtml = user.avatar ? `<img src="assets/uploads/${user.avatar}" class="img-circle elevation-2 mr-2" alt="User Image" style="width: 40px; height: 40px; object-fit: cover;">` : 
                                                   `<div class="default-avatar mr-2">${initials}</div>`;

                    var unreadBadge = user.unread_count > 0 ? `<span class="badge badge-pill badge-danger">${user.unread_count}</span>` : '';
                    var nameBold = user.unread_count > 0 ? 'font-weight-bold' : '';
                    var contentBold = user.unread_count > 0 ? 'font-weight-bold' : '';
                    var lastMessageContent = user.last_message_content ? user.last_message_content.substring(0, 30) + (user.last_message_content.length > 30 ? '...' : '') : '<span class="text-muted">Start Conversation</span>';
                    var lastMessageTime = user.last_message_timestamp ? moment(user.last_message_timestamp).fromNow() : ''; 
                            
                    var userHtml = `
                        <div class="user-item d-flex ${activeClass}" 
                             data-type="personal" 
                             data-thread-id="${user.thread_id || ''}" 
                             data-id="${user.id}" 
                             data-name="${user.name.trim()}">
                            ${avatarHtml}
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-dark ${nameBold}">${user.name}</span>
                                    <small class="text-muted">${lastMessageTime}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted ${contentBold} text-truncate" style="max-width: 85%;">${lastMessageContent}</small>
                                    ${unreadBadge}
                                </div>
                            </div>
                        </div>
                    `;
                    $('#user_list_display').append(userHtml);
                });
                
                // --- 2. PROSES GROUP CHAT ---
                $('#group_list_display h6').nextAll().remove(); 
                
                groups.sort((a, b) => {
                    if (a.unread_count > 0 && b.unread_count === 0) return -1;
                    if (a.unread_count === 0 && b.unread_count > 0) return 1;
                    const timeA = a.last_message_timestamp ? new Date(a.last_message_timestamp).getTime() : 0;
                    const timeB = b.last_message_timestamp ? new Date(b.last_message_timestamp).getTime() : 0;
                    return timeB - timeA;
                });

                groups.forEach(function(group) {
                    var activeClass = (group.id == currentGroupId && currentChatType === 'group') ? 'active' : '';
                    
                    var unreadBadge = group.unread_count > 0 ? `<span class="badge badge-pill badge-danger">${group.unread_count}</span>` : '';
                    var nameBold = group.unread_count > 0 ? 'font-weight-bold' : '';
                    var contentBold = group.unread_count > 0 ? 'font-weight-bold' : '';
                    
                    // Menampilkan nama pengirim pesan terakhir di grup
                    var lastMessagePrefix = group.last_sender_name ? `${group.last_sender_name}: ` : '';

                    var lastMessageContent = group.last_message_content ? 
                        lastMessagePrefix + group.last_message_content.substring(0, 20) + (group.last_message_content.length > 20 ? '...' : '') : 
                        'Start Group Conversation';
                    
                    var lastMessageTime = group.last_message_timestamp ? moment(group.last_message_timestamp).fromNow() : ''; 
                    
                    var groupHtml = `
                        <div class="user-item d-flex align-items-center ${activeClass}" 
                            data-type="group" 
                            data-id="${group.id}" 
                            data-name="${group.name.trim()}">
                            <div class="default-avatar mr-2 bg-info"><i class="fas fa-users"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-dark ${nameBold}">${group.name}</span>
                                    <small class="text-muted">${lastMessageTime}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted ${contentBold} text-truncate" style="max-width: 85%;">${lastMessageContent}</small>
                                    ${unreadBadge}
                                </div>
                            </div>
                        </div>
                    `;
                    $('#group_list_display').append(groupHtml);
                });

                // --- 3. Logika Inisialisasi dari URL ---
                if (initialLoad) {
                    // Jika ada ID di URL, set state awal
                    if (currentRecipientId && currentChatType === 'personal') {
                        const selectedUser = $(`.user-item[data-id="${currentRecipientId}"][data-type="personal"]`);
                        if(selectedUser.length > 0) {
                           currentRecipientName = selectedUser.data('name');
                           selectedUser.addClass('active');
                           getOrCreateThread(); // Lanjutkan ke load chat
                        }
                    } else if (currentGroupId && currentChatType === 'group') {
                        const selectedGroup = $(`.user-item[data-id="${currentGroupId}"][data-type="group"]`);
                        if(selectedGroup.length > 0) {
                            currentRecipientName = selectedGroup.data('name');
                            selectedGroup.addClass('active');
                            loadChatMessages(); // Langsung muat pesan grup
                        }
                    } else {
                         $('#chat_input_container').hide();
                         $('#group_settings_btn').hide();
                    }
                }
                
                // Muat opsi anggota untuk modal grup (Create Group)
                loadGroupUserOptions(users, '#new_group_member_list');
                
            },
            error: function(xhr, status, error) {
                 console.error("AJAX Error (Load Chat Sidebar):", status, error, xhr.responseText);
                 if(initialLoad) $('#user_list_display').html('<div class="text-center p-3 text-danger">Gagal memuat daftar chat.</div>');
            }
        });
    }

    // --- EVENT HANDLERS ---

    // A. Tangani Klik Pindah Kontak/Grup (DELEGATED EVENT - Sidebar)
    $('#user_list_display, #group_list_display').on('click', '.user-item', function() {
        const item = $(this);
        const type = item.data('type');
        
        // Reset status aktif di semua item
        $('.user-item').removeClass('active');
        item.addClass('active');
        
        // Reset state ID
        currentThreadId = '';
        currentRecipientId = '';
        currentGroupId = '';
        currentChatType = type;
        
        // ⬅️ Sembunyikan tombol setting saat beralih ke personal
        $('#group_settings_btn').hide();

        currentRecipientName = item.data('name');

        if (type === 'personal') {
            currentRecipientId = item.data('id').toString();
            currentThreadId = item.data('thread-id') || ''; 
            getOrCreateThread(); 
        } else if (type === 'group') {
            currentGroupId = item.data('id').toString();
            loadChatMessages(true); 
        }

        // Hapus visual notif saat diklik
        item.find('.badge-danger').remove();
        item.find('.font-weight-bold').removeClass('font-weight-bold');
    });
    
    // B. Tangani Pembukaan Modal Group Settings
    $('#group_settings_btn').click(function() {
        loadGroupSettingsModalData(currentGroupId);
        $('#groupSettingsModal').modal('show');
    });

    // C. Tangani Pengiriman Pesan (Personal/Group)
    $('#chat_form').submit(function(e) {
        e.preventDefault();
        
        if (!currentThreadId && !currentGroupId) return;

        var messageContent = $('#message_content').val().trim();
        if (messageContent === '') return;

        var formData = $(this).serialize();
        var action = currentChatType === 'personal' ? 'save_personal_chat_message' : 'save_group_chat_message';
        
        // Tambahkan Group ID jika chat grup
        if (currentChatType === 'group') {
            formData += '&group_id=' + currentGroupId;
        }

        $.ajax({
            url: 'ajax.php?action=' + action,
            method: 'POST',
            data: formData,
            success: function(resp) {
                if (resp == 1) {
                    $('#message_content').val('');
                    loadChatMessages(true); 
                    loadChatSidebar(false); 
                } else {
                    alert_toast("Gagal menyimpan pesan: " + resp, "error");
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan saat mengirim pesan.", "error");
            }
        });
    });

    // D. Tangani Pembuatan Grup Baru (Perubahan Form Data)
    $('#group_form').submit(function(e) {
        e.preventDefault();
        
        // Cek apakah ada anggota yang dipilih (minimal 1, current user akan ditambahkan backend)
        const selectedMembers = $('#new_group_member_list input[name="user_ids[]"]:checked').length;
        if (selectedMembers === 0) {
            alert_toast("Pilih setidaknya satu anggota lain.", "error");
            return;
        }

        // Ambil data form, termasuk user_ids[] dari checkbox
        const formData = $(this).serialize();
        
        // Pastikan currentUserId dikirim di backend meskipun tidak ditampilkan di list
        
        $.ajax({
            url: 'ajax.php?action=create_new_group',
            method: 'POST',
            data: formData,
            success: function(resp) {
                if (resp > 0) {
                    alert_toast("Grup berhasil dibuat!", "success");
                    $('#groupModal').modal('hide');
                    $('#group_form')[0].reset();
                    loadChatSidebar(true); 
                } else {
                    alert_toast("Gagal membuat grup. Pastikan Anda memilih setidaknya satu anggota.", "error");
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan koneksi saat membuat grup.", "error");
            }
        });
    });
    
    // E. Tangani Form Update Group Settings (PERBAIKAN)
    $('#group_settings_form').submit(function(e) {
        e.preventDefault();
        
        // 1. Ambil ID yang dicentang (Hanya yang TIDAK disabled)
        const checkedMembers = $('#settings_group_member_list input[name="user_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        // 2. Tambahkan ID User Saat Ini secara paksa (Mencegah terhapus karena disabled)
        if (!checkedMembers.includes(currentUserId.toString())) {
             checkedMembers.push(currentUserId.toString());
        }

        if (checkedMembers.length === 0) {
            alert_toast("Grup harus memiliki setidaknya satu anggota.", "error");
            return;
        }
        
        // 3. Serialisasi data form (tanpa checkbox, karena kita akan kirim manual)
        let formData = $(this).serializeArray();
        
        // Hapus entri user_ids[] yang mungkin terkirim dari checkbox lain
        formData = formData.filter(item => item.name !== 'user_ids[]');
        
        // Tambahkan user_ids[] dari array yang sudah diperbaiki
        checkedMembers.forEach(id => {
            formData.push({ name: 'user_ids[]', value: id });
        });

        // Cek apakah minimal ada anggota yang tersisa
        if (checkedMembers.length === 0) {
            alert_toast("Grup harus memiliki setidaknya satu anggota.", "error");
            return;
        }

        $.ajax({
            url: 'ajax.php?action=update_group_settings', 
            method: 'POST',
            data: $.param(formData), // Mengubah array objek menjadi string URL-encoded
            success: function(resp) {
                // ... (Logika success/error sama)
                if (resp == 1) {
                    alert_toast("Pengaturan grup berhasil diubah!", "success");
                    $('#groupSettingsModal').modal('hide');
                    currentRecipientName = $('#settings_group_name').val();
                    loadChatMessages(true); 
                    loadChatSidebar(false); 
                } else {
                    alert_toast("Gagal menyimpan perubahan grup: " + resp, "error");
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan koneksi saat menyimpan pengaturan.", "error");
            }
        });
    });

    // F. Tangani Hapus Grup
    $('#delete_group_btn').click(function() {
        if (confirm("Apakah Anda yakin ingin menghapus grup ini secara permanen? Tindakan ini tidak dapat dibatalkan.")) {
            $.ajax({
                url: 'ajax.php?action=delete_group', // 💡 Harus ada di ajax.php
                method: 'POST',
                data: { group_id: currentGroupId },
                success: function(resp) {
                    if (resp == 1) {
                        alert_toast("Grup berhasil dihapus.", "success");
                        $('#groupSettingsModal').modal('hide');
                        
                        // Reset state chat setelah dihapus
                        currentThreadId = '';
                        currentRecipientId = '';
                        currentGroupId = '';
                        currentChatType = '';
                        $('#recipient_header h5').text('Choose contact or group');
                        $('#chat_display').html('<div class="text-center p-5 text-muted">Grup telah dihapus. Pilih kontak atau grup lain.</div>');
                        $('#chat_input_container').hide();
                        $('#group_settings_btn').hide();
                        loadChatSidebar(true); 
                    } else {
                        alert_toast("Gagal menghapus grup: " + resp, "error");
                    }
                },
                error: function() {
                    alert_toast("Terjadi kesalahan koneksi saat menghapus grup.", "error");
                }
            });
        }
    });

    // G. Reset Modal Create Group saat ditutup
    $('#groupModal').on('hidden.bs.modal', function() {
        $('#group_form')[0].reset();
        loadChatSidebar(false); // Reload untuk memastikan daftar anggota terbaru
    });
 
    $('body').on('click', '.member-list-item', function(e) {
        if (!$(e.target).is('input[type="checkbox"]')) {
            const checkbox = $(this).find('input[type="checkbox"]');
            // Toggle status centang pada checkbox
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change'); 
            e.preventDefault(); // Mencegah tindakan default label
        }
    });


    // --- POLLING & INITIAL CALL ---
    
    // PSEUDO-REALTIME (POLLING)
    setInterval(function(){
        // Update chat aktif (hanya jika ada thread/group aktif)
        if(currentThreadId || currentGroupId) {
             loadChatMessages(false); 
        }
        
        // Update daftar kontak untuk notifikasi dan urutan
        loadChatSidebar(false);
        
    }, 3000); 

    // Panggil fungsi awal
    loadChatSidebar(true);
</script>