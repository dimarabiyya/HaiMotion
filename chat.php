<?php 
// FILE: chat.php (REVISI LENGKAP MESSENGER)
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

// ➡️ DECODE ID DARI URL & PASTIKAN HANYA ID NUMERIK YANG DIGUNAKAN
$initial_thread_id = null;
if (!empty($encoded_thread_id)) {
    // Asumsi fungsi decode_id() tersedia dari db_connect.php
    $decoded_id = function_exists('decode_id') ? decode_id($encoded_thread_id) : $encoded_thread_id; 
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_thread_id = $decoded_id;
    }
}

$initial_recipient_id = null;
if (!empty($encoded_recipient_id)) {
    $decoded_id = function_exists('decode_id') ? decode_id($encoded_recipient_id) : $encoded_recipient_id;
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_recipient_id = $decoded_id;
    }
}

// Jika ada thread_id yang berhasil didekode dan recipient_id belum ada
if ($initial_thread_id && !$initial_recipient_id && isset($conn)) {
    // Ambil data thread untuk menentukan recipient_id (gunakan ID numerik $initial_thread_id)
    $thread_q = $conn->query("SELECT user1_id, user2_id FROM chat_threads WHERE id = '{$initial_thread_id}'");
    if ($thread_q && $thread_q->num_rows > 0) {
        $thread_data = $thread_q->fetch_assoc();
        $initial_recipient_id = ($thread_data['user1_id'] == $current_user_id) ? $thread_data['user2_id'] : $thread_data['user1_id'];
    } else {
        $initial_thread_id = '';
        $initial_recipient_id = '';
    }
}

// Teruskan nilai ke JavaScript, pastikan string kosong jika null/gagal
$initial_thread_id_js = $initial_thread_id === null ? '' : (string)$initial_thread_id;
$initial_recipient_id_js = $initial_recipient_id === null ? '' : (string)$initial_recipient_id;

?>

<style>
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
        display: flex; /* Penting untuk layout avatar dan teks */
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
        background-color: #f0f0f0; /* Latar belakang abu-abu muda */
    }
    .chat-header {
        background-color: #ffffff; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        z-index: 10;
        padding: 10px 15px;
    }
    .chat-box {
        flex-grow: 1;
        overflow-y: auto;
        padding: 20px 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        height: 1px;
        /* Opsional: Terapkan latar belakang chat image di sini jika ada */
        /* background-image: url('assets/img/chat-bg.jpg'); */
        /* background-size: cover; */
    }
    
    /* --- MESSAGE BUBBLES --- */
    .message-item {
        margin: 5px 0;
        padding: 8px 12px;
        border-radius: 18px; /* Lebih membulat */
        max-width: 65%; 
        position: relative;
        word-wrap: break-word; 
        font-size: 0.95em; /* Ukuran teks normal */
    }
    .incoming-message {
        background-color: #ffffff; /* Putih bersih */
        margin-right: auto;
        border-bottom-left-radius: 4px; /* Sudut bawah dekat pengirim lebih tajam */
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
    }
    .outgoing-message {
        background-color: #B75301; /* Warna tema (Merah bata/Oranye tua) */
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 4px; /* Sudut bawah dekat pengirim lebih tajam */
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
    }
    
    .sender-name {
        font-weight: bold;
        margin-bottom: 2px;
        font-size: 0.85em; 
        opacity: 0.8;
        color: #000000; /* Hitam atau warna yang kontras */
    }
    .outgoing-message .sender-name {
        color: #ffffff; /* Putih untuk pesan keluar */
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
        border-radius: 20px !important; /* Input membulat */
        padding: 10px 15px;
        min-height: 44px;
        line-height: 24px;
    }
    #chat_input_container .input-group-append button {
        border-radius: 20px !important;
        margin-left: 5px;
    }
    
    /* --- AVATAR & MENTION TAGS & BADGE --- */
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
    .mention-tag {
        background-color: #077bffff; 
        color: #ffffffff;
        padding: 1px 4px;
        border-radius: 4px;
        font-weight: bold;
        text-decoration: none;
        font-size: 0.9em;
    }
    .badge-danger {
        background-color: #dc3545; 
        color: white;
        font-size: 0.7rem;
        padding: 4px 6px;
        line-height: 1;
        margin-left: 5px; /* Jarak dari teks */
    }
    /* Bold class for unread messages */
    .user-item .font-weight-bold {
        font-weight: bold !important;
    }
</style>
<h3 class="m-0 mb-3">Messenger</h3>
    <div class="p-0 chat-container container-fluid">
            
            <div class="user-list-sidebar">
                <div class="p-3 border-bottom bg-white sticky-top" style="z-index: 5;">
                    <h5 class="m-0 text-dark font-weight-bold">Contacts</h5>
                </div>
                <div id="user_list_display">
                    </div>
            </div>

            <div class="chat-area">
                <div class="chat-header" id="recipient_header">
                    <h5 class="m-0 text-muted">Choose contact</h5>
                </div>
                
                <div class="chat-box" id="chat_display">
                    <div class="text-center p-5 text-muted">Choose contact for start conversation</div>
                </div>
                
                <div id="chat_input_container" style="display: none;">
                    <form id="chat_form">
                        <input type="hidden" name="thread_id" id="form_thread_id">
                        <div class="input-group">
                            <input type="text" name="message_content" id="message_content" class="form-control" placeholder="Write messenge.." required>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/id.min.js"></script> 
