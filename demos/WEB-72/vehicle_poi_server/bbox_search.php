<?php
header('Content-type: application/json');

$pois = array();

$addr = "http://data.itsfactory.fi/siriaccess/vm/json";
$file = "TRE_siri.txt";
$pois = bbox_search($pois, $addr, $file);

$addr = "http://dev.hsl.fi/siriaccess/vm/json?operatorRef=HSL";
$file = "HSL_siri.txt";
$pois = bbox_search($pois, $addr, $file);

$pois_data = array("pois" => $pois);
$pois_json = json_encode($pois_data,JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS);
echo $pois_json;

function bbox_search($pois, $addr, $file) {
  $south = $_REQUEST['south'];
  $north = $_REQUEST['north'];
  $west = $_REQUEST['west'];
  $east = $_REQUEST['east'];
  $oldt = filemtime($file);
  $next = $oldt + 10;
  $nowt = time();
  $base_uuid_bin = hex2bin("17ee7690-76f0-11e4-82f8-0800200c9a66");
  $data = NULL;
  if ($next < $nowt) {
    $data = file_get_contents($addr);
    if (strlen($data) < 300) {
      $data = NULL;
    }
  }
  if ($data != NULL) {
    file_put_contents($file, $data, LOCK_EX);
  } else {
    $data = file_get_contents($file);
  }
  $data_val = json_decode($data);
  $super_tbl = $data_val -> Siri -> ServiceDelivery -> VehicleMonitoringDelivery;
  $super_tbl_size = count($super_tbl);
  for ($i = 0; $i < $super_tbl_size; $i++) {
    $data_tbl = $super_tbl[$i] -> VehicleActivity;
    $data_tbl_size = count($data_tbl);
    for ($j = 0; $j < $data_tbl_size; $j++) {
      $data_item = $data_tbl[$j] -> MonitoredVehicleJourney;
      $lat = $data_item -> VehicleLocation -> Latitude;
      $lng = $data_item -> VehicleLocation -> Longitude;
      if ( (is_null($south) || $lat >= $south)
        && (is_null($north) || $lat <= $north)
        && (is_null($west) || $lng >= $west )
        && (is_null($east) || $lng <= east) )
      {
        $poi_name_str = $data_item->OperatorRef->value." ".$data_item->VehicleRef->value;
        $poi_name = array("" => $poi_name_str);
        $uuid_bin = md5($base_uuid_bin . $poi_name_str, true);
        $uuid_bin[6] = chr((ord($uuid_bin[6]) & 0x0f) | 0x30);
        $uuid_bin[8] = chr((ord($uuid_bin[8]) & 0x3f) | 0x80);
        $uuid_hex = bin2hex($uuid_bin);
        $uuid = substr($uuid_hex, 0, 8) . "-"
              . substr($uuid_hex, 8, 4) . "-"
              . substr($uuid_hex, 12, 4) . "-"
              . substr($uuid_hex, 16, 4) . "-"
              . substr($uuid_hex, 20, 12);
        $poi_categories = array("vehicle");
        $poi_location = array("wgs84" => array("latitude" => $lat, "longitude" => $lng));
        $poi_desc_text = json_encode($data_item);
        $poi_desc = array("" => $poi_desc_text);
        $poi_core = array(
            "categories" => $poi_categories,
            "location" => $poi_location,
            "name" => $poi_name,
            "description" => $poi_desc,
        );
        $poi_data = array("fw_core" => $poi_core, "fw_extra" => $data_item, "fw_vehicle" => $data_item);
        if ($lat > 0 && $lng > 0) {
          $pois[$uuid] = $poi_data;
        }
      }
    }
  }
  return $pois;
}
