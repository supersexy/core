<?php

/*
	Copuyright (C) 20124 Deciso B.V.
	Copyright 2007 Scott Dale
	Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
	and Jonathan Watt <jwatt@jwatt.org>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

$first_time = false;

if (!is_array($config["widgets"]["trafficgraphs"])) {
	$first_time = true;
	$config["widgets"]["trafficgraphs"] = array();
}
$a_config = &$config["widgets"]["trafficgraphs"];

if (!is_array($a_config["shown"])) {
	$a_config["shown"] = array();
}
if (!is_array($a_config["shown"]["item"])) {
	$a_config["shown"]["item"] = array();
}

$ifdescrs = get_configured_interface_with_descr();
if (isset($config['ipsec']['enable']))
	$ifdescrs['enc0'] = "IPsec";

if ($_POST) {

	if (isset($_POST["refreshinterval"])) {
		$a_config["refreshinterval"] = $_POST["refreshinterval"];
	}
	if (isset($_POST["scale_type"])) {
		$a_config["scale_type"] = $_POST["scale_type"];
	}
	$a_config["shown"]["item"] = array();
	foreach ($ifdescrs as $ifname => $ifdescr) {
		$state = $_POST["shown"][$ifname];
		if ($state === "show") {
			$a_config["shown"]["item"][] = $ifname;
		}
	}
	write_config("Updated traffic graph settings via dashboard.");
	header("Location: /");
	exit(0);
}

$shown = array();
foreach ($a_config["shown"]["item"] as $if) {
	$shown[$if] = true;
}
if ($first_time) {
	$keys = array_keys($ifdescrs);
	$shown[$keys[0]] = true;
}

if (isset($a_config["refreshinterval"])) {
	$refreshinterval = $a_config["refreshinterval"];
} else {
	$refreshinterval = 10;
}

if (isset($a_config["scale_type"])) {
        $scale_type = $a_config["scale_type"];
} else {
        $scale_type = "up";
}

?>
<input type="hidden" id="traffic_graphs-config" name="traffic_graphs-config" value="" />

<div id="traffic_graphs-settings" class="widgetconfigdiv" style="display:none;">
<form action="/widgets/widgets/traffic_graphs.widget.php" method="post" name="iform" id="iform">
				<?php foreach ($ifdescrs as $ifname => $ifdescr) { ?>
						<input type="hidden" name="shown[<?= $ifname ?>]" value="<?= $shown[$ifname] ? "show" : "hide" ?>" />
						<?php } ?>

    <table class="table table-striped">
	<tbody>

						<tr>
							<td>
								Default AutoScale:
							</td>
						</tr>
							<?php
								$scale_type_up="checked=\"checked\"";
								$scale_type_follow="";
								if (isset($config["widgets"]["trafficgraphs"]["scale_type"])) {
									$selected_radio = $config["widgets"]["trafficgraphs"]["scale_type"];
									if ($selected_radio == "up") {
										$scale_type_up = "checked=\"checked\"";
										$scale_type_follow="";
									}
									else if ($selected_radio == "follow") {
										$scale_type_up="";
										$scale_type_follow = "checked=\"checked\"";
									}
								}
							?>
			<tr>
				<td>
					<input name="scale_type"  type="radio" id="scale_type_up" value="up" <?php echo $scale_type_up; ?> /> <?php echo gettext('Scale up')?>
				</td>
			</tr>
			<tr>
				<td>
				<input name="scale_type" type="radio" id="scale_type_follow" value="follow" <?php echo $scale_type_follow; ?> /> <?php echo gettext('Scale follow')?><br /><br />
				Refresh Interval:
				<select name="refreshinterval" class="formfld" id="refreshinterval" >
					<?php for ($i = 1; $i <= 10; $i += 1) { ?>
						<option value="<?= $i ?>" <?php if ($refreshinterval == $i) echo "selected=\"selected\"";?>><?= $i ?></option>
					<?php } ?>
				</select>&nbsp; Seconds<br />&nbsp; &nbsp; &nbsp; <b>Note:</b> changing this setting will increase CPU utilization<br /><br />
				</td>
			</tr>
			<tr>
				<td>
					<input id="submit_settings" name="submit_settings" type="submit" class="formbtn btn btn-primary" value="Save Settings" />
				</td>
			</tr>
		</tbody>
	</table>
</form>
</div>

<script type="text/javascript">
//<![CDATA[
	d = document;
	selectIntLink = "traffic_graphs-configure";
	textlink = d.getElementById(selectIntLink);
	textlink.style.display = "inline";
//]]>
</script>

<?php
foreach ($ifdescrs as $ifname => $ifdescr) {
	$ifinfo = get_interface_info($ifname);
	if ($shown[$ifname]) {
		$mingraphbutton = "inline";
		$showgraphbutton = "none";
		$graphdisplay = "inline";
		$interfacevalue = "show";
	} else {
		$mingraphbutton = "none";
		$showgraphbutton = "inline";
		$graphdisplay = "none";
		$interfacevalue = "hide";
	}
	if ($ifinfo['status'] != "down") { ?>
		<div id="<?=$ifname;?>trafficdiv" style="padding: 5px">
			<div id="<?=$ifname;?>topic" class="widgetsubheader">
				<div style="float:left;width:49%">
					<span onclick="location.href='/status_graph.php?if=<?=$ifname;?>'" style="cursor:pointer">Current <?=$ifdescr;?> Traffic</span>
				</div>
				<div align="right" style="float:right;width:49%">
					<div id="<?=$ifname;?>graphdiv-min" onclick='return trafficminimizeDiv("<?= $ifname ?>", true);'
						style="display:<?php echo $mingraphbutton;?>; cursor:pointer" ><span class="glyphicon glyphicon-minus" alt="Minimize <?=$ifname;?> traffic graph" /></span></div>
					<div id="<?=$ifname;?>graphdiv-open" onclick='return trafficshowDiv("<?= $ifname ?>", true);'
						style="display:<?php echo $showgraphbutton;?>; cursor:pointer" ><span class="glyphicon glyphicon-plus" alt="Show <?=$ifname;?> traffic graph" /></span></div>
				</div>
				<div style="clear:both;"></div>
			</div>
			<div id="<?=$ifname;?>graphdiv" style="display:<?php echo $graphdisplay;?>">
				<object data="graph.php?ifnum=<?=$ifname;?>&amp;ifname=<?=rawurlencode($ifdescr);?>&amp;timeint=<?=$refreshinterval;?>&amp;initdelay=<?=($graphcounter+1) * 2;?>" height="100%" width="100%">
					<param name="id" value="graph" />
					<param name="type" value="image/svg+xml" />
					<param name="pluginspage" value="http://www.adobe.com/svg/viewer/install/auto" />
				</object>
			</div>
		</div>
	<?php }
}
?>
