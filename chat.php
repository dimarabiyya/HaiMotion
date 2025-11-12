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
    /* Styling dasar untuk tampilan chat */
    .chat-container {
        display: flex;
        height: 80vh; /* Sesuaikan tinggi */
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
        background-color: #007bff;
        color: white;
        margin-left: auto;
        text-align: left;
    }
    .sender-name {
        font-weight: bold;
        margin-bottom: 3px;
        font-size: 0.85em;
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
        background-color: #ffc107; 
        color: #333;
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

<div class="container-fluid h-100">
    <div class="card card-outline card-info h-100">
        <div class="card-header">
            <h3 class="card-title">Chat Personal (DM)</h3>
        </div>
        <div class="card-body p-0 chat-container">
            <div class="user-list-sidebar">
                <div class="p-3 border-bottom">
                    <h5 class="m-0">Kontak</h5>
                </div>
                <div id="user_list_display">
                    </div>
            </div>

            <div class="chat-area">
                <div class="p-3 border-bottom" id="recipient_header">
                    <h5 class="m-0 text-muted">Pilih kontak di sebelah kiri untuk memulai chat.</h5>
                </div>
                <div class="chat-box" id="chat_display">
                    <div class="text-center p-5 text-muted">Pilih kontak di sebelah kiri untuk memulai percakapan.</div>
                </div>
                
                <div class="p-3 border-top bg-light" id="chat_input_container" style="display: none;">
                    <form id="chat_form">
                        <input type="hidden" name="thread_id" id="form_thread_id">
                        <div class="input-group">
                            <input type="text" name="message_content" id="message_content" class="form-control" placeholder="Ketik pesan Anda (@user-ID, #project-ID, !task-ID untuk mention)" required>
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">Kirim</button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Fitur mention tag: Gunakan `@user-ID`, `#project-ID`, atau `!task-ID` untuk menyertakan tautan di pesan.</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let lookupData = { users: {}, projects: {}, tasks: {} };
    let currentThreadId = '<?php echo $initial_thread_id; ?>';
    let currentRecipientId = '<?php echo $initial_recipient_id; ?>';
    let currentRecipientName = '';
    const currentUserId = <?php echo $current_user_id; ?>;
    
    // 1. Fungsi untuk memformat sintaks mention menjadi HTML tag (Client-Side Rendering)
    function formatMessage(message) {
        const links = {
            'user': 'index.php?page=view_user&id=',
            'project': 'index.php?page=view_project&id=',
            'task': 'index.php?page=view_task&id='
        };
        
        const regex = /(@user-(\d+))|(#project-(\d+))|(!task-(\d+))/gi;
        
        return message.replace(regex, (match, p1, p2, p3, p4, p5, p6) => {
            let type, id, name, link;

            if (p2) { 
                type = 'user';
                id = p2;
                name = lookupData.users[id] || `User ID:${id}`;
                link = links.user + id;
            } else if (p4) { 
                type = 'project';
                id = p4;
                name = lookupData.projects[id] || `Project ID:${id}`;
                link = links.project + id;
            } else if (p6) { 
                type = 'task';
                id = p6;
                name = lookupData.tasks[id] || `Task ID:${id}`;
                link = links.task + id;
            }
            // Mengganti match dengan link HTML
            return `<a href="${link}" class="mention-tag" target="_blank">${match} (${name.split(' ')[0]})</a>`;
        });
    }

    // 2. Memuat pesan chat untuk thread aktif
    function loadChatMessages() {
        if (!currentThreadId) {
            $('#chat_display').html('<div class="text-center p-5 text-muted">Pilih kontak di sebelah kiri untuk memulai percakapan.</div>');
            return;
        }

        var chatBox = $('#chat_display');
        chatBox.html('<div class="text-center p-3 text-muted">Memuat pesan...</div>');
        
        // Update header dan tampilkan input
        $('#recipient_header h5').html('<i class="fas fa-comment-dots mr-2"></i> Chat dengan ' + currentRecipientName);
        $('#chat_input_container').show();

        $.ajax({
            url: 'ajax.php?action=get_personal_chat_messages',
            method: 'POST',
            data: { thread_id: currentThreadId },
            dataType: 'json',
            success: function(response) {
                // Update lookup data
                lookupData.users = response.users || {};
                lookupData.projects = response.projects || {};
                lookupData.tasks = response.tasks || {};
                
                chatBox.empty();

                if (response.messages && response.messages.length > 0) {
                    response.messages.forEach(function(msg) {
                        var isOutgoing = msg.sender_id == currentUserId;
                        var messageClass = isOutgoing ? 'outgoing-message' : 'incoming-message';
                        var senderName = isOutgoing ? 'Anda' : msg.sender_name;
                        
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
                    chatBox.html('<div class="text-center p-5 text-muted">Belum ada pesan. Kirim pesan pertama Anda!</div>');
                }
                
                chatBox.scrollTop(chatBox[0].scrollHeight);
            },
            error: function() {
                chatBox.html('<div class="text-center p-5 text-danger">Gagal memuat pesan.</div>');
            }
        });
    }
    
    // 3. Mendapatkan ID thread atau membuat yang baru
    function getOrCreateThread() {
        // start_load(); // Jika tidak ingin overlay, bisa diabaikan
        $.ajax({
            url: 'ajax.php?action=get_or_create_thread_id',
            method: 'POST',
            data: { user2_id: currentRecipientId },
            success: function(threadId) {
                // end_load();
                if (threadId > 0) {
                    currentThreadId = threadId;
                    $('#form_thread_id').val(threadId);
                    loadChatMessages();
                } else {
                    alert_toast("Gagal membuat atau mendapatkan thread chat.", "error");
                }
            },
            error: function() {
                // end_load();
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
                    lookupData.users[user.id] = user.name; 
                    
                    var activeClass = (user.id == currentRecipientId) ? 'active' : '';
                    var avatarHtml;
                    if (user.avatar && user.avatar !== '') {
                        avatarHtml = `<img src="assets/uploads/${user.avatar}" class="img-circle elevation-2 mr-2" alt="User Image" style="width: 35px; height: 35px; object-fit: cover;">`;
                    } else {
                        var initials = user.name.split(' ').map(n => n[0]).join('').substring(0, 2);
                        avatarHtml = `<div class="default-avatar">${initials}</div>`;
                    }

                    var userHtml = `
                        <div class="user-item d-flex align-items-center ${activeClass}" data-user-id="${user.id}" data-user-name="${user.name}">
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
                    
                    // Panggil fungsi untuk mendapatkan thread ID atau membuat yang baru
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
            },
            error: function() {
                $('#user_list_display').html('<div class="text-center p-3 text-danger">Gagal memuat daftar pengguna.</div>');
            }
        });
    }

    // 5. Tangani pengiriman pesan
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
    
    // Atur interval untuk memuat pesan baru secara otomatis (contoh: setiap 3 detik)
    // setInterval(function() {
    //     if (currentThreadId) {
    //         loadChatMessages();
    //     }
    // }, 3000); 
</script>