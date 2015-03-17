<?php
/*
version 1.5

This is a simple proxy to the overpass API to provide near 0.6 API compatibilty for read calls.
All other not handled calls (POST, PUT, DELETE, GET for history calls, changesets, gpx, etc.) are forwarded to the main
API at http://api.openstreetmap.org without changes

author : sly sylvain () letuffe * org
Licence is WTFPL
free software, not warranty, etc.

 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details.
 
CHANGELOG :

version 1.5 : For the map call, relations up to 2 levels for which a node/way is a member are returned 
version 1.4 : In order to limit useless proxing, all GET calls not answered locally (gpx, history, changeset...) are redirected at http level with a http 302
version 1.3 : Code clean up to support a call from any base URI at proxy side
version 1.2 : when requesting a node with /relations relations it is a member of wasn't return
version 1.1 : add the <bounds> tag for map calls return
version 1.0 : 1st release, it should work
*/

require_once("config.php");

// this is the real proxy part, should only be used for write access (but could do for READ access as well but is less usefull and risks of a blacklist)
function forward_to_live_api($uri_without_api_in_case_of_forward)
{
  global $config;
  $client_headers=apache_request_headers ( );
  $payload_file='php://input';
  $payload_handler=fopen($payload_file,"r");
  if (isset($client_headers['Content-Length']))
	$payload_size=$client_headers['Content-Length'];
  else
	$payload_size="";
  
  $out=tmpfile();
  
  $ch = curl_init();
  if ($config['debug'])
  {
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen($config['debug_file'], 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
  }
  // User and pass
  if (isset($_SERVER['PHP_AUTH_USER']))
  {
    // Auth method
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD,$_SERVER['PHP_AUTH_USER'].":".$_SERVER['PHP_AUTH_PW']); 
  }

  // url and method
  curl_setopt($ch, CURLOPT_URL, $config['live_osm_url'].$uri_without_api_in_case_of_forward);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

  curl_setopt($ch, CURLOPT_FILE, $out);

  curl_setopt($ch, CURLOPT_PUT, true);
  curl_setopt($ch, CURLOPT_INFILE,$payload_handler);
  curl_setopt($ch, CURLOPT_INFILESIZE, $payload_size);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $client_headers );
  curl_exec($ch);
  curl_close($ch);
  
  rewind($out);

  $result="";
  while($r=fread($out,4096))
    $result.=$r;
  fclose($out);
  if ($config['debug'])
  {
    fwrite($verbose,"\ncontent we'll send to client:\n".$result);
    fclose($verbose);
  }
  return $result;
}
function redirect_to_live_api($query)
{
	global $config;
	header("HTTP/1.1 302 Moved Temporarily - call the official API directly for those calls");
	header ('location: '.$config['live_osm_url'].'/0.6/'.$query);
}


function overpass_api_id_condition($type,$id)
{
  return '<id-query type="'.$type.'" ref="'.$id.'"/>'."\n";
}

// when the /full options is given to a call for an object
function overpass_api_add_full($type)
{
  // we want the relation's ways and relation's relation and relation's nodes
  if ($type=="relation")
  {
    $recurse='<recurse type="relation-way"/>
    <recurse type="way-node"/>
    </union>
    <union>
    <item/>
    <recurse type="relation-relation"/>
    </union>
    <union>
    <item/>
    <recurse type="relation-node"/>
    ';
  }
elseif ($type=="way") // we want way's nodes
    $recurse='<recurse type="way-node"/>'."\n";
else
    $recurse="";
    
  return $recurse;
}

// when the /relations options is given to a call for an object
function overpass_api_add_relations($type)
{
  // we want the relations the way is a member
  if ($type=="way")
    $recurse='<recurse type="way-relation"/>'."\n";

  // we want the relations the relation is a member
  if ($type=="relation")
    $recurse='<recurse type="relation-backwards"/>'."\n";

  // we want the relations the node is a member
  if ($type=="node")
    $recurse='<recurse type="node-relation"/>'."\n";
  return $recurse;
}

// The map call (should return ways and it's nodes or nodes intersecting the bbox + relation they are a member of up to 3 levels
function overpass_api_bbox_query($west,$south,$east,$north)
{
  return '<union>
  <bbox-query s="'.$south.'" n="'.$north.'" w="'.$west.'" e="'.$east.'"/>
  <recurse type="node-relation" into="rels"/>
  <recurse type="node-way"/>
  <recurse type="way-relation"/>
  </union>
  <union>
  <item/>
  <recurse type="way-node"/>
</union>
  <union>
  <item/>
  <recurse type="way-relation"/>
</union>
  <union>
  <item/>
<recurse from="_" into="_" type="relation-backwards"/>
</union>
  ';
}

// in case we want to mute what the output is like (for future use)
function overpass_api_print()
{
  return '<print mode="meta"/>';
}


function overpass_api_request($xml_query)
{
  global $config;

  $ch = curl_init($config['overpass_interpreter_url']);
  
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, "data=".$xml_query);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  curl_close($ch);
  return $response;
}

