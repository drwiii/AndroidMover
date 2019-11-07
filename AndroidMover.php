#!/usr/bin/php -q
<?php
//
// AndroidMover v1.1
// Douglas Winslow <winslowdoug@gmail.com>
// Download from the remote repository to make a local copy of SDKs
//
// Please support me via PayPal:  https://www.paypal.me/drwinslow
// Code versions are on GitHub:   https://github.com/drwiii/AndroidMover/
//

//
//              \
//           /  .
//            .
//             __/
//  [Android]
//

//
// I wrote this to shift a stack of XML into a finite state machine.
// Many more features will soon become available.  This does not download
// actual SDK files yet; I am working on a sensible way to archive these
// with revision control, dependency resolution, and differential analysis.
//

print "ANDROID MOVER v1.1\n";
print "Copyright (C) 2019 Douglas Winslow. All Rights Reserved.\n\n";

error_reporting(0);

// define repository list, found in android studio
$repolist = "
true	Android Automotive System Images	https://dl.google.com/android/repository/sys-img/android-automotive/sys-img2-1.xml
true	Android Repository	https://dl.google.com/android/repository/repository2-1.xml
true	Android System Images	https://dl.google.com/android/repository/sys-img/android/sys-img2-1.xml
true	Android TV System Images	https://dl.google.com/android/repository/sys-img/android-tv/sys-img2-1.xml
true	Android Wear System Images	https://dl.google.com/android/repository/sys-img/android-wear/sys-img2-1.xml
true	Android Wear for China System Images	https://dl.google.com/android/repository/sys-img/android-wear-cn/sys-img2-1.xml
true	Glass Development Kit, Google Inc.	https://dl.google.com/android/repository/glass/addon2-1.xml
true	Google API add-on System Images	https://dl.google.com/android/repository/sys-img/google_apis/sys-img2-1.xml
true	Google API with Playstore System Images	https://dl.google.com/android/repository/sys-img/google_apis_playstore/sys-img2-1.xml
true	Google Inc.	https://dl.google.com/android/repository/addon2-1.xml
true	Intel HAXM	https://dl.google.com/android/repository/extras/intel/addon2-1.xml
true	Offline Repo	file:/opt/android-studio/plugins/sdk-updates/offline-repo/offline-repo.xml
";

// parse repository list into array variable
$repos = explode("\t", $repolist);
while (TRUE)
{
	$i++;

	if (substr($repos[$i],0,4) == "http")
		$url = strtok($repos[$i],"\n");
	else
		$title = $repos[$i];

	if ($title and $url)
	{
		$j++;
		$repo[$j]['id'] = $j;
		$repo[$j]['title'] = $title;
		$repo[$j]['url'] = $url;
		$repo[$j]['local'] = str_replace("https://", "./", $url);
		unset($title);
		unset($url);
	}
	if (!isset($repos[$i])) break;
}
print $j." remote repositories are defined.\n";
print "\n";

// repossess the repository index
foreach ($repo as $a)
{
// download xml index
	print "\n";
	print "REPOSITORY.ID: ".$a['id']."\n";
	print "REPOSITORY.REMOTE.TITLE: \"".$a['title']."\"\n";
	print "REPOSITORY.REMOTE.NAME: ".$a['url']."\n";

	$b = explode("./", $a['local']);
	$c = explode("/", $b[1]);
	$dp = ".";
	foreach ($c as $d)
	{
		$dp .= "/".$d;
		if (substr($d, -4) == ".xml")
		{
			if (!file_exists($dp)) file_put_contents($dp, file_get_contents($a['url']));
		}
		else
			mkdir($dp);
	}
	unset($dp);

// load downloaded xml file
	print "\n";
	print "REPOSITORY.FILE.SERIAL: #".fileinode($a['local'])."\n";
	print "REPOSITORY.FILE.NAME: ".$a['local']."\n";
	print "REPOSITORY.FILE.MODIFIED: ".date("m/d/Y h:i:s A", filemtime($a['local']))."\n";
	print "REPOSITORY.FILE.SIZE: ".filesize($a['local'])." bytes\n";

	$x = xml_parser_create();
//	xml_parse($x, file_get_contents($a['local']));
	xml_parse_into_struct($x, file_get_contents($a['local']), $y);
	xml_parser_free($x);
	ob_start();
	print_r($y);
	$z = ob_get_contents();
	ob_end_clean();

	print "\n";

// unwind struct stack (let's go surfing)
	foreach ($y as $z)
	{
		if ($z['type'] == "open") {$xpath[$z['level']] = $z['tag']; $xpath[$z['level']+1] = "*";}
		else if ($z['type'] == "complete") {$xpath[$z['level']] = $z['tag']; $xpath[$z['level']+1] = "*";}
		else if ($z['type'] == "close") $xpath[$z['level']] = "*";
		else continue;

		unset($xp);
		foreach ($xpath as $x) if ($x == "*") break; else $xp .= "/".strtolower($x);

		if ($z['type'] == "complete" and $z['attributes']) $A = $z['attributes'];
		if ($z['type'] == "complete" and $z['value'] != "") $V = $z['value'];

		if (($z['type'] == "complete" or $z['type'] == "close") and ($A or $V))
		{
			if (substr($xp, -8) == "/license") $V = TRUE;	// this is what google does

			print $xp;
			if ($A) print " ".str_replace("\n", "",print_r($z['attributes'],1));
			if ($V) print " = ".$V;
			print "\n";
		}

		if ($z['type'] == "open") print $xp." >\n";
		else if ($z['type'] == "close") print $xp." <\n";

		if (substr($xp, -4) == "/url") $urls[(int)$u++] = substr($a['url'], 0, strrpos($a['url'], "/") + 1).$V;
		if (substr($xp, -5) == "/size") $gt += $V;

		unset($A);
		unset($V);
	}

	print "\n";
}

//foreach ($urls as $url) print $url."\n";

print "There are ".count($urls)." remote files defined in the ".$j." repositories.\n";
print number_format(floor($gt/1024),0)." KB is represented on the remote server.\n";
print "\n";

$dt = disk_free_space(".");
print number_format(floor($dt/1024),0,".",",")." KB of room is available for use in this directory.\n";
if ($gt > $dt)
{
	print number_format(floor(($gt-$dt)/1024),0)." KB is necessary to copy the repositories to this directory.\n";
	print "\n";
}

?>
