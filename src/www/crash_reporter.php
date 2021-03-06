<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2011 Scott Ullrich
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

define("FILE_SIZE", 450000);

function upload_crash_report($files)
{
	global $g;

	$post = array();
	$counter = 0;

	foreach($files as $file) {
		$post["file{$counter}"] = "@{$file}";
		$counter++;
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible;)');
	curl_setopt($ch, CURLOPT_URL, 'https://crash.opnsense.org/');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	$response = curl_exec($ch);

	return $response;
}

function output_crash_reporter_html($crash_reports) {
	echo "<p><strong>" . gettext("Unfortunately we have detected a programming bug.") . "</strong></p>";
	echo "<p>" . gettext("Would you like to submit the programming debug logs to the OPNsense developers for inspection?") . "</p>";
	echo "<p><i>" . gettext("Please double check the contents to ensure you are comfortable sending this information before clicking Yes.") . "</i></p>";
	echo "<p>" . gettext("Contents of crash reports") . ":<br />";
	echo "<textarea readonly=\"readonly\" style=\"max-width: none;\" rows=\"24\" cols=\"80\" name=\"crashreports\">{$crash_reports}</textarea></p>";
	echo "<p><input disabled=\"disabled\" name=\"Submit\" type=\"submit\" class=\"btn btn-primary\" value=\"" . gettext("Yes") .  "\" />" . gettext(" - Submit this to the developers for inspection") . "</p>";
	echo "<p><input name=\"Submit\" type=\"submit\" class=\"btn btn-primary\" value=\"" . gettext("No") .  "\" />" . gettext(" - Just delete the crash report and take me back to the Dashboard") . "</p>";
	echo "</form>";
}

$pgtitle = array(gettext("Diagnostics"),gettext("Crash Reporter"));
include('head.inc');

$crash_report_header = "Crash report begins.  Anonymous machine information:\n\n";
$crash_report_header .= php_uname("m") . "\n";
$crash_report_header .= php_uname("r") . "\n";
$crash_report_header .= php_uname("v") . "\n";
$crash_report_header .= "\nCrash report details:\n";

$php_errors = @file_get_contents('/tmp/PHP_errors.log');

?>

<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			<section class="col-xs-12">
                <div class="content-box">
					 <form action="crash_reporter.php" method="post">
						 <div class="col-xs-12">


<?php
	if (gettext($_POST['Submit']) == "Yes") {
		echo gettext("Processing...");
		if (!is_dir("/var/crash"))
			mkdir("/var/crash", 0750, true);
		@file_put_contents("/var/crash/crashreport_header.txt", $crash_report_header);
		if(file_exists("/tmp/PHP_errors.log"))
			copy("/tmp/PHP_errors.log", "/var/crash/PHP_errors.log");
		exec("/usr/bin/gzip /var/crash/*");
		$files_to_upload = glob("/var/crash/*");
		echo "<br/>";
		echo gettext("Uploading...");
		ob_flush();
		flush();
		if(is_array($files_to_upload)) {
			$resp = upload_crash_report($files_to_upload);
			array_map('unlink', glob("/var/crash/*"));
			// Erase the contents of the PHP error log
			fclose(fopen("/tmp/PHP_errors.log", 'w'));
			echo "<br/>";
			print_r($resp);
			echo "<p><a href=\"/\">" . gettext("Continue") . "</a>" . gettext(" and delete crash report files from local disk.") . "</p>";
		} else {
			echo "Could not find any crash files.";
		}
	} else if(gettext($_POST['Submit']) == "No") {
		array_map('unlink', glob("/var/crash/*"));
		// Erase the contents of the PHP error log
		fclose(fopen("/tmp/PHP_errors.log", 'w'));
		header("Location: /");
		exit;
	} else {
		$crash_files = glob("/var/crash/*");
		$crash_reports = $crash_report_header;
		if (!empty($php_errors)) {
			$crash_reports .= "\nPHP Errors:\n";
			$crash_reports .= $php_errors;
		}
		if(is_array($crash_files))	{
			foreach($crash_files as $cf) {
				if(filesize($cf) < FILE_SIZE) {
					$crash_reports .= "\nFilename: {$cf}\n";
					$crash_reports .= file_get_contents($cf);
				}
			}
		} else {
			echo "Could not locate any crash data.";
		}
		output_crash_reporter_html($crash_reports);
	}
?>
						 </div>
					</form>
                </div>
			</section>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>