<script>
    moment.locale('id'); 

    // ... (Variabel Global SAMA) ...
    let lookupData = { users: {}, projects: {}, tasks: {} }; 
    let reverseLookup = { users: {}, projects: {}, tasks: {} }; 
    
    let currentThreadId = '<?php echo $initial_thread_id_js; ?>'; 
    let currentRecipientId = '<?php echo $initial_recipient_id_js; ?>';
    let currentRecipientName = '';
    const currentUserId = <?php echo $current_user_id; ?>;
    
    let isChatBoxScrolledToBottom = true; 
    
    // ... (Fungsi escapeRegExp SAMA) ...
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // ... (Fungsi formatMessage SAMA) ...
    function formatMessage(message) {
        // ... (Kode formatMessage SAMA) ...
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
    
    // Listener untuk scroll chat box
    $('#chat_display').scroll(function() {
        const chatBox = $(this);
        // Tentukan jika scroll berada dalam jarak 50px dari bawah
        isChatBoxScrolledToBottom = (chatBox.scrollTop() + chatBox.innerHeight() >= chatBox[0].scrollHeight - 50);
    });
    
    function buildChatHtml(messages) {
        var html = '';
        if (messages && messages.length > 0) {
            messages.forEach(function(msg) {
                var isOutgoing = msg.sender_id == currentUserId;
                var messageClass = isOutgoing ? 'outgoing-message' : 'incoming-message';
                var senderNameHtml = !isOutgoing ? `<span class="sender-name">${msg.sender_name}</span>` : '';
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


    // 2. Memuat pesan chat untuk thread aktif (memperhitungkan realtime)
    function loadChatMessages(isManualLoad = true) {
        if (!currentThreadId) {
            if(isManualLoad) $('#chat_display').html('<div class="text-center p-5 text-muted">Pilih kontak di sebelah kiri untuk memulai percakapan.</div>');
            $('#chat_input_container').hide();
            return;
        }

        var chatBox = $('#chat_display');
        if(isManualLoad) chatBox.html('<div class="text-center p-3 text-muted">Loading...</div>');
        
        $('#recipient_header h5').html('<div class="d-flex align-items-center"><i class="fas fa-comment-dots mr-2"></i>' + currentRecipientName + '</div>');
        $('#chat_input_container').show();
        $('#form_thread_id').val(currentThreadId);

        $.ajax({
            url: 'ajax.php?action=get_personal_chat_messages',
            method: 'POST',
            data: { thread_id: currentThreadId },
            dataType: 'json',
            success: function(response) {
                
                // ... (Update Lookup Maps SAMA) ...
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

                // HANYA update jika konten berubah ATAU ini adalah loading manual/initial
                if (isManualLoad || chatBox.data('last-content') !== newContentHtml) {
                    chatBox.empty().append(newContentHtml);
                    chatBox.data('last-content', newContentHtml);
                    
                    if (isManualLoad || isChatBoxScrolledToBottom) {
                        chatBox.scrollTop(chatBox[0].scrollHeight);
                    }
                }

                // Setelah memuat pesan, panggil loadUserList untuk memastikan update badge/urutan telah terjadi
                loadUserList(false);
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (loadChatMessages):", status, error, xhr.responseText);
                if(isManualLoad) chatBox.html('<div class="text-center p-5 text-danger">Error loading messages. Check console for details.</div>');
            }
        });
    }
    
    // 3. Mendapatkan ID thread atau membuat yang baru (SAMA)
    function getOrCreateThread() {
        if (!currentRecipientId || currentRecipientId === currentUserId) return;
        
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

    // 4. Memuat daftar pengguna di sidebar (dengan urutan & notifikasi)
    function loadUserList(initialLoad = true) {
        $.ajax({
            url: 'ajax.php?action=get_all_users_for_chat',
            method: 'GET',
            dataType: 'json',
            success: function(users) {
                
                // Cek jika ada error dari backend
                if(users.error) {
                     $('#user_list_display').html('<div class="text-center p-3 text-danger">Error SQL: '+users.debug+'</div>');
                     console.error("Backend Error:", users.debug);
                     return;
                }
                
                $('#user_list_display').empty();
                
                if (users.length === 0) {
                    $('#user_list_display').html('<div class="text-center p-3 text-muted">No other users found.</div>');
                    if(initialLoad) $('#chat_input_container').hide();
                    return;
                }
                
                // KRITIS: Logika Pengurutan
                users.sort((a, b) => {
                    // Prioritas 1: Pesan belum dibaca (unread_count)
                    if (a.unread_count > 0 && b.unread_count === 0) return -1;
                    if (a.unread_count === 0 && b.unread_count > 0) return 1;

                    // Prioritas 2: Timestamp pesan terakhir (terbaru di atas)
                    const timeA = a.last_message_timestamp ? new Date(a.last_message_timestamp).getTime() : 0;
                    const timeB = b.last_message_timestamp ? new Date(b.last_message_timestamp).getTime() : 0;
                    return timeB - timeA;
                });

                users.forEach(function(user) {
                    if (user.id == currentUserId) return; 

                    var activeClass = (user.id == currentRecipientId) ? 'active' : '';
                    var avatarHtml;
                    
                    if (user.avatar && user.avatar !== '') {
                        avatarHtml = `<img src="assets/uploads/${user.avatar}" class="img-circle elevation-2 mr-2" alt="User Image" style="width: 40px; height: 40px; object-fit: cover;">`;
                    } else {
                        var initials = (user.name || 'NN').split(' ').map(n => n[0]).join('').substring(0, 2);
                        avatarHtml = `<div class="default-avatar mr-2">${initials}</div>`;
                    }

                    // LOGIKA NOTIFIKASI & PESAN TERAKHIR
                    var unreadBadge = '';
                    var nameBold = '';
                    var contentBold = '';
                    if (user.unread_count && user.unread_count > 0) {
                        unreadBadge = `<span class="badge badge-pill badge-danger">${user.unread_count}</span>`;
                        nameBold = 'font-weight-bold';
                        contentBold = 'font-weight-bold';
                    }
                    
                    var lastMessageContent = user.last_message_content ? 
                        user.last_message_content.substring(0, 30) + (user.last_message_content.length > 30 ? '...' : '') : 
                        '<span class="text-muted">Start Conversation</span>'; 
                    
                    var lastMessageTime = user.last_message_timestamp ? 
                        moment(user.last_message_timestamp).fromNow() : ''; 
                        

                    var userHtml = `
                        <div class="user-item d-flex ${activeClass}" 
                             data-user-id="${user.id}" 
                             data-user-name="${user.name.trim()}">
                            
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

                
                // Inisialisasi dari URL
                if (currentRecipientId !== '' && initialLoad) {
                    var selectedUser = $(`.user-item[data-user-id="${currentRecipientId}"]`);
                    if(selectedUser.length > 0) {
                       currentRecipientName = selectedUser.data('user-name');
                       selectedUser.addClass('active');
                       getOrCreateThread();
                       
                       selectedUser.find('.badge-danger').remove();
                       selectedUser.find('.font-weight-bold').removeClass('font-weight-bold');
                       
                    } else {
                       $('#recipient_header h5').html('<div class="d-flex align-items-center"><i class="fas fa-comment-dots mr-2"></i>Recipient tidak ditemukan</div>');
                       $('#chat_input_container').hide();
                    }
                } else if(currentRecipientId === '') {
                     $('#chat_input_container').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (Load User List):", status, error, xhr.responseText);
                $('#user_list_display').html('<div class="text-center p-3 text-danger">Gagal memuat daftar pengguna. Check console untuk detail.</div>');
                if(initialLoad) $('#chat_input_container').hide();
            }
        });
    }

    // 5. Tangani Klik Pindah Kontak (Menggunakan Delegasi Event)
    $('#user_list_display').on('click', '.user-item', function() {
        // Logika yang sama dari sebelumnya, tapi sekarang lebih stabil.
        $('.user-item').removeClass('active');
        $(this).addClass('active');
        
        currentRecipientId = $(this).data('user-id').toString();
        currentRecipientName = $(this).data('user-name');
        
        // Hapus visual notif saat diklik
        $(this).find('.badge-danger').remove();
        $(this).find('.font-weight-bold').removeClass('font-weight-bold');
        
        currentThreadId = ''; 
        getOrCreateThread();
    });

    // 5. Tangani pengiriman pesan (SAMA)
    $('#chat_form').submit(function(e) {
        e.preventDefault();
        
        if (!currentThreadId) {
            alert_toast("Pilih kontak sebelum mengirim pesan.", "warning");
            return;
        }

        var messageContent = $('#message_content').val().trim();
        if (messageContent === '') return;

        var formData = $(this).serialize();
        
        $.ajax({
            url: 'ajax.php?action=save_personal_chat_message',
            method: 'POST',
            data: formData,
            success: function(resp) {
                if (resp == 1) {
                    $('#message_content').val('');
                    loadChatMessages(true); 
                    loadUserList(false); // Update daftar kontak agar pesan terbaru pindah ke atas
                } else {
                    alert_toast("Gagal menyimpan pesan: " + resp, "error");
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan saat mengirim pesan.", "error");
            }
        });
    });

    // 6. PSEUDO-REALTIME (POLLING)
    // Refresh otomatis chat aktif dan daftar kontak setiap 3 detik
    setInterval(function(){
        // Update chat aktif (hanya jika ada thread aktif)
        if(currentThreadId) {
             loadChatMessages(false); 
        }
        
        // Update daftar kontak untuk notifikasi dan urutan
        loadUserList(false);
        
    }, 3000); 

    // Panggil fungsi awal
    loadUserList();
</script>