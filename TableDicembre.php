<?php
include('simple_html_dom.php');

function fetch_weather_data($station_name, $year, $month) {
    $url = "https://www.wunderground.com/dashboard/pws/$station_name/table/$year-$month-1/$year-$month-1/monthly";
    $html = file_get_html($url);

    // Estrai il nome del paese
    $heading = $html->find('div.heading h1', 0)->plaintext;

    // Trova la posizione del primo trattino
    $pos = strpos($heading, ' - ');

    if ($pos !== false) {
        // Estrai la parte della stringa prima del primo trattino
        $country = trim(substr($heading, 0, $pos));
    } else {
        // Se non c'è alcun trattino, prendi tutta la stringa
        $country = trim($heading);
    }

    $data = [];
    foreach($html->find('tr.ng-star-inserted') as $row) {
        $date = $row->find('td', 0)->plaintext;
        $high = $row->find('td', 1)->plaintext;
        $low = $row->find('td', 3)->plaintext;

        if ($date && $high && $low) {
            $data[$date] = [
                'high' => floatval($high),
                'low' => floatval($low)
            ];
        }
    }
    return ['data' => $data, 'country' => $country];
}

function save_weather_data($station_name, $year) {
    $all_data = ['country' => '', 'data' => []];

    for ($month = 1; $month <= 12; $month++) {
        $result = fetch_weather_data($station_name, $year, $month);
        $country = $result['country'];
        $data = $result['data'];
        $all_data['country'] = $country;
        foreach ($data as $date => $values) {
            $all_data['data'][$date][$month] = $values;
        }
    }

    // Crea la cartella se non esiste
    $directory = "weather_data/{$station_name}";
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }

    // Salva i dati nella cartella della stazione meteo
    file_put_contents("{$directory}/weather_data_{$year}.json", json_encode($all_data));
}

function load_weather_data($station_name, $year) {
    $directory = "weather_data/{$station_name}";
    $file = "{$directory}/weather_data_{$year}.json";
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    } else {
        save_weather_data($station_name, $year);
        return json_decode(file_get_contents($file), true);
    }
}

function get_days_in_month($year, $month) {
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

function format_date($year, $month, $day) {
    return sprintf("%d/%d/%d", $month, $day, $year);
}

function interpolate_color($value, $min_temp, $max_temp) {
    $colors = [
        [-5, [0, 0, 255]],     // Blu per le temperature più fredde
        [10, [135, 206, 235]],   // Blu cielo per temperature leggermente fredde
        [15, [255, 255, 0]],    // Giallo per temperature fresche
        [20, [255, 165, 0]],    // Arancione per temperature calde
        [30, [255, 69, 0]],     // Arancione-rosso per temperature più calde
        [40, [255, 0, 0]],      // Rosso per le temperature più calde
    ];

    for ($i = 0; $i < count($colors) - 1; $i++) {
        if ($value >= $colors[$i][0] && $value <= $colors[$i+1][0]) {
            $ratio = ($value - $colors[$i][0]) / ($colors[$i+1][0] - $colors[$i][0]);
            $r = intval($colors[$i][1][0] + $ratio * ($colors[$i+1][1][0] - $colors[$i][1][0]));
            $g = intval($colors[$i][1][1] + $ratio * ($colors[$i+1][1][1] - $colors[$i][1][1]));
            $b = intval($colors[$i][1][2] + $ratio * ($colors[$i+1][1][2] - $colors[$i][1][2]));
            return sprintf("#%02x%02x%02x", $r, $g, $b);
        }
    }

    return "#ffffff"; // Colore predefinito se fuori intervallo
}

function fahrenheit_to_celsius($fahrenheit) {
    return ($fahrenheit - 32) * 5 / 9;
}

function prepare_chart_data($data, $year) {
    $chart_data = [
        'labels' => [],
        'temp_max' => [],
        'temp_min' => [],
        'year' => $year
    ];

    foreach ($data['data'] as $date => $months) {
        foreach ($months as $month => $values) {
            $chart_data['labels'][] = $date;
            $chart_data['temp_max'][] = round(fahrenheit_to_celsius($values['high']), 1);
            $chart_data['temp_min'][] = round(fahrenheit_to_celsius($values['low']), 1);
        }
    }

    return $chart_data;
}

$station_name = isset($_GET['NOMESTAZIONE']) ? $_GET['NOMESTAZIONE'] : 'ILAZIOCA17';
$year = isset($_POST['year']) ? intval($_POST['year']) : date("Y");
$comparison_year = isset($_POST['comparison_year']) ? intval($_POST['comparison_year']) : $year - 1;

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action == 'update') {
    $directory = "weather_data/{$station_name}";
    $file = "{$directory}/weather_data_{$year}.json";
    if (file_exists($file)) {
        unlink($file); // Cancella il file JSON esistente
    }
    save_weather_data($station_name, $year);
}

