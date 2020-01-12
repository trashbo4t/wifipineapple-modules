<?php namespace pineapple;

class Headers extends Module
{
    // CONSTANTS
    private $CLIENTS_FILE = '/tmp/HEADERS_CLIENTS.txt';
    private $ALLOWED_FILE = '/pineapple/modules/Headers/data/allowed.txt';
    private $STORAGE_LOCATION = '/pineapple/modules/Headers/portals/';
    private $BASE_EP_COMMAND = 'module Headers';

    /**
     * An implementation of the route method from Module.
     * This method routes the request to the method that handles it.
     */
    public function route()
    {
        $this->createPortalFolders();  

        switch ($this->request->action) {
            case 'status':
                $this->response = array(
                    "running" => $this->checkHeadersRunning(),
                    "startOnBoot" => $this->checkAutoStart(),
                );
                break;

            case 'writeFileContent':
                $this->response = $this->writeFileContents($this->request->filePath, $this->request->content, $this->request->append);
                break;

            case 'deleteDirectory':
            case 'deleteFile':
                $this->response = $this->deleteFileOrDirectory($this->request->filePath);
                break;

            case 'getDirectoryContent':
            case 'getFileContent':
                $this->response =  $this->getFileOrDirectoryContents($this->request->filePath);
                break;

            case 'download':
                $this->response = $this->download($this->request->filePath);
                break;

            case 'listAvailablePortals':
                $this->response = $this->getListOfPortals();
                break;

            case 'activatePortal':
                $this->response = $this->activatePortal($this->request->name, $this->request->storage);
                break;

            case 'deactivatePortal':
                $this->response = $this->deactivatePortal($this->request->name, $this->request->storage);
                break;

            case 'getRules':
                $this->response = $this->getPortalRules($this->request->name, $this->request->storage);
                break;

            case 'saveRules':
                $this->response = $this->savePortalRules($this->request->name, $this->request->storage, $this->request->rules);
                break;

            case 'toggleOnBoot':
                $this->response = $this->autoStartHeaders();
                break;

            case 'toggleCaptivePortal':
                $this->response = $this->toggleCaptivePortal();
                break;

            case 'removeClientFromList':
                $this->response = $this->removeFromList($this->request->clientIP, $this->request->listName);
                break;

            case 'authorizeClient':
                $this->authorizeClient($this->request->clientIP);
                $this->response = array("success" => true);
                break;
        }
    }

    /**
     * Create the folders that portals are stored in if they don't exist
     */
    private function createPortalFolders()
    {
        if (!is_dir($this->STORAGE_LOCATION)) 
		{
            mkdir($this->STORAGE_LOCATION);
        }
    }

    /**
     * Decide if a file for a given portal is "deletable" or not.
     * If it is not then the UI should not display a delete option for the file.
     * @param $file: The name of the file
     * @return bool: Is the file deletable or not
     */
    private function isFileDeletable($file)
    {
        if (substr($file, -strlen(".ep")) == ".ep")
		{
            return false;
		}

        return !in_array($file, array("MyPortal.php", "default.php", "helper.php", "index.php"));
    }

    /**
     * Get the contents of a specified file or directory
     *
     * If this method is being called as the result of an HTTP request, make sure that "file" is specified as a
     * parameter of the request and includes the full path to the file that should have its contents returned.
     *
     * @param $file : The file or directory to get contents of
     * @return array
     */
    private function getFileOrDirectoryContents($file)
    {

        if (!file_exists($file)) {
            $message = "No such file or directory {$file}.";
            $contents = null;
            $success = false;
        } else if (is_file($file)) {
            $message = "Found file {$file} and retrieved contents";
            $contents = array(
                "name" => basename($file),
                "path" => $file,
                "size" => $this->readableFileSize($file),
                "fileContent" => file_get_contents($file)
            );
            $success = true;
        } else if (is_dir($file)) {
            $contents = array();
            $message = "Returning directory contents for {$file}";
            foreach (preg_grep('/^([^.])/', scandir($file)) as $object) {
                // skip .ep files because they shouldn't be edited directly.
                if (substr($object, -strlen(".ep")) == ".ep")
                    continue;

                $obj = array("name" => $object, "directory" => is_dir("{$file}/{$object}"),
                    "path" => realpath("{$file}/{$object}"),
                    "permissions" => substr(sprintf('%o', fileperms("{$file}/{$object}")), -4),
                    "size" => $this->readableFileSize("{$file}/{$object}"),
                    "deletable" => $this->isFileDeletable($object));
                array_push($contents, $obj);
            }

            $success = true;
        } else {
            $contents = null;
            $success = false;
            $message = "Unknown case. This should never happen.";
        }
        return array("success" => $success, "message" => $message, "content" => $contents);
    }

