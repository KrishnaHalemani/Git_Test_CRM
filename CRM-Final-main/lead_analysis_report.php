<?php
session_start();
include 'db.php';
require_role(['superadmin']);

logSystemActivity($_SESSION['user_id'], 'view_report', "Viewed Lead Analysis Report for Year: " . (isset($_GET['year']) ? $_GET['year'] : date('Y')));

$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$current_year = intval(date('Y'));
$current_month = intval(date('n'));
$current_week = intval(date('W'));

// 1. Fetch available years for filter
$years = [];
try {
    $sql = "SELECT DISTINCT YEAR(created_at) as y FROM leads ORDER BY y DESC";
    if ($db_type === 'pdo') {
        $stmt = $conn->query($sql);
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $res = $conn->query($sql);
        while($row = $res->fetch_assoc()) $years[] = $row['y'];
    }
} catch (Exception $e) {}
if (empty($years)) $years = [date('Y')];

// 2. Monthly Data for Selected Year (Bar Chart)
$monthly_counts = array_fill(1, 12, 0);
$monthly_converted = array_fill(1, 12, 0);
try {
    // Total Leads Created
    $sql = "SELECT MONTH(created_at) as m, COUNT(*) as c FROM leads WHERE YEAR(created_at) = ? GROUP BY m";
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selected_year]);
        while($row = $stmt->fetch()) $monthly_counts[$row['m']] = $row['c'];
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $selected_year);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) $monthly_counts[$row['m']] = $row['c'];
    }

    // Converted Leads (Created in that month)
    $sql = "SELECT MONTH(created_at) as m, COUNT(*) as c FROM leads WHERE YEAR(created_at) = ? AND status = 'converted' GROUP BY m";
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selected_year]);
        while($row = $stmt->fetch()) $monthly_converted[$row['m']] = $row['c'];
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $selected_year);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) $monthly_converted[$row['m']] = $row['c'];
    }
} catch (Exception $e) {}

// 3. Yearly Data History (Bar Chart)
$yearly_counts = [];
try {
    $sql = "SELECT YEAR(created_at) as y, COUNT(*) as c FROM leads GROUP BY y ORDER BY y ASC";
    if ($db_type === 'pdo') {
        $stmt = $conn->query($sql);
        while($row = $stmt->fetch()) $yearly_counts[$row['y']] = $row['c'];
    } else {
        $res = $conn->query($sql);
        while($row = $res->fetch_assoc()) $yearly_counts[$row['y']] = $row['c'];
    }
} catch (Exception $e) {}

// 4. Status Distribution for Selected Year (Pie Chart)
$status_counts = [];
try {
    $sql = "SELECT status, COUNT(*) as c FROM leads WHERE YEAR(created_at) = ? GROUP BY status";
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selected_year]);
        while($row = $stmt->fetch()) $status_counts[$row['status']] = $row['c'];
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $selected_year);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) $status_counts[$row['status']] = $row['c'];
    }
} catch (Exception $e) {}

// 5. Weekly, Monthly, Yearly Stats (Snapshot)
$snapshot_month = $current_month;
$snapshot_week = $current_week;
$requested_week = isset($_GET['week']) ? intval($_GET['week']) : null;
$available_weeks = [];
$weekly_prev_week = null;
$weekly_next_week = null;
$latest_date_in_year = null;
$weekly_total = 0;
$weekly_converted = 0;
$monthly_total = 0;
$monthly_converted_snapshot = 0;
$yearly_total = array_sum($monthly_counts);
$yearly_converted = intval($status_counts['converted'] ?? 0);

