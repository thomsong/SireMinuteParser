<?php

include_once ('Encoding.php');

function multiexplode ($delimiters,$string) {
    $ready = str_replace($delimiters, $delimiters[0], $string);
    $launch = explode($delimiters[0], $ready);
    return  $launch;
}

function startsWith ($haystack, $needle) {
    return $needle === "" || strpos($haystack, $needle) === 0;
}

function pretty_json($json) {

    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i=0; $i<=$strLen; $i++) {

        // Grab the next character in the string.
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;
        
        // If this character is the end of an element, 
        // output a new line and indent the next line.
        } else if(($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos --;
            for ($j=0; $j<$pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        // Add the character to the result string.
        $result .= $char;

        // If the last character was the beginning of an element, 
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos ++;
            }
            
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        $prevChar = $char;
    }

    return $result;
}

header('Content-Type: application/json');

$sireDataHtml = file_get_contents("data/606.html");
$sireDataHtml = html_entity_decode($sireDataHtml);
$sireDataHtml = strip_tags($sireDataHtml);

$sireDataHtml = str_replace("\n", ' ', $sireDataHtml);


$sireDataMotions = multiexplode(array('Motion made by ', 'The motion to '), $sireDataHtml);

// Remove the first item
array_shift($sireDataMotions);

$minuteMotions = array();

foreach($sireDataMotions as $sireMotion) {
	// More work needs to be done here to remove all invalid characters
	$sireMotion = Encoding::fixUTF8($sireMotion);

	// Replace all of the multi-spaces with one space
	$sireMotion = preg_replace('!\s+!', ' ', $sireMotion);

	$motionPassedPos = strpos($sireMotion, 'Motion Passed');
	$motionFailedPos = strpos($sireMotion, 'Motion Failed');
	$motionStatusPos = max($motionPassedPos, $motionFailedPos);

	$motionText = trim(substr($sireMotion, 0, $motionStatusPos));

	if (startsWith($motionText, 'Councillor')) {
		$motionText = 'Motion made by ' . $motionText;
	} else {
		$motionText = 'The motion to ' . $motionText;
	}
	
	//$motionText = substr($motionText, 0, 125);

	$motionPassed = $motionPassedPos !== FALSE;
	$motionFailed = $motionFailedPos !== FALSE;

	if (!$motionFailed && !$motionPassed) {
		// Not a votable motion.  Normally a follow up motion is related to this one.
		// In a future version, it should possibly merge the 2
		continue;
	}

	if ($motionFailed && $motionPassed) {
		// This should ideally NEVER happen.  This is normally the result of not having
		// All possible splitting values for motions
		continue;
	}

	// It can't be both
	if ($motionFailed) {
		$motionPassed = false;
	}

	$yeas = array();
	$nays = array();

	$yeasTextPos = strpos($sireMotion, 'YEAS:');
	$naysTextPos = strpos($sireMotion, 'NAYS:');

	if ($yeasTextPos !== FALSE) {
		$yeasText = substr($sireMotion, $yeasTextPos + 5);
		$yeasText = substr($yeasText, 0, strpos($yeasText, '('));

		$yeas = array_map('trim', explode(',', $yeasText));
	}

	if ($naysTextPos !== FALSE) {
		$naysText = substr($sireMotion, $naysTextPos + 5);
		$naysText = substr($naysText, 0, strpos($naysText, '('));

		$nays = array_map('trim', explode(',', $naysText));
	}

	$motion = array();

	$motion['motion'] = $motionText;
	$motion['passed'] = $motionPassed;
	$motion['yeas'] = $yeas;
	$motion['nays'] = $nays;
	
	array_push($minuteMotions, $motion);
}

echo( pretty_json(json_encode($minuteMotions)));