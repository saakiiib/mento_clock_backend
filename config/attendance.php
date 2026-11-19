<?php
return [
    // Rollout safety: preserve employees' ability to finish shifts while GPS
    // enforcement is introduced. After field testing set true and cache config.
    'enforce_clock_out_geofence' => (bool) env('ATTENDANCE_GPS_STRICT_CLOCK_OUT', false),
];