try {
    // Always anchor snapshots to latest available lead date in selected year.
    // This avoids empty weekly view when current week has no new leads yet.
    $sql = "SELECT MAX(created_at) AS latest_date FROM leads WHERE YEAR(created_at) = ?";
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selected_year]);
        $latest_date_in_year = $stmt->fetchColumn();
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $selected_year);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $latest_date_in_year = $row['latest_date'] ?? null;
    }

    if (!empty($latest_date_in_year)) {
        $snapshot_month = intval(date('n', strtotime($latest_date_in_year)));
        $snapshot_week = intval(date('W', strtotime($latest_date_in_year)));
    }

    $sql = "SELECT DISTINCT WEEK(created_at, 1) AS w FROM leads WHERE YEAR(created_at) = ? ORDER BY w DESC";
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selected_year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $week_num = intval($row['w'] ?? 0);
            if ($week_num > 0) $available_weeks[] = $week_num;
        }
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $selected_year);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $week_num = intval($row['w'] ?? 0);
            if ($week_num > 0) $available_weeks[] = $week_num;
        }
    }

    if (!empty($available_weeks)) {
        if (!empty($requested_week) && in_array($requested_week, $available_weeks, true)) {
            $snapshot_week = $requested_week;
        } else {
            $snapshot_week = $available_weeks[0];
        }
        $week_index = array_search($snapshot_week, $available_weeks, true);
        if ($week_index !== false) {
            if (isset($available_weeks[$week_index + 1])) {
                $weekly_prev_week = intval($available_weeks[$week_index + 1]);
            }
            if ($week_index > 0 && isset($available_weeks[$week_index - 1])) {
                $weekly_next_week = intval($available_weeks[$week_index - 1]);
            }
        }
    }

    $sql = "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted
            FROM leads
            WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?";
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selected_year, $snapshot_month]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $monthly_total = intval($row['total'] ?? 0);
        $monthly_converted_snapshot = intval($row['converted'] ?? 0);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $selected_year, $snapshot_month);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $monthly_total = intval($row['total'] ?? 0);
        $monthly_converted_snapshot = intval($row['converted'] ?? 0);
    }

    $sql = "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted
            FROM leads
            WHERE YEAR(created_at) = ? AND WEEK(created_at, 1) = ?";
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selected_year, $snapshot_week]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $weekly_total = intval($row['total'] ?? 0);
        $weekly_converted = intval($row['converted'] ?? 0);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $selected_year, $snapshot_week);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $weekly_total = intval($row['total'] ?? 0);
        $weekly_converted = intval($row['converted'] ?? 0);
    }
} catch (Exception $e) {}

$weekly_rate = $weekly_total > 0 ? round(($weekly_converted / $weekly_total) * 100, 1) : 0;
$monthly_rate = $monthly_total > 0 ? round(($monthly_converted_snapshot / $monthly_total) * 100, 1) : 0;
$yearly_rate = $yearly_total > 0 ? round(($yearly_converted / $yearly_total) * 100, 1) : 0;
$snapshot_month_name = date('F', mktime(0, 0, 0, $snapshot_month, 1));
$selected_view = isset($_GET['view']) ? strtolower(trim($_GET['view'])) : 'monthly';
if (!in_array($selected_view, ['weekly', 'monthly', 'yearly'], true)) {
    $selected_view = 'monthly';
}
$weekly_view_base = "lead_analysis_report.php?year={$selected_year}&view=weekly";
$weekly_view_with_current_week = $weekly_view_base . "&week=" . intval($snapshot_week);
$month_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$week_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$weekly_counts = array_fill(0, 7, 0);
$weekly_converted_counts = array_fill(0, 7, 0);
$max_monthly_value = !empty($monthly_counts) ? max($monthly_counts) : 0;
$max_yearly_value = !empty($yearly_counts) ? max($yearly_counts) : 0;
$status_palette = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74c3c', '#858796', '#20c997', '#fd7e14'];
$view_status_counts = [];
$trend_title = '';

try {
    $sql = "SELECT WEEKDAY(created_at) AS d, COUNT(*) AS total, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted
            FROM leads
            WHERE YEAR(created_at) = ? AND WEEK(created_at, 1) = ?
            GROUP BY d";
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selected_year, $snapshot_week]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $day = intval($row['d']);
            if ($day >= 0 && $day <= 6) {
                $weekly_counts[$day] = intval($row['total'] ?? 0);
                $weekly_converted_counts[$day] = intval($row['converted'] ?? 0);
            }
        }
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $selected_year, $snapshot_week);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $day = intval($row['d']);
            if ($day >= 0 && $day <= 6) {
                $weekly_counts[$day] = intval($row['total'] ?? 0);
                $weekly_converted_counts[$day] = intval($row['converted'] ?? 0);
            }
        }
    }
} catch (Exception $e) {}

