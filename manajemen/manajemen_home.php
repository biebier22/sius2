<?php
session_start();
include "../config.php";

$showDashboard = false;
$notif = "";
$userType = ""; // 'manajemen' atau 'pengawas'

// =============================
//         LOGIN PROCESS
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {

    if (!isset($conn2) || $conn2->connect_error) {
        $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Database connection (conn2) tidak ditemukan.</div>";
    } else {

        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Cek dulu di pj_setting untuk Direktur & Manajer
        $stmt = $conn2->prepare("SELECT * FROM pj_setting WHERE username = ? AND role IN ('MANAJER', 'DIREKTUR') LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $q = $stmt->get_result();

        if ($q && $q->num_rows > 0) {
            $d = $q->fetch_assoc();
            $stmt->close();

            // Cek password (plain text sesuai permintaan)
            if (trim($d['password']) === $password) {
                $role = $d['role'];
                $namaUser = $d['nama'] ?? $username;

                // Set session untuk manajemen
                $_SESSION['manajemen_unlock'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['nama_user'] = $namaUser;
                $_SESSION['role'] = $role;
                $_SESSION['lokasi'] = $d['lokasi'] ?? '';
                $userType = 'manajemen';

                header("Location: manajemen_home.php");
                exit;
            } else {
                $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Login Gagal: Password salah.</div>";
            }
        } else {
            $stmt->close();
            
            // Jika tidak ditemukan di pj_setting, cek di pengawas_ruang (backward compatibility)
            $q_pengawas = mysqli_query($conn2, "
                SELECT * FROM pengawas_ruang 
                WHERE id_pengawas = '".mysqli_real_escape_string($conn2, $username)."'
                LIMIT 1
            ");

            if (mysqli_num_rows($q_pengawas) > 0) {
                $d = mysqli_fetch_assoc($q_pengawas);

                // Cek password (default 123456, atau dari kolom password jika ada)
                $stored_password = isset($d['password']) ? trim($d['password']) : '123456';
                
                // Jika password kosong di database, gunakan default 123456
                if (empty($stored_password)) {
                    $stored_password = '123456';
                }

                if ($stored_password === $password) {
                    $_SESSION['pengawas_unlock'] = true;
                    $_SESSION['nama'] = $d['nama_pengawas'];
                    $_SESSION['id_pengawas'] = $d['id_pengawas'];
                    $userType = 'pengawas';

                    header("Location: manajemen_home.php");
                    exit;
                } else {
                    $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Login Gagal: Password salah.</div>";
                }
            } else {
                $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Login Gagal: Username tidak ditemukan.</div>";
            }
        }
    }
}

// =============================
//         SESSION CHECK
// =============================
if (isset($_SESSION['manajemen_unlock']) && $_SESSION['manajemen_unlock'] === true) {
    $showDashboard = true;
    $userType = 'manajemen';
    $namaUser = $_SESSION['nama_user'];
    $role = $_SESSION['role'];
} elseif (isset($_SESSION['pengawas_unlock']) && $_SESSION['pengawas_unlock'] === true) {
    $showDashboard = true;
    $userType = 'pengawas';
    $namaUser = $_SESSION['nama'] ?? 'Pengawas';
}

// =============================
//         FETCH LOKASI UJIAN
// =============================
$lokasiList = [];
$tanggal_hari_ini = date('Y-m-d'); // Tanggal hari ini
$hari_aktif = null; // 1 atau 2, sesuai dengan tanggal di jadwal

// Ambil jadwal untuk cek tanggal H1 dan H2, serta jam mulai/selesai
$jadwal_h1 = null;
$jadwal_h2 = null;
$waktu_sekarang = date('H:i:s'); // Waktu sekarang (HH:MM:SS)
$tanggal_waktu_sekarang = date('Y-m-d H:i:s'); // Tanggal dan waktu sekarang

if ($showDashboard && isset($conn2) && !$conn2->connect_error) {
    $masa = '20252';
    
    // Query jadwal H1 (dengan jam mulai/selesai)
    $stmt_jadwal_h1 = $conn2->prepare("SELECT * FROM jadwal_ujian WHERE masa = ? AND hari = 1 LIMIT 1");
    if ($stmt_jadwal_h1) {
        $stmt_jadwal_h1->bind_param("s", $masa);
        $stmt_jadwal_h1->execute();
        $result_jadwal_h1 = $stmt_jadwal_h1->get_result();
        if ($result_jadwal_h1 && $result_jadwal_h1->num_rows > 0) {
            $jadwal_h1 = $result_jadwal_h1->fetch_assoc();
            if ($jadwal_h1['tanggal_ujian'] == $tanggal_hari_ini) {
                $hari_aktif = 1;
            }
        }
        $stmt_jadwal_h1->close();
    }
    
    // Query jadwal H2 (dengan jam mulai/selesai)
    $stmt_jadwal_h2 = $conn2->prepare("SELECT * FROM jadwal_ujian WHERE masa = ? AND hari = 2 LIMIT 1");
    if ($stmt_jadwal_h2) {
        $stmt_jadwal_h2->bind_param("s", $masa);
        $stmt_jadwal_h2->execute();
        $result_jadwal_h2 = $stmt_jadwal_h2->get_result();
        if ($result_jadwal_h2 && $result_jadwal_h2->num_rows > 0) {
            $jadwal_h2 = $result_jadwal_h2->fetch_assoc();
            if ($jadwal_h2['tanggal_ujian'] == $tanggal_hari_ini) {
                $hari_aktif = 2;
            }
        }
        $stmt_jadwal_h2->close();
    }
    // Query untuk mendapatkan lokasi unik
    $stmt = $conn2->prepare("
        SELECT DISTINCT 
            lokasi
        FROM e_lokasi_uas
        WHERE masa = ?
        AND lokasi IS NOT NULL 
        AND lokasi != ''
        AND lokasi != 'Kantor UT Daerah Serang'
        ORDER BY lokasi ASC
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $masa);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $lokasi = $row['lokasi'];
            
            // Query untuk mendapatkan data hari dan ruang untuk setiap lokasi
            $stmt_hari = $conn2->prepare("
                SELECT 
                    hari,
                    MIN(CAST(ruang_awal AS UNSIGNED)) AS ruang_awal_min,
                    MAX(CAST(ruang_akhir AS UNSIGNED)) AS ruang_akhir_max
                FROM e_lokasi_uas
                WHERE masa = ?
                AND lokasi = ?
                AND hari IS NOT NULL
                AND hari != ''
                GROUP BY hari
                ORDER BY CAST(hari AS UNSIGNED) ASC
            ");
            
            $hariData = [];
            if ($stmt_hari) {
                $stmt_hari->bind_param("ss", $masa, $lokasi);
                $stmt_hari->execute();
                $result_hari = $stmt_hari->get_result();
                
                while ($row_hari = $result_hari->fetch_assoc()) {
                    $hari = $row_hari['hari'];
                    $hari_int = (int)$hari;
                    
                    // Jika hari_aktif sudah ditentukan, hanya tampilkan hari yang sesuai dengan tanggal hari ini
                    if ($hari_aktif !== null && $hari_int !== $hari_aktif) {
                        continue; // Skip hari yang tidak sesuai dengan tanggal hari ini
                    }
                    
                    // Query untuk mendapatkan semua ruang di lokasi ini untuk hari ini
                    $stmt_ruang = $conn2->prepare("
                        SELECT DISTINCT r.ruang_ke
                        FROM rekap_ujian r
                        LEFT JOIN e_lokasi_uas e
                            ON r.kode_tpu = e.kode_tpu
                            AND e.masa = ?
                            AND CAST(r.ruang_ke AS UNSIGNED) BETWEEN CAST(e.ruang_awal AS UNSIGNED) AND CAST(e.ruang_akhir AS UNSIGNED)
                            AND CAST(r.hari_ke AS CHAR) = e.hari
                        WHERE r.masa = ?
                            AND e.lokasi = ?
                            AND CAST(r.hari_ke AS CHAR) = ?
                            AND r.ruang_ke IS NOT NULL
                    ");
                    
                    $total_ruang = 0;
                    $ruang_selesai = 0;
                    $ruang_sedang_ujian = 0;
                    
                    // Ambil jadwal untuk hari ini
                    $jadwal_hari_ini = null;
                    if ($hari_int == 1 && $jadwal_h1 && $jadwal_h1['tanggal_ujian'] == $tanggal_hari_ini) {
                        $jadwal_hari_ini = $jadwal_h1;
                    } elseif ($hari_int == 2 && $jadwal_h2 && $jadwal_h2['tanggal_ujian'] == $tanggal_hari_ini) {
                        $jadwal_hari_ini = $jadwal_h2;
                    }
                    
                    // Cek apakah waktu sekarang dalam range salah satu jam
                    $is_sedang_ujian = false;
                    $jam_aktif = null; // Menyimpan jam yang sedang aktif (1-5)
                    $is_setelah_semua_jam = false; // Flag untuk cek apakah sudah lewat semua jam
                    
                    if ($jadwal_hari_ini) {
                        // Pastikan format waktu konsisten (HH:MM:SS)
                        // Waktu dari database mungkin hanya HH:MM, tambahkan :00 jika perlu
                        $waktu_sekarang_formatted = $waktu_sekarang;
                        if (strlen($waktu_sekarang) == 5) {
                            $waktu_sekarang_formatted = $waktu_sekarang . ':00';
                        }
                        
                        // Cek apakah waktu sekarang dalam range salah satu jam (jam1-jam5)
                        for ($j = 1; $j <= 5; $j++) {
                            $jam_mulai = $jadwal_hari_ini['jam' . $j . '_mulai'];
                            $jam_selesai = $jadwal_hari_ini['jam' . $j . '_selesai'];
                            
                            // Pastikan format waktu konsisten (HH:MM:SS)
                            if (strlen($jam_mulai) == 5) {
                                $jam_mulai = $jam_mulai . ':00';
                            }
                            if (strlen($jam_selesai) == 5) {
                                $jam_selesai = $jam_selesai . ':00';
                            }
                            
                            // Perbandingan string langsung untuk format TIME (HH:MM:SS)
                            if ($waktu_sekarang_formatted >= $jam_mulai && $waktu_sekarang_formatted <= $jam_selesai) {
                                $is_sedang_ujian = true;
                                $jam_aktif = $j; // Simpan jam yang sedang aktif
                                break;
                            }
                        }
                        
                        // Cek apakah waktu sekarang sudah lewat semua jam selesai
                        $jam5_selesai = $jadwal_hari_ini['jam5_selesai'];
                        if (strlen($jam5_selesai) == 5) {
                            $jam5_selesai = $jam5_selesai . ':00';
                        }
                        if ($waktu_sekarang_formatted > $jam5_selesai) {
                            $is_setelah_semua_jam = true;
                        }
                    }
                    
                    if ($stmt_ruang) {
                        $stmt_ruang->bind_param("ssss", $masa, $masa, $lokasi, $hari);
                        $stmt_ruang->execute();
                        $result_ruang = $stmt_ruang->get_result();
                        
                        while ($row_ruang = $result_ruang->fetch_assoc()) {
                            $ruang_ke = $row_ruang['ruang_ke'];
                            $total_ruang++;
                            
                            // Cek apakah ruang ini sudah selesai untuk jam yang sedang aktif
                            $ruang_sudah_selesai = false;
                            
                            if ($jam_aktif) {
                                // Jika ada jam aktif, cek apakah sudah ada data untuk jam tersebut
                                $stmt_cek_selesai = $conn2->prepare("
                                    SELECT COUNT(*) AS jumlah
                                    FROM berita_acara_serah_terima
                                    WHERE masa = ?
                                        AND hari = ?
                                        AND ruang = ?
                                        AND jam_ke = ?
                                        AND hasil_lju_terisi IS NOT NULL
                                        AND hasil_lju_kosong IS NOT NULL
                                        AND hasil_lju_terisi >= 0
                                        AND hasil_lju_kosong >= 0
                                ");
                                
                                if ($stmt_cek_selesai) {
                                    $stmt_cek_selesai->bind_param("ssii", $masa, $hari, $ruang_ke, $jam_aktif);
                                    $stmt_cek_selesai->execute();
                                    $result_cek = $stmt_cek_selesai->get_result();
                                    if ($row_cek = $result_cek->fetch_assoc()) {
                                        // Jika sudah ada data untuk jam yang sedang aktif, ruang dianggap selesai untuk jam tersebut
                                        if ($row_cek['jumlah'] > 0) {
                                            $ruang_sudah_selesai = true;
                                            $ruang_selesai++;
                                        }
                                    }
                                    $stmt_cek_selesai->close();
                                }
                            } else {
                                // Jika belum ada jam aktif, ruang belum selesai
                                $ruang_sudah_selesai = false;
                            }
                            
                            // Ruang "sedang ujian" = belum selesai untuk jam aktif DAN waktu sekarang dalam range salah satu jam
                            if (!$ruang_sudah_selesai && $is_sedang_ujian) {
                                $ruang_sedang_ujian++;
                            }
                        }
                        $stmt_ruang->close();
                    }
                    
                    // Tentukan status berdasarkan waktu dan data
                    if (!$jadwal_hari_ini) {
                        // Jika tidak ada jadwal, gunakan logic lama (berdasarkan data berita_acara saja)
                        $status = ($ruang_selesai == $total_ruang && $total_ruang > 0) ? 'selesai' : 'sedang_ujian';
                        // Jika tidak ada jadwal, hitung ruang_sedang_ujian sebagai total - selesai
                        if (!$is_sedang_ujian) {
                            $ruang_sedang_ujian = $total_ruang - $ruang_selesai;
                        }
                    } else {
                        // Jika ada jadwal, cek waktu
                        $jam1_mulai = $jadwal_hari_ini['jam1_mulai'];
                        // Pastikan format waktu konsisten (HH:MM:SS)
                        if (strlen($jam1_mulai) == 5) {
                            $jam1_mulai = $jam1_mulai . ':00';
                        }
                        
                        // Pastikan format waktu sekarang konsisten
                        $waktu_sekarang_formatted = $waktu_sekarang;
                        if (strlen($waktu_sekarang) == 5) {
                            $waktu_sekarang_formatted = $waktu_sekarang . ':00';
                        }
                        
                        // Perbandingan string langsung untuk format TIME (HH:MM:SS)
                        if ($waktu_sekarang_formatted < $jam1_mulai) {
                            // Sebelum jam mulai: tidak tampilkan status atau "Belum Mulai"
                            $status = 'belum_mulai';
                            $ruang_sedang_ujian = 0; // Belum ada yang sedang ujian
                        } elseif ($is_sedang_ujian) {
                            // Waktu dalam range salah satu jam (jam1-jam5)
                            $status = ($ruang_selesai == $total_ruang && $total_ruang > 0) ? 'selesai' : 'sedang_ujian';
                        } elseif ($is_setelah_semua_jam) {
                            // Setelah semua jam selesai (setelah jam5_selesai)
                            $status = ($ruang_selesai == $total_ruang && $total_ruang > 0) ? 'selesai' : 'selesai'; // Semua ruang yang belum selesai dianggap selesai setelah jam berakhir
                            $ruang_sedang_ujian = 0; // Tidak ada yang sedang ujian setelah jam berakhir
                        } else {
                            // Waktu di antara jam (misalnya antara jam1_selesai dan jam2_mulai)
                            // Jika sudah lewat jam1_mulai tapi belum masuk range jam berikutnya, tetap dianggap "sedang ujian" atau "selesai" tergantung data
                            $status = ($ruang_selesai == $total_ruang && $total_ruang > 0) ? 'selesai' : 'sedang_ujian';
                            // Jika tidak ada yang sedang ujian (karena tidak dalam range), set ke 0
                            if (!$is_sedang_ujian) {
                                $ruang_sedang_ujian = 0;
                            }
                        }
                    }
                    
                    $hariData[] = [
                        'hari' => $hari,
                        'ruang_awal' => $row_hari['ruang_awal_min'],
                        'ruang_akhir' => $row_hari['ruang_akhir_max'],
                        'total_ruang' => $total_ruang,
                        'ruang_selesai' => $ruang_selesai,
                        'ruang_sedang_ujian' => $ruang_sedang_ujian,
                        'status' => $status,
                        'jam_aktif' => $jam_aktif // Jam yang sedang aktif (1-5 atau null)
                    ];
                }
                $stmt_hari->close();
            }
            
            $lokasiList[] = [
                'lokasi' => $lokasi,
                'hari_data' => $hariData
            ];
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
    <title>Dashboard Manajemen</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 100%;
            margin: 0;
            padding: 20px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header .user-info span {
            font-size: 14px;
            opacity: 0.9;
        }

        .btn-logout {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Login Form */
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .login-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .menu-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }

        .menu-card i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .menu-card h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
        }

        .menu-card p {
            font-size: 14px;
            color: #666;
        }

        /* Content Area */
        .content-area {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            min-height: 400px;
        }

        /* Lokasi Cards */
        .lokasi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .lokasi-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-left: 4px solid #667eea;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .lokasi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-left-width: 6px;
        }

        /* Variasi warna border dan background untuk setiap card */
        .lokasi-card.color-1 { 
            border-left-color: #667eea; 
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }
        .lokasi-card.color-2 { 
            border-left-color: #f093fb; 
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 167, 167, 0.1) 100%);
        }
        .lokasi-card.color-3 { 
            border-left-color: #4facfe; 
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
        }
        .lokasi-card.color-4 { 
            border-left-color: #43e97b; 
            background: linear-gradient(135deg, rgba(67, 233, 123, 0.1) 0%, rgba(46, 191, 145, 0.1) 100%);
        }
        .lokasi-card.color-5 { 
            border-left-color: #fa709a; 
            background: linear-gradient(135deg, rgba(250, 112, 154, 0.1) 0%, rgba(254, 225, 64, 0.1) 100%);
        }
        .lokasi-card.color-6 { 
            border-left-color: #fee140; 
            background: linear-gradient(135deg, rgba(254, 225, 64, 0.1) 0%, rgba(250, 112, 154, 0.1) 100%);
        }
        .lokasi-card.color-7 { 
            border-left-color: #30cfd0; 
            background: linear-gradient(135deg, rgba(48, 207, 208, 0.1) 0%, rgba(51, 236, 129, 0.1) 100%);
        }
        .lokasi-card.color-8 { 
            border-left-color: #a8edea; 
            background: linear-gradient(135deg, rgba(168, 237, 234, 0.1) 0%, rgba(254, 163, 99, 0.1) 100%);
        }
        .lokasi-card.color-9 { 
            border-left-color: #ff9a9e; 
            background: linear-gradient(135deg, rgba(255, 154, 158, 0.1) 0%, rgba(254, 163, 99, 0.1) 100%);
        }
        .lokasi-card.color-10 { 
            border-left-color: #fec163; 
            background: linear-gradient(135deg, rgba(254, 193, 99, 0.1) 0%, rgba(253, 140, 140, 0.1) 100%);
        }
        .lokasi-card.color-11 { 
            border-left-color: #4facfe; 
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
        }
        .lokasi-card.color-12 { 
            border-left-color: #00f2fe; 
            background: linear-gradient(135deg, rgba(0, 242, 254, 0.1) 0%, rgba(4, 190, 254, 0.1) 100%);
        }
        .lokasi-card.color-13 { 
            border-left-color: #a8c0ff; 
            background: linear-gradient(135deg, rgba(168, 192, 255, 0.1) 0%, rgba(132, 144, 255, 0.1) 100%);
        }
        .lokasi-card.color-14 { 
            border-left-color: #ff6b6b; 
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.1) 0%, rgba(255, 159, 159, 0.1) 100%);
        }
        .lokasi-card.color-15 { 
            border-left-color: #4ecdc4; 
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.1) 0%, rgba(48, 207, 208, 0.1) 100%);
        }

        .lokasi-card h3 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lokasi-card h3 i {
            font-size: 20px;
        }

        .lokasi-card .hari-info-container {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .lokasi-card .hari-info {
            background: rgba(255, 255, 255, 0.8);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            color: #555;
            font-weight: 500;
            display: inline-block;
        }

        .lokasi-card .hari-info strong {
            color: #667eea;
            font-size: 11px;
        }
        
        .lokasi-card .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-top: 8px;
            margin-right: 6px;
        }
        
        .lokasi-card .status-badge.selesai {
            background: #10b981;
            color: white;
        }
        
        .lokasi-card .status-badge.sedang-ujian {
            background: #f59e0b;
            color: white;
        }
        
        .lokasi-card .status-badge.belum-mulai {
            background: #94a3b8;
            color: white;
        }
        
        .lokasi-card .status-info {
            margin-top: 10px;
            font-size: 11px;
            color: #64748b;
            line-height: 1.6;
        }
        
        .lokasi-card .status-info span {
            display: block;
            margin-top: 4px;
        }

        /* Live Indicator Animation */
        .live-indicator {
            color: #10b981;
            font-size: 12px;
            animation: blink 1.5s infinite;
            margin-right: 8px;
        }

        @keyframes blink {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.3;
            }
        }
        
        /* Real-time Clock */
        .realtime-clock {
            display: inline-block;
            margin-left: 12px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            padding: 4px 10px;
            border-radius: 6px;
            letter-spacing: 1px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                padding: 15px 20px;
            }

            .header h1 {
                font-size: 20px;
            }

            .header .user-info {
                width: 100%;
                margin-top: 10px;
                justify-content: space-between;
            }

            .dashboard-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }

            .menu-card {
                padding: 20px;
            }

            .menu-card i {
                font-size: 36px;
            }

            .login-container {
                margin: 50px auto;
                padding: 30px 20px;
            }

            .lokasi-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .content-area {
                padding: 20px;
            }
        }

        .notif {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .notif.error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
    </style>
</head>
<body>

<?php if(!$showDashboard): ?>

<div class="login-container">
    <h2><i class="fa-solid fa-user-tie"></i> Login Manajemen</h2>
    <p style="text-align: center; color: #666; margin-bottom: 30px;">Masukkan username dan password untuk mengakses dashboard.</p>

    <form method="post" action="manajemen_home.php">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus placeholder="Masukkan username">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="Masukkan password">
        </div>

        <button type="submit" class="btn-login">
            <i class="fa-solid fa-sign-in-alt"></i> Masuk
        </button>
    </form>

    <?php if($notif): ?>
        <div class="notif error"><?php echo $notif; ?></div>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="container">
    <div class="header">
        <div>
            <h1><i class="fa-solid fa-briefcase"></i> Dashboard Manajemen</h1>
            <span style="font-size: 14px; opacity: 0.9; margin-top: 5px; display: block;">
                <?php echo $userType === 'manajemen' ? 'Direktur & Manajer' : 'Pengawas Ruang'; ?>
            </span>
        </div>
        <div class="user-info">
            <span><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($namaUser); ?></span>
            <?php if($userType === 'manajemen'): ?>
                <span style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 4px;">
                    <?php echo htmlspecialchars($role); ?>
                </span>
            <?php endif; ?>
            <a href="../logout.php" class="btn-logout">
                <i class="fa-solid fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="dashboard-grid">

    <!-- MENU DASHBOARD / HOME -->
    <div class="menu-card" onclick="window.location.href='manajemen_home.php'">
        <i class="fa-solid fa-house"></i>
        <h3>Dashboard</h3>
        <p>Kembali ke laman awal</p>
    </div>

    <!-- MENU SETTING JAM UJIAN -->
    <div class="menu-card" onclick="loadPage('jam_ujian.php')">
        <i class="fa-solid fa-clock"></i>
        <h3>Setting Jam Ujian</h3>
        <p>Pengaturan jadwal jam ujian</p>
    </div>

    <!-- MENU MANAJEMEN PJTU / PJLU -->
    <div class="menu-card" onclick="loadPage('manajemen_pjtu_pjlu.php')">
        <i class="fa-solid fa-users-gear"></i>
        <h3>Manajemen PJTU / PJLU</h3>
        <p>Kelola data PJTU dan PJLU</p>
    </div>

</div>


    <div id="content-area" class="content-area">
        <?php if (!empty($lokasiList)): ?>
            <h2 style="margin-bottom: 20px; color: #333; font-size: 22px; display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                <span>
                    <i class="fa-solid fa-circle live-indicator"></i> Realtime UTM Monitor
                </span>
                <span class="realtime-clock" id="realtime-clock">
                    <?php echo date('H:i:s'); ?>
                </span>
            </h2>
            <div class="lokasi-grid">
                <?php 
                $colorIndex = 1;
                foreach ($lokasiList as $lokasi): 
                    $colorClass = 'color-' . $colorIndex;
                    $colorIndex = ($colorIndex % 15) + 1; // Cycle through 15 colors
                ?>
                    <div class="lokasi-card <?php echo $colorClass; ?>" onclick="loadLokasiDetail('<?php echo htmlspecialchars($lokasi['lokasi'], ENT_QUOTES); ?>')" style="cursor: pointer;">
                        <h3>
                            <i class="fa-solid fa-school"></i>
                            <?php echo htmlspecialchars($lokasi['lokasi']); ?>
                        </h3>
                        <?php if (!empty($lokasi['hari_data'])): ?>
                            <div class="hari-info-container">
                                <?php foreach ($lokasi['hari_data'] as $hari): ?>
                                    <div class="hari-info">
                                        <strong>H<?php echo htmlspecialchars($hari['hari']); ?>:</strong> 
                                        R<?php echo $hari['ruang_awal']; ?>-<?php echo $hari['ruang_akhir']; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="status-info">
                                <?php foreach ($lokasi['hari_data'] as $hari): ?>
                                    <div style="margin-top: 8px;">
                                        <?php if ($hari['status'] == 'belum_mulai'): ?>
                                            <span class="status-badge belum-mulai">
                                                ⏸ Belum Mulai
                                            </span>
                                            <span style="display: block; margin-top: 4px; color: #94a3b8; font-size: 10px;">
                                                Menunggu waktu ujian dimulai
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge <?php echo $hari['status'] == 'selesai' ? 'selesai' : 'sedang-ujian'; ?>">
                                                <?php echo $hari['status'] == 'selesai' ? '✓ Sudah Selesai' : '⏳ Sedang Ujian'; ?>
                                            </span>
                                            <?php if ($hari['status'] == 'sedang_ujian' && !empty($hari['jam_aktif'])): ?>
                                                <span style="display: block; margin-top: 4px; font-size: 10px; color: #f59e0b; font-weight: 600;">
                                                    Jam <?php echo $hari['jam_aktif']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <span style="display: block; margin-top: 4px;">
                                                <?php echo $hari['ruang_selesai']; ?> ruang selesai | 
                                                <?php echo $hari['ruang_sedang_ujian']; ?> ruang sedang ujian
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="hari-info">
                                <span style="color: #999;">Tidak ada data ruang</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; color: #999; padding: 50px;">
                <i class="fa-solid fa-inbox" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                <p>Tidak ada data lokasi ujian untuk masa 20252</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Simpan HTML default daftar lokasi saat halaman pertama kali dimuat
let defaultLokasiHTML = null;

function initDefaultLokasiHTML() {
    const contentArea = document.getElementById("content-area");
    if (contentArea) {
        defaultLokasiHTML = contentArea.innerHTML;
        console.log('Default lokasi HTML saved');
    }
}

// Simpan saat DOM ready atau langsung jika sudah ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDefaultLokasiHTML);
} else {
    // Jika sudah ready, simpan langsung
    setTimeout(initDefaultLokasiHTML, 100);
}

