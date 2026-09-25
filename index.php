<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// =================================================================
// CONFIGURATION: Paste your two separate published CSV URLs below
// =================================================================
$csvUrlSPB    = "https://docs.google.com/spreadsheets/d/e/2PACX-1vRIHSAwWWNdjeLPTJdM83WsWAAsH3XJB7kbqGOKuCwCAx8odVRZ0PXUbHOJ_HPHBi7US4QmO_bjQg76/pub?gid=413308845&single=true&output=csv";
$csvUrlBunker = "https://docs.google.com/spreadsheets/d/e/2PACX-1vRIHSAwWWNdjeLPTJdM83WsWAAsH3XJB7kbqGOKuCwCAx8odVRZ0PXUbHOJ_HPHBi7US4QmO_bjQg76/pub?gid=502455271&single=true&output=csv";

// Reload the page every 5 minutes so the published Google Sheet data is refreshed.
$autoRefreshSeconds = 300;

// Helper function to read remote CSV files safely
function fetchCsvData($url) {
    if (empty($url) || strpos($url, "PASTE_YOUR_") !== false) return [];
    $rows = [];
    $context = stream_context_create(["http" => ["timeout" => 5]]);
    if (($handle = @fopen($url, "r", false, $context)) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
    }
    return $rows;
}

// Load datasets and remove header row frames
$spbRows = fetchCsvData($csvUrlSPB);
if (!empty($spbRows)) array_shift($spbRows);

$bunkerRows = fetchCsvData($csvUrlBunker);
if (!empty($bunkerRows)) array_shift($bunkerRows);

