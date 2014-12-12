<?php
// OverpassAPI server config (which URL to access)
//$config['overpass_interpreter_url']="http://www.overpass-api.de/api/interpreter";
//$config['overpass_interpreter_url']="http://overpass.osm.rambler.ru/cgi/interpreter";
$config['overpass_interpreter_url']="http://api.openstreetmap.fr/oapi/interpreter";
$config['overpass_interpreter_var_name']="data";

// Set it to true for a temporary switch to a redirect/proxy 
// You'd better switch to an other overpass api up there before doing that.
// And only if no suitable overpassAPI exists, then swith this to true
$config['force_proxy_mode']=false;

// The base URL of the 0.6 API server
// DO THINK TWICE AND MORE THAN DOUBLE CHECK BEFORE UNCOMMENTING THIS :
//$live_osm_url_without_protocol="api.openstreetmap.org/api" 
$live_osm_url_without_protocol="http://api06.dev.openstreetmap.org/api";

// Dynamically choose https if we were called with https
if (empty($_SERVER['HTTPS']))
  $config['live_osm_url']="http://".$live_osm_url_without_protocol;
else
  $config['live_osm_url']="https://".$live_osm_url_without_protocol;
    
    
    
// Some funky text for the /capabilities call, this is only indicative for the client, the real limit is a timeout in case the request last too long
// I believe even JOSM don't care about those values, so they are only here for API compliance
$config['server_string']="--give me a name-- api 0.6 proxy tool to the overpass api";
$config['maximum_served_area']="2";
$config['maximum_objects']=10000;


// DEBUG options
$config['debug']=false;
$config['debug_file']='/tmp/simple_api_proxy-http-debug.txt';


?>