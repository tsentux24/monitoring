<?php
require_once 'includes/koneksi.php';

// Inisialisasi variabel
$search = $_GET['search'] ?? '';
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$kategori_merchant = $_GET['kategori_merchant'] ?? '';
$kabupaten_id = $_GET['kabupaten_id'] ?? '';

// Ambil data kabupaten dari database
$query_kabupaten = "SELECT * FROM kabupaten ORDER BY nama_kabupaten";
$result_kabupaten = $conn->query($query_kabupaten);
$kabupaten_list = [];
$kabupaten_logos = []; // Tambahkan variabel ini
while ($row = $result_kabupaten->fetch_assoc()) {
    $kabupaten_list[$row['id']] = $row['nama_kabupaten'];
    $kabupaten_logos[$row['id']] = $row['logo']; // Simpan nama file logo
}

// Fungsi executeQuery yang aman
function executeQuery($conn, $query, $params = [])
{
    // Handle queries that don't need parameters (filtered categories)
    if (strpos($query, 'SELECT 0 as total') !== false) {
        $result = $conn->query($query);
        return $result ? $result->fetch_assoc() : ['total' => 0, 'jumlah_alat' => 0];
    }

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return ['total' => 0, 'jumlah_alat' => 0];
    }

    if (!empty($params)) {
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : ['total' => 0, 'jumlah_alat' => 0];
}

// Query untuk semua kategori
$queries = [
    'TMD' => "SELECT COALESCE(SUM(IFNULL(r.transaksi_dpp, 0) + IFNULL(r.transaksi_pajak, 0)), 0) as total, 
              COUNT(DISTINCT a.id) as jumlah_alat 
              FROM alat a
              LEFT JOIN riwayat_alat r ON a.id = r.alat_id 
                 AND r.bulan = ?
                 AND r.tahun = ?
              WHERE a.kategori = 'TMD' AND a.kategori_merchant = 'PBJT'",

    'MPOS' => "SELECT COALESCE(SUM(IFNULL(r.transaksi_dpp, 0) + IFNULL(r.transaksi_pajak, 0)), 0) as total, 
               COUNT(DISTINCT a.id) as jumlah_alat 
               FROM alat a
               LEFT JOIN riwayat_alat r ON a.id = r.alat_id 
                  AND r.bulan = ?
                  AND r.tahun = ?
               WHERE a.kategori = 'MPOS' AND a.kategori_merchant = 'PBJT'",

    'ERetribusi' => "SELECT COALESCE(SUM(IFNULL(r.transaksi_retribusi, 0)), 0) as total, 
                    COUNT(DISTINCT a.id) as jumlah_alat 
                    FROM alat a
                    LEFT JOIN riwayat_alat r ON a.id = r.alat_id 
                       AND r.bulan = ?
                       AND r.tahun = ?
                    WHERE a.kategori_merchant = 'E-Retribusi'",

    'Samsat' => "SELECT COALESCE(SUM(IFNULL(r.transaksi_samsat, 0)), 0) as total, 
                COUNT(DISTINCT a.id) as jumlah_alat 
                FROM alat a
                LEFT JOIN riwayat_alat r ON a.id = r.alat_id 
                   AND r.bulan = ?
                   AND r.tahun = ?
                WHERE a.kategori_merchant = 'Samsat'"
];

// Tambahkan filter pencarian
if (!empty($search)) {
    $searchSafe = $conn->real_escape_string($search);
    foreach ($queries as &$query) {
        if (strpos($query, 'WHERE') !== false) {
            $query .= " AND a.nama_wp LIKE '%$searchSafe%'";
        }
    }
}

// Filter kategori merchant
if (!empty($kategori_merchant)) {
    $kategoriSafe = $conn->real_escape_string($kategori_merchant);
    switch ($kategoriSafe) {
        case 'PBJT':
            $queries['ERetribusi'] = "SELECT 0 as total, 0 as jumlah_alat";
            $queries['Samsat'] = "SELECT 0 as total, 0 as jumlah_alat";
            break;
        case 'E-Retribusi':
            $queries['TMD'] = "SELECT 0 as total, 0 as jumlah_alat";
            $queries['MPOS'] = "SELECT 0 as total, 0 as jumlah_alat";
            $queries['Samsat'] = "SELECT 0 as total, 0 as jumlah_alat";
            break;
        case 'Samsat':
            $queries['TMD'] = "SELECT 0 as total, 0 as jumlah_alat";
            $queries['MPOS'] = "SELECT 0 as total, 0 as jumlah_alat";
            $queries['ERetribusi'] = "SELECT 0 as total, 0 as jumlah_alat";
            break;
    }
}

