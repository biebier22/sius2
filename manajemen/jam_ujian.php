<?php
session_start();
include "../config.php";

// Cek session
if (!isset($_SESSION['manajemen_unlock']) && !isset($_SESSION['pengawas_unlock'])) {
    echo "<div style='padding: 20px; text-align: center; color: #c33;'>Anda harus login terlebih dahulu.</div>";
    exit;
}

$masa = "20252";

// Ambil jadwal existing jika ada (untuk H1 dan H2)
$jadwal_h1 = null;
$jadwal_h2 = null;
if (isset($conn2)) {
    $stmt = $conn2->prepare("SELECT * FROM jadwal_ujian WHERE masa = ? AND hari = 1 LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $masa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $jadwal_h1 = $result->fetch_assoc();
        }
        $stmt->close();
    }
    
    $stmt = $conn2->prepare("SELECT * FROM jadwal_ujian WHERE masa = ? AND hari = 2 LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $masa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $jadwal_h2 = $result->fetch_assoc();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Ujian</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .form-container {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-group input[type="date"],
        .form-group input[type="time"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input[type="date"]:focus,
        .form-group input[type="time"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .jam-row {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border-left: 4px solid #667eea;
        }
        .jam-row h3 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .jam-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .input-group {
            display: flex;
            flex-direction: column;
        }
        .input-group label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-save:active {
            transform: translateY(0);
        }
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .message.show {
            display: block;
        }
        @media (max-width: 768px) {
            .jam-inputs {
                grid-template-columns: 1fr;
            }
            .form-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-clock"></i> Jadwal Ujian</h1>
            <p>Atur tanggal dan jam mulai/selesai ujian untuk semua lokasi</p>
        </div>
        
        <div class="form-container">
            <div id="message" class="message"></div>
            
            <!-- Selector Hari -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="select_hari"><i class="fa-solid fa-calendar-day"></i> Pilih Hari</label>
                <select id="select_hari" name="select_hari" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; cursor: pointer;">
                    <option value="1" <?php echo (!isset($_GET['hari']) || $_GET['hari'] == 1) ? 'selected' : ''; ?>>Hari 1</option>
                    <option value="2" <?php echo (isset($_GET['hari']) && $_GET['hari'] == 2) ? 'selected' : ''; ?>>Hari 2</option>
                </select>
            </div>
            
            <form id="jadwalForm">
                <input type="hidden" id="hari" name="hari" value="<?php echo (!isset($_GET['hari']) || $_GET['hari'] == 1) ? '1' : '2'; ?>">
                
                <div id="form-hari-container">
                    <?php 
                    $hari_selected = (!isset($_GET['hari']) || $_GET['hari'] == 1) ? 1 : 2;
                    $jadwal_aktif = ($hari_selected == 1) ? $jadwal_h1 : $jadwal_h2;
                    $bg_color = ($hari_selected == 1) ? '#f0f9ff' : '#fef3c7';
                    $border_color = ($hari_selected == 1) ? '#3b82f6' : '#f59e0b';
                    $text_color = ($hari_selected == 1) ? '#3b82f6' : '#f59e0b';
                    ?>
                    <div style="background: <?php echo $bg_color; ?>; border-radius: 12px; padding: 20px; margin-bottom: 24px; border-left: 4px solid <?php echo $border_color; ?>;">
                        <h2 style="color: <?php echo $text_color; ?>; font-size: 18px; margin-bottom: 16px;">
                            <i class="fa-solid fa-calendar-day"></i> Hari <?php echo $hari_selected; ?>
                        </h2>
                        <div class="form-group">
                            <label for="tanggal_ujian"><i class="fa-solid fa-calendar"></i> Tanggal Ujian</label>
                            <input type="date" id="tanggal_ujian" name="tanggal_ujian" required 
                                   value="<?php echo $jadwal_aktif ? htmlspecialchars($jadwal_aktif['tanggal_ujian']) : ''; ?>">
                        </div>
                        
                        <?php for ($j = 1; $j <= 5; $j++): ?>
                            <div class="jam-row">
                                <h3><i class="fa-solid fa-hourglass-half"></i> Jam <?php echo $j; ?></h3>
                                <div class="jam-inputs">
                                    <div class="input-group">
                                        <label>Jam Mulai</label>
                                        <input type="time" 
                                               name="jam<?php echo $j; ?>_mulai" 
                                               id="jam<?php echo $j; ?>_mulai" 
                                               required
                                               value="<?php echo $jadwal_aktif ? htmlspecialchars($jadwal_aktif['jam' . $j . '_mulai']) : ''; ?>">
                                    </div>
                                    <div class="input-group">
                                        <label>Jam Selesai</label>
                                        <input type="time" 
                                               name="jam<?php echo $j; ?>_selesai" 
                                               id="jam<?php echo $j; ?>_selesai" 
                                               required
                                               value="<?php echo $jadwal_aktif ? htmlspecialchars($jadwal_aktif['jam' . $j . '_selesai']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-save"></i> Simpan Jadwal Hari <span id="hari-label"><?php echo $hari_selected; ?></span>
                </button>
            </form>
        </div>
    </div>
    
    <script>
        const form = document.getElementById('jadwalForm');
        const messageDiv = document.getElementById('message');
        
        // Validasi jam selesai harus setelah jam mulai
        function validateTimes() {
            const hari = document.getElementById('hari').value;
            for (let j = 1; j <= 5; j++) {
                const mulai = document.getElementById('jam' + j + '_mulai').value;
                const selesai = document.getElementById('jam' + j + '_selesai').value;
                
                if (mulai && selesai && selesai <= mulai) {
                    showMessage('Hari ' + hari + ' - Jam selesai harus setelah jam mulai untuk Jam ' + j, 'error');
                    return false;
                }
            }
            return true;
        }
        
        // Handler untuk perubahan selector hari
        const selectHari = document.getElementById('select_hari');
        selectHari.addEventListener('change', function() {
            const hari = this.value;
            // Reload halaman dengan parameter hari
            window.location.href = 'jam_ujian.php?hari=' + hari;
        });
        
        function showMessage(text, type) {
            messageDiv.textContent = text;
            messageDiv.className = 'message ' + type + ' show';
            setTimeout(() => {
                messageDiv.classList.remove('show');
            }, 5000);
        }
        
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!validateTimes()) {
                return;
            }
            
            const formData = new FormData(form);
            formData.append('masa', '<?php echo $masa; ?>');
            formData.append('hari', document.getElementById('hari').value);
            
            try {
                const response = await fetch('save_jadwal_ujian.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Cek apakah response OK
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                
                // Ambil text dulu untuk debug jika perlu
                const text = await response.text();
                
                // Cek apakah response kosong
                if (!text || text.trim() === '') {
                    throw new Error('Response kosong dari server');
                }
                
                // Parse JSON
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response: ' + parseError.message);
                }
                
                if (result.status === 'ok') {
                    showMessage(result.message || 'Jadwal berhasil disimpan!', 'success');
                    // Reload setelah 1 detik untuk refresh data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage(result.message || 'Gagal menyimpan jadwal', 'error');
                }
            } catch (error) {
                console.error('Error details:', error);
                showMessage('Terjadi kesalahan: ' + error.message, 'error');
            }
        });
        
        // Update label hari saat selector berubah
        selectHari.addEventListener('change', function() {
            document.getElementById('hari-label').textContent = this.value;
        });
    </script>
</body>
</html>
