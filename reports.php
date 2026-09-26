<?php
session_start();
require_once 'config/database.php';

// Tema kontrolü
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $themeClass = 'dark';
} else {
    $themeClass = '';
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index');
    exit();
}

// Admin rolü kontrolü
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = 'Bu sayfaya erişim yetkiniz bulunmuyor.';
    header('Location: dashboard');
    exit();
}

// Türkçe ay isimleri
$aylar = [
    'January' => 'Ocak',
    'February' => 'Şubat',
    'March' => 'Mart',
    'April' => 'Nisan',
    'May' => 'Mayıs',
    'June' => 'Haziran',
    'July' => 'Temmuz',
    'August' => 'Ağustos',
    'September' => 'Eylül',
    'October' => 'Ekim',
    'November' => 'Kasım',
    'December' => 'Aralık'
];

// Tarih aralığını al
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Son 6 ayın verilerini hazırla
$labels = [];
$kazanc_data = [];
$randevu_data = [];
$yeni_danisan_data = [];

// DateTime kullanarak güvenli ay hesaplama
$current_date = new DateTime();
$current_date->setDate($current_date->format('Y'), $current_date->format('n'), 1); // Ayın ilk günü

for ($i = 5; $i >= 0; $i--) {
    $date = clone $current_date;
    $date->modify("-$i months");

    $month_name = $aylar[$date->format('F')];
    $year = $date->format('Y');
    $labels[] = $month_name . ' ' . $year;

    // Ay başı ve sonu
    $month_start = $date->format('Y-m-01');
    $month_end = $date->format('Y-m-t');

    try {
        // Aylık kazanç
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ?");
        $stmt->execute([$month_start, $month_end]);
        $kazanc_data[] = $stmt->fetch()['total'];

        // Aylık randevu sayısı
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE appointment_date BETWEEN ? AND ?");
        $stmt->execute([$month_start, $month_end]);
        $randevu_data[] = $stmt->fetch()['total'];

        // Aylık yeni danışan sayısı
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT client_id) as total
            FROM appointments
            WHERE appointment_date BETWEEN ? AND ?
            AND client_id NOT IN (
                SELECT DISTINCT client_id
                FROM appointments
                WHERE appointment_date < ?
            )
        ");
        $stmt->execute([$month_start, $month_end, $month_start]);
        $yeni_danisan_data[] = $stmt->fetch()['total'];

    } catch (PDOException $e) {
        $kazanc_data[] = 0;
        $randevu_data[] = 0;
        $yeni_danisan_data[] = 0;
    }
}

try {
    // Randevu istatistikleri
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_appointments,
            COUNT(CASE WHEN appointment_date < CURDATE() THEN 1 END) as past_appointments,
            COUNT(CASE WHEN appointment_date = CURDATE() THEN 1 END) as today_appointments,
            COUNT(CASE WHEN appointment_date > CURDATE() THEN 1 END) as future_appointments
        FROM appointments
        WHERE appointment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $appointment_stats = $stmt->fetch();

    // Ödeme istatistikleri
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_payments,
            SUM(amount) as total_amount,
            COUNT(CASE WHEN payment_method = 'cash' THEN 1 END) as cash_payments,
            COUNT(CASE WHEN payment_method IN ('card', 'credit_card') THEN 1 END) as credit_card_payments,
            COUNT(CASE WHEN payment_method = 'bank_transfer' THEN 1 END) as bank_transfer_payments
        FROM payments
        WHERE payment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $payment_stats = $stmt->fetch();

    // Danışan istatistikleri
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT client_id) as total_clients,
            COUNT(DISTINCT CASE WHEN appointment_date BETWEEN ? AND ? THEN client_id END) as active_clients
        FROM appointments
    ");
    $stmt->execute([$start_date, $end_date]);
    $client_stats = $stmt->fetch();

} catch (PDOException $e) {
    $_SESSION['error'] = "Raporlar alınırken bir hata oluştu: " . $e->getMessage();
    $appointment_stats = ['total_appointments' => 0, 'past_appointments' => 0, 'today_appointments' => 0, 'future_appointments' => 0];
    $payment_stats = ['total_payments' => 0, 'total_amount' => 0, 'cash_payments' => 0, 'credit_card_payments' => 0, 'bank_transfer_payments' => 0];
    $client_stats = ['total_clients' => 0, 'active_clients' => 0];
}