    /**
     * Write given content to a given file.
     * @param $file : The file to write content to
     * @param $content : The content to write to the file
     * @param $append : Should the data be appended to the end of the file (true) or over-write the file (false)
     * @return array
     */
    private function writeFileContents($file, $content, $append)
    {
        if ($append) {
            file_put_contents($file, $content, FILE_APPEND);
		}
        else {
            file_put_contents($file, $content);
		}

        return array("success" => true, "message" => null);
    }

    /**
     * Delete a given file or directory and check if it has been deleted.
     * If the file was deleted the success will be true otherwise success is false
     * @param $filePath
     * @return array
     */
    private function deleteFileOrDirectory($filePath)
    {
        if ($this->isFileDeletable(basename($filePath))) {
            exec(escapeshellcmd("rm -rf {$filePath}"));

            $success = (!file_exists($filePath));
            $message = (file_exists($filePath)) ? "Error deleting file {$filePath}." : "{$filePath} has been deleted.";
        } else {
            $success = false;
            $message = "{$filePath} can not be deleted!";
        }
        return array("success" => $success, "message" => $message);
    }

    /**
     * Download a file
     * @param: The path to the file to download
     * @return array : array
     */
    private function download($filePath)
    {
        if (file_exists($filePath)) {
            return array("success" => true, "message" => null, "download" => $this->downloadFile($filePath));
        } else {
            return array("success" => false, "message" => "File does not exist", "download" => null);
        }
    }

    /**
     * Get a list of portals found on internal and sd storage.
     */
    private function getListOfPortals()
    {
        // an array of all of the portals found
        $portals = array();

		foreach (preg_grep('/^([^.])/', scandir($this->STORAGE_LOCATION)) as $object) {
			if (!is_dir($this->STORAGE_LOCATION . $object)) 
			{
				continue; // skip the object if it is not a directory.
			}

			$portal = array(
				"title" => $object,
				"portalType" => $this->getValueFromJSONFile(array("type"), "{$this->STORAGE_LOCATION}{$object}/{$object}.ep")["type"],
				"size" => $this->readableFileSize("{$this->STORAGE_LOCATION}{$object}"),
				"location" => "{$this->STORAGE_LOCATION}{$object}",
				"storage" => $medium,
				"active" => (file_exists("/www/{$object}.ep"))
			);
			// push the portal object to the array of portals found
			array_push($portals, $portal);
		}

        return array("success" => true, "portals" => $portals);
    }


    /**
     * Set a given portal to "active".
     * This means to move the portals contents to /www so it can be access via HTTP port 80.
     *
     * If any file with the same name as one of the files being copied to /www already exists in /www
     * then that file will be renamed to {file_name}.ep_backup and restored when the portal is deactivated.
     *
     * If any portals are currently active when this method is called they will be deactivated.
     *
     * @param $name: The name of the portal to activate
     * @param $storage: The storage medium the portal is on
     * @return array
     */
    private function activatePortal($name, $storage)
    {
        $success = false;
        
		$dir = $this->STORAGE_LOCATION;

        $portalPath = escapeshellarg($dir . $name);

        if (file_exists($dir . $name)) {
            exec("ln -s /pineapple/modules/Headers/includes/api /www/headers");

            $portal_files = scandir($dir . $name);
            foreach ($portal_files as $file) 
			{
                exec("ln -s {$portalPath}/{$file} /www/{$file}");

                $success = true;
            }
            
			$message = "{$name} is now active.";
        } else {
            $message = "Couldn't find {$portalPath}.";
        }

        return array("message" => $message, "success" => $success);
    }

    /**
     * Deactivate a given portal.
     *
     * To do this we remove all files associated with the given portal from /www
     * This method also renames any files with the extension ".ep_backup" to their original name
     *
     * @param $name: The name of the portal to deactivate
     * @param $storage: The storage medium the portal is on
     * @return array
     */
    private function deactivatePortal($name, $storage)
    {
        $storage = ($storage == "internal" || $storage == "sd") ? $storage : "internal";
        $dir = $this->STORAGE_LOCATION;

        // if the portal is not active then return an error
        if (!(file_exists("/www/{$name}.ep"))) 
		{
            return array("success" => false, "message" => "{$name} is not currently active.");
        }

        // if the portal does not exist then return an error
        if (!file_exists($dir . $name)) 
		{
            return array("success" => false, "message" => "Unable to find the portal {$name}.");
        }

        // remove portal files from /www
        foreach(scandir($dir. $name) as $file) 
		{
            unlink("/www/{$file}");
        }

        // rename any files that may have been renamed back to their original name
        foreach(scandir("/www/") as $file) {
            if (substr($file, strlen($file) - strlen(".ep_backup")) === ".ep_backup") {
                $oldName = str_replace(".ep_backup", "", $file);
                rename("/www/{$file}", "www/{$oldName}");
            }
        }

        // holding off on toggle commands until a future release.
        // exec("echo {$dir}{$name}/.disable | at now");

        return array("success" => true, "message" => "Deactivated {$name}.");
    }