try {
    if ($selected_view === 'weekly') {
        $trend_title = "Weekly Lead Trend (Week {$snapshot_week}, {$selected_year})";
        $sql = "SELECT status, COUNT(*) as c FROM leads WHERE YEAR(created_at) = ? AND WEEK(created_at, 1) = ? GROUP BY status";
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$selected_year, $snapshot_week]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $view_status_counts[$row['status']] = intval($row['c']);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $selected_year, $snapshot_week);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $view_status_counts[$row['status']] = intval($row['c']);
        }
    } elseif ($selected_view === 'yearly') {
        $trend_title = "Yearly Growth Analysis (Historical)";
        $view_status_counts = $status_counts;
    } else {
        $trend_title = "Monthly Lead Trend ({$selected_year})";
        $sql = "SELECT status, COUNT(*) as c FROM leads WHERE YEAR(created_at) = ? AND MONTH(created_at) = ? GROUP BY status";
        if ($db_type === 'pdo') {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$selected_year, $snapshot_month]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $view_status_counts[$row['status']] = intval($row['c']);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $selected_year, $snapshot_month);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $view_status_counts[$row['status']] = intval($row['c']);
        }
    }
} catch (Exception $e) {
    $view_status_counts = $status_counts;
}

$max_weekly_value = max(array_merge($weekly_counts, $weekly_converted_counts));
$total_status_count = array_sum($view_status_counts);
$trend_pie_data = [];
if ($selected_view === 'weekly') {
    foreach ($week_labels as $idx => $label) {
        $val = intval($weekly_counts[$idx] ?? 0);
        if ($val > 0) $trend_pie_data[$label] = $val;
    }
} elseif ($selected_view === 'yearly') {
    foreach ($yearly_counts as $year => $count) {
        $val = intval($count);
        if ($val > 0) $trend_pie_data[(string)$year] = $val;
    }
} else {
    foreach ($month_labels as $idx => $label) {
        $month_no = $idx + 1;
        $val = intval($monthly_counts[$month_no] ?? 0);
        if ($val > 0) $trend_pie_data[$label] = $val;
    }
}
$trend_total_count = array_sum($trend_pie_data);
$trend_chart_labels = array_values(array_keys($trend_pie_data));
$trend_chart_series = array_values(array_map('intval', $trend_pie_data));
$status_chart_labels = [];
$status_chart_series = [];
foreach ($view_status_counts as $status => $count) {
    $status_chart_labels[] = ucfirst((string)$status);
    $status_chart_series[] = intval($count);
}
$trend_pie_download_url = "lead_analysis_report.php?year={$selected_year}&view={$selected_view}";
if ($selected_view === 'weekly') {
    $trend_pie_download_url .= '&week=' . intval($snapshot_week);
}
$trend_pie_download_url .= '&download=trend_pie';

