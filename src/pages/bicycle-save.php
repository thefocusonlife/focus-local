<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = '/index/44';
    $_SESSION['flash_failure'] = 'You must be logged in to submit a ride.';
    redirect('login');
    exit();
}
require_once APP_ROOT . '/src/security/guard.php';
include APP_ROOT . '/src/pages/menu-path.php';
$websiteId = (int) ($_POST['website_id'] ?? 44);
if ($websiteId !== 44) {
    $websiteId = 44;
}

$memberId = $viewerId;

$rideDate = trim((string) ($_POST['ride_date'] ?? ''));
$title = trim((string) ($_POST['title'] ?? ''));
$rideType = trim((string) ($_POST['ride_type'] ?? ''));
$startLocation = trim((string) ($_POST['start_location'] ?? ''));
$distanceMiles = trim((string) ($_POST['distance_miles'] ?? ''));
$elapsedMinutes = trim((string) ($_POST['elapsed_minutes'] ?? ''));
$elevationGainFt = trim((string) ($_POST['elevation_gain_ft'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($rideDate === '' || $rideType === '') {
    $_SESSION['flash_failure'] = 'Ride date and ride type are required.';
    header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44');
    exit();
}

/**
 * Distance helper
 */
function haversineMiles(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthRadiusMiles = 3958.7613;

    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle =
        2 *
        asin(
            sqrt(
                pow(sin($latDelta / 2), 2) +
                    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2),
            ),
        );

    return $earthRadiusMiles * $angle;
}

/**
 * Parse GPX file and derive ride metrics.
 */
function parseGpxRide(string $filePath): array
{
    libxml_use_internal_errors(true);

    $xml = simplexml_load_file($filePath);
    if ($xml === false) {
        throw new RuntimeException('Unable to read GPX file.');
    }

    $points = [];

    if (!isset($xml->trk)) {
        throw new RuntimeException('GPX file does not contain track data.');
    }

    foreach ($xml->trk as $track) {
        foreach ($track->trkseg as $segment) {
            foreach ($segment->trkpt as $point) {
                $lat = isset($point['lat']) ? (float) $point['lat'] : null;
                $lng = isset($point['lon']) ? (float) $point['lon'] : null;

                if ($lat === null || $lng === null) {
                    continue;
                }

                $ele = isset($point->ele) ? (float) $point->ele : null;
                $time = isset($point->time) ? strtotime((string) $point->time) : null;

                $power = null;

                // Look for power tags anywhere inside extensions
                if (isset($point->extensions)) {
                    $extXml = $point->extensions->asXML();
                    if ($extXml) {
                        if (
                            preg_match(
                                '/<(?:[^:>]+:)?(?:power|PowerInWatts)>([^<]+)</i',
                                $extXml,
                                $m,
                            )
                        ) {
                            if (is_numeric($m[1])) {
                                $power = (float) $m[1];
                            }
                        }
                    }
                }

                $points[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'ele' => $ele,
                    'time' => $time,
                    'power' => $power,
                ];
            }
        }
    }

    if (count($points) < 2) {
        throw new RuntimeException('GPX file does not contain enough track points.');
    }

    $distanceMilesValue = 0.0;
    $elevationGainFeetValue = 0.0;
    $powerSamples = [];
    $pointCount = count($points);

    for ($i = 1; $i < $pointCount; $i++) {
        $prev = $points[$i - 1];
        $curr = $points[$i];

        $distanceMilesValue += haversineMiles(
            $prev['lat'],
            $prev['lng'],
            $curr['lat'],
            $curr['lng'],
        );

        if ($curr['power'] !== null && $curr['power'] > 0) {
            $powerSamples[] = $curr['power'];
        }
    }

    // Build a smoothed elevation series using a 3-point moving average
    $smoothedElevations = [];

    for ($i = 0; $i < $pointCount; $i++) {
        $samples = [];

        for ($j = max(0, $i - 1); $j <= min($pointCount - 1, $i + 1); $j++) {
            if ($points[$j]['ele'] !== null) {
                $samples[] = $points[$j]['ele'];
            }
        }

        $smoothedElevations[$i] = !empty($samples) ? array_sum($samples) / count($samples) : null;
    }

    // Sum only positive elevation gain from the smoothed series
    for ($i = 1; $i < $pointCount; $i++) {
        $prevEle = $smoothedElevations[$i - 1];
        $currEle = $smoothedElevations[$i];

        if ($prevEle !== null && $currEle !== null) {
            $diffFeet = ($currEle - $prevEle) * 3.28084;

            if ($diffFeet > 0) {
                $elevationGainFeetValue += $diffFeet;
            }
        }
    }

    $firstPoint = $points[0];
    $lastPoint = $points[count($points) - 1];

    $elapsedMinutesValue = null;
    if (
        !empty($firstPoint['time']) &&
        !empty($lastPoint['time']) &&
        $lastPoint['time'] > $firstPoint['time']
    ) {
        $elapsedMinutesValue = (int) round(($lastPoint['time'] - $firstPoint['time']) / 60);
    }

    $avgPowerWattsValue = null;
    if (!empty($powerSamples)) {
        $avgPowerWattsValue = (int) round(array_sum($powerSamples) / count($powerSamples));
    }

    return [
        'distance_miles' => round($distanceMilesValue, 2),
        'elapsed_minutes' => $elapsedMinutesValue,
        'elevation_gain_ft' => (int) round($elevationGainFeetValue),
        'avg_power_watts' => $avgPowerWattsValue,
        'np_power_watts' => null,
        'start_time' => !empty($firstPoint['time'])
            ? date('Y-m-d H:i:s', $firstPoint['time'])
            : null,
        'start_lat' => $firstPoint['lat'],
        'start_lng' => $firstPoint['lng'],
        'end_lat' => $lastPoint['lat'],
        'end_lng' => $lastPoint['lng'],
    ];
}

function parseTcxRide(string $filePath): array
{
    libxml_use_internal_errors(true);

    $xml = simplexml_load_file($filePath);
    if ($xml === false) {
        throw new RuntimeException('Unable to read TCX file.');
    }

    $namespaces = $xml->getNamespaces(true);
    if (isset($namespaces[''])) {
        $xml->registerXPathNamespace('tcx', $namespaces['']);
    } else {
        $xml->registerXPathNamespace(
            'tcx',
            'http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2',
        );
    }

    $points = [];
    $lapDistanceMeters = 0.0;
    $lapCalories = null;

    $trackpoints = $xml->xpath('//tcx:Trackpoint');
    if ($trackpoints === false || empty($trackpoints)) {
        throw new RuntimeException('TCX file does not contain trackpoints.');
    }

    foreach ($trackpoints as $point) {
        $tpXml = $point->asXML();

        $time = null;
        if ($tpXml && preg_match('/<Time>([^<]+)<\/Time>/i', $tpXml, $m)) {
            $time = strtotime($m[1]);
        }

        $lat = null;
        if ($tpXml && preg_match('/<LatitudeDegrees>([^<]+)<\/LatitudeDegrees>/i', $tpXml, $m)) {
            $lat = (float) $m[1];
        }

        $lng = null;
        if ($tpXml && preg_match('/<LongitudeDegrees>([^<]+)<\/LongitudeDegrees>/i', $tpXml, $m)) {
            $lng = (float) $m[1];
        }

        $ele = null;
        if ($tpXml && preg_match('/<AltitudeMeters>([^<]+)<\/AltitudeMeters>/i', $tpXml, $m)) {
            $ele = (float) $m[1];
        }

        $power = null;
        if (
            $tpXml &&
            preg_match('/<(?:[^:>]+:)?Watts>([^<]+)<\/(?:[^:>]+:)?Watts>/i', $tpXml, $m)
        ) {
            if (is_numeric($m[1])) {
                $power = (float) $m[1];
            }
        }

        $points[] = [
            'lat' => $lat,
            'lng' => $lng,
            'ele' => $ele,
            'time' => $time,
            'power' => $power,
        ];
    }

    $laps = $xml->xpath('//tcx:Lap');
    if ($laps !== false && !empty($laps)) {
        foreach ($laps as $lap) {
            if (isset($lap->DistanceMeters) && is_numeric((string) $lap->DistanceMeters)) {
                $lapDistanceMeters += (float) $lap->DistanceMeters;
            }
        }
    }

    $distanceMilesValue = 0.0;
    $elevationGainFeetValue = 0.0;
    $powerSamples = [];
    $pointCount = count($points);

    $usableGeoPoints = array_values(
        array_filter($points, static function ($p) {
            return $p['lat'] !== null && $p['lng'] !== null;
        }),
    );

    if ($lapDistanceMeters > 0) {
        $distanceMilesValue = $lapDistanceMeters * 0.000621371;
    } elseif (count($usableGeoPoints) >= 2) {
        for ($i = 1, $count = count($usableGeoPoints); $i < $count; $i++) {
            $prev = $usableGeoPoints[$i - 1];
            $curr = $usableGeoPoints[$i];
            $distanceMilesValue += haversineMiles(
                $prev['lat'],
                $prev['lng'],
                $curr['lat'],
                $curr['lng'],
            );
        }
    }

    for ($i = 1; $i < $pointCount; $i++) {
        $curr = $points[$i];

        if ($curr['power'] !== null && $curr['power'] > 0) {
            $powerSamples[] = $curr['power'];
        }
    }

    // Build a smoothed elevation series using a 3-point moving average
    $smoothedElevations = [];

    for ($i = 0; $i < $pointCount; $i++) {
        $samples = [];

        for ($j = max(0, $i - 1); $j <= min($pointCount - 1, $i + 1); $j++) {
            if ($points[$j]['ele'] !== null) {
                $samples[] = $points[$j]['ele'];
            }
        }

        $smoothedElevations[$i] = !empty($samples) ? array_sum($samples) / count($samples) : null;
    }

    // Sum only positive elevation gain from the smoothed series
    for ($i = 1; $i < $pointCount; $i++) {
        $prevEle = $smoothedElevations[$i - 1];
        $currEle = $smoothedElevations[$i];

        if ($prevEle !== null && $currEle !== null) {
            $diffFeet = ($currEle - $prevEle) * 3.28084;

            if ($diffFeet > 0) {
                $elevationGainFeetValue += $diffFeet;
            }
        }
    }

    $firstPoint = $points[0];
    $lastPoint = $points[count($points) - 1];

    $elapsedMinutesValue = null;
    if (
        !empty($firstPoint['time']) &&
        !empty($lastPoint['time']) &&
        $lastPoint['time'] > $firstPoint['time']
    ) {
        $elapsedMinutesValue = (int) round(($lastPoint['time'] - $firstPoint['time']) / 60);
    }

    $avgPowerWattsValue = null;
    if (!empty($powerSamples)) {
        $avgPowerWattsValue = (int) round(array_sum($powerSamples) / count($powerSamples));
    }

    return [
        'distance_miles' => round($distanceMilesValue, 2),
        'elapsed_minutes' => $elapsedMinutesValue,
        'elevation_gain_ft' => (int) round($elevationGainFeetValue),
        'avg_power_watts' => $avgPowerWattsValue,
        'np_power_watts' => null,
        'start_time' => !empty($firstPoint['time'])
            ? date('Y-m-d H:i:s', $firstPoint['time'])
            : null,
        'start_lat' => $firstPoint['lat'],
        'start_lng' => $firstPoint['lng'],
        'end_lat' => $lastPoint['lat'],
        'end_lng' => $lastPoint['lng'],
    ];
}

$distanceMilesValue = $distanceMiles !== '' ? (float) $distanceMiles : null;
$elapsedMinutesValue = $elapsedMinutes !== '' ? (int) $elapsedMinutes : null;
$elevationGainFtValue = $elevationGainFt !== '' ? (int) $elevationGainFt : null;

$avgPowerWattsValue = null;
$npPowerWattsValue = null;
$startTimeValue = null;
$startLatValue = null;
$startLngValue = null;
$endLatValue = null;
$endLngValue = null;
$uploadFileValue = null;
$uploadFileUploadedValue = 0;

/**
 * Optional ride file upload (GPX or TCX)
 */
if (!empty($_FILES['gpx_file']['name'])) {
    if (!isset($_FILES['gpx_file']['error']) || $_FILES['gpx_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_failure'] = 'There was a problem uploading the ride file.';
        header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44');
        exit();
    }

    $extension = strtolower(pathinfo($_FILES['gpx_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['gpx', 'tcx'], true)) {
        $_SESSION['flash_failure'] = 'Only GPX and TCX files are allowed.';
        header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44');
        exit();
    }

    if ((int) $_FILES['gpx_file']['size'] > 5 * 1024 * 1024) {
        $_SESSION['flash_failure'] = 'The ride file is too large. Max size is 5 MB.';
        header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44');
        exit();
    }

    $uploadDirectory = __DIR__ . '/../../../public/uploads/gpx/';
    if (
        !is_dir($uploadDirectory) &&
        !mkdir($uploadDirectory, 0755, true) &&
        !is_dir($uploadDirectory)
    ) {
        $_SESSION['flash_failure'] = 'Upload folder could not be created.';
        header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44');
        exit();
    }

    $safeFileName = 'ride_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destinationPath = $uploadDirectory . $safeFileName;

    if (!move_uploaded_file($_FILES['gpx_file']['tmp_name'], $destinationPath)) {
        $_SESSION['flash_failure'] = 'GPX file could not be saved.';
        header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44');
        exit();
    }

    try {
        if ($extension === 'gpx') {
            $parsed = parseGpxRide($destinationPath);
        } elseif ($extension === 'tcx') {
            $parsed = parseTcxRide($destinationPath);
        } else {
            throw new RuntimeException('Unsupported file type.');
        }

        $gpxFileValue = 'uploads/gpx/' . $safeFileName;
        $gpxUploadedValue = 1;

        $distanceMilesValue = $parsed['distance_miles'] ?? $distanceMilesValue;
        $elapsedMinutesValue = $parsed['elapsed_minutes'] ?? $elapsedMinutesValue;
        $elevationGainFtValue = $parsed['elevation_gain_ft'] ?? $elevationGainFtValue;
        $avgPowerWattsValue = $parsed['avg_power_watts'] ?? null;
        $npPowerWattsValue = $parsed['np_power_watts'] ?? null;
        $startTimeValue = $parsed['start_time'] ?? null;
        $startLatValue = $parsed['start_lat'] ?? null;
        $startLngValue = $parsed['start_lng'] ?? null;
        $endLatValue = $parsed['end_lat'] ?? null;
        $endLngValue = $parsed['end_lng'] ?? null;
    } catch (Throwable $e) {
        @unlink($destinationPath);
        $_SESSION['flash_failure'] = 'The ride file could not be parsed: ' . $e->getMessage();
        header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44');
        exit();
    }
}

$avgSpeed = null;
if ($distanceMilesValue !== null && $elapsedMinutesValue !== null && $elapsedMinutesValue > 0) {
    $avgSpeed = round($distanceMilesValue / ($elapsedMinutesValue / 60), 2);
}

$sql = "
    INSERT INTO ride (
        website_id,
        member_id,
        ride_date,
        start_time,
        title,
        ride_type,
        start_location,
        distance_miles,
        elapsed_minutes,
        elevation_gain_ft,
        avg_speed_mph,
        avg_power_watts,
        np_power_watts,
        notes,
        gpx_file,
        gpx_uploaded,
        start_lat,
        start_lng,
        end_lat,
        end_lng,
        status
    ) VALUES (
        :website_id,
        :member_id,
        :ride_date,
        :start_time,
        :title,
        :ride_type,
        :start_location,
        :distance_miles,
        :elapsed_minutes,
        :elevation_gain_ft,
        :avg_speed_mph,
        :avg_power_watts,
        :np_power_watts,
        :notes,
        :gpx_file,
        :gpx_uploaded,
        :start_lat,
        :start_lng,
        :end_lat,
        :end_lng,
        :status
    )
";

$cms->getDb()->runSql($sql, [
    'website_id' => $websiteId,
    'member_id' => $memberId,
    'ride_date' => $rideDate,
    'start_time' => $startTimeValue,
    'title' => $title !== '' ? $title : null,
    'ride_type' => $rideType,
    'start_location' => $startLocation !== '' ? $startLocation : null,
    'distance_miles' => $distanceMilesValue,
    'elapsed_minutes' => $elapsedMinutesValue,
    'elevation_gain_ft' => $elevationGainFtValue,
    'avg_speed_mph' => $avgSpeed,
    'avg_power_watts' => $avgPowerWattsValue,
    'np_power_watts' => $npPowerWattsValue,
    'notes' => $notes !== '' ? $notes : null,
    'gpx_file' => $gpxFileValue,
    'gpx_uploaded' => $gpxUploadedValue,
    'start_lat' => $startLatValue,
    'start_lng' => $startLngValue,
    'end_lat' => $endLatValue,
    'end_lng' => $endLngValue,
    'status' => 'published',
]);

$_SESSION['flash_success'] = 'Ride submitted successfully.';
header('Location: ' . DOC_ROOT . 'index/44');
exit();
