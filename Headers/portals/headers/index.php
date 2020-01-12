<?php
$destination = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['HTTP_URI'] . "";
require_once('helper.php');
?>

<HTML>
	<HEAD>
		<title>Wifi | Portal</title>
		<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
		<meta http-equiv="Pragma" content="no-cache" />
		<meta http-equiv="Expires" content="0" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
	</HEAD>

	<BODY>
		<div style="text-align: center;">
			<?php
				$prof = profile($_SERVER['REMOTE_ADDR']);
				// print_r($prof);
			?>

			<p><?=getClientSSID($_SERVER['REMOTE_ADDR']);?> Internet Access Portal Verification </p>

			<form method="POST" action="/headers/index.php">
				<input type="hidden" name="target" value="<?=$destination?>">
				<button type="submit">Connect</button>
			</form>
		</div>
	</BODY>
</HTML>