$trend_pie_svg_markup = '';
if ($trend_total_count > 0) {
    $svg_width = 820;
    $svg_height = 320;
    $cx = 150;
    $cy = 165;
    $radius = 95;
    $start_angle = -90.0;
    $legend_x = 290;
    $legend_y = 50;
    $legend_step = 22;
    $slice_index = 0;
    $path_markup = '';
    $legend_markup = '';

    foreach ($trend_pie_data as $label => $count) {
        $count = intval($count);
        if ($count <= 0) continue;
        $color = $status_palette[$slice_index % count($status_palette)];
        $slice_index++;
        $slice_angle = ($count / $trend_total_count) * 360.0;
        $end_angle = $start_angle + $slice_angle;
        $start_rad = deg2rad($start_angle);
        $end_rad = deg2rad($end_angle);
        $x1 = $cx + ($radius * cos($start_rad));
        $y1 = $cy + ($radius * sin($start_rad));
        $x2 = $cx + ($radius * cos($end_rad));
        $y2 = $cy + ($radius * sin($end_rad));
        $large_arc = ($slice_angle > 180) ? 1 : 0;
        if ($slice_angle >= 359.99) {
            $path_markup .= '<circle cx="' . number_format($cx, 2, '.', '') . '" cy="' . number_format($cy, 2, '.', '') . '" r="' . number_format($radius, 2, '.', '') . '" fill="' . htmlspecialchars($color) . '"></circle>';
        } else {
            $path_markup .= '<path d="M ' . number_format($cx, 2, '.', '') . ' ' . number_format($cy, 2, '.', '') .
                            ' L ' . number_format($x1, 2, '.', '') . ' ' . number_format($y1, 2, '.', '') .
                            ' A ' . number_format($radius, 2, '.', '') . ' ' . number_format($radius, 2, '.', '') .
                            ' 0 ' . $large_arc . ' 1 ' . number_format($x2, 2, '.', '') . ' ' . number_format($y2, 2, '.', '') .
                            ' Z" fill="' . htmlspecialchars($color) . '"></path>';
        }
        $pct = round(($count / $trend_total_count) * 100, 1);
        $legend_row_y = $legend_y + (($slice_index - 1) * $legend_step);
        $legend_markup .= '<rect x="' . $legend_x . '" y="' . $legend_row_y . '" width="12" height="12" fill="' . htmlspecialchars($color) . '"></rect>';
        $legend_markup .= '<text x="' . ($legend_x + 18) . '" y="' . ($legend_row_y + 10) . '" fill="#343a40" font-size="12" font-family="Arial, sans-serif">' .
                          htmlspecialchars($label) . ': ' . $count . ' (' . $pct . '%)</text>';
        $start_angle = $end_angle;
    }

    $trend_pie_svg_markup = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $svg_width . '" height="' . $svg_height . '" viewBox="0 0 ' . $svg_width . ' ' . $svg_height . '">'
                          . '<rect x="0" y="0" width="' . $svg_width . '" height="' . $svg_height . '" fill="#ffffff"></rect>'
                          . '<text x="20" y="28" fill="#212529" font-size="18" font-family="Arial, sans-serif">' . htmlspecialchars($trend_title) . '</text>'
                          . $path_markup
                          . '<circle cx="' . number_format($cx, 2, '.', '') . '" cy="' . number_format($cy, 2, '.', '') . '" r="50" fill="#ffffff"></circle>'
                          . '<text x="' . number_format($cx - 18, 2, '.', '') . '" y="' . number_format($cy + 6, 2, '.', '') . '" fill="#495057" font-size="16" font-family="Arial, sans-serif">' . $trend_total_count . '</text>'
                          . $legend_markup
                          . '</svg>';
} else {
    $trend_pie_svg_markup = '<svg xmlns="http://www.w3.org/2000/svg" width="820" height="320" viewBox="0 0 820 320">'
                          . '<rect x="0" y="0" width="820" height="320" fill="#ffffff"></rect>'
                          . '<text x="20" y="28" fill="#212529" font-size="18" font-family="Arial, sans-serif">' . htmlspecialchars($trend_title) . '</text>'
                          . '<text x="20" y="80" fill="#6c757d" font-size="14" font-family="Arial, sans-serif">No trend data available.</text>'
                          . '</svg>';
}

$status_pie_download_url = "lead_analysis_report.php?year={$selected_year}&view={$selected_view}";
if ($selected_view === 'weekly') {
    $status_pie_download_url .= '&week=' . intval($snapshot_week);
}
$status_pie_download_url .= '&download=status_pie';

