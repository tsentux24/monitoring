<?php
require_once 'includes/koneksi.php';


// Ambil parameter
$search = $_POST['search'] ?? '';
$bulan = $_POST['bulan'] ?? '';
$tahun = $_POST['tahun'] ?? '';
$kategori_merchant = $_POST['kategori_merchant'] ?? '';

// Validasi input
$bulan = is_numeric($bulan) ? (int)$bulan : '';
$tahun = is_numeric($tahun) ? (int)$tahun : '';

// Query untuk masing-masing kategori
$queryTMD = "SELECT 
            COALESCE(SUM(IFNULL(r.transaksi_dpp, 0) + IFNULL(r.transaksi_pajak, 0)), 0) as total, 
            COUNT(DISTINCT a.id) as jumlah_alat 
            FROM alat a
            LEFT JOIN riwayat_alat r ON a.id = r.alat_id 
                AND r.bulan = '$bulan' 
                AND r.tahun = '$tahun'
            WHERE a.kategori = 'TMD' AND a.kategori_merchant = 'PBJT'";

$queryMPOS = "SELECT 
             COALESCE(SUM(IFNULL(r.transaksi_dpp, 0) + IFNULL(r.transaksi_pajak, 0)), 0) as total, 
             COUNT(DISTINCT a.id) as jumlah_alat 
             FROM alat a
             LEFT JOIN riwayat_alat r ON a.id = r.alat_id 
                 AND r.bulan = '$bulan' 
                 AND r.tahun = '$tahun'
             WHERE a.kategori = 'MPOS' AND a.kategori_merchant = 'PBJT'";

$queryERetribusi = "SELECT 
                   COALESCE(SUM(IFNULL(r.transaksi_retribusi, 0)), 0) as total,
                   COUNT(DISTINCT a.id) as jumlah_alat
                   FROM alat a
                   LEFT JOIN riwayat_alat r ON a.id = r.alat_id 
                       AND r.bulan = '$bulan' 
                       AND r.tahun = '$tahun'
                   WHERE a.kategori_merchant = 'E-Retribusi'";

$querySamsat = "SELECT 
               COALESCE(SUM(IFNULL(r.transaksi_samsat, 0)), 0) as total,
               COUNT(DISTINCT a.id) as jumlah_alat
               FROM alat a
               LEFT JOIN riwayat_alat r ON a.id = r.alat_id 
                   AND r.bulan = '$bulan' 
                   AND r.tahun = '$tahun'
               WHERE a.kategori_merchant = 'Samsat'";

// Filter pencarian
if (!empty($search)) {
    $searchSafe = $conn->real_escape_string($search);
    $queryTMD .= " AND a.nama_wp LIKE '%$searchSafe%'";
    $queryMPOS .= " AND a.nama_wp LIKE '%$searchSafe%'";
    $queryERetribusi .= " AND a.nama_wp LIKE '%$searchSafe%'";
    $querySamsat .= " AND a.nama_wp LIKE '%$searchSafe%'";
}

// Filter tanggal
if (!empty($bulan) && !empty($tahun)) {
    $queryTMD .= " AND r.bulan = $bulan AND r.tahun = $tahun";
    $queryMPOS .= " AND r.bulan = $bulan AND r.tahun = $tahun";
    $queryERetribusi .= " AND r.bulan = $bulan AND r.tahun = $tahun";
    $querySamsat .= " AND r.bulan = $bulan AND r.tahun = $tahun";
}

// Filter kategori merchant
// Filter kategori merchant
if (!empty($kategori_merchant)) {
    $kategoriSafe = $conn->real_escape_string($kategori_merchant);
    switch ($kategoriSafe) {
        case 'PBJT':
            $queryERetribusi = "SELECT 0 as total, 0 as jumlah_alat";
            $querySamsat = "SELECT 0 as total, 0 as jumlah_alat";
            break;
        case 'E-Retribusi':
            $queryTMD = "SELECT 0 as total, 0 as jumlah_alat";
            $queryMPOS = "SELECT 0 as total, 0 as jumlah_alat";
            $querySamsat = "SELECT 0 as total, 0 as jumlah_alat";
            break;
        case 'Samsat':
            $queryTMD = "SELECT 0 as total, 0 as jumlah_alat";
            $queryMPOS = "SELECT 0 as total, 0 as jumlah_alat";
            $queryERetribusi = "SELECT 0 as total, 0 as jumlah_alat";
            break;
    }
}

// Fungsi untuk menjalankan query
function runQuery($conn, $query) {
    $result = $conn->query($query);
    if (!$result) {
        error_log("Query error: " . $conn->error);
        error_log("Query: " . $query);
        return ['total' => 0, 'jumlah_alat' => 0];
    }
    return $result->fetch_assoc();
}

// Eksekusi semua query
$tmd = runQuery($conn, $queryTMD);
$mpos = runQuery($conn, $queryMPOS);
$eretribusi = runQuery($conn, $queryERetribusi);
$samsat = runQuery($conn, $querySamsat);

// Hitung total keseluruhan
$totalAll = $tmd['total'] + $mpos['total'] + $eretribusi['total'] + $samsat['total'];
$totalAlat = $tmd['jumlah_alat'] + $mpos['jumlah_alat'] + $eretribusi['jumlah_alat'] + $samsat['jumlah_alat'];

// Response JSON
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'data' => [
        'TMD' => [
            'total' => (float)$tmd['total'],
            'jumlah_alat' => (int)$tmd['jumlah_alat']
        ],
        'MPOS' => [
            'total' => (float)$mpos['total'],
            'jumlah_alat' => (int)$mpos['jumlah_alat']
        ],
        'ERetribusi' => [
            'total' => (float)$eretribusi['total'],
            'jumlah_alat' => (int)$eretribusi['jumlah_alat']
        ],
        'Samsat' => [
            'total' => (float)$samsat['total'],
            'jumlah_alat' => (int)$samsat['jumlah_alat']
        ],
        'totalAll' => (float)$totalAll,
        'totalAlat' => (int)$totalAlat
    ]
]);
?>