// Helper function to calculate row coloring based on aging profile
function getAgingStyle($dateString) {
    if (empty($dateString)) return ['class' => 'expire-safe', 'text' => '0 Hari'];
    
    $cleanDate = str_replace('/', '-', $dateString);
    $timestamp = strtotime($cleanDate);
    if ($timestamp === FALSE) return ['class' => 'expire-safe', 'text' => '-'];
    
    $diffSeconds = time() - $timestamp;
    $daysOld = floor($diffSeconds / (60 * 60 * 24));
    
    // Fallback protection for negative values due to varying timezone format caches
    if ($daysOld < 0) $daysOld = 0; 
    
    $text = $daysOld . " Hari";

    // More than 7 days: billing code is considered EXPIRED
    if ($daysOld > 7) {
        return ['class' => 'expire-expired', 'text' => $text . " (EXPIRED)"];
    } elseif ($daysOld >= 6) {
        return ['class' => 'expire-critical', 'text' => $text . " (Jatuh Tempo)"];
    } elseif ($daysOld >= 3) {
        return ['class' => 'expire-warning', 'text' => $text . " (Peringatan)"];
    }

    return ['class' => 'expire-safe', 'text' => $text . " (Aman)"];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Tagihan Belum Lunas (SPB & BUNKER)</title>
    <style>
        :root {
            --bg-color: #f3f6f9;
            --card-bg: #ffffff;
            --text-color: #263238;
            --muted: #607d8b;
            --border-color: #dfe5ea;
            --primary: #005baa;
            --primary-dark: #003f7d;
            --gold: #d4a72c;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 0 12px 24px;
        }

        .official-header {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto 18px;
            background: #fff;
            border-bottom: 4px solid var(--gold);
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            padding: 14px 16px 12px;
        }

        .official-header-inner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
        }

        .official-logo {
            width: 62px;
            height: 72px;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .official-title {
            text-align: center;
            line-height: 1.25;
            color: #17324d;
        }

        .official-title .ministry {
            font-size: .82rem;
            font-weight: 700;
            letter-spacing: .4px;
        }

        .official-title .office {
            font-size: 1.18rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .official-title .location {
            font-size: .88rem;
            font-weight: 700;
            margin-top: 2px;
        }

        .header-section {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto 16px;
            text-align: center;
        }

        h1 {
            margin: 0 0 4px;
            color: #263b50;
            font-size: clamp(1.15rem, 4vw, 1.8rem);
        }

        .subtitle {
            color: var(--muted);
            font-size: .88rem;
        }

        .legend {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px 16px;
            background: var(--card-bg);
            padding: 10px 12px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,.05);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .78rem;
        }

        .color-box {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            border: 1px solid rgba(0,0,0,.1);
            flex: 0 0 auto;
        }

        /* IMPORTANT: one column instead of side-by-side */
        .dashboard-grid {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .column {
            width: 100%;
            min-width: 0;
        }

        .column-header {
            padding: 12px 14px;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: #fff;
            border-radius: 9px 9px 0 0;
            font-weight: 700;
            text-align: center;
            font-size: .95rem;
            letter-spacing: .25px;
        }

        .table-responsive {
            background: var(--card-bg);
            border-radius: 0 0 9px 9px;
            box-shadow: 0 3px 8px rgba(0,0,0,.07);
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-top: 0;
            max-height: 70vh;
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: .84rem;
            vertical-align: middle;
        }

        th {
            background: #f7f9fb;
            color: #30485f;
            font-weight: 700;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .expire-safe { background-color: #fff; }
        .expire-warning { background-color: #fff3cd; color: #856404; }
        .expire-critical { background-color: #f8d7da; color: #721c24; font-weight: 600; }
        .expire-expired { background-color: #e2e3e5; color: #343a40; font-weight: 700; }

        tr.clickable-row { cursor: pointer; }
        tr.clickable-row:hover { filter: brightness(.97); }

        code {
            font-family: Consolas, monospace;
            background: #eef2f5;
            padding: 3px 5px;
            border-radius: 4px;
            font-size: .84em;
            white-space: nowrap;
        }

        .filter-panel {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto 18px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 9px;
            box-shadow: 0 3px 8px rgba(0,0,0,.06);
            padding: 12px;
        }
        .filter-title {
            font-weight: 700;
            color: #30485f;
            margin-bottom: 9px;
            font-size: .9rem;
        }
        .filter-controls {
            display: grid;
            grid-template-columns: minmax(180px, 1.5fr) minmax(150px, .9fr) minmax(150px, .9fr) auto;
            gap: 8px;
        }
        .filter-controls input,
        .filter-controls select,
        .filter-controls button {
            min-height: 40px;
            border: 1px solid #cfd8df;
            border-radius: 6px;
            padding: 8px 10px;
            font: inherit;
            font-size: .82rem;
        }
        .filter-controls button {
            background: var(--primary);
            color: #fff;
            border: 0;
            cursor: pointer;
            font-weight: 700;
            padding: 8px 16px;
        }
        .filter-controls button:hover { background: var(--primary-dark); }
        .filter-info {
            margin-top: 8px;
            color: var(--muted);
            font-size: .74rem;
            line-height: 1.35;
        }
        .hidden-row { display: none !important; }

        #toast {
            visibility: hidden;
            width: min(92vw, 430px);
            background-color: #263b50;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 12px;
            position: fixed;
            z-index: 10;
            left: 50%;
            bottom: 20px;
            transform: translateX(-50%);
            font-size: .9rem;
            box-shadow: 0 4px 14px rgba(0,0,0,.3);
        }

        #toast.show { visibility: visible; animation: fadein .3s, fadeout .3s 2.2s; }

        @keyframes fadein {
            from { bottom: 0; opacity: 0; }
            to { bottom: 20px; opacity: 1; }
        }

        @keyframes fadeout {
            from { bottom: 20px; opacity: 1; }
            to { bottom: 0; opacity: 0; }
        }

        /* Phone view: turn each row into a readable card */
        @media (max-width: 700px) {
            body {
                padding: 0 8px 20px;
            }

            .official-header {
                margin-bottom: 14px;
                padding: 12px 8px 10px;
            }

            .official-header-inner {
                gap: 9px;
            }

            .official-logo {
                width: 48px;
                height: 58px;
            }

            .official-title .ministry {
                font-size: .65rem;
            }

            .official-title .office {
                font-size: .88rem;
            }

            .official-title .location {
                font-size: .68rem;
            }

            .header-section {
                margin-bottom: 12px;
            }

            h1 {
                font-size: 1.05rem;
            }

            .subtitle {
                font-size: .75rem;
                line-height: 1.35;
            }

            .legend {
                justify-content: flex-start;
                gap: 7px 12px;
                margin-bottom: 14px;
                padding: 9px 10px;
            }

            .legend-item {
                font-size: .7rem;
            }

            .filter-controls {
                grid-template-columns: 1fr;
            }
            .filter-controls input,
            .filter-controls select,
            .filter-controls button {
                width: 100%;
            }

            .column-header {
                font-size: .82rem;
                padding: 11px 8px;
            }

            .table-responsive {
                max-height: none;
                overflow: visible;
            }

            table, thead, tbody, tr, th, td {
                display: block;
                width: 100%;
            }

            thead {
                display: none;
            }

            tr.clickable-row {
                position: relative;
                margin: 0;
                padding: 8px 10px;
                border-bottom: 1px solid var(--border-color);
            }

            tr.clickable-row td {
                display: grid;
                grid-template-columns: 42% 58%;
                gap: 6px;
                padding: 5px 0;
                border: 0;
                font-size: .78rem;
                word-break: break-word;
            }

            tr.clickable-row td::before {
                font-weight: 700;
                color: #607d8b;
            }

            /* SPB row labels */
            .spb-table tr.clickable-row td:nth-child(1)::before { content: "No"; }
            .spb-table tr.clickable-row td:nth-child(2)::before { content: "Tgl Berangkat"; }
            .spb-table tr.clickable-row td:nth-child(3)::before { content: "Nama Kapal"; }
            .spb-table tr.clickable-row td:nth-child(4)::before { content: "Agen / Pemilik"; }
            .spb-table tr.clickable-row td:nth-child(5)::before { content: "Kode Billing"; }
            .spb-table tr.clickable-row td:nth-child(6)::before { content: "Tenggang Waktu"; }

            /* Bunker row labels */
            .bunker-table tr.clickable-row td:nth-child(1)::before { content: "No"; }
            .bunker-table tr.clickable-row td:nth-child(2)::before { content: "Tgl Kegiatan"; }
            .bunker-table tr.clickable-row td:nth-child(3)::before { content: "Nama Kapal"; }
            .bunker-table tr.clickable-row td:nth-child(4)::before { content: "Pemohon / No.HP"; }
            .bunker-table tr.clickable-row td:nth-child(5)::before { content: "Kode Billing"; }
            .bunker-table tr.clickable-row td:nth-child(6)::before { content: "Tenggang Waktu"; }

            tr:not(.clickable-row) td {
                padding: 18px 10px;
                font-size: .78rem;
            }

            code {
                white-space: normal;
                overflow-wrap: anywhere;
            }
        }
    </style>
</head>
<body>

<header class="official-header">
    <div class="official-header-inner">
        <img
            class="official-logo"
            src="https://commons.wikimedia.org/wiki/Special:Redirect/file/Logo_of_the_Ministry_of_Transportation_of_the_Republic_of_Indonesia.svg"
            alt="Logo Kementerian Perhubungan"
        >
        <div class="official-title">
            <div class="ministry">KEMENTERIAN PERHUBUNGAN</div>
            <div class="office">KANTOR KESYAHBANDARAN DAN OTORITAS PELABUHAN</div>
            <div class="location">KELAS III LABUAN BAJO</div>
        </div>
    </div>
</header>

<div class="header-section">
    <h1>Dashboard Monitoring Tagihan Utama</h1>
    <div class="subtitle">Klik baris data untuk menyalin Kode Billing • Tenggang waktu maksimal 7 hari • Data diperbarui otomatis setiap 5 menit</div>
</div>

<div class="legend">
    <div class="legend-item"><div class="color-box" style="background-color: #ffffff;"></div><span>Aman (0-2 Hari)</span></div>
    <div class="legend-item"><div class="color-box" style="background-color: #fff3cd;"></div><span>Peringatan (3-5 Hari)</span></div>
    <div class="legend-item"><div class="color-box" style="background-color: #f8d7da;"></div><span>Jatuh Tempo (6-7 Hari)</span></div>
    <div class="legend-item"><div class="color-box" style="background-color: #e2e3e5;"></div><span>EXPIRED (&gt;7 Hari)</span></div>
</div>

<div class="filter-panel">
    <div class="filter-title">🔎 Filter &amp; Urutkan Data</div>
    <div class="filter-controls">
        <input type="search" id="globalFilter"
               placeholder="Cari kapal, agen/pemohon, kode billing...">
        <select id="columnFilter">
            <option value="all">Cari di: Semua Kolom</option>
            <option value="date">Tanggal</option>
            <option value="vessel">Nama Kapal</option>
            <option value="agent">Agen / Pemilik / Pemohon</option>
            <option value="billing">Kode Billing</option>
            <option value="aging">Tenggang Waktu</option>
        </select>
        <select id="dateSort">
            <option value="none">Urutan Tanggal: Default</option>
            <option value="newest">Terbaru → Terlama</option>
            <option value="oldest">Terlama → Terbaru</option>
        </select>
        <button type="button" id="resetFilter">Reset</button>
    </div>
    <div class="filter-info">
        Filter dan pengurutan hanya berlaku pada browser pengguna yang sedang membuka halaman.
        Filter tidak mengubah Google Spreadsheet dan tidak memengaruhi pengguna lain.
        Data diperbarui otomatis setiap 5 menit.
    </div>
</div>

<div class="dashboard-grid">

    <!-- LEFT COLUMN: BILLING SPB UNPAID DATA LIST -->
    <div class="column">
        <div class="column-header">DATA TAGIHAN BELUM SPB LUNAS</div>
        <div class="table-responsive">
            <table class="spb-table" data-section="spb">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tgl Berangkat</th>
                        <th>Nama Kapal</th>
                        <th>Agen / Pemilik</th>
                        <th>Kode Billing</th>
                        <th>Tenggang Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $noSpb = 1;
                    if (!empty($spbRows)):
                        foreach ($spbRows as $row): 
                            // Mapping variables: Col B(1)=Vessel, Col F(5)=Date, Col I(8)=Agent, Col J(9)=Billing Code
                            $vesselName  = isset($row[1]) ? $row[1] : '';
                            $dateDepart  = isset($row[5]) ? $row[5] : '';
                            $agentName   = isset($row[8]) ? $row[8] : '';
                            $billingCode = isset($row[9]) ? $row[9] : '';
                            
                            if (empty($vesselName) || empty($billingCode) || $vesselName == "NAMA KAPAL") continue;
                            $aging = getAgingStyle($dateDepart);
                    ?>
                    <tr class="clickable-row <?php echo $aging['class']; ?>"
    data-billing="<?php echo htmlspecialchars($billingCode); ?>"
    data-date="<?php echo htmlspecialchars($dateDepart); ?>"
    data-vessel="<?php echo htmlspecialchars($vesselName); ?>"
    data-agent="<?php echo htmlspecialchars($agentName); ?>"
    data-aging="<?php echo htmlspecialchars($aging['text']); ?>">
                        <td><?php echo $noSpb++; ?></td>
                        <td><?php echo htmlspecialchars($dateDepart); ?></td>
                        <td><strong><?php echo htmlspecialchars($vesselName); ?></strong></td>
                        <td><?php echo htmlspecialchars($agentName); ?></td>
                        <td><code><?php echo htmlspecialchars($billingCode); ?></code></td>
                        <td><?php echo $aging['text']; ?></td>
                    </tr>
                    <?php 
                        endforeach; 
                    endif; 
                    if ($noSpb === 1):
                    ?>
                    <tr><td colspan="6" style="text-align: center; color: #7f8c8d; padding: 20px;">Tidak ada antrean data SPB Belum Lunas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RIGHT COLUMN: BILLING BUNKER UNPAID DATA LIST -->
    <div class="column">
        <div class="column-header">DATA TAGIHAN BUNKER BELUM LUNAS</div>
        <div class="table-responsive">
            <table class="bunker-table" data-section="bunker">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tgl Kegiatan</th>
                        <th>Nama Kapal</th>
                        <th>Pemohon / No.HP</th>
                        <th>Kode Billing</th>
                        <th>Tenggang Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $noBunker = 1;
                    if (!empty($bunkerRows)):
                        foreach ($bunkerRows as $row): 
                            // Mapping variables: Col B(1)=Vessel, Col H(7)=Pemohon, Col I(8)=Date, Col J(9)=Billing Code
                            $vesselName  = isset($row[1]) ? $row[1] : '';
                            $pemohonName = isset($row[7]) ? $row[7] : '';
                            $dateActivity= isset($row[8]) ? $row[8] : '';
                            $billingCode = isset($row[9]) ? $row[9] : '';
                            
                            if (empty($vesselName) || empty($billingCode) || $vesselName == "NAMA KAPAL") continue;
                            $aging = getAgingStyle($dateActivity);
                    ?>
                    <tr class="clickable-row <?php echo $aging['class']; ?>"
    data-billing="<?php echo htmlspecialchars($billingCode); ?>"
    data-date="<?php echo htmlspecialchars($dateActivity); ?>"
    data-vessel="<?php echo htmlspecialchars($vesselName); ?>"
    data-agent="<?php echo htmlspecialchars($pemohonName); ?>"
    data-aging="<?php echo htmlspecialchars($aging['text']); ?>">
                        <td><?php echo $noBunker++; ?></td>
                        <td><?php echo htmlspecialchars($dateActivity); ?></td>
                        <td><strong><?php echo htmlspecialchars($vesselName); ?></strong></td>
                        <td><?php echo htmlspecialchars($pemohonName); ?></td>
                        <td><code><?php echo htmlspecialchars($billingCode); ?></code></td>
                        <td><?php echo $aging['text']; ?></td>
                    </tr>
                    <?php 
                        endforeach; 
                    endif; 
                    if ($noBunker === 1):
                    ?>
                    <tr><td colspan="6" style="text-align: center; color: #7f8c8d; padding: 20px;">Tidak ada antrean data Bunker Belum Lunas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div id="toast">Kode Billing disalin!</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const toast = document.getElementById("toast");
    const globalFilter = document.getElementById("globalFilter");
    const columnFilter = document.getElementById("columnFilter");
    const dateSort = document.getElementById("dateSort");
    const resetFilter = document.getElementById("resetFilter");
    const tables = Array.from(document.querySelectorAll("table[data-section]"));

    // Click a billing row to copy the billing code.
    document.querySelectorAll(".clickable-row").forEach(row => {
        row.addEventListener("click", function() {
            const billingCode = this.getAttribute("data-billing");
            if (!billingCode) return;
            navigator.clipboard.writeText(billingCode).then(() => {
                toast.textContent = "Berhasil Menyalin: " + billingCode;
                toast.classList.add("show");
                setTimeout(() => toast.classList.remove("show"), 2200);
            }).catch(err => console.error("Gagal menyalin kode: ", err));
        });
    });

    function normalize(value) {
        return String(value || "").toLowerCase().trim();
    }

    function parseDate(value) {
        const raw = String(value || "").trim();
        if (!raw) return 0;

        let m = raw.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})/);
        if (m) return new Date(Number(m[3]), Number(m[2]) - 1, Number(m[1])).getTime();

        m = raw.match(/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/);
        if (m) return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])).getTime();

        const parsed = Date.parse(raw);
        return isNaN(parsed) ? 0 : parsed;
    }

    function getSearchValue(row, field) {
        if (field === "all") {
            return [row.dataset.date, row.dataset.vessel, row.dataset.agent,
                    row.dataset.billing, row.dataset.aging].join(" ");
        }
        return row.dataset[field] || "";
    }

    function applyFilters() {
        const search = normalize(globalFilter.value);
        const field = columnFilter.value;
        const sort = dateSort.value;

        tables.forEach(table => {
            const tbody = table.querySelector("tbody");
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll("tr.clickable-row"));

            rows.forEach(row => {
                const value = normalize(getSearchValue(row, field));
                row.classList.toggle("hidden-row", Boolean(search) && !value.includes(search));
            });

            if (sort !== "none") {
                rows.sort((a, b) => {
                    const da = parseDate(a.dataset.date);
                    const db = parseDate(b.dataset.date);
                    return sort === "newest" ? db - da : da - db;
                });
                rows.forEach(row => tbody.appendChild(row));
            }
        });
    }

    function resetAllFilters() {
        globalFilter.value = "";
        columnFilter.value = "all";
        dateSort.value = "none";
        document.querySelectorAll(".clickable-row").forEach(row => row.classList.remove("hidden-row"));
    }

    globalFilter.addEventListener("input", applyFilters);
    columnFilter.addEventListener("change", applyFilters);
    dateSort.addEventListener("change", applyFilters);
    resetFilter.addEventListener("click", resetAllFilters);

    // The server reads the published Google CSV on every page load.
    // Reloading every 5 minutes fetches fresh data while preserving per-user filters
    // only until the page is reloaded.
    const autoRefreshSeconds = <?php echo (int)$autoRefreshSeconds; ?>;
    setTimeout(() => window.location.reload(), autoRefreshSeconds * 1000);
});
</script>

</body>
</html>