$status_pie_svg_markup = '';
if ($total_status_count > 0) {
    $svg_width = 520;
    $svg_height = 320;
    $cx = 140;
    $cy = 160;
    $radius = 95;
    $start_angle = -90.0;
    $legend_x = 270;
    $legend_y = 45;
    $legend_step = 28;
    $slice_index = 0;
    $path_markup = '';
    $legend_markup = '';

    foreach ($view_status_counts as $status => $count) {
        $count = intval($count);
        if ($count <= 0) continue;
        $color = $status_palette[$slice_index % count($status_palette)];
        $slice_index++;
        $slice_angle = ($count / $total_status_count) * 360.0;
        $end_angle = $start_angle + $slice_angle;
        $start_rad = deg2rad($start_angle);
        $end_rad = deg2rad($end_angle);
        $x1 = $cx + ($radius * cos($start_rad));
        $y1 = $cy + ($radius * sin($start_rad));
        $x2 = $cx + ($radius * cos($end_rad));
        $y2 = $cy + ($radius * sin($end_rad));
        $large_arc = ($slice_angle > 180) ? 1 : 0;
        if ($slice_angle >= 359.99) {
            $path_markup .= '<circle cx="' . number_format($cx, 2, '.', '') . '" cy="' . number_format($cy, 2, '.', '') . '" r="' . number_format($radius, 2, '.', '') . '" fill="' . htmlspecialchars($color) . '"></circle>';
        } else {
            $path_markup .= '<path d="M ' . number_format($cx, 2, '.', '') . ' ' . number_format($cy, 2, '.', '') .
                            ' L ' . number_format($x1, 2, '.', '') . ' ' . number_format($y1, 2, '.', '') .
                            ' A ' . number_format($radius, 2, '.', '') . ' ' . number_format($radius, 2, '.', '') .
                            ' 0 ' . $large_arc . ' 1 ' . number_format($x2, 2, '.', '') . ' ' . number_format($y2, 2, '.', '') .
                            ' Z" fill="' . htmlspecialchars($color) . '"></path>';
        }
        $pct = round(($count / $total_status_count) * 100, 1);
        $legend_row_y = $legend_y + (($slice_index - 1) * $legend_step);
        $legend_markup .= '<rect x="' . $legend_x . '" y="' . $legend_row_y . '" width="12" height="12" fill="' . htmlspecialchars($color) . '"></rect>';
        $legend_markup .= '<text x="' . ($legend_x + 18) . '" y="' . ($legend_row_y + 10) . '" fill="#343a40" font-size="13" font-family="Arial, sans-serif">' .
                          htmlspecialchars(ucfirst($status)) . ': ' . $count . ' (' . $pct . '%)</text>';
        $start_angle = $end_angle;
    }

    $status_pie_svg_markup = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $svg_width . '" height="' . $svg_height . '" viewBox="0 0 ' . $svg_width . ' ' . $svg_height . '">'
                           . '<rect x="0" y="0" width="' . $svg_width . '" height="' . $svg_height . '" fill="#ffffff"></rect>'
                           . '<text x="20" y="28" fill="#212529" font-size="18" font-family="Arial, sans-serif">Lead Status Distribution (' . htmlspecialchars(ucfirst($selected_view)) . ')</text>'
                           . $path_markup
                           . '<circle cx="' . number_format($cx, 2, '.', '') . '" cy="' . number_format($cy, 2, '.', '') . '" r="50" fill="#ffffff"></circle>'
                           . '<text x="' . number_format($cx - 18, 2, '.', '') . '" y="' . number_format($cy + 6, 2, '.', '') . '" fill="#495057" font-size="16" font-family="Arial, sans-serif">' . $total_status_count . '</text>'
                           . $legend_markup
                           . '</svg>';
} else {
    $status_pie_svg_markup = '<svg xmlns="http://www.w3.org/2000/svg" width="520" height="320" viewBox="0 0 520 320">'
                           . '<rect x="0" y="0" width="520" height="320" fill="#ffffff"></rect>'
                           . '<text x="20" y="28" fill="#212529" font-size="18" font-family="Arial, sans-serif">Lead Status Distribution (' . htmlspecialchars(ucfirst($selected_view)) . ')</text>'
                           . '<text x="20" y="80" fill="#6c757d" font-size="14" font-family="Arial, sans-serif">No status data available.</text>'
                           . '</svg>';
}

if (isset($_GET['download']) && $_GET['download'] === 'trend_pie') {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="lead_trend_distribution_' . $selected_year . '_' . $selected_view . '.svg"');
    echo $trend_pie_svg_markup;
    exit();
}

