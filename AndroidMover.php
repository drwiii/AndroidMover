#!/usr/bin/php -q
<?php
//
// AndroidMover v1.2
// Douglas Winslow <winslowdoug@gmail.com>
// This software crawls and caches a remote handset SDK repository.
//
// Code versions are on GitHub:   https://github.com/drwiii/AndroidMover/
// Help me via PayPal:            https://www.paypal.me/drwinslow
//
// v1.0:
//  Created XML parser unwinder with single repository support.
// v1.1:
//  Supports multiple repositories. Initial public release.
// v1.2:
//  Added source comments to show how the parser works.
//  Added release channels to URL loader: We use the name, not the channel reference id.
//  Provided clarification on why crawling is permitted and why I do what I do.
//

//
// I wrote this to shift a messy stack of XML into a cool and socially acceptable finite state machine.
//
//              \
//           /  .
//            .
//             __/
//  [android]
//

print "AndroidMover v1.2\n";
print "Copyright (C) 2019 Douglas Winslow. All Rights Reserved.\n";
print "\n";

// to do:
//  revision control
//  differential comparison of XML
//  dependency resolution for local installation
//  consider copying remote robots.txt as a local file if it exists and if Android Studio attempts to access it
//  add repository list from https://dl.google.com/android/repository/addons_list-3.xml
//  calculate archive sha1 checksum if exists

error_reporting(0);	// because it's convenient.

// define repository list, seen in Android Studio
// - changed variable name from $repolist; this is because I own things in storage that are at threat of being repossessed.
// - demoted and disabled the "Intel HAXM" repository due to its dubious necessity for a working Android SDK.
$repositories = "
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
true	Offline Repo	file:/opt/android-studio/plugins/sdk-updates/offline-repo/offline-repo.xml
false	Intel HAXM	https://dl.google.com/android/repository/extras/intel/addon2-1.xml
";

define(GR_REPOSITORY_ACTIVE, 0);	// column ID of whether or not the repository line is active
define(GR_REPOSITORY_TITLE, 1);		// column ID of the title of the repository
define(GR_REPOSITORY_URL, 2);		// column ID of the URL that we version in a future update

// parse repository list into array variable
$lines = explode("\n", $repositories);	// split the above list into an array via carriage returns.
foreach ($lines as $line)	// loop: for each line, do this
{
	$repository = explode("\t", $line);

	if ($repository[GR_REPOSITORY_ACTIVE] == "true")
	{
		if (substr($repository[GR_REPOSITORY_URL], 0, 8) == "https://")
		{
			$j++;	// increment array counter
			$repo[$j]['id'] = $j;
			$repo[$j]['title'] = $repository[GR_REPOSITORY_TITLE];
			$repo[$j]['url'] = $repository[GR_REPOSITORY_URL];
			$repo[$j]['local'] = str_replace("https://", "./", $repository[GR_REPOSITORY_URL]);
		}
	}
}
print $j." remote repositories are defined.\n";	// list how many repositories
print "\n";	// newline