$data = load_weather_data($station_name, $year);
$chart_data = prepare_chart_data($data, $year);

$comparison_data = load_weather_data($station_name, $comparison_year);
$comparison_chart_data = prepare_chart_data($comparison_data, $comparison_year);

$min_temp = 0; // Temperatura minima in Celsius per la scala dei colori
$max_temp = 40;  // Temperatura massima in Celsius per la scala dei colori
$country = $data['country'];
?>

<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dati Meteo per <?php echo htmlspecialchars($station_name); ?> nel <?php echo htmlspecialchars($country); ?></title>
    <!-- Includi Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css" integrity="sha512-SzlrxWUlpfuzQ+pcUCosxcglQRNAq/DZjVsC0lE40xsADsfeQoEypE+enwcOiGjk/bSuGGKHEyjSoQ1zVisanQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #3490dc; /* Colore principale */
            --secondary-color: #6574cd; /* Colore secondario */
            --accent-color: #f6ad55; /* Colore di accento */
            --text-color: #2d3748; /* Colore del testo */
            --background-color: #f7fafc; /* Colore di sfondo */
        }

        [data-theme="dark"] {
            --primary-color: #90cdf4;
            --secondary-color: #a3bffa;
            --accent-color: #f6ad55;
            --text-color: #e2e8f0;
            --background-color: #1a202c;
        }
        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
        }
        .container {
            max-width: 1200px;
        }
        .shadow-custom {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .rounded-xl {
            border-radius: 0.75rem;
        }
        .hover\:bg-blue-700:hover {
            background-color: var(--primary-color);
        }
        .hover\:bg-green-700:hover {
            background-color: #38a169;
        }
        .theme-toggle {
            background-color: var(--secondary-color);
        }
        .theme-toggle .handle {
            background-color: white;
        }
        [data-theme="dark"] .theme-toggle .handle {
            transform: translateX(2rem);
        }
        .chart-container {
            background: linear-gradient(135deg, var(--primary-color) 10%, var(--background-color) 90%);
            position: relative;
            padding-top: 3rem;
            border-radius: 0.75rem;
        }

        .chart-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background-color: rgba(255, 255, 255, 0.7);
            padding: 0.5rem;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            z-index: 10;
        }

        [data-theme="dark"] .chart-overlay {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--text-color);
            margin-bottom: 0.5rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .chart-subtitle {
            font-size: 1rem;
            color: var(--text-color);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        #tempChart {
          background-color: transparent;
        }

        .weather-table thead th {
            background-color: var(--primary-color);
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
        }

        .weather-table tbody td {
            font-weight: 500;
        }
        
        .weather-table tbody tr:nth-child(odd) {
            background-color: var(--background-color);
        }

        .weather-table tbody tr:nth-child(even) {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        [data-theme="dark"] .weather-table tbody tr:nth-child(even) {
            background-color: rgba(255, 255, 255, 0.05);
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleInput = document.getElementById('themeToggle');
            const html = document.documentElement;
            
            const currentTheme = localStorage.getItem('theme') ? localStorage.getItem('theme') : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            html.setAttribute('data-theme', currentTheme);
            toggleInput.checked = currentTheme === 'dark';

            toggleInput.addEventListener('change', function() {
                if (this.checked) {
                    html.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    html.setAttribute('data-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            });
        });
        
        function showLoading() {
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('loading').classList.add('flex');
        }

        document.addEventListener('DOMContentLoaded', function () {
            var ctx = document.getElementById('tempChart').getContext('2d');
            var chartData = {
                labels: <?php echo json_encode($chart_data['labels']); ?>,
                datasets: [
                    {
                        label: 'Temp Max (<?php echo $year; ?>) (°C)',
                        data: <?php echo json_encode($chart_data['temp_max']); ?>,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 1,
                    },
                    {
                        label: 'Temp Min (<?php echo $year; ?>) (°C)',
                        data: <?php echo json_encode($chart_data['temp_min']); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 1,
                    },
                    {
                        label: 'Temp Max (<?php echo $comparison_year; ?>) (°C)',
                        data: <?php echo json_encode($comparison_chart_data['temp_max']); ?>,
                        borderColor: 'rgba(255, 159, 64, 1)',
                        backgroundColor: 'rgba(255, 159, 64, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 1,
                    },
                    {
                        label: 'Temp Min (<?php echo $comparison_year; ?>) (°C)',
                        data: <?php echo json_encode($comparison_chart_data['temp_min']); ?>,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 1,
                    }
                ]
            };

            new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: 'var(--text-color)',
                                font: {
                                    size: 14
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'category',
                            title: {
                                display: true,
                                text: 'Date',
                                color: 'var(--text-color)',
                                font: {
                                    size: 16
                                }
                            },
                            ticks: {
                                color: 'var(--text-color)'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Temperature (°C)',
                                color: 'var(--text-color)',
                                font: {
                                    size: 16
                                }
                            },
                            ticks: {
                                color: 'var(--text-color)'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    }
                }
            });
        });
    </script>
</head>
<body class="transition-colors duration-500">
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto flex justify-between items-center p-4">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-temperature-half text-3xl mr-3"></i>
                <span>Dati Meteo per <?php echo htmlspecialchars($station_name); ?> nel <?php echo htmlspecialchars($country); ?></span>
            </h1>
            <div class="flex items-center">
                <input type="checkbox" id="themeToggle" class="hidden">
                <label for="themeToggle" class="cursor-pointer">
                    <div class="w-12 h-6 theme-toggle rounded-full p-1 flex items-center transition-colors duration-300">
                        <div class="w-4 h-4 handle rounded-full shadow-md transform transition-transform duration-300"></div>
                    </div>
                </label>
            </div>
        </div>
    </header>

    <section class="py-6">
        <div class="container mx-auto">
            <form method="post" onsubmit="showLoading()" class="flex flex-wrap gap-4 justify-center items-center">
                <div class="flex items-center gap-2">
                    <label for="year" class="font-semibold text-lg">Seleziona Anno:</label>
                    <select name="year" id="year" class="p-2 border rounded-md text-lg" required>
                        <?php for ($i = date("Y"); $i >= 2000; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $year) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label for="comparison_year" class="font-semibold text-lg">Confronta con Anno:</label>
                    <select name="comparison_year" id="comparison_year" class="p-2 border rounded-md text-lg" required>
                        <?php for ($i = date("Y"); $i >= 2000; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $comparison_year) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <input type="hidden" name="station_name" value="<?php echo htmlspecialchars($station_name); ?>">
                <button type="submit" name="action" value="load" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 text-lg font-semibold transition-colors duration-300">
                    <i class="fas fa-sync-alt mr-2"></i> Carica Dati
                </button>
                <button type="submit" name="action" value="update" class="bg-green-600 text-white px-6 py-3 rounded-md hover:bg-green-700 text-lg font-semibold transition-colors duration-300">
                    <i class="fas fa-cloud-download-alt mr-2"></i> Aggiorna Dati
                </button>
            </form>
        </div>
    </section>

    <div id="loading" class="hidden fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="loader ease-linear rounded-full border-8 border-t-8 border-gray-200 h-32 w-32"></div>
    </div>

    <div class="container mx-auto my-8">
        <div class="chart-container shadow-custom rounded-xl overflow-hidden">
            <div class="chart-overlay">
                <h2 class="chart-title">Grafico Temperature</h2>
                <p class="chart-subtitle"><?php echo htmlspecialchars($station_name); ?> - <?php echo htmlspecialchars($country); ?></p>
            </div>
            <canvas id="tempChart" class="w-full h-96" style="padding-top: 1rem;"></canvas>
        </div>
    </div>

    <div class="container mx-auto my-8 overflow-x-auto">
        <table class="min-w-full weather-table shadow-custom rounded-xl">
            <thead class="text-white">
                <tr>
                    <th rowspan="2" class="p-3 text-left">Data</th>
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                        <th colspan="2" class="p-3 text-left"><?php echo date('F', mktime(0, 0, 0, $month, 10)); ?></th>
                    <?php endfor; ?>
                </tr>
                <tr>
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                        <th class="p-3 text-left">Temp. Max (°C)</th>
                        <th class="p-3 text-left">Temp. Min (°C)</th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($day = 1; $day <= 31; $day++): ?>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <td class="p-3 font-semibold"><?php echo $day; ?></td>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <?php if ($day <= get_days_in_month($year, $month)): ?>
                                <?php
                                $date = format_date($year, $month, $day);
                                if (isset($data['data'][$date][$month])):
                                    $highF = $data['data'][$date][$month]['high'];
                                    $lowF = $data['data'][$date][$month]['low'];
                                    $highC = fahrenheit_to_celsius($highF);
                                    $lowC = fahrenheit_to_celsius($lowF);
                                    $highColor = interpolate_color($highC, $min_temp, $max_temp);
                                    $lowColor = interpolate_color($lowC, $min_temp, $max_temp);
                                ?>
                                    <td class="p-3" style="background-color: <?php echo $highColor; ?>;">
                                        <?php echo round($highC, 1); ?> °C
                                    </td>
                                    <td class="p-3" style="background-color: <?php echo $lowColor; ?>;">
                                        <?php echo round($lowC, 1); ?> °C
                                    </td>
                                <?php else: ?>
                                    <td colspan="2" class="p-3">N/D</td>
                                <?php endif; ?>
                            <?php else: ?>
                                <td colspan="2" class="p-3"></td>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</body>
</html><?php
include('simple_html_dom.php');

function fetch_weather_data($station_name, $year, $month) {
    $url = "https://www.wunderground.com/dashboard/pws/$station_name/table/$year-$month-1/$year-$month-1/monthly";
    $html = file_get_html($url);

    // Estrai il nome del paese
    $heading = $html->find('div.heading h1', 0)->plaintext;

    // Trova la posizione del primo trattino
    $pos = strpos($heading, ' - ');

    if ($pos !== false) {
        // Estrai la parte della stringa prima del primo trattino
        $country = trim(substr($heading, 0, $pos));
    } else {
        // Se non c'è alcun trattino, prendi tutta la stringa
        $country = trim($heading);
    }

    $data = [];
    foreach($html->find('tr.ng-star-inserted') as $row) {
        $date = $row->find('td', 0)->plaintext;
        $high = $row->find('td', 1)->plaintext;
        $low = $row->find('td', 3)->plaintext;

        if ($date && $high && $low) {
            $data[$date] = [
                'high' => floatval($high),
                'low' => floatval($low)
            ];
        }
    }
    return ['data' => $data, 'country' => $country];
}

function save_weather_data($station_name, $year) {
    $all_data = ['country' => '', 'data' => []];

    for ($month = 1; $month <= 12; $month++) {
        $result = fetch_weather_data($station_name, $year, $month);
        $country = $result['country'];
        $data = $result['data'];
        $all_data['country'] = $country;
        foreach ($data as $date => $values) {
            $all_data['data'][$date][$month] = $values;
        }
    }

    // Crea la cartella se non esiste
    $directory = "weather_data/{$station_name}";
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }

    // Salva i dati nella cartella della stazione meteo
    file_put_contents("{$directory}/weather_data_{$year}.json", json_encode($all_data));
}

function load_weather_data($station_name, $year) {
    $directory = "weather_data/{$station_name}";
    $file = "{$directory}/weather_data_{$year}.json";
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    } else {
        save_weather_data($station_name, $year);
        return json_decode(file_get_contents($file), true);
    }
}