// Return the capabilities of this serveur + one day freshness of data
function capabilities_call()
{
  global $config;
  $r = new stdclass;
  $r->xml='
<osm version="0.6" generator="'.$config['server_string'].'">
<api>
	<version minimum="0.6" maximum="0.6"/>
	<area maximum="'.$config['maximum_served_area'].'"/>
	<waynodes maximum="'.$config['maximum_objects'].'"/>    <tracepoints per_page="5000"/>
	<waynodes maximum="2000"/>
	<changesets maximum_elements="50000"/>
	<timeout seconds="300"/>
</api>
</osm>';
 $r->error=false;
 return $r;
}

function map_call($get)
{
  $r = new stdclass;
  $bbox_array=explode(",",$_GET['bbox']);
  if (!count($bbox_array==4))
    $r->error=true;
  else
  {
    $xml_query=overpass_api_bbox_query($bbox_array[0],$bbox_array[1],$bbox_array[2],$bbox_array[3]);
    $xml_query.=overpass_api_print();
    $raw_xml=overpass_api_request($xml_query);
    // Add the <bounds> tag to help JOSM choose the viewpoint
    $return=explode("\n",$raw_xml);
    $r->xml=$raw_xml;
    $r->xml="";
    $found=false;
    foreach ($return as $line)
    {
      $r->xml.=$line."\n";
      if ($found==false and preg_match("/^<osm .*/",$line))
      {
	$found=true;
	$r->xml.="<bounds minlat=\"$bbox_array[1]\" minlon=\"$bbox_array[0]\" maxlat=\"$bbox_array[3]\" maxlon=\"$bbox_array[2]\"/>\n";
      }
    }
    
    $r->error=false;
  }
  return $r;
}

function multi_objects_call($type,$get)
{
	$r = new stdclass;
	$list=explode(",",$get[$type]);
    $xml_query='<union>'."\n";
	foreach ($list as $id)
		$xml_query.=overpass_api_id_condition(trim($type,"s"),$id);
    $xml_query.=overpass_api_add_full(trim($type,"s"));
    $xml_query.='</union>'."\n";

    $xml_query.=overpass_api_print();
    $r->xml=overpass_api_request($xml_query);
    $r->error=false;
    return $r;
}

function single_object_call($type,$params)
{
  $r = new stdclass;
  if (!isset($params[1]) or !ctype_digit($params[1]) or 
    (isset($params[2]) and $params[2]!="full" and $params[2]!="relations") )
    {
      $r->error=true;
      $r->redirect=true;
    }
  else
  {
	$xml_query='<union>'."\n";
	$xml_query.=overpass_api_id_condition($type,$params[1]);
	if (isset($params[2]))
	{
		if ($params[2]=="full")
			$xml_query.=overpass_api_add_full($type);
		if ($params[2]=="relations")
			$xml_query.=overpass_api_add_relations($type);
	}
	$xml_query.='</union>'."\n";
	$xml_query.=overpass_api_print();
	$r->xml=overpass_api_request($xml_query);
	$r->error=false;
  
  }
  return $r;
}

function call_dispatcher($query_string)
{
  $r = new stdclass;
  // Check that we have a GET request, if not
  // no need to continue, this proxy doesn't support anything but some of GET calls
  if ($_SERVER['REQUEST_METHOD']!="GET")
  {
    $r->error=true;
    $r->redirect=false;
    return $r;
  }
    
  $params=explode("?",$query_string);
  if ($params[0]=="map")
    $r=map_call($_GET);

  elseif(in_array($params[0] ,array("nodes","relations","ways")))
    $r=multi_objects_call($params[0],$_GET);
  
  else
  {
    $params=explode("/",$query_string);
    if(in_array($params[0] ,array("node","relation","way")))
    {
      $r=single_object_call($params[0],$params);
    }
    elseif ($params[0]=="capabilities")
      $r=capabilities_call();
    else
	{      
		$r->error=true;
		$r->redirect=true;
	}
    }

return $r;
}

/***************** Start ***************/

//want to get what is after the /0.6/*
//Makes it possible to have anything as base path
$query=preg_replace("/.*\/0.6\//","",$_SERVER['REQUEST_URI']);

// Special case of the /api/capabilities call
if (preg_match("/.*\/capabilities$/",$_SERVER['REQUEST_URI']))
	$query="capabilities";
	
if ($config['force_proxy_mode'])
  forward_to_live_api("/0.6/$query");
else
{
$osm_file=call_dispatcher($query);
  if ($osm_file->error) // this call can not be handeled locally
  {
    if ($osm_file->redirect) // JOSM supports http redirect, let it do the request himself since this proxy can't handle it locally
  	redirect_to_live_api($query);
    else // I thought I could use a 302/303 redirect as well on POST/PUT/DELETE requests but failed, so proxying
      print(forward_to_live_api("/0.6/$query"));
  }
  else // We can handle this call locally with the overpass API
	print($osm_file->xml); 
}
?>