    /**
     * Attempt to get rules for a targeted portal
     * @param $name: The name of the portal
     * @param $storage: The storage medium of the portal
     * @return array
     */
    private function getPortalRules($name, $storage)
    {
        $storage = ($storage == "internal" || $storage == "sd") ? $storage : "internal";
        $path = $this->STORAGE_LOCATION;


        if (is_file("{$path}{$name}/{$name}.ep")) {
            $rules = $this->getValueFromJSONFile(array("targeted_rules"), "{$path}{$name}/{$name}.ep")["targeted_rules"];
            return array(
                "message" => "Found portal rules",
                "data" => $rules,
                "success" => true
            );
        } else {
            return array("message" => "Unable to find portal.", "success" => false);
        }

    }

    /**
     * Save rules to a targeted portal
     * @param $name : The name of the portal
     * @param $storage : The storage medium of the portal
     * @param $rules : The rules to save
     * @return array
     */
    private function savePortalRules($name, $storage, $rules)
    {
        $storage = ($storage == "internal" || $storage == "sd") ? $storage : "internal";
        $path = $this->STORAGE_LOCATION;

        if (is_file("{$path}{$name}/{$name}.ep")) {
            $this->updateJSONFile(array("targeted_rules" => json_decode($rules)), "{$path}{$name}/{$name}.ep")["targeted_rules"];
            return array(
                "message" => "Saved portal rules",
                "success" => true
            );
        } else {
            return array("message" => "Unable to find portal {$name}.", "success" => false);
        }

    }

    /**
     * Check if Headers is currently running or not by checking iptables.
     * @return bool
     */
    private function checkHeadersRunning()
    {
        return exec("iptables -t nat -L PREROUTING | grep 172.16.42.1") != '';
    }

    /**
     * Check if Headers is running when the Pineapple starts or not
     * @return bool
     */
    public function checkAutoStart()
    {
        return !(exec("ls /etc/rc.d/ | grep headers") == '');
    }

    /**
     * Grant a client access to the internet and stop blocking them with the captive portal
     * @param $client: The IP address of the client to authorize
     */
    private function authorizeClient($client)
    {
        exec("iptables -t nat -I PREROUTING -s {$client} -j ACCEPT");
        $this->writeFileContents($this->CLIENTS_FILE, "{$client}", true);
    }

    /**
     * Revoke a clients access to the internet and start blocking them with the captive portal
     * @param $client: The IP address of the client to revoke
     */
    private function revokeClient($client)
    {
        exec("iptables -t nat -D PREROUTING -s {$client}");
        exec("iptables -t nat -D PREROUTING -s {$client} -j ACCEPT");
    }

    /**
     * Start the captive portal portion of Headers 
     *
     * All clients in the White List should be automatically authorized when Headers starts.
     *
     * @return array
     */
    private function startHeaders()
    {
        // Delete client tracking file if it exists
        if (file_exists($this->CLIENTS_FILE)) 
		{
            unlink($this->CLIENTS_FILE);
        }

        // Enable forwarding. It should already be enabled on the pineapple but do it anyways just to be safe
        exec("echo 1 > /proc/sys/net/ipv4/ip_forward");
        exec("ln -s /pineapple/modules/Headers/includes/api /www/headers");

        // Insert allowed clients into tracking file
        $allowedClients = file_get_contents($this->ALLOWED_FILE);

        file_put_contents($this->CLIENTS_FILE, $allowedClients);

        // Configure other rules
        exec("iptables -A INPUT -s 172.16.42.0/24 -j DROP");
        exec("iptables -A OUTPUT -s 172.16.42.0/24 -j DROP");
        exec("iptables -A INPUT -s 172.16.42.0/24 -p udp --dport 53 -j ACCEPT");

        // Allow the pineapple
        exec("iptables -A INPUT -s 172.16.42.1 -j ACCEPT");
        exec("iptables -A OUTPUT -s 172.16.42.1 -j ACCEPT");

        exec("iptables -t nat -A PREROUTING -i br-lan -p tcp --dport 80 -j DNAT --to-destination 172.16.42.1:80");
        exec("iptables -t nat -A POSTROUTING -j MASQUERADE");

        // Add rule for each allowed client
        $lines = file($this->CLIENTS_FILE);
        foreach ($lines as $client) 
		{
            $this->authorizeClient($client);
        }
        
        $success = $this->checkHeadersRunning();

        $message = ($success) ? "Headers is now up and running!" : "Headers failed to start.";

        return array("success" => $success, "message" => $message);
    }