// Filter kabupaten
if (!empty($kabupaten_id)) {
    $kabupatenSafe = $conn->real_escape_string($kabupaten_id);
    foreach ($queries as &$query) {
        if (strpos($query, 'WHERE') !== false) {
            $query .= " AND a.kabupaten_id = '$kabupatenSafe'";
        }
    }
}

// Eksekusi semua query
$results = [];
foreach ($queries as $key => $query) {
    // Hanya kirim parameter untuk query yang membutuhkan
    $results[$key] = (strpos($query, '?') !== false)
        ? executeQuery($conn, $query, [$bulan, $tahun])
        : executeQuery($conn, $query);
}

// Assign hasil
$totalTMD = $results['TMD']['total'] ?? 0;
$jumlahTMD = $results['TMD']['jumlah_alat'] ?? 0;

$totalMPOS = $results['MPOS']['total'] ?? 0;
$jumlahMPOS = $results['MPOS']['jumlah_alat'] ?? 0;

$totalERetribusi = $results['ERetribusi']['total'] ?? 0;
$jumlahERetribusi = $results['ERetribusi']['jumlah_alat'] ?? 0;

$totalSamsat = $results['Samsat']['total'] ?? 0;
$jumlahSamsat = $results['Samsat']['jumlah_alat'] ?? 0;

// Hitung total
$totalPBJT = $totalTMD + $totalMPOS;
$jumlahPBJT = $jumlahTMD + $jumlahMPOS;

$totalNonPBJT = $totalERetribusi + $totalSamsat;
$jumlahNonPBJT = $jumlahERetribusi + $jumlahSamsat;

