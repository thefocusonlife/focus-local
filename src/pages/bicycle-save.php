<?php
declare(strict_types=1);
$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower((string) ($_POST['location'] ?? ($_GET['location'] ?? 'redmond')));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}
require_once APP_ROOT . '/vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44&location=' . urlencode($location));
    exit();
}
function logstep(string $message, array $context = []): void
{
    if (!defined('TFOL_DEBUG') || !TFOL_DEBUG) {
        return;
    }

    $line = date('c') . ' ' . $message;

    if (!empty($context)) {
        $line .= ' ' . json_encode($context);
    }

    file_put_contents('/tmp/tfol-debug.log', $line . "\n", FILE_APPEND);
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

function reverseGeocodeNominatim(float $lat, float $lng): ?string
{
    $url =
        'https://nominatim.openstreetmap.org/reverse?format=jsonv2' .
        '&lat=' .
        urlencode((string) $lat) .
        '&lon=' .
        urlencode((string) $lng) .
        '&zoom=14' .
        '&addressdetails=1';

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['User-Agent: FocusOnLife/1.0'],
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log('Geocode curl error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    $data = json_decode($response, true);
    if (!is_array($data)) {
        error_log('Invalid JSON from geocode: ' . $response);
        return null;
    }

    $address = $data['address'] ?? [];

    $road =
        $address['road'] ??
        ($address['footway'] ??
            ($address['path'] ??
                ($address['cycleway'] ?? ($address['track'] ?? ($address['pedestrian'] ?? null)))));

    $county = $address['county'] ?? null;

    $city =
        $address['city'] ??
        ($address['town'] ?? ($address['village'] ?? ($address['hamlet'] ?? null)));

    $state = $address['state'] ?? null;

    if ($road && $city) {
        return $road . ', ' . $city;
    }

    if ($road && $county) {
        return $road . ', ' . $county;
    }

    if ($city && $county) {
        return $city . ', ' . $county;
    }

    if ($county && $state) {
        return $county . ', ' . $state;
    }

    if (!empty($data['display_name'])) {
        $parts = array_map('trim', explode(',', $data['display_name']));
        if (!empty($parts[0]) && $county) {
            return $parts[0] . ', ' . $county;
        }

        return $data['display_name'];
    }

    return null;
}

$websiteId = (int) ($_POST['website_id'] ?? ($_GET['website'] ?? ($_SESSION['website'] ?? 1)));
if ($websiteId !== 44) {
    $websiteId = 44;
}

$memberId = $viewerId;

$rideDate = trim((string) ($_POST['ride_date'] ?? ''));
$title = trim((string) ($_POST['title'] ?? ''));
$rideType = trim((string) ($_POST['ride_type'] ?? ''));
$startLocation = trim((string) ($_POST['start_location'] ?? ''));
$manualStartTime = trim((string) ($_POST['manual_start_time'] ?? ''));
$distanceMiles = trim((string) ($_POST['distance_miles'] ?? ''));
$elapsedMinutes = trim((string) ($_POST['elapsed_minutes'] ?? ''));
$elevationGainFt = trim((string) ($_POST['elevation_gain_ft'] ?? ''));
$avgSpeedMph = trim((string) ($_POST['avg_speed_mph'] ?? ''));
$avgPowerWatts = trim((string) ($_POST['avg_power_watts'] ?? ''));
$avgHeartRate = trim((string) ($_POST['avg_heart_rate'] ?? ''));
$maxSpeedMphValue =
    isset($_POST['max_speed_mph']) && $_POST['max_speed_mph'] !== ''
        ? (float) $_POST['max_speed_mph']
        : null;

$maxPowerWattsValue =
    isset($_POST['max_power_watts']) && $_POST['max_power_watts'] !== ''
        ? (int) $_POST['max_power_watts']
        : null;

$maxHeartRateValue =
    isset($_POST['max_heart_rate']) && $_POST['max_heart_rate'] !== ''
        ? (int) $_POST['max_heart_rate']
        : null;

$notes = trim((string) ($_POST['notes'] ?? ''));

if ($rideDate === '' || $rideType === '') {
    $_SESSION['flash_failure'] = 'Ride date and ride type are required.';
    header('Location: ' . DOC_ROOT . 'bicycle-submit?website=44&location=' . urlencode($location));
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

function firstFitValue(array $source, string $field)
{
    if (!isset($source[$field])) {
        return null;
    }

    if (is_array($source[$field])) {
        $values = array_values(
            array_filter($source[$field], static function ($value) {
                return $value !== null && $value !== '';
            }),
        );

        return $values[0] ?? null;
    }

    return $source[$field];
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
    $samplePoints = [];
    $heartRateSamples = [];

    if (!isset($xml->trk)) {
        throw new RuntimeException('GPX file does not contain track data.');
    }

    foreach ($xml->trk as $track) {
        foreach ($track->trkseg as $segment) {
            foreach ($segment->trkpt as $point) {
                $lat = isset($point['lat']) ? (float) $point['lat'] : null;
                $lng = isset($point['lon']) ? (float) $point['lon'] : null;

                if ($lat !== null && $lng !== null && count($samplePoints) < 5) {
                    $samplePoints[] = ['lat' => $lat, 'lng' => $lng];
                }

                if ($lat === null || $lng === null) {
                    continue;
                }

                $ele = isset($point->ele) ? (float) $point->ele : null;
                $time = isset($point->time) ? strtotime((string) $point->time) : null;

                $power = null;
                $heartRate = null;

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

                        if (
                            preg_match('/<(?:[^:>]+:)?hr>([^<]+)<\/(?:[^:>]+:)?hr>/i', $extXml, $m)
                        ) {
                            if (is_numeric($m[1])) {
                                $heartRate = (int) $m[1];
                            }
                        }
                    }
                }

                if ($heartRate !== null && $heartRate > 0) {
                    $heartRateSamples[] = $heartRate;
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
    $speedSamples = [];
    $pointCount = count($points);

    for ($i = 1; $i < $pointCount; $i++) {
        $prev = $points[$i - 1];
        $curr = $points[$i];

        $segmentDistanceMiles = haversineMiles(
            $prev['lat'],
            $prev['lng'],
            $curr['lat'],
            $curr['lng'],
        );

        $distanceMilesValue += $segmentDistanceMiles;

        if (
            !empty($prev['time']) &&
            !empty($curr['time']) &&
            $curr['time'] > $prev['time'] &&
            $segmentDistanceMiles > 0
        ) {
            $seconds = $curr['time'] - $prev['time'];
            $segmentSpeedMph = $segmentDistanceMiles / ($seconds / 3600);

            if ($segmentSpeedMph > 0 && $segmentSpeedMph < 45) {
                $speedSamples[] = $segmentSpeedMph;
            }
        }

        if ($curr['power'] !== null && $curr['power'] > 0) {
            $powerSamples[] = $curr['power'];
        }
    }

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

    $maxPowerWattsValue = null;
    if (!empty($powerSamples)) {
        $maxPowerWattsValue = (int) round(max($powerSamples));
    }

    $avgHeartRateValue = null;
    if (!empty($heartRateSamples)) {
        $avgHeartRateValue = (int) round(array_sum($heartRateSamples) / count($heartRateSamples));
    }

    $maxHeartRateValue = null;
    if (!empty($heartRateSamples)) {
        $maxHeartRateValue = (int) max($heartRateSamples);
    }

    $maxSpeedMphValue = null;
    if (!empty($speedSamples)) {
        $maxSpeedMphValue = round(max($speedSamples), 2);
    }

    return [
        'distance_miles' => round($distanceMilesValue, 2),
        'elapsed_minutes' => $elapsedMinutesValue,
        'elevation_gain_ft' => (int) round($elevationGainFeetValue),
        'avg_power_watts' => $avgPowerWattsValue,
        'max_power_watts' => $maxPowerWattsValue,
        'avg_heart_rate' => $avgHeartRateValue,
        'max_heart_rate' => $maxHeartRateValue,
        'max_speed_mph' => $maxSpeedMphValue,
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
    $avgHeartRateValue = null;
    if (!empty($heartRateSamples)) {
        $avgHeartRateValue = (int) round(array_sum($heartRateSamples) / count($heartRateSamples));
    }
    return [
        'distance_miles' => round($distanceMilesValue, 2),
        'elapsed_minutes' => $elapsedMinutesValue,
        'elevation_gain_ft' => (int) round($elevationGainFeetValue),
        'avg_power_watts' => $avgPowerWattsValue,
        'avg_heart_rate' => $avgHeartRateValue,
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

function parseFitRide(string $filePath): array
{
    if (!class_exists('\\adriangibbons\\phpFITFileAnalysis')) {
        throw new RuntimeException(
            'FIT parser is not installed. Run: composer require adriangibbons/php-fit-file-analysis',
        );
    }

    $options = [
        'units' => 'statute',
        'overwrite_with_dev_data' => true,
    ];

    $pFFA = new \adriangibbons\phpFITFileAnalysis($filePath, $options);
    $messages = $pFFA->data_mesgs ?? [];

    $session = $messages['session'] ?? [];
    $record = $messages['record'] ?? [];

    error_log('[FIT session keys] ' . implode(', ', array_keys($session)));
    error_log('[FIT record keys] ' . implode(', ', array_keys($record)));

    error_log('[FIT session raw] ' . print_r($session, true));

    if (empty($session) && empty($record)) {
        throw new RuntimeException('FIT file did not contain usable activity data.');
    }

    $distanceMilesValue = null;
    $elapsedMinutesValue = null;
    $elevationGainFeetValue = null;
    $avgPowerWattsValue = null;
    $maxPowerWattsValue = null;
    $avgHeartRateValue = null;
    $maxHeartRateValue = null;
    $maxSpeedMphValue = null;
    $npPowerWattsValue = null;
    $startTimeValue = null;
    $rideDate = trim((string) ($_POST['ride_date'] ?? ''));
    $manualStartTime = trim((string) ($_POST['manual_start_time'] ?? ''));
    if ($rideDate !== '' && $manualStartTime !== '') {
        $startTimeValue = $rideDate . ' ' . $manualStartTime . ':00';
    }
    $startLatValue = null;
    $startLngValue = null;
    $endLatValue = null;
    $endLngValue = null;

    /*
     * Prefer FIT session summary values.
     * With units=statute:
     * - distance is miles
     * - speed is mph
     * - altitude/elevation is feet
     */
    $totalDistance = firstFitValue($session, 'total_distance');
    if ($totalDistance !== null && is_numeric($totalDistance)) {
        $distanceMilesValue = round((float) $totalDistance, 2);
    }

    $totalTimerTime = firstFitValue($session, 'total_timer_time');
    if ($totalTimerTime !== null && is_numeric($totalTimerTime)) {
        $elapsedMinutesValue = (int) round(((float) $totalTimerTime) / 60);
    }

    $totalAscent = firstFitValue($session, 'total_ascent');
    if ($totalAscent !== null && is_numeric($totalAscent)) {
        $elevationGainFeetValue = (int) round((float) $totalAscent);
    }

    $avgPower = firstFitValue($session, 'avg_power');
    if ($avgPower !== null && is_numeric($avgPower)) {
        $avgPowerWattsValue = (int) round((float) $avgPower);
    }

    $maxPower = firstFitValue($session, 'max_power');
    if ($maxPower !== null && is_numeric($maxPower)) {
        $maxPowerWattsValue = (int) round((float) $maxPower);
    }

    $avgHeartRate = firstFitValue($session, 'avg_heart_rate');
    if ($avgHeartRate !== null && is_numeric($avgHeartRate)) {
        $avgHeartRateValue = (int) round((float) $avgHeartRate);
    }

    $maxHeartRate = firstFitValue($session, 'max_heart_rate');
    if ($maxHeartRate !== null && is_numeric($maxHeartRate)) {
        $maxHeartRateValue = (int) round((float) $maxHeartRate);
    }

    $maxSpeed = firstFitValue($session, 'max_speed');
    if ($maxSpeed !== null && is_numeric($maxSpeed)) {
        $maxSpeedMphValue = round((float) $maxSpeed, 2);
    }

    $normalizedPower = firstFitValue($session, 'normalized_power');
    if ($normalizedPower !== null && is_numeric($normalizedPower)) {
        $npPowerWattsValue = (int) round((float) $normalizedPower);
    }

    $startTime = firstFitValue($session, 'start_time');
    if ($startTime === null) {
        $startTime = firstFitValue($record, 'timestamp');
    }

    if ($startTime !== null) {
        if (is_numeric($startTime)) {
            $dt = new DateTime('@' . (int) $startTime);
            $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
            $startTimeValue = $dt->format('Y-m-d H:i:s');
        } else {
            $timestamp = strtotime((string) $startTime);
            if ($timestamp !== false) {
                $dt = new DateTime('@' . $timestamp);
                $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
                $startTimeValue = $dt->format('Y-m-d H:i:s');
            }
        }
    }

    /*
     * Fallbacks from record streams.
     */
    if (
        $distanceMilesValue === null &&
        !empty($record['distance']) &&
        is_array($record['distance'])
    ) {
        $distanceValues = array_values(array_filter($record['distance'], 'is_numeric'));
        if (!empty($distanceValues)) {
            $distanceMilesValue = round((float) max($distanceValues), 2);
        }
    }

    if ($maxSpeedMphValue === null && !empty($record['speed']) && is_array($record['speed'])) {
        $speedValues = array_values(
            array_filter($record['speed'], static function ($value) {
                return is_numeric($value) && (float) $value > 0 && (float) $value < 80;
            }),
        );

        if (!empty($speedValues)) {
            $maxSpeedMphValue = round((float) max($speedValues), 2);
        }
    }

    if ($avgPowerWattsValue === null && !empty($record['power']) && is_array($record['power'])) {
        $powerValues = array_values(
            array_filter($record['power'], static function ($value) {
                return is_numeric($value) && (float) $value > 0;
            }),
        );

        if (!empty($powerValues)) {
            $avgPowerWattsValue = (int) round(array_sum($powerValues) / count($powerValues));
        }
    }

    if ($maxPowerWattsValue === null && !empty($record['power']) && is_array($record['power'])) {
        $powerValues = array_values(
            array_filter($record['power'], static function ($value) {
                return is_numeric($value) && (float) $value > 0;
            }),
        );

        if (!empty($powerValues)) {
            $maxPowerWattsValue = (int) round(max($powerValues));
        }
    }

    if (
        $avgHeartRateValue === null &&
        !empty($record['heart_rate']) &&
        is_array($record['heart_rate'])
    ) {
        $heartRateValues = array_values(
            array_filter($record['heart_rate'], static function ($value) {
                return is_numeric($value) && (int) $value > 0;
            }),
        );

        if (!empty($heartRateValues)) {
            $avgHeartRateValue = (int) round(array_sum($heartRateValues) / count($heartRateValues));
        }
    }

    if (
        $maxHeartRateValue === null &&
        !empty($record['heart_rate']) &&
        is_array($record['heart_rate'])
    ) {
        $heartRateValues = array_values(
            array_filter($record['heart_rate'], static function ($value) {
                return is_numeric($value) && (int) $value > 0;
            }),
        );

        if (!empty($heartRateValues)) {
            $maxHeartRateValue = (int) max($heartRateValues);
        }
    }

    if (!empty($record['position_lat']) && !empty($record['position_long'])) {
        $latValues = array_values($record['position_lat']);
        $lngValues = array_values($record['position_long']);

        $count = min(count($latValues), count($lngValues));

        for ($i = 0; $i < $count; $i++) {
            if (is_numeric($latValues[$i]) && is_numeric($lngValues[$i])) {
                $startLatValue = round((float) $latValues[$i], 6);
                $startLngValue = round((float) $lngValues[$i], 6);
                break;
            }
        }

        for ($i = $count - 1; $i >= 0; $i--) {
            if (is_numeric($latValues[$i]) && is_numeric($lngValues[$i])) {
                $endLatValue = round((float) $latValues[$i], 6);
                $endLngValue = round((float) $lngValues[$i], 6);
                break;
            }
        }
    }

    return [
        'distance_miles' => $distanceMilesValue,
        'elapsed_minutes' => $elapsedMinutesValue,
        'elevation_gain_ft' => $elevationGainFeetValue,
        'avg_power_watts' => $avgPowerWattsValue,
        'max_power_watts' => $maxPowerWattsValue,
        'avg_heart_rate' => $avgHeartRateValue,
        'max_heart_rate' => $maxHeartRateValue,
        'max_speed_mph' => $maxSpeedMphValue,
        'np_power_watts' => $npPowerWattsValue,
        'start_time' => $startTimeValue,
        'start_lat' => $startLatValue,
        'start_lng' => $startLngValue,
        'end_lat' => $endLatValue,
        'end_lng' => $endLngValue,
    ];
}

$distanceMilesValue = $distanceMiles !== '' ? (float) $distanceMiles : null;
$elapsedMinutesValue = $elapsedMinutes !== '' ? (int) $elapsedMinutes : null;
$elevationGainFtValue = $elevationGainFt !== '' ? (int) $elevationGainFt : null;

$manualAvgSpeedMphValue = $avgSpeedMph !== '' ? round((float) $avgSpeedMph, 2) : null;
$avgPowerWattsValue = $avgPowerWatts !== '' ? (int) round((float) $avgPowerWatts) : null;
$avgHeartRateValue = $avgHeartRate !== '' ? (int) round((float) $avgHeartRate) : null;

$npPowerWattsValue = null;
$startTimeValue = null;

if ($rideDate !== '' && $manualStartTime !== '') {
    $startTimeValue = $rideDate . ' ' . $manualStartTime . ':00';
}
$startLatValue = null;
$startLngValue = null;
$endLatValue = null;
$endLngValue = null;
$uploadFileValue = null;
$uploadFileUploadedValue = 0;

/**
 * Optional ride file upload (GPX or TCX)
 */

//logStep('FILE received', $_FILES['gpx_file']['name'] ?? 'none');

if (!empty($_FILES['gpx_file']['name'])) {
    if (!isset($_FILES['gpx_file']['error']) || $_FILES['gpx_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_failure'] = 'There was a problem uploading the ride file.';
        header(
            'Location: ' . DOC_ROOT . 'bicycle-submit?website=44&location=' . urlencode($location),
        );
        exit();
    }

    $extension = strtolower(pathinfo($_FILES['gpx_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['gpx', 'tcx', 'fit'], true)) {
        $_SESSION['flash_failure'] = 'Only GPX, TCX, and FIT files are allowed.';
        header(
            'Location: ' . DOC_ROOT . 'bicycle-submit?website=44&location=' . urlencode($location),
        );
        exit();
    }

    //logStep('FILE received', $_FILES['gpx_file']['name'] ?? 'none');

    if ((int) $_FILES['gpx_file']['size'] > 15 * 1024 * 1024) {
        $_SESSION['flash_failure'] = 'The ride file is too large. Max size is 15 MB.';
        header(
            'Location: ' . DOC_ROOT . 'bicycle-submit?website=44&location=' . urlencode($location),
        );
        exit();
    }

    $uploadDirectory = APP_ROOT . '/public/uploads/gpx';

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            $_SESSION['flash_failure'] =
                'Upload directory could not be created: ' . $uploadDirectory;
            header(
                'Location: ' .
                    DOC_ROOT .
                    'bicycle-submit?website=44&location=' .
                    urlencode($location),
            );
            exit();
        }
    }

    if (!is_writable($uploadDirectory)) {
        $_SESSION['flash_failure'] = 'Upload directory is not writable: ' . $uploadDirectory;
        header(
            'Location: ' . DOC_ROOT . 'bicycle-submit?website=44&location=' . urlencode($location),
        );
        exit();
    }

    $safeFileName = 'ride_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destinationPath = $uploadDirectory . '/' . $safeFileName;

    //('MOVE file →', $destinationPath);
    if (!move_uploaded_file($_FILES['gpx_file']['tmp_name'], $destinationPath)) {
        $_SESSION['flash_failure'] = 'Ride file could not be saved.';
        header(
            'Location: ' . DOC_ROOT . 'bicycle-submit?website=44&location=' . urlencode($location),
        );
        exit();
    }

    try {
        if ($extension === 'gpx') {
            $parsed = parseGpxRide($destinationPath);
        } elseif ($extension === 'tcx') {
            $parsed = parseTcxRide($destinationPath);
        } elseif ($extension === 'fit') {
            $parsed = parseFitRide($destinationPath);
            error_log('[FIT parsed result] ' . print_r($parsed, true));
        } else {
            throw new RuntimeException('Unsupported file type.');
        }

        $gpxFileValue = 'uploads/gpx/' . $safeFileName;
        $gpxUploadedValue = 1;

        $distanceMilesValue = $parsed['distance_miles'] ?? $distanceMilesValue;
        $elapsedMinutesValue = $parsed['elapsed_minutes'] ?? $elapsedMinutesValue;
        $elevationGainFtValue = $parsed['elevation_gain_ft'] ?? $elevationGainFtValue;

        $maxSpeedMphValue = $parsed['max_speed_mph'] ?? $maxSpeedMphValue;
        $avgPowerWattsValue = $parsed['avg_power_watts'] ?? $avgPowerWattsValue;
        $maxPowerWattsValue = $parsed['max_power_watts'] ?? $maxPowerWattsValue;
        $avgHeartRateValue = $parsed['avg_heart_rate'] ?? $avgHeartRateValue;
        $maxHeartRateValue = $parsed['max_heart_rate'] ?? $maxHeartRateValue;
        $npPowerWattsValue = $parsed['np_power_watts'] ?? $npPowerWattsValue;

        $startTimeValue = $parsed['start_time'] ?? $startTimeValue;
        if (!empty($parsed['start_time'])) {
            $rideDate = substr($parsed['start_time'], 0, 10);
        }
        $startLatValue = $parsed['start_lat'] ?? $startLatValue;
        $startLngValue = $parsed['start_lng'] ?? $startLngValue;
        $endLatValue = $parsed['end_lat'] ?? $endLatValue;
        $endLngValue = $parsed['end_lng'] ?? $endLngValue;
        $startLocation = trim((string) ($startLocation ?? ''));

        if ($startLocation === '' && $startLatValue !== null && $startLngValue !== null) {
            $resolvedLocation = reverseGeocodeNominatim(
                (float) $startLatValue,
                (float) $startLngValue,
            );

            //logStep('GEOCODE result', $resolvedLocation);

            if ($resolvedLocation !== null && trim($resolvedLocation) !== '') {
                $startLocation = trim($resolvedLocation);
            }
        }
    } catch (Throwable $e) {
        @unlink($destinationPath);
        $_SESSION['flash_failure'] = 'The ride file could not be parsed: ' . $e->getMessage();
        header(
            'Location: ' . DOC_ROOT . 'bicycle-submit?website=44&location=' . urlencode($location),
        );
        exit();
    }
}

$avgSpeed = $manualAvgSpeedMphValue;

if (
    $avgSpeed === null &&
    $distanceMilesValue !== null &&
    $elapsedMinutesValue !== null &&
    $elapsedMinutesValue > 0
) {
    $avgSpeed = round($distanceMilesValue / ($elapsedMinutesValue / 60), 2);
}

error_log(
    '[RIDE INSERT VALUES] ' .
        print_r(
            [
                'max_speed_mph' => $maxSpeedMphValue,
                'avg_power_watts' => $avgPowerWattsValue,
                'max_power_watts' => $maxPowerWattsValue,
                'avg_heart_rate' => $avgHeartRateValue,
                'max_heart_rate' => $maxHeartRateValue,
                'np_power_watts' => $npPowerWattsValue,
            ],
            true,
        ),
);

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
        max_speed_mph,
        avg_power_watts,
        max_power_watts,
        avg_heart_rate,
        max_heart_rate,
        np_power_watts,
        notes,
        gpx_file,
        gpx_uploaded,
        start_lat,
        start_lng,
        end_lat,
        end_lng,
        status,
        location

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
        :max_speed_mph,
        :avg_power_watts,
        :max_power_watts,
        :avg_heart_rate,
        :max_heart_rate,
        :np_power_watts,
        :notes,
        :gpx_file,
        :gpx_uploaded,
        :start_lat,
        :start_lng,
        :end_lat,
        :end_lng,
        :status,
        :location
    )
";

$startLocation = trim((string) ($startLocation ?? ''));
$gpxFileValue = $gpxFileValue ?? null;
$gpxUploadedValue = $gpxUploadedValue ?? 0;

error_log(
    '[RIDE INSERT VALUES] ' .
        print_r(
            [
                'avg_speed_mph' => $avgSpeed,
                'max_speed_mph' => $maxSpeedMphValue,
                'avg_power_watts' => $avgPowerWattsValue,
                'max_power_watts' => $maxPowerWattsValue,
                'avg_heart_rate' => $avgHeartRateValue,
                'max_heart_rate' => $maxHeartRateValue,
                'np_power_watts' => $npPowerWattsValue,
            ],
            true,
        ),
);

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
    'max_speed_mph' => $maxSpeedMphValue,
    'avg_power_watts' => $avgPowerWattsValue,
    'max_power_watts' => $maxPowerWattsValue,
    'avg_heart_rate' => $avgHeartRateValue,
    'max_heart_rate' => $maxHeartRateValue,
    'np_power_watts' => $npPowerWattsValue,
    'notes' => $notes !== '' ? $notes : null,
    'gpx_file' => $gpxFileValue,
    'gpx_uploaded' => $gpxUploadedValue,
    'start_lat' => $startLatValue,
    'start_lng' => $startLngValue,
    'end_lat' => $endLatValue,
    'end_lng' => $endLngValue,
    'status' => 'published',
    'location' => $location,
]);

$_SESSION['flash_success'] = 'Ride submitted successfully.';
header('Location: ' . DOC_ROOT . 'index/44?location=' . urlencode($location));
exit();
