registerController("HeadersController", ['$api', '$scope', function ($api, $scope) {
    // status information about the module
    $scope.headers = {
        "throbber": false,
        "sdAvailable": false,
        "running": false,
        "startOnBoot": false,
        "library": true
    };

    // controls that belong in the Controls pane
    $scope.controls = [
        {
			"title": "Captive Portal", 
			"visible": true, 
			"throbber": false, 
			"status": "Enable"
		},
        {
			"title": "Start On Boot", 
			"visible": true, 
			"throbber": false, 
			"status": "Enable"
		}
    ];

    // messages to be displayed in the Messages pane
    $scope.messages = [];
    $scope.clients = [];

    $scope.whiteList = {"clients": "", "toManipulate": null};

    $scope.accessList = {"clients": "", "toManipulate": null};

	$scope.CLIENTS_FILE = "/pineapple/modules/Headers/portals/headers/data/clients.json"; 

    $scope.captive = {
		"portal": {
				"title": "headers", 
				"storage": "sd",
				"active": false
		}, 
		"inRoot": true, 
		"rootDirectory": "/pineapple/modules/Headers/portals/headers", 
    };

    // active log file
    $scope.activeLog = {"title": null, "path": null, "contents": null};

	/**
     * Activate the portal
     */
    $scope.activatePortal = function() {
		rollcall("activatePortal");
		
		portal = $scope.captive.portal;

        $api.request({
            module: "Headers",
            action: "activatePortal",
            name: portal.title,
            storage: portal.storage
        }, function(response) {
            console.log(response);

            if (response.success) {
				$scope.captive.portal.active = true;

                $scope.sendMessage("Activated Portal", portal.title + " has been activated successfully.");
            } else {
                $scope.sendMessage("Error Activating " + portal.title, response.message);
            }
        });
    };


    /**
     * Add a new client to the white list
     */
    $scope.addWhiteListClient = function() {
		rollcall("addWhiteListClient");

        writeToFile('/pineapple/modules/Headers/data/allowed.txt', $scope.whiteList.toManipulate + "\n", true, function(response) {
            $scope.getList('whiteList');
        });
        $scope.whiteList.toManipulate = null;
    };

    /**
     * Authorize a new client
     */
    $scope.authorizeClient = function () {
		rollcall("authorizeClient");
        
		$api.request({
            module: "Headers",
            action: "authorizeClient",
            clientIP: $scope.accessList.toManipulate
        }, function (response) {
            $scope.getList('accessList');
            $scope.accessList.toManipulate = null;
        });
    };

    /**
     * Deactivate the portal if its active
     */
    $scope.deactivatePortal = function() {
		rollcall("deactivatePortal");
		
		portal = $scope.captive.portal;
        
		$api.request({
            module: "Headers",
            action: "deactivatePortal",
            name: portal.title,
            storage: portal.storage
        }, function(response) {
            console.log(response);
            if (response.success) {
				$scope.captive.portal.active = false;

                $scope.sendMessage("Deactivated Portal", portal.title + " has been deactivated successfully.");
            } else {
                $scope.sendMessage("Error Deactivating " + portal.title, response.message);
            }
        });
    };

    /**
     * Remove a message from the Headers Messages pane
     * @param $index: The index of the message in the list to remove
     */
    $scope.dismissMessage = function ($index) {
		rollcall("dismissMessage");

        $scope.messages.splice($index, 1);
    };

    /**
     * Get a line clicked in a text area and set that line as the text for a text input
     * @param textareaId: The id of the text area to grab from
     * @param inputname: The name of the input field to write to
     */
    $scope.getClickedClient = function(textareaId, inputname) {
		rollcall("getClickedClient");
        
		var textarea = $('#' + textareaId);
        var lineNumber = textarea.val().substr(0, textarea[0].selectionStart).split('\n').length;
        var ssid = textarea.val().split('\n')[lineNumber-1].trim();
        $("input[name='" + inputname + "']").val(ssid).trigger('input');
    };

    /**
     * Load either the white list or the authorized clients (access) list
     * @param listName: The name of the list: whiteList or accessList (authorized clients)
     */
    $scope.getList = function (listName) {
		rollcall("getList");
        
		var whiteList = '/pineapple/modules/Headers/data/allowed.txt';
		var clients = '/pineapple/modules/Headers/data/clients.json';
        var authorized = '/tmp/EVILPORTAL_CLIENTS.txt';

		var list = "";
		switch (listName) {
			case 'whiteList':
				list = whiteList;
				break;
			case 'accessList':
				list = authorized;
				break;
			case 'clients':
				list = clients;
				break;
		}

        getFileOrDirectoryContent(list, function (response) {
			console.log(response);
            switch (listName) {
                case 'whiteList':
                    $scope.whiteList.clients = response.content.fileContent;
                    break;
                case 'accessList':
                    $scope.accessList.clients = response.content.fileContent;
                    break;
         		case 'clients':
					$scope.clients = [];
					var clientsObj =  JSON.parse(response.content.fileContent);
					console.log(clientsObj);

					for (var key in clientsObj) 
					{
					   $scope.clients.push(JSON.parse(clientsObj[key]));
					}

					break;
            }
        })
    };


    /**
     * Get the contents of a directory
     * @param pathToObject: The full path to the file or directory to get the contents of
     * @param callback: A function that handles the response from the API.
     */
    function getFileOrDirectoryContent(pathToObject, callback) {
		rollcall("getFileDirectoryContent");
   
		$api.request({
            module: "Headers",
            action: "getFileContent",
            filePath: pathToObject
        }, function(response) {
            callback(response);
        });
    }


    /**
     * Get the status's for the controls in the Controls pane and other various information
     */
    function getStatus() {
		rollcall("getStatus");
        
		$scope.headers.throbber = true;

        $api.request({
            module: "Headers",
            action: "status"
        }, function (response) {
            for (var key in response) {
                if (response.hasOwnProperty(key) && $scope.headers.hasOwnProperty(key)) {
                    $scope.headers[key] = response[key];
                }
            }

            $scope.headers.throbber = false;

            updateControls();
        });
    }

    /**
     * Preform an action for a given control
     * This can be starting the captive portal or toggle on boot.
     * @param control: The control to handle
     */
    $scope.handleControl = function(control) {
		rollcall("handleControl");
        
		control.throbber = true;

        var actionToPreform = null;

        switch(control.title) {
            case "Captive Portal":
				if (!$scope.captive.portal.active) {
					$scope.activatePortal();
				} else {
					$scope.deactivatePortal();
				}

                actionToPreform = "toggleCaptivePortal";

                break;
            case "Start On Boot":
				if (!$scope.captive.portal.active) 
				{
					$scope.activatePortal();
				} 

	            actionToPreform = "toggleOnBoot";

                break;
        }

        if (actionToPreform !== null) {
			$api.request({
                module: "Headers",
                action: actionToPreform
            }, function(response) {
                if (!response.success) {
                    $scope.sendMessage(control.title, response.message);
                }

                getStatus();
            });
        }
    };

    /**
     * Remove a client from either the white list (whiteList) the authorized clients list (accessList)
     * @param listName: whiteList or accessList
     */
    $scope.removeClientFromList = function(listName) {
		rollcall("removeClientFromList");

        var clientToRemove = (listName === 'whiteList') ? $scope.whiteList.toManipulate : $scope.accessList.toManipulate;
        console.log(clientToRemove);
        $api.request({
            module: "Headers",
            action: "removeClientFromList",
            clientIP: clientToRemove,
            listName: listName
        }, function(response) {
            if (!response.success) {
                $scope.sendMessage("Error", response.message);
                return;
            }

            $scope.getList(listName);

            switch (listName) {
                case 'whiteList':
                    $scope.whiteList.toManipulate = null;
                    break;
                case 'accessList':
                    $scope.accessList.toManipulate = null;
                    break;
            }
        });
    };

	function rollcall(fn) {
		console.log(fn);
	}

    /**
     * Push a message to the Headers Messages Pane
     * @param t: The Title of the message
     * @param m: The message body
     */
    $scope.sendMessage = function (t, m) {
		rollcall("sendMessage");
        
		// Add a new message to the top of the list
        $scope.messages.unshift({title: t, msg: m});
    };

    /**
     * Update the control models so they reflect the proper information
     */
    function updateControls() {
		rollcall(arguments.callee.name);
    
		$scope.controls = [
            {
                "title": "Captive Portal",
                "status": ($scope.headers.running) ? "Disable" : "Enable",
                "visible": true,
                "throbber": false
            },
            {
                "title": "Start On Boot",
                "status": ($scope.headers.startOnBoot) ? "Disable": "Enable",
                "visible": true,
                "throbber": false
            }
        ];
        
        if ($scope.headers.running || $scope.headers.startOnBoot)
		{
			$scope.captive.portal.active = true;
		}
    }

    /**
     * Write given content to a given file on the file system.
     * @param filePath: The path to the file to write content to
     * @param fileContent: The content to write to the file
     * @param appendFile: Should the content be append to the file (true) or overwrite the file (false)
     * @param callback: A callback function to handle the API response
     */
    function writeToFile(filePath, fileContent, appendFile, callback) {
   		rollcall(arguments.callee.name);
        
		$api.request({
            module: "Headers",
            action: "writeFileContent",
            filePath: filePath,
            content: fileContent,
            append: appendFile
        }, function(response) {
            callback(response);
        });
    }

	// Get the status when the controller loads.
    getStatus();

}]);