function get_days_in_month($year, $month) {
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

function format_date($year, $month, $day) {
    return sprintf("%d/%d/%d", $month, $day, $year);
}

function interpolate_color($value, $min_temp, $max_temp) {
    $colors = [
        [-5, [0, 0, 255]],     // Blu per le temperature più fredde
        [10, [135, 206, 235]],   // Blu cielo per temperature leggermente fredde
        [15, [255, 255, 0]],    // Giallo per temperature fresche
        [20, [255, 165, 0]],    // Arancione per temperature calde
        [30, [255, 69, 0]],     // Arancione-rosso per temperature più calde
        [40, [255, 0, 0]],      // Rosso per le temperature più calde
    ];

    for ($i = 0; $i < count($colors) - 1; $i++) {
        if ($value >= $colors[$i][0] && $value <= $colors[$i+1][0]) {
            $ratio = ($value - $colors[$i][0]) / ($colors[$i+1][0] - $colors[$i][0]);
            $r = intval($colors[$i][1][0] + $ratio * ($colors[$i+1][1][0] - $colors[$i][1][0]));
            $g = intval($colors[$i][1][1] + $ratio * ($colors[$i+1][1][1] - $colors[$i][1][1]));
            $b = intval($colors[$i][1][2] + $ratio * ($colors[$i+1][1][2] - $colors[$i][1][2]));
            return sprintf("#%02x%02x%02x", $r, $g, $b);
        }
    }

    return "#ffffff"; // Colore predefinito se fuori intervallo
}

function fahrenheit_to_celsius($fahrenheit) {
    return ($fahrenheit - 32) * 5 / 9;
}

function prepare_chart_data($data, $year) {
    $chart_data = [
        'labels' => [],
        'temp_max' => [],
        'temp_min' => [],
        'year' => $year
    ];

    foreach ($data['data'] as $date => $months) {
        foreach ($months as $month => $values) {
            $chart_data['labels'][] = $date;
            $chart_data['temp_max'][] = round(fahrenheit_to_celsius($values['high']), 1);
            $chart_data['temp_min'][] = round(fahrenheit_to_celsius($values['low']), 1);
        }
    }

    return $chart_data;
}

$station_name = isset($_GET['NOMESTAZIONE']) ? $_GET['NOMESTAZIONE'] : 'ILAZIOCA17';
$year = isset($_POST['year']) ? intval($_POST['year']) : date("Y");
$comparison_year = isset($_POST['comparison_year']) ? intval($_POST['comparison_year']) : $year - 1;

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action == 'update') {
    $directory = "weather_data/{$station_name}";
    $file = "{$directory}/weather_data_{$year}.json";
    if (file_exists($file)) {
        unlink($file); // Cancella il file JSON esistente
    }
    save_weather_data($station_name, $year);
}