// repossess the repository index
foreach ($repo as $a)	// loop: fill $a with the next member of $repo, then do this
{
	// download xml index
	print "\n";
	print "REPOSITORY.ID: ".$a['id']."\n";
	print "REPOSITORY.REMOTE.TITLE: \"".$a['title']."\"\n";
	print "REPOSITORY.REMOTE.NAME: ".$a['url']."\n";

	$b = explode("./", $a['local']);	// parse path into $b
	$c = explode("/", $b[1]);		// parse $b[1] path into $c
	$dp = ".";				// set directory/file pointer as the indicator for CWD (current working directory)
	foreach ($c as $d)	// loop: fill $d with the next member of $c, then do this
	{
		$dp .= "/".$d;				// add pathing to directory/file pointer, then add the current member of $c
		$dp = str_replace("..", "", $dp);	// perform a sanitize check on the string to make sure there are no path escapes

		if (substr($d, -4) == ".xml")	// if the current directory/file pointer ends with .xml, then do this
		{ // * if you nest if() statements, PHP will prefer the inner if() if there's an else, such as here. try to use curly braces with if() when possible.
			if (!file_exists($dp))		// if the file named in $dp doesn't exist, then do this
				file_put_contents($dp, file_get_contents($a['url']));	// get the current URL from remote server and put it into the file at $dp
		}
		else
			mkdir($dp);	// make a directory named what's set in $dp, starting with CWD, if $d is not an .xml filename. (we recursively call this to build the directory structure.)
	}
	unset($dp);	// it is good practice to free unused variables to save resources; this is necessary in this type of design.

	// load local xml file
	print "\n";
	print "REPOSITORY.FILE.SERIAL: #".fileinode($a['local'])."\n";
	print "REPOSITORY.FILE.NAME: ".$a['local']."\n";
	print "REPOSITORY.FILE.MODIFIED: ".date("m/d/Y h:i:s A", filemtime($a['local']))."\n";
	print "REPOSITORY.FILE.SIZE: ".filesize($a['local'])." bytes\n";

	$x = xml_parser_create();					// create an instance of the PHP XML parser
	xml_parse_into_struct($x, file_get_contents($a['local']), $y);	// 	load the local file into parser, stack result into $y
	xml_parser_free($x);						// let the PHP XML parser have a break

/*
	ob_start();			// start output buffering because you might need to learn what this does
	print_r($y);			// 	print the $y array to output
	$z = ob_get_contents();		// 	fill $z with the contents of the output buffer
	ob_end_clean();			// stop output buffering
	print $z; exit;			// see what output buffer has caught for us and exit
*/

	print "\n";	// newline

	// unwind struct stack
	foreach ($y as $z)	// loop: fill $z with the next member of $y, then do this
	{
		// set up $xpath. here we take the PHP XML parser output and iterate over each result.
		// (examine the ob_get_contents above to see the PHP XML parser output.)
		if ($z['type'] == "open" or $z['type'] == "complete")	// if the XML parser is indicating an open tag or a self-completed tag, then do this
		{
			$xpath[$z['level']] = $z['tag'];		//  set $xpath at current nesting level as the tag name.
			$xpath[$z['level']+1] = "*";			//  set $xpath at one above current nesting level as HWM.
		}
		else if ($z['type'] == "close")				// else, if the XML parser is indicated a closed tag, then
			$xpath[$z['level']] = "*";			//  set $xpath at current nesting level as HWM.
		else							// else,
			continue;					//  next the loop.

		// set up $xp (our $xpath pointer), and a wholly owned subsidiary of $xpath
		unset($xp);									// unset previous setting of $xp
		foreach ($xpath as $x) if ($x == "*") break; else $xp .= "/".strtolower($x);	// loop: if not high water marker (HWM), build $xp

		if ($z['type'] == "complete" and $z['attributes']) $A = $z['attributes'];	// set $A (attributes. this is an array)
		if ($z['type'] == "complete" and $z['value'] != "") $V = $z['value'];		// set $V (values. this is a string)

		if (($z['type'] == "complete" or $z['type'] == "close") and ($A or $V))		// on a complete or close XML tag event, parse
		{
			if (substr($xp, -8) == "/license")	// does $xpath end with "/license"?
			{
				// The following action is in compliance with handset
				// application interoperability provisions added to the
				// United States DMCA in 2010.  [drw 10-Nov-2019]
				//
				// As Google is a founding member of a consortium named
				// "Open Handset Alliance", from which originated the
				// Android system software, the aforementioned software
				// and its relevant SDK components are subject to the
				// updated provisions of the DMCA.  It is advised that
				// you obtain an Android handset, operable or not, if
				// this script is run with the intent of copying your
				// SDKs to local storage.
				//
				// Notice: This software does not cause you to agree
				//  to any terms and/or conditions presented by the
				//  Android SDK server, however, it depends on remote
				//  files, noted in the repository setting, which are
				//  not under the control of this author (thus, this
				//  software being useful.)

				// You can edit the following if() statement if you want
				// access to any data that exists in the license field;
				// Some examples: to perform differential comparison,
				// translation, or any other modification that you deem
				// appropriate.

				if ($V!="") $V = TRUE;	// if there is presence of data via this tag, instead note that value has been detected and keep going anyway. this software exists to process data, not people words. (this is how Google justifies crawling and caching your copyrighted website.)
			}

			print $xp;								// print $xp
			if ($A) print " ".str_replace("\n", "",print_r($z['attributes'],1));	//   if attributes, then print the array
			if ($V) print " = ".$V;							//   if value, then print the string
			print "\n";								// newline
		}

		// let's go surfing.
		if ($z['type'] == "open") print $xp." >\n";		// if it's an open tag, indicate heirarchy increase.
		else if ($z['type'] == "close") print $xp." <\n";	// if it's a close tag, indicate heirarchy decrease.

		// prepare variables for the resulting readout (remember, we're calling this for every XML tag event, or every line of parsed XML printed.)
		if (substr($xp, -8) == "/channel") $channel[$A['ID']] = $V;
		if (substr($xp, -11) == "/channelref") $chanref = $A['REF'];
		if (substr($xp, -14) == "/remotepackage" and $z['type'] == "close") unset($chanref);
		if ($xp == "" and $z['type'] == "close") unset($channel);

		if (substr($xp, -5) == "/size")	// if it's a size tag, then do this
		{
			$gt += $V;	// add the number we found as the value to our grand total $gt
			$fsize = $V;
		}
		else if (substr($xp, -4) == "/url")	// if it's a url tag, then do this
		{
			$urls[(int)$u++] = substr($a['url'], 0, strrpos($a['url'], "/") + 1).$V;	// add it to the path url, then add a path separator and the filename we found as the value.
			$remoteurls[$channel[$chanref]][(int)$u++] = substr($a['url'], 0, strrpos($a['url'], "/") + 1).$V;	// bonus: categorize by channel
			$furl = $V;
		}

		if (substr($xp, -8) == "/archive" and $z['type'] == "close")	// if the archive tag is completed, then do this
		{
			if ($fsize > $high)	// this routine seizes high and low sizes for the result
			{
				if (!$low) $low = $high;
				$high = $fsize;
				$highfile = $furl;
			}
			else if ($fsize < $low)
			{
				$low = $fsize;
				$lowfile = $furl;
			}
			unset($fsize);
			unset($furl);
		}

		// unset tag attributes and value to prepare for the next iteration.
		unset($A);
		unset($V);
	}

	print "\n";	// newline
}

