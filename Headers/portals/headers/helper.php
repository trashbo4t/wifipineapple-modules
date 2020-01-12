<?php
 
/**
 * getClientMac
 * Gets the mac address of a client by the IP address
 * Returns the mac address as a string
 * @param $clientIP : The clients IP address
 * @return string
 */
function getClientMac($clientIP)
{
    return trim(exec("grep " . escapeshellarg($clientIP) . " /tmp/dhcp.leases | awk '{print $2}'"));
}

/**
 * getClientSSID
 * Gets the SSID a client is associated by the IP address
 * Returns the SSID as a string
 * @param $clientIP : The clients IP address
 * @return string
 */
function getClientSSID($clientIP)
{
    // Get the clients mac address. We need this to get the SSID
    $mac = getClientMac($clientIP);

    // get the path to the log file
    $pineAPLogPath = trim(file_get_contents('/etc/pineapple/pineap_log_location'));

    // get the ssid
    return trim(exec("grep " . $mac . " " . $pineAPLogPath . "pineap.log | grep 'Association' | awk -F ',' '{print $4}'"));

}

/**
 * getClientHostName
 * Gets the host name of the connected client by the IP address
 * Returns the host name as a string
 * @param $clientIP : The clients IP address
 * @return string
 */
function getClientHostName($clientIP)
{
    return trim(exec("grep " . escapeshellarg($clientIP) . " /tmp/dhcp.leases | awk '{print $4}'"));
}

function getClientHeaders($clientIP)
{
	if (!function_exists('getallheaders'))
	{
		function getallheaders()
		{
		   $headers = [];
		   foreach ($_SERVER as $name => $value)
		   {
			   if (substr($name, 0, 5) == 'HTTP_')
			   {
				   $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			   }
		   }

		   return $headers;
		}
	} 

	return getallheaders();
}

function initFile($file) {
	$data = json_decode(file_get_contents($file), true);
	if ($data == null)
	{
		$emptyObj = json_encode(json_decode ("{}"));
		file_put_contents($file, $emptyObj);
	}
}

/**
 * Add a value to a json file
 * @param $keyValueArray: The data to add to the file
 * @param $file: The file to write the content to.
 */
function updateJSONFile($keyValueArray, $file) {
	initFile($file);
	$data = json_decode(file_get_contents($file), true);
	$data[$keyValueArray['mac']] = json_encode($keyValueArray);

	file_put_contents($file, json_encode($data));
}

function profile($clientip) {
	$JSON_FILE = '/pineapple/modules/Headers/data/clients.json';

	if (!is_dir('/pineapple/modules/Headers/data'))
	{
		mkdir('/pineapple/modules/Headers/data');
    }

    $mac = getClientMac($clientIP);
    $ssid = getClientSSID($clientIP);
	$hostname = getClientHostName($clientip);
	$headers = getClientHeaders($clientip);

	$p = array("ip" => $clientip, 
		"mac" => $mac, 
		"ssid" => $ssid, 
		"hostname" => $hostname, 
		"headers" => $headers
	);
	
	updateJSONFile($p, $JSON_FILE);

	return json_encode($p);
}