function loadLokasiList() {
    // Memuat kembali daftar lokasi default ke content-area
    const contentArea = document.getElementById("content-area");
    if (contentArea) {
        if (defaultLokasiHTML) {
            contentArea.innerHTML = defaultLokasiHTML;
            // Update jam real-time setelah load
            updateRealtimeClock();
        } else {
            // Jika defaultLokasiHTML belum tersimpan, coba simpan dulu
            initDefaultLokasiHTML();
            if (defaultLokasiHTML) {
                contentArea.innerHTML = defaultLokasiHTML;
                // Update jam real-time setelah load
                updateRealtimeClock();
            } else {
                // Jika masih belum ada, reload halaman
                location.reload();
            }
        }
    }
}
// Pastikan fungsi tersedia di window scope
window.loadLokasiList = loadLokasiList;

function loadPage(page) {
    fetch(page, {
        credentials: "include"  // WAJIB, agar PHP menerima session
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById("content-area").innerHTML = html;
    })
    .catch(() => {
        document.getElementById("content-area").innerHTML =
            '<div style="text-align:center;padding:50px;color:#c33;">Gagal memuat halaman</div>';
    });
}



function loadLokasiDetail(lokasi) {
    // Pastikan defaultLokasiHTML sudah tersimpan sebelum mengubah content-area
    if (!defaultLokasiHTML) {
        initDefaultLokasiHTML();
    }
    
    document.getElementById("content-area").innerHTML = '<div style="text-align: center; padding: 50px;"><i class="fa-solid fa-spinner fa-spin" style="font-size: 32px;"></i><p style="margin-top: 15px;">Memuat...</p></div>';

    fetch('detail_lokasi.php?lokasi=' + encodeURIComponent(lokasi))
        .then(res => res.text())
        .then(html => {
            document.getElementById("content-area").innerHTML = html;

            // Execute any scripts in the loaded content
            const scripts = document.querySelectorAll("#content-area script");
            scripts.forEach(scr => {
                const s = document.createElement("script");
                if (scr.src) {
                    s.src = scr.src;
                } else {
                    s.textContent = scr.innerHTML;
                }
                document.body.appendChild(s);
            });
            
            // Pastikan fungsi loadLokasiList tersedia setelah script dieksekusi
            setTimeout(function() {
                if (typeof window.loadLokasiList !== 'function') {
                    // Jika fungsi belum ada, definisikan ulang
                    window.loadLokasiList = function() {
                        const contentArea = document.getElementById("content-area");
                        if (contentArea && defaultLokasiHTML) {
                            contentArea.innerHTML = defaultLokasiHTML;
                        } else {
                            location.reload();
                        }
                    };
                }
            }, 200);
        })
        .catch(() => {
            document.getElementById("content-area").innerHTML = '<div style="text-align: center; padding: 50px; color: #c33;"><i class="fa-solid fa-exclamation-triangle" style="font-size: 32px;"></i><p style="margin-top: 15px;">Gagal memuat detail lokasi</p></div>';
        });
}
window.loadLokasiDetail = loadLokasiDetail;