$data = load_weather_data($station_name, $year);
$chart_data = prepare_chart_data($data, $year);

$comparison_data = load_weather_data($station_name, $comparison_year);
$comparison_chart_data = prepare_chart_data($comparison_data, $comparison_year);

$min_temp = 0; // Temperatura minima in Celsius per la scala dei colori
$max_temp = 40;  // Temperatura massima in Celsius per la scala dei colori
$country = $data['country'];
?>

<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dati Meteo per <?php echo htmlspecialchars($station_name); ?> nel <?php echo htmlspecialchars($country); ?></title>
    <!-- Includi Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css" integrity="sha512-SzlrxWUlpfuzQ+pcUCosxcglQRNAq/DZjVsC0lE40xsADsfeQoEypE+enwcOiGjk/bSuGGKHEyjSoQ1zVisanQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #3490dc; /* Colore principale */
            --secondary-color: #6574cd; /* Colore secondario */
            --accent-color: #f6ad55; /* Colore di accento */
            --text-color: #2d3748; /* Colore del testo */
            --background-color: #f7fafc; /* Colore di sfondo */
        }

        [data-theme="dark"] {
            --primary-color: #90cdf4;
            --secondary-color: #a3bffa;
            --accent-color: #f6ad55;
            --text-color: #e2e8f0;
            --background-color: #1a202c;
        }
        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
        }
        .container {
            max-width: 1200px;
        }
        .shadow-custom {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .rounded-xl {
            border-radius: 0.75rem;
        }
        .hover\:bg-blue-700:hover {
            background-color: var(--primary-color);
        }
        .hover\:bg-green-700:hover {
            background-color: #38a169;
        }
        .theme-toggle {
            background-color: var(--secondary-color);
        }
        .theme-toggle .handle {
            background-color: white;
        }
        [data-theme="dark"] .theme-toggle .handle {
            transform: translateX(2rem);
        }
        .chart-container {
            background: linear-gradient(135deg, var(--primary-color) 10%, var(--background-color) 90%);
            position: relative;
            padding-top: 3rem;
            border-radius: 0.75rem;
        }

        .chart-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background-color: rgba(255, 255, 255, 0.7);
            padding: 0.5rem;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            z-index: 10;
        }

        [data-theme="dark"] .chart-overlay {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--text-color);
            margin-bottom: 0.5rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .chart-subtitle {
            font-size: 1rem;
            color: var(--text-color);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        #tempChart {
          background-color: transparent;
        }

        .weather-table thead th {
            background-color: var(--primary-color);
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
        }

        .weather-table tbody td {
            font-weight: 500;
        }
        
        .weather-table tbody tr:nth-child(odd) {
            background-color: var(--background-color);
        }

        .weather-table tbody tr:nth-child(even) {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        [data-theme="dark"] .weather-table tbody tr:nth-child(even) {
            background-color: rgba(255, 255, 255, 0.05);
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleInput = document.getElementById('themeToggle');
            const html = document.documentElement;
            
            const currentTheme = localStorage.getItem('theme') ? localStorage.getItem('theme') : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            html.setAttribute('data-theme', currentTheme);
            toggleInput.checked = currentTheme === 'dark';

            toggleInput.addEventListener('change', function() {
                if (this.checked) {
                    html.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    html.setAttribute('data-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            });
        });
        
        function showLoading() {
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('loading').classList.add('flex');
        }

        document.addEventListener('DOMContentLoaded', function () {
            var ctx = document.getElementById('tempChart').getContext('2d');
            var chartData = {
                labels: <?php echo json_encode($chart_data['labels']); ?>,
                datasets: [
                    {
                        label: 'Temp Max (<?php echo $year; ?>) (°C)',
                        data: <?php echo json_encode($chart_data['temp_max']); ?>,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 1,
                    },
                    {
                        label: 'Temp Min (<?php echo $year; ?>) (°C)',
                        data: <?php echo json_encode($chart_data['temp_min']); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 1,
                    },
                    {
                        label: 'Temp Max (<?php echo $comparison_year; ?>) (°C)',
                        data: <?php echo json_encode($comparison_chart_data['temp_max']); ?>,
                        borderColor: 'rgba(255, 159, 64, 1)',
                        backgroundColor: 'rgba(255, 159, 64, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 1,
                    },
                    {
                        label: 'Temp Min (<?php echo $comparison_year; ?>) (°C)',
                        data: <?php echo json_encode($comparison_chart_data['temp_min']); ?>,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 1,
                    }
                ]
            };

            new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: 'var(--text-color)',
                                font: {
                                    size: 14
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'category',
                            title: {
                                display: true,
                                text: 'Date',
                                color: 'var(--text-color)',
                                font: {
                                    size: 16
                                }
                            },
                            ticks: {
                                color: 'var(--text-color)'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Temperature (°C)',
                                color: 'var(--text-color)',
                                font: {
                                    size: 16
                                }
                            },
                            ticks: {
                                color: 'var(--text-color)'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    }
                }
            });
        });
    </script>
</head>
<body class="transition-colors duration-500">
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto flex justify-between items-center p-4">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-temperature-half text-3xl mr-3"></i>
                <span>Dati Meteo per <?php echo htmlspecialchars($station_name); ?> nel <?php echo htmlspecialchars($country); ?></span>
            </h1>
            <div class="flex items-center">
                <input type="checkbox" id="themeToggle" class="hidden">
                <label for="themeToggle" class="cursor-pointer">
                    <div class="w-12 h-6 theme-toggle rounded-full p-1 flex items-center transition-colors duration-300">
                        <div class="w-4 h-4 handle rounded-full shadow-md transform transition-transform duration-300"></div>
                    </div>
                </label>
            </div>
        </div>
    </header>

    <section class="py-6">
        <div class="container mx-auto">
            <form method="post" onsubmit="showLoading()" class="flex flex-wrap gap-4 justify-center items-center">
                <div class="flex items-center gap-2">
                    <label for="year" class="font-semibold text-lg">Seleziona Anno:</label>
                    <select name="year" id="year" class="p-2 border rounded-md text-lg" required>
                        <?php for ($i = date("Y"); $i >= 2000; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $year) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label for="comparison_year" class="font-semibold text-lg">Confronta con Anno:</label>
                    <select name="comparison_year" id="comparison_year" class="p-2 border rounded-md text-lg" required>
                        <?php for ($i = date("Y"); $i >= 2000; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $comparison_year) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <input type="hidden" name="station_name" value="<?php echo htmlspecialchars($station_name); ?>">
                <button type="submit" name="action" value="load" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 text-lg font-semibold transition-colors duration-300">
                    <i class="fas fa-sync-alt mr-2"></i> Carica Dati
                </button>
                <button type="submit" name="action" value="update" class="bg-green-600 text-white px-6 py-3 rounded-md hover:bg-green-700 text-lg font-semibold transition-colors duration-300">
                    <i class="fas fa-cloud-download-alt mr-2"></i> Aggiorna Dati
                </button>
            </form>
        </div>
    </section>

    <div id="loading" class="hidden fixed inset-0 bg-black bg-opacity-50 justify-center items-center z-50">
        <div class="loader ease-linear rounded-full border-8 border-t-8 border-gray-200 h-32 w-32"></div>
    </div>

    <div class="container mx-auto my-8">
        <div class="chart-container shadow-custom rounded-xl overflow-hidden">
            <div class="chart-overlay">
                <h2 class="chart-title">Grafico Temperature</h2>
                <p class="chart-subtitle"><?php echo htmlspecialchars($station_name); ?> - <?php echo htmlspecialchars($country); ?></p>
            </div>
            <canvas id="tempChart" class="w-full h-96" style="padding-top: 1rem;"></canvas>
        </div>
    </div>

    <div class="container mx-auto my-8 overflow-x-auto">
        <table class="min-w-full weather-table shadow-custom rounded-xl">
            <thead class="text-white">
                <tr>
                    <th rowspan="2" class="p-3 text-left">Data</th>
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                        <th colspan="2" class="p-3 text-left"><?php echo date('F', mktime(0, 0, 0, $month, 10)); ?></th>
                    <?php endfor; ?>
                </tr>
                <tr>
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                        <th class="p-3 text-left">Temp. Max (°C)</th>
                        <th class="p-3 text-left">Temp. Min (°C)</th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($day = 1; $day <= 31; $day++): ?>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <td class="p-3 font-semibold"><?php echo $day; ?></td>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <?php if ($day <= get_days_in_month($year, $month)): ?>
                                <?php
                                $date = format_date($year, $month, $day);
                                if (isset($data['data'][$date][$month])):
                                    $highF = $data['data'][$date][$month]['high'];
                                    $lowF = $data['data'][$date][$month]['low'];
                                    $highC = fahrenheit_to_celsius($highF);
                                    $lowC = fahrenheit_to_celsius($lowF);
                                    $highColor = interpolate_color($highC, $min_temp, $max_temp);
                                    $lowColor = interpolate_color($lowC, $min_temp, $max_temp);
                                ?>
                                    <td class="p-3" style="background-color: <?php echo $highColor; ?>;">
                                        <?php echo round($highC, 1); ?> °C
                                    </td>
                                    <td class="p-3" style="background-color: <?php echo $lowColor; ?>;">
                                        <?php echo round($lowC, 1); ?> °C
                                    </td>
                                <?php else: ?>
                                    <td colspan="2" class="p-3">N/D</td>
                                <?php endif; ?>
                            <?php else: ?>
                                <td colspan="2" class="p-3"></td>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</body>
</html>