// --- Görünüm için yardımcı hesaplar (sorgulara dokunmaz) ---
function reportMoney($amount) {
    $amount = (float) $amount;
    return '₺' . number_format($amount, (floor($amount) == $amount) ? 0 : 2, ',', '.');
}

$trMonthsByNum = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

// Seçili aralığın başlığı: varsayılan aralık "Bu ay", aksi hâlde tarih aralığı
$rangeStartTs = strtotime((string) $start_date);
$rangeEndTs = strtotime((string) $end_date);
$isDefaultRange = ($start_date === date('Y-m-01') && $end_date === date('Y-m-d'));
if ($rangeStartTs && $rangeEndTs) {
    $rangeTitle = $isDefaultRange ? 'Bu ay' : 'Seçili dönem';
    if (date('Y-m', $rangeStartTs) === date('Y-m', $rangeEndTs)) {
        $rangeNote = date('j', $rangeStartTs) . '–' . date('j', $rangeEndTs) . ' ' . $trMonthsByNum[(int) date('n', $rangeEndTs)] . ' ' . date('Y', $rangeEndTs);
    } else {
        $rangeNote = date('d.m.Y', $rangeStartTs) . ' – ' . date('d.m.Y', $rangeEndTs);
    }
} else {
    $rangeTitle = 'Seçili dönem';
    $rangeNote = $start_date . ' – ' . $end_date;
}

// Grafik için kısa ay adları (telefonda sığsın); ipucunda tam ad gösterilir
$shortLabels = array_map(function ($label) {
    return mb_substr($label, 0, 3, 'UTF-8');
}, $labels);

$chartIncome = array_map('floatval', $kazanc_data);
$chartAppointments = array_map('intval', $randevu_data);
$chartNewClients = array_map('intval', $yeni_danisan_data);

$sixMonthIncome = array_sum($chartIncome);
$sixMonthNewClients = array_sum($chartNewClients);

$pageTitle = 'Raporlar';
$firstLabel = $labels[0];
$lastLabel = $labels[count($labels) - 1];
// "Nisan – Eylül 2026" (aynı yıl) ya da "Kasım 2025 – Nisan 2026"
$pageSubtitle = (substr($firstLabel, -4) === substr($lastLabel, -4))
    ? trim(substr($firstLabel, 0, -4)) . ' – ' . $lastLabel
    : $firstLabel . ' – ' . $lastLabel;