if (isset($_GET['download']) && $_GET['download'] === 'status_pie') {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="lead_status_distribution_' . $selected_year . '_' . $selected_view . '.svg"');
    echo $status_pie_svg_markup;
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Analysis Report - <?php echo $selected_year; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        body { background-color: #f8f9fa; color: #1f2937; }
        #sidebar {
            position: fixed; top: 0; left: 0; height: 100%; width: 250px;
            background-color: #343a40; padding-top: 1rem; transition: all 0.3s; z-index: 1030;
        }
        #sidebar .nav-link { color: #adb5bd; padding: 0.75rem 1.5rem; }
        #sidebar .nav-link:hover, #sidebar .nav-link.active { color: #fff; background-color: #495057; }
        #sidebar .nav-link .fa { margin-right: 10px; }
        #content { margin-left: 250px; padding: 24px; }

        .section-gap { margin-bottom: 1.5rem; }
        .report-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 6px 20px rgba(16, 24, 40, 0.06);
        }
        .header-card { padding: 1.5rem; }
        .summary-card {
            min-height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .summary-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.65rem;
            font-size: 1rem;
        }
        .summary-number {
            font-size: 2rem;
            line-height: 1.1;
            font-weight: 700;
            margin-bottom: 0;
        }
        .summary-label { color: #6b7280; margin-bottom: 0.35rem; font-weight: 600; }

        .switcher-wrap {
            display: inline-flex;
            gap: 0.5rem;
            padding: 0.4rem;
            border-radius: 999px;
            background: #f1f5f9;
        }
        .switch-pill {
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .switch-pill.active {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.28);
        }
        .switch-pill.inactive {
            background: #fff;
            color: #334155;
            border-color: #dbe3ed;
        }

        .stat-card { min-height: 150px; }
        .stat-title { color: #6b7280; font-weight: 600; margin-bottom: 0.75rem; }

        .week-nav {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            background: #f8fafc;
        }
        .week-chip {
            border-radius: 999px;
            font-weight: 700;
            padding: 0.4rem 0.9rem;
            background: #f59e0b;
            color: #fff;
            border: 1px solid #f59e0b;
        }

        .card-chart {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .chart-card { min-height: 420px; }
        .chart-box { min-height: 340px; overflow: hidden; }
        .chart-scroll { max-height: none; overflow: hidden; padding-right: 0; }
        .chart-render { width: 100%; overflow: hidden; }
        .apexcharts-canvas, .apexcharts-svg { max-width: 100% !important; }
        .apexcharts-legend { padding-top: 8px !important; }
        .apexcharts-legend-marker { border-radius: 999px !important; }
        
        @media print {
            #sidebar, .no-print { display: none !important; }
            #content { margin-left: 0 !important; padding: 0 !important; }
            body { background-color: white !important; }
            .report-card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
            .container-fluid { max-width: 100% !important; }
        }

        @media (max-width: 991.98px) {
            #content { margin-left: 0; padding: 14px; }
            .header-actions { width: 100%; }
            .header-actions form { width: 100%; }
            .header-actions .form-select { width: 100%; }
            .chart-card { min-height: auto; }
            .chart-box { min-height: 260px; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header text-center py-4">
            <h4 class="text-white">CRM Pro</h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="superadmin_dashboard.php"><i class="fa fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="leads_advanced.php"><i class="fa fa-users"></i> Lead Management</a></li>
            <li class="nav-item"><a class="nav-link" href="analytics_dashboard.php"><i class="fa fa-chart-line"></i> Leads Analytics</a></li>
            <li class="nav-item"><a class="nav-link active" href="lead_analysis_report.php"><i class="fa fa-file-invoice"></i> Analysis Report</a></li>
            <li class="nav-item"><a class="nav-link" href="https://infinite-vision.co.in/task_final/Task_ManagerIV-main/html/backend/login.php"><i class="fa fa-tasks"></i> Task Manager</a></li>
        </ul>
    </nav>

    <!-- Content -->
    <div id="content">
        <div class="container-fluid">
            <div class="report-card header-card section-gap">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="mb-1"><i class="fas fa-chart-pie me-2 text-primary"></i>Lead Analysis Report</h2>
                        <p class="text-muted mb-0">Comprehensive analysis for Year <strong><?php echo $selected_year; ?></strong></p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 no-print header-actions">
                    <form method="GET" class="d-flex align-items-center">
                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($selected_view); ?>">
                        <?php if ($selected_view === 'weekly'): ?>
                            <input type="hidden" name="week" value="<?php echo intval($snapshot_week); ?>">
                        <?php endif; ?>
                        <select name="year" class="form-select me-2" onchange="this.form.submit()">
                            <?php foreach($years as $y): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-download me-2"></i>Download Report</button>
                    <a href="superadmin_dashboard.php" class="btn btn-outline-secondary">Back</a>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 section-gap">
                <div class="col-lg-4 col-md-6">
                    <div class="report-card summary-card">
                        <div class="summary-icon bg-primary-subtle text-primary"><i class="fas fa-users"></i></div>
                        <div class="summary-label">Total Leads (<?php echo $selected_year; ?>)</div>
                        <h2 class="summary-number text-primary"><?php echo array_sum($monthly_counts); ?></h2>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="report-card summary-card">
                        <div class="summary-icon bg-success-subtle text-success"><i class="fas fa-check-circle"></i></div>
                        <div class="summary-label">Converted (<?php echo $selected_year; ?>)</div>
                        <h2 class="summary-number text-success"><?php echo $status_counts['converted'] ?? 0; ?></h2>
                    </div>
                </div>
                <div class="col-lg-4 col-md-12">
                    <div class="report-card summary-card">
                        <div class="summary-icon bg-info-subtle text-info"><i class="fas fa-chart-line"></i></div>
                        <div class="summary-label">Avg Leads / Month</div>
                        <h2 class="summary-number text-info"><?php echo round(array_sum($monthly_counts) / 12, 1); ?></h2>
                    </div>
                </div>
            </div>

            <!-- Weekly / Monthly / Yearly Stats -->
            <div class="report-card section-gap no-print">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted fw-semibold">Time Slot View</span>
                    <div class="switcher-wrap">
                    <a href="<?php echo htmlspecialchars($weekly_view_with_current_week); ?>"
                       class="btn btn-sm switch-pill <?php echo $selected_view === 'weekly' ? 'active' : 'inactive'; ?>">
                        Weekly
                    </a>
                    <a href="lead_analysis_report.php?year=<?php echo $selected_year; ?>&view=monthly"
                       class="btn btn-sm switch-pill <?php echo $selected_view === 'monthly' ? 'active' : 'inactive'; ?>">
                        Monthly
                    </a>
                    <a href="lead_analysis_report.php?year=<?php echo $selected_year; ?>&view=yearly"
                       class="btn btn-sm switch-pill <?php echo $selected_view === 'yearly' ? 'active' : 'inactive'; ?>">
                        Yearly
                    </a>
                    </div>
                </div>
            </div>

            <div class="row section-gap">
                <?php if ($selected_view === 'weekly'): ?>
                    <div class="col-12">
                        <div class="report-card stat-card border-start border-4 border-warning">
                            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3 no-print">
                                <div class="text-muted small fw-semibold">Week Navigator</div>
                                <div class="week-nav">
                                    <?php if ($weekly_prev_week !== null): ?>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="<?php echo htmlspecialchars($weekly_view_base . '&week=' . $weekly_prev_week); ?>">
                                            <i class="fas fa-chevron-left me-1"></i>Previous
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled><i class="fas fa-chevron-left me-1"></i>Previous</button>
                                    <?php endif; ?>

                                    <span class="week-chip">Week <?php echo $snapshot_week; ?></span>

                                    <?php if ($weekly_next_week !== null): ?>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="<?php echo htmlspecialchars($weekly_view_base . '&week=' . $weekly_next_week); ?>">
                                            Next<i class="fas fa-chevron-right ms-1"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled>Next<i class="fas fa-chevron-right ms-1"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h6 class="stat-title">Weekly Stats (Week <?php echo $snapshot_week; ?>)</h6>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Total Leads</span>
                                <strong><?php echo $weekly_total; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Converted</span>
                                <strong class="text-success"><?php echo $weekly_converted; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Conversion Rate</span>
                                <strong><?php echo $weekly_rate; ?>%</strong>
                            </div>
                        </div>
                    </div>
                <?php elseif ($selected_view === 'monthly'): ?>
                    <div class="col-12">
                        <div class="report-card stat-card border-start border-4 border-info">
                            <h6 class="stat-title">Monthly Stats (<?php echo $snapshot_month_name; ?> <?php echo $selected_year; ?>)</h6>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Total Leads</span>
                                <strong><?php echo $monthly_total; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Converted</span>
                                <strong class="text-success"><?php echo $monthly_converted_snapshot; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Conversion Rate</span>
                                <strong><?php echo $monthly_rate; ?>%</strong>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <div class="report-card stat-card border-start border-4 border-primary">
                            <h6 class="stat-title">Yearly Stats (<?php echo $selected_year; ?>)</h6>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Total Leads</span>
                                <strong><?php echo $yearly_total; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Converted</span>
                                <strong class="text-success"><?php echo $yearly_converted; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Conversion Rate</span>
                                <strong><?php echo $yearly_rate; ?>%</strong>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Dynamic Graphs By Time Slot -->
            <div class="row g-3 section-gap">
                <div class="col-xl-7 col-lg-7">
                    <div class="report-card card-chart chart-card">
                        <h5 class="card-title mb-3"><?php echo ucfirst($selected_view); ?> Lead Trend</h5>
                        <div class="chart-box chart-scroll">
                            <div class="chart-render">
                                <div id="trendDonutChart"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5 col-lg-5">
                    <div class="report-card card-chart chart-card">
                        <h5 class="card-title mb-3">Lead Status Distribution</h5>
                        <div class="chart-box chart-scroll">
                            <div class="chart-render">
                                <div id="statusDonutChart"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center text-muted mt-4 small">
                Report Generated on <?php echo date('F j, Y, g:i a'); ?> | CRM Pro
            </div>
        </div>
    </div>

    <script>
        (function () {
            const trendLabels = <?php echo json_encode($trend_chart_labels); ?> || [];
            const trendSeries = <?php echo json_encode($trend_chart_series); ?> || [];
            const statusLabelsRaw = <?php echo json_encode($status_chart_labels); ?> || [];
            const statusSeries = <?php echo json_encode($status_chart_series); ?> || [];

            const statusNameMap = {
                new: 'New Leads',
                converted: 'Converted',
                lost: 'Lost',
                hot: 'Hot Leads'
            };

            const normalizeLabel = (label) => {
                const key = String(label || '').trim().toLowerCase();
                return statusNameMap[key] || label;
            };

            const statusLabels = statusLabelsRaw.map(normalizeLabel);

            const trendPalette = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#06B6D4', '#8B5CF6', '#F97316', '#84CC16', '#0EA5E9', '#EC4899', '#14B8A6', '#A855F7'];
            const statusColorMap = {
                'New Leads': '#4F46E5',
                'Converted': '#10B981',
                'Lost': '#EF4444',
                'Hot Leads': '#F59E0B'
            };
            const statusColors = statusLabels.map((label, idx) => statusColorMap[label] || trendPalette[idx % trendPalette.length]);

            const legendFormatter = function (seriesName, opts) {
                const value = Number(opts.w.globals.series[opts.seriesIndex] || 0);
                const total = (opts.w.globals.seriesTotals || []).reduce((a, b) => a + Number(b || 0), 0);
                const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                return seriesName + ' — ' + value + ' Leads (' + pct + '%)';
            };

            const buildDonutOptions = (labels, series, colors) => ({
                chart: {
                    type: 'donut',
                    height: 320,
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 650
                    },
                    toolbar: { show: false }
                },
                series: series,
                labels: labels,
                colors: colors,
                legend: {
                    position: 'bottom',
                    fontSize: '13px',
                    formatter: legendFormatter
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                name: { show: true },
                                value: {
                                    show: true,
                                    formatter: function (val) { return parseInt(val, 10) + ''; }
                                },
                                total: {
                                    show: true,
                                    label: 'Total',
                                    formatter: function (w) {
                                        const t = (w.globals.seriesTotals || []).reduce((a, b) => a + Number(b || 0), 0);
                                        return String(t);
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) { return val.toFixed(1) + '%'; }
                },
                stroke: { width: 0 },
                responsive: [
                    {
                        breakpoint: 992,
                        options: {
                            chart: { height: 300 },
                            legend: { position: 'bottom' }
                        }
                    },
                    {
                        breakpoint: 576,
                        options: {
                            chart: { height: 280 },
                            legend: { fontSize: '12px' }
                        }
                    }
                ]
            });

            const safeRender = (selector, options, fallbackText) => {
                const node = document.querySelector(selector);
                if (!node) return;
                if (!Array.isArray(options.series) || options.series.length === 0 || options.series.every(v => Number(v) <= 0)) {
                    node.innerHTML = '<p class="text-muted mb-0">' + fallbackText + '</p>';
                    return;
                }
                const chart = new ApexCharts(node, options);
                chart.render();
            };

            safeRender('#trendDonutChart', buildDonutOptions(trendLabels, trendSeries, trendPalette), 'No trend data available.');
            safeRender('#statusDonutChart', buildDonutOptions(statusLabels, statusSeries, statusColors), 'No status data available.');
        })();
    </script>
</body>
</html>
