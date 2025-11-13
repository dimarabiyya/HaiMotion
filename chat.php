<?php 
// Pastikan file db_connect dan session sudah ter-include di index.php
if(!isset($_SESSION['login_id'])) {
    header("location:login.php");
    exit();
}
$current_user_id = $_SESSION['login_id'];

// Ambil thread_id dan recipient_id dari URL jika ada (misalnya dari notifikasi)
$initial_thread_id = isset($_GET['thread_id']) ? $_GET['thread_id'] : '';
$initial_recipient_id = isset($_GET['recipient_id']) ? $_GET['recipient_id'] : '';

// Jika ada thread_id, kita perlu mencari user lawan bicaranya untuk inisialisasi
if ($initial_thread_id && !$initial_recipient_id) {
    // Ambil data thread untuk menentukan recipient_id
    $thread_q = $conn->query("SELECT user1_id, user2_id FROM chat_threads WHERE id = '{$initial_thread_id}'");
    if ($thread_q && $thread_q->num_rows > 0) {
        $thread_data = $thread_q->fetch_assoc();
        $initial_recipient_id = ($thread_data['user1_id'] == $current_user_id) ? $thread_data['user2_id'] : $thread_data['user1_id'];
    }
}
?>

<style>
    .chat-container {
        display: flex;
        height: 80vh; 
    }
    .user-list-sidebar {
        width: 300px;
        border-right: 1px solid #ccc;
        overflow-y: auto;
        padding: 0;
        background-color: #f8f9fa;
        min-height: 100%;
    }
    .user-item {
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
    }
    .user-item:hover, .user-item.active {
        background-color: #e9ecef;
    }
    .chat-area {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        background-color: #fff;
    }
    .chat-box {
        flex-grow: 1;
        overflow-y: auto;
        padding: 15px;
        height: 1px; /* Penting agar flex-grow bekerja */
    }
    .message-item {
        margin-bottom: 15px;
        padding: 8px 12px;
        border-radius: 10px;
        max-width: 70%;
        position: relative;
    }
    .incoming-message {
        background-color: #f1f0f0;
        margin-right: auto;
        text-align: left;
    }
    .outgoing-message {
        background-color: #B75301;
        color: white;
        margin-left: auto;
        text-align: left;
    }
    .sender-name {
        font-weight: bold;
        margin-bottom: 3px;
        font-size: 0.95em;
    }
    .timestamp {
        font-size: 0.7em;
        opacity: 0.7;
        display: block;
        margin-top: 5px;
        text-align: right;
        color: #6c757d;
    }
    .outgoing-message .timestamp {
        color: #fff;
        opacity: 0.8;
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
    .default-avatar {
        background-color: #6c757d;
        color: white;
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        font-size: 1.2em;
        text-transform: uppercase;
        margin-right: 10px;
    }
</style>
    <h3 class="m-0 mb-3">Massanger</h3>
    <div class="p-0 chat-container container-fluid border rounded-0 shadow-sm">
            <div class="user-list-sidebar">
                <div class="p-3 border-bottom">
                    <h5 class="m-0">Contact</h5>
                </div>
                <div id="user_list_display"></div>
            </div>

            <div class="chat-area">
                <div class="p-3 border-bottom" id="recipient_header">
                    <h5 class="m-0 text-muted">Choose contact to chat</h5>
                </div>
                <div class="chat-box" id="chat_display">
                    <div class="text-center p-5 text-muted">Choose contact to chat</div>
                </div>
                
                <div class="p-3 border-top bg-light" id="chat_input_container" style="display: none;">
                    <form id="chat_form">
                        <input type="hidden" name="thread_id" id="form_thread_id">
                        <div class="input-group">
                            <input type="text" name="message_content" id="message_content" class="form-control" placeholder="Start conversation here!" required>
                            <div class="input-group-append">
                                <button class="btn text-white" style="background-color:#B75301" type="submit">Send</button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Use Metion Tag : '@user', '#project', '!task'</small>
                    </form>
                </div>
            </div>
    </div>

<script>
    // Struktur data untuk memetakan ID ke Nama dan Nama ke ID
    let lookupData = { users: {}, projects: {}, tasks: {} }; // ID -> Name
    let reverseLookup = { users: {}, projects: {}, tasks: {} }; // Name -> ID
    
    let currentThreadId = '<?php echo $initial_thread_id; ?>';
    let currentRecipientId = '<?php echo $initial_recipient_id; ?>';
    let currentRecipientName = '';
    const currentUserId = <?php echo $current_user_id; ?>;
    
    // Helper function untuk menghindari masalah pada regex jika nama mengandung karakter khusus
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // 1. Fungsi untuk memformat sintaks mention menjadi HTML tag
    function formatMessage(message) {
        // Mapping entitas ke URL dasar (sesuaikan dengan URL view anda)
        const links = {
            'user': 'index.php?page=view_user&id=',
            'project': 'index.php?page=view_project&id=',
            'task': 'index.php?page=view_task&id='
        };
        
        let formattedMessage = message;

        // Urutkan nama berdasarkan panjangnya (terpanjang lebih dulu)
        // Ini untuk memastikan nama yang lebih panjang (misal: "Sample Project 102") di-match sebelum nama yang lebih pendek (misal: "Sample Project")
        const sortedUserNames = Object.keys(reverseLookup.users).sort((a, b) => b.length - a.length);
        const sortedProjectNames = Object.keys(reverseLookup.projects).sort((a, b) => b.length - a.length);
        const sortedTaskNames = Object.keys(reverseLookup.tasks).sort((a, b) => b.length - a.length);

        // A. Parse User Mentions (@Nama User)
        // Pola: @<Nama> diikuti spasi atau akhir string
        sortedUserNames.forEach(name => {
            const escapedName = escapeRegExp(name);
            const userRegex = new RegExp(`@(${escapedName})(?=\\s|$)`, 'gi');
            
            if (userRegex.test(formattedMessage)) {
                const id = reverseLookup.users[name];
                // Mengganti hanya teks mentah dengan tag yang menunjukkan nama yang sama
                const replacement = `<a href="${links.user}${id}" class="mention-tag" target="_blank">@${name}</a>`;
                formattedMessage = formattedMessage.replace(userRegex, replacement);
            }
        });
        
        // B. Parse Project Mentions (#Nama Project)
        // Pola: #<Nama> diikuti spasi atau akhir string
        sortedProjectNames.forEach(name => {
            const escapedName = escapeRegExp(name);
            const projectRegex = new RegExp(`#(${escapedName})(?=\\s|$)`, 'gi');
            
            if (projectRegex.test(formattedMessage)) {
                const id = reverseLookup.projects[name];
                const replacement = `<a href="${links.project}${id}" class="mention-tag" target="_blank">#${name}</a>`;
                formattedMessage = formattedMessage.replace(projectRegex, replacement);
            }
        });

        // C. Parse Task Mentions (!Nama Task)
        // Pola: !<Nama> diikuti spasi atau akhir string
        sortedTaskNames.forEach(name => {
            const escapedName = escapeRegExp(name);
            const taskRegex = new RegExp(`!(${escapedName})(?=\\s|$)`, 'gi');
            
            if (taskRegex.test(formattedMessage)) {
                const id = reverseLookup.tasks[name];
                const replacement = `<a href="${links.task}${id}" class="mention-tag" target="_blank">!${name}</a>`;
                formattedMessage = formattedMessage.replace(taskRegex, replacement);
            }
        });

        return formattedMessage;
    }

    // 2. Memuat pesan chat untuk thread aktif
    function loadChatMessages() {
        if (!currentThreadId) {
            $('#chat_display').html('<div class="text-center p-5 text-muted">Pilih kontak di sebelah kiri untuk memulai percakapan.</div>');
            return;
        }

        var chatBox = $('#chat_display');
        chatBox.html('<div class="text-center p-3 text-muted">Loading...</div>');
        
        $('#recipient_header h5').html('<i class="fas fa-comment-dots mr-2"></i> Chat with ' + currentRecipientName);
        $('#chat_input_container').show();

        $.ajax({
            url: 'ajax.php?action=get_personal_chat_messages',
            method: 'POST',
            data: { thread_id: currentThreadId },
            dataType: 'json',
            success: function(response) {
                
                // --- BAGIAN PENTING: MEMBANGUN REVERSE LOOKUP MAP (Name -> ID) ---
                lookupData.users = response.users || {};
                lookupData.projects = response.projects || {};
                lookupData.tasks = response.tasks || {};
                
                reverseLookup.users = {};
                for (const id in lookupData.users) {
                    // Simpan nama yang sudah di-trim untuk pencarian
                    reverseLookup.users[lookupData.users[id].trim()] = id;
                }
                reverseLookup.projects = {};
                for (const id in lookupData.projects) {
                    reverseLookup.projects[lookupData.projects[id].trim()] = id;
                }
                reverseLookup.tasks = {};
                for (const id in lookupData.tasks) {
                    reverseLookup.tasks[lookupData.tasks[id].trim()] = id;
                }
                // -----------------------------------------------------------
                
                chatBox.empty();

                if (response.messages && response.messages.length > 0) {
                    response.messages.forEach(function(msg) {
                        var isOutgoing = msg.sender_id == currentUserId;
                        var messageClass = isOutgoing ? 'outgoing-message' : 'incoming-message';
                        var senderName = isOutgoing ? 'Anda' : msg.sender_name;
                        
                        // Gunakan formatMessage untuk memproses mention nama
                        var formattedContent = formatMessage(msg.message_content);

                        var html = `
                            <div class="message-item ${messageClass}">
                                <span class="sender-name">${senderName}</span>
                                <p class="m-0">${formattedContent}</p>
                                <span class="timestamp">${msg.created_at}</span>
                            </div>
                        `;
                        chatBox.append(html);
                    });
                } else {
                    chatBox.html('<div class="text-center p-5 text-muted">Start firts Conversation</div>');
                }
                
                chatBox.scrollTop(chatBox[0].scrollHeight);
            },
            error: function() {
                chatBox.html('<div class="text-center p-5 text-danger">Error</div>');
            }
        });
    }
    
    // 3. Mendapatkan ID thread atau membuat yang baru
    function getOrCreateThread() {
        $.ajax({
            url: 'ajax.php?action=get_or_create_thread_id',
            method: 'POST',
            data: { user2_id: currentRecipientId },
            success: function(threadId) {
                if (threadId > 0) {
                    currentThreadId = threadId;
                    $('#form_thread_id').val(threadId);
                    loadChatMessages();
                } else {
                    alert_toast("Gagal membuat atau mendapatkan thread chat.", "error");
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan koneksi saat membuat thread.", "error");
            }
        });
    }

    // 4. Memuat daftar pengguna di sidebar
    function loadUserList() {
        $.ajax({
            url: 'ajax.php?action=get_all_users_for_chat',
            method: 'GET',
            dataType: 'json',
            success: function(users) {
                $('#user_list_display').empty();
                users.forEach(function(user) {
                    // Simpan mapping ID ke Nama untuk lookup
                    lookupData.users[user.id] = user.name.trim(); 
                    
                    var activeClass = (user.id == currentRecipientId) ? 'active' : '';
                    var avatarHtml;
                    if (user.avatar && user.avatar !== '') {
                        avatarHtml = `<img src="assets/uploads/${user.avatar}" class="img-circle elevation-2 mr-2" alt="User Image" style="width: 35px; height: 35px; object-fit: cover;">`;
                    } else {
                        var initials = user.name.split(' ').map(n => n[0]).join('').substring(0, 2);
                        avatarHtml = `<div class="default-avatar">${initials}</div>`;
                    }

                    var userHtml = `
                        <div class="user-item d-flex align-items-center ${activeClass}" data-user-id="${user.id}" data-user-name="${user.name.trim()}">
                            ${avatarHtml}
                            <span class="text-dark">${user.name}</span>
                        </div>
                    `;
                    $('#user_list_display').append(userHtml);
                });

                // Pasang event listener untuk klik pengguna
                $('.user-item').click(function() {
                    $('.user-item').removeClass('active');
                    $(this).addClass('active');
                    
                    currentRecipientId = $(this).data('user-id');
                    currentRecipientName = $(this).data('user-name');
                    
                    getOrCreateThread();
                });
                
                // Jika ada inisialisasi dari URL, muat chat secara otomatis
                if (currentRecipientId) {
                    var selectedUser = $(`.user-item[data-user-id="${currentRecipientId}"]`);
                    currentRecipientName = selectedUser.data('user-name');
                    selectedUser.addClass('active');
                    getOrCreateThread();
                } else {
                     // Jika tidak ada recipient awal, nonaktifkan input chat
                    $('#chat_input_container').hide();
                }
                
                // NOTE: Setelah user list dimuat, kita perlu memuat data Project dan Task 
                // untuk inisialisasi reverseLookup Project/Task yang digunakan di formatMessage.
                loadProjectTaskLookup();
            },
            error: function() {
                $('#user_list_display').html('<div class="text-center p-3 text-danger">Gagal memuat daftar pengguna.</div>');
            }
        });
    }

    // Fungsi tambahan untuk memuat data Project dan Task
    function loadProjectTaskLookup() {
        // Ambil data Project dan Task dari backend untuk lookup
        $.ajax({
            url: 'ajax.php?action=get_personal_chat_messages', // Gunakan endpoint yang sama untuk ambil lookup data
            method: 'POST',
            data: { thread_id: 0 }, // Kirim thread_id 0 atau null untuk ambil lookup data saja
            dataType: 'json',
            success: function(response) {
                
                lookupData.projects = response.projects || {};
                lookupData.tasks = response.tasks || {};
                
                reverseLookup.projects = {};
                for (const id in lookupData.projects) {
                    reverseLookup.projects[lookupData.projects[id].trim()] = id;
                }
                reverseLookup.tasks = {};
                for (const id in lookupData.tasks) {
                    reverseLookup.tasks[lookupData.tasks[id].trim()] = id;
                }
                
                // Jika ada chat yang diinisialisasi dari URL, load pesan
                if (currentThreadId) {
                    loadChatMessages();
                }
            },
            error: function() {
                console.error("Gagal memuat data Project/Task untuk lookup.");
            }
        });
    }
    
    // 5. Tangani pengiriman pesan (Tidak Berubah)
    $('#chat_form').submit(function(e) {
        e.preventDefault();
        
        if (!currentThreadId) {
            alert_toast("Pilih kontak sebelum mengirim pesan.", "warning");
            return;
        }

        var messageContent = $('#message_content').val().trim();
        if (messageContent === '') return;

        // Di sini, pesan dengan nama mentah dikirim ke server.
        // Server hanya menyimpan, dan frontend yang memproses display.
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'ajax.php?action=save_personal_chat_message',
            method: 'POST',
            data: formData,
            success: function(resp) {
                if (resp == 1) {
                    $('#message_content').val('');
                    loadChatMessages(); // Muat ulang pesan
                } else {
                    alert_toast("Gagal menyimpan pesan: " + resp, "error");
                }
            },
            error: function() {
                alert_toast("Terjadi kesalahan saat mengirim pesan.", "error");
            }
        });
    });

    // Panggil fungsi awal
    loadUserList();
</script>   