// Header'ı dahil et
include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="reports">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page">
                <!-- Seçili dönem özeti -->
                <section class="section" aria-labelledby="rangeTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="rangeTitle"><?php echo htmlspecialchars($rangeTitle); ?></h2>
                        <span class="section-note"><?php echo htmlspecialchars($rangeNote); ?></span>
                    </div>
                    <div class="ledger">
                        <div class="ledger-item is-lead">
                            <span class="ledger-label">Kazanç · <?php echo (int) ($payment_stats['total_payments'] ?? 0); ?> ödeme</span>
                            <span class="ledger-value"><?php echo reportMoney($payment_stats['total_amount'] ?? 0); ?></span>
                        </div>
                        <div class="ledger-item">
                            <span class="ledger-label">Randevu</span>
                            <span class="ledger-value"><?php echo (int) ($appointment_stats['total_appointments'] ?? 0); ?></span>
                        </div>
                        <div class="ledger-item">
                            <span class="ledger-label">Randevulu danışan</span>
                            <span class="ledger-value"><?php echo (int) ($client_stats['active_clients'] ?? 0); ?></span>
                        </div>
                    </div>
                </section>

                <!-- Kazanç ve randevu -->
                <section class="section" aria-labelledby="incomeChartTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="incomeChartTitle">Kazanç ve randevu</h2>
                        <span class="section-note">Son 6 ay · <?php echo reportMoney($sixMonthIncome); ?></span>
                    </div>
                    <div class="panel panel-pad">
                        <div class="chart-box">
                            <canvas id="kazancChart" role="img" aria-label="Son 6 ayın aylık kazancı ve randevu sayısı. Sayılar aşağıdaki Ay ay tablosunda."></canvas>
                        </div>
                    </div>
                </section>

                <div class="report-pair">
                    <!-- Yeni danışanlar -->
                    <section class="section" aria-labelledby="newClientsTitle">
                        <div class="section-head">
                            <h2 class="section-title" id="newClientsTitle">Yeni danışanlar</h2>
                            <span class="section-note">Son 6 ay · <?php echo $sixMonthNewClients; ?> kişi</span>
                        </div>
                        <div class="panel panel-pad">
                            <div class="chart-box">
                                <canvas id="yeniDanisanChart" role="img" aria-label="Son 6 ayda her ay ilk kez randevu alan danışan sayısı. Sayılar Ay ay tablosunda."></canvas>
                            </div>
                        </div>
                    </section>

                    <!-- Ay ay döküm -->
                    <section class="section" aria-labelledby="monthlyTitle">
                        <div class="section-head">
                            <h2 class="section-title" id="monthlyTitle">Ay ay</h2>
                            <span class="section-note">En yeni ay üstte</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-stack">
                                <thead>
                                    <tr>
                                        <th scope="col">Ay</th>
                                        <th scope="col" class="text-end">Kazanç</th>
                                        <th scope="col" class="text-end">Randevu</th>
                                        <th scope="col" class="text-end">Yeni danışan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($m = count($labels) - 1; $m >= 0; $m--): ?>
                                    <tr>
                                        <td class="td-title"><?php echo htmlspecialchars($labels[$m]); ?></td>
                                        <td class="text-end" data-label="Kazanç"><span class="money"><?php echo reportMoney($chartIncome[$m]); ?></span></td>
                                        <td class="text-end" data-label="Randevu"><?php echo $chartAppointments[$m]; ?></td>
                                        <td class="text-end" data-label="Yeni danışan"><?php echo $chartNewClients[$m]; ?></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            const fullLabels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
            const shortLabels = <?php echo json_encode($shortLabels, JSON_UNESCAPED_UNICODE); ?>;
            const incomeData = <?php echo json_encode($chartIncome); ?>;
            const appointmentData = <?php echo json_encode($chartAppointments); ?>;
            const newClientData = <?php echo json_encode($chartNewClients); ?>;

            const moneyFmt = new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 2 });
            const money = v => '₺' + moneyFmt.format(v);

            // Renkler yalnızca tasarım tokenlarından okunur (tema değişince yeniden çizilir)
            function palette() {
                const cs = getComputedStyle(document.body);
                const v = name => cs.getPropertyValue(name).trim();
                const dark = document.body.classList.contains('dark');
                return {
                    // Koyu temada yün zemin rengi yüzeyde kaybolur; aynı rengin okunur tonunu kullan
                    main: dark ? v('--accent-text') : v('--wool'),
                    second: v('--brass'),
                    line: v('--line'),
                    tick: v('--ink-3'),
                    legend: v('--ink-2'),
                    tipBg: v('--ink'),
                    tipText: v('--surface'),
                    surface: v('--surface'),
                    font: v('--font') || 'Figtree, system-ui, sans-serif'
                };
            }

            let charts = [];

            function build() {
                charts.forEach(c => c.destroy());
                charts = [];

                const p = palette();
                Chart.defaults.font.family = p.font;
                Chart.defaults.font.size = 12;
                Chart.defaults.color = p.tick;
                Chart.defaults.borderColor = p.line;

                const tooltip = {
                    backgroundColor: p.tipBg,
                    titleColor: p.tipText,
                    bodyColor: p.tipText,
                    borderWidth: 0,
                    cornerRadius: 10,
                    padding: 10,
                    boxPadding: 4,
                    usePointStyle: true,
                    titleFont: { family: p.font, weight: '600' },
                    bodyFont: { family: p.font },
                    callbacks: {
                        title: items => items.length ? fullLabels[items[0].dataIndex] : ''
                    }
                };

                const legend = {
                    position: 'top',
                    align: 'start',
                    labels: {
                        color: p.legend,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        padding: 16,
                        font: { family: p.font, size: 13, weight: '600' }
                    }
                };

                // Kazanç ve Randevu Grafiği
                const kazancCtx = document.getElementById('kazancChart').getContext('2d');
                charts.push(new Chart(kazancCtx, {
                    type: 'line',
                    data: {
                        labels: shortLabels,
                        datasets: [{
                            label: 'Kazanç (₺)',
                            data: incomeData,
                            borderColor: p.main,
                            backgroundColor: p.main,
                            pointBackgroundColor: p.main,
                            pointBorderColor: p.surface,
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2.5,
                            tension: 0.25,
                            yAxisID: 'y'
                        }, {
                            label: 'Randevu sayısı',
                            data: appointmentData,
                            borderColor: p.second,
                            backgroundColor: p.second,
                            pointBackgroundColor: p.second,
                            pointBorderColor: p.surface,
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2,
                            borderDash: [6, 4],
                            tension: 0.25,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: legend,
                            tooltip: Object.assign({}, tooltip, {
                                callbacks: Object.assign({}, tooltip.callbacks, {
                                    label: ctx => ctx.datasetIndex === 0
                                        ? ' Kazanç: ' + money(ctx.parsed.y)
                                        : ' Randevu: ' + ctx.parsed.y
                                })
                            })
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { color: p.line },
                                ticks: { color: p.tick }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                beginAtZero: true,
                                grid: { color: p.line },
                                border: { display: false },
                                ticks: {
                                    color: p.tick,
                                    maxTicksLimit: 5,
                                    callback: value => money(value)
                                },
                                title: {
                                    display: false,
                                    text: 'Kazanç (₺)'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                beginAtZero: true,
                                border: { display: false },
                                ticks: {
                                    color: p.tick,
                                    precision: 0,
                                    maxTicksLimit: 5
                                },
                                title: {
                                    display: false,
                                    text: 'Randevu Sayısı'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                }));

                // Yeni Danışan Grafiği
                const yeniDanisanCtx = document.getElementById('yeniDanisanChart').getContext('2d');
                charts.push(new Chart(yeniDanisanCtx, {
                    type: 'bar',
                    data: {
                        labels: shortLabels,
                        datasets: [{
                            label: 'Yeni danışan',
                            data: newClientData,
                            backgroundColor: p.main,
                            hoverBackgroundColor: p.main,
                            borderWidth: 0,
                            borderRadius: 6,
                            maxBarThickness: 36
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: Object.assign({}, tooltip, {
                                callbacks: Object.assign({}, tooltip.callbacks, {
                                    label: ctx => ' ' + ctx.parsed.y + ' yeni danışan'
                                })
                            })
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { color: p.line },
                                ticks: { color: p.tick }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: p.line },
                                border: { display: false },
                                ticks: {
                                    color: p.tick,
                                    precision: 0,
                                    maxTicksLimit: 5
                                },
                                title: {
                                    display: false,
                                    text: 'Danışan Sayısı'
                                }
                            }
                        }
                    }
                }));
            }

            // Figtree yüklenmeden çizilirse tuval yedek yazı tipinde kalır; yazı tiplerini bekle
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(build, build);
            } else {
                build();
            }

            // Tema (açık/koyu) değişince renkleri tokenlardan yeniden oku
            let lastDark = document.body.classList.contains('dark');
            new MutationObserver(function () {
                const dark = document.body.classList.contains('dark');
                if (dark !== lastDark) {
                    lastDark = dark;
                    build();
                }
            }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
        });
    </script>

<?php include 'includes/footer.php'; ?>
