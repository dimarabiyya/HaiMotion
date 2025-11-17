<?php 
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
    $decoded_id = decode_id($encoded_thread_id);
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_thread_id = $decoded_id;
    }
}

$initial_recipient_id = null;
if (!empty($encoded_recipient_id)) {
    $decoded_id = decode_id($encoded_recipient_id);
    if (is_numeric($decoded_id) && $decoded_id > 0) {
        $initial_recipient_id = $decoded_id;
    }
}

// Jika ada thread_id yang berhasil didekode dan recipient_id belum ada
if ($initial_thread_id && !$initial_recipient_id) {
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
    /* ------------------------------------------- */
    /* WA / MESSENGER STYLES             */
    /* ------------------------------------------- */
    .chat-container {
        display: flex;
        height: 75vh; /* Sedikit lebih kecil dari 80vh agar header terlihat */
        border: 1px solid #dee2e6; /* Border tipis */
        border-radius: 8px;
        overflow: hidden;
    }
    .user-list-sidebar {
        width: 350px; /* Lebar sidebar sedikit ditambah */
        border-right: 1px solid #dee2e6;
        overflow-y: auto;
        padding: 0;
        background-color: #f8f9fa;
        min-height: 100%;
        flex-shrink: 0;
    }
    .user-item {
        padding: 12px 15px; /* Padding lebih besar */
        border-bottom: 1px solid #f1f1f1;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .user-item:hover, .user-item.active {
        background-color: #e9ecef;
    }
    
    /* CHAT AREA */
    .chat-area {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        background-color: #f0f0f0; /* Latar belakang area chat abu-abu muda */
    }
    .chat-header {
        background-color: #ffffff; /* Header putih */
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        z-index: 10;
        padding: 10px 15px;
    }
    .chat-box {
        flex-grow: 1;
        overflow-y: auto;
        padding: 20px 15px; /* Padding lebih besar */
        display: flex;
        flex-direction: column;
        gap: 8px; /* Jarak antar pesan */
        height: 1px;
    }
    
    /* MESSAGE BUBBLES */
    .message-item {
        margin: 5px 0;
        padding: 8px 12px;
        border-radius: 18px; /* Lebih membulat seperti WA */
        max-width: 65%; /* Lebih lebar */
        position: relative;
        word-wrap: break-word; /* Memastikan teks panjang pecah */
    }
    .incoming-message {
        background-color: #ffffff; /* Putih bersih */
        margin-right: auto;
        border-bottom-left-radius: 4px; /* Sudut bawah dekat pengirim lebih tajam */
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
    }
    .outgoing-message {
        background-color: #B75301; /* Warna tema */
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 4px; /* Sudut bawah dekat pengirim lebih tajam */
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
    }
    
    .sender-name {
        font-weight: bold;
        margin-bottom: 2px;
        font-size: 0.85em; /* Lebih kecil */
        opacity: 0.7;
        color: #B75301; /* Warna berbeda untuk nama pengirim */
    }
    .outgoing-message .sender-name {
        color: #ffffff; /* Putih untuk pesan keluar */
        opacity: 0.9;
    }
    
    .timestamp {
        font-size: 0.65em; /* Paling kecil */
        opacity: 0.6;
        display: block;
        margin-top: 3px;
        text-align: right;
        /* Warna default (hitam/abu-abu) untuk pesan masuk */
    }
    .outgoing-message .timestamp {
        color: #fff;
        opacity: 0.8;
    }
    
    /* INPUT AREA */
    #chat_input_container {
        background-color: #ffffff; 
        border-top: 1px solid #dee2e6;
        padding: 10px 15px;
        flex-shrink: 0;
    }
    #message_content {
        border-radius: 20px !important; /* Input membulat */
        padding: 10px 15px;
    }
    #chat_input_container .input-group-append button {
        border-radius: 20px !important;
        margin-left: 5px;
    }
    
    /* AVATAR & MENTION TAGS */
    .default-avatar {
        width: 40px; /* Ukuran avatar sedikit diperbesar */
        height: 40px;
        font-size: 1.1em;
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

<script>
    // Struktur data untuk memetakan ID ke Nama dan Nama ke ID
    let lookupData = { users: {}, projects: {}, tasks: {} }; // ID -> {Name, EncodedID}
    let reverseLookup = { users: {}, projects: {}, tasks: {} }; // Name -> ID
    
    // Nilai-nilai ini sekarang adalah ID NUMERIK atau string kosong ('')
    let currentThreadId = '<?php echo $initial_thread_id_js; ?>'; 
    let currentRecipientId = '<?php echo $initial_recipient_id_js; ?>';
    let currentRecipientName = '';
    const currentUserId = <?php echo $current_user_id; ?>;
    
    // Helper function untuk menghindari masalah pada regex jika nama mengandung karakter khusus
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // 1. Fungsi untuk memformat sintaks mention menjadi HTML tag
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

        // A. Parse User Mentions (@Nama User)
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
        
        // B. Parse Project Mentions (#Nama Project)
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

        // C. Parse Task Mentions (!Nama Task)
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

    // 2. Memuat pesan chat untuk thread aktif
    function loadChatMessages() {
        if (!currentThreadId) {
            $('#chat_display').html('<div class="text-center p-5 text-muted">Pilih kontak di sebelah kiri untuk memulai percakapan.</div>');
            $('#chat_input_container').hide();
            return;
        }

        var chatBox = $('#chat_display');
        chatBox.html('<div class="text-center p-3 text-muted">Loading...</div>');
        
        $('#recipient_header h5').html('<div class="d-flex align-items-center"><i class="fas fa-comment-dots mr-2"></i>' + currentRecipientName + '</div>');
        $('#chat_input_container').show();
        $('#form_thread_id').val(currentThreadId);

        $.ajax({
            url: 'ajax.php?action=get_personal_chat_messages',
            method: 'POST',
            data: { thread_id: currentThreadId },
            dataType: 'json',
            success: function(response) {
                
                // --- MEMBANGUN REVERSE LOOKUP MAP ---
                lookupData.users = response.users || {};
                lookupData.projects = response.projects || {};
                lookupData.tasks = response.tasks || {};
                
                reverseLookup.users = {};
                for (const id in lookupData.users) {
                    reverseLookup.users[lookupData.users[id].name.trim()] = id;
                }
                reverseLookup.projects = {};
                for (const id in lookupData.projects) {
                    reverseLookup.projects[lookupData.projects[id].name.trim()] = id;
                }
                reverseLookup.tasks = {};
                for (const id in lookupData.tasks) {
                    reverseLookup.tasks[lookupData.tasks[id].name.trim()] = id;
                }
                // ------------------------------------
                
                chatBox.empty();

                if (response.messages && response.messages.length > 0) {
                    response.messages.forEach(function(msg) {
                        var isOutgoing = msg.sender_id == currentUserId;
                        var messageClass = isOutgoing ? 'outgoing-message' : 'incoming-message';
                        
                        // Tampilkan nama pengirim hanya jika pesan masuk
                        var senderNameHtml = !isOutgoing ? `<span class="sender-name">${msg.sender_name}</span>` : '';
                        
                        var formattedContent = formatMessage(msg.message_content);

                        var html = `
                            <div class="message-item ${messageClass}">
                                ${senderNameHtml}
                                <p class="m-0">${formattedContent}</p>
                                <span class="timestamp">${msg.created_at}</span>
                            </div>
                        `;
                        chatBox.append(html);
                    });
                } else {
                    chatBox.html('<div class="text-center p-5 text-muted">Start first Conversation</div>');
                }
                
                chatBox.scrollTop(chatBox[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (loadChatMessages):", status, error, xhr.responseText);
                chatBox.html('<div class="text-center p-5 text-danger">Error loading messages. Check console for details.</div>');
            }
        });
    }
    
    // 3. Mendapatkan ID thread atau membuat yang baru
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

    // 4. Memuat daftar pengguna di sidebar
    function loadUserList() {
        $.ajax({
            url: 'ajax.php?action=get_all_users_for_chat',
            method: 'GET',
            dataType: 'json',
            success: function(users) {
                $('#user_list_display').empty();
                
                if (users.length === 0) {
                    $('#user_list_display').html('<div class="text-center p-3 text-muted">No other users found.</div>');
                    $('#chat_input_container').hide();
                    return;
                }
                
                users.forEach(function(user) {
                    var activeClass = (user.id == currentRecipientId) ? 'active' : '';
                    var avatarHtml;
                    
                    if (user.avatar && user.avatar !== '') {
                        avatarHtml = `<img src="assets/uploads/${user.avatar}" class="img-circle elevation-2 mr-2" alt="User Image" style="width: 35px; height: 35px; object-fit: cover;">`;
                    } else {
                        var initials = (user.name || 'NN').split(' ').map(n => n[0]).join('').substring(0, 2);
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
                    
                    currentRecipientId = $(this).data('user-id').toString();
                    currentRecipientName = $(this).data('user-name');
                    
                    currentThreadId = ''; 
                    getOrCreateThread();
                });
                
                // Jika ada inisialisasi dari URL, muat chat secara otomatis
                if (currentRecipientId !== '') {
                    var selectedUser = $(`.user-item[data-user-id="${currentRecipientId}"]`);
                    if(selectedUser.length > 0) {
                       currentRecipientName = selectedUser.data('user-name');
                       selectedUser.addClass('active');
                       getOrCreateThread();
                    } else {
                       $('#recipient_header h5').html('<div class="d-flex align-items-center"><i class="fas fa-comment-dots mr-2"></i>Recipient tidak ditemukan</div>');
                       $('#chat_input_container').hide();
                    }
                } else {
                     $('#chat_input_container').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (Load User List):", status, error, xhr.responseText);
                $('#user_list_display').html('<div class="text-center p-3 text-danger">Gagal memuat daftar pengguna.</div>');
                $('#chat_input_container').hide();
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

        var formData = $(this).serialize();
        
        $.ajax({
            url: 'ajax.php?action=save_personal_chat_message',
            method: 'POST',
            data: formData,
            success: function(resp) {
                if (resp == 1) {
                    $('#message_content').val('');
                    loadChatMessages(); 
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