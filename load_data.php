<?php
require_once 'includes/koneksi.php';

// Ambil parameter
$search = $_POST['search'] ?? '';
$bulan = $_POST['bulan'] ?? '';
$tahun = $_POST['tahun'] ?? '';
$page = $_POST['page'] ?? 1;
$kategori_merchant = $_POST['kategori_merchant'] ?? '';
$kabupaten_id = $_POST['kabupaten_id'] ?? ''; // Tambahkan parameter kabupaten_id
$perPage = 5;

// Validasi
$page = max(1, (int)$page);
$perPage = max(1, (int)$perPage);
$offset = ($page - 1) * $perPage;

// Query utama dengan JOIN
$query = "SELECT a.*, k.nama_kabupaten, a.kategori_merchant,
         COALESCE(r.transaksi_dpp, a.transaksi_dpp) as transaksi_dpp,
         COALESCE(r.transaksi_pajak, a.transaksi_pajak) as transaksi_pajak,
         COALESCE(r.transaksi_retribusi, a.transaksi_retribusi) as transaksi_retribusi,
         COALESCE(r.transaksi_samsat, a.transaksi_samsat) as transaksi_samsat,
         COALESCE(r.masalah, a.masalah) as masalah,
         COALESCE(r.solusi, a.solusi) as solusi
         FROM alat a
         LEFT JOIN kabupaten k ON a.kabupaten_id = k.id
         LEFT JOIN riwayat_alat r ON a.id = r.alat_id 
             AND r.bulan = '".intval($bulan)."' 
             AND r.tahun = '".intval($tahun)."'
         WHERE 1=1";

// Query count dengan JOIN ke riwayat_alat
$countQuery = "SELECT COUNT(DISTINCT a.id) as total FROM alat a
               LEFT JOIN riwayat_alat r ON a.id = r.alat_id
               WHERE 1=1";

// Filter kategori merchant
if (!empty($kategori_merchant)) {
    $kategori = $conn->real_escape_string($kategori_merchant);
    $query .= " AND a.kategori_merchant = '$kategori'";
    $countQuery .= " AND a.kategori_merchant = '$kategori'";
}

// Filter kabupaten (ditambahkan)
if (!empty($kabupaten_id)) {
    $kabupaten = $conn->real_escape_string($kabupaten_id);
    $query .= " AND a.kabupaten_id = '$kabupaten'";
    $countQuery .= " AND a.kabupaten_id = '$kabupaten'";
}

if (!empty($search)) {
    $searchSafe = $conn->real_escape_string($search);
    $query .= " AND a.nama_wp LIKE '%$searchSafe%'";
    $countQuery .= " AND a.nama_wp LIKE '%$searchSafe%'";
}

// Filter tanggal
if (!empty($bulan) && !empty($tahun)) {
    // Tidak perlu filter tambahan karena sudah di JOIN condition
}

// Urutkan dan limit
$query .= " ORDER BY CASE 
              WHEN a.kategori_merchant = 'PBJT' AND a.kategori = 'TMD' THEN 1 
              WHEN a.kategori_merchant = 'PBJT' AND a.kategori = 'MPOS' THEN 2
              WHEN a.kategori_merchant = 'E-Retribusi' THEN 3
              WHEN a.kategori_merchant = 'Samsat' THEN 4
              ELSE 5 
           END, a.created_at DESC
           LIMIT $perPage OFFSET $offset";

// Eksekusi query data
$data = $conn->query($query);

// Hitung total data untuk pagination
$totalResult = $conn->query($countQuery);
$totalRow = $totalResult->fetch_assoc();
$totalData = $totalRow['total'];
$totalPages = ceil($totalData / $perPage);

