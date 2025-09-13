<?php
require_once 'includes/koneksi.php';
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

// Ambil parameter
$search = $_GET['search'] ?? '';
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$kategori_merchant = $_GET['kategori_merchant'] ?? '';
$kabupaten_id = $_GET['kabupaten_id'] ?? ''; // Tambahkan parameter kabupaten_id

// Query database dengan JOIN ke riwayat_alat
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
             AND r.bulan = '$bulan' 
             AND r.tahun = '$tahun'
         WHERE 1=1";

// Filter kategori merchant
if (!empty($kategori_merchant)) {
    $query .= " AND a.kategori_merchant = '" . $conn->real_escape_string($kategori_merchant) . "'";
}

// Filter kabupaten (ditambahkan)
if (!empty($kabupaten_id)) {
    $query .= " AND a.kabupaten_id = '" . $conn->real_escape_string($kabupaten_id) . "'";
}

if (!empty($search)) {
    $searchSafe = $conn->real_escape_string($search);
    $query .= " AND a.nama_wp LIKE '%$searchSafe%'";
}

// Urutkan berdasarkan kategori merchant dan kategori
$query .= " ORDER BY CASE 
              WHEN a.kategori_merchant = 'PBJT' AND a.kategori = 'TMD' THEN 1 
              WHEN a.kategori_merchant = 'PBJT' AND a.kategori = 'MPOS' THEN 2
              WHEN a.kategori_merchant = 'E-Retribusi' THEN 3
              WHEN a.kategori_merchant = 'Samsat' THEN 4
              ELSE 5 
           END, a.created_at DESC";

$data = $conn->query($query);

// Hitung total
$totalTMD = 0;
$totalMPOS = 0;
$totalERetribusi = 0;
$totalSamsat = 0;
$totalTransaksiTMD = 0;
$totalTransaksiMPOS = 0;
$totalTransaksiERetribusi = 0;
$totalTransaksiSamsat = 0;
$totalPajakTMD = 0;
$totalPajakMPOS = 0;
$allRows = [];
$counter = 1;

if ($data && $data->num_rows > 0) {
    while ($row = $data->fetch_assoc()) {
        // PERBAIKAN DI SINI - Konversi \r\n menjadi karakter baris baru
        $row['masalah'] = str_replace('\r\n', "\r\n", $row['masalah'] ?? '');
        $row['solusi'] = str_replace('\r\n', "\r\n", $row['solusi'] ?? '');
        
        $row['nomor'] = $counter++;
        $allRows[] = $row;
        
        // Hitung berdasarkan kategori merchant
        switch ($row['kategori_merchant']) {
            case 'PBJT':
                if ($row['kategori'] == 'TMD') {
                    $totalTMD++;
                    $totalTransaksiTMD += $row['transaksi_dpp'] + $row['transaksi_pajak'];
                    $totalPajakTMD += $row['transaksi_pajak'];
                } elseif ($row['kategori'] == 'MPOS') {
                    $totalMPOS++;
                    $totalTransaksiMPOS += $row['transaksi_dpp'] + $row['transaksi_pajak'];
                    $totalPajakMPOS += $row['transaksi_pajak'];
                }
                break;
                
            case 'E-Retribusi':
                $totalERetribusi++;
                $totalTransaksiERetribusi += $row['transaksi_retribusi'];
                break;
                
            case 'Samsat':
                $totalSamsat++;
                $totalTransaksiSamsat += $row['transaksi_samsat'];
                break;
        }
    }
}

// Logo
$logoPath = __DIR__.'/img/bank_maluku.jpg';
$logoHtml = file_exists($logoPath) 
    ? '<div style="position:absolute; top:10px; left:10px; width:150px; text-align:left;">
         <img src="data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath)).'" style="height:50px; max-width:190px;">
       </div>'
    : '<div style="color:red; position:absolute; top:10px; left:10px;">[Logo tidak ditemukan]</div>';