    /**
     * Stop the captive portal portion of Headers from running
     * @return mixed
     */
    private function stopHeaders()
    {
        if (file_exists($this->CLIENTS_FILE)) 
		{
            $lines = file($this->CLIENTS_FILE);
            foreach ($lines as $client) 
			{
                $this->revokeClient($client);
            }

            unlink($this->CLIENTS_FILE);
        }

        exec("iptables -t nat -D PREROUTING -i br-lan -p tcp --dport 80 -j DNAT --to-destination 172.16.42.1:80");
        exec("iptables -D INPUT -p tcp --dport 53 -j ACCEPT");
        exec("iptables -D INPUT -j DROP");

        $success = !$this->checkHeadersRunning();
        $message = ($success) ? "Headers has stopped running" : "There was an issue stopping Headers";

        return array("success" => $success, "messsage" => $message);
    }

    /**
     * If Headers is running then stop it, otherwise start it.
     */
    private function toggleCaptivePortal() {
        chmod("/pineapple/modules/Headers/executable/executable", 0755);
        return $this->checkHeadersRunning() ? $this->stopHeaders() : $this->startHeaders();
    }

    /**
     * Enable or Disable Headers to run when the pineapple boots.
     * If Headers is supposed to run when this method is called then it will be disabled
     * If Headers is not supposed to run when this method is called then it will be enabled on boot.
     *
     * This method does not start nor stop current running instances of Headers.
     *
     * @return array
     */
    private function autoStartHeaders()
    {
        // if Headers is not set to start on startup then set that shit
        if (!$this->checkAutoStart()) {
            copy("/pineapple/modules/Headers/includes/headers.sh", "/etc/init.d/headers");
            chmod("/etc/init.d/headers", 0755);
            exec("/etc/init.d/headers enable");

            $enabled = $this->checkAutoStart();

            $message = ($enabled) ? "Headers is now enabled on start up" : "Error enabling Headers on startup.";

            return array(
                "success" => $enabled,
                "message" => $message
            );
        } else {  // if evil portal is set to run on startup then disable that shit.
            exec("/etc/init.d/headers disable");

            $enabled = !$this->checkAutoStart();
            $message = ($enabled) ? "Headers is now disabled on startup." : "Error disabling Headers on startup.";

            return array(
                "success" => $enabled,
                "message" => $message
            );
        }
    }

    /**
     * Removes a client from either the whiteList or authorizedList
     * @param $clientIP: The IP address of the client to be removed
     * @param $listName: The name of the list to remove the client from
     * @return array
     */
    private function removeFromList($clientIP, $listName)
    {
        $valid = preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/', $clientIP);

        // if the IP address is invalid then return an error message
        if (!$valid) 
		{
            return array("success" => false, "message" => "Invalid IP Address.");
        }

        $success = true;
        switch ($listName) {
            case "whiteList":
                $data = file_get_contents($this->ALLOWED_FILE);
                $data = str_replace("{$clientIP}\n", '', $data);
                file_put_contents("/root/removeFromList", $data);
                file_put_contents($this->ALLOWED_FILE, $data);
                break;

            case "accessList":
                $data = file_get_contents($this->CLIENTS_FILE);
                $data = str_replace("{$clientIP}\n", '', $data);
                file_put_contents($this->CLIENTS_FILE, $data);
                $this->revokeClient($clientIP);
                break;

            default:
                $success = false;
                break;

        }

        $message = ($success) ? "Successfully removed {$clientIP} from {$listName}" : "Error removing {$clientIP} from {$listName}";

        return array("success" => $success, "message" => $message);
    }

    /**
     * Add a value to a json file
     * @param $keyValueArray: The data to add to the file
     * @param $file: The file to write the content to.
     */
    private function updateJSONFile($keyValueArray, $file) {
        $data = json_decode(file_get_contents($file), true);

        foreach ($data as $key => $value) {
            if (isset($keyValueArray[$key])) {
                $data[$key] = $keyValueArray[$key];
            }
        }

        file_put_contents($file, json_encode($data));
    }

    /**
     * Get values from a JSON file
     * @param $keys: The key or keys you wish to get the value from
     * @param $file: The file to that contains the JSON data.
     * @return array
     */
    private function getValueFromJSONFile($keys, $file) {
        $data = json_decode(file_get_contents($file), true);

        $values = array();
        foreach ($data as $key => $value) {
            if (in_array($key, $keys)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * Get the size of a file and add a unit to the end of it.
     * @param $file: The file to get size of
     * @return string: File size plus unit. Exp: 3.14M
     */
    private function readableFileSize($file) {
        $size = filesize($file);

        if ($size == null)
		{
            return "0 Bytes";
		}

        if ($size < 1024) {
            return "{$size} Bytes";
        } else if ($size >= 1024 && $size < 1024*1024) {
            return round($size / 1024, 2) . "K";
        } else if ($size >= 1024*1024) {
            return round($size / (1024*1024), 2) . "M";
        }

        return "{$size} Bytes";
    }

}
