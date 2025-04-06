#!/usr/bin/php
<?
require_once("/usr/local/emhttp/plugins/dynamix/include/Wrappers.php");

$cfg = parse_plugin_cfg("ca.mover.tuning");
$vars = @parse_ini_file("/var/local/emhttp/var.ini");
$cron = ($argv[1] == "crond");

#Check to see if $cfg has relevant fields in it, "ca.mover.tuning.cfg" may not have existed
if (!isset($cfg['moverDisabled'])) {   #moverDisabled should always have a value of yes or no
    logger("ca.mover.tuning.cfg either does not exist or does not have relevant fields. Please check before running mover.");
    exit();
}

function logger($string)
{
    global $cfg;

    if ($cfg['logging'] == 'yes') {
        exec("logger " . escapeshellarg($string));
    }
}

function startMover($options = "start")
{
    global $vars, $cfg, $cron;

    if ($options == "status") {
        exec("echo 'running status update' >> /var/log/syslog");
        $niceLevel = $cfg['moverNice'] ?: "0";
        $ioLevel = $cfg['moverIO'] ?: "-c 2 -n 0";
        $age_mover_str = "/usr/local/emhttp/plugins/ca.mover.tuning/age_mover status";
        logger("ionice $ioLevel nice -n $niceLevel $age_mover_str");
        passthru("ionice $ioLevel nice -n $niceLevel $age_mover_str");
        exit();
    }

    if ($options != "stop") {
        clearstatcache();
        $pid = @file_get_contents("/var/run/mover.pid");
        if ($pid) {
            logger("Mover already running");
            exit();
        }
    }
    if ($options == "force") {
        $options = "";
        if ($cfg['forceParity'] == "no" && $vars['mdResyncPos']) {
            logger("Parity Check / Rebuild in Progress.  Not running forced move");
            exit();
        }
    }

    if ($options == "stop") {
        $niceLevel = $cfg['moverNice'] ?: "0";
        $ioLevel = $cfg['moverIO'] ?: "-c 2 -n 0";
        // logger("ionice $ioLevel nice -n $niceLevel /usr/local/sbin/mover.old stop");
        // passthru("ionice $ioLevel nice -n $niceLevel /usr/local/sbin/mover.old stop");

        logger("ionice $ioLevel nice -n $niceLevel /usr/local/emhttp/plugins/ca.mover.tuning/age_mover stop");
        passthru("ionice $ioLevel nice -n $niceLevel /usr/local/emhttp/plugins/ca.mover.tuning/age_mover stop");
        exit();
    }

    if ($options == "softstop") {
        $niceLevel = $cfg['moverNice'] ?: "0";
        $ioLevel = $cfg['moverIO'] ?: "-c 2 -n 0";
        logger("ionice $ioLevel nice -n $niceLevel /usr/local/emhttp/plugins/ca.mover.tuning/age_mover softstop");
        passthru("ionice $ioLevel nice -n $niceLevel /usr/local/emhttp/plugins/ca.mover.tuning/age_mover softstop");
        exit();
    }

    // We don't want to run the default mover at all, force execution of new one
    $cfg['movenow'] = "yes";

    if ($cron or $cfg['movenow'] == "yes") {
        //exec("echo 'running from cron or move now question is yes' >> /var/log/syslog");
        $niceLevel = $cfg['moverNice'] ?: "0";
        $ioLevel = $cfg['moverIO'] ?: "-c 2 -n 0";

        if ($cfg['moveThreshold'] >= 0 or $cfg['age'] == "yes" or $cfg['sizef'] == "yes" or $cfg['sparsnessf'] == "yes" or $cfg['filelistf'] == "yes" or $cfg['filetypesf'] == "yes" or $cfg['$beforescript'] != '' or $cfg['$afterscript'] != '' or $cfg['testmode'] == "yes") {
			$age_mover_str = "/usr/local/emhttp/plugins/ca.mover.tuning/age_mover start";
            //exec("echo 'about to hit mover string here: $age_mover_str' >> /var/log/syslog");
            logger("ionice $ioLevel nice -n $niceLevel $age_mover_str");
            passthru("ionice $ioLevel nice -n $niceLevel $age_mover_str");
        }
    } else {
        //exec("echo 'Running from button' >> /var/log/syslog");
        //Default "move now" button has been hit.
        $niceLevel = $cfg['moverNice'] ?: "0";
        $ioLevel = $cfg['moverIO'] ?: "-c 2 -n 0";
        logger("ionice $ioLevel nice -n $niceLevel /usr/local/sbin/mover.old $options");
        passthru("ionice $ioLevel nice -n $niceLevel /usr/local/sbin/mover.old $options");
    }

}

if ($argv[2]) {
    startMover(trim($argv[2]));
    exit();
}


/*if ( ! $cron && $cfg['moveFollows'] != 'follows') {
    logger("Manually starting mover");
    startMover();
    exit();
}
*/

if ($cron && $cfg['moverDisabled'] == 'yes') {
    logger("Mover schedule disabled");
    exit();
}

if ($cfg['parity'] == 'no' && $vars['mdResyncPos']) {
    logger("Parity Check / rebuild in progress.  Not running mover");
    exit();
}



logger("Starting Mover");
startMover();

?>