// HTML Structure
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 20px 10px 10px 10px;
            position: relative;
        }
        .header-space { height: 80px; }
        .judul { text-align: center; margin-bottom: 15px; }
        .judul h2 { margin: 0; padding: 0; font-size: 16pt; }
        .judul p { margin: 5px 0 0 0; font-size: 10pt; }
        .transaction-info {
            margin: 15px 0;
            padding: 10px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .transaction-info p {
            margin: 5px 0;
            font-size: 10pt;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f2f2f2; font-weight: bold; padding: 6px; border: 1px solid #000; text-align: center; font-size: 8pt; }
        td { padding: 5px; border: 1px solid #000; text-align: left; vertical-align: top; font-size: 8pt; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .summary-total {
            margin-top: 15px;
            padding: 8px;
            background-color: #f5f5f5;
            border-top: 1px solid #ddd;
            font-weight: bold;
            text-align: center;
        }
        .merchant-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .pbjt { background-color: #d4edff; color: #0056b3; }
        .eretribusi { background-color: #e0ffe0; color: #1e7a1e; }
        .samsat { background-color: #fff0f0; color: #c82333; }
        /* PERBAIKAN: Style untuk menjaga format baris baru */
        .multiline-cell {
            white-space: pre-line;
            line-height: 1.3;
        }
    </style>
</head>
<body>
    '.$logoHtml.'
    <div class="header-space"></div>
    
    <div class="judul">
        <h2>Laporan Monitoring Alat Tapping Box Bank Maluku Malut</h2>
        <p>Periode: '.($bulan && $tahun ? date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) : 'Semua Periode').'</p>
        <p>Kategori Merchant: ' . (!empty($kategori_merchant) ? htmlspecialchars($kategori_merchant) : 'Semua Kategori') . '</p>
        ' . (!empty($kabupaten_id) ? '<p>Kabupaten/Kota: ' . htmlspecialchars($kabupaten_id) . '</p>' : '') . '
        ' . (!empty($search) ? '<p>Pencarian: ' . htmlspecialchars($search) . '</p>' : '') . '
    </div>';

// Informasi transaksi berdasarkan kategori
$html .= '<div class="transaction-info">
        <p><strong>Informasi Transaksi:</strong></p>';

if (empty($kategori_merchant) || $kategori_merchant == 'PBJT') {
    $html .= '<p>- Total Transaksi TMD: Rp '.number_format($totalTransaksiTMD, 2).'</p>
              <p>- Total Transaksi MPOS: Rp '.number_format($totalTransaksiMPOS, 2).'</p>';
}

if (empty($kategori_merchant) || $kategori_merchant == 'E-Retribusi') {
    $html .= '<p>- Total Transaksi E-Retribusi: Rp '.number_format($totalTransaksiERetribusi, 2).'</p>';
}

if (empty($kategori_merchant) || $kategori_merchant == 'Samsat') {
    $html .= '<p>- Total Transaksi Samsat: Rp '.number_format($totalTransaksiSamsat, 2).'</p>';
}

$html .= '</div>
    
    <table>
        <thead>
            <tr>
                <th width="4%">No</th>
                <th width="15%">Nama WP</th>
                <th width="8%">Kategori</th>
                <th width="12%">Vendor</th>
                <th width="10%">Merchant</th>
                <th width="10%">Kabupaten/Kota</th>'; // Tambahkan kolom Kabupaten/Kota
    
// Kolom dinamis berdasarkan kategori
if (empty($kategori_merchant) || $kategori_merchant == 'PBJT') {
    $html .= '<th width="10%" class="text-right">DPP</th>
              <th width="10%" class="text-right">Pajak</th>';
} elseif ($kategori_merchant == 'E-Retribusi') {
    $html .= '<th width="15%" class="text-right">Retribusi</th>';
} elseif ($kategori_merchant == 'Samsat') {
    $html .= '<th width="15%" class="text-right">Samsat</th>';
}

$html .= '      <th width="20%">Masalah</th>
                <th width="20%">Solusi</th>
            </tr>
        </thead>
        <tbody>';

// Tampilkan data
foreach ($allRows as $row) {
    $merchantClass = strtolower(str_replace('-', '', $row['kategori_merchant']));
    
    $html .= '<tr>
            <td class="text-center">'.$row['nomor'].'</td>
            <td>'.htmlspecialchars($row['nama_wp']).'</td>
            <td class="text-center">'.htmlspecialchars($row['kategori']).'</td>
            <td>'.htmlspecialchars($row['vendor']).'</td>
            <td class="text-center"><span class="merchant-badge '.$merchantClass.'">'.htmlspecialchars($row['kategori_merchant']).'</span></td>
            <td>'.htmlspecialchars($row['nama_kabupaten']).'</td>'; // Tambahkan data kabupaten
    
    // Kolom nilai berdasarkan kategori
    if (empty($kategori_merchant) || $kategori_merchant == 'PBJT') {
        $html .= '<td class="text-right">'.number_format($row['transaksi_dpp'], 2).'</td>
                  <td class="text-right">'.number_format($row['transaksi_pajak'], 2).'</td>';
    } elseif ($kategori_merchant == 'E-Retribusi') {
        $html .= '<td class="text-right">'.number_format($row['transaksi_retribusi'], 2).'</td>';
    } elseif ($kategori_merchant == 'Samsat') {
        $html .= '<td class="text-right">'.number_format($row['transaksi_samsat'], 2).'</td>';
    }
    
    // PERBAIKAN: Gunakan class multiline-cell dan white-space: pre-line
    $html .= '  <td class="multiline-cell">'.htmlspecialchars($row['masalah'] ?? '-').'</td>
                <td class="multiline-cell">'.htmlspecialchars($row['solusi'] ?? '-').'</td>
            </tr>';
}

if (empty($allRows)) {
    $colspan = 7; // No, Nama WP, Kategori, Vendor, Merchant, Kabupaten, Masalah, Solusi
    if (empty($kategori_merchant) || $kategori_merchant == 'PBJT') {
        $colspan += 2; // DPP + Pajak
    } else {
        $colspan += 1; // Retribusi atau Samsat
    }
    $html .= '<tr><td colspan="' . $colspan . '" class="text-center">Tidak ada data ditemukan</td></tr>';
}

$html .= '</tbody></table>';

// Summary total
$html .= '<div style="margin-top: 20px;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <th style="padding: 8px; text-align: center; width: 25%;">TOTAL ALAT</th>
            <th style="padding: 8px; text-align: center; width: 25%;">TOTAL TRANSAKSI</th>';

if (empty($kategori_merchant) || $kategori_merchant == 'PBJT') {
    $html .= '<th style="padding: 8px; text-align: center; width: 25%;">TOTAL PAJAK</th>
              <th style="padding: 8px; text-align: center; width: 25%;">TOTAL DPP + PAJAK</th>';
} else {
    $html .= '<th style="padding: 8px; text-align: center; width: 50%;" colspan="2">TOTAL KESELURUHAN</th>';
}

$html .= '</tr>';

if (empty($kategori_merchant) || $kategori_merchant == 'PBJT') {
    $html .= '<tr>
            <td style="padding: 6px; text-align: left;">TMD: '.$totalTMD.'</td>
            <td style="padding: 6px; text-align: left;">TMD: Rp '.number_format($totalTransaksiTMD - $totalPajakTMD, 2).'</td>
            <td style="padding: 6px; text-align: left;">TMD: Rp '.number_format($totalPajakTMD, 2).'</td>
            <td style="padding: 6px; text-align: left;">TMD: Rp '.number_format($totalTransaksiTMD, 2).'</td>
        </tr>
        <tr>
            <td style="padding: 6px; text-align: left;">MPOS: '.$totalMPOS.'</td>
            <td style="padding: 6px; text-align: left;">MPOS: Rp '.number_format($totalTransaksiMPOS - $totalPajakMPOS, 2).'</td>
            <td style="padding: 6px; text-align: left;">MPOS: Rp '.number_format($totalPajakMPOS, 2).'</td>
            <td style="padding: 6px; text-align: left;">MPOS: Rp '.number_format($totalTransaksiMPOS, 2).'</td>
        </tr>';
}

if (empty($kategori_merchant) || $kategori_merchant == 'E-Retribusi') {
    $html .= '<tr>
            <td style="padding: 6px; text-align: left;">E-Retribusi: '.$totalERetribusi.'</td>
            <td style="padding: 6px; text-align: left;" colspan="3">E-Retribusi: Rp '.number_format($totalTransaksiERetribusi, 2).'</td>
        </tr>';
}

if (empty($kategori_merchant) || $kategori_merchant == 'Samsat') {
    $html .= '<tr>
            <td style="padding: 6px; text-align: left;">Samsat: '.$totalSamsat.'</td>
            <td style="padding: 6px; text-align: left;" colspan="3">Samsat: Rp '.number_format($totalTransaksiSamsat, 2).'</td>
        </tr>';
}

$html .= '<tr>
        <td style="padding: 6px; text-align: left; font-weight: bold;">
            TOTAL: '.($totalTMD + $totalMPOS + $totalERetribusi + $totalSamsat).'
        </td>
        <td style="padding: 6px; text-align: left; font-weight: bold;" colspan="3">
            TOTAL: Rp '.number_format(($totalTransaksiTMD + $totalTransaksiMPOS + $totalTransaksiERetribusi + $totalTransaksiSamsat), 2).'
        </td>
    </tr>
</table>
</div>';

// Tanda tangan
$html .= '<div style="margin-top: 50px;">
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 50%; text-align: center; border: none;">
                <div style="font-weight: bold;">Dibuat Oleh:</div>
                <div style="margin-top: 60px; border-top: 1px solid #000; width: 200px; margin-left: auto; margin-right: auto;"></div>
                <div style="font-style: italic; margin-top: 5px;">(Tim Support)</div>
            </td>
            <td style="width: 50%; text-align: center; border: none;">
                <div style="font-weight: bold;">Diterima Oleh:</div>
                <div style="margin-top: 60px; border-top: 1px solid #000; width: 200px; margin-left: auto; margin-right: auto;"></div>
                <div style="font-style: italic; margin-top: 5px;">(Divisi IT-OPS BPD Maluku Malut)</div>
            </td>
        </tr>
    </table>
</div>

</body></html>';

// Generate PDF
$dompdf = new Dompdf([
    'enable_remote' => true,
    'isHtml5ParserEnabled' => true,
    'isPhpEnabled' => true
]);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("laporan_alat_".date('YmdHis').".pdf", ["Attachment" => false]);
exit;