// Tampilkan data
if ($data && $data->num_rows > 0) {
    echo '<div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama WP</th>
                        <th>Kategori</th>
                        <th>Vendor</th>
                        <th>Merchant</th>
                        <th>Kabupaten/Kota</th>'; // Tambahkan kolom Kabupaten/Kota
    
    // Tampilkan kolom berdasarkan kategori merchant
    if (empty($kategori_merchant) || $kategori_merchant == 'PBJT') {
        echo '<th class="text-right">DPP</th>
              <th class="text-right">Pajak</th>';
    } elseif ($kategori_merchant == 'E-Retribusi') {
        echo '<th class="text-right">Retribusi</th>';
    } elseif ($kategori_merchant == 'Samsat') {
        echo '<th class="text-right">Samsat</th>';
    }
    
    echo '      <th>Masalah</th>
                <th>Solusi</th>
                    </tr>
                </thead>
                <tbody>';

    $counter = $offset + 1;
    while ($row = $data->fetch_assoc()) {
        // PERBAIKAN DI SINI - Konversi \r\n menjadi karakter baris baru
        $masalah = $row['masalah'] ?: '';
        $solusi = $row['solusi'] ?: '';
        
        // Ganti \r\n dengan karakter baris baru yang sebenarnya
        $masalah = str_replace('\r\n', "\r\n", $masalah);
        $solusi = str_replace('\r\n', "\r\n", $solusi);
        
        echo '<tr>
                <td>' . $counter++ . '</td>
                <td>' . htmlspecialchars($row['nama_wp']) . '</td>
                <td><span class="status-badge ' . ($row['kategori'] == 'TMD' ? 'status-active' : 'status-inactive') . '">' . htmlspecialchars($row['kategori']) . '</span></td>
                <td>' . htmlspecialchars($row['vendor']) . '</td>
                <td><span class="merchant-badge ' . strtolower(str_replace('-', '', $row['kategori_merchant'])) . '">' . htmlspecialchars($row['kategori_merchant']) . '</span></td>
                <td>' . htmlspecialchars($row['nama_kabupaten']) . '</td>'; // Tambahkan data kabupaten
        
        // Tampilkan nilai berdasarkan kategori merchant
        if (empty($kategori_merchant) || $kategori_merchant == 'PBJT') {
            echo '<td class="text-right">' . number_format($row['transaksi_dpp'], 2) . '</td>
                  <td class="text-right">' . number_format($row['transaksi_pajak'], 2) . '</td>';
        } elseif ($kategori_merchant == 'E-Retribusi') {
            echo '<td class="text-right">' . number_format($row['transaksi_retribusi'], 2) . '</td>';
        } elseif ($kategori_merchant == 'Samsat') {
            echo '<td class="text-right">' . number_format($row['transaksi_samsat'], 2) . '</td>';
        }
        
        echo '  <td>' . ($masalah ? nl2br(htmlspecialchars($masalah)) : '-') . '</td>
                <td>' . ($solusi ? nl2br(htmlspecialchars($solusi)) : '-') . '</td>
              </tr>';
    }

    echo '</tbody></table></div>';

    // Tampilkan pagination
    if ($totalPages > 1) {
        echo '<div class="pagination">';

        // Previous button
        if ($page > 1) {
            echo '<a href="#" class="page-link" data-page="' . ($page - 1) . '"><i class="fas fa-chevron-left"></i></a>';
        } else {
            echo '<a href="#" class="page-link disabled"><i class="fas fa-chevron-left"></i></a>';
        }

        // Page numbers
        $startPage = max(1, min($page - 2, $totalPages - 4));
        $endPage = min($totalPages, $page + 2);

        if ($startPage > 1) {
            echo '<a href="#" class="page-link" data-page="1">1</a>';
            if ($startPage > 2) echo '<span class="page-dots">...</span>';
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<a href="#" class="page-link ' . $active . '" data-page="' . $i . '">' . $i . '</a>';
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) echo '<span class="page-dots">...</span>';
            echo '<a href="#" class="page-link" data-page="' . $totalPages . '">' . $totalPages . '</a>';
        }

        // Next button
        if ($page < $totalPages) {
            echo '<a href="#" class="page-link" data-page="' . ($page + 1) . '"><i class="fas fa-chevron-right"></i></a>';
        } else {
            echo '<a href="#" class="page-link disabled"><i class="fas fa-chevron-right"></i></a>';
        }

        echo '</div>';
    }
} else {
    echo '<div class="empty-state">
            <i class="fas fa-database"></i>
            <p>Tidak ada data ditemukan</p>
          </div>';
}
?>