// Auto-refresh halaman untuk update status berdasarkan waktu
// Refresh setiap 60 detik (1 menit) untuk update status real-time
let autoRefreshInterval = null;

function startAutoRefresh() {
    // Hanya auto-refresh jika di halaman utama (content-area berisi lokasi cards)
    const contentArea = document.getElementById("content-area");
    if (!contentArea) return;
    
    // Cek apakah content-area berisi lokasi cards (bukan halaman detail)
    const hasLokasiCards = contentArea.querySelector('.lokasi-grid') || contentArea.querySelector('.lokasi-card');
    
    if (hasLokasiCards) {
        // Clear interval sebelumnya jika ada
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
        
        // Set interval untuk auto-refresh setiap 60 detik
        autoRefreshInterval = setInterval(function() {
            // Reload halaman untuk update status berdasarkan waktu sekarang
            location.reload();
        }, 60000); // 60000 ms = 60 detik = 1 menit
    }
}

// Update jam real-time setiap detik
function updateRealtimeClock() {
    const clockElement = document.getElementById('realtime-clock');
    if (clockElement) {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        clockElement.textContent = hours + ':' + minutes + ':' + seconds;
    }
}

// Update jam setiap detik
setInterval(updateRealtimeClock, 1000);

// Update jam saat halaman dimuat
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        updateRealtimeClock();
        setTimeout(startAutoRefresh, 2000); // Tunggu 2 detik setelah DOM ready
    });
} else {
    updateRealtimeClock();
    setTimeout(startAutoRefresh, 2000);
}

// Stop auto-refresh saat user navigasi ke halaman lain
function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// Stop auto-refresh saat loadPage dipanggil (user klik menu)
const originalLoadPage = window.loadPage;
if (originalLoadPage) {
    window.loadPage = function(page) {
        stopAutoRefresh();
        originalLoadPage(page);
    };
}

// Stop auto-refresh saat loadLokasiDetail dipanggil
const originalLoadLokasiDetail = window.loadLokasiDetail;
if (originalLoadLokasiDetail) {
    window.loadLokasiDetail = function(lokasi) {
        stopAutoRefresh();
        originalLoadLokasiDetail(lokasi);
    };
}
</script>

<?php endif; ?>

</body>
</html>

