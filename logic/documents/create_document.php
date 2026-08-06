<?php
// logic/documents/create_document.php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creator_id = $_SESSION['user_id'];

    if ($action === 'create_outgoing' || $action === 'edit_outgoing') {
        check_permission('document.create');
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : null;
        $title = trim($_POST['title']);
        $receiver_division_id = !empty($_POST['receiver_division_id']) ? intval($_POST['receiver_division_id']) : (!empty($_POST['receiver_department_id']) ? intval($_POST['receiver_department_id']) : null);
        $receiver_unit_id = !empty($_POST['receiver_unit_id']) ? intval($_POST['receiver_unit_id']) : null;
        
        // Fetch template content if using a template
        $template = null;
        $content = '';
        $placeholders_json = null;
        
        if ($template_id > 0) {
            $stmtTpl = $conn->prepare("SELECT content_template FROM document_templates WHERE id = ?");
            $stmtTpl->execute([$template_id]);
            $template = $stmtTpl->fetch(PDO::FETCH_ASSOC);
            
            if ($template) {
                // Parse filled placeholder fields from POST
                $fields = isset($_POST['placeholders']) ? $_POST['placeholders'] : [];
                $placeholders_json = json_encode($fields);
                
                // Replace placeholders in template content
                $content = $template['content_template'];
                foreach ($fields as $key => $val) {
                    $content = str_replace('{{' . $key . '}}', htmlspecialchars($val), $content);
                }
            }
        } else {
            // Manual editor content (fallback/raw editor)
            $content = $_POST['content'] ?? '';
        }

        if (empty($title) || empty($content) || !$receiver_division_id) {
            $redirect_url = $action === 'create_outgoing' ? "views/documents/outgoing.php?error=Judul,+tujuan+bidang,+dan+konten+wajib+diisi" : "views/documents/outgoing.php?action=edit&id=$id&error=Judul,+tujuan+bidang,+dan+konten+wajib+diisi";
            redirect($redirect_url);
        }

        try {
            if ($action === 'create_outgoing') {
                $qr_token = bin2hex(random_bytes(16));
                
                // Set temporary document number for draft
                $temp_doc_no = "DRAFT/" . date('Ymd') . "/" . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

                $stmt = $conn->prepare("INSERT INTO documents (creator_id, template_id, type, document_number, title, content, placeholder_values, receiver_division_id, receiver_department_id, receiver_unit_id, qr_token, status) 
                                        VALUES (:creator_id, :template_id, 'outgoing', :document_number, :title, :content, :placeholder_values, :receiver_division_id, :receiver_division_id, :receiver_unit_id, :qr_token, 'draft')");
                $stmt->execute([
                    ':creator_id' => $creator_id,
                    ':template_id' => $template_id,
                    ':document_number' => $temp_doc_no,
                    ':title' => $title,
                    ':content' => $content,
                    ':placeholder_values' => $placeholders_json,
                    ':receiver_division_id' => $receiver_division_id,
                    ':receiver_unit_id' => $receiver_unit_id,
                    ':qr_token' => $qr_token
                ]);
                $new_id = $conn->lastInsertId();

                Logger::activity('Dokumen', 'CREATE_DOCUMENT', 'Membuat draft surat keluar: ' . $title, ['id' => $new_id]);
                redirect("views/documents/outgoing.php?success=Draft+surat+berhasil+dibuat");
            } else {
                // Edit existing outgoing document (only if status is draft or rejected)
                $stmtCheck = $conn->prepare("SELECT status FROM documents WHERE id = ?");
                $stmtCheck->execute([$id]);
                $status = $stmtCheck->fetchColumn();

                if ($status !== 'draft' && $status !== 'rejected') {
                    redirect("views/documents/outgoing.php?error=Hanya+surat+berstatus+Draft+atau+Ditolak+yang+dapat+diedit");
                }

                $stmt = $conn->prepare("UPDATE documents 
                                        SET title = :title, content = :content, placeholder_values = :placeholder_values, receiver_division_id = :receiver_division_id, receiver_department_id = :receiver_division_id, receiver_unit_id = :receiver_unit_id, status = 'draft'
                                        WHERE id = :id");
                $stmt->execute([
                    ':title' => $title,
                    ':content' => $content,
                    ':placeholder_values' => $placeholders_json,
                    ':receiver_division_id' => $receiver_division_id,
                    ':receiver_unit_id' => $receiver_unit_id,
                    ':id' => $id
                ]);

                Logger::activity('Dokumen', 'UPDATE_DOCUMENT', 'Mengubah draft surat keluar: ' . $title, ['id' => $id]);
                redirect("views/documents/outgoing.php?success=Draft+surat+berhasil+diperbarui");
            }
        } catch (PDOException $e) {
            redirect("views/documents/outgoing.php?error=Gagal+menyimpan+surat:+" . urlencode($e->getMessage()));
        }

    } elseif ($action === 'create_incoming' || $action === 'edit_incoming') {
        check_permission('document.create');

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $document_number = trim($_POST['document_number']);
        $sender = trim($_POST['sender']);
        $title = trim($_POST['title']); // perihal / subyek
        $receiver_unit_id = !empty($_POST['receiver_unit_id']) ? intval($_POST['receiver_unit_id']) : null;
        
        if (empty($document_number) || empty($sender) || empty($title) || !$receiver_unit_id) {
            $redirect_url = $action === 'create_incoming' ? "views/documents/incoming.php?error=Semua+field+wajib+diisi" : "views/documents/incoming.php?action=edit&id=$id&error=Semua+field+wajib+diisi";
            redirect($redirect_url);
        }

        // Handle file upload
        $file_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['attachment']['tmp_name'];
            $file_name = $_FILES['attachment']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($file_ext, $allowed_exts)) {
                redirect("views/documents/incoming.php?error=Format+file+tidak+diizinkan+(Hanya+PDF,+JPG,+PNG)");
            }

            // Create target upload dir if not exists
            $upload_dir = BASE_PATH . '/uploads/documents';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'incoming_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $dest_path = $upload_dir . '/' . $new_filename;
            
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $file_path = 'uploads/documents/' . $new_filename;
            } else {
                redirect("views/documents/incoming.php?error=Gagal+mengunggah+berkas+lampiran");
            }
        }

        try {
            if ($action === 'create_incoming') {
                $qr_token = bin2hex(random_bytes(16));

                $stmt = $conn->prepare("INSERT INTO documents (creator_id, type, document_number, title, sender, receiver_unit_id, file_path, qr_token, status) 
                                        VALUES (:creator_id, 'incoming', :document_number, :title, :sender, :receiver_unit_id, :file_path, :qr_token, 'completed')");
                $stmt->execute([
                    ':creator_id' => $creator_id,
                    ':document_number' => $document_number,
                    ':title' => $title,
                    ':sender' => $sender,
                    ':receiver_unit_id' => $receiver_unit_id,
                    ':file_path' => $file_path,
                    ':qr_token' => $qr_token
                ]);
                $new_id = $conn->lastInsertId();

                Logger::activity('Dokumen', 'CREATE_INCOMING', 'Mencatat surat masuk: ' . $title, ['id' => $new_id]);
                redirect("views/documents/incoming.php?success=Surat+masuk+berhasil+dicatat");
            } else {
                // Edit incoming document details
                // Fetch current file path if no new file is uploaded
                if (!$file_path) {
                    $stmtFile = $conn->prepare("SELECT file_path FROM documents WHERE id = ?");
                    $stmtFile->execute([$id]);
                    $file_path = $stmtFile->fetchColumn();
                }

                $stmt = $conn->prepare("UPDATE documents 
                                        SET document_number = :document_number, title = :title, sender = :sender, 
                                            receiver_unit_id = :receiver_unit_id, file_path = :file_path 
                                        WHERE id = :id");
                $stmt->execute([
                    ':document_number' => $document_number,
                    ':title' => $title,
                    ':sender' => $sender,
                    ':receiver_unit_id' => $receiver_unit_id,
                    ':file_path' => $file_path,
                    ':id' => $id
                ]);

                Logger::activity('Dokumen', 'UPDATE_INCOMING', 'Mengubah catatan surat masuk: ' . $title, ['id' => $id]);
                redirect("views/documents/incoming.php?success=Catatan+surat+masuk+berhasil+diperbarui");
            }
        } catch (PDOException $e) {
            redirect("views/documents/incoming.php?error=Gagal+menyimpan+surat+masuk:+" . urlencode($e->getMessage()));
        }
    } elseif ($action === 'delete' || $action === 'delete_incoming') {
        check_permission('document.create');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            redirect($action === 'delete' ? "views/documents/outgoing.php?error=ID+tidak+valid" : "views/documents/incoming.php?error=ID+tidak+valid");
        }

        try {
            // Fetch document title and file path
            $stmt = $conn->prepare("SELECT title, file_path, status, type FROM documents WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($doc) {
                if ($action === 'delete' && $doc['status'] !== 'draft' && $doc['status'] !== 'rejected') {
                    redirect("views/documents/outgoing.php?error=Hanya+surat+berstatus+Draft+atau+Ditolak+yang+dapat+dihapus");
                }

                // Delete document
                $stmtDel = $conn->prepare("DELETE FROM documents WHERE id = ?");
                $stmtDel->execute([$id]);

                // Delete physical file if exists
                if (!empty($doc['file_path']) && file_exists(BASE_PATH . '/' . $doc['file_path'])) {
                    @unlink(BASE_PATH . '/' . $doc['file_path']);
                }

                Logger::activity('Dokumen', 'DELETE_DOCUMENT', 'Menghapus dokumen: ' . $doc['title'] . ' (Type: ' . $doc['type'] . ')', ['id' => $id]);
                
                if ($action === 'delete') {
                    redirect("views/documents/outgoing.php?success=Draft+surat+berhasil+dihapus");
                } else {
                    redirect("views/documents/incoming.php?success=Catatan+surat+masuk+berhasil+dihapus");
                }
            }
        } catch (PDOException $e) {
            redirect($action === 'delete' ? "views/documents/outgoing.php?error=Gagal+menghapus:+" . urlencode($e->getMessage()) : "views/documents/incoming.php?error=Gagal+menghapus:+" . urlencode($e->getMessage()));
        }
    }
}
redirect("views/documents/index.php");