//foreach ($urls as $url) print $url."\n";			// loop: print all URLs we found in the XML files. (see variable preparation above)
//foreach ($remoteurls['canary'] as $url) print $url."\n";	// loop: bonus, print only URLs in a certain release channel

// Run a disk space analysis to determine how much local space is necessary to copy all found URLs to separate local files.
// This calculation doesn't consider existing local files or duplicate remote URLs in the array.
print "There are ".count($urls)." remote files defined in the ".$j." repositories.\n";
print number_format(floor($gt/1024),0)." KB is represented on the remote server.\n";
print "\n";

$dt = disk_free_space(".");	// get the amount of free disk space in the CWD for a result.
print number_format(floor($dt/1024),0,".",",")." KB of room is available for use in this directory.\n";
if ($gt > $dt) print number_format(floor(($gt-$dt)/1024),0)." KB is necessary to copy the repositories to this directory.\n\n";

print "SIZE TABLE\n";	// things status:
print "  low size:  ".number_format($low, 0)." bytes in ".$lowfile."\n";	// small
print " high size:  ".number_format($high, 0)." bytes in ".$highfile."\n\n";	// great

print "Learn about legal resources if you are living with autism:\n";
print "   https://www.autism-society.org/living-with-autism/legal-resources/ \n";

print "Help me to offset development costs:\n";
print "   https://www.paypal.me/drwinslow \n";

// wget --input-file=urllist.txt
// for i in `cat urllist.txt`; do wget -c $i; done
exit(0);
?>