$totalAll = $totalPBJT + $totalNonPBJT;
$jumlahAll = $jumlahPBJT + $jumlahNonPBJT;

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Alat - Bank Maluku</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style.css?v=1.1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="container">
        <header class="header">
            <img src="img/bank_maluku.jpg" alt="Logo Bank Maluku" class="logo">
            <div class="header-content">
                <h1>Dashboard Monitoring Alat</h1>
                <p class="subtitle">Sistem Pelaporan Perangkat Tapping Box, E-Retribusi dan Samsat Bank Maluku Malut</p>
            </div>
        </header>



        <!-- Total Transaksi -->
        <div class="dashboard-summary">
            <div style="display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                <!-- Card TMD -->
                <div class="summary-card tmd-card modern-card" style="flex: 1; min-width: 300px; max-width: 33%;">
                    <span class="live-indicator"></span>
                    <div class="card-icon">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Total Transaksi TMD</h3>
                        <p class="card-amount" id="count-tmd-total">Rp <?= number_format($totalTMD, 0, ',', '.') ?></p>
                        <p class="card-percentage" id="count-tmd-percent"><?= $totalAll > 0 ? round(($totalTMD / $totalAll) * 100, 2) : 0 ?>% dari total</p>
                        <p class="card-device-count" id="count-tmd-device"><?= $jumlahTMD ?> Alat</p>
                    </div>
                    <div class="card-wave"></div>
                </div>

                <!-- MPOS Card -->
                <div class="summary-card mpos-card modern-card" style="flex: 1; min-width: 300px; max-width: 33%;">
                    <span class="live-indicator"></span>
                    <div class="card-icon">
                        <i class="fas fa-tablet-screen-button"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Total Transaksi MPOS</h3>
                        <p class="card-amount" id="count-mpos-total">Rp <?= number_format($totalMPOS, 0, ',', '.') ?></p>
                        <p class="card-percentage" id="count-mpos-percent"><?= $totalAll > 0 ? round(($totalMPOS / $totalAll) * 100, 2) : 0 ?>% dari total</p>
                        <p class="card-device-count" id="count-mpos-device"><?= $jumlahMPOS ?> Alat</p>
                    </div>
                    <div class="card-wave"></div>
                </div>

                <!-- Total Card -->
                <div class="summary-card total-pbjt-card modern-card" style="flex: 1; min-width: 300px; max-width: 33%;">
                    <div class="card-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Total Keseluruhan PBJT</h3>
                        <p class="card-amount" id="count-pbjt-total">Rp <?= number_format($totalPBJT, 0, ',', '.') ?></p>
                        <p class="card-device-count" id="count-pbjt-device"><?= $jumlahPBJT ?> Alat</p>
                        <div class="trend-indicator">
                            <i class="fas fa-chart-line"></i>
                            <span>Ringkasan Bulan Ini</span>
                        </div>
                    </div>
                    <div class="card-wave"></div>
                </div>
            </div><br>

            <!-- Total Transaksi E-Retribusi-->
            <div class="dashboard-summary">
                <div style="display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                    <!-- Card E-Retribusi -->
                    <div class="summary-card eretribusi-card modern-card" style="flex: 1; min-width: 300px; max-width: 33%;">
                        <span class="live-indicator"></span>
                        <div class="card-icon">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Total Transaksi E-Retribusi</h3>
                            <p class="card-amount" id="count-eretribusi-total">Rp <?= number_format($totalERetribusi, 0, ',', '.') ?></p>
                            <p class="card-percentage" id="count-eretribusi-percent"><?= $totalAll > 0 ? round(($totalERetribusi / $totalAll) * 100, 2) : 0 ?>% dari total</p>
                            <p class="card-device-count" id="count-eretribusi-device"><?= $jumlahERetribusi ?> Alat</p>
                        </div>
                        <div class="card-wave"></div>
                    </div>

                    <div class="summary-card samsat-card modern-card" style="flex: 1; min-width: 300px; max-width: 33%;">
                        <span class="live-indicator"></span>
                        <div class="card-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Total Transaksi Samsat</h3>
                            <p class="card-amount" id="count-samsat-total">Rp <?= number_format($totalSamsat, 0, ',', '.') ?></p>
                            <p class="card-percentage" id="count-samsat-percent"><?= $totalAll > 0 ? round(($totalSamsat / $totalAll) * 100, 2) : 0 ?>% dari total</p>
                            <p class="card-device-count" id="count-samsat-device"><?= $jumlahSamsat ?> Alat</p>
                        </div>
                        <div class="card-wave"></div>
                    </div>

                    <!-- Card Total Non-PBJT -->
                    <div class="summary-card total-nonpbjt-card modern-card" style="flex: 1; min-width: 300px; max-width: 33%;">
                        <div class="card-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">Total E-Retribusi & Samsat</h3>
                            <p class="card-amount" id="count-nonpbjt-total">Rp <?= number_format($totalNonPBJT, 0, ',', '.') ?></p>
                            <p class="card-device-count" id="count-nonpbjt-device"><?= $jumlahNonPBJT ?> Alat</p>
                            <div class="trend-indicator">
                                <i class="fas fa-chart-line"></i>
                                <span>Ringkasan Bulan Ini</span>
                            </div>
                        </div>
                        <div class="card-wave"></div>
                    </div>
                </div><br>

                <!-- Form Gabungan Search & Filter -->
                <div class="filter-card modern-card">
                    <div class="filter-header">
                        <i class="fa-solid fa-filter"></i>
                        <h3>Filter Pencarian</h3>
                    </div>

                    <form id="searchForm" class="search-form">
                        <div class="form-grid">
                            <div class="form-group icon-input">
                                <label for="search">
                                    <i class="fas fa-search"></i>
                                    Cari Nama Merchant
                                </label>
                                <input type="text" name="search" id="search" placeholder="Masukkan nama...">
                                <input type="hidden" name="page" id="page" value="1">
                            </div>

                            <div class="form-group icon-input">
                                <label for="kategori_merchant">
                                    <i class="fas fa-tags"></i>
                                    Kategori Merchant
                                </label>
                                <select name="kategori_merchant" id="kategori_merchant">
                                    <option value="">Semua Kategori</option>
                                    <option value="PBJT">PBJT</option>
                                    <option value="E-Retribusi">E-Retribusi</option>
                                    <option value="Samsat">Samsat</option>
                                </select>
                            </div>

                            <div class="form-group icon-input">
                                <label for="kabupaten_id">
                                    <i class="fas fa-city"></i>
                                    Kabupaten/Kota
                                </label>
                                <select name="kabupaten_id" id="kabupaten_id" class="logo-dropdown">
                                    <option value="">Semua Kabupaten/Kota</option>
                                    <?php
                                    foreach ($kabupaten_list as $id => $nama) {
                                        $logo_path = !empty($kabupaten_logos[$id]) ?
                                            "admin/uploads/kabupaten/" . $kabupaten_logos[$id] :
                                            "img/default-city.png";
                                        $selected = ($kabupaten_id == $id) ? 'selected' : '';
                                        echo "<option value='$id' $selected data-logo='$logo_path'>$nama</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group icon-input">
                                <label for="bulan">
                                    <i class="fas fa-calendar-alt"></i>
                                    Bulan
                                </label>
                                <select name="bulan" id="bulan">
                                    <option value="">Semua Bulan</option>
                                    <?php
                                    $bulanList = [
                                        '01' => 'Januari',
                                        '02' => 'Februari',
                                        '03' => 'Maret',
                                        '04' => 'April',
                                        '05' => 'Mei',
                                        '06' => 'Juni',
                                        '07' => 'Juli',
                                        '08' => 'Agustus',
                                        '09' => 'September',
                                        '10' => 'Oktober',
                                        '11' => 'November',
                                        '12' => 'Desember'
                                    ];
                                    foreach ($bulanList as $key => $value) {
                                        $selected = ($bulan == $key) ? 'selected' : '';
                                        echo "<option value='$key' $selected>$value</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group icon-input">
                                <label for="tahun">
                                    <i class="fas fa-calendar"></i>
                                    Tahun
                                </label>
                                <select name="tahun" id="tahun">
                                    <option value="">Semua Tahun</option>
                                    <?php
                                    $tahunSekarang = date('Y');
                                    for ($i = $tahunSekarang; $i >= $tahunSekarang - 5; $i--) {
                                        $selected = ($tahun == $i) ? 'selected' : '';
                                        echo "<option value='$i' $selected>$i</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Data Table Section -->
                <div class="data-table-container">
                    <div id="result">
                        <?php include 'load_data.php'; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a id="downloadLink" href="download_laporan.php?search=<?= urlencode($search) ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&kabupaten_id=<?= $kabupaten_id ?>" target="_blank" class="download-btn">
                        <i class="fas fa-file-pdf"></i> Download Laporan PDF
                    </a>
                    <button class="refresh-btn" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                </div>

                <!-- Footer dengan Logo Kabupaten -->
                <footer style="background: linear-gradient(135deg, #2c3e50 0%, #1a2530 100%); padding: 2rem; margin-top: 3rem; border-radius: 12px; color: white;">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <h3 style="color: #fff; margin-bottom: 1rem;">Wilayah Kerja Bank Maluku Malut</h3>
                        <p style="color: #b8c2cc;">Melayani berbagai kabupaten/kota di wilayah Maluku & Maluku Utara</p>
                    </div>

                    <div class="kabupaten-logos" style="display: flex; justify-content: center; flex-wrap: wrap; gap: 1.5rem;">
                        <?php
                        // Query untuk mendapatkan data kabupaten
                        $query_kabupaten_footer = "SELECT * FROM kabupaten ORDER BY nama_kabupaten";
                        $result_kabupaten_footer = $conn->query($query_kabupaten_footer);

                        if ($result_kabupaten_footer && $result_kabupaten_footer->num_rows > 0) {
                            while ($kabupaten = $result_kabupaten_footer->fetch_assoc()) {
                                // Path yang benar ke folder uploads yang berada di dalam admin/
                                $logo_path = !empty($kabupaten['logo']) ?
                                    "admin/uploads/kabupaten/" . $kabupaten['logo'] :
                                    "img/default-city.png";

                                // Cek apakah file logo ada
                                $logo_exists = file_exists($logo_path);

                                echo '
                <div class="kabupaten-item" style="text-align: center; transition: transform 0.3s ease;">
                    <div class="logo-container" style="width: 80px; height: 80px; border-radius: 50%; background: ' . ($logo_exists ? 'white' : '#f8f9fa') . '; padding: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                        <img src="' . $logo_path . '" alt="' . htmlspecialchars($kabupaten['nama_kabupaten']) . '" 
                             style="max-width: 100%; max-height: 100%; object-fit: contain;"
                             onerror="this.onerror=null; this.src=\'img/default-city.png\'; this.style.opacity=\'0.7\'">
                        ' . (!$logo_exists ? '<div style="font-size: 0.6rem; color: #999; text-align: center;">No Logo</div>' : '') . '
                    </div>
                    <p style="margin: 0; font-size: 0.9rem; color: #e2e8f0;">' . htmlspecialchars($kabupaten['nama_kabupaten']) . '</p>
                </div>';
                            }
                        } else {
                            // Fallback jika tidak ada data kabupaten
                            $default_kabupaten = array("Ambon", "Buru", "Kapulauan Aru", "Maluku Tengah", "Maluku Tenggara", "Seram Bagian Barat", "Tanimbar");

                            foreach ($default_kabupaten as $nama) {
                                echo '
                <div class="kabupaten-item" style="text-align: center; transition: transform 0.3s ease;">
                    <div class="logo-container" style="width: 80px; height: 80px; border-radius: 50%; background: #f8f9fa; padding: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                        <i class="fas fa-city" style="font-size: 2rem; color: #6c757d;"></i>
                    </div>
                    <p style="margin: 0; font-size: 0.9rem; color: #e2e8f0;">' . htmlspecialchars($nama) . '</p>
                </div>';
                            }
                        }
                        ?>
                    </div>

                    <div style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #4a5568;">
                        <p style="color: #b8c2cc; margin: 0;">&copy; <?php echo date('Y'); ?> Bank Maluku Malut. All rights reserved.</p>
                    </div>
                </footer>

                <!-- Font Awesome untuk ikon fallback -->
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

                <style>
                    .kabupaten-item:hover {
                        transform: translateY(-5px);
                    }

                    .logo-container:hover {
                        box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
                    }

                    @media (max-width: 768px) {
                        .kabupaten-logos {
                            gap: 1rem;
                        }

                        .kabupaten-item {
                            flex: 0 0 calc(33.333% - 1rem);
                        }
                    }

                    @media (max-width: 576px) {
                        .kabupaten-item {
                            flex: 0 0 calc(50% - 1rem);
                        }
                    }
                </style>

                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const form = document.getElementById("searchForm");
                        const searchInput = document.getElementById("search");
                        const bulan = document.getElementById("bulan");
                        const tahun = document.getElementById("tahun");
                        const kabupatenId = document.getElementById("kabupaten_id");
                        const pageInput = document.getElementById("page");
                        const resultContainer = document.getElementById("result");
                        const downloadLink = document.getElementById("downloadLink");

                        // Handle perubahan select
                        document.getElementById('kategori_merchant').addEventListener('change', function() {
                            pageInput.value = 1;
                            updateData();
                            updateTransactionSummary();
                        });

                        document.getElementById('kabupaten_id').addEventListener('change', function() {
                            pageInput.value = 1;
                            updateData();
                            updateTransactionSummary();
                        });

                        document.getElementById('bulan').addEventListener('change', function() {
                            pageInput.value = 1;
                            updateData();
                            updateTransactionSummary();
                        });

                        document.getElementById('tahun').addEventListener('change', function() {
                            pageInput.value = 1;
                            updateData();
                            updateTransactionSummary();
                        });

                        function updateData() {
                            const formData = new FormData(form);
                            const kategoriMerchant = document.getElementById('kategori_merchant').value;
                            const kabupatenIdVal = document.getElementById('kabupaten_id').value;
                            formData.append('kategori_merchant', kategoriMerchant);
                            formData.append('kabupaten_id', kabupatenIdVal);
                            const scrollPosition = window.scrollY || window.pageYOffset;

                            // Tampilkan loading
                            resultContainer.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Memuat data...</div>';

                            fetch("load_data.php", {
                                    method: "POST",
                                    body: formData
                                })
                                .then(response => {
                                    if (!response.ok) throw new Error("Network response was not ok");
                                    return response.text();
                                })
                                .then(data => {
                                    resultContainer.innerHTML = data;
                                    // Kembalikan posisi scroll
                                    window.scrollTo(0, scrollPosition);

                                    // Update URL tanpa reload
                                    const params = new URLSearchParams(formData);
                                    history.replaceState(null, '', '?' + params.toString());
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    resultContainer.innerHTML = '<div class="error-state">Gagal memuat data. Silakan coba lagi.</div>';
                                    window.scrollTo(0, scrollPosition);
                                });

                            // Update download link
                            // Update download link
                            const searchVal = encodeURIComponent(searchInput.value);
                            const bulanVal = bulan.value;
                            const tahunVal = tahun.value;
                            const kabupatenVal = kabupatenId.value;
                            const kategoriMerchantVal = document.getElementById('kategori_merchant').value;
                            const pageVal = pageInput.value;
                            downloadLink.href = `download_laporan.php?search=${searchVal}&bulan=${bulanVal}&tahun=${tahunVal}&kabupaten_id=${kabupatenVal}&kategori_merchant=${kategoriMerchantVal}&page=${pageVal}`;
                        }

                        // Handle pagination click
                        document.addEventListener('click', function(e) {
                            if (e.target.closest('.page-link')) {
                                e.preventDefault();
                                const pageLink = e.target.closest('.page-link');
                                if (pageLink.classList.contains('disabled')) return;

                                pageInput.value = pageLink.dataset.page;
                                updateData();
                            }
                        });

                        // Live search dan filter
                        let timer;
                        form.addEventListener("input", function() {
                            clearTimeout(timer);
                            timer = setTimeout(() => {
                                pageInput.value = 1;
                                updateData();
                            }, 300);
                        });

                        bulan.addEventListener("change", updateData);
                        tahun.addEventListener("change", updateData);
                        kabupatenId.addEventListener("change", updateData);

                        form.addEventListener("input", function() {
                            clearTimeout(timer);
                            timer = setTimeout(() => {
                                pageInput.value = 1;
                                updateData();
                                updateTransactionSummary(); // Panggil fungsi update summary
                            }, 300);
                        });

                        // Fungsi untuk update summary
                        function updateTransactionSummary() {
                            const formData = new FormData(form);
                            const kategoriMerchant = document.getElementById('kategori_merchant').value;
                            const kabupatenIdVal = document.getElementById('kabupaten_id').value;
                            formData.append('kategori_merchant', kategoriMerchant);
                            formData.append('kabupaten_id', kabupatenIdVal);

                            fetch("get_transaction_summary.php", {
                                    method: "POST",
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status !== 'success') return;

                                    const formatNumber = num => new Intl.NumberFormat('id-ID').format(num);
                                    const results = data.data;

                                    // Update card individual
                                    updateCard('.tmd-card', results.TMD, formatNumber, results.totalAll);
                                    updateCard('.mpos-card', results.MPOS, formatNumber, results.totalAll);
                                    updateCard('.eretribusi-card', results.ERetribusi, formatNumber, results.totalAll);
                                    updateCard('.samsat-card', results.Samsat, formatNumber, results.totalAll);

                                    // Update card total
                                    updateTotalCard('.total-pbjt-card',
                                        results.TMD.total + results.MPOS.total,
                                        results.TMD.jumlah_alat + results.MPOS.jumlah_alat,
                                        formatNumber
                                    );

                                    updateTotalCard('.total-nonpbjt-card',
                                        results.ERetribusi.total + results.Samsat.total,
                                        results.ERetribusi.jumlah_alat + results.Samsat.jumlah_alat,
                                        formatNumber
                                    );
                                })
                                .catch(error => console.error('Error:', error));
                        }

                        // Panggil saat pertama kali load
                        updateTransactionSummary();
                    });

                    // Untuk efek acak yang lebih dinamis
                    function randomizePulse() {
                        const indicators = document.querySelectorAll('.live-indicator');
                        indicators.forEach(indicator => {
                            // Randomize animation duration between 1s and 2s
                            const duration = 1 + Math.random();
                            indicator.style.animationDuration = `${duration}s`;

                            // Slightly randomize pulse size
                            const size = 0.9 + Math.random() * 0.3;
                            indicator.style.transform = `scale(${size})`;
                        });
                    }

                    // Fungsi update card individual
                    function updateCard(selector, data, formatNumber, totalAll) {
                        const card = document.querySelector(selector);
                        if (!card) return;

                        const amountElement = card.querySelector('.card-amount');
                        const percentageElement = card.querySelector('.card-percentage');
                        const deviceCountElement = card.querySelector('.card-device-count');

                        if (amountElement) {
                            amountElement.textContent = `Rp ${formatNumber(data.total)}`;
                        }

                        if (percentageElement && totalAll > 0) {
                            const percentage = ((data.total / totalAll) * 100).toFixed(2);
                            percentageElement.textContent = `${percentage}% dari total`;
                        }

                        if (deviceCountElement) {
                            deviceCountElement.textContent = `${data.jumlah_alat} Alat`;
                        }
                    }

                    // Fungsi update card total
                    function updateTotalCard(selector, total, jumlahAlat, formatNumber) {
                        const card = document.querySelector(selector);
                        if (!card) return;

                        const amountElement = card.querySelector('.card-amount');
                        const deviceCountElement = card.querySelector('.card-device-count');

                        if (amountElement) {
                            amountElement.textContent = `Rp ${formatNumber(total)}`;
                        }

                        if (deviceCountElement) {
                            deviceCountElement.textContent = `${jumlahAlat} Alat`;
                        }
                    }

                    // Fungsi untuk animasi count up
                    function animateValue(element, start, end, duration) {
                        let startTimestamp = null;
                        const step = (timestamp) => {
                            if (!startTimestamp) startTimestamp = timestamp;
                            const progress = Math.min((timestamp - startTimestamp) / duration, 1);

                            // Format angka dengan pemisah ribuan
                            const value = Math.floor(progress * (end - start) + start);
                            element.textContent = element.textContent.startsWith('Rp') ?
                                'Rp ' + value.toLocaleString('id-ID') :
                                value.toLocaleString('id-ID');

                            if (progress < 1) {
                                window.requestAnimationFrame(step);
                            }
                        };
                        window.requestAnimationFrame(step);
                    }

                    // Fungsi untuk memulai semua animasi count up
                    function startCountAnimations() {
                        // TMD Card
                        animateValue(document.getElementById('count-tmd-total'), 0, <?= $totalTMD ?>, 2000);
                        animateValue(document.getElementById('count-tmd-device'), 0, <?= $jumlahTMD ?>, 1500);

                        // MPOS Card
                        animateValue(document.getElementById('count-mpos-total'), 0, <?= $totalMPOS ?>, 2000);
                        animateValue(document.getElementById('count-mpos-device'), 0, <?= $jumlahMPOS ?>, 1500);

                        // PBJT Total Card
                        animateValue(document.getElementById('count-pbjt-total'), 0, <?= $totalPBJT ?>, 2000);
                        animateValue(document.getElementById('count-pbjt-device'), 0, <?= $jumlahPBJT ?>, 1500);

                        // E-Retribusi Card
                        animateValue(document.getElementById('count-eretribusi-total'), 0, <?= $totalERetribusi ?>, 2000);
                        animateValue(document.getElementById('count-eretribusi-device'), 0, <?= $jumlahERetribusi ?>, 1500);

                        // Samsat Card
                        animateValue(document.getElementById('count-samsat-total'), 0, <?= $totalSamsat ?>, 2000);
                        animateValue(document.getElementById('count-samsat-device'), 0, <?= $jumlahSamsat ?>, 1500);

                        // Non-PBJT Total Card
                        animateValue(document.getElementById('count-nonpbjt-total'), 0, <?= $totalNonPBJT ?>, 2000);
                        animateValue(document.getElementById('count-nonpbjt-device'), 0, <?= $jumlahNonPBJT ?>, 1500);
                    }

                    // Jalanim animasi saat halaman dimuat
                    document.addEventListener("DOMContentLoaded", function() {
                        startCountAnimations();

                        // Untuk update data real-time, panggil fungsi ini setelah data diperbarui
                        // startCountAnimations();
                    });

                    // Update setiap 5 detik
                    setInterval(randomizePulse, 5000);



                    document.addEventListener("DOMContentLoaded", function() {
                        // Fungsi untuk menambahkan logo ke dropdown kabupaten
                        function initLogoDropdown() {
                            const kabupatenDropdown = document.getElementById('kabupaten_id');
                            if (!kabupatenDropdown) return;

                            // Set logo untuk setiap opsi
                            const options = kabupatenDropdown.querySelectorAll('option[data-logo]');
                            options.forEach(option => {
                                const logoUrl = option.getAttribute('data-logo');
                                option.style.setProperty('--logo-url', `url(${logoUrl})`);
                            });

                            // Untuk browser modern yang mendukung styling pada dropdown
                            try {
                                // Coba terapkan styling
                                kabupatenDropdown.classList.add('logo-dropdown');
                            } catch (e) {
                                console.log('Browser tidak mendukung styling dropdown lengkap');
                            }
                        }

                        // Panggil fungsi inisialisasi
                        initLogoDropdown();

                        // Alternatif: Buat custom dropdown jika diperlukan
                        // (kode untuk custom dropdown bisa ditambahkan di sini jika diperlukan)
                    });
                </script>
